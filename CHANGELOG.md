# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

<!-- 
## Unreleased
### Fixed
### Added
### Changed
### Deprecated
### Removed
### Security
-->
### fixed
- Zenodo submissions is out of service following the update of 13/10/2023.

## 1.0.40.18 - 2023-10-19
## Changed
- Removed sorting option from volumes & sections tables, the feature was confusing and conflicting with another one: drag n drop to sort the volumes/sections 

## 1.0.40.17 - 2023-10-18
### Fixed
- Sorting volumes and sections: drag and drop doesn't always work.

## 1.0.40.16 - 2023-10-11
### Fixed
- The order of the papers was corrupted on the page listing the papers in a volume.

## Unreleased
### Added
- Display revision deadlines and make them editable: [#367](https://github.com/CCSDForge/episciences/issues/367) 
- .env file (/!\ /.env.exemple)
- script to import volumes and volume metadata from journals translation files into the database (/!\ @see /src/mysql/alter_volume_and_volume_metadata_tables.sql)
- bioRxiv and medRxiv preprint servers.
- New version crossref export 4.3 -> 5.3
- bibliographical references in export crossref
- affiliation acronym in crossref and TEI export
- possibility to research acronym in affiliation input ROR
- icon to make it easier to identify the user in revision requests section.
- script to import volumes and volume metadata from journals translation files into the database.
- Integration of api and application for the bibliographical references
- [#374](https://github.com/CCSDForge/episciences/issues/374): Importing orcid and affiliations from Zenodo
- Compatibility Dataverse

### Changed
-  repositories config. is now stored in DB. @see /scr/mysql/alter_metadata_sources.sql
- Answer revision request: [#313](https://github.com/CCSDForge/episciences/issues/313):
    * optional file attachment in "Contact without sending a new version" and "answer without any modifications".
- UX: icon to make it easier to identify the user in revision requests section
- Some adjustments: titles and descriptions volume's, titles and content metadata volumes are now stored
  in the T_VOLUMES and T_VOLUME_METADATAS tables respectively.
- Modified (some adjustments): volume - titles and descriptions -, metadata volume's - titles and content - are now
  stored in the T_VOLUMES and T_VOLUME_METADATAS tables respectively.
- [323](https://github.com/CCSDForge/episciences/issues/323) change default label for home and ethical
- Template for DataLinked
### Fixed
- [#353](https://github.com/CCSDForge/episciences/issues/353)
## 1.0.40.15 - 2023-09-15
### Fixed
- Fixed a case where the button to send a response to the reviewer seems to be missing (RT#193137)
- News: translations were not updated when editing news
- [#360](https://github.com/CCSDForge/episciences/issues/360): Improvement of the referees user experience.

## 1.0.40.14 - 2023-08-23
### Fixed
- Automatic reminders: fixed incorrect management of dates
- Fixed translations of Volumes for Journals with only one locale defined as French
 
## 1.0.40.13 - 2023-07-20
### Fixed
- Fixed English translation

## 1.0.40.12 - 2023-07-20
### Fixed
 - Refactoring to prevent error on temporary links

## 1.0.40.11 - 2023-07-11
### Fixed
 - DOI filter not working properly.
 - [#361](https://github.com/CCSDForge/episciences/issues/361): upload Temporary version dialog with no content 

## 1.0.40.10 - 2023-07-05
### Changed
- [351] (https://github.com/CCSDForge/episciences/issues/351): formatted files available on the copy editing section
### Fixed
- Display "revision contact comments" in "Revision requests" section.

## 1.0.40.9 - 2023-06-21
## Fixed
- Fixed internal error: Use of undefined constant REVIEW_PATH

## 1.0.40.8 - 2023-06-20
### Fixed
- Automated repeated reminders failed

## 1.0.40.7 - 2023-06-08
### Fixed
- [133](https://github.com/CCSDForge/episciences/issues/133): wrong acceptance date and docUrl for tmp versions.

### Changed
- [133](https://github.com/CCSDForge/episciences/issues/133) Added html classes and ids, removed useless H2 title

## 1.0.40.6 - 2023-06-07
### Fixed
- [133](https://github.com/CCSDForge/episciences/issues/133): now all accepted items are included

### Changed
- [133](https://github.com/CCSDForge/episciences/issues/133):
 * the modification date previously displayed is replaced by the acceptance date 
 * a link to the article administration page has been added for editorial secretaries

## 1.0.40.5 - 2023-06-01
### Fixed
- Fixed: #352 Augmenter la taille du champ adresse Mastodon / Increase the size of the Mastodon address field

## 1.0.40.4 - 2023-05-31
### Changed
- Administrator are now able to change the address of a user account for support.
- Export of metadata: use relative URLs

### Added
- API: list of publishing journals, added journal 'Code' in result

## 1.0.40.3 - 2023-05-30
### Fixed
- Fixed export URLs

### Changed
- Updates on label and download button size [#342 Feature request: "Consult the article webpage"](https://github.com/CCSDForge/episciences/issues/342)
 
## 1.0.40.2 - 2023-05-30
### Fixed
- Conflict of interest: prevent sending email in CC in case of Conflict 
- Revision requests section: Fixed wrong URL
- Author's suggestions: fixed: the choice of an editor for the article was not rendered

### Changed
- [#342 Feature request: "Consult the article webpage"](https://github.com/CCSDForge/episciences/issues/342)

## 1.0.40.1 - 2023-05-24
### Changed
- Merged Share + Metadata export panels to save space

## 1.0.40 - 2023-05-23
### Changed
- New WYSIWYG editor version (editing toolbar for text areas) ; bumped from TinyMCE v4 to TinyMCE v6
- [278](https://github.com/CCSDForge/episciences/issues/278): In case an article has been refused: new option to allow forward automatically the message sent to the authors explaining the final decision made by the editor in charge.
- Share buttons for published articles now using [sharingbuttons.io](https://sharingbuttons.io/). We no longer rely on an external service for the feature

### Added
- Cancel a DOI assignment for the journal. The feature is available only with manual DOI assignment setting and until the DOI has been requested to Crossref.
- The user profile email update form will detect duplicate accounts and allow you to request merging duplicate accounts
- [283](https://github.com/CCSDForge/episciences/issues/283): new template pages for journal websites (credits, publishing-policies, ethical-charter)
- Automated Metadata Enrichment: for authors via HAL XML-TEI when using the metadata update button (OpenAire and HAL Metadata will automatically update)
- [95](https://github.com/CCSDForge/episciences/issues/95): Twitter and Mastodon support through user profiles. Possibility to share published papers via admin paper page. The feature will automatically mention the @user if they have added their username in their profile
- Journal may use a specific email alias for bounced emails sent by the platform. Using the new email alias requires a request to Episciences support team for setting up the recipients of the alias.

### Added for upcoming features  
- COAR Inbox notifications: new script to initialize submissions, pending implementation on HAL
- It is now possible to use LemonLDAP::NG authentication. Pending the release of the new CCSD LemonLDAP::NG in production.


## 1.0.39.15 - 2023-05-22
- Fixed [RT#186373]: in some situations, an article can be accepted several times. Now, the first date of acceptance will be taken into account.
### Fixed

## 1.0.39.14 - 2023-04-26
- COAR Notify: ietf:cite-as as url for DOI
- OpenAIRE OAI metadata: include mandatory resource license
- [RT#184361]: a person with a conflict of interest may determine the identity of the article's reviewers by 
  trial and error

## 1.0.39.13 - 2023-04-06
### Changed
- related to Allow post - accepting article revisions: "Contact without sending a new version" is now disabled when answer final version request
- now the version of the article can be modified only if its status is :
  * submitted
  * waiting for reviewing
  * accepted
  * published
  * ready to publish
  * approved by author - waiting for final publication
- statistics: the lower limit of years is now set at 2013


## 1.0.39.12 - 2023-03-23
### Fixed
- Paper password registration failure

## 1.0.39.11 - 2023-03-23
### Fixed
- [RT#182289]: reminders are ignored once the document is accepted.
- [RT#182641]: fixed a case where persons who have declared a conflict of interest are copied in the "Completed rating"
  e-mail sent to the editors of the article.

## 1.0.39.10 - 2023-03-15
### Fixed
- Prevent repeated submission of Editor suggestions form : Force post method and add CSRF

## 1.0.39.9 - 2023-03-15
### Fixed
- Prevent repeated submission of comment form
- Fix ignored form name parameter in comment form

### Added
- UI/UX add icon to make it easier to identify the user adding a comment

## 1.0.39.8 - 2023-03-09
### Added
- Prevent multiple submissions of the same form
### Fixed
- Prevent injection when refusing a reviewer invitation with comments and in email history

## 1.0.39.7 - 2023-03-06
### Added
- Support for autoincrement with DOI Patterns

## 1.0.39.6 - 2023-03-01
### Fixed
- [#325](https://github.com/CCSDForge/episciences/issues/325): the system allows accepting an invitation after it has been canceled
### Changed
- [#324](https://github.com/CCSDForge/episciences/issues/324): Updates to journal settings translations

## 1.0.39.5 - 2023-02-23
### Added
- [#315](https://github.com/CCSDForge/episciences/issues/315): missing translation
### Changed
- [#316](https://github.com/CCSDForge/episciences/issues/316): it is no longer necessary to validate / create an account before declining the invitation.
### Fixed
- 'Not enough reviewers - editor copy: editors' reminder: editors received the reminders before the deadline set in the reminder template configuration.

## 1.0.39.4 - 2023-02-03
### Fixed
- Fixed errors when creating user accounts

## 1.0.39.3 - 2023-02-02
### Fixed
- %%PERMANENT_ARTICLE_ID%% tag not replaced in the mail subject.
### Added
- [#310](https://github.com/CCSDForge/episciences/issues/310): missing translation

## 1.0.39.2 - 2023-02-01
### Changed
- The visibility of the statistics dashboard is now configurable by journal (three possible options): 
  1) Default (hidden)
  2) Public
  3) Administrator only
- New display group "Additional settings"

### Fixed 
- Editing custom templates: loss of translations (wrong journal's translation path)
- [#296](https://github.com/CCSDForge/episciences/issues/296): keep only two possible choices: 
  1. Contact without sending a new version 
  2. Upload a new version
- [RT #177185]: Data too long for column 'VALUE' of the 'USER_INVITATION_ANSWER_DETAIL' table. The length for the 
  comments when replying to an invitation has been increased to accept long text comments

## 1.0.39.1 - 2023-01-19
### Added
- [#295](https://github.com/CCSDForge/episciences/issues/295): %%PERMANENT_ARTICLE_ID%% tag is now available in all 
email templates. 
- ### Fixed
- [#293](https://github.com/CCSDForge/episciences/issues/293): the system has overwritten the invitation date with 
the date of the latest action
- Fixed footer links to avoid redirects with updated website

## 1.0.39 - 2023-01-11
### Added
- Added three options for sharing the paper password (arXiv):
  * No (default): do not share
  * Optional: possibility to share
  * Required: sharing is required when submitting a new version and responding to a revision request without any changes
- New filter: repositories
- It often happens to change the version number of an article during the publication process of an article,
  this manipulation, can block the publication process: from now on, by checking the box 
  "ready to publish" at the time of the modification of the version number, the status is updated automatically
  thus allowing the publication of the aforementioned version.
- Updated volume import script to handle new metadata
- Crossref metadata: added text-mining URL

### Changed
- the "DOI" filter is now only accessible on the article administration page.
- Dashboard: improved rendering of the "filters" view 
- Code refactoring
- From now on, the change of the reviewing deadline is reflected in the article's history [RT#75351].
### Fixed
- Fixed: allow to submit documents from hal.science and HAL portals with a TLD different from .FR
- [#299](https://github.com/CCSDForge/episciences/issues/299) Fixed licences missing character and version



### New
- Updated volume import script to handle new metadata

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
  * Accepted temporary version, waiting for author's final version: now, temporary versions that are accepted have this new status instead of "pending minor revision" (git #372)
  * Accepted - waiting for author's final version
  * Accepted, waiting for major revision
  * Accepted article - final version submitted, waiting for formatting by copy editors
  * Accepted temporary version after author's modifications
  * Accepted temporary version, waiting for minor revision
  * Accepted temporary version, waiting for major revision
  * Accepted - waiting validation by the author
  * Approved by author, waiting for final publication
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
- [#188](https://github.com/CCSDForge/episciences/issues/188 ): force notifications
- It is now possible to update the document version in Episciences to the most recent version in the open archive.
- [#164](https://github.com/CCSDForge/episciences/issues/164): harmonization of roles/privileges.
- CWI open repository has been temporarily removed until we adapt to their new OAI server 
  
### Fixed
- [#212](https://github.com/CCSDForge/episciences/issues/212): capitalization of names
- [#207](https://github.com/CCSDForge/episciences/issues/207): editing the translation of Chief Editors
- [#169](https://github.com/CCSDForge/episciences/issues/169): reports become visible on the article web page:
  * according to the parameters of the journal for published articles
  * for the owner, only if the paper is refused, waiting for revision, already accepted or published
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
- Empty TAG  %%RECIPIENT_SCREEN_NAME%% for users who do not have a local account in "Unanswered reviewer invitation (reviewer copy)" reminder.
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
- PHP Warning:  Use of undefined constant RVID - assumed 'RVID' (this will throw an Error in a future version of PHP)
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
-  fix issue in crossref xml format with related_item

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

##  1.0.27 - 2022-03-14
# Changed
- Reviewer invitation: Users are now sorted by Name, First Name instead of First Name

# Fixed
- Dashboard: the number of papers assigned to the copy editor includes obsolete papers
- Issue reported to support RT #148133 (fixed before this release):
  * Some editors could see the reviewers names if they were also authors of the article  

## 1.0.26 - 2022-03-01
# Fixed
- Issue reported to support RT #148133:
   * Incorrect "Reminder Delay: The "Reminder Delay" should correspond, in number of days, to the difference between the date the reminder was sent and the date the invitation was sent.
- Issue reported to support RT #148466:
   * Sorting by date in the Dashboard is wrong: Enabling the conflict of interest (COI) option distorts the pagination

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

## 1.0.22.3 - 2021-11-30
### Changed
- New SVG logo + PNG fallback

## 1.0.22.2 - 2021-11-24
### Changed
- Renamed logo file to prevent browser cache ; cleaned commented HTML Code

## 1.0.22.1 - 2021-11-24
### Changed
- New logos and favicons

## 1.0.22 - 2021-11-16
### Added
- Reviewers invitations: identify the account with which the user has been invited.
- [#115](https://github.com/CCSDForge/episciences/issues/115) Set CAS UI language according to journal's language
- [#110](https://github.com/CCSDForge/episciences/issues/110) Add missing translations

### Fixed
- Fixed: "Cancel" and "Submit" buttons when selecting the details of an item in an article's history: [106](https://github.com/CCSDForge/episciences/issues/106): replaced by a single "Close" button
- Fixed:  non-localized text when setting a reviewing deadline which is too large: [112](https://github.com/CCSDForge/episciences/issues/112)
- Reviewers invitations: identify the account with which the user has been invited.
- Fixed: pending invitations are not displayed in the user's dashboard if they are not already a reviewer.
- Fixed: [#107](https://github.com/CCSDForge/episciences/issues/107) Small grammar problem

## 1.0.21 - 2021-11-03
### Added
- Support new file extensions and mimetypes: RAR (with additional mimetype), GZ, DVI, EPS, PS

## 1.0.20.3 - 2021-11-03
### Fixed
- Fixed: incorrect Grammar: [107](https://github.com/CCSDForge/episciences/issues/107)
- Fixed: "support mail alias" more visible on the portal (gitlab #404) + making it configurable.
 
## 1.0.20.2 - 2021-10-28
### Fixed
- Fixed bug: linked Data: conflict between DOI and SWHID: [97](https://github.com/CCSDForge/episciences/issues/97)

## 1.0.20.1 - 2021-10-28
### Fixed
- Fixed bug: automatic designations of editors who answer "no" to the presence of a conflict of interest (gitlab #406)

## 1.0.20 - 2021-10-26
### Fixed
- fixed bug: inability to continue reviewing (RT#138067)
- fixed typo ; add missing translation

## 1.0.19.2 - 2021-10-19
### Fixed
- Fixed bug: possibility to assign the article to the author himself

## 1.0.19.1 - 2021-10-15
### Fixed
- [COI]: fixed bug: author's does not have full access to his/her own submission

### Changed
- [COI] #83: label "Enable/Disable COI" changed to "Enable declaring COI".

## 1.0.19 - 2021-10-14
### Added
- Develop an option to handle conflict of interest (COI) [81](https://github.com/CCSDForge/episciences/issues/81):
- Journal settings: new setting to Enable/disable COI for journal managers: [83](https://github.com/CCSDForge/episciences/issues/83) 
- DB table and manager for CRUD of COI Information: [84](https://github.com/CCSDForge/episciences/issues/84) 
- For editors : Filtering 'Emails' in email history: [85](https://github.com/CCSDForge/episciences/issues/85) 
- For editors : Filtering 'paper status' information on dashboard: [86](https://github.com/CCSDForge/episciences/issues/86) 
- For editors: Filtering 'Reviewer information' on dashboard: [87](https://github.com/CCSDForge/episciences/issues/87) 
- When assigning an editor/Copy editor ; do not propose editors/Copy editor that have reported a COI in the user list: [89](https://github.com/CCSDForge/episciences/issues/89) 
- Design a Form to request user consent about access to private submission information: [88](https://github.com/CCSDForge/episciences/issues/88)
- Notify editors-in-chief/secretaries when an editor assigned to a paper declares a conflict with this paper: [90](https://github.com/CCSDForge/episciences/issues/90)
- Fixed bug: redirection problem with an editor's own submission: [92](https://github.com/CCSDForge/episciences/issues/92)
- Other improvements
- Choose one and only one editor parameter: the editors field is not pre-completed when this choice is imposed by the journal (git #392)
### Fixed
- Editing site headers: fixed bug: drag and drop does not work. 

## 1.0.18 - 2021-09-30
### Added
- Add 'FLAG' attribute to Episciences::Paper class
- Alter table PAPERS: add new field to identify imported articles, to ignore them from journal statistics and report them as 'imported' and not submitted
- New script to update papers table to report imported article
- Linked data: links to related resources are extracted from the open archives and displayed on the article's page
- Version block: indicate the date of submission/import of the different versions of the document
- Addition of the "acceptance rate" indicator [(A/S)x100 with A a number of accepted articles and S a number of submissions] on the statistics page
- Changing the publication date of a paper already published: [50](https://github.com/CCSDForge/episciences/issues/50)

## 1.0.17.1 - 2021-09-28
### Added
- Add new document metadata: "Accepted" date

### Fixed
- Fixed issue: [77](https://github.com/CCSDForge/episciences/issues/77): It is not possible for the reviewer to intervene when the document is in the copy editing process if this one has already been started
- Fixed bug: "obsolete invitations" on the paper management page are not labeled (when a paper is obsolete, reviewers are disabled)
- Update paper stats only if paper is published and user is not the contributor
- Do not display the document meta. 'keywords' if its value is empty
- Report score rounding (RT#135523): propagate the fix (git#389)
- Edit a template via a get request: fix Application error : SQLSTATE[HY093]: Invalid parameter number
- OAI-PMH: fix badResumptionToken value for error message
- Fixed bug in account merge module

## 1.0.16 - 2021-09-13
### Fixed
- Fixed issue: [64](https://github.com/CCSDForge/episciences/issues/64): Layout of mails with reviewers' comments
- Improvement of the process of accounts merging
 
### Changed
- Improvements for displaying when a document has been imported. E.g. for journals coming to the platform with previous content 
- Switch to paperIds as Episciences public PIDs for export formats and OAI-PMH. The switch from the docIds to paperIds was incomplete. One unique paperId is assigned to each version of a document. Each version of a document has a different unique docid.

## 1.0.15.1 - 2021-08-31
### Fixed
- Fixed issue: [62](https://github.com/CCSDForge/episciences/issues/62): String not localized

## 1.0.15 - 2021-08-30

### Added
- New API: JSON Feed of published articles hosted by HAL and their metadata (Call /hal/bibfeed on portal hostname)
- Published documents metadata : Submission Date: Add a different label for imported documents because for these documents submission date may be >= publication date. It only happens with imported documents previously published on another platform.
- New script to import volumes with a CSV file
- OAI-PMH: Adding Datacite metadata format to OAI-PMH repository with metadataPrefix oai_openaire

### Fixed
- Fixed bug: [56](https://github.com/CCSDForge/episciences/issues/56): when entering a comment and saving it, the text also remains in the comment box leaving the impression that the comment did not go through.
- Fixed bug: [48](https://github.com/CCSDForge/episciences/issues/48): moving the article to other volumes, creates an entry in the 'History' + improvements.
- Fixed bug: 'script' tag in TinyMCE is removed
- Fixed bug:  script in charge of updating the consultation statistics was broken
- OAI-PMH: Return a real earliestDatestamp with Identify response
- Export formats:  use Variables instead of Constants for journal URLs

### Removed
- Do not display the search bar on the portal (no content available for search)
- Remove the possibility to create an account on the portal (RT #133571) (no features available for users)

### Changed
- Expire metadata of published articles after 1 month (previously 1 week)

## 1.0.14 - 2021-07-28
### Fixed
- Reviewer report: if the article is under review, access to its already completed report is broken
- [48](https://github.com/CCSDForge/episciences/issues/48): Assigned secondary volume is lost on article update

## 1.0.13 - 2021-07-23
### Fixed
- [46](https://github.com/CCSDForge/episciences/issues/46): Paper order within a volume is broken

### Added
New field in solr schema: paperid

## 1.0.12 - 2021-07-15

### Added
- OAI-PMH endpoint: cache metadata to improve response time
- new translations + fixed typo

#### Rating status
- New "obsolete rating" status for reviews no longer needed. The reviewing on an obsolete version is no longer possible (when a new version has been requested).
- It's not possible to invite reviewers on an obsolete version  
- New flash message when a user tries to review an article under revision (a new version has been requested to the authors)



### Changed
- Users list: invalid accounts are no longer listed

### Fixed
- [38](https://github.com/CCSDForge/episciences/issues/38): article versions may be listed in wrong order for some articles
- Reviewer report: if the article is under review, access to its already completed report is broken

#### Rating grids
- Reviewing grids rating status were sometimes not mentioned in the grid list on article's management page
- Reviewing grids editing: fixed incorrect display of criteria in multilingual reviewing grids

#### Emails
- Fixed an empty tag `%%SENDER_FULL_NAME%%` in "updated rating deadline" template.
