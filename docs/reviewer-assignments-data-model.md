# Reviewer assignments — data model and gotchas

This documents how peer-review assignments actually work in the database, based on
building the `/reviewersstats` (list, per-reviewer detail page, search suggestions) and
`/myreviewstats` reviewer-statistics dashboards
(`library/Episciences/Reviewer/StatsQuery.php`,
`application/modules/journal/controllers/ReviewersstatsController.php`). It focuses on
the parts that are *not* obvious from the table/column names alone, because several of
them turned out to be misleading when read naively.

## The four tables involved

| Table (constant) | Class | Key columns | Notes |
|---|---|---|---|
| `T_ASSIGNMENTS` (`user_assignment`) | `Episciences_User_Assignment` | `ID`, `INVITATION_ID`, `ITEMID`, `ITEM`, `RVID`, `UID`, `FROM_UID`, `TMP_USER`, `ROLEID`, `STATUS`, `WHEN`, `DEADLINE` | One row per "someone is assigned to something" — not reviewer-specific: `ITEM` can be `paper`, `section` or `volume`, and `ROLEID` can be `reviewer`, `editor`, `copyeditor`. |
| `T_USER_INVITATIONS` (`user_invitation`) | `Episciences_User_Invitation` | `ID`, `AID` (→ `USER_ASSIGNMENT.ID`), `STATUS`, `SENDING_DATE`, `EXPIRATION_DATE`, `SENDER_UID` | Not every assignment has an invitation (an editor can be assigned directly). `save()` always inserts a new row — invitations are never updated in place: the rows of one `AID` form a **state log** (the invitation sent, then one row per state change), so **a single `AID` routinely has more than one row** (most assignments). See below. |
| `T_USER_INVITATION_ANSWER` | `Episciences_User_InvitationAnswer` | `ID_UIA`, `ID` (= `USER_INVITATION.ID`, unique), `ANSWER` (`yes`/`no`), `ANSWER_DATE` | Attached to the invitation row that was **answered** — the original `'pending'` one, *not* the later `accepted`/`declined` row. See below. |
| `T_REVIEWER_REPORTS` (`rating_report` / `REVIEWER_REPORT`) | `Episciences_Rating_Report` | `ID`, `UID`, `DOCID`, `STATUS` (0 pending / 1 in progress / 2 completed), `UPDATE_DATE` | **Keyed by `(UID, DOCID)` only — no reference to a specific `USER_ASSIGNMENT` row.** See below. |

Relevant for review-specific queries: `USER_ASSIGNMENT.ROLEID = Episciences_Acl::ROLE_REVIEWER`
and `ITEM = Episciences_User_Assignment::ITEM_PAPER`.

## Who is the reviewer: `UID` + `TMP_USER`

A reviewer is identified by `(UID, TMP_USER)`, not `UID` alone:

- `TMP_USER = 0` → `UID` points to `USERS` (a real, registered account).
- `TMP_USER = 1` → `UID` points to `USER_TMP` (an external/unregistered reviewer, e.g.
  invited by email before ever creating an account).

**The same real person can end up split across several identities:**
- An external reviewer invited to several papers before registering gets a **new**
  `USER_TMP` row every time — same email, different `UID`s, all `TMP_USER = 1`.
- A reviewer invited while unregistered (`TMP_USER = 1`) who later creates a real
  account (`TMP_USER = 0`) ends up as two entirely different identities for the same
  email, one per registration state.

Any aggregation across a reviewer's history must group by lowercased **email**
(`COALESCE(u.EMAIL, ut.EMAIL)`) when one is known, falling back to `(UID, TMP_USER)`
only when there is truly no email on record. This is what
`StatsQuery::buildCoreQuery()`'s `identity_key` does.

## Status fields are not maintained — the paper's own lifecycle is authoritative

`USER_ASSIGNMENT.STATUS` (`pending|active|inactive|expired|cancelled|declined`) and
`USER_INVITATION.STATUS` (`pending|accepted|declined|cancelled`) are set **once**, when
the row is created or answered, and are **never revisited** when the underlying paper
moves past the point where the review even matters anymore (published, refused, a new
version requested, copy-editing started, ...). There is no batch job or trigger that
goes back and closes out old rows.

In practice, this leaves a large number of `USER_ASSIGNMENT` rows stuck on
`STATUS = 'pending'` for years, many of them for papers whose `REVIEWER_REPORT` was
completed long ago. A reviewer whose accurate dashboard breakdown is mostly completed
reviews and obsolete invitations can then show a dozen "pending invitations" on a naive
`COUNT(... WHERE STATUS = 'pending')`, because most of those invitations were for papers
that had since moved on.

