# Change Log - 2023

All notable changes to this project for 2023.

## v1.0.41.4 - 2023-12-20

### Fixed

- [413](https://github.com/CCSDForge/episciences/issues/413): Crossref Export for bibliographical references when the reference has no DOI
- Wrong link to the paper's URL in the email template for paper's managers.
- Unable to submit a new version from Zenodo.

### Changed

- The version number for articles "formatted by the author, awaiting final version" is no longer available: the feature could prevent the author from answering the request for a final version
- [322](https://github.com/CCSDForge/episciences/issues/322) manage closing mail author
- changing right to adding orcid when you're owner of paper (before was only for paper's managers)
- [133](https://github.com/CCSDForge/episciences/issues/133) Add default style for the accepted page

### Added

- New email template: inbox_paper_submission_author_copy: Your submission made via a preprint server - Author copy.
- [#388](https://github.com/CCSDForge/episciences/issues/388): new option to require a deadline to be indicated when requesting a revision.
- [#420](https://github.com/CCSDForge/episciences/issues/420): Add bibliographical references in zbjats export

## v1.0.41.3 - 2023-12-11

### Fixed

- [414](https://github.com/CCSDForge/episciences/issues/414): volume page does not show volume description anymore
- [409](https://github.com/CCSDForge/episciences/issues/409): https://doi.org/ was automatically added to avery PIDs even if they were not DOIs

## 1.0.41.2 - 2023-12-05

### Fixed

- [408](https://github.com/CCSDForge/episciences/issues/408)"Update metadata" button seems to delete existing metadata
- Fixed Misleading error message about a document not found

### Changed

- The bibliographic references panel is now hidden when empty
- Removed cache from OpenCitation API calls when the response is empty

## 1.0.41.1 - 2023-11-28

### Changed

- [394](https://github.com/CCSDForge/episciences/issues/394): Improvements to facilitate submissions.

## 1.0.41 - 2023-11-27

### Added

- New supported servers: [bioRxiv](https://www.biorxiv.org/) and [medRxiv](https://www.medrxiv.org/) preprint servers
- New supported servers: Support for any data repositories powered by Dataverse. 2 examples are available: [DaRUS (University of Stuttgart)](https://darus.uni-stuttgart.de/) and [Recherche Data Gouv](https://recherche.data.gouv.fr/)
- New feature to automatically extract and manage bibliographical references [with a new application](https://github.com/CCSDForge/episciences-citations)
- Reviewed and accepted bibliographical references will be exported to Crossref
- [New API](https://github.com/CCSDForge/episciences-api) available for journals and internal usage

- It is possible to link documents with software, datasets and publications:
  Previously it was only available with documents hosted on HAL, the feature is now available for any repository, by adding the links on the document's page.
  As recommended, if the software is archived by [Software Heritage](https://www.softwareheritage.org/?lang=en) and a [SWHID](https://www.swhid.org/) is provided, a widget from Software Heritage may be displayed on the web page of the document, showing the archived repository
    - Fixed [#213](https://github.com/CCSDForge/episciences/issues/213): Supplementary material DOI cannot be indicated
    - Closed [#326](https://github.com/CCSDForge/episciences/issues/326): Accept SWHID reference on Episciences platform for software artifact
    - Closed [#327](https://github.com/CCSDForge/episciences/issues/327): Accept HAL reference on Episciences platform for software artifact
    - Closed [#328](https://github.com/CCSDForge/episciences/issues/328): Add a reference to the archived software artifact on reviewer's peer-reviewing form
    - Closed [#329](https://github.com/CCSDForge/episciences/issues/329): Add a reference to the archived software artifact on record landing page
    - Closed [#330](https://github.com/CCSDForge/episciences/issues/330): Add a reference to the archived software artifact on exported metadata (OpenAIRE, Crossref, etc.)
- It is now possible to add a cover letter after the initial submission
- Closed [#367](https://github.com/CCSDForge/episciences/issues/367) Display revision deadlines and make them editable:

- Author affiliations:
    - Added the affiliation acronyms in Crossref DOI and TEI export formats
    - Adding an affiliation from ROR: possibility to search by acronym
    - Closed [#374](https://github.com/CCSDForge/episciences/issues/374): ORCID and affiliations are retrieved from Zenodo

- UI/UX:
    - Document types are now displayed on the document's page(ie preprint, article, ...)
    - icon to make it easier to identify the user in revision requests section.

- Statistics, 2 new indicators:
    - The number of accepted articles (submitted in the same year) [A]
    - Acceptance rate over the whole year [(A\S)x100] : [ A, S : the number of submissions over the whole year]
- Internal updates:
    - script to import volumes and volume metadata from journals translation files into the database
    - .env file
    - New version of Crossref Schema 4.3 -> 5.3 used for DOIs

### Changed

- Accept/reject an invitation: This action is now blocked with an alert message "This paper is under revision, it is useless to review it now."

- Answer revision request: Closed [#313](https://github.com/CCSDForge/episciences/issues/313):
    - optional file attachment in "Contact without sending a new version" and "answer without any modifications".
- UX: icon to make it easier to identify the user in revision requests section

- Closed [323](https://github.com/CCSDForge/episciences/issues/323) change default label for home and Ethical charter
- Switched to [OpenAlex](https://openalex.org/) to obtain the text of citations
- Template for datasets
- Internal updates:
    - Repositories config. is now stored in Database.
    - Titles and descriptions volume's, titles and content metadata volumes are now stored as JSON

### Fixed

- Fixed [#353](https://github.com/CCSDForge/episciences/issues/353) Adding a DOI to a proceeding volume does not save the DOI status

## 1.0.40.21 - 2023-11-14

### Changed

- Volumes & Sections list:
    - display all available rows in the table (pagination disabled).
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
    - refactoring : 'yarn encore prod' needed
    - Display the total number of volumes / sections
    - display all available rows in the table (pagination disabled). The pagination feature was conflicting with the drag and drop feature.
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
- Fixed [#139](https://github.com/CCSDForge/episciences/issues/139) Put newly created volumes at the top of the list #139

## 1.0.40.19 - 2023-10-19

### Fixed

- [#392](https://github.com/CCSDForge/episciences/issues/392): Translations updated and fixed
- Fixed a bug updating dates for user profiles (User profile modification date updated automatically /!\ https://github.com/CCSDForge/episciences/blob/main/src/mysql/ALTER_USER_TABLE.sql)
- Fixed a bug with feature "Re-invite this reviewer".
- Fixed a difference between the address displayed in the profile and the one displayed when the address was modified.
- Fixed Zenodo submissions following the [major update of Zenodo on 2023-10-13](https://blog.zenodo.org/2023/10/13/2023-10-13-zenodo-rdm/).
- Fixed arXiv bibfeed

## 1.0.40.18 - 2023-10-19

## Changed

- Removed sorting option from volumes & sections tables, the feature was confusing and conflicting with another one: drag n drop to sort the volumes/sections
- The new section/volume is now inserted in the first position instead of the last.

## 1.0.40.17 - 2023-10-18

### Fixed

- Sorting volumes and sections: drag and drop doesn't always work.

## 1.0.40.16 - 2023-10-11

### Fixed

- The order of the papers was corrupted on the page listing the papers in a volume.

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
    - submitted
    - waiting for reviewing
    - accepted
    - published
    - ready to publish
    - approved by author - waiting for final publication
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
    1. Default (hidden)
    2. Public
    3. Administrator only
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
    - No (default): do not share
    - Optional: possibility to share
    - Required: sharing is required when submitting a new version and responding to a revision request without any changes
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
- Add an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings.

### New

- Updated volume import script to handle new metadata

## v1.0.41.4 - 2023-12-20

### Fixed

- [413](https://github.com/CCSDForge/episciences/issues/413): Crossref Export for bibliographical references when the reference has no DOI
- Wrong link to the paper's URL in the email template for paper's managers.
- Unable to submit a new version from Zenodo.

### Changed

- The version number for articles "formatted by the author, awaiting final version" is no longer available: the feature could prevent the author from answering the request for a final version
- [322](https://github.com/CCSDForge/episciences/issues/322) manage closing mail author
- changing right to adding orcid when you're owner of paper (before was only for paper's managers)
- [133](https://github.com/CCSDForge/episciences/issues/133) Add default style for the accepted page

### Added

- New email template: inbox_paper_submission_author_copy: Your submission made via a preprint server - Author copy.
- [#388](https://github.com/CCSDForge/episciences/issues/388): new option to require a deadline to be indicated when requesting a revision.
- [#420](https://github.com/CCSDForge/episciences/issues/420): Add bibliographical references in zbjats export

## v1.0.41.3 - 2023-12-11

### Fixed

- [414](https://github.com/CCSDForge/episciences/issues/414): volume page does not show volume description anymore
- [409](https://github.com/CCSDForge/episciences/issues/409): https://doi.org/ was automatically added to avery PIDs even if they were not DOIs

## 1.0.41.2 - 2023-12-05

### Fixed

- [408](https://github.com/CCSDForge/episciences/issues/408)"Update metadata" button seems to delete existing metadata
- Fixed Misleading error message about a document not found

### Changed

- The bibliographic references panel is now hidden when empty
- Removed cache from OpenCitation API calls when the response is empty

## 1.0.41.1 - 2023-11-28

### Changed

- [394](https://github.com/CCSDForge/episciences/issues/394): Improvements to facilitate submissions.

## 1.0.41 - 2023-11-27

### Added

- New supported servers: [bioRxiv](https://www.biorxiv.org/) and [medRxiv](https://www.medrxiv.org/) preprint servers
- New supported servers: Support for any data repositories powered by Dataverse. 2 examples are available: [DaRUS (University of Stuttgart)](https://darus.uni-stuttgart.de/) and [Recherche Data Gouv](https://recherche.data.gouv.fr/)
- New feature to automatically extract and manage bibliographical references [with a new application](https://github.com/CCSDForge/episciences-citations)
- Reviewed and accepted bibliographical references will be exported to Crossref
- [New API](https://github.com/CCSDForge/episciences-api) available for journals and internal usage

- It is possible to link documents with software, datasets and publications:
  Previously it was only available with documents hosted on HAL, the feature is now available for any repository, by adding the links on the document's page.
  As recommended, if the software is archived by [Software Heritage](https://www.softwareheritage.org/?lang=en) and a [SWHID](https://www.swhid.org/) is provided, a widget from Software Heritage may be displayed on the web page of the document, showing the archived repository
    - Fixed [#213](https://github.com/CCSDForge/episciences/issues/213): Supplementary material DOI cannot be indicated
    - Closed [#326](https://github.com/CCSDForge/episciences/issues/326): Accept SWHID reference on Episciences platform for software artifact
    - Closed [#327](https://github.com/CCSDForge/episciences/issues/327): Accept HAL reference on Episciences platform for software artifact
    - Closed [#328](https://github.com/CCSDForge/episciences/issues/328): Add a reference to the archived software artifact on reviewer's peer-reviewing form
    - Closed [#329](https://github.com/CCSDForge/episciences/issues/329): Add a reference to the archived software artifact on record landing page
    - Closed [#330](https://github.com/CCSDForge/episciences/issues/330): Add a reference to the archived software artifact on exported metadata (OpenAIRE, Crossref, etc.)
- It is now possible to add a cover letter after the initial submission
- Closed [#367](https://github.com/CCSDForge/episciences/issues/367) Display revision deadlines and make them editable:

- Author affiliations:
    - Added the affiliation acronyms in Crossref DOI and TEI export formats
    - Adding an affiliation from ROR: possibility to search by acronym
    - Closed [#374](https://github.com/CCSDForge/episciences/issues/374): ORCID and affiliations are retrieved from Zenodo

- UI/UX:
    - Document types are now displayed on the document's page(ie preprint, article, ...)
    - icon to make it easier to identify the user in revision requests section.

- Statistics, 2 new indicators:
    - The number of accepted articles (submitted in the same year) [A]
    - Acceptance rate over the whole year [(A\S)x100] : [ A, S : the number of submissions over the whole year]
- Internal updates:
    - script to import volumes and volume metadata from journals translation files into the database
    - .env file
    - New version of Crossref Schema 4.3 -> 5.3 used for DOIs

### Changed

- Accept/reject an invitation: This action is now blocked with an alert message "This paper is under revision, it is useless to review it now."

- Answer revision request: Closed [#313](https://github.com/CCSDForge/episciences/issues/313):
    - optional file attachment in "Contact without sending a new version" and "answer without any modifications".
- UX: icon to make it easier to identify the user in revision requests section

- Closed [323](https://github.com/CCSDForge/episciences/issues/323) change default label for home and Ethical charter
- Switched to [OpenAlex](https://openalex.org/) to obtain the text of citations
- Template for datasets
- Internal updates:
    - Repositories config. is now stored in Database.
    - Titles and descriptions volume's, titles and content metadata volumes are now stored as JSON

### Fixed

- Fixed [#353](https://github.com/CCSDForge/episciences/issues/353) Adding a DOI to a proceeding volume does not save the DOI status

## 1.0.40.21 - 2023-11-14

### Changed

- Volumes & Sections list:
    - display all available rows in the table (pagination disabled).
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
    - refactoring : 'yarn encore prod' needed
    - Display the total number of volumes / sections
    - display all available rows in the table (pagination disabled). The pagination feature was conflicting with the drag and drop feature.
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
- Fixed [#139](https://github.com/CCSDForge/episciences/issues/139) Put newly created volumes at the top of the list #139

## 1.0.40.19 - 2023-10-19

### Fixed

- [#392](https://github.com/CCSDForge/episciences/issues/392): Translations updated and fixed
- Fixed a bug updating dates for user profiles (User profile modification date updated automatically /!\ https://github.com/CCSDForge/episciences/blob/main/src/mysql/ALTER_USER_TABLE.sql)
- Fixed a bug with feature "Re-invite this reviewer".
- Fixed a difference between the address displayed in the profile and the one displayed when the address was modified.
- Fixed Zenodo submissions following the [major update of Zenodo on 2023-10-13](https://blog.zenodo.org/2023/10/13/2023-10-13-zenodo-rdm/).
- Fixed arXiv bibfeed

## 1.0.40.18 - 2023-10-19

## Changed

- Removed sorting option from volumes & sections tables, the feature was confusing and conflicting with another one: drag n drop to sort the volumes/sections
- The new section/volume is now inserted in the first position instead of the last.

## 1.0.40.17 - 2023-10-18

### Fixed

- Sorting volumes and sections: drag and drop doesn't always work.

## 1.0.40.16 - 2023-10-11

### Fixed

- The order of the papers was corrupted on the page listing the papers in a volume.

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
- Revision requests sectio# Change Log

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

## v1.0.41.4 - 2023-12-20

### Fixed

- [413](https://github.com/CCSDForge/episciences/issues/413): Crossref Export for bibliographical references when the reference has no DOI
- Wrong link to the paper's URL in the email template for paper's managers.
- Unable to submit a new version from Zenodo.

### Changed

- The version number for articles "formatted by the author, awaiting final version" is no longer available: the feature could prevent the author from answering the request for a final version
- [322](https://github.com/CCSDForge/episciences/issues/322) manage closing mail author
- changing right to adding orcid when you're owner of paper (before was only for paper's managers)
- [133](https://github.com/CCSDForge/episciences/issues/133) Add default style for the accepted page

### Added

- New email template: inbox_paper_submission_author_copy: Your submission made via a preprint server - Author copy.
- [#388](https://github.com/CCSDForge/episciences/issues/388): new option to require a deadline to be indicated when requesting a revision.
- [#420](https://github.com/CCSDForge/episciences/issues/420): Add bibliographical references in zbjats export

## v1.0.41.3 - 2023-12-11

### Fixed

- [414](https://github.com/CCSDForge/episciences/issues/414): volume page does not show volume description anymore
- [409](https://github.com/CCSDForge/episciences/issues/409): https://doi.org/ was automatically added to avery PIDs even if they were not DOIs

## 1.0.41.2 - 2023-12-05

### Fixed

- [408](https://github.com/CCSDForge/episciences/issues/408)"Update metadata" button seems to delete existing metadata
- Fixed Misleading error message about a document not found

### Changed

- The bibliographic references panel is now hidden when empty
- Removed cache from OpenCitation API calls when the response is empty

## 1.0.41.1 - 2023-11-28

### Changed

- [394](https://github.com/CCSDForge/episciences/issues/394): Improvements to facilitate submissions.

## 1.0.41 - 2023-11-27

### Added

- New supported servers: [bioRxiv](https://www.biorxiv.org/) and [medRxiv](https://www.medrxiv.org/) preprint servers
- New supported servers: Support for any data repositories powered by Dataverse. 2 examples are available: [DaRUS (University of Stuttgart)](https://darus.uni-stuttgart.de/) and [Recherche Data Gouv](https://recherche.data.gouv.fr/)
- New feature to automatically extract and manage bibliographical references [with a new application](https://github.com/CCSDForge/episciences-citations)
- Reviewed and accepted bibliographical references will be exported to Crossref
- [New API](https://github.com/CCSDForge/episciences-api) available for journals and internal usage

- It is possible to link documents with software, datasets and publications:
  Previously it was only available with documents hosted on HAL, the feature is now available for any repository, by adding the links on the document's page.
  As recommended, if the software is archived by [Software Heritage](https://www.softwareheritage.org/?lang=en) and a [SWHID](https://www.swhid.org/) is provided, a widget from Software Heritage may be displayed on the web page of the document, showing the archived repository
    - Fixed [#213](https://github.com/CCSDForge/episciences/issues/213): Supplementary material DOI cannot be indicated
    - Closed [#326](https://github.com/CCSDForge/episciences/issues/326): Accept SWHID reference on Episciences platform for software artifact
    - Closed [#327](https://github.com/CCSDForge/episciences/issues/327): Accept HAL reference on Episciences platform for software artifact
    - Closed [#328](https://github.com/CCSDForge/episciences/issues/328): Add a reference to the archived software artifact on reviewer's peer-reviewing form
    - Closed [#329](https://github.com/CCSDForge/episciences/issues/329): Add a reference to the archived software artifact on record landing page
    - Closed [#330](https://github.com/CCSDForge/episciences/issues/330): Add a reference to the archived software artifact on exported metadata (OpenAIRE, Crossref, etc.)
- It is now possible to add a cover letter after the initial submission
- Closed [#367](https://github.com/CCSDForge/episciences/issues/367) Display revision deadlines and make them editable:

- Author affiliations:
    - Added the affiliation acronyms in Crossref DOI and TEI export formats
    - Adding an affiliation from ROR: possibility to search by acronym
    - Closed [#374](https://github.com/CCSDForge/episciences/issues/374): ORCID and affiliations are retrieved from Zenodo

- UI/UX:
    - Document types are now displayed on the document's page(ie preprint, article, ...)
    - icon to make it easier to identify the user in revision requests section.

- Statistics, 2 new indicators:
    - The number of accepted articles (submitted in the same year) [A]
    - Acceptance rate over the whole year [(A\S)x100] : [ A, S : the number of submissions over the whole year]
- Internal updates:
    - script to import volumes and volume metadata from journals translation files into the database
    - .env file
    - New version of Crossref Schema 4.3 -> 5.3 used for DOIs

### Changed

- Accept/reject an invitation: This action is now blocked with an alert message "This paper is under revision, it is useless to review it now."

- Answer revision request: Closed [#313](https://github.com/CCSDForge/episciences/issues/313):
    - optional file attachment in "Contact without sending a new version" and "answer without any modifications".
- UX: icon to make it easier to identify the user in revision requests section

- Closed [323](https://github.com/CCSDForge/episciences/issues/323) change default label for home and Ethical charter
- Switched to [OpenAlex](https://openalex.org/) to obtain the text of citations
- Template for datasets
- Internal updates:
    - Repositories config. is now stored in Database.
    - Titles and descriptions volume's, titles and content metadata volumes are now stored as JSON

### Fixed

- Fixed [#353](https://github.com/CCSDForge/episciences/issues/353) Adding a DOI to a proceeding volume does not save the DOI status

## 1.0.40.21 - 2023-11-14

### Changed

- Volumes & Sections list:
    - display all available rows in the table (pagination disabled).
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
    - refactoring : 'yarn encore prod' needed
    - Display the total number of volumes / sections
    - display all available rows in the table (pagination disabled). The pagination feature was conflicting with the drag and drop feature.
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
- Fixed [#139](https://github.com/CCSDForge/episciences/issues/139) Put newly created volumes at the top of the list #139

## 1.0.40.19 - 2023-10-19

### Fixed

- [#392](https://github.com/CCSDForge/episciences/issues/392): Translations updated and fixed
- Fixed a bug updating dates for user profiles (User profile modification date updated automatically /!\ https://github.com/CCSDForge/episciences/blob/main/src/mysql/ALTER_USER_TABLE.sql)
- Fixed a bug with feature "Re-invite this reviewer".
- Fixed a difference between the address displayed in the profile and the one displayed when the address was modified.
- Fixed Zenodo submissions following the [major update of Zenodo on 2023-10-13](https://blog.zenodo.org/2023/10/13/2023-10-13-zenodo-rdm/).
- Fixed arXiv bibfeed

## 1.0.40.18 - 2023-10-19

## Changed

- Removed sorting option from volumes & sections tables, the feature was confusing and conflicting with another one: drag n drop to sort the volumes/sections
- The new section/volume is now inserted in the first position instead of the last.

## 1.0.40.17 - 2023-10-18

### Fixed

- Sorting volumes and sections: drag and drop doesn't always work.

## 1.0.40.16 - 2023-10-11

### Fixed

- The order of the papers was corrupted on the page listing the papers in a volume.

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
    - submitted
    - waiting for reviewing
    - accepted
    - published
    - ready to publish
    - approved by author - waiting for final publication
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
    1. Default (hidden)
    2. Public
    3. Administrator only
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
    - No (default): do not share
    - Optional: possibility to share
    - Required: sharing is required when submitting a new version and responding to a revision request without any changes
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
- Add an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings.

### New

- Updated volume import script to handle new metadata

## v1.0.41.4 - 2023-12-20

### Fixed

- [413](https://github.com/CCSDForge/episciences/issues/413): Crossref Export for bibliographical references when the reference has no DOI
- Wrong link to the paper's URL in the email template for paper's managers.
- Unable to submit a new version from Zenodo.

### Changed

- The version number for articles "formatted by the author, awaiting final version" is no longer available: the feature could prevent the author from answering the request for a final version
- [322](https://github.com/CCSDForge/episciences/issues/322) manage closing mail author
- changing right to adding orcid when you're owner of paper (before was only for paper's managers)
- [133](https://github.com/CCSDForge/episciences/issues/133) Add default style for the accepted page

### Added

- New email template: inbox_paper_submission_author_copy: Your submission made via a preprint server - Author copy.
- [#388](https://github.com/CCSDForge/episciences/issues/388): new option to require a deadline to be indicated when requesting a revision.
- [#420](https://github.com/CCSDForge/episciences/issues/420): Add bibliographical references in zbjats export

## v1.0.41.3 - 2023-12-11

### Fixed

- [414](https://github.com/CCSDForge/episciences/issues/414): volume page does not show volume description anymore
- [409](https://github.com/CCSDForge/episciences/issues/409): https://doi.org/ was automatically added to avery PIDs even if they were not DOIs

## 1.0.41.2 - 2023-12-05

### Fixed

- [408](https://github.com/CCSDForge/episciences/issues/408)"Update metadata" button seems to delete existing metadata
- Fixed Misleading error message about a document not found

### Changed

- The bibliographic references panel is now hidden when empty
- Removed cache from OpenCitation API calls when the response is empty

## 1.0.41.1 - 2023-11-28

### Changed

- [394](https://github.com/CCSDForge/episciences/issues/394): Improvements to facilitate submissions.

## 1.0.41 - 2023-11-27

### Added

- New supported servers: [bioRxiv](https://www.biorxiv.org/) and [medRxiv](https://www.medrxiv.org/) preprint servers
- New supported servers: Support for any data repositories powered by Dataverse. 2 examples are available: [DaRUS (University of Stuttgart)](https://darus.uni-stuttgart.de/) and [Recherche Data Gouv](https://recherche.data.gouv.fr/)
- New feature to automatically extract and manage bibliographical references [with a new application](https://github.com/CCSDForge/episciences-citations)
- Reviewed and accepted bibliographical references will be exported to Crossref
- [New API](https://github.com/CCSDForge/episciences-api) available for journals and internal usage

- It is possible to link documents with software, datasets and publications:
  Previously it was only available with documents hosted on HAL, the feature is now available for any repository, by adding the links on the document's page.
  As recommended, if the software is archived by [Software Heritage](https://www.softwareheritage.org/?lang=en) and a [SWHID](https://www.swhid.org/) is provided, a widget from Software Heritage may be displayed on the web page of the document, showing the archived repository
    - Fixed [#213](https://github.com/CCSDForge/episciences/issues/213): Supplementary material DOI cannot be indicated
    - Closed [#326](https://github.com/CCSDForge/episciences/issues/326): Accept SWHID reference on Episciences platform for software artifact
    - Closed [#327](https://github.com/CCSDForge/episciences/issues/327): Accept HAL reference on Episciences platform for software artifact
    - Closed [#328](https://github.com/CCSDForge/episciences/issues/328): Add a reference to the archived software artifact on reviewer's peer-reviewing form
    - Closed [#329](https://github.com/CCSDForge/episciences/issues/329): Add a reference to the archived software artifact on record landing page
    - Closed [#330](https://github.com/CCSDForge/episciences/issues/330): Add a reference to the archived software artifact on exported metadata (OpenAIRE, Crossref, etc.)
- It is now possible to add a cover letter after the initial submission
- Closed [#367](https://github.com/CCSDForge/episciences/issues/367) Display revision deadlines and make them editable:

- Author affiliations:
    - Added the affiliation acronyms in Crossref DOI and TEI export formats
    - Adding an affiliation from ROR: possibility to search by acronym
    - Closed [#374](https://github.com/CCSDForge/episciences/issues/374): ORCID and affiliations are retrieved from Zenodo

- UI/UX:
    - Document types are now displayed on the document's page(ie preprint, article, ...)
    - icon to make it easier to identify the user in revision requests section.

- Statistics, 2 new indicators:
    - The number of accepted articles (submitted in the same year) [A]
    - Acceptance rate over the whole year [(A\S)x100] : [ A, S : the number of submissions over the whole year]
- Internal updates:
    - script to import volumes and volume metadata from journals translation files into the database
    - .env file
    - New version of Crossref Schema 4.3 -> 5.3 used for DOIs

### Changed

- Accept/reject an invitation: This action is now blocked with an alert message "This paper is under revision, it is useless to review it now."

- Answer revision request: Closed [#313](https://github.com/CCSDForge/episciences/issues/313):
    - optional file attachment in "Contact without sending a new version" and "answer without any modifications".
- UX: icon to make it easier to identify the user in revision requests section

- Closed [323](https://github.com/CCSDForge/episciences/issues/323) change default label for home and Ethical charter
- Switched to [OpenAlex](https://openalex.org/) to obtain the text of citations
- Template for datasets
- Internal updates:
    - Repositories config. is now stored in Database.
    - Titles and descriptions volume's, titles and content metadata volumes are now stored as JSON

### Fixed

- Fixed [#353](https://github.com/CCSDForge/episciences/issues/353) Adding a DOI to a proceeding volume does not save the DOI status

## 1.0.40.21 - 2023-11-14

### Changed

- Volumes & Sections list:
    - display all available rows in the table (pagination disabled).
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
    - refactoring : 'yarn encore prod' needed
    - Display the total number of volumes / sections
    - display all available rows in the table (pagination disabled). The pagination feature was conflicting with the drag and drop feature.
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
- Fixed [#139](https://github.com/CCSDForge/episciences/issues/139) Put newly created volumes at the top of the list #139

## 1.0.40.19 - 2023-10-19

### Fixed

- [#392](https://github.com/CCSDForge/episciences/issues/392): Translations updated and fixed
- Fixed a bug updating dates for user profiles (User profile modification date updated automatically /!\ https://github.com/CCSDForge/episciences/blob/main/src/mysql/ALTER_USER_TABLE.sql)
- Fixed a bug with feature "Re-invite this reviewer".
- Fixed a difference between the address displayed in the profile and the one displayed when the address was modified.
- Fixed Zenodo submissions following the [major update of Zenodo on 2023-10-13](https://blog.zenodo.org/2023/10/13/2023-10-13-zenodo-rdm/).
- Fixed arXiv bibfeed

## 1.0.40.18 - 2023-10-19

## Changed

- Removed sorting option from volumes & sections tables, the feature was confusing and conflicting with another one: drag n drop to sort the volumes/sections
- The new section/volume is now inserted in the first position instead of the last.

## 1.0.40.17 - 2023-10-18

### Fixed

- Sorting volumes and sections: drag and drop doesn't always work.

## 1.0.40.16 - 2023-10-11

### Fixed

- The order of the papers was corrupted on the page listing the papers in a volume.

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
    - submitted
    - waiting for reviewing
    - accepted
    - published
    - ready to publish
    - approved by author - waiting for final publication
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
    1. Default (hidden)
    2. Public
    3. Administrator only
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
    - No (default): do not share
    - Optional: possibility to share
    - Required: sharing is required when submitting a new version and responding to a revision request without any changes
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
- Add an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings.

### New

- Updated volume import script to handle new metadata

## v1.0.41.4 - 2023-12-20

### Fixed

- [413](https://github.com/CCSDForge/episciences/issues/413): Crossref Export for bibliographical references when the reference has no DOI
- Wrong link to the paper's URL in the email template for paper's managers.
- Unable to submit a new version from Zenodo.

### Changed

- The version number for articles "formatted by the author, awaiting final version" is no longer available: the feature could prevent the author from answering the request for a final version
- [322](https://github.com/CCSDForge/episciences/issues/322) manage closing mail author
- changing right to adding orcid when you're owner of paper (before was only for paper's managers)
- [133](https://github.com/CCSDForge/episciences/issues/133) Add default style for the accepted page

### Added

- New email template: inbox_paper_submission_author_copy: Your submission made via a preprint server - Author copy.
- [#388](https://github.com/CCSDForge/episciences/issues/388): new option to require a deadline to be indicated when requesting a revision.
- [#420](https://github.com/CCSDForge/episciences/issues/420): Add bibliographical references in zbjats export

## v1.0.41.3 - 2023-12-11

### Fixed

- [414](https://github.com/CCSDForge/episciences/issues/414): volume page does not show volume description anymore
- [409](https://github.com/CCSDForge/episciences/issues/409): https://doi.org/ was automatically added to avery PIDs even if they were not DOIs

## 1.0.41.2 - 2023-12-05

### Fixed

- [408](https://github.com/CCSDForge/episciences/issues/408)"Update metadata" button seems to delete existing metadata
- Fixed Misleading error message about a document not found

### Changed

- The bibliographic references panel is now hidden when empty
- Removed cache from OpenCitation API calls when the response is empty

## 1.0.41.1 - 2023-11-28

### Changed

- [394](https://github.com/CCSDForge/episciences/issues/394): Improvements to facilitate submissions.

## 1.0.41 - 2023-11-27

### Added

- New supported servers: [bioRxiv](https://www.biorxiv.org/) and [medRxiv](https://www.medrxiv.org/) preprint servers
- New supported servers: Support for any data repositories powered by Dataverse. 2 examples are available: [DaRUS (University of Stuttgart)](https://darus.uni-stuttgart.de/) and [Recherche Data Gouv](https://recherche.data.gouv.fr/)
- New feature to automatically extract and manage bibliographical references [with a new application](https://github.com/CCSDForge/episciences-citations)
- Reviewed and accepted bibliographical references will be exported to Crossref
- [New API](https://github.com/CCSDForge/episciences-api) available for journals and internal usage

- It is possible to link documents with software, datasets and publications:
  Previously it was only available with documents hosted on HAL, the feature is now available for any repository, by adding the links on the document's page.
  As recommended, if the software is archived by [Software Heritage](https://www.softwareheritage.org/?lang=en) and a [SWHID](https://www.swhid.org/) is provided, a widget from Software Heritage may be displayed on the web page of the document, showing the archived repository
    - Fixed [#213](https://github.com/CCSDForge/episciences/issues/213): Supplementary material DOI cannot be indicated
    - Closed [#326](https://github.com/CCSDForge/episciences/issues/326): Accept SWHID reference on Episciences platform for software artifact
    - Closed [#327](https://github.com/CCSDForge/episciences/issues/327): Accept HAL reference on Episciences platform for software artifact
    - Closed [#328](https://github.com/CCSDForge/episciences/issues/328): Add a reference to the archived software artifact on reviewer's peer-reviewing form
    - Closed [#329](https://github.com/CCSDForge/episciences/issues/329): Add a reference to the archived software artifact on record landing page
    - Closed [#330](https://github.com/CCSDForge/episciences/issues/330): Add a reference to the archived software artifact on exported metadata (OpenAIRE, Crossref, etc.)
- It is now possible to add a cover letter after the initial submission
- Closed [#367](https://github.com/CCSDForge/episciences/issues/367) Display revision deadlines and make them editable:

- Author affiliations:
    - Added the affiliation acronyms in Crossref DOI and TEI export formats
    - Adding an affiliation from ROR: possibility to search by acronym
    - Closed [#374](https://github.com/CCSDForge/episciences/issues/374): ORCID and affiliations are retrieved from Zenodo

- UI/UX:
    - Document types are now displayed on the document's page(ie preprint, article, ...)
    - icon to make it easier to identify the user in revision requests section.

- Statistics, 2 new indicators:
    - The number of accepted articles (submitted in the same year) [A]
    - Acceptance rate over the whole year [(A\S)x100] : [ A, S : the number of submissions over the whole year]
- Internal updates:
    - script to import volumes and volume metadata from journals translation files into the database
    - .env file
    - New version of Crossref Schema 4.3 -> 5.3 used for DOIs

### Changed

- Accept/reject an invitation: This action is now blocked with an alert message "This paper is under revision, it is useless to review it now."

- Answer revision request: Closed [#313](https://github.com/CCSDForge/episciences/issues/313):
    - optional file attachment in "Contact without sending a new version" and "answer without any modifications".
- UX: icon to make it easier to identify the user in revision requests section

- Closed [323](https://github.com/CCSDForge/episciences/issues/323) change default label for home and Ethical charter
- Switched to [OpenAlex](https://openalex.org/) to obtain the text of citations
- Template for datasets
- Internal updates:
    - Repositories config. is now stored in Database.
    - Titles and descriptions volume's, titles and content metadata volumes are now stored as JSON

### Fixed

- Fixed [#353](https://github.com/CCSDForge/episciences/issues/353) Adding a DOI to a proceeding volume does not save the DOI status

## 1.0.40.21 - 2023-11-14

### Changed

- Volumes & Sections list:
    - display all available rows in the table (pagination disabled).
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
    - refactoring : 'yarn encore prod' needed
    - Display the total number of volumes / sections
    - display all available rows in the table (pagination disabled). The pagination feature was conflicting with the drag and drop feature.
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
- Fixed [#139](https://github.com/CCSDForge/episciences/issues/139) Put newly created volumes at the top of the list #139

## 1.0.40.19 - 2023-10-19

### Fixed

- [#392](https://github.com/CCSDForge/episciences/issues/392): Translations updated and fixed
- Fixed a bug updating dates for user profiles (User profile modification date updated automatically /!\ https://github.com/CCSDForge/episciences/blob/main/src/mysql/ALTER_USER_TABLE.sql)
- Fixed a bug with feature "Re-invite this reviewer".
- Fixed a difference between the address displayed in the profile and the one displayed when the address was modified.
- Fixed Zenodo submissions following the [major update of Zenodo on 2023-10-13](https://blog.zenodo.org/2023/10/13/2023-10-13-zenodo-rdm/).
- Fixed arXiv bibfeed

## 1.0.40.18 - 2023-10-19

## Changed

- Removed sorting option from volumes & sections tables, the feature was confusing and conflicting with another one: drag n drop to sort the volumes/sections
- The new section/volume is now inserted in the first position instead of the last.

## 1.0.40.17 - 2023-10-18

### Fixed

- Sorting volumes and sections: drag and drop doesn't always work.

## 1.0.40.16 - 2023-10-11

### Fixed

- The order of the papers was corrupted on the page listing the papers in a volume.

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
    - submitted
    - waiting for reviewing
    - accepted
    - published
    - ready to publish
    - approved by author - waiting for final publication
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
    1. Default (hidden)
    2. Public
    3. Administrator only
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
    - No (default): do not share
    - Optional: possibility to share
    - Required: sharing is required when submitting a new version and responding to a revision request without any changes
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
- Add an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings.

### New

- Updated volume import script to handle new metadata

## v1.0.41.4 - 2023-12-20

### Fixed

- [413](https://github.com/CCSDForge/episciences/issues/413): Crossref Export for bibliographical references when the reference has no DOI
- Wrong link to the paper's URL in the email template for paper's managers.
- Unable to submit a new version from Zenodo.

### Changed

- The version number for articles "formatted by the author, awaiting final version" is no longer available: the feature could prevent the author from answering the request for a final version
- [322](https://github.com/CCSDForge/episciences/issues/322) manage closing mail author
- changing right to adding orcid when you're owner of paper (before was only for paper's managers)
- [133](https://github.com/CCSDForge/episciences/issues/133) Add default style for the accepted page

### Added

- New email template: inbox_paper_submission_author_copy: Your submission made via a preprint server - Author copy.
- [#388](https://github.com/CCSDForge/episciences/issues/388): new option to require a deadline to be indicated when requesting a revision.
- [#420](https://github.com/CCSDForge/episciences/issues/420): Add bibliographical references in zbjats export

## v1.0.41.3 - 2023-12-11

### Fixed

- [414](https://github.com/CCSDForge/episciences/issues/414): volume page does not show volume description anymore
- [409](https://github.com/CCSDForge/episciences/issues/409): https://doi.org/ was automatically added to avery PIDs even if they were not DOIs

## 1.0.41.2 - 2023-12-05

### Fixed

- [408](https://github.com/CCSDForge/episciences/issues/408)"Update metadata" button seems to delete existing metadata
- Fixed Misleading error message about a document not found

### Changed

- The bibliographic references panel is now hidden when empty
- Removed cache from OpenCitation API calls when the response is empty

## 1.0.41.1 - 2023-11-28

### Changed

- [394](https://github.com/CCSDForge/episciences/issues/394): Improvements to facilitate submissions.

## 1.0.41 - 2023-11-27

### Added

- New supported servers: [bioRxiv](https://www.biorxiv.org/) and [medRxiv](https://www.medrxiv.org/) preprint servers
- New supported servers: Support for any data repositories powered by Dataverse. 2 examples are available: [DaRUS (University of Stuttgart)](https://darus.uni-stuttgart.de/) and [Recherche Data Gouv](https://recherche.data.gouv.fr/)
- New feature to automatically extract and manage bibliographical references [with a new application](https://github.com/CCSDForge/episciences-citations)
- Reviewed and accepted bibliographical references will be exported to Crossref
- [New API](https://github.com/CCSDForge/episciences-api) available for journals and internal usage

- It is possible to link documents with software, datasets and publications:
  Previously it was only available with documents hosted on HAL, the feature is now available for any repository, by adding the links on the document's page.
  As recommended, if the software is archived by [Software Heritage](https://www.softwareheritage.org/?lang=en) and a [SWHID](https://www.swhid.org/) is provided, a widget from Software Heritage may be displayed on the web page of the document, showing the archived repository
    - Fixed [#213](https://github.com/CCSDForge/episciences/issues/213): Supplementary material DOI cannot be indicated
    - Closed [#326](https://github.com/CCSDForge/episciences/issues/326): Accept SWHID reference on Episciences platform for software artifact
    - Closed [#327](https://github.com/CCSDForge/episciences/issues/327): Accept HAL reference on Episciences platform for software artifact
    - Closed [#328](https://github.com/CCSDForge/episciences/issues/328): Add a reference to the archived software artifact on reviewer's peer-reviewing form
    - Closed [#329](https://github.com/CCSDForge/episciences/issues/329): Add a reference to the archived software artifact on record landing page
    - Closed [#330](https://github.com/CCSDForge/episciences/issues/330): Add a reference to the archived software artifact on exported metadata (OpenAIRE, Crossref, etc.)
- It is now possible to add a cover letter after the initial submission
- Closed [#367](https://github.com/CCSDForge/episciences/issues/367) Display revision deadlines and make them editable:

- Author affiliations:
    - Added the affiliation acronyms in Crossref DOI and TEI export formats
    - Adding an affiliation from ROR: possibility to search by acronym
    - Closed [#374](https://github.com/CCSDForge/episciences/issues/374): ORCID and affiliations are retrieved from Zenodo

- UI/UX:
    - Document types are now displayed on the document's page(ie preprint, article, ...)
    - icon to make it easier to identify the user in revision requests section.

- Statistics, 2 new indicators:
    - The number of accepted articles (submitted in the same year) [A]
    - Acceptance rate over the whole year [(A\S)x100] : [ A, S : the number of submissions over the whole year]
- Internal updates:
    - script to import volumes and volume metadata from journals translation files into the database
    - .env file
    - New version of Crossref Schema 4.3 -> 5.3 used for DOIs

### Changed

- Accept/reject an invitation: This action is now blocked with an alert message "This paper is under revision, it is useless to review it now."

- Answer revision request: Closed [#313](https://github.com/CCSDForge/episciences/issues/313):
    - optional file attachment in "Contact without sending a new version" and "answer without any modifications".
- UX: icon to make it easier to identify the user in revision requests section

- Closed [323](https://github.com/CCSDForge/episciences/issues/323) change default label for home and Ethical charter
- Switched to [OpenAlex](https://openalex.org/) to obtain the text of citations
- Template for datasets
- Internal updates:
    - Repositories config. is now stored in Database.
    - Titles and descriptions volume's, titles and content metadata volumes are now stored as JSON

### Fixed

- Fixed [#353](https://github.com/CCSDForge/episciences/issues/353) Adding a DOI to a proceeding volume does not save the DOI status

## 1.0.40.21 - 2023-11-14

### Changed

- Volumes & Sections list:
    - display all available rows in the table (pagination disabled).
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
    - refactoring : 'yarn encore prod' needed
    - Display the total number of volumes / sections
    - display all available rows in the table (pagination disabled). The pagination feature was conflicting with the drag and drop feature.
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
- Fixed [#139](https://github.com/CCSDForge/episciences/issues/139) Put newly created volumes at the top of the list #139

## 1.0.40.19 - 2023-10-19

### Fixed

- [#392](https://github.com/CCSDForge/episciences/issues/392): Translations updated and fixed
- Fixed a bug updating dates for user profiles (User profile modification date updated automatically /!\ https://github.com/CCSDForge/episciences/blob/main/src/mysql/ALTER_USER_TABLE.sql)
- Fixed a bug with feature "Re-invite this reviewer".
- Fixed a difference between the address displayed in the profile and the one displayed when the address was modified.
- Fixed Zenodo submissions following the [major update of Zenodo on 2023-10-13](https://blog.zenodo.org/2023/10/13/2023-10-13-zenodo-rdm/).
- Fixed arXiv bibfeed

## 1.0.40.18 - 2023-10-19

## Changed

- Removed sorting option from volumes & sections tables, the feature was confusing and conflicting with another one: drag n drop to sort the volumes/sections
- The new section/volume is now inserted in the first position instead of the last.

## 1.0.40.17 - 2023-10-18

### Fixed

- Sorting volumes and sections: drag and drop doesn't always work.

## 1.0.40.16 - 2023-10-11

### Fixed

- The order of the papers was corrupted on the page listing the papers in a volume.

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
    - submitted
    - waiting for reviewing
    - accepted
    - published
    - ready to publish
    - approved by author - waiting for final publication
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
    1. Default (hidden)
    2. Public
    3. Administrator only
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
    - No (default): do not share
    - Optional: possibility to share
    - Required: sharing is required when submitting a new version and responding to a revision request without any changes
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
- Add an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings.

### New

- Updated volume import script to handle new metadata

## v1.0.41.4 - 2023-12-20

### Fixed

- [413](https://github.com/CCSDForge/episciences/issues/413): Crossref Export for bibliographical references when the reference has no DOI
- Wrong link to the paper's URL in the email template for paper's managers.
- Unable to submit a new version from Zenodo.

### Changed

- The version number for articles "formatted by the author, awaiting final version" is no longer available: the feature could prevent the author from answering the request for a final version
- [322](https://github.com/CCSDForge/episciences/issues/322) manage closing mail author
- changing right to adding orcid when you're owner of paper (before was only for paper's managers)
- [133](https://github.com/CCSDForge/episciences/issues/133) Add default style for the accepted page

### Added

- New email template: inbox_paper_submission_author_copy: Your submission made via a preprint server - Author copy.
- [#388](https://github.com/CCSDForge/episciences/issues/388): new option to require a deadline to be indicated when requesting a revision.
- [#420](https://github.com/CCSDForge/episciences/issues/420): Add bibliographical references in zbjats export

## v1.0.41.3 - 2023-12-11

### Fixed

- [414](https://github.com/CCSDForge/episciences/issues/414): volume page does not show volume description anymore
- [409](https://github.com/CCSDForge/episciences/issues/409): https://doi.org/ was automatically added to avery PIDs even if they were not DOIs

## 1.0.41.2 - 2023-12-05

### Fixed

- [408](https://github.com/CCSDForge/episciences/issues/408)"Update metadata" button seems to delete existing metadata
- Fixed Misleading error message about a document not found

### Changed

- The bibliographic references panel is now hidden when empty
- Removed cache from OpenCitation API calls when the response is empty

## 1.0.41.1 - 2023-11-28

### Changed

- [394](https://github.com/CCSDForge/episciences/issues/394): Improvements to facilitate submissions.

## 1.0.41 - 2023-11-27

### Added

- New supported servers: [bioRxiv](https://www.biorxiv.org/) and [medRxiv](https://www.medrxiv.org/) preprint servers
- New supported servers: Support for any data repositories powered by Dataverse. 2 examples are available: [DaRUS (University of Stuttgart)](https://darus.uni-stuttgart.de/) and [Recherche Data Gouv](https://recherche.data.gouv.fr/)
- New feature to automatically extract and manage bibliographical references [with a new application](https://github.com/CCSDForge/episciences-citations)
- Reviewed and accepted bibliographical references will be exported to Crossref
- [New API](https://github.com/CCSDForge/episciences-api) available for journals and internal usage

- It is possible to link documents with software, datasets and publications:
  Previously it was only available with documents hosted on HAL, the feature is now available for any repository, by adding the links on the document's page.
  As recommended, if the software is archived by [Software Heritage](https://www.softwareheritage.org/?lang=en) and a [SWHID](https://www.swhid.org/) is provided, a widget from Software Heritage may be displayed on the web page of the document, showing the archived repository
    - Fixed [#213](https://github.com/CCSDForge/episciences/issues/213): Supplementary material DOI cannot be indicated
    - Closed [#326](https://github.com/CCSDForge/episciences/issues/326): Accept SWHID reference on Episciences platform for software artifact
    - Closed [#327](https://github.com/CCSDForge/episciences/issues/327): Accept HAL reference on Episciences platform for software artifact
    - Closed [#328](https://github.com/CCSDForge/episciences/issues/328): Add a reference to the archived software artifact on reviewer's peer-reviewing form
    - Closed [#329](https://github.com/CCSDForge/episciences/issues/329): Add a reference to the archived software artifact on record landing page
    - Closed [#330](https://github.com/CCSDForge/episciences/issues/330): Add a reference to the archived software artifact on exported metadata (OpenAIRE, Crossref, etc.)
- It is now possible to add a cover letter after the initial submission
- Closed [#367](https://github.com/CCSDForge/episciences/issues/367) Display revision deadlines and make them editable:

- Author affiliations:
    - Added the affiliation acronyms in Crossref DOI and TEI export formats
    - Adding an affiliation from ROR: possibility to search by acronym
    - Closed [#374](https://github.com/CCSDForge/episciences/issues/374): ORCID and affiliations are retrieved from Zenodo

- UI/UX:
    - Document types are now displayed on the document's page(ie preprint, article, ...)
    - icon to make it easier to identify the user in revision requests section.

- Statistics, 2 new indicators:
    - The number of accepted articles (submitted in the same year) [A]
    - Acceptance rate over the whole year [(A\S)x100] : [ A, S : the number of submissions over the whole year]
- Internal updates:
    - script to import volumes and volume metadata from journals translation files into the database
    - .env file
    - New version of Crossref Schema 4.3 -> 5.3 used for DOIs

### Changed

- Accept/reject an invitation: This action is now blocked with an alert message "This paper is under revision, it is useless to review it now."

- Answer revision request: Closed [#313](https://github.com/CCSDForge/episciences/issues/313):
    - optional file attachment in "Contact without sending a new version" and "answer without any modifications".
- UX: icon to make it easier to identify the user in revision requests section

- Closed [323](https://github.com/CCSDForge/episciences/issues/323) change default label for home and Ethical charter
- Switched to [OpenAlex](https://openalex.org/) to obtain the text of citations
- Template for datasets
- Internal updates:
    - Repositories config. is now stored in Database.
    - Titles and descriptions volume's, titles and content metadata volumes are now stored as JSON

### Fixed

- Fixed [#353](https://github.com/CCSDForge/episciences/issues/353) Adding a DOI to a proceeding volume does not save the DOI status

## 1.0.40.21 - 2023-11-14

### Changed

- Volumes & Sections list:
    - display all available rows in the table (pagination disabled).
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
    - refactoring : 'yarn encore prod' needed
    - Display the total number of volumes / sections
    - display all available rows in the table (pagination disabled). The pagination feature was conflicting with the drag and drop feature.
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
- Fixed [#139](https://github.com/CCSDForge/episciences/issues/139) Put newly created volumes at the top of the list #139

## 1.0.40.19 - 2023-10-19

### Fixed

- [#392](https://github.com/CCSDForge/episciences/issues/392): Translations updated and fixed
- Fixed a bug updating dates for user profiles (User profile modification date updated automatically /!\ https://github.com/CCSDForge/episciences/blob/main/src/mysql/ALTER_USER_TABLE.sql)
- Fixed a bug with feature "Re-invite this reviewer".
- Fixed a difference between the address displayed in the profile and the one displayed when the address was modified.
- Fixed Zenodo submissions following the [major update of Zenodo on 2023-10-13](https://blog.zenodo.org/2023/10/13/2023-10-13-zenodo-rdm/).
- Fixed arXiv bibfeed

## 1.0.40.18 - 2023-10-19

## Changed

- Removed sorting option from volumes & sections tables, the feature was confusing and conflicting with another one: drag n drop to sort the volumes/sections
- The new section/volume is now inserted in the first position instead of the last.

## 1.0.40.17 - 2023-10-18

### Fixed

- Sorting volumes and sections: drag and drop doesn't always work.

## 1.0.40.16 - 2023-10-11

### Fixed

- The order of the papers was corrupted on the page listing the papers in a volume.

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
    - submitted
    - waiting for reviewing
    - accepted
    - published
    - ready to publish
    - approved by author - waiting for final publication
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
    1. Default (hidden)
    2. Public
    3. Administrator only
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
    - No (default): do not share
    - Optional: possibility to share
    - Required: sharing is required when submitting a new version and responding to a revision request without any changes
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
- Add an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings.

### New

- Updated volume import script to handle new metadata

## v1.0.41.4 - 2023-12-20

### Fixed

- [413](https://github.com/CCSDForge/episciences/issues/413): Crossref Export for bibliographical references when the reference has no DOI
- Wrong link to the paper's URL in the email template for paper's managers.
- Unable to submit a new version from Zenodo.

### Changed

- The version number for articles "formatted by the author, awaiting final version" is no longer available: the feature could prevent the author from answering the request for a final version
- [322](https://github.com/CCSDForge/episciences/issues/322) manage closing mail author
- changing right to adding orcid when you're owner of paper (before was only for paper's managers)
- [133](https://github.com/CCSDForge/episciences/issues/133) Add default style for the accepted page

### Added

- New email template: inbox_paper_submission_author_copy: Your submission made via a preprint server - Author copy.
- [#388](https://github.com/CCSDForge/episciences/issues/388): new option to require a deadline to be indicated when requesting a revision.
- [#420](https://github.com/CCSDForge/episciences/issues/420): Add bibliographical references in zbjats export

## v1.0.41.3 - 2023-12-11

### Fixed

- [414](https://github.com/CCSDForge/episciences/issues/414): volume page does not show volume description anymore
- [409](https://github.com/CCSDForge/episciences/issues/409): https://doi.org/ was automatically added to avery PIDs even if they were not DOIs

## 1.0.41.2 - 2023-12-05

### Fixed

- [408](https://github.com/CCSDForge/episciences/issues/408)"Update metadata" button seems to delete existing metadata
- Fixed Misleading error message about a document not found

### Changed

- The bibliographic references panel is now hidden when empty
- Removed cache from OpenCitation API calls when the response is empty

## 1.0.41.1 - 2023-11-28

### Changed

- [394](https://github.com/CCSDForge/episciences/issues/394): Improvements to facilitate submissions.

## 1.0.41 - 2023-11-27

### Added

- New supported servers: [bioRxiv](https://www.biorxiv.org/) and [medRxiv](https://www.medrxiv.org/) preprint servers
- New supported servers: Support for any data repositories powered by Dataverse. 2 examples are available: [DaRUS (University of Stuttgart)](https://darus.uni-stuttgart.de/) and [Recherche Data Gouv](https://recherche.data.gouv.fr/)
- New feature to automatically extract and manage bibliographical references [with a new application](https://github.com/CCSDForge/episciences-citations)
- Reviewed and accepted bibliographical references will be exported to Crossref
- [New API](https://github.com/CCSDForge/episciences-api) available for journals and internal usage

- It is possible to link documents with software, datasets and publications:
  Previously it was only available with documents hosted on HAL, the feature is now available for any repository, by adding the links on the document's page.
  As recommended, if the software is archived by [Software Heritage](https://www.softwareheritage.org/?lang=en) and a [SWHID](https://www.swhid.org/) is provided, a widget from Software Heritage may be displayed on the web page of the document, showing the archived repository
    - Fixed [#213](https://github.com/CCSDForge/episciences/issues/213): Supplementary material DOI cannot be indicated
    - Closed [#326](https://github.com/CCSDForge/episciences/issues/326): Accept SWHID reference on Episciences platform for software artifact
    - Closed [#327](https://github.com/CCSDForge/episciences/issues/327): Accept HAL reference on Episciences platform for software artifact
    - Closed [#328](https://github.com/CCSDForge/episciences/issues/328): Add a reference to the archived software artifact on reviewer's peer-reviewing form
    - Closed [#329](https://github.com/CCSDForge/episciences/issues/329): Add a reference to the archived software artifact on record landing page
    - Closed [#330](https://github.com/CCSDForge/episciences/issues/330): Add a reference to the archived software artifact on exported metadata (OpenAIRE, Crossref, etc.)
- It is now possible to add a cover letter after the initial submission
- Closed [#367](https://github.com/CCSDForge/episciences/issues/367) Display revision deadlines and make them editable:

- Author affiliations:
    - Added the affiliation acronyms in Crossref DOI and TEI export formats
    - Adding an affiliation from ROR: possibility to search by acronym
    - Closed [#374](https://github.com/CCSDForge/episciences/issues/374): ORCID and affiliations are retrieved from Zenodo

- UI/UX:
    - Document types are now displayed on the document's page(ie preprint, article, ...)
    - icon to make it easier to identify the user in revision requests section.

- Statistics, 2 new indicators:
    - The number of accepted articles (submitted in the same year) [A]
    - Acceptance rate over the whole year [(A\S)x100] : [ A, S : the number of submissions over the whole year]
- Internal updates:
    - script to import volumes and volume metadata from journals translation files into the database
    - .env file
    - New version of Crossref Schema 4.3 -> 5.3 used for DOIs

### Changed

- Accept/reject an invitation: This action is now blocked with an alert message "This paper is under revision, it is useless to review it now."

- Answer revision request: Closed [#313](https://github.com/CCSDForge/episciences/issues/313):
    - optional file attachment in "Contact without sending a new version" and "answer without any modifications".
- UX: icon to make it easier to identify the user in revision requests section

- Closed [323](https://github.com/CCSDForge/episciences/issues/323) change default label for home and Ethical charter
- Switched to [OpenAlex](https://openalex.org/) to obtain the text of citations
- Template for datasets
- Internal updates:
    - Repositories config. is now stored in Database.
    - Titles and descriptions volume's, titles and content metadata volumes are now stored as JSON

### Fixed

- Fixed [#353](https://github.com/CCSDForge/episciences/issues/353) Adding a DOI to a proceeding volume does not save the DOI status

## 1.0.40.21 - 2023-11-14

### Changed

- Volumes & Sections list:
    - display all available rows in the table (pagination disabled).
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
    - refactoring : 'yarn encore prod' needed
    - Display the total number of volumes / sections
    - display all available rows in the table (pagination disabled). The pagination feature was conflicting with the drag and drop feature.
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
- Fixed [#139](https://github.com/CCSDForge/episciences/issues/139) Put newly created volumes at the top of the list #139

## 1.0.40.19 - 2023-10-19

### Fixed

- [#392](https://github.com/CCSDForge/episciences/issues/392): Translations updated and fixed
- Fixed a bug updating dates for user profiles (User profile modification date updated automatically /!\ https://github.com/CCSDForge/episciences/blob/main/src/mysql/ALTER_USER_TABLE.sql)
- Fixed a bug with feature "Re-invite this reviewer".
- Fixed a difference between the address displayed in the profile and the one displayed when the address was modified.
- Fixed Zenodo submissions following the [major update of Zenodo on 2023-10-13](https://blog.zenodo.org/2023/10/13/2023-10-13-zenodo-rdm/).
- Fixed arXiv bibfeed

## 1.0.40.18 - 2023-10-19

## Changed

- Removed sorting option from volumes & sections tables, the feature was confusing and conflicting with another one: drag n drop to sort the volumes/sections
- The new section/volume is now inserted in the first position instead of the last.

## 1.0.40.17 - 2023-10-18

### Fixed

- Sorting volumes and sections: drag and drop doesn't always work.

## 1.0.40.16 - 2023-10-11

### Fixed

- The order of the papers was corrupted on the page listing the papers in a volume.

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
    - submitted
    - waiting for reviewing
    - accepted
    - published
    - ready to publish
    - approved by author - waiting for final publication
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
    1. Default (hidden)
    2. Public
    3. Administrator only
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
    - No (default): do not share
    - Optional: possibility to share
    - Required: sharing is required when submitting a new version and responding to a revision request without any changes
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
- Add an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings.

### New

- Updated volume import script to handle new metadata

## v1.0.41.4 - 2023-12-20

### Fixed

- [413](https://github.com/CCSDForge/episciences/issues/413): Crossref Export for bibliographical references when the reference has no DOI
- Wrong link to the paper's URL in the email template for paper's managers.
- Unable to submit a new version from Zenodo.

### Changed

- The version number for articles "formatted by the author, awaiting final version" is no longer available: the feature could prevent the author from answering the request for a final version
- [322](https://github.com/CCSDForge/episciences/issues/322) manage closing mail author
- changing right to adding orcid when you're owner of paper (before was only for paper's managers)
- [133](https://github.com/CCSDForge/episciences/issues/133) Add default style for the accepted page

### Added

- New email template: inbox_paper_submission_author_copy: Your submission made via a preprint server - Author copy.
- [#388](https://github.com/CCSDForge/episciences/issues/388): new option to require a deadline to be indicated when requesting a revision.
- [#420](https://github.com/CCSDForge/episciences/issues/420): Add bibliographical references in zbjats export

## v1.0.41.3 - 2023-12-11

### Fixed

- [414](https://github.com/CCSDForge/episciences/issues/414): volume page does not show volume description anymore
- [409](https://github.com/CCSDForge/episciences/issues/409): https://doi.org/ was automatically added to avery PIDs even if they were not DOIs

## 1.0.41.2 - 2023-12-05

### Fixed

- [408](https://github.com/CCSDForge/episciences/issues/408)"Update metadata" button seems to delete existing metadata
- Fixed Misleading error message about a document not found

### Changed

- The bibliographic references panel is now hidden when empty
- Removed cache from OpenCitation API calls when the response is empty

## 1.0.41.1 - 2023-11-28

### Changed

- [394](https://github.com/CCSDForge/episciences/issues/394): Improvements to facilitate submissions.

## 1.0.41 - 2023-11-27

### Added

- New supported servers: [bioRxiv](https://www.biorxiv.org/) and [medRxiv](https://www.medrxiv.org/) preprint servers
- New supported servers: Support for any data repositories powered by Dataverse. 2 examples are available: [DaRUS (University of Stuttgart)](https://darus.uni-stuttgart.de/) and [Recherche Data Gouv](https://recherche.data.gouv.fr/)
- New feature to automatically extract and manage bibliographical references [with a new application](https://github.com/CCSDForge/episciences-citations)
- Reviewed and accepted bibliographical references will be exported to Crossref
- [New API](https://github.com/CCSDForge/episciences-api) available for journals and internal usage

- It is possible to link documents with software, datasets and publications:
  Previously it was only available with documents hosted on HAL, the feature is now available for any repository, by adding the links on the document's page.
  As recommended, if the software is archived by [Software Heritage](https://www.softwareheritage.org/?lang=en) and a [SWHID](https://www.swhid.org/) is provided, a widget from Software Heritage may be displayed on the web page of the document, showing the archived repository
    - Fixed [#213](https://github.com/CCSDForge/episciences/issues/213): Supplementary material DOI cannot be indicated
    - Closed [#326](https://github.com/CCSDForge/episciences/issues/326): Accept SWHID reference on Episciences platform for software artifact
    - Closed [#327](https://github.com/CCSDForge/episciences/issues/327): Accept HAL reference on Episciences platform for software artifact
    - Closed [#328](https://github.com/CCSDForge/episciences/issues/328): Add a reference to the archived software artifact on reviewer's peer-reviewing form
    - Closed [#329](https://github.com/CCSDForge/episciences/issues/329): Add a reference to the archived software artifact on record landing page
    - Closed [#330](https://github.com/CCSDForge/episciences/issues/330): Add a reference to the archived software artifact on exported metadata (OpenAIRE, Crossref, etc.)
- It is now possible to add a cover letter after the initial submission
- Closed [#367](https://github.com/CCSDForge/episciences/issues/367) Display revision deadlines and make them editable:

- Author affiliations:
    - Added the affiliation acronyms in Crossref DOI and TEI export formats
    - Adding an affiliation from ROR: possibility to search by acronym
    - Closed [#374](https://github.com/CCSDForge/episciences/issues/374): ORCID and affiliations are retrieved from Zenodo

- UI/UX:
    - Document types are now displayed on the document's page(ie preprint, article, ...)
    - icon to make it easier to identify the user in revision requests section.

- Statistics, 2 new indicators:
    - The number of accepted articles (submitted in the same year) [A]
    - Acceptance rate over the whole year [(A\S)x100] : [ A, S : the number of submissions over the whole year]
- Internal updates:
    - script to import volumes and volume metadata from journals translation files into the database
    - .env file
    - New version of Crossref Schema 4.3 -> 5.3 used for DOIs

### Changed

- Accept/reject an invitation: This action is now blocked with an alert message "This paper is under revision, it is useless to review it now."

- Answer revision request: Closed [#313](https://github.com/CCSDForge/episciences/issues/313):
    - optional file attachment in "Contact without sending a new version" and "answer without any modifications".
- UX: icon to make it easier to identify the user in revision requests section

- Closed [323](https://github.com/CCSDForge/episciences/issues/323) change default label for home and Ethical charter
- Switched to [OpenAlex](https://openalex.org/) to obtain the text of citations
- Template for datasets
- Internal updates:
    - Repositories config. is now stored in Database.
    - Titles and descriptions volume's, titles and content metadata volumes are now stored as JSON

### Fixed

- Fixed [#353](https://github.com/CCSDForge/episciences/issues/353) Adding a DOI to a proceeding volume does not save the DOI status

## 1.0.40.21 - 2023-11-14

### Changed

- Volumes & Sections list:
    - display all available rows in the table (pagination disabled).
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
    - refactoring : 'yarn encore prod' needed
    - Display the total number of volumes / sections
    - display all available rows in the table (pagination disabled). The pagination feature was conflicting with the drag and drop feature.
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
- Fixed [#139](https://github.com/CCSDForge/episciences/issues/139) Put newly created volumes at the top of the list #139

## 1.0.40.19 - 2023-10-19

### Fixed

- [#392](https://github.com/CCSDForge/episciences/issues/392): Translations updated and fixed
- Fixed a bug updating dates for user profiles (User profile modification date updated automatically /!\ https://github.com/CCSDForge/episciences/blob/main/src/mysql/ALTER_USER_TABLE.sql)
- Fixed a bug with feature "Re-invite this reviewer".
- Fixed a difference between the address displayed in the profile and the one displayed when the address was modified.
- Fixed Zenodo submissions following the [major update of Zenodo on 2023-10-13](https://blog.zenodo.org/2023/10/13/2023-10-13-zenodo-rdm/).
- Fixed arXiv bibfeed

## 1.0.40.18 - 2023-10-19

## Changed

- Removed sorting option from volumes & sections tables, the feature was confusing and conflicting with another one: drag n drop to sort the volumes/sections
- The new section/volume is now inserted in the first position instead of the last.

## 1.0.40.17 - 2023-10-18

### Fixed

- Sorting volumes and sections: drag and drop doesn't always work.

## 1.0.40.16 - 2023-10-11

### Fixed

- The order of the papers was corrupted on the page listing the papers in a volume.

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
    - submitted
    - waiting for reviewing
    - accepted
    - published
    - ready to publish
    - approved by author - waiting for final publication
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
    1. Default (hidden)
    2. Public
    3. Administrator only
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
    - No (default): do not share
    - Optional: possibility to share
    - Required: sharing is required when submitting a new version and responding to a revision request without any changes
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
- Add an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings.

### New

- Updated volume import script to handle new metadata

## v1.0.41.4 - 2023-12-20

### Fixed

- [413](https://github.com/CCSDForge/episciences/issues/413): Crossref Export for bibliographical references when the reference has no DOI
- Wrong link to the paper's URL in the email template for paper's managers.
- Unable to submit a new version from Zenodo.

### Changed

- The version number for articles "formatted by the author, awaiting final version" is no longer available: the feature could prevent the author from answering the request for a final version
- [322](https://github.com/CCSDForge/episciences/issues/322) manage closing mail author
- changing right to adding orcid when you're owner of paper (before was only for paper's managers)
- [133](https://github.com/CCSDForge/episciences/issues/133) Add default style for the accepted page

### Added

- New email template: inbox_paper_submission_author_copy: Your submission made via a preprint server - Author copy.
- [#388](https://github.com/CCSDForge/episciences/issues/388): new option to require a deadline to be indicated when requesting a revision.
- [#420](https://github.com/CCSDForge/episciences/issues/420): Add bibliographical references in zbjats export

## v1.0.41.3 - 2023-12-11

### Fixed

- [414](https://github.com/CCSDForge/episciences/issues/414): volume page does not show volume description anymore
- [409](https://github.com/CCSDForge/episciences/issues/409): https://doi.org/ was automatically added to avery PIDs even if they were not DOIs

## 1.0.41.2 - 2023-12-05

### Fixed

- [408](https://github.com/CCSDForge/episciences/issues/408)"Update metadata" button seems to delete existing metadata
- Fixed Misleading error message about a document not found

### Changed

- The bibliographic references panel is now hidden when empty
- Removed cache from OpenCitation API calls when the response is empty

## 1.0.41.1 - 2023-11-28

### Changed

- [394](https://github.com/CCSDForge/episciences/issues/394): Improvements to facilitate submissions.

## 1.0.41 - 2023-11-27

### Added

- New supported servers: [bioRxiv](https://www.biorxiv.org/) and [medRxiv](https://www.medrxiv.org/) preprint servers
- New supported servers: Support for any data repositories powered by Dataverse. 2 examples are available: [DaRUS (University of Stuttgart)](https://darus.uni-stuttgart.de/) and [Recherche Data Gouv](https://recherche.data.gouv.fr/)
- New feature to automatically extract and manage bibliographical references [with a new application](https://github.com/CCSDForge/episciences-citations)
- Reviewed and accepted bibliographical references will be exported to Crossref
- [New API](https://github.com/CCSDForge/episciences-api) available for journals and internal usage

- It is possible to link documents with software, datasets and publications:
  Previously it was only available with documents hosted on HAL, the feature is now available for any repository, by adding the links on the document's page.
  As recommended, if the software is archived by [Software Heritage](https://www.softwareheritage.org/?lang=en) and a [SWHID](https://www.swhid.org/) is provided, a widget from Software Heritage may be displayed on the web page of the document, showing the archived repository
    - Fixed [#213](https://github.com/CCSDForge/episciences/issues/213): Supplementary material DOI cannot be indicated
    - Closed [#326](https://github.com/CCSDForge/episciences/issues/326): Accept SWHID reference on Episciences platform for software artifact
    - Closed [#327](https://github.com/CCSDForge/episciences/issues/327): Accept HAL reference on Episciences platform for software artifact
    - Closed [#328](https://github.com/CCSDForge/episciences/issues/328): Add a reference to the archived software artifact on reviewer's peer-reviewing form
    - Closed [#329](https://github.com/CCSDForge/episciences/issues/329): Add a reference to the archived software artifact on record landing page
    - Closed [#330](https://github.com/CCSDForge/episciences/issues/330): Add a reference to the archived software artifact on exported metadata (OpenAIRE, Crossref, etc.)
- It is now possible to add a cover letter after the initial submission
- Closed [#367](https://github.com/CCSDForge/episciences/issues/367) Display revision deadlines and make them editable:

- Author affiliations:
    - Added the affiliation acronyms in Crossref DOI and TEI export formats
    - Adding an affiliation from ROR: possibility to search by acronym
    - Closed [#374](https://github.com/CCSDForge/episciences/issues/374): ORCID and affiliations are retrieved from Zenodo

- UI/UX:
    - Document types are now displayed on the document's page(ie preprint, article, ...)
    - icon to make it easier to identify the user in revision requests section.

- Statistics, 2 new indicators:
    - The number of accepted articles (submitted in the same year) [A]
    - Acceptance rate over the whole year [(A\S)x100] : [ A, S : the number of submissions over the whole year]
- Internal updates:
    - script to import volumes and volume metadata from journals translation files into the database
    - .env file
    - New version of Crossref Schema 4.3 -> 5.3 used for DOIs

### Changed

- Accept/reject an invitation: This action is now blocked with an alert message "This paper is under revision, it is useless to review it now."

- Answer revision request: Closed [#313](https://github.com/CCSDForge/episciences/issues/313):
    - optional file attachment in "Contact without sending a new version" and "answer without any modifications".
- UX: icon to make it easier to identify the user in revision requests section

- Closed [323](https://github.com/CCSDForge/episciences/issues/323) change default label for home and Ethical charter
- Switched to [OpenAlex](https://openalex.org/) to obtain the text of citations
- Template for datasets
- Internal updates:
    - Repositories config. is now stored in Database.
    - Titles and descriptions volume's, titles and content metadata volumes are now stored as JSON

### Fixed

- Fixed [#353](https://github.com/CCSDForge/episciences/issues/353) Adding a DOI to a proceeding volume does not save the DOI status

## 1.0.40.21 - 2023-11-14

### Changed

- Volumes & Sections list:
    - display all available rows in the table (pagination disabled).
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
    - refactoring : 'yarn encore prod' needed
    - Display the total number of volumes / sections
    - display all available rows in the table (pagination disabled). The pagination feature was conflicting with the drag and drop feature.
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
- Fixed [#139](https://github.com/CCSDForge/episciences/issues/139) Put newly created volumes at the top of the list #139

## 1.0.40.19 - 2023-10-19

### Fixed

- [#392](https://github.com/CCSDForge/episciences/issues/392): Translations updated and fixed
- Fixed a bug updating dates for user profiles (User profile modification date updated automatically /!\ https://github.com/CCSDForge/episciences/blob/main/src/mysql/ALTER_USER_TABLE.sql)
- Fixed a bug with feature "Re-invite this reviewer".
- Fixed a difference between the address displayed in the profile and the one displayed when the address was modified.
- Fixed Zenodo submissions following the [major update of Zenodo on 2023-10-13](https://blog.zenodo.org/2023/10/13/2023-10-13-zenodo-rdm/).
- Fixed arXiv bibfeed

## 1.0.40.18 - 2023-10-19

## Changed

- Removed sorting option from volumes & sections tables, the feature was confusing and conflicting with another one: drag n drop to sort the volumes/sections
- The new section/volume is now inserted in the first position instead of the last.

## 1.0.40.17 - 2023-10-18

### Fixed

- Sorting volumes and sections: drag and drop doesn't always work.

## 1.0.40.16 - 2023-10-11

### Fixed

- The order of the papers was corrupted on the page listing the papers in a volume.

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
    - submitted
    - waiting for reviewing
    - accepted
    - published
    - ready to publish
    - approved by author - waiting for final publication
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
    1. Default (hidden)
    2. Public
    3. Administrator only
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
    - No (default): do not share
    - Optional: possibility to share
    - Required: sharing is required when submitting a new version and responding to a revision request without any changes
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
- Add an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings.

### New

- Updated volume import script to handle new metadata

## v1.0.41.4 - 2023-12-20

### Fixed

- [413](https://github.com/CCSDForge/episciences/issues/413): Crossref Export for bibliographical references when the reference has no DOI
- Wrong link to the paper's URL in the email template for paper's managers.
- Unable to submit a new version from Zenodo.

### Changed

- The version number for articles "formatted by the author, awaiting final version" is no longer available: the feature could prevent the author from answering the request for a final version
- [322](https://github.com/CCSDForge/episciences/issues/322) manage closing mail author
- changing right to adding orcid when you're owner of paper (before was only for paper's managers)
- [133](https://github.com/CCSDForge/episciences/issues/133) Add default style for the accepted page

### Added

- New email template: inbox_paper_submission_author_copy: Your submission made via a preprint server - Author copy.
- [#388](https://github.com/CCSDForge/episciences/issues/388): new option to require a deadline to be indicated when requesting a revision.
- [#420](https://github.com/CCSDForge/episciences/issues/420): Add bibliographical references in zbjats export

## v1.0.41.3 - 2023-12-11

### Fixed

- [414](https://github.com/CCSDForge/episciences/issues/414): volume page does not show volume description anymore
- [409](https://github.com/CCSDForge/episciences/issues/409): https://doi.org/ was automatically added to avery PIDs even if they were not DOIs

## 1.0.41.2 - 2023-12-05

### Fixed

- [408](https://github.com/CCSDForge/episciences/issues/408)"Update metadata" button seems to delete existing metadata
- Fixed Misleading error message about a document not found

### Changed

- The bibliographic references panel is now hidden when empty
- Removed cache from OpenCitation API calls when the response is empty

## 1.0.41.1 - 2023-11-28

### Changed

- [394](https://github.com/CCSDForge/episciences/issues/394): Improvements to facilitate submissions.

## 1.0.41 - 2023-11-27

### Added

- New supported servers: [bioRxiv](https://www.biorxiv.org/) and [medRxiv](https://www.medrxiv.org/) preprint servers
- New supported servers: Support for any data repositories powered by Dataverse. 2 examples are available: [DaRUS (University of Stuttgart)](https://darus.uni-stuttgart.de/) and [Recherche Data Gouv](https://recherche.data.gouv.fr/)
- New feature to automatically extract and manage bibliographical references [with a new application](https://github.com/CCSDForge/episciences-citations)
- Reviewed and accepted bibliographical references will be exported to Crossref
- [New API](https://github.com/CCSDForge/episciences-api) available for journals and internal usage

- It is possible to link documents with software, datasets and publications:
  Previously it was only available with documents hosted on HAL, the feature is now available for any repository, by adding the links on the document's page.
  As recommended, if the software is archived by [Software Heritage](https://www.softwareheritage.org/?lang=en) and a [SWHID](https://www.swhid.org/) is provided, a widget from Software Heritage may be displayed on the web page of the document, showing the archived repository
    - Fixed [#213](https://github.com/CCSDForge/episciences/issues/213): Supplementary material DOI cannot be indicated
    - Closed [#326](https://github.com/CCSDForge/episciences/issues/326): Accept SWHID reference on Episciences platform for software artifact
    - Closed [#327](https://github.com/CCSDForge/episciences/issues/327): Accept HAL reference on Episciences platform for software artifact
    - Closed [#328](https://github.com/CCSDForge/episciences/issues/328): Add a reference to the archived software artifact on reviewer's peer-reviewing form
    - Closed [#329](https://github.com/CCSDForge/episciences/issues/329): Add a reference to the archived software artifact on record landing page
    - Closed [#330](https://github.com/CCSDForge/episciences/issues/330): Add a reference to the archived software artifact on exported metadata (OpenAIRE, Crossref, etc.)
- It is now possible to add a cover letter after the initial submission
- Closed [#367](https://github.com/CCSDForge/episciences/issues/367) Display revision deadlines and make them editable:

- Author affiliations:
    - Added the affiliation acronyms in Crossref DOI and TEI export formats
    - Adding an affiliation from ROR: possibility to search by acronym
    - Closed [#374](https://github.com/CCSDForge/episciences/issues/374): ORCID and affiliations are retrieved from Zenodo

- UI/UX:
    - Document types are now displayed on the document's page(ie preprint, article, ...)
    - icon to make it easier to identify the user in revision requests section.

- Statistics, 2 new indicators:
    - The number of accepted articles (submitted in the same year) [A]
    - Acceptance rate over the whole year [(A\S)x100] : [ A, S : the number of submissions over the whole year]
- Internal updates:
    - script to import volumes and volume metadata from journals translation files into the database
    - .env file
    - New version of Crossref Schema 4.3 -> 5.3 used for DOIs

### Changed

- Accept/reject an invitation: This action is now blocked with an alert message "This paper is under revision, it is useless to review it now."

- Answer revision request: Closed [#313](https://github.com/CCSDForge/episciences/issues/313):
    - optional file attachment in "Contact without sending a new version" and "answer without any modifications".
- UX: icon to make it easier to identify the user in revision requests section

- Closed [323](https://github.com/CCSDForge/episciences/issues/323) change default label for home and Ethical charter
- Switched to [OpenAlex](https://openalex.org/) to obtain the text of citations
- Template for datasets
- Internal updates:
    - Repositories config. is now stored in Database.
    - Titles and descriptions volume's, titles and content metadata volumes are now stored as JSON

### Fixed

- Fixed [#353](https://github.com/CCSDForge/episciences/issues/353) Adding a DOI to a proceeding volume does not save the DOI status

## 1.0.40.21 - 2023-11-14

### Changed

- Volumes & Sections list:
    - display all available rows in the table (pagination disabled).
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
    - refactoring : 'yarn encore prod' needed
    - Display the total number of volumes / sections
    - display all available rows in the table (pagination disabled). The pagination feature was conflicting with the drag and drop feature.
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
- Fixed [#139](https://github.com/CCSDForge/episciences/issues/139) Put newly created volumes at the top of the list #139

## 1.0.40.19 - 2023-10-19

### Fixed

- [#392](https://github.com/CCSDForge/episciences/issues/392): Translations updated and fixed
- Fixed a bug updating dates for user profiles (User profile modification date updated automatically /!\ https://github.com/CCSDForge/episciences/blob/main/src/mysql/ALTER_USER_TABLE.sql)
- Fixed a bug with feature "Re-invite this reviewer".
- Fixed a difference between the address displayed in the profile and the one displayed when the address was modified.
- Fixed Zenodo submissions following the [major update of Zenodo on 2023-10-13](https://blog.zenodo.org/2023/10/13/2023-10-13-zenodo-rdm/).
- Fixed arXiv bibfeed

## 1.0.40.18 - 2023-10-19

## Changed

- Removed sorting option from volumes & sections tables, the feature was confusing and conflicting with another one: drag n drop to sort the volumes/sections
- The new section/volume is now inserted in the first position instead of the last.

## 1.0.40.17 - 2023-10-18

### Fixed

- Sorting volumes and sections: drag and drop doesn't always work.

## 1.0.40.16 - 2023-10-11

### Fixed

- The order of the papers was corrupted on the page listing the papers in a volume.

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
    - submitted
    - waiting for reviewing
    - accepted
    - published
    - ready to publish
    - approved by author - waiting for final publication
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
    1. Default (hidden)
    2. Public
    3. Administrator only
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
    - No (default): do not share
    - Optional: possibility to share
    - Required: sharing is required when submitting a new version and responding to a revision request without any changes
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
- Add an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings.

### New

- Updated volume import script to handle new metadata

## v1.0.41.4 - 2023-12-20

### Fixed

- [413](https://github.com/CCSDForge/episciences/issues/413): Crossref Export for bibliographical references when the reference has no DOI
- Wrong link to the paper's URL in the email template for paper's managers.
- Unable to submit a new version from Zenodo.

### Changed

- The version number for articles "formatted by the author, awaiting final version" is no longer available: the feature could prevent the author from answering the request for a final version
- [322](https://github.com/CCSDForge/episciences/issues/322) manage closing mail author
- changing right to adding orcid when you're owner of paper (before was only for paper's managers)
- [133](https://github.com/CCSDForge/episciences/issues/133) Add default style for the accepted page

### Added

- New email template: inbox_paper_submission_author_copy: Your submission made via a preprint server - Author copy.
- [#388](https://github.com/CCSDForge/episciences/issues/388): new option to require a deadline to be indicated when requesting a revision.
- [#420](https://github.com/CCSDForge/episciences/issues/420): Add bibliographical references in zbjats export

## v1.0.41.3 - 2023-12-11

### Fixed

- [414](https://github.com/CCSDForge/episciences/issues/414): volume page does not show volume description anymore
- [409](https://github.com/CCSDForge/episciences/issues/409): https://doi.org/ was automatically added to avery PIDs even if they were not DOIs

## 1.0.41.2 - 2023-12-05

### Fixed

- [408](https://github.com/CCSDForge/episciences/issues/408)"Update metadata" button seems to delete existing metadata
- Fixed Misleading error message about a document not found

### Changed

- The bibliographic references panel is now hidden when empty
- Removed cache from OpenCitation API calls when the response is empty

## 1.0.41.1 - 2023-11-28

### Changed

- [394](https://github.com/CCSDForge/episciences/issues/394): Improvements to facilitate submissions.

## 1.0.41 - 2023-11-27

### Added

- New supported servers: [bioRxiv](https://www.biorxiv.org/) and [medRxiv](https://www.medrxiv.org/) preprint servers
- New supported servers: Support for any data repositories powered by Dataverse. 2 examples are available: [DaRUS (University of Stuttgart)](https://darus.uni-stuttgart.de/) and [Recherche Data Gouv](https://recherche.data.gouv.fr/)
- New feature to automatically extract and manage bibliographical references [with a new application](https://github.com/CCSDForge/episciences-citations)
- Reviewed and accepted bibliographical references will be exported to Crossref
- [New API](https://github.com/CCSDForge/episciences-api) available for journals and internal usage

- It is possible to link documents with software, datasets and publications:
  Previously it was only available with documents hosted on HAL, the feature is now available for any repository, by adding the links on the document's page.
  As recommended, if the software is archived by [Software Heritage](https://www.softwareheritage.org/?lang=en) and a [SWHID](https://www.swhid.org/) is provided, a widget from Software Heritage may be displayed on the web page of the document, showing the archived repository
    - Fixed [#213](https://github.com/CCSDForge/episciences/issues/213): Supplementary material DOI cannot be indicated
    - Closed [#326](https://github.com/CCSDForge/episciences/issues/326): Accept SWHID reference on Episciences platform for software artifact
    - Closed [#327](https://github.com/CCSDForge/episciences/issues/327): Accept HAL reference on Episciences platform for software artifact
    - Closed [#328](https://github.com/CCSDForge/episciences/issues/328): Add a reference to the archived software artifact on reviewer's peer-reviewing form
    - Closed [#329](https://github.com/CCSDForge/episciences/issues/329): Add a reference to the archived software artifact on record landing page
    - Closed [#330](https://github.com/CCSDForge/episciences/issues/330): Add a reference to the archived software artifact on exported metadata (OpenAIRE, Crossref, etc.)
- It is now possible to add a cover letter after the initial submission
- Closed [#367](https://github.com/CCSDForge/episciences/issues/367) Display revision deadlines and make them editable:

- Author affiliations:
    - Added the affiliation acronyms in Crossref DOI and TEI export formats
    - Adding an affiliation from ROR: possibility to search by acronym
    - Closed [#374](https://github.com/CCSDForge/episciences/issues/374): ORCID and affiliations are retrieved from Zenodo

- UI/UX:
    - Document types are now displayed on the document's page(ie preprint, article, ...)
    - icon to make it easier to identify the user in revision requests section.

- Statistics, 2 new indicators:
    - The number of accepted articles (submitted in the same year) [A]
    - Acceptance rate over the whole year [(A\S)x100] : [ A, S : the number of submissions over the whole year]
- Internal updates:
    - script to import volumes and volume metadata from journals translation files into the database
    - .env file
    - New version of Crossref Schema 4.3 -> 5.3 used for DOIs

### Changed

- Accept/reject an invitation: This action is now blocked with an alert message "This paper is under revision, it is useless to review it now."

- Answer revision request: Closed [#313](https://github.com/CCSDForge/episciences/issues/313):
    - optional file attachment in "Contact without sending a new version" and "answer without any modifications".
- UX: icon to make it easier to identify the user in revision requests section

- Closed [323](https://github.com/CCSDForge/episciences/issues/323) change default label for home and Ethical charter
- Switched to [OpenAlex](https://openalex.org/) to obtain the text of citations
- Template for datasets
- Internal updates:
    - Repositories config. is now stored in Database.
    - Titles and descriptions volume's, titles and content metadata volumes are now stored as JSON

### Fixed

- Fixed [#353](https://github.com/CCSDForge/episciences/issues/353) Adding a DOI to a proceeding volume does not save the DOI status

## 1.0.40.21 - 2023-11-14

### Changed

- Volumes & Sections list:
    - display all available rows in the table (pagination disabled).
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
    - refactoring : 'yarn encore prod' needed
    - Display the total number of volumes / sections
    - display all available rows in the table (pagination disabled). The pagination feature was conflicting with the drag and drop feature.
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
- Fixed [#139](https://github.com/CCSDForge/episciences/issues/139) Put newly created volumes at the top of the list #139

## 1.0.40.19 - 2023-10-19

### Fixed

- [#392](https://github.com/CCSDForge/episciences/issues/392): Translations updated and fixed
- Fixed a bug updating dates for user profiles (User profile modification date updated automatically /!\ https://github.com/CCSDForge/episciences/blob/main/src/mysql/ALTER_USER_TABLE.sql)
- Fixed a bug with feature "Re-invite this reviewer".
- Fixed a difference between the address displayed in the profile and the one displayed when the address was modified.
- Fixed Zenodo submissions following the [major update of Zenodo on 2023-10-13](https://blog.zenodo.org/2023/10/13/2023-10-13-zenodo-rdm/).
- Fixed arXiv bibfeed

## 1.0.40.18 - 2023-10-19

## Changed

- Removed sorting option from volumes & sections tables, the feature was confusing and conflicting with another one: drag n drop to sort the volumes/sections
- The new section/volume is now inserted in the first position instead of the last.

## 1.0.40.17 - 2023-10-18

### Fixed

- Sorting volumes and sections: drag and drop doesn't always work.

## 1.0.40.16 - 2023-10-11

### Fixed

- The order of the papers was corrupted on the page listing the papers in a volume.

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
    - submitted
    - waiting for reviewing
    - accepted
    - published
    - ready to publish
    - approved by author - waiting for final publication
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
    1. Default (hidden)
    2. Public
    3. Administrator only
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
    - No (default): do not share
    - Optional: possibility to share
    - Required: sharing is required when submitting a new version and responding to a revision request without any changes
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
- Add an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings.

### New

- Updated volume import script to handle new metadata

## v1.0.41.4 - 2023-12-20

### Fixed

- [413](https://github.com/CCSDForge/episciences/issues/413): Crossref Export for bibliographical references when the reference has no DOI
- Wrong link to the paper's URL in the email template for paper's managers.
- Unable to submit a new version from Zenodo.

### Changed

- The version number for articles "formatted by the author, awaiting final version" is no longer available: the feature could prevent the author from answering the request for a final version
- [322](https://github.com/CCSDForge/episciences/issues/322) manage closing mail author
- changing right to adding orcid when you're owner of paper (before was only for paper's managers)
- [133](https://github.com/CCSDForge/episciences/issues/133) Add default style for the accepted page

### Added

- New email template: inbox_paper_submission_author_copy: Your submission made via a preprint server - Author copy.
- [#388](https://github.com/CCSDForge/episciences/issues/388): new option to require a deadline to be indicated when requesting a revision.
- [#420](https://github.com/CCSDForge/episciences/issues/420): Add bibliographical references in zbjats export

## v1.0.41.3 - 2023-12-11

### Fixed

- [414](https://github.com/CCSDForge/episciences/issues/414): volume page does not show volume description anymore
- [409](https://github.com/CCSDForge/episciences/issues/409): https://doi.org/ was automatically added to avery PIDs even if they were not DOIs

## 1.0.41.2 - 2023-12-05

### Fixed

- [408](https://github.com/CCSDForge/episciences/issues/408)"Update metadata" button seems to delete existing metadata
- Fixed Misleading error message about a document not found

### Changed

- The bibliographic references panel is now hidden when empty
- Removed cache from OpenCitation API calls when the response is empty

## 1.0.41.1 - 2023-11-28

### Changed

- [394](https://github.com/CCSDForge/episciences/issues/394): Improvements to facilitate submissions.

## 1.0.41 - 2023-11-27

### Added

- New supported servers: [bioRxiv](https://www.biorxiv.org/) and [medRxiv](https://www.medrxiv.org/) preprint servers
- New supported servers: Support for any data repositories powered by Dataverse. 2 examples are available: [DaRUS (University of Stuttgart)](https://darus.uni-stuttgart.de/) and [Recherche Data Gouv](https://recherche.data.gouv.fr/)
- New feature to automatically extract and manage bibliographical references [with a new application](https://github.com/CCSDForge/episciences-citations)
- Reviewed and accepted bibliographical references will be exported to Crossref
- [New API](https://github.com/CCSDForge/episciences-api) available for journals and internal usage

- It is possible to link documents with software, datasets and publications:
  Previously it was only available with documents hosted on HAL, the feature is now available for any repository, by adding the links on the document's page.
  As recommended, if the software is archived by [Software Heritage](https://www.softwareheritage.org/?lang=en) and a [SWHID](https://www.swhid.org/) is provided, a widget from Software Heritage may be displayed on the web page of the document, showing the archived repository
    - Fixed [#213](https://github.com/CCSDForge/episciences/issues/213): Supplementary material DOI cannot be indicated
    - Closed [#326](https://github.com/CCSDForge/episciences/issues/326): Accept SWHID reference on Episciences platform for software artifact
    - Closed [#327](https://github.com/CCSDForge/episciences/issues/327): Accept HAL reference on Episciences platform for software artifact
    - Closed [#328](https://github.com/CCSDForge/episciences/issues/328): Add a reference to the archived software artifact on reviewer's peer-reviewing form
    - Closed [#329](https://github.com/CCSDForge/episciences/issues/329): Add a reference to the archived software artifact on record landing page
    - Closed [#330](https://github.com/CCSDForge/episciences/issues/330): Add a reference to the archived software artifact on exported metadata (OpenAIRE, Crossref, etc.)
- It is now possible to add a cover letter after the initial submission
- Closed [#367](https://github.com/CCSDForge/episciences/issues/367) Display revision deadlines and make them editable:

- Author affiliations:
    - Added the affiliation acronyms in Crossref DOI and TEI export formats
    - Adding an affiliation from ROR: possibility to search by acronym
    - Closed [#374](https://github.com/CCSDForge/episciences/issues/374): ORCID and affiliations are retrieved from Zenodo

- UI/UX:
    - Document types are now displayed on the document's page(ie preprint, article, ...)
    - icon to make it easier to identify the user in revision requests section.

- Statistics, 2 new indicators:
    - The number of accepted articles (submitted in the same year) [A]
    - Acceptance rate over the whole year [(A\S)x100] : [ A, S : the number of submissions over the whole year]
- Internal updates:
    - script to import volumes and volume metadata from journals translation files into the database
    - .env file
    - New version of Crossref Schema 4.3 -> 5.3 used for DOIs

### Changed

- Accept/reject an invitation: This action is now blocked with an alert message "This paper is under revision, it is useless to review it now."

- Answer revision request: Closed [#313](https://github.com/CCSDForge/episciences/issues/313):
    - optional file attachment in "Contact without sending a new version" and "answer without any modifications".
- UX: icon to make it easier to identify the user in revision requests section

- Closed [323](https://github.com/CCSDForge/episciences/issues/323) change default label for home and Ethical charter
- Switched to [OpenAlex](https://openalex.org/) to obtain the text of citations
- Template for datasets
- Internal updates:
    - Repositories config. is now stored in Database.
    - Titles and descriptions volume's, titles and content metadata volumes are now stored as JSON

### Fixed

- Fixed [#353](https://github.com/CCSDForge/episciences/issues/353) Adding a DOI to a proceeding volume does not save the DOI status

## 1.0.40.21 - 2023-11-14

### Changed

- Volumes & Sections list:
    - display all available rows in the table (pagination disabled).
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
    - refactoring : 'yarn encore prod' needed
    - Display the total number of volumes / sections
    - display all available rows in the table (pagination disabled). The pagination feature was conflicting with the drag and drop feature.
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
- Fixed [#139](https://github.com/CCSDForge/episciences/issues/139) Put newly created volumes at the top of the list #139

## 1.0.40.19 - 2023-10-19

### Fixed

- [#392](https://github.com/CCSDForge/episciences/issues/392): Translations updated and fixed
- Fixed a bug updating dates for user profiles (User profile modification date updated automatically /!\ https://github.com/CCSDForge/episciences/blob/main/src/mysql/ALTER_USER_TABLE.sql)
- Fixed a bug with feature "Re-invite this reviewer".
- Fixed a difference between the address displayed in the profile and the one displayed when the address was modified.
- Fixed Zenodo submissions following the [major update of Zenodo on 2023-10-13](https://blog.zenodo.org/2023/10/13/2023-10-13-zenodo-rdm/).
- Fixed arXiv bibfeed

## 1.0.40.18 - 2023-10-19

## Changed

- Removed sorting option from volumes & sections tables, the feature was confusing and conflicting with another one: drag n drop to sort the volumes/sections
- The new section/volume is now inserted in the first position instead of the last.

## 1.0.40.17 - 2023-10-18

### Fixed

- Sorting volumes and sections: drag and drop doesn't always work.

## 1.0.40.16 - 2023-10-11

### Fixed

- The order of the papers was corrupted on the page listing the papers in a volume.

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
    - submitted
    - waiting for reviewing
    - accepted
    - published
    - ready to publish
    - approved by author - waiting for final publication
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
    1. Default (hidden)
    2. Public
    3. Administrator only
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
    - No (default): do not share
    - Optional: possibility to share
    - Required: sharing is required when submitting a new version and responding to a revision request without any changes
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
- Add an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings.

### New

- Updated volume import script to handle new metadata

## v1.0.41.4 - 2023-12-20

### Fixed

- [413](https://github.com/CCSDForge/episciences/issues/413): Crossref Export for bibliographical references when the reference has no DOI
- Wrong link to the paper's URL in the email template for paper's managers.
- Unable to submit a new version from Zenodo.

### Changed

- The version number for articles "formatted by the author, awaiting final version" is no longer available: the feature could prevent the author from answering the request for a final version
- [322](https://github.com/CCSDForge/episciences/issues/322) manage closing mail author
- changing right to adding orcid when you're owner of paper (before was only for paper's managers)
- [133](https://github.com/CCSDForge/episciences/issues/133) Add default style for the accepted page

### Added

- New email template: inbox_paper_submission_author_copy: Your submission made via a preprint server - Author copy.
- [#388](https://github.com/CCSDForge/episciences/issues/388): new option to require a deadline to be indicated when requesting a revision.
- [#420](https://github.com/CCSDForge/episciences/issues/420): Add bibliographical references in zbjats export

## v1.0.41.3 - 2023-12-11

### Fixed

- [414](https://github.com/CCSDForge/episciences/issues/414): volume page does not show volume description anymore
- [409](https://github.com/CCSDForge/episciences/issues/409): https://doi.org/ was automatically added to avery PIDs even if they were not DOIs

## 1.0.41.2 - 2023-12-05

### Fixed

- [408](https://github.com/CCSDForge/episciences/issues/408)"Update metadata" button seems to delete existing metadata
- Fixed Misleading error message about a document not found

### Changed

- The bibliographic references panel is now hidden when empty
- Removed cache from OpenCitation API calls when the response is empty

## 1.0.41.1 - 2023-11-28

### Changed

- [394](https://github.com/CCSDForge/episciences/issues/394): Improvements to facilitate submissions.

## 1.0.41 - 2023-11-27

### Added

- New supported servers: [bioRxiv](https://www.biorxiv.org/) and [medRxiv](https://www.medrxiv.org/) preprint servers
- New supported servers: Support for any data repositories powered by Dataverse. 2 examples are available: [DaRUS (University of Stuttgart)](https://darus.uni-stuttgart.de/) and [Recherche Data Gouv](https://recherche.data.gouv.fr/)
- New feature to automatically extract and manage bibliographical references [with a new application](https://github.com/CCSDForge/episciences-citations)
- Reviewed and accepted bibliographical references will be exported to Crossref
- [New API](https://github.com/CCSDForge/episciences-api) available for journals and internal usage

- It is possible to link documents with software, datasets and publications:
  Previously it was only available with documents hosted on HAL, the feature is now available for any repository, by adding the links on the document's page.
  As recommended, if the software is archived by [Software Heritage](https://www.softwareheritage.org/?lang=en) and a [SWHID](https://www.swhid.org/) is provided, a widget from Software Heritage may be displayed on the web page of the document, showing the archived repository
    - Fixed [#213](https://github.com/CCSDForge/episciences/issues/213): Supplementary material DOI cannot be indicated
    - Closed [#326](https://github.com/CCSDForge/episciences/issues/326): Accept SWHID reference on Episciences platform for software artifact
    - Closed [#327](https://github.com/CCSDForge/episciences/issues/327): Accept HAL reference on Episciences platform for software artifact
    - Closed [#328](https://github.com/CCSDForge/episciences/issues/328): Add a reference to the archived software artifact on reviewer's peer-reviewing form
    - Closed [#329](https://github.com/CCSDForge/episciences/issues/329): Add a reference to the archived software artifact on record landing page
    - Closed [#330](https://github.com/CCSDForge/episciences/issues/330): Add a reference to the archived software artifact on exported metadata (OpenAIRE, Crossref, etc.)
- It is now possible to add a cover letter after the initial submission
- Closed [#367](https://github.com/CCSDForge/episciences/issues/367) Display revision deadlines and make them editable:

- Author affiliations:
    - Added the affiliation acronyms in Crossref DOI and TEI export formats
    - Adding an affiliation from ROR: possibility to search by acronym
    - Closed [#374](https://github.com/CCSDForge/episciences/issues/374): ORCID and affiliations are retrieved from Zenodo

- UI/UX:
    - Document types are now displayed on the document's page(ie preprint, article, ...)
    - icon to make it easier to identify the user in revision requests section.

- Statistics, 2 new indicators:
    - The number of accepted articles (submitted in the same year) [A]
    - Acceptance rate over the whole year [(A\S)x100] : [ A, S : the number of submissions over the whole year]
- Internal updates:
    - script to import volumes and volume metadata from journals translation files into the database
    - .env file
    - New version of Crossref Schema 4.3 -> 5.3 used for DOIs

### Changed

- Accept/reject an invitation: This action is now blocked with an alert message "This paper is under revision, it is useless to review it now."

- Answer revision request: Closed [#313](https://github.com/CCSDForge/episciences/issues/313):
    - optional file attachment in "Contact without sending a new version" and "answer without any modifications".
- UX: icon to make it easier to identify the user in revision requests section

- Closed [323](https://github.com/CCSDForge/episciences/issues/323) change default label for home and Ethical charter
- Switched to [OpenAlex](https://openalex.org/) to obtain the text of citations
- Template for datasets
- Internal updates:
    - Repositories config. is now stored in Database.
    - Titles and descriptions volume's, titles and content metadata volumes are now stored as JSON

### Fixed

- Fixed [#353](https://github.com/CCSDForge/episciences/issues/353) Adding a DOI to a proceeding volume does not save the DOI status

## 1.0.40.21 - 2023-11-14

### Changed

- Volumes & Sections list:
    - display all available rows in the table (pagination disabled).
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
    - refactoring : 'yarn encore prod' needed
    - Display the total number of volumes / sections
    - display all available rows in the table (pagination disabled). The pagination feature was conflicting with the drag and drop feature.
    - using the search filter: in this case, drag & drop repositions all displayed items, making them appear at the top of the list
    - bump jqueryui to 1.13.2
- Fixed [#139](https://github.com/CCSDForge/episciences/issues/139) Put newly created volumes at the top of the list #139

## 1.0.40.19 - 2023-10-19

### Fixed

- [#392](https://github.com/CCSDForge/episciences/issues/392): Translations updated and fixed
- Fixed a bug updating dates for user profiles (User profile modification date updated automatically /!\ https://github.com/CCSDForge/episciences/blob/main/src/mysql/ALTER_USER_TABLE.sql)
- Fixed a bug with feature "Re-invite this reviewer".
- Fixed a difference between the address displayed in the profile and the one displayed when the address was modified.
- Fixed Zenodo submissions following the [major update of Zenodo on 2023-10-13](https://blog.zenodo.org/2023/10/13/2023-10-13-zenodo-rdm/).
- Fixed arXiv bibfeed

## 1.0.40.18 - 2023-10-19

## Changed

- Removed sorting option from volumes & sections tables, the feature was confusing and conflicting with another one: drag n drop to sort the volumes/sections
- The new section/volume is now inserted in the first position instead of the last.

## 1.0.40.17 - 2023-10-18

### Fixed

- Sorting volumes and sections: drag and drop doesn't always work.

## 1.0.40.16 - 2023-10-11

### Fixed

- The order of the papers was corrupted on the page listing the papers in a volume.

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
    - submitted
    - waiting for reviewing
    - accepted
    - published
    - ready to publish
    - approved by author - waiting for final publication
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
    1. Default (hidden)
    2. Public
    3. Administrator only
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
    - No (default): do not share
    - Optional: possibility to share
    - Required: sharing is required when submitting a new version and responding to a revision request without any changes
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
- Add an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings.

### New

- Updated volume import script to handle new metadata

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
    - submitted
    - waiting for reviewing
    - accepted
    - published
    - ready to publish
    - approved by author - waiting for final publication
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
    1. Default (hidden)
    2. Public
    3. Administrator only
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
    - No (default): do not share
    - Optional: possibility to share
    - Required: sharing is required when submitting a new version and responding to a revision request without any changes
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
- Add an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings.

### New

- Updated volume import script to handle new metadata
