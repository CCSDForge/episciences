# Journal Provisioning

Creating a new Episciences journal used to be an entirely manual, untracked procedure: a
hand-written SQL `INSERT` into `REVIEW`, a `cp -rp src/data/default data/<rvcode>`, an Apache
vhost, and page-by-page back-office data entry. `journal:create` (see
[`docs/console-commands.md`](console-commands.md#journalcreate)) replaces the database and
data-directory half of that with one auditable command run.

## What `journal:create` does

In order:

1. Refuses if the requested code already exists, unless `--complete` is given.
2. Inserts the `REVIEW` row. Nothing else in the codebase does this —
   `Episciences_Review::save()` only ever writes `REVIEW_SETTING` rows.
3. Clones `REVIEW_SETTING` from the template journal, excluding editorial identity and
   outgoing-notification settings (see below).
4. Creates the `data/<rvcode>/` directory tree (`config/`, `files/`, `languages/`, `layout/`,
   `public/`, `tmp/`), and copies the template's `config/` and `languages/` content across.
5. Clones the menu (`WEBSITE_NAVIGATION` rows and `languages/<lang>/menu.php` labels), the
   interface languages setting, and the appearance (`WEBSITE_STYLES`, `WEBSITE_HEADER` plus its
   logo image files).
6. Clones custom CMS pages (the `pages` table and their rendered HTML files).
7. Grants the given `--admin-uid` the administrator role on the new journal.

Everything from step 2 onward runs inside one database transaction; a failure rolls the
transaction back and removes any directory or file the run itself created (never anything that
pre-existed). `--dry-run` shows the same plan without writing anything.

## What it does NOT do

- **Apache vhost** — add a `<VirtualHost>` block (see `src/apache/vhost.conf` for the pattern):
  `ServerName <code>.episciences.org`, `SetEnv RVCODE <code>`, and the usual aliases.
- **DNS entry** — point `<code>.episciences.org` at the server.
- **Matomo site** — if you want traffic stats, create a site in Matomo and pass its id via
  `--piwikid` (or update `WEBSITE_SETTINGS`/`REVIEW.PIWIKID` afterward). No Matomo API call is
  ever made by this command.
- **Enabling the journal** — a fresh journal is created with `STATUS = 0` (disabled) by design,
  so an empty site is never exposed. Enable it once it is ready, via `--status=1` re-run with
  `--complete`, the back office, or a direct `UPDATE REVIEW SET STATUS = 1 WHERE CODE = ...`.
- **Editorial identity** — ISSN, contact emails, description, keywords, publisher: deliberately
  never cloned (see below). Fill these in through the back office.
- **Mail templates** — nothing to initialize: `Episciences_Mail_TemplatesManager::getList()`
  automatically falls back to the generic templates (`RVID IS NULL`) for a journal that has none
  of its own.

## What is cloned, and what is deliberately excluded

| Cloned | Excluded |
|---|---|
| `REVIEW_SETTING` (all but the list below) | Editorial identity: `ISSN`, `ISSN_PRINT`, `domains`, `journalAssignedDoi`, `description`, `journalDescription`, `journalKeywords`, `journalCreationYear`, `journalPublisher`, `journalPublisherLoc`, `startStatsAfterDate`, `specialIssueAccessCode` |
| Menu (`WEBSITE_NAVIGATION`) + labels (`menu.php`) | Outgoing notifications: `contactJournal`, `contactJournalEmail`, `contactJournalNotice`, `contactTechSupportEmail`, `contactErrorMail`, `systemNotifications`, `systemCanNotifyChiefEditors`, `systemCanNotifyAdministrator`, `systemCanNotifySecretaries` |
| Interface languages (`WEBSITE_SETTINGS.languages`) | Secrets: the journal's crypto key file (`<rvcode>-crypto.json`), `config/pwd.json`, and any file whose name matches `*.key`, `*.pem`, `*secret*` or `*token*` |
| Appearance (`WEBSITE_STYLES`, `WEBSITE_HEADER` + its logo images) | `files/` and `public/` are **not** copied wholesale: on a real journal these hold per-paper submission storage and per-volume generated exports (DOAJ XML, merged PDFs), not reusable template assets. Only the specific logo files referenced by the cloned header row are copied. |
| Custom pages (`pages` table + rendered HTML files) | `PIWIKID` (`--piwikid`, default `0`) |
| DOI settings (`doiPrefix`, `doiFormat`, `doiRegistrationAgency`, `doiAssignMode`) — see warning below | |

### DOI settings warning

DOI settings are cloned by default (so a customized DOI format survives the clone), but this
means a sandbox created from a real journal inherits that journal's real DOI prefix. If the
cloned `doiAssignMode` is `automatic`, the command prints a warning: running `doi:manage` on the
new journal would then register real DOIs under the template's prefix. Pass `--reset-doi` to
force `doiAssignMode = manual` and clear the prefix instead — `journal:create` and
`journal:seed-demo` never call `doi:manage` themselves, but a later manual run would.

## `--complete`: finishing a partially-provisioned journal

If a run is interrupted, or you need to add what `journal:create` provides to a journal created
some other way, re-run the same command with `--complete`. Every step becomes additive:

- The `REVIEW` row is reused rather than recreated.
- `REVIEW_SETTING` rows are added with `INSERT IGNORE` — an existing key is never overwritten.
- The data directory only gets the files/directories that are still missing.
- The menu/languages/appearance clone is skipped entirely if the journal already has any
  `WEBSITE_NAVIGATION` rows (it has no way to safely merge into an existing menu).
- Pages are only added for `page_code` values the target doesn't already have.
- The admin role grant is additive — it never removes a role the user already has.

## Building a sandbox

```bash
php scripts/console.php journal:create \
  --code=sandbox1 --name='Sandbox Journal' --template-rvcode=dmtcs \
  --admin-uid=1 --reset-doi --no-interaction

php scripts/console.php journal:seed-demo --rvcode=sandbox1 --uid=1
```

`journal:seed-demo` (see [`docs/console-commands.md`](console-commands.md#journalseed-demo))
injects a small set of real, fixed submissions (submitted/accepted/published) across a few
volumes and sections, for manually testing a journal end-to-end without touching production
data.

## `journal:seed-demo` dataset

[`scripts/importSamples/demo-papers.csv`](../scripts/importSamples/demo-papers.csv) uses the
same CSV format `import:papers` reads, with `uid` and `rvid` left blank — `journal:seed-demo`
fills those in from `--uid`/`--rvcode` at run time (`Episciences\Journal\Demo\DemoCsvRewriter`),
so the same versioned file can be seeded into any journal. It has 6 rows: 2 arXiv, 2 HAL, 1
Zenodo, 1 BAOBAB, spread across 3 volumes (one flagged as the current issue, one as a special
issue — a post-import step, since volume creation on the fly always defaults both flags to off)
and 3 sections, in status submitted (`0`), accepted (`4`) and published (`16`) only.

Seeding fetches each identifier's real metadata over the network from its repository (HAL,
arXiv, Zenodo, BAOBAB), exactly like `import:papers` does — a network failure or an unreachable
repository fails that one row without aborting the rest (per-row `try/catch`, reported as a
warning; the command exits non-zero if any row failed).

**Known limitation**: a repository record whose metadata contains a 4-byte UTF-8 character
(e.g. certain math symbols) fails to insert with a `PAPERS.RECORD` MySQL error, because that
column is still `utf8mb3` — a pre-existing, already-tracked limitation of this database (see the
planned `utf8mb4` migration), not specific to this command. Any `import:papers` run can hit the
same error on such a record.

Re-running `journal:seed-demo` is safe: papers are matched by identifier (updated, not
duplicated), volumes/sections are matched by title, and a volume already flagged as the current
or special issue is left alone.