**The authoritative status logic** lives in
`Episciences_Reviewer_Reviewing::loadStatus()` (`library/Episciences/Reviewer/Reviewing.php`):

```php
if (hasRating()) {
    // a REVIEWER_REPORT exists
    if (!report.isCompleted() && !paper.canBeReviewed()) → STATUS_NOT_NEED_REVIEWING
    elseif report.isCompleted()                          → STATUS_COMPLETE
    elseif report.isInProgress()                          → STATUS_WIP
    elseif report.isPending()                              → STATUS_PENDING
} else {
    // no report yet, only an assignment/invitation
    status = (assignment.STATUS === STATUS_DECLINED) ? STATUS_DECLINED : STATUS_UNANSWERED
    result = paper.canBeReviewed() ? status : STATUS_OBSOLETE
}
```

So an unanswered invitation or a not-yet-started report is only genuinely
"pending"/"unanswered" if the **paper itself** can still be reviewed. Otherwise it's
`STATUS_OBSOLETE` (assignment/invitation side) or `STATUS_NOT_NEED_REVIEWING` (a report
had been started but the paper moved on before it was finished) — regardless of what the
raw `STATUS` column says.

`Episciences_Paper::canBeReviewed()` is:

```php
isEditable()                    // status not in $_noEditableStatus:
                                 //   published, refused, removed, deleted, obsolete, abandoned
&& !isAccepted()                 // status !== STATUS_ACCEPTED
&& !isRevisionRequested()        // status not in STATUS_WITH_EXPECTED_REVISION (~7 codes)
&& !isCopyEditingProcessStarted()
&& !isReadyToPublish()
```

— roughly two dozen distinct `Episciences_Paper::STATUS_*` codes combined. Any SQL
aggregate that wants the same semantics has to build the *same* exclusion list from
those *same* constants, not guess numeric literals — see
`StatsQuery::nonReviewablePaperStatuses()`, which merges `$_noEditableStatus`,
`STATUS_ACCEPTED`, `STATUS_WITH_EXPECTED_REVISION` and the copy-editing/ready-to-publish
codes.

**Rule of thumb:** never treat `USER_ASSIGNMENT.STATUS IN ('active','pending')` or
`USER_INVITATION.STATUS = 'pending'` alone as "still actionable" in a batch/SQL context.
Always join `PAPERS` on the item id and gate on the paper's own status.

## `REVIEWER_REPORT` has no link to a specific assignment attempt

A single reviewer can have **several `USER_ASSIGNMENT` rows for the exact same paper**:
every re-invitation or reassignment cycle inserts a brand new row instead of updating
the old one (same root cause as above — nothing ever closes out a superseded row), and a
*later*, unrelated event can also auto-create one (e.g. requesting a new version
auto-unassigns every active reviewer, which inserts one more `USER_ASSIGNMENT` row with
no `INVITATION_ID` at all).

`REVIEWER_REPORT` only carries `(UID, DOCID)` — it does **not** say which assignment
attempt it belongs to. Any query that `LEFT JOIN`s `REVIEWER_REPORT` on `(UID, DOCID)`
therefore fans the *same single* report out across **every** `USER_ASSIGNMENT` row for
that reviewer/paper.

Typical case: one `REVIEWER_REPORT` row, three `USER_ASSIGNMENT` rows for the same
reviewer and paper.

1. Naive per-row listing showed "completed review" on all three attempts.
2. First fix attempt — "only the *most recent* attempt owns the report" — was still
   wrong: the report was completed while the first assignment was the active one, but a
   *later* event (a new paper version requested) auto-unassigned the reviewer, creating
   the most-recent-by-date row — with no invitation at all. "Most recent" attached the completed report to that
   unassignment row instead of the attempt that was actually active when the report was
   submitted.

**Correct rule:** the owning attempt is the one with the latest `assignment_date`
(`USER_ASSIGNMENT.WHEN`) that is still `<=` the report's own `UPDATE_DATE` — i.e. the
attempt that was current *at the time the report was completed*, not the latest attempt
overall. Falls back to "latest attempt" only when the report isn't completed yet (no
completion date to compare against). Every other attempt for that `(UID, DOCID)` must
have its report fields nulled out before computing its display status. Implemented in
`ReviewersstatsController::enrichAndGroupRows()`.

