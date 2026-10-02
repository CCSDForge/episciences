# Change Log - 2025

All notable changes to this project for 2025.

## v1.0.53 - 2025-12-03

The changelog was not updated for this tag, but we keep the tag here as a reminder

## v1.0.52 - 2025-08-28

### Fixed

- RT#279519, TR#279601: Dates prior to the reviewing deadline were not permitted; however, it is now possible to change the reviewing deadline between the minimum and maximum dates.
- DOI metadata: stop overwriting acceptance date with the last modification date
- [#693](https://github.com/CCSDForge/episciences/issues/693) allow to save comment without attachment
- [#690](https://github.com/CCSDForge/episciences/issues/690) ORCID might be duplicated when one of the authors has no ORCID
- [#702](https://github.com/CCSDForge/episciences/issues/702) Inclusion of arxiv article version invalidates document identifier
- When updating Authors' affiliations, redirect to the latest paper version, not the 1st version
- [#629](https://github.com/CCSDForge/episciences/issues/629) Malfunction when updating volume description
- [#639](https://github.com/CCSDForge/episciences/issues/639) DOAJ Export unavailable. Added a function to Convert between ISO 639-2/T and ISO 639-2/B codes. DOAJ is in the team iso_639-2b whereas our current tools create iso_639-2t not supported by the DOAJ Schema
- Fixed unescaped identifiers causing invalid XML in OpenAire export format
- [#600](https://github.com/CCSDForge/episciences/issues/600) Abstract ignores line breaks
- [#694](https://github.com/CCSDForge/episciences/issues/694) accents via LaTeX macros in abstracts aren't rendered
- [#541](https://github.com/CCSDForge/episciences/issues/541) remove user assignation (editor) when a user is removed from the journal

### Added

- DOI management automation: Added shell scripts for batch DOI operations and enhanced getDoi.php with logging and journal fetching capabilities
- CSV import functionality for sections
- When pasting URL for repository identifiers, the URL is automatically cleaned to keep only the identifier, if the version number is in the URL, it is also automatically added into the "Version" field
- Javascript Tests with Jest
- PHP tests with Phpunit (updated test + new tests)
- Prettier to format Javascript
- Configured GitHub actions for CI (PHP+JS tests)
- Updated Renovate and Dependabot tests to target staging branch
- New Makefile with reorganised commands + new commands
- Document view:
    - Now displaying the abstract in all languages we have, prefixed with the language code
    - Now displaying keywords prefixed with their language code
- Relationships module:
    - added logs details for added/removed values
    - improved content escaping

### Changed

- Refactored some Javascript with more modern approach
- Editor Comments: in case conflict of interests is enabled in the journal, editors will receive the comments even if they have not yet answered to COI. If they declare a COI, they will be unassigned and will stop receiving editors comments
- Crossref title export: in case of multiple titles, the priority is given to the title in the language of the document or fallback to the fist title if no language
- [#657](https://github.com/CCSDForge/episciences/issues/657) Move "Download this file" button a bit and rename "Download this paper"

## v1.0.51 - 2025-04-07

### Changed

- [#650](https://github.com/CCSDForge/episciences/issues/650): send notifications when a cover letter is added or edited
- published dataset/software: the title of the data/software descriptor is its identifier.
- Only PDF files are backed up
- [#649](https://github.com/CCSDForge/episciences/issues/649): Assigned editors are now automatically added as (hidden) copies of messages sent to reviewers
- A copy of the default grid is proposed if the default grid is not empty, and if the destination grid is not empty either.

### Fixed

- [#653](https://github.com/CCSDForge/episciences/issues/653): Filtering email history doesn't work
- File Not Found Error
- Wrong link on page "Invitation to review an article" for temporary versions

## v1.0.50.1 - 2025-03-14

### Fixed

- Fixed CACHE_PATH not existing

### Changed

- Button label renamed for datasets/software papers

## v1.0.50 - 2025-03-13

### Added

- [#651](https://github.com/CCSDForge/episciences/issues/651): Statistics on invited and submitted referee reports

### Fixed

- Duplicate insertions when updating metadata
- [RT#238873]: it is now possible to replace an article with more recent version if its status is "Ready to publish"
- The average rating is displayed twice.
- [#654](https://github.com/CCSDForge/episciences/issues/654): Unable to modify a user's profile.
- [RT#237714]: The file renamed after downloading was not taken into account, which broke the link with this file.
  This bug affected the new version of the cover letter.
- IDE Warning: Method 'getVersion' not found in array|null

## v1.0.49 - 2025-02-19

### Added

- data descriptor viewer for data papers and software papers

### Changed

- Force the article type to "Article" only if it is a preprint when published,
- Improved licence enrichment for dataset

### Fixed

- Updating the volume number and year no longer works.
- [#652](https://github.com/CCSDForge/episciences/issues/652): missing translation
- RT#235731: failed to submit manuscript via ArXiv.
- Cancelling a file download does not work.
- Website resources: the file to be downloaded is saved repeatedly each time the page is refreshed.

## v1.0.48 - 2025-02-06

### Changed

- It is no longer possible to delete files attached to the temporary version.
- Metadata updates will take account of author enrichment, except for arXiv articles.
- It is now possible to select a new version when the article status is: Copy ed.: final version submitted, waiting for validation
- Version panel: temporary versions are now labeled as temporary

### Added

- It is now possible to attach a data/software descriptor to a submission if the submission type is Dataset or Software.
- New script to auto-declare conflicts of interest for journals activating the feature with an important backlog of articles
- New button on volume pages to download all the PDF files at once with a single PDF file (at the moment only available after login)

### Fixed

- The response to a modification request via "Contact without sending a new version" did not appear on the article page.
- Wrong links for the Dataverse repository with versioned datasets
- The graphical abstract was no longer displayed
- Volume's metadata was displayed twice

## v1.0.47 - 2025-01-08

### Added

- A script to generate sitemaps for the sites using the new UI/UX

### Fixed

- The graphical abstract is no longer displayed.
- When the "conflict of interest" option is activated reviewers are no longer visible for articles which the absence of conflict has been confirmed.
- The small logo to the left of each article's permanent identifier will only appear if at least one conflict has been confirmed (Nb. If you hover over this logo, you will see the number of conflicts reported for that article)

## v1.0.46.3 - 2025-01-06

### Fixed

- Failed to change email address for user profiles without affiliations.

## v1.0.46.2 - 2025-01-03

### Added

- classifications and project title metadata in the document's JSON export.
- improved scripts for importing and processing classifications

### Fixed

- [RT#229027]: Multiple files attached to the temporary version: only one file is visible on the article page.
- Prevent having empty roles when the last user role is deleted

## v1.0.53 - 2025-12-03

The changelog was not updated for this tag, but we keep the tag here as a reminder

## v1.0.52 - 2025-08-28

### Fixed

- RT#279519, TR#279601: Dates prior to the reviewing deadline were not permitted; however, it is now possible to change the reviewing deadline between the minimum and maximum dates.
- DOI metadata: stop overwriting acceptance date with the last modification date
- [#693](https://github.com/CCSDForge/episciences/issues/693) allow to save comment without attachment
- [#690](https://github.com/CCSDForge/episciences/issues/690) ORCID might be duplicated when one of the authors has no ORCID
- [#702](https://github.com/CCSDForge/episciences/issues/702) Inclusion of arxiv article version invalidates document identifier
- When updating Authors' affiliations, redirect to the latest paper version, not the 1st version
- [#629](https://github.com/CCSDForge/episciences/issues/629) Malfunction when updating volume description
- [#639](https://github.com/CCSDForge/episciences/issues/639) DOAJ Export unavailable. Added a function to Convert between ISO 639-2/T and ISO 639-2/B codes. DOAJ is in the team iso_639-2b whereas our current tools create iso_639-2t not supported by the DOAJ Schema
- Fixed unescaped identifiers causing invalid XML in OpenAire export format
- [#600](https://github.com/CCSDForge/episciences/issues/600) Abstract ignores line breaks
- [#694](https://github.com/CCSDForge/episciences/issues/694) accents via LaTeX macros in abstracts aren't rendered
- [#541](https://github.com/CCSDForge/episciences/issues/541) remove user assignation (editor) when a user is removed from the journal

### Added

- DOI management automation: Added shell scripts for batch DOI operations and enhanced getDoi.php with logging and journal fetching capabilities
- CSV import functionality for sections
- When pasting URL for repository identifiers, the URL is automatically cleaned to keep only the identifier, if the version number is in the URL, it is also automatically added into the "Version" field
- Javascript Tests with Jest
- PHP tests with Phpunit (updated test + new tests)
- Prettier to format Javascript
- Configured GitHub actions for CI (PHP+JS tests)
- Updated Renovate and Dependabot tests to target staging branch
- New Makefile with reorganised commands + new commands
- Document view:
    - Now displaying the abstract in all languages we have, prefixed with the language code
    - Now displaying keywords prefixed with their language code
- Relationships module:
    - added logs details for added/removed values
    - improved content escaping

### Changed

- Refactored some Javascript with more modern approach
- Editor Comments: in case conflict of interests is enabled in the journal, editors will receive the comments even if they have not yet answered to COI. If they declare a COI, they will be unassigned and will stop receiving editors comments
- Crossref title export: in case of multiple titles, the priority is given to the title in the language of the document or fallback to the fist title if no language
- [#657](https://github.com/CCSDForge/episciences/issues/657) Move "Download this file" button a bit and rename "Download this paper"

## v1.0.51 - 2025-04-07

### Changed

- [#650](https://github.com/CCSDForge/episciences/issues/650): send notifications when a cover letter is added or edited
- published dataset/software: the title of the data/software descriptor is its identifier.
- Only PDF files are backed up
- [#649](https://github.com/CCSDForge/episciences/issues/649): Assigned editors are now automatically added as (hidden) copies of messages sent to reviewers
- A copy of the default grid is proposed if the default grid is not empty, and if the destination grid is not empty either.

### Fixed

- [#653](https://github.com/CCSDForge/episciences/issues/653): Filtering email history doesn't work
- File Not Found Error
- Wrong link on page "Invitation to review an article" for temporary versions

## v1.0.50.1 - 2025-03-14

### Fixed

- Fixed CACHE_PATH not existing

### Changed

- Button label renamed for datasets/software papers

## v1.0.50 - 2025-03-13

### Added

- [#651](https://github.com/CCSDForge/episciences/issues/651): Statistics on invited and submitted referee reports

### Fixed

- Duplicate insertions when updating metadata
- [RT#238873]: it is now possible to replace an article with more recent version if its status is "Ready to publish"
- The average rating is displayed twice.
- [#654](https://github.com/CCSDForge/episciences/issues/654): Unable to modify a user's profile.
- [RT#237714]: The file renamed after downloading was not taken into account, which broke the link with this file.
  This bug affected the new version of the cover letter.
- IDE Warning: Method 'getVersion' not found in array|null

## v1.0.49 - 2025-02-19

### Added

- data descriptor viewer for data papers and software papers

### Changed

- Force the article type to "Article" only if it is a preprint when published,
- Improved licence enrichment for dataset

### Fixed

- Updating the volume number and year no longer works.
- [#652](https://github.com/CCSDForge/episciences/issues/652): missing translation
- RT#235731: failed to submit manuscript via ArXiv.
- Cancelling a file download does not work.
- Website resources: the file to be downloaded is saved repeatedly each time the page is refreshed.

## v1.0.48 - 2025-02-06

### Changed

- It is no longer possible to delete files attached to the temporary version.
- Metadata updates will take account of author enrichment, except for arXiv articles.
- It is now possible to select a new version when the article status is: Copy ed.: final version submitted, waiting for validation
- Version panel: temporary versions are now labeled as temporary

### Added

- It is now possible to attach a data/software descriptor to a submission if the submission type is Dataset or Software.
- New script to auto-declare conflicts of interest for journals activating the feature with an important backlog of articles
- New button on volume pages to download all the PDF files at once with a single PDF file (at the moment only available after login)

### Fixed

- The response to a modification request via "Contact without sending a new version" did not appear on the article page.
- Wrong links for the Dataverse repository with versioned datasets
- The graphical abstract was no longer displayed
- Volume's metadata was displayed twice

## v1.0.47 - 2025-01-08

### Added

- A script to generate sitemaps for the sites using the new UI/UX

### Fixed

- The graphical abstract is no longer displayed.
- When the "conflict of interest" option is activated reviewers are no longer visible for articles which the absence of conflict has been confirmed.
- The small logo to the left of each article's permanent identifier will only appear if at least one conflict has been confirmed (Nb. If you hover over this logo, you will see the number of conflicts reported for that article)

## v1.0.46.3 - 2025-01-06

### Fixed

- Failed to change email address for user profiles without affiliations.

## v1.0.46.2 - 2025-01-03

### Added

- classifications and project title metadata in the document's JSON export.
- improved scripts for importing and processing classifications

### Fixed

- [RT#229027]: Multiple files attached to the temporary version: only one file is visible on the article page.
- Prevent having empty roles when the last user role is deleted

## v1.0.53 - 2025-12-03

The changelog was not updated for this tag, but we keep the tag here as a reminder

## v1.0.52 - 2025-08-28

### Fixed

- RT#279519, TR#279601: Dates prior to the reviewing deadline were not permitted; however, it is now possible to change the reviewing deadline between the minimum and maximum dates.
- DOI metadata: stop overwriting acceptance date with the last modification date
- [#693](https://github.com/CCSDForge/episciences/issues/693) allow to save comment without attachment
- [#690](https://github.com/CCSDForge/episciences/issues/690) ORCID might be duplicated when one of the authors has no ORCID
- [#702](https://github.com/CCSDForge/episciences/issues/702) Inclusion of arxiv article version invalidates document identifier
- When updating Authors' affiliations, redirect to the latest paper version, not the 1st version
- [#629](https://github.com/CCSDForge/episciences/issues/629) Malfunction when updating volume description
- [#639](https://github.com/CCSDForge/episciences/issues/639) DOAJ Export unavailable. Added a function to Convert between ISO 639-2/T and ISO 639-2/B codes. DOAJ is in the team iso_639-2b whereas our current tools create iso_639-2t not supported by the DOAJ Schema
- Fixed unescaped identifiers causing invalid XML in OpenAire export format
- [#600](https://github.com/CCSDForge/episciences/issues/600) Abstract ignores line breaks
- [#694](https://github.com/CCSDForge/episciences/issues/694) accents via LaTeX macros in abstracts aren't rendered
- [#541](https://github.com/CCSDForge/episciences/issues/541) remove user assignation (editor) when a user is removed from the journal

### Added

- DOI management automation: Added shell scripts for batch DOI operations and enhanced getDoi.php with logging and journal fetching capabilities
- CSV import functionality for sections
- When pasting URL for repository identifiers, the URL is automatically cleaned to keep only the identifier, if the version number is in the URL, it is also automatically added into the "Version" field
- Javascript Tests with Jest
- PHP tests with Phpunit (updated test + new tests)
- Prettier to format Javascript
- Configured GitHub actions for CI (PHP+JS tests)
- Updated Renovate and Dependabot tests to target staging branch
- New Makefile with reorganised commands + new commands
- Document view:
    - Now displaying the abstract in all languages we have, prefixed with the language code
    - Now displaying keywords prefixed with their language code
- Relationships module:
    - added logs details for added/removed values
    - improved content escaping

### Changed

- Refactored some Javascript with more modern approach
- Editor Comments: in case conflict of interests is enabled in the journal, editors will receive the comments even if they have not yet answered to COI. If they declare a COI, they will be unassigned and will stop receiving editors comments
- Crossref title export: in case of multiple titles, the priority is given to the title in the language of the document or fallback to the fist title if no language
- [#657](https://github.com/CCSDForge/episciences/issues/657) Move "Download this file" button a bit and rename "Download this paper"

## v1.0.51 - 2025-04-07

### Changed

- [#650](https://github.com/CCSDForge/episciences/issues/650): send notifications when a cover letter is added or edited
- published dataset/software: the title of the data/software descriptor is its identifier.
- Only PDF files are backed up
- [#649](https://github.com/CCSDForge/episciences/issues/649): Assigned editors are now automatically added as (hidden) copies of messages sent to reviewers
- A copy of the default grid is proposed if the default grid is not empty, and if the destination grid is not empty either.

### Fixed

- [#653](https://github.com/CCSDForge/episciences/issues/653): Filtering email history doesn't work
- File Not Found Error
- Wrong link on page "Invitation to review an article" for temporary versions

## v1.0.50.1 - 2025-03-14

### Fixed

- Fixed CACHE_PATH not existing

### Changed

- Button label renamed for datasets/software papers

## v1.0.50 - 2025-03-13

### Added

- [#651](https://github.com/CCSDForge/episciences/issues/651): Statistics on invited and submitted referee reports

### Fixed

- Duplicate insertions when updating metadata
- [RT#238873]: it is now possible to replace an article with more recent version if its status is "Ready to publish"
- The average rating is displayed twice.
- [#654](https://github.com/CCSDForge/episciences/issues/654): Unable to modify a user's profile.
- [RT#237714]: The file renamed after downloading was not taken into account, which broke the link with this file.
  This bug affected the new version of the cover letter.
- IDE Warning: Method 'getVersion' not found in array|null

## v1.0.49 - 2025-02-19

### Added

- data descriptor viewer for data papers and software papers

### Changed

- Force the article type to "Article" only if it is a preprint when published,
- Improved licence enrichment for dataset

### Fixed

- Updating the volume number and year no longer works.
- [#652](https://github.com/CCSDForge/episciences/issues/652): missing translation
- RT#235731: failed to submit manuscript via ArXiv.
- Cancelling a file download does not work.
- Website resources: the file to be downloaded is saved repeatedly each time the page is refreshed.

## v1.0.48 - 2025-02-06

### Changed

- It is no longer possible to delete files attached to the temporary version.
- Metadata updates will take account of author enrichment, except for arXiv articles.
- It is now possible to select a new version when the article status is: Copy ed.: final version submitted, waiting for validation
- Version panel: temporary versions are now labeled as temporary

### Added

- It is now possible to attach a data/software descriptor to a submission if the submission type is Dataset or Software.
- New script to auto-declare conflicts of interest for journals activating the feature with an important backlog of articles
- New button on volume pages to download all the PDF files at once with a single PDF file (at the moment only available after login)

### Fixed

- The response to a modification request via "Contact without sending a new version" did not appear on the article page.
- Wrong links for the Dataverse repository with versioned datasets
- The graphical abstract was no longer displayed
- Volume's metadata was displayed twice

## v1.0.47 - 2025-01-08

### Added

- A script to generate sitemaps for the sites using the new UI/UX

### Fixed

- The graphical abstract is no longer displayed.
- When the "conflict of interest" option is activated reviewers are no longer visible for articles which the absence of conflict has been confirmed.
- The small logo to the left of each article's permanent identifier will only appear if at least one conflict has been confirmed (Nb. If you hover over this logo, you will see the number of conflicts reported for that article)

## v1.0.46.3 - 2025-01-06

### Fixed

- Failed to change email address for user profiles without affiliations.

## v1.0.46.2 - 2025-01-03

### Added

- classifications and project title metadata in the document's JSON export.
- improved scripts for importing and processing classifications

### Fixed

- [RT#229027]: Multiple files attached to the temporary version: only one file is visible on the article page.
- Prevent having empty roles when the last user role is deleted

## v1.0.53 - 2025-12-03

The changelog was not updated for this tag, but we keep the tag here as a reminder

## v1.0.52 - 2025-08-28

### Fixed

- RT#279519, TR#279601: Dates prior to the reviewing deadline were not permitted; however, it is now possible to change the reviewing deadline between the minimum and maximum dates.
- DOI metadata: stop overwriting acceptance date with the last modification date
- [#693](https://github.com/CCSDForge/episciences/issues/693) allow to save comment without attachment
- [#690](https://github.com/CCSDForge/episciences/issues/690) ORCID might be duplicated when one of the authors has no ORCID
- [#702](https://github.com/CCSDForge/episciences/issues/702) Inclusion of arxiv article version invalidates document identifier
- When updating Authors' affiliations, redirect to the latest paper version, not the 1st version
- [#629](https://github.com/CCSDForge/episciences/issues/629) Malfunction when updating volume description
- [#639](https://github.com/CCSDForge/episciences/issues/639) DOAJ Export unavailable. Added a function to Convert between ISO 639-2/T and ISO 639-2/B codes. DOAJ is in the team iso_639-2b whereas our current tools create iso_639-2t not supported by the DOAJ Schema
- Fixed unescaped identifiers causing invalid XML in OpenAire export format
- [#600](https://github.com/CCSDForge/episciences/issues/600) Abstract ignores line breaks
- [#694](https://github.com/CCSDForge/episciences/issues/694) accents via LaTeX macros in abstracts aren't rendered
- [#541](https://github.com/CCSDForge/episciences/issues/541) remove user assignation (editor) when a user is removed from the journal

### Added

- DOI management automation: Added shell scripts for batch DOI operations and enhanced getDoi.php with logging and journal fetching capabilities
- CSV import functionality for sections
- When pasting URL for repository identifiers, the URL is automatically cleaned to keep only the identifier, if the version number is in the URL, it is also automatically added into the "Version" field
- Javascript Tests with Jest
- PHP tests with Phpunit (updated test + new tests)
- Prettier to format Javascript
- Configured GitHub actions for CI (PHP+JS tests)
- Updated Renovate and Dependabot tests to target staging branch
- New Makefile with reorganised commands + new commands
- Document view:
    - Now displaying the abstract in all languages we have, prefixed with the language code
    - Now displaying keywords prefixed with their language code
- Relationships module:
    - added logs details for added/removed values
    - improved content escaping

### Changed

- Refactored some Javascript with more modern approach
- Editor Comments: in case conflict of interests is enabled in the journal, editors will receive the comments even if they have not yet answered to COI. If they declare a COI, they will be unassigned and will stop receiving editors comments
- Crossref title export: in case of multiple titles, the priority is given to the title in the language of the document or fallback to the fist title if no language
- [#657](https://github.com/CCSDForge/episciences/issues/657) Move "Download this file" button a bit and rename "Download this paper"

## v1.0.51 - 2025-04-07

### Changed

- [#650](https://github.com/CCSDForge/episciences/issues/650): send notifications when a cover letter is added or edited
- published dataset/software: the title of the data/software descriptor is its identifier.
- Only PDF files are backed up
- [#649](https://github.com/CCSDForge/episciences/issues/649): Assigned editors are now automatically added as (hidden) copies of messages sent to reviewers
- A copy of the default grid is proposed if the default grid is not empty, and if the destination grid is not empty either.

### Fixed

- [#653](https://github.com/CCSDForge/episciences/issues/653): Filtering email history doesn't work
- File Not Found Error
- Wrong link on page "Invitation to review an article" for temporary versions

## v1.0.50.1 - 2025-03-14

### Fixed

- Fixed CACHE_PATH not existing

### Changed

- Button label renamed for datasets/software papers

## v1.0.50 - 2025-03-13

### Added

- [#651](https://github.com/CCSDForge/episciences/issues/651): Statistics on invited and submitted referee reports

### Fixed

- Duplicate insertions when updating metadata
- [RT#238873]: it is now possible to replace an article with more recent version if its status is "Ready to publish"
- The average rating is displayed twice.
- [#654](https://github.com/CCSDForge/episciences/issues/654): Unable to modify a user's profile.
- [RT#237714]: The file renamed after downloading was not taken into account, which broke the link with this file.
  This bug affected the new version of the cover letter.
- IDE Warning: Method 'getVersion' not found in array|null

## v1.0.49 - 2025-02-19

### Added

- data descriptor viewer for data papers and software papers

### Changed

- Force the article type to "Article" only if it is a preprint when published,
- Improved licence enrichment for dataset

### Fixed

- Updating the volume number and year no longer works.
- [#652](https://github.com/CCSDForge/episciences/issues/652): missing translation
- RT#235731: failed to submit manuscript via ArXiv.
- Cancelling a file download does not work.
- Website resources: the file to be downloaded is saved repeatedly each time the page is refreshed.

## v1.0.48 - 2025-02-06

### Changed

- It is no longer possible to delete files attached to the temporary version.
- Metadata updates will take account of author enrichment, except for arXiv articles.
- It is now possible to select a new version when the article status is: Copy ed.: final version submitted, waiting for validation
- Version panel: temporary versions are now labeled as temporary

### Added

- It is now possible to attach a data/software descriptor to a submission if the submission type is Dataset or Software.
- New script to auto-declare conflicts of interest for journals activating the feature with an important backlog of articles
- New button on volume pages to download all the PDF files at once with a single PDF file (at the moment only available after login)

### Fixed

- The response to a modification request via "Contact without sending a new version" did not appear on the article page.
- Wrong links for the Dataverse repository with versioned datasets
- The graphical abstract was no longer displayed
- Volume's metadata was displayed twice

## v1.0.47 - 2025-01-08

### Added

- A script to generate sitemaps for the sites using the new UI/UX

### Fixed

- The graphical abstract is no longer displayed.
- When the "conflict of interest" option is activated reviewers are no longer visible for articles which the absence of conflict has been confirmed.
- The small logo to the left of each article's permanent identifier will only appear if at least one conflict has been confirmed (Nb. If you hover over this logo, you will see the number of conflicts reported for that article)

## v1.0.46.3 - 2025-01-06

### Fixed

- Failed to change email address for user profiles without affiliations.

## v1.0.46.2 - 2025-01-03

### Added

- classifications and project title metadata in the document's JSON export.
- improved scripts for importing and processing classifications

### Fixed

- [RT#229027]: Multiple files attached to the temporary version: only one file is visible on the article page.
- Prevent having empty roles when the last user role is deleted

## v1.0.53 - 2025-12-03

The changelog was not updated for this tag, but we keep the tag here as a reminder

## v1.0.52 - 2025-08-28

### Fixed

- RT#279519, TR#279601: Dates prior to the reviewing deadline were not permitted; however, it is now possible to change the reviewing deadline between the minimum and maximum dates.
- DOI metadata: stop overwriting acceptance date with the last modification date
- [#693](https://github.com/CCSDForge/episciences/issues/693) allow to save comment without attachment
- [#690](https://github.com/CCSDForge/episciences/issues/690) ORCID might be duplicated when one of the authors has no ORCID
- [#702](https://github.com/CCSDForge/episciences/issues/702) Inclusion of arxiv article version invalidates document identifier
- When updating Authors' affiliations, redirect to the latest paper version, not the 1st version
- [#629](https://github.com/CCSDForge/episciences/issues/629) Malfunction when updating volume description
- [#639](https://github.com/CCSDForge/episciences/issues/639) DOAJ Export unavailable. Added a function to Convert between ISO 639-2/T and ISO 639-2/B codes. DOAJ is in the team iso_639-2b whereas our current tools create iso_639-2t not supported by the DOAJ Schema
- Fixed unescaped identifiers causing invalid XML in OpenAire export format
- [#600](https://github.com/CCSDForge/episciences/issues/600) Abstract ignores line breaks
- [#694](https://github.com/CCSDForge/episciences/issues/694) accents via LaTeX macros in abstracts aren't rendered
- [#541](https://github.com/CCSDForge/episciences/issues/541) remove user assignation (editor) when a user is removed from the journal

### Added

- DOI management automation: Added shell scripts for batch DOI operations and enhanced getDoi.php with logging and journal fetching capabilities
- CSV import functionality for sections
- When pasting URL for repository identifiers, the URL is automatically cleaned to keep only the identifier, if the version number is in the URL, it is also automatically added into the "Version" field
- Javascript Tests with Jest
- PHP tests with Phpunit (updated test + new tests)
- Prettier to format Javascript
- Configured GitHub actions for CI (PHP+JS tests)
- Updated Renovate and Dependabot tests to target staging branch
- New Makefile with reorganised commands + new commands
- Document view:
    - Now displaying the abstract in all languages we have, prefixed with the language code
    - Now displaying keywords prefixed with their language code
- Relationships module:
    - added logs details for added/removed values
    - improved content escaping

### Changed

- Refactored some Javascript with more modern approach
- Editor Comments: in case conflict of interests is enabled in the journal, editors will receive the comments even if they have not yet answered to COI. If they declare a COI, they will be unassigned and will stop receiving editors comments
- Crossref title export: in case of multiple titles, the priority is given to the title in the language of the document or fallback to the fist title if no language
- [#657](https://github.com/CCSDForge/episciences/issues/657) Move "Download this file" button a bit and rename "Download this paper"

## v1.0.51 - 2025-04-07

### Changed

- [#650](https://github.com/CCSDForge/episciences/issues/650): send notifications when a cover letter is added or edited
- published dataset/software: the title of the data/software descriptor is its identifier.
- Only PDF files are backed up
- [#649](https://github.com/CCSDForge/episciences/issues/649): Assigned editors are now automatically added as (hidden) copies of messages sent to reviewers
- A copy of the default grid is proposed if the default grid is not empty, and if the destination grid is not empty either.

### Fixed

- [#653](https://github.com/CCSDForge/episciences/issues/653): Filtering email history doesn't work
- File Not Found Error
- Wrong link on page "Invitation to review an article" for temporary versions

## v1.0.50.1 - 2025-03-14

### Fixed

- Fixed CACHE_PATH not existing

### Changed

- Button label renamed for datasets/software papers

## v1.0.50 - 2025-03-13

### Added

- [#651](https://github.com/CCSDForge/episciences/issues/651): Statistics on invited and submitted referee reports

### Fixed

- Duplicate insertions when updating metadata
- [RT#238873]: it is now possible to replace an article with more recent version if its status is "Ready to publish"
- The average rating is displayed twice.
- [#654](https://github.com/CCSDForge/episciences/issues/654): Unable to modify a user's profile.
- [RT#237714]: The file renamed after downloading was not taken into account, which broke the link with this file.
  This bug affected the new version of the cover letter.
- IDE Warning: Method 'getVersion' not found in array|null

## v1.0.49 - 2025-02-19

### Added

- data descriptor viewer for data papers and software papers

### Changed

- Force the article type to "Article" only if it is a preprint when published,
- Improved licence enrichment for dataset

### Fixed

- Updating the volume number and year no longer works.
- [#652](https://github.com/CCSDForge/episciences/issues/652): missing translation
- RT#235731: failed to submit manuscript via ArXiv.
- Cancelling a file download does not work.
- Website resources: the file to be downloaded is saved repeatedly each time the page is refreshed.

## v1.0.48 - 2025-02-06

### Changed

- It is no longer possible to delete files attached to the temporary version.
- Metadata updates will take account of author enrichment, except for arXiv articles.
- It is now possible to select a new version when the article status is: Copy ed.: final version submitted, waiting for validation
- Version panel: temporary versions are now labeled as temporary

### Added

- It is now possible to attach a data/software descriptor to a submission if the submission type is Dataset or Software.
- New script to auto-declare conflicts of interest for journals activating the feature with an important backlog of articles
- New button on volume pages to download all the PDF files at once with a single PDF file (at the moment only available after login)

### Fixed

- The response to a modification request via "Contact without sending a new version" did not appear on the article page.
- Wrong links for the Dataverse repository with versioned datasets
- The graphical abstract was no longer displayed
- Volume's metadata was displayed twice

## v1.0.47 - 2025-01-08

### Added

- A script to generate sitemaps for the sites using the new UI/UX

### Fixed

- The graphical abstract is no longer displayed.
- When the "conflict of interest" option is activated reviewers are no longer visible for articles which the absence of conflict has been confirmed.
- The small logo to the left of each article's permanent identifier will only appear if at least one conflict has been confirmed (Nb. If you hover over this logo, you will see the number of conflicts reported for that article)

## v1.0.46.3 - 2025-01-06

### Fixed

- Failed to change email address for user profiles without affiliations.

## v1.0.46.2 - 2025-01-03

### Added

- classifications and project title metadata in the document's JSON export.
- improved scripts for importing and processing classifications

### Fixed

- [RT#229027]: Multiple files attached to the temporary version: only one file is visible on the article page.
- Prevent having empty roles when the last user role is deleted

## v1.0.53 - 2025-12-03

The changelog was not updated for this tag, but we keep the tag here as a reminder

## v1.0.52 - 2025-08-28

### Fixed

- RT#279519, TR#279601: Dates prior to the reviewing deadline were not permitted; however, it is now possible to change the reviewing deadline between the minimum and maximum dates.
- DOI metadata: stop overwriting acceptance date with the last modification date
- [#693](https://github.com/CCSDForge/episciences/issues/693) allow to save comment without attachment
- [#690](https://github.com/CCSDForge/episciences/issues/690) ORCID might be duplicated when one of the authors has no ORCID
- [#702](https://github.com/CCSDForge/episciences/issues/702) Inclusion of arxiv article version invalidates document identifier
- When updating Authors' affiliations, redirect to the latest paper version, not the 1st version
- [#629](https://github.com/CCSDForge/episciences/issues/629) Malfunction when updating volume description
- [#639](https://github.com/CCSDForge/episciences/issues/639) DOAJ Export unavailable. Added a function to Convert between ISO 639-2/T and ISO 639-2/B codes. DOAJ is in the team iso_639-2b whereas our current tools create iso_639-2t not supported by the DOAJ Schema
- Fixed unescaped identifiers causing invalid XML in OpenAire export format
- [#600](https://github.com/CCSDForge/episciences/issues/600) Abstract ignores line breaks
- [#694](https://github.com/CCSDForge/episciences/issues/694) accents via LaTeX macros in abstracts aren't rendered
- [#541](https://github.com/CCSDForge/episciences/issues/541) remove user assignation (editor) when a user is removed from the journal

### Added

- DOI management automation: Added shell scripts for batch DOI operations and enhanced getDoi.php with logging and journal fetching capabilities
- CSV import functionality for sections
- When pasting URL for repository identifiers, the URL is automatically cleaned to keep only the identifier, if the version number is in the URL, it is also automatically added into the "Version" field
- Javascript Tests with Jest
- PHP tests with Phpunit (updated test + new tests)
- Prettier to format Javascript
- Configured GitHub actions for CI (PHP+JS tests)
- Updated Renovate and Dependabot tests to target staging branch
- New Makefile with reorganised commands + new commands
- Document view:
    - Now displaying the abstract in all languages we have, prefixed with the language code
    - Now displaying keywords prefixed with their language code
- Relationships module:
    - added logs details for added/removed values
    - improved content escaping

### Changed

- Refactored some Javascript with more modern approach
- Editor Comments: in case conflict of interests is enabled in the journal, editors will receive the comments even if they have not yet answered to COI. If they declare a COI, they will be unassigned and will stop receiving editors comments
- Crossref title export: in case of multiple titles, the priority is given to the title in the language of the document or fallback to the fist title if no language
- [#657](https://github.com/CCSDForge/episciences/issues/657) Move "Download this file" button a bit and rename "Download this paper"

## v1.0.51 - 2025-04-07

### Changed

- [#650](https://github.com/CCSDForge/episciences/issues/650): send notifications when a cover letter is added or edited
- published dataset/software: the title of the data/software descriptor is its identifier.
- Only PDF files are backed up
- [#649](https://github.com/CCSDForge/episciences/issues/649): Assigned editors are now automatically added as (hidden) copies of messages sent to reviewers
- A copy of the default grid is proposed if the default grid is not empty, and if the destination grid is not empty either.

### Fixed

- [#653](https://github.com/CCSDForge/episciences/issues/653): Filtering email history doesn't work
- File Not Found Error
- Wrong link on page "Invitation to review an article" for temporary versions

## v1.0.50.1 - 2025-03-14

### Fixed

- Fixed CACHE_PATH not existing

### Changed

- Button label renamed for datasets/software papers

## v1.0.50 - 2025-03-13

### Added

- [#651](https://github.com/CCSDForge/episciences/issues/651): Statistics on invited and submitted referee reports

### Fixed

- Duplicate insertions when updating metadata
- [RT#238873]: it is now possible to replace an article with more recent version if its status is "Ready to publish"
- The average rating is displayed twice.
- [#654](https://github.com/CCSDForge/episciences/issues/654): Unable to modify a user's profile.
- [RT#237714]: The file renamed after downloading was not taken into account, which broke the link with this file.
  This bug affected the new version of the cover letter.
- IDE Warning: Method 'getVersion' not found in array|null

## v1.0.49 - 2025-02-19

### Added

- data descriptor viewer for data papers and software papers

### Changed

- Force the article type to "Article" only if it is a preprint when published,
- Improved licence enrichment for dataset

### Fixed

- Updating the volume number and year no longer works.
- [#652](https://github.com/CCSDForge/episciences/issues/652): missing translation
- RT#235731: failed to submit manuscript via ArXiv.
- Cancelling a file download does not work.
- Website resources: the file to be downloaded is saved repeatedly each time the page is refreshed.

## v1.0.48 - 2025-02-06

### Changed

- It is no longer possible to delete files attached to the temporary version.
- Metadata updates will take account of author enrichment, except for arXiv articles.
- It is now possible to select a new version when the article status is: Copy ed.: final version submitted, waiting for validation
- Version panel: temporary versions are now labeled as temporary

### Added

- It is now possible to attach a data/software descriptor to a submission if the submission type is Dataset or Software.
- New script to auto-declare conflicts of interest for journals activating the feature with an important backlog of articles
- New button on volume pages to download all the PDF files at once with a single PDF file (at the moment only available after login)

### Fixed

- The response to a modification request via "Contact without sending a new version" did not appear on the article page.
- Wrong links for the Dataverse repository with versioned datasets
- The graphical abstract was no longer displayed
- Volume's metadata was displayed twice

## v1.0.47 - 2025-01-08

### Added

- A script to generate sitemaps for the sites using the new UI/UX

### Fixed

- The graphical abstract is no longer displayed.
- When the "conflict of interest" option is activated reviewers are no longer visible for articles which the absence of conflict has been confirmed.
- The small logo to the left of each article's permanent identifier will only appear if at least one conflict has been confirmed (Nb. If you hover over this logo, you will see the number of conflicts reported for that article)

## v1.0.46.3 - 2025-01-06

### Fixed

- Failed to change email address for user profiles without affiliations.

## v1.0.46.2 - 2025-01-03

### Added

- classifications and project title metadata in the document's JSON export.
- improved scripts for importing and processing classifications

### Fixed

- [RT#229027]: Multiple files attached to the temporary version: only one file is visible on the article page.
- Prevent having empty roles when the last user role is deleted

## v1.0.53 - 2025-12-03

The changelog was not updated for this tag, but we keep the tag here as a reminder

## v1.0.52 - 2025-08-28

### Fixed

- RT#279519, TR#279601: Dates prior to the reviewing deadline were not permitted; however, it is now possible to change the reviewing deadline between the minimum and maximum dates.
- DOI metadata: stop overwriting acceptance date with the last modification date
- [#693](https://github.com/CCSDForge/episciences/issues/693) allow to save comment without attachment
- [#690](https://github.com/CCSDForge/episciences/issues/690) ORCID might be duplicated when one of the authors has no ORCID
- [#702](https://github.com/CCSDForge/episciences/issues/702) Inclusion of arxiv article version invalidates document identifier
- When updating Authors' affiliations, redirect to the latest paper version, not the 1st version
- [#629](https://github.com/CCSDForge/episciences/issues/629) Malfunction when updating volume description
- [#639](https://github.com/CCSDForge/episciences/issues/639) DOAJ Export unavailable. Added a function to Convert between ISO 639-2/T and ISO 639-2/B codes. DOAJ is in the team iso_639-2b whereas our current tools create iso_639-2t not supported by the DOAJ Schema
- Fixed unescaped identifiers causing invalid XML in OpenAire export format
- [#600](https://github.com/CCSDForge/episciences/issues/600) Abstract ignores line breaks
- [#694](https://github.com/CCSDForge/episciences/issues/694) accents via LaTeX macros in abstracts aren't rendered
- [#541](https://github.com/CCSDForge/episciences/issues/541) remove user assignation (editor) when a user is removed from the journal

### Added

- DOI management automation: Added shell scripts for batch DOI operations and enhanced getDoi.php with logging and journal fetching capabilities
- CSV import functionality for sections
- When pasting URL for repository identifiers, the URL is automatically cleaned to keep only the identifier, if the version number is in the URL, it is also automatically added into the "Version" field
- Javascript Tests with Jest
- PHP tests with Phpunit (updated test + new tests)
- Prettier to format Javascript
- Configured GitHub actions for CI (PHP+JS tests)
- Updated Renovate and Dependabot tests to target staging branch
- New Makefile with reorganised commands + new commands
- Document view:
    - Now displaying the abstract in all languages we have, prefixed with the language code
    - Now displaying keywords prefixed with their language code
- Relationships module:
    - added logs details for added/removed values
    - improved content escaping

### Changed

- Refactored some Javascript with more modern approach
- Editor Comments: in case conflict of interests is enabled in the journal, editors will receive the comments even if they have not yet answered to COI. If they declare a COI, they will be unassigned and will stop receiving editors comments
- Crossref title export: in case of multiple titles, the priority is given to the title in the language of the document or fallback to the fist title if no language
- [#657](https://github.com/CCSDForge/episciences/issues/657) Move "Download this file" button a bit and rename "Download this paper"

## v1.0.51 - 2025-04-07

### Changed

- [#650](https://github.com/CCSDForge/episciences/issues/650): send notifications when a cover letter is added or edited
- published dataset/software: the title of the data/software descriptor is its identifier.
- Only PDF files are backed up
- [#649](https://github.com/CCSDForge/episciences/issues/649): Assigned editors are now automatically added as (hidden) copies of messages sent to reviewers
- A copy of the default grid is proposed if the default grid is not empty, and if the destination grid is not empty either.

### Fixed

- [#653](https://github.com/CCSDForge/episciences/issues/653): Filtering email history doesn't work
- File Not Found Error
- Wrong link on page "Invitation to review an article" for temporary versions

## v1.0.50.1 - 2025-03-14

### Fixed

- Fixed CACHE_PATH not existing

### Changed

- Button label renamed for datasets/software papers

## v1.0.50 - 2025-03-13

### Added

- [#651](https://github.com/CCSDForge/episciences/issues/651): Statistics on invited and submitted referee reports

### Fixed

- Duplicate insertions when updating metadata
- [RT#238873]: it is now possible to replace an article with more recent version if its status is "Ready to publish"
- The average rating is displayed twice.
- [#654](https://github.com/CCSDForge/episciences/issues/654): Unable to modify a user's profile.
- [RT#237714]: The file renamed after downloading was not taken into account, which broke the link with this file.
  This bug affected the new version of the cover letter.
- IDE Warning: Method 'getVersion' not found in array|null

## v1.0.49 - 2025-02-19

### Added

- data descriptor viewer for data papers and software papers

### Changed

- Force the article type to "Article" only if it is a preprint when published,
- Improved licence enrichment for dataset

### Fixed

- Updating the volume number and year no longer works.
- [#652](https://github.com/CCSDForge/episciences/issues/652): missing translation
- RT#235731: failed to submit manuscript via ArXiv.
- Cancelling a file download does not work.
- Website resources: the file to be downloaded is saved repeatedly each time the page is refreshed.

## v1.0.48 - 2025-02-06

### Changed

- It is no longer possible to delete files attached to the temporary version.
- Metadata updates will take account of author enrichment, except for arXiv articles.
- It is now possible to select a new version when the article status is: Copy ed.: final version submitted, waiting for validation
- Version panel: temporary versions are now labeled as temporary

### Added

- It is now possible to attach a data/software descriptor to a submission if the submission type is Dataset or Software.
- New script to auto-declare conflicts of interest for journals activating the feature with an important backlog of articles
- New button on volume pages to download all the PDF files at once with a single PDF file (at the moment only available after login)

### Fixed

- The response to a modification request via "Contact without sending a new version" did not appear on the article page.
- Wrong links for the Dataverse repository with versioned datasets
- The graphical abstract was no longer displayed
- Volume's metadata was displayed twice

## v1.0.47 - 2025-01-08

### Added

- A script to generate sitemaps for the sites using the new UI/UX

### Fixed

- The graphical abstract is no longer displayed.
- When the "conflict of interest" option is activated reviewers are no longer visible for articles which the absence of conflict has been confirmed.
- The small logo to the left of each article's permanent identifier will only appear if at least one conflict has been confirmed (Nb. If you hover over this logo, you will see the number of conflicts reported for that article)

## v1.0.46.3 - 2025-01-06

### Fixed

- Failed to change email address for user profiles without affiliations.

## v1.0.46.2 - 2025-01-03

### Added

- classifications and project title metadata in the document's JSON export.
- improved scripts for importing and processing classifications

### Fixed

- [RT#229027]: Multiple files attached to the temporary version: only one file is visible on the article page.
- Prevent having empty roles when the last user role is deleted

## v1.0.53 - 2025-12-03

The changelog was not updated for this tag, but we keep the tag here as a reminder

## v1.0.52 - 2025-08-28

### Fixed

- RT#279519, TR#279601: Dates prior to the reviewing deadline were not permitted; however, it is now possible to change the reviewing deadline between the minimum and maximum dates.
- DOI metadata: stop overwriting acceptance date with the last modification date
- [#693](https://github.com/CCSDForge/episciences/issues/693) allow to save comment without attachment
- [#690](https://github.com/CCSDForge/episciences/issues/690) ORCID might be duplicated when one of the authors has no ORCID
- [#702](https://github.com/CCSDForge/episciences/issues/702) Inclusion of arxiv article version invalidates document identifier
- When updating Authors' affiliations, redirect to the latest paper version, not the 1st version
- [#629](https://github.com/CCSDForge/episciences/issues/629) Malfunction when updating volume description
- [#639](https://github.com/CCSDForge/episciences/issues/639) DOAJ Export unavailable. Added a function to Convert between ISO 639-2/T and ISO 639-2/B codes. DOAJ is in the team iso_639-2b whereas our current tools create iso_639-2t not supported by the DOAJ Schema
- Fixed unescaped identifiers causing invalid XML in OpenAire export format
- [#600](https://github.com/CCSDForge/episciences/issues/600) Abstract ignores line breaks
- [#694](https://github.com/CCSDForge/episciences/issues/694) accents via LaTeX macros in abstracts aren't rendered
- [#541](https://github.com/CCSDForge/episciences/issues/541) remove user assignation (editor) when a user is removed from the journal

### Added

- DOI management automation: Added shell scripts for batch DOI operations and enhanced getDoi.php with logging and journal fetching capabilities
- CSV import functionality for sections
- When pasting URL for repository identifiers, the URL is automatically cleaned to keep only the identifier, if the version number is in the URL, it is also automatically added into the "Version" field
- Javascript Tests with Jest
- PHP tests with Phpunit (updated test + new tests)
- Prettier to format Javascript
- Configured GitHub actions for CI (PHP+JS tests)
- Updated Renovate and Dependabot tests to target staging branch
- New Makefile with reorganised commands + new commands
- Document view:
    - Now displaying the abstract in all languages we have, prefixed with the language code
    - Now displaying keywords prefixed with their language code
- Relationships module:
    - added logs details for added/removed values
    - improved content escaping

### Changed

- Refactored some Javascript with more modern approach
- Editor Comments: in case conflict of interests is enabled in the journal, editors will receive the comments even if they have not yet answered to COI. If they declare a COI, they will be unassigned and will stop receiving editors comments
- Crossref title export: in case of multiple titles, the priority is given to the title in the language of the document or fallback to the fist title if no language
- [#657](https://github.com/CCSDForge/episciences/issues/657) Move "Download this file" button a bit and rename "Download this paper"

## v1.0.51 - 2025-04-07

### Changed

- [#650](https://github.com/CCSDForge/episciences/issues/650): send notifications when a cover letter is added or edited
- published dataset/software: the title of the data/software descriptor is its identifier.
- Only PDF files are backed up
- [#649](https://github.com/CCSDForge/episciences/issues/649): Assigned editors are now automatically added as (hidden) copies of messages sent to reviewers
- A copy of the default grid is proposed if the default grid is not empty, and if the destination grid is not empty either.

### Fixed

- [#653](https://github.com/CCSDForge/episciences/issues/653): Filtering email history doesn't work
- File Not Found Error
- Wrong link on page "Invitation to review an article" for temporary versions

## v1.0.50.1 - 2025-03-14

### Fixed

- Fixed CACHE_PATH not existing

### Changed

- Button label renamed for datasets/software papers

## v1.0.50 - 2025-03-13

### Added

- [#651](https://github.com/CCSDForge/episciences/issues/651): Statistics on invited and submitted referee reports

### Fixed

- Duplicate insertions when updating metadata
- [RT#238873]: it is now possible to replace an article with more recent version if its status is "Ready to publish"
- The average rating is displayed twice.
- [#654](https://github.com/CCSDForge/episciences/issues/654): Unable to modify a user's profile.
- [RT#237714]: The file renamed after downloading was not taken into account, which broke the link with this file.
  This bug affected the new version of the cover letter.
- IDE Warning: Method 'getVersion' not found in array|null

## v1.0.49 - 2025-02-19

### Added

- data descriptor viewer for data papers and software papers

### Changed

- Force the article type to "Article" only if it is a preprint when published,
- Improved licence enrichment for dataset

### Fixed

- Updating the volume number and year no longer works.
- [#652](https://github.com/CCSDForge/episciences/issues/652): missing translation
- RT#235731: failed to submit manuscript via ArXiv.
- Cancelling a file download does not work.
- Website resources: the file to be downloaded is saved repeatedly each time the page is refreshed.

## v1.0.48 - 2025-02-06

### Changed

- It is no longer possible to delete files attached to the temporary version.
- Metadata updates will take account of author enrichment, except for arXiv articles.
- It is now possible to select a new version when the article status is: Copy ed.: final version submitted, waiting for validation
- Version panel: temporary versions are now labeled as temporary

### Added

- It is now possible to attach a data/software descriptor to a submission if the submission type is Dataset or Software.
- New script to auto-declare conflicts of interest for journals activating the feature with an important backlog of articles
- New button on volume pages to download all the PDF files at once with a single PDF file (at the moment only available after login)

### Fixed

- The response to a modification request via "Contact without sending a new version" did not appear on the article page.
- Wrong links for the Dataverse repository with versioned datasets
- The graphical abstract was no longer displayed
- Volume's metadata was displayed twice

## v1.0.47 - 2025-01-08

### Added

- A script to generate sitemaps for the sites using the new UI/UX

### Fixed

- The graphical abstract is no longer displayed.
- When the "conflict of interest" option is activated reviewers are no longer visible for articles which the absence of conflict has been confirmed.
- The small logo to the left of each article's permanent identifier will only appear if at least one conflict has been confirmed (Nb. If you hover over this logo, you will see the number of conflicts reported for that article)

## v1.0.46.3 - 2025-01-06

### Fixed

- Failed to change email address for user profiles without affiliations.

## v1.0.46.2 - 2025-01-03

### Added

- classifications and project title metadata in the document's JSON export.
- improved scripts for importing and processing classifications

### Fixed

- [RT#229027]: Multiple files attached to the temporary version: only one file is visible on the article page.
- Prevent having empty roles when the last user role is deleted

## v1.0.53 - 2025-12-03

The changelog was not updated for this tag, but we keep the tag here as a reminder

## v1.0.52 - 2025-08-28

### Fixed

- RT#279519, TR#279601: Dates prior to the reviewing deadline were not permitted; however, it is now possible to change the reviewing deadline between the minimum and maximum dates.
- DOI metadata: stop overwriting acceptance date with the last modification date
- [#693](https://github.com/CCSDForge/episciences/issues/693) allow to save comment without attachment
- [#690](https://github.com/CCSDForge/episciences/issues/690) ORCID might be duplicated when one of the authors has no ORCID
- [#702](https://github.com/CCSDForge/episciences/issues/702) Inclusion of arxiv article version invalidates document identifier
- When updating Authors' affiliations, redirect to the latest paper version, not the 1st version
- [#629](https://github.com/CCSDForge/episciences/issues/629) Malfunction when updating volume description
- [#639](https://github.com/CCSDForge/episciences/issues/639) DOAJ Export unavailable. Added a function to Convert between ISO 639-2/T and ISO 639-2/B codes. DOAJ is in the team iso_639-2b whereas our current tools create iso_639-2t not supported by the DOAJ Schema
- Fixed unescaped identifiers causing invalid XML in OpenAire export format
- [#600](https://github.com/CCSDForge/episciences/issues/600) Abstract ignores line breaks
- [#694](https://github.com/CCSDForge/episciences/issues/694) accents via LaTeX macros in abstracts aren't rendered
- [#541](https://github.com/CCSDForge/episciences/issues/541) remove user assignation (editor) when a user is removed from the journal

### Added

- DOI management automation: Added shell scripts for batch DOI operations and enhanced getDoi.php with logging and journal fetching capabilities
- CSV import functionality for sections
- When pasting URL for repository identifiers, the URL is automatically cleaned to keep only the identifier, if the version number is in the URL, it is also automatically added into the "Version" field
- Javascript Tests with Jest
- PHP tests with Phpunit (updated test + new tests)
- Prettier to format Javascript
- Configured GitHub actions for CI (PHP+JS tests)
- Updated Renovate and Dependabot tests to target staging branch
- New Makefile with reorganised commands + new commands
- Document view:
    - Now displaying the abstract in all languages we have, prefixed with the language code
    - Now displaying keywords prefixed with their language code
- Relationships module:
    - added logs details for added/removed values
    - improved content escaping

### Changed

- Refactored some Javascript with more modern approach
- Editor Comments: in case conflict of interests is enabled in the journal, editors will receive the comments even if they have not yet answered to COI. If they declare a COI, they will be unassigned and will stop receiving editors comments
- Crossref title export: in case of multiple titles, the priority is given to the title in the language of the document or fallback to the fist title if no language
- [#657](https://github.com/CCSDForge/episciences/issues/657) Move "Download this file" button a bit and rename "Download this paper"

## v1.0.51 - 2025-04-07

### Changed

- [#650](https://github.com/CCSDForge/episciences/issues/650): send notifications when a cover letter is added or edited
- published dataset/software: the title of the data/software descriptor is its identifier.
- Only PDF files are backed up
- [#649](https://github.com/CCSDForge/episciences/issues/649): Assigned editors are now automatically added as (hidden) copies of messages sent to reviewers
- A copy of the default grid is proposed if the default grid is not empty, and if the destination grid is not empty either.

### Fixed

- [#653](https://github.com/CCSDForge/episciences/issues/653): Filtering email history doesn't work
- File Not Found Error
- Wrong link on page "Invitation to review an article" for temporary versions

## v1.0.50.1 - 2025-03-14

### Fixed

- Fixed CACHE_PATH not existing

### Changed

- Button label renamed for datasets/software papers

## v1.0.50 - 2025-03-13

### Added

- [#651](https://github.com/CCSDForge/episciences/issues/651): Statistics on invited and submitted referee reports

### Fixed

- Duplicate insertions when updating metadata
- [RT#238873]: it is now possible to replace an article with more recent version if its status is "Ready to publish"
- The average rating is displayed twice.
- [#654](https://github.com/CCSDForge/episciences/issues/654): Unable to modify a user's profile.
- [RT#237714]: The file renamed after downloading was not taken into account, which broke the link with this file.
  This bug affected the new version of the cover letter.
- IDE Warning: Method 'getVersion' not found in array|null

## v1.0.49 - 2025-02-19

### Added

- data descriptor viewer for data papers and software papers

### Changed

- Force the article type to "Article" only if it is a preprint when published,
- Improved licence enrichment for dataset

### Fixed

- Updating the volume number and year no longer works.
- [#652](https://github.com/CCSDForge/episciences/issues/652): missing translation
- RT#235731: failed to submit manuscript via ArXiv.
- Cancelling a file download does not work.
- Website resources: the file to be downloaded is saved repeatedly each time the page is refreshed.

## v1.0.48 - 2025-02-06

### Changed

- It is no longer possible to delete files attached to the temporary version.
- Metadata updates will take account of author enrichment, except for arXiv articles.
- It is now possible to select a new version when the article status is: Copy ed.: final version submitted, waiting for validation
- Version panel: temporary versions are now labeled as temporary

### Added

- It is now possible to attach a data/software descriptor to a submission if the submission type is Dataset or Software.
- New script to auto-declare conflicts of interest for journals activating the feature with an important backlog of articles
- New button on volume pages to download all the PDF files at once with a single PDF file (at the moment only available after login)

### Fixed

- The response to a modification request via "Contact without sending a new version" did not appear on the article page.
- Wrong links for the Dataverse repository with versioned datasets
- The graphical abstract was no longer displayed
- Volume's metadata was displayed twice

## v1.0.47 - 2025-01-08

### Added

- A script to generate sitemaps for the sites using the new UI/UX

### Fixed

- The graphical abstract is no longer displayed.
- When the "conflict of interest" option is activated reviewers are no longer visible for articles which the absence of conflict has been confirmed.
- The small logo to the left of each article's permanent identifier will only appear if at least one conflict has been confirmed (Nb. If you hover over this logo, you will see the number of conflicts reported for that article)

## v1.0.46.3 - 2025-01-06

### Fixed

- Failed to change email address for user profiles without affiliations.

## v1.0.46.2 - 2025-01-03

### Added

- classifications and project title metadata in the document's JSON export.
- improved scripts for importing and processing classifications

### Fixed

- [RT#229027]: Multiple files attached to the temporary version: only one file is visible on the article page.
- Prevent having empty roles when the last user role is deleted

## v1.0.53 - 2025-12-03

The changelog was not updated for this tag, but we keep the tag here as a reminder

## v1.0.52 - 2025-08-28

### Fixed

- RT#279519, TR#279601: Dates prior to the reviewing deadline were not permitted; however, it is now possible to change the reviewing deadline between the minimum and maximum dates.
- DOI metadata: stop overwriting acceptance date with the last modification date
- [#693](https://github.com/CCSDForge/episciences/issues/693) allow to save comment without attachment
- [#690](https://github.com/CCSDForge/episciences/issues/690) ORCID might be duplicated when one of the authors has no ORCID
- [#702](https://github.com/CCSDForge/episciences/issues/702) Inclusion of arxiv article version invalidates document identifier
- When updating Authors' affiliations, redirect to the latest paper version, not the 1st version
- [#629](https://github.com/CCSDForge/episciences/issues/629) Malfunction when updating volume description
- [#639](https://github.com/CCSDForge/episciences/issues/639) DOAJ Export unavailable. Added a function to Convert between ISO 639-2/T and ISO 639-2/B codes. DOAJ is in the team iso_639-2b whereas our current tools create iso_639-2t not supported by the DOAJ Schema
- Fixed unescaped identifiers causing invalid XML in OpenAire export format
- [#600](https://github.com/CCSDForge/episciences/issues/600) Abstract ignores line breaks
- [#694](https://github.com/CCSDForge/episciences/issues/694) accents via LaTeX macros in abstracts aren't rendered
- [#541](https://github.com/CCSDForge/episciences/issues/541) remove user assignation (editor) when a user is removed from the journal

### Added

- DOI management automation: Added shell scripts for batch DOI operations and enhanced getDoi.php with logging and journal fetching capabilities
- CSV import functionality for sections
- When pasting URL for repository identifiers, the URL is automatically cleaned to keep only the identifier, if the version number is in the URL, it is also automatically added into the "Version" field
- Javascript Tests with Jest
- PHP tests with Phpunit (updated test + new tests)
- Prettier to format Javascript
- Configured GitHub actions for CI (PHP+JS tests)
- Updated Renovate and Dependabot tests to target staging branch
- New Makefile with reorganised commands + new commands
- Document view:
    - Now displaying the abstract in all languages we have, prefixed with the language code
    - Now displaying keywords prefixed with their language code
- Relationships module:
    - added logs details for added/removed values
    - improved content escaping

### Changed

- Refactored some Javascript with more modern approach
- Editor Comments: in case conflict of interests is enabled in the journal, editors will receive the comments even if they have not yet answered to COI. If they declare a COI, they will be unassigned and will stop receiving editors comments
- Crossref title export: in case of multiple titles, the priority is given to the title in the language of the document or fallback to the fist title if no language
- [#657](https://github.com/CCSDForge/episciences/issues/657) Move "Download this file" button a bit and rename "Download this paper"

## v1.0.51 - 2025-04-07

### Changed

- [#650](https://github.com/CCSDForge/episciences/issues/650): send notifications when a cover letter is added or edited
- published dataset/software: the title of the data/software descriptor is its identifier.
- Only PDF files are backed up
- [#649](https://github.com/CCSDForge/episciences/issues/649): Assigned editors are now automatically added as (hidden) copies of messages sent to reviewers
- A copy of the default grid is proposed if the default grid is not empty, and if the destination grid is not empty either.

### Fixed

- [#653](https://github.com/CCSDForge/episciences/issues/653): Filtering email history doesn't work
- File Not Found Error
- Wrong link on page "Invitation to review an article" for temporary versions

## v1.0.50.1 - 2025-03-14

### Fixed

- Fixed CACHE_PATH not existing

### Changed

- Button label renamed for datasets/software papers

## v1.0.50 - 2025-03-13

### Added

- [#651](https://github.com/CCSDForge/episciences/issues/651): Statistics on invited and submitted referee reports

### Fixed

- Duplicate insertions when updating metadata
- [RT#238873]: it is now possible to replace an article with more recent version if its status is "Ready to publish"
- The average rating is displayed twice.
- [#654](https://github.com/CCSDForge/episciences/issues/654): Unable to modify a user's profile.
- [RT#237714]: The file renamed after downloading was not taken into account, which broke the link with this file.
  This bug affected the new version of the cover letter.
- IDE Warning: Method 'getVersion' not found in array|null

## v1.0.49 - 2025-02-19

### Added

- data descriptor viewer for data papers and software papers

### Changed

- Force the article type to "Article" only if it is a preprint when published,
- Improved licence enrichment for dataset

### Fixed

- Updating the volume number and year no longer works.
- [#652](https://github.com/CCSDForge/episciences/issues/652): missing translation
- RT#235731: failed to submit manuscript via ArXiv.
- Cancelling a file download does not work.
- Website resources: the file to be downloaded is saved repeatedly each time the page is refreshed.

## v1.0.48 - 2025-02-06

### Changed

- It is no longer possible to delete files attached to the temporary version.
- Metadata updates will take account of author enrichment, except for arXiv articles.
- It is now possible to select a new version when the article status is: Copy ed.: final version submitted, waiting for validation
- Version panel: temporary versions are now labeled as temporary

### Added

- It is now possible to attach a data/software descriptor to a submission if the submission type is Dataset or Software.
- New script to auto-declare conflicts of interest for journals activating the feature with an important backlog of articles
- New button on volume pages to download all the PDF files at once with a single PDF file (at the moment only available after login)

### Fixed

- The response to a modification request via "Contact without sending a new version" did not appear on the article page.
- Wrong links for the Dataverse repository with versioned datasets
- The graphical abstract was no longer displayed
- Volume's metadata was displayed twice

## v1.0.47 - 2025-01-08

### Added

- A script to generate sitemaps for the sites using the new UI/UX

### Fixed

- The graphical abstract is no longer displayed.
- When the "conflict of interest" option is activated reviewers are no longer visible for articles which the absence of conflict has been confirmed.
- The small logo to the left of each article's permanent identifier will only appear if at least one conflict has been confirmed (Nb. If you hover over this logo, you will see the number of conflicts reported for that article)

## v1.0.46.3 - 2025-01-06

### Fixed

- Failed to change email address for user profiles without affiliations.

## v1.0.46.2 - 2025-01-03

### Added

- classifications and project title metadata in the document's JSON export.
- improved scripts for importing and processing classifications

### Fixed

- [RT#229027]: Multiple files attached to the temporary version: only one file is visible on the article page.
- Prevent having empty roles when the last user role is deleted

## v1.0.53 - 2025-12-03

The changelog was not updated for this tag, but we keep the tag here as a reminder

## v1.0.52 - 2025-08-28

### Fixed

- RT#279519, TR#279601: Dates prior to the reviewing deadline were not permitted; however, it is now possible to change the reviewing deadline between the minimum and maximum dates.
- DOI metadata: stop overwriting acceptance date with the last modification date
- [#693](https://github.com/CCSDForge/episciences/issues/693) allow to save comment without attachment
- [#690](https://github.com/CCSDForge/episciences/issues/690) ORCID might be duplicated when one of the authors has no ORCID
- [#702](https://github.com/CCSDForge/episciences/issues/702) Inclusion of arxiv article version invalidates document identifier
- When updating Authors' affiliations, redirect to the latest paper version, not the 1st version
- [#629](https://github.com/CCSDForge/episciences/issues/629) Malfunction when updating volume description
- [#639](https://github.com/CCSDForge/episciences/issues/639) DOAJ Export unavailable. Added a function to Convert between ISO 639-2/T and ISO 639-2/B codes. DOAJ is in the team iso_639-2b whereas our current tools create iso_639-2t not supported by the DOAJ Schema
- Fixed unescaped identifiers causing invalid XML in OpenAire export format
- [#600](https://github.com/CCSDForge/episciences/issues/600) Abstract ignores line breaks
- [#694](https://github.com/CCSDForge/episciences/issues/694) accents via LaTeX macros in abstracts aren't rendered
- [#541](https://github.com/CCSDForge/episciences/issues/541) remove user assignation (editor) when a user is removed from the journal

### Added

- DOI management automation: Added shell scripts for batch DOI operations and enhanced getDoi.php with logging and journal fetching capabilities
- CSV import functionality for sections
- When pasting URL for repository identifiers, the URL is automatically cleaned to keep only the identifier, if the version number is in the URL, it is also automatically added into the "Version" field
- Javascript Tests with Jest
- PHP tests with Phpunit (updated test + new tests)
- Prettier to format Javascript
- Configured GitHub actions for CI (PHP+JS tests)
- Updated Renovate and Dependabot tests to target staging branch
- New Makefile with reorganised commands + new commands
- Document view:
    - Now displaying the abstract in all languages we have, prefixed with the language code
    - Now displaying keywords prefixed with their language code
- Relationships module:
    - added logs details for added/removed values
    - improved content escaping

### Changed

- Refactored some Javascript with more modern approach
- Editor Comments: in case conflict of interests is enabled in the journal, editors will receive the comments even if they have not yet answered to COI. If they declare a COI, they will be unassigned and will stop receiving editors comments
- Crossref title export: in case of multiple titles, the priority is given to the title in the language of the document or fallback to the fist title if no language
- [#657](https://github.com/CCSDForge/episciences/issues/657) Move "Download this file" button a bit and rename "Download this paper"

## v1.0.51 - 2025-04-07

### Changed

- [#650](https://github.com/CCSDForge/episciences/issues/650): send notifications when a cover letter is added or edited
- published dataset/software: the title of the data/software descriptor is its identifier.
- Only PDF files are backed up
- [#649](https://github.com/CCSDForge/episciences/issues/649): Assigned editors are now automatically added as (hidden) copies of messages sent to reviewers
- A copy of the default grid is proposed if the default grid is not empty, and if the destination grid is not empty either.

### Fixed

- [#653](https://github.com/CCSDForge/episciences/issues/653): Filtering email history doesn't work
- File Not Found Error
- Wrong link on page "Invitation to review an article" for temporary versions

## v1.0.50.1 - 2025-03-14

### Fixed

- Fixed CACHE_PATH not existing

### Changed

- Button label renamed for datasets/software papers

## v1.0.50 - 2025-03-13

### Added

- [#651](https://github.com/CCSDForge/episciences/issues/651): Statistics on invited and submitted referee reports

### Fixed

- Duplicate insertions when updating metadata
- [RT#238873]: it is now possible to replace an article with more recent version if its status is "Ready to publish"
- The average rating is displayed twice.
- [#654](https://github.com/CCSDForge/episciences/issues/654): Unable to modify a user's profile.
- [RT#237714]: The file renamed after downloading was not taken into account, which broke the link with this file.
  This bug affected the new version of the cover letter.
- IDE Warning: Method 'getVersion' not found in array|null

## v1.0.49 - 2025-02-19

### Added

- data descriptor viewer for data papers and software papers

### Changed

- Force the article type to "Article" only if it is a preprint when published,
- Improved licence enrichment for dataset

### Fixed

- Updating the volume number and year no longer works.
- [#652](https://github.com/CCSDForge/episciences/issues/652): missing translation
- RT#235731: failed to submit manuscript via ArXiv.
- Cancelling a file download does not work.
- Website resources: the file to be downloaded is saved repeatedly each time the page is refreshed.

## v1.0.48 - 2025-02-06

### Changed

- It is no longer possible to delete files attached to the temporary version.
- Metadata updates will take account of author enrichment, except for arXiv articles.
- It is now possible to select a new version when the article status is: Copy ed.: final version submitted, waiting for validation
- Version panel: temporary versions are now labeled as temporary

### Added

- It is now possible to attach a data/software descriptor to a submission if the submission type is Dataset or Software.
- New script to auto-declare conflicts of interest for journals activating the feature with an important backlog of articles
- New button on volume pages to download all the PDF files at once with a single PDF file (at the moment only available after login)

### Fixed

- The response to a modification request via "Contact without sending a new version" did not appear on the article page.
- Wrong links for the Dataverse repository with versioned datasets
- The graphical abstract was no longer displayed
- Volume's metadata was displayed twice

## v1.0.47 - 2025-01-08

### Added

- A script to generate sitemaps for the sites using the new UI/UX

### Fixed

- The graphical abstract is no longer displayed.
- When the "conflict of interest" option is activated reviewers are no longer visible for articles which the absence of conflict has been confirmed.
- The small logo to the left of each article's permanent identifier will only appear if at least one conflict has been confirmed (Nb. If you hover over this logo, you will see the number of conflicts reported for that article)

## v1.0.46.3 - 2025-01-06

### Fixed

- Failed to change email address for user profiles without affiliations.

## v1.0.46.2 - 2025-01-03

### Added

- classifications and project title metadata in the document's JSON export.
- improved scripts for importing and processing classifications

### Fixed

- [RT#229027]: Multiple files attached to the temporary version: only one file is visible on the article page.
- Prevent having empty roles when the last user role is deleted

## v1.0.53 - 2025-12-03

The changelog was not updated for this tag, but we keep the tag here as a reminder

## v1.0.52 - 2025-08-28

### Fixed

- RT#279519, TR#279601: Dates prior to the reviewing deadline were not permitted; however, it is now possible to change the reviewing deadline between the minimum and maximum dates.
- DOI metadata: stop overwriting acceptance date with the last modification date
- [#693](https://github.com/CCSDForge/episciences/issues/693) allow to save comment without attachment
- [#690](https://github.com/CCSDForge/episciences/issues/690) ORCID might be duplicated when one of the authors has no ORCID
- [#702](https://github.com/CCSDForge/episciences/issues/702) Inclusion of arxiv article version invalidates document identifier
- When updating Authors' affiliations, redirect to the latest paper version, not the 1st version
- [#629](https://github.com/CCSDForge/episciences/issues/629) Malfunction when updating volume description
- [#639](https://github.com/CCSDForge/episciences/issues/639) DOAJ Export unavailable. Added a function to Convert between ISO 639-2/T and ISO 639-2/B codes. DOAJ is in the team iso_639-2b whereas our current tools create iso_639-2t not supported by the DOAJ Schema
- Fixed unescaped identifiers causing invalid XML in OpenAire export format
- [#600](https://github.com/CCSDForge/episciences/issues/600) Abstract ignores line breaks
- [#694](https://github.com/CCSDForge/episciences/issues/694) accents via LaTeX macros in abstracts aren't rendered
- [#541](https://github.com/CCSDForge/episciences/issues/541) remove user assignation (editor) when a user is removed from the journal

### Added

- DOI management automation: Added shell scripts for batch DOI operations and enhanced getDoi.php with logging and journal fetching capabilities
- CSV import functionality for sections
- When pasting URL for repository identifiers, the URL is automatically cleaned to keep only the identifier, if the version number is in the URL, it is also automatically added into the "Version" field
- Javascript Tests with Jest
- PHP tests with Phpunit (updated test + new tests)
- Prettier to format Javascript
- Configured GitHub actions for CI (PHP+JS tests)
- Updated Renovate and Dependabot tests to target staging branch
- New Makefile with reorganised commands + new commands
- Document view:
    - Now displaying the abstract in all languages we have, prefixed with the language code
    - Now displaying keywords prefixed with their language code
- Relationships module:
    - added logs details for added/removed values
    - improved content escaping

### Changed

- Refactored some Javascript with more modern approach
- Editor Comments: in case conflict of interests is enabled in the journal, editors will receive the comments even if they have not yet answered to COI. If they declare a COI, they will be unassigned and will stop receiving editors comments
- Crossref title export: in case of multiple titles, the priority is given to the title in the language of the document or fallback to the fist title if no language
- [#657](https://github.com/CCSDForge/episciences/issues/657) Move "Download this file" button a bit and rename "Download this paper"

## v1.0.51 - 2025-04-07

### Changed

- [#650](https://github.com/CCSDForge/episciences/issues/650): send notifications when a cover letter is added or edited
- published dataset/software: the title of the data/software descriptor is its identifier.
- Only PDF files are backed up
- [#649](https://github.com/CCSDForge/episciences/issues/649): Assigned editors are now automatically added as (hidden) copies of messages sent to reviewers
- A copy of the default grid is proposed if the default grid is not empty, and if the destination grid is not empty either.

### Fixed

- [#653](https://github.com/CCSDForge/episciences/issues/653): Filtering email history doesn't work
- File Not Found Error
- Wrong link on page "Invitation to review an article" for temporary versions

## v1.0.50.1 - 2025-03-14

### Fixed

- Fixed CACHE_PATH not existing

### Changed

- Button label renamed for datasets/software papers

## v1.0.50 - 2025-03-13

### Added

- [#651](https://github.com/CCSDForge/episciences/issues/651): Statistics on invited and submitted referee reports

### Fixed

- Duplicate insertions when updating metadata
- [RT#238873]: it is now possible to replace an article with more recent version if its status is "Ready to publish"
- The average rating is displayed twice.
- [#654](https://github.com/CCSDForge/episciences/issues/654): Unable to modify a user's profile.
- [RT#237714]: The file renamed after downloading was not taken into account, which broke the link with this file.
  This bug affected the new version of the cover letter.
- IDE Warning: Method 'getVersion' not found in array|null

## v1.0.49 - 2025-02-19

### Added

- data descriptor viewer for data papers and software papers

### Changed

- Force the article type to "Article" only if it is a preprint when published,
- Improved licence enrichment for dataset

### Fixed

- Updating the volume number and year no longer works.
- [#652](https://github.com/CCSDForge/episciences/issues/652): missing translation
- RT#235731: failed to submit manuscript via ArXiv.
- Cancelling a file download does not work.
- Website resources: the file to be downloaded is saved repeatedly each time the page is refreshed.

## v1.0.48 - 2025-02-06

### Changed

- It is no longer possible to delete files attached to the temporary version.
- Metadata updates will take account of author enrichment, except for arXiv articles.
- It is now possible to select a new version when the article status is: Copy ed.: final version submitted, waiting for validation
- Version panel: temporary versions are now labeled as temporary

### Added

- It is now possible to attach a data/software descriptor to a submission if the submission type is Dataset or Software.
- New script to auto-declare conflicts of interest for journals activating the feature with an important backlog of articles
- New button on volume pages to download all the PDF files at once with a single PDF file (at the moment only available after login)

### Fixed

- The response to a modification request via "Contact without sending a new version" did not appear on the article page.
- Wrong links for the Dataverse repository with versioned datasets
- The graphical abstract was no longer displayed
- Volume's metadata was displayed twice

## v1.0.47 - 2025-01-08

### Added

- A script to generate sitemaps for the sites using the new UI/UX

### Fixed

- The graphical abstract is no longer displayed.
- When the "conflict of interest" option is activated reviewers are no longer visible for articles which the absence of conflict has been confirmed.
- The small logo to the left of each article's permanent identifier will only appear if at least one conflict has been confirmed (Nb. If you hover over this logo, you will see the number of conflicts reported for that article)

## v1.0.46.3 - 2025-01-06

### Fixed

- Failed to change email address for user profiles without affiliations.

## v1.0.46.2 - 2025-01-03

### Added

- classifications and project title metadata in the document's JSON export.
- improved scripts for importing and processing classifications

### Fixed

- [RT#229027]: Multiple files attached to the temporary version: only one file is visible on the article page.
- Prevent having empty roles when the last user role is deleted

## v1.0.53 - 2025-12-03

The changelog was not updated for this tag, but we keep the tag here as a reminder

## v1.0.52 - 2025-08-28

### Fixed

- RT#279519, TR#279601: Dates prior to the reviewing deadline were not permitted; however, it is now possible to change the reviewing deadline between the minimum and maximum dates.
- DOI metadata: stop overwriting acceptance date with the last modification date
- [#693](https://github.com/CCSDForge/episciences/issues/693) allow to save comment without attachment
- [#690](https://github.com/CCSDForge/episciences/issues/690) ORCID might be duplicated when one of the authors has no ORCID
- [#702](https://github.com/CCSDForge/episciences/issues/702) Inclusion of arxiv article version invalidates document identifier
- When updating Authors' affiliations, redirect to the latest paper version, not the 1st version
- [#629](https://github.com/CCSDForge/episciences/issues/629) Malfunction when updating volume description
- [#639](https://github.com/CCSDForge/episciences/issues/639) DOAJ Export unavailable. Added a function to Convert between ISO 639-2/T and ISO 639-2/B codes. DOAJ is in the team iso_639-2b whereas our current tools create iso_639-2t not supported by the DOAJ Schema
- Fixed unescaped identifiers causing invalid XML in OpenAire export format
- [#600](https://github.com/CCSDForge/episciences/issues/600) Abstract ignores line breaks
- [#694](https://github.com/CCSDForge/episciences/issues/694) accents via LaTeX macros in abstracts aren't rendered
- [#541](https://github.com/CCSDForge/episciences/issues/541) remove user assignation (editor) when a user is removed from the journal

### Added

- DOI management automation: Added shell scripts for batch DOI operations and enhanced getDoi.php with logging and journal fetching capabilities
- CSV import functionality for sections
- When pasting URL for repository identifiers, the URL is automatically cleaned to keep only the identifier, if the version number is in the URL, it is also automatically added into the "Version" field
- Javascript Tests with Jest
- PHP tests with Phpunit (updated test + new tests)
- Prettier to format Javascript
- Configured GitHub actions for CI (PHP+JS tests)
- Updated Renovate and Dependabot tests to target staging branch
- New Makefile with reorganised commands + new commands
- Document view:
    - Now displaying the abstract in all languages we have, prefixed with the language code
    - Now displaying keywords prefixed with their language code
- Relationships module:
    - added logs details for added/removed values
    - improved content escaping

### Changed

- Refactored some Javascript with more modern approach
- Editor Comments: in case conflict of interests is enabled in the journal, editors will receive the comments even if they have not yet answered to COI. If they declare a COI, they will be unassigned and will stop receiving editors comments
- Crossref title export: in case of multiple titles, the priority is given to the title in the language of the document or fallback to the fist title if no language
- [#657](https://github.com/CCSDForge/episciences/issues/657) Move "Download this file" button a bit and rename "Download this paper"

## v1.0.51 - 2025-04-07

### Changed

- [#650](https://github.com/CCSDForge/episciences/issues/650): send notifications when a cover letter is added or edited
- published dataset/software: the title of the data/software descriptor is its identifier.
- Only PDF files are backed up
- [#649](https://github.com/CCSDForge/episciences/issues/649): Assigned editors are now automatically added as (hidden) copies of messages sent to reviewers
- A copy of the default grid is proposed if the default grid is not empty, and if the destination grid is not empty either.

### Fixed

- [#653](https://github.com/CCSDForge/episciences/issues/653): Filtering email history doesn't work
- File Not Found Error
- Wrong link on page "Invitation to review an article" for temporary versions

## v1.0.50.1 - 2025-03-14

### Fixed

- Fixed CACHE_PATH not existing

### Changed

- Button label renamed for datasets/software papers

## v1.0.50 - 2025-03-13

### Added

- [#651](https://github.com/CCSDForge/episciences/issues/651): Statistics on invited and submitted referee reports

### Fixed

- Duplicate insertions when updating metadata
- [RT#238873]: it is now possible to replace an article with more recent version if its status is "Ready to publish"
- The average rating is displayed twice.
- [#654](https://github.com/CCSDForge/episciences/issues/654): Unable to modify a user's profile.
- [RT#237714]: The file renamed after downloading was not taken into account, which broke the link with this file.
  This bug affected the new version of the cover letter.
- IDE Warning: Method 'getVersion' not found in array|null

## v1.0.49 - 2025-02-19

### Added

- data descriptor viewer for data papers and software papers

### Changed

- Force the article type to "Article" only if it is a preprint when published,
- Improved licence enrichment for dataset

### Fixed

- Updating the volume number and year no longer works.
- [#652](https://github.com/CCSDForge/episciences/issues/652): missing translation
- RT#235731: failed to submit manuscript via ArXiv.
- Cancelling a file download does not work.
- Website resources: the file to be downloaded is saved repeatedly each time the page is refreshed.

## v1.0.48 - 2025-02-06

### Changed

- It is no longer possible to delete files attached to the temporary version.
- Metadata updates will take account of author enrichment, except for arXiv articles.
- It is now possible to select a new version when the article status is: Copy ed.: final version submitted, waiting for validation
- Version panel: temporary versions are now labeled as temporary

### Added

- It is now possible to attach a data/software descriptor to a submission if the submission type is Dataset or Software.
- New script to auto-declare conflicts of interest for journals activating the feature with an important backlog of articles
- New button on volume pages to download all the PDF files at once with a single PDF file (at the moment only available after login)

### Fixed

- The response to a modification request via "Contact without sending a new version" did not appear on the article page.
- Wrong links for the Dataverse repository with versioned datasets
- The graphical abstract was no longer displayed
- Volume's metadata was displayed twice

## v1.0.47 - 2025-01-08

### Added

- A script to generate sitemaps for the sites using the new UI/UX

### Fixed

- The graphical abstract is no longer displayed.
- When the "conflict of interest" option is activated reviewers are no longer visible for articles which the absence of conflict has been confirmed.
- The small logo to the left of each article's permanent identifier will only appear if at least one conflict has been confirmed (Nb. If you hover over this logo, you will see the number of conflicts reported for that article)

## v1.0.46.3 - 2025-01-06

### Fixed

- Failed to change email address for user profiles without affiliations.

## v1.0.46.2 - 2025-01-03

### Added

- classifications and project title metadata in the document's JSON export.
- improved scripts for importing and processing classifications

### Fixed

- [RT#229027]: Multiple files attached to the temporary version: only one file is visible on the article page.
- Prevent having empty roles when the last user role is deleted

## v1.0.53 - 2025-12-03

The changelog was not updated for this tag, but we keep the tag here as a reminder

## v1.0.52 - 2025-08-28

### Fixed

- RT#279519, TR#279601: Dates prior to the reviewing deadline were not permitted; however, it is now possible to change the reviewing deadline between the minimum and maximum dates.
- DOI metadata: stop overwriting acceptance date with the last modification date
- [#693](https://github.com/CCSDForge/episciences/issues/693) allow to save comment without attachment
- [#690](https://github.com/CCSDForge/episciences/issues/690) ORCID might be duplicated when one of the authors has no ORCID
- [#702](https://github.com/CCSDForge/episciences/issues/702) Inclusion of arxiv article version invalidates document identifier
- When updating Authors' affiliations, redirect to the latest paper version, not the 1st version
- [#629](https://github.com/CCSDForge/episciences/issues/629) Malfunction when updating volume description
- [#639](https://github.com/CCSDForge/episciences/issues/639) DOAJ Export unavailable. Added a function to Convert between ISO 639-2/T and ISO 639-2/B codes. DOAJ is in the team iso_639-2b whereas our current tools create iso_639-2t not supported by the DOAJ Schema
- Fixed unescaped identifiers causing invalid XML in OpenAire export format
- [#600](https://github.com/CCSDForge/episciences/issues/600) Abstract ignores line breaks
- [#694](https://github.com/CCSDForge/episciences/issues/694) accents via LaTeX macros in abstracts aren't rendered
- [#541](https://github.com/CCSDForge/episciences/issues/541) remove user assignation (editor) when a user is removed from the journal

### Added

- DOI management automation: Added shell scripts for batch DOI operations and enhanced getDoi.php with logging and journal fetching capabilities
- CSV import functionality for sections
- When pasting URL for repository identifiers, the URL is automatically cleaned to keep only the identifier, if the version number is in the URL, it is also automatically added into the "Version" field
- Javascript Tests with Jest
- PHP tests with Phpunit (updated test + new tests)
- Prettier to format Javascript
- Configured GitHub actions for CI (PHP+JS tests)
- Updated Renovate and Dependabot tests to target staging branch
- New Makefile with reorganised commands + new commands
- Document view:
    - Now displaying the abstract in all languages we have, prefixed with the language code
    - Now displaying keywords prefixed with their language code
- Relationships module:
    - added logs details for added/removed values
    - improved content escaping

### Changed

- Refactored some Javascript with more modern approach
- Editor Comments: in case conflict of interests is enabled in the journal, editors will receive the comments even if they have not yet answered to COI. If they declare a COI, they will be unassigned and will stop receiving editors comments
- Crossref title export: in case of multiple titles, the priority is given to the title in the language of the document or fallback to the fist title if no language
- [#657](https://github.com/CCSDForge/episciences/issues/657) Move "Download this file" button a bit and rename "Download this paper"

## v1.0.51 - 2025-04-07

### Changed

- [#650](https://github.com/CCSDForge/episciences/issues/650): send notifications when a cover letter is added or edited
- published dataset/software: the title of the data/software descriptor is its identifier.
- Only PDF files are backed up
- [#649](https://github.com/CCSDForge/episciences/issues/649): Assigned editors are now automatically added as (hidden) copies of messages sent to reviewers
- A copy of the default grid is proposed if the default grid is not empty, and if the destination grid is not empty either.

### Fixed

- [#653](https://github.com/CCSDForge/episciences/issues/653): Filtering email history doesn't work
- File Not Found Error
- Wrong link on page "Invitation to review an article" for temporary versions

## v1.0.50.1 - 2025-03-14

### Fixed

- Fixed CACHE_PATH not existing

### Changed

- Button label renamed for datasets/software papers

## v1.0.50 - 2025-03-13

### Added

- [#651](https://github.com/CCSDForge/episciences/issues/651): Statistics on invited and submitted referee reports

### Fixed

- Duplicate insertions when updating metadata
- [RT#238873]: it is now possible to replace an article with more recent version if its status is "Ready to publish"
- The average rating is displayed twice.
- [#654](https://github.com/CCSDForge/episciences/issues/654): Unable to modify a user's profile.
- [RT#237714]: The file renamed after downloading was not taken into account, which broke the link with this file.
  This bug affected the new version of the cover letter.
- IDE Warning: Method 'getVersion' not found in array|null

## v1.0.49 - 2025-02-19

### Added

- data descriptor viewer for data papers and software papers

### Changed

- Force the article type to "Article" only if it is a preprint when published,
- Improved licence enrichment for dataset

### Fixed

- Updating the volume number and year no longer works.
- [#652](https://github.com/CCSDForge/episciences/issues/652): missing translation
- RT#235731: failed to submit manuscript via ArXiv.
- Cancelling a file download does not work.
- Website resources: the file to be downloaded is saved repeatedly each time the page is refreshed.

## v1.0.48 - 2025-02-06

### Changed

- It is no longer possible to delete files attached to the temporary version.
- Metadata updates will take account of author enrichment, except for arXiv articles.
- It is now possible to select a new version when the article status is: Copy ed.: final version submitted, waiting for validation
- Version panel: temporary versions are now labeled as temporary

### Added

- It is now possible to attach a data/software descriptor to a submission if the submission type is Dataset or Software.
- New script to auto-declare conflicts of interest for journals activating the feature with an important backlog of articles
- New button on volume pages to download all the PDF files at once with a single PDF file (at the moment only available after login)

### Fixed

- The response to a modification request via "Contact without sending a new version" did not appear on the article page.
- Wrong links for the Dataverse repository with versioned datasets
- The graphical abstract was no longer displayed
- Volume's metadata was displayed twice

## v1.0.47 - 2025-01-08

### Added

- A script to generate sitemaps for the sites using the new UI/UX

### Fixed

- The graphical abstract is no longer displayed.
- When the "conflict of interest" option is activated reviewers are no longer visible for articles which the absence of conflict has been confirmed.
- The small logo to the left of each article's permanent identifier will only appear if at least one conflict has been confirmed (Nb. If you hover over this logo, you will see the number of conflicts reported for that article)

## v1.0.46.3 - 2025-01-06

### Fixed

- Failed to change email address for user profiles without affiliations.

## v1.0.46.2 - 2025-01-03

### Added

- classifications and project title metadata in the document's JSON export.
- improved scripts for importing and processing classifications

### Fixed

- [RT#229027]: Multiple files attached to the temporary version: only one file is visible on the article page.
- Prevent having empty roles when the last user role is deleted
