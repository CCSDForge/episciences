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
- Update paper stats only if paper is published and user is not the contributor
- Add new document metadata: acceptance date
- Do not display the document meta. 'keywords' if its value is empty
- Report score rounding (RT#135523): propagate the fix (git#389)
- Edit a template via a get request: fix Application error : SQLSTATE[HY093]: Invalid parameter number
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