This is a two-step derivation and easy to get half-right — if you're about to attach a
`REVIEWER_REPORT` row to a specific assignment anywhere else in the codebase, reuse this
logic rather than re-deriving it.

## `USER_INVITATION` also fans out — one assignment, several invitation rows

`Episciences_User_Invitation::save()` always `INSERT`s a new row; it never updates one in
place. Answering (accept/decline) or cancelling an invitation therefore inserts a new row
under the same `AID`, dated at the time of that transition, and leaves the original row
behind, still `'pending'` forever — the same staleness pattern as `USER_ASSIGNMENT.STATUS`
above. The rows of one `AID` are really a state log:

- every `AID` has **exactly one** `'pending'` row: the invitation actually sent (a new
  invitation for the same paper version creates a new `USER_ASSIGNMENT` row, not a second
  `'pending'` row under the same `AID`);
- every `accepted`/`declined`/`cancelled` row is dated **after** that `'pending'` row, and
  carries the same `SENDER_UID` (with very rare exceptions);
- **most assignments have more than one `USER_INVITATION` row sharing the same `AID`.**
  This is the normal case, not an edge case.

A naive `LEFT JOIN USER_INVITATION ui ON ua.ID = ui.AID` therefore fans a *single*
assignment out into one row per state. For instance, an assignment with two rows under
the same `AID` — the invitation sent (still `'pending'`) and the acceptance recorded two
days later (`'accepted'`). Joining both produced two rows for
what is really one single assignment attempt, both displayed as "obsolete review
invitation" on the per-reviewer drill-down — reading as two independent obsolete
invitations when there was only one.

`Episciences_PapersManager::getLatestInvitationByDocIdQuery()` (used by
`administratepaper/view`) already solves this correctly: it joins only the invitation
with `MAX(SENDING_DATE)` per `AID`. `StatsQuery::latestInvitationPerAssignmentSql()`
mirrors the same intent so `getReviewersGlobalStats()` and `getReviewerInvitationDetails()`
don't double-count or duplicate-display a superseded invitation — but **not the same SQL
shape**, for a performance reason below. `getLatestInvitationByDocIdQuery()` gets away
with the self-join form because it's always scoped to one `DOCID` first; `StatsQuery`
runs over an entire journal's assignments, where the self-join form is catastrophic.

### The latest row carries the state — not the answer, nor the sending date

Keeping only the latest row per `AID` (below) gives the right **current state**
(`ui.STATUS = 'pending'` really means "still unanswered"), but two other facts live on
*other* rows of the same `AID`:

- **The answer.** `USER_INVITATION_ANSWER.ID` points to the row that was answered, i.e.
  the original `'pending'` row, while the latest row is the `accepted`/`declined` one.
  **Almost every answer sits on an older row than the latest one.** Joining answers on the
  latest row's `ID` silently lost almost all of them: response and acceptance rates at 0 % for nearly every reviewer, average review
  time (answer date → report date) empty, and sorting on those columns a no-op since every
  row tied. `StatsQuery::latestAnswerPerAssignmentSql()` joins answers **per assignment**
  instead (`uia.AID = ua.ID`, latest answer per `AID`; a handful of `AID`s have several).
- **The sending date.** The latest row's `SENDING_DATE` is the time of the state change
  (e.g. the acceptance), not when the invitation was sent. The real sending date is the
  earliest row of the `AID`: `latestInvitationPerAssignmentSql()` exposes it as
  `FIRST_SENDING_DATE` (`MIN(SENDING_DATE) OVER (PARTITION BY AID)`), used for "sent on",
  "last invitation", the response delay and the rolling-period filter.

### Trap: `USER_INVITATION` had no index on `AID` — a self-join dedup can be dramatically slower than it looks
At the time, `USER_INVITATION`'s indexes were only `PRIMARY (ID)`, `TOKEN`, `STATUS`,
`SENDER_UID` — no `AID` (an index has since been added, see the end of this section).
The obvious way to write "keep only the invitation with `MAX(SENDING_DATE)` per
`AID`" is a self-join:
```sql
SELECT ui1.* FROM USER_INVITATION ui1
INNER JOIN (SELECT AID, MAX(SENDING_DATE) max_date FROM USER_INVITATION GROUP BY AID) ui2
  ON ui1.AID = ui2.AID AND ui1.SENDING_DATE = ui2.max_date
```
Used as a derived table in a larger query (`LEFT JOIN (...) ui ON ua.ID = ui.AID`), this
is eligible for MySQL's **derived-table merge** optimization: MySQL flattens the derived
table's joins back into the outer query instead of materializing it once: `EXPLAIN` shows
the *inner* `ui1` full scan (`type=ALL`, the whole table) run once per outer assignment row
instead of once total — on a large journal, `getReviewersGlobalStats()` went from a normal query to
several minutes, causing a gateway timeout on `/reviewersstats`.

