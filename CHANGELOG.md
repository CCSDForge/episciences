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


## Unreleased

### Added
- Add release version on the interface (dashboard and page footer) #116
- Editors' choice at time of submission: informative text added. (gitlab #369)
- Browse by Volume or Sections : Handle new content type 'application/json' to return json instead of html
- Fix #143 Allow to switch off manual or automatic DOI assignation (disabled mode)
- [#37](https://github.com/CCSDForge/episciences/issues/37)
  - On URLs like /volume/view/id/314159 and /section/view/id/314159 Handle new content type 'application/json' to return json instead of html (only published content)
  - On URLs like /volume/edit?id=314159 Handle new content type 'application/json' to return json instead of html but including all statuses of articles ; an authenticated and allowed user is required
  - JSON added to the list of public export formats
- added: info message for the stats.

### Changed
- Make the version number more explicit when proposing a new version
- Refactoring
- Dumping default data for table `MAIL_TEMPLATE`

### Fixed
- Fixed mailing bug: loss of the mail when you forget to put a recipient (gitlab #343)
- Fixed: Sending emails: language of the sender and the recipient could be a problem if the language of the sender is different from the language of the recipient (gitlab #402)
- Fixed: IDE warnings
- Fixed: Inconsistent REMINDER_DELAY: the displayed value is the "delay" parameter of the reminder instead of the calculated value (interval in number of days between the time the reminder was sent and the deadline.
- Fixed: new submission: "submission date" metadata is empty: do not display it anymore.
- Fixed: [#124](https://github.com/CCSDForge/episciences/issues/124): refining automatic notifications when abandoning submission
- Fixed: RT#144252: cleanup of attached file names
- Fixed: RT#144246: an inactive account remains visible on the Editorial Staff members page. 
- Fixed: [#408](https://github.com/CCSDForge/episciences/issues/408): An empty e-mail is not sent or received
- Fixed [#129](https://github.com/CCSDForge/episciences/issues/129): when you do a search from the mail history page nothing happens.
- Fixed [#141](https://github.com/CCSDForge/episciences/issues/141): visibility of pages menu is wrong.

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
