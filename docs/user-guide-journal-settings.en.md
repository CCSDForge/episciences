# Journal settings — User guide

*[English](user-guide-journal-settings.en.md) · [Français](user-guide-journal-settings.fr.md)*

This guide sums up the **Journal settings** page and the related **Licenses** and **DOI settings** pages: what each
setting does and how to use it.

## In short

| Key points | |
|---|---|
| Where is the page? | **Journal management › Journal › Settings** menu (`/review/settings` address), or the **Journal settings** button of the dashboard. |
| Who can change it? | The editors in chief, administrators and editorial secretaries of the journal. |
| When do the changes apply? | As soon as they are saved. Some also concern the articles in progress or published, others only the next actions: see below. |
| How to save? | **Save settings** button, always visible at the bottom of the screen. A message tells when changes are not saved; the browser asks for a confirmation if you leave the page. The **Licenses** and **DOI settings** pages work the same way. |
| What if a value is refused? | "This form contains errors." is shown and the page moves to the first field to fix. Nothing is saved while an error remains. |
| Licenses and DOIs? | They have their own page. The Settings page sums them up at the top, with a link. |
| A setting is dimmed? | It depends on another setting which is off (e.g. "Do not allow choosing an editor in chief" while the author cannot choose an editor). It can still be changed but has no effect for now. |

## When does a change take effect?

Every change counts as soon as it is saved, but not in the same way for every setting.
The Settings page recalls these rules (**When does a change take effect?** link under the introduction).

| When | Settings | Example |
|---|---|---|
| **Immediately, for every article**, including those in progress or already published | What everyone can do or see: rights and encapsulation of the editors and copy editors; options of the authors (temporary version, abandon, new version of a rejected article, messages); conflict of interest declaration; display on the website (review reports, volumes, statistics, footer); published metadata. | If you remove the editors' right to accept, an editor can no longer accept any article, even one they have handled for months. If you show the review reports, those of the **already published** articles become public. |
| **At the next related action** | Review and invitation deadlines (set when each invitation is sent); submission form and automatic assignment of the editors (on each new submission); notifications (in the next emails); reassignment of the reviewers and revision deadline (at the next revision request). | If you change the review deadline from 2 to 3 months, only the invitations sent afterwards get 3 months. |
| **Never retroactively** | What already happened. | The invitations already sent keep their delay and deadline; the editors already assigned stay assigned; the emails already sent are not sent again; the DOIs already assigned do not change. |

## Using the page, step by step

| I want to… | I… |
|---|---|
| go to a topic | click a topic of the **Contents** on the left; the topic on screen is highlighted there. |
| understand a setting | read the grey text under the field. |
| change a deadline | type a number (1 to 999) and choose the unit: day(s), week(s) or month(s). |
| choose several repositories | hold **Ctrl** (or **Cmd** on a Mac) while clicking in the **Repositories** list. |
| change the licenses | click **Edit the licenses** in the **Licenses** box. |
| change the DOIs | click **Edit the DOI settings** in the **Article DOIs** box. If the button is not there, contact the Episciences team. |
| undo my changes | reload the page without saving and confirm leaving. |

## The settings, topic by topic

### Journal identity

Used in the footer of the website, the exports and the published metadata (OAI-PMH, Crossref, DOAJ…).

| Setting | Purpose | Tip |
|---|---|---|
| eISSN (online) | ISSN of the electronic version | Format `1234-5678` (the last character can be `X`). |
| ISSN (print) | ISSN of the print version | Only if there is a print version. |
| Journal DOI | DOI of the journal itself | Not the DOI of the articles. Leave empty if the journal has none. |
| Publisher / Place of publication | Publisher and city, country | The place requires the publisher. |
| Creation year | 4-digit year | — |
| Journal description | Short presentation (plain text, 2000 characters) | Published in the OAI-PMH metadata. |
| Keywords | Topics of the journal | Separated with semicolons. |

### Contacts and emails

| Setting | Purpose | Tip |
|---|---|---|
| Journal contact page | "Contact" link of the footer | Full address, starting with `https://`. |
| Catalogue record page | Link to the record of the journal (e.g. ISSN portal) | Full address. |
| Journal contact email | Address shown in the footer | — |
| Technical support email | Support address shown in the footer | — |
| Code displayed in emails | Replaces the journal code in the subject and text of the automatic emails | Empty: usual journal code. 100 characters maximum. |
| Bounce address | Address receiving the undelivered emails | Choose the journal's own address to follow these failures. |

### Submission

| Setting | Choices | Effect |
|---|---|---|
| Repositories | One or more repositories | Repositories from which articles can be submitted. At least one. |
| Cover letter | Disabled / Optional / Required | File attached to the submission. The comment to the journal is always optional. |
| Section choice | Cannot / Can / Must choose | Section asked to the author. |
| Allow authors to choose the volume | Yes / No | The author suggests a volume. |
| Editor selection | Cannot / Can / Must / Must choose only one | Editors suggested by the author. |
| Do not allow choosing an editor in chief | Yes / No | Only useful if the author can choose an editor. |
| Suggest reviewers / Indicate unwanted reviewers | Yes / No | Extra fields in the submission form. |
| Share the arXiv paper password | No / Optional / Required | The author lets the journal update their arXiv submission. |