Fix: a window function is *not* eligible for derived-table merge (MySQL cannot push outer
predicates through `ROW_NUMBER()`), so it forces one-time materialization regardless of
the missing index:
```sql
SELECT ui1.* FROM (
    SELECT ui2.*, ROW_NUMBER() OVER (PARTITION BY AID ORDER BY SENDING_DATE DESC, ID DESC) AS rn
    FROM USER_INVITATION ui2
) ui1 WHERE ui1.rn = 1
```
Back to well under a second on the same data, confirmed via `EXPLAIN` (MySQL then
auto-indexes the materialized derived table on `AID` for the outer join).

**Takeaway:** when deduplicating a "latest row per group" pattern as a derived table that
will be `LEFT JOIN`ed into a larger, non-single-row-scoped query, prefer a window function
over a self-join — and always re-run `EXPLAIN` (and re-time the query against realistic data
volumes) after changing a join shape, even when the change is "just" adding a dedup
subquery to something that already worked.

Added `USER_INVITATION(AID, SENDING_DATE)` as a proper index
(`src/mysql/2026-07-12-add-user-invitation-aid-index.sql`) — there was no index on `AID`
at all before, and no FK constraint either (confirmed via `information_schema`, purely a
logical relationship). This is a smaller win for the window-function query above (MySQL's
window-function planner doesn't fully exploit it, still shows `Using filesort` computing
`ROW_NUMBER()`), but it fixes `Episciences_PapersManager::getLatestInvitationByDocIdQuery()`
(used by `administratepaper/view`, every paper's admin page) from a full table scan to a
full covering-index scan (`Extra: Using index`, no filesort) — worth keeping regardless of
which query shape ends up needing it.

## Paper versioning: several `DOCID`s can be "the same paper"

A paper's identity across revisions is `PAPERS.CONCEPT_IDENTIFIER` (shared by every
version); `PAPERS.VERSION` (float) and `PAPERS.DOCID` (unique, one per version) vary.
`USER_ASSIGNMENT.ITEMID` points at a specific `DOCID`, i.e. a specific *version*, not at
the paper concept.

Consequence: a reviewer's history for "one paper" can legitimately span several
`DOCID`s (one per version they were asked to review) *on top of* the same-`DOCID`
re-invitation duplicates described above. A UI listing raw rows without grouping by
`CONCEPT_IDENTIFIER` first will show the same title repeated with no explanation. The
detail view groups by `CONCEPT_IDENTIFIER`, orders versions newest-first, and within a
version flags every attempt except the chronologically latest as
`is_superseded` (purely based on `WHEN`, independent of which one owns the report).

## `PAPER_LOG` as ground truth when the data model itself is ambiguous

`PAPER_LOG` (`T_LOGS`) is an append-only audit trail (`ACTION` values such as
`reviewing_completed`, `reviewer_unassignment`, `revision_request_new_version`,
`reminder_sent`, `mail_sent`, ...). Unlike the status columns above, it is never mutated
after the fact, which makes it the reliable way to reconstruct what actually happened in
what order when the assignment/invitation/report data looks contradictory — this is how
the `REVIEWER_REPORT` fan-out bug above was diagnosed (querying `PAPER_LOG` around the
report's completion timestamp and the assignment's `WHEN` timestamps for both
`reviewing_completed` and `reviewer_unassignment`/`revision_request_new_version`
entries). It's also the correct source for "was a reminder actually sent" — see
`StatsQuery::getReminderCounts()`, which reads `ACTION = 'reminder_sent'` and parses the
recipient out of `DETAIL.mail.To`, instead of pattern-matching `MAIL_LOG.SUBJECT` (an
older, less reliable approach that both this feature and `PAPER_LOG` itself have since
superseded).

## "Obsolete" is paper-status-driven — misleading once a later attempt succeeded

