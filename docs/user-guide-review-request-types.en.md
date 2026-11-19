# Review Request Types - User Guide

*[English](user-guide-review-request-types.en.md) · [Français](user-guide-review-request-types.fr.md)*

## Overview

When you invite a reviewer, you now choose the **type of request**: a full review report, a quick opinion, or a type
created by your journal (for example a revised version check). The type sets:

- the invitation e-mail sent to the reviewer;
- the suggested due date;
- the rating grid used for the report (if your journal created one for this type);
- whether the reviewer can answer **without an account**, directly from the invitation link.

A reviewer can receive one request of each type for the same article version, for example a quick opinion first, then
a full report.

## Who Can Manage the Types?

The types are managed in the **Request types** section of the **Review: grids and request types** page (journal
settings). The actions on the types are available to:
- **Administrators**
- **Chief Editors**
- **Secretaries**

## Default Types

Every journal starts with two types:

| Type | Default due date | Use |
|---|---|---|
| Review report | journal setting | detailed review with the rating grid (the usual review) |
| Quick opinion | 2 weeks | a few lines on the interest and relevance of the article |

## Managing the Types

In the **Request types** section, each type is a block showing its default due date, its state and the grid it uses:

1. Click **Add a request type**, or **Edit this request type** in the block of a type.
2. Fill in:
   - **Label** in each language of the journal;
   - **Default deadline** (leave empty to use the journal setting);
   - **Position** in the list;
   - **Offer this type when inviting a reviewer** (uncheck to hide it);
   - **Count its reports as review reports** (see "Reports");
   - **Answer without an account, from the invitation link** (see below), and its **Instructions** in each language
     (shown only while the option is checked; they are kept when it is unchecked).
3. Click **Save**.

The two default types are always available: they cannot be deleted, only disabled, so that each journal chooses the
types it offers. A type created by your journal can be deleted as long as it has not been used; afterwards, disable it.
The review report type is always offered and always requires an account.

For other needs (revised version check, review stages…), create your own types, each with its grid if needed.

## Inviting a Reviewer

1. Open **Invite a reviewer** from the paper's administration page and choose the reviewer as usual.
2. In the invitation e-mail step, choose the **Request type**.
3. The message and the due date change according to the type. You can still edit the message before sending it.

The types that the reviewer already has in progress for this article are greyed out.

## Asking for a Revision: Type of the Next Version

In the revision request form (minor or major), the **Type of the new request to the reviewers** list sets what the
reviewers will be asked on the version the author is going to submit (for instance a quick opinion after a first
report).

- **Same type as the current request** (default): nothing changes. When **Automatically reassign the same reviewers when the new version of the article is submitted** is
  checked, each reviewer is invited again with the type of each of their requests, and the journal's deadline.
- **A chosen type**: when the reassignment box is checked, each reviewer receives **one** invitation of this type on the
  new version (or the temporary version), with the type's default deadline (else the journal's).
- In both cases, when you invite a reviewer on the new version, the chosen type is offered first.

The re-invitation e-mails can show the type with the `%%REVIEW_REQUEST_TYPE%%` tag.

## Invitation E-mails

Each type has its own e-mail template, which you can customise in the mail templates screen, like any other template.
A type created by your journal gets its own template when it is created, copied from a generic template.
The templates of the types you disabled are no longer listed.

The paragraph that helps the reviewer to sign in depends on their account (known reviewer, existing account, no
account, answer without an account). It is inserted with the `%%INVITATION_ACCOUNT_HINT%%` tag; its four texts are
also mail templates that you can customise.

The **review report** keeps the three existing invitation templates: your current customisations are unchanged.

## Rating Grids

The **Review: grids and request types** page shows the default grid, then one block per request type, then the grids
per volume. The block of a type shows its grid if it has one; otherwise, it states that the type uses the volume grid,
or the default grid; a type answered without an account says so too. **Create an evaluation grid**, in the block of a type, creates its grid.
Beyond five volume grids, they are collapsed and a click on the title of a volume shows its grid. A new report uses, in
this order:

1. the grid of its request type;
2. otherwise, the grid of the article's volume (except for a quick opinion);
3. otherwise, the default grid.

A quick opinion therefore never uses the grid of a volume, made for review reports: until the journal creates a grid
for it, it uses the default grid.

A report keeps the grid it was created with.

A type answered without an account uses its grid the same way: the invited person fills it in from the invitation
link (see "Answer Without an Account").

## Reports

Each request has its own report. On the paper's administration page:
- the **Reviewers** panel shows the type of each invitation;
- the **Reviews** panel shows the type of the reports other than review reports.

Only the **review reports**, and those of the types where **Count its reports as review reports** is checked, count for
the "reviewed" status of the article, the minimum number of reviews, the average rating and the "not enough reviewers"
reminder. The other types are shown for information. Check this option, for example, for the review stages your journal
creates as types ("Phase 2", "Revised version check"…). It has no effect for a type answered without an account, which
produces no report. On the grids page, these types show the **Counts as a review report** badge.

## Answer Without an Account

When the option is checked on a type, the invitation link opens a page where the invited person:
- reads the instructions of the type (by default, the quick opinion asks for a few lines on the interest of the
  article);
- fills in the grid of the request (options, comments and files according to the criteria), chosen as for a report:
  grid of the type, else of the volume (except for a quick opinion), else the default grid;
- or declines the request.

When no grid is configured, the page tells the invited person, who cannot answer yet, and the editors of the article
are told by e-mail (once per invitation); the history of the article keeps it. Then create a grid for the type, or a
default grid: the invitation link remains valid.

No account is needed. The first answer accepts the invitation and informs the editors by e-mail. The person can edit
their answer from the same link until the due date; a new file replaces the one already sent for the same criterion.

The answer is shown only to the editorial staff, in the **Answers given without an account** panel of the paper's
administration page (and under the invitation in the **Reviewers** panel). Authors never see it.

Without an account, the invited person does not see their previous reports, and their answer does not count as a
review report. The type form reminds
it when the option is checked on a type other than the quick opinion. For a revised version check type, keep the
answer with an account when the reviewer needs their previous report.

## Email Notifications

| Event | Recipients |
|---|---|
| Invitation | the invited person (template of the request type) |
| Answer given without an account | the editors of the article (same e-mail as a completed review, with the filled grid and its files) |
| Answer without an account impossible: no grid configured | the editors of the article (once per invitation) |

## Paper History

The history of the article shows the request type of each invitation.