### Authors

| Setting | Effect when enabled |
|---|---|
| Answer a revision request with a temporary version | The author can upload a file directly to the journal, pending the new version in the repository. |
| Abandon the publication process | The author can withdraw their article from the process. |
| Resubmit an already rejected article | The author can submit a new version of a rejected article. |
| Communication between authors and editors | Authors and assigned editors exchange messages directly. |
| Show the editors' names to the authors | The author sees who handles their article. |

### Reviewing

| Setting | Effect | Tip |
|---|---|---|
| Delay before an invitation expires | After it, the reviewer can no longer accept. | Default: 1 month. |
| Default review delay | Time to submit the report, counted from the invitation. | Must be between the minimum and the maximum. |
| Minimum / maximum review delay | Limits the editors can apply to each reviewer. | Defaults: 2 months and 6 months. |
| Minimum reviews count | Number of completed reports for the article to be "reviewed"; below it, the editors get reminders to invite reviewers. | 0: every reviewer who accepted must submit their report. |
| Reviewers: send messages to the author | The reviewer can write to the author. | — |
| Show the reports on the public page | The reports are visible on the page of the **published** articles, including those published before it was enabled. | Tell the reviewers. |
| Reassign the same reviewers (major / minor revisions) | Box checked by default when the editor requests revisions. | The editor can change it for each request. |

### Editors

| Setting | Effect when enabled |
|---|---|
| Encapsulate editors | An editor only sees the articles assigned to them. |
| Accept / Reject / Request revisions / Publish | The editor can take these decisions. Otherwise, they can only suggest them. |
| Abandon the publication process | The editor can withdraw an article from the process. |
| Edit the email templates | The editor can adapt the automatic emails. |
| Automatically assign the… | On submission, the checked editors (in chief, section, volume, special issue, suggested by the author) are assigned to the article. Nothing checked: no automatic assignment. |

### Editorial decisions

| Setting | Effect when enabled |
|---|---|
| Require a deadline for revision requests | The editor must set a deadline for each revision request. |
| Allow revision requests | An accepted article can still be asked for a new version. |

### Conflicts of interest

| Setting | Effect when enabled |
|---|---|
| Enable declaring COI | The editors must declare they have no conflict of interest before accessing the non-public information of an article. |
| Comments for the editors before the declaration | The editors get these comments before declaring. Only applies when the COI declaration is enabled. |

### Notifications

| Setting | Effect |
|---|---|
| Notify the editors in chief / administrators / editorial secretaries | These roles are notified (submission, update, rejection…) in addition to the editors of the article. Nothing checked: the three roles are still notified of a new submission. |
| Forward the rejection message to the reviewers | On rejection, the message explaining the decision is also sent to the reviewers. |

### Volumes and special issues

| Setting | Effect when enabled |
|---|---|
| Show empty volumes | Volumes without articles are shown on the website. |
| Edit the title of a volume holding published articles | The title can still be changed after publication. |
| Show secondary volumes | They are shown on the public page of the article. |
| Access code of the special issues | The code of the special issue is needed to submit to it. |
| Reassign the handling of the article | In a special issue, an editor of the article can hand it over to another editor of the issue. |

### Copy editing

| Setting | Effect when enabled |
|---|---|
| Encapsulate copy editors | A copy editor only sees the articles assigned to them. |

### Statistics

| Setting | Choices | Effect |
|---|---|---|
| Statistics visibility | Hidden / Public / Restricted to administrators | Who sees the statistics page. |
| Statistics start date | `YYYY-MM-DD` date (optional) | Only the data after it are counted. |

## Licenses (`/review/licenses`)

| Setting | Effect | Tip |
|---|---|---|
| Default license (required) | Preselected when setting the license of an article that has none. | Not a constraint. |
| Allowed licenses (optional) | Limits the licenses offered for an article. | Nothing selected: all the recommended licenses. The default license is always in the list. |

## DOI settings (`/doi/settings`)

This page is managed by the Episciences team; the Settings page sums it up.

| Setting | Effect |
|---|---|
| Automatic DOI assignment | **Automatic**: DOI created on acceptance. **Manual**: the journal clicks "Request a DOI" on the article page. **Disabled**: the journal enters the DOI of each article. |
| Registration agency | Crossref. |
| DOI prefix | Given by the agency (e.g. `10.46298`). Without a prefix, no DOI can be created. |
| DOI format | Template of the end of the DOI, with tags (`%R%` journal code, `%P%` article number, `%Y%` year…). A preview shows the resulting DOI. The DOIs already assigned do not change. |

## Frequently asked questions

| Question | Answer |
|---|---|
| I changed a deadline: are the invitations already sent changed? | No. The new deadline applies to the next invitations. |
| I cannot find a setting. | Use the **Contents**, or the search of the browser (Ctrl + F). Licenses and DOIs have their own page. |
| Saving fails with "please also enter the publisher". | A place of publication is filled in without publisher: fill in the publisher or empty the place. |
| The ISSN is refused. | Type it as `1234-5678`, with the hyphen. |
| Who can see what each role can do? | Everyone, on the **Permissions** page. |