`STATUS_OBSOLETE`/`STATUS_UNANSWERED` are derived from the paper's *current*
`canBeReviewed()` (see above): a never-answered invitation on a paper that's moved past
active review is labelled "obsolete", regardless of *why* it moved past review. That's
fine for `Episciences_Reviewer_Reviewing::loadStatus()`'s original single-reviewer,
single-status use — but the detail drill-down shows every historical attempt for a
reviewer/paper side by side, including superseded ones. Typical case: a first invitation is never individually
answered and is superseded a few days later by a second invitation that is accepted and
completed. The paper's current status is
`STATUS_WAITING_FOR_MAJOR_REVISION` — review concluded *successfully*, `canBeReviewed()`
is false simply because the process moved to the next phase, not because anything went
stale. Showing "obsolete review invitation" on the first attempt right next to "completed
review" on the second reads as a contradiction.

Fix: `ReviewersstatsController::STATUS_SUPERSEDED_REPLACED` (a display-only pseudo status,
not one of `Episciences_Reviewer_Reviewing`'s real codes) — applied in
`enrichAndGroupRows()` whenever a row is `is_superseded` **and** its raw computed status
would be `STATUS_OBSOLETE`/`STATUS_UNANSWERED` (i.e. it was never individually resolved).
A superseded row that *was* individually resolved (`STATUS_DECLINED`, or `STATUS_COMPLETE`
if it happens to be the report owner) keeps that real status — those are facts about that
specific attempt, not a paper-status artifact.

## Visibility rules of the dashboards

Applied identically by the list, its count, the per-reviewer detail page and the search
suggestions (`StatsQuery::buildCoreQuery()` / `getReviewerInvitationDetails()`), so none of
them can ever show a reviewer or an invitation the others would hide:

- **Rolling period (GDPR).** The managers' views are capped at 24 months:
  `COALESCE(ui.FIRST_SENDING_DATE, ua.WHEN) >= NOW() - INTERVAL n MONTH`, `n` ≤ 24 whatever
  the request says. `USER_ASSIGNMENT.STATUS` is deliberately *not* used to exempt "still
  open" rows (see the staleness section). A reviewer's own `/myreviewstats` has no cap.
- **Restricted view.** Guest editors, editors when the journal setting
  `encapsulateEditors` is on, and any manager who ticks "only the papers I am responsible
  for" only see reviewers they invited (`ui.SENDER_UID`), assigned (`ua.FROM_UID`), or
  assigned to a paper they manage (a `USER_ASSIGNMENT` row of theirs on the same `ITEMID`
  with role `editor`, `guest_editor` or `chief_editor`) —
  `StatsQuery::restrictionClauseSql()`.
- **Paper visibility.** Whatever their role, managers never see the reviewers of papers
  they submitted (`PAPERS.UID`). When the journal has conflicts of interest enabled
  (`isCoiEnabled`), editorial staff only see papers they answered "no conflict" for in
  `paper_conflicts`, and administrators without an editorial role (who cannot declare a
  conflict) see every paper except those recorded with a conflict; root is exempt —
  `StatsQuery::appendPaperVisibility()` and `Episciences\Reviewer\StatsCoiFilter`. The
  detail page's profile card is only resolved from these visible rows, never from the URL.
- **Personal view.** `/myreviewstats` is limited to the logged-in user's registered
  identity (`ua.UID = :uid AND ua.TMP_USER = 0`) in the current journal.

## Practical checklist for new code touching assignments

- Group/identify reviewers by email first, `(UID, TMP_USER)` only as a fallback.
- Never trust `USER_ASSIGNMENT.STATUS` / `USER_INVITATION.STATUS` alone as "still open" —
  gate on `Episciences_Paper::canBeReviewed()` (or the paper-status exclusion list built
  from the same constants) via a join on `PAPERS`.
- Don't join `USER_INVITATION` on `AID` without deduplicating to the latest `SENDING_DATE`
  per `AID` first — most assignments have more than one invitation row.
- Take the invitation's **state** from that latest row, but its **sending date** from the
  earliest row of the `AID`, and its **answer** per `AID` (`USER_INVITATION_ANSWER` points
  to the original `'pending'` row, not to the latest one).
- Don't join `REVIEWER_REPORT` on `(UID, DOCID)` and assume the result belongs to
  whichever `USER_ASSIGNMENT` row you happened to be looking at — resolve ownership by
  the "latest attempt at-or-before the report's completion date" rule first.
- Group by `PAPERS.CONCEPT_IDENTIFIER`, not `DOCID`, when presenting "this reviewer's
  history for a paper" to a human.
- When the derived data still looks wrong, check `PAPER_LOG` for the real chronological
  narrative before assuming another layer of the aggregation logic is at fault.
