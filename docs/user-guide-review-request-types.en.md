# Review Request Types - User Guide

*[English](user-guide-review-request-types.en.md) · [Français](user-guide-review-request-types.fr.md)*

## Overview

When you invite a reviewer, you now choose the **type of request**: a full review report, a quick opinion, a check of a
revised version, or a type created by your journal. The type sets:

- the invitation e-mail sent to the reviewer;
- the suggested due date;
- the rating grid used for the report (if your journal created one for this type);
- whether the reviewer can answer **without an account**, directly from the invitation link.

A reviewer can receive one request of each type for the same article version, for example a quick opinion first, then
a full report.

## Who Can Manage the Types?

The **Review request types** screen (journal settings, next to the review grids) is available to:
- **Administrators**
- **Chief Editors**
- **Secretaries**

## Default Types

Every journal starts with three types:

| Type | Default due date | Use |
|---|---|---|
| Review report | journal setting | detailed review with the rating grid (the usual review) |
| Quick opinion | 2 weeks | a few lines on the interest and relevance of the article |
| Revised version check | 3 weeks | check that a revised version addresses the previous comments |

## Managing the Types

In **Review request types**:

1. Click **Add a request type**, or **Edit** on an existing type.
2. Fill in:
   - **Code**: a stable identifier (lower-case letters, digits, underscores), which cannot be changed later;
   - **Label** in each language of the journal;
   - **Default deadline** (leave empty to use the journal setting);
   - **Position** in the list;
   - **Offer this type when inviting a reviewer** (uncheck to hide it);
   - **Answer without an account, from the invitation link** (see below).
3. Click **Save**.

The three default types are always available: they cannot be deleted, only disabled, so that each journal chooses the
types it offers. A type created by your journal can be deleted as long as it has not been used; afterwards, disable it.
The review report type is always offered and always requires an account.

## Inviting a Reviewer

1. Open **Invite a reviewer** from the paper's administration page and choose the reviewer as usual.
2. In the invitation e-mail step, choose the **Request type**.
3. The message and the due date change according to the type. You can still edit the message before sending it.

The types that the reviewer already has in progress for this article are greyed out.

## Invitation E-mails

Each type has its own e-mail template, which you can customise in the mail templates screen, like any other template.
A type created by your journal gets its own template when it is created, copied from a generic template.
The templates of the types you disabled are no longer listed.

The paragraph that helps the reviewer to sign in depends on their account (known reviewer, existing account, no
account, answer without an account). It is inserted with the `%%INVITATION_ACCOUNT_HINT%%` tag; its four texts are
also mail templates that you can customise.

The **review report** keeps the three existing invitation templates: your current customisations are unchanged.

## Rating Grids

In **Review Grids**, you can create a grid for a request type (**Create a grid for the request type**). A new report
then uses, in this order:

1. the grid of its request type;
2. otherwise, the grid of the article's volume;
3. otherwise, the default grid.

A report keeps the grid it was created with.

## Reports

Each request has its own report. On the paper's administration page:
- the **Reviewers** panel shows the type of each invitation;
- the **Reviews** panel shows the type of the reports other than review reports.

Only the **review reports** count for the "reviewed" status of the article, the required number of reviewers and the
average rating. The other types are shown for information.

## Answer Without an Account

When the option is checked on a type, the invitation link opens a page where the invited person:
- gives an opinion: a text, a file (optional), or both;
- or declines the request.

No account is needed. The first answer accepts the invitation and informs the editors by e-mail. The person can edit
their opinion from the same link until the due date.

The opinion is shown only to the editorial staff, in the **Opinions given without an account** panel of the paper's
administration page (and under the invitation in the **Reviewers** panel). Authors never see it.

## Email Notifications

| Event | Recipients |
|---|---|
| Invitation | the invited person (template of the request type) |
| Opinion given without an account | the editors of the article (same e-mail as a completed review) |

## Paper History

The history of the article shows the request type of each invitation.
