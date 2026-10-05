# Change Log - 2022

All notable changes to this project for 2022.

## 1.0.38.5 - 2022-12-14

### Fixed

- [#294](https://github.com/CCSDForge/episciences/issues/294): untranslated text
- API to return number of published documents

## 1.0.38.4 - 2022-12-12

### Fixed

- JS error: Failed to load plugin url: https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/langs/fr_FR.js
  @see https://www.tiny.cloud/docs-4x/configure/localization/#language
- Add %%PAPER_REP_URL%% tag to the description of the invitation templates.
- Change password: redirect to authentication if user is not logged in
- The '%%SENDER_SCREEN_NAME%%' tag is always present in the 'paper_paper_editor_refused_monitoring' template

## 1.0.38.3 - 2022-11-30

### Fixed

- Html filtering problem that breaks the display in the comments

## 1.0.38.2 - 2022-11-24

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): There is a difference between the capitalization of the first and last names in the user's profile
  and the first and last names of the user in the email recipient field [RT#148137]
- Fixed the fact that the Administrator role prevents testing for conflict of interest
- Error: $(...).fileupload is not a function for published articles.

### Changed

- Conflict management section: set default display lines to 5.
- Mailing > getContacts: add 'Authors' filter.
- resolved [#282](https://github.com/CCSDForge/episciences/issues/282): C:\fakepath: This is the security
  implementation of the browser: the browser is protecting you from accessing your disk structure.
- Code review / refactoring

## 1.0.38.1 - 2022-11-16

### Fixed

- Added missing translation for conflict of interest

### Changed

- Footer: replaced TOS with Term Of Use

## 1.0.38 - 2022-11-16

### Added

- New footer link to the Term of use (between the platform and users)

### Fixed

- Fixed ORCID 'X' in DOAJ export format, make it compliant with ORCID specification
- [RT#171463]: the reviews should not be seen by someone with a CoI declaration with
  the paper: this fix concerns all the paper's details.
# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

<!--
### Added
### Changed
### Deprecated
### Fixed
### Removed
### Security
### Performances
-->

## Unreleased

### Added
- [#937](https://github.com/CCSDForge/episciences/issues/937) Admin paper list (`/administratepaper/list`): columns **Reviewers**, **Editors**, **Copy editors**, and **Contributor** are now sortable server-side.
### Performances

- [#937](https://github.com/CCSDForge/episciences/issues/937) Sorting by Reviewers / Editors / Copy editors uses a derived-table `LEFT JOIN` with `MIN(SCREEN_NAME)` aggregation (single evaluation per query) rather than a correlated subquery.



- Paper admin: volume/section assignment now uses a native `<dialog>` modal with Tom Select; `paper-assignment-modal.js` (vanilla JS) replaces `volume-assignment.js` and `section-assignment.js`.
- Paper admin: Tom Select `dropdown_input` and `remove_button` plugins in modal selects.
- Admin paper list: Tom Select search on all 7 filter selects (Status, Volume, Section, Editors, Reviewers, DOI, Repositories) — type to filter within long lists, animated chevron indicates dropdown, placeholder shows "All" when nothing is selected.

### Changed
- [#1038](https://github.com/CCSDForge/episciences/issues/1038) It is now possible to publish the article at any stage of the editorial process if it is accepted[RT##287093].
- [RT##287093]: It is now possible to publish the article at any stage of the editorial process if it is accepted.
- Paper admin: assignment buttons replaced by pencil icon (`fa-pen-to-square`); glyphicons removed.
- Paper admin: modal save now does a targeted AJAX refresh instead of a full page reload.
- Paper admin: other volumes form replaced by a Tom Select `<select multiple>` (`checkbox_options` plugin).
- Paper admin (i18n): "Master volume" renamed to "Main volume".
- Paper admin: section edit button moved before label/name, consistent with volumes layout.
- Paper admin: volume sort button uses `fa-arrow-down-1-9` icon with a descriptive tooltip.
- Admin paper list: filter panel reorganised into 3 rows — Status/Editors/Reviewers on the first row, Volume+DOI on the second, Section+Repositories on the third; Volume and Section are wider (`col-sm-8`) to accommodate long titles.
- Admin paper list: removed redundant page description blockquote.

### Fixed
- Application error: Call to a member function getVol_num() on bool

- `scripts/update_papers.php` : fixed issue where importing a new version (e.g. version 2) of an existing paper (e.g. version 1) overwrote the version 1 entry in the database instead of creating a new version record.

- BibTeX export: month field was rendered in the user's UI locale (e.g. `mai` for French users) instead of English (`May`); locale is now forced to `en` in the BibTeX template.

- [#1030](https://github.com/CCSDForge/episciences/issues/1030): missing "Ask the author for the sources (copy editing by the journal): because the "Allow post - acceptance revisions of articles" option is used, which alters the workflow steps, as it is assumed that the journal already has the source files. At this stage, it is now possible to request the sources.

- Paper admin: closed modal remained visible (`display:flex` SCSS overrode native `dialog:not([open])` behaviour).
- Paper admin: Tom Select selected items now styled as blue pills in the modal.
- Paper admin: translation failure on volume-alert text (curly apostrophe in `views.php` vs plain ASCII in template).
- Paper admin: "Assign editors" checkbox now disabled+unchecked when no section is selected.
- Paper admin: article position badge wrapped in a Bootstrap `label-default` span with an accessible tooltip.
- Paper admin: main volume cell not updated in list after remove+reassign (stale `btn.dataset.vid` + missing fallback in `refreshallmastervolumesAction` for papers without a position row).
- Admin paper list: `checkFilterParams` crashed with `TypeError: Cannot read properties of null (reading 'length')` when clicking "Filter" with Tom Select multiselects returning `null` for empty selections; fallback to `['']` restores expected behaviour.

- [#930](https://github.com/CCSDForge/episciences/issues/930) New email template tags for volume metadata: `%%VOLUME_NUMBER%%`, `%%VOLUME_YEAR%%`, and `%%VOLUME_TYPE%%`.
- Mailing lists: added `created_at` and `updated_at` columns to the `mailing_lists` table.
- Mailing lists: four MySQL triggers propagate `updated_at` to the parent row when individual members or roles are modified.
- Mailing lists: `v_mailing_lists_resolved` view now exposes `list_created_at` and `list_updated_at`.
- Mailing lists: "Last updated" column displayed after "Name" in the dashboard table.
- Mailing lists: creation date displayed (read-only) in the list edit form.

### Performances

- Avoid repeated `REVIEW_SETTING` queries in a single request by caching loaded review settings on the current review object and sharing cached review instances between `RVID` and `RVCODE` lookups.

### Changed
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration
  button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)"
- Modernized `Ccsd_Form_Filter_Clean` filter and `Ccsd_Form_Validate_NotSame` validator (introduced strict typing, comprehensive type hinting, and robust recursive array filtering for the `Clean` filter).

### Removed

- Removed obsolete `DEAD CODE AUDIT` deprecation warnings from `UserFtpQuota` and `UserFtpQuotaMapper` classes.

## 1.0.38.5 - 2022-12-14

### Fixed

- [#294](https://github.com/CCSDForge/episciences/issues/294): untranslated text
- API to return number of published documents

## 1.0.38.4 - 2022-12-12

### Fixed

- JS error: Failed to load plugin url: https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/langs/fr_FR.js
  @see https://www.tiny.cloud/docs-4x/configure/localization/#language
- Add %%PAPER_REP_URL%% tag to the description of the invitation templates.
- Change password: redirect to authentication if user is not logged in
- The '%%SENDER_SCREEN_NAME%%' tag is always present in the 'paper_paper_editor_refused_monitoring' template

## 1.0.38.3 - 2022-11-30

### Fixed

- Html filtering problem that breaks the display in the comments

## 1.0.38.2 - 2022-11-24

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): There is a difference between the capitalization of the first and last names in the user's profile
  and the first and last names of the user in the email recipient field [RT#148137]
- Fixed the fact that the Administrator role prevents testing for conflict of interest
- Error: $(...).fileupload is not a function for published articles.

### Changed

- Conflict management section: set default display lines to 5.
- Mailing > getContacts: add 'Authors' filter.
- resolved [#282](https://github.com/CCSDForge/episciences/issues/282): C:\fakepath: This is the security
  implementation of the browser: the browser is protecting you from accessing your disk structure.
- Code review / refactoring

## 1.0.38.1 - 2022-11-16

### Fixed

- Added missing translation for conflict of interest

### Changed

- Footer: replaced TOS with Term Of Use

## 1.0.38 - 2022-11-16

### Added

- New footer link to the Term of use (between the platform and users)

### Fixed

- Fixed ORCID 'X' in DOAJ export format, make it compliant with ORCID specification
- [RT#171463]: the reviews should not be seen by someone with a CoI declaration with
  the paper: this fix concerns all the paper's details.

### Changed

- UI/UX: Improved rendering of the "conflict management" section
- [RT#170200]: confirmation of the absence of conflict of interest: it is now possible to cancel this type of response.

## 1.0.37 - 2022-11-10

### Added

- New feature: [Open Science Lens](https://www.opensciencelens.eu/) feature available as a preview on selected journals

### Changed

- Improved rendering of volumes

## 1.0.36 - 2022-11-09

### Added

- Improved rendering of the volume page
- authors can now enter their paper password provided by the open archive on the article page.
- alt attribute to volume's image.
- Added an API method to get the number of users accounts

### Changed

- Optimization: there is no need to check for conflicts at the time of submission
- [#280](https://github.com/CCSDForge/episciences/issues/280): set spellcheck to false in dynamic datatable search boxes.
- [#281](https://github.com/CCSDForge/episciences/issues/281): Submit an article > Guidelines section: harmonization of journal/review terms [FR]
- Now, when an article is accepted, all unanswered invitations are deleted. The reviewer is informed of this action.
- Add 'ISSN pending' until the ISSN is actually issued

### Fixed

- Fix incorrect HTML in footer
- Incorrect paper status LMCS #10145 (related to https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#10273---2022-03-18)
- Empty %%REQUESTER_EXPRESSION%% tag (related to [#187](https://github.com/CCSDForge/episciences/issues/187)
- Incorrect status jdmdh#10203 (related with git#372)
- Fixed [#RT169943]: the tag %%SENDER_SCREEN_NAME%% (obsolete) that should have contained the editor's screen name has not been replaced:
  to be replaced in custom templates by %%EDITOR_FULL_NAME%% or %%EDITOR_SCREEN_NAME%%
  (@see https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#fixed-11 [RT##160301])

## 1.0.35 - 2022-10-19

### Added

- [RT#169107]: Feature - new option: do not allow the selection of an editor-in-chief when the author has the option to
  propose an editor at the time of submission.

### Changed

- [#255](https://github.com/CCSDForge/episciences/issues/255) Display ratings reports on the article page: Label updated

### Fixed

- [RT#169088]: Editors-in-chief and editorial secretaries are no longer notified of new submissions.

## 1.0.34.2 - 2022-10-18

### Fixed

- temporary fix: TinyMCE: loss of formatting for successive mailings
- Insert data in 'T_PAPER_DATASETS' table: MySql insert 0 instead of NULL
- [#273](https://github.com/CCSDForge/episciences/issues/273): if the editor changes the rating due date, then this
  is not reflected in the message.
- Fixes in exports format related to trailing ',' and language codes

## 1.0.34.1 - 2022-10-16

### Fixed

- Fixed bad JS escape of authors in reviewer invitation form
- Fixed Handle URLs links adding https://hdl.handle.net/
- Fixed display of disabled buttons
- Fixed 'Description of affiliation block'

### Added

- The activation of maintenance alerts is now configurable

## 1.0.34 - 2022-10-13

### Added

- Article managers (authors, editors, etc.) may add new optional - but recommended - metadata to the documents
    - [ORCID](https://orcid.org/)
    - Affiliations: for affiliations the [ROR](https://ror.org/) or a free text may be used. If an institution is not available in the ROR, a
      simple text entry may be used.
- Automatic enrichment of metadata:
    - Licenses: retrieved from [Datacite](https://datacite.org/), [OpenAIRE Research Graph](https://graph.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/)
    - ORCID: retrieved from [OpenAIRE Research Graph](https://graph.openaire.eu/)
    - Linked Datasets: [Scholexplorer](https://scholexplorer.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/): Datasets linked to Episciences
      publications
    - Funding: Research projects from [OpenAIRE Research Graph](https://graph.openaire.eu/) and [HAL](https://hal.archives-ouvertes.fr/)
    - Citations: Citations of published document retrieved with [OpenCitations](https://opencitations.net/) APIs
- Exports formats:
    - [DOAJ](https://doaj.org/about/) (export one document or a whole published volume) using DOAJ Schema
    - The new collected metadata have been added to Datacite, [Crossref](https://www.crossref.org/) and DOAJ export formats
- Browse by volume: new parameter to allow to display empty volumes. Default value is still no (only show volumes
  with papers published in the volume)
- [#186](https://github.com/CCSDForge/episciences/issues/186): Editors-in-chief can now report a conflict of
  interest. (Sign in an admin as another user: the real identity is now saved.)

### Fixed

- Fixed a display bug on the portal in connected mode (used by Episciences staff only)
- Failed to edit another account's profile from the user management page.
- impossibility to validate the profile if the user does not have an account in the application

### Changed

- new parameter for enabling/disabling submission from the archive to the application and vice versa.

## 1.0.33.2 - 2022-10-05

### Fixed

- RT#167820: all occurrences of tags in a template will be replaced by their real values.

## 1.0.33.1 - 2022-09-29

### Fixed

- Allow editors to view the list of conflicts on the article management page
- The number of conflicts indicated on the article management page is incorrect if the article has several versions

## 1.0.33 - 2022-09-28

### Added

- New page listing all email templates and available tags for each journal at /administratemail/tagslist
  (- Mail -> TagList)
- Episciences portal:
    - New Feed RSS + Atom : latest published documents, 2 docs per journal
    - API to get a list of publishing journals: include accepted repositories.
- Episciences journals
    - Added 'journals news' RSS feed for each journal
- New journal settings parameter: Allow post-acceptance revisions of articles
- 9 article statuses, available for journals allowing 'post-acceptance revisions of articles':
    - Accepted temporary version, waiting for author's final version: now, temporary versions that are accepted have this new status instead of "pending minor revision" (git #372)
    - Accepted - waiting for author's final version
    - Accepted, waiting for major revision
    - Accepted article - final version submitted, waiting for formatting by copy editors
    - Accepted temporary version after author's modifications
    - Accepted temporary version, waiting for minor revision
    - Accepted temporary version, waiting for major revision
    - Accepted - waiting validation by the author
    - Approved by author, waiting for final publication
- User profiles:
    - Adding ORCID and affiliation to your profile is now possible + this information is diplayed on the
      "editorial staff member" (gitlab #410) & dashboard pages.
    - Social Medias & Websites profile informations.
- New 'Author' role automatically added to users that have submitted a document
- Paper list: temporary versions now have a specific label
- Dashboard: Alert for administrators on the existence of papers without assigned editors
- Display the origin of a linked data when papers are linked to a dataset
- Administration page of a paper: new shortcut for administrators :
    - to allow to sign in as a copy-editor
    - to allow to sign in as an editor
- [227](https://github.com/CCSDForge/episciences/issues/227):
    - The revision deadline is now displayed below the article's status.
    - A pictogram is added to indicate the date of the revision deadline in the article management table.

### Changed

- [#237](https://github.com/CCSDForge/episciences/issues/237) Editor comments div is now bigger
- Upgraded publication RSS Feeds with DOIs
- [#142](https://github.com/CCSDForge/episciences/issues/142): allow that administrators answer revision and copy editing requests
- It is now possible to update the metadata on the document's page
- Request a new version of an article that has already been accepted
- [#166](https://github.com/CCSDForge/episciences/issues/116): hide "guest" role and doi-settings action
- The "paper status" filter is now dynamically created.
- Prevent "Add sources files" and "Add the formatted version" buttons JS reactivation
- Moved "copy editing" section to a more usable place on the same page
- Dashboard improvements: ability to reach an article from sections: my submissions and assigned articles
- [#187](https://github.com/CCSDForge/episciences/issues/187): change the default templates: 'new version submitted' and 'tmp version submitted'
- [#188](https://github.com/CCSDForge/episciences/issues/188): force notifications
- It is now possible to update the document version in Episciences to the most recent version in the open archive.
- [#164](https://github.com/CCSDForge/episciences/issues/164): harmonization of roles/privileges.
- CWI open repository has been temporarily removed until we adapt to their new OAI server

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): capitalization of names
- [#207](https://github.com/CCSDForge/episciences/issues/207): editing the translation of Chief Editors
- [#169](https://github.com/CCSDForge/episciences/issues/169): reports become visible on the article web page:
    - according to the parameters of the journal for published articles
    - for the owner, only if the paper is refused, waiting for revision, already accepted or published
- Another status (Revision request answer: without any modifications) is used when responding to a revision request: without any modifications.
- Revision requests: now we have an immediate visual feedback when changing the deadline.
- If the journal allows revision of articles after acceptance, the response to a request for a final version (without any changes) is similar to the proposal of a new version.

### Internal refactoring

- Code Refactoring fixing of warning
- Update Episciences_User::getScreenName()
- User Table: merge AFFILIATIONS" filed in "ADDITIONAL_PROFILE_INFORMATION"
- Multiple roles: ignore the "member" role when merging two accounts.
- Email Templates updates
- Script to clean the "USER_ROLES" table
- Parameter to detect automatic emails

## 1.0.32.2 - 2022-09-28

### Changed

- [196](https://github.com/CCSDForge/episciences/issues/196): Creation of an account following an invitation with a temporary account: from now on, the Last Name, First Name and Screen Name fields will be left empty (for the reviewer to fill in)
- [RT#164153]: refactoring: now obsolete template "paper_new_version_reviewer_reassign" (Reviewer assignment to a new version of an article) is removed.

### Fixed:

- account merging incomplete due to sql error when updating 'paper_conflicts' table.

## 1.0.32.1 - 2022-09-01

### Fixed

- Fixed bug introduced with [196] "Name" => "Last Name" when creating a new reviewer
- Fixed release notes links to issues

## 1.0.32 - 2022-08-31

### Changed

- [201](https://github.com/CCSDForge/episciences/issues/201): depending on journal settings, editor can accept, ask revision, reject and publish papers in every round.
- [238](https://github.com/CCSDForge/episciences/issues/238): harmonization of terms 'sections/rubriques'.
- [196](https://github.com/CCSDForge/episciences/issues/196) now,
    - Only one 'name' field is available when creating a new reviewer
    - Reviewers are sorted by lastname by default
- Empty TAG %%RECIPIENT_SCREEN_NAME%% for users who do not have a local account in "Unanswered reviewer invitation (reviewer copy)" reminder.
- (Internal/refactoring) transition to PHP 8: refactoring: "MaxMind GeoIP2 PHP API" is now used instead of PHP module.

### Fixed

- [RT#163166]: Problem sending mail from staff page.
- Fixed [#165](https://github.com/CCSDForge/episciences/issues/165): Line breaks in paper titles in API
- Fixed [#251](https://github.com/CCSDForge/episciences/issues/251): conform to ISO 3297 for displaying ISSNs

## Added

- Episciences portal: New Feed RSS + Atom : latest published documents, 2 docs per journal
- Added 'journals news' RSS feed

## 1.0.31.1 - 2022-07-13

### Changed

- User accounts merging procedure: take into account the Conflict Of Interests

## 1.0.31 - 2022-06-28

### Added

- A pictogram is added to indicate papers with conflicts in the article management table.

### Changed

- [192](https://github.com/CCSDForge/episciences/issues/192): allow editors to "Ask for other editors opinion".
- Only confirmed conflicts will be displayed in the conflict management section

### Fixed

- Ask revision: possibility to backdate the deadline.
- Fixed: RT #160301:
  the tags [%%SENDER_FULL_NAME%%, %%SENDER_SCREEN_NAME%%, %%SENDER_EMAIL%%, %%SENDER_FIRST_NAME%%', %%SENDER_LAST_NAME%% ]
  concerning the user of the action are filled with the data of the user connected at the time of the action.
  Making these variables available in the automatic mails poses a real problem: they are filled with the data of the mail recipient.
  So, from now on, the tags mentioned above will no longer be available in the automatic mail templates.
- Shifted display because of the error message (CSRF token)

## 1.0.30 - 2022-06-20

### Fixed

- Titles do not appear in the correct language when more than one language has been entered (e.g. SLOVO )

### Added

- It is now possible to manage conflicts on the article page: display & delete them

## 1.0.29.1 - 2022-06-13

### Changed

- Revision requests: now we have an immediate visual feedback when changing the deadline

### Fixed

- [#247](https://github.com/CCSDForge/episciences/issues/247):
  HTML links in outgoing emails are made relative [#247]: TinyMCE configuration: convert all relative URLs to absolute URLs.
- Edition of a volume: The title of article might not be retrieved if the language was not managed by the platform.
- Profile editing form does not display correctly when changing language.

## 1.0.29 - 2022-06-01

### Fixed

- Failed 'Not enough reviewers - editor copy' reminders.
- But leading to load journal translations from '/'.
- PHP Warning: Use of undefined constant RVID - assumed 'RVID' (this will throw an Error in a future version of PHP)
- Not enough review reminders: articles without invitations escape reminders because the function that retrieves invitations is not used properly.

### Added

- User list: make it easier to locate accounts that have not been activated.
- Addes Roadmap link + User survey/feedback on portal
- Make EU grants and OpenAIRE support more visible at the bottom of the page

## 1.0.28.5 - 2022-05-25

### Fixed

- [RT#158293]: article status is not correctly updated

## 1.0.28.4 - 2022-05-09

### Fixed

- File not found for Temporary Versions without attached files + refactoring.

## 1.0.28.3 - 2022-04-13

### Changed

- Updated error message when using a HAL identifier without any file.
- [#142](https://github.com/CCSDForge/episciences/issues/142)

## 1.0.28.2 - 2022-04-06

### Fixed

- Empty records from HAL repository: error message improved.
- Improvements and fixes on Datacite and Crossref XML exports

## 1.0.28.1 - 2022-03-23

### Fixed

- fix issue in crossref xml format with related_item

## 1.0.28 - 2022-03-23

### Changed

- Bump crossref schema to ver 4.8.1
- arXiv URLs now links to https version instead of http

### Added

- Crossref metadata export format for DOI:
    - add previous version URLs, link with relationship 'hasPreprint' to link preprints versions with the published version
    - add arXiv DOIs, link to journal's published DOI with 'isSameAs' relationship
    - add full text PDF URLs for Crossref 'Similarity Check' service

## 1.0.27.5 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format
- Adding Polyfill PHP 8.0

## 1.0.27.4 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format

## 1.0.27.3 - 2022-03-18

### Fixed

- Ratings submitted late after the start of the layout process caused the status of the article to be updated (rolled back).
- There is no need to review an article while it is being formatted by the author.

## 1.0.27.2 - 2022-03-18

### Fixed

- "Copy editor" role not authorized accessing to the public page of the paper
  and report a conflict of interest, if the option has been activated by the journal

## 1.0.27.1 - 2022-03-16

### Fixed

- Exclude the "paper-status" directory from the journal's resources.
- Content that is copied and pasted when reviewing an article may lose end of line.
- The linked data to an article is not deleted when the author deletes his own article

## 1.0.27 - 2022-03-14

# Changed

- Reviewer invitation: Users are now sorted by Name, First Name instead of First Name

# Fixed

- Dashboard: the number of papers assigned to the copy editor includes obsolete papers
- Issue reported to support RT #148133 (fixed before this release):
    - Some editors could see the reviewers names if they were also authors of the article

## 1.0.26 - 2022-03-01

# Fixed

- Issue reported to support RT #148133:
    - Incorrect "Reminder Delay: The "Reminder Delay" should correspond, in number of days, to the difference between the date the reminder was sent and the date the invitation was sent.
- Issue reported to support RT #148466:
    - Sorting by date in the Dashboard is wrong: Enabling the conflict of interest (COI) option distorts the pagination

## 1.0.25.1 - 2022-02-15

# Changed

[#152](https://github.com/CCSDForge/episciences/issues/152): page footer modifications.

## 1.0.25 - 2022-02-15

# Fixed

- All submissions are accessible to "guest editor" roles.
- Language bar missing when editing a mail template, including reminders.

## 1.0.24.2 - 2022-02-10

# Fixed

- [RT#146987] "search" button no longer works
- application error: syntax error or access violation if CAS user not found

## 1.0.24.1 - 2022-02-08

# Fixed

- A bug in the reminders code triggered emails with unusable HTTP links

## 1.0.24 - 2022-02-08

# Fixed

- Inability to delete a volume (RT#145178): reset the volume of the previous version when submitting a new version.
- [#117](https://github.com/CCSDForge/episciences/issues/117) Improvements in emailing
- Undetected inactive accounts: Invite a reviewer > new reviewer > Invite a new user.
- Not allowing the author to be invited: Invite a reviewer > new reviewer > Invite a new user.
- The attachments to the rating report are not available [RT#145473]
- Answer revision request: in some situations, the "Submit" button remains inactive.

## 1.0.23 - 2022-01-19

### Added

- [#116](https://github.com/CCSDForge/episciences/issues/116) Release version is displayed on the interface (dashboard and page footer)
- Editors' choice at time of submission: informative text added. (gitlab #369)
- [#143](https://github.com/CCSDForge/episciences/issues/143) New DOI setting: Allow switching off manual or automatic DOI assignation (disabled mode)
- [#37](https://github.com/CCSDForge/episciences/issues/37)
    - Browse by Volume or Sections : Handle new content type 'application/json' to return json instead of html
    - On URLs like /volume/view/id/314159 and /section/view/id/314159 Handle new content type 'application/json' to return json instead of html (only published content)
    - On URLs like /volume/edit?id=314159 Handle new content type 'application/json' to return json instead of html but including all statuses of articles ; an authenticated and allowed user is required
    - JSON added to the list of public export formats
- Enhanced information message for statistics

### Changed

- UI/UX: Make the version number more explicit when proposing a new version
- Code Refactoring fixing of warning
- Dumping default data for table `MAIL_TEMPLATE`

### Fixed

- [#126](https://github.com/CCSDForge/episciences/issues/126) Clicking outside the window to compose an email closes the window with no confirmation #126
- [#117](https://github.com/CCSDForge/episciences/issues/117) Mailing bug: loss of the mail when you forget to put a recipient (gitlab #343)
- [#149](https://github.com/CCSDForge/episciences/issues/149) Emails and default language selection: language of the sender and the recipient could be a problem if the language of the sender is different from the language of the recipient (gitlab #402)
- Inconsistent REMINDER_DELAY: the displayed value is the "delay" parameter of the reminder instead of the calculated value (interval in number of days between the time the reminder was sent and the deadline).
- New submission: if the "submission date" metadata is empty: do not display it anymore.
- [#124](https://github.com/CCSDForge/episciences/issues/124): refining automatic notifications when abandoning submission
- RT#144252: better cleaning of attached file names
- RT#144246: an inactive account could still be visible on the Editorial Staff members page.
- [#117](https://github.com/CCSDForge/episciences/issues/117): An empty e-mail is not sent or received
- [#129](https://github.com/CCSDForge/episciences/issues/129): when you do a search from the mail history page nothing happens.
- [#141](https://github.com/CCSDForge/episciences/issues/141): visibility of pages menu is wrong inside menu editing.

### Changed

- [#126](https://github.com/CCSDForge/episciences/issues/126): prevent modal closure: disabling the click outside the modal area and by pressing Esc
- Enabling the "COI" option overrides the "Encapsulate editors" option

## 1.0.38.5 - 2022-12-14

### Fixed

- [#294](https://github.com/CCSDForge/episciences/issues/294): untranslated text
- API to return number of published documents

## 1.0.38.4 - 2022-12-12

### Fixed

- JS error: Failed to load plugin url: https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/langs/fr_FR.js
  @see https://www.tiny.cloud/docs-4x/configure/localization/#language
- Add %%PAPER_REP_URL%% tag to the description of the invitation templates.
- Change password: redirect to authentication if user is not logged in
- The '%%SENDER_SCREEN_NAME%%' tag is always present in the 'paper_paper_editor_refused_monitoring' template

## 1.0.38.3 - 2022-11-30

### Fixed

- Html filtering problem that breaks the display in the comments

## 1.0.38.2 - 2022-11-24

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): There is a difference between the capitalization of the first and last names in the user's profile
  and the first and last names of the user in the email recipient field [RT#148137]
- Fixed the fact that the Administrator role prevents testing for conflict of interest
- Error: $(...).fileupload is not a function for published articles.

### Changed

- Conflict management section: set default display lines to 5.
- Mailing > getContacts: add 'Authors' filter.
- resolved [#282](https://github.com/CCSDForge/episciences/issues/282): C:\fakepath: This is the security
  implementation of the browser: the browser is protecting you from accessing your disk structure.
- Code review / refactoring

## 1.0.38.1 - 2022-11-16

### Fixed

- Added missing translation for conflict of interest

### Changed

- Footer: replaced TOS with Term Of Use

## 1.0.38 - 2022-11-16

### Added

- New footer link to the Term of use (between the platform and users)

### Fixed

- Fixed ORCID 'X' in DOAJ export format, make it compliant with ORCID specification
- [RT#171463]: the reviews should not be seen by someone with a CoI declaration with
  the paper: this fix concerns all the paper's details.

### Changed

- UI/UX: Improved rendering of the "conflict management" section
- [RT#170200]: confirmation of the absence of conflict of interest: it is now possible to cancel this type of response.

## 1.0.37 - 2022-11-10

### Added

- New feature: [Open Science Lens](https://www.opensciencelens.eu/) feature available as a preview on selected journals

### Changed

- Improved rendering of volumes

## 1.0.36 - 2022-11-09

### Added

- Improved rendering of the volume page
- authors can now enter their paper password provided by the open archive on the article page.
- alt attribute to volume's image.
- Added an API method to get the number of users accounts

### Changed

- Optimization: there is no need to check for conflicts at the time of submission
- [#280](https://github.com/CCSDForge/episciences/issues/280): set spellcheck to false in dynamic datatable search boxes.
- [#281](https://github.com/CCSDForge/episciences/issues/281): Submit an article > Guidelines section: harmonization of journal/review terms [FR]
- Now, when an article is accepted, all unanswered invitations are deleted. The reviewer is informed of this action.
- Add 'ISSN pending' until the ISSN is actually issued

### Fixed

- Fix incorrect HTML in footer
- Incorrect paper status LMCS #10145 (related to https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#10273---2022-03-18)
- Empty %%REQUESTER_EXPRESSION%% tag (related to [#187](https://github.com/CCSDForge/episciences/issues/187)
- Incorrect status jdmdh#10203 (related with git#372)
- Fixed [#RT169943]: the tag %%SENDER_SCREEN_NAME%% (obsolete) that should have contained the editor's screen name has not been replaced:
  to be replaced in custom templates by %%EDITOR_FULL_NAME%% or %%EDITOR_SCREEN_NAME%%
  (@see https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#fixed-11 [RT##160301])

## 1.0.35 - 2022-10-19

### Added

- [RT#169107]: Feature - new option: do not allow the selection of an editor-in-chief when the author has the option to
  propose an editor at the time of submission.

### Changed

- [#255](https://github.com/CCSDForge/episciences/issues/255) Display ratings reports on the article page: Label updated

### Fixed

- [RT#169088]: Editors-in-chief and editorial secretaries are no longer notified of new submissions.

## 1.0.34.2 - 2022-10-18

### Fixed

- temporary fix: TinyMCE: loss of formatting for successive mailings
- Insert data in 'T_PAPER_DATASETS' table: MySql insert 0 instead of NULL
- [#273](https://github.com/CCSDForge/episciences/issues/273): if the editor changes the rating due date, then this
  is not reflected in the message.
- Fixes in exports format related to trailing ',' and language codes

## 1.0.34.1 - 2022-10-16

### Fixed

- Fixed bad JS escape of authors in reviewer invitation form
- Fixed Handle URLs links adding https://hdl.handle.net/
- Fixed display of disabled buttons
- Fixed 'Description of affiliation block'

### Added

- The activation of maintenance alerts is now configurable

## 1.0.34 - 2022-10-13

### Added

- Article managers (authors, editors, etc.) may add new optional - but recommended - metadata to the documents
    - [ORCID](https://orcid.org/)
    - Affiliations: for affiliations the [ROR](https://ror.org/) or a free text may be used. If an institution is not available in the ROR, a
      simple text entry may be used.
- Automatic enrichment of metadata:
    - Licenses: retrieved from [Datacite](https://datacite.org/), [OpenAIRE Research Graph](https://graph.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/)
    - ORCID: retrieved from [OpenAIRE Research Graph](https://graph.openaire.eu/)
    - Linked Datasets: [Scholexplorer](https://scholexplorer.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/): Datasets linked to Episciences
      publications
    - Funding: Research projects from [OpenAIRE Research Graph](https://graph.openaire.eu/) and [HAL](https://hal.archives-ouvertes.fr/)
    - Citations: Citations of published document retrieved with [OpenCitations](https://opencitations.net/) APIs
- Exports formats:
    - [DOAJ](https://doaj.org/about/) (export one document or a whole published volume) using DOAJ Schema
    - The new collected metadata have been added to Datacite, [Crossref](https://www.crossref.org/) and DOAJ export formats
- Browse by volume: new parameter to allow to display empty volumes. Default value is still no (only show volumes
  with papers published in the volume)
- [#186](https://github.com/CCSDForge/episciences/issues/186): Editors-in-chief can now report a conflict of
  interest. (Sign in an admin as another user: the real identity is now saved.)

### Fixed

- Fixed a display bug on the portal in connected mode (used by Episciences staff only)
- Failed to edit another account's profile from the user management page.
- impossibility to validate the profile if the user does not have an account in the application

### Changed

- new parameter for enabling/disabling submission from the archive to the application and vice versa.

## 1.0.33.2 - 2022-10-05

### Fixed

- RT#167820: all occurrences of tags in a template will be replaced by their real values.

## 1.0.33.1 - 2022-09-29

### Fixed

- Allow editors to view the list of conflicts on the article management page
- The number of conflicts indicated on the article management page is incorrect if the article has several versions

## 1.0.33 - 2022-09-28

### Added

- New page listing all email templates and available tags for each journal at /administratemail/tagslist
  (- Mail -> TagList)
- Episciences portal:
    - New Feed RSS + Atom : latest published documents, 2 docs per journal
    - API to get a list of publishing journals: include accepted repositories.
- Episciences journals
    - Added 'journals news' RSS feed for each journal
- New journal settings parameter: Allow post-acceptance revisions of articles
- 9 article statuses, available for journals allowing 'post-acceptance revisions of articles':
    - Accepted temporary version, waiting for author's final version: now, temporary versions that are accepted have this new status instead of "pending minor revision" (git #372)
    - Accepted - waiting for author's final version
    - Accepted, waiting for major revision
    - Accepted article - final version submitted, waiting for formatting by copy editors
    - Accepted temporary version after author's modifications
    - Accepted temporary version, waiting for minor revision
    - Accepted temporary version, waiting for major revision
    - Accepted - waiting validation by the author
    - Approved by author, waiting for final publication
- User profiles:
    - Adding ORCID and affiliation to your profile is now possible + this information is diplayed on the
      "editorial staff member" (gitlab #410) & dashboard pages.
    - Social Medias & Websites profile informations.
- New 'Author' role automatically added to users that have submitted a document
- Paper list: temporary versions now have a specific label
- Dashboard: Alert for administrators on the existence of papers without assigned editors
- Display the origin of a linked data when papers are linked to a dataset
- Administration page of a paper: new shortcut for administrators :
    - to allow to sign in as a copy-editor
    - to allow to sign in as an editor
- [227](https://github.com/CCSDForge/episciences/issues/227):
    - The revision deadline is now displayed below the article's status.
    - A pictogram is added to indicate the date of the revision deadline in the article management table.

### Changed

- [#237](https://github.com/CCSDForge/episciences/issues/237) Editor comments div is now bigger
- Upgraded publication RSS Feeds with DOIs
- [#142](https://github.com/CCSDForge/episciences/issues/142): allow that administrators answer revision and copy editing requests
- It is now possible to update the metadata on the document's page
- Request a new version of an article that has already been accepted
- [#166](https://github.com/CCSDForge/episciences/issues/116): hide "guest" role and doi-settings action
- The "paper status" filter is now dynamically created.
- Prevent "Add sources files" and "Add the formatted version" buttons JS reactivation
- Moved "copy editing" section to a more usable place on the same page
- Dashboard improvements: ability to reach an article from sections: my submissions and assigned articles
- [#187](https://github.com/CCSDForge/episciences/issues/187): change the default templates: 'new version submitted' and 'tmp version submitted'
- [#188](https://github.com/CCSDForge/episciences/issues/188): force notifications
- It is now possible to update the document version in Episciences to the most recent version in the open archive.
- [#164](https://github.com/CCSDForge/episciences/issues/164): harmonization of roles/privileges.
- CWI open repository has been temporarily removed until we adapt to their new OAI server

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): capitalization of names
- [#207](https://github.com/CCSDForge/episciences/issues/207): editing the translation of Chief Editors
- [#169](https://github.com/CCSDForge/episciences/issues/169): reports become visible on the article web page:
    - according to the parameters of the journal for published articles
    - for the owner, only if the paper is refused, waiting for revision, already accepted or published
- Another status (Revision request answer: without any modifications) is used when responding to a revision request: without any modifications.
- Revision requests: now we have an immediate visual feedback when changing the deadline.
- If the journal allows revision of articles after acceptance, the response to a request for a final version (without any changes) is similar to the proposal of a new version.

### Internal refactoring

- Code Refactoring fixing of warning
- Update Episciences_User::getScreenName()
- User Table: merge AFFILIATIONS" filed in "ADDITIONAL_PROFILE_INFORMATION"
- Multiple roles: ignore the "member" role when merging two accounts.
- Email Templates updates
- Script to clean the "USER_ROLES" table
- Parameter to detect automatic emails

## 1.0.32.2 - 2022-09-28

### Changed

- [196](https://github.com/CCSDForge/episciences/issues/196): Creation of an account following an invitation with a temporary account: from now on, the Last Name, First Name and Screen Name fields will be left empty (for the reviewer to fill in)
- [RT#164153]: refactoring: now obsolete template "paper_new_version_reviewer_reassign" (Reviewer assignment to a new version of an article) is removed.

### Fixed:

- account merging incomplete due to sql error when updating 'paper_conflicts' table.

## 1.0.32.1 - 2022-09-01

### Fixed

- Fixed bug introduced with [196] "Name" => "Last Name" when creating a new reviewer
- Fixed release notes links to issues

## 1.0.32 - 2022-08-31

### Changed

- [201](https://github.com/CCSDForge/episciences/issues/201): depending on journal settings, editor can accept, ask revision, reject and publish papers in every round.
- [238](https://github.com/CCSDForge/episciences/issues/238): harmonization of terms 'sections/rubriques'.
- [196](https://github.com/CCSDForge/episciences/issues/196) now,
    - Only one 'name' field is available when creating a new reviewer
    - Reviewers are sorted by lastname by default
- Empty TAG %%RECIPIENT_SCREEN_NAME%% for users who do not have a local account in "Unanswered reviewer invitation (reviewer copy)" reminder.
- (Internal/refactoring) transition to PHP 8: refactoring: "MaxMind GeoIP2 PHP API" is now used instead of PHP module.

### Fixed

- [RT#163166]: Problem sending mail from staff page.
- Fixed [#165](https://github.com/CCSDForge/episciences/issues/165): Line breaks in paper titles in API
- Fixed [#251](https://github.com/CCSDForge/episciences/issues/251): conform to ISO 3297 for displaying ISSNs

## Added

- Episciences portal: New Feed RSS + Atom : latest published documents, 2 docs per journal
- Added 'journals news' RSS feed

## 1.0.31.1 - 2022-07-13

### Changed

- User accounts merging procedure: take into account the Conflict Of Interests

## 1.0.31 - 2022-06-28

### Added

- A pictogram is added to indicate papers with conflicts in the article management table.

### Changed

- [192](https://github.com/CCSDForge/episciences/issues/192): allow editors to "Ask for other editors opinion".
- Only confirmed conflicts will be displayed in the conflict management section

### Fixed

- Ask revision: possibility to backdate the deadline.
- Fixed: RT #160301:
  the tags [%%SENDER_FULL_NAME%%, %%SENDER_SCREEN_NAME%%, %%SENDER_EMAIL%%, %%SENDER_FIRST_NAME%%', %%SENDER_LAST_NAME%% ]
  concerning the user of the action are filled with the data of the user connected at the time of the action.
  Making these variables available in the automatic mails poses a real problem: they are filled with the data of the mail recipient.
  So, from now on, the tags mentioned above will no longer be available in the automatic mail templates.
- Shifted display because of the error message (CSRF token)

## 1.0.30 - 2022-06-20

### Fixed

- Titles do not appear in the correct language when more than one language has been entered (e.g. SLOVO )

### Added

- It is now possible to manage conflicts on the article page: display & delete them

## 1.0.29.1 - 2022-06-13

### Changed

- Revision requests: now we have an immediate visual feedback when changing the deadline

### Fixed

- [#247](https://github.com/CCSDForge/episciences/issues/247):
  HTML links in outgoing emails are made relative [#247]: TinyMCE configuration: convert all relative URLs to absolute URLs.
- Edition of a volume: The title of article might not be retrieved if the language was not managed by the platform.
- Profile editing form does not display correctly when changing language.

## 1.0.29 - 2022-06-01

### Fixed

- Failed 'Not enough reviewers - editor copy' reminders.
- But leading to load journal translations from '/'.
- PHP Warning: Use of undefined constant RVID - assumed 'RVID' (this will throw an Error in a future version of PHP)
- Not enough review reminders: articles without invitations escape reminders because the function that retrieves invitations is not used properly.

### Added

- User list: make it easier to locate accounts that have not been activated.
- Addes Roadmap link + User survey/feedback on portal
- Make EU grants and OpenAIRE support more visible at the bottom of the page

## 1.0.28.5 - 2022-05-25

### Fixed

- [RT#158293]: article status is not correctly updated

## 1.0.28.4 - 2022-05-09

### Fixed

- File not found for Temporary Versions without attached files + refactoring.

## 1.0.28.3 - 2022-04-13

### Changed

- Updated error message when using a HAL identifier without any file.
- [#142](https://github.com/CCSDForge/episciences/issues/142)

## 1.0.28.2 - 2022-04-06

### Fixed

- Empty records from HAL repository: error message improved.
- Improvements and fixes on Datacite and Crossref XML exports

## 1.0.28.1 - 2022-03-23

### Fixed

- fix issue in crossref xml format with related_item

## 1.0.28 - 2022-03-23

### Changed

- Bump crossref schema to ver 4.8.1
- arXiv URLs now links to https version instead of http

### Added

- Crossref metadata export format for DOI:
    - add previous version URLs, link with relationship 'hasPreprint' to link preprints versions with the published version
    - add arXiv DOIs, link to journal's published DOI with 'isSameAs' relationship
    - add full text PDF URLs for Crossref 'Similarity Check' service

## 1.0.27.5 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format
- Adding Polyfill PHP 8.0

## 1.0.27.4 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format

## 1.0.27.3 - 2022-03-18

### Fixed

- Ratings submitted late after the start of the layout process caused the status of the article to be updated (rolled back).
- There is no need to review an article while it is being formatted by the author.

## 1.0.27.2 - 2022-03-18

### Fixed

- "Copy editor" role not authorized accessing to the public page of the paper
  and report a conflict of interest, if the option has been activated by the journal

## 1.0.27.1 - 2022-03-16

### Fixed

- Exclude the "paper-status" directory from the journal's resources.
- Content that is copied and pasted when reviewing an article may lose end of line.
- The linked data to an article is not deleted when the author deletes his own article

## 1.0.27 - 2022-03-14

# Changed

- Reviewer invitation: Users are now sorted by Name, First Name instead of First Name

# Fixed

- Dashboard: the number of papers assigned to the copy editor includes obsolete papers
- Issue reported to support RT #148133 (fixed before this release):
    - Some editors could see the reviewers names if they were also authors of the article

## 1.0.26 - 2022-03-01

# Fixed

- Issue reported to support RT #148133:
    - Incorrect "Reminder Delay: The "Reminder Delay" should correspond, in number of days, to the difference between the date the reminder was sent and the date the invitation was sent.
- Issue reported to support RT #148466:
    - Sorting by date in the Dashboard is wrong: Enabling the conflict of interest (COI) option distorts the pagination

## 1.0.25.1 - 2022-02-15

# Changed

[#152](https://github.com/CCSDForge/episciences/issues/152): page footer modifications.

## 1.0.25 - 2022-02-15

# Fixed

- All submissions are accessible to "guest editor" roles.
- Language bar missing when editing a mail template, including reminders.

## 1.0.24.2 - 2022-02-10

# Fixed

- [RT#146987] "search" button no longer works
- application error: syntax error or access violation if CAS user not found

## 1.0.24.1 - 2022-02-08

# Fixed

- A bug in the reminders code triggered emails with unusable HTTP links

## 1.0.24 - 2022-02-08

# Fixed

- Inability to delete a volume (RT#145178): reset the volume of the previous version when submitting a new version.
- [#117](https://github.com/CCSDForge/episciences/issues/117) Improvements in emailing
- Undetected inactive accounts: Invite a reviewer > new reviewer > Invite a new user.
- Not allowing the author to be invited: Invite a reviewer > new reviewer > Invite a new user.
- The attachments to the rating report are not available [RT#145473]
- Answer revision request: in some situations, the "Submit" button remains inactive.

## 1.0.23 - 2022-01-19

### Added

- [#116](https://github.com/CCSDForge/episciences/issues/116) Release version is displayed on the interface (dashboard and page footer)
- Editors' choice at time of submission: informative text added. (gitlab #369)
- [#143](https://github.com/CCSDForge/episciences/issues/143) New DOI setting: Allow switching off manual or automatic DOI assignation (disabled mode)
- [#37](https://github.com/CCSDForge/episciences/issues/37)
    - Browse by Volume or Sections : Handle new content type 'application/json' to return json instead of html
    - On URLs like /volume/view/id/314159 and /section/view/id/314159 Handle new content type 'application/json' to return json instead of html (only published content)
    - On URLs like /volume/edit?id=314159 Handle new content type 'application/json' to return json instead of html but including all statuses of articles ; an authenticated and allowed user is required
    - JSON added to the list of public export formats
- Enhanced information message for statistics

### Changed

- UI/UX: Make the version number more explicit when proposing a new version
- Code Refactoring fixing of warning
- Dumping default data for table `MAIL_TEMPLATE`

### Fixed

- [#126](https://github.com/CCSDForge/episciences/issues/126) Clicking outside the window to compose an email closes the window with no confirmation #126
- [#117](https://github.com/CCSDForge/episciences/issues/117) Mailing bug: loss of the mail when you forget to put a recipient (gitlab #343)
- [#149](https://github.com/CCSDForge/episciences/issues/149) Emails and default language selection: language of the sender and the recipient could be a problem if the language of the sender is different from the language of the recipient (gitlab #402)
- Inconsistent REMINDER_DELAY: the displayed value is the "delay" parameter of the reminder instead of the calculated value (interval in number of days between the time the reminder was sent and the deadline).
- New submission: if the "submission date" metadata is empty: do not display it anymore.
- [#124](https://github.com/CCSDForge/episciences/issues/124): refining automatic notifications when abandoning submission
- RT#144252: better cleaning of attached file names
- RT#144246: an inactive account could still be visible on the Editorial Staff members page.
- [#117](https://github.com/CCSDForge/episciences/issues/117): An empty e-mail is not sent or received
- [#129](https://github.com/CCSDForge/episciences/issues/129): when you do a search from the mail history page nothing happens.
- [#141](https://github.com/CCSDForge/episciences/issues/141): visibility of pages menu is wrong inside menu editing.

### Changed

- [#126](https://github.com/CCSDForge/episciences/issues/126): prevent modal closure: disabling the click outside the modal area and by pressing Esc
- Enabling the "COI" option overrides the "Encapsulate editors" option

## 1.0.38.5 - 2022-12-14

### Fixed

- [#294](https://github.com/CCSDForge/episciences/issues/294): untranslated text
- API to return number of published documents

## 1.0.38.4 - 2022-12-12

### Fixed

- JS error: Failed to load plugin url: https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/langs/fr_FR.js
  @see https://www.tiny.cloud/docs-4x/configure/localization/#language
- Add %%PAPER_REP_URL%% tag to the description of the invitation templates.
- Change password: redirect to authentication if user is not logged in
- The '%%SENDER_SCREEN_NAME%%' tag is always present in the 'paper_paper_editor_refused_monitoring' template

## 1.0.38.3 - 2022-11-30

### Fixed

- Html filtering problem that breaks the display in the comments

## 1.0.38.2 - 2022-11-24

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): There is a difference between the capitalization of the first and last names in the user's profile
  and the first and last names of the user in the email recipient field [RT#148137]
- Fixed the fact that the Administrator role prevents testing for conflict of interest
- Error: $(...).fileupload is not a function for published articles.

### Changed

- Conflict management section: set default display lines to 5.
- Mailing > getContacts: add 'Authors' filter.
- resolved [#282](https://github.com/CCSDForge/episciences/issues/282): C:\fakepath: This is the security
  implementation of the browser: the browser is protecting you from accessing your disk structure.
- Code review / refactoring

## 1.0.38.1 - 2022-11-16

### Fixed

- Added missing translation for conflict of interest

### Changed

- Footer: replaced TOS with Term Of Use

## 1.0.38 - 2022-11-16

### Added

- New footer link to the Term of use (between the platform and users)

### Fixed

- Fixed ORCID 'X' in DOAJ export format, make it compliant with ORCID specification
- [RT#171463]: the reviews should not be seen by someone with a CoI declaration with
  the paper: this fix concerns all the paper's details.

### Changed

- UI/UX: Improved rendering of the "conflict management" section
- [RT#170200]: confirmation of the absence of conflict of interest: it is now possible to cancel this type of response.

## 1.0.37 - 2022-11-10

### Added

- New feature: [Open Science Lens](https://www.opensciencelens.eu/) feature available as a preview on selected journals

### Changed

- Improved rendering of volumes

## 1.0.36 - 2022-11-09

### Added

- Improved rendering of the volume page
- authors can now enter their paper password provided by the open archive on the article page.
- alt attribute to volume's image.
- Added an API method to get the number of users accounts

### Changed

- Optimization: there is no need to check for conflicts at the time of submission
- [#280](https://github.com/CCSDForge/episciences/issues/280): set spellcheck to false in dynamic datatable search boxes.
- [#281](https://github.com/CCSDForge/episciences/issues/281): Submit an article > Guidelines section: harmonization of journal/review terms [FR]
- Now, when an article is accepted, all unanswered invitations are deleted. The reviewer is informed of this action.
- Add 'ISSN pending' until the ISSN is actually issued

### Fixed

- Fix incorrect HTML in footer
- Incorrect paper status LMCS #10145 (related to https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#10273---2022-03-18)
- Empty %%REQUESTER_EXPRESSION%% tag (related to [#187](https://github.com/CCSDForge/episciences/issues/187)
- Incorrect status jdmdh#10203 (related with git#372)
- Fixed [#RT169943]: the tag %%SENDER_SCREEN_NAME%% (obsolete) that should have contained the editor's screen name has not been replaced:
  to be replaced in custom templates by %%EDITOR_FULL_NAME%% or %%EDITOR_SCREEN_NAME%%
  (@see https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#fixed-11 [RT##160301])

## 1.0.35 - 2022-10-19

### Added

- [RT#169107]: Feature - new option: do not allow the selection of an editor-in-chief when the author has the option to
  propose an editor at the time of submission.

### Changed

- [#255](https://github.com/CCSDForge/episciences/issues/255) Display ratings reports on the article page: Label updated

### Fixed

- [RT#169088]: Editors-in-chief and editorial secretaries are no longer notified of new submissions.

## 1.0.34.2 - 2022-10-18

### Fixed

- temporary fix: TinyMCE: loss of formatting for successive mailings
- Insert data in 'T_PAPER_DATASETS' table: MySql insert 0 instead of NULL
- [#273](https://github.com/CCSDForge/episciences/issues/273): if the editor changes the rating due date, then this
  is not reflected in the message.
- Fixes in exports format related to trailing ',' and language codes

## 1.0.34.1 - 2022-10-16

### Fixed

- Fixed bad JS escape of authors in reviewer invitation form
- Fixed Handle URLs links adding https://hdl.handle.net/
- Fixed display of disabled buttons
- Fixed 'Description of affiliation block'

### Added

- The activation of maintenance alerts is now configurable

## 1.0.34 - 2022-10-13

### Added

- Article managers (authors, editors, etc.) may add new optional - but recommended - metadata to the documents
    - [ORCID](https://orcid.org/)
    - Affiliations: for affiliations the [ROR](https://ror.org/) or a free text may be used. If an institution is not available in the ROR, a
      simple text entry may be used.
- Automatic enrichment of metadata:
    - Licenses: retrieved from [Datacite](https://datacite.org/), [OpenAIRE Research Graph](https://graph.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/)
    - ORCID: retrieved from [OpenAIRE Research Graph](https://graph.openaire.eu/)
    - Linked Datasets: [Scholexplorer](https://scholexplorer.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/): Datasets linked to Episciences
      publications
    - Funding: Research projects from [OpenAIRE Research Graph](https://graph.openaire.eu/) and [HAL](https://hal.archives-ouvertes.fr/)
    - Citations: Citations of published document retrieved with [OpenCitations](https://opencitations.net/) APIs
- Exports formats:
    - [DOAJ](https://doaj.org/about/) (export one document or a whole published volume) using DOAJ Schema
    - The new collected metadata have been added to Datacite, [Crossref](https://www.crossref.org/) and DOAJ export formats
- Browse by volume: new parameter to allow to display empty volumes. Default value is still no (only show volumes
  with papers published in the volume)
- [#186](https://github.com/CCSDForge/episciences/issues/186): Editors-in-chief can now report a conflict of
  interest. (Sign in an admin as another user: the real identity is now saved.)

### Fixed

- Fixed a display bug on the portal in connected mode (used by Episciences staff only)
- Failed to edit another account's profile from the user management page.
- impossibility to validate the profile if the user does not have an account in the application

### Changed

- new parameter for enabling/disabling submission from the archive to the application and vice versa.

## 1.0.33.2 - 2022-10-05

### Fixed

- RT#167820: all occurrences of tags in a template will be replaced by their real values.

## 1.0.33.1 - 2022-09-29

### Fixed

- Allow editors to view the list of conflicts on the article management page
- The number of conflicts indicated on the article management page is incorrect if the article has several versions

## 1.0.33 - 2022-09-28

### Added

- New page listing all email templates and available tags for each journal at /administratemail/tagslist
  (- Mail -> TagList)
- Episciences portal:
    - New Feed RSS + Atom : latest published documents, 2 docs per journal
    - API to get a list of publishing journals: include accepted repositories.
- Episciences journals
    - Added 'journals news' RSS feed for each journal
- New journal settings parameter: Allow post-acceptance revisions of articles
- 9 article statuses, available for journals allowing 'post-acceptance revisions of articles':
    - Accepted temporary version, waiting for author's final version: now, temporary versions that are accepted have this new status instead of "pending minor revision" (git #372)
    - Accepted - waiting for author's final version
    - Accepted, waiting for major revision
    - Accepted article - final version submitted, waiting for formatting by copy editors
    - Accepted temporary version after author's modifications
    - Accepted temporary version, waiting for minor revision
    - Accepted temporary version, waiting for major revision
    - Accepted - waiting validation by the author
    - Approved by author, waiting for final publication
- User profiles:
    - Adding ORCID and affiliation to your profile is now possible + this information is diplayed on the
      "editorial staff member" (gitlab #410) & dashboard pages.
    - Social Medias & Websites profile informations.
- New 'Author' role automatically added to users that have submitted a document
- Paper list: temporary versions now have a specific label
- Dashboard: Alert for administrators on the existence of papers without assigned editors
- Display the origin of a linked data when papers are linked to a dataset
- Administration page of a paper: new shortcut for administrators :
    - to allow to sign in as a copy-editor
    - to allow to sign in as an editor
- [227](https://github.com/CCSDForge/episciences/issues/227):
    - The revision deadline is now displayed below the article's status.
    - A pictogram is added to indicate the date of the revision deadline in the article management table.

### Changed

- [#237](https://github.com/CCSDForge/episciences/issues/237) Editor comments div is now bigger
- Upgraded publication RSS Feeds with DOIs
- [#142](https://github.com/CCSDForge/episciences/issues/142): allow that administrators answer revision and copy editing requests
- It is now possible to update the metadata on the document's page
- Request a new version of an article that has already been accepted
- [#166](https://github.com/CCSDForge/episciences/issues/116): hide "guest" role and doi-settings action
- The "paper status" filter is now dynamically created.
- Prevent "Add sources files" and "Add the formatted version" buttons JS reactivation
- Moved "copy editing" section to a more usable place on the same page
- Dashboard improvements: ability to reach an article from sections: my submissions and assigned articles
- [#187](https://github.com/CCSDForge/episciences/issues/187): change the default templates: 'new version submitted' and 'tmp version submitted'
- [#188](https://github.com/CCSDForge/episciences/issues/188): force notifications
- It is now possible to update the document version in Episciences to the most recent version in the open archive.
- [#164](https://github.com/CCSDForge/episciences/issues/164): harmonization of roles/privileges.
- CWI open repository has been temporarily removed until we adapt to their new OAI server

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): capitalization of names
- [#207](https://github.com/CCSDForge/episciences/issues/207): editing the translation of Chief Editors
- [#169](https://github.com/CCSDForge/episciences/issues/169): reports become visible on the article web page:
    - according to the parameters of the journal for published articles
    - for the owner, only if the paper is refused, waiting for revision, already accepted or published
- Another status (Revision request answer: without any modifications) is used when responding to a revision request: without any modifications.
- Revision requests: now we have an immediate visual feedback when changing the deadline.
- If the journal allows revision of articles after acceptance, the response to a request for a final version (without any changes) is similar to the proposal of a new version.

### Internal refactoring

- Code Refactoring fixing of warning
- Update Episciences_User::getScreenName()
- User Table: merge AFFILIATIONS" filed in "ADDITIONAL_PROFILE_INFORMATION"
- Multiple roles: ignore the "member" role when merging two accounts.
- Email Templates updates
- Script to clean the "USER_ROLES" table
- Parameter to detect automatic emails

## 1.0.32.2 - 2022-09-28

### Changed

- [196](https://github.com/CCSDForge/episciences/issues/196): Creation of an account following an invitation with a temporary account: from now on, the Last Name, First Name and Screen Name fields will be left empty (for the reviewer to fill in)
- [RT#164153]: refactoring: now obsolete template "paper_new_version_reviewer_reassign" (Reviewer assignment to a new version of an article) is removed.

### Fixed:

- account merging incomplete due to sql error when updating 'paper_conflicts' table.

## 1.0.32.1 - 2022-09-01

### Fixed

- Fixed bug introduced with [196] "Name" => "Last Name" when creating a new reviewer
- Fixed release notes links to issues

## 1.0.32 - 2022-08-31

### Changed

- [201](https://github.com/CCSDForge/episciences/issues/201): depending on journal settings, editor can accept, ask revision, reject and publish papers in every round.
- [238](https://github.com/CCSDForge/episciences/issues/238): harmonization of terms 'sections/rubriques'.
- [196](https://github.com/CCSDForge/episciences/issues/196) now,
    - Only one 'name' field is available when creating a new reviewer
    - Reviewers are sorted by lastname by default
- Empty TAG %%RECIPIENT_SCREEN_NAME%% for users who do not have a local account in "Unanswered reviewer invitation (reviewer copy)" reminder.
- (Internal/refactoring) transition to PHP 8: refactoring: "MaxMind GeoIP2 PHP API" is now used instead of PHP module.

### Fixed

- [RT#163166]: Problem sending mail from staff page.
- Fixed [#165](https://github.com/CCSDForge/episciences/issues/165): Line breaks in paper titles in API
- Fixed [#251](https://github.com/CCSDForge/episciences/issues/251): conform to ISO 3297 for displaying ISSNs

## Added

- Episciences portal: New Feed RSS + Atom : latest published documents, 2 docs per journal
- Added 'journals news' RSS feed

## 1.0.31.1 - 2022-07-13

### Changed

- User accounts merging procedure: take into account the Conflict Of Interests

## 1.0.31 - 2022-06-28

### Added

- A pictogram is added to indicate papers with conflicts in the article management table.

### Changed

- [192](https://github.com/CCSDForge/episciences/issues/192): allow editors to "Ask for other editors opinion".
- Only confirmed conflicts will be displayed in the conflict management section

### Fixed

- Ask revision: possibility to backdate the deadline.
- Fixed: RT #160301:
  the tags [%%SENDER_FULL_NAME%%, %%SENDER_SCREEN_NAME%%, %%SENDER_EMAIL%%, %%SENDER_FIRST_NAME%%', %%SENDER_LAST_NAME%% ]
  concerning the user of the action are filled with the data of the user connected at the time of the action.
  Making these variables available in the automatic mails poses a real problem: they are filled with the data of the mail recipient.
  So, from now on, the tags mentioned above will no longer be available in the automatic mail templates.
- Shifted display because of the error message (CSRF token)

## 1.0.30 - 2022-06-20

### Fixed

- Titles do not appear in the correct language when more than one language has been entered (e.g. SLOVO )

### Added

- It is now possible to manage conflicts on the article page: display & delete them

## 1.0.29.1 - 2022-06-13

### Changed

- Revision requests: now we have an immediate visual feedback when changing the deadline

### Fixed

- [#247](https://github.com/CCSDForge/episciences/issues/247):
  HTML links in outgoing emails are made relative [#247]: TinyMCE configuration: convert all relative URLs to absolute URLs.
- Edition of a volume: The title of article might not be retrieved if the language was not managed by the platform.
- Profile editing form does not display correctly when changing language.

## 1.0.29 - 2022-06-01

### Fixed

- Failed 'Not enough reviewers - editor copy' reminders.
- But leading to load journal translations from '/'.
- PHP Warning: Use of undefined constant RVID - assumed 'RVID' (this will throw an Error in a future version of PHP)
- Not enough review reminders: articles without invitations escape reminders because the function that retrieves invitations is not used properly.

### Added

- User list: make it easier to locate accounts that have not been activated.
- Addes Roadmap link + User survey/feedback on portal
- Make EU grants and OpenAIRE support more visible at the bottom of the page

## 1.0.28.5 - 2022-05-25

### Fixed

- [RT#158293]: article status is not correctly updated

## 1.0.28.4 - 2022-05-09

### Fixed

- File not found for Temporary Versions without attached files + refactoring.

## 1.0.28.3 - 2022-04-13

### Changed

- Updated error message when using a HAL identifier without any file.
- [#142](https://github.com/CCSDForge/episciences/issues/142)

## 1.0.28.2 - 2022-04-06

### Fixed

- Empty records from HAL repository: error message improved.
- Improvements and fixes on Datacite and Crossref XML exports

## 1.0.28.1 - 2022-03-23

### Fixed

- fix issue in crossref xml format with related_item

## 1.0.28 - 2022-03-23

### Changed

- Bump crossref schema to ver 4.8.1
- arXiv URLs now links to https version instead of http

### Added

- Crossref metadata export format for DOI:
    - add previous version URLs, link with relationship 'hasPreprint' to link preprints versions with the published version
    - add arXiv DOIs, link to journal's published DOI with 'isSameAs' relationship
    - add full text PDF URLs for Crossref 'Similarity Check' service

## 1.0.27.5 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format
- Adding Polyfill PHP 8.0

## 1.0.27.4 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format

## 1.0.27.3 - 2022-03-18

### Fixed

- Ratings submitted late after the start of the layout process caused the status of the article to be updated (rolled back).
- There is no need to review an article while it is being formatted by the author.

## 1.0.27.2 - 2022-03-18

### Fixed

- "Copy editor" role not authorized accessing to the public page of the paper
  and report a conflict of interest, if the option has been activated by the journal

## 1.0.27.1 - 2022-03-16

### Fixed

- Exclude the "paper-status" directory from the journal's resources.
- Content that is copied and pasted when reviewing an article may lose end of line.
- The linked data to an article is not deleted when the author deletes his own article

## 1.0.27 - 2022-03-14

# Changed

- Reviewer invitation: Users are now sorted by Name, First Name instead of First Name

# Fixed

- Dashboard: the number of papers assigned to the copy editor includes obsolete papers
- Issue reported to support RT #148133 (fixed before this release):
    - Some editors could see the reviewers names if they were also authors of the article

## 1.0.26 - 2022-03-01

# Fixed

- Issue reported to support RT #148133:
    - Incorrect "Reminder Delay: The "Reminder Delay" should correspond, in number of days, to the difference between the date the reminder was sent and the date the invitation was sent.
- Issue reported to support RT #148466:
    - Sorting by date in the Dashboard is wrong: Enabling the conflict of interest (COI) option distorts the pagination

## 1.0.25.1 - 2022-02-15

# Changed

[#152](https://github.com/CCSDForge/episciences/issues/152): page footer modifications.

## 1.0.25 - 2022-02-15

# Fixed

- All submissions are accessible to "guest editor" roles.
- Language bar missing when editing a mail template, including reminders.

## 1.0.24.2 - 2022-02-10

# Fixed

- [RT#146987] "search" button no longer works
- application error: syntax error or access violation if CAS user not found

## 1.0.24.1 - 2022-02-08

# Fixed

- A bug in the reminders code triggered emails with unusable HTTP links

## 1.0.24 - 2022-02-08

# Fixed

- Inability to delete a volume (RT#145178): reset the volume of the previous version when submitting a new version.
- [#117](https://github.com/CCSDForge/episciences/issues/117) Improvements in emailing
- Undetected inactive accounts: Invite a reviewer > new reviewer > Invite a new user.
- Not allowing the author to be invited: Invite a reviewer > new reviewer > Invite a new user.
- The attachments to the rating report are not available [RT#145473]
- Answer revision request: in some situations, the "Submit" button remains inactive.

## 1.0.23 - 2022-01-19

### Added

- [#116](https://github.com/CCSDForge/episciences/issues/116) Release version is displayed on the interface (dashboard and page footer)
- Editors' choice at time of submission: informative text added. (gitlab #369)
- [#143](https://github.com/CCSDForge/episciences/issues/143) New DOI setting: Allow switching off manual or automatic DOI assignation (disabled mode)
- [#37](https://github.com/CCSDForge/episciences/issues/37)
    - Browse by Volume or Sections : Handle new content type 'application/json' to return json instead of html
    - On URLs like /volume/view/id/314159 and /section/view/id/314159 Handle new content type 'application/json' to return json instead of html (only published content)
    - On URLs like /volume/edit?id=314159 Handle new content type 'application/json' to return json instead of html but including all statuses of articles ; an authenticated and allowed user is required
    - JSON added to the list of public export formats
- Enhanced information message for statistics

### Changed

- UI/UX: Make the version number more explicit when proposing a new version
- Code Refactoring fixing of warning
- Dumping default data for table `MAIL_TEMPLATE`

### Fixed

- [#126](https://github.com/CCSDForge/episciences/issues/126) Clicking outside the window to compose an email closes the window with no confirmation #126
- [#117](https://github.com/CCSDForge/episciences/issues/117) Mailing bug: loss of the mail when you forget to put a recipient (gitlab #343)
- [#149](https://github.com/CCSDForge/episciences/issues/149) Emails and default language selection: language of the sender and the recipient could be a problem if the language of the sender is different from the language of the recipient (gitlab #402)
- Inconsistent REMINDER_DELAY: the displayed value is the "delay" parameter of the reminder instead of the calculated value (interval in number of days between the time the reminder was sent and the deadline).
- New submission: if the "submission date" metadata is empty: do not display it anymore.
- [#124](https://github.com/CCSDForge/episciences/issues/124): refining automatic notifications when abandoning submission
- RT#144252: better cleaning of attached file names
- RT#144246: an inactive account could still be visible on the Editorial Staff members page.
- [#117](https://github.com/CCSDForge/episciences/issues/117): An empty e-mail is not sent or received
- [#129](https://github.com/CCSDForge/episciences/issues/129): when you do a search from the mail history page nothing happens.
- [#141](https://github.com/CCSDForge/episciences/issues/141): visibility of pages menu is wrong inside menu editing.

### Changed

- [#126](https://github.com/CCSDForge/episciences/issues/126): prevent modal closure: disabling the click outside the modal area and by pressing Esc
- Enabling the "COI" option overrides the "Encapsulate editors" option

## 1.0.38.5 - 2022-12-14

### Fixed

- [#294](https://github.com/CCSDForge/episciences/issues/294): untranslated text
- API to return number of published documents

## 1.0.38.4 - 2022-12-12

### Fixed

- JS error: Failed to load plugin url: https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/langs/fr_FR.js
  @see https://www.tiny.cloud/docs-4x/configure/localization/#language
- Add %%PAPER_REP_URL%% tag to the description of the invitation templates.
- Change password: redirect to authentication if user is not logged in
- The '%%SENDER_SCREEN_NAME%%' tag is always present in the 'paper_paper_editor_refused_monitoring' template

## 1.0.38.3 - 2022-11-30

### Fixed

- Html filtering problem that breaks the display in the comments

## 1.0.38.2 - 2022-11-24

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): There is a difference between the capitalization of the first and last names in the user's profile
  and the first and last names of the user in the email recipient field [RT#148137]
- Fixed the fact that the Administrator role prevents testing for conflict of interest
- Error: $(...).fileupload is not a function for published articles.

### Changed

- Conflict management section: set default display lines to 5.
- Mailing > getContacts: add 'Authors' filter.
- resolved [#282](https://github.com/CCSDForge/episciences/issues/282): C:\fakepath: This is the security
  implementation of the browser: the browser is protecting you from accessing your disk structure.
- Code review / refactoring

## 1.0.38.1 - 2022-11-16

### Fixed

- Added missing translation for conflict of interest

### Changed

- Footer: replaced TOS with Term Of Use

## 1.0.38 - 2022-11-16

### Added

- New footer link to the Term of use (between the platform and users)

### Fixed

- Fixed ORCID 'X' in DOAJ export format, make it compliant with ORCID specification
- [RT#171463]: the reviews should not be seen by someone with a CoI declaration with
  the paper: this fix concerns all the paper's details.

### Changed

- UI/UX: Improved rendering of the "conflict management" section
- [RT#170200]: confirmation of the absence of conflict of interest: it is now possible to cancel this type of response.

## 1.0.37 - 2022-11-10

### Added

- New feature: [Open Science Lens](https://www.opensciencelens.eu/) feature available as a preview on selected journals

### Changed

- Improved rendering of volumes

## 1.0.36 - 2022-11-09

### Added

- Improved rendering of the volume page
- authors can now enter their paper password provided by the open archive on the article page.
- alt attribute to volume's image.
- Added an API method to get the number of users accounts

### Changed

- Optimization: there is no need to check for conflicts at the time of submission
- [#280](https://github.com/CCSDForge/episciences/issues/280): set spellcheck to false in dynamic datatable search boxes.
- [#281](https://github.com/CCSDForge/episciences/issues/281): Submit an article > Guidelines section: harmonization of journal/review terms [FR]
- Now, when an article is accepted, all unanswered invitations are deleted. The reviewer is informed of this action.
- Add 'ISSN pending' until the ISSN is actually issued

### Fixed

- Fix incorrect HTML in footer
- Incorrect paper status LMCS #10145 (related to https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#10273---2022-03-18)
- Empty %%REQUESTER_EXPRESSION%% tag (related to [#187](https://github.com/CCSDForge/episciences/issues/187)
- Incorrect status jdmdh#10203 (related with git#372)
- Fixed [#RT169943]: the tag %%SENDER_SCREEN_NAME%% (obsolete) that should have contained the editor's screen name has not been replaced:
  to be replaced in custom templates by %%EDITOR_FULL_NAME%% or %%EDITOR_SCREEN_NAME%%
  (@see https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#fixed-11 [RT##160301])

## 1.0.35 - 2022-10-19

### Added

- [RT#169107]: Feature - new option: do not allow the selection of an editor-in-chief when the author has the option to
  propose an editor at the time of submission.

### Changed

- [#255](https://github.com/CCSDForge/episciences/issues/255) Display ratings reports on the article page: Label updated

### Fixed

- [RT#169088]: Editors-in-chief and editorial secretaries are no longer notified of new submissions.

## 1.0.34.2 - 2022-10-18

### Fixed

- temporary fix: TinyMCE: loss of formatting for successive mailings
- Insert data in 'T_PAPER_DATASETS' table: MySql insert 0 instead of NULL
- [#273](https://github.com/CCSDForge/episciences/issues/273): if the editor changes the rating due date, then this
  is not reflected in the message.
- Fixes in exports format related to trailing ',' and language codes

## 1.0.34.1 - 2022-10-16

### Fixed

- Fixed bad JS escape of authors in reviewer invitation form
- Fixed Handle URLs links adding https://hdl.handle.net/
- Fixed display of disabled buttons
- Fixed 'Description of affiliation block'

### Added

- The activation of maintenance alerts is now configurable

## 1.0.34 - 2022-10-13

### Added

- Article managers (authors, editors, etc.) may add new optional - but recommended - metadata to the documents
    - [ORCID](https://orcid.org/)
    - Affiliations: for affiliations the [ROR](https://ror.org/) or a free text may be used. If an institution is not available in the ROR, a
      simple text entry may be used.
- Automatic enrichment of metadata:
    - Licenses: retrieved from [Datacite](https://datacite.org/), [OpenAIRE Research Graph](https://graph.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/)
    - ORCID: retrieved from [OpenAIRE Research Graph](https://graph.openaire.eu/)
    - Linked Datasets: [Scholexplorer](https://scholexplorer.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/): Datasets linked to Episciences
      publications
    - Funding: Research projects from [OpenAIRE Research Graph](https://graph.openaire.eu/) and [HAL](https://hal.archives-ouvertes.fr/)
    - Citations: Citations of published document retrieved with [OpenCitations](https://opencitations.net/) APIs
- Exports formats:
    - [DOAJ](https://doaj.org/about/) (export one document or a whole published volume) using DOAJ Schema
    - The new collected metadata have been added to Datacite, [Crossref](https://www.crossref.org/) and DOAJ export formats
- Browse by volume: new parameter to allow to display empty volumes. Default value is still no (only show volumes
  with papers published in the volume)
- [#186](https://github.com/CCSDForge/episciences/issues/186): Editors-in-chief can now report a conflict of
  interest. (Sign in an admin as another user: the real identity is now saved.)

### Fixed

- Fixed a display bug on the portal in connected mode (used by Episciences staff only)
- Failed to edit another account's profile from the user management page.
- impossibility to validate the profile if the user does not have an account in the application

### Changed

- new parameter for enabling/disabling submission from the archive to the application and vice versa.

## 1.0.33.2 - 2022-10-05

### Fixed

- RT#167820: all occurrences of tags in a template will be replaced by their real values.

## 1.0.33.1 - 2022-09-29

### Fixed

- Allow editors to view the list of conflicts on the article management page
- The number of conflicts indicated on the article management page is incorrect if the article has several versions

## 1.0.33 - 2022-09-28

### Added

- New page listing all email templates and available tags for each journal at /administratemail/tagslist
  (- Mail -> TagList)
- Episciences portal:
    - New Feed RSS + Atom : latest published documents, 2 docs per journal
    - API to get a list of publishing journals: include accepted repositories.
- Episciences journals
    - Added 'journals news' RSS feed for each journal
- New journal settings parameter: Allow post-acceptance revisions of articles
- 9 article statuses, available for journals allowing 'post-acceptance revisions of articles':
    - Accepted temporary version, waiting for author's final version: now, temporary versions that are accepted have this new status instead of "pending minor revision" (git #372)
    - Accepted - waiting for author's final version
    - Accepted, waiting for major revision
    - Accepted article - final version submitted, waiting for formatting by copy editors
    - Accepted temporary version after author's modifications
    - Accepted temporary version, waiting for minor revision
    - Accepted temporary version, waiting for major revision
    - Accepted - waiting validation by the author
    - Approved by author, waiting for final publication
- User profiles:
    - Adding ORCID and affiliation to your profile is now possible + this information is diplayed on the
      "editorial staff member" (gitlab #410) & dashboard pages.
    - Social Medias & Websites profile informations.
- New 'Author' role automatically added to users that have submitted a document
- Paper list: temporary versions now have a specific label
- Dashboard: Alert for administrators on the existence of papers without assigned editors
- Display the origin of a linked data when papers are linked to a dataset
- Administration page of a paper: new shortcut for administrators :
    - to allow to sign in as a copy-editor
    - to allow to sign in as an editor
- [227](https://github.com/CCSDForge/episciences/issues/227):
    - The revision deadline is now displayed below the article's status.
    - A pictogram is added to indicate the date of the revision deadline in the article management table.

### Changed

- [#237](https://github.com/CCSDForge/episciences/issues/237) Editor comments div is now bigger
- Upgraded publication RSS Feeds with DOIs
- [#142](https://github.com/CCSDForge/episciences/issues/142): allow that administrators answer revision and copy editing requests
- It is now possible to update the metadata on the document's page
- Request a new version of an article that has already been accepted
- [#166](https://github.com/CCSDForge/episciences/issues/116): hide "guest" role and doi-settings action
- The "paper status" filter is now dynamically created.
- Prevent "Add sources files" and "Add the formatted version" buttons JS reactivation
- Moved "copy editing" section to a more usable place on the same page
- Dashboard improvements: ability to reach an article from sections: my submissions and assigned articles
- [#187](https://github.com/CCSDForge/episciences/issues/187): change the default templates: 'new version submitted' and 'tmp version submitted'
- [#188](https://github.com/CCSDForge/episciences/issues/188): force notifications
- It is now possible to update the document version in Episciences to the most recent version in the open archive.
- [#164](https://github.com/CCSDForge/episciences/issues/164): harmonization of roles/privileges.
- CWI open repository has been temporarily removed until we adapt to their new OAI server

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): capitalization of names
- [#207](https://github.com/CCSDForge/episciences/issues/207): editing the translation of Chief Editors
- [#169](https://github.com/CCSDForge/episciences/issues/169): reports become visible on the article web page:
    - according to the parameters of the journal for published articles
    - for the owner, only if the paper is refused, waiting for revision, already accepted or published
- Another status (Revision request answer: without any modifications) is used when responding to a revision request: without any modifications.
- Revision requests: now we have an immediate visual feedback when changing the deadline.
- If the journal allows revision of articles after acceptance, the response to a request for a final version (without any changes) is similar to the proposal of a new version.

### Internal refactoring

- Code Refactoring fixing of warning
- Update Episciences_User::getScreenName()
- User Table: merge AFFILIATIONS" filed in "ADDITIONAL_PROFILE_INFORMATION"
- Multiple roles: ignore the "member" role when merging two accounts.
- Email Templates updates
- Script to clean the "USER_ROLES" table
- Parameter to detect automatic emails

## 1.0.32.2 - 2022-09-28

### Changed

- [196](https://github.com/CCSDForge/episciences/issues/196): Creation of an account following an invitation with a temporary account: from now on, the Last Name, First Name and Screen Name fields will be left empty (for the reviewer to fill in)
- [RT#164153]: refactoring: now obsolete template "paper_new_version_reviewer_reassign" (Reviewer assignment to a new version of an article) is removed.

### Fixed:

- account merging incomplete due to sql error when updating 'paper_conflicts' table.

## 1.0.32.1 - 2022-09-01

### Fixed

- Fixed bug introduced with [196] "Name" => "Last Name" when creating a new reviewer
- Fixed release notes links to issues

## 1.0.32 - 2022-08-31

### Changed

- [201](https://github.com/CCSDForge/episciences/issues/201): depending on journal settings, editor can accept, ask revision, reject and publish papers in every round.
- [238](https://github.com/CCSDForge/episciences/issues/238): harmonization of terms 'sections/rubriques'.
- [196](https://github.com/CCSDForge/episciences/issues/196) now,
    - Only one 'name' field is available when creating a new reviewer
    - Reviewers are sorted by lastname by default
- Empty TAG %%RECIPIENT_SCREEN_NAME%% for users who do not have a local account in "Unanswered reviewer invitation (reviewer copy)" reminder.
- (Internal/refactoring) transition to PHP 8: refactoring: "MaxMind GeoIP2 PHP API" is now used instead of PHP module.

### Fixed

- [RT#163166]: Problem sending mail from staff page.
- Fixed [#165](https://github.com/CCSDForge/episciences/issues/165): Line breaks in paper titles in API
- Fixed [#251](https://github.com/CCSDForge/episciences/issues/251): conform to ISO 3297 for displaying ISSNs

## Added

- Episciences portal: New Feed RSS + Atom : latest published documents, 2 docs per journal
- Added 'journals news' RSS feed

## 1.0.31.1 - 2022-07-13

### Changed

- User accounts merging procedure: take into account the Conflict Of Interests

## 1.0.31 - 2022-06-28

### Added

- A pictogram is added to indicate papers with conflicts in the article management table.

### Changed

- [192](https://github.com/CCSDForge/episciences/issues/192): allow editors to "Ask for other editors opinion".
- Only confirmed conflicts will be displayed in the conflict management section

### Fixed

- Ask revision: possibility to backdate the deadline.
- Fixed: RT #160301:
  the tags [%%SENDER_FULL_NAME%%, %%SENDER_SCREEN_NAME%%, %%SENDER_EMAIL%%, %%SENDER_FIRST_NAME%%', %%SENDER_LAST_NAME%% ]
  concerning the user of the action are filled with the data of the user connected at the time of the action.
  Making these variables available in the automatic mails poses a real problem: they are filled with the data of the mail recipient.
  So, from now on, the tags mentioned above will no longer be available in the automatic mail templates.
- Shifted display because of the error message (CSRF token)

## 1.0.30 - 2022-06-20

### Fixed

- Titles do not appear in the correct language when more than one language has been entered (e.g. SLOVO )

### Added

- It is now possible to manage conflicts on the article page: display & delete them

## 1.0.29.1 - 2022-06-13

### Changed

- Revision requests: now we have an immediate visual feedback when changing the deadline

### Fixed

- [#247](https://github.com/CCSDForge/episciences/issues/247):
  HTML links in outgoing emails are made relative [#247]: TinyMCE configuration: convert all relative URLs to absolute URLs.
- Edition of a volume: The title of article might not be retrieved if the language was not managed by the platform.
- Profile editing form does not display correctly when changing language.

## 1.0.29 - 2022-06-01

### Fixed

- Failed 'Not enough reviewers - editor copy' reminders.
- But leading to load journal translations from '/'.
- PHP Warning: Use of undefined constant RVID - assumed 'RVID' (this will throw an Error in a future version of PHP)
- Not enough review reminders: articles without invitations escape reminders because the function that retrieves invitations is not used properly.

### Added

- User list: make it easier to locate accounts that have not been activated.
- Addes Roadmap link + User survey/feedback on portal
- Make EU grants and OpenAIRE support more visible at the bottom of the page

## 1.0.28.5 - 2022-05-25

### Fixed

- [RT#158293]: article status is not correctly updated

## 1.0.28.4 - 2022-05-09

### Fixed

- File not found for Temporary Versions without attached files + refactoring.

## 1.0.28.3 - 2022-04-13

### Changed

- Updated error message when using a HAL identifier without any file.
- [#142](https://github.com/CCSDForge/episciences/issues/142)

## 1.0.28.2 - 2022-04-06

### Fixed

- Empty records from HAL repository: error message improved.
- Improvements and fixes on Datacite and Crossref XML exports

## 1.0.28.1 - 2022-03-23

### Fixed

- fix issue in crossref xml format with related_item

## 1.0.28 - 2022-03-23

### Changed

- Bump crossref schema to ver 4.8.1
- arXiv URLs now links to https version instead of http

### Added

- Crossref metadata export format for DOI:
    - add previous version URLs, link with relationship 'hasPreprint' to link preprints versions with the published version
    - add arXiv DOIs, link to journal's published DOI with 'isSameAs' relationship
    - add full text PDF URLs for Crossref 'Similarity Check' service

## 1.0.27.5 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format
- Adding Polyfill PHP 8.0

## 1.0.27.4 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format

## 1.0.27.3 - 2022-03-18

### Fixed

- Ratings submitted late after the start of the layout process caused the status of the article to be updated (rolled back).
- There is no need to review an article while it is being formatted by the author.

## 1.0.27.2 - 2022-03-18

### Fixed

- "Copy editor" role not authorized accessing to the public page of the paper
  and report a conflict of interest, if the option has been activated by the journal

## 1.0.27.1 - 2022-03-16

### Fixed

- Exclude the "paper-status" directory from the journal's resources.
- Content that is copied and pasted when reviewing an article may lose end of line.
- The linked data to an article is not deleted when the author deletes his own article

## 1.0.27 - 2022-03-14

# Changed

- Reviewer invitation: Users are now sorted by Name, First Name instead of First Name

# Fixed

- Dashboard: the number of papers assigned to the copy editor includes obsolete papers
- Issue reported to support RT #148133 (fixed before this release):
    - Some editors could see the reviewers names if they were also authors of the article

## 1.0.26 - 2022-03-01

# Fixed

- Issue reported to support RT #148133:
    - Incorrect "Reminder Delay: The "Reminder Delay" should correspond, in number of days, to the difference between the date the reminder was sent and the date the invitation was sent.
- Issue reported to support RT #148466:
    - Sorting by date in the Dashboard is wrong: Enabling the conflict of interest (COI) option distorts the pagination

## 1.0.25.1 - 2022-02-15

# Changed

[#152](https://github.com/CCSDForge/episciences/issues/152): page footer modifications.

## 1.0.25 - 2022-02-15

# Fixed

- All submissions are accessible to "guest editor" roles.
- Language bar missing when editing a mail template, including reminders.

## 1.0.24.2 - 2022-02-10

# Fixed

- [RT#146987] "search" button no longer works
- application error: syntax error or access violation if CAS user not found

## 1.0.24.1 - 2022-02-08

# Fixed

- A bug in the reminders code triggered emails with unusable HTTP links

## 1.0.24 - 2022-02-08

# Fixed

- Inability to delete a volume (RT#145178): reset the volume of the previous version when submitting a new version.
- [#117](https://github.com/CCSDForge/episciences/issues/117) Improvements in emailing
- Undetected inactive accounts: Invite a reviewer > new reviewer > Invite a new user.
- Not allowing the author to be invited: Invite a reviewer > new reviewer > Invite a new user.
- The attachments to the rating report are not available [RT#145473]
- Answer revision request: in some situations, the "Submit" button remains inactive.

## 1.0.23 - 2022-01-19

### Added

- [#116](https://github.com/CCSDForge/episciences/issues/116) Release version is displayed on the interface (dashboard and page footer)
- Editors' choice at time of submission: informative text added. (gitlab #369)
- [#143](https://github.com/CCSDForge/episciences/issues/143) New DOI setting: Allow switching off manual or automatic DOI assignation (disabled mode)
- [#37](https://github.com/CCSDForge/episciences/issues/37)
    - Browse by Volume or Sections : Handle new content type 'application/json' to return json instead of html
    - On URLs like /volume/view/id/314159 and /section/view/id/314159 Handle new content type 'application/json' to return json instead of html (only published content)
    - On URLs like /volume/edit?id=314159 Handle new content type 'application/json' to return json instead of html but including all statuses of articles ; an authenticated and allowed user is required
    - JSON added to the list of public export formats
- Enhanced information message for statistics

### Changed

- UI/UX: Make the version number more explicit when proposing a new version
- Code Refactoring fixing of warning
- Dumping default data for table `MAIL_TEMPLATE`

### Fixed

- [#126](https://github.com/CCSDForge/episciences/issues/126) Clicking outside the window to compose an email closes the window with no confirmation #126
- [#117](https://github.com/CCSDForge/episciences/issues/117) Mailing bug: loss of the mail when you forget to put a recipient (gitlab #343)
- [#149](https://github.com/CCSDForge/episciences/issues/149) Emails and default language selection: language of the sender and the recipient could be a problem if the language of the sender is different from the language of the recipient (gitlab #402)
- Inconsistent REMINDER_DELAY: the displayed value is the "delay" parameter of the reminder instead of the calculated value (interval in number of days between the time the reminder was sent and the deadline).
- New submission: if the "submission date" metadata is empty: do not display it anymore.
- [#124](https://github.com/CCSDForge/episciences/issues/124): refining automatic notifications when abandoning submission
- RT#144252: better cleaning of attached file names
- RT#144246: an inactive account could still be visible on the Editorial Staff members page.
- [#117](https://github.com/CCSDForge/episciences/issues/117): An empty e-mail is not sent or received
- [#129](https://github.com/CCSDForge/episciences/issues/129): when you do a search from the mail history page nothing happens.
- [#141](https://github.com/CCSDForge/episciences/issues/141): visibility of pages menu is wrong inside menu editing.

### Changed

- [#126](https://github.com/CCSDForge/episciences/issues/126): prevent modal closure: disabling the click outside the modal area and by pressing Esc
- Enabling the "COI" option overrides the "Encapsulate editors" option

## 1.0.38.5 - 2022-12-14

### Fixed

- [#294](https://github.com/CCSDForge/episciences/issues/294): untranslated text
- API to return number of published documents

## 1.0.38.4 - 2022-12-12

### Fixed

- JS error: Failed to load plugin url: https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/langs/fr_FR.js
  @see https://www.tiny.cloud/docs-4x/configure/localization/#language
- Add %%PAPER_REP_URL%% tag to the description of the invitation templates.
- Change password: redirect to authentication if user is not logged in
- The '%%SENDER_SCREEN_NAME%%' tag is always present in the 'paper_paper_editor_refused_monitoring' template

## 1.0.38.3 - 2022-11-30

### Fixed

- Html filtering problem that breaks the display in the comments

## 1.0.38.2 - 2022-11-24

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): There is a difference between the capitalization of the first and last names in the user's profile
  and the first and last names of the user in the email recipient field [RT#148137]
- Fixed the fact that the Administrator role prevents testing for conflict of interest
- Error: $(...).fileupload is not a function for published articles.

### Changed

- Conflict management section: set default display lines to 5.
- Mailing > getContacts: add 'Authors' filter.
- resolved [#282](https://github.com/CCSDForge/episciences/issues/282): C:\fakepath: This is the security
  implementation of the browser: the browser is protecting you from accessing your disk structure.
- Code review / refactoring

## 1.0.38.1 - 2022-11-16

### Fixed

- Added missing translation for conflict of interest

### Changed

- Footer: replaced TOS with Term Of Use

## 1.0.38 - 2022-11-16

### Added

- New footer link to the Term of use (between the platform and users)

### Fixed

- Fixed ORCID 'X' in DOAJ export format, make it compliant with ORCID specification
- [RT#171463]: the reviews should not be seen by someone with a CoI declaration with
  the paper: this fix concerns all the paper's details.

### Changed

- UI/UX: Improved rendering of the "conflict management" section
- [RT#170200]: confirmation of the absence of conflict of interest: it is now possible to cancel this type of response.

## 1.0.37 - 2022-11-10

### Added

- New feature: [Open Science Lens](https://www.opensciencelens.eu/) feature available as a preview on selected journals

### Changed

- Improved rendering of volumes

## 1.0.36 - 2022-11-09

### Added

- Improved rendering of the volume page
- authors can now enter their paper password provided by the open archive on the article page.
- alt attribute to volume's image.
- Added an API method to get the number of users accounts

### Changed

- Optimization: there is no need to check for conflicts at the time of submission
- [#280](https://github.com/CCSDForge/episciences/issues/280): set spellcheck to false in dynamic datatable search boxes.
- [#281](https://github.com/CCSDForge/episciences/issues/281): Submit an article > Guidelines section: harmonization of journal/review terms [FR]
- Now, when an article is accepted, all unanswered invitations are deleted. The reviewer is informed of this action.
- Add 'ISSN pending' until the ISSN is actually issued

### Fixed

- Fix incorrect HTML in footer
- Incorrect paper status LMCS #10145 (related to https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#10273---2022-03-18)
- Empty %%REQUESTER_EXPRESSION%% tag (related to [#187](https://github.com/CCSDForge/episciences/issues/187)
- Incorrect status jdmdh#10203 (related with git#372)
- Fixed [#RT169943]: the tag %%SENDER_SCREEN_NAME%% (obsolete) that should have contained the editor's screen name has not been replaced:
  to be replaced in custom templates by %%EDITOR_FULL_NAME%% or %%EDITOR_SCREEN_NAME%%
  (@see https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#fixed-11 [RT##160301])

## 1.0.35 - 2022-10-19

### Added

- [RT#169107]: Feature - new option: do not allow the selection of an editor-in-chief when the author has the option to
  propose an editor at the time of submission.

### Changed

- [#255](https://github.com/CCSDForge/episciences/issues/255) Display ratings reports on the article page: Label updated

### Fixed

- [RT#169088]: Editors-in-chief and editorial secretaries are no longer notified of new submissions.

## 1.0.34.2 - 2022-10-18

### Fixed

- temporary fix: TinyMCE: loss of formatting for successive mailings
- Insert data in 'T_PAPER_DATASETS' table: MySql insert 0 instead of NULL
- [#273](https://github.com/CCSDForge/episciences/issues/273): if the editor changes the rating due date, then this
  is not reflected in the message.
- Fixes in exports format related to trailing ',' and language codes

## 1.0.34.1 - 2022-10-16

### Fixed

- Fixed bad JS escape of authors in reviewer invitation form
- Fixed Handle URLs links adding https://hdl.handle.net/
- Fixed display of disabled buttons
- Fixed 'Description of affiliation block'

### Added

- The activation of maintenance alerts is now configurable

## 1.0.34 - 2022-10-13

### Added

- Article managers (authors, editors, etc.) may add new optional - but recommended - metadata to the documents
    - [ORCID](https://orcid.org/)
    - Affiliations: for affiliations the [ROR](https://ror.org/) or a free text may be used. If an institution is not available in the ROR, a
      simple text entry may be used.
- Automatic enrichment of metadata:
    - Licenses: retrieved from [Datacite](https://datacite.org/), [OpenAIRE Research Graph](https://graph.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/)
    - ORCID: retrieved from [OpenAIRE Research Graph](https://graph.openaire.eu/)
    - Linked Datasets: [Scholexplorer](https://scholexplorer.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/): Datasets linked to Episciences
      publications
    - Funding: Research projects from [OpenAIRE Research Graph](https://graph.openaire.eu/) and [HAL](https://hal.archives-ouvertes.fr/)
    - Citations: Citations of published document retrieved with [OpenCitations](https://opencitations.net/) APIs
- Exports formats:
    - [DOAJ](https://doaj.org/about/) (export one document or a whole published volume) using DOAJ Schema
    - The new collected metadata have been added to Datacite, [Crossref](https://www.crossref.org/) and DOAJ export formats
- Browse by volume: new parameter to allow to display empty volumes. Default value is still no (only show volumes
  with papers published in the volume)
- [#186](https://github.com/CCSDForge/episciences/issues/186): Editors-in-chief can now report a conflict of
  interest. (Sign in an admin as another user: the real identity is now saved.)

### Fixed

- Fixed a display bug on the portal in connected mode (used by Episciences staff only)
- Failed to edit another account's profile from the user management page.
- impossibility to validate the profile if the user does not have an account in the application

### Changed

- new parameter for enabling/disabling submission from the archive to the application and vice versa.

## 1.0.33.2 - 2022-10-05

### Fixed

- RT#167820: all occurrences of tags in a template will be replaced by their real values.

## 1.0.33.1 - 2022-09-29

### Fixed

- Allow editors to view the list of conflicts on the article management page
- The number of conflicts indicated on the article management page is incorrect if the article has several versions

## 1.0.33 - 2022-09-28

### Added

- New page listing all email templates and available tags for each journal at /administratemail/tagslist
  (- Mail -> TagList)
- Episciences portal:
    - New Feed RSS + Atom : latest published documents, 2 docs per journal
    - API to get a list of publishing journals: include accepted repositories.
- Episciences journals
    - Added 'journals news' RSS feed for each journal
- New journal settings parameter: Allow post-acceptance revisions of articles
- 9 article statuses, available for journals allowing 'post-acceptance revisions of articles':
    - Accepted temporary version, waiting for author's final version: now, temporary versions that are accepted have this new status instead of "pending minor revision" (git #372)
    - Accepted - waiting for author's final version
    - Accepted, waiting for major revision
    - Accepted article - final version submitted, waiting for formatting by copy editors
    - Accepted temporary version after author's modifications
    - Accepted temporary version, waiting for minor revision
    - Accepted temporary version, waiting for major revision
    - Accepted - waiting validation by the author
    - Approved by author, waiting for final publication
- User profiles:
    - Adding ORCID and affiliation to your profile is now possible + this information is diplayed on the
      "editorial staff member" (gitlab #410) & dashboard pages.
    - Social Medias & Websites profile informations.
- New 'Author' role automatically added to users that have submitted a document
- Paper list: temporary versions now have a specific label
- Dashboard: Alert for administrators on the existence of papers without assigned editors
- Display the origin of a linked data when papers are linked to a dataset
- Administration page of a paper: new shortcut for administrators :
    - to allow to sign in as a copy-editor
    - to allow to sign in as an editor
- [227](https://github.com/CCSDForge/episciences/issues/227):
    - The revision deadline is now displayed below the article's status.
    - A pictogram is added to indicate the date of the revision deadline in the article management table.

### Changed

- [#237](https://github.com/CCSDForge/episciences/issues/237) Editor comments div is now bigger
- Upgraded publication RSS Feeds with DOIs
- [#142](https://github.com/CCSDForge/episciences/issues/142): allow that administrators answer revision and copy editing requests
- It is now possible to update the metadata on the document's page
- Request a new version of an article that has already been accepted
- [#166](https://github.com/CCSDForge/episciences/issues/116): hide "guest" role and doi-settings action
- The "paper status" filter is now dynamically created.
- Prevent "Add sources files" and "Add the formatted version" buttons JS reactivation
- Moved "copy editing" section to a more usable place on the same page
- Dashboard improvements: ability to reach an article from sections: my submissions and assigned articles
- [#187](https://github.com/CCSDForge/episciences/issues/187): change the default templates: 'new version submitted' and 'tmp version submitted'
- [#188](https://github.com/CCSDForge/episciences/issues/188): force notifications
- It is now possible to update the document version in Episciences to the most recent version in the open archive.
- [#164](https://github.com/CCSDForge/episciences/issues/164): harmonization of roles/privileges.
- CWI open repository has been temporarily removed until we adapt to their new OAI server

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): capitalization of names
- [#207](https://github.com/CCSDForge/episciences/issues/207): editing the translation of Chief Editors
- [#169](https://github.com/CCSDForge/episciences/issues/169): reports become visible on the article web page:
    - according to the parameters of the journal for published articles
    - for the owner, only if the paper is refused, waiting for revision, already accepted or published
- Another status (Revision request answer: without any modifications) is used when responding to a revision request: without any modifications.
- Revision requests: now we have an immediate visual feedback when changing the deadline.
- If the journal allows revision of articles after acceptance, the response to a request for a final version (without any changes) is similar to the proposal of a new version.

### Internal refactoring

- Code Refactoring fixing of warning
- Update Episciences_User::getScreenName()
- User Table: merge AFFILIATIONS" filed in "ADDITIONAL_PROFILE_INFORMATION"
- Multiple roles: ignore the "member" role when merging two accounts.
- Email Templates updates
- Script to clean the "USER_ROLES" table
- Parameter to detect automatic emails

## 1.0.32.2 - 2022-09-28

### Changed

- [196](https://github.com/CCSDForge/episciences/issues/196): Creation of an account following an invitation with a temporary account: from now on, the Last Name, First Name and Screen Name fields will be left empty (for the reviewer to fill in)
- [RT#164153]: refactoring: now obsolete template "paper_new_version_reviewer_reassign" (Reviewer assignment to a new version of an article) is removed.

### Fixed:

- account merging incomplete due to sql error when updating 'paper_conflicts' table.

## 1.0.32.1 - 2022-09-01

### Fixed

- Fixed bug introduced with [196] "Name" => "Last Name" when creating a new reviewer
- Fixed release notes links to issues

## 1.0.32 - 2022-08-31

### Changed

- [201](https://github.com/CCSDForge/episciences/issues/201): depending on journal settings, editor can accept, ask revision, reject and publish papers in every round.
- [238](https://github.com/CCSDForge/episciences/issues/238): harmonization of terms 'sections/rubriques'.
- [196](https://github.com/CCSDForge/episciences/issues/196) now,
    - Only one 'name' field is available when creating a new reviewer
    - Reviewers are sorted by lastname by default
- Empty TAG %%RECIPIENT_SCREEN_NAME%% for users who do not have a local account in "Unanswered reviewer invitation (reviewer copy)" reminder.
- (Internal/refactoring) transition to PHP 8: refactoring: "MaxMind GeoIP2 PHP API" is now used instead of PHP module.

### Fixed

- [RT#163166]: Problem sending mail from staff page.
- Fixed [#165](https://github.com/CCSDForge/episciences/issues/165): Line breaks in paper titles in API
- Fixed [#251](https://github.com/CCSDForge/episciences/issues/251): conform to ISO 3297 for displaying ISSNs

## Added

- Episciences portal: New Feed RSS + Atom : latest published documents, 2 docs per journal
- Added 'journals news' RSS feed

## 1.0.31.1 - 2022-07-13

### Changed

- User accounts merging procedure: take into account the Conflict Of Interests

## 1.0.31 - 2022-06-28

### Added

- A pictogram is added to indicate papers with conflicts in the article management table.

### Changed

- [192](https://github.com/CCSDForge/episciences/issues/192): allow editors to "Ask for other editors opinion".
- Only confirmed conflicts will be displayed in the conflict management section

### Fixed

- Ask revision: possibility to backdate the deadline.
- Fixed: RT #160301:
  the tags [%%SENDER_FULL_NAME%%, %%SENDER_SCREEN_NAME%%, %%SENDER_EMAIL%%, %%SENDER_FIRST_NAME%%', %%SENDER_LAST_NAME%% ]
  concerning the user of the action are filled with the data of the user connected at the time of the action.
  Making these variables available in the automatic mails poses a real problem: they are filled with the data of the mail recipient.
  So, from now on, the tags mentioned above will no longer be available in the automatic mail templates.
- Shifted display because of the error message (CSRF token)

## 1.0.30 - 2022-06-20

### Fixed

- Titles do not appear in the correct language when more than one language has been entered (e.g. SLOVO )

### Added

- It is now possible to manage conflicts on the article page: display & delete them

## 1.0.29.1 - 2022-06-13

### Changed

- Revision requests: now we have an immediate visual feedback when changing the deadline

### Fixed

- [#247](https://github.com/CCSDForge/episciences/issues/247):
  HTML links in outgoing emails are made relative [#247]: TinyMCE configuration: convert all relative URLs to absolute URLs.
- Edition of a volume: The title of article might not be retrieved if the language was not managed by the platform.
- Profile editing form does not display correctly when changing language.

## 1.0.29 - 2022-06-01

### Fixed

- Failed 'Not enough reviewers - editor copy' reminders.
- But leading to load journal translations from '/'.
- PHP Warning: Use of undefined constant RVID - assumed 'RVID' (this will throw an Error in a future version of PHP)
- Not enough review reminders: articles without invitations escape reminders because the function that retrieves invitations is not used properly.

### Added

- User list: make it easier to locate accounts that have not been activated.
- Addes Roadmap link + User survey/feedback on portal
- Make EU grants and OpenAIRE support more visible at the bottom of the page

## 1.0.28.5 - 2022-05-25

### Fixed

- [RT#158293]: article status is not correctly updated

## 1.0.28.4 - 2022-05-09

### Fixed

- File not found for Temporary Versions without attached files + refactoring.

## 1.0.28.3 - 2022-04-13

### Changed

- Updated error message when using a HAL identifier without any file.
- [#142](https://github.com/CCSDForge/episciences/issues/142)

## 1.0.28.2 - 2022-04-06

### Fixed

- Empty records from HAL repository: error message improved.
- Improvements and fixes on Datacite and Crossref XML exports

## 1.0.28.1 - 2022-03-23

### Fixed

- fix issue in crossref xml format with related_item

## 1.0.28 - 2022-03-23

### Changed

- Bump crossref schema to ver 4.8.1
- arXiv URLs now links to https version instead of http

### Added

- Crossref metadata export format for DOI:
    - add previous version URLs, link with relationship 'hasPreprint' to link preprints versions with the published version
    - add arXiv DOIs, link to journal's published DOI with 'isSameAs' relationship
    - add full text PDF URLs for Crossref 'Similarity Check' service

## 1.0.27.5 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format
- Adding Polyfill PHP 8.0

## 1.0.27.4 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format

## 1.0.27.3 - 2022-03-18

### Fixed

- Ratings submitted late after the start of the layout process caused the status of the article to be updated (rolled back).
- There is no need to review an article while it is being formatted by the author.

## 1.0.27.2 - 2022-03-18

### Fixed

- "Copy editor" role not authorized accessing to the public page of the paper
  and report a conflict of interest, if the option has been activated by the journal

## 1.0.27.1 - 2022-03-16

### Fixed

- Exclude the "paper-status" directory from the journal's resources.
- Content that is copied and pasted when reviewing an article may lose end of line.
- The linked data to an article is not deleted when the author deletes his own article

## 1.0.27 - 2022-03-14

# Changed

- Reviewer invitation: Users are now sorted by Name, First Name instead of First Name

# Fixed

- Dashboard: the number of papers assigned to the copy editor includes obsolete papers
- Issue reported to support RT #148133 (fixed before this release):
    - Some editors could see the reviewers names if they were also authors of the article

## 1.0.26 - 2022-03-01

# Fixed

- Issue reported to support RT #148133:
    - Incorrect "Reminder Delay: The "Reminder Delay" should correspond, in number of days, to the difference between the date the reminder was sent and the date the invitation was sent.
- Issue reported to support RT #148466:
    - Sorting by date in the Dashboard is wrong: Enabling the conflict of interest (COI) option distorts the pagination

## 1.0.25.1 - 2022-02-15

# Changed

[#152](https://github.com/CCSDForge/episciences/issues/152): page footer modifications.

## 1.0.25 - 2022-02-15# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

<!--
### Added
### Changed
### Deprecated
### Fixed
### Removed
### Security
### Performances
-->

## Unreleased

### Added
- [#937](https://github.com/CCSDForge/episciences/issues/937) Admin paper list (`/administratepaper/list`): columns **Reviewers**, **Editors**, **Copy editors**, and **Contributor** are now sortable server-side.
### Performances

- [#937](https://github.com/CCSDForge/episciences/issues/937) Sorting by Reviewers / Editors / Copy editors uses a derived-table `LEFT JOIN` with `MIN(SCREEN_NAME)` aggregation (single evaluation per query) rather than a correlated subquery.



- Paper admin: volume/section assignment now uses a native `<dialog>` modal with Tom Select; `paper-assignment-modal.js` (vanilla JS) replaces `volume-assignment.js` and `section-assignment.js`.
- Paper admin: Tom Select `dropdown_input` and `remove_button` plugins in modal selects.
- Admin paper list: Tom Select search on all 7 filter selects (Status, Volume, Section, Editors, Reviewers, DOI, Repositories) — type to filter within long lists, animated chevron indicates dropdown, placeholder shows "All" when nothing is selected.

### Changed
- [#1038](https://github.com/CCSDForge/episciences/issues/1038) It is now possible to publish the article at any stage of the editorial process if it is accepted[RT##287093].
- [RT##287093]: It is now possible to publish the article at any stage of the editorial process if it is accepted.
- Paper admin: assignment buttons replaced by pencil icon (`fa-pen-to-square`); glyphicons removed.
- Paper admin: modal save now does a targeted AJAX refresh instead of a full page reload.
- Paper admin: other volumes form replaced by a Tom Select `<select multiple>` (`checkbox_options` plugin).
- Paper admin (i18n): "Master volume" renamed to "Main volume".
- Paper admin: section edit button moved before label/name, consistent with volumes layout.
- Paper admin: volume sort button uses `fa-arrow-down-1-9` icon with a descriptive tooltip.
- Admin paper list: filter panel reorganised into 3 rows — Status/Editors/Reviewers on the first row, Volume+DOI on the second, Section+Repositories on the third; Volume and Section are wider (`col-sm-8`) to accommodate long titles.
- Admin paper list: removed redundant page description blockquote.

### Fixed
- Application error: Call to a member function getVol_num() on bool

- `scripts/update_papers.php` : fixed issue where importing a new version (e.g. version 2) of an existing paper (e.g. version 1) overwrote the version 1 entry in the database instead of creating a new version record.

- BibTeX export: month field was rendered in the user's UI locale (e.g. `mai` for French users) instead of English (`May`); locale is now forced to `en` in the BibTeX template.

- [#1030](https://github.com/CCSDForge/episciences/issues/1030): missing "Ask the author for the sources (copy editing by the journal): because the "Allow post - acceptance revisions of articles" option is used, which alters the workflow steps, as it is assumed that the journal already has the source files. At this stage, it is now possible to request the sources.

- Paper admin: closed modal remained visible (`display:flex` SCSS overrode native `dialog:not([open])` behaviour).
- Paper admin: Tom Select selected items now styled as blue pills in the modal.
- Paper admin: translation failure on volume-alert text (curly apostrophe in `views.php` vs plain ASCII in template).
- Paper admin: "Assign editors" checkbox now disabled+unchecked when no section is selected.
- Paper admin: article position badge wrapped in a Bootstrap `label-default` span with an accessible tooltip.
- Paper admin: main volume cell not updated in list after remove+reassign (stale `btn.dataset.vid` + missing fallback in `refreshallmastervolumesAction` for papers without a position row).
- Admin paper list: `checkFilterParams` crashed with `TypeError: Cannot read properties of null (reading 'length')` when clicking "Filter" with Tom Select multiselects returning `null` for empty selections; fallback to `['']` restores expected behaviour.

- [#930](https://github.com/CCSDForge/episciences/issues/930) New email template tags for volume metadata: `%%VOLUME_NUMBER%%`, `%%VOLUME_YEAR%%`, and `%%VOLUME_TYPE%%`.
- Mailing lists: added `created_at` and `updated_at` columns to the `mailing_lists` table.
- Mailing lists: four MySQL triggers propagate `updated_at` to the parent row when individual members or roles are modified.
- Mailing lists: `v_mailing_lists_resolved` view now exposes `list_created_at` and `list_updated_at`.
- Mailing lists: "Last updated" column displayed after "Name" in the dashboard table.
- Mailing lists: creation date displayed (read-only) in the list edit form.

### Performances

- Avoid repeated `REVIEW_SETTING` queries in a single request by caching loaded review settings on the current review object and sharing cached review instances between `RVID` and `RVCODE` lookups.

### Changed
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration
  button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)"
- Modernized `Ccsd_Form_Filter_Clean` filter and `Ccsd_Form_Validate_NotSame` validator (introduced strict typing, comprehensive type hinting, and robust recursive array filtering for the `Clean` filter).

### Removed

- Removed obsolete `DEAD CODE AUDIT` deprecation warnings from `UserFtpQuota` and `UserFtpQuotaMapper` classes.

## 1.0.38.5 - 2022-12-14

### Fixed

- [#294](https://github.com/CCSDForge/episciences/issues/294): untranslated text
- API to return number of published documents

## 1.0.38.4 - 2022-12-12

### Fixed

- JS error: Failed to load plugin url: https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/langs/fr_FR.js
  @see https://www.tiny.cloud/docs-4x/configure/localization/#language
- Add %%PAPER_REP_URL%% tag to the description of the invitation templates.
- Change password: redirect to authentication if user is not logged in
- The '%%SENDER_SCREEN_NAME%%' tag is always present in the 'paper_paper_editor_refused_monitoring' template

## 1.0.38.3 - 2022-11-30

### Fixed

- Html filtering problem that breaks the display in the comments

## 1.0.38.2 - 2022-11-24

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): There is a difference between the capitalization of the first and last names in the user's profile
  and the first and last names of the user in the email recipient field [RT#148137]
- Fixed the fact that the Administrator role prevents testing for conflict of interest
- Error: $(...).fileupload is not a function for published articles.

### Changed

- Conflict management section: set default display lines to 5.
- Mailing > getContacts: add 'Authors' filter.
- resolved [#282](https://github.com/CCSDForge/episciences/issues/282): C:\fakepath: This is the security
  implementation of the browser: the browser is protecting you from accessing your disk structure.
- Code review / refactoring

## 1.0.38.1 - 2022-11-16

### Fixed

- Added missing translation for conflict of interest

### Changed

- Footer: replaced TOS with Term Of Use

## 1.0.38 - 2022-11-16

### Added

- New footer link to the Term of use (between the platform and users)

### Fixed

- Fixed ORCID 'X' in DOAJ export format, make it compliant with ORCID specification
- [RT#171463]: the reviews should not be seen by someone with a CoI declaration with
  the paper: this fix concerns all the paper's details.

### Changed

- UI/UX: Improved rendering of the "conflict management" section
- [RT#170200]: confirmation of the absence of conflict of interest: it is now possible to cancel this type of response.

## 1.0.37 - 2022-11-10

### Added

- New feature: [Open Science Lens](https://www.opensciencelens.eu/) feature available as a preview on selected journals

### Changed

- Improved rendering of volumes

## 1.0.36 - 2022-11-09

### Added

- Improved rendering of the volume page
- authors can now enter their paper password provided by the open archive on the article page.
- alt attribute to volume's image.
- Added an API method to get the number of users accounts

### Changed

- Optimization: there is no need to check for conflicts at the time of submission
- [#280](https://github.com/CCSDForge/episciences/issues/280): set spellcheck to false in dynamic datatable search boxes.
- [#281](https://github.com/CCSDForge/episciences/issues/281): Submit an article > Guidelines section: harmonization of journal/review terms [FR]
- Now, when an article is accepted, all unanswered invitations are deleted. The reviewer is informed of this action.
- Add 'ISSN pending' until the ISSN is actually issued

### Fixed

- Fix incorrect HTML in footer
- Incorrect paper status LMCS #10145 (related to https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#10273---2022-03-18)
- Empty %%REQUESTER_EXPRESSION%% tag (related to [#187](https://github.com/CCSDForge/episciences/issues/187)
- Incorrect status jdmdh#10203 (related with git#372)
- Fixed [#RT169943]: the tag %%SENDER_SCREEN_NAME%% (obsolete) that should have contained the editor's screen name has not been replaced:
  to be replaced in custom templates by %%EDITOR_FULL_NAME%% or %%EDITOR_SCREEN_NAME%%
  (@see https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#fixed-11 [RT##160301])

## 1.0.35 - 2022-10-19

### Added

- [RT#169107]: Feature - new option: do not allow the selection of an editor-in-chief when the author has the option to
  propose an editor at the time of submission.

### Changed

- [#255](https://github.com/CCSDForge/episciences/issues/255) Display ratings reports on the article page: Label updated

### Fixed

- [RT#169088]: Editors-in-chief and editorial secretaries are no longer notified of new submissions.

## 1.0.34.2 - 2022-10-18

### Fixed

- temporary fix: TinyMCE: loss of formatting for successive mailings
- Insert data in 'T_PAPER_DATASETS' table: MySql insert 0 instead of NULL
- [#273](https://github.com/CCSDForge/episciences/issues/273): if the editor changes the rating due date, then this
  is not reflected in the message.
- Fixes in exports format related to trailing ',' and language codes

## 1.0.34.1 - 2022-10-16

### Fixed

- Fixed bad JS escape of authors in reviewer invitation form
- Fixed Handle URLs links adding https://hdl.handle.net/
- Fixed display of disabled buttons
- Fixed 'Description of affiliation block'

### Added

- The activation of maintenance alerts is now configurable

## 1.0.34 - 2022-10-13

### Added

- Article managers (authors, editors, etc.) may add new optional - but recommended - metadata to the documents
    - [ORCID](https://orcid.org/)
    - Affiliations: for affiliations the [ROR](https://ror.org/) or a free text may be used. If an institution is not available in the ROR, a
      simple text entry may be used.
- Automatic enrichment of metadata:
    - Licenses: retrieved from [Datacite](https://datacite.org/), [OpenAIRE Research Graph](https://graph.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/)
    - ORCID: retrieved from [OpenAIRE Research Graph](https://graph.openaire.eu/)
    - Linked Datasets: [Scholexplorer](https://scholexplorer.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/): Datasets linked to Episciences
      publications
    - Funding: Research projects from [OpenAIRE Research Graph](https://graph.openaire.eu/) and [HAL](https://hal.archives-ouvertes.fr/)
    - Citations: Citations of published document retrieved with [OpenCitations](https://opencitations.net/) APIs
- Exports formats:
    - [DOAJ](https://doaj.org/about/) (export one document or a whole published volume) using DOAJ Schema
    - The new collected metadata have been added to Datacite, [Crossref](https://www.crossref.org/) and DOAJ export formats
- Browse by volume: new parameter to allow to display empty volumes. Default value is still no (only show volumes
  with papers published in the volume)
- [#186](https://github.com/CCSDForge/episciences/issues/186): Editors-in-chief can now report a conflict of
  interest. (Sign in an admin as another user: the real identity is now saved.)

### Fixed

- Fixed a display bug on the portal in connected mode (used by Episciences staff only)
- Failed to edit another account's profile from the user management page.
- impossibility to validate the profile if the user does not have an account in the application

### Changed

- new parameter for enabling/disabling submission from the archive to the application and vice versa.

## 1.0.33.2 - 2022-10-05

### Fixed

- RT#167820: all occurrences of tags in a template will be replaced by their real values.

## 1.0.33.1 - 2022-09-29

### Fixed

- Allow editors to view the list of conflicts on the article management page
- The number of conflicts indicated on the article management page is incorrect if the article has several versions

## 1.0.33 - 2022-09-28

### Added

- New page listing all email templates and available tags for each journal at /administratemail/tagslist
  (- Mail -> TagList)
- Episciences portal:
    - New Feed RSS + Atom : latest published documents, 2 docs per journal
    - API to get a list of publishing journals: include accepted repositories.
- Episciences journals
    - Added 'journals news' RSS feed for each journal
- New journal settings parameter: Allow post-acceptance revisions of articles
- 9 article statuses, available for journals allowing 'post-acceptance revisions of articles':
    - Accepted temporary version, waiting for author's final version: now, temporary versions that are accepted have this new status instead of "pending minor revision" (git #372)
    - Accepted - waiting for author's final version
    - Accepted, waiting for major revision
    - Accepted article - final version submitted, waiting for formatting by copy editors
    - Accepted temporary version after author's modifications
    - Accepted temporary version, waiting for minor revision
    - Accepted temporary version, waiting for major revision
    - Accepted - waiting validation by the author
    - Approved by author, waiting for final publication
- User profiles:
    - Adding ORCID and affiliation to your profile is now possible + this information is diplayed on the
      "editorial staff member" (gitlab #410) & dashboard pages.
    - Social Medias & Websites profile informations.
- New 'Author' role automatically added to users that have submitted a document
- Paper list: temporary versions now have a specific label
- Dashboard: Alert for administrators on the existence of papers without assigned editors
- Display the origin of a linked data when papers are linked to a dataset
- Administration page of a paper: new shortcut for administrators :
    - to allow to sign in as a copy-editor
    - to allow to sign in as an editor
- [227](https://github.com/CCSDForge/episciences/issues/227):
    - The revision deadline is now displayed below the article's status.
    - A pictogram is added to indicate the date of the revision deadline in the article management table.

### Changed

- [#237](https://github.com/CCSDForge/episciences/issues/237) Editor comments div is now bigger
- Upgraded publication RSS Feeds with DOIs
- [#142](https://github.com/CCSDForge/episciences/issues/142): allow that administrators answer revision and copy editing requests
- It is now possible to update the metadata on the document's page
- Request a new version of an article that has already been accepted
- [#166](https://github.com/CCSDForge/episciences/issues/116): hide "guest" role and doi-settings action
- The "paper status" filter is now dynamically created.
- Prevent "Add sources files" and "Add the formatted version" buttons JS reactivation
- Moved "copy editing" section to a more usable place on the same page
- Dashboard improvements: ability to reach an article from sections: my submissions and assigned articles
- [#187](https://github.com/CCSDForge/episciences/issues/187): change the default templates: 'new version submitted' and 'tmp version submitted'
- [#188](https://github.com/CCSDForge/episciences/issues/188): force notifications
- It is now possible to update the document version in Episciences to the most recent version in the open archive.
- [#164](https://github.com/CCSDForge/episciences/issues/164): harmonization of roles/privileges.
- CWI open repository has been temporarily removed until we adapt to their new OAI server

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): capitalization of names
- [#207](https://github.com/CCSDForge/episciences/issues/207): editing the translation of Chief Editors
- [#169](https://github.com/CCSDForge/episciences/issues/169): reports become visible on the article web page:
    - according to the parameters of the journal for published articles
    - for the owner, only if the paper is refused, waiting for revision, already accepted or published
- Another status (Revision request answer: without any modifications) is used when responding to a revision request: without any modifications.
- Revision requests: now we have an immediate visual feedback when changing the deadline.
- If the journal allows revision of articles after acceptance, the response to a request for a final version (without any changes) is similar to the proposal of a new version.

### Internal refactoring

- Code Refactoring fixing of warning
- Update Episciences_User::getScreenName()
- User Table: merge AFFILIATIONS" filed in "ADDITIONAL_PROFILE_INFORMATION"
- Multiple roles: ignore the "member" role when merging two accounts.
- Email Templates updates
- Script to clean the "USER_ROLES" table
- Parameter to detect automatic emails

## 1.0.32.2 - 2022-09-28

### Changed

- [196](https://github.com/CCSDForge/episciences/issues/196): Creation of an account following an invitation with a temporary account: from now on, the Last Name, First Name and Screen Name fields will be left empty (for the reviewer to fill in)
- [RT#164153]: refactoring: now obsolete template "paper_new_version_reviewer_reassign" (Reviewer assignment to a new version of an article) is removed.

### Fixed:

- account merging incomplete due to sql error when updating 'paper_conflicts' table.

## 1.0.32.1 - 2022-09-01

### Fixed

- Fixed bug introduced with [196] "Name" => "Last Name" when creating a new reviewer
- Fixed release notes links to issues

## 1.0.32 - 2022-08-31

### Changed

- [201](https://github.com/CCSDForge/episciences/issues/201): depending on journal settings, editor can accept, ask revision, reject and publish papers in every round.
- [238](https://github.com/CCSDForge/episciences/issues/238): harmonization of terms 'sections/rubriques'.
- [196](https://github.com/CCSDForge/episciences/issues/196) now,
    - Only one 'name' field is available when creating a new reviewer
    - Reviewers are sorted by lastname by default
- Empty TAG %%RECIPIENT_SCREEN_NAME%% for users who do not have a local account in "Unanswered reviewer invitation (reviewer copy)" reminder.
- (Internal/refactoring) transition to PHP 8: refactoring: "MaxMind GeoIP2 PHP API" is now used instead of PHP module.

### Fixed

- [RT#163166]: Problem sending mail from staff page.
- Fixed [#165](https://github.com/CCSDForge/episciences/issues/165): Line breaks in paper titles in API
- Fixed [#251](https://github.com/CCSDForge/episciences/issues/251): conform to ISO 3297 for displaying ISSNs

## Added

- Episciences portal: New Feed RSS + Atom : latest published documents, 2 docs per journal
- Added 'journals news' RSS feed

## 1.0.31.1 - 2022-07-13

### Changed

- User accounts merging procedure: take into account the Conflict Of Interests

## 1.0.31 - 2022-06-28

### Added

- A pictogram is added to indicate papers with conflicts in the article management table.

### Changed

- [192](https://github.com/CCSDForge/episciences/issues/192): allow editors to "Ask for other editors opinion".
- Only confirmed conflicts will be displayed in the conflict management section

### Fixed

- Ask revision: possibility to backdate the deadline.
- Fixed: RT #160301:
  the tags [%%SENDER_FULL_NAME%%, %%SENDER_SCREEN_NAME%%, %%SENDER_EMAIL%%, %%SENDER_FIRST_NAME%%', %%SENDER_LAST_NAME%% ]
  concerning the user of the action are filled with the data of the user connected at the time of the action.
  Making these variables available in the automatic mails poses a real problem: they are filled with the data of the mail recipient.
  So, from now on, the tags mentioned above will no longer be available in the automatic mail templates.
- Shifted display because of the error message (CSRF token)

## 1.0.30 - 2022-06-20

### Fixed

- Titles do not appear in the correct language when more than one language has been entered (e.g. SLOVO )

### Added

- It is now possible to manage conflicts on the article page: display & delete them

## 1.0.29.1 - 2022-06-13

### Changed

- Revision requests: now we have an immediate visual feedback when changing the deadline

### Fixed

- [#247](https://github.com/CCSDForge/episciences/issues/247):
  HTML links in outgoing emails are made relative [#247]: TinyMCE configuration: convert all relative URLs to absolute URLs.
- Edition of a volume: The title of article might not be retrieved if the language was not managed by the platform.
- Profile editing form does not display correctly when changing language.

## 1.0.29 - 2022-06-01

### Fixed

- Failed 'Not enough reviewers - editor copy' reminders.
- But leading to load journal translations from '/'.
- PHP Warning: Use of undefined constant RVID - assumed 'RVID' (this will throw an Error in a future version of PHP)
- Not enough review reminders: articles without invitations escape reminders because the function that retrieves invitations is not used properly.

### Added

- User list: make it easier to locate accounts that have not been activated.
- Addes Roadmap link + User survey/feedback on portal
- Make EU grants and OpenAIRE support more visible at the bottom of the page

## 1.0.28.5 - 2022-05-25

### Fixed

- [RT#158293]: article status is not correctly updated

## 1.0.28.4 - 2022-05-09

### Fixed

- File not found for Temporary Versions without attached files + refactoring.

## 1.0.28.3 - 2022-04-13

### Changed

- Updated error message when using a HAL identifier without any file.
- [#142](https://github.com/CCSDForge/episciences/issues/142)

## 1.0.28.2 - 2022-04-06

### Fixed

- Empty records from HAL repository: error message improved.
- Improvements and fixes on Datacite and Crossref XML exports

## 1.0.28.1 - 2022-03-23

### Fixed

- fix issue in crossref xml format with related_item

## 1.0.28 - 2022-03-23

### Changed

- Bump crossref schema to ver 4.8.1
- arXiv URLs now links to https version instead of http

### Added

- Crossref metadata export format for DOI:
    - add previous version URLs, link with relationship 'hasPreprint' to link preprints versions with the published version
    - add arXiv DOIs, link to journal's published DOI with 'isSameAs' relationship
    - add full text PDF URLs for Crossref 'Similarity Check' service

## 1.0.27.5 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format
- Adding Polyfill PHP 8.0

## 1.0.27.4 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format

## 1.0.27.3 - 2022-03-18

### Fixed

- Ratings submitted late after the start of the layout process caused the status of the article to be updated (rolled back).
- There is no need to review an article while it is being formatted by the author.

## 1.0.27.2 - 2022-03-18

### Fixed

- "Copy editor" role not authorized accessing to the public page of the paper
  and report a conflict of interest, if the option has been activated by the journal

## 1.0.27.1 - 2022-03-16

### Fixed

- Exclude the "paper-status" directory from the journal's resources.
- Content that is copied and pasted when reviewing an article may lose end of line.
- The linked data to an article is not deleted when the author deletes his own article

## 1.0.27 - 2022-03-14

# Changed

- Reviewer invitation: Users are now sorted by Name, First Name instead of First Name

# Fixed

- Dashboard: the number of papers assigned to the copy editor includes obsolete papers
- Issue reported to support RT #148133 (fixed before this release):
    - Some editors could see the reviewers names if they were also authors of the article

## 1.0.26 - 2022-03-01

# Fixed

- Issue reported to support RT #148133:
    - Incorrect "Reminder Delay: The "Reminder Delay" should correspond, in number of days, to the difference between the date the reminder was sent and the date the invitation was sent.
- Issue reported to support RT #148466:
    - Sorting by date in the Dashboard is wrong: Enabling the conflict of interest (COI) option distorts the pagination

## 1.0.25.1 - 2022-02-15

# Changed

[#152](https://github.com/CCSDForge/episciences/issues/152): page footer modifications.

## 1.0.25 - 2022-02-15

# Fixed

- All submissions are accessible to "guest editor" roles.
- Language bar missing when editing a mail template, including reminders.

## 1.0.24.2 - 2022-02-10

# Fixed

- [RT#146987] "search" button no longer works
- application error: syntax error or access violation if CAS user not found

## 1.0.24.1 - 2022-02-08

# Fixed

- A bug in the reminders code triggered emails with unusable HTTP links

## 1.0.24 - 2022-02-08

# Fixed

- Inability to delete a volume (RT#145178): reset the volume of the previous version when submitting a new version.
- [#117](https://github.com/CCSDForge/episciences/issues/117) Improvements in emailing
- Undetected inactive accounts: Invite a reviewer > new reviewer > Invite a new user.
- Not allowing the author to be invited: Invite a reviewer > new reviewer > Invite a new user.
- The attachments to the rating report are not available [RT#145473]
- Answer revision request: in some situations, the "Submit" button remains inactive.

## 1.0.23 - 2022-01-19

### Added

- [#116](https://github.com/CCSDForge/episciences/issues/116) Release version is displayed on the interface (dashboard and page footer)
- Editors' choice at time of submission: informative text added. (gitlab #369)
- [#143](https://github.com/CCSDForge/episciences/issues/143) New DOI setting: Allow switching off manual or automatic DOI assignation (disabled mode)
- [#37](https://github.com/CCSDForge/episciences/issues/37)
    - Browse by Volume or Sections : Handle new content type 'application/json' to return json instead of html
    - On URLs like /volume/view/id/314159 and /section/view/id/314159 Handle new content type 'application/json' to return json instead of html (only published content)
    - On URLs like /volume/edit?id=314159 Handle new content type 'application/json' to return json instead of html but including all statuses of articles ; an authenticated and allowed user is required
    - JSON added to the list of public export formats
- Enhanced information message for statistics

### Changed

- UI/UX: Make the version number more explicit when proposing a new version
- Code Refactoring fixing of warning
- Dumping default data for table `MAIL_TEMPLATE`

### Fixed

- [#126](https://github.com/CCSDForge/episciences/issues/126) Clicking outside the window to compose an email closes the window with no confirmation #126
- [#117](https://github.com/CCSDForge/episciences/issues/117) Mailing bug: loss of the mail when you forget to put a recipient (gitlab #343)
- [#149](https://github.com/CCSDForge/episciences/issues/149) Emails and default language selection: language of the sender and the recipient could be a problem if the language of the sender is different from the language of the recipient (gitlab #402)
- Inconsistent REMINDER_DELAY: the displayed value is the "delay" parameter of the reminder instead of the calculated value (interval in number of days between the time the reminder was sent and the deadline).
- New submission: if the "submission date" metadata is empty: do not display it anymore.
- [#124](https://github.com/CCSDForge/episciences/issues/124): refining automatic notifications when abandoning submission
- RT#144252: better cleaning of attached file names
- RT#144246: an inactive account could still be visible on the Editorial Staff members page.
- [#117](https://github.com/CCSDForge/episciences/issues/117): An empty e-mail is not sent or received
- [#129](https://github.com/CCSDForge/episciences/issues/129): when you do a search from the mail history page nothing happens.
- [#141](https://github.com/CCSDForge/episciences/issues/141): visibility of pages menu is wrong inside menu editing.

### Changed

- [#126](https://github.com/CCSDForge/episciences/issues/126): prevent modal closure: disabling the click outside the modal area and by pressing Esc
- Enabling the "COI" option overrides the "Encapsulate editors" option

## 1.0.38.5 - 2022-12-14

### Fixed

- [#294](https://github.com/CCSDForge/episciences/issues/294): untranslated text
- API to return number of published documents

## 1.0.38.4 - 2022-12-12

### Fixed

- JS error: Failed to load plugin url: https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/langs/fr_FR.js
  @see https://www.tiny.cloud/docs-4x/configure/localization/#language
- Add %%PAPER_REP_URL%% tag to the description of the invitation templates.
- Change password: redirect to authentication if user is not logged in
- The '%%SENDER_SCREEN_NAME%%' tag is always present in the 'paper_paper_editor_refused_monitoring' template

## 1.0.38.3 - 2022-11-30

### Fixed

- Html filtering problem that breaks the display in the comments

## 1.0.38.2 - 2022-11-24

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): There is a difference between the capitalization of the first and last names in the user's profile
  and the first and last names of the user in the email recipient field [RT#148137]
- Fixed the fact that the Administrator role prevents testing for conflict of interest
- Error: $(...).fileupload is not a function for published articles.

### Changed

- Conflict management section: set default display lines to 5.
- Mailing > getContacts: add 'Authors' filter.
- resolved [#282](https://github.com/CCSDForge/episciences/issues/282): C:\fakepath: This is the security
  implementation of the browser: the browser is protecting you from accessing your disk structure.
- Code review / refactoring

## 1.0.38.1 - 2022-11-16

### Fixed

- Added missing translation for conflict of interest

### Changed

- Footer: replaced TOS with Term Of Use

## 1.0.38 - 2022-11-16

### Added

- New footer link to the Term of use (between the platform and users)

### Fixed

- Fixed ORCID 'X' in DOAJ export format, make it compliant with ORCID specification
- [RT#171463]: the reviews should not be seen by someone with a CoI declaration with
  the paper: this fix concerns all the paper's details.

### Changed

- UI/UX: Improved rendering of the "conflict management" section
- [RT#170200]: confirmation of the absence of conflict of interest: it is now possible to cancel this type of response.

## 1.0.37 - 2022-11-10

### Added

- New feature: [Open Science Lens](https://www.opensciencelens.eu/) feature available as a preview on selected journals

### Changed

- Improved rendering of volumes

## 1.0.36 - 2022-11-09

### Added

- Improved rendering of the volume page
- authors can now enter their paper password provided by the open archive on the article page.
- alt attribute to volume's image.
- Added an API method to get the number of users accounts

### Changed

- Optimization: there is no need to check for conflicts at the time of submission
- [#280](https://github.com/CCSDForge/episciences/issues/280): set spellcheck to false in dynamic datatable search boxes.
- [#281](https://github.com/CCSDForge/episciences/issues/281): Submit an article > Guidelines section: harmonization of journal/review terms [FR]
- Now, when an article is accepted, all unanswered invitations are deleted. The reviewer is informed of this action.
- Add 'ISSN pending' until the ISSN is actually issued

### Fixed

- Fix incorrect HTML in footer
- Incorrect paper status LMCS #10145 (related to https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#10273---2022-03-18)
- Empty %%REQUESTER_EXPRESSION%% tag (related to [#187](https://github.com/CCSDForge/episciences/issues/187)
- Incorrect status jdmdh#10203 (related with git#372)
- Fixed [#RT169943]: the tag %%SENDER_SCREEN_NAME%% (obsolete) that should have contained the editor's screen name has not been replaced:
  to be replaced in custom templates by %%EDITOR_FULL_NAME%% or %%EDITOR_SCREEN_NAME%%
  (@see https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#fixed-11 [RT##160301])

## 1.0.35 - 2022-10-19

### Added

- [RT#169107]: Feature - new option: do not allow the selection of an editor-in-chief when the author has the option to
  propose an editor at the time of submission.

### Changed

- [#255](https://github.com/CCSDForge/episciences/issues/255) Display ratings reports on the article page: Label updated

### Fixed

- [RT#169088]: Editors-in-chief and editorial secretaries are no longer notified of new submissions.

## 1.0.34.2 - 2022-10-18

### Fixed

- temporary fix: TinyMCE: loss of formatting for successive mailings
- Insert data in 'T_PAPER_DATASETS' table: MySql insert 0 instead of NULL
- [#273](https://github.com/CCSDForge/episciences/issues/273): if the editor changes the rating due date, then this
  is not reflected in the message.
- Fixes in exports format related to trailing ',' and language codes

## 1.0.34.1 - 2022-10-16

### Fixed

- Fixed bad JS escape of authors in reviewer invitation form
- Fixed Handle URLs links adding https://hdl.handle.net/
- Fixed display of disabled buttons
- Fixed 'Description of affiliation block'

### Added

- The activation of maintenance alerts is now configurable

## 1.0.34 - 2022-10-13

### Added

- Article managers (authors, editors, etc.) may add new optional - but recommended - metadata to the documents
    - [ORCID](https://orcid.org/)
    - Affiliations: for affiliations the [ROR](https://ror.org/) or a free text may be used. If an institution is not available in the ROR, a
      simple text entry may be used.
- Automatic enrichment of metadata:
    - Licenses: retrieved from [Datacite](https://datacite.org/), [OpenAIRE Research Graph](https://graph.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/)
    - ORCID: retrieved from [OpenAIRE Research Graph](https://graph.openaire.eu/)
    - Linked Datasets: [Scholexplorer](https://scholexplorer.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/): Datasets linked to Episciences
      publications
    - Funding: Research projects from [OpenAIRE Research Graph](https://graph.openaire.eu/) and [HAL](https://hal.archives-ouvertes.fr/)
    - Citations: Citations of published document retrieved with [OpenCitations](https://opencitations.net/) APIs
- Exports formats:
    - [DOAJ](https://doaj.org/about/) (export one document or a whole published volume) using DOAJ Schema
    - The new collected metadata have been added to Datacite, [Crossref](https://www.crossref.org/) and DOAJ export formats
- Browse by volume: new parameter to allow to display empty volumes. Default value is still no (only show volumes
  with papers published in the volume)
- [#186](https://github.com/CCSDForge/episciences/issues/186): Editors-in-chief can now report a conflict of
  interest. (Sign in an admin as another user: the real identity is now saved.)

### Fixed

- Fixed a display bug on the portal in connected mode (used by Episciences staff only)
- Failed to edit another account's profile from the user management page.
- impossibility to validate the profile if the user does not have an account in the application

### Changed

- new parameter for enabling/disabling submission from the archive to the application and vice versa.

## 1.0.33.2 - 2022-10-05

### Fixed

- RT#167820: all occurrences of tags in a template will be replaced by their real values.

## 1.0.33.1 - 2022-09-29

### Fixed

- Allow editors to view the list of conflicts on the article management page
- The number of conflicts indicated on the article management page is incorrect if the article has several versions

## 1.0.33 - 2022-09-28

### Added

- New page listing all email templates and available tags for each journal at /administratemail/tagslist
  (- Mail -> TagList)
- Episciences portal:
    - New Feed RSS + Atom : latest published documents, 2 docs per journal
    - API to get a list of publishing journals: include accepted repositories.
- Episciences journals
    - Added 'journals news' RSS feed for each journal
- New journal settings parameter: Allow post-acceptance revisions of articles
- 9 article statuses, available for journals allowing 'post-acceptance revisions of articles':
    - Accepted temporary version, waiting for author's final version: now, temporary versions that are accepted have this new status instead of "pending minor revision" (git #372)
    - Accepted - waiting for author's final version
    - Accepted, waiting for major revision
    - Accepted article - final version submitted, waiting for formatting by copy editors
    - Accepted temporary version after author's modifications
    - Accepted temporary version, waiting for minor revision
    - Accepted temporary version, waiting for major revision
    - Accepted - waiting validation by the author
    - Approved by author, waiting for final publication
- User profiles:
    - Adding ORCID and affiliation to your profile is now possible + this information is diplayed on the
      "editorial staff member" (gitlab #410) & dashboard pages.
    - Social Medias & Websites profile informations.
- New 'Author' role automatically added to users that have submitted a document
- Paper list: temporary versions now have a specific label
- Dashboard: Alert for administrators on the existence of papers without assigned editors
- Display the origin of a linked data when papers are linked to a dataset
- Administration page of a paper: new shortcut for administrators :
    - to allow to sign in as a copy-editor
    - to allow to sign in as an editor
- [227](https://github.com/CCSDForge/episciences/issues/227):
    - The revision deadline is now displayed below the article's status.
    - A pictogram is added to indicate the date of the revision deadline in the article management table.

### Changed

- [#237](https://github.com/CCSDForge/episciences/issues/237) Editor comments div is now bigger
- Upgraded publication RSS Feeds with DOIs
- [#142](https://github.com/CCSDForge/episciences/issues/142): allow that administrators answer revision and copy editing requests
- It is now possible to update the metadata on the document's page
- Request a new version of an article that has already been accepted
- [#166](https://github.com/CCSDForge/episciences/issues/116): hide "guest" role and doi-settings action
- The "paper status" filter is now dynamically created.
- Prevent "Add sources files" and "Add the formatted version" buttons JS reactivation
- Moved "copy editing" section to a more usable place on the same page
- Dashboard improvements: ability to reach an article from sections: my submissions and assigned articles
- [#187](https://github.com/CCSDForge/episciences/issues/187): change the default templates: 'new version submitted' and 'tmp version submitted'
- [#188](https://github.com/CCSDForge/episciences/issues/188): force notifications
- It is now possible to update the document version in Episciences to the most recent version in the open archive.
- [#164](https://github.com/CCSDForge/episciences/issues/164): harmonization of roles/privileges.
- CWI open repository has been temporarily removed until we adapt to their new OAI server

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): capitalization of names
- [#207](https://github.com/CCSDForge/episciences/issues/207): editing the translation of Chief Editors
- [#169](https://github.com/CCSDForge/episciences/issues/169): reports become visible on the article web page:
    - according to the parameters of the journal for published articles
    - for the owner, only if the paper is refused, waiting for revision, already accepted or published
- Another status (Revision request answer: without any modifications) is used when responding to a revision request: without any modifications.
- Revision requests: now we have an immediate visual feedback when changing the deadline.
- If the journal allows revision of articles after acceptance, the response to a request for a final version (without any changes) is similar to the proposal of a new version.

### Internal refactoring

- Code Refactoring fixing of warning
- Update Episciences_User::getScreenName()
- User Table: merge AFFILIATIONS" filed in "ADDITIONAL_PROFILE_INFORMATION"
- Multiple roles: ignore the "member" role when merging two accounts.
- Email Templates updates
- Script to clean the "USER_ROLES" table
- Parameter to detect automatic emails

## 1.0.32.2 - 2022-09-28

### Changed

- [196](https://github.com/CCSDForge/episciences/issues/196): Creation of an account following an invitation with a temporary account: from now on, the Last Name, First Name and Screen Name fields will be left empty (for the reviewer to fill in)
- [RT#164153]: refactoring: now obsolete template "paper_new_version_reviewer_reassign" (Reviewer assignment to a new version of an article) is removed.

### Fixed:

- account merging incomplete due to sql error when updating 'paper_conflicts' table.

## 1.0.32.1 - 2022-09-01

### Fixed

- Fixed bug introduced with [196] "Name" => "Last Name" when creating a new reviewer
- Fixed release notes links to issues

## 1.0.32 - 2022-08-31

### Changed

- [201](https://github.com/CCSDForge/episciences/issues/201): depending on journal settings, editor can accept, ask revision, reject and publish papers in every round.
- [238](https://github.com/CCSDForge/episciences/issues/238): harmonization of terms 'sections/rubriques'.
- [196](https://github.com/CCSDForge/episciences/issues/196) now,
    - Only one 'name' field is available when creating a new reviewer
    - Reviewers are sorted by lastname by default
- Empty TAG %%RECIPIENT_SCREEN_NAME%% for users who do not have a local account in "Unanswered reviewer invitation (reviewer copy)" reminder.
- (Internal/refactoring) transition to PHP 8: refactoring: "MaxMind GeoIP2 PHP API" is now used instead of PHP module.

### Fixed

- [RT#163166]: Problem sending mail from staff page.
- Fixed [#165](https://github.com/CCSDForge/episciences/issues/165): Line breaks in paper titles in API
- Fixed [#251](https://github.com/CCSDForge/episciences/issues/251): conform to ISO 3297 for displaying ISSNs

## Added

- Episciences portal: New Feed RSS + Atom : latest published documents, 2 docs per journal
- Added 'journals news' RSS feed

## 1.0.31.1 - 2022-07-13

### Changed

- User accounts merging procedure: take into account the Conflict Of Interests

## 1.0.31 - 2022-06-28

### Added

- A pictogram is added to indicate papers with conflicts in the article management table.

### Changed

- [192](https://github.com/CCSDForge/episciences/issues/192): allow editors to "Ask for other editors opinion".
- Only confirmed conflicts will be displayed in the conflict management section

### Fixed

- Ask revision: possibility to backdate the deadline.
- Fixed: RT #160301:
  the tags [%%SENDER_FULL_NAME%%, %%SENDER_SCREEN_NAME%%, %%SENDER_EMAIL%%, %%SENDER_FIRST_NAME%%', %%SENDER_LAST_NAME%% ]
  concerning the user of the action are filled with the data of the user connected at the time of the action.
  Making these variables available in the automatic mails poses a real problem: they are filled with the data of the mail recipient.
  So, from now on, the tags mentioned above will no longer be available in the automatic mail templates.
- Shifted display because of the error message (CSRF token)

## 1.0.30 - 2022-06-20

### Fixed

- Titles do not appear in the correct language when more than one language has been entered (e.g. SLOVO )

### Added

- It is now possible to manage conflicts on the article page: display & delete them

## 1.0.29.1 - 2022-06-13

### Changed

- Revision requests: now we have an immediate visual feedback when changing the deadline

### Fixed

- [#247](https://github.com/CCSDForge/episciences/issues/247):
  HTML links in outgoing emails are made relative [#247]: TinyMCE configuration: convert all relative URLs to absolute URLs.
- Edition of a volume: The title of article might not be retrieved if the language was not managed by the platform.
- Profile editing form does not display correctly when changing language.

## 1.0.29 - 2022-06-01

### Fixed

- Failed 'Not enough reviewers - editor copy' reminders.
- But leading to load journal translations from '/'.
- PHP Warning: Use of undefined constant RVID - assumed 'RVID' (this will throw an Error in a future version of PHP)
- Not enough review reminders: articles without invitations escape reminders because the function that retrieves invitations is not used properly.

### Added

- User list: make it easier to locate accounts that have not been activated.
- Addes Roadmap link + User survey/feedback on portal
- Make EU grants and OpenAIRE support more visible at the bottom of the page

## 1.0.28.5 - 2022-05-25

### Fixed

- [RT#158293]: article status is not correctly updated

## 1.0.28.4 - 2022-05-09

### Fixed

- File not found for Temporary Versions without attached files + refactoring.

## 1.0.28.3 - 2022-04-13

### Changed

- Updated error message when using a HAL identifier without any file.
- [#142](https://github.com/CCSDForge/episciences/issues/142)

## 1.0.28.2 - 2022-04-06

### Fixed

- Empty records from HAL repository: error message improved.
- Improvements and fixes on Datacite and Crossref XML exports

## 1.0.28.1 - 2022-03-23

### Fixed

- fix issue in crossref xml format with related_item

## 1.0.28 - 2022-03-23

### Changed

- Bump crossref schema to ver 4.8.1
- arXiv URLs now links to https version instead of http

### Added

- Crossref metadata export format for DOI:
    - add previous version URLs, link with relationship 'hasPreprint' to link preprints versions with the published version
    - add arXiv DOIs, link to journal's published DOI with 'isSameAs' relationship
    - add full text PDF URLs for Crossref 'Similarity Check' service

## 1.0.27.5 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format
- Adding Polyfill PHP 8.0

## 1.0.27.4 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format

## 1.0.27.3 - 2022-03-18

### Fixed

- Ratings submitted late after the start of the layout process caused the status of the article to be updated (rolled back).
- There is no need to review an article while it is being formatted by the author.

## 1.0.27.2 - 2022-03-18

### Fixed

- "Copy editor" role not authorized accessing to the public page of the paper
  and report a conflict of interest, if the option has been activated by the journal

## 1.0.27.1 - 2022-03-16

### Fixed

- Exclude the "paper-status" directory from the journal's resources.
- Content that is copied and pasted when reviewing an article may lose end of line.
- The linked data to an article is not deleted when the author deletes his own article

## 1.0.27 - 2022-03-14

# Changed

- Reviewer invitation: Users are now sorted by Name, First Name instead of First Name

# Fixed

- Dashboard: the number of papers assigned to the copy editor includes obsolete papers
- Issue reported to support RT #148133 (fixed before this release):
    - Some editors could see the reviewers names if they were also authors of the article

## 1.0.26 - 2022-03-01

# Fixed

- Issue reported to support RT #148133:
    - Incorrect "Reminder Delay: The "Reminder Delay" should correspond, in number of days, to the difference between the date the reminder was sent and the date the invitation was sent.
- Issue reported to support RT #148466:
    - Sorting by date in the Dashboard is wrong: Enabling the conflict of interest (COI) option distorts the pagination

## 1.0.25.1 - 2022-02-15

# Changed

[#152](https://github.com/CCSDForge/episciences/issues/152): page footer modifications.

## 1.0.25 - 2022-02-15

# Fixed

- All submissions are accessible to "guest editor" roles.
- Language bar missing when editing a mail template, including reminders.

## 1.0.24.2 - 2022-02-10

# Fixed

- [RT#146987] "search" button no longer works
- application error: syntax error or access violation if CAS user not found

## 1.0.24.1 - 2022-02-08

# Fixed

- A bug in the reminders code triggered emails with unusable HTTP links

## 1.0.24 - 2022-02-08

# Fixed

- Inability to delete a volume (RT#145178): reset the volume of the previous version when submitting a new version.
- [#117](https://github.com/CCSDForge/episciences/issues/117) Improvements in emailing
- Undetected inactive accounts: Invite a reviewer > new reviewer > Invite a new user.
- Not allowing the author to be invited: Invite a reviewer > new reviewer > Invite a new user.
- The attachments to the rating report are not available [RT#145473]
- Answer revision request: in some situations, the "Submit" button remains inactive.

## 1.0.23 - 2022-01-19

### Added

- [#116](https://github.com/CCSDForge/episciences/issues/116) Release version is displayed on the interface (dashboard and page footer)
- Editors' choice at time of submission: informative text added. (gitlab #369)
- [#143](https://github.com/CCSDForge/episciences/issues/143) New DOI setting: Allow switching off manual or automatic DOI assignation (disabled mode)
- [#37](https://github.com/CCSDForge/episciences/issues/37)
    - Browse by Volume or Sections : Handle new content type 'application/json' to return json instead of html
    - On URLs like /volume/view/id/314159 and /section/view/id/314159 Handle new content type 'application/json' to return json instead of html (only published content)
    - On URLs like /volume/edit?id=314159 Handle new content type 'application/json' to return json instead of html but including all statuses of articles ; an authenticated and allowed user is required
    - JSON added to the list of public export formats
- Enhanced information message for statistics

### Changed

- UI/UX: Make the version number more explicit when proposing a new version
- Code Refactoring fixing of warning
- Dumping default data for table `MAIL_TEMPLATE`

### Fixed

- [#126](https://github.com/CCSDForge/episciences/issues/126) Clicking outside the window to compose an email closes the window with no confirmation #126
- [#117](https://github.com/CCSDForge/episciences/issues/117) Mailing bug: loss of the mail when you forget to put a recipient (gitlab #343)
- [#149](https://github.com/CCSDForge/episciences/issues/149) Emails and default language selection: language of the sender and the recipient could be a problem if the language of the sender is different from the language of the recipient (gitlab #402)
- Inconsistent REMINDER_DELAY: the displayed value is the "delay" parameter of the reminder instead of the calculated value (interval in number of days between the time the reminder was sent and the deadline).
- New submission: if the "submission date" metadata is empty: do not display it anymore.
- [#124](https://github.com/CCSDForge/episciences/issues/124): refining automatic notifications when abandoning submission
- RT#144252: better cleaning of attached file names
- RT#144246: an inactive account could still be visible on the Editorial Staff members page.
- [#117](https://github.com/CCSDForge/episciences/issues/117): An empty e-mail is not sent or received
- [#129](https://github.com/CCSDForge/episciences/issues/129): when you do a search from the mail history page nothing happens.
- [#141](https://github.com/CCSDForge/episciences/issues/141): visibility of pages menu is wrong inside menu editing.

### Changed

- [#126](https://github.com/CCSDForge/episciences/issues/126): prevent modal closure: disabling the click outside the modal area and by pressing Esc
- Enabling the "COI" option overrides the "Encapsulate editors" option

## 1.0.38.5 - 2022-12-14

### Fixed

- [#294](https://github.com/CCSDForge/episciences/issues/294): untranslated text
- API to return number of published documents

## 1.0.38.4 - 2022-12-12

### Fixed

- JS error: Failed to load plugin url: https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/langs/fr_FR.js
  @see https://www.tiny.cloud/docs-4x/configure/localization/#language
- Add %%PAPER_REP_URL%% tag to the description of the invitation templates.
- Change password: redirect to authentication if user is not logged in
- The '%%SENDER_SCREEN_NAME%%' tag is always present in the 'paper_paper_editor_refused_monitoring' template

## 1.0.38.3 - 2022-11-30

### Fixed

- Html filtering problem that breaks the display in the comments

## 1.0.38.2 - 2022-11-24

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): There is a difference between the capitalization of the first and last names in the user's profile
  and the first and last names of the user in the email recipient field [RT#148137]
- Fixed the fact that the Administrator role prevents testing for conflict of interest
- Error: $(...).fileupload is not a function for published articles.

### Changed

- Conflict management section: set default display lines to 5.
- Mailing > getContacts: add 'Authors' filter.
- resolved [#282](https://github.com/CCSDForge/episciences/issues/282): C:\fakepath: This is the security
  implementation of the browser: the browser is protecting you from accessing your disk structure.
- Code review / refactoring

## 1.0.38.1 - 2022-11-16

### Fixed

- Added missing translation for conflict of interest

### Changed

- Footer: replaced TOS with Term Of Use

## 1.0.38 - 2022-11-16

### Added

- New footer link to the Term of use (between the platform and users)

### Fixed

- Fixed ORCID 'X' in DOAJ export format, make it compliant with ORCID specification
- [RT#171463]: the reviews should not be seen by someone with a CoI declaration with
  the paper: this fix concerns all the paper's details.

### Changed

- UI/UX: Improved rendering of the "conflict management" section
- [RT#170200]: confirmation of the absence of conflict of interest: it is now possible to cancel this type of response.

## 1.0.37 - 2022-11-10

### Added

- New feature: [Open Science Lens](https://www.opensciencelens.eu/) feature available as a preview on selected journals

### Changed

- Improved rendering of volumes

## 1.0.36 - 2022-11-09

### Added

- Improved rendering of the volume page
- authors can now enter their paper password provided by the open archive on the article page.
- alt attribute to volume's image.
- Added an API method to get the number of users accounts

### Changed

- Optimization: there is no need to check for conflicts at the time of submission
- [#280](https://github.com/CCSDForge/episciences/issues/280): set spellcheck to false in dynamic datatable search boxes.
- [#281](https://github.com/CCSDForge/episciences/issues/281): Submit an article > Guidelines section: harmonization of journal/review terms [FR]
- Now, when an article is accepted, all unanswered invitations are deleted. The reviewer is informed of this action.
- Add 'ISSN pending' until the ISSN is actually issued

### Fixed

- Fix incorrect HTML in footer
- Incorrect paper status LMCS #10145 (related to https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#10273---2022-03-18)
- Empty %%REQUESTER_EXPRESSION%% tag (related to [#187](https://github.com/CCSDForge/episciences/issues/187)
- Incorrect status jdmdh#10203 (related with git#372)
- Fixed [#RT169943]: the tag %%SENDER_SCREEN_NAME%% (obsolete) that should have contained the editor's screen name has not been replaced:
  to be replaced in custom templates by %%EDITOR_FULL_NAME%% or %%EDITOR_SCREEN_NAME%%
  (@see https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#fixed-11 [RT##160301])

## 1.0.35 - 2022-10-19

### Added

- [RT#169107]: Feature - new option: do not allow the selection of an editor-in-chief when the author has the option to
  propose an editor at the time of submission.

### Changed

- [#255](https://github.com/CCSDForge/episciences/issues/255) Display ratings reports on the article page: Label updated

### Fixed

- [RT#169088]: Editors-in-chief and editorial secretaries are no longer notified of new submissions.

## 1.0.34.2 - 2022-10-18

### Fixed

- temporary fix: TinyMCE: loss of formatting for successive mailings
- Insert data in 'T_PAPER_DATASETS' table: MySql insert 0 instead of NULL
- [#273](https://github.com/CCSDForge/episciences/issues/273): if the editor changes the rating due date, then this
  is not reflected in the message.
- Fixes in exports format related to trailing ',' and language codes

## 1.0.34.1 - 2022-10-16

### Fixed

- Fixed bad JS escape of authors in reviewer invitation form
- Fixed Handle URLs links adding https://hdl.handle.net/
- Fixed display of disabled buttons
- Fixed 'Description of affiliation block'

### Added

- The activation of maintenance alerts is now configurable

## 1.0.34 - 2022-10-13

### Added

- Article managers (authors, editors, etc.) may add new optional - but recommended - metadata to the documents
    - [ORCID](https://orcid.org/)
    - Affiliations: for affiliations the [ROR](https://ror.org/) or a free text may be used. If an institution is not available in the ROR, a
      simple text entry may be used.
- Automatic enrichment of metadata:
    - Licenses: retrieved from [Datacite](https://datacite.org/), [OpenAIRE Research Graph](https://graph.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/)
    - ORCID: retrieved from [OpenAIRE Research Graph](https://graph.openaire.eu/)
    - Linked Datasets: [Scholexplorer](https://scholexplorer.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/): Datasets linked to Episciences
      publications
    - Funding: Research projects from [OpenAIRE Research Graph](https://graph.openaire.eu/) and [HAL](https://hal.archives-ouvertes.fr/)
    - Citations: Citations of published document retrieved with [OpenCitations](https://opencitations.net/) APIs
- Exports formats:
    - [DOAJ](https://doaj.org/about/) (export one document or a whole published volume) using DOAJ Schema
    - The new collected metadata have been added to Datacite, [Crossref](https://www.crossref.org/) and DOAJ export formats
- Browse by volume: new parameter to allow to display empty volumes. Default value is still no (only show volumes
  with papers published in the volume)
- [#186](https://github.com/CCSDForge/episciences/issues/186): Editors-in-chief can now report a conflict of
  interest. (Sign in an admin as another user: the real identity is now saved.)

### Fixed

- Fixed a display bug on the portal in connected mode (used by Episciences staff only)
- Failed to edit another account's profile from the user management page.
- impossibility to validate the profile if the user does not have an account in the application

### Changed

- new parameter for enabling/disabling submission from the archive to the application and vice versa.

## 1.0.33.2 - 2022-10-05

### Fixed

- RT#167820: all occurrences of tags in a template will be replaced by their real values.

## 1.0.33.1 - 2022-09-29

### Fixed

- Allow editors to view the list of conflicts on the article management page
- The number of conflicts indicated on the article management page is incorrect if the article has several versions

## 1.0.33 - 2022-09-28

### Added

- New page listing all email templates and available tags for each journal at /administratemail/tagslist
  (- Mail -> TagList)
- Episciences portal:
    - New Feed RSS + Atom : latest published documents, 2 docs per journal
    - API to get a list of publishing journals: include accepted repositories.
- Episciences journals
    - Added 'journals news' RSS feed for each journal
- New journal settings parameter: Allow post-acceptance revisions of articles
- 9 article statuses, available for journals allowing 'post-acceptance revisions of articles':
    - Accepted temporary version, waiting for author's final version: now, temporary versions that are accepted have this new status instead of "pending minor revision" (git #372)
    - Accepted - waiting for author's final version
    - Accepted, waiting for major revision
    - Accepted article - final version submitted, waiting for formatting by copy editors
    - Accepted temporary version after author's modifications
    - Accepted temporary version, waiting for minor revision
    - Accepted temporary version, waiting for major revision
    - Accepted - waiting validation by the author
    - Approved by author, waiting for final publication
- User profiles:
    - Adding ORCID and affiliation to your profile is now possible + this information is diplayed on the
      "editorial staff member" (gitlab #410) & dashboard pages.
    - Social Medias & Websites profile informations.
- New 'Author' role automatically added to users that have submitted a document
- Paper list: temporary versions now have a specific label
- Dashboard: Alert for administrators on the existence of papers without assigned editors
- Display the origin of a linked data when papers are linked to a dataset
- Administration page of a paper: new shortcut for administrators :
    - to allow to sign in as a copy-editor
    - to allow to sign in as an editor
- [227](https://github.com/CCSDForge/episciences/issues/227):
    - The revision deadline is now displayed below the article's status.
    - A pictogram is added to indicate the date of the revision deadline in the article management table.

### Changed

- [#237](https://github.com/CCSDForge/episciences/issues/237) Editor comments div is now bigger
- Upgraded publication RSS Feeds with DOIs
- [#142](https://github.com/CCSDForge/episciences/issues/142): allow that administrators answer revision and copy editing requests
- It is now possible to update the metadata on the document's page
- Request a new version of an article that has already been accepted
- [#166](https://github.com/CCSDForge/episciences/issues/116): hide "guest" role and doi-settings action
- The "paper status" filter is now dynamically created.
- Prevent "Add sources files" and "Add the formatted version" buttons JS reactivation
- Moved "copy editing" section to a more usable place on the same page
- Dashboard improvements: ability to reach an article from sections: my submissions and assigned articles
- [#187](https://github.com/CCSDForge/episciences/issues/187): change the default templates: 'new version submitted' and 'tmp version submitted'
- [#188](https://github.com/CCSDForge/episciences/issues/188): force notifications
- It is now possible to update the document version in Episciences to the most recent version in the open archive.
- [#164](https://github.com/CCSDForge/episciences/issues/164): harmonization of roles/privileges.
- CWI open repository has been temporarily removed until we adapt to their new OAI server

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): capitalization of names
- [#207](https://github.com/CCSDForge/episciences/issues/207): editing the translation of Chief Editors
- [#169](https://github.com/CCSDForge/episciences/issues/169): reports become visible on the article web page:
    - according to the parameters of the journal for published articles
    - for the owner, only if the paper is refused, waiting for revision, already accepted or published
- Another status (Revision request answer: without any modifications) is used when responding to a revision request: without any modifications.
- Revision requests: now we have an immediate visual feedback when changing the deadline.
- If the journal allows revision of articles after acceptance, the response to a request for a final version (without any changes) is similar to the proposal of a new version.

### Internal refactoring

- Code Refactoring fixing of warning
- Update Episciences_User::getScreenName()
- User Table: merge AFFILIATIONS" filed in "ADDITIONAL_PROFILE_INFORMATION"
- Multiple roles: ignore the "member" role when merging two accounts.
- Email Templates updates
- Script to clean the "USER_ROLES" table
- Parameter to detect automatic emails

## 1.0.32.2 - 2022-09-28

### Changed

- [196](https://github.com/CCSDForge/episciences/issues/196): Creation of an account following an invitation with a temporary account: from now on, the Last Name, First Name and Screen Name fields will be left empty (for the reviewer to fill in)
- [RT#164153]: refactoring: now obsolete template "paper_new_version_reviewer_reassign" (Reviewer assignment to a new version of an article) is removed.

### Fixed:

- account merging incomplete due to sql error when updating 'paper_conflicts' table.

## 1.0.32.1 - 2022-09-01

### Fixed

- Fixed bug introduced with [196] "Name" => "Last Name" when creating a new reviewer
- Fixed release notes links to issues

## 1.0.32 - 2022-08-31

### Changed

- [201](https://github.com/CCSDForge/episciences/issues/201): depending on journal settings, editor can accept, ask revision, reject and publish papers in every round.
- [238](https://github.com/CCSDForge/episciences/issues/238): harmonization of terms 'sections/rubriques'.
- [196](https://github.com/CCSDForge/episciences/issues/196) now,
    - Only one 'name' field is available when creating a new reviewer
    - Reviewers are sorted by lastname by default
- Empty TAG %%RECIPIENT_SCREEN_NAME%% for users who do not have a local account in "Unanswered reviewer invitation (reviewer copy)" reminder.
- (Internal/refactoring) transition to PHP 8: refactoring: "MaxMind GeoIP2 PHP API" is now used instead of PHP module.

### Fixed

- [RT#163166]: Problem sending mail from staff page.
- Fixed [#165](https://github.com/CCSDForge/episciences/issues/165): Line breaks in paper titles in API
- Fixed [#251](https://github.com/CCSDForge/episciences/issues/251): conform to ISO 3297 for displaying ISSNs

## Added

- Episciences portal: New Feed RSS + Atom : latest published documents, 2 docs per journal
- Added 'journals news' RSS feed

## 1.0.31.1 - 2022-07-13

### Changed

- User accounts merging procedure: take into account the Conflict Of Interests

## 1.0.31 - 2022-06-28

### Added

- A pictogram is added to indicate papers with conflicts in the article management table.

### Changed

- [192](https://github.com/CCSDForge/episciences/issues/192): allow editors to "Ask for other editors opinion".
- Only confirmed conflicts will be displayed in the conflict management section

### Fixed

- Ask revision: possibility to backdate the deadline.
- Fixed: RT #160301:
  the tags [%%SENDER_FULL_NAME%%, %%SENDER_SCREEN_NAME%%, %%SENDER_EMAIL%%, %%SENDER_FIRST_NAME%%', %%SENDER_LAST_NAME%% ]
  concerning the user of the action are filled with the data of the user connected at the time of the action.
  Making these variables available in the automatic mails poses a real problem: they are filled with the data of the mail recipient.
  So, from now on, the tags mentioned above will no longer be available in the automatic mail templates.
- Shifted display because of the error message (CSRF token)

## 1.0.30 - 2022-06-20

### Fixed

- Titles do not appear in the correct language when more than one language has been entered (e.g. SLOVO )

### Added

- It is now possible to manage conflicts on the article page: display & delete them

## 1.0.29.1 - 2022-06-13

### Changed

- Revision requests: now we have an immediate visual feedback when changing the deadline

### Fixed

- [#247](https://github.com/CCSDForge/episciences/issues/247):
  HTML links in outgoing emails are made relative [#247]: TinyMCE configuration: convert all relative URLs to absolute URLs.
- Edition of a volume: The title of article might not be retrieved if the language was not managed by the platform.
- Profile editing form does not display correctly when changing language.

## 1.0.29 - 2022-06-01

### Fixed

- Failed 'Not enough reviewers - editor copy' reminders.
- But leading to load journal translations from '/'.
- PHP Warning: Use of undefined constant RVID - assumed 'RVID' (this will throw an Error in a future version of PHP)
- Not enough review reminders: articles without invitations escape reminders because the function that retrieves invitations is not used properly.

### Added

- User list: make it easier to locate accounts that have not been activated.
- Addes Roadmap link + User survey/feedback on portal
- Make EU grants and OpenAIRE support more visible at the bottom of the page

## 1.0.28.5 - 2022-05-25

### Fixed

- [RT#158293]: article status is not correctly updated

## 1.0.28.4 - 2022-05-09

### Fixed

- File not found for Temporary Versions without attached files + refactoring.

## 1.0.28.3 - 2022-04-13

### Changed

- Updated error message when using a HAL identifier without any file.
- [#142](https://github.com/CCSDForge/episciences/issues/142)

## 1.0.28.2 - 2022-04-06

### Fixed

- Empty records from HAL repository: error message improved.
- Improvements and fixes on Datacite and Crossref XML exports

## 1.0.28.1 - 2022-03-23

### Fixed

- fix issue in crossref xml format with related_item

## 1.0.28 - 2022-03-23

### Changed

- Bump crossref schema to ver 4.8.1
- arXiv URLs now links to https version instead of http

### Added

- Crossref metadata export format for DOI:
    - add previous version URLs, link with relationship 'hasPreprint' to link preprints versions with the published version
    - add arXiv DOIs, link to journal's published DOI with 'isSameAs' relationship
    - add full text PDF URLs for Crossref 'Similarity Check' service

## 1.0.27.5 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format
- Adding Polyfill PHP 8.0

## 1.0.27.4 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format

## 1.0.27.3 - 2022-03-18

### Fixed

- Ratings submitted late after the start of the layout process caused the status of the article to be updated (rolled back).
- There is no need to review an article while it is being formatted by the author.

## 1.0.27.2 - 2022-03-18

### Fixed

- "Copy editor" role not authorized accessing to the public page of the paper
  and report a conflict of interest, if the option has been activated by the journal

## 1.0.27.1 - 2022-03-16

### Fixed

- Exclude the "paper-status" directory from the journal's resources.
- Content that is copied and pasted when reviewing an article may lose end of line.
- The linked data to an article is not deleted when the author deletes his own article

## 1.0.27 - 2022-03-14

# Changed

- Reviewer invitation: Users are now sorted by Name, First Name instead of First Name

# Fixed

- Dashboard: the number of papers assigned to the copy editor includes obsolete papers
- Issue reported to support RT #148133 (fixed before this release):
    - Some editors could see the reviewers names if they were also authors of the article

## 1.0.26 - 2022-03-01

# Fixed

- Issue reported to support RT #148133:
    - Incorrect "Reminder Delay: The "Reminder Delay" should correspond, in number of days, to the difference between the date the reminder was sent and the date the invitation was sent.
- Issue reported to support RT #148466:
    - Sorting by date in the Dashboard is wrong: Enabling the conflict of interest (COI) option distorts the pagination

## 1.0.25.1 - 2022-02-15

# Changed

[#152](https://github.com/CCSDForge/episciences/issues/152): page footer modifications.

## 1.0.25 - 2022-02-15

# Fixed

- All submissions are accessible to "guest editor" roles.
- Language bar missing when editing a mail template, including reminders.

## 1.0.24.2 - 2022-02-10

# Fixed

- [RT#146987] "search" button no longer works
- application error: syntax error or access violation if CAS user not found

## 1.0.24.1 - 2022-02-08

# Fixed

- A bug in the reminders code triggered emails with unusable HTTP links

## 1.0.24 - 2022-02-08

# Fixed

- Inability to delete a volume (RT#145178): reset the volume of the previous version when submitting a new version.
- [#117](https://github.com/CCSDForge/episciences/issues/117) Improvements in emailing
- Undetected inactive accounts: Invite a reviewer > new reviewer > Invite a new user.
- Not allowing the author to be invited: Invite a reviewer > new reviewer > Invite a new user.
- The attachments to the rating report are not available [RT#145473]
- Answer revision request: in some situations, the "Submit" button remains inactive.

## 1.0.23 - 2022-01-19

### Added

- [#116](https://github.com/CCSDForge/episciences/issues/116) Release version is displayed on the interface (dashboard and page footer)
- Editors' choice at time of submission: informative text added. (gitlab #369)
- [#143](https://github.com/CCSDForge/episciences/issues/143) New DOI setting: Allow switching off manual or automatic DOI assignation (disabled mode)
- [#37](https://github.com/CCSDForge/episciences/issues/37)
    - Browse by Volume or Sections : Handle new content type 'application/json' to return json instead of html
    - On URLs like /volume/view/id/314159 and /section/view/id/314159 Handle new content type 'application/json' to return json instead of html (only published content)
    - On URLs like /volume/edit?id=314159 Handle new content type 'application/json' to return json instead of html but including all statuses of articles ; an authenticated and allowed user is required
    - JSON added to the list of public export formats
- Enhanced information message for statistics

### Changed

- UI/UX: Make the version number more explicit when proposing a new version
- Code Refactoring fixing of warning
- Dumping default data for table `MAIL_TEMPLATE`

### Fixed

- [#126](https://github.com/CCSDForge/episciences/issues/126) Clicking outside the window to compose an email closes the window with no confirmation #126
- [#117](https://github.com/CCSDForge/episciences/issues/117) Mailing bug: loss of the mail when you forget to put a recipient (gitlab #343)
- [#149](https://github.com/CCSDForge/episciences/issues/149) Emails and default language selection: language of the sender and the recipient could be a problem if the language of the sender is different from the language of the recipient (gitlab #402)
- Inconsistent REMINDER_DELAY: the displayed value is the "delay" parameter of the reminder instead of the calculated value (interval in number of days between the time the reminder was sent and the deadline).
- New submission: if the "submission date" metadata is empty: do not display it anymore.
- [#124](https://github.com/CCSDForge/episciences/issues/124): refining automatic notifications when abandoning submission
- RT#144252: better cleaning of attached file names
- RT#144246: an inactive account could still be visible on the Editorial Staff members page.
- [#117](https://github.com/CCSDForge/episciences/issues/117): An empty e-mail is not sent or received
- [#129](https://github.com/CCSDForge/episciences/issues/129): when you do a search from the mail history page nothing happens.
- [#141](https://github.com/CCSDForge/episciences/issues/141): visibility of pages menu is wrong inside menu editing.

### Changed

- [#126](https://github.com/CCSDForge/episciences/issues/126): prevent modal closure: disabling the click outside the modal area and by pressing Esc
- Enabling the "COI" option overrides the "Encapsulate editors" option

## 1.0.38.5 - 2022-12-14

### Fixed

- [#294](https://github.com/CCSDForge/episciences/issues/294): untranslated text
- API to return number of published documents

## 1.0.38.4 - 2022-12-12

### Fixed

- JS error: Failed to load plugin url: https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/langs/fr_FR.js
  @see https://www.tiny.cloud/docs-4x/configure/localization/#language
- Add %%PAPER_REP_URL%% tag to the description of the invitation templates.
- Change password: redirect to authentication if user is not logged in
- The '%%SENDER_SCREEN_NAME%%' tag is always present in the 'paper_paper_editor_refused_monitoring' template

## 1.0.38.3 - 2022-11-30

### Fixed

- Html filtering problem that breaks the display in the comments

## 1.0.38.2 - 2022-11-24

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): There is a difference between the capitalization of the first and last names in the user's profile
  and the first and last names of the user in the email recipient field [RT#148137]
- Fixed the fact that the Administrator role prevents testing for conflict of interest
- Error: $(...).fileupload is not a function for published articles.

### Changed

- Conflict management section: set default display lines to 5.
- Mailing > getContacts: add 'Authors' filter.
- resolved [#282](https://github.com/CCSDForge/episciences/issues/282): C:\fakepath: This is the security
  implementation of the browser: the browser is protecting you from accessing your disk structure.
- Code review / refactoring

## 1.0.38.1 - 2022-11-16

### Fixed

- Added missing translation for conflict of interest

### Changed

- Footer: replaced TOS with Term Of Use

## 1.0.38 - 2022-11-16

### Added

- New footer link to the Term of use (between the platform and users)

### Fixed

- Fixed ORCID 'X' in DOAJ export format, make it compliant with ORCID specification
- [RT#171463]: the reviews should not be seen by someone with a CoI declaration with
  the paper: this fix concerns all the paper's details.

### Changed

- UI/UX: Improved rendering of the "conflict management" section
- [RT#170200]: confirmation of the absence of conflict of interest: it is now possible to cancel this type of response.

## 1.0.37 - 2022-11-10

### Added

- New feature: [Open Science Lens](https://www.opensciencelens.eu/) feature available as a preview on selected journals

### Changed

- Improved rendering of volumes

## 1.0.36 - 2022-11-09

### Added

- Improved rendering of the volume page
- authors can now enter their paper password provided by the open archive on the article page.
- alt attribute to volume's image.
- Added an API method to get the number of users accounts

### Changed

- Optimization: there is no need to check for conflicts at the time of submission
- [#280](https://github.com/CCSDForge/episciences/issues/280): set spellcheck to false in dynamic datatable search boxes.
- [#281](https://github.com/CCSDForge/episciences/issues/281): Submit an article > Guidelines section: harmonization of journal/review terms [FR]
- Now, when an article is accepted, all unanswered invitations are deleted. The reviewer is informed of this action.
- Add 'ISSN pending' until the ISSN is actually issued

### Fixed

- Fix incorrect HTML in footer
- Incorrect paper status LMCS #10145 (related to https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#10273---2022-03-18)
- Empty %%REQUESTER_EXPRESSION%% tag (related to [#187](https://github.com/CCSDForge/episciences/issues/187)
- Incorrect status jdmdh#10203 (related with git#372)
- Fixed [#RT169943]: the tag %%SENDER_SCREEN_NAME%% (obsolete) that should have contained the editor's screen name has not been replaced:
  to be replaced in custom templates by %%EDITOR_FULL_NAME%% or %%EDITOR_SCREEN_NAME%%
  (@see https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#fixed-11 [RT##160301])

## 1.0.35 - 2022-10-19

### Added

- [RT#169107]: Feature - new option: do not allow the selection of an editor-in-chief when the author has the option to
  propose an editor at the time of submission.

### Changed

- [#255](https://github.com/CCSDForge/episciences/issues/255) Display ratings reports on the article page: Label updated

### Fixed

- [RT#169088]: Editors-in-chief and editorial secretaries are no longer notified of new submissions.

## 1.0.34.2 - 2022-10-18

### Fixed

- temporary fix: TinyMCE: loss of formatting for successive mailings
- Insert data in 'T_PAPER_DATASETS' table: MySql insert 0 instead of NULL
- [#273](https://github.com/CCSDForge/episciences/issues/273): if the editor changes the rating due date, then this
  is not reflected in the message.
- Fixes in exports format related to trailing ',' and language codes

## 1.0.34.1 - 2022-10-16

### Fixed

- Fixed bad JS escape of authors in reviewer invitation form
- Fixed Handle URLs links adding https://hdl.handle.net/
- Fixed display of disabled buttons
- Fixed 'Description of affiliation block'

### Added

- The activation of maintenance alerts is now configurable

## 1.0.34 - 2022-10-13

### Added

- Article managers (authors, editors, etc.) may add new optional - but recommended - metadata to the documents
    - [ORCID](https://orcid.org/)
    - Affiliations: for affiliations the [ROR](https://ror.org/) or a free text may be used. If an institution is not available in the ROR, a
      simple text entry may be used.
- Automatic enrichment of metadata:
    - Licenses: retrieved from [Datacite](https://datacite.org/), [OpenAIRE Research Graph](https://graph.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/)
    - ORCID: retrieved from [OpenAIRE Research Graph](https://graph.openaire.eu/)
    - Linked Datasets: [Scholexplorer](https://scholexplorer.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/): Datasets linked to Episciences
      publications
    - Funding: Research projects from [OpenAIRE Research Graph](https://graph.openaire.eu/) and [HAL](https://hal.archives-ouvertes.fr/)
    - Citations: Citations of published document retrieved with [OpenCitations](https://opencitations.net/) APIs
- Exports formats:
    - [DOAJ](https://doaj.org/about/) (export one document or a whole published volume) using DOAJ Schema
    - The new collected metadata have been added to Datacite, [Crossref](https://www.crossref.org/) and DOAJ export formats
- Browse by volume: new parameter to allow to display empty volumes. Default value is still no (only show volumes
  with papers published in the volume)
- [#186](https://github.com/CCSDForge/episciences/issues/186): Editors-in-chief can now report a conflict of
  interest. (Sign in an admin as another user: the real identity is now saved.)

### Fixed

- Fixed a display bug on the portal in connected mode (used by Episciences staff only)
- Failed to edit another account's profile from the user management page.
- impossibility to validate the profile if the user does not have an account in the application

### Changed

- new parameter for enabling/disabling submission from the archive to the application and vice versa.

## 1.0.33.2 - 2022-10-05

### Fixed

- RT#167820: all occurrences of tags in a template will be replaced by their real values.

## 1.0.33.1 - 2022-09-29

### Fixed

- Allow editors to view the list of conflicts on the article management page
- The number of conflicts indicated on the article management page is incorrect if the article has several versions

## 1.0.33 - 2022-09-28

### Added

- New page listing all email templates and available tags for each journal at /administratemail/tagslist
  (- Mail -> TagList)
- Episciences portal:
    - New Feed RSS + Atom : latest published documents, 2 docs per journal
    - API to get a list of publishing journals: include accepted repositories.
- Episciences journals
    - Added 'journals news' RSS feed for each journal
- New journal settings parameter: Allow post-acceptance revisions of articles
- 9 article statuses, available for journals allowing 'post-acceptance revisions of articles':
    - Accepted temporary version, waiting for author's final version: now, temporary versions that are accepted have this new status instead of "pending minor revision" (git #372)
    - Accepted - waiting for author's final version
    - Accepted, waiting for major revision
    - Accepted article - final version submitted, waiting for formatting by copy editors
    - Accepted temporary version after author's modifications
    - Accepted temporary version, waiting for minor revision
    - Accepted temporary version, waiting for major revision
    - Accepted - waiting validation by the author
    - Approved by author, waiting for final publication
- User profiles:
    - Adding ORCID and affiliation to your profile is now possible + this information is diplayed on the
      "editorial staff member" (gitlab #410) & dashboard pages.
    - Social Medias & Websites profile informations.
- New 'Author' role automatically added to users that have submitted a document
- Paper list: temporary versions now have a specific label
- Dashboard: Alert for administrators on the existence of papers without assigned editors
- Display the origin of a linked data when papers are linked to a dataset
- Administration page of a paper: new shortcut for administrators :
    - to allow to sign in as a copy-editor
    - to allow to sign in as an editor
- [227](https://github.com/CCSDForge/episciences/issues/227):
    - The revision deadline is now displayed below the article's status.
    - A pictogram is added to indicate the date of the revision deadline in the article management table.

### Changed

- [#237](https://github.com/CCSDForge/episciences/issues/237) Editor comments div is now bigger
- Upgraded publication RSS Feeds with DOIs
- [#142](https://github.com/CCSDForge/episciences/issues/142): allow that administrators answer revision and copy editing requests
- It is now possible to update the metadata on the document's page
- Request a new version of an article that has already been accepted
- [#166](https://github.com/CCSDForge/episciences/issues/116): hide "guest" role and doi-settings action
- The "paper status" filter is now dynamically created.
- Prevent "Add sources files" and "Add the formatted version" buttons JS reactivation
- Moved "copy editing" section to a more usable place on the same page
- Dashboard improvements: ability to reach an article from sections: my submissions and assigned articles
- [#187](https://github.com/CCSDForge/episciences/issues/187): change the default templates: 'new version submitted' and 'tmp version submitted'
- [#188](https://github.com/CCSDForge/episciences/issues/188): force notifications
- It is now possible to update the document version in Episciences to the most recent version in the open archive.
- [#164](https://github.com/CCSDForge/episciences/issues/164): harmonization of roles/privileges.
- CWI open repository has been temporarily removed until we adapt to their new OAI server

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): capitalization of names
- [#207](https://github.com/CCSDForge/episciences/issues/207): editing the translation of Chief Editors
- [#169](https://github.com/CCSDForge/episciences/issues/169): reports become visible on the article web page:
    - according to the parameters of the journal for published articles
    - for the owner, only if the paper is refused, waiting for revision, already accepted or published
- Another status (Revision request answer: without any modifications) is used when responding to a revision request: without any modifications.
- Revision requests: now we have an immediate visual feedback when changing the deadline.
- If the journal allows revision of articles after acceptance, the response to a request for a final version (without any changes) is similar to the proposal of a new version.

### Internal refactoring

- Code Refactoring fixing of warning
- Update Episciences_User::getScreenName()
- User Table: merge AFFILIATIONS" filed in "ADDITIONAL_PROFILE_INFORMATION"
- Multiple roles: ignore the "member" role when merging two accounts.
- Email Templates updates
- Script to clean the "USER_ROLES" table
- Parameter to detect automatic emails

## 1.0.32.2 - 2022-09-28

### Changed

- [196](https://github.com/CCSDForge/episciences/issues/196): Creation of an account following an invitation with a temporary account: from now on, the Last Name, First Name and Screen Name fields will be left empty (for the reviewer to fill in)
- [RT#164153]: refactoring: now obsolete template "paper_new_version_reviewer_reassign" (Reviewer assignment to a new version of an article) is removed.

### Fixed:

- account merging incomplete due to sql error when updating 'paper_conflicts' table.

## 1.0.32.1 - 2022-09-01

### Fixed

- Fixed bug introduced with [196] "Name" => "Last Name" when creating a new reviewer
- Fixed release notes links to issues

## 1.0.32 - 2022-08-31

### Changed

- [201](https://github.com/CCSDForge/episciences/issues/201): depending on journal settings, editor can accept, ask revision, reject and publish papers in every round.
- [238](https://github.com/CCSDForge/episciences/issues/238): harmonization of terms 'sections/rubriques'.
- [196](https://github.com/CCSDForge/episciences/issues/196) now,
    - Only one 'name' field is available when creating a new reviewer
    - Reviewers are sorted by lastname by default
- Empty TAG %%RECIPIENT_SCREEN_NAME%% for users who do not have a local account in "Unanswered reviewer invitation (reviewer copy)" reminder.
- (Internal/refactoring) transition to PHP 8: refactoring: "MaxMind GeoIP2 PHP API" is now used instead of PHP module.

### Fixed

- [RT#163166]: Problem sending mail from staff page.
- Fixed [#165](https://github.com/CCSDForge/episciences/issues/165): Line breaks in paper titles in API
- Fixed [#251](https://github.com/CCSDForge/episciences/issues/251): conform to ISO 3297 for displaying ISSNs

## Added

- Episciences portal: New Feed RSS + Atom : latest published documents, 2 docs per journal
- Added 'journals news' RSS feed

## 1.0.31.1 - 2022-07-13

### Changed

- User accounts merging procedure: take into account the Conflict Of Interests

## 1.0.31 - 2022-06-28

### Added

- A pictogram is added to indicate papers with conflicts in the article management table.

### Changed

- [192](https://github.com/CCSDForge/episciences/issues/192): allow editors to "Ask for other editors opinion".
- Only confirmed conflicts will be displayed in the conflict management section

### Fixed

- Ask revision: possibility to backdate the deadline.
- Fixed: RT #160301:
  the tags [%%SENDER_FULL_NAME%%, %%SENDER_SCREEN_NAME%%, %%SENDER_EMAIL%%, %%SENDER_FIRST_NAME%%', %%SENDER_LAST_NAME%% ]
  concerning the user of the action are filled with the data of the user connected at the time of the action.
  Making these variables available in the automatic mails poses a real problem: they are filled with the data of the mail recipient.
  So, from now on, the tags mentioned above will no longer be available in the automatic mail templates.
- Shifted display because of the error message (CSRF token)

## 1.0.30 - 2022-06-20

### Fixed

- Titles do not appear in the correct language when more than one language has been entered (e.g. SLOVO )

### Added

- It is now possible to manage conflicts on the article page: display & delete them

## 1.0.29.1 - 2022-06-13

### Changed

- Revision requests: now we have an immediate visual feedback when changing the deadline

### Fixed

- [#247](https://github.com/CCSDForge/episciences/issues/247):
  HTML links in outgoing emails are made relative [#247]: TinyMCE configuration: convert all relative URLs to absolute URLs.
- Edition of a volume: The title of article might not be retrieved if the language was not managed by the platform.
- Profile editing form does not display correctly when changing language.

## 1.0.29 - 2022-06-01

### Fixed

- Failed 'Not enough reviewers - editor copy' reminders.
- But leading to load journal translations from '/'.
- PHP Warning: Use of undefined constant RVID - assumed 'RVID' (this will throw an Error in a future version of PHP)
- Not enough review reminders: articles without invitations escape reminders because the function that retrieves invitations is not used properly.

### Added

- User list: make it easier to locate accounts that have not been activated.
- Addes Roadmap link + User survey/feedback on portal
- Make EU grants and OpenAIRE support more visible at the bottom of the page

## 1.0.28.5 - 2022-05-25

### Fixed

- [RT#158293]: article status is not correctly updated

## 1.0.28.4 - 2022-05-09

### Fixed

- File not found for Temporary Versions without attached files + refactoring.

## 1.0.28.3 - 2022-04-13

### Changed

- Updated error message when using a HAL identifier without any file.
- [#142](https://github.com/CCSDForge/episciences/issues/142)

## 1.0.28.2 - 2022-04-06

### Fixed

- Empty records from HAL repository: error message improved.
- Improvements and fixes on Datacite and Crossref XML exports

## 1.0.28.1 - 2022-03-23

### Fixed

- fix issue in crossref xml format with related_item

## 1.0.28 - 2022-03-23

### Changed

- Bump crossref schema to ver 4.8.1
- arXiv URLs now links to https version instead of http

### Added

- Crossref metadata export format for DOI:
    - add previous version URLs, link with relationship 'hasPreprint' to link preprints versions with the published version
    - add arXiv DOIs, link to journal's published DOI with 'isSameAs' relationship
    - add full text PDF URLs for Crossref 'Similarity Check' service

## 1.0.27.5 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format
- Adding Polyfill PHP 8.0

## 1.0.27.4 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format

## 1.0.27.3 - 2022-03-18

### Fixed

- Ratings submitted late after the start of the layout process caused the status of the article to be updated (rolled back).
- There is no need to review an article while it is being formatted by the author.

## 1.0.27.2 - 2022-03-18

### Fixed

- "Copy editor" role not authorized accessing to the public page of the paper
  and report a conflict of interest, if the option has been activated by the journal

## 1.0.27.1 - 2022-03-16

### Fixed

- Exclude the "paper-status" directory from the journal's resources.
- Content that is copied and pasted when reviewing an article may lose end of line.
- The linked data to an article is not deleted when the author deletes his own article

## 1.0.27 - 2022-03-14

# Changed

- Reviewer invitation: Users are now sorted by Name, First Name instead of First Name

# Fixed

- Dashboard: the number of papers assigned to the copy editor includes obsolete papers
- Issue reported to support RT #148133 (fixed before this release):
    - Some editors could see the reviewers names if they were also authors of the article

## 1.0.26 - 2022-03-01

# Fixed

- Issue reported to support RT #148133:
    - Incorrect "Reminder Delay: The "Reminder Delay" should correspond, in number of days, to the difference between the date the reminder was sent and the date the invitation was sent.
- Issue reported to support RT #148466:
    - Sorting by date in the Dashboard is wrong: Enabling the conflict of interest (COI) option distorts the pagination

## 1.0.25.1 - 2022-02-15

# Changed

[#152](https://github.com/CCSDForge/episciences/issues/152): page footer modifications.

## 1.0.25 - 2022-02-15

# Fixed

- All submissions are accessible to "guest editor" roles.
- Language bar missing when editing a mail template, including reminders.

## 1.0.24.2 - 2022-02-10

# Fixed

- [RT#146987] "search" button no longer works
- application error: syntax error or access violation if CAS user not found

## 1.0.24.1 - 2022-02-08

# Fixed

- A bug in the reminders code triggered emails with unusable HTTP links

## 1.0.24 - 2022-02-08

# Fixed

- Inability to delete a volume (RT#145178): reset the volume of the previous version when submitting a new version.
- [#117](https://github.com/CCSDForge/episciences/issues/117) Improvements in emailing
- Undetected inactive accounts: Invite a reviewer > new reviewer > Invite a new user.
- Not allowing the author to be invited: Invite a reviewer > new reviewer > Invite a new user.
- The attachments to the rating report are not available [RT#145473]
- Answer revision request: in some situations, the "Submit" button remains inactive.

## 1.0.23 - 2022-01-19

### Added

- [#116](https://github.com/CCSDForge/episciences/issues/116) Release version is displayed on the interface (dashboard and page footer)
- Editors' choice at time of submission: informative text added. (gitlab #369)
- [#143](https://github.com/CCSDForge/episciences/issues/143) New DOI setting: Allow switching off manual or automatic DOI assignation (disabled mode)
- [#37](https://github.com/CCSDForge/episciences/issues/37)
    - Browse by Volume or Sections : Handle new content type 'application/json' to return json instead of html
    - On URLs like /volume/view/id/314159 and /section/view/id/314159 Handle new content type 'application/json' to return json instead of html (only published content)
    - On URLs like /volume/edit?id=314159 Handle new content type 'application/json' to return json instead of html but including all statuses of articles ; an authenticated and allowed user is required
    - JSON added to the list of public export formats
- Enhanced information message for statistics

### Changed

- UI/UX: Make the version number more explicit when proposing a new version
- Code Refactoring fixing of warning
- Dumping default data for table `MAIL_TEMPLATE`

### Fixed

- [#126](https://github.com/CCSDForge/episciences/issues/126) Clicking outside the window to compose an email closes the window with no confirmation #126
- [#117](https://github.com/CCSDForge/episciences/issues/117) Mailing bug: loss of the mail when you forget to put a recipient (gitlab #343)
- [#149](https://github.com/CCSDForge/episciences/issues/149) Emails and default language selection: language of the sender and the recipient could be a problem if the language of the sender is different from the language of the recipient (gitlab #402)
- Inconsistent REMINDER_DELAY: the displayed value is the "delay" parameter of the reminder instead of the calculated value (interval in number of days between the time the reminder was sent and the deadline).
- New submission: if the "submission date" metadata is empty: do not display it anymore.
- [#124](https://github.com/CCSDForge/episciences/issues/124): refining automatic notifications when abandoning submission
- RT#144252: better cleaning of attached file names
- RT#144246: an inactive account could still be visible on the Editorial Staff members page.
- [#117](https://github.com/CCSDForge/episciences/issues/117): An empty e-mail is not sent or received
- [#129](https://github.com/CCSDForge/episciences/issues/129): when you do a search from the mail history page nothing happens.
- [#141](https://github.com/CCSDForge/episciences/issues/141): visibility of pages menu is wrong inside menu editing.

### Changed

- [#126](https://github.com/CCSDForge/episciences/issues/126): prevent modal closure: disabling the click outside the modal area and by pressing Esc
- Enabling the "COI" option overrides the "Encapsulate editors" option

## 1.0.38.5 - 2022-12-14

### Fixed

- [#294](https://github.com/CCSDForge/episciences/issues/294): untranslated text
- API to return number of published documents

## 1.0.38.4 - 2022-12-12

### Fixed

- JS error: Failed to load plugin url: https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/langs/fr_FR.js
  @see https://www.tiny.cloud/docs-4x/configure/localization/#language
- Add %%PAPER_REP_URL%% tag to the description of the invitation templates.
- Change password: redirect to authentication if user is not logged in
- The '%%SENDER_SCREEN_NAME%%' tag is always present in the 'paper_paper_editor_refused_monitoring' template

## 1.0.38.3 - 2022-11-30

### Fixed

- Html filtering problem that breaks the display in the comments

## 1.0.38.2 - 2022-11-24

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): There is a difference between the capitalization of the first and last names in the user's profile
  and the first and last names of the user in the email recipient field [RT#148137]
- Fixed the fact that the Administrator role prevents testing for conflict of interest
- Error: $(...).fileupload is not a function for published articles.

### Changed

- Conflict management section: set default display lines to 5.
- Mailing > getContacts: add 'Authors' filter.
- resolved [#282](https://github.com/CCSDForge/episciences/issues/282): C:\fakepath: This is the security
  implementation of the browser: the browser is protecting you from accessing your disk structure.
- Code review / refactoring

## 1.0.38.1 - 2022-11-16

### Fixed

- Added missing translation for conflict of interest

### Changed

- Footer: replaced TOS with Term Of Use

## 1.0.38 - 2022-11-16

### Added

- New footer link to the Term of use (between the platform and users)

### Fixed

- Fixed ORCID 'X' in DOAJ export format, make it compliant with ORCID specification
- [RT#171463]: the reviews should not be seen by someone with a CoI declaration with
  the paper: this fix concerns all the paper's details.

### Changed

- UI/UX: Improved rendering of the "conflict management" section
- [RT#170200]: confirmation of the absence of conflict of interest: it is now possible to cancel this type of response.

## 1.0.37 - 2022-11-10

### Added

- New feature: [Open Science Lens](https://www.opensciencelens.eu/) feature available as a preview on selected journals

### Changed

- Improved rendering of volumes

## 1.0.36 - 2022-11-09

### Added

- Improved rendering of the volume page
- authors can now enter their paper password provided by the open archive on the article page.
- alt attribute to volume's image.
- Added an API method to get the number of users accounts

### Changed

- Optimization: there is no need to check for conflicts at the time of submission
- [#280](https://github.com/CCSDForge/episciences/issues/280): set spellcheck to false in dynamic datatable search boxes.
- [#281](https://github.com/CCSDForge/episciences/issues/281): Submit an article > Guidelines section: harmonization of journal/review terms [FR]
- Now, when an article is accepted, all unanswered invitations are deleted. The reviewer is informed of this action.
- Add 'ISSN pending' until the ISSN is actually issued

### Fixed

- Fix incorrect HTML in footer
- Incorrect paper status LMCS #10145 (related to https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#10273---2022-03-18)
- Empty %%REQUESTER_EXPRESSION%% tag (related to [#187](https://github.com/CCSDForge/episciences/issues/187)
- Incorrect status jdmdh#10203 (related with git#372)
- Fixed [#RT169943]: the tag %%SENDER_SCREEN_NAME%% (obsolete) that should have contained the editor's screen name has not been replaced:
  to be replaced in custom templates by %%EDITOR_FULL_NAME%% or %%EDITOR_SCREEN_NAME%%
  (@see https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#fixed-11 [RT##160301])

## 1.0.35 - 2022-10-19

### Added

- [RT#169107]: Feature - new option: do not allow the selection of an editor-in-chief when the author has the option to
  propose an editor at the time of submission.

### Changed

- [#255](https://github.com/CCSDForge/episciences/issues/255) Display ratings reports on the article page: Label updated

### Fixed

- [RT#169088]: Editors-in-chief and editorial secretaries are no longer notified of new submissions.

## 1.0.34.2 - 2022-10-18

### Fixed

- temporary fix: TinyMCE: loss of formatting for successive mailings
- Insert data in 'T_PAPER_DATASETS' table: MySql insert 0 instead of NULL
- [#273](https://github.com/CCSDForge/episciences/issues/273): if the editor changes the rating due date, then this
  is not reflected in the message.
- Fixes in exports format related to trailing ',' and language codes

## 1.0.34.1 - 2022-10-16

### Fixed

- Fixed bad JS escape of authors in reviewer invitation form
- Fixed Handle URLs links adding https://hdl.handle.net/
- Fixed display of disabled buttons
- Fixed 'Description of affiliation block'

### Added

- The activation of maintenance alerts is now configurable

## 1.0.34 - 2022-10-13

### Added

- Article managers (authors, editors, etc.) may add new optional - but recommended - metadata to the documents
    - [ORCID](https://orcid.org/)
    - Affiliations: for affiliations the [ROR](https://ror.org/) or a free text may be used. If an institution is not available in the ROR, a
      simple text entry may be used.
- Automatic enrichment of metadata:
    - Licenses: retrieved from [Datacite](https://datacite.org/), [OpenAIRE Research Graph](https://graph.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/)
    - ORCID: retrieved from [OpenAIRE Research Graph](https://graph.openaire.eu/)
    - Linked Datasets: [Scholexplorer](https://scholexplorer.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/): Datasets linked to Episciences
      publications
    - Funding: Research projects from [OpenAIRE Research Graph](https://graph.openaire.eu/) and [HAL](https://hal.archives-ouvertes.fr/)
    - Citations: Citations of published document retrieved with [OpenCitations](https://opencitations.net/) APIs
- Exports formats:
    - [DOAJ](https://doaj.org/about/) (export one document or a whole published volume) using DOAJ Schema
    - The new collected metadata have been added to Datacite, [Crossref](https://www.crossref.org/) and DOAJ export formats
- Browse by volume: new parameter to allow to display empty volumes. Default value is still no (only show volumes
  with papers published in the volume)
- [#186](https://github.com/CCSDForge/episciences/issues/186): Editors-in-chief can now report a conflict of
  interest. (Sign in an admin as another user: the real identity is now saved.)

### Fixed

- Fixed a display bug on the portal in connected mode (used by Episciences staff only)
- Failed to edit another account's profile from the user management page.
- impossibility to validate the profile if the user does not have an account in the application

### Changed

- new parameter for enabling/disabling submission from the archive to the application and vice versa.

## 1.0.33.2 - 2022-10-05

### Fixed

- RT#167820: all occurrences of tags in a template will be replaced by their real values.

## 1.0.33.1 - 2022-09-29

### Fixed

- Allow editors to view the list of conflicts on the article management page
- The number of conflicts indicated on the article management page is incorrect if the article has several versions

## 1.0.33 - 2022-09-28

### Added

- New page listing all email templates and available tags for each journal at /administratemail/tagslist
  (- Mail -> TagList)
- Episciences portal:
    - New Feed RSS + Atom : latest published documents, 2 docs per journal
    - API to get a list of publishing journals: include accepted repositories.
- Episciences journals
    - Added 'journals news' RSS feed for each journal
- New journal settings parameter: Allow post-acceptance revisions of articles
- 9 article statuses, available for journals allowing 'post-acceptance revisions of articles':
    - Accepted temporary version, waiting for author's final version: now, temporary versions that are accepted have this new status instead of "pending minor revision" (git #372)
    - Accepted - waiting for author's final version
    - Accepted, waiting for major revision
    - Accepted article - final version submitted, waiting for formatting by copy editors
    - Accepted temporary version after author's modifications
    - Accepted temporary version, waiting for minor revision
    - Accepted temporary version, waiting for major revision
    - Accepted - waiting validation by the author
    - Approved by author, waiting for final publication
- User profiles:
    - Adding ORCID and affiliation to your profile is now possible + this information is diplayed on the
      "editorial staff member" (gitlab #410) & dashboard pages.
    - Social Medias & Websites profile informations.
- New 'Author' role automatically added to users that have submitted a document
- Paper list: temporary versions now have a specific label
- Dashboard: Alert for administrators on the existence of papers without assigned editors
- Display the origin of a linked data when papers are linked to a dataset
- Administration page of a paper: new shortcut for administrators :
    - to allow to sign in as a copy-editor
    - to allow to sign in as an editor
- [227](https://github.com/CCSDForge/episciences/issues/227):
    - The revision deadline is now displayed below the article's status.
    - A pictogram is added to indicate the date of the revision deadline in the article management table.

### Changed

- [#237](https://github.com/CCSDForge/episciences/issues/237) Editor comments div is now bigger
- Upgraded publication RSS Feeds with DOIs
- [#142](https://github.com/CCSDForge/episciences/issues/142): allow that administrators answer revision and copy editing requests
- It is now possible to update the metadata on the document's page
- Request a new version of an article that has already been accepted
- [#166](https://github.com/CCSDForge/episciences/issues/116): hide "guest" role and doi-settings action
- The "paper status" filter is now dynamically created.
- Prevent "Add sources files" and "Add the formatted version" buttons JS reactivation
- Moved "copy editing" section to a more usable place on the same page
- Dashboard improvements: ability to reach an article from sections: my submissions and assigned articles
- [#187](https://github.com/CCSDForge/episciences/issues/187): change the default templates: 'new version submitted' and 'tmp version submitted'
- [#188](https://github.com/CCSDForge/episciences/issues/188): force notifications
- It is now possible to update the document version in Episciences to the most recent version in the open archive.
- [#164](https://github.com/CCSDForge/episciences/issues/164): harmonization of roles/privileges.
- CWI open repository has been temporarily removed until we adapt to their new OAI server

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): capitalization of names
- [#207](https://github.com/CCSDForge/episciences/issues/207): editing the translation of Chief Editors
- [#169](https://github.com/CCSDForge/episciences/issues/169): reports become visible on the article web page:
    - according to the parameters of the journal for published articles
    - for the owner, only if the paper is refused, waiting for revision, already accepted or published
- Another status (Revision request answer: without any modifications) is used when responding to a revision request: without any modifications.
- Revision requests: now we have an immediate visual feedback when changing the deadline.
- If the journal allows revision of articles after acceptance, the response to a request for a final version (without any changes) is similar to the proposal of a new version.

### Internal refactoring

- Code Refactoring fixing of warning
- Update Episciences_User::getScreenName()
- User Table: merge AFFILIATIONS" filed in "ADDITIONAL_PROFILE_INFORMATION"
- Multiple roles: ignore the "member" role when merging two accounts.
- Email Templates updates
- Script to clean the "USER_ROLES" table
- Parameter to detect automatic emails

## 1.0.32.2 - 2022-09-28

### Changed

- [196](https://github.com/CCSDForge/episciences/issues/196): Creation of an account following an invitation with a temporary account: from now on, the Last Name, First Name and Screen Name fields will be left empty (for the reviewer to fill in)
- [RT#164153]: refactoring: now obsolete template "paper_new_version_reviewer_reassign" (Reviewer assignment to a new version of an article) is removed.

### Fixed:

- account merging incomplete due to sql error when updating 'paper_conflicts' table.

## 1.0.32.1 - 2022-09-01

### Fixed

- Fixed bug introduced with [196] "Name" => "Last Name" when creating a new reviewer
- Fixed release notes links to issues

## 1.0.32 - 2022-08-31

### Changed

- [201](https://github.com/CCSDForge/episciences/issues/201): depending on journal settings, editor can accept, ask revision, reject and publish papers in every round.
- [238](https://github.com/CCSDForge/episciences/issues/238): harmonization of terms 'sections/rubriques'.
- [196](https://github.com/CCSDForge/episciences/issues/196) now,
    - Only one 'name' field is available when creating a new reviewer
    - Reviewers are sorted by lastname by default
- Empty TAG %%RECIPIENT_SCREEN_NAME%% for users who do not have a local account in "Unanswered reviewer invitation (reviewer copy)" reminder.
- (Internal/refactoring) transition to PHP 8: refactoring: "MaxMind GeoIP2 PHP API" is now used instead of PHP module.

### Fixed

- [RT#163166]: Problem sending mail from staff page.
- Fixed [#165](https://github.com/CCSDForge/episciences/issues/165): Line breaks in paper titles in API
- Fixed [#251](https://github.com/CCSDForge/episciences/issues/251): conform to ISO 3297 for displaying ISSNs

## Added

- Episciences portal: New Feed RSS + Atom : latest published documents, 2 docs per journal
- Added 'journals news' RSS feed

## 1.0.31.1 - 2022-07-13

### Changed

- User accounts merging procedure: take into account the Conflict Of Interests

## 1.0.31 - 2022-06-28

### Added

- A pictogram is added to indicate papers with conflicts in the article management table.

### Changed

- [192](https://github.com/CCSDForge/episciences/issues/192): allow editors to "Ask for other editors opinion".
- Only confirmed conflicts will be displayed in the conflict management section

### Fixed

- Ask revision: possibility to backdate the deadline.
- Fixed: RT #160301:
  the tags [%%SENDER_FULL_NAME%%, %%SENDER_SCREEN_NAME%%, %%SENDER_EMAIL%%, %%SENDER_FIRST_NAME%%', %%SENDER_LAST_NAME%% ]
  concerning the user of the action are filled with the data of the user connected at the time of the action.
  Making these variables available in the automatic mails poses a real problem: they are filled with the data of the mail recipient.
  So, from now on, the tags mentioned above will no longer be available in the automatic mail templates.
- Shifted display because of the error message (CSRF token)

## 1.0.30 - 2022-06-20

### Fixed

- Titles do not appear in the correct language when more than one language has been entered (e.g. SLOVO )

### Added

- It is now possible to manage conflicts on the article page: display & delete them

## 1.0.29.1 - 2022-06-13

### Changed

- Revision requests: now we have an immediate visual feedback when changing the deadline

### Fixed

- [#247](https://github.com/CCSDForge/episciences/issues/247):
  HTML links in outgoing emails are made relative [#247]: TinyMCE configuration: convert all relative URLs to absolute URLs.
- Edition of a volume: The title of article might not be retrieved if the language was not managed by the platform.
- Profile editing form does not display correctly when changing language.

## 1.0.29 - 2022-06-01

### Fixed

- Failed 'Not enough reviewers - editor copy' reminders.
- But leading to load journal translations from '/'.
- PHP Warning: Use of undefined constant RVID - assumed 'RVID' (this will throw an Error in a future version of PHP)
- Not enough review reminders: articles without invitations escape reminders because the function that retrieves invitations is not used properly.

### Added

- User list: make it easier to locate accounts that have not been activated.
- Addes Roadmap link + User survey/feedback on portal
- Make EU grants and OpenAIRE support more visible at the bottom of the page

## 1.0.28.5 - 2022-05-25

### Fixed

- [RT#158293]: article status is not correctly updated

## 1.0.28.4 - 2022-05-09

### Fixed

- File not found for Temporary Versions without attached files + refactoring.

## 1.0.28.3 - 2022-04-13

### Changed

- Updated error message when using a HAL identifier without any file.
- [#142](https://github.com/CCSDForge/episciences/issues/142)

## 1.0.28.2 - 2022-04-06

### Fixed

- Empty records from HAL repository: error message improved.
- Improvements and fixes on Datacite and Crossref XML exports

## 1.0.28.1 - 2022-03-23

### Fixed

- fix issue in crossref xml format with related_item

## 1.0.28 - 2022-03-23

### Changed

- Bump crossref schema to ver 4.8.1
- arXiv URLs now links to https version instead of http

### Added

- Crossref metadata export format for DOI:
    - add previous version URLs, link with relationship 'hasPreprint' to link preprints versions with the published version
    - add arXiv DOIs, link to journal's published DOI with 'isSameAs' relationship
    - add full text PDF URLs for Crossref 'Similarity Check' service

## 1.0.27.5 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format
- Adding Polyfill PHP 8.0

## 1.0.27.4 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format

## 1.0.27.3 - 2022-03-18

### Fixed

- Ratings submitted late after the start of the layout process caused the status of the article to be updated (rolled back).
- There is no need to review an article while it is being formatted by the author.

## 1.0.27.2 - 2022-03-18

### Fixed

- "Copy editor" role not authorized accessing to the public page of the paper
  and report a conflict of interest, if the option has been activated by the journal

## 1.0.27.1 - 2022-03-16

### Fixed

- Exclude the "paper-status" directory from the journal's resources.
- Content that is copied and pasted when reviewing an article may lose end of line.
- The linked data to an article is not deleted when the author deletes his own article

## 1.0.27 - 2022-03-14

# Changed

- Reviewer invitation: Users are now sorted by Name, First Name instead of First Name

# Fixed

- Dashboard: the number of papers assigned to the copy editor includes obsolete papers
- Issue reported to support RT #148133 (fixed before this release):
    - Some editors could see the reviewers names if they were also authors of the article

## 1.0.26 - 2022-03-01

# Fixed

- Issue reported to support RT #148133:
    - Incorrect "Reminder Delay: The "Reminder Delay" should correspond, in number of days, to the difference between the date the reminder was sent and the date the invitation was sent.
- Issue reported to support RT #148466:
    - Sorting by date in the Dashboard is wrong: Enabling the conflict of interest (COI) option distorts the pagination

## 1.0.25.1 - 2022-02-15

# Changed

[#152](https://github.com/CCSDForge/episciences/issues/152): page footer modifications.

## 1.0.25 - 2022-02-15

# Fixed

- All submissions are accessible to "guest editor" roles.
- Language bar missing when editing a mail template, including reminders.

## 1.0.24.2 - 2022-02-10

# Fixed

- [RT#146987] "search" button no longer works
- application error: syntax error or access violation if CAS user not found

## 1.0.24.1 - 2022-02-08

# Fixed

- A bug in the reminders code triggered emails with unusable HTTP links

## 1.0.24 - 2022-02-08

# Fixed

- Inability to delete a volume (RT#145178): reset the volume of the previous version when submitting a new version.
- [#117](https://github.com/CCSDForge/episciences/issues/117) Improvements in emailing
- Undetected inactive accounts: Invite a reviewer > new reviewer > Invite a new user.
- Not allowing the author to be invited: Invite a reviewer > new reviewer > Invite a new user.
- The attachments to the rating report are not available [RT#145473]
- Answer revision request: in some situations, the "Submit" button remains inactive.

## 1.0.23 - 2022-01-19

### Added

- [#116](https://github.com/CCSDForge/episciences/issues/116) Release version is displayed on the interface (dashboard and page footer)
- Editors' choice at time of submission: informative text added. (gitlab #369)
- [#143](https://github.com/CCSDForge/episciences/issues/143) New DOI setting: Allow switching off manual or automatic DOI assignation (disabled mode)
- [#37](https://github.com/CCSDForge/episciences/issues/37)
    - Browse by Volume or Sections : Handle new content type 'application/json' to return json instead of html
    - On URLs like /volume/view/id/314159 and /section/view/id/314159 Handle new content type 'application/json' to return json instead of html (only published content)
    - On URLs like /volume/edit?id=314159 Handle new content type 'application/json' to return json instead of html but including all statuses of articles ; an authenticated and allowed user is required
    - JSON added to the list of public export formats
- Enhanced information message for statistics

### Changed

- UI/UX: Make the version number more explicit when proposing a new version
- Code Refactoring fixing of warning
- Dumping default data for table `MAIL_TEMPLATE`

### Fixed

- [#126](https://github.com/CCSDForge/episciences/issues/126) Clicking outside the window to compose an email closes the window with no confirmation #126
- [#117](https://github.com/CCSDForge/episciences/issues/117) Mailing bug: loss of the mail when you forget to put a recipient (gitlab #343)
- [#149](https://github.com/CCSDForge/episciences/issues/149) Emails and default language selection: language of the sender and the recipient could be a problem if the language of the sender is different from the language of the recipient (gitlab #402)
- Inconsistent REMINDER_DELAY: the displayed value is the "delay" parameter of the reminder instead of the calculated value (interval in number of days between the time the reminder was sent and the deadline).
- New submission: if the "submission date" metadata is empty: do not display it anymore.
- [#124](https://github.com/CCSDForge/episciences/issues/124): refining automatic notifications when abandoning submission
- RT#144252: better cleaning of attached file names
- RT#144246: an inactive account could still be visible on the Editorial Staff members page.
- [#117](https://github.com/CCSDForge/episciences/issues/117): An empty e-mail is not sent or received
- [#129](https://github.com/CCSDForge/episciences/issues/129): when you do a search from the mail history page nothing happens.
- [#141](https://github.com/CCSDForge/episciences/issues/141): visibility of pages menu is wrong inside menu editing.

### Changed

- [#126](https://github.com/CCSDForge/episciences/issues/126): prevent modal closure: disabling the click outside the modal area and by pressing Esc
- Enabling the "COI" option overrides the "Encapsulate editors" option

## 1.0.38.5 - 2022-12-14

### Fixed

- [#294](https://github.com/CCSDForge/episciences/issues/294): untranslated text
- API to return number of published documents

## 1.0.38.4 - 2022-12-12

### Fixed

- JS error: Failed to load plugin url: https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/langs/fr_FR.js
  @see https://www.tiny.cloud/docs-4x/configure/localization/#language
- Add %%PAPER_REP_URL%% tag to the description of the invitation templates.
- Change password: redirect to authentication if user is not logged in
- The '%%SENDER_SCREEN_NAME%%' tag is always present in the 'paper_paper_editor_refused_monitoring' template

## 1.0.38.3 - 2022-11-30

### Fixed

- Html filtering problem that breaks the display in the comments

## 1.0.38.2 - 2022-11-24

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): There is a difference between the capitalization of the first and last names in the user's profile
  and the first and last names of the user in the email recipient field [RT#148137]
- Fixed the fact that the Administrator role prevents testing for conflict of interest
- Error: $(...).fileupload is not a function for published articles.

### Changed

- Conflict management section: set default display lines to 5.
- Mailing > getContacts: add 'Authors' filter.
- resolved [#282](https://github.com/CCSDForge/episciences/issues/282): C:\fakepath: This is the security
  implementation of the browser: the browser is protecting you from accessing your disk structure.
- Code review / refactoring

## 1.0.38.1 - 2022-11-16

### Fixed

- Added missing translation for conflict of interest

### Changed

- Footer: replaced TOS with Term Of Use

## 1.0.38 - 2022-11-16

### Added

- New footer link to the Term of use (between the platform and users)

### Fixed

- Fixed ORCID 'X' in DOAJ export format, make it compliant with ORCID specification
- [RT#171463]: the reviews should not be seen by someone with a CoI declaration with
  the paper: this fix concerns all the paper's details.

### Changed

- UI/UX: Improved rendering of the "conflict management" section
- [RT#170200]: confirmation of the absence of conflict of interest: it is now possible to cancel this type of response.

## 1.0.37 - 2022-11-10

### Added

- New feature: [Open Science Lens](https://www.opensciencelens.eu/) feature available as a preview on selected journals

### Changed

- Improved rendering of volumes

## 1.0.36 - 2022-11-09

### Added

- Improved rendering of the volume page
- authors can now enter their paper password provided by the open archive on the article page.
- alt attribute to volume's image.
- Added an API method to get the number of users accounts

### Changed

- Optimization: there is no need to check for conflicts at the time of submission
- [#280](https://github.com/CCSDForge/episciences/issues/280): set spellcheck to false in dynamic datatable search boxes.
- [#281](https://github.com/CCSDForge/episciences/issues/281): Submit an article > Guidelines section: harmonization of journal/review terms [FR]
- Now, when an article is accepted, all unanswered invitations are deleted. The reviewer is informed of this action.
- Add 'ISSN pending' until the ISSN is actually issued

### Fixed

- Fix incorrect HTML in footer
- Incorrect paper status LMCS #10145 (related to https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#10273---2022-03-18)
- Empty %%REQUESTER_EXPRESSION%% tag (related to [#187](https://github.com/CCSDForge/episciences/issues/187)
- Incorrect status jdmdh#10203 (related with git#372)
- Fixed [#RT169943]: the tag %%SENDER_SCREEN_NAME%% (obsolete) that should have contained the editor's screen name has not been replaced:
  to be replaced in custom templates by %%EDITOR_FULL_NAME%% or %%EDITOR_SCREEN_NAME%%
  (@see https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#fixed-11 [RT##160301])

## 1.0.35 - 2022-10-19

### Added

- [RT#169107]: Feature - new option: do not allow the selection of an editor-in-chief when the author has the option to
  propose an editor at the time of submission.

### Changed

- [#255](https://github.com/CCSDForge/episciences/issues/255) Display ratings reports on the article page: Label updated

### Fixed

- [RT#169088]: Editors-in-chief and editorial secretaries are no longer notified of new submissions.

## 1.0.34.2 - 2022-10-18

### Fixed

- temporary fix: TinyMCE: loss of formatting for successive mailings
- Insert data in 'T_PAPER_DATASETS' table: MySql insert 0 instead of NULL
- [#273](https://github.com/CCSDForge/episciences/issues/273): if the editor changes the rating due date, then this
  is not reflected in the message.
- Fixes in exports format related to trailing ',' and language codes

## 1.0.34.1 - 2022-10-16

### Fixed

- Fixed bad JS escape of authors in reviewer invitation form
- Fixed Handle URLs links adding https://hdl.handle.net/
- Fixed display of disabled buttons
- Fixed 'Description of affiliation block'

### Added

- The activation of maintenance alerts is now configurable

## 1.0.34 - 2022-10-13

### Added

- Article managers (authors, editors, etc.) may add new optional - but recommended - metadata to the documents
    - [ORCID](https://orcid.org/)
    - Affiliations: for affiliations the [ROR](https://ror.org/) or a free text may be used. If an institution is not available in the ROR, a
      simple text entry may be used.
- Automatic enrichment of metadata:
    - Licenses: retrieved from [Datacite](https://datacite.org/), [OpenAIRE Research Graph](https://graph.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/)
    - ORCID: retrieved from [OpenAIRE Research Graph](https://graph.openaire.eu/)
    - Linked Datasets: [Scholexplorer](https://scholexplorer.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/): Datasets linked to Episciences
      publications
    - Funding: Research projects from [OpenAIRE Research Graph](https://graph.openaire.eu/) and [HAL](https://hal.archives-ouvertes.fr/)
    - Citations: Citations of published document retrieved with [OpenCitations](https://opencitations.net/) APIs
- Exports formats:
    - [DOAJ](https://doaj.org/about/) (export one document or a whole published volume) using DOAJ Schema
    - The new collected metadata have been added to Datacite, [Crossref](https://www.crossref.org/) and DOAJ export formats
- Browse by volume: new parameter to allow to display empty volumes. Default value is still no (only show volumes
  with papers published in the volume)
- [#186](https://github.com/CCSDForge/episciences/issues/186): Editors-in-chief can now report a conflict of
  interest. (Sign in an admin as another user: the real identity is now saved.)

### Fixed

- Fixed a display bug on the portal in connected mode (used by Episciences staff only)
- Failed to edit another account's profile from the user management page.
- impossibility to validate the profile if the user does not have an account in the application

### Changed

- new parameter for enabling/disabling submission from the archive to the application and vice versa.

## 1.0.33.2 - 2022-10-05

### Fixed

- RT#167820: all occurrences of tags in a template will be replaced by their real values.

## 1.0.33.1 - 2022-09-29

### Fixed

- Allow editors to view the list of conflicts on the article management page
- The number of conflicts indicated on the article management page is incorrect if the article has several versions

## 1.0.33 - 2022-09-28

### Added

- New page listing all email templates and available tags for each journal at /administratemail/tagslist
  (- Mail -> TagList)
- Episciences portal:
    - New Feed RSS + Atom : latest published documents, 2 docs per journal
    - API to get a list of publishing journals: include accepted repositories.
- Episciences journals
    - Added 'journals news' RSS feed for each journal
- New journal settings parameter: Allow post-acceptance revisions of articles
- 9 article statuses, available for journals allowing 'post-acceptance revisions of articles':
    - Accepted temporary version, waiting for author's final version: now, temporary versions that are accepted have this new status instead of "pending minor revision" (git #372)
    - Accepted - waiting for author's final version
    - Accepted, waiting for major revision
    - Accepted article - final version submitted, waiting for formatting by copy editors
    - Accepted temporary version after author's modifications
    - Accepted temporary version, waiting for minor revision
    - Accepted temporary version, waiting for major revision
    - Accepted - waiting validation by the author
    - Approved by author, waiting for final publication
- User profiles:
    - Adding ORCID and affiliation to your profile is now possible + this information is diplayed on the
      "editorial staff member" (gitlab #410) & dashboard pages.
    - Social Medias & Websites profile informations.
- New 'Author' role automatically added to users that have submitted a document
- Paper list: temporary versions now have a specific label
- Dashboard: Alert for administrators on the existence of papers without assigned editors
- Display the origin of a linked data when papers are linked to a dataset
- Administration page of a paper: new shortcut for administrators :
    - to allow to sign in as a copy-editor
    - to allow to sign in as an editor
- [227](https://github.com/CCSDForge/episciences/issues/227):
    - The revision deadline is now displayed below the article's status.
    - A pictogram is added to indicate the date of the revision deadline in the article management table.

### Changed

- [#237](https://github.com/CCSDForge/episciences/issues/237) Editor comments div is now bigger
- Upgraded publication RSS Feeds with DOIs
- [#142](https://github.com/CCSDForge/episciences/issues/142): allow that administrators answer revision and copy editing requests
- It is now possible to update the metadata on the document's page
- Request a new version of an article that has already been accepted
- [#166](https://github.com/CCSDForge/episciences/issues/116): hide "guest" role and doi-settings action
- The "paper status" filter is now dynamically created.
- Prevent "Add sources files" and "Add the formatted version" buttons JS reactivation
- Moved "copy editing" section to a more usable place on the same page
- Dashboard improvements: ability to reach an article from sections: my submissions and assigned articles
- [#187](https://github.com/CCSDForge/episciences/issues/187): change the default templates: 'new version submitted' and 'tmp version submitted'
- [#188](https://github.com/CCSDForge/episciences/issues/188): force notifications
- It is now possible to update the document version in Episciences to the most recent version in the open archive.
- [#164](https://github.com/CCSDForge/episciences/issues/164): harmonization of roles/privileges.
- CWI open repository has been temporarily removed until we adapt to their new OAI server

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): capitalization of names
- [#207](https://github.com/CCSDForge/episciences/issues/207): editing the translation of Chief Editors
- [#169](https://github.com/CCSDForge/episciences/issues/169): reports become visible on the article web page:
    - according to the parameters of the journal for published articles
    - for the owner, only if the paper is refused, waiting for revision, already accepted or published
- Another status (Revision request answer: without any modifications) is used when responding to a revision request: without any modifications.
- Revision requests: now we have an immediate visual feedback when changing the deadline.
- If the journal allows revision of articles after acceptance, the response to a request for a final version (without any changes) is similar to the proposal of a new version.

### Internal refactoring

- Code Refactoring fixing of warning
- Update Episciences_User::getScreenName()
- User Table: merge AFFILIATIONS" filed in "ADDITIONAL_PROFILE_INFORMATION"
- Multiple roles: ignore the "member" role when merging two accounts.
- Email Templates updates
- Script to clean the "USER_ROLES" table
- Parameter to detect automatic emails

## 1.0.32.2 - 2022-09-28

### Changed

- [196](https://github.com/CCSDForge/episciences/issues/196): Creation of an account following an invitation with a temporary account: from now on, the Last Name, First Name and Screen Name fields will be left empty (for the reviewer to fill in)
- [RT#164153]: refactoring: now obsolete template "paper_new_version_reviewer_reassign" (Reviewer assignment to a new version of an article) is removed.

### Fixed:

- account merging incomplete due to sql error when updating 'paper_conflicts' table.

## 1.0.32.1 - 2022-09-01

### Fixed

- Fixed bug introduced with [196] "Name" => "Last Name" when creating a new reviewer
- Fixed release notes links to issues

## 1.0.32 - 2022-08-31

### Changed

- [201](https://github.com/CCSDForge/episciences/issues/201): depending on journal settings, editor can accept, ask revision, reject and publish papers in every round.
- [238](https://github.com/CCSDForge/episciences/issues/238): harmonization of terms 'sections/rubriques'.
- [196](https://github.com/CCSDForge/episciences/issues/196) now,
    - Only one 'name' field is available when creating a new reviewer
    - Reviewers are sorted by lastname by default
- Empty TAG %%RECIPIENT_SCREEN_NAME%% for users who do not have a local account in "Unanswered reviewer invitation (reviewer copy)" reminder.
- (Internal/refactoring) transition to PHP 8: refactoring: "MaxMind GeoIP2 PHP API" is now used instead of PHP module.

### Fixed

- [RT#163166]: Problem sending mail from staff page.
- Fixed [#165](https://github.com/CCSDForge/episciences/issues/165): Line breaks in paper titles in API
- Fixed [#251](https://github.com/CCSDForge/episciences/issues/251): conform to ISO 3297 for displaying ISSNs

## Added

- Episciences portal: New Feed RSS + Atom : latest published documents, 2 docs per journal
- Added 'journals news' RSS feed

## 1.0.31.1 - 2022-07-13

### Changed

- User accounts merging procedure: take into account the Conflict Of Interests

## 1.0.31 - 2022-06-28

### Added

- A pictogram is added to indicate papers with conflicts in the article management table.

### Changed

- [192](https://github.com/CCSDForge/episciences/issues/192): allow editors to "Ask for other editors opinion".
- Only confirmed conflicts will be displayed in the conflict management section

### Fixed

- Ask revision: possibility to backdate the deadline.
- Fixed: RT #160301:
  the tags [%%SENDER_FULL_NAME%%, %%SENDER_SCREEN_NAME%%, %%SENDER_EMAIL%%, %%SENDER_FIRST_NAME%%', %%SENDER_LAST_NAME%% ]
  concerning the user of the action are filled with the data of the user connected at the time of the action.
  Making these variables available in the automatic mails poses a real problem: they are filled with the data of the mail recipient.
  So, from now on, the tags mentioned above will no longer be available in the automatic mail templates.
- Shifted display because of the error message (CSRF token)

## 1.0.30 - 2022-06-20

### Fixed

- Titles do not appear in the correct language when more than one language has been entered (e.g. SLOVO )

### Added

- It is now possible to manage conflicts on the article page: display & delete them

## 1.0.29.1 - 2022-06-13

### Changed

- Revision requests: now we have an immediate visual feedback when changing the deadline

### Fixed

- [#247](https://github.com/CCSDForge/episciences/issues/247):
  HTML links in outgoing emails are made relative [#247]: TinyMCE configuration: convert all relative URLs to absolute URLs.
- Edition of a volume: The title of article might not be retrieved if the language was not managed by the platform.
- Profile editing form does not display correctly when changing language.

## 1.0.29 - 2022-06-01

### Fixed

- Failed 'Not enough reviewers - editor copy' reminders.
- But leading to load journal translations from '/'.
- PHP Warning: Use of undefined constant RVID - assumed 'RVID' (this will throw an Error in a future version of PHP)
- Not enough review reminders: articles without invitations escape reminders because the function that retrieves invitations is not used properly.

### Added

- User list: make it easier to locate accounts that have not been activated.
- Addes Roadmap link + User survey/feedback on portal
- Make EU grants and OpenAIRE support more visible at the bottom of the page

## 1.0.28.5 - 2022-05-25

### Fixed

- [RT#158293]: article status is not correctly updated

## 1.0.28.4 - 2022-05-09

### Fixed

- File not found for Temporary Versions without attached files + refactoring.

## 1.0.28.3 - 2022-04-13

### Changed

- Updated error message when using a HAL identifier without any file.
- [#142](https://github.com/CCSDForge/episciences/issues/142)

## 1.0.28.2 - 2022-04-06

### Fixed

- Empty records from HAL repository: error message improved.
- Improvements and fixes on Datacite and Crossref XML exports

## 1.0.28.1 - 2022-03-23

### Fixed

- fix issue in crossref xml format with related_item

## 1.0.28 - 2022-03-23

### Changed

- Bump crossref schema to ver 4.8.1
- arXiv URLs now links to https version instead of http

### Added

- Crossref metadata export format for DOI:
    - add previous version URLs, link with relationship 'hasPreprint' to link preprints versions with the published version
    - add arXiv DOIs, link to journal's published DOI with 'isSameAs' relationship
    - add full text PDF URLs for Crossref 'Similarity Check' service

## 1.0.27.5 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format
- Adding Polyfill PHP 8.0

## 1.0.27.4 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format

## 1.0.27.3 - 2022-03-18

### Fixed

- Ratings submitted late after the start of the layout process caused the status of the article to be updated (rolled back).
- There is no need to review an article while it is being formatted by the author.

## 1.0.27.2 - 2022-03-18

### Fixed

- "Copy editor" role not authorized accessing to the public page of the paper
  and report a conflict of interest, if the option has been activated by the journal

## 1.0.27.1 - 2022-03-16

### Fixed

- Exclude the "paper-status" directory from the journal's resources.
- Content that is copied and pasted when reviewing an article may lose end of line.
- The linked data to an article is not deleted when the author deletes his own article

## 1.0.27 - 2022-03-14

# Changed

- Reviewer invitation: Users are now sorted by Name, First Name instead of First Name

# Fixed

- Dashboard: the number of papers assigned to the copy editor includes obsolete papers
- Issue reported to support RT #148133 (fixed before this release):
    - Some editors could see the reviewers names if they were also authors of the article

## 1.0.26 - 2022-03-01

# Fixed

- Issue reported to support RT #148133:
    - Incorrect "Reminder Delay: The "Reminder Delay" should correspond, in number of days, to the difference between the date the reminder was sent and the date the invitation was sent.
- Issue reported to support RT #148466:
    - Sorting by date in the Dashboard is wrong: Enabling the conflict of interest (COI) option distorts the pagination

## 1.0.25.1 - 2022-02-15

# Changed

[#152](https://github.com/CCSDForge/episciences/issues/152): page footer modifications.

## 1.0.25 - 2022-02-15

# Fixed

- All submissions are accessible to "guest editor" roles.
- Language bar missing when editing a mail template, including reminders.

## 1.0.24.2 - 2022-02-10

# Fixed

- [RT#146987] "search" button no longer works
- application error: syntax error or access violation if CAS user not found

## 1.0.24.1 - 2022-02-08

# Fixed

- A bug in the reminders code triggered emails with unusable HTTP links

## 1.0.24 - 2022-02-08

# Fixed

- Inability to delete a volume (RT#145178): reset the volume of the previous version when submitting a new version.
- [#117](https://github.com/CCSDForge/episciences/issues/117) Improvements in emailing
- Undetected inactive accounts: Invite a reviewer > new reviewer > Invite a new user.
- Not allowing the author to be invited: Invite a reviewer > new reviewer > Invite a new user.
- The attachments to the rating report are not available [RT#145473]
- Answer revision request: in some situations, the "Submit" button remains inactive.

## 1.0.23 - 2022-01-19

### Added

- [#116](https://github.com/CCSDForge/episciences/issues/116) Release version is displayed on the interface (dashboard and page footer)
- Editors' choice at time of submission: informative text added. (gitlab #369)
- [#143](https://github.com/CCSDForge/episciences/issues/143) New DOI setting: Allow switching off manual or automatic DOI assignation (disabled mode)
- [#37](https://github.com/CCSDForge/episciences/issues/37)
    - Browse by Volume or Sections : Handle new content type 'application/json' to return json instead of html
    - On URLs like /volume/view/id/314159 and /section/view/id/314159 Handle new content type 'application/json' to return json instead of html (only published content)
    - On URLs like /volume/edit?id=314159 Handle new content type 'application/json' to return json instead of html but including all statuses of articles ; an authenticated and allowed user is required
    - JSON added to the list of public export formats
- Enhanced information message for statistics

### Changed

- UI/UX: Make the version number more explicit when proposing a new version
- Code Refactoring fixing of warning
- Dumping default data for table `MAIL_TEMPLATE`

### Fixed

- [#126](https://github.com/CCSDForge/episciences/issues/126) Clicking outside the window to compose an email closes the window with no confirmation #126
- [#117](https://github.com/CCSDForge/episciences/issues/117) Mailing bug: loss of the mail when you forget to put a recipient (gitlab #343)
- [#149](https://github.com/CCSDForge/episciences/issues/149) Emails and default language selection: language of the sender and the recipient could be a problem if the language of the sender is different from the language of the recipient (gitlab #402)
- Inconsistent REMINDER_DELAY: the displayed value is the "delay" parameter of the reminder instead of the calculated value (interval in number of days between the time the reminder was sent and the deadline).
- New submission: if the "submission date" metadata is empty: do not display it anymore.
- [#124](https://github.com/CCSDForge/episciences/issues/124): refining automatic notifications when abandoning submission
- RT#144252: better cleaning of attached file names
- RT#144246: an inactive account could still be visible on the Editorial Staff members page.
- [#117](https://github.com/CCSDForge/episciences/issues/117): An empty e-mail is not sent or received
- [#129](https://github.com/CCSDForge/episciences/issues/129): when you do a search from the mail history page nothing happens.
- [#141](https://github.com/CCSDForge/episciences/issues/141): visibility of pages menu is wrong inside menu editing.

### Changed

- [#126](https://github.com/CCSDForge/episciences/issues/126): prevent modal closure: disabling the click outside the modal area and by pressing Esc
- Enabling the "COI" option overrides the "Encapsulate editors" option

## 1.0.38.5 - 2022-12-14

### Fixed

- [#294](https://github.com/CCSDForge/episciences/issues/294): untranslated text
- API to return number of published documents

## 1.0.38.4 - 2022-12-12

### Fixed

- JS error: Failed to load plugin url: https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/langs/fr_FR.js
  @see https://www.tiny.cloud/docs-4x/configure/localization/#language
- Add %%PAPER_REP_URL%% tag to the description of the invitation templates.
- Change password: redirect to authentication if user is not logged in
- The '%%SENDER_SCREEN_NAME%%' tag is always present in the 'paper_paper_editor_refused_monitoring' template

## 1.0.38.3 - 2022-11-30

### Fixed

- Html filtering problem that breaks the display in the comments

## 1.0.38.2 - 2022-11-24

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): There is a difference between the capitalization of the first and last names in the user's profile
  and the first and last names of the user in the email recipient field [RT#148137]
- Fixed the fact that the Administrator role prevents testing for conflict of interest
- Error: $(...).fileupload is not a function for published articles.

### Changed

- Conflict management section: set default display lines to 5.
- Mailing > getContacts: add 'Authors' filter.
- resolved [#282](https://github.com/CCSDForge/episciences/issues/282): C:\fakepath: This is the security
  implementation of the browser: the browser is protecting you from accessing your disk structure.
- Code review / refactoring

## 1.0.38.1 - 2022-11-16

### Fixed

- Added missing translation for conflict of interest

### Changed

- Footer: replaced TOS with Term Of Use

## 1.0.38 - 2022-11-16

### Added

- New footer link to the Term of use (between the platform and users)

### Fixed

- Fixed ORCID 'X' in DOAJ export format, make it compliant with ORCID specification
- [RT#171463]: the reviews should not be seen by someone with a CoI declaration with
  the paper: this fix concerns all the paper's details.

### Changed

- UI/UX: Improved rendering of the "conflict management" section
- [RT#170200]: confirmation of the absence of conflict of interest: it is now possible to cancel this type of response.

## 1.0.37 - 2022-11-10

### Added

- New feature: [Open Science Lens](https://www.opensciencelens.eu/) feature available as a preview on selected journals

### Changed

- Improved rendering of volumes

## 1.0.36 - 2022-11-09

### Added

- Improved rendering of the volume page
- authors can now enter their paper password provided by the open archive on the article page.
- alt attribute to volume's image.
- Added an API method to get the number of users accounts

### Changed

- Optimization: there is no need to check for conflicts at the time of submission
- [#280](https://github.com/CCSDForge/episciences/issues/280): set spellcheck to false in dynamic datatable search boxes.
- [#281](https://github.com/CCSDForge/episciences/issues/281): Submit an article > Guidelines section: harmonization of journal/review terms [FR]
- Now, when an article is accepted, all unanswered invitations are deleted. The reviewer is informed of this action.
- Add 'ISSN pending' until the ISSN is actually issued

### Fixed

- Fix incorrect HTML in footer
- Incorrect paper status LMCS #10145 (related to https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#10273---2022-03-18)
- Empty %%REQUESTER_EXPRESSION%% tag (related to [#187](https://github.com/CCSDForge/episciences/issues/187)
- Incorrect status jdmdh#10203 (related with git#372)
- Fixed [#RT169943]: the tag %%SENDER_SCREEN_NAME%% (obsolete) that should have contained the editor's screen name has not been replaced:
  to be replaced in custom templates by %%EDITOR_FULL_NAME%% or %%EDITOR_SCREEN_NAME%%
  (@see https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#fixed-11 [RT##160301])

## 1.0.35 - 2022-10-19

### Added

- [RT#169107]: Feature - new option: do not allow the selection of an editor-in-chief when the author has the option to
  propose an editor at the time of submission.

### Changed

- [#255](https://github.com/CCSDForge/episciences/issues/255) Display ratings reports on the article page: Label updated

### Fixed

- [RT#169088]: Editors-in-chief and editorial secretaries are no longer notified of new submissions.

## 1.0.34.2 - 2022-10-18

### Fixed

- temporary fix: TinyMCE: loss of formatting for successive mailings
- Insert data in 'T_PAPER_DATASETS' table: MySql insert 0 instead of NULL
- [#273](https://github.com/CCSDForge/episciences/issues/273): if the editor changes the rating due date, then this
  is not reflected in the message.
- Fixes in exports format related to trailing ',' and language codes

## 1.0.34.1 - 2022-10-16

### Fixed

- Fixed bad JS escape of authors in reviewer invitation form
- Fixed Handle URLs links adding https://hdl.handle.net/
- Fixed display of disabled buttons
- Fixed 'Description of affiliation block'

### Added

- The activation of maintenance alerts is now configurable

## 1.0.34 - 2022-10-13

### Added

- Article managers (authors, editors, etc.) may add new optional - but recommended - metadata to the documents
    - [ORCID](https://orcid.org/)
    - Affiliations: for affiliations the [ROR](https://ror.org/) or a free text may be used. If an institution is not available in the ROR, a
      simple text entry may be used.
- Automatic enrichment of metadata:
    - Licenses: retrieved from [Datacite](https://datacite.org/), [OpenAIRE Research Graph](https://graph.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/)
    - ORCID: retrieved from [OpenAIRE Research Graph](https://graph.openaire.eu/)
    - Linked Datasets: [Scholexplorer](https://scholexplorer.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/): Datasets linked to Episciences
      publications
    - Funding: Research projects from [OpenAIRE Research Graph](https://graph.openaire.eu/) and [HAL](https://hal.archives-ouvertes.fr/)
    - Citations: Citations of published document retrieved with [OpenCitations](https://opencitations.net/) APIs
- Exports formats:
    - [DOAJ](https://doaj.org/about/) (export one document or a whole published volume) using DOAJ Schema
    - The new collected metadata have been added to Datacite, [Crossref](https://www.crossref.org/) and DOAJ export formats
- Browse by volume: new parameter to allow to display empty volumes. Default value is still no (only show volumes
  with papers published in the volume)
- [#186](https://github.com/CCSDForge/episciences/issues/186): Editors-in-chief can now report a conflict of
  interest. (Sign in an admin as another user: the real identity is now saved.)

### Fixed

- Fixed a display bug on the portal in connected mode (used by Episciences staff only)
- Failed to edit another account's profile from the user management page.
- impossibility to validate the profile if the user does not have an account in the application

### Changed

- new parameter for enabling/disabling submission from the archive to the application and vice versa.

## 1.0.33.2 - 2022-10-05

### Fixed

- RT#167820: all occurrences of tags in a template will be replaced by their real values.

## 1.0.33.1 - 2022-09-29

### Fixed

- Allow editors to view the list of conflicts on the article management page
- The number of conflicts indicated on the article management page is incorrect if the article has several versions

## 1.0.33 - 2022-09-28

### Added

- New page listing all email templates and available tags for each journal at /administratemail/tagslist
  (- Mail -> TagList)
- Episciences portal:
    - New Feed RSS + Atom : latest published documents, 2 docs per journal
    - API to get a list of publishing journals: include accepted repositories.
- Episciences journals
    - Added 'journals news' RSS feed for each journal
- New journal settings parameter: Allow post-acceptance revisions of articles
- 9 article statuses, available for journals allowing 'post-acceptance revisions of articles':
    - Accepted temporary version, waiting for author's final version: now, temporary versions that are accepted have this new status instead of "pending minor revision" (git #372)
    - Accepted - waiting for author's final version
    - Accepted, waiting for major revision
    - Accepted article - final version submitted, waiting for formatting by copy editors
    - Accepted temporary version after author's modifications
    - Accepted temporary version, waiting for minor revision
    - Accepted temporary version, waiting for major revision
    - Accepted - waiting validation by the author
    - Approved by author, waiting for final publication
- User profiles:
    - Adding ORCID and affiliation to your profile is now possible + this information is diplayed on the
      "editorial staff member" (gitlab #410) & dashboard pages.
    - Social Medias & Websites profile informations.
- New 'Author' role automatically added to users that have submitted a document
- Paper list: temporary versions now have a specific label
- Dashboard: Alert for administrators on the existence of papers without assigned editors
- Display the origin of a linked data when papers are linked to a dataset
- Administration page of a paper: new shortcut for administrators :
    - to allow to sign in as a copy-editor
    - to allow to sign in as an editor
- [227](https://github.com/CCSDForge/episciences/issues/227):
    - The revision deadline is now displayed below the article's status.
    - A pictogram is added to indicate the date of the revision deadline in the article management table.

### Changed

- [#237](https://github.com/CCSDForge/episciences/issues/237) Editor comments div is now bigger
- Upgraded publication RSS Feeds with DOIs
- [#142](https://github.com/CCSDForge/episciences/issues/142): allow that administrators answer revision and copy editing requests
- It is now possible to update the metadata on the document's page
- Request a new version of an article that has already been accepted
- [#166](https://github.com/CCSDForge/episciences/issues/116): hide "guest" role and doi-settings action
- The "paper status" filter is now dynamically created.
- Prevent "Add sources files" and "Add the formatted version" buttons JS reactivation
- Moved "copy editing" section to a more usable place on the same page
- Dashboard improvements: ability to reach an article from sections: my submissions and assigned articles
- [#187](https://github.com/CCSDForge/episciences/issues/187): change the default templates: 'new version submitted' and 'tmp version submitted'
- [#188](https://github.com/CCSDForge/episciences/issues/188): force notifications
- It is now possible to update the document version in Episciences to the most recent version in the open archive.
- [#164](https://github.com/CCSDForge/episciences/issues/164): harmonization of roles/privileges.
- CWI open repository has been temporarily removed until we adapt to their new OAI server

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): capitalization of names
- [#207](https://github.com/CCSDForge/episciences/issues/207): editing the translation of Chief Editors
- [#169](https://github.com/CCSDForge/episciences/issues/169): reports become visible on the article web page:
    - according to the parameters of the journal for published articles
    - for the owner, only if the paper is refused, waiting for revision, already accepted or published
- Another status (Revision request answer: without any modifications) is used when responding to a revision request: without any modifications.
- Revision requests: now we have an immediate visual feedback when changing the deadline.
- If the journal allows revision of articles after acceptance, the response to a request for a final version (without any changes) is similar to the proposal of a new version.

### Internal refactoring

- Code Refactoring fixing of warning
- Update Episciences_User::getScreenName()
- User Table: merge AFFILIATIONS" filed in "ADDITIONAL_PROFILE_INFORMATION"
- Multiple roles: ignore the "member" role when merging two accounts.
- Email Templates updates
- Script to clean the "USER_ROLES" table
- Parameter to detect automatic emails

## 1.0.32.2 - 2022-09-28

### Changed

- [196](https://github.com/CCSDForge/episciences/issues/196): Creation of an account following an invitation with a temporary account: from now on, the Last Name, First Name and Screen Name fields will be left empty (for the reviewer to fill in)
- [RT#164153]: refactoring: now obsolete template "paper_new_version_reviewer_reassign" (Reviewer assignment to a new version of an article) is removed.

### Fixed:

- account merging incomplete due to sql error when updating 'paper_conflicts' table.

## 1.0.32.1 - 2022-09-01

### Fixed

- Fixed bug introduced with [196] "Name" => "Last Name" when creating a new reviewer
- Fixed release notes links to issues

## 1.0.32 - 2022-08-31

### Changed

- [201](https://github.com/CCSDForge/episciences/issues/201): depending on journal settings, editor can accept, ask revision, reject and publish papers in every round.
- [238](https://github.com/CCSDForge/episciences/issues/238): harmonization of terms 'sections/rubriques'.
- [196](https://github.com/CCSDForge/episciences/issues/196) now,
    - Only one 'name' field is available when creating a new reviewer
    - Reviewers are sorted by lastname by default
- Empty TAG %%RECIPIENT_SCREEN_NAME%% for users who do not have a local account in "Unanswered reviewer invitation (reviewer copy)" reminder.
- (Internal/refactoring) transition to PHP 8: refactoring: "MaxMind GeoIP2 PHP API" is now used instead of PHP module.

### Fixed

- [RT#163166]: Problem sending mail from staff page.
- Fixed [#165](https://github.com/CCSDForge/episciences/issues/165): Line breaks in paper titles in API
- Fixed [#251](https://github.com/CCSDForge/episciences/issues/251): conform to ISO 3297 for displaying ISSNs

## Added

- Episciences portal: New Feed RSS + Atom : latest published documents, 2 docs per journal
- Added 'journals news' RSS feed

## 1.0.31.1 - 2022-07-13

### Changed

- User accounts merging procedure: take into account the Conflict Of Interests

## 1.0.31 - 2022-06-28

### Added

- A pictogram is added to indicate papers with conflicts in the article management table.

### Changed

- [192](https://github.com/CCSDForge/episciences/issues/192): allow editors to "Ask for other editors opinion".
- Only confirmed conflicts will be displayed in the conflict management section

### Fixed

- Ask revision: possibility to backdate the deadline.
- Fixed: RT #160301:
  the tags [%%SENDER_FULL_NAME%%, %%SENDER_SCREEN_NAME%%, %%SENDER_EMAIL%%, %%SENDER_FIRST_NAME%%', %%SENDER_LAST_NAME%% ]
  concerning the user of the action are filled with the data of the user connected at the time of the action.
  Making these variables available in the automatic mails poses a real problem: they are filled with the data of the mail recipient.
  So, from now on, the tags mentioned above will no longer be available in the automatic mail templates.
- Shifted display because of the error message (CSRF token)

## 1.0.30 - 2022-06-20

### Fixed

- Titles do not appear in the correct language when more than one language has been entered (e.g. SLOVO )

### Added

- It is now possible to manage conflicts on the article page: display & delete them

## 1.0.29.1 - 2022-06-13

### Changed

- Revision requests: now we have an immediate visual feedback when changing the deadline

### Fixed

- [#247](https://github.com/CCSDForge/episciences/issues/247):
  HTML links in outgoing emails are made relative [#247]: TinyMCE configuration: convert all relative URLs to absolute URLs.
- Edition of a volume: The title of article might not be retrieved if the language was not managed by the platform.
- Profile editing form does not display correctly when changing language.

## 1.0.29 - 2022-06-01

### Fixed

- Failed 'Not enough reviewers - editor copy' reminders.
- But leading to load journal translations from '/'.
- PHP Warning: Use of undefined constant RVID - assumed 'RVID' (this will throw an Error in a future version of PHP)
- Not enough review reminders: articles without invitations escape reminders because the function that retrieves invitations is not used properly.

### Added

- User list: make it easier to locate accounts that have not been activated.
- Addes Roadmap link + User survey/feedback on portal
- Make EU grants and OpenAIRE support more visible at the bottom of the page

## 1.0.28.5 - 2022-05-25

### Fixed

- [RT#158293]: article status is not correctly updated

## 1.0.28.4 - 2022-05-09

### Fixed

- File not found for Temporary Versions without attached files + refactoring.

## 1.0.28.3 - 2022-04-13

### Changed

- Updated error message when using a HAL identifier without any file.
- [#142](https://github.com/CCSDForge/episciences/issues/142)

## 1.0.28.2 - 2022-04-06

### Fixed

- Empty records from HAL repository: error message improved.
- Improvements and fixes on Datacite and Crossref XML exports

## 1.0.28.1 - 2022-03-23

### Fixed

- fix issue in crossref xml format with related_item

## 1.0.28 - 2022-03-23

### Changed

- Bump crossref schema to ver 4.8.1
- arXiv URLs now links to https version instead of http

### Added

- Crossref metadata export format for DOI:
    - add previous version URLs, link with relationship 'hasPreprint' to link preprints versions with the published version
    - add arXiv DOIs, link to journal's published DOI with 'isSameAs' relationship
    - add full text PDF URLs for Crossref 'Similarity Check' service

## 1.0.27.5 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format
- Adding Polyfill PHP 8.0

## 1.0.27.4 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format

## 1.0.27.3 - 2022-03-18

### Fixed

- Ratings submitted late after the start of the layout process caused the status of the article to be updated (rolled back).
- There is no need to review an article while it is being formatted by the author.

## 1.0.27.2 - 2022-03-18

### Fixed

- "Copy editor" role not authorized accessing to the public page of the paper
  and report a conflict of interest, if the option has been activated by the journal

## 1.0.27.1 - 2022-03-16

### Fixed

- Exclude the "paper-status" directory from the journal's resources.
- Content that is copied and pasted when reviewing an article may lose end of line.
- The linked data to an article is not deleted when the author deletes his own article

## 1.0.27 - 2022-03-14

# Changed

- Reviewer invitation: Users are now sorted by Name, First Name instead of First Name

# Fixed

- Dashboard: the number of papers assigned to the copy editor includes obsolete papers
- Issue reported to support RT #148133 (fixed before this release):
    - Some editors could see the reviewers names if they were also authors of the article

## 1.0.26 - 2022-03-01

# Fixed

- Issue reported to support RT #148133:
    - Incorrect "Reminder Delay: The "Reminder Delay" should correspond, in number of days, to the difference between the date the reminder was sent and the date the invitation was sent.
- Issue reported to support RT #148466:
    - Sorting by date in the Dashboard is wrong: Enabling the conflict of interest (COI) option distorts the pagination

## 1.0.25.1 - 2022-02-15

# Changed

[#152](https://github.com/CCSDForge/episciences/issues/152): page footer modifications.

## 1.0.25 - 2022-02-15

# Fixed

- All submissions are accessible to "guest editor" roles.
- Language bar missing when editing a mail template, including reminders.

## 1.0.24.2 - 2022-02-10

# Fixed

- [RT#146987] "search" button no longer works
- application error: syntax error or access violation if CAS user not found

## 1.0.24.1 - 2022-02-08

# Fixed

- A bug in the reminders code triggered emails with unusable HTTP links

## 1.0.24 - 2022-02-08

# Fixed

- Inability to delete a volume (RT#145178): reset the volume of the previous version when submitting a new version.
- [#117](https://github.com/CCSDForge/episciences/issues/117) Improvements in emailing
- Undetected inactive accounts: Invite a reviewer > new reviewer > Invite a new user.
- Not allowing the author to be invited: Invite a reviewer > new reviewer > Invite a new user.
- The attachments to the rating report are not available [RT#145473]
- Answer revision request: in some situations, the "Submit" button remains inactive.

## 1.0.23 - 2022-01-19

### Added

- [#116](https://github.com/CCSDForge/episciences/issues/116) Release version is displayed on the interface (dashboard and page footer)
- Editors' choice at time of submission: informative text added. (gitlab #369)
- [#143](https://github.com/CCSDForge/episciences/issues/143) New DOI setting: Allow switching off manual or automatic DOI assignation (disabled mode)
- [#37](https://github.com/CCSDForge/episciences/issues/37)
    - Browse by Volume or Sections : Handle new content type 'application/json' to return json instead of html
    - On URLs like /volume/view/id/314159 and /section/view/id/314159 Handle new content type 'application/json' to return json instead of html (only published content)
    - On URLs like /volume/edit?id=314159 Handle new content type 'application/json' to return json instead of html but including all statuses of articles ; an authenticated and allowed user is required
    - JSON added to the list of public export formats
- Enhanced information message for statistics

### Changed

- UI/UX: Make the version number more explicit when proposing a new version
- Code Refactoring fixing of warning
- Dumping default data for table `MAIL_TEMPLATE`

### Fixed

- [#126](https://github.com/CCSDForge/episciences/issues/126) Clicking outside the window to compose an email closes the window with no confirmation #126
- [#117](https://github.com/CCSDForge/episciences/issues/117) Mailing bug: loss of the mail when you forget to put a recipient (gitlab #343)
- [#149](https://github.com/CCSDForge/episciences/issues/149) Emails and default language selection: language of the sender and the recipient could be a problem if the language of the sender is different from the language of the recipient (gitlab #402)
- Inconsistent REMINDER_DELAY: the displayed value is the "delay" parameter of the reminder instead of the calculated value (interval in number of days between the time the reminder was sent and the deadline).
- New submission: if the "submission date" metadata is empty: do not display it anymore.
- [#124](https://github.com/CCSDForge/episciences/issues/124): refining automatic notifications when abandoning submission
- RT#144252: better cleaning of attached file names
- RT#144246: an inactive account could still be visible on the Editorial Staff members page.
- [#117](https://github.com/CCSDForge/episciences/issues/117): An empty e-mail is not sent or received
- [#129](https://github.com/CCSDForge/episciences/issues/129): when you do a search from the mail history page nothing happens.
- [#141](https://github.com/CCSDForge/episciences/issues/141): visibility of pages menu is wrong inside menu editing.

### Changed

- [#126](https://github.com/CCSDForge/episciences/issues/126): prevent modal closure: disabling the click outside the modal area and by pressing Esc
- Enabling the "COI" option overrides the "Encapsulate editors" option

## 1.0.24.2 - 2022-02-10

# Fixed

- [RT#146987] "search" button no longer works
- application error: syntax error or access violation if CAS user not found

## 1.0.24.1 - 2022-02-08

# Fixed

- A bug in the reminders code triggered emails with unusable HTTP links

## 1.0.24 - 2022-02-08

# Fixed

- Inability to delete a volume (RT#145178): reset the volume of the previous version when submitting a new version.
- [#117](https://github.com/CCSDForge/episciences/issues/117) Improvements in emailing
- Undetected inactive accounts: Invite a reviewer > new reviewer > Invite a new user.
- Not allowing the author to be invited: Invite a reviewer > new reviewer > Invite a new user.
- The attachments to the rating report are not available [RT#145473]
- Answer revision request: in some situations, the "Submit" button remains inactive.

## 1.0.23 - 2022-01-19

### Added

- [#116](https://github.com/CCSDForge/episciences/issues/116) Release version is displayed on the interface (dashboard and page footer)
- Editors' choice at time of submission: informative text added. (gitlab #369)
- [#143](https://github.com/CCSDForge/episciences/issues/143) New DOI setting: Allow switching off manual or automatic DOI assignation (disabled mode)
- [#37](https://github.com/CCSDForge/episciences/issues/37)
    - Browse by Volume or Sections : Handle new content type 'application/json' to return json instead of html
    - On URLs like /volume/view/id/314159 and /section/view/id/314159 Handle new content type 'application/json' to return json instead of html (only published content)
    - On URLs like /volume/edit?id=314159 Handle new content type 'application/json' to return json instead of html but including all statuses of articles ; an authenticated and allowed user is required
    - JSON added to the list of public export formats
- Enhanced information message for statistics

### Changed

- UI/UX: Make the version number more explicit when proposing a new version
- Code Refactoring fixing of warning
- Dumping default data for table `MAIL_TEMPLATE`

### Fixed

- [#126](https://github.com/CCSDForge/episciences/issues/126) Clicking outside the window to compose an email closes the window with no confirmation #126
- [#117](https://github.com/CCSDForge/episciences/issues/117) Mailing bug: loss of the mail when you forget to put a recipient (gitlab #343)
- [#149](https://github.com/CCSDForge/episciences/issues/149) Emails and default language selection: language of the sender and the recipient could be a problem if the language of the sender is different from the language of the recipient (gitlab #402)
- Inconsistent REMINDER_DELAY: the displayed value is the "delay" parameter of the reminder instead of the calculated value (interval in number of days between the time the reminder was sent and the deadline).
- New submission: if the "submission date" metadata is empty: do not display it anymore.
- [#124](https://github.com/CCSDForge/episciences/issues/124): refining automatic notifications when abandoning submission
- RT#144252: better cleaning of attached file names
- RT#144246: an inactive account could still be visible on the Editorial Staff members page.
- [#117](https://github.com/CCSDForge/episciences/issues/117): An empty e-mail is not sent or received
- [#129](https://github.com/CCSDForge/episciences/issues/129): when you do a search from the mail history page nothing happens.
- [#141](https://github.com/CCSDForge/episciences/issues/141): visibility of pages menu is wrong inside menu editing.

### Changed

- [#126](https://github.com/CCSDForge/episciences/issues/126): prevent modal closure: disabling the click outside the modal area and by pressing Esc
- Enabling the "COI" option overrides the "Encapsulate editors" option

## 1.0.38.5 - 2022-12-14

### Fixed

- [#294](https://github.com/CCSDForge/episciences/issues/294): untranslated text
- API to return number of published documents

## 1.0.38.4 - 2022-12-12

### Fixed

- JS error: Failed to load plugin url: https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/langs/fr_FR.js
  @see https://www.tiny.cloud/docs-4x/configure/localization/#language
- Add %%PAPER_REP_URL%% tag to the description of the invitation templates.
- Change password: redirect to authentication if user is not logged in
- The '%%SENDER_SCREEN_NAME%%' tag is always present in the 'paper_paper_editor_refused_monitoring' template

## 1.0.38.3 - 2022-11-30

### Fixed

- Html filtering problem that breaks the display in the comments

## 1.0.38.2 - 2022-11-24

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): There is a difference between the capitalization of the first and last names in the user's profile
  and the first and last names of the user in the email recipient field [RT#148137]
- Fixed the fact that the Administrator role prevents testing for conflict of interest
- Error: $(...).fileupload is not a function for published articles.

### Changed

- Conflict management section: set default display lines to 5.
- Mailing > getContacts: add 'Authors' filter.
- resolved [#282](https://github.com/CCSDForge/episciences/issues/282): C:\fakepath: This is the security
  implementation of the browser: the browser is protecting you from accessing your disk structure.
- Code review / refactoring

## 1.0.38.1 - 2022-11-16

### Fixed

- Added missing translation for conflict of interest

### Changed

- Footer: replaced TOS with Term Of Use

## 1.0.38 - 2022-11-16

### Added

- New footer link to the Term of use (between the platform and users)

### Fixed

- Fixed ORCID 'X' in DOAJ export format, make it compliant with ORCID specification
- [RT#171463]: the reviews should not be seen by someone with a CoI declaration with
  the paper: this fix concerns all the paper's details.

### Changed

- UI/UX: Improved rendering of the "conflict management" section
- [RT#170200]: confirmation of the absence of conflict of interest: it is now possible to cancel this type of response.

## 1.0.37 - 2022-11-10

### Added

- New feature: [Open Science Lens](https://www.opensciencelens.eu/) feature available as a preview on selected journals

### Changed

- Improved rendering of volumes

## 1.0.36 - 2022-11-09

### Added

- Improved rendering of the volume page
- authors can now enter their paper password provided by the open archive on the article page.
- alt attribute to volume's image.
- Added an API method to get the number of users accounts

### Changed

- Optimization: there is no need to check for conflicts at the time of submission
- [#280](https://github.com/CCSDForge/episciences/issues/280): set spellcheck to false in dynamic datatable search boxes.
- [#281](https://github.com/CCSDForge/episciences/issues/281): Submit an article > Guidelines section: harmonization of journal/review terms [FR]
- Now, when an article is accepted, all unanswered invitations are deleted. The reviewer is informed of this action.
- Add 'ISSN pending' until the ISSN is actually issued

### Fixed

- Fix incorrect HTML in footer
- Incorrect paper status LMCS #10145 (related to https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#10273---2022-03-18)
- Empty %%REQUESTER_EXPRESSION%% tag (related to [#187](https://github.com/CCSDForge/episciences/issues/187)
- Incorrect status jdmdh#10203 (related with git#372)
- Fixed [#RT169943]: the tag %%SENDER_SCREEN_NAME%% (obsolete) that should have contained the editor's screen name has not been replaced:
  to be replaced in custom templates by %%EDITOR_FULL_NAME%% or %%EDITOR_SCREEN_NAME%%
  (@see https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#fixed-11 [RT##160301])

## 1.0.35 - 2022-10-19

### Added

- [RT#169107]: Feature - new option: do not allow the selection of an editor-in-chief when the author has the option to
  propose an editor at the time of submission.

### Changed

- [#255](https://github.com/CCSDForge/episciences/issues/255) Display ratings reports on the article page: Label updated

### Fixed

- [RT#169088]: Editors-in-chief and editorial secretaries are no longer notified of new submissions.

## 1.0.34.2 - 2022-10-18

### Fixed

- temporary fix: TinyMCE: loss of formatting for successive mailings
- Insert data in 'T_PAPER_DATASETS' table: MySql insert 0 instead of NULL
- [#273](https://github.com/CCSDForge/episciences/issues/273): if the editor changes the rating due date, then this
  is not reflected in the message.
- Fixes in exports format related to trailing ',' and language codes

## 1.0.34.1 - 2022-10-16

### Fixed

- Fixed bad JS escape of authors in reviewer invitation form
- Fixed Handle URLs links adding https://hdl.handle.net/
- Fixed display of disabled buttons
- Fixed 'Description of affiliation block'

### Added

- The activation of maintenance alerts is now configurable

## 1.0.34 - 2022-10-13

### Added

- Article managers (authors, editors, etc.) may add new optional - but recommended - metadata to the documents
    - [ORCID](https://orcid.org/)
    - Affiliations: for affiliations the [ROR](https://ror.org/) or a free text may be used. If an institution is not available in the ROR, a
      simple text entry may be used.
- Automatic enrichment of metadata:
    - Licenses: retrieved from [Datacite](https://datacite.org/), [OpenAIRE Research Graph](https://graph.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/)
    - ORCID: retrieved from [OpenAIRE Research Graph](https://graph.openaire.eu/)
    - Linked Datasets: [Scholexplorer](https://scholexplorer.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/): Datasets linked to Episciences
      publications
    - Funding: Research projects from [OpenAIRE Research Graph](https://graph.openaire.eu/) and [HAL](https://hal.archives-ouvertes.fr/)
    - Citations: Citations of published document retrieved with [OpenCitations](https://opencitations.net/) APIs
- Exports formats:
    - [DOAJ](https://doaj.org/about/) (export one document or a whole published volume) using DOAJ Schema
    - The new collected metadata have been added to Datacite, [Crossref](https://www.crossref.org/) and DOAJ export formats
- Browse by volume: new parameter to allow to display empty volumes. Default value is still no (only show volumes
  with papers published in the volume)
- [#186](https://github.com/CCSDForge/episciences/issues/186): Editors-in-chief can now report a conflict of
  interest. (Sign in an admin as another user: the real identity is now saved.)

### Fixed

- Fixed a display bug on the portal in connected mode (used by Episciences staff only)
- Failed to edit another account's profile from the user management page.
- impossibility to validate the profile if the user does not have an account in the application

### Changed

- new parameter for enabling/disabling submission from the archive to the application and vice versa.

## 1.0.33.2 - 2022-10-05

### Fixed

- RT#167820: all occurrences of tags in a template will be replaced by their real values.

## 1.0.33.1 - 2022-09-29

### Fixed

- Allow editors to view the list of conflicts on the article management page
- The number of conflicts indicated on the article management page is incorrect if the article has several versions

## 1.0.33 - 2022-09-28

### Added

- New page listing all email templates and available tags for each journal at /administratemail/tagslist
  (- Mail -> TagList)
- Episciences portal:
    - New Feed RSS + Atom : latest published documents, 2 docs per journal
    - API to get a list of publishing journals: include accepted repositories.
- Episciences journals
    - Added 'journals news' RSS feed for each journal
- New journal settings parameter: Allow post-acceptance revisions of articles
- 9 article statuses, available for journals allowing 'post-acceptance revisions of articles':
    - Accepted temporary version, waiting for author's final version: now, temporary versions that are accepted have this new status instead of "pending minor revision" (git #372)
    - Accepted - waiting for author's final version
    - Accepted, waiting for major revision
    - Accepted article - final version submitted, waiting for formatting by copy editors
    - Accepted temporary version after author's modifications
    - Accepted temporary version, waiting for minor revision
    - Accepted temporary version, waiting for major revision
    - Accepted - waiting validation by the author
    - Approved by author, waiting for final publication
- User profiles:
    - Adding ORCID and affiliation to your profile is now possible + this information is diplayed on the
      "editorial staff member" (gitlab #410) & dashboard pages.
    - Social Medias & Websites profile informations.
- New 'Author' role automatically added to users that have submitted a document
- Paper list: temporary versions now have a specific label
- Dashboard: Alert for administrators on the existence of papers without assigned editors
- Display the origin of a linked data when papers are linked to a dataset
- Administration page of a paper: new shortcut for administrators :
    - to allow to sign in as a copy-editor
    - to allow to sign in as an editor
- [227](https://github.com/CCSDForge/episciences/issues/227):
    - The revision deadline is now displayed below the article's status.
    - A pictogram is added to indicate the date of the revision deadline in the article management table.

### Changed

- [#237](https://github.com/CCSDForge/episciences/issues/237) Editor comments div is now bigger
- Upgraded publication RSS Feeds with DOIs
- [#142](https://github.com/CCSDForge/episciences/issues/142): allow that administrators answer revision and copy editing requests
- It is now possible to update the metadata on the document's page
- Request a new version of an article that has already been accepted
- [#166](https://github.com/CCSDForge/episciences/issues/116): hide "guest" role and doi-settings action
- The "paper status" filter is now dynamically created.
- Prevent "Add sources files" and "Add the formatted version" buttons JS reactivation
- Moved "copy editing" section to a more usable place on the same page
- Dashboard improvements: ability to reach an article from sections: my submissions and assigned articles
- [#187](https://github.com/CCSDForge/episciences/issues/187): change the default templates: 'new version submitted' and 'tmp version submitted'
- [#188](https://github.com/CCSDForge/episciences/issues/188): force notifications
- It is now possible to update the document version in Episciences to the most recent version in the open archive.
- [#164](https://github.com/CCSDForge/episciences/issues/164): harmonization of roles/privileges.
- CWI open repository has been temporarily removed until we adapt to their new OAI server

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): capitalization of names
- [#207](https://github.com/CCSDForge/episciences/issues/207): editing the translation of Chief Editors
- [#169](https://github.com/CCSDForge/episciences/issues/169): reports become visible on the article web page:
    - according to the parameters of the journal for published articles
    - for the owner, only if the paper is refused, waiting for revision, already accepted or published
- Another status (Revision request answer: without any modifications) is used when responding to a revision request: without any modifications.
- Revision requests: now we have an immediate visual feedback when changing the deadline.
- If the journal allows revision of articles after acceptance, the response to a request for a final version (without any changes) is similar to the proposal of a new version.

### Internal refactoring

- Code Refactoring fixing of warning
- Update Episciences_User::getScreenName()
- User Table: merge AFFILIATIONS" filed in "ADDITIONAL_PROFILE_INFORMATION"
- Multiple roles: ignore the "member" role when merging two accounts.
- Email Templates updates
- Script to clean the "USER_ROLES" table
- Parameter to detect automatic emails

## 1.0.32.2 - 2022-09-28

### Changed

- [196](https://github.com/CCSDForge/episciences/issues/196): Creation of an account following an invitation with a temporary account: from now on, the Last Name, First Name and Screen Name fields will be left empty (for the reviewer to fill in)
- [RT#164153]: refactoring: now obsolete template "paper_new_version_reviewer_reassign" (Reviewer assignment to a new version of an article) is removed.

### Fixed:

- account merging incomplete due to sql error when updating 'paper_conflicts' table.

## 1.0.32.1 - 2022-09-01

### Fixed

- Fixed bug introduced with [196] "Name" => "Last Name" when creating a new reviewer
- Fixed release notes links to issues

## 1.0.32 - 2022-08-31

### Changed

- [201](https://github.com/CCSDForge/episciences/issues/201): depending on journal settings, editor can accept, ask revision, reject and publish papers in every round.
- [238](https://github.com/CCSDForge/episciences/issues/238): harmonization of terms 'sections/rubriques'.
- [196](https://github.com/CCSDForge/episciences/issues/196) now,
    - Only one 'name' field is available when creating a new reviewer
    - Reviewers are sorted by lastname by default
- Empty TAG %%RECIPIENT_SCREEN_NAME%% for users who do not have a local account in "Unanswered reviewer invitation (reviewer copy)" reminder.
- (Internal/refactoring) transition to PHP 8: refactoring: "MaxMind GeoIP2 PHP API" is now used instead of PHP module.

### Fixed

- [RT#163166]: Problem sending mail from staff page.
- Fixed [#165](https://github.com/CCSDForge/episciences/issues/165): Line breaks in paper titles in API
- Fixed [#251](https://github.com/CCSDForge/episciences/issues/251): conform to ISO 3297 for displaying ISSNs

## Added

- Episciences portal: New Feed RSS + Atom : latest published documents, 2 docs per journal
- Added 'journals news' RSS feed

## 1.0.31.1 - 2022-07-13

### Changed

- User accounts merging procedure: take into account the Conflict Of Interests

## 1.0.31 - 2022-06-28

### Added

- A pictogram is added to indicate papers with conflicts in the article management table.

### Changed

- [192](https://github.com/CCSDForge/episciences/issues/192): allow editors to "Ask for other editors opinion".
- Only confirmed conflicts will be displayed in the conflict management section

### Fixed

- Ask revision: possibility to backdate the deadline.
- Fixed: RT #160301:
  the tags [%%SENDER_FULL_NAME%%, %%SENDER_SCREEN_NAME%%, %%SENDER_EMAIL%%, %%SENDER_FIRST_NAME%%', %%SENDER_LAST_NAME%% ]
  concerning the user of the action are filled with the data of the user connected at the time of the action.
  Making these variables available in the automatic mails poses a real problem: they are filled with the data of the mail recipient.
  So, from now on, the tags mentioned above will no longer be available in the automatic mail templates.
- Shifted display because of the error message (CSRF token)

## 1.0.30 - 2022-06-20

### Fixed

- Titles do not appear in the correct language when more than one language has been entered (e.g. SLOVO )

### Added

- It is now possible to manage conflicts on the article page: display & delete them

## 1.0.29.1 - 2022-06-13

### Changed

- Revision requests: now we have an immediate visual feedback when changing the deadline

### Fixed

- [#247](https://github.com/CCSDForge/episciences/issues/247):
  HTML links in outgoing emails are made relative [#247]: TinyMCE configuration: convert all relative URLs to absolute URLs.
- Edition of a volume: The title of article might not be retrieved if the language was not managed by the platform.
- Profile editing form does not display correctly when changing language.

## 1.0.29 - 2022-06-01

### Fixed

- Failed 'Not enough reviewers - editor copy' reminders.
- But leading to load journal translations from '/'.
- PHP Warning: Use of undefined constant RVID - assumed 'RVID' (this will throw an Error in a future version of PHP)
- Not enough review reminders: articles without invitations escape reminders because the function that retrieves invitations is not used properly.

### Added

- User list: make it easier to locate accounts that have not been activated.
- Addes Roadmap link + User survey/feedback on portal
- Make EU grants and OpenAIRE support more visible at the bottom of the page

## 1.0.28.5 - 2022-05-25

### Fixed

- [RT#158293]: article status is not correctly updated

## 1.0.28.4 - 2022-05-09

### Fixed

- File not found for Temporary Versions without attached files + refactoring.

## 1.0.28.3 - 2022-04-13

### Changed

- Updated error message when using a HAL identifier without any file.
- [#142](https://github.com/CCSDForge/episciences/issues/142)

## 1.0.28.2 - 2022-04-06

### Fixed

- Empty records from HAL repository: error message improved.
- Improvements and fixes on Datacite and Crossref XML exports

## 1.0.28.1 - 2022-03-23

### Fixed

- fix issue in crossref xml format with related_item

## 1.0.28 - 2022-03-23

### Changed

- Bump crossref schema to ver 4.8.1
- arXiv URLs now links to https version instead of http

### Added

- Crossref metadata export format for DOI:
    - add previous version URLs, link with relationship 'hasPreprint' to link preprints versions with the published version
    - add arXiv DOIs, link to journal's published DOI with 'isSameAs' relationship
    - add full text PDF URLs for Crossref 'Similarity Check' service

## 1.0.27.5 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format
- Adding Polyfill PHP 8.0

## 1.0.27.4 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format

## 1.0.27.3 - 2022-03-18

### Fixed

- Ratings submitted late after the start of the layout process caused the status of the article to be updated (rolled back).
- There is no need to review an article while it is being formatted by the author.

## 1.0.27.2 - 2022-03-18

### Fixed

- "Copy editor" role not authorized accessing to the public page of the paper
  and report a conflict of interest, if the option has been activated by the journal

## 1.0.27.1 - 2022-03-16

### Fixed

- Exclude the "paper-status" directory from the journal's resources.
- Content that is copied and pasted when reviewing an article may lose end of line.
- The linked data to an article is not deleted when the author deletes his own article

## 1.0.27 - 2022-03-14

# Changed

- Reviewer invitation: Users are now sorted by Name, First Name instead of First Name

# Fixed

- Dashboard: the number of papers assigned to the copy editor includes obsolete papers
- Issue reported to support RT #148133 (fixed before this release):
    - Some editors could see the reviewers names if they were also authors of the article

## 1.0.26 - 2022-03-01

# Fixed

- Issue reported to support RT #148133:
    - Incorrect "Reminder Delay: The "Reminder Delay" should correspond, in number of days, to the difference between the date the reminder was sent and the date the invitation was sent.
- Issue reported to support RT #148466:
    - Sorting by date in the Dashboard is wrong: Enabling the conflict of interest (COI) option distorts the pagination

## 1.0.25.1 - 2022-02-15

# Changed

[#152](https://github.com/CCSDForge/episciences/issues/152): page footer modifications.

## 1.0.25 - 2022-02-15

# Fixed

- All submissions are accessible to "guest editor" roles.
- Language bar missing when editing a mail template, including reminders.

## 1.0.24.2 - 2022-02-10

# Fixed

- [RT#146987] "search" button no longer works
- application error: syntax error or access violation if CAS user not found

## 1.0.24.1 - 2022-02-08

# Fixed

- A bug in the reminders code triggered emails with unusable HTTP links

## 1.0.24 - 2022-02-08

# Fixed

- Inability to delete a volume (RT#145178): reset the volume of the previous version when submitting a new version.
- [#117](https://github.com/CCSDForge/episciences/issues/117) Improvements in emailing
- Undetected inactive accounts: Invite a reviewer > new reviewer > Invite a new user.
- Not allowing the author to be invited: Invite a reviewer > new reviewer > Invite a new user.
- The attachments to the rating report are not available [RT#145473]
- Answer revision request: in some situations, the "Submit" button remains inactive.

## 1.0.23 - 2022-01-19

### Added

- [#116](https://github.com/CCSDForge/episciences/issues/116) Release version is displayed on the interface (dashboard and page footer)
- Editors' choice at time of submission: informative text added. (gitlab #369)
- [#143](https://github.com/CCSDForge/episciences/issues/143) New DOI setting: Allow switching off manual or automatic DOI assignation (disabled mode)
- [#37](https://github.com/CCSDForge/episciences/issues/37)
    - Browse by Volume or Sections : Handle new content type 'application/json' to return json instead of html
    - On URLs like /volume/view/id/314159 and /section/view/id/314159 Handle new content type 'application/json' to return json instead of html (only published content)
    - On URLs like /volume/edit?id=314159 Handle new content type 'application/json' to return json instead of html but including all statuses of articles ; an authenticated and allowed user is required
    - JSON added to the list of public export formats
- Enhanced information message for statistics

### Changed

- UI/UX: Make the version number more explicit when proposing a new version
- Code Refactoring fixing of warning
- Dumping default data for table `MAIL_TEMPLATE`

### Fixed

- [#126](https://github.com/CCSDForge/episciences/issues/126) Clicking outside the window to compose an email closes the window with no confirmation #126
- [#117](https://github.com/CCSDForge/episciences/issues/117) Mailing bug: loss of the mail when you forget to put a recipient (gitlab #343)
- [#149](https://github.com/CCSDForge/episciences/issues/149) Emails and default language selection: language of the sender and the recipient could be a problem if the language of the sender is different from the language of the recipient (gitlab #402)
- Inconsistent REMINDER_DELAY: the displayed value is the "delay" parameter of the reminder instead of the calculated value (interval in number of days between the time the reminder was sent and the deadline).
- New submission: if the "submission date" metadata is empty: do not display it anymore.
- [#124](https://github.com/CCSDForge/episciences/issues/124): refining automatic notifications when abandoning submission
- RT#144252: better cleaning of attached file names
- RT#144246: an inactive account could still be visible on the Editorial Staff members page.
- [#117](https://github.com/CCSDForge/episciences/issues/117): An empty e-mail is not sent or received
- [#129](https://github.com/CCSDForge/episciences/issues/129): when you do a search from the mail history page nothing happens.
- [#141](https://github.com/CCSDForge/episciences/issues/141): visibility of pages menu is wrong inside menu editing.

### Changed

- [#126](https://github.com/CCSDForge/episciences/issues/126): prevent modal closure: disabling the click outside the modal area and by pressing Esc
- Enabling the "COI" option overrides the "Encapsulate editors" option

## 1.0.37 - 2022-11-10

### Added

- New feature: [Open Science Lens](https://www.opensciencelens.eu/) feature available as a preview on selected journals

### Changed

- Improved rendering of volumes

## 1.0.36 - 2022-11-09

### Added

- Improved rendering of the volume page
- authors can now enter their paper password provided by the open archive on the article page.
- alt attribute to volume's image.
- Added an API method to get the number of users accounts

### Changed

- Optimization: there is no need to check for conflicts at the time of submission
- [#280](https://github.com/CCSDForge/episciences/issues/280): set spellcheck to false in dynamic datatable search boxes.
- [#281](https://github.com/CCSDForge/episciences/issues/281): Submit an article > Guidelines section: harmonization of journal/review terms [FR]
- Now, when an article is accepted, all unanswered invitations are deleted. The reviewer is informed of this action.
- Add 'ISSN pending' until the ISSN is actually issued

### Fixed

- Fix incorrect HTML in footer
- Incorrect paper status LMCS #10145 (related to https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#10273---2022-03-18)
- Empty %%REQUESTER_EXPRESSION%% tag (related to [#187](https://github.com/CCSDForge/episciences/issues/187)
- Incorrect status jdmdh#10203 (related with git#372)
- Fixed [#RT169943]: the tag %%SENDER_SCREEN_NAME%% (obsolete) that should have contained the editor's screen name has not been replaced:
  to be replaced in custom templates by %%EDITOR_FULL_NAME%% or %%EDITOR_SCREEN_NAME%%
  (@see https://github.com/CCSDForge/episciences/blob/main/CHANGELOG.md#fixed-11 [RT##160301])

## 1.0.35 - 2022-10-19

### Added

- [RT#169107]: Feature - new option: do not allow the selection of an editor-in-chief when the author has the option to
  propose an editor at the time of submission.

### Changed

- [#255](https://github.com/CCSDForge/episciences/issues/255) Display ratings reports on the article page: Label updated

### Fixed

- [RT#169088]: Editors-in-chief and editorial secretaries are no longer notified of new submissions.

## 1.0.34.2 - 2022-10-18

### Fixed

- temporary fix: TinyMCE: loss of formatting for successive mailings
- Insert data in 'T_PAPER_DATASETS' table: MySql insert 0 instead of NULL
- [#273](https://github.com/CCSDForge/episciences/issues/273): if the editor changes the rating due date, then this
  is not reflected in the message.
- Fixes in exports format related to trailing ',' and language codes

## 1.0.34.1 - 2022-10-16

### Fixed

- Fixed bad JS escape of authors in reviewer invitation form
- Fixed Handle URLs links adding https://hdl.handle.net/
- Fixed display of disabled buttons
- Fixed 'Description of affiliation block'

### Added

- The activation of maintenance alerts is now configurable

## 1.0.34 - 2022-10-13

### Added

- Article managers (authors, editors, etc.) may add new optional - but recommended - metadata to the documents
    - [ORCID](https://orcid.org/)
    - Affiliations: for affiliations the [ROR](https://ror.org/) or a free text may be used. If an institution is not available in the ROR, a
      simple text entry may be used.
- Automatic enrichment of metadata:
    - Licenses: retrieved from [Datacite](https://datacite.org/), [OpenAIRE Research Graph](https://graph.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/)
    - ORCID: retrieved from [OpenAIRE Research Graph](https://graph.openaire.eu/)
    - Linked Datasets: [Scholexplorer](https://scholexplorer.openaire.eu/), [HAL](https://hal.archives-ouvertes.fr/): Datasets linked to Episciences
      publications
    - Funding: Research projects from [OpenAIRE Research Graph](https://graph.openaire.eu/) and [HAL](https://hal.archives-ouvertes.fr/)
    - Citations: Citations of published document retrieved with [OpenCitations](https://opencitations.net/) APIs
- Exports formats:
    - [DOAJ](https://doaj.org/about/) (export one document or a whole published volume) using DOAJ Schema
    - The new collected metadata have been added to Datacite, [Crossref](https://www.crossref.org/) and DOAJ export formats
- Browse by volume: new parameter to allow to display empty volumes. Default value is still no (only show volumes
  with papers published in the volume)
- [#186](https://github.com/CCSDForge/episciences/issues/186): Editors-in-chief can now report a conflict of
  interest. (Sign in an admin as another user: the real identity is now saved.)

### Fixed

- Fixed a display bug on the portal in connected mode (used by Episciences staff only)
- Failed to edit another account's profile from the user management page.
- impossibility to validate the profile if the user does not have an account in the application

### Changed

- new parameter for enabling/disabling submission from the archive to the application and vice versa.

## 1.0.33.2 - 2022-10-05

### Fixed

- RT#167820: all occurrences of tags in a template will be replaced by their real values.

## 1.0.33.1 - 2022-09-29

### Fixed

- Allow editors to view the list of conflicts on the article management page
- The number of conflicts indicated on the article management page is incorrect if the article has several versions

## 1.0.33 - 2022-09-28

### Added

- New page listing all email templates and available tags for each journal at /administratemail/tagslist
  (- Mail -> TagList)
- Episciences portal:
    - New Feed RSS + Atom : latest published documents, 2 docs per journal
    - API to get a list of publishing journals: include accepted repositories.
- Episciences journals
    - Added 'journals news' RSS feed for each journal
- New journal settings parameter: Allow post-acceptance revisions of articles
- 9 article statuses, available for journals allowing 'post-acceptance revisions of articles':
    - Accepted temporary version, waiting for author's final version: now, temporary versions that are accepted have this new status instead of "pending minor revision" (git #372)
    - Accepted - waiting for author's final version
    - Accepted, waiting for major revision
    - Accepted article - final version submitted, waiting for formatting by copy editors
    - Accepted temporary version after author's modifications
    - Accepted temporary version, waiting for minor revision
    - Accepted temporary version, waiting for major revision
    - Accepted - waiting validation by the author
    - Approved by author, waiting for final publication
- User profiles:
    - Adding ORCID and affiliation to your profile is now possible + this information is diplayed on the
      "editorial staff member" (gitlab #410) & dashboard pages.
    - Social Medias & Websites profile informations.
- New 'Author' role automatically added to users that have submitted a document
- Paper list: temporary versions now have a specific label
- Dashboard: Alert for administrators on the existence of papers without assigned editors
- Display the origin of a linked data when papers are linked to a dataset
- Administration page of a paper: new shortcut for administrators :
    - to allow to sign in as a copy-editor
    - to allow to sign in as an editor
- [227](https://github.com/CCSDForge/episciences/issues/227):
    - The revision deadline is now displayed below the article's status.
    - A pictogram is added to indicate the date of the revision deadline in the article management table.

### Changed

- [#237](https://github.com/CCSDForge/episciences/issues/237) Editor comments div is now bigger
- Upgraded publication RSS Feeds with DOIs
- [#142](https://github.com/CCSDForge/episciences/issues/142): allow that administrators answer revision and copy editing requests
- It is now possible to update the metadata on the document's page
- Request a new version of an article that has already been accepted
- [#166](https://github.com/CCSDForge/episciences/issues/116): hide "guest" role and doi-settings action
- The "paper status" filter is now dynamically created.
- Prevent "Add sources files" and "Add the formatted version" buttons JS reactivation
- Moved "copy editing" section to a more usable place on the same page
- Dashboard improvements: ability to reach an article from sections: my submissions and assigned articles
- [#187](https://github.com/CCSDForge/episciences/issues/187): change the default templates: 'new version submitted' and 'tmp version submitted'
- [#188](https://github.com/CCSDForge/episciences/issues/188): force notifications
- It is now possible to update the document version in Episciences to the most recent version in the open archive.
- [#164](https://github.com/CCSDForge/episciences/issues/164): harmonization of roles/privileges.
- CWI open repository has been temporarily removed until we adapt to their new OAI server

### Fixed

- [#212](https://github.com/CCSDForge/episciences/issues/212): capitalization of names
- [#207](https://github.com/CCSDForge/episciences/issues/207): editing the translation of Chief Editors
- [#169](https://github.com/CCSDForge/episciences/issues/169): reports become visible on the article web page:
    - according to the parameters of the journal for published articles
    - for the owner, only if the paper is refused, waiting for revision, already accepted or published
- Another status (Revision request answer: without any modifications) is used when responding to a revision request: without any modifications.
- Revision requests: now we have an immediate visual feedback when changing the deadline.
- If the journal allows revision of articles after acceptance, the response to a request for a final version (without any changes) is similar to the proposal of a new version.

### Internal refactoring

- Code Refactoring fixing of warning
- Update Episciences_User::getScreenName()
- User Table: merge AFFILIATIONS" filed in "ADDITIONAL_PROFILE_INFORMATION"
- Multiple roles: ignore the "member" role when merging two accounts.
- Email Templates updates
- Script to clean the "USER_ROLES" table
- Parameter to detect automatic emails

## 1.0.32.2 - 2022-09-28

### Changed

- [196](https://github.com/CCSDForge/episciences/issues/196): Creation of an account following an invitation with a temporary account: from now on, the Last Name, First Name and Screen Name fields will be left empty (for the reviewer to fill in)
- [RT#164153]: refactoring: now obsolete template "paper_new_version_reviewer_reassign" (Reviewer assignment to a new version of an article) is removed.

### Fixed:

- account merging incomplete due to sql error when updating 'paper_conflicts' table.

## 1.0.32.1 - 2022-09-01

### Fixed

- Fixed bug introduced with [196] "Name" => "Last Name" when creating a new reviewer
- Fixed release notes links to issues

## 1.0.32 - 2022-08-31

### Changed

- [201](https://github.com/CCSDForge/episciences/issues/201): depending on journal settings, editor can accept, ask revision, reject and publish papers in every round.
- [238](https://github.com/CCSDForge/episciences/issues/238): harmonization of terms 'sections/rubriques'.
- [196](https://github.com/CCSDForge/episciences/issues/196) now,
    - Only one 'name' field is available when creating a new reviewer
    - Reviewers are sorted by lastname by default
- Empty TAG %%RECIPIENT_SCREEN_NAME%% for users who do not have a local account in "Unanswered reviewer invitation (reviewer copy)" reminder.
- (Internal/refactoring) transition to PHP 8: refactoring: "MaxMind GeoIP2 PHP API" is now used instead of PHP module.

### Fixed

- [RT#163166]: Problem sending mail from staff page.
- Fixed [#165](https://github.com/CCSDForge/episciences/issues/165): Line breaks in paper titles in API
- Fixed [#251](https://github.com/CCSDForge/episciences/issues/251): conform to ISO 3297 for displaying ISSNs

## Added

- Episciences portal: New Feed RSS + Atom : latest published documents, 2 docs per journal
- Added 'journals news' RSS feed

## 1.0.31.1 - 2022-07-13

### Changed

- User accounts merging procedure: take into account the Conflict Of Interests

## 1.0.31 - 2022-06-28

### Added

- A pictogram is added to indicate papers with conflicts in the article management table.

### Changed

- [192](https://github.com/CCSDForge/episciences/issues/192): allow editors to "Ask for other editors opinion".
- Only confirmed conflicts will be displayed in the conflict management section

### Fixed

- Ask revision: possibility to backdate the deadline.
- Fixed: RT #160301:
  the tags [%%SENDER_FULL_NAME%%, %%SENDER_SCREEN_NAME%%, %%SENDER_EMAIL%%, %%SENDER_FIRST_NAME%%', %%SENDER_LAST_NAME%% ]
  concerning the user of the action are filled with the data of the user connected at the time of the action.
  Making these variables available in the automatic mails poses a real problem: they are filled with the data of the mail recipient.
  So, from now on, the tags mentioned above will no longer be available in the automatic mail templates.
- Shifted display because of the error message (CSRF token)

## 1.0.30 - 2022-06-20

### Fixed

- Titles do not appear in the correct language when more than one language has been entered (e.g. SLOVO )

### Added

- It is now possible to manage conflicts on the article page: display & delete them

## 1.0.29.1 - 2022-06-13

### Changed

- Revision requests: now we have an immediate visual feedback when changing the deadline

### Fixed

- [#247](https://github.com/CCSDForge/episciences/issues/247):
  HTML links in outgoing emails are made relative [#247]: TinyMCE configuration: convert all relative URLs to absolute URLs.
- Edition of a volume: The title of article might not be retrieved if the language was not managed by the platform.
- Profile editing form does not display correctly when changing language.

## 1.0.29 - 2022-06-01

### Fixed

- Failed 'Not enough reviewers - editor copy' reminders.
- But leading to load journal translations from '/'.
- PHP Warning: Use of undefined constant RVID - assumed 'RVID' (this will throw an Error in a future version of PHP)
- Not enough review reminders: articles without invitations escape reminders because the function that retrieves invitations is not used properly.

### Added

- User list: make it easier to locate accounts that have not been activated.
- Addes Roadmap link + User survey/feedback on portal
- Make EU grants and OpenAIRE support more visible at the bottom of the page

## 1.0.28.5 - 2022-05-25

### Fixed

- [RT#158293]: article status is not correctly updated

## 1.0.28.4 - 2022-05-09

### Fixed

- File not found for Temporary Versions without attached files + refactoring.

## 1.0.28.3 - 2022-04-13

### Changed

- Updated error message when using a HAL identifier without any file.
- [#142](https://github.com/CCSDForge/episciences/issues/142)

## 1.0.28.2 - 2022-04-06

### Fixed

- Empty records from HAL repository: error message improved.
- Improvements and fixes on Datacite and Crossref XML exports

## 1.0.28.1 - 2022-03-23

### Fixed

- fix issue in crossref xml format with related_item

## 1.0.28 - 2022-03-23

### Changed

- Bump crossref schema to ver 4.8.1
- arXiv URLs now links to https version instead of http

### Added

- Crossref metadata export format for DOI:
    - add previous version URLs, link with relationship 'hasPreprint' to link preprints versions with the published version
    - add arXiv DOIs, link to journal's published DOI with 'isSameAs' relationship
    - add full text PDF URLs for Crossref 'Similarity Check' service

## 1.0.27.5 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format
- Adding Polyfill PHP 8.0

## 1.0.27.4 - 2022-03-21

### Fixed

- Fixes and enhancements for zbMATH Open format

## 1.0.27.3 - 2022-03-18

### Fixed

- Ratings submitted late after the start of the layout process caused the status of the article to be updated (rolled back).
- There is no need to review an article while it is being formatted by the author.

## 1.0.27.2 - 2022-03-18

### Fixed

- "Copy editor" role not authorized accessing to the public page of the paper
  and report a conflict of interest, if the option has been activated by the journal

## 1.0.27.1 - 2022-03-16

### Fixed

- Exclude the "paper-status" directory from the journal's resources.
- Content that is copied and pasted when reviewing an article may lose end of line.
- The linked data to an article is not deleted when the author deletes his own article

## 1.0.27 - 2022-03-14

# Changed

- Reviewer invitation: Users are now sorted by Name, First Name instead of First Name

# Fixed

- Dashboard: the number of papers assigned to the copy editor includes obsolete papers
- Issue reported to support RT #148133 (fixed before this release):
    - Some editors could see the reviewers names if they were also authors of the article

## 1.0.26 - 2022-03-01

# Fixed

- Issue reported to support RT #148133:
    - Incorrect "Reminder Delay: The "Reminder Delay" should correspond, in number of days, to the difference between the date the reminder was sent and the date the invitation was sent.
- Issue reported to support RT #148466:
    - Sorting by date in the Dashboard is wrong: Enabling the conflict of interest (COI) option distorts the pagination

## 1.0.25.1 - 2022-02-15

# Changed

[#152](https://github.com/CCSDForge/episciences/issues/152): page footer modifications.

## 1.0.25 - 2022-02-15

# Fixed

- All submissions are accessible to "guest editor" roles.
- Language bar missing when editing a mail template, including reminders.

## 1.0.24.2 - 2022-02-10

# Fixed

- [RT#146987] "search" button no longer works
- application error: syntax error or access violation if CAS user not found

## 1.0.24.1 - 2022-02-08

# Fixed

- A bug in the reminders code triggered emails with unusable HTTP links

## 1.0.24 - 2022-02-08

# Fixed

- Inability to delete a volume (RT#145178): reset the volume of the previous version when submitting a new version.
- [#117](https://github.com/CCSDForge/episciences/issues/117) Improvements in emailing
- Undetected inactive accounts: Invite a reviewer > new reviewer > Invite a new user.
- Not allowing the author to be invited: Invite a reviewer > new reviewer > Invite a new user.
- The attachments to the rating report are not available [RT#145473]
- Answer revision request: in some situations, the "Submit" button remains inactive.

## 1.0.23 - 2022-01-19

### Added

- [#116](https://github.com/CCSDForge/episciences/issues/116) Release version is displayed on the interface (dashboard and page footer)
- Editors' choice at time of submission: informative text added. (gitlab #369)
- [#143](https://github.com/CCSDForge/episciences/issues/143) New DOI setting: Allow switching off manual or automatic DOI assignation (disabled mode)
- [#37](https://github.com/CCSDForge/episciences/issues/37)
    - Browse by Volume or Sections : Handle new content type 'application/json' to return json instead of html
    - On URLs like /volume/view/id/314159 and /section/view/id/314159 Handle new content type 'application/json' to return json instead of html (only published content)
    - On URLs like /volume/edit?id=314159 Handle new content type 'application/json' to return json instead of html but including all statuses of articles ; an authenticated and allowed user is required
    - JSON added to the list of public export formats
- Enhanced information message for statistics

### Changed

- UI/UX: Make the version number more explicit when proposing a new version
- Code Refactoring fixing of warning
- Dumping default data for table `MAIL_TEMPLATE`

### Fixed

- [#126](https://github.com/CCSDForge/episciences/issues/126) Clicking outside the window to compose an email closes the window with no confirmation #126
- [#117](https://github.com/CCSDForge/episciences/issues/117) Mailing bug: loss of the mail when you forget to put a recipient (gitlab #343)
- [#149](https://github.com/CCSDForge/episciences/issues/149) Emails and default language selection: language of the sender and the recipient could be a problem if the language of the sender is different from the language of the recipient (gitlab #402)
- Inconsistent REMINDER_DELAY: the displayed value is the "delay" parameter of the reminder instead of the calculated value (interval in number of days between the time the reminder was sent and the deadline).
- New submission: if the "submission date" metadata is empty: do not display it anymore.
- [#124](https://github.com/CCSDForge/episciences/issues/124): refining automatic notifications when abandoning submission
- RT#144252: better cleaning of attached file names
- RT#144246: an inactive account could still be visible on the Editorial Staff members page.
- [#117](https://github.com/CCSDForge/episciences/issues/117): An empty e-mail is not sent or received
- [#129](https://github.com/CCSDForge/episciences/issues/129): when you do a search from the mail history page nothing happens.
- [#141](https://github.com/CCSDForge/episciences/issues/141): visibility of pages menu is wrong inside menu editing.

### Changed

- [#126](https://github.com/CCSDForge/episciences/issues/126): prevent modal closure: disabling the click outside the modal area and by pressing Esc
- Enabling the "COI" option overrides the "Encapsulate editors" option
