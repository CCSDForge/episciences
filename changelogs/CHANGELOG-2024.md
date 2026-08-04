# Change Log - 2024

All notable changes to this project for 2024.

## v1.0.46.1 - 2024-11-27

### Fixed

- In some cases, the translated 'name' and translated 'subject' of the email template may be lost when they are modified

## v1.0.46 - 2024-11-25

### Removed

- [#625](https://github.com/CCSDForge/episciences/issues/625): 'contact tech support' field has been removed (not used).

### Fixed

- Citations are sometimes not retrieved
- Linked Datasets
    - fixed display of swhids for snapshots + revisions
    - fixed delete button not working with multiple links in same category
    - fixed no confirmation message in confirmation modal
    - fixed duplicate HTML id elements
    - fixed style of forms, use bootstrap
    - used vanilla JS and fetch API instead of Jquery and xmlhttprequest to delete links
    - added missing translations
- Authors affiliation management: use bootstrap style for select
- Update dependency geoip2/geoip2 to v3: todo: downloading databases: https://github.com/P3TERX/GeoLite.mmdb/releases/tag/2024.07.13
- [#506](https://github.com/CCSDForge/episciences/issues/506): Fixed wrong photo displayed when switching profiles.
- Fixed missing volume title when updating metadata from an Episciences journal to HAL.
- [#555](https://github.com/CCSDForge/episciences/issues/555): improved conflict of interest explanations and translations
- Users who have already validated their profile on one of the journals can connect to another without having to validate their profile on that journal (even if they don't have one)
  [#567](https://github.com/CCSDForge/episciences/issues/567): random message recipients.

### Added

- [#513](https://github.com/CCSDForge/episciences/issues/513): Periodic Reminder E-Mails to Editors | editors-in-chief for 'Reviewed' and 'Submitted' articles
- Metadata Export to JSON: include article's citations.
- Created an Opaque UID for users using UUID
    - Profile photo is now stored according to the user's UUID.
    - Script to generate user's UUID.
- New settings for the journals:
    - 'Publisher'
    - 'Publisher location'
    - The values are exported to OpenAIRE and the DOAJ (not supported by Crossref)
- Button to be redirected to the Episciences bibliographic extraction application to import bibtex
- Using COAR Notify with HAL and Episciences to notify HAL of metadata updates for published articles.
- Added in ZBJATS: export bibliographical references from bibtex import
- Added button to redirect to Episciences citation management software without extracting form PDF
- [#418](https://github.com/CCSDForge/episciences/issues/418) Enable edition of relationship and type for the linked data
- Public representation of a document in JSON format (@see /json?v=2)
- [#475](https://github.com/CCSDForge/episciences/issues/475) feature to add biography to profile
- [#476](https://github.com/CCSDForge/episciences/issues/476) feature to add years in volumes forms
- Functionality for migrating news to the new sql news table
- Tinymce toolbar: make headings available
- New field in volume form (volume number)
- [#534](https://github.com/CCSDForge/episciences/issues/534) feature graphical abstract
- Automatic discovery of JEL Classification for published articles, with OpenAIREAPI service
- Automatic discovery of MSC2020 Classification for published articles, with zbMATH Open API service
- Automatic discovery of Reviews on published articles, with zbMATH Open API service

### Changed

- UI/UX: improvements to the editing form and the creation of reminders
- Bump tinymce to latest version (7.2.0)
- Crossref export:
    - For conference proceedings the title and original_language_title elements have been removed from proceedings_metadata: they are not authorised here
    - ROR added to the schema (did not work on the previous version)
- classification of keywords by language
- [#241](https://github.com/CCSDForge/episciences/issues/241) show users roles in mailing popup
- [#531](https://github.com/CCSDForge/episciences/issues/531) enhances volumes form translations

## v1.0.45.2 - 2024-10-22

## Fixed

- [#607](https://github.com/CCSDForge/episciences/issues/607):
    - fixed: the two tags '%COMMENT%%' and '%%ANSWER%%' have identical content.
    - improvement: the attachment with the original comment is now attached to the reply.

### Changed

- [#564](https://github.com/CCSDForge/episciences/issues/564): Fixed and changed French label in journal settings
- [#547](https://github.com/CCSDForge/episciences/issues/547): Ask for other editors opinion: select | deselect all option.
- COAR notifications from HAL: resubmit the same version as a new version.

## v1.0.45.1 - 2024-09-18

- [#585](https://github.com/CCSDForge/episciences/issues/585): The history of manually sent emails is not visible when the "Conflict of Interest" option is enabled.

## v1.0.45 - 2024-09-16

### Added

- [#580](https://github.com/CCSDForge/episciences/issues/580) zbMATH Open export: Authors: add affiliations and ORCIDs

### Fixed

- Volume names are not translated in the volume drop-down list on the reviewing grids list page.
- [#543](https://github.com/CCSDForge/episciences/issues/543): Not translate select options
- [#546](https://github.com/CCSDForge/episciences/issues/546): Editors with different local default date formats cannot update the revision deadline.
- [#571](https://github.com/CCSDForge/episciences/issues/571): The number of days before the review deadline was calculated incorrectly (one day difference).

### Changed

- Ability to block the automatic transfer of a submission from HAL

## v1.0.44.3 - 2024-07-23

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): Completing label updates, fixed label starting with a character outside the [a-z] range;

## v1.0.44.2 - 2024-06-24

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): update the label 'Reviewed' to 'Reviewed pending editorial decision';

## v1.0.44.2 - 2024-06-24

### Changed

- [512](https://github.com/CCSDForge/episciences/issues/512): is now possible to introduce a revision deadline at a later date if the publisher has not indicated a revision deadline (optional) when sending the request for revision.

### Fixed

- [#511](https://github.com/CCSDForge/episciences/issues/511) Do not filter out comments with no message when getting the suggestions form the comment manager.
- Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#507](https://github.com/CCSDForge/episciences/issues/507): Can't change URLs / HTML in email templates
- [#511](https://github.com/CCSDForge/episciences/issues/511):Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#508](https://github.com/CCSDForge/episciences/issues/508): Submission notification parameter: Only the selected roles will receive the notification, If no option is checked, the notification is sent to everyone.
- [#509](https://github.com/CCSDForge/episciences/issues/509): Pixelated photos (Editorial Staff members page)
- Fixed missing spaces with volumes and section on references for arXiv

## v1.0.44.1 - 2024-05-21

### Fixed "Uncaught TypeError: in_array(): Argument #2 ($haystack) must be of type array, null given."

## v1.0.44 - 2024-05-21

### Fixed

- Fixed a bug related to temporary versions with Zenodo:
    - Unable to enter the identifier when submitting a new version
    - submitting a new version results in an error message "the version of the article to be updated must be greater than the previous version".
- [#472](https://github.com/CCSDForge/episciences/issues/472)[Bug Report] English translation missing: Proposer dans le volume #472
- Duplicated submission when automatically transferring a new version from HAL.

### Added

- Add the latest publications to any page by inserting '<div id="widget-browse-latest"></div>' to the HTML source code of a page (same content as /browse/latest page)

## v1.0.43.1 - 2024-05-02

### Fixed

- Reallocation of a previously allocated doi.
- Resetting the position of a paper in a volume will not work if the paper is removed from the volume.
- [#469](https://github.com/CCSDForge/episciences/issues/469) [Bug Report] Add ORCID for an author with apostrophe in the name is broken into two

### Changed

- Allow the assignment of a DOI to a temporarily accepted article
- Automatic numbering of bibliographical references, increased padding between references

## v1.0.43 - 2024-04-17

### Changed

- Updated application's requirements to PHP 8.1.\* . All application dependencies were updated accordingly
- Section's translations are now stored in database instead of server files
- DOI Content Negotiation support for linked data automatically obtained when submitting or updating metadata + code refactoring
- Crossref Export: the unstructured reference is added with the DOI. Previously it was either DOI or the unstructured

### Fixed

- Scholexplorer API usage: The automated script to discover related datasets to published articles now only handle Datasets

### Added

- [#431](https://github.com/CCSDForge/episciences/issues/431): A link from the peer review result page to the article's administration page.
- Using Citation Style Language and APIs we now retrieve and display the text of the references added for datasets, software, HAL Ids, SWHID, arXiv Ids)
- two new statistical indicators ( Submission-acceptance time, Submission-publication time) to the "At a glance" section.
- A new button as a shortcut access the Episciences bibliographic extraction application to import bibtex
- If the metadata of an Episciences publication hosted on HAL is updated, we now send a COAR notification to HAL to trigger an update of the metadata on HAL.
- ZBJATS export: bibliographical references from bibtex import are now also supported and exported

## v1.0.42.5 - 2024-04-17

### Fixed

- [#457](https://github.com/CCSDForge/episciences/issues/457): DOAJ fullText record gives an url that can't be accessed.
- SignPosting headers: fixed missing doi.org domain prefix for DOIs

### Changed

- Taking into account the new value (3) after changing the authorised values in the user CAS table.

## v1.0.42.4 - 2024-03-27

### Fixed

- [#453](https://github.com/CCSDForge/episciences/issues/453): Show a more prominent error when there is a CSRF token error after the editor's comment has been sent.
- When a new version is transferred form HAL, the DOI assigned to the previous version is not restored in the new document.
- [#449](https://github.com/CCSDForge/episciences/issues/449): related to [#169](https://github.com/CCSDForge/episciences/issues/169): the author does not see the review (visible to the administrator but not to the author) on a non-editable version.
- [#458](https://github.com/CCSDForge/episciences/issues/458) [Bug Report] Bibtex export volume name is malformed

## v1.0.42.3 - 2024-03-20

### Fixed

- [#446](https://github.com/CCSDForge/episciences/issues/446) and [#448](https://github.com/CCSDForge/episciences/issues/448): Paper list drops some spaces in abstracts.
- [#450](https://github.com/CCSDForge/episciences/issues/450): Fixed a server configuration issue for Theoretics

### Changed

- [#450](https://github.com/CCSDForge/episciences/issues/450): Open the target of the journal's logo in the same page/tab

## v1.0.42.2 - 2024-03-19

### Fixed

- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers:
  Make sure the invitation is sent x days [delay] later.
- Wrong display of titles and abstracts on the latest articles page: these will now be displayed in the main language [if available].

## v1.0.42.1 - 2024-03-12

### Changed

- delete a volume: add assigned papers error's message.
- Sorted paper by volume: include assigned papers to this one as a secondary volume
- [#445](https://github.com/CCSDForge/episciences/issues/445): do not limit the number of papers per page to 10: the default limit (100) is used + acceptance date descending order.

### Fixed

- Exporting volumes in DOAJ format does not work for Secondary Volumes
- [#423](https://github.com/CCSDForge/episciences/issues/423): fix typo Technical support Email.
- [#443](https://github.com/CCSDForge/episciences/issues/443): In the templates (New version - author and co-author copy, New temporary version author and co-author copy) the tag %%PERMANENT_ARTICLE_ID%% is used, but it did not appear in the subject of the message.
- [#443](https://github.com/CCSDForge/episciences/issues/443): no content for tags %%TAG_AUTHORS_NAMES%% and %%TAG_CONTRIBUTOR_FULL_NAME%%.
- The guest editor could not see the cover letter for version 2 and the reviewers' names (Revues/Reviews section) on the first version.
- Links to documentation : the "About version numbers" link url has changed and has not been updated.
- Browse by year: documents have been indexed by year of submission instead of year of publication

## v1.0.42 - 2024-02-15

### Added

- Added an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings. The feature is also handled by the Episciences API

### Fixed

- Submission form validation fails when replacing existing version.

## v1.0.41.7 - 2024-02-12

### Fixed

- [Bug Report] Browse by authors or date: wrong links on the titles #435
- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers.
- Fixed ORCID max length of user profiles

### Changed

- Invite a new user form: make it possible to choose between two languages, even for uni-lingual sites & label renamed to 'Default Language'.

## v1.0.41.7 - 2024-01-23

### Changed

- [COAR Notify] processing of new versions + enhancements
- Improvements on the indexing process with Apache Solr

### Fixed

- Script ZBJATS : Skip documents which are not articles with a PDF, such as datasets
- Application error: Argument 1 passed to Episciences_Volume::setTitles() must be of the type array or null, string give.
- "false positive" for missing translations in logs.
- [COAR Notify] when processing several submission at the same time, notification of submissions were sent to wrong recipients
- [Acceptance date] During the publication process, if the article is accepted with a temporary version, then its acceptance date is that of the 1st temporary version. The problem did not exist on published articles.
- Citations of published papers: Improvement in obtaining the source journal from openAlex's response

## v1.0.41.6 - 2024-01-15

### Fixed

- PHP Notice: FAILED_TO_COPY_FILE_ERROR

## v1.0.41.5 - 2024-01-11

### Fixed

- Typo [423](https://github.com/CCSDForge/episciences/issues/423)
- Crossref export of bibliographical references: fixed unescaped HTML, added a log for XML errors
- Fix #417 Identifiers other than SWHID for Software solutions: remove domain prefixing SWH for all software, add the right domain for software (other than SWH), add correct domain for software in TEI

## v1.0.46.1 - 2024-11-27

### Fixed

- In some cases, the translated 'name' and translated 'subject' of the email template may be lost when they are modified

## v1.0.46 - 2024-11-25

### Removed

- [#625](https://github.com/CCSDForge/episciences/issues/625): 'contact tech support' field has been removed (not used).

### Fixed

- Citations are sometimes not retrieved
- Linked Datasets
    - fixed display of swhids for snapshots + revisions
    - fixed delete button not working with multiple links in same category
    - fixed no confirmation message in confirmation modal
    - fixed duplicate HTML id elements
    - fixed style of forms, use bootstrap
    - used vanilla JS and fetch API instead of Jquery and xmlhttprequest to delete links
    - added missing translations
- Authors affiliation management: use bootstrap style for select
- Update dependency geoip2/geoip2 to v3: todo: downloading databases: https://github.com/P3TERX/GeoLite.mmdb/releases/tag/2024.07.13
- [#506](https://github.com/CCSDForge/episciences/issues/506): Fixed wrong photo displayed when switching profiles.
- Fixed missing volume title when updating metadata from an Episciences journal to HAL.
- [#555](https://github.com/CCSDForge/episciences/issues/555): improved conflict of interest explanations and translations
- Users who have already validated their profile on one of the journals can connect to another without having to validate their profile on that journal (even if they don't have one)
  [#567](https://github.com/CCSDForge/episciences/issues/567): random message recipients.

### Added

- [#513](https://github.com/CCSDForge/episciences/issues/513): Periodic Reminder E-Mails to Editors | editors-in-chief for 'Reviewed' and 'Submitted' articles
- Metadata Export to JSON: include article's citations.
- Created an Opaque UID for users using UUID
    - Profile photo is now stored according to the user's UUID.
    - Script to generate user's UUID.
- New settings for the journals:
    - 'Publisher'
    - 'Publisher location'
    - The values are exported to OpenAIRE and the DOAJ (not supported by Crossref)
- Button to be redirected to the Episciences bibliographic extraction application to import bibtex
- Using COAR Notify with HAL and Episciences to notify HAL of metadata updates for published articles.
- Added in ZBJATS: export bibliographical references from bibtex import
- Added button to redirect to Episciences citation management software without extracting form PDF
- [#418](https://github.com/CCSDForge/episciences/issues/418) Enable edition of relationship and type for the linked data
- Public representation of a document in JSON format (@see /json?v=2)
- [#475](https://github.com/CCSDForge/episciences/issues/475) feature to add biography to profile
- [#476](https://github.com/CCSDForge/episciences/issues/476) feature to add years in volumes forms
- Functionality for migrating news to the new sql news table
- Tinymce toolbar: make headings available
- New field in volume form (volume number)
- [#534](https://github.com/CCSDForge/episciences/issues/534) feature graphical abstract
- Automatic discovery of JEL Classification for published articles, with OpenAIREAPI service
- Automatic discovery of MSC2020 Classification for published articles, with zbMATH Open API service
- Automatic discovery of Reviews on published articles, with zbMATH Open API service

### Changed

- UI/UX: improvements to the editing form and the creation of reminders
- Bump tinymce to latest version (7.2.0)
- Crossref export:
    - For conference proceedings the title and original_language_title elements have been removed from proceedings_metadata: they are not authorised here
    - ROR added to the schema (did not work on the previous version)
- classification of keywords by language
- [#241](https://github.com/CCSDForge/episciences/issues/241) show users roles in mailing popup
- [#531](https://github.com/CCSDForge/episciences/issues/531) enhances volumes form translations

## v1.0.45.2 - 2024-10-22

## Fixed

- [#607](https://github.com/CCSDForge/episciences/issues/607):
    - fixed: the two tags '%COMMENT%%' and '%%ANSWER%%' have identical content.
    - improvement: the attachment with the original comment is now attached to the reply.

### Changed

- [#564](https://github.com/CCSDForge/episciences/issues/564): Fixed and changed French label in journal settings
- [#547](https://github.com/CCSDForge/episciences/issues/547): Ask for other editors opinion: select | deselect all option.
- COAR notifications from HAL: resubmit the same version as a new version.

## v1.0.45.1 - 2024-09-18

- [#585](https://github.com/CCSDForge/episciences/issues/585): The history of manually sent emails is not visible when the "Conflict of Interest" option is enabled.

## v1.0.45 - 2024-09-16

### Added

- [#580](https://github.com/CCSDForge/episciences/issues/580) zbMATH Open export: Authors: add affiliations and ORCIDs

### Fixed

- Volume names are not translated in the volume drop-down list on the reviewing grids list page.
- [#543](https://github.com/CCSDForge/episciences/issues/543): Not translate select options
- [#546](https://github.com/CCSDForge/episciences/issues/546): Editors with different local default date formats cannot update the revision deadline.
- [#571](https://github.com/CCSDForge/episciences/issues/571): The number of days before the review deadline was calculated incorrectly (one day difference).

### Changed

- Ability to block the automatic transfer of a submission from HAL

## v1.0.44.3 - 2024-07-23

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): Completing label updates, fixed label starting with a character outside the [a-z] range;

## v1.0.44.2 - 2024-06-24

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): update the label 'Reviewed' to 'Reviewed pending editorial decision';

## v1.0.44.2 - 2024-06-24

### Changed

- [512](https://github.com/CCSDForge/episciences/issues/512): is now possible to introduce a revision deadline at a later date if the publisher has not indicated a revision deadline (optional) when sending the request for revision.

### Fixed

- [#511](https://github.com/CCSDForge/episciences/issues/511) Do not filter out comments with no message when getting the suggestions form the comment manager.
- Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#507](https://github.com/CCSDForge/episciences/issues/507): Can't change URLs / HTML in email templates
- [#511](https://github.com/CCSDForge/episciences/issues/511):Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#508](https://github.com/CCSDForge/episciences/issues/508): Submission notification parameter: Only the selected roles will receive the notification, If no option is checked, the notification is sent to everyone.
- [#509](https://github.com/CCSDForge/episciences/issues/509): Pixelated photos (Editorial Staff members page)
- Fixed missing spaces with volumes and section on references for arXiv

## v1.0.44.1 - 2024-05-21

### Fixed "Uncaught TypeError: in_array(): Argument #2 ($haystack) must be of type array, null given."

## v1.0.44 - 2024-05-21

### Fixed

- Fixed a bug related to temporary versions with Zenodo:
    - Unable to enter the identifier when submitting a new version
    - submitting a new version results in an error message "the version of the article to be updated must be greater than the previous version".
- [#472](https://github.com/CCSDForge/episciences/issues/472)[Bug Report] English translation missing: Proposer dans le volume #472
- Duplicated submission when automatically transferring a new version from HAL.

### Added

- Add the latest publications to any page by inserting '<div id="widget-browse-latest"></div>' to the HTML source code of a page (same content as /browse/latest page)

## v1.0.43.1 - 2024-05-02

### Fixed

- Reallocation of a previously allocated doi.
- Resetting the position of a paper in a volume will not work if the paper is removed from the volume.
- [#469](https://github.com/CCSDForge/episciences/issues/469) [Bug Report] Add ORCID for an author with apostrophe in the name is broken into two

### Changed

- Allow the assignment of a DOI to a temporarily accepted article
- Automatic numbering of bibliographical references, increased padding between references

## v1.0.43 - 2024-04-17

### Changed

- Updated application's requirements to PHP 8.1.\* . All application dependencies were updated accordingly
- Section's translations are now stored in database instead of server files
- DOI Content Negotiation support for linked data automatically obtained when submitting or updating metadata + code refactoring
- Crossref Export: the unstructured reference is added with the DOI. Previously it was either DOI or the unstructured

### Fixed

- Scholexplorer API usage: The automated script to discover related datasets to published articles now only handle Datasets

### Added

- [#431](https://github.com/CCSDForge/episciences/issues/431): A link from the peer review result page to the article's administration page.
- Using Citation Style Language and APIs we now retrieve and display the text of the references added for datasets, software, HAL Ids, SWHID, arXiv Ids)
- two new statistical indicators ( Submission-acceptance time, Submission-publication time) to the "At a glance" section.
- A new button as a shortcut access the Episciences bibliographic extraction application to import bibtex
- If the metadata of an Episciences publication hosted on HAL is updated, we now send a COAR notification to HAL to trigger an update of the metadata on HAL.
- ZBJATS export: bibliographical references from bibtex import are now also supported and exported

## v1.0.42.5 - 2024-04-17

### Fixed

- [#457](https://github.com/CCSDForge/episciences/issues/457): DOAJ fullText record gives an url that can't be accessed.
- SignPosting headers: fixed missing doi.org domain prefix for DOIs

### Changed

- Taking into account the new value (3) after changing the authorised values in the user CAS table.

## v1.0.42.4 - 2024-03-27

### Fixed

- [#453](https://github.com/CCSDForge/episciences/issues/453): Show a more prominent error when there is a CSRF token error after the editor's comment has been sent.
- When a new version is transferred form HAL, the DOI assigned to the previous version is not restored in the new document.
- [#449](https://github.com/CCSDForge/episciences/issues/449): related to [#169](https://github.com/CCSDForge/episciences/issues/169): the author does not see the review (visible to the administrator but not to the author) on a non-editable version.
- [#458](https://github.com/CCSDForge/episciences/issues/458) [Bug Report] Bibtex export volume name is malformed

## v1.0.42.3 - 2024-03-20

### Fixed

- [#446](https://github.com/CCSDForge/episciences/issues/446) and [#448](https://github.com/CCSDForge/episciences/issues/448): Paper list drops some spaces in abstracts.
- [#450](https://github.com/CCSDForge/episciences/issues/450): Fixed a server configuration issue for Theoretics

### Changed

- [#450](https://github.com/CCSDForge/episciences/issues/450): Open the target of the journal's logo in the same page/tab

## v1.0.42.2 - 2024-03-19

### Fixed

- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers:
  Make sure the invitation is sent x days [delay] later.
- Wrong display of titles and abstracts on the latest articles page: these will now be displayed in the main language [if available].

## v1.0.42.1 - 2024-03-12

### Changed

- delete a volume: add assigned papers error's message.
- Sorted paper by volume: include assigned papers to this one as a secondary volume
- [#445](https://github.com/CCSDForge/episciences/issues/445): do not limit the number of papers per page to 10: the default limit (100) is used + acceptance date descending order.

### Fixed

- Exporting volumes in DOAJ format does not work for Secondary Volumes
- [#423](https://github.com/CCSDForge/episciences/issues/423): fix typo Technical support Email.
- [#443](https://github.com/CCSDForge/episciences/issues/443): In the templates (New version - author and co-author copy, New temporary version author and co-author copy) the tag %%PERMANENT_ARTICLE_ID%% is used, but it did not appear in the subject of the message.
- [#443](https://github.com/CCSDForge/episciences/issues/443): no content for tags %%TAG_AUTHORS_NAMES%% and %%TAG_CONTRIBUTOR_FULL_NAME%%.
- The guest editor could not see the cover letter for version 2 and the reviewers' names (Revues/Reviews section) on the first version.
- Links to documentation : the "About version numbers" link url has changed and has not been updated.
- Browse by year: documents have been indexed by year of submission instead of year of publication

## v1.0.42 - 2024-02-15

### Added

- Added an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings. The feature is also handled by the Episciences API

### Fixed

- Submission form validation fails when replacing existing version.

## v1.0.41.7 - 2024-02-12

### Fixed

- [Bug Report] Browse by authors or date: wrong links on the titles #435
- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers.
- Fixed ORCID max length of user profiles

### Changed

- Invite a new user form: make it possible to choose between two languages, even for uni-lingual sites & label renamed to 'Default Language'.

## v1.0.41.7 - 2024-01-23

### Changed

- [COAR Notify] processing of new versions + enhancements
- Improvements on the indexing process with Apache Solr

### Fixed

- Script ZBJATS : Skip documents which are not articles with a PDF, such as datasets
- Application error: Argument 1 passed to Episciences_Volume::setTitles() must be of the type array or null, string give.
- "false positive" for missing translations in logs.
- [COAR Notify] when processing several submission at the same time, notification of submissions were sent to wrong recipients
- [Acceptance date] During the publication process, if the article is accepted with a temporary version, then its acceptance date is that of the 1st temporary version. The problem did not exist on published articles.
- Citations of published papers: Improvement in obtaining the source journal from openAlex's response

## v1.0.41.6 - 2024-01-15

### Fixed

- PHP Notice: FAILED_TO_COPY_FILE_ERROR

## v1.0.41.5 - 2024-01-11

### Fixed

- Typo [423](https://github.com/CCSDForge/episciences/issues/423)
- Crossref export of bibliographical references: fixed unescaped HTML, added a log for XML errors
- Fix #417 Identifiers other than SWHID for Software solutions: remove domain prefixing SWH for all software, add the right domain for software (other than SWH), add correct domain for software in TEI

## v1.0.46.1 - 2024-11-27

### Fixed

- In some cases, the translated 'name' and translated 'subject' of the email template may be lost when they are modified

## v1.0.46 - 2024-11-25

### Removed

- [#625](https://github.com/CCSDForge/episciences/issues/625): 'contact tech support' field has been removed (not used).

### Fixed

- Citations are sometimes not retrieved
- Linked Datasets
    - fixed display of swhids for snapshots + revisions
    - fixed delete button not working with multiple links in same category
    - fixed no confirmation message in confirmation modal
    - fixed duplicate HTML id elements
    - fixed style of forms, use bootstrap
    - used vanilla JS and fetch API instead of Jquery and xmlhttprequest to delete links
    - added missing translations
- Authors affiliation management: use bootstrap style for select
- Update dependency geoip2/geoip2 to v3: todo: downloading databases: https://github.com/P3TERX/GeoLite.mmdb/releases/tag/2024.07.13
- [#506](https://github.com/CCSDForge/episciences/issues/506): Fixed wrong photo displayed when switching profiles.
- Fixed missing volume title when updating metadata from an Episciences journal to HAL.
- [#555](https://github.com/CCSDForge/episciences/issues/555): improved conflict of interest explanations and translations
- Users who have already validated their profile on one of the journals can connect to another without having to validate their profile on that journal (even if they don't have one)
  [#567](https://github.com/CCSDForge/episciences/issues/567): random message recipients.

### Added

- [#513](https://github.com/CCSDForge/episciences/issues/513): Periodic Reminder E-Mails to Editors | editors-in-chief for 'Reviewed' and 'Submitted' articles
- Metadata Export to JSON: include article's citations.
- Created an Opaque UID for users using UUID
    - Profile photo is now stored according to the user's UUID.
    - Script to generate user's UUID.
- New settings for the journals:
    - 'Publisher'
    - 'Publisher location'
    - The values are exported to OpenAIRE and the DOAJ (not supported by Crossref)
- Button to be redirected to the Episciences bibliographic extraction application to import bibtex
- Using COAR Notify with HAL and Episciences to notify HAL of metadata updates for published articles.
- Added in ZBJATS: export bibliographical references from bibtex import
- Added button to redirect to Episciences citation management software without extracting form PDF
- [#418](https://github.com/CCSDForge/episciences/issues/418) Enable edition of relationship and type for the linked data
- Public representation of a document in JSON format (@see /json?v=2)
- [#475](https://github.com/CCSDForge/episciences/issues/475) feature to add biography to profile
- [#476](https://github.com/CCSDForge/episciences/issues/476) feature to add years in volumes forms
- Functionality for migrating news to the new sql news table
- Tinymce toolbar: make headings available
- New field in volume form (volume number)
- [#534](https://github.com/CCSDForge/episciences/issues/534) feature graphical abstract
- Automatic discovery of JEL Classification for published articles, with OpenAIREAPI service
- Automatic discovery of MSC2020 Classification for published articles, with zbMATH Open API service
- Automatic discovery of Reviews on published articles, with zbMATH Open API service

### Changed

- UI/UX: improvements to the editing form and the creation of reminders
- Bump tinymce to latest version (7.2.0)
- Crossref export:
    - For conference proceedings the title and original_language_title elements have been removed from proceedings_metadata: they are not authorised here
    - ROR added to the schema (did not work on the previous version)
- classification of keywords by language
- [#241](https://github.com/CCSDForge/episciences/issues/241) show users roles in mailing popup
- [#531](https://github.com/CCSDForge/episciences/issues/531) enhances volumes form translations

## v1.0.45.2 - 2024-10-22

## Fixed

- [#607](https://github.com/CCSDForge/episciences/issues/607):
    - fixed: the two tags '%COMMENT%%' and '%%ANSWER%%' have identical content.
    - improvement: the attachment with the original comment is now attached to the reply.

### Changed

- [#564](https://github.com/CCSDForge/episciences/issues/564): Fixed and changed French label in journal settings
- [#547](https://github.com/CCSDForge/episciences/issues/547): Ask for other editors opinion: select | deselect all option.
- COAR notifications from HAL: resubmit the same version as a new version.

## v1.0.45.1 - 2024-09-18

- [#585](https://github.com/CCSDForge/episciences/issues/585): The history of manually sent emails is not visible when the "Conflict of Interest" option is enabled.

## v1.0.45 - 2024-09-16

### Added

- [#580](https://github.com/CCSDForge/episciences/issues/580) zbMATH Open export: Authors: add affiliations and ORCIDs

### Fixed

- Volume names are not translated in the volume drop-down list on the reviewing grids list page.
- [#543](https://github.com/CCSDForge/episciences/issues/543): Not translate select options
- [#546](https://github.com/CCSDForge/episciences/issues/546): Editors with different local default date formats cannot update the revision deadline.
- [#571](https://github.com/CCSDForge/episciences/issues/571): The number of days before the review deadline was calculated incorrectly (one day difference).

### Changed

- Ability to block the automatic transfer of a submission from HAL

## v1.0.44.3 - 2024-07-23

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): Completing label updates, fixed label starting with a character outside the [a-z] range;

## v1.0.44.2 - 2024-06-24

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): update the label 'Reviewed' to 'Reviewed pending editorial decision';

## v1.0.44.2 - 2024-06-24

### Changed

- [512](https://github.com/CCSDForge/episciences/issues/512): is now possible to introduce a revision deadline at a later date if the publisher has not indicated a revision deadline (optional) when sending the request for revision.

### Fixed

- [#511](https://github.com/CCSDForge/episciences/issues/511) Do not filter out comments with no message when getting the suggestions form the comment manager.
- Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#507](https://github.com/CCSDForge/episciences/issues/507): Can't change URLs / HTML in email templates
- [#511](https://github.com/CCSDForge/episciences/issues/511):Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#508](https://github.com/CCSDForge/episciences/issues/508): Submission notification parameter: Only the selected roles will receive the notification, If no option is checked, the notification is sent to everyone.
- [#509](https://github.com/CCSDForge/episciences/issues/509): Pixelated photos (Editorial Staff members page)
- Fixed missing spaces with volumes and section on references for arXiv

## v1.0.44.1 - 2024-05-21

### Fixed "Uncaught TypeError: in_array(): Argument #2 ($haystack) must be of type array, null given."

## v1.0.44 - 2024-05-21

### Fixed

- Fixed a bug related to temporary versions with Zenodo:
    - Unable to enter the identifier when submitting a new version
    - submitting a new version results in an error message "the version of the article to be updated must be greater than the previous version".
- [#472](https://github.com/CCSDForge/episciences/issues/472)[Bug Report] English translation missing: Proposer dans le volume #472
- Duplicated submission when automatically transferring a new version from HAL.

### Added

- Add the latest publications to any page by inserting '<div id="widget-browse-latest"></div>' to the HTML source code of a page (same content as /browse/latest page)

## v1.0.43.1 - 2024-05-02

### Fixed

- Reallocation of a previously allocated doi.
- Resetting the position of a paper in a volume will not work if the paper is removed from the volume.
- [#469](https://github.com/CCSDForge/episciences/issues/469) [Bug Report] Add ORCID for an author with apostrophe in the name is broken into two

### Changed

- Allow the assignment of a DOI to a temporarily accepted article
- Automatic numbering of bibliographical references, increased padding between references

## v1.0.43 - 2024-04-17

### Changed

- Updated application's requirements to PHP 8.1.\* . All application dependencies were updated accordingly
- Section's translations are now stored in database instead of server files
- DOI Content Negotiation support for linked data automatically obtained when submitting or updating metadata + code refactoring
- Crossref Export: the unstructured reference is added with the DOI. Previously it was either DOI or the unstructured

### Fixed

- Scholexplorer API usage: The automated script to discover related datasets to published articles now only handle Datasets

### Added

- [#431](https://github.com/CCSDForge/episciences/issues/431): A link from the peer review result page to the article's administration page.
- Using Citation Style Language and APIs we now retrieve and display the text of the references added for datasets, software, HAL Ids, SWHID, arXiv Ids)
- two new statistical indicators ( Submission-acceptance time, Submission-publication time) to the "At a glance" section.
- A new button as a shortcut access the Episciences bibliographic extraction application to import bibtex
- If the metadata of an Episciences publication hosted on HAL is updated, we now send a COAR notification to HAL to trigger an update of the metadata on HAL.
- ZBJATS export: bibliographical references from bibtex import are now also supported and exported

## v1.0.42.5 - 2024-04-17

### Fixed

- [#457](https://github.com/CCSDForge/episciences/issues/457): DOAJ fullText record gives an url that can't be accessed.
- SignPosting headers: fixed missing doi.org domain prefix for DOIs

### Changed

- Taking into account the new value (3) after changing the authorised values in the user CAS table.

## v1.0.42.4 - 2024-03-27

### Fixed

- [#453](https://github.com/CCSDForge/episciences/issues/453): Show a more prominent error when there is a CSRF token error after the editor's comment has been sent.
- When a new version is transferred form HAL, the DOI assigned to the previous version is not restored in the new document.
- [#449](https://github.com/CCSDForge/episciences/issues/449): related to [#169](https://github.com/CCSDForge/episciences/issues/169): the author does not see the review (visible to the administrator but not to the author) on a non-editable version.
- [#458](https://github.com/CCSDForge/episciences/issues/458) [Bug Report] Bibtex export volume name is malformed

## v1.0.42.3 - 2024-03-20

### Fixed

- [#446](https://github.com/CCSDForge/episciences/issues/446) and [#448](https://github.com/CCSDForge/episciences/issues/448): Paper list drops some spaces in abstracts.
- [#450](https://github.com/CCSDForge/episciences/issues/450): Fixed a server configuration issue for Theoretics

### Changed

- [#450](https://github.com/CCSDForge/episciences/issues/450): Open the target of the journal's logo in the same page/tab

## v1.0.42.2 - 2024-03-19

### Fixed

- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers:
  Make sure the invitation is sent x days [delay] later.
- Wrong display of titles and abstracts on the latest articles page: these will now be displayed in the main language [if available].

## v1.0.42.1 - 2024-03-12

### Changed

- delete a volume: add assigned papers error's message.
- Sorted paper by volume: include assigned papers to this one as a secondary volume
- [#445](https://github.com/CCSDForge/episciences/issues/445): do not limit the number of papers per page to 10: the default limit (100) is used + acceptance date descending order.

### Fixed

- Exporting volumes in DOAJ format does not work for Secondary Volumes
- [#423](https://github.com/CCSDForge/episciences/issues/423): fix typo Technical support Email.
- [#443](https://github.com/CCSDForge/episciences/issues/443): In the templates (New version - author and co-author copy, New temporary version author and co-author copy) the tag %%PERMANENT_ARTICLE_ID%% is used, but it did not appear in the subject of the message.
- [#443](https://github.com/CCSDForge/episciences/issues/443): no content for tags %%TAG_AUTHORS_NAMES%% and %%TAG_CONTRIBUTOR_FULL_NAME%%.
- The guest editor could not see the cover letter for version 2 and the reviewers' names (Revues/Reviews section) on the first version.
- Links to documentation : the "About version numbers" link url has changed and has not been updated.
- Browse by year: documents have been indexed by year of submission instead of year of publication

## v1.0.42 - 2024-02-15

### Added

- Added an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings. The feature is also handled by the Episciences API

### Fixed

- Submission form validation fails when replacing existing version.

## v1.0.41.7 - 2024-02-12

### Fixed

- [Bug Report] Browse by authors or date: wrong links on the titles #435
- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers.
- Fixed ORCID max length of user profiles

### Changed

- Invite a new user form: make it possible to choose between two languages, even for uni-lingual sites & label renamed to 'Default Language'.

## v1.0.41.7 - 2024-01-23

### Changed

- [COAR Notify] processing of new versions + enhancements
- Improvements on the indexing process with Apache Solr

### Fixed

- Script ZBJATS : Skip documents which are not articles with a PDF, such as datasets
- Application error: Argument 1 passed to Episciences_Volume::setTitles() must be of the type array or null, string give.
- "false positive" for missing translations in logs.
- [COAR Notify] when processing several submission at the same time, notification of submissions were sent to wrong recipients
- [Acceptance date] During the publication process, if the article is accepted with a temporary version, then its acceptance date is that of the 1st temporary version. The problem did not exist on published articles.
- Citations of published papers: Improvement in obtaining the source journal from openAlex's response

## v1.0.41.6 - 2024-01-15

### Fixed

- PHP Notice: FAILED_TO_COPY_FILE_ERROR

## v1.0.41.5 - 2024-01-11

### Fixed

- Typo [423](https://github.com/CCSDForge/episciences/issues/423)
- Crossref export of bibliographical references: fixed unescaped HTML, added a log for XML errors
- Fix #417 Identifiers other than SWHID for Software solutions: remove domain prefixing SWH for all software, add the right domain for software (other than SWH), add correct domain for software in TEI

## v1.0.46.1 - 2024-11-27

### Fixed

- In some cases, the translated 'name' and translated 'subject' of the email template may be lost when they are modified

## v1.0.46 - 2024-11-25

### Removed

- [#625](https://github.com/CCSDForge/episciences/issues/625): 'contact tech support' field has been removed (not used).

### Fixed

- Citations are sometimes not retrieved
- Linked Datasets
    - fixed display of swhids for snapshots + revisions
    - fixed delete button not working with multiple links in same category
    - fixed no confirmation message in confirmation modal
    - fixed duplicate HTML id elements
    - fixed style of forms, use bootstrap
    - used vanilla JS and fetch API instead of Jquery and xmlhttprequest to delete links
    - added missing translations
- Authors affiliation management: use bootstrap style for select
- Update dependency geoip2/geoip2 to v3: todo: downloading databases: https://github.com/P3TERX/GeoLite.mmdb/releases/tag/2024.07.13
- [#506](https://github.com/CCSDForge/episciences/issues/506): Fixed wrong photo displayed when switching profiles.
- Fixed missing volume title when updating metadata from an Episciences journal to HAL.
- [#555](https://github.com/CCSDForge/episciences/issues/555): improved conflict of interest explanations and translations
- Users who have already validated their profile on one of the journals can connect to another without having to validate their profile on that journal (even if they don't have one)
  [#567](https://github.com/CCSDForge/episciences/issues/567): random message recipients.

### Added

- [#513](https://github.com/CCSDForge/episciences/issues/513): Periodic Reminder E-Mails to Editors | editors-in-chief for 'Reviewed' and 'Submitted' articles
- Metadata Export to JSON: include article's citations.
- Created an Opaque UID for users using UUID
    - Profile photo is now stored according to the user's UUID.
    - Script to generate user's UUID.
- New settings for the journals:
    - 'Publisher'
    - 'Publisher location'
    - The values are exported to OpenAIRE and the DOAJ (not supported by Crossref)
- Button to be redirected to the Episciences bibliographic extraction application to import bibtex
- Using COAR Notify with HAL and Episciences to notify HAL of metadata updates for published articles.
- Added in ZBJATS: export bibliographical references from bibtex import
- Added button to redirect to Episciences citation management software without extracting form PDF
- [#418](https://github.com/CCSDForge/episciences/issues/418) Enable edition of relationship and type for the linked data
- Public representation of a document in JSON format (@see /json?v=2)
- [#475](https://github.com/CCSDForge/episciences/issues/475) feature to add biography to profile
- [#476](https://github.com/CCSDForge/episciences/issues/476) feature to add years in volumes forms
- Functionality for migrating news to the new sql news table
- Tinymce toolbar: make headings available
- New field in volume form (volume number)
- [#534](https://github.com/CCSDForge/episciences/issues/534) feature graphical abstract
- Automatic discovery of JEL Classification for published articles, with OpenAIREAPI service
- Automatic discovery of MSC2020 Classification for published articles, with zbMATH Open API service
- Automatic discovery of Reviews on published articles, with zbMATH Open API service

### Changed

- UI/UX: improvements to the editing form and the creation of reminders
- Bump tinymce to latest version (7.2.0)
- Crossref export:
    - For conference proceedings the title and original_language_title elements have been removed from proceedings_metadata: they are not authorised here
    - ROR added to the schema (did not work on the previous version)
- classification of keywords by language
- [#241](https://github.com/CCSDForge/episciences/issues/241) show users roles in mailing popup
- [#531](https://github.com/CCSDForge/episciences/issues/531) enhances volumes form translations

## v1.0.45.2 - 2024-10-22

## Fixed

- [#607](https://github.com/CCSDForge/episciences/issues/607):
    - fixed: the two tags '%COMMENT%%' and '%%ANSWER%%' have identical content.
    - improvement: the attachment with the original comment is now attached to the reply.

### Changed

- [#564](https://github.com/CCSDForge/episciences/issues/564): Fixed and changed French label in journal settings
- [#547](https://github.com/CCSDForge/episciences/issues/547): Ask for other editors opinion: select | deselect all option.
- COAR notifications from HAL: resubmit the same version as a new version.

## v1.0.45.1 - 2024-09-18

- [#585](https://github.com/CCSDForge/episciences/issues/585): The history of manually sent emails is not visible when the "Conflict of Interest" option is enabled.

## v1.0.45 - 2024-09-16

### Added

- [#580](https://github.com/CCSDForge/episciences/issues/580) zbMATH Open export: Authors: add affiliations and ORCIDs

### Fixed

- Volume names are not translated in the volume drop-down list on the reviewing grids list page.
- [#543](https://github.com/CCSDForge/episciences/issues/543): Not translate select options
- [#546](https://github.com/CCSDForge/episciences/issues/546): Editors with different local default date formats cannot update the revision deadline.
- [#571](https://github.com/CCSDForge/episciences/issues/571): The number of days before the review deadline was calculated incorrectly (one day difference).

### Changed

- Ability to block the automatic transfer of a submission from HAL

## v1.0.44.3 - 2024-07-23

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): Completing label updates, fixed label starting with a character outside the [a-z] range;

## v1.0.44.2 - 2024-06-24

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): update the label 'Reviewed' to 'Reviewed pending editorial decision';

## v1.0.44.2 - 2024-06-24

### Changed

- [512](https://github.com/CCSDForge/episciences/issues/512): is now possible to introduce a revision deadline at a later date if the publisher has not indicated a revision deadline (optional) when sending the request for revision.

### Fixed

- [#511](https://github.com/CCSDForge/episciences/issues/511) Do not filter out comments with no message when getting the suggestions form the comment manager.
- Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#507](https://github.com/CCSDForge/episciences/issues/507): Can't change URLs / HTML in email templates
- [#511](https://github.com/CCSDForge/episciences/issues/511):Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#508](https://github.com/CCSDForge/episciences/issues/508): Submission notification parameter: Only the selected roles will receive the notification, If no option is checked, the notification is sent to everyone.
- [#509](https://github.com/CCSDForge/episciences/issues/509): Pixelated photos (Editorial Staff members page)
- Fixed missing spaces with volumes and section on references for arXiv

## v1.0.44.1 - 2024-05-21

### Fixed "Uncaught TypeError: in_array(): Argument #2 ($haystack) must be of type array, null given."

## v1.0.44 - 2024-05-21

### Fixed

- Fixed a bug related to temporary versions with Zenodo:
    - Unable to enter the identifier when submitting a new version
    - submitting a new version results in an error message "the version of the article to be updated must be greater than the previous version".
- [#472](https://github.com/CCSDForge/episciences/issues/472)[Bug Report] English translation missing: Proposer dans le volume #472
- Duplicated submission when automatically transferring a new version from HAL.

### Added

- Add the latest publications to any page by inserting '<div id="widget-browse-latest"></div>' to the HTML source code of a page (same content as /browse/latest page)

## v1.0.43.1 - 2024-05-02

### Fixed

- Reallocation of a previously allocated doi.
- Resetting the position of a paper in a volume will not work if the paper is removed from the volume.
- [#469](https://github.com/CCSDForge/episciences/issues/469) [Bug Report] Add ORCID for an author with apostrophe in the name is broken into two

### Changed

- Allow the assignment of a DOI to a temporarily accepted article
- Automatic numbering of bibliographical references, increased padding between references

## v1.0.43 - 2024-04-17

### Changed

- Updated application's requirements to PHP 8.1.\* . All application dependencies were updated accordingly
- Section's translations are now stored in database instead of server files
- DOI Content Negotiation support for linked data automatically obtained when submitting or updating metadata + code refactoring
- Crossref Export: the unstructured reference is added with the DOI. Previously it was either DOI or the unstructured

### Fixed

- Scholexplorer API usage: The automated script to discover related datasets to published articles now only handle Datasets

### Added

- [#431](https://github.com/CCSDForge/episciences/issues/431): A link from the peer review result page to the article's administration page.
- Using Citation Style Language and APIs we now retrieve and display the text of the references added for datasets, software, HAL Ids, SWHID, arXiv Ids)
- two new statistical indicators ( Submission-acceptance time, Submission-publication time) to the "At a glance" section.
- A new button as a shortcut access the Episciences bibliographic extraction application to import bibtex
- If the metadata of an Episciences publication hosted on HAL is updated, we now send a COAR notification to HAL to trigger an update of the metadata on HAL.
- ZBJATS export: bibliographical references from bibtex import are now also supported and exported

## v1.0.42.5 - 2024-04-17

### Fixed

- [#457](https://github.com/CCSDForge/episciences/issues/457): DOAJ fullText record gives an url that can't be accessed.
- SignPosting headers: fixed missing doi.org domain prefix for DOIs

### Changed

- Taking into account the new value (3) after changing the authorised values in the user CAS table.

## v1.0.42.4 - 2024-03-27

### Fixed

- [#453](https://github.com/CCSDForge/episciences/issues/453): Show a more prominent error when there is a CSRF token error after the editor's comment has been sent.
- When a new version is transferred form HAL, the DOI assigned to the previous version is not restored in the new document.
- [#449](https://github.com/CCSDForge/episciences/issues/449): related to [#169](https://github.com/CCSDForge/episciences/issues/169): the author does not see the review (visible to the administrator but not to the author) on a non-editable version.
- [#458](https://github.com/CCSDForge/episciences/issues/458) [Bug Report] Bibtex export volume name is malformed

## v1.0.42.3 - 2024-03-20

### Fixed

- [#446](https://github.com/CCSDForge/episciences/issues/446) and [#448](https://github.com/CCSDForge/episciences/issues/448): Paper list drops some spaces in abstracts.
- [#450](https://github.com/CCSDForge/episciences/issues/450): Fixed a server configuration issue for Theoretics

### Changed

- [#450](https://github.com/CCSDForge/episciences/issues/450): Open the target of the journal's logo in the same page/tab

## v1.0.42.2 - 2024-03-19

### Fixed

- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers:
  Make sure the invitation is sent x days [delay] later.
- Wrong display of titles and abstracts on the latest articles page: these will now be displayed in the main language [if available].

## v1.0.42.1 - 2024-03-12

### Changed

- delete a volume: add assigned papers error's message.
- Sorted paper by volume: include assigned papers to this one as a secondary volume
- [#445](https://github.com/CCSDForge/episciences/issues/445): do not limit the number of papers per page to 10: the default limit (100) is used + acceptance date descending order.

### Fixed

- Exporting volumes in DOAJ format does not work for Secondary Volumes
- [#423](https://github.com/CCSDForge/episciences/issues/423): fix typo Technical support Email.
- [#443](https://github.com/CCSDForge/episciences/issues/443): In the templates (New version - author and co-author copy, New temporary version author and co-author copy) the tag %%PERMANENT_ARTICLE_ID%% is used, but it did not appear in the subject of the message.
- [#443](https://github.com/CCSDForge/episciences/issues/443): no content for tags %%TAG_AUTHORS_NAMES%% and %%TAG_CONTRIBUTOR_FULL_NAME%%.
- The guest editor could not see the cover letter for version 2 and the reviewers' names (Revues/Reviews section) on the first version.
- Links to documentation : the "About version numbers" link url has changed and has not been updated.
- Browse by year: documents have been indexed by year of submission instead of year of publication

## v1.0.42 - 2024-02-15

### Added

- Added an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings. The feature is also handled by the Episciences API

### Fixed

- Submission form validation fails when replacing existing version.

## v1.0.41.7 - 2024-02-12

### Fixed

- [Bug Report] Browse by authors or date: wrong links on the titles #435
- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers.
- Fixed ORCID max length of user profiles

### Changed

- Invite a new user form: make it possible to choose between two languages, even for uni-lingual sites & label renamed to 'Default Language'.

## v1.0.41.7 - 2024-01-23

### Changed

- [COAR Notify] processing of new versions + enhancements
- Improvements on the indexing process with Apache Solr

### Fixed

- Script ZBJATS : Skip documents which are not articles with a PDF, such as datasets
- Application error: Argument 1 passed to Episciences_Volume::setTitles() must be of the type array or null, string give.
- "false positive" for missing translations in logs.
- [COAR Notify] when processing several submission at the same time, notification of submissions were sent to wrong recipients
- [Acceptance date] During the publication process, if the article is accepted with a temporary version, then its acceptance date is that of the 1st temporary version. The problem did not exist on published articles.
- Citations of published papers: Improvement in obtaining the source journal from openAlex's response

## v1.0.41.6 - 2024-01-15

### Fixed

- PHP Notice: FAILED_TO_COPY_FILE_ERROR

## v1.0.41.5 - 2024-01-11

### Fixed

- Typo [423](https://github.com/CCSDForge/episciences/issues/423)
- Crossref export of bibliographical references: fixed unescaped HTML, added a log for XML errors
- Fix #417 Identifiers other than SWHID for Software solutions: remove domain prefixing SWH for all software, add the right domain for software (other than SWH), add correct domain for software in TEI

## v1.0.46.1 - 2024-11-27

### Fixed

- In some cases, the translated 'name' and translated 'subject' of the email template may be lost when they are modified

## v1.0.46 - 2024-11-25

### Removed

- [#625](https://github.com/CCSDForge/episciences/issues/625): 'contact tech support' field has been removed (not used).

### Fixed

- Citations are sometimes not retrieved
- Linked Datasets
    - fixed display of swhids for snapshots + revisions
    - fixed delete button not working with multiple links in same category
    - fixed no confirmation message in confirmation modal
    - fixed duplicate HTML id elements
    - fixed style of forms, use bootstrap
    - used vanilla JS and fetch API instead of Jquery and xmlhttprequest to delete links
    - added missing translations
- Authors affiliation management: use bootstrap style for select
- Update dependency geoip2/geoip2 to v3: todo: downloading databases: https://github.com/P3TERX/GeoLite.mmdb/releases/tag/2024.07.13
- [#506](https://github.com/CCSDForge/episciences/issues/506): Fixed wrong photo displayed when switching profiles.
- Fixed missing volume title when updating metadata from an Episciences journal to HAL.
- [#555](https://github.com/CCSDForge/episciences/issues/555): improved conflict of interest explanations and translations
- Users who have already validated their profile on one of the journals can connect to another without having to validate their profile on that journal (even if they don't have one)
  [#567](https://github.com/CCSDForge/episciences/issues/567): random message recipients.

### Added

- [#513](https://github.com/CCSDForge/episciences/issues/513): Periodic Reminder E-Mails to Editors | editors-in-chief for 'Reviewed' and 'Submitted' articles
- Metadata Export to JSON: include article's citations.
- Created an Opaque UID for users using UUID
    - Profile photo is now stored according to the user's UUID.
    - Script to generate user's UUID.
- New settings for the journals:
    - 'Publisher'
    - 'Publisher location'
    - The values are exported to OpenAIRE and the DOAJ (not supported by Crossref)
- Button to be redirected to the Episciences bibliographic extraction application to import bibtex
- Using COAR Notify with HAL and Episciences to notify HAL of metadata updates for published articles.
- Added in ZBJATS: export bibliographical references from bibtex import
- Added button to redirect to Episciences citation management software without extracting form PDF
- [#418](https://github.com/CCSDForge/episciences/issues/418) Enable edition of relationship and type for the linked data
- Public representation of a document in JSON format (@see /json?v=2)
- [#475](https://github.com/CCSDForge/episciences/issues/475) feature to add biography to profile
- [#476](https://github.com/CCSDForge/episciences/issues/476) feature to add years in volumes forms
- Functionality for migrating news to the new sql news table
- Tinymce toolbar: make headings available
- New field in volume form (volume number)
- [#534](https://github.com/CCSDForge/episciences/issues/534) feature graphical abstract
- Automatic discovery of JEL Classification for published articles, with OpenAIREAPI service
- Automatic discovery of MSC2020 Classification for published articles, with zbMATH Open API service
- Automatic discovery of Reviews on published articles, with zbMATH Open API service

### Changed

- UI/UX: improvements to the editing form and the creation of reminders
- Bump tinymce to latest version (7.2.0)
- Crossref export:
    - For conference proceedings the title and original_language_title elements have been removed from proceedings_metadata: they are not authorised here
    - ROR added to the schema (did not work on the previous version)
- classification of keywords by language
- [#241](https://github.com/CCSDForge/episciences/issues/241) show users roles in mailing popup
- [#531](https://github.com/CCSDForge/episciences/issues/531) enhances volumes form translations

## v1.0.45.2 - 2024-10-22

## Fixed

- [#607](https://github.com/CCSDForge/episciences/issues/607):
    - fixed: the two tags '%COMMENT%%' and '%%ANSWER%%' have identical content.
    - improvement: the attachment with the original comment is now attached to the reply.

### Changed

- [#564](https://github.com/CCSDForge/episciences/issues/564): Fixed and changed French label in journal settings
- [#547](https://github.com/CCSDForge/episciences/issues/547): Ask for other editors opinion: select | deselect all option.
- COAR notifications from HAL: resubmit the same version as a new version.

## v1.0.45.1 - 2024-09-18

- [#585](https://github.com/CCSDForge/episciences/issues/585): The history of manually sent emails is not visible when the "Conflict of Interest" option is enabled.

## v1.0.45 - 2024-09-16

### Added

- [#580](https://github.com/CCSDForge/episciences/issues/580) zbMATH Open export: Authors: add affiliations and ORCIDs

### Fixed

- Volume names are not translated in the volume drop-down list on the reviewing grids list page.
- [#543](https://github.com/CCSDForge/episciences/issues/543): Not translate select options
- [#546](https://github.com/CCSDForge/episciences/issues/546): Editors with different local default date formats cannot update the revision deadline.
- [#571](https://github.com/CCSDForge/episciences/issues/571): The number of days before the review deadline was calculated incorrectly (one day difference).

### Changed

- Ability to block the automatic transfer of a submission from HAL

## v1.0.44.3 - 2024-07-23

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): Completing label updates, fixed label starting with a character outside the [a-z] range;

## v1.0.44.2 - 2024-06-24

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): update the label 'Reviewed' to 'Reviewed pending editorial decision';

## v1.0.44.2 - 2024-06-24

### Changed

- [512](https://github.com/CCSDForge/episciences/issues/512): is now possible to introduce a revision deadline at a later date if the publisher has not indicated a revision deadline (optional) when sending the request for revision.

### Fixed

- [#511](https://github.com/CCSDForge/episciences/issues/511) Do not filter out comments with no message when getting the suggestions form the comment manager.
- Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#507](https://github.com/CCSDForge/episciences/issues/507): Can't change URLs / HTML in email templates
- [#511](https://github.com/CCSDForge/episciences/issues/511):Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#508](https://github.com/CCSDForge/episciences/issues/508): Submission notification parameter: Only the selected roles will receive the notification, If no option is checked, the notification is sent to everyone.
- [#509](https://github.com/CCSDForge/episciences/issues/509): Pixelated photos (Editorial Staff members page)
- Fixed missing spaces with volumes and section on references for arXiv

## v1.0.44.1 - 2024-05-21

### Fixed "Uncaught TypeError: in_array(): Argument #2 ($haystack) must be of type array, null given."

## v1.0.44 - 2024-05-21

### Fixed

- Fixed a bug related to temporary versions with Zenodo:
    - Unable to enter the identifier when submitting a new version
    - submitting a new version results in an error message "the version of the article to be updated must be greater than the previous version".
- [#472](https://github.com/CCSDForge/episciences/issues/472)[Bug Report] English translation missing: Proposer dans le volume #472
- Duplicated submission when automatically transferring a new version from HAL.

### Added

- Add the latest publications to any page by inserting '<div id="widget-browse-latest"></div>' to the HTML source code of a page (same content as /browse/latest page)

## v1.0.43.1 - 2024-05-02

### Fixed

- Reallocation of a previously allocated doi.
- Resetting the position of a paper in a volume will not work if the paper is removed from the volume.
- [#469](https://github.com/CCSDForge/episciences/issues/469) [Bug Report] Add ORCID for an author with apostrophe in the name is broken into two

### Changed

- Allow the assignment of a DOI to a temporarily accepted article
- Automatic numbering of bibliographical references, increased padding between references

## v1.0.43 - 2024-04-17

### Changed

- Updated application's requirements to PHP 8.1.\* . All application dependencies were updated accordingly
- Section's translations are now stored in database instead of server files
- DOI Content Negotiation support for linked data automatically obtained when submitting or updating metadata + code refactoring
- Crossref Export: the unstructured reference is added with the DOI. Previously it was either DOI or the unstructured

### Fixed

- Scholexplorer API usage: The automated script to discover related datasets to published articles now only handle Datasets

### Added

- [#431](https://github.com/CCSDForge/episciences/issues/431): A link from the peer review result page to the article's administration page.
- Using Citation Style Language and APIs we now retrieve and display the text of the references added for datasets, software, HAL Ids, SWHID, arXiv Ids)
- two new statistical indicators ( Submission-acceptance time, Submission-publication time) to the "At a glance" section.
- A new button as a shortcut access the Episciences bibliographic extraction application to import bibtex
- If the metadata of an Episciences publication hosted on HAL is updated, we now send a COAR notification to HAL to trigger an update of the metadata on HAL.
- ZBJATS export: bibliographical references from bibtex import are now also supported and exported

## v1.0.42.5 - 2024-04-17

### Fixed

- [#457](https://github.com/CCSDForge/episciences/issues/457): DOAJ fullText record gives an url that can't be accessed.
- SignPosting headers: fixed missing doi.org domain prefix for DOIs

### Changed

- Taking into account the new value (3) after changing the authorised values in the user CAS table.

## v1.0.42.4 - 2024-03-27

### Fixed

- [#453](https://github.com/CCSDForge/episciences/issues/453): Show a more prominent error when there is a CSRF token error after the editor's comment has been sent.
- When a new version is transferred form HAL, the DOI assigned to the previous version is not restored in the new document.
- [#449](https://github.com/CCSDForge/episciences/issues/449): related to [#169](https://github.com/CCSDForge/episciences/issues/169): the author does not see the review (visible to the administrator but not to the author) on a non-editable version.
- [#458](https://github.com/CCSDForge/episciences/issues/458) [Bug Report] Bibtex export volume name is malformed

## v1.0.42.3 - 2024-03-20

### Fixed

- [#446](https://github.com/CCSDForge/episciences/issues/446) and [#448](https://github.com/CCSDForge/episciences/issues/448): Paper list drops some spaces in abstracts.
- [#450](https://github.com/CCSDForge/episciences/issues/450): Fixed a server configuration issue for Theoretics

### Changed

- [#450](https://github.com/CCSDForge/episciences/issues/450): Open the target of the journal's logo in the same page/tab

## v1.0.42.2 - 2024-03-19

### Fixed

- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers:
  Make sure the invitation is sent x days [delay] later.
- Wrong display of titles and abstracts on the latest articles page: these will now be displayed in the main language [if available].

## v1.0.42.1 - 2024-03-12

### Changed

- delete a volume: add assigned papers error's message.
- Sorted paper by volume: include assigned papers to this one as a secondary volume
- [#445](https://github.com/CCSDForge/episciences/issues/445): do not limit the number of papers per page to 10: the default limit (100) is used + acceptance date descending order.

### Fixed

- Exporting volumes in DOAJ format does not work for Secondary Volumes
- [#423](https://github.com/CCSDForge/episciences/issues/423): fix typo Technical support Email.
- [#443](https://github.com/CCSDForge/episciences/issues/443): In the templates (New version - author and co-author copy, New temporary version author and co-author copy) the tag %%PERMANENT_ARTICLE_ID%% is used, but it did not appear in the subject of the message.
- [#443](https://github.com/CCSDForge/episciences/issues/443): no content for tags %%TAG_AUTHORS_NAMES%% and %%TAG_CONTRIBUTOR_FULL_NAME%%.
- The guest editor could not see the cover letter for version 2 and the reviewers' names (Revues/Reviews section) on the first version.
- Links to documentation : the "About version numbers" link url has changed and has not been updated.
- Browse by year: documents have been indexed by year of submission instead of year of publication

## v1.0.42 - 2024-02-15

### Added

- Added an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings. The feature is also handled by the Episciences API

### Fixed

- Submission form validation fails when replacing existing version.

## v1.0.41.7 - 2024-02-12

### Fixed

- [Bug Report] Browse by authors or date: wrong links on the titles #435
- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers.
- Fixed ORCID max length of user profiles

### Changed

- Invite a new user form: make it possible to choose between two languages, even for uni-lingual sites & label renamed to 'Default Language'.

## v1.0.41.7 - 2024-01-23

### Changed

- [COAR Notify] processing of new versions + enhancements
- Improvements on the indexing process with Apache Solr

### Fixed

- Script ZBJATS : Skip documents which are not articles with a PDF, such as datasets
- Application error: Argument 1 passed to Episciences_Volume::setTitles() must be of the type array or null, string give.
- "false positive" for missing translations in logs.
- [COAR Notify] when processing several submission at the same time, notification of submissions were sent to wrong recipients
- [Acceptance date] During the publication process, if the article is accepted with a temporary version, then its acceptance date is that of the 1st temporary version. The problem did not exist on published articles.
- Citations of published papers: Improvement in obtaining the source journal from openAlex's response

## v1.0.41.6 - 2024-01-15

### Fixed

- PHP Notice: FAILED_TO_COPY_FILE_ERROR

## v1.0.41.5 - 2024-01-11

### Fixed

- Typo [423](https://github.com/CCSDForge/episciences/issues/423)
- Crossref export of bibliographical references: fixed unescaped HTML, added a log for XML errors
- Fix #417 Identifiers other than SWHID for Software solutions: remove domain prefixing SWH for all software, add the right domain for software (other than SWH), add correct domain for software in TEI

## v1.0.46.1 - 2024-11-27

### Fixed

- In some cases, the translated 'name' and translated 'subject' of the email template may be lost when they are modified

## v1.0.46 - 2024-11-25

### Removed

- [#625](https://github.com/CCSDForge/episciences/issues/625): 'contact tech support' field has been removed (not used).

### Fixed

- Citations are sometimes not retrieved
- Linked Datasets
    - fixed display of swhids for snapshots + revisions
    - fixed delete button not working with multiple links in same category
    - fixed no confirmation message in confirmation modal
    - fixed duplicate HTML id elements
    - fixed style of forms, use bootstrap
    - used vanilla JS and fetch API instead of Jquery and xmlhttprequest to delete links
    - added missing translations
- Authors affiliation management: use bootstrap style for select
- Update dependency geoip2/geoip2 to v3: todo: downloading databases: https://github.com/P3TERX/GeoLite.mmdb/releases/tag/2024.07.13
- [#506](https://github.com/CCSDForge/episciences/issues/506): Fixed wrong photo displayed when switching profiles.
- Fixed missing volume title when updating metadata from an Episciences journal to HAL.
- [#555](https://github.com/CCSDForge/episciences/issues/555): improved conflict of interest explanations and translations
- Users who have already validated their profile on one of the journals can connect to another without having to validate their profile on that journal (even if they don't have one)
  [#567](https://github.com/CCSDForge/episciences/issues/567): random message recipients.

### Added

- [#513](https://github.com/CCSDForge/episciences/issues/513): Periodic Reminder E-Mails to Editors | editors-in-chief for 'Reviewed' and 'Submitted' articles
- Metadata Export to JSON: include article's citations.
- Created an Opaque UID for users using UUID
    - Profile photo is now stored according to the user's UUID.
    - Script to generate user's UUID.
- New settings for the journals:
    - 'Publisher'
    - 'Publisher location'
    - The values are exported to OpenAIRE and the DOAJ (not supported by Crossref)
- Button to be redirected to the Episciences bibliographic extraction application to import bibtex
- Using COAR Notify with HAL and Episciences to notify HAL of metadata updates for published articles.
- Added in ZBJATS: export bibliographical references from bibtex import
- Added button to redirect to Episciences citation management software without extracting form PDF
- [#418](https://github.com/CCSDForge/episciences/issues/418) Enable edition of relationship and type for the linked data
- Public representation of a document in JSON format (@see /json?v=2)
- [#475](https://github.com/CCSDForge/episciences/issues/475) feature to add biography to profile
- [#476](https://github.com/CCSDForge/episciences/issues/476) feature to add years in volumes forms
- Functionality for migrating news to the new sql news table
- Tinymce toolbar: make headings available
- New field in volume form (volume number)
- [#534](https://github.com/CCSDForge/episciences/issues/534) feature graphical abstract
- Automatic discovery of JEL Classification for published articles, with OpenAIREAPI service
- Automatic discovery of MSC2020 Classification for published articles, with zbMATH Open API service
- Automatic discovery of Reviews on published articles, with zbMATH Open API service

### Changed

- UI/UX: improvements to the editing form and the creation of reminders
- Bump tinymce to latest version (7.2.0)
- Crossref export:
    - For conference proceedings the title and original_language_title elements have been removed from proceedings_metadata: they are not authorised here
    - ROR added to the schema (did not work on the previous version)
- classification of keywords by language
- [#241](https://github.com/CCSDForge/episciences/issues/241) show users roles in mailing popup
- [#531](https://github.com/CCSDForge/episciences/issues/531) enhances volumes form translations

## v1.0.45.2 - 2024-10-22

## Fixed

- [#607](https://github.com/CCSDForge/episciences/issues/607):
    - fixed: the two tags '%COMMENT%%' and '%%ANSWER%%' have identical content.
    - improvement: the attachment with the original comment is now attached to the reply.

### Changed

- [#564](https://github.com/CCSDForge/episciences/issues/564): Fixed and changed French label in journal settings
- [#547](https://github.com/CCSDForge/episciences/issues/547): Ask for other editors opinion: select | deselect all option.
- COAR notifications from HAL: resubmit the same version as a new version.

## v1.0.45.1 - 2024-09-18

- [#585](https://github.com/CCSDForge/episciences/issues/585): The history of manually sent emails is not visible when the "Conflict of Interest" option is enabled.

## v1.0.45 - 2024-09-16

### Added

- [#580](https://github.com/CCSDForge/episciences/issues/580) zbMATH Open export: Authors: add affiliations and ORCIDs

### Fixed

- Volume names are not translated in the volume drop-down list on the reviewing grids list page.
- [#543](https://github.com/CCSDForge/episciences/issues/543): Not translate select options
- [#546](https://github.com/CCSDForge/episciences/issues/546): Editors with different local default date formats cannot update the revision deadline.
- [#571](https://github.com/CCSDForge/episciences/issues/571): The number of days before the review deadline was calculated incorrectly (one day difference).

### Changed

- Ability to block the automatic transfer of a submission from HAL

## v1.0.44.3 - 2024-07-23

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): Completing label updates, fixed label starting with a character outside the [a-z] range;

## v1.0.44.2 - 2024-06-24

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): update the label 'Reviewed' to 'Reviewed pending editorial decision';

## v1.0.44.2 - 2024-06-24

### Changed

- [512](https://github.com/CCSDForge/episciences/issues/512): is now possible to introduce a revision deadline at a later date if the publisher has not indicated a revision deadline (optional) when sending the request for revision.

### Fixed

- [#511](https://github.com/CCSDForge/episciences/issues/511) Do not filter out comments with no message when getting the suggestions form the comment manager.
- Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#507](https://github.com/CCSDForge/episciences/issues/507): Can't change URLs / HTML in email templates
- [#511](https://github.com/CCSDForge/episciences/issues/511):Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#508](https://github.com/CCSDForge/episciences/issues/508): Submission notification parameter: Only the selected roles will receive the notification, If no option is checked, the notification is sent to everyone.
- [#509](https://github.com/CCSDForge/episciences/issues/509): Pixelated photos (Editorial Staff members page)
- Fixed missing spaces with volumes and section on references for arXiv

## v1.0.44.1 - 2024-05-21

### Fixed "Uncaught TypeError: in_array(): Argument #2 ($haystack) must be of type array, null given."

## v1.0.44 - 2024-05-21

### Fixed

- Fixed a bug related to temporary versions with Zenodo:
    - Unable to enter the identifier when submitting a new version
    - submitting a new version results in an error message "the version of the article to be updated must be greater than the previous version".
- [#472](https://github.com/CCSDForge/episciences/issues/472)[Bug Report] English translation missing: Proposer dans le volume #472
- Duplicated submission when automatically transferring a new version from HAL.

### Added

- Add the latest publications to any page by inserting '<div id="widget-browse-latest"></div>' to the HTML source code of a page (same content as /browse/latest page)

## v1.0.43.1 - 2024-05-02

### Fixed

- Reallocation of a previously allocated doi.
- Resetting the position of a paper in a volume will not work if the paper is removed from the volume.
- [#469](https://github.com/CCSDForge/episciences/issues/469) [Bug Report] Add ORCID for an author with apostrophe in the name is broken into two

### Changed

- Allow the assignment of a DOI to a temporarily accepted article
- Automatic numbering of bibliographical references, increased padding between references

## v1.0.43 - 2024-04-17

### Changed

- Updated application's requirements to PHP 8.1.\* . All application dependencies were updated accordingly
- Section's translations are now stored in database instead of server files
- DOI Content Negotiation support for linked data automatically obtained when submitting or updating metadata + code refactoring
- Crossref Export: the unstructured reference is added with the DOI. Previously it was either DOI or the unstructured

### Fixed

- Scholexplorer API usage: The automated script to discover related datasets to published articles now only handle Datasets

### Added

- [#431](https://github.com/CCSDForge/episciences/issues/431): A link from the peer review result page to the article's administration page.
- Using Citation Style Language and APIs we now retrieve and display the text of the references added for datasets, software, HAL Ids, SWHID, arXiv Ids)
- two new statistical indicators ( Submission-acceptance time, Submission-publication time) to the "At a glance" section.
- A new button as a shortcut access the Episciences bibliographic extraction application to import bibtex
- If the metadata of an Episciences publication hosted on HAL is updated, we now send a COAR notification to HAL to trigger an update of the metadata on HAL.
- ZBJATS export: bibliographical references from bibtex import are now also supported and exported

## v1.0.42.5 - 2024-04-17

### Fixed

- [#457](https://github.com/CCSDForge/episciences/issues/457): DOAJ fullText record gives an url that can't be accessed.
- SignPosting headers: fixed missing doi.org domain prefix for DOIs

### Changed

- Taking into account the new value (3) after changing the authorised values in the user CAS table.

## v1.0.42.4 - 2024-03-27

### Fixed

- [#453](https://github.com/CCSDForge/episciences/issues/453): Show a more prominent error when there is a CSRF token error after the editor's comment has been sent.
- When a new version is transferred form HAL, the DOI assigned to the previous version is not restored in the new document.
- [#449](https://github.com/CCSDForge/episciences/issues/449): related to [#169](https://github.com/CCSDForge/episciences/issues/169): the author does not see the review (visible to the administrator but not to the author) on a non-editable version.
- [#458](https://github.com/CCSDForge/episciences/issues/458) [Bug Report] Bibtex export volume name is malformed

## v1.0.42.3 - 2024-03-20

### Fixed

- [#446](https://github.com/CCSDForge/episciences/issues/446) and [#448](https://github.com/CCSDForge/episciences/issues/448): Paper list drops some spaces in abstracts.
- [#450](https://github.com/CCSDForge/episciences/issues/450): Fixed a server configuration issue for Theoretics

### Changed

- [#450](https://github.com/CCSDForge/episciences/issues/450): Open the target of the journal's logo in the same page/tab

## v1.0.42.2 - 2024-03-19

### Fixed

- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers:
  Make sure the invitation is sent x days [delay] later.
- Wrong display of titles and abstracts on the latest articles page: these will now be displayed in the main language [if available].

## v1.0.42.1 - 2024-03-12

### Changed

- delete a volume: add assigned papers error's message.
- Sorted paper by volume: include assigned papers to this one as a secondary volume
- [#445](https://github.com/CCSDForge/episciences/issues/445): do not limit the number of papers per page to 10: the default limit (100) is used + acceptance date descending order.

### Fixed

- Exporting volumes in DOAJ format does not work for Secondary Volumes
- [#423](https://github.com/CCSDForge/episciences/issues/423): fix typo Technical support Email.
- [#443](https://github.com/CCSDForge/episciences/issues/443): In the templates (New version - author and co-author copy, New temporary version author and co-author copy) the tag %%PERMANENT_ARTICLE_ID%% is used, but it did not appear in the subject of the message.
- [#443](https://github.com/CCSDForge/episciences/issues/443): no content for tags %%TAG_AUTHORS_NAMES%% and %%TAG_CONTRIBUTOR_FULL_NAME%%.
- The guest editor could not see the cover letter for version 2 and the reviewers' names (Revues/Reviews section) on the first version.
- Links to documentation : the "About version numbers" link url has changed and has not been updated.
- Browse by year: documents have been indexed by year of submission instead of year of publication

## v1.0.42 - 2024-02-15

### Added

- Added an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings. The feature is also handled by the Episciences API

### Fixed

- Submission form validation fails when replacing existing version.

## v1.0.41.7 - 2024-02-12

### Fixed

- [Bug Report] Browse by authors or date: wrong links on the titles #435
- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers.
- Fixed ORCID max length of user profiles

### Changed

- Invite a new user form: make it possible to choose between two languages, even for uni-lingual sites & label renamed to 'Default Language'.

## v1.0.41.7 - 2024-01-23

### Changed

- [COAR Notify] processing of new versions + enhancements
- Improvements on the indexing process with Apache Solr

### Fixed

- Script ZBJATS : Skip documents which are not articles with a PDF, such as datasets
- Application error: Argument 1 passed to Episciences_Volume::setTitles() must be of the type array or null, string give.
- "false positive" for missing translations in logs.
- [COAR Notify] when processing several submission at the same time, notification of submissions were sent to wrong recipients
- [Acceptance date] During the publication process, if the article is accepted with a temporary version, then its acceptance date is that of the 1st temporary version. The problem did not exist on published articles.
- Citations of published papers: Improvement in obtaining the source journal from openAlex's response

## v1.0.41.6 - 2024-01-15

### Fixed

- PHP Notice: FAILED_TO_COPY_FILE_ERROR

## v1.0.41.5 - 2024-01-11

### Fixed

- Typo [423](https://github.com/CCSDForge/episciences/issues/423)
- Crossref export of bibliographical references: fixed unescaped HTML, added a log for XML errors
- Fix #417 Identifiers other than SWHID for Software solutions: remove domain prefixing SWH for all software, add the right domain for software (other than SWH), add correct domain for software in TEI

## v1.0.46.1 - 2024-11-27

### Fixed

- In some cases, the translated 'name' and translated 'subject' of the email template may be lost when they are modified

## v1.0.46 - 2024-11-25

### Removed

- [#625](https://github.com/CCSDForge/episciences/issues/625): 'contact tech support' field has been removed (not used).

### Fixed

- Citations are sometimes not retrieved
- Linked Datasets
    - fixed display of swhids for snapshots + revisions
    - fixed delete button not working with multiple links in same category
    - fixed no confirmation message in confirmation modal
    - fixed duplicate HTML id elements
    - fixed style of forms, use bootstrap
    - used vanilla JS and fetch API instead of Jquery and xmlhttprequest to delete links
    - added missing translations
- Authors affiliation management: use bootstrap style for select
- Update dependency geoip2/geoip2 to v3: todo: downloading databases: https://github.com/P3TERX/GeoLite.mmdb/releases/tag/2024.07.13
- [#506](https://github.com/CCSDForge/episciences/issues/506): Fixed wrong photo displayed when switching profiles.
- Fixed missing volume title when updating metadata from an Episciences journal to HAL.
- [#555](https://github.com/CCSDForge/episciences/issues/555): improved conflict of interest explanations and translations
- Users who have already validated their profile on one of the journals can connect to another without having to validate their profile on that journal (even if they don't have one)
  [#567](https://github.com/CCSDForge/episciences/issues/567): random message recipients.

### Added

- [#513](https://github.com/CCSDForge/episciences/issues/513): Periodic Reminder E-Mails to Editors | editors-in-chief for 'Reviewed' and 'Submitted' articles
- Metadata Export to JSON: include article's citations.
- Created an Opaque UID for users using UUID
    - Profile photo is now stored according to the user's UUID.
    - Script to generate user's UUID.
- New settings for the journals:
    - 'Publisher'
    - 'Publisher location'
    - The values are exported to OpenAIRE and the DOAJ (not supported by Crossref)
- Button to be redirected to the Episciences bibliographic extraction application to import bibtex
- Using COAR Notify with HAL and Episciences to notify HAL of metadata updates for published articles.
- Added in ZBJATS: export bibliographical references from bibtex import
- Added button to redirect to Episciences citation management software without extracting form PDF
- [#418](https://github.com/CCSDForge/episciences/issues/418) Enable edition of relationship and type for the linked data
- Public representation of a document in JSON format (@see /json?v=2)
- [#475](https://github.com/CCSDForge/episciences/issues/475) feature to add biography to profile
- [#476](https://github.com/CCSDForge/episciences/issues/476) feature to add years in volumes forms
- Functionality for migrating news to the new sql news table
- Tinymce toolbar: make headings available
- New field in volume form (volume number)
- [#534](https://github.com/CCSDForge/episciences/issues/534) feature graphical abstract
- Automatic discovery of JEL Classification for published articles, with OpenAIREAPI service
- Automatic discovery of MSC2020 Classification for published articles, with zbMATH Open API service
- Automatic discovery of Reviews on published articles, with zbMATH Open API service

### Changed

- UI/UX: improvements to the editing form and the creation of reminders
- Bump tinymce to latest version (7.2.0)
- Crossref export:
    - For conference proceedings the title and original_language_title elements have been removed from proceedings_metadata: they are not authorised here
    - ROR added to the schema (did not work on the previous version)
- classification of keywords by language
- [#241](https://github.com/CCSDForge/episciences/issues/241) show users roles in mailing popup
- [#531](https://github.com/CCSDForge/episciences/issues/531) enhances volumes form translations

## v1.0.45.2 - 2024-10-22

## Fixed

- [#607](https://github.com/CCSDForge/episciences/issues/607):
    - fixed: the two tags '%COMMENT%%' and '%%ANSWER%%' have identical content.
    - improvement: the attachment with the original comment is now attached to the reply.

### Changed

- [#564](https://github.com/CCSDForge/episciences/issues/564): Fixed and changed French label in journal settings
- [#547](https://github.com/CCSDForge/episciences/issues/547): Ask for other editors opinion: select | deselect all option.
- COAR notifications from HAL: resubmit the same version as a new version.

## v1.0.45.1 - 2024-09-18

- [#585](https://github.com/CCSDForge/episciences/issues/585): The history of manually sent emails is not visible when the "Conflict of Interest" option is enabled.

## v1.0.45 - 2024-09-16

### Added

- [#580](https://github.com/CCSDForge/episciences/issues/580) zbMATH Open export: Authors: add affiliations and ORCIDs

### Fixed

- Volume names are not translated in the volume drop-down list on the reviewing grids list page.
- [#543](https://github.com/CCSDForge/episciences/issues/543): Not translate select options
- [#546](https://github.com/CCSDForge/episciences/issues/546): Editors with different local default date formats cannot update the revision deadline.
- [#571](https://github.com/CCSDForge/episciences/issues/571): The number of days before the review deadline was calculated incorrectly (one day difference).

### Changed

- Ability to block the automatic transfer of a submission from HAL

## v1.0.44.3 - 2024-07-23

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): Completing label updates, fixed label starting with a character outside the [a-z] range;

## v1.0.44.2 - 2024-06-24

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): update the label 'Reviewed' to 'Reviewed pending editorial decision';

## v1.0.44.2 - 2024-06-24

### Changed

- [512](https://github.com/CCSDForge/episciences/issues/512): is now possible to introduce a revision deadline at a later date if the publisher has not indicated a revision deadline (optional) when sending the request for revision.

### Fixed

- [#511](https://github.com/CCSDForge/episciences/issues/511) Do not filter out comments with no message when getting the suggestions form the comment manager.
- Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#507](https://github.com/CCSDForge/episciences/issues/507): Can't change URLs / HTML in email templates
- [#511](https://github.com/CCSDForge/episciences/issues/511):Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#508](https://github.com/CCSDForge/episciences/issues/508): Submission notification parameter: Only the selected roles will receive the notification, If no option is checked, the notification is sent to everyone.
- [#509](https://github.com/CCSDForge/episciences/issues/509): Pixelated photos (Editorial Staff members page)
- Fixed missing spaces with volumes and section on references for arXiv

## v1.0.44.1 - 2024-05-21

### Fixed "Uncaught TypeError: in_array(): Argument #2 ($haystack) must be of type array, null given."

## v1.0.44 - 2024-05-21

### Fixed

- Fixed a bug related to temporary versions with Zenodo:
    - Unable to enter the identifier when submitting a new version
    - submitting a new version results in an error message "the version of the article to be updated must be greater than the previous version".
- [#472](https://github.com/CCSDForge/episciences/issues/472)[Bug Report] English translation missing: Proposer dans le volume #472
- Duplicated submission when automatically transferring a new version from HAL.

### Added

- Add the latest publications to any page by inserting '<div id="widget-browse-latest"></div>' to the HTML source code of a page (same content as /browse/latest page)

## v1.0.43.1 - 2024-05-02

### Fixed

- Reallocation of a previously allocated doi.
- Resetting the position of a paper in a volume will not work if the paper is removed from the volume.
- [#469](https://github.com/CCSDForge/episciences/issues/469) [Bug Report] Add ORCID for an author with apostrophe in the name is broken into two

### Changed

- Allow the assignment of a DOI to a temporarily accepted article
- Automatic numbering of bibliographical references, increased padding between references

## v1.0.43 - 2024-04-17

### Changed

- Updated application's requirements to PHP 8.1.\* . All application dependencies were updated accordingly
- Section's translations are now stored in database instead of server files
- DOI Content Negotiation support for linked data automatically obtained when submitting or updating metadata + code refactoring
- Crossref Export: the unstructured reference is added with the DOI. Previously it was either DOI or the unstructured

### Fixed

- Scholexplorer API usage: The automated script to discover related datasets to published articles now only handle Datasets

### Added

- [#431](https://github.com/CCSDForge/episciences/issues/431): A link from the peer review result page to the article's administration page.
- Using Citation Style Language and APIs we now retrieve and display the text of the references added for datasets, software, HAL Ids, SWHID, arXiv Ids)
- two new statistical indicators ( Submission-acceptance time, Submission-publication time) to the "At a glance" section.
- A new button as a shortcut access the Episciences bibliographic extraction application to import bibtex
- If the metadata of an Episciences publication hosted on HAL is updated, we now send a COAR notification to HAL to trigger an update of the metadata on HAL.
- ZBJATS export: bibliographical references from bibtex import are now also supported and exported

## v1.0.42.5 - 2024-04-17

### Fixed

- [#457](https://github.com/CCSDForge/episciences/issues/457): DOAJ fullText record gives an url that can't be accessed.
- SignPosting headers: fixed missing doi.org domain prefix for DOIs

### Changed

- Taking into account the new value (3) after changing the authorised values in the user CAS table.

## v1.0.42.4 - 2024-03-27

### Fixed

- [#453](https://github.com/CCSDForge/episciences/issues/453): Show a more prominent error when there is a CSRF token error after the editor's comment has been sent.
- When a new version is transferred form HAL, the DOI assigned to the previous version is not restored in the new document.
- [#449](https://github.com/CCSDForge/episciences/issues/449): related to [#169](https://github.com/CCSDForge/episciences/issues/169): the author does not see the review (visible to the administrator but not to the author) on a non-editable version.
- [#458](https://github.com/CCSDForge/episciences/issues/458) [Bug Report] Bibtex export volume name is malformed

## v1.0.42.3 - 2024-03-20

### Fixed

- [#446](https://github.com/CCSDForge/episciences/issues/446) and [#448](https://github.com/CCSDForge/episciences/issues/448): Paper list drops some spaces in abstracts.
- [#450](https://github.com/CCSDForge/episciences/issues/450): Fixed a server configuration issue for Theoretics

### Changed

- [#450](https://github.com/CCSDForge/episciences/issues/450): Open the target of the journal's logo in the same page/tab

## v1.0.42.2 - 2024-03-19

### Fixed

- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers:
  Make sure the invitation is sent x days [delay] later.
- Wrong display of titles and abstracts on the latest articles page: these will now be displayed in the main language [if available].

## v1.0.42.1 - 2024-03-12

### Changed

- delete a volume: add assigned papers error's message.
- Sorted paper by volume: include assigned papers to this one as a secondary volume
- [#445](https://github.com/CCSDForge/episciences/issues/445): do not limit the number of papers per page to 10: the default limit (100) is used + acceptance date descending order.

### Fixed

- Exporting volumes in DOAJ format does not work for Secondary Volumes
- [#423](https://github.com/CCSDForge/episciences/issues/423): fix typo Technical support Email.
- [#443](https://github.com/CCSDForge/episciences/issues/443): In the templates (New version - author and co-author copy, New temporary version author and co-author copy) the tag %%PERMANENT_ARTICLE_ID%% is used, but it did not appear in the subject of the message.
- [#443](https://github.com/CCSDForge/episciences/issues/443): no content for tags %%TAG_AUTHORS_NAMES%% and %%TAG_CONTRIBUTOR_FULL_NAME%%.
- The guest editor could not see the cover letter for version 2 and the reviewers' names (Revues/Reviews section) on the first version.
- Links to documentation : the "About version numbers" link url has changed and has not been updated.
- Browse by year: documents have been indexed by year of submission instead of year of publication

## v1.0.42 - 2024-02-15

### Added

- Added an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings. The feature is also handled by the Episciences API

### Fixed

- Submission form validation fails when replacing existing version.

## v1.0.41.7 - 2024-02-12

### Fixed

- [Bug Report] Browse by authors or date: wrong links on the titles #435
- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers.
- Fixed ORCID max length of user profiles

### Changed

- Invite a new user form: make it possible to choose between two languages, even for uni-lingual sites & label renamed to 'Default Language'.

## v1.0.41.7 - 2024-01-23

### Changed

- [COAR Notify] processing of new versions + enhancements
- Improvements on the indexing process with Apache Solr

### Fixed

- Script ZBJATS : Skip documents which are not articles with a PDF, such as datasets
- Application error: Argument 1 passed to Episciences_Volume::setTitles() must be of the type array or null, string give.
- "false positive" for missing translations in logs.
- [COAR Notify] when processing several submission at the same time, notification of submissions were sent to wrong recipients
- [Acceptance date] During the publication process, if the article is accepted with a temporary version, then its acceptance date is that of the 1st temporary version. The problem did not exist on published articles.
- Citations of published papers: Improvement in obtaining the source journal from openAlex's response

## v1.0.41.6 - 2024-01-15

### Fixed

- PHP Notice: FAILED_TO_COPY_FILE_ERROR

## v1.0.41.5 - 2024-01-11

### Fixed

- Typo [423](https://github.com/CCSDForge/episciences/issues/423)
- Crossref export of bibliographical references: fixed unescaped HTML, added a log for XML errors
- Fix #417 Identifiers other than SWHID for Software solutions: remove domain prefixing SWH for all software, add the right domain for software (other than SWH), add correct domain for software in TEI

## v1.0.46.1 - 2024-11-27

### Fixed

- In some cases, the translated 'name' and translated 'subject' of the email template may be lost when they are modified

## v1.0.46 - 2024-11-25

### Removed

- [#625](https://github.com/CCSDForge/episciences/issues/625): 'contact tech support' field has been removed (not used).

### Fixed

- Citations are sometimes not retrieved
- Linked Datasets
    - fixed display of swhids for snapshots + revisions
    - fixed delete button not working with multiple links in same category
    - fixed no confirmation message in confirmation modal
    - fixed duplicate HTML id elements
    - fixed style of forms, use bootstrap
    - used vanilla JS and fetch API instead of Jquery and xmlhttprequest to delete links
    - added missing translations
- Authors affiliation management: use bootstrap style for select
- Update dependency geoip2/geoip2 to v3: todo: downloading databases: https://github.com/P3TERX/GeoLite.mmdb/releases/tag/2024.07.13
- [#506](https://github.com/CCSDForge/episciences/issues/506): Fixed wrong photo displayed when switching profiles.
- Fixed missing volume title when updating metadata from an Episciences journal to HAL.
- [#555](https://github.com/CCSDForge/episciences/issues/555): improved conflict of interest explanations and translations
- Users who have already validated their profile on one of the journals can connect to another without having to validate their profile on that journal (even if they don't have one)
  [#567](https://github.com/CCSDForge/episciences/issues/567): random message recipients.

### Added

- [#513](https://github.com/CCSDForge/episciences/issues/513): Periodic Reminder E-Mails to Editors | editors-in-chief for 'Reviewed' and 'Submitted' articles
- Metadata Export to JSON: include article's citations.
- Created an Opaque UID for users using UUID
    - Profile photo is now stored according to the user's UUID.
    - Script to generate user's UUID.
- New settings for the journals:
    - 'Publisher'
    - 'Publisher location'
    - The values are exported to OpenAIRE and the DOAJ (not supported by Crossref)
- Button to be redirected to the Episciences bibliographic extraction application to import bibtex
- Using COAR Notify with HAL and Episciences to notify HAL of metadata updates for published articles.
- Added in ZBJATS: export bibliographical references from bibtex import
- Added button to redirect to Episciences citation management software without extracting form PDF
- [#418](https://github.com/CCSDForge/episciences/issues/418) Enable edition of relationship and type for the linked data
- Public representation of a document in JSON format (@see /json?v=2)
- [#475](https://github.com/CCSDForge/episciences/issues/475) feature to add biography to profile
- [#476](https://github.com/CCSDForge/episciences/issues/476) feature to add years in volumes forms
- Functionality for migrating news to the new sql news table
- Tinymce toolbar: make headings available
- New field in volume form (volume number)
- [#534](https://github.com/CCSDForge/episciences/issues/534) feature graphical abstract
- Automatic discovery of JEL Classification for published articles, with OpenAIREAPI service
- Automatic discovery of MSC2020 Classification for published articles, with zbMATH Open API service
- Automatic discovery of Reviews on published articles, with zbMATH Open API service

### Changed

- UI/UX: improvements to the editing form and the creation of reminders
- Bump tinymce to latest version (7.2.0)
- Crossref export:
    - For conference proceedings the title and original_language_title elements have been removed from proceedings_metadata: they are not authorised here
    - ROR added to the schema (did not work on the previous version)
- classification of keywords by language
- [#241](https://github.com/CCSDForge/episciences/issues/241) show users roles in mailing popup
- [#531](https://github.com/CCSDForge/episciences/issues/531) enhances volumes form translations

## v1.0.45.2 - 2024-10-22

## Fixed

- [#607](https://github.com/CCSDForge/episciences/issues/607):
    - fixed: the two tags '%COMMENT%%' and '%%ANSWER%%' have identical content.
    - improvement: the attachment with the original comment is now attached to the reply.

### Changed

- [#564](https://github.com/CCSDForge/episciences/issues/564): Fixed and changed French label in journal settings
- [#547](https://github.com/CCSDForge/episciences/issues/547): Ask for other editors opinion: select | deselect all option.
- COAR notifications from HAL: resubmit the same version as a new version.

## v1.0.45.1 - 2024-09-18

- [#585](https://github.com/CCSDForge/episciences/issues/585): The history of manually sent emails is not visible when the "Conflict of Interest" option is enabled.

## v1.0.45 - 2024-09-16

### Added

- [#580](https://github.com/CCSDForge/episciences/issues/580) zbMATH Open export: Authors: add affiliations and ORCIDs

### Fixed

- Volume names are not translated in the volume drop-down list on the reviewing grids list page.
- [#543](https://github.com/CCSDForge/episciences/issues/543): Not translate select options
- [#546](https://github.com/CCSDForge/episciences/issues/546): Editors with different local default date formats cannot update the revision deadline.
- [#571](https://github.com/CCSDForge/episciences/issues/571): The number of days before the review deadline was calculated incorrectly (one day difference).

### Changed

- Ability to block the automatic transfer of a submission from HAL

## v1.0.44.3 - 2024-07-23

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): Completing label updates, fixed label starting with a character outside the [a-z] range;

## v1.0.44.2 - 2024-06-24

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): update the label 'Reviewed' to 'Reviewed pending editorial decision';

## v1.0.44.2 - 2024-06-24

### Changed

- [512](https://github.com/CCSDForge/episciences/issues/512): is now possible to introduce a revision deadline at a later date if the publisher has not indicated a revision deadline (optional) when sending the request for revision.

### Fixed

- [#511](https://github.com/CCSDForge/episciences/issues/511) Do not filter out comments with no message when getting the suggestions form the comment manager.
- Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#507](https://github.com/CCSDForge/episciences/issues/507): Can't change URLs / HTML in email templates
- [#511](https://github.com/CCSDForge/episciences/issues/511):Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#508](https://github.com/CCSDForge/episciences/issues/508): Submission notification parameter: Only the selected roles will receive the notification, If no option is checked, the notification is sent to everyone.
- [#509](https://github.com/CCSDForge/episciences/issues/509): Pixelated photos (Editorial Staff members page)
- Fixed missing spaces with volumes and section on references for arXiv

## v1.0.44.1 - 2024-05-21

### Fixed "Uncaught TypeError: in_array(): Argument #2 ($haystack) must be of type array, null given."

## v1.0.44 - 2024-05-21

### Fixed

- Fixed a bug related to temporary versions with Zenodo:
    - Unable to enter the identifier when submitting a new version
    - submitting a new version results in an error message "the version of the article to be updated must be greater than the previous version".
- [#472](https://github.com/CCSDForge/episciences/issues/472)[Bug Report] English translation missing: Proposer dans le volume #472
- Duplicated submission when automatically transferring a new version from HAL.

### Added

- Add the latest publications to any page by inserting '<div id="widget-browse-latest"></div>' to the HTML source code of a page (same content as /browse/latest page)

## v1.0.43.1 - 2024-05-02

### Fixed

- Reallocation of a previously allocated doi.
- Resetting the position of a paper in a volume will not work if the paper is removed from the volume.
- [#469](https://github.com/CCSDForge/episciences/issues/469) [Bug Report] Add ORCID for an author with apostrophe in the name is broken into two

### Changed

- Allow the assignment of a DOI to a temporarily accepted article
- Automatic numbering of bibliographical references, increased padding between references

## v1.0.43 - 2024-04-17

### Changed

- Updated application's requirements to PHP 8.1.\* . All application dependencies were updated accordingly
- Section's translations are now stored in database instead of server files
- DOI Content Negotiation support for linked data automatically obtained when submitting or updating metadata + code refactoring
- Crossref Export: the unstructured reference is added with the DOI. Previously it was either DOI or the unstructured

### Fixed

- Scholexplorer API usage: The automated script to discover related datasets to published articles now only handle Datasets

### Added

- [#431](https://github.com/CCSDForge/episciences/issues/431): A link from the peer review result page to the article's administration page.
- Using Citation Style Language and APIs we now retrieve and display the text of the references added for datasets, software, HAL Ids, SWHID, arXiv Ids)
- two new statistical indicators ( Submission-acceptance time, Submission-publication time) to the "At a glance" section.
- A new button as a shortcut access the Episciences bibliographic extraction application to import bibtex
- If the metadata of an Episciences publication hosted on HAL is updated, we now send a COAR notification to HAL to trigger an update of the metadata on HAL.
- ZBJATS export: bibliographical references from bibtex import are now also supported and exported

## v1.0.42.5 - 2024-04-17

### Fixed

- [#457](https://github.com/CCSDForge/episciences/issues/457): DOAJ fullText record gives an url that can't be accessed.
- SignPosting headers: fixed missing doi.org domain prefix for DOIs

### Changed

- Taking into account the new value (3) after changing the authorised values in the user CAS table.

## v1.0.42.4 - 2024-03-27

### Fixed

- [#453](https://github.com/CCSDForge/episciences/issues/453): Show a more prominent error when there is a CSRF token error after the editor's comment has been sent.
- When a new version is transferred form HAL, the DOI assigned to the previous version is not restored in the new document.
- [#449](https://github.com/CCSDForge/episciences/issues/449): related to [#169](https://github.com/CCSDForge/episciences/issues/169): the author does not see the review (visible to the administrator but not to the author) on a non-editable version.
- [#458](https://github.com/CCSDForge/episciences/issues/458) [Bug Report] Bibtex export volume name is malformed

## v1.0.42.3 - 2024-03-20

### Fixed

- [#446](https://github.com/CCSDForge/episciences/issues/446) and [#448](https://github.com/CCSDForge/episciences/issues/448): Paper list drops some spaces in abstracts.
- [#450](https://github.com/CCSDForge/episciences/issues/450): Fixed a server configuration issue for Theoretics

### Changed

- [#450](https://github.com/CCSDForge/episciences/issues/450): Open the target of the journal's logo in the same page/tab

## v1.0.42.2 - 2024-03-19

### Fixed

- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers:
  Make sure the invitation is sent x days [delay] later.
- Wrong display of titles and abstracts on the latest articles page: these will now be displayed in the main language [if available].

## v1.0.42.1 - 2024-03-12

### Changed

- delete a volume: add assigned papers error's message.
- Sorted paper by volume: include assigned papers to this one as a secondary volume
- [#445](https://github.com/CCSDForge/episciences/issues/445): do not limit the number of papers per page to 10: the default limit (100) is used + acceptance date descending order.

### Fixed

- Exporting volumes in DOAJ format does not work for Secondary Volumes
- [#423](https://github.com/CCSDForge/episciences/issues/423): fix typo Technical support Email.
- [#443](https://github.com/CCSDForge/episciences/issues/443): In the templates (New version - author and co-author copy, New temporary version author and co-author copy) the tag %%PERMANENT_ARTICLE_ID%% is used, but it did not appear in the subject of the message.
- [#443](https://github.com/CCSDForge/episciences/issues/443): no content for tags %%TAG_AUTHORS_NAMES%% and %%TAG_CONTRIBUTOR_FULL_NAME%%.
- The guest editor could not see the cover letter for version 2 and the reviewers' names (Revues/Reviews section) on the first version.
- Links to documentation : the "About version numbers" link url has changed and has not been updated.
- Browse by year: documents have been indexed by year of submission instead of year of publication

## v1.0.42 - 2024-02-15

### Added

- Added an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings. The feature is also handled by the Episciences API

### Fixed

- Submission form validation fails when replacing existing version.

## v1.0.41.7 - 2024-02-12

### Fixed

- [Bug Report] Browse by authors or date: wrong links on the titles #435
- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers.
- Fixed ORCID max length of user profiles

### Changed

- Invite a new user form: make it possible to choose between two languages, even for uni-lingual sites & label renamed to 'Default Language'.

## v1.0.41.7 - 2024-01-23

### Changed

- [COAR Notify] processing of new versions + enhancements
- Improvements on the indexing process with Apache Solr

### Fixed

- Script ZBJATS : Skip documents which are not articles with a PDF, such as datasets
- Application error: Argument 1 passed to Episciences_Volume::setTitles() must be of the type array or null, string give.
- "false positive" for missing translations in logs.
- [COAR Notify] when processing several submission at the same time, notification of submissions were sent to wrong recipients
- [Acceptance date] During the publication process, if the article is accepted with a temporary version, then its acceptance date is that of the 1st temporary version. The problem did not exist on published articles.
- Citations of published papers: Improvement in obtaining the source journal from openAlex's response

## v1.0.41.6 - 2024-01-15

### Fixed

- PHP Notice: FAILED_TO_COPY_FILE_ERROR

## v1.0.41.5 - 2024-01-11

### Fixed

- Typo [423](https://github.com/CCSDForge/episciences/issues/423)
- Crossref export of bibliographical references: fixed unescaped HTML, added a log for XML errors
- Fix #417 Identifiers other than SWHID for Software solutions: remove domain prefixing SWH for all software, add the right domain for software (other than SWH), add correct domain for software in TEI

## v1.0.46.1 - 2024-11-27

### Fixed

- In some cases, the translated 'name' and translated 'subject' of the email template may be lost when they are modified

## v1.0.46 - 2024-11-25

### Removed

- [#625](https://github.com/CCSDForge/episciences/issues/625): 'contact tech support' field has been removed (not used).

### Fixed

- Citations are sometimes not retrieved
- Linked Datasets
    - fixed display of swhids for snapshots + revisions
    - fixed delete button not working with multiple links in same category
    - fixed no confirmation message in confirmation modal
    - fixed duplicate HTML id elements
    - fixed style of forms, use bootstrap
    - used vanilla JS and fetch API instead of Jquery and xmlhttprequest to delete links
    - added missing translations
- Authors affiliation management: use bootstrap style for select
- Update dependency geoip2/geoip2 to v3: todo: downloading databases: https://github.com/P3TERX/GeoLite.mmdb/releases/tag/2024.07.13
- [#506](https://github.com/CCSDForge/episciences/issues/506): Fixed wrong photo displayed when switching profiles.
- Fixed missing volume title when updating metadata from an Episciences journal to HAL.
- [#555](https://github.com/CCSDForge/episciences/issues/555): improved conflict of interest explanations and translations
- Users who have already validated their profile on one of the journals can connect to another without having to validate their profile on that journal (even if they don't have one)
  [#567](https://github.com/CCSDForge/episciences/issues/567): random message recipients.

### Added

- [#513](https://github.com/CCSDForge/episciences/issues/513): Periodic Reminder E-Mails to Editors | editors-in-chief for 'Reviewed' and 'Submitted' articles
- Metadata Export to JSON: include article's citations.
- Created an Opaque UID for users using UUID
    - Profile photo is now stored according to the user's UUID.
    - Script to generate user's UUID.
- New settings for the journals:
    - 'Publisher'
    - 'Publisher location'
    - The values are exported to OpenAIRE and the DOAJ (not supported by Crossref)
- Button to be redirected to the Episciences bibliographic extraction application to import bibtex
- Using COAR Notify with HAL and Episciences to notify HAL of metadata updates for published articles.
- Added in ZBJATS: export bibliographical references from bibtex import
- Added button to redirect to Episciences citation management software without extracting form PDF
- [#418](https://github.com/CCSDForge/episciences/issues/418) Enable edition of relationship and type for the linked data
- Public representation of a document in JSON format (@see /json?v=2)
- [#475](https://github.com/CCSDForge/episciences/issues/475) feature to add biography to profile
- [#476](https://github.com/CCSDForge/episciences/issues/476) feature to add years in volumes forms
- Functionality for migrating news to the new sql news table
- Tinymce toolbar: make headings available
- New field in volume form (volume number)
- [#534](https://github.com/CCSDForge/episciences/issues/534) feature graphical abstract
- Automatic discovery of JEL Classification for published articles, with OpenAIREAPI service
- Automatic discovery of MSC2020 Classification for published articles, with zbMATH Open API service
- Automatic discovery of Reviews on published articles, with zbMATH Open API service

### Changed

- UI/UX: improvements to the editing form and the creation of reminders
- Bump tinymce to latest version (7.2.0)
- Crossref export:
    - For conference proceedings the title and original_language_title elements have been removed from proceedings_metadata: they are not authorised here
    - ROR added to the schema (did not work on the previous version)
- classification of keywords by language
- [#241](https://github.com/CCSDForge/episciences/issues/241) show users roles in mailing popup
- [#531](https://github.com/CCSDForge/episciences/issues/531) enhances volumes form translations

## v1.0.45.2 - 2024-10-22

## Fixed

- [#607](https://github.com/CCSDForge/episciences/issues/607):
    - fixed: the two tags '%COMMENT%%' and '%%ANSWER%%' have identical content.
    - improvement: the attachment with the original comment is now attached to the reply.

### Changed

- [#564](https://github.com/CCSDForge/episciences/issues/564): Fixed and changed French label in journal settings
- [#547](https://github.com/CCSDForge/episciences/issues/547): Ask for other editors opinion: select | deselect all option.
- COAR notifications from HAL: resubmit the same version as a new version.

## v1.0.45.1 - 2024-09-18

- [#585](https://github.com/CCSDForge/episciences/issues/585): The history of manually sent emails is not visible when the "Conflict of Interest" option is enabled.

## v1.0.45 - 2024-09-16

### Added

- [#580](https://github.com/CCSDForge/episciences/issues/580) zbMATH Open export: Authors: add affiliations and ORCIDs

### Fixed

- Volume names are not translated in the volume drop-down list on the reviewing grids list page.
- [#543](https://github.com/CCSDForge/episciences/issues/543): Not translate select options
- [#546](https://github.com/CCSDForge/episciences/issues/546): Editors with different local default date formats cannot update the revision deadline.
- [#571](https://github.com/CCSDForge/episciences/issues/571): The number of days before the review deadline was calculated incorrectly (one day difference).

### Changed

- Ability to block the automatic transfer of a submission from HAL

## v1.0.44.3 - 2024-07-23

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): Completing label updates, fixed label starting with a character outside the [a-z] range;

## v1.0.44.2 - 2024-06-24

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): update the label 'Reviewed' to 'Reviewed pending editorial decision';

## v1.0.44.2 - 2024-06-24

### Changed

- [512](https://github.com/CCSDForge/episciences/issues/512): is now possible to introduce a revision deadline at a later date if the publisher has not indicated a revision deadline (optional) when sending the request for revision.

### Fixed

- [#511](https://github.com/CCSDForge/episciences/issues/511) Do not filter out comments with no message when getting the suggestions form the comment manager.
- Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#507](https://github.com/CCSDForge/episciences/issues/507): Can't change URLs / HTML in email templates
- [#511](https://github.com/CCSDForge/episciences/issues/511):Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#508](https://github.com/CCSDForge/episciences/issues/508): Submission notification parameter: Only the selected roles will receive the notification, If no option is checked, the notification is sent to everyone.
- [#509](https://github.com/CCSDForge/episciences/issues/509): Pixelated photos (Editorial Staff members page)
- Fixed missing spaces with volumes and section on references for arXiv

## v1.0.44.1 - 2024-05-21

### Fixed "Uncaught TypeError: in_array(): Argument #2 ($haystack) must be of type array, null given."

## v1.0.44 - 2024-05-21

### Fixed

- Fixed a bug related to temporary versions with Zenodo:
    - Unable to enter the identifier when submitting a new version
    - submitting a new version results in an error message "the version of the article to be updated must be greater than the previous version".
- [#472](https://github.com/CCSDForge/episciences/issues/472)[Bug Report] English translation missing: Proposer dans le volume #472
- Duplicated submission when automatically transferring a new version from HAL.

### Added

- Add the latest publications to any page by inserting '<div id="widget-browse-latest"></div>' to the HTML source code of a page (same content as /browse/latest page)

## v1.0.43.1 - 2024-05-02

### Fixed

- Reallocation of a previously allocated doi.
- Resetting the position of a paper in a volume will not work if the paper is removed from the volume.
- [#469](https://github.com/CCSDForge/episciences/issues/469) [Bug Report] Add ORCID for an author with apostrophe in the name is broken into two

### Changed

- Allow the assignment of a DOI to a temporarily accepted article
- Automatic numbering of bibliographical references, increased padding between references

## v1.0.43 - 2024-04-17

### Changed

- Updated application's requirements to PHP 8.1.\* . All application dependencies were updated accordingly
- Section's translations are now stored in database instead of server files
- DOI Content Negotiation support for linked data automatically obtained when submitting or updating metadata + code refactoring
- Crossref Export: the unstructured reference is added with the DOI. Previously it was either DOI or the unstructured

### Fixed

- Scholexplorer API usage: The automated script to discover related datasets to published articles now only handle Datasets

### Added

- [#431](https://github.com/CCSDForge/episciences/issues/431): A link from the peer review result page to the article's administration page.
- Using Citation Style Language and APIs we now retrieve and display the text of the references added for datasets, software, HAL Ids, SWHID, arXiv Ids)
- two new statistical indicators ( Submission-acceptance time, Submission-publication time) to the "At a glance" section.
- A new button as a shortcut access the Episciences bibliographic extraction application to import bibtex
- If the metadata of an Episciences publication hosted on HAL is updated, we now send a COAR notification to HAL to trigger an update of the metadata on HAL.
- ZBJATS export: bibliographical references from bibtex import are now also supported and exported

## v1.0.42.5 - 2024-04-17

### Fixed

- [#457](https://github.com/CCSDForge/episciences/issues/457): DOAJ fullText record gives an url that can't be accessed.
- SignPosting headers: fixed missing doi.org domain prefix for DOIs

### Changed

- Taking into account the new value (3) after changing the authorised values in the user CAS table.

## v1.0.42.4 - 2024-03-27

### Fixed

- [#453](https://github.com/CCSDForge/episciences/issues/453): Show a more prominent error when there is a CSRF token error after the editor's comment has been sent.
- When a new version is transferred form HAL, the DOI assigned to the previous version is not restored in the new document.
- [#449](https://github.com/CCSDForge/episciences/issues/449): related to [#169](https://github.com/CCSDForge/episciences/issues/169): the author does not see the review (visible to the administrator but not to the author) on a non-editable version.
- [#458](https://github.com/CCSDForge/episciences/issues/458) [Bug Report] Bibtex export volume name is malformed

## v1.0.42.3 - 2024-03-20

### Fixed

- [#446](https://github.com/CCSDForge/episciences/issues/446) and [#448](https://github.com/CCSDForge/episciences/issues/448): Paper list drops some spaces in abstracts.
- [#450](https://github.com/CCSDForge/episciences/issues/450): Fixed a server configuration issue for Theoretics

### Changed

- [#450](https://github.com/CCSDForge/episciences/issues/450): Open the target of the journal's logo in the same page/tab

## v1.0.42.2 - 2024-03-19

### Fixed

- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers:
  Make sure the invitation is sent x days [delay] later.
- Wrong display of titles and abstracts on the latest articles page: these will now be displayed in the main language [if available].

## v1.0.42.1 - 2024-03-12

### Changed

- delete a volume: add assigned papers error's message.
- Sorted paper by volume: include assigned papers to this one as a secondary volume
- [#445](https://github.com/CCSDForge/episciences/issues/445): do not limit the number of papers per page to 10: the default limit (100) is used + acceptance date descending order.

### Fixed

- Exporting volumes in DOAJ format does not work for Secondary Volumes
- [#423](https://github.com/CCSDForge/episciences/issues/423): fix typo Technical support Email.
- [#443](https://github.com/CCSDForge/episciences/issues/443): In the templates (New version - author and co-author copy, New temporary version author and co-author copy) the tag %%PERMANENT_ARTICLE_ID%% is used, but it did not appear in the subject of the message.
- [#443](https://github.com/CCSDForge/episciences/issues/443): no content for tags %%TAG_AUTHORS_NAMES%% and %%TAG_CONTRIBUTOR_FULL_NAME%%.
- The guest editor could not see the cover letter for version 2 and the reviewers' names (Revues/Reviews section) on the first version.
- Links to documentation : the "About version numbers" link url has changed and has not been updated.
- Browse by year: documents have been indexed by year of submission instead of year of publication

## v1.0.42 - 2024-02-15

### Added

- Added an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings. The feature is also handled by the Episciences API

### Fixed

- Submission form validation fails when replacing existing version.

## v1.0.41.7 - 2024-02-12

### Fixed

- [Bug Report] Browse by authors or date: wrong links on the titles #435
- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers.
- Fixed ORCID max length of user profiles

### Changed

- Invite a new user form: make it possible to choose between two languages, even for uni-lingual sites & label renamed to 'Default Language'.

## v1.0.41.7 - 2024-01-23

### Changed

- [COAR Notify] processing of new versions + enhancements
- Improvements on the indexing process with Apache Solr

### Fixed

- Script ZBJATS : Skip documents which are not articles with a PDF, such as datasets
- Application error: Argument 1 passed to Episciences_Volume::setTitles() must be of the type array or null, string give.
- "false positive" for missing translations in logs.
- [COAR Notify] when processing several submission at the same time, notification of submissions were sent to wrong recipients
- [Acceptance date] During the publication process, if the article is accepted with a temporary version, then its acceptance date is that of the 1st temporary version. The problem did not exist on published articles.
- Citations of published papers: Improvement in obtaining the source journal from openAlex's response

## v1.0.41.6 - 2024-01-15

### Fixed

- PHP Notice: FAILED_TO_COPY_FILE_ERROR

## v1.0.41.5 - 2024-01-11

### Fixed

- Typo [423](https://github.com/CCSDForge/episciences/issues/423)
- Crossref export of bibliographical references: fixed unescaped HTML, added a log for XML errors
- Fix #417 Identifiers other than SWHID for Software solutions: remove domain prefixing SWH for all software, add the right domain for software (other than SWH), add correct domain for software in TEI

## v1.0.46.1 - 2024-11-27

### Fixed

- In some cases, the translated 'name' and translated 'subject' of the email template may be lost when they are modified

## v1.0.46 - 2024-11-25

### Removed

- [#625](https://github.com/CCSDForge/episciences/issues/625): 'contact tech support' field has been removed (not used).

### Fixed

- Citations are sometimes not retrieved
- Linked Datasets
    - fixed display of swhids for snapshots + revisions
    - fixed delete button not working with multiple links in same category
    - fixed no confirmation message in confirmation modal
    - fixed duplicate HTML id elements
    - fixed style of forms, use bootstrap
    - used vanilla JS and fetch API instead of Jquery and xmlhttprequest to delete links
    - added missing translations
- Authors affiliation management: use bootstrap style for select
- Update dependency geoip2/geoip2 to v3: todo: downloading databases: https://github.com/P3TERX/GeoLite.mmdb/releases/tag/2024.07.13
- [#506](https://github.com/CCSDForge/episciences/issues/506): Fixed wrong photo displayed when switching profiles.
- Fixed missing volume title when updating metadata from an Episciences journal to HAL.
- [#555](https://github.com/CCSDForge/episciences/issues/555): improved conflict of interest explanations and translations
- Users who have already validated their profile on one of the journals can connect to another without having to validate their profile on that journal (even if they don't have one)
  [#567](https://github.com/CCSDForge/episciences/issues/567): random message recipients.

### Added

- [#513](https://github.com/CCSDForge/episciences/issues/513): Periodic Reminder E-Mails to Editors | editors-in-chief for 'Reviewed' and 'Submitted' articles
- Metadata Export to JSON: include article's citations.
- Created an Opaque UID for users using UUID
    - Profile photo is now stored according to the user's UUID.
    - Script to generate user's UUID.
- New settings for the journals:
    - 'Publisher'
    - 'Publisher location'
    - The values are exported to OpenAIRE and the DOAJ (not supported by Crossref)
- Button to be redirected to the Episciences bibliographic extraction application to import bibtex
- Using COAR Notify with HAL and Episciences to notify HAL of metadata updates for published articles.
- Added in ZBJATS: export bibliographical references from bibtex import
- Added button to redirect to Episciences citation management software without extracting form PDF
- [#418](https://github.com/CCSDForge/episciences/issues/418) Enable edition of relationship and type for the linked data
- Public representation of a document in JSON format (@see /json?v=2)
- [#475](https://github.com/CCSDForge/episciences/issues/475) feature to add biography to profile
- [#476](https://github.com/CCSDForge/episciences/issues/476) feature to add years in volumes forms
- Functionality for migrating news to the new sql news table
- Tinymce toolbar: make headings available
- New field in volume form (volume number)
- [#534](https://github.com/CCSDForge/episciences/issues/534) feature graphical abstract
- Automatic discovery of JEL Classification for published articles, with OpenAIREAPI service
- Automatic discovery of MSC2020 Classification for published articles, with zbMATH Open API service
- Automatic discovery of Reviews on published articles, with zbMATH Open API service

### Changed

- UI/UX: improvements to the editing form and the creation of reminders
- Bump tinymce to latest version (7.2.0)
- Crossref export:
    - For conference proceedings the title and original_language_title elements have been removed from proceedings_metadata: they are not authorised here
    - ROR added to the schema (did not work on the previous version)
- classification of keywords by language
- [#241](https://github.com/CCSDForge/episciences/issues/241) show users roles in mailing popup
- [#531](https://github.com/CCSDForge/episciences/issues/531) enhances volumes form translations

## v1.0.45.2 - 2024-10-22

## Fixed

- [#607](https://github.com/CCSDForge/episciences/issues/607):
    - fixed: the two tags '%COMMENT%%' and '%%ANSWER%%' have identical content.
    - improvement: the attachment with the original comment is now attached to the reply.

### Changed

- [#564](https://github.com/CCSDForge/episciences/issues/564): Fixed and changed French label in journal settings
- [#547](https://github.com/CCSDForge/episciences/issues/547): Ask for other editors opinion: select | deselect all option.
- COAR notifications from HAL: resubmit the same version as a new version.

## v1.0.45.1 - 2024-09-18

- [#585](https://github.com/CCSDForge/episciences/issues/585): The history of manually sent emails is not visible when the "Conflict of Interest" option is enabled.

## v1.0.45 - 2024-09-16

### Added

- [#580](https://github.com/CCSDForge/episciences/issues/580) zbMATH Open export: Authors: add affiliations and ORCIDs

### Fixed

- Volume names are not translated in the volume drop-down list on the reviewing grids list page.
- [#543](https://github.com/CCSDForge/episciences/issues/543): Not translate select options
- [#546](https://github.com/CCSDForge/episciences/issues/546): Editors with different local default date formats cannot update the revision deadline.
- [#571](https://github.com/CCSDForge/episciences/issues/571): The number of days before the review deadline was calculated incorrectly (one day difference).

### Changed

- Ability to block the automatic transfer of a submission from HAL

## v1.0.44.3 - 2024-07-23

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): Completing label updates, fixed label starting with a character outside the [a-z] range;

## v1.0.44.2 - 2024-06-24

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): update the label 'Reviewed' to 'Reviewed pending editorial decision';

## v1.0.44.2 - 2024-06-24

### Changed

- [512](https://github.com/CCSDForge/episciences/issues/512): is now possible to introduce a revision deadline at a later date if the publisher has not indicated a revision deadline (optional) when sending the request for revision.

### Fixed

- [#511](https://github.com/CCSDForge/episciences/issues/511) Do not filter out comments with no message when getting the suggestions form the comment manager.
- Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#507](https://github.com/CCSDForge/episciences/issues/507): Can't change URLs / HTML in email templates
- [#511](https://github.com/CCSDForge/episciences/issues/511):Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#508](https://github.com/CCSDForge/episciences/issues/508): Submission notification parameter: Only the selected roles will receive the notification, If no option is checked, the notification is sent to everyone.
- [#509](https://github.com/CCSDForge/episciences/issues/509): Pixelated photos (Editorial Staff members page)
- Fixed missing spaces with volumes and section on references for arXiv

## v1.0.44.1 - 2024-05-21

### Fixed "Uncaught TypeError: in_array(): Argument #2 ($haystack) must be of type array, null given."

## v1.0.44 - 2024-05-21

### Fixed

- Fixed a bug related to temporary versions with Zenodo:
    - Unable to enter the identifier when submitting a new version
    - submitting a new version results in an error message "the version of the article to be updated must be greater than the previous version".
- [#472](https://github.com/CCSDForge/episciences/issues/472)[Bug Report] English translation missing: Proposer dans le volume #472
- Duplicated submission when automatically transferring a new version from HAL.

### Added

- Add the latest publications to any page by inserting '<div id="widget-browse-latest"></div>' to the HTML source code of a page (same content as /browse/latest page)

## v1.0.43.1 - 2024-05-02

### Fixed

- Reallocation of a previously allocated doi.
- Resetting the position of a paper in a volume will not work if the paper is removed from the volume.
- [#469](https://github.com/CCSDForge/episciences/issues/469) [Bug Report] Add ORCID for an author with apostrophe in the name is broken into two

### Changed

- Allow the assignment of a DOI to a temporarily accepted article
- Automatic numbering of bibliographical references, increased padding between references

## v1.0.43 - 2024-04-17

### Changed

- Updated application's requirements to PHP 8.1.\* . All application dependencies were updated accordingly
- Section's translations are now stored in database instead of server files
- DOI Content Negotiation support for linked data automatically obtained when submitting or updating metadata + code refactoring
- Crossref Export: the unstructured reference is added with the DOI. Previously it was either DOI or the unstructured

### Fixed

- Scholexplorer API usage: The automated script to discover related datasets to published articles now only handle Datasets

### Added

- [#431](https://github.com/CCSDForge/episciences/issues/431): A link from the peer review result page to the article's administration page.
- Using Citation Style Language and APIs we now retrieve and display the text of the references added for datasets, software, HAL Ids, SWHID, arXiv Ids)
- two new statistical indicators ( Submission-acceptance time, Submission-publication time) to the "At a glance" section.
- A new button as a shortcut access the Episciences bibliographic extraction application to import bibtex
- If the metadata of an Episciences publication hosted on HAL is updated, we now send a COAR notification to HAL to trigger an update of the metadata on HAL.
- ZBJATS export: bibliographical references from bibtex import are now also supported and exported

## v1.0.42.5 - 2024-04-17

### Fixed

- [#457](https://github.com/CCSDForge/episciences/issues/457): DOAJ fullText record gives an url that can't be accessed.
- SignPosting headers: fixed missing doi.org domain prefix for DOIs

### Changed

- Taking into account the new value (3) after changing the authorised values in the user CAS table.

## v1.0.42.4 - 2024-03-27

### Fixed

- [#453](https://github.com/CCSDForge/episciences/issues/453): Show a more prominent error when there is a CSRF token error after the editor's comment has been sent.
- When a new version is transferred form HAL, the DOI assigned to the previous version is not restored in the new document.
- [#449](https://github.com/CCSDForge/episciences/issues/449): related to [#169](https://github.com/CCSDForge/episciences/issues/169): the author does not see the review (visible to the administrator but not to the author) on a non-editable version.
- [#458](https://github.com/CCSDForge/episciences/issues/458) [Bug Report] Bibtex export volume name is malformed

## v1.0.42.3 - 2024-03-20

### Fixed

- [#446](https://github.com/CCSDForge/episciences/issues/446) and [#448](https://github.com/CCSDForge/episciences/issues/448): Paper list drops some spaces in abstracts.
- [#450](https://github.com/CCSDForge/episciences/issues/450): Fixed a server configuration issue for Theoretics

### Changed

- [#450](https://github.com/CCSDForge/episciences/issues/450): Open the target of the journal's logo in the same page/tab

## v1.0.42.2 - 2024-03-19

### Fixed

- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers:
  Make sure the invitation is sent x days [delay] later.
- Wrong display of titles and abstracts on the latest articles page: these will now be displayed in the main language [if available].

## v1.0.42.1 - 2024-03-12

### Changed

- delete a volume: add assigned papers error's message.
- Sorted paper by volume: include assigned papers to this one as a secondary volume
- [#445](https://github.com/CCSDForge/episciences/issues/445): do not limit the number of papers per page to 10: the default limit (100) is used + acceptance date descending order.

### Fixed

- Exporting volumes in DOAJ format does not work for Secondary Volumes
- [#423](https://github.com/CCSDForge/episciences/issues/423): fix typo Technical support Email.
- [#443](https://github.com/CCSDForge/episciences/issues/443): In the templates (New version - author and co-author copy, New temporary version author and co-author copy) the tag %%PERMANENT_ARTICLE_ID%% is used, but it did not appear in the subject of the message.
- [#443](https://github.com/CCSDForge/episciences/issues/443): no content for tags %%TAG_AUTHORS_NAMES%% and %%TAG_CONTRIBUTOR_FULL_NAME%%.
- The guest editor could not see the cover letter for version 2 and the reviewers' names (Revues/Reviews section) on the first version.
- Links to documentation : the "About version numbers" link url has changed and has not been updated.
- Browse by year: documents have been indexed by year of submission instead of year of publication

## v1.0.42 - 2024-02-15

### Added

- Added an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings. The feature is also handled by the Episciences API

### Fixed

- Submission form validation fails when replacing existing version.

## v1.0.41.7 - 2024-02-12

### Fixed

- [Bug Report] Browse by authors or date: wrong links on the titles #435
- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers.
- Fixed ORCID max length of user profiles

### Changed

- Invite a new user form: make it possible to choose between two languages, even for uni-lingual sites & label renamed to 'Default Language'.

## v1.0.41.7 - 2024-01-23

### Changed

- [COAR Notify] processing of new versions + enhancements
- Improvements on the indexing process with Apache Solr

### Fixed

- Script ZBJATS : Skip documents which are not articles with a PDF, such as datasets
- Application error: Argument 1 passed to Episciences_Volume::setTitles() must be of the type array or null, string give.
- "false positive" for missing translations in logs.
- [COAR Notify] when processing several submission at the same time, notification of submissions were sent to wrong recipients
- [Acceptance date] During the publication process, if the article is accepted with a temporary version, then its acceptance date is that of the 1st temporary version. The problem did not exist on published articles.
- Citations of published papers: Improvement in obtaining the source journal from openAlex's response

## v1.0.41.6 - 2024-01-15

### Fixed

- PHP Notice: FAILED_TO_COPY_FILE_ERROR

## v1.0.41.5 - 2024-01-11

### Fixed

- Typo [423](https://github.com/CCSDForge/episciences/issues/423)
- Crossref export of bibliographical references: fixed unescaped HTML, added a log for XML errors
- Fix #417 Identifiers other than SWHID for Software solutions: remove domain prefixing SWH for all software, add the right domain for software (other than SWH), add correct domain for software in TEI

## v1.0.46.1 - 2024-11-27

### Fixed

- In some cases, the translated 'name' and translated 'subject' of the email template may be lost when they are modified

## v1.0.46 - 2024-11-25

### Removed

- [#625](https://github.com/CCSDForge/episciences/issues/625): 'contact tech support' field has been removed (not used).

### Fixed

- Citations are sometimes not retrieved
- Linked Datasets
    - fixed display of swhids for snapshots + revisions
    - fixed delete button not working with multiple links in same category
    - fixed no confirmation message in confirmation modal
    - fixed duplicate HTML id elements
    - fixed style of forms, use bootstrap
    - used vanilla JS and fetch API instead of Jquery and xmlhttprequest to delete links
    - added missing translations
- Authors affiliation management: use bootstrap style for select
- Update dependency geoip2/geoip2 to v3: todo: downloading databases: https://github.com/P3TERX/GeoLite.mmdb/releases/tag/2024.07.13
- [#506](https://github.com/CCSDForge/episciences/issues/506): Fixed wrong photo displayed when switching profiles.
- Fixed missing volume title when updating metadata from an Episciences journal to HAL.
- [#555](https://github.com/CCSDForge/episciences/issues/555): improved conflict of interest explanations and translations
- Users who have already validated their profile on one of the journals can connect to another without having to validate their profile on that journal (even if they don't have one)
  [#567](https://github.com/CCSDForge/episciences/issues/567): random message recipients.

### Added

- [#513](https://github.com/CCSDForge/episciences/issues/513): Periodic Reminder E-Mails to Editors | editors-in-chief for 'Reviewed' and 'Submitted' articles
- Metadata Export to JSON: include article's citations.
- Created an Opaque UID for users using UUID
    - Profile photo is now stored according to the user's UUID.
    - Script to generate user's UUID.
- New settings for the journals:
    - 'Publisher'
    - 'Publisher location'
    - The values are exported to OpenAIRE and the DOAJ (not supported by Crossref)
- Button to be redirected to the Episciences bibliographic extraction application to import bibtex
- Using COAR Notify with HAL and Episciences to notify HAL of metadata updates for published articles.
- Added in ZBJATS: export bibliographical references from bibtex import
- Added button to redirect to Episciences citation management software without extracting form PDF
- [#418](https://github.com/CCSDForge/episciences/issues/418) Enable edition of relationship and type for the linked data
- Public representation of a document in JSON format (@see /json?v=2)
- [#475](https://github.com/CCSDForge/episciences/issues/475) feature to add biography to profile
- [#476](https://github.com/CCSDForge/episciences/issues/476) feature to add years in volumes forms
- Functionality for migrating news to the new sql news table
- Tinymce toolbar: make headings available
- New field in volume form (volume number)
- [#534](https://github.com/CCSDForge/episciences/issues/534) feature graphical abstract
- Automatic discovery of JEL Classification for published articles, with OpenAIREAPI service
- Automatic discovery of MSC2020 Classification for published articles, with zbMATH Open API service
- Automatic discovery of Reviews on published articles, with zbMATH Open API service

### Changed

- UI/UX: improvements to the editing form and the creation of reminders
- Bump tinymce to latest version (7.2.0)
- Crossref export:
    - For conference proceedings the title and original_language_title elements have been removed from proceedings_metadata: they are not authorised here
    - ROR added to the schema (did not work on the previous version)
- classification of keywords by language
- [#241](https://github.com/CCSDForge/episciences/issues/241) show users roles in mailing popup
- [#531](https://github.com/CCSDForge/episciences/issues/531) enhances volumes form translations

## v1.0.45.2 - 2024-10-22

## Fixed

- [#607](https://github.com/CCSDForge/episciences/issues/607):
    - fixed: the two tags '%COMMENT%%' and '%%ANSWER%%' have identical content.
    - improvement: the attachment with the original comment is now attached to the reply.

### Changed

- [#564](https://github.com/CCSDForge/episciences/issues/564): Fixed and changed French label in journal settings
- [#547](https://github.com/CCSDForge/episciences/issues/547): Ask for other editors opinion: select | deselect all option.
- COAR notifications from HAL: resubmit the same version as a new version.

## v1.0.45.1 - 2024-09-18

- [#585](https://github.com/CCSDForge/episciences/issues/585): The history of manually sent emails is not visible when the "Conflict of Interest" option is enabled.

## v1.0.45 - 2024-09-16

### Added

- [#580](https://github.com/CCSDForge/episciences/issues/580) zbMATH Open export: Authors: add affiliations and ORCIDs

### Fixed

- Volume names are not translated in the volume drop-down list on the reviewing grids list page.
- [#543](https://github.com/CCSDForge/episciences/issues/543): Not translate select options
- [#546](https://github.com/CCSDForge/episciences/issues/546): Editors with different local default date formats cannot update the revision deadline.
- [#571](https://github.com/CCSDForge/episciences/issues/571): The number of days before the review deadline was calculated incorrectly (one day difference).

### Changed

- Ability to block the automatic transfer of a submission from HAL

## v1.0.44.3 - 2024-07-23

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): Completing label updates, fixed label starting with a character outside the [a-z] range;

## v1.0.44.2 - 2024-06-24

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): update the label 'Reviewed' to 'Reviewed pending editorial decision';

## v1.0.44.2 - 2024-06-24

### Changed

- [512](https://github.com/CCSDForge/episciences/issues/512): is now possible to introduce a revision deadline at a later date if the publisher has not indicated a revision deadline (optional) when sending the request for revision.

### Fixed

- [#511](https://github.com/CCSDForge/episciences/issues/511) Do not filter out comments with no message when getting the suggestions form the comment manager.
- Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#507](https://github.com/CCSDForge/episciences/issues/507): Can't change URLs / HTML in email templates
- [#511](https://github.com/CCSDForge/episciences/issues/511):Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#508](https://github.com/CCSDForge/episciences/issues/508): Submission notification parameter: Only the selected roles will receive the notification, If no option is checked, the notification is sent to everyone.
- [#509](https://github.com/CCSDForge/episciences/issues/509): Pixelated photos (Editorial Staff members page)
- Fixed missing spaces with volumes and section on references for arXiv

## v1.0.44.1 - 2024-05-21

### Fixed "Uncaught TypeError: in_array(): Argument #2 ($haystack) must be of type array, null given."

## v1.0.44 - 2024-05-21

### Fixed

- Fixed a bug related to temporary versions with Zenodo:
    - Unable to enter the identifier when submitting a new version
    - submitting a new version results in an error message "the version of the article to be updated must be greater than the previous version".
- [#472](https://github.com/CCSDForge/episciences/issues/472)[Bug Report] English translation missing: Proposer dans le volume #472
- Duplicated submission when automatically transferring a new version from HAL.

### Added

- Add the latest publications to any page by inserting '<div id="widget-browse-latest"></div>' to the HTML source code of a page (same content as /browse/latest page)

## v1.0.43.1 - 2024-05-02

### Fixed

- Reallocation of a previously allocated doi.
- Resetting the position of a paper in a volume will not work if the paper is removed from the volume.
- [#469](https://github.com/CCSDForge/episciences/issues/469) [Bug Report] Add ORCID for an author with apostrophe in the name is broken into two

### Changed

- Allow the assignment of a DOI to a temporarily accepted article
- Automatic numbering of bibliographical references, increased padding between references

## v1.0.43 - 2024-04-17

### Changed

- Updated application's requirements to PHP 8.1.\* . All application dependencies were updated accordingly
- Section's translations are now stored in database instead of server files
- DOI Content Negotiation support for linked data automatically obtained when submitting or updating metadata + code refactoring
- Crossref Export: the unstructured reference is added with the DOI. Previously it was either DOI or the unstructured

### Fixed

- Scholexplorer API usage: The automated script to discover related datasets to published articles now only handle Datasets

### Added

- [#431](https://github.com/CCSDForge/episciences/issues/431): A link from the peer review result page to the article's administration page.
- Using Citation Style Language and APIs we now retrieve and display the text of the references added for datasets, software, HAL Ids, SWHID, arXiv Ids)
- two new statistical indicators ( Submission-acceptance time, Submission-publication time) to the "At a glance" section.
- A new button as a shortcut access the Episciences bibliographic extraction application to import bibtex
- If the metadata of an Episciences publication hosted on HAL is updated, we now send a COAR notification to HAL to trigger an update of the metadata on HAL.
- ZBJATS export: bibliographical references from bibtex import are now also supported and exported

## v1.0.42.5 - 2024-04-17

### Fixed

- [#457](https://github.com/CCSDForge/episciences/issues/457): DOAJ fullText record gives an url that can't be accessed.
- SignPosting headers: fixed missing doi.org domain prefix for DOIs

### Changed

- Taking into account the new value (3) after changing the authorised values in the user CAS table.

## v1.0.42.4 - 2024-03-27

### Fixed

- [#453](https://github.com/CCSDForge/episciences/issues/453): Show a more prominent error when there is a CSRF token error after the editor's comment has been sent.
- When a new version is transferred form HAL, the DOI assigned to the previous version is not restored in the new document.
- [#449](https://github.com/CCSDForge/episciences/issues/449): related to [#169](https://github.com/CCSDForge/episciences/issues/169): the author does not see the review (visible to the administrator but not to the author) on a non-editable version.
- [#458](https://github.com/CCSDForge/episciences/issues/458) [Bug Report] Bibtex export volume name is malformed

## v1.0.42.3 - 2024-03-20

### Fixed

- [#446](https://github.com/CCSDForge/episciences/issues/446) and [#448](https://github.com/CCSDForge/episciences/issues/448): Paper list drops some spaces in abstracts.
- [#450](https://github.com/CCSDForge/episciences/issues/450): Fixed a server configuration issue for Theoretics

### Changed

- [#450](https://github.com/CCSDForge/episciences/issues/450): Open the target of the journal's logo in the same page/tab

## v1.0.42.2 - 2024-03-19

### Fixed

- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers:
  Make sure the invitation is sent x days [delay] later.
- Wrong display of titles and abstracts on the latest articles page: these will now be displayed in the main language [if available].

## v1.0.42.1 - 2024-03-12

### Changed

- delete a volume: add assigned papers error's message.
- Sorted paper by volume: include assigned papers to this one as a secondary volume
- [#445](https://github.com/CCSDForge/episciences/issues/445): do not limit the number of papers per page to 10: the default limit (100) is used + acceptance date descending order.

### Fixed

- Exporting volumes in DOAJ format does not work for Secondary Volumes
- [#423](https://github.com/CCSDForge/episciences/issues/423): fix typo Technical support Email.
- [#443](https://github.com/CCSDForge/episciences/issues/443): In the templates (New version - author and co-author copy, New temporary version author and co-author copy) the tag %%PERMANENT_ARTICLE_ID%% is used, but it did not appear in the subject of the message.
- [#443](https://github.com/CCSDForge/episciences/issues/443): no content for tags %%TAG_AUTHORS_NAMES%% and %%TAG_CONTRIBUTOR_FULL_NAME%%.
- The guest editor could not see the cover letter for version 2 and the reviewers' names (Revues/Reviews section) on the first version.
- Links to documentation : the "About version numbers" link url has changed and has not been updated.
- Browse by year: documents have been indexed by year of submission instead of year of publication

## v1.0.42 - 2024-02-15

### Added

- Added an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings. The feature is also handled by the Episciences API

### Fixed

- Submission form validation fails when replacing existing version.

## v1.0.41.7 - 2024-02-12

### Fixed

- [Bug Report] Browse by authors or date: wrong links on the titles #435
- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers.
- Fixed ORCID max length of user profiles

### Changed

- Invite a new user form: make it possible to choose between two languages, even for uni-lingual sites & label renamed to 'Default Language'.

## v1.0.41.7 - 2024-01-23

### Changed

- [COAR Notify] processing of new versions + enhancements
- Improvements on the indexing process with Apache Solr

### Fixed

- Script ZBJATS : Skip documents which are not articles with a PDF, such as datasets
- Application error: Argument 1 passed to Episciences_Volume::setTitles() must be of the type array or null, string give.
- "false positive" for missing translations in logs.
- [COAR Notify] when processing several submission at the same time, notification of submissions were sent to wrong recipients
- [Acceptance date] During the publication process, if the article is accepted with a temporary version, then its acceptance date is that of the 1st temporary version. The problem did not exist on published articles.
- Citations of published papers: Improvement in obtaining the source journal from openAlex's response

## v1.0.41.6 - 2024-01-15

### Fixed

- PHP Notice: FAILED_TO_COPY_FILE_ERROR

## v1.0.41.5 - 2024-01-11

### Fixed

- Typo [423](https://github.com/CCSDForge/episciences/issues/423)
- Crossref export of bibliographical references: fixed unescaped HTML, added a log for XML errors
- Fix #417 Identifiers other than SWHID for Software solutions: remove domain prefixing SWH for all software, add the right domain for software (other than SWH), add correct domain for software in TEI

## v1.0.46.1 - 2024-11-27

### Fixed

- In some cases, the translated 'name' and translated 'subject' of the email template may be lost when they are modified

## v1.0.46 - 2024-11-25

### Removed

- [#625](https://github.com/CCSDForge/episciences/issues/625): 'contact tech support' field has been removed (not used).

### Fixed

- Citations are sometimes not retrieved
- Linked Datasets
    - fixed display of swhids for snapshots + revisions
    - fixed delete button not working with multiple links in same category
    - fixed no confirmation message in confirmation modal
    - fixed duplicate HTML id elements
    - fixed style of forms, use bootstrap
    - used vanilla JS and fetch API instead of Jquery and xmlhttprequest to delete links
    - added missing translations
- Authors affiliation management: use bootstrap style for select
- Update dependency geoip2/geoip2 to v3: todo: downloading databases: https://github.com/P3TERX/GeoLite.mmdb/releases/tag/2024.07.13
- [#506](https://github.com/CCSDForge/episciences/issues/506): Fixed wrong photo displayed when switching profiles.
- Fixed missing volume title when updating metadata from an Episciences journal to HAL.
- [#555](https://github.com/CCSDForge/episciences/issues/555): improved conflict of interest explanations and translations
- Users who have already validated their profile on one of the journals can connect to another without having to validate their profile on that journal (even if they don't have one)
  [#567](https://github.com/CCSDForge/episciences/issues/567): random message recipients.

### Added

- [#513](https://github.com/CCSDForge/episciences/issues/513): Periodic Reminder E-Mails to Editors | editors-in-chief for 'Reviewed' and 'Submitted' articles
- Metadata Export to JSON: include article's citations.
- Created an Opaque UID for users using UUID
    - Profile photo is now stored according to the user's UUID.
    - Script to generate user's UUID.
- New settings for the journals:
    - 'Publisher'
    - 'Publisher location'
    - The values are exported to OpenAIRE and the DOAJ (not supported by Crossref)
- Button to be redirected to the Episciences bibliographic extraction application to import bibtex
- Using COAR Notify with HAL and Episciences to notify HAL of metadata updates for published articles.
- Added in ZBJATS: export bibliographical references from bibtex import
- Added button to redirect to Episciences citation management software without extracting form PDF
- [#418](https://github.com/CCSDForge/episciences/issues/418) Enable edition of relationship and type for the linked data
- Public representation of a document in JSON format (@see /json?v=2)
- [#475](https://github.com/CCSDForge/episciences/issues/475) feature to add biography to profile
- [#476](https://github.com/CCSDForge/episciences/issues/476) feature to add years in volumes forms
- Functionality for migrating news to the new sql news table
- Tinymce toolbar: make headings available
- New field in volume form (volume number)
- [#534](https://github.com/CCSDForge/episciences/issues/534) feature graphical abstract
- Automatic discovery of JEL Classification for published articles, with OpenAIREAPI service
- Automatic discovery of MSC2020 Classification for published articles, with zbMATH Open API service
- Automatic discovery of Reviews on published articles, with zbMATH Open API service

### Changed

- UI/UX: improvements to the editing form and the creation of reminders
- Bump tinymce to latest version (7.2.0)
- Crossref export:
    - For conference proceedings the title and original_language_title elements have been removed from proceedings_metadata: they are not authorised here
    - ROR added to the schema (did not work on the previous version)
- classification of keywords by language
- [#241](https://github.com/CCSDForge/episciences/issues/241) show users roles in mailing popup
- [#531](https://github.com/CCSDForge/episciences/issues/531) enhances volumes form translations

## v1.0.45.2 - 2024-10-22

## Fixed

- [#607](https://github.com/CCSDForge/episciences/issues/607):
    - fixed: the two tags '%COMMENT%%' and '%%ANSWER%%' have identical content.
    - improvement: the attachment with the original comment is now attached to the reply.

### Changed

- [#564](https://github.com/CCSDForge/episciences/issues/564): Fixed and changed French label in journal settings
- [#547](https://github.com/CCSDForge/episciences/issues/547): Ask for other editors opinion: select | deselect all option.
- COAR notifications from HAL: resubmit the same version as a new version.

## v1.0.45.1 - 2024-09-18

- [#585](https://github.com/CCSDForge/episciences/issues/585): The history of manually sent emails is not visible when the "Conflict of Interest" option is enabled.

## v1.0.45 - 2024-09-16

### Added

- [#580](https://github.com/CCSDForge/episciences/issues/580) zbMATH Open export: Authors: add affiliations and ORCIDs

### Fixed

- Volume names are not translated in the volume drop-down list on the reviewing grids list page.
- [#543](https://github.com/CCSDForge/episciences/issues/543): Not translate select options
- [#546](https://github.com/CCSDForge/episciences/issues/546): Editors with different local default date formats cannot update the revision deadline.
- [#571](https://github.com/CCSDForge/episciences/issues/571): The number of days before the review deadline was calculated incorrectly (one day difference).

### Changed

- Ability to block the automatic transfer of a submission from HAL

## v1.0.44.3 - 2024-07-23

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): Completing label updates, fixed label starting with a character outside the [a-z] range;

## v1.0.44.2 - 2024-06-24

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): update the label 'Reviewed' to 'Reviewed pending editorial decision';

## v1.0.44.2 - 2024-06-24

### Changed

- [512](https://github.com/CCSDForge/episciences/issues/512): is now possible to introduce a revision deadline at a later date if the publisher has not indicated a revision deadline (optional) when sending the request for revision.

### Fixed

- [#511](https://github.com/CCSDForge/episciences/issues/511) Do not filter out comments with no message when getting the suggestions form the comment manager.
- Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#507](https://github.com/CCSDForge/episciences/issues/507): Can't change URLs / HTML in email templates
- [#511](https://github.com/CCSDForge/episciences/issues/511):Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#508](https://github.com/CCSDForge/episciences/issues/508): Submission notification parameter: Only the selected roles will receive the notification, If no option is checked, the notification is sent to everyone.
- [#509](https://github.com/CCSDForge/episciences/issues/509): Pixelated photos (Editorial Staff members page)
- Fixed missing spaces with volumes and section on references for arXiv

## v1.0.44.1 - 2024-05-21

### Fixed "Uncaught TypeError: in_array(): Argument #2 ($haystack) must be of type array, null given."

## v1.0.44 - 2024-05-21

### Fixed

- Fixed a bug related to temporary versions with Zenodo:
    - Unable to enter the identifier when submitting a new version
    - submitting a new version results in an error message "the version of the article to be updated must be greater than the previous version".
- [#472](https://github.com/CCSDForge/episciences/issues/472)[Bug Report] English translation missing: Proposer dans le volume #472
- Duplicated submission when automatically transferring a new version from HAL.

### Added

- Add the latest publications to any page by inserting '<div id="widget-browse-latest"></div>' to the HTML source code of a page (same content as /browse/latest page)

## v1.0.43.1 - 2024-05-02

### Fixed

- Reallocation of a previously allocated doi.
- Resetting the position of a paper in a volume will not work if the paper is removed from the volume.
- [#469](https://github.com/CCSDForge/episciences/issues/469) [Bug Report] Add ORCID for an author with apostrophe in the name is broken into two

### Changed

- Allow the assignment of a DOI to a temporarily accepted article
- Automatic numbering of bibliographical references, increased padding between references

## v1.0.43 - 2024-04-17

### Changed

- Updated application's requirements to PHP 8.1.\* . All application dependencies were updated accordingly
- Section's translations are now stored in database instead of server files
- DOI Content Negotiation support for linked data automatically obtained when submitting or updating metadata + code refactoring
- Crossref Export: the unstructured reference is added with the DOI. Previously it was either DOI or the unstructured

### Fixed

- Scholexplorer API usage: The automated script to discover related datasets to published articles now only handle Datasets

### Added

- [#431](https://github.com/CCSDForge/episciences/issues/431): A link from the peer review result page to the article's administration page.
- Using Citation Style Language and APIs we now retrieve and display the text of the references added for datasets, software, HAL Ids, SWHID, arXiv Ids)
- two new statistical indicators ( Submission-acceptance time, Submission-publication time) to the "At a glance" section.
- A new button as a shortcut access the Episciences bibliographic extraction application to import bibtex
- If the metadata of an Episciences publication hosted on HAL is updated, we now send a COAR notification to HAL to trigger an update of the metadata on HAL.
- ZBJATS export: bibliographical references from bibtex import are now also supported and exported

## v1.0.42.5 - 2024-04-17

### Fixed

- [#457](https://github.com/CCSDForge/episciences/issues/457): DOAJ fullText record gives an url that can't be accessed.
- SignPosting headers: fixed missing doi.org domain prefix for DOIs

### Changed

- Taking into account the new value (3) after changing the authorised values in the user CAS table.

## v1.0.42.4 - 2024-03-27

### Fixed

- [#453](https://github.com/CCSDForge/episciences/issues/453): Show a more prominent error when there is a CSRF token error after the editor's comment has been sent.
- When a new version is transferred form HAL, the DOI assigned to the previous version is not restored in the new document.
- [#449](https://github.com/CCSDForge/episciences/issues/449): related to [#169](https://github.com/CCSDForge/episciences/issues/169): the author does not see the review (visible to the administrator but not to the author) on a non-editable version.
- [#458](https://github.com/CCSDForge/episciences/issues/458) [Bug Report] Bibtex export volume name is malformed

## v1.0.42.3 - 2024-03-20

### Fixed

- [#446](https://github.com/CCSDForge/episciences/issues/446) and [#448](https://github.com/CCSDForge/episciences/issues/448): Paper list drops some spaces in abstracts.
- [#450](https://github.com/CCSDForge/episciences/issues/450): Fixed a server configuration issue for Theoretics

### Changed

- [#450](https://github.com/CCSDForge/episciences/issues/450): Open the target of the journal's logo in the same page/tab

## v1.0.42.2 - 2024-03-19

### Fixed

- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers:
  Make sure the invitation is sent x days [delay] later.
- Wrong display of titles and abstracts on the latest articles page: these will now be displayed in the main language [if available].

## v1.0.42.1 - 2024-03-12

### Changed

- delete a volume: add assigned papers error's message.
- Sorted paper by volume: include assigned papers to this one as a secondary volume
- [#445](https://github.com/CCSDForge/episciences/issues/445): do not limit the number of papers per page to 10: the default limit (100) is used + acceptance date descending order.

### Fixed

- Exporting volumes in DOAJ format does not work for Secondary Volumes
- [#423](https://github.com/CCSDForge/episciences/issues/423): fix typo Technical support Email.
- [#443](https://github.com/CCSDForge/episciences/issues/443): In the templates (New version - author and co-author copy, New temporary version author and co-author copy) the tag %%PERMANENT_ARTICLE_ID%% is used, but it did not appear in the subject of the message.
- [#443](https://github.com/CCSDForge/episciences/issues/443): no content for tags %%TAG_AUTHORS_NAMES%% and %%TAG_CONTRIBUTOR_FULL_NAME%%.
- The guest editor could not see the cover letter for version 2 and the reviewers' names (Revues/Reviews section) on the first version.
- Links to documentation : the "About version numbers" link url has changed and has not been updated.
- Browse by year: documents have been indexed by year of submission instead of year of publication

## v1.0.42 - 2024-02-15

### Added

- Added an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings. The feature is also handled by the Episciences API

### Fixed

- Submission form validation fails when replacing existing version.

## v1.0.41.7 - 2024-02-12

### Fixed

- [Bug Report] Browse by authors or date: wrong links on the titles #435
- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers.
- Fixed ORCID max length of user profiles

### Changed

- Invite a new user form: make it possible to choose between two languages, even for uni-lingual sites & label renamed to 'Default Language'.

## v1.0.41.7 - 2024-01-23

### Changed

- [COAR Notify] processing of new versions + enhancements
- Improvements on the indexing process with Apache Solr

### Fixed

- Script ZBJATS : Skip documents which are not articles with a PDF, such as datasets
- Application error: Argument 1 passed to Episciences_Volume::setTitles() must be of the type array or null, string give.
- "false positive" for missing translations in logs.
- [COAR Notify] when processing several submission at the same time, notification of submissions were sent to wrong recipients
- [Acceptance date] During the publication process, if the article is accepted with a temporary version, then its acceptance date is that of the 1st temporary version. The problem did not exist on published articles.
- Citations of published papers: Improvement in obtaining the source journal from openAlex's response

## v1.0.41.6 - 2024-01-15

### Fixed

- PHP Notice: FAILED_TO_COPY_FILE_ERROR

## v1.0.41.5 - 2024-01-11

### Fixed

- Typo [423](https://github.com/CCSDForge/episciences/issues/423)
- Crossref export of bibliographical references: fixed unescaped HTML, added a log for XML errors
- Fix #417 Identifiers other than SWHID for Software solutions: remove domain prefixing SWH for all software, add the right domain for software (other than SWH), add correct domain for software in TEI

## v1.0.46.1 - 2024-11-27

### Fixed

- In some cases, the translated 'name' and translated 'subject' of the email template may be lost when they are modified

## v1.0.46 - 2024-11-25

### Removed

- [#625](https://github.com/CCSDForge/episciences/issues/625): 'contact tech support' field has been removed (not used).

### Fixed

- Citations are sometimes not retrieved
- Linked Datasets
    - fixed display of swhids for snapshots + revisions
    - fixed delete button not working with multiple links in same category
    - fixed no confirmation message in confirmation modal
    - fixed duplicate HTML id elements
    - fixed style of forms, use bootstrap
    - used vanilla JS and fetch API instead of Jquery and xmlhttprequest to delete links
    - added missing translations
- Authors affiliation management: use bootstrap style for select
- Update dependency geoip2/geoip2 to v3: todo: downloading databases: https://github.com/P3TERX/GeoLite.mmdb/releases/tag/2024.07.13
- [#506](https://github.com/CCSDForge/episciences/issues/506): Fixed wrong photo displayed when switching profiles.
- Fixed missing volume title when updating metadata from an Episciences journal to HAL.
- [#555](https://github.com/CCSDForge/episciences/issues/555): improved conflict of interest explanations and translations
- Users who have already validated their profile on one of the journals can connect to another without having to validate their profile on that journal (even if they don't have one)
  [#567](https://github.com/CCSDForge/episciences/issues/567): random message recipients.

### Added

- [#513](https://github.com/CCSDForge/episciences/issues/513): Periodic Reminder E-Mails to Editors | editors-in-chief for 'Reviewed' and 'Submitted' articles
- Metadata Export to JSON: include article's citations.
- Created an Opaque UID for users using UUID
    - Profile photo is now stored according to the user's UUID.
    - Script to generate user's UUID.
- New settings for the journals:
    - 'Publisher'
    - 'Publisher location'
    - The values are exported to OpenAIRE and the DOAJ (not supported by Crossref)
- Button to be redirected to the Episciences bibliographic extraction application to import bibtex
- Using COAR Notify with HAL and Episciences to notify HAL of metadata updates for published articles.
- Added in ZBJATS: export bibliographical references from bibtex import
- Added button to redirect to Episciences citation management software without extracting form PDF
- [#418](https://github.com/CCSDForge/episciences/issues/418) Enable edition of relationship and type for the linked data
- Public representation of a document in JSON format (@see /json?v=2)
- [#475](https://github.com/CCSDForge/episciences/issues/475) feature to add biography to profile
- [#476](https://github.com/CCSDForge/episciences/issues/476) feature to add years in volumes forms
- Functionality for migrating news to the new sql news table
- Tinymce toolbar: make headings available
- New field in volume form (volume number)
- [#534](https://github.com/CCSDForge/episciences/issues/534) feature graphical abstract
- Automatic discovery of JEL Classification for published articles, with OpenAIREAPI service
- Automatic discovery of MSC2020 Classification for published articles, with zbMATH Open API service
- Automatic discovery of Reviews on published articles, with zbMATH Open API service

### Changed

- UI/UX: improvements to the editing form and the creation of reminders
- Bump tinymce to latest version (7.2.0)
- Crossref export:
    - For conference proceedings the title and original_language_title elements have been removed from proceedings_metadata: they are not authorised here
    - ROR added to the schema (did not work on the previous version)
- classification of keywords by language
- [#241](https://github.com/CCSDForge/episciences/issues/241) show users roles in mailing popup
- [#531](https://github.com/CCSDForge/episciences/issues/531) enhances volumes form translations

## v1.0.45.2 - 2024-10-22

## Fixed

- [#607](https://github.com/CCSDForge/episciences/issues/607):
    - fixed: the two tags '%COMMENT%%' and '%%ANSWER%%' have identical content.
    - improvement: the attachment with the original comment is now attached to the reply.

### Changed

- [#564](https://github.com/CCSDForge/episciences/issues/564): Fixed and changed French label in journal settings
- [#547](https://github.com/CCSDForge/episciences/issues/547): Ask for other editors opinion: select | deselect all option.
- COAR notifications from HAL: resubmit the same version as a new version.

## v1.0.45.1 - 2024-09-18

- [#585](https://github.com/CCSDForge/episciences/issues/585): The history of manually sent emails is not visible when the "Conflict of Interest" option is enabled.

## v1.0.45 - 2024-09-16

### Added

- [#580](https://github.com/CCSDForge/episciences/issues/580) zbMATH Open export: Authors: add affiliations and ORCIDs

### Fixed

- Volume names are not translated in the volume drop-down list on the reviewing grids list page.
- [#543](https://github.com/CCSDForge/episciences/issues/543): Not translate select options
- [#546](https://github.com/CCSDForge/episciences/issues/546): Editors with different local default date formats cannot update the revision deadline.
- [#571](https://github.com/CCSDForge/episciences/issues/571): The number of days before the review deadline was calculated incorrectly (one day difference).

### Changed

- Ability to block the automatic transfer of a submission from HAL

## v1.0.44.3 - 2024-07-23

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): Completing label updates, fixed label starting with a character outside the [a-z] range;

## v1.0.44.2 - 2024-06-24

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): update the label 'Reviewed' to 'Reviewed pending editorial decision';

## v1.0.44.2 - 2024-06-24

### Changed

- [512](https://github.com/CCSDForge/episciences/issues/512): is now possible to introduce a revision deadline at a later date if the publisher has not indicated a revision deadline (optional) when sending the request for revision.

### Fixed

- [#511](https://github.com/CCSDForge/episciences/issues/511) Do not filter out comments with no message when getting the suggestions form the comment manager.
- Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#507](https://github.com/CCSDForge/episciences/issues/507): Can't change URLs / HTML in email templates
- [#511](https://github.com/CCSDForge/episciences/issues/511):Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#508](https://github.com/CCSDForge/episciences/issues/508): Submission notification parameter: Only the selected roles will receive the notification, If no option is checked, the notification is sent to everyone.
- [#509](https://github.com/CCSDForge/episciences/issues/509): Pixelated photos (Editorial Staff members page)
- Fixed missing spaces with volumes and section on references for arXiv

## v1.0.44.1 - 2024-05-21

### Fixed "Uncaught TypeError: in_array(): Argument #2 ($haystack) must be of type array, null given."

## v1.0.44 - 2024-05-21

### Fixed

- Fixed a bug related to temporary versions with Zenodo:
    - Unable to enter the identifier when submitting a new version
    - submitting a new version results in an error message "the version of the article to be updated must be greater than the previous version".
- [#472](https://github.com/CCSDForge/episciences/issues/472)[Bug Report] English translation missing: Proposer dans le volume #472
- Duplicated submission when automatically transferring a new version from HAL.

### Added

- Add the latest publications to any page by inserting '<div id="widget-browse-latest"></div>' to the HTML source code of a page (same content as /browse/latest page)

## v1.0.43.1 - 2024-05-02

### Fixed

- Reallocation of a previously allocated doi.
- Resetting the position of a paper in a volume will not work if the paper is removed from the volume.
- [#469](https://github.com/CCSDForge/episciences/issues/469) [Bug Report] Add ORCID for an author with apostrophe in the name is broken into two

### Changed

- Allow the assignment of a DOI to a temporarily accepted article
- Automatic numbering of bibliographical references, increased padding between references

## v1.0.43 - 2024-04-17

### Changed

- Updated application's requirements to PHP 8.1.\* . All application dependencies were updated accordingly
- Section's translations are now stored in database instead of server files
- DOI Content Negotiation support for linked data automatically obtained when submitting or updating metadata + code refactoring
- Crossref Export: the unstructured reference is added with the DOI. Previously it was either DOI or the unstructured

### Fixed

- Scholexplorer API usage: The automated script to discover related datasets to published articles now only handle Datasets

### Added

- [#431](https://github.com/CCSDForge/episciences/issues/431): A link from the peer review result page to the article's administration page.
- Using Citation Style Language and APIs we now retrieve and display the text of the references added for datasets, software, HAL Ids, SWHID, arXiv Ids)
- two new statistical indicators ( Submission-acceptance time, Submission-publication time) to the "At a glance" section.
- A new button as a shortcut access the Episciences bibliographic extraction application to import bibtex
- If the metadata of an Episciences publication hosted on HAL is updated, we now send a COAR notification to HAL to trigger an update of the metadata on HAL.
- ZBJATS export: bibliographical references from bibtex import are now also supported and exported

## v1.0.42.5 - 2024-04-17

### Fixed

- [#457](https://github.com/CCSDForge/episciences/issues/457): DOAJ fullText record gives an url that can't be accessed.
- SignPosting headers: fixed missing doi.org domain prefix for DOIs

### Changed

- Taking into account the new value (3) after changing the authorised values in the user CAS table.

## v1.0.42.4 - 2024-03-27

### Fixed

- [#453](https://github.com/CCSDForge/episciences/issues/453): Show a more prominent error when there is a CSRF token error after the editor's comment has been sent.
- When a new version is transferred form HAL, the DOI assigned to the previous version is not restored in the new document.
- [#449](https://github.com/CCSDForge/episciences/issues/449): related to [#169](https://github.com/CCSDForge/episciences/issues/169): the author does not see the review (visible to the administrator but not to the author) on a non-editable version.
- [#458](https://github.com/CCSDForge/episciences/issues/458) [Bug Report] Bibtex export volume name is malformed

## v1.0.42.3 - 2024-03-20

### Fixed

- [#446](https://github.com/CCSDForge/episciences/issues/446) and [#448](https://github.com/CCSDForge/episciences/issues/448): Paper list drops some spaces in abstracts.
- [#450](https://github.com/CCSDForge/episciences/issues/450): Fixed a server configuration issue for Theoretics

### Changed

- [#450](https://github.com/CCSDForge/episciences/issues/450): Open the target of the journal's logo in the same page/tab

## v1.0.42.2 - 2024-03-19

### Fixed

- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers:
  Make sure the invitation is sent x days [delay] later.
- Wrong display of titles and abstracts on the latest articles page: these will now be displayed in the main language [if available].

## v1.0.42.1 - 2024-03-12

### Changed

- delete a volume: add assigned papers error's message.
- Sorted paper by volume: include assigned papers to this one as a secondary volume
- [#445](https://github.com/CCSDForge/episciences/issues/445): do not limit the number of papers per page to 10: the default limit (100) is used + acceptance date descending order.

### Fixed

- Exporting volumes in DOAJ format does not work for Secondary Volumes
- [#423](https://github.com/CCSDForge/episciences/issues/423): fix typo Technical support Email.
- [#443](https://github.com/CCSDForge/episciences/issues/443): In the templates (New version - author and co-author copy, New temporary version author and co-author copy) the tag %%PERMANENT_ARTICLE_ID%% is used, but it did not appear in the subject of the message.
- [#443](https://github.com/CCSDForge/episciences/issues/443): no content for tags %%TAG_AUTHORS_NAMES%% and %%TAG_CONTRIBUTOR_FULL_NAME%%.
- The guest editor could not see the cover letter for version 2 and the reviewers' names (Revues/Reviews section) on the first version.
- Links to documentation : the "About version numbers" link url has changed and has not been updated.
- Browse by year: documents have been indexed by year of submission instead of year of publication

## v1.0.42 - 2024-02-15

### Added

- Added an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings. The feature is also handled by the Episciences API

### Fixed

- Submission form validation fails when replacing existing version.

## v1.0.41.7 - 2024-02-12

### Fixed

- [Bug Report] Browse by authors or date: wrong links on the titles #435
- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers.
- Fixed ORCID max length of user profiles

### Changed

- Invite a new user form: make it possible to choose between two languages, even for uni-lingual sites & label renamed to 'Default Language'.

## v1.0.41.7 - 2024-01-23

### Changed

- [COAR Notify] processing of new versions + enhancements
- Improvements on the indexing process with Apache Solr

### Fixed

- Script ZBJATS : Skip documents which are not articles with a PDF, such as datasets
- Application error: Argument 1 passed to Episciences_Volume::setTitles() must be of the type array or null, string give.
- "false positive" for missing translations in logs.
- [COAR Notify] when processing several submission at the same time, notification of submissions were sent to wrong recipients
- [Acceptance date] During the publication process, if the article is accepted with a temporary version, then its acceptance date is that of the 1st temporary version. The problem did not exist on published articles.
- Citations of published papers: Improvement in obtaining the source journal from openAlex's response

## v1.0.41.6 - 2024-01-15

### Fixed

- PHP Notice: FAILED_TO_COPY_FILE_ERROR

## v1.0.41.5 - 2024-01-11

### Fixed

- Typo [423](https://github.com/CCSDForge/episciences/issues/423)
- Crossref export of bibliographical references: fixed unescaped HTML, added a log for XML errors
- Fix #417 Identifiers other than SWHID for Software solutions: remove domain prefixing SWH for all software, add the right domain for software (other than SWH), add correct domain for software in TEI

## v1.0.46.1 - 2024-11-27

### Fixed

- In some cases, the translated 'name' and translated 'subject' of the email template may be lost when they are modified

## v1.0.46 - 2024-11-25

### Removed

- [#625](https://github.com/CCSDForge/episciences/issues/625): 'contact tech support' field has been removed (not used).

### Fixed

- Citations are sometimes not retrieved
- Linked Datasets
    - fixed display of swhids for snapshots + revisions
    - fixed delete button not working with multiple links in same category
    - fixed no confirmation message in confirmation modal
    - fixed duplicate HTML id elements
    - fixed style of forms, use bootstrap
    - used vanilla JS and fetch API instead of Jquery and xmlhttprequest to delete links
    - added missing translations
- Authors affiliation management: use bootstrap style for select
- Update dependency geoip2/geoip2 to v3: todo: downloading databases: https://github.com/P3TERX/GeoLite.mmdb/releases/tag/2024.07.13
- [#506](https://github.com/CCSDForge/episciences/issues/506): Fixed wrong photo displayed when switching profiles.
- Fixed missing volume title when updating metadata from an Episciences journal to HAL.
- [#555](https://github.com/CCSDForge/episciences/issues/555): improved conflict of interest explanations and translations
- Users who have already validated their profile on one of the journals can connect to another without having to validate their profile on that journal (even if they don't have one)
  [#567](https://github.com/CCSDForge/episciences/issues/567): random message recipients.

### Added

- [#513](https://github.com/CCSDForge/episciences/issues/513): Periodic Reminder E-Mails to Editors | editors-in-chief for 'Reviewed' and 'Submitted' articles
- Metadata Export to JSON: include article's citations.
- Created an Opaque UID for users using UUID
    - Profile photo is now stored according to the user's UUID.
    - Script to generate user's UUID.
- New settings for the journals:
    - 'Publisher'
    - 'Publisher location'
    - The values are exported to OpenAIRE and the DOAJ (not supported by Crossref)
- Button to be redirected to the Episciences bibliographic extraction application to import bibtex
- Using COAR Notify with HAL and Episciences to notify HAL of metadata updates for published articles.
- Added in ZBJATS: export bibliographical references from bibtex import
- Added button to redirect to Episciences citation management software without extracting form PDF
- [#418](https://github.com/CCSDForge/episciences/issues/418) Enable edition of relationship and type for the linked data
- Public representation of a document in JSON format (@see /json?v=2)
- [#475](https://github.com/CCSDForge/episciences/issues/475) feature to add biography to profile
- [#476](https://github.com/CCSDForge/episciences/issues/476) feature to add years in volumes forms
- Functionality for migrating news to the new sql news table
- Tinymce toolbar: make headings available
- New field in volume form (volume number)
- [#534](https://github.com/CCSDForge/episciences/issues/534) feature graphical abstract
- Automatic discovery of JEL Classification for published articles, with OpenAIREAPI service
- Automatic discovery of MSC2020 Classification for published articles, with zbMATH Open API service
- Automatic discovery of Reviews on published articles, with zbMATH Open API service

### Changed

- UI/UX: improvements to the editing form and the creation of reminders
- Bump tinymce to latest version (7.2.0)
- Crossref export:
    - For conference proceedings the title and original_language_title elements have been removed from proceedings_metadata: they are not authorised here
    - ROR added to the schema (did not work on the previous version)
- classification of keywords by language
- [#241](https://github.com/CCSDForge/episciences/issues/241) show users roles in mailing popup
- [#531](https://github.com/CCSDForge/episciences/issues/531) enhances volumes form translations

## v1.0.45.2 - 2024-10-22

## Fixed

- [#607](https://github.com/CCSDForge/episciences/issues/607):
    - fixed: the two tags '%COMMENT%%' and '%%ANSWER%%' have identical content.
    - improvement: the attachment with the original comment is now attached to the reply.

### Changed

- [#564](https://github.com/CCSDForge/episciences/issues/564): Fixed and changed French label in journal settings
- [#547](https://github.com/CCSDForge/episciences/issues/547): Ask for other editors opinion: select | deselect all option.
- COAR notifications from HAL: resubmit the same version as a new version.

## v1.0.45.1 - 2024-09-18

- [#585](https://github.com/CCSDForge/episciences/issues/585): The history of manually sent emails is not visible when the "Conflict of Interest" option is enabled.

## v1.0.45 - 2024-09-16

### Added

- [#580](https://github.com/CCSDForge/episciences/issues/580) zbMATH Open export: Authors: add affiliations and ORCIDs

### Fixed

- Volume names are not translated in the volume drop-down list on the reviewing grids list page.
- [#543](https://github.com/CCSDForge/episciences/issues/543): Not translate select options
- [#546](https://github.com/CCSDForge/episciences/issues/546): Editors with different local default date formats cannot update the revision deadline.
- [#571](https://github.com/CCSDForge/episciences/issues/571): The number of days before the review deadline was calculated incorrectly (one day difference).

### Changed

- Ability to block the automatic transfer of a submission from HAL

## v1.0.44.3 - 2024-07-23

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): Completing label updates, fixed label starting with a character outside the [a-z] range;

## v1.0.44.2 - 2024-06-24

### Changed

- [#510](https://github.com/CCSDForge/episciences/issues/510): update the label 'Reviewed' to 'Reviewed pending editorial decision';

## v1.0.44.2 - 2024-06-24

### Changed

- [512](https://github.com/CCSDForge/episciences/issues/512): is now possible to introduce a revision deadline at a later date if the publisher has not indicated a revision deadline (optional) when sending the request for revision.

### Fixed

- [#511](https://github.com/CCSDForge/episciences/issues/511) Do not filter out comments with no message when getting the suggestions form the comment manager.
- Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#507](https://github.com/CCSDForge/episciences/issues/507): Can't change URLs / HTML in email templates
- [#511](https://github.com/CCSDForge/episciences/issues/511):Do not filter out comments with no message when getting the suggestions form the comment manager.
- [#508](https://github.com/CCSDForge/episciences/issues/508): Submission notification parameter: Only the selected roles will receive the notification, If no option is checked, the notification is sent to everyone.
- [#509](https://github.com/CCSDForge/episciences/issues/509): Pixelated photos (Editorial Staff members page)
- Fixed missing spaces with volumes and section on references for arXiv

## v1.0.44.1 - 2024-05-21

### Fixed "Uncaught TypeError: in_array(): Argument #2 ($haystack) must be of type array, null given."

## v1.0.44 - 2024-05-21

### Fixed

- Fixed a bug related to temporary versions with Zenodo:
    - Unable to enter the identifier when submitting a new version
    - submitting a new version results in an error message "the version of the article to be updated must be greater than the previous version".
- [#472](https://github.com/CCSDForge/episciences/issues/472)[Bug Report] English translation missing: Proposer dans le volume #472
- Duplicated submission when automatically transferring a new version from HAL.

### Added

- Add the latest publications to any page by inserting '<div id="widget-browse-latest"></div>' to the HTML source code of a page (same content as /browse/latest page)

## v1.0.43.1 - 2024-05-02

### Fixed

- Reallocation of a previously allocated doi.
- Resetting the position of a paper in a volume will not work if the paper is removed from the volume.
- [#469](https://github.com/CCSDForge/episciences/issues/469) [Bug Report] Add ORCID for an author with apostrophe in the name is broken into two

### Changed

- Allow the assignment of a DOI to a temporarily accepted article
- Automatic numbering of bibliographical references, increased padding between references

## v1.0.43 - 2024-04-17

### Changed

- Updated application's requirements to PHP 8.1.\* . All application dependencies were updated accordingly
- Section's translations are now stored in database instead of server files
- DOI Content Negotiation support for linked data automatically obtained when submitting or updating metadata + code refactoring
- Crossref Export: the unstructured reference is added with the DOI. Previously it was either DOI or the unstructured

### Fixed

- Scholexplorer API usage: The automated script to discover related datasets to published articles now only handle Datasets

### Added

- [#431](https://github.com/CCSDForge/episciences/issues/431): A link from the peer review result page to the article's administration page.
- Using Citation Style Language and APIs we now retrieve and display the text of the references added for datasets, software, HAL Ids, SWHID, arXiv Ids)
- two new statistical indicators ( Submission-acceptance time, Submission-publication time) to the "At a glance" section.
- A new button as a shortcut access the Episciences bibliographic extraction application to import bibtex
- If the metadata of an Episciences publication hosted on HAL is updated, we now send a COAR notification to HAL to trigger an update of the metadata on HAL.
- ZBJATS export: bibliographical references from bibtex import are now also supported and exported

## v1.0.42.5 - 2024-04-17

### Fixed

- [#457](https://github.com/CCSDForge/episciences/issues/457): DOAJ fullText record gives an url that can't be accessed.
- SignPosting headers: fixed missing doi.org domain prefix for DOIs

### Changed

- Taking into account the new value (3) after changing the authorised values in the user CAS table.

## v1.0.42.4 - 2024-03-27

### Fixed

- [#453](https://github.com/CCSDForge/episciences/issues/453): Show a more prominent error when there is a CSRF token error after the editor's comment has been sent.
- When a new version is transferred form HAL, the DOI assigned to the previous version is not restored in the new document.
- [#449](https://github.com/CCSDForge/episciences/issues/449): related to [#169](https://github.com/CCSDForge/episciences/issues/169): the author does not see the review (visible to the administrator but not to the author) on a non-editable version.
- [#458](https://github.com/CCSDForge/episciences/issues/458) [Bug Report] Bibtex export volume name is malformed

## v1.0.42.3 - 2024-03-20

### Fixed

- [#446](https://github.com/CCSDForge/episciences/issues/446) and [#448](https://github.com/CCSDForge/episciences/issues/448): Paper list drops some spaces in abstracts.
- [#450](https://github.com/CCSDForge/episciences/issues/450): Fixed a server configuration issue for Theoretics

### Changed

- [#450](https://github.com/CCSDForge/episciences/issues/450): Open the target of the journal's logo in the same page/tab

## v1.0.42.2 - 2024-03-19

### Fixed

- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers:
  Make sure the invitation is sent x days [delay] later.
- Wrong display of titles and abstracts on the latest articles page: these will now be displayed in the main language [if available].

## v1.0.42.1 - 2024-03-12

### Changed

- delete a volume: add assigned papers error's message.
- Sorted paper by volume: include assigned papers to this one as a secondary volume
- [#445](https://github.com/CCSDForge/episciences/issues/445): do not limit the number of papers per page to 10: the default limit (100) is used + acceptance date descending order.

### Fixed

- Exporting volumes in DOAJ format does not work for Secondary Volumes
- [#423](https://github.com/CCSDForge/episciences/issues/423): fix typo Technical support Email.
- [#443](https://github.com/CCSDForge/episciences/issues/443): In the templates (New version - author and co-author copy, New temporary version author and co-author copy) the tag %%PERMANENT_ARTICLE_ID%% is used, but it did not appear in the subject of the message.
- [#443](https://github.com/CCSDForge/episciences/issues/443): no content for tags %%TAG_AUTHORS_NAMES%% and %%TAG_CONTRIBUTOR_FULL_NAME%%.
- The guest editor could not see the cover letter for version 2 and the reviewers' names (Revues/Reviews section) on the first version.
- Links to documentation : the "About version numbers" link url has changed and has not been updated.
- Browse by year: documents have been indexed by year of submission instead of year of publication

## v1.0.42 - 2024-02-15

### Added

- Added an option to ignore statistics before a given date [434](https://github.com/CCSDForge/episciences/issues/434)
  now, except for users, the total number of submissions is the total number of articles published, indicators only include data after the date configured in the journal's settings. The feature is also handled by the Episciences API

### Fixed

- Submission form validation fails when replacing existing version.

## v1.0.41.7 - 2024-02-12

### Fixed

- [Bug Report] Browse by authors or date: wrong links on the titles #435
- System has immediately send automatic reminders 'Unanswered reviewer invitation' following the invitation emails to reviewers.
- Fixed ORCID max length of user profiles

### Changed

- Invite a new user form: make it possible to choose between two languages, even for uni-lingual sites & label renamed to 'Default Language'.

## v1.0.41.7 - 2024-01-23

### Changed

- [COAR Notify] processing of new versions + enhancements
- Improvements on the indexing process with Apache Solr

### Fixed

- Script ZBJATS : Skip documents which are not articles with a PDF, such as datasets
- Application error: Argument 1 passed to Episciences_Volume::setTitles() must be of the type array or null, string give.
- "false positive" for missing translations in logs.
- [COAR Notify] when processing several submission at the same time, notification of submissions were sent to wrong recipients
- [Acceptance date] During the publication process, if the article is accepted with a temporary version, then its acceptance date is that of the 1st temporary version. The problem did not exist on published articles.
- Citations of published papers: Improvement in obtaining the source journal from openAlex's response

## v1.0.41.6 - 2024-01-15

### Fixed

- PHP Notice: FAILED_TO_COPY_FILE_ERROR

## v1.0.41.5 - 2024-01-11

### Fixed

- Typo [423](https://github.com/CCSDForge/episciences/issues/423)
- Crossref export of bibliographical references: fixed unescaped HTML, added a log for XML errors
- Fix #417 Identifiers other than SWHID for Software solutions: remove domain prefixing SWH for all software, add the right domain for software (other than SWH), add correct domain for software in TEI
