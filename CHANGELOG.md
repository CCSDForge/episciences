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

## Archives

- [2025](changelogs/CHANGELOG-2025.md)
- [2024](changelogs/CHANGELOG-2024.md)
- [2023](changelogs/CHANGELOG-2023.md)
- [2022](changelogs/CHANGELOG-2022.md)
- [2021](changelogs/CHANGELOG-2021.md)

## Unreleased

### Added

- Cache PDF and XML responses with Symfony Cache in `ZbjatsTools` and support new-front URLs.
- [#1125](https://github.com/CCSDForge/episciences/pull/1125) Allow anonymous access to public review report attachments for open peer review.
- Add `CheckDoajJournalsCommand` CLI command to check journal presence in DOAJ, track published article count, and enforce API rate limits.
- Add constraint description helper text to the volume year field in volume management.
- Add TomSelect component to paper filters on `/paper/submitted` and `/paper/ratings`.
- Configure local SonarQube support (`make sonar`) and XML-based PHPUnit coverage report generation.
- [#1140](https://github.com/CCSDForge/episciences/issues/1140) Export secondary volumes under `database.current.secondary_volumes` in the JSON v2 paper export.

### Performances

- Memoise the paper's primary volume on the `Episciences_Paper` instance, so `toJson()`, `getXml()` and `XmlExportManager::xmlExport()` share a single lookup instead of loading it twice: 38 to 34 queries per paper export.
- Stop reloading volume settings from `Episciences_Volume::getProceedingInfo()`; every caller reaches it through `isProceeding()`, which already needs them loaded.
- Load every volume's settings in one query on `/browse/volumes` via `Episciences_VolumesManager::loadSettingsForVolumes()`, instead of one query per volume.

### Fixed

- Restore the article download for every repository that does not mirror its files into `PAPER_FILES` (arXiv, HAL, bioRxiv, medRxiv, ARCHE). `Episciences_Repositories::hasHook()` only reports that a hooks class exists, yet it was read as "this repository's files live in `PAPER_FILES`". Giving arXiv ([#1135](https://github.com/CCSDForge/episciences/pull/1135)) and HAL ([#1137](https://github.com/CCSDForge/episciences/pull/1137)) a metadata-filtering hooks class therefore made `Paper::getMainPaperUrl()` look for a row that never exists, so `/{docid}/pdf` answered `404 no PDF files found`, the "Download the article" button disappeared from the article page, and `database.current.mainPdfUrl` and `files` went empty in the JSON v2 export. bioRxiv, medRxiv and ARCHE had been broken the same way ever since they got a hooks class. Capabilities are now asked for explicitly, through the interfaces the hook classes already declare: `hasFilesEnrichment()`, `hasLinkedDataEnrichment()`, `handlesOwnEnrichment()` and `hasConceptIdentifier()`. The XML node driving the download button is renamed `notHasHook` → `hasMainPaperUrl` after what it actually holds.
- Require a version number again when submitting an arXiv or HAL paper. Their `hookIsRequiredVersion()` returns `[]` to keep the historical default, but callers read `['result']` straight off it, so the version block was hidden in the submission form and skipped by its client-side validation. The default now lives in `Episciences_Repositories::isVersionRequired()`. Also fixes the same lookup on temporary papers, which queried the hook with `repoId = 0` instead of the repository of their first version.
- Run the generic dataset enrichment (`Episciences_Submit::datasetsProcessing()`) again for arXiv and HAL papers, at submission and on metadata refresh: both were silently skipped because they now have a hooks class, even though neither declares any enrichment capability.
- [#1140](https://github.com/CCSDForge/episciences/issues/1140) Refresh the `PAPERS.DOCUMENT` JSON column after a secondary volume change, so API consumers no longer read stale volume data. Papers already in database keep a `DOCUMENT` without the new key until resaved: run `php scripts/console.php papers:update-document` after deploying (API consumers should read the key as `?? null` in the meantime).
- Report `database.current.graphical_abstract_file` as `null` instead of `""` in the JSON v2 paper export when the paper has no graphical abstract, and drop the dead `unset()` that was meant to do it. Consumers must treat both `null` and a missing key as "no graphical abstract".
- [#1125](https://github.com/CCSDForge/episciences/pull/1125) Block review report attachment downloads for users with a declared Conflict of Interest (COI).
- [#1125](https://github.com/CCSDForge/episciences/pull/1125) Ensure paper authors' access precedence over editorial staff COI checks when downloading review report attachments.
- [#1132](https://github.com/CCSDForge/episciences/pull/1132) Pass `previousVersion` context to `hookVersion` in `savenewpostedversionAction` to correctly update Zenodo version identifiers when posting a new version.
- Define missing `RVID` constant in `ZbjatsZipperCommand` CLI bootstrap.
- Allow non-numeric volume numbers (`varchar(6)`) in volume management forms.
- Stream Crossref DOI XML in-memory and scope volume/DOAJ caches under `rvcode`.
- Allow authorized roles to visualize all bibliographic references on unpublished papers.
- Add explicit `string` type hint to `Ccsd_Website_Header::$_langDir` for PHP 8 compatibility.
- Stop `Episciences_Submit::getDoc()` from crashing on a `latestObsoleteDocId` that does not resolve. The value arrives straight from the `/submit/getdoc` POST, so `partialGet()` returns `null` for a stale docid or one belonging to another journal, and the previous paper was dereferenced without a check — `Call to a member function isTmp() on null`. The lookup now degrades to "no previous version", which every downstream check already handles, and the real latest version is still found by `findExistingDocId()`. Same for a temporary paper with no previous version, which indexed an array with `array_key_first([])`. The `addContext()` call feeding the `hookVersion` parameters is guarded too: it takes a non-nullable `Episciences_Paper`, and the `TypeError` it raised is an `Error`, which `getDoc()`'s `catch (Exception)` does not intercept.
- Restore submissions from Cryptology ePrint and DSpace, broken by the `hasConceptIdentifier()` capability check introduced above: that check was scoped to Zenodo's own hooks interface, while Cryptology ePrint's and DSpace's hooks set a concept identifier through `hookApiRecords()` without implementing it. Every submission (or new version) from these two repositories therefore threw `InvalidArgumentException` in `Episciences_Paper::setConcept_identifier()`, caught as a generic error by `Episciences_Submit::getDoc()`. The capability is now a dedicated `ConceptIdentifierInterface`, implemented by all three hook classes.
- Stop `Episciences_Submit::getDoc()` from throwing a `TypeError` when a temporary previous paper has no previous version of its own: `getPreviousVersions()` returns `null` (not `[]`) in that case, and `array_key_first()` requires an array.

### Removed

- Drop the redundant `!$previousPaper->hasHook` guard from `Episciences_Submit::assertDateTimeVersion()`. The branch is selected by the `UPDATE_DATETIME` key, which only ever reaches the result from a repository's `hookApiRecords()`, so the guard added nothing — and it dereferenced a nullable `Episciences_Paper`, raising `Attempt to read property "hasHook" on null` whenever `partialGet()` returned nothing for an existing `docId`. `Episciences_Paper::$hasHook` now has no caller left and is marked `@deprecated`.
- Remove the dead `hasHook` plumbing left behind now that repository capabilities are asked for explicitly: the `hasHook` JavaScript global on the article and submission pages (declared, assigned from `/submit/ajaxhashook`, never read), the `hasHook` view variable of the version-number form, and the `h_hasHook` hidden form element, whose value came from a `$defaults['hasHook']` key that had been commented out. `/submit/ajaxhashook` now answers `isRequiredVersion` only.

### Refactored

- Route `Episciences_Submit::getRepositoriesForm()` and `getNewVersionForm()` through `Episciences_Repositories::isVersionRequired()` instead of calling `callHook('hookIsRequiredVersion', ...)` directly and re-implementing its `[]` → required fallback locally, so the two form-building sites this PR left behind stay in sync with the centralized default.
- [#793](https://github.com/CCSDForge/episciences/issues/793) Add `hookFilterMetadata` repository hook and strip surplus arXiv `dc:description` comment nodes at ingestion.
- Strip HAL's non-abstract `dc:description` markers (`International audience`, `National audience`, `soumission à Episciences`) at ingestion instead of filtering them in every consumer. A new `Episciences_Repositories_HAL_Hooks::hookCleanXMLRecordInput()` removes the nodes from the raw OAI record before it reaches `PAPERS.RECORD`, so the two independent render paths — `Paper::getMetadata()` and `Paper::getXslt()` → `public/xsl/*.xsl` — stay consistent on their own. Removes the eight downstream workarounds in `Paper::getAbstractsCleaned()`, `Paper_Export`, the DataCite and zbJATS exports and the three paper XSLTs, and fixes the TEI export, which never filtered the marker at all. Matching is content-based and case-insensitive on normalized whitespace, so a real (possibly multilingual) abstract can never be dropped. `National audience` was never filtered anywhere and was displayed as an abstract; it is now handled with the others. Existing rows are migrated by the new `papers:clean-hal-descriptions` command (`--dry-run`, `--update-document`, `--no-reindex`).
- Remove static `Ccsd_Auth` dependencies to improve testability.
- Simplify portal module and remove dead code.

## v1.0.56.1 - 2026-08-04

### Added

- [CLOCKSS] Additional article URLs, published ahead of the actual switch to ease harvesting during the transition period: `/articles/{paperid}` and `/articles/{paperid}/download`, both also accepting an optional `en`, `fr` or `es` language prefix (e.g. `/en/articles/{paperid}`). They are addressed by paper id — the canonical reference, as used by the new interfaces — and answer `200` with the published version, without redirecting to its docid: `Episciences_ArticleAlias_Plugin` resolves the paper id at routing time. A docid is still accepted, and the historical `/{docid}` and `/{docid}/pdf` URLs are unchanged.
- [#1011](https://github.com/CCSDForge/episciences/issues/1011) Redesign the "Manage the journal" dashboard panel as a four-quadrant grid (evaluation, revisions & suggestions, copy-editing & publication, archives), with a compact paper search box moved into each panel header.
- [#1011](https://github.com/CCSDForge/episciences/issues/1011) Add a "decision suggestion" filter (acceptance / refusal / revision) to the paper list, and show the number of papers with a pending suggestion on the dashboard. A suggestion stops being counted as pending once the editor in chief has ruled, whether by accepting the paper or by requesting revisions. The filter is restricted to users allowed to manage papers.

### Fixed

- Allow paper authors to download review report attachments after an editorial decision by aligning authorization checks in `FileController::reportAction()`.
- Fix XML export corruption during Solr indexing by checking `isRegistered()` in `AppRegistry::getMonoLogger()` instead of catching exceptions.
- Fix `TypeError` in `ZbjatsTools` and `BiblioRefApiClient` by normalizing CSL response parsing to accept both array and JSON-encoded string formats from the bibliographic reference API.
- [#1118](https://github.com/CCSDForge/episciences/issues/1118) Add missing French and English translations for intra-work relationship types and group headers in linked data forms.

### Changed

- Update dependencies.

## v1.0.56 - 2026-07-16

### Added
- [CLOCKSS] Include the CLOCKSS Permissions Statement to allow archiving and ingestion of open access Archival Units.
- [Bibliographic References] Display open-access links (retrieved e.g. from OpenAlex) when available and distinct from the DOI link.
- [OpenAlex] Support configuring and appending an `api_key` query parameter to OpenAlex API requests when the `OPENALEX_APIKEY` constant is defined.
- For submissions containing multiple PDF files, it is now possible to designate the main file. This is the file that will appear in previews on public websites.

- Migration of captcha system (hCaptcha/reCAPTCHA) to a self-hosted, privacy-friendly solution: **ALTCHA** (utilizing the Argon2id PoW algorithm and bundled via Webpack).
- Export journal ISSN as `dc:identifier` (`urn:ISSN:...`) in OAI-PMH ListSets.
- Add Cron daemon to the PHP-FPM container to automatically run the mail queue flushing command every minute in the development environment.
- Add a visual count badge in the bibliographical references panel indicating the number of automatically detected problematic references.
- [#1061](https://github.com/CCSDForge/episciences/pull/1061) Allow secretaries and editors to accept a review invitation on behalf of the reviewer.
- [#937](https://github.com/CCSDForge/episciences/issues/937) Admin paper list (`/administratepaper/list`): columns **Reviewers**, **Editors**, **Copy editors**, and **Contributor** are now sortable server-side.
- [#1058](https://github.com/CCSDForge/episciences/pull/1058) OAI-PMH ListSets: add `<setDescription>` with Dublin Core metadata (title, publisher, date, description, subjects) for journal sets.
- Volume and section reordering: added editable position inputs to reorder them directly by typing numbers.
- Volume settings: added copy-to-clipboard buttons for volume access codes in the volumes list and edit pages.
- Website settings: added support for journal description, keywords, and creation year fields.
- MIME type: added support check for `file` binary presence on the system in `FileBinaryMimeTypeGuesser`.
- [#922](https://github.com/CCSDForge/episciences/issues/922) Add cover letter requirement setting for file attachments. Initial submission form supports three options: disabled (hidden), optional, or required. Author comment form on paper page supports two options: disabled (hidden) or displayed.
- [#1081](https://github.com/CCSDForge/episciences/pull/1081) Add dedicated cover image upload interface to the volume edit page (`/volume/edit/`), replacing the legacy `tile` metadata naming convention.
- [#1087](https://github.com/CCSDForge/episciences/pull/1087) Add a console command `users:normalize-affiliations` to normalize and backfill existing user affiliations data to `{label, rorId}`.

### Changed

- Remove redundant per-type add buttons in linked-data panels.
- Add Subresource Integrity (SRI) to CDN-hosted `cookieconsent.min.js`.
- Remove flag emojis from language-switcher dropdown links.
- Refactor `AccessControlControllerTrait` to reduce cognitive complexity.
- MySQL 8.4 compatibility:
  - Migrate `ON DUPLICATE KEY UPDATE column = VALUES(column)` statements to the modern `AS new_row ... UPDATE column = new_row.column` syntax.
  - Drop the non-standard foreign key `paper_projects_ibfk_1` from the `paper_projects` schema.
- Add unit tests to cover `updateNestedJsonDocument()` and `savemasterfileAction()` wiring.
- [#1080](https://github.com/CCSDForge/episciences/pull/1080) Replace cover letter requirement magic numbers with class constants in `Episciences_Review` and related managers.
- Replace native language switcher dropdown with an accessible WAI-ARIA custom dropdown.
- Route development emails to a local Mailpit SMTP server instead of dumping them to the UI, and remove legacy UI mail dump.
- Development Docker environment: delegate DB, Solr, ZooKeeper, and phpMyAdmin services to the centralized `episciences-infrastructure` repository, and route local traffic via Traefik with default HTTPS support.
- Align document relationship types with HAL schema and refactor their configurations.
- Migrated ROR (Research Organization Registry) API integration to v2.
- Upgraded `symfony/cache` to `^6.4` and updated `shardj/zf1-future` dependencies.
- Sitemap generation:
  - Added per-language URL prefixes based on the `WEBSITE_SETTINGS` configuration.
  - Added individual volume and section pages (`/volumes/{vid}` and `/sections/{sid}`) to the sitemap.
  - Replaced static `changefreq` and `priority` elements with real last modification dates (`lastmod`).
- Replaced jQuery UI sortable with SortableJS for better volume and section reordering behavior and touch support.
- Modernized section list datatable from DataTables 1.9 to 1.10 API.
- [#952](https://github.com/CCSDForge/episciences/issues/952) Add EiC, Editorial Sec and Administrators to recipients of "New answer to your revision request: comment" (paper_revision_answer).
- [#1038](https://github.com/CCSDForge/episciences/issues/1038) It is now possible to publish the article at any stage of the editorial process if it is accepted.
- Paper admin: modal save now does a targeted AJAX refresh instead of a full page reload.
- Paper admin: other volumes form replaced by a Tom Select `<select multiple>` (`checkbox_options` plugin).
- Paper admin (i18n): "Master volume" renamed to "Main volume".
- Admin paper list: filter panel reorganised into 3 rows — Status/Editors/Reviewers on the first row, Volume+DOI on the second, Section+Repositories on the third; Volume and Section are wider (`col-sm-8`) to accommodate long titles.
- Admin paper list: removed redundant page description blockquote.
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)".
- Modernized `Ccsd_Form_Filter_Clean` filter and `Ccsd_Form_Validate_NotSame` validator (introduced strict typing, comprehensive type hinting, and robust recursive array filtering for the `Clean` filter).
- Display the Article status and Versions panels side by side in the paper administration view, and render the versions list as a condensed table.

### Performances

- Eliminate N+1 query patterns on the volume list page (reducing SQL queries from 894 to ~4).
- Batch-load mail templates in 2 SQL queries instead of 2×N.
- Implemented PSR-6 request-scoped caching for database entities (authors, projects, comments, and user assignments repositories) to reduce database queries.
- Eliminated N+1 query patterns on paper list pages by batch-loading users and roles in chunks of 1000 and priming a request-level identity map cache before render loops (`AdministratepaperController`, `UsersManager`).
- `loadRoles()` is now lazy: returns immediately when roles are already loaded, avoiding redundant per-user DB queries in callers such as `AdministratemailController::getcontactsAction()`.
- [#937](https://github.com/CCSDForge/episciences/issues/937) Sorting by Reviewers / Editors / Copy editors uses a derived-table `LEFT JOIN` with `MIN(SCREEN_NAME)` aggregation (single evaluation per query) rather than a correlated subquery.
- Paper admin: volume/section assignment now uses a native `<dialog>` modal with Tom Select; `paper-assignment-modal.js` (vanilla JS) replaces `volume-assignment.js` and `section-assignment.js`.
- Paper admin: Tom Select `dropdown_input` and `remove_button` plugins in modal selects.
- Admin paper list: Tom Select search on all 7 filter selects (Status, Volume, Section, Editors, Reviewers, DOI, Repositories) — type to filter within long lists, animated chevron indicates dropdown, placeholder shows "All" when nothing is selected.
- Avoid repeated `REVIEW_SETTING` queries in a single request by caching loaded review settings on the current review object and sharing cached review instances between `RVID` and `RVCODE` lookups.

### Fixed

- Fix PHP 8 compatibility by explicitly declaring `Ccsd_Website_Header::$_langDir` as `string` to prevent fatal type variance errors in subclasses.
- Fix deprecation warning "Implicit conversion from float to int loses precision" when updating metadata.
- Fix missing translation caused by a curly apostrophe character (`’`) in templates.
- Fix application error in `Episciences_Paper_FilesManager::getMainFile()` where a null `$docId` parameter caused a type crash.
- Fix malformed `Content-Type` headers in COAR Notify client requests sent to HAL by injecting a custom cURL HTTP layer (`CoarNotifyHttpLayer`).
- Fix JSON path mapping in `updateNestedJsonDocument` (`$.document.database.mainPdfUrl` -> `$.database.current.mainPdfUrl`) and add a null-safe operator to database preparation.
- Adapt the master file choice logic so that the option to select a main file does not apply to Software and Dataset submissions.
- Fix COAR Notify Location-header lookup warnings.
- [#1087](https://github.com/CCSDForge/episciences/pull/1087) Normalize user affiliations to `{label, rorId}` format during account signup to ensure shape consistency with profile edits.
- [#1080](https://github.com/CCSDForge/episciences/pull/1080) Fix cover letter validation error target (attaching the error to the file element instead of the comments field) and default the setting value to optional (1) for older reviews.
- Escaping of XML special characters in OAI-PMH ListSets and setDescription (by using `createTextNode`).
- Explicitly set permission to `0644` on uploaded volume metadata files.
- Fix linked-data: remove premature `changePlaceholder` calls before AJAX resolves.
- [#1059](https://github.com/CCSDForge/episciences/issues/1059) Fixed method naming inconsistency (`RelationsShips` → `RelationShips`) in `Episciences\Paper\Relationship` and its callers; added missing `string` type hint on closure parameter to comply with PHPStan level 6.
- [#1035](https://github.com/CCSDForge/episciences/issues/1035) Fixed an issue where editors were unable to proceed to the "copy editing" stage because the transition button was hidden behind an incorrect conditional block in the status dropdown menu.
- Prevent the submission of a dataset or software that does not include a descriptor.
- [#1048](https://github.com/CCSDForge/episciences/issues/1048) Volume metadata: quotes in preface/content caused JSON parsing error when editing. Removed `double_encode=false` from `form.phtml` display while keeping it in `Volume.php` save to prevent double-encoding (#962).
- [#1039](https://github.com/CCSDForge/episciences/issues/1039) The MIME type for docx files is detected as "application/octet-stream".
- Fixed crash (`TypeError`) in PHP 8.1 in `Episciences_Form_Validate_MimeType` when the file parameter is not an array.
- Fixed the `%type%` placeholder not being included in validation error messages when file upload failed due to incorrect MIME type.
- Fixed the `%%SUBMISSION_DATE%%` email tag using the paper creation/update timestamp instead of the actual initial submission date.
- Fixed volume editor list not refreshing after save in volume settings.
- Fixed section refresh updating all rows instead of only the changed paper row in the section list.
- Fixed deadline modal submitting form natively instead of via AJAX.
- Fixed report attachment deletion redirecting reviewers away from the rating page.
- Fixed reviewer role not being saved when accepting a review invitation.
- Validated comment attachment extensions to reject disallowed file types.
- Trimmed visit log entries by keeping only client IP and proxy checks to reduce IPv6 log entry size.
- Fixed Application Error when calling `Episciences_Submit::assignEditors()` where `$sid` or `$vid` arguments were passed as string instead of integer or null.
- Application error: Call to a member function getVol_num() on bool.
- `scripts/update_papers.php`: fixed issue where importing a new version (e.g. version 2) of an existing paper (e.g. version 1) overwrote the version 1 entry in the database instead of creating a new version record.
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
- Fix an unterminated SQL string literal in `Episciences_User::filterUsers()` when filtering users without roles.
- Fix history filter date popover sizing, correct jQuery UI datepicker positioning/z-index, and fix calendar-icon click interaction in the paper administration view.
- Fix multiple bugs and potential XSS issues found during a public JavaScript audit (including strict tooltip option defaults, escaping regex patterns in search inputs, and removing incorrect JSON dataType requirements).
- Improve `Episciences_Paper_FilesManager::syncFiles()`: generate unique self-link hashes to prevent database collisions when file links are empty or set to `#`, resolve argument order in file difference checking, and clean up paper file deletion logic.
- [#1125](https://github.com/CCSDForge/episciences/pull/1125) Improve report attachment access control: allow paper authors to download, block users with declared COI.
### Deprecated

- Deprecate obsolete `Ccsd_Form_Element_Thesaurus` form element (scheduled for removal).

### Removed

- [#1088](https://github.com/CCSDForge/episciences/pull/1088) Remove unused `AGENT`, `CITY`, `LAT`, and `LON` columns from the `PAPER_STAT` table and delete the legacy `scripts/stat.php` script (superseded by the `stats:process` console command).
- Remove obsolete `google/recaptcha` and `neverbehave/hcaptcha` libraries from `composer.json`.
- Removed obsolete, unused, and deprecated authentication adapters: `Asso`, `DbTable`, `Idp`, `Asso/Ext`, and `Orcid` (`Ccsd/Auth`).
- Removed obsolete `DEAD CODE AUDIT` deprecation warnings from `UserFtpQuota` and `UserFtpQuotaMapper` classes.

### Security

- Restructured ACL and CSRF usage
- Cleaned up and restructured CAS and MySQL authentication adapters (`Ccsd/Auth/Adapter/Mysql`, `Ccsd/Auth/Adapter/Cas`).
- Removed obsolete, unused, and deprecated authentication adapters: `Asso`, `DbTable`, `Idp`, `Asso/Ext`, and `Orcid` (`Ccsd/Auth`).
- Translated ACL and Auth comments to English and enforced strict typing on plugins.
- Prevent potential XSS by HTML-escaping user metadata in the email autocomplete suggestions, and sanitize screen names at the source (cleaning tags, control characters, and whitespace).

## v1.0.55.3 - 2026-05-20

### Fixed

- [RT#287093]: Failed to submit "acceptedAskAuthorsFinalVersion" Form: CSRF validation failed (CSRF field missing)
- Adapt "acceptedAskAuthorsFinalVersion" form to the requirements for setting a deadline for requesting a revision after acceptance

### Changed

- [#1010](https://github.com/CCSDForge/episciences/issues/1010) The allowed range for modifying the deadline is now between the original deadline minus "rating_deadline_min" and the original deadline plus "rating_deadline_max".
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration
  button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)"

## v1.0.55.2 - 2026-05-19

### Fixed

- Improved article URL resolution in `ExtractBiblioRefsCommand` by adding fallbacks to `getMainPaperUrl()` and `getDocUrl()` when the repository template is empty (e.g., for Zenodo).
- Added debug logging for resolved article URLs and included raw response snippets in JSON decode error messages in `ExtractBiblioRefsCommand`.

## v1.0.55.1 - 2026-05-19

### Added

- Legend for bibliographic reference status icons in both admin and public views (publicly shown only when problematic references are detected).

### Changed

- Replaced bibliographic reference status indicators with standardized square icons (red `fa-square-xmark` for problematic, green `fa-square-check` for validated, and gray `fa-square` for automatically extracted references).
- Improved accessibility of bibliographic references (added ARIA landmarks, screen-reader-only status prefixes, descriptions for status badges, and warnings for links opening in new tabs).
- Optimized bibliographic references layout by moving the status legend inline with the action button.
- Renamed 'Not validated reference' to 'Automatically extracted reference' for clarity.
- Renamed 'Manage' button to 'Manage References' / 'Gérer les références' in the administration view.

### Fixed

- Removed obsolete BibTeX import button (feature migrated to an external application).
- Removed hardcoded Semantic Scholar source attribution from bibliographic references display.
- Skip temporary versions (without external repository URL) during bibliographic reference extraction.
- Fixed a missing dependency (`require_once`) for `GetDoiCommand` in `console.php`.

## v1.0.55 - 2026-05-18

### Added

- Automatic detection of problematic papers cited in the references of the papers. This is heavily based on the huge work provided by the Problematic Paper Screener https://dbrech.irit.fr/pls/apex/f?p=9999:1::::::
  For more information: Cabanac, G., Labbé, C., & Magazinov, A. (2022). The ‘Problematic Paper Screener’ automatically selects suspect publications for post-publication (re)assessment.
  Presented at WCRI 2022: 7th World Conference on Research Integrity. arXiv preprint. https://doi.org/10.48550/arXiv.2210.04895

- [#630](https://github.com/CCSDForge/episciences/issues/630) COI (Conflict of Interest) notifications:
    - Email notification to the editor-in-chief when an editor declares a positive COI (answered "yes")
    - Email notification to other assigned editors (if any) when an editor declares a COI
- Many legacy scripts migrated to Symfony Console commands (e.g., `doi:manage` replaces `getDoi.php`, `sitemap:generate --all`, translations update).
- Expanded unit test coverage for repository connectors (Zenodo, BioMedRxiv, ARCHE, Dspace) and COI notifications (+60 tests).

### Changed

- Sending the invitation in the user’s language, including when using the autocomplete input field for CAS users who already have a profile on the site.
- Simplified news add/edit form for a better user experience.
- Refactored OpenAIRE integration into a dedicated `OpenAireApiClient`.
- Improved PHP 8.1 compatibility and robustness across the codebase (removed deprecations and warnings).
- Optimized the submission workflow processing order.

### Fixed

- A bug that likely appeared in the latest update: section and/or volume editors are not assigned automatically, even if the relevant settings are enabled.
- Panel not opening when updating linked data
- Submission of a dataset without a data descriptor due to an error while uploading attached files (one of the file types exceeded the maximum allowed size for that field type)
- Corrected article position in volumes (1-based instead of 0-based) for CSL generation.
- Resolved relative `hydra:next` URLs in sitemap generation.
- Fixed several potential `TypeError` and crashes when metadata or publication dates are missing.

## v1.0.54.3 - 2026-04-23

### Fixed

- The link to the new version of the data descriptor isn't working; it still points to the first version.
- [RT#285106]: Application error when submitting with 'display secondary volume' option enabled
- Always SELECT RECORD so Paper::getMetadata() / toJson() can resolve titles and abstracts
- Log parser: extract IP from syslog-prefixed Apache log lines

## v1.0.54.2 - 2026-04-16

### Fixed

- Inability to change the review deadline (restore loading js via url).

### Added

- docs: add documentation index to README and update docs
- stats:import-logs command to parse Apache logs into STAT_TEMP. Introduces ImportApacheLogsCommand (stats:import-logs), a Symfony Console command that parses Apache access logs and inserts article visits into STAT_TEMP. Replaces the legacy UpdateStatistics.php . Run src/mysql/2025-08-24-stat-processing-log-table.sql before deploying

## v1.0.54.1 - 2026-04-14

### Changed

- Refactored English email templates, grammar only, no new features, no new tags

### Fixed

- [#962](https://github.com/CCSDForge/episciences/issues/962) Fixed double HTML encoding bug in volume metadata titles where special
  characters like `<`, `>`, and `'` were displayed as `&lt;`, `&gt;`, `&#039;`
- TypeError in enrichment:creators on non-sequential affiliation keys
- isImported detection for published papers when the the publication date has been fixed after publication
- [#985](https://github.com/CCSDForge/episciences/issues/985) Impossible to submit a final version
- Fixed bug in management of journal headers for adding and updating logo and texts

## v1.0.54 - 2026-04-09

### Added

- [#779](https://github.com/CCSDForge/episciences/issues/779) [Feature request] Display the secondary volume on the article page. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Adding "Publish" => "Proposing special issues" template page [#951](https://github.com/CCSDForge/episciences/issues/951)
- New supported servers:
    - Cryptology ePrint Archive (https://eprint.iacr.org)
    - Support for any data repositories powered by Dspace (eg. [repositorium.uminho(University of Minho)](https://repositorium.uminho.pt))
- Statistics: new Symfony Console commands `stats:process` (replaces legacy `scripts/stat.php`) and `stats:update-robots-list` for UA-based bot detection using the COUNTER Robots list; real client IP is now stored in `STAT_TEMP` and anonymized at processing time.
- Documentation: added `docs/console-commands.md` listing all 19 CLI commands available via `scripts/console.php` with options and usage examples.
- OAI-PMH: added `crossref` as a new metadata format (`metadataPrefix=crossref`), exposing Crossref 5.3.1 XML for each published paper. The depositor email is automatically replaced with a generic `noreply@{domain}` address in this context.
- Comprehensive Jest test suite for ORCID authors management (`tests/js/updateOrcidAuthors.test.js`).
- Unit tests for `Episciences_Paper_Projects` entity (setOptions aliases, DateTime handling, toArray serialization, fluent setters).
- Unit tests for `Episciences_Paper_Projects_EnrichmentService` pure functions (EU/ANR response normalization, OpenAIRE relation filtering).
- Unit tests for `Episciences_Paper_Projects_ViewFormatter` (empty-array return, URL HTML-escaping, unidentified title suppression).
- Unit tests for `Episciences_Paper_Citations_ViewFormatter` (20 tests: sort, author formatting, ORCID links, XSS prevention, book-chapter/proceedings-article reordering).
- Unit tests for `Episciences_Paper_Citations` entity (8 tests: `toArray()` key, fluent setters, nullable `updatedAt`, constructor).
- Comprehensive unit tests for `Episciences_Paper_Authors_ViewFormatter` covering HTML display logic and XSS prevention.
- Unit tests for `Episciences_View_Helper_DoiAsLink`, `Episciences_View_Helper_FormatIssn`, `Episciences_View_Helper_Log`, `Episciences_View_Helper_Tag`, `Episciences_View_Helper_UserAvatar`, `Episciences_View_Helper_GetAvatar` and `Episciences_CommentsManager`.
- 89+ unit tests for `Ccsd\Auth` adapters (`CasAbstract`, `Idp`, `Orcid`, `AdapterFactory`, `Asso`), `Ccsd\User` models (`User`, `UserTokens`, `UserFtpQuota`) and `Episciences\User` entities covering pure logic without DB or network access.
- Updated `tests/README.md` with accurate Docker-based testing instructions, `make` target reference, subset-run examples, and contributor guidelines.
- [#883](https://github.com/CCSDForge/episciences/issues/883) Allow json files as attachments.
- Optional Webhook: It is now possible to report status changes to an external entry point (can be configured by journal).
- [#658](https://github.com/CCSDForge/episciences/issues/658) It is now possible to link an invitation that is not intended for you to your account.
- allow the co-author to view the publication.
- Statistics: the script has a new parameter `--all` - Process all statistics (with confirmation prompt).
- New option to allow Editors to receive 'Comments for editors' before declaring a conflict of interest (disabled by default).
- Volume Settings: add New configuration section(displayEmptyVolumes and allowEditVolumeTitleWithPublishedArticles) in journal settings.
- [#679](https://github.com/CCSDForge/episciences/issues/679) "Ask other editors for their opinion" form now includes chief editors (ROLE_CHIEF_EDITOR) in addition to regular editors (ROLE_EDITOR).
- [#691](http://github.com/CCSDForge/episciences/issues/691) Display "(optional)" label below comment and cover letter fields in submission forms to clarify these fields are not required.
- [#350](https://github.com/CCSDForge/episciences/issues/350) Author-Editor Communication Settings:
    - New option to allow authors to contact assigned editors (`authorEditorCommunication`)
    - New option to disclose editor names to authors or keep them anonymized (`discloseEditorNamesToAuthors`)
- feat(navigation): sync predefined page titles with T_PAGES table on menu save.
- New `library/Episciences/Api/` namespace with 5 injectable, independently-testable API clients: `AbstractApiClient`, `CrossrefApiClient`, `OpenAlexApiClient`, `OpenCitationsApiClient`, `OpenAireApiClient`.
- Five Symfony Console commands replacing the legacy JournalScript enrichment scripts: `enrichment:citations`, `enrichment:creators`, `enrichment:funding`, `enrichment:licences`, `enrichment:links`.
- Added MSC2020 Classification in ZBJATS export.
- `Episciences\Notify\NotifySourceConfig` — immutable value object holding per-source configuration (repoId, label, originId, originInbox, acceptedTypes); new repositories can be added without touching `InboxNotifications`
- `Episciences\Notify\NotifySourceRegistry` — registry with a `createFromConstants()` factory; resolves source config from a payload's `origin.inbox`
- `Episciences\Notify\ValidationResult` — pure value object wrapping a validation outcome (success / failure + error message)
- `Episciences\Notify\PreprintUrlParser` — pure helper extracting version and identifier from preprint landing-page URLs (HAL-style)
- `Episciences\Notify\PayloadValidator` — validates COAR Notify 1.0.1 payloads for Offer patterns (Request Review / Request Endorsement): all required fields including `object.id` (HTTP URI) and `object['ietf:item']` (id, type, mediaType)

### Changed

- [#947](https://github.com/CCSDForge/episciences/issues/947) [Feature request] wording on the platform - refused / rejected and refused/declined
- Refactored `public/js/paper/updateOrcidAuthors.js` from jQuery to modern vanilla ES6+ with a class-based architecture.
- Enhanced ORCID form validation: added duplicate detection while maintaining support for empty submissions for deletions.
- Update TinyMCE form 7.3.0 to 8.1.2.
- Increased Volume Description length to 1024 chars.
- DOI panel: requesting, saving, and cancelling a DOI no longer triggers a full page reload; success feedback message is suppressed.
- Refactored `Episciences_Paper_ProjectsManager` into `Episciences_Paper_Projects_Repository`, `Episciences_Paper_Projects_HalApiClient`, `Episciences_Paper_Projects_EnrichmentService`, and `Episciences_Paper_Projects_ViewFormatter`.
- Refactored `Episciences_Paper_CitationsManager` into `Episciences_Paper_Citations_Repository`, `Episciences_Paper_Citations_ViewFormatter`, `Episciences_Paper_Citations_EnrichmentService`, and `Episciences_Paper_Citations_Logger`.
- Redundant PHPDoc removed from all new citations classes via Rector.
- Replaced `Episciences_Cache` with `symfony/cache` 5.4 across all internal usages.
- `PapersManager::getList()`: removed `$cached` parameter; paper list is now always fetched fresh.
- `Review::getPapers()`, `CopyEditor::loadAssignedPapers()`, `Editor::loadAssignedPapers()`, `Reviewer::loadAssignedPapers()`, `Volume::getPaperListFromVolume()`: updated signatures and call sites following the removal of `$cached`.
- `Oai/Server::getIds()`: OAI resumption token cache migrated to PSR-6.
- Statistics: log import script now supports anonymized log files (.access_log_anonym.gz) as a fallback.
- Improved accessibility of the ORCID authors modal: proper `<label>` associations, `aria-modal="true"`, and corrected `aria-labelledby`.
- Modernized ORCID authors row layout using Flexbox.
- Standardized modal button styles for ORCID updates (Cancel: `btn-default`, Save: `btn-primary`).
- ORCID input fields: URLs are now automatically cleaned to keep only the 16-digit identifier.
- `administratepaper/view.phtml`: reordered panels; moved "Volumes & Rubriques" earlier; `paper_versions` moved to the bottom; removed redundant label.
- `paper/paper_datasets.phtml`: "Liens publications – données – logiciels" panel is now collapsed by default.
- `paper/paper_graphical_abstract.phtml`: graphical abstract panel is now collapsed by default when empty.
- `partials/coauthors.phtml`: "Ajouter un co-auteur" panel is now collapsed by default.
- `partials/paper_affiliation_authors.phtml`: "Ajouter une affiliation" panel is now collapsed by default.
- `volume/list.phtml`: Added "Number" and "Year" columns; improved table accessibility; Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: Removed redundant inline scripts; construction of the editors list optimized.
- Updated icons from `fas fa-address-card` to `fa-regular fa-address-card`.
- Improved visual indicators for editor availability in `editor-availability.js`.
- Refactored `scripts/zbjatZipper.php`: renamed class to `ZbjatZipper`, replaced `echo` with Monolog, extracted God method, switched to HTTPS.
- Refactored `Episciences_Paper_AuthorsManager` into `Episciences_Hal_TeiCacheManager`, `Episciences_Paper_Authors_HalTeiParser`, `Episciences_Paper_Authors_Repository`, `Episciences_Paper_Authors_EnrichmentService`, `Episciences_Paper_Authors_AffiliationHelper`, and `Episciences_Paper_Authors_ViewFormatter`.
- Refactored `Episciences_Paper_Authors_ViewFormatter` to separate data fetching from formatting logic.
- Moved `normalizeOrcid()` implementation from `AuthorsManager` to `HalTeiParser`.
- `AuthorsManager::ONE_MONTH` now references `TeiCacheManager::ONE_MONTH`.
- Added `TeiCacheManager::fetchAndGet()` to combine fetch-if-needed + read in a single call.
- The "Encapsulate reviewers" parameter is now hidden.
- [#528](https://github.com/CCSDForge/episciences/issues/528): 'upload a new version' on top; updated contact label.
- Paper metrics Refactored; 1 query instead of 2.
- The Locale is now stored in a cookie instead of the session.
- Redirect to the article to be reviewed when accepting an invitation.
- Allow volume years to be a string (AAAA or AAAA-AAAA).
- Synchronization of predefined page titles between navigation menu and T_PAGES table when saving menu configuration.
- Replaced `cottagelabs/coar-notifications` (Doctrine ORM + Guzzle) with `cottagelabs/coarnotify` (stateless pattern builder) and a custom PDO persistence layer (`Notification` DTO + `NotificationsRepository`)
- Refactored `InboxNotifications` (1390-line God Class) into focused private methods (`processFirstSubmission`, `processSubsequentSubmission`, `setupJournalTranslations`, `resolveNewVersionStatus`, `logVersionUpdate`, …) and pure, dependency-free helper classes
- `checkNotifyPayloads()` and `initSubmission()` are now source-aware: the COAR Notify source is resolved from `origin.inbox` via `NotifySourceRegistry`
- `InitSubmissionsFromHalInbox.php` is now a direct CLI entry point on `InboxNotifications`; the empty subclass has been removed

### Fixed

- [#858](https://github.com/CCSDForge/episciences/issues/858) fix: only use "et al." when 2+ authors are omitted. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Limit the editor’s permissions to allow assigning only the "reviewer" role.
- [#944](https://github.com/CCSDForge/episciences/issues/944): An author informed us that they received this message signed with their name: %%SENDER_FULL_NAME%% and %%RECIPIENT_SCREEN_NAME%% filled with the same value
- [#258](https://github.com/CCSDForge/episciences-front/issues/258): Tables are not handled by default: Explicit table conversion
- Fixed RT#277365: in order to correct the printing of extra lines, two options have been added to the TinyMCE configuration to insert a <br> instead of a <p> and another option to remove extra <br>s at the end of a block
- Fixed tooltip initialization in volume and section assignment after AJAX refreshes and DataTable redraws.
- Fixed Solr search engine indexation of Authors with a middle name.
- Consider an additional review when sending reminders for an insufficient number of reviewers.
- `Projects`: `$_dateUpdated` default fixed; `getDateUpdated()` return type consistency.
- `Projects::setFunding()`: made method fluent.
- `Projects::setOptions()`: fixed `setIdproject()` alias and case-insensitive method lookup.
- `Projects::toArray()`: fixed `DateTime` serialization.
- `Repository::insert()`: replaced deprecated `VALUES()` syntax with row-alias syntax for MySQL 8.0.20+.
- `Repository`: exceptions now propagate instead of terminating script on DB errors.
- `ViewFormatter`: fixed XSS by escaping `$vfunding['url']` and other user-controlled values in HTML attributes.
- `HalApiClient::doGet()`: added `E_USER_WARNING` severity level.
- `EnrichmentService::resolveHalProjectIds()`: removed duplicate log message; sanitized cache key.
- `Citations_ViewFormatter`: removed double escaping; fixed unstable compound sort; added ORCID format validation.
- `Citations_Repository`: replaced deprecated MySQL `VALUES()` function; added check for `$docId <= 0`.
- `Citations` entity: fixed `toArray()` key name; fixed `$_updatedAt` type and default.
- ORCID normalization: `cleanLowerCaseOrcid()` fix; applied normalization in Zenodo and ARCHE hooks.
- `findAffiliationsOneAuthorByPaperId()`: fixed potential undefined variable.
- `hasAcronym()`: fixed iteration over nested `id` array.
- `HalTeiParser::getAuthorsFromHalTei()`: fixed logic to prevent enriching the wrong author.
- `ViewFormatter::buildAffiliationListHtml()`: fixed Stored XSS by escaping the affiliation acronym.
- `ViewFormatter`: fixed `html_entity_decode(htmlspecialchars())` no-op.
- `EnrichmentService::mergeExistingAffiliations()`: fixed matching key lookup with `array_search()`.
- `AffiliationHelper::isAcronymDuplicate()`: now iterates all identifiers.
- `AffiliationHelper::setOrUpdateRorAcronym()`: returns first match deterministically.
- `TeiCacheManager::buildApiUrl()`: applied `urlencode()` to prevent Solr query injection.
- `TeiCacheManager::getFromCache()`: removed dead `expiresAfter()` call.
- `Repository`: fixed `JSON_DECODE_FLAGS`.
- `CommentsManager::$_typeLabel`: added missing entry for `TYPE_CONTRIBUTOR_TO_REVIEWER`.
- `CommentsManager::updateUid()`: fixed negative UID guard.
- `FormatIssn::FormatIssn()`: fixed substring length.
- `Log::log()`: fixed uncaught exception from `$logger->log()`.
- `DoiAsLink::DoiAsLink()`: fixed label when no text provided.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass with `preg_quote()` and anchors.
- Fixed RT#277365: added TinyMCE configuration options to handle `<br>` and `<p>` correctly.
- [#886](https://github.com/CCSDForge/episciences/issues/886): reminder logic for lack of reviewers fixed.
- `convertToBytes()`: fixed handling of pure numeric strings and added validation.
- `isHal()`, `isHalUrl()`, `isArxiv()`: fixed regex anchors.
- Added comprehensive test coverage for `Episciences_Tools`.
- [#236](https://github.com/CCSDForge/episciences-front/issues/236): HTML entities (&) displayed in the title.
- [#695](https://github.com/CCSDForge/episciences/issues/695) Fixed CC/BCC fields not clickable in paper status change forms.
- [#830](https://github.com/CCSDForge/episciences/issues/830) Paper number messed up for secondary volume; altered secondary volume rendering.
- [#776](https://github.com/CCSDForge/episciences/issues/776) Action Required: Fix Renovate Configuration.
- [#657](https://github.com/CCSDForge/episciences/issues/657) Conditionally remove the `<hr>` separator in metadata views.
- [#786](https://github.com/CCSDForge/episciences/issues/786) English translation of 'Télécharger le fichier'.
- [#646](https://github.com/CCSDForge/episciences/issues/646) Redirect to article page after submission.
- [#780](https://github.com/CCSDForge/episciences/issues/780) Option to lock volume name when an article is published.
- [#147](https://github.com/CCSDForge/episciences/issues/147) Add new pages Acknowledgements in menu 'About'.
- Fixed Paper Metrics based on wrong DocId.
- Fixed Pre-defined pages not deleted from the database.
- [#77](https://github.com/CCSDForge/episciences-front/issues/77) Fix orphan assignments when deleting sections or volumes.
- `CrossrefTools`: fixed fatal error on constant name; fixed raw `CacheItem` return.
- `OpenalexTools`: fixed `getPages()` return value and trailing separator in `getAuthors()`.
- `OpencitationsTools`: fixed caching of empty responses; fixed DOI extraction and updated API format support.
- `getLinkData`: switched to HTTPS; added null guards for `$valuesResult`.
- `getLicenceDataEnrichment`: fixed ineffective cache.
- `OpenAireResearchGraphTools`: enabled strict mode for `array_search()`.
- `Projects/EnrichmentService`: fixed log path and added `(int)` cast for source ID.
- `Citations/EnrichmentService`: added null guards for OpenAlex API fields.
- [#875](https://github.com/CCSDForge/episciences/issues/875): fixed HTML/Emoji translation issue.

### Deprecated

- `Episciences_Cache` and its parent `Ccsd_Cache` are now marked `@deprecated`; use `Symfony\Component\Cache\Adapter\FilesystemAdapter` instead.
- `ProjectsManager` kept as a thin backward-compatible facade.
- `CitationsManager` kept as backward-compatible facade with `@deprecated` proxies.
- `AuthorsManager` kept as orchestrator with backward-compatible `@deprecated` proxies.
- `Episciences_Paper_Visits::count` is deprecated.

### Removed

- Redundant "Statut actuel :" label prefix from the article status panel.
- Stray `<br>` and inlined `versionCache` script tag in `partials/paper_affiliation_authors.phtml`.
- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`.

### Security

- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`, eliminating a potential PHP object injection vector.
- OAI resumption token cache keys are now MD5-hashed before use, preventing cache-key injection.
- Escaped output in `volume/editors_list.phtml` to prevent potential XSS.
- Fixed XSS vulnerability in `Projects/ViewFormatter`: funding URL was interpolated unescaped into `href` attribute and link text.
- Fixed XSS in `Citations_ViewFormatter`: `href=` attributes for DOI and OA links were unquoted.
- Fixed XSS vulnerability in `ViewFormatter::buildAuthorHtml()` and `buildAffiliationListHtml()` regarding user-controlled values in HTML attributes.
- Fixed potential Solr query injection in `TeiCacheManager::buildApiUrl()`.
- `GetAvatar::asPaperStatusSvg()`: fixed two path traversal vectors ($lang sanitization and $paperStatus casting).
- `DoiAsLink::DoiAsLink()`: added `rel="noopener noreferrer"` to prevent tab-napping.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass that allowed authentication from unauthorized email domains.

### Performances

- `volume/list.phtml`: Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: construction of the editors list optimized.
- `Episciences_Paper_Citations_Logger`: Singleton Monolog logger (avoids Logger recreation on every call).
- Paper metrics Refactored; 1 query instead of 2.

## v1.0.55.3 - 2026-05-20

### Fixed

- [RT#287093]: Failed to submit "acceptedAskAuthorsFinalVersion" Form: CSRF validation failed (CSRF field missing)
- Adapt "acceptedAskAuthorsFinalVersion" form to the requirements for setting a deadline for requesting a revision after acceptance

### Changed

- [#1010](https://github.com/CCSDForge/episciences/issues/1010) The allowed range for modifying the deadline is now between the original deadline minus "rating_deadline_min" and the original deadline plus "rating_deadline_max".
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration
  button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)"

## v1.0.55.2 - 2026-05-19

### Fixed

- Improved article URL resolution in `ExtractBiblioRefsCommand` by adding fallbacks to `getMainPaperUrl()` and `getDocUrl()` when the repository template is empty (e.g., for Zenodo).
- Added debug logging for resolved article URLs and included raw response snippets in JSON decode error messages in `ExtractBiblioRefsCommand`.

## v1.0.55.1 - 2026-05-19

### Added

- Legend for bibliographic reference status icons in both admin and public views (publicly shown only when problematic references are detected).

### Changed

- Replaced bibliographic reference status indicators with standardized square icons (red `fa-square-xmark` for problematic, green `fa-square-check` for validated, and gray `fa-square` for automatically extracted references).
- Improved accessibility of bibliographic references (added ARIA landmarks, screen-reader-only status prefixes, descriptions for status badges, and warnings for links opening in new tabs).
- Optimized bibliographic references layout by moving the status legend inline with the action button.
- Renamed 'Not validated reference' to 'Automatically extracted reference' for clarity.
- Renamed 'Manage' button to 'Manage References' / 'Gérer les références' in the administration view.

### Fixed

- Removed obsolete BibTeX import button (feature migrated to an external application).
- Removed hardcoded Semantic Scholar source attribution from bibliographic references display.
- Skip temporary versions (without external repository URL) during bibliographic reference extraction.
- Fixed a missing dependency (`require_once`) for `GetDoiCommand` in `console.php`.

## v1.0.55 - 2026-05-18

### Added

- Automatic detection of problematic papers cited in the references of the papers. This is heavily based on the huge work provided by the Problematic Paper Screener https://dbrech.irit.fr/pls/apex/f?p=9999:1::::::
  For more information: Cabanac, G., Labbé, C., & Magazinov, A. (2022). The ‘Problematic Paper Screener’ automatically selects suspect publications for post-publication (re)assessment.
  Presented at WCRI 2022: 7th World Conference on Research Integrity. arXiv preprint. https://doi.org/10.48550/arXiv.2210.04895

- [#630](https://github.com/CCSDForge/episciences/issues/630) COI (Conflict of Interest) notifications:
    - Email notification to the editor-in-chief when an editor declares a positive COI (answered "yes")
    - Email notification to other assigned editors (if any) when an editor declares a COI
- Many legacy scripts migrated to Symfony Console commands (e.g., `doi:manage` replaces `getDoi.php`, `sitemap:generate --all`, translations update).
- Expanded unit test coverage for repository connectors (Zenodo, BioMedRxiv, ARCHE, Dspace) and COI notifications (+60 tests).

### Changed

- Sending the invitation in the user’s language, including when using the autocomplete input field for CAS users who already have a profile on the site.
- Simplified news add/edit form for a better user experience.
- Refactored OpenAIRE integration into a dedicated `OpenAireApiClient`.
- Improved PHP 8.1 compatibility and robustness across the codebase (removed deprecations and warnings).
- Optimized the submission workflow processing order.

### Fixed

- A bug that likely appeared in the latest update: section and/or volume editors are not assigned automatically, even if the relevant settings are enabled.
- Panel not opening when updating linked data
- Submission of a dataset without a data descriptor due to an error while uploading attached files (one of the file types exceeded the maximum allowed size for that field type)
- Corrected article position in volumes (1-based instead of 0-based) for CSL generation.
- Resolved relative `hydra:next` URLs in sitemap generation.
- Fixed several potential `TypeError` and crashes when metadata or publication dates are missing.

## v1.0.54.3 - 2026-04-23

### Fixed

- The link to the new version of the data descriptor isn't working; it still points to the first version.
- [RT#285106]: Application error when submitting with 'display secondary volume' option enabled
- Always SELECT RECORD so Paper::getMetadata() / toJson() can resolve titles and abstracts
- Log parser: extract IP from syslog-prefixed Apache log lines

## v1.0.54.2 - 2026-04-16

### Fixed

- Inability to change the review deadline (restore loading js via url).

### Added

- docs: add documentation index to README and update docs
- stats:import-logs command to parse Apache logs into STAT_TEMP. Introduces ImportApacheLogsCommand (stats:import-logs), a Symfony Console command that parses Apache access logs and inserts article visits into STAT_TEMP. Replaces the legacy UpdateStatistics.php . Run src/mysql/2025-08-24-stat-processing-log-table.sql before deploying

## v1.0.54.1 - 2026-04-14

### Changed

- Refactored English email templates, grammar only, no new features, no new tags

### Fixed

- [#962](https://github.com/CCSDForge/episciences/issues/962) Fixed double HTML encoding bug in volume metadata titles where special
  characters like `<`, `>`, and `'` were displayed as `&lt;`, `&gt;`, `&#039;`
- TypeError in enrichment:creators on non-sequential affiliation keys
- isImported detection for published papers when the the publication date has been fixed after publication
- [#985](https://github.com/CCSDForge/episciences/issues/985) Impossible to submit a final version
- Fixed bug in management of journal headers for adding and updating logo and texts

## v1.0.54 - 2026-04-09

### Added

- [#779](https://github.com/CCSDForge/episciences/issues/779) [Feature request] Display the secondary volume on the article page. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Adding "Publish" => "Proposing special issues" template page [#951](https://github.com/CCSDForge/episciences/issues/951)
- New supported servers:
    - Cryptology ePrint Archive (https://eprint.iacr.org)
    - Support for any data repositories powered by Dspace (eg. [repositorium.uminho(University of Minho)](https://repositorium.uminho.pt))
- Statistics: new Symfony Console commands `stats:process` (replaces legacy `scripts/stat.php`) and `stats:update-robots-list` for UA-based bot detection using the COUNTER Robots list; real client IP is now stored in `STAT_TEMP` and anonymized at processing time.
- Documentation: added `docs/console-commands.md` listing all 19 CLI commands available via `scripts/console.php` with options and usage examples.
- OAI-PMH: added `crossref` as a new metadata format (`metadataPrefix=crossref`), exposing Crossref 5.3.1 XML for each published paper. The depositor email is automatically replaced with a generic `noreply@{domain}` address in this context.
- Comprehensive Jest test suite for ORCID authors management (`tests/js/updateOrcidAuthors.test.js`).
- Unit tests for `Episciences_Paper_Projects` entity (setOptions aliases, DateTime handling, toArray serialization, fluent setters).
- Unit tests for `Episciences_Paper_Projects_EnrichmentService` pure functions (EU/ANR response normalization, OpenAIRE relation filtering).
- Unit tests for `Episciences_Paper_Projects_ViewFormatter` (empty-array return, URL HTML-escaping, unidentified title suppression).
- Unit tests for `Episciences_Paper_Citations_ViewFormatter` (20 tests: sort, author formatting, ORCID links, XSS prevention, book-chapter/proceedings-article reordering).
- Unit tests for `Episciences_Paper_Citations` entity (8 tests: `toArray()` key, fluent setters, nullable `updatedAt`, constructor).
- Comprehensive unit tests for `Episciences_Paper_Authors_ViewFormatter` covering HTML display logic and XSS prevention.
- Unit tests for `Episciences_View_Helper_DoiAsLink`, `Episciences_View_Helper_FormatIssn`, `Episciences_View_Helper_Log`, `Episciences_View_Helper_Tag`, `Episciences_View_Helper_UserAvatar`, `Episciences_View_Helper_GetAvatar` and `Episciences_CommentsManager`.
- 89+ unit tests for `Ccsd\Auth` adapters (`CasAbstract`, `Idp`, `Orcid`, `AdapterFactory`, `Asso`), `Ccsd\User` models (`User`, `UserTokens`, `UserFtpQuota`) and `Episciences\User` entities covering pure logic without DB or network access.
- Updated `tests/README.md` with accurate Docker-based testing instructions, `make` target reference, subset-run examples, and contributor guidelines.
- [#883](https://github.com/CCSDForge/episciences/issues/883) Allow json files as attachments.
- Optional Webhook: It is now possible to report status changes to an external entry point (can be configured by journal).
- [#658](https://github.com/CCSDForge/episciences/issues/658) It is now possible to link an invitation that is not intended for you to your account.
- allow the co-author to view the publication.
- Statistics: the script has a new parameter `--all` - Process all statistics (with confirmation prompt).
- New option to allow Editors to receive 'Comments for editors' before declaring a conflict of interest (disabled by default).
- Volume Settings: add New configuration section(displayEmptyVolumes and allowEditVolumeTitleWithPublishedArticles) in journal settings.
- [#679](https://github.com/CCSDForge/episciences/issues/679) "Ask other editors for their opinion" form now includes chief editors (ROLE_CHIEF_EDITOR) in addition to regular editors (ROLE_EDITOR).
- [#691](http://github.com/CCSDForge/episciences/issues/691) Display "(optional)" label below comment and cover letter fields in submission forms to clarify these fields are not required.
- [#350](https://github.com/CCSDForge/episciences/issues/350) Author-Editor Communication Settings:
    - New option to allow authors to contact assigned editors (`authorEditorCommunication`)
    - New option to disclose editor names to authors or keep them anonymized (`discloseEditorNamesToAuthors`)
- feat(navigation): sync predefined page titles with T_PAGES table on menu save.
- New `library/Episciences/Api/` namespace with 5 injectable, independently-testable API clients: `AbstractApiClient`, `CrossrefApiClient`, `OpenAlexApiClient`, `OpenCitationsApiClient`, `OpenAireApiClient`.
- Five Symfony Console commands replacing the legacy JournalScript enrichment scripts: `enrichment:citations`, `enrichment:creators`, `enrichment:funding`, `enrichment:licences`, `enrichment:links`.
- Added MSC2020 Classification in ZBJATS export.
- `Episciences\Notify\NotifySourceConfig` — immutable value object holding per-source configuration (repoId, label, originId, originInbox, acceptedTypes); new repositories can be added without touching `InboxNotifications`
- `Episciences\Notify\NotifySourceRegistry` — registry with a `createFromConstants()` factory; resolves source config from a payload's `origin.inbox`
- `Episciences\Notify\ValidationResult` — pure value object wrapping a validation outcome (success / failure + error message)
- `Episciences\Notify\PreprintUrlParser` — pure helper extracting version and identifier from preprint landing-page URLs (HAL-style)
- `Episciences\Notify\PayloadValidator` — validates COAR Notify 1.0.1 payloads for Offer patterns (Request Review / Request Endorsement): all required fields including `object.id` (HTTP URI) and `object['ietf:item']` (id, type, mediaType)

### Changed

- [#947](https://github.com/CCSDForge/episciences/issues/947) [Feature request] wording on the platform - refused / rejected and refused/declined
- Refactored `public/js/paper/updateOrcidAuthors.js` from jQuery to modern vanilla ES6+ with a class-based architecture.
- Enhanced ORCID form validation: added duplicate detection while maintaining support for empty submissions for deletions.
- Update TinyMCE form 7.3.0 to 8.1.2.
- Increased Volume Description length to 1024 chars.
- DOI panel: requesting, saving, and cancelling a DOI no longer triggers a full page reload; success feedback message is suppressed.
- Refactored `Episciences_Paper_ProjectsManager` into `Episciences_Paper_Projects_Repository`, `Episciences_Paper_Projects_HalApiClient`, `Episciences_Paper_Projects_EnrichmentService`, and `Episciences_Paper_Projects_ViewFormatter`.
- Refactored `Episciences_Paper_CitationsManager` into `Episciences_Paper_Citations_Repository`, `Episciences_Paper_Citations_ViewFormatter`, `Episciences_Paper_Citations_EnrichmentService`, and `Episciences_Paper_Citations_Logger`.
- Redundant PHPDoc removed from all new citations classes via Rector.
- Replaced `Episciences_Cache` with `symfony/cache` 5.4 across all internal usages.
- `PapersManager::getList()`: removed `$cached` parameter; paper list is now always fetched fresh.
- `Review::getPapers()`, `CopyEditor::loadAssignedPapers()`, `Editor::loadAssignedPapers()`, `Reviewer::loadAssignedPapers()`, `Volume::getPaperListFromVolume()`: updated signatures and call sites following the removal of `$cached`.
- `Oai/Server::getIds()`: OAI resumption token cache migrated to PSR-6.
- Statistics: log import script now supports anonymized log files (.access_log_anonym.gz) as a fallback.
- Improved accessibility of the ORCID authors modal: proper `<label>` associations, `aria-modal="true"`, and corrected `aria-labelledby`.
- Modernized ORCID authors row layout using Flexbox.
- Standardized modal button styles for ORCID updates (Cancel: `btn-default`, Save: `btn-primary`).
- ORCID input fields: URLs are now automatically cleaned to keep only the 16-digit identifier.
- `administratepaper/view.phtml`: reordered panels; moved "Volumes & Rubriques" earlier; `paper_versions` moved to the bottom; removed redundant label.
- `paper/paper_datasets.phtml`: "Liens publications – données – logiciels" panel is now collapsed by default.
- `paper/paper_graphical_abstract.phtml`: graphical abstract panel is now collapsed by default when empty.
- `partials/coauthors.phtml`: "Ajouter un co-auteur" panel is now collapsed by default.
- `partials/paper_affiliation_authors.phtml`: "Ajouter une affiliation" panel is now collapsed by default.
- `volume/list.phtml`: Added "Number" and "Year" columns; improved table accessibility; Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: Removed redundant inline scripts; construction of the editors list optimized.
- Updated icons from `fas fa-address-card` to `fa-regular fa-address-card`.
- Improved visual indicators for editor availability in `editor-availability.js`.
- Refactored `scripts/zbjatZipper.php`: renamed class to `ZbjatZipper`, replaced `echo` with Monolog, extracted God method, switched to HTTPS.
- Refactored `Episciences_Paper_AuthorsManager` into `Episciences_Hal_TeiCacheManager`, `Episciences_Paper_Authors_HalTeiParser`, `Episciences_Paper_Authors_Repository`, `Episciences_Paper_Authors_EnrichmentService`, `Episciences_Paper_Authors_AffiliationHelper`, and `Episciences_Paper_Authors_ViewFormatter`.
- Refactored `Episciences_Paper_Authors_ViewFormatter` to separate data fetching from formatting logic.
- Moved `normalizeOrcid()` implementation from `AuthorsManager` to `HalTeiParser`.
- `AuthorsManager::ONE_MONTH` now references `TeiCacheManager::ONE_MONTH`.
- Added `TeiCacheManager::fetchAndGet()` to combine fetch-if-needed + read in a single call.
- The "Encapsulate reviewers" parameter is now hidden.
- [#528](https://github.com/CCSDForge/episciences/issues/528): 'upload a new version' on top; updated contact label.
- Paper metrics Refactored; 1 query instead of 2.
- The Locale is now stored in a cookie instead of the session.
- Redirect to the article to be reviewed when accepting an invitation.
- Allow volume years to be a string (AAAA or AAAA-AAAA).
- Synchronization of predefined page titles between navigation menu and T_PAGES table when saving menu configuration.
- Replaced `cottagelabs/coar-notifications` (Doctrine ORM + Guzzle) with `cottagelabs/coarnotify` (stateless pattern builder) and a custom PDO persistence layer (`Notification` DTO + `NotificationsRepository`)
- Refactored `InboxNotifications` (1390-line God Class) into focused private methods (`processFirstSubmission`, `processSubsequentSubmission`, `setupJournalTranslations`, `resolveNewVersionStatus`, `logVersionUpdate`, …) and pure, dependency-free helper classes
- `checkNotifyPayloads()` and `initSubmission()` are now source-aware: the COAR Notify source is resolved from `origin.inbox` via `NotifySourceRegistry`
- `InitSubmissionsFromHalInbox.php` is now a direct CLI entry point on `InboxNotifications`; the empty subclass has been removed

### Fixed

- [#858](https://github.com/CCSDForge/episciences/issues/858) fix: only use "et al." when 2+ authors are omitted. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Limit the editor’s permissions to allow assigning only the "reviewer" role.
- [#944](https://github.com/CCSDForge/episciences/issues/944): An author informed us that they received this message signed with their name: %%SENDER_FULL_NAME%% and %%RECIPIENT_SCREEN_NAME%% filled with the same value
- [#258](https://github.com/CCSDForge/episciences-front/issues/258): Tables are not handled by default: Explicit table conversion
- Fixed RT#277365: in order to correct the printing of extra lines, two options have been added to the TinyMCE configuration to insert a <br> instead of a <p> and another option to remove extra <br>s at the end of a block
- Fixed tooltip initialization in volume and section assignment after AJAX refreshes and DataTable redraws.
- Fixed Solr search engine indexation of Authors with a middle name.
- Consider an additional review when sending reminders for an insufficient number of reviewers.
- `Projects`: `$_dateUpdated` default fixed; `getDateUpdated()` return type consistency.
- `Projects::setFunding()`: made method fluent.
- `Projects::setOptions()`: fixed `setIdproject()` alias and case-insensitive method lookup.
- `Projects::toArray()`: fixed `DateTime` serialization.
- `Repository::insert()`: replaced deprecated `VALUES()` syntax with row-alias syntax for MySQL 8.0.20+.
- `Repository`: exceptions now propagate instead of terminating script on DB errors.
- `ViewFormatter`: fixed XSS by escaping `$vfunding['url']` and other user-controlled values in HTML attributes.
- `HalApiClient::doGet()`: added `E_USER_WARNING` severity level.
- `EnrichmentService::resolveHalProjectIds()`: removed duplicate log message; sanitized cache key.
- `Citations_ViewFormatter`: removed double escaping; fixed unstable compound sort; added ORCID format validation.
- `Citations_Repository`: replaced deprecated MySQL `VALUES()` function; added check for `$docId <= 0`.
- `Citations` entity: fixed `toArray()` key name; fixed `$_updatedAt` type and default.
- ORCID normalization: `cleanLowerCaseOrcid()` fix; applied normalization in Zenodo and ARCHE hooks.
- `findAffiliationsOneAuthorByPaperId()`: fixed potential undefined variable.
- `hasAcronym()`: fixed iteration over nested `id` array.
- `HalTeiParser::getAuthorsFromHalTei()`: fixed logic to prevent enriching the wrong author.
- `ViewFormatter::buildAffiliationListHtml()`: fixed Stored XSS by escaping the affiliation acronym.
- `ViewFormatter`: fixed `html_entity_decode(htmlspecialchars())` no-op.
- `EnrichmentService::mergeExistingAffiliations()`: fixed matching key lookup with `array_search()`.
- `AffiliationHelper::isAcronymDuplicate()`: now iterates all identifiers.
- `AffiliationHelper::setOrUpdateRorAcronym()`: returns first match deterministically.
- `TeiCacheManager::buildApiUrl()`: applied `urlencode()` to prevent Solr query injection.
- `TeiCacheManager::getFromCache()`: removed dead `expiresAfter()` call.
- `Repository`: fixed `JSON_DECODE_FLAGS`.
- `CommentsManager::$_typeLabel`: added missing entry for `TYPE_CONTRIBUTOR_TO_REVIEWER`.
- `CommentsManager::updateUid()`: fixed negative UID guard.
- `FormatIssn::FormatIssn()`: fixed substring length.
- `Log::log()`: fixed uncaught exception from `$logger->log()`.
- `DoiAsLink::DoiAsLink()`: fixed label when no text provided.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass with `preg_quote()` and anchors.
- Fixed RT#277365: added TinyMCE configuration options to handle `<br>` and `<p>` correctly.
- [#886](https://github.com/CCSDForge/episciences/issues/886): reminder logic for lack of reviewers fixed.
- `convertToBytes()`: fixed handling of pure numeric strings and added validation.
- `isHal()`, `isHalUrl()`, `isArxiv()`: fixed regex anchors.
- Added comprehensive test coverage for `Episciences_Tools`.
- [#236](https://github.com/CCSDForge/episciences-front/issues/236): HTML entities (&) displayed in the title.
- [#695](https://github.com/CCSDForge/episciences/issues/695) Fixed CC/BCC fields not clickable in paper status change forms.
- [#830](https://github.com/CCSDForge/episciences/issues/830) Paper number messed up for secondary volume; altered secondary volume rendering.
- [#776](https://github.com/CCSDForge/episciences/issues/776) Action Required: Fix Renovate Configuration.
- [#657](https://github.com/CCSDForge/episciences/issues/657) Conditionally remove the `<hr>` separator in metadata views.
- [#786](https://github.com/CCSDForge/episciences/issues/786) English translation of 'Télécharger le fichier'.
- [#646](https://github.com/CCSDForge/episciences/issues/646) Redirect to article page after submission.
- [#780](https://github.com/CCSDForge/episciences/issues/780) Option to lock volume name when an article is published.
- [#147](https://github.com/CCSDForge/episciences/issues/147) Add new pages Acknowledgements in menu 'About'.
- Fixed Paper Metrics based on wrong DocId.
- Fixed Pre-defined pages not deleted from the database.
- [#77](https://github.com/CCSDForge/episciences-front/issues/77) Fix orphan assignments when deleting sections or volumes.
- `CrossrefTools`: fixed fatal error on constant name; fixed raw `CacheItem` return.
- `OpenalexTools`: fixed `getPages()` return value and trailing separator in `getAuthors()`.
- `OpencitationsTools`: fixed caching of empty responses; fixed DOI extraction and updated API format support.
- `getLinkData`: switched to HTTPS; added null guards for `$valuesResult`.
- `getLicenceDataEnrichment`: fixed ineffective cache.
- `OpenAireResearchGraphTools`: enabled strict mode for `array_search()`.
- `Projects/EnrichmentService`: fixed log path and added `(int)` cast for source ID.
- `Citations/EnrichmentService`: added null guards for OpenAlex API fields.
- [#875](https://github.com/CCSDForge/episciences/issues/875): fixed HTML/Emoji translation issue.

### Deprecated

- `Episciences_Cache` and its parent `Ccsd_Cache` are now marked `@deprecated`; use `Symfony\Component\Cache\Adapter\FilesystemAdapter` instead.
- `ProjectsManager` kept as a thin backward-compatible facade.
- `CitationsManager` kept as backward-compatible facade with `@deprecated` proxies.
- `AuthorsManager` kept as orchestrator with backward-compatible `@deprecated` proxies.
- `Episciences_Paper_Visits::count` is deprecated.

### Removed

- Redundant "Statut actuel :" label prefix from the article status panel.
- Stray `<br>` and inlined `versionCache` script tag in `partials/paper_affiliation_authors.phtml`.
- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`.

### Security

- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`, eliminating a potential PHP object injection vector.
- OAI resumption token cache keys are now MD5-hashed before use, preventing cache-key injection.
- Escaped output in `volume/editors_list.phtml` to prevent potential XSS.
- Fixed XSS vulnerability in `Projects/ViewFormatter`: funding URL was interpolated unescaped into `href` attribute and link text.
- Fixed XSS in `Citations_ViewFormatter`: `href=` attributes for DOI and OA links were unquoted.
- Fixed XSS vulnerability in `ViewFormatter::buildAuthorHtml()` and `buildAffiliationListHtml()` regarding user-controlled values in HTML attributes.
- Fixed potential Solr query injection in `TeiCacheManager::buildApiUrl()`.
- `GetAvatar::asPaperStatusSvg()`: fixed two path traversal vectors ($lang sanitization and $paperStatus casting).
- `DoiAsLink::DoiAsLink()`: added `rel="noopener noreferrer"` to prevent tab-napping.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass that allowed authentication from unauthorized email domains.

### Performances

- `volume/list.phtml`: Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: construction of the editors list optimized.
- `Episciences_Paper_Citations_Logger`: Singleton Monolog logger (avoids Logger recreation on every call).
- Paper metrics Refactored; 1 query instead of 2.

## v1.0.55.3 - 2026-05-20

### Fixed

- [RT#287093]: Failed to submit "acceptedAskAuthorsFinalVersion" Form: CSRF validation failed (CSRF field missing)
- Adapt "acceptedAskAuthorsFinalVersion" form to the requirements for setting a deadline for requesting a revision after acceptance

### Changed

- [#1010](https://github.com/CCSDForge/episciences/issues/1010) The allowed range for modifying the deadline is now between the original deadline minus "rating_deadline_min" and the original deadline plus "rating_deadline_max".
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration
  button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)"

## v1.0.55.2 - 2026-05-19

### Fixed

- Improved article URL resolution in `ExtractBiblioRefsCommand` by adding fallbacks to `getMainPaperUrl()` and `getDocUrl()` when the repository template is empty (e.g., for Zenodo).
- Added debug logging for resolved article URLs and included raw response snippets in JSON decode error messages in `ExtractBiblioRefsCommand`.

## v1.0.55.1 - 2026-05-19

### Added

- Legend for bibliographic reference status icons in both admin and public views (publicly shown only when problematic references are detected).

### Changed

- Replaced bibliographic reference status indicators with standardized square icons (red `fa-square-xmark` for problematic, green `fa-square-check` for validated, and gray `fa-square` for automatically extracted references).
- Improved accessibility of bibliographic references (added ARIA landmarks, screen-reader-only status prefixes, descriptions for status badges, and warnings for links opening in new tabs).
- Optimized bibliographic references layout by moving the status legend inline with the action button.
- Renamed 'Not validated reference' to 'Automatically extracted reference' for clarity.
- Renamed 'Manage' button to 'Manage References' / 'Gérer les références' in the administration view.

### Fixed

- Removed obsolete BibTeX import button (feature migrated to an external application).
- Removed hardcoded Semantic Scholar source attribution from bibliographic references display.
- Skip temporary versions (without external repository URL) during bibliographic reference extraction.
- Fixed a missing dependency (`require_once`) for `GetDoiCommand` in `console.php`.

## v1.0.55 - 2026-05-18

### Added

- Automatic detection of problematic papers cited in the references of the papers. This is heavily based on the huge work provided by the Problematic Paper Screener https://dbrech.irit.fr/pls/apex/f?p=9999:1::::::
  For more information: Cabanac, G., Labbé, C., & Magazinov, A. (2022). The ‘Problematic Paper Screener’ automatically selects suspect publications for post-publication (re)assessment.
  Presented at WCRI 2022: 7th World Conference on Research Integrity. arXiv preprint. https://doi.org/10.48550/arXiv.2210.04895

- [#630](https://github.com/CCSDForge/episciences/issues/630) COI (Conflict of Interest) notifications:
    - Email notification to the editor-in-chief when an editor declares a positive COI (answered "yes")
    - Email notification to other assigned editors (if any) when an editor declares a COI
- Many legacy scripts migrated to Symfony Console commands (e.g., `doi:manage` replaces `getDoi.php`, `sitemap:generate --all`, translations update).
- Expanded unit test coverage for repository connectors (Zenodo, BioMedRxiv, ARCHE, Dspace) and COI notifications (+60 tests).

### Changed

- Sending the invitation in the user’s language, including when using the autocomplete input field for CAS users who already have a profile on the site.
- Simplified news add/edit form for a better user experience.
- Refactored OpenAIRE integration into a dedicated `OpenAireApiClient`.
- Improved PHP 8.1 compatibility and robustness across the codebase (removed deprecations and warnings).
- Optimized the submission workflow processing order.

### Fixed

- A bug that likely appeared in the latest update: section and/or volume editors are not assigned automatically, even if the relevant settings are enabled.
- Panel not opening when updating linked data
- Submission of a dataset without a data descriptor due to an error while uploading attached files (one of the file types exceeded the maximum allowed size for that field type)
- Corrected article position in volumes (1-based instead of 0-based) for CSL generation.
- Resolved relative `hydra:next` URLs in sitemap generation.
- Fixed several potential `TypeError` and crashes when metadata or publication dates are missing.

## v1.0.54.3 - 2026-04-23

### Fixed

- The link to the new version of the data descriptor isn't working; it still points to the first version.
- [RT#285106]: Application error when submitting with 'display secondary volume' option enabled
- Always SELECT RECORD so Paper::getMetadata() / toJson() can resolve titles and abstracts
- Log parser: extract IP from syslog-prefixed Apache log lines

## v1.0.54.2 - 2026-04-16

### Fixed

- Inability to change the review deadline (restore loading js via url).

### Added

- docs: add documentation index to README and update docs
- stats:import-logs command to parse Apache logs into STAT_TEMP. Introduces ImportApacheLogsCommand (stats:import-logs), a Symfony Console command that parses Apache access logs and inserts article visits into STAT_TEMP. Replaces the legacy UpdateStatistics.php . Run src/mysql/2025-08-24-stat-processing-log-table.sql before deploying

## v1.0.54.1 - 2026-04-14

### Changed

- Refactored English email templates, grammar only, no new features, no new tags

### Fixed

- [#962](https://github.com/CCSDForge/episciences/issues/962) Fixed double HTML encoding bug in volume metadata titles where special
  characters like `<`, `>`, and `'` were displayed as `&lt;`, `&gt;`, `&#039;`
- TypeError in enrichment:creators on non-sequential affiliation keys
- isImported detection for published papers when the the publication date has been fixed after publication
- [#985](https://github.com/CCSDForge/episciences/issues/985) Impossible to submit a final version
- Fixed bug in management of journal headers for adding and updating logo and texts

## v1.0.54 - 2026-04-09

### Added

- [#779](https://github.com/CCSDForge/episciences/issues/779) [Feature request] Display the secondary volume on the article page. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Adding "Publish" => "Proposing special issues" template page [#951](https://github.com/CCSDForge/episciences/issues/951)
- New supported servers:
    - Cryptology ePrint Archive (https://eprint.iacr.org)
    - Support for any data repositories powered by Dspace (eg. [repositorium.uminho(University of Minho)](https://repositorium.uminho.pt))
- Statistics: new Symfony Console commands `stats:process` (replaces legacy `scripts/stat.php`) and `stats:update-robots-list` for UA-based bot detection using the COUNTER Robots list; real client IP is now stored in `STAT_TEMP` and anonymized at processing time.
- Documentation: added `docs/console-commands.md` listing all 19 CLI commands available via `scripts/console.php` with options and usage examples.
- OAI-PMH: added `crossref` as a new metadata format (`metadataPrefix=crossref`), exposing Crossref 5.3.1 XML for each published paper. The depositor email is automatically replaced with a generic `noreply@{domain}` address in this context.
- Comprehensive Jest test suite for ORCID authors management (`tests/js/updateOrcidAuthors.test.js`).
- Unit tests for `Episciences_Paper_Projects` entity (setOptions aliases, DateTime handling, toArray serialization, fluent setters).
- Unit tests for `Episciences_Paper_Projects_EnrichmentService` pure functions (EU/ANR response normalization, OpenAIRE relation filtering).
- Unit tests for `Episciences_Paper_Projects_ViewFormatter` (empty-array return, URL HTML-escaping, unidentified title suppression).
- Unit tests for `Episciences_Paper_Citations_ViewFormatter` (20 tests: sort, author formatting, ORCID links, XSS prevention, book-chapter/proceedings-article reordering).
- Unit tests for `Episciences_Paper_Citations` entity (8 tests: `toArray()` key, fluent setters, nullable `updatedAt`, constructor).
- Comprehensive unit tests for `Episciences_Paper_Authors_ViewFormatter` covering HTML display logic and XSS prevention.
- Unit tests for `Episciences_View_Helper_DoiAsLink`, `Episciences_View_Helper_FormatIssn`, `Episciences_View_Helper_Log`, `Episciences_View_Helper_Tag`, `Episciences_View_Helper_UserAvatar`, `Episciences_View_Helper_GetAvatar` and `Episciences_CommentsManager`.
- 89+ unit tests for `Ccsd\Auth` adapters (`CasAbstract`, `Idp`, `Orcid`, `AdapterFactory`, `Asso`), `Ccsd\User` models (`User`, `UserTokens`, `UserFtpQuota`) and `Episciences\User` entities covering pure logic without DB or network access.
- Updated `tests/README.md` with accurate Docker-based testing instructions, `make` target reference, subset-run examples, and contributor guidelines.
- [#883](https://github.com/CCSDForge/episciences/issues/883) Allow json files as attachments.
- Optional Webhook: It is now possible to report status changes to an external entry point (can be configured by journal).
- [#658](https://github.com/CCSDForge/episciences/issues/658) It is now possible to link an invitation that is not intended for you to your account.
- allow the co-author to view the publication.
- Statistics: the script has a new parameter `--all` - Process all statistics (with confirmation prompt).
- New option to allow Editors to receive 'Comments for editors' before declaring a conflict of interest (disabled by default).
- Volume Settings: add New configuration section(displayEmptyVolumes and allowEditVolumeTitleWithPublishedArticles) in journal settings.
- [#679](https://github.com/CCSDForge/episciences/issues/679) "Ask other editors for their opinion" form now includes chief editors (ROLE_CHIEF_EDITOR) in addition to regular editors (ROLE_EDITOR).
- [#691](http://github.com/CCSDForge/episciences/issues/691) Display "(optional)" label below comment and cover letter fields in submission forms to clarify these fields are not required.
- [#350](https://github.com/CCSDForge/episciences/issues/350) Author-Editor Communication Settings:
    - New option to allow authors to contact assigned editors (`authorEditorCommunication`)
    - New option to disclose editor names to authors or keep them anonymized (`discloseEditorNamesToAuthors`)
- feat(navigation): sync predefined page titles with T_PAGES table on menu save.
- New `library/Episciences/Api/` namespace with 5 injectable, independently-testable API clients: `AbstractApiClient`, `CrossrefApiClient`, `OpenAlexApiClient`, `OpenCitationsApiClient`, `OpenAireApiClient`.
- Five Symfony Console commands replacing the legacy JournalScript enrichment scripts: `enrichment:citations`, `enrichment:creators`, `enrichment:funding`, `enrichment:licences`, `enrichment:links`.
- Added MSC2020 Classification in ZBJATS export.
- `Episciences\Notify\NotifySourceConfig` — immutable value object holding per-source configuration (repoId, label, originId, originInbox, acceptedTypes); new repositories can be added without touching `InboxNotifications`
- `Episciences\Notify\NotifySourceRegistry` — registry with a `createFromConstants()` factory; resolves source config from a payload's `origin.inbox`
- `Episciences\Notify\ValidationResult` — pure value object wrapping a validation outcome (success / failure + error message)
- `Episciences\Notify\PreprintUrlParser` — pure helper extracting version and identifier from preprint landing-page URLs (HAL-style)
- `Episciences\Notify\PayloadValidator` — validates COAR Notify 1.0.1 payloads for Offer patterns (Request Review / Request Endorsement): all required fields including `object.id` (HTTP URI) and `object['ietf:item']` (id, type, mediaType)

### Changed

- [#947](https://github.com/CCSDForge/episciences/issues/947) [Feature request] wording on the platform - refused / rejected and refused/declined
- Refactored `public/js/paper/updateOrcidAuthors.js` from jQuery to modern vanilla ES6+ with a class-based architecture.
- Enhanced ORCID form validation: added duplicate detection while maintaining support for empty submissions for deletions.
- Update TinyMCE form 7.3.0 to 8.1.2.
- Increased Volume Description length to 1024 chars.
- DOI panel: requesting, saving, and cancelling a DOI no longer triggers a full page reload; success feedback message is suppressed.
- Refactored `Episciences_Paper_ProjectsManager` into `Episciences_Paper_Projects_Repository`, `Episciences_Paper_Projects_HalApiClient`, `Episciences_Paper_Projects_EnrichmentService`, and `Episciences_Paper_Projects_ViewFormatter`.
- Refactored `Episciences_Paper_CitationsManager` into `Episciences_Paper_Citations_Repository`, `Episciences_Paper_Citations_ViewFormatter`, `Episciences_Paper_Citations_EnrichmentService`, and `Episciences_Paper_Citations_Logger`.
- Redundant PHPDoc removed from all new citations classes via Rector.
- Replaced `Episciences_Cache` with `symfony/cache` 5.4 across all internal usages.
- `PapersManager::getList()`: removed `$cached` parameter; paper list is now always fetched fresh.
- `Review::getPapers()`, `CopyEditor::loadAssignedPapers()`, `Editor::loadAssignedPapers()`, `Reviewer::loadAssignedPapers()`, `Volume::getPaperListFromVolume()`: updated signatures and call sites following the removal of `$cached`.
- `Oai/Server::getIds()`: OAI resumption token cache migrated to PSR-6.
- Statistics: log import script now supports anonymized log files (.access_log_anonym.gz) as a fallback.
- Improved accessibility of the ORCID authors modal: proper `<label>` associations, `aria-modal="true"`, and corrected `aria-labelledby`.
- Modernized ORCID authors row layout using Flexbox.
- Standardized modal button styles for ORCID updates (Cancel: `btn-default`, Save: `btn-primary`).
- ORCID input fields: URLs are now automatically cleaned to keep only the 16-digit identifier.
- `administratepaper/view.phtml`: reordered panels; moved "Volumes & Rubriques" earlier; `paper_versions` moved to the bottom; removed redundant label.
- `paper/paper_datasets.phtml`: "Liens publications – données – logiciels" panel is now collapsed by default.
- `paper/paper_graphical_abstract.phtml`: graphical abstract panel is now collapsed by default when empty.
- `partials/coauthors.phtml`: "Ajouter un co-auteur" panel is now collapsed by default.
- `partials/paper_affiliation_authors.phtml`: "Ajouter une affiliation" panel is now collapsed by default.
- `volume/list.phtml`: Added "Number" and "Year" columns; improved table accessibility; Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: Removed redundant inline scripts; construction of the editors list optimized.
- Updated icons from `fas fa-address-card` to `fa-regular fa-address-card`.
- Improved visual indicators for editor availability in `editor-availability.js`.
- Refactored `scripts/zbjatZipper.php`: renamed class to `ZbjatZipper`, replaced `echo` with Monolog, extracted God method, switched to HTTPS.
- Refactored `Episciences_Paper_AuthorsManager` into `Episciences_Hal_TeiCacheManager`, `Episciences_Paper_Authors_HalTeiParser`, `Episciences_Paper_Authors_Repository`, `Episciences_Paper_Authors_EnrichmentService`, `Episciences_Paper_Authors_AffiliationHelper`, and `Episciences_Paper_Authors_ViewFormatter`.
- Refactored `Episciences_Paper_Authors_ViewFormatter` to separate data fetching from formatting logic.
- Moved `normalizeOrcid()` implementation from `AuthorsManager` to `HalTeiParser`.
- `AuthorsManager::ONE_MONTH` now references `TeiCacheManager::ONE_MONTH`.
- Added `TeiCacheManager::fetchAndGet()` to combine fetch-if-needed + read in a single call.
- The "Encapsulate reviewers" parameter is now hidden.
- [#528](https://github.com/CCSDForge/episciences/issues/528): 'upload a new version' on top; updated contact label.
- Paper metrics Refactored; 1 query instead of 2.
- The Locale is now stored in a cookie instead of the session.
- Redirect to the article to be reviewed when accepting an invitation.
- Allow volume years to be a string (AAAA or AAAA-AAAA).
- Synchronization of predefined page titles between navigation menu and T_PAGES table when saving menu configuration.
- Replaced `cottagelabs/coar-notifications` (Doctrine ORM + Guzzle) with `cottagelabs/coarnotify` (stateless pattern builder) and a custom PDO persistence layer (`Notification` DTO + `NotificationsRepository`)
- Refactored `InboxNotifications` (1390-line God Class) into focused private methods (`processFirstSubmission`, `processSubsequentSubmission`, `setupJournalTranslations`, `resolveNewVersionStatus`, `logVersionUpdate`, …) and pure, dependency-free helper classes
- `checkNotifyPayloads()` and `initSubmission()` are now source-aware: the COAR Notify source is resolved from `origin.inbox` via `NotifySourceRegistry`
- `InitSubmissionsFromHalInbox.php` is now a direct CLI entry point on `InboxNotifications`; the empty subclass has been removed

### Fixed

- [#858](https://github.com/CCSDForge/episciences/issues/858) fix: only use "et al." when 2+ authors are omitted. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Limit the editor’s permissions to allow assigning only the "reviewer" role.
- [#944](https://github.com/CCSDForge/episciences/issues/944): An author informed us that they received this message signed with their name: %%SENDER_FULL_NAME%% and %%RECIPIENT_SCREEN_NAME%% filled with the same value
- [#258](https://github.com/CCSDForge/episciences-front/issues/258): Tables are not handled by default: Explicit table conversion
- Fixed RT#277365: in order to correct the printing of extra lines, two options have been added to the TinyMCE configuration to insert a <br> instead of a <p> and another option to remove extra <br>s at the end of a block
- Fixed tooltip initialization in volume and section assignment after AJAX refreshes and DataTable redraws.
- Fixed Solr search engine indexation of Authors with a middle name.
- Consider an additional review when sending reminders for an insufficient number of reviewers.
- `Projects`: `$_dateUpdated` default fixed; `getDateUpdated()` return type consistency.
- `Projects::setFunding()`: made method fluent.
- `Projects::setOptions()`: fixed `setIdproject()` alias and case-insensitive method lookup.
- `Projects::toArray()`: fixed `DateTime` serialization.
- `Repository::insert()`: replaced deprecated `VALUES()` syntax with row-alias syntax for MySQL 8.0.20+.
- `Repository`: exceptions now propagate instead of terminating script on DB errors.
- `ViewFormatter`: fixed XSS by escaping `$vfunding['url']` and other user-controlled values in HTML attributes.
- `HalApiClient::doGet()`: added `E_USER_WARNING` severity level.
- `EnrichmentService::resolveHalProjectIds()`: removed duplicate log message; sanitized cache key.
- `Citations_ViewFormatter`: removed double escaping; fixed unstable compound sort; added ORCID format validation.
- `Citations_Repository`: replaced deprecated MySQL `VALUES()` function; added check for `$docId <= 0`.
- `Citations` entity: fixed `toArray()` key name; fixed `$_updatedAt` type and default.
- ORCID normalization: `cleanLowerCaseOrcid()` fix; applied normalization in Zenodo and ARCHE hooks.
- `findAffiliationsOneAuthorByPaperId()`: fixed potential undefined variable.
- `hasAcronym()`: fixed iteration over nested `id` array.
- `HalTeiParser::getAuthorsFromHalTei()`: fixed logic to prevent enriching the wrong author.
- `ViewFormatter::buildAffiliationListHtml()`: fixed Stored XSS by escaping the affiliation acronym.
- `ViewFormatter`: fixed `html_entity_decode(htmlspecialchars())` no-op.
- `EnrichmentService::mergeExistingAffiliations()`: fixed matching key lookup with `array_search()`.
- `AffiliationHelper::isAcronymDuplicate()`: now iterates all identifiers.
- `AffiliationHelper::setOrUpdateRorAcronym()`: returns first match deterministically.
- `TeiCacheManager::buildApiUrl()`: applied `urlencode()` to prevent Solr query injection.
- `TeiCacheManager::getFromCache()`: removed dead `expiresAfter()` call.
- `Repository`: fixed `JSON_DECODE_FLAGS`.
- `CommentsManager::$_typeLabel`: added missing entry for `TYPE_CONTRIBUTOR_TO_REVIEWER`.
- `CommentsManager::updateUid()`: fixed negative UID guard.
- `FormatIssn::FormatIssn()`: fixed substring length.
- `Log::log()`: fixed uncaught exception from `$logger->log()`.
- `DoiAsLink::DoiAsLink()`: fixed label when no text provided.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass with `preg_quote()` and anchors.
- Fixed RT#277365: added TinyMCE configuration options to handle `<br>` and `<p>` correctly.
- [#886](https://github.com/CCSDForge/episciences/issues/886): reminder logic for lack of reviewers fixed.
- `convertToBytes()`: fixed handling of pure numeric strings and added validation.
- `isHal()`, `isHalUrl()`, `isArxiv()`: fixed regex anchors.
- Added comprehensive test coverage for `Episciences_Tools`.
- [#236](https://github.com/CCSDForge/episciences-front/issues/236): HTML entities (&) displayed in the title.
- [#695](https://github.com/CCSDForge/episciences/issues/695) Fixed CC/BCC fields not clickable in paper status change forms.
- [#830](https://github.com/CCSDForge/episciences/issues/830) Paper number messed up for secondary volume; altered secondary volume rendering.
- [#776](https://github.com/CCSDForge/episciences/issues/776) Action Required: Fix Renovate Configuration.
- [#657](https://github.com/CCSDForge/episciences/issues/657) Conditionally remove the `<hr>` separator in metadata views.
- [#786](https://github.com/CCSDForge/episciences/issues/786) English translation of 'Télécharger le fichier'.
- [#646](https://github.com/CCSDForge/episciences/issues/646) Redirect to article page after submission.
- [#780](https://github.com/CCSDForge/episciences/issues/780) Option to lock volume name when an article is published.
- [#147](https://github.com/CCSDForge/episciences/issues/147) Add new pages Acknowledgements in menu 'About'.
- Fixed Paper Metrics based on wrong DocId.
- Fixed Pre-defined pages not deleted from the database.
- [#77](https://github.com/CCSDForge/episciences-front/issues/77) Fix orphan assignments when deleting sections or volumes.
- `CrossrefTools`: fixed fatal error on constant name; fixed raw `CacheItem` return.
- `OpenalexTools`: fixed `getPages()` return value and trailing separator in `getAuthors()`.
- `OpencitationsTools`: fixed caching of empty responses; fixed DOI extraction and updated API format support.
- `getLinkData`: switched to HTTPS; added null guards for `$valuesResult`.
- `getLicenceDataEnrichment`: fixed ineffective cache.
- `OpenAireResearchGraphTools`: enabled strict mode for `array_search()`.
- `Projects/EnrichmentService`: fixed log path and added `(int)` cast for source ID.
- `Citations/EnrichmentService`: added null guards for OpenAlex API fields.
- [#875](https://github.com/CCSDForge/episciences/issues/875): fixed HTML/Emoji translation issue.

### Deprecated

- `Episciences_Cache` and its parent `Ccsd_Cache` are now marked `@deprecated`; use `Symfony\Component\Cache\Adapter\FilesystemAdapter` instead.
- `ProjectsManager` kept as a thin backward-compatible facade.
- `CitationsManager` kept as backward-compatible facade with `@deprecated` proxies.
- `AuthorsManager` kept as orchestrator with backward-compatible `@deprecated` proxies.
- `Episciences_Paper_Visits::count` is deprecated.

### Removed

- Redundant "Statut actuel :" label prefix from the article status panel.
- Stray `<br>` and inlined `versionCache` script tag in `partials/paper_affiliation_authors.phtml`.
- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`.

### Security

- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`, eliminating a potential PHP object injection vector.
- OAI resumption token cache keys are now MD5-hashed before use, preventing cache-key injection.
- Escaped output in `volume/editors_list.phtml` to prevent potential XSS.
- Fixed XSS vulnerability in `Projects/ViewFormatter`: funding URL was interpolated unescaped into `href` attribute and link text.
- Fixed XSS in `Citations_ViewFormatter`: `href=` attributes for DOI and OA links were unquoted.
- Fixed XSS vulnerability in `ViewFormatter::buildAuthorHtml()` and `buildAffiliationListHtml()` regarding user-controlled values in HTML attributes.
- Fixed potential Solr query injection in `TeiCacheManager::buildApiUrl()`.
- `GetAvatar::asPaperStatusSvg()`: fixed two path traversal vectors ($lang sanitization and $paperStatus casting).
- `DoiAsLink::DoiAsLink()`: added `rel="noopener noreferrer"` to prevent tab-napping.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass that allowed authentication from unauthorized email domains.

### Performances

- `volume/list.phtml`: Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: construction of the editors list optimized.
- `Episciences_Paper_Citations_Logger`: Singleton Monolog logger (avoids Logger recreation on every call).
- Paper metrics Refactored; 1 query instead of 2.

## v1.0.55.3 - 2026-05-20

### Fixed

- [RT#287093]: Failed to submit "acceptedAskAuthorsFinalVersion" Form: CSRF validation failed (CSRF field missing)
- Adapt "acceptedAskAuthorsFinalVersion" form to the requirements for setting a deadline for requesting a revision after acceptance

### Changed

- [#1010](https://github.com/CCSDForge/episciences/issues/1010) The allowed range for modifying the deadline is now between the original deadline minus "rating_deadline_min" and the original deadline plus "rating_deadline_max".
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration
  button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)"

## v1.0.55.2 - 2026-05-19

### Fixed

- Improved article URL resolution in `ExtractBiblioRefsCommand` by adding fallbacks to `getMainPaperUrl()` and `getDocUrl()` when the repository template is empty (e.g., for Zenodo).
- Added debug logging for resolved article URLs and included raw response snippets in JSON decode error messages in `ExtractBiblioRefsCommand`.

## v1.0.55.1 - 2026-05-19

### Added

- Legend for bibliographic reference status icons in both admin and public views (publicly shown only when problematic references are detected).

### Changed

- Replaced bibliographic reference status indicators with standardized square icons (red `fa-square-xmark` for problematic, green `fa-square-check` for validated, and gray `fa-square` for automatically extracted references).
- Improved accessibility of bibliographic references (added ARIA landmarks, screen-reader-only status prefixes, descriptions for status badges, and warnings for links opening in new tabs).
- Optimized bibliographic references layout by moving the status legend inline with the action button.
- Renamed 'Not validated reference' to 'Automatically extracted reference' for clarity.
- Renamed 'Manage' button to 'Manage References' / 'Gérer les références' in the administration view.

### Fixed

- Removed obsolete BibTeX import button (feature migrated to an external application).
- Removed hardcoded Semantic Scholar source attribution from bibliographic references display.
- Skip temporary versions (without external repository URL) during bibliographic reference extraction.
- Fixed a missing dependency (`require_once`) for `GetDoiCommand` in `console.php`.

## v1.0.55 - 2026-05-18

### Added

- Automatic detection of problematic papers cited in the references of the papers. This is heavily based on the huge work provided by the Problematic Paper Screener https://dbrech.irit.fr/pls/apex/f?p=9999:1::::::
  For more information: Cabanac, G., Labbé, C., & Magazinov, A. (2022). The ‘Problematic Paper Screener’ automatically selects suspect publications for post-publication (re)assessment.
  Presented at WCRI 2022: 7th World Conference on Research Integrity. arXiv preprint. https://doi.org/10.48550/arXiv.2210.04895

- [#630](https://github.com/CCSDForge/episciences/issues/630) COI (Conflict of Interest) notifications:
    - Email notification to the editor-in-chief when an editor declares a positive COI (answered "yes")
    - Email notification to other assigned editors (if any) when an editor declares a COI
- Many legacy scripts migrated to Symfony Console commands (e.g., `doi:manage` replaces `getDoi.php`, `sitemap:generate --all`, translations update).
- Expanded unit test coverage for repository connectors (Zenodo, BioMedRxiv, ARCHE, Dspace) and COI notifications (+60 tests).

### Changed

- Sending the invitation in the user’s language, including when using the autocomplete input field for CAS users who already have a profile on the site.
- Simplified news add/edit form for a better user experience.
- Refactored OpenAIRE integration into a dedicated `OpenAireApiClient`.
- Improved PHP 8.1 compatibility and robustness across the codebase (removed deprecations and warnings).
- Optimized the submission workflow processing order.

### Fixed

- A bug that likely appeared in the latest update: section and/or volume editors are not assigned automatically, even if the relevant settings are enabled.
- Panel not opening when updating linked data
- Submission of a dataset without a data descriptor due to an error while uploading attached files (one of the file types exceeded the maximum allowed size for that field type)
- Corrected article position in volumes (1-based instead of 0-based) for CSL generation.
- Resolved relative `hydra:next` URLs in sitemap generation.
- Fixed several potential `TypeError` and crashes when metadata or publication dates are missing.

## v1.0.54.3 - 2026-04-23

### Fixed

- The link to the new version of the data descriptor isn't working; it still points to the first version.
- [RT#285106]: Application error when submitting with 'display secondary volume' option enabled
- Always SELECT RECORD so Paper::getMetadata() / toJson() can resolve titles and abstracts
- Log parser: extract IP from syslog-prefixed Apache log lines

## v1.0.54.2 - 2026-04-16

### Fixed

- Inability to change the review deadline (restore loading js via url).

### Added

- docs: add documentation index to README and update docs
- stats:import-logs command to parse Apache logs into STAT_TEMP. Introduces ImportApacheLogsCommand (stats:import-logs), a Symfony Console command that parses Apache access logs and inserts article visits into STAT_TEMP. Replaces the legacy UpdateStatistics.php . Run src/mysql/2025-08-24-stat-processing-log-table.sql before deploying

## v1.0.54.1 - 2026-04-14

### Changed

- Refactored English email templates, grammar only, no new features, no new tags

### Fixed

- [#962](https://github.com/CCSDForge/episciences/issues/962) Fixed double HTML encoding bug in volume metadata titles where special
  characters like `<`, `>`, and `'` were displayed as `&lt;`, `&gt;`, `&#039;`
- TypeError in enrichment:creators on non-sequential affiliation keys
- isImported detection for published papers when the the publication date has been fixed after publication
- [#985](https://github.com/CCSDForge/episciences/issues/985) Impossible to submit a final version
- Fixed bug in management of journal headers for adding and updating logo and texts

## v1.0.54 - 2026-04-09

### Added

- [#779](https://github.com/CCSDForge/episciences/issues/779) [Feature request] Display the secondary volume on the article page. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Adding "Publish" => "Proposing special issues" template page [#951](https://github.com/CCSDForge/episciences/issues/951)
- New supported servers:
    - Cryptology ePrint Archive (https://eprint.iacr.org)
    - Support for any data repositories powered by Dspace (eg. [repositorium.uminho(University of Minho)](https://repositorium.uminho.pt))
- Statistics: new Symfony Console commands `stats:process` (replaces legacy `scripts/stat.php`) and `stats:update-robots-list` for UA-based bot detection using the COUNTER Robots list; real client IP is now stored in `STAT_TEMP` and anonymized at processing time.
- Documentation: added `docs/console-commands.md` listing all 19 CLI commands available via `scripts/console.php` with options and usage examples.
- OAI-PMH: added `crossref` as a new metadata format (`metadataPrefix=crossref`), exposing Crossref 5.3.1 XML for each published paper. The depositor email is automatically replaced with a generic `noreply@{domain}` address in this context.
- Comprehensive Jest test suite for ORCID authors management (`tests/js/updateOrcidAuthors.test.js`).
- Unit tests for `Episciences_Paper_Projects` entity (setOptions aliases, DateTime handling, toArray serialization, fluent setters).
- Unit tests for `Episciences_Paper_Projects_EnrichmentService` pure functions (EU/ANR response normalization, OpenAIRE relation filtering).
- Unit tests for `Episciences_Paper_Projects_ViewFormatter` (empty-array return, URL HTML-escaping, unidentified title suppression).
- Unit tests for `Episciences_Paper_Citations_ViewFormatter` (20 tests: sort, author formatting, ORCID links, XSS prevention, book-chapter/proceedings-article reordering).
- Unit tests for `Episciences_Paper_Citations` entity (8 tests: `toArray()` key, fluent setters, nullable `updatedAt`, constructor).
- Comprehensive unit tests for `Episciences_Paper_Authors_ViewFormatter` covering HTML display logic and XSS prevention.
- Unit tests for `Episciences_View_Helper_DoiAsLink`, `Episciences_View_Helper_FormatIssn`, `Episciences_View_Helper_Log`, `Episciences_View_Helper_Tag`, `Episciences_View_Helper_UserAvatar`, `Episciences_View_Helper_GetAvatar` and `Episciences_CommentsManager`.
- 89+ unit tests for `Ccsd\Auth` adapters (`CasAbstract`, `Idp`, `Orcid`, `AdapterFactory`, `Asso`), `Ccsd\User` models (`User`, `UserTokens`, `UserFtpQuota`) and `Episciences\User` entities covering pure logic without DB or network access.
- Updated `tests/README.md` with accurate Docker-based testing instructions, `make` target reference, subset-run examples, and contributor guidelines.
- [#883](https://github.com/CCSDForge/episciences/issues/883) Allow json files as attachments.
- Optional Webhook: It is now possible to report status changes to an external entry point (can be configured by journal).
- [#658](https://github.com/CCSDForge/episciences/issues/658) It is now possible to link an invitation that is not intended for you to your account.
- allow the co-author to view the publication.
- Statistics: the script has a new parameter `--all` - Process all statistics (with confirmation prompt).
- New option to allow Editors to receive 'Comments for editors' before declaring a conflict of interest (disabled by default).
- Volume Settings: add New configuration section(displayEmptyVolumes and allowEditVolumeTitleWithPublishedArticles) in journal settings.
- [#679](https://github.com/CCSDForge/episciences/issues/679) "Ask other editors for their opinion" form now includes chief editors (ROLE_CHIEF_EDITOR) in addition to regular editors (ROLE_EDITOR).
- [#691](http://github.com/CCSDForge/episciences/issues/691) Display "(optional)" label below comment and cover letter fields in submission forms to clarify these fields are not required.
- [#350](https://github.com/CCSDForge/episciences/issues/350) Author-Editor Communication Settings:
    - New option to allow authors to contact assigned editors (`authorEditorCommunication`)
    - New option to disclose editor names to authors or keep them anonymized (`discloseEditorNamesToAuthors`)
- feat(navigation): sync predefined page titles with T_PAGES table on menu save.
- New `library/Episciences/Api/` namespace with 5 injectable, independently-testable API clients: `AbstractApiClient`, `CrossrefApiClient`, `OpenAlexApiClient`, `OpenCitationsApiClient`, `OpenAireApiClient`.
- Five Symfony Console commands replacing the legacy JournalScript enrichment scripts: `enrichment:citations`, `enrichment:creators`, `enrichment:funding`, `enrichment:licences`, `enrichment:links`.
- Added MSC2020 Classification in ZBJATS export.
- `Episciences\Notify\NotifySourceConfig` — immutable value object holding per-source configuration (repoId, label, originId, originInbox, acceptedTypes); new repositories can be added without touching `InboxNotifications`
- `Episciences\Notify\NotifySourceRegistry` — registry with a `createFromConstants()` factory; resolves source config from a payload's `origin.inbox`
- `Episciences\Notify\ValidationResult` — pure value object wrapping a validation outcome (success / failure + error message)
- `Episciences\Notify\PreprintUrlParser` — pure helper extracting version and identifier from preprint landing-page URLs (HAL-style)
- `Episciences\Notify\PayloadValidator` — validates COAR Notify 1.0.1 payloads for Offer patterns (Request Review / Request Endorsement): all required fields including `object.id` (HTTP URI) and `object['ietf:item']` (id, type, mediaType)

### Changed

- [#947](https://github.com/CCSDForge/episciences/issues/947) [Feature request] wording on the platform - refused / rejected and refused/declined
- Refactored `public/js/paper/updateOrcidAuthors.js` from jQuery to modern vanilla ES6+ with a class-based architecture.
- Enhanced ORCID form validation: added duplicate detection while maintaining support for empty submissions for deletions.
- Update TinyMCE form 7.3.0 to 8.1.2.
- Increased Volume Description length to 1024 chars.
- DOI panel: requesting, saving, and cancelling a DOI no longer triggers a full page reload; success feedback message is suppressed.
- Refactored `Episciences_Paper_ProjectsManager` into `Episciences_Paper_Projects_Repository`, `Episciences_Paper_Projects_HalApiClient`, `Episciences_Paper_Projects_EnrichmentService`, and `Episciences_Paper_Projects_ViewFormatter`.
- Refactored `Episciences_Paper_CitationsManager` into `Episciences_Paper_Citations_Repository`, `Episciences_Paper_Citations_ViewFormatter`, `Episciences_Paper_Citations_EnrichmentService`, and `Episciences_Paper_Citations_Logger`.
- Redundant PHPDoc removed from all new citations classes via Rector.
- Replaced `Episciences_Cache` with `symfony/cache` 5.4 across all internal usages.
- `PapersManager::getList()`: removed `$cached` parameter; paper list is now always fetched fresh.
- `Review::getPapers()`, `CopyEditor::loadAssignedPapers()`, `Editor::loadAssignedPapers()`, `Reviewer::loadAssignedPapers()`, `Volume::getPaperListFromVolume()`: updated signatures and call sites following the removal of `$cached`.
- `Oai/Server::getIds()`: OAI resumption token cache migrated to PSR-6.
- Statistics: log import script now supports anonymized log files (.access_log_anonym.gz) as a fallback.
- Improved accessibility of the ORCID authors modal: proper `<label>` associations, `aria-modal="true"`, and corrected `aria-labelledby`.
- Modernized ORCID authors row layout using Flexbox.
- Standardized modal button styles for ORCID updates (Cancel: `btn-default`, Save: `btn-primary`).
- ORCID input fields: URLs are now automatically cleaned to keep only the 16-digit identifier.
- `administratepaper/view.phtml`: reordered panels; moved "Volumes & Rubriques" earlier; `paper_versions` moved to the bottom; removed redundant label.
- `paper/paper_datasets.phtml`: "Liens publications – données – logiciels" panel is now collapsed by default.
- `paper/paper_graphical_abstract.phtml`: graphical abstract panel is now collapsed by default when empty.
- `partials/coauthors.phtml`: "Ajouter un co-auteur" panel is now collapsed by default.
- `partials/paper_affiliation_authors.phtml`: "Ajouter une affiliation" panel is now collapsed by default.
- `volume/list.phtml`: Added "Number" and "Year" columns; improved table accessibility; Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: Removed redundant inline scripts; construction of the editors list optimized.
- Updated icons from `fas fa-address-card` to `fa-regular fa-address-card`.
- Improved visual indicators for editor availability in `editor-availability.js`.
- Refactored `scripts/zbjatZipper.php`: renamed class to `ZbjatZipper`, replaced `echo` with Monolog, extracted God method, switched to HTTPS.
- Refactored `Episciences_Paper_AuthorsManager` into `Episciences_Hal_TeiCacheManager`, `Episciences_Paper_Authors_HalTeiParser`, `Episciences_Paper_Authors_Repository`, `Episciences_Paper_Authors_EnrichmentService`, `Episciences_Paper_Authors_AffiliationHelper`, and `Episciences_Paper_Authors_ViewFormatter`.
- Refactored `Episciences_Paper_Authors_ViewFormatter` to separate data fetching from formatting logic.
- Moved `normalizeOrcid()` implementation from `AuthorsManager` to `HalTeiParser`.
- `AuthorsManager::ONE_MONTH` now references `TeiCacheManager::ONE_MONTH`.
- Added `TeiCacheManager::fetchAndGet()` to combine fetch-if-needed + read in a single call.
- The "Encapsulate reviewers" parameter is now hidden.
- [#528](https://github.com/CCSDForge/episciences/issues/528): 'upload a new version' on top; updated contact label.
- Paper metrics Refactored; 1 query instead of 2.
- The Locale is now stored in a cookie instead of the session.
- Redirect to the article to be reviewed when accepting an invitation.
- Allow volume years to be a string (AAAA or AAAA-AAAA).
- Synchronization of predefined page titles between navigation menu and T_PAGES table when saving menu configuration.
- Replaced `cottagelabs/coar-notifications` (Doctrine ORM + Guzzle) with `cottagelabs/coarnotify` (stateless pattern builder) and a custom PDO persistence layer (`Notification` DTO + `NotificationsRepository`)
- Refactored `InboxNotifications` (1390-line God Class) into focused private methods (`processFirstSubmission`, `processSubsequentSubmission`, `setupJournalTranslations`, `resolveNewVersionStatus`, `logVersionUpdate`, …) and pure, dependency-free helper classes
- `checkNotifyPayloads()` and `initSubmission()` are now source-aware: the COAR Notify source is resolved from `origin.inbox` via `NotifySourceRegistry`
- `InitSubmissionsFromHalInbox.php` is now a direct CLI entry point on `InboxNotifications`; the empty subclass has been removed

### Fixed

- [#858](https://github.com/CCSDForge/episciences/issues/858) fix: only use "et al." when 2+ authors are omitted. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Limit the editor’s permissions to allow assigning only the "reviewer" role.
- [#944](https://github.com/CCSDForge/episciences/issues/944): An author informed us that they received this message signed with their name: %%SENDER_FULL_NAME%% and %%RECIPIENT_SCREEN_NAME%% filled with the same value
- [#258](https://github.com/CCSDForge/episciences-front/issues/258): Tables are not handled by default: Explicit table conversion
- Fixed RT#277365: in order to correct the printing of extra lines, two options have been added to the TinyMCE configuration to insert a <br> instead of a <p> and another option to remove extra <br>s at the end of a block
- Fixed tooltip initialization in volume and section assignment after AJAX refreshes and DataTable redraws.
- Fixed Solr search engine indexation of Authors with a middle name.
- Consider an additional review when sending reminders for an insufficient number of reviewers.
- `Projects`: `$_dateUpdated` default fixed; `getDateUpdated()` return type consistency.
- `Projects::setFunding()`: made method fluent.
- `Projects::setOptions()`: fixed `setIdproject()` alias and case-insensitive method lookup.
- `Projects::toArray()`: fixed `DateTime` serialization.
- `Repository::insert()`: replaced deprecated `VALUES()` syntax with row-alias syntax for MySQL 8.0.20+.
- `Repository`: exceptions now propagate instead of terminating script on DB errors.
- `ViewFormatter`: fixed XSS by escaping `$vfunding['url']` and other user-controlled values in HTML attributes.
- `HalApiClient::doGet()`: added `E_USER_WARNING` severity level.
- `EnrichmentService::resolveHalProjectIds()`: removed duplicate log message; sanitized cache key.
- `Citations_ViewFormatter`: removed double escaping; fixed unstable compound sort; added ORCID format validation.
- `Citations_Repository`: replaced deprecated MySQL `VALUES()` function; added check for `$docId <= 0`.
- `Citations` entity: fixed `toArray()` key name; fixed `$_updatedAt` type and default.
- ORCID normalization: `cleanLowerCaseOrcid()` fix; applied normalization in Zenodo and ARCHE hooks.
- `findAffiliationsOneAuthorByPaperId()`: fixed potential undefined variable.
- `hasAcronym()`: fixed iteration over nested `id` array.
- `HalTeiParser::getAuthorsFromHalTei()`: fixed logic to prevent enriching the wrong author.
- `ViewFormatter::buildAffiliationListHtml()`: fixed Stored XSS by escaping the affiliation acronym.
- `ViewFormatter`: fixed `html_entity_decode(htmlspecialchars())` no-op.
- `EnrichmentService::mergeExistingAffiliations()`: fixed matching key lookup with `array_search()`.
- `AffiliationHelper::isAcronymDuplicate()`: now iterates all identifiers.
- `AffiliationHelper::setOrUpdateRorAcronym()`: returns first match deterministically.
- `TeiCacheManager::buildApiUrl()`: applied `urlencode()` to prevent Solr query injection.
- `TeiCacheManager::getFromCache()`: removed dead `expiresAfter()` call.
- `Repository`: fixed `JSON_DECODE_FLAGS`.
- `CommentsManager::$_typeLabel`: added missing entry for `TYPE_CONTRIBUTOR_TO_REVIEWER`.
- `CommentsManager::updateUid()`: fixed negative UID guard.
- `FormatIssn::FormatIssn()`: fixed substring length.
- `Log::log()`: fixed uncaught exception from `$logger->log()`.
- `DoiAsLink::DoiAsLink()`: fixed label when no text provided.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass with `preg_quote()` and anchors.
- Fixed RT#277365: added TinyMCE configuration options to handle `<br>` and `<p>` correctly.
- [#886](https://github.com/CCSDForge/episciences/issues/886): reminder logic for lack of reviewers fixed.
- `convertToBytes()`: fixed handling of pure numeric strings and added validation.
- `isHal()`, `isHalUrl()`, `isArxiv()`: fixed regex anchors.
- Added comprehensive test coverage for `Episciences_Tools`.
- [#236](https://github.com/CCSDForge/episciences-front/issues/236): HTML entities (&) displayed in the title.
- [#695](https://github.com/CCSDForge/episciences/issues/695) Fixed CC/BCC fields not clickable in paper status change forms.
- [#830](https://github.com/CCSDForge/episciences/issues/830) Paper number messed up for secondary volume; altered secondary volume rendering.
- [#776](https://github.com/CCSDForge/episciences/issues/776) Action Required: Fix Renovate Configuration.
- [#657](https://github.com/CCSDForge/episciences/issues/657) Conditionally remove the `<hr>` separator in metadata views.
- [#786](https://github.com/CCSDForge/episciences/issues/786) English translation of 'Télécharger le fichier'.
- [#646](https://github.com/CCSDForge/episciences/issues/646) Redirect to article page after submission.
- [#780](https://github.com/CCSDForge/episciences/issues/780) Option to lock volume name when an article is published.
- [#147](https://github.com/CCSDForge/episciences/issues/147) Add new pages Acknowledgements in menu 'About'.
- Fixed Paper Metrics based on wrong DocId.
- Fixed Pre-defined pages not deleted from the database.
- [#77](https://github.com/CCSDForge/episciences-front/issues/77) Fix orphan assignments when deleting sections or volumes.
- `CrossrefTools`: fixed fatal error on constant name; fixed raw `CacheItem` return.
- `OpenalexTools`: fixed `getPages()` return value and trailing separator in `getAuthors()`.
- `OpencitationsTools`: fixed caching of empty responses; fixed DOI extraction and updated API format support.
- `getLinkData`: switched to HTTPS; added null guards for `$valuesResult`.
- `getLicenceDataEnrichment`: fixed ineffective cache.
- `OpenAireResearchGraphTools`: enabled strict mode for `array_search()`.
- `Projects/EnrichmentService`: fixed log path and added `(int)` cast for source ID.
- `Citations/EnrichmentService`: added null guards for OpenAlex API fields.
- [#875](https://github.com/CCSDForge/episciences/issues/875): fixed HTML/Emoji translation issue.

### Deprecated

- `Episciences_Cache` and its parent `Ccsd_Cache` are now marked `@deprecated`; use `Symfony\Component\Cache\Adapter\FilesystemAdapter` instead.
- `ProjectsManager` kept as a thin backward-compatible facade.
- `CitationsManager` kept as backward-compatible facade with `@deprecated` proxies.
- `AuthorsManager` kept as orchestrator with backward-compatible `@deprecated` proxies.
- `Episciences_Paper_Visits::count` is deprecated.

### Removed

- Redundant "Statut actuel :" label prefix from the article status panel.
- Stray `<br>` and inlined `versionCache` script tag in `partials/paper_affiliation_authors.phtml`.
- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`.

### Security

- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`, eliminating a potential PHP object injection vector.
- OAI resumption token cache keys are now MD5-hashed before use, preventing cache-key injection.
- Escaped output in `volume/editors_list.phtml` to prevent potential XSS.
- Fixed XSS vulnerability in `Projects/ViewFormatter`: funding URL was interpolated unescaped into `href` attribute and link text.
- Fixed XSS in `Citations_ViewFormatter`: `href=` attributes for DOI and OA links were unquoted.
- Fixed XSS vulnerability in `ViewFormatter::buildAuthorHtml()` and `buildAffiliationListHtml()` regarding user-controlled values in HTML attributes.
- Fixed potential Solr query injection in `TeiCacheManager::buildApiUrl()`.
- `GetAvatar::asPaperStatusSvg()`: fixed two path traversal vectors ($lang sanitization and $paperStatus casting).
- `DoiAsLink::DoiAsLink()`: added `rel="noopener noreferrer"` to prevent tab-napping.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass that allowed authentication from unauthorized email domains.

### Performances

- `volume/list.phtml`: Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: construction of the editors list optimized.
- `Episciences_Paper_Citations_Logger`: Singleton Monolog logger (avoids Logger recreation on every call).
- Paper metrics Refactored; 1 query instead of 2.

## v1.0.55.3 - 2026-05-20

### Fixed

- [RT#287093]: Failed to submit "acceptedAskAuthorsFinalVersion" Form: CSRF validation failed (CSRF field missing)
- Adapt "acceptedAskAuthorsFinalVersion" form to the requirements for setting a deadline for requesting a revision after acceptance

### Changed

- [#1010](https://github.com/CCSDForge/episciences/issues/1010) The allowed range for modifying the deadline is now between the original deadline minus "rating_deadline_min" and the original deadline plus "rating_deadline_max".
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration
  button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)"

## v1.0.55.2 - 2026-05-19

### Fixed

- Improved article URL resolution in `ExtractBiblioRefsCommand` by adding fallbacks to `getMainPaperUrl()` and `getDocUrl()` when the repository template is empty (e.g., for Zenodo).
- Added debug logging for resolved article URLs and included raw response snippets in JSON decode error messages in `ExtractBiblioRefsCommand`.

## v1.0.55.1 - 2026-05-19

### Added

- Legend for bibliographic reference status icons in both admin and public views (publicly shown only when problematic references are detected).

### Changed

- Replaced bibliographic reference status indicators with standardized square icons (red `fa-square-xmark` for problematic, green `fa-square-check` for validated, and gray `fa-square` for automatically extracted references).
- Improved accessibility of bibliographic references (added ARIA landmarks, screen-reader-only status prefixes, descriptions for status badges, and warnings for links opening in new tabs).
- Optimized bibliographic references layout by moving the status legend inline with the action button.
- Renamed 'Not validated reference' to 'Automatically extracted reference' for clarity.
- Renamed 'Manage' button to 'Manage References' / 'Gérer les références' in the administration view.

### Fixed

- Removed obsolete BibTeX import button (feature migrated to an external application).
- Removed hardcoded Semantic Scholar source attribution from bibliographic references display.
- Skip temporary versions (without external repository URL) during bibliographic reference extraction.
- Fixed a missing dependency (`require_once`) for `GetDoiCommand` in `console.php`.

## v1.0.55 - 2026-05-18

### Added

- Automatic detection of problematic papers cited in the references of the papers. This is heavily based on the huge work provided by the Problematic Paper Screener https://dbrech.irit.fr/pls/apex/f?p=9999:1::::::
  For more information: Cabanac, G., Labbé, C., & Magazinov, A. (2022). The ‘Problematic Paper Screener’ automatically selects suspect publications for post-publication (re)assessment.
  Presented at WCRI 2022: 7th World Conference on Research Integrity. arXiv preprint. https://doi.org/10.48550/arXiv.2210.04895

- [#630](https://github.com/CCSDForge/episciences/issues/630) COI (Conflict of Interest) notifications:
    - Email notification to the editor-in-chief when an editor declares a positive COI (answered "yes")
    - Email notification to other assigned editors (if any) when an editor declares a COI
- Many legacy scripts migrated to Symfony Console commands (e.g., `doi:manage` replaces `getDoi.php`, `sitemap:generate --all`, translations update).
- Expanded unit test coverage for repository connectors (Zenodo, BioMedRxiv, ARCHE, Dspace) and COI notifications (+60 tests).

### Changed

- Sending the invitation in the user’s language, including when using the autocomplete input field for CAS users who already have a profile on the site.
- Simplified news add/edit form for a better user experience.
- Refactored OpenAIRE integration into a dedicated `OpenAireApiClient`.
- Improved PHP 8.1 compatibility and robustness across the codebase (removed deprecations and warnings).
- Optimized the submission workflow processing order.

### Fixed

- A bug that likely appeared in the latest update: section and/or volume editors are not assigned automatically, even if the relevant settings are enabled.
- Panel not opening when updating linked data
- Submission of a dataset without a data descriptor due to an error while uploading attached files (one of the file types exceeded the maximum allowed size for that field type)
- Corrected article position in volumes (1-based instead of 0-based) for CSL generation.
- Resolved relative `hydra:next` URLs in sitemap generation.
- Fixed several potential `TypeError` and crashes when metadata or publication dates are missing.

## v1.0.54.3 - 2026-04-23

### Fixed

- The link to the new version of the data descriptor isn't working; it still points to the first version.
- [RT#285106]: Application error when submitting with 'display secondary volume' option enabled
- Always SELECT RECORD so Paper::getMetadata() / toJson() can resolve titles and abstracts
- Log parser: extract IP from syslog-prefixed Apache log lines

## v1.0.54.2 - 2026-04-16

### Fixed

- Inability to change the review deadline (restore loading js via url).

### Added

- docs: add documentation index to README and update docs
- stats:import-logs command to parse Apache logs into STAT_TEMP. Introduces ImportApacheLogsCommand (stats:import-logs), a Symfony Console command that parses Apache access logs and inserts article visits into STAT_TEMP. Replaces the legacy UpdateStatistics.php . Run src/mysql/2025-08-24-stat-processing-log-table.sql before deploying

## v1.0.54.1 - 2026-04-14

### Changed

- Refactored English email templates, grammar only, no new features, no new tags

### Fixed

- [#962](https://github.com/CCSDForge/episciences/issues/962) Fixed double HTML encoding bug in volume metadata titles where special
  characters like `<`, `>`, and `'` were displayed as `&lt;`, `&gt;`, `&#039;`
- TypeError in enrichment:creators on non-sequential affiliation keys
- isImported detection for published papers when the the publication date has been fixed after publication
- [#985](https://github.com/CCSDForge/episciences/issues/985) Impossible to submit a final version
- Fixed bug in management of journal headers for adding and updating logo and texts

## v1.0.54 - 2026-04-09

### Added

- [#779](https://github.com/CCSDForge/episciences/issues/779) [Feature request] Display the secondary volume on the article page. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Adding "Publish" => "Proposing special issues" template page [#951](https://github.com/CCSDForge/episciences/issues/951)
- New supported servers:
    - Cryptology ePrint Archive (https://eprint.iacr.org)
    - Support for any data repositories powered by Dspace (eg. [repositorium.uminho(University of Minho)](https://repositorium.uminho.pt))
- Statistics: new Symfony Console commands `stats:process` (replaces legacy `scripts/stat.php`) and `stats:update-robots-list` for UA-based bot detection using the COUNTER Robots list; real client IP is now stored in `STAT_TEMP` and anonymized at processing time.
- Documentation: added `docs/console-commands.md` listing all 19 CLI commands available via `scripts/console.php` with options and usage examples.
- OAI-PMH: added `crossref` as a new metadata format (`metadataPrefix=crossref`), exposing Crossref 5.3.1 XML for each published paper. The depositor email is automatically replaced with a generic `noreply@{domain}` address in this context.
- Comprehensive Jest test suite for ORCID authors management (`tests/js/updateOrcidAuthors.test.js`).
- Unit tests for `Episciences_Paper_Projects` entity (setOptions aliases, DateTime handling, toArray serialization, fluent setters).
- Unit tests for `Episciences_Paper_Projects_EnrichmentService` pure functions (EU/ANR response normalization, OpenAIRE relation filtering).
- Unit tests for `Episciences_Paper_Projects_ViewFormatter` (empty-array return, URL HTML-escaping, unidentified title suppression).
- Unit tests for `Episciences_Paper_Citations_ViewFormatter` (20 tests: sort, author formatting, ORCID links, XSS prevention, book-chapter/proceedings-article reordering).
- Unit tests for `Episciences_Paper_Citations` entity (8 tests: `toArray()` key, fluent setters, nullable `updatedAt`, constructor).
- Comprehensive unit tests for `Episciences_Paper_Authors_ViewFormatter` covering HTML display logic and XSS prevention.
- Unit tests for `Episciences_View_Helper_DoiAsLink`, `Episciences_View_Helper_FormatIssn`, `Episciences_View_Helper_Log`, `Episciences_View_Helper_Tag`, `Episciences_View_Helper_UserAvatar`, `Episciences_View_Helper_GetAvatar` and `Episciences_CommentsManager`.
- 89+ unit tests for `Ccsd\Auth` adapters (`CasAbstract`, `Idp`, `Orcid`, `AdapterFactory`, `Asso`), `Ccsd\User` models (`User`, `UserTokens`, `UserFtpQuota`) and `Episciences\User` entities covering pure logic without DB or network access.
- Updated `tests/README.md` with accurate Docker-based testing instructions, `make` target reference, subset-run examples, and contributor guidelines.
- [#883](https://github.com/CCSDForge/episciences/issues/883) Allow json files as attachments.
- Optional Webhook: It is now possible to report status changes to an external entry point (can be configured by journal).
- [#658](https://github.com/CCSDForge/episciences/issues/658) It is now possible to link an invitation that is not intended for you to your account.
- allow the co-author to view the publication.
- Statistics: the script has a new parameter `--all` - Process all statistics (with confirmation prompt).
- New option to allow Editors to receive 'Comments for editors' before declaring a conflict of interest (disabled by default).
- Volume Settings: add New configuration section(displayEmptyVolumes and allowEditVolumeTitleWithPublishedArticles) in journal settings.
- [#679](https://github.com/CCSDForge/episciences/issues/679) "Ask other editors for their opinion" form now includes chief editors (ROLE_CHIEF_EDITOR) in addition to regular editors (ROLE_EDITOR).
- [#691](http://github.com/CCSDForge/episciences/issues/691) Display "(optional)" label below comment and cover letter fields in submission forms to clarify these fields are not required.
- [#350](https://github.com/CCSDForge/episciences/issues/350) Author-Editor Communication Settings:
    - New option to allow authors to contact assigned editors (`authorEditorCommunication`)
    - New option to disclose editor names to authors or keep them anonymized (`discloseEditorNamesToAuthors`)
- feat(navigation): sync predefined page titles with T_PAGES table on menu save.
- New `library/Episciences/Api/` namespace with 5 injectable, independently-testable API clients: `AbstractApiClient`, `CrossrefApiClient`, `OpenAlexApiClient`, `OpenCitationsApiClient`, `OpenAireApiClient`.
- Five Symfony Console commands replacing the legacy JournalScript enrichment scripts: `enrichment:citations`, `enrichment:creators`, `enrichment:funding`, `enrichment:licences`, `enrichment:links`.
- Added MSC2020 Classification in ZBJATS export.
- `Episciences\Notify\NotifySourceConfig` — immutable value object holding per-source configuration (repoId, label, originId, originInbox, acceptedTypes); new repositories can be added without touching `InboxNotifications`
- `Episciences\Notify\NotifySourceRegistry` — registry with a `createFromConstants()` factory; resolves source config from a payload's `origin.inbox`
- `Episciences\Notify\ValidationResult` — pure value object wrapping a validation outcome (success / failure + error message)
- `Episciences\Notify\PreprintUrlParser` — pure helper extracting version and identifier from preprint landing-page URLs (HAL-style)
- `Episciences\Notify\PayloadValidator` — validates COAR Notify 1.0.1 payloads for Offer patterns (Request Review / Request Endorsement): all required fields including `object.id` (HTTP URI) and `object['ietf:item']` (id, type, mediaType)

### Changed

- [#947](https://github.com/CCSDForge/episciences/issues/947) [Feature request] wording on the platform - refused / rejected and refused/declined
- Refactored `public/js/paper/updateOrcidAuthors.js` from jQuery to modern vanilla ES6+ with a class-based architecture.
- Enhanced ORCID form validation: added duplicate detection while maintaining support for empty submissions for deletions.
- Update TinyMCE form 7.3.0 to 8.1.2.
- Increased Volume Description length to 1024 chars.
- DOI panel: requesting, saving, and cancelling a DOI no longer triggers a full page reload; success feedback message is suppressed.
- Refactored `Episciences_Paper_ProjectsManager` into `Episciences_Paper_Projects_Repository`, `Episciences_Paper_Projects_HalApiClient`, `Episciences_Paper_Projects_EnrichmentService`, and `Episciences_Paper_Projects_ViewFormatter`.
- Refactored `Episciences_Paper_CitationsManager` into `Episciences_Paper_Citations_Repository`, `Episciences_Paper_Citations_ViewFormatter`, `Episciences_Paper_Citations_EnrichmentService`, and `Episciences_Paper_Citations_Logger`.
- Redundant PHPDoc removed from all new citations classes via Rector.
- Replaced `Episciences_Cache` with `symfony/cache` 5.4 across all internal usages.
- `PapersManager::getList()`: removed `$cached` parameter; paper list is now always fetched fresh.
- `Review::getPapers()`, `CopyEditor::loadAssignedPapers()`, `Editor::loadAssignedPapers()`, `Reviewer::loadAssignedPapers()`, `Volume::getPaperListFromVolume()`: updated signatures and call sites following the removal of `$cached`.
- `Oai/Server::getIds()`: OAI resumption token cache migrated to PSR-6.
- Statistics: log import script now supports anonymized log files (.access_log_anonym.gz) as a fallback.
- Improved accessibility of the ORCID authors modal: proper `<label>` associations, `aria-modal="true"`, and corrected `aria-labelledby`.
- Modernized ORCID authors row layout using Flexbox.
- Standardized modal button styles for ORCID updates (Cancel: `btn-default`, Save: `btn-primary`).
- ORCID input fields: URLs are now automatically cleaned to keep only the 16-digit identifier.
- `administratepaper/view.phtml`: reordered panels; moved "Volumes & Rubriques" earlier; `paper_versions` moved to the bottom; removed redundant label.
- `paper/paper_datasets.phtml`: "Liens publications – données – logiciels" panel is now collapsed by default.
- `paper/paper_graphical_abstract.phtml`: graphical abstract panel is now collapsed by default when empty.
- `partials/coauthors.phtml`: "Ajouter un co-auteur" panel is now collapsed by default.
- `partials/paper_affiliation_authors.phtml`: "Ajouter une affiliation" panel is now collapsed by default.
- `volume/list.phtml`: Added "Number" and "Year" columns; improved table accessibility; Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: Removed redundant inline scripts; construction of the editors list optimized.
- Updated icons from `fas fa-address-card` to `fa-regular fa-address-card`.
- Improved visual indicators for editor availability in `editor-availability.js`.
- Refactored `scripts/zbjatZipper.php`: renamed class to `ZbjatZipper`, replaced `echo` with Monolog, extracted God method, switched to HTTPS.
- Refactored `Episciences_Paper_AuthorsManager` into `Episciences_Hal_TeiCacheManager`, `Episciences_Paper_Authors_HalTeiParser`, `Episciences_Paper_Authors_Repository`, `Episciences_Paper_Authors_EnrichmentService`, `Episciences_Paper_Authors_AffiliationHelper`, and `Episciences_Paper_Authors_ViewFormatter`.
- Refactored `Episciences_Paper_Authors_ViewFormatter` to separate data fetching from formatting logic.
- Moved `normalizeOrcid()` implementation from `AuthorsManager` to `HalTeiParser`.
- `AuthorsManager::ONE_MONTH` now references `TeiCacheManager::ONE_MONTH`.
- Added `TeiCacheManager::fetchAndGet()` to combine fetch-if-needed + read in a single call.
- The "Encapsulate reviewers" parameter is now hidden.
- [#528](https://github.com/CCSDForge/episciences/issues/528): 'upload a new version' on top; updated contact label.
- Paper metrics Refactored; 1 query instead of 2.
- The Locale is now stored in a cookie instead of the session.
- Redirect to the article to be reviewed when accepting an invitation.
- Allow volume years to be a string (AAAA or AAAA-AAAA).
- Synchronization of predefined page titles between navigation menu and T_PAGES table when saving menu configuration.
- Replaced `cottagelabs/coar-notifications` (Doctrine ORM + Guzzle) with `cottagelabs/coarnotify` (stateless pattern builder) and a custom PDO persistence layer (`Notification` DTO + `NotificationsRepository`)
- Refactored `InboxNotifications` (1390-line God Class) into focused private methods (`processFirstSubmission`, `processSubsequentSubmission`, `setupJournalTranslations`, `resolveNewVersionStatus`, `logVersionUpdate`, …) and pure, dependency-free helper classes
- `checkNotifyPayloads()` and `initSubmission()` are now source-aware: the COAR Notify source is resolved from `origin.inbox` via `NotifySourceRegistry`
- `InitSubmissionsFromHalInbox.php` is now a direct CLI entry point on `InboxNotifications`; the empty subclass has been removed

### Fixed

- [#858](https://github.com/CCSDForge/episciences/issues/858) fix: only use "et al." when 2+ authors are omitted. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Limit the editor’s permissions to allow assigning only the "reviewer" role.
- [#944](https://github.com/CCSDForge/episciences/issues/944): An author informed us that they received this message signed with their name: %%SENDER_FULL_NAME%% and %%RECIPIENT_SCREEN_NAME%% filled with the same value
- [#258](https://github.com/CCSDForge/episciences-front/issues/258): Tables are not handled by default: Explicit table conversion
- Fixed RT#277365: in order to correct the printing of extra lines, two options have been added to the TinyMCE configuration to insert a <br> instead of a <p> and another option to remove extra <br>s at the end of a block
- Fixed tooltip initialization in volume and section assignment after AJAX refreshes and DataTable redraws.
- Fixed Solr search engine indexation of Authors with a middle name.
- Consider an additional review when sending reminders for an insufficient number of reviewers.
- `Projects`: `$_dateUpdated` default fixed; `getDateUpdated()` return type consistency.
- `Projects::setFunding()`: made method fluent.
- `Projects::setOptions()`: fixed `setIdproject()` alias and case-insensitive method lookup.
- `Projects::toArray()`: fixed `DateTime` serialization.
- `Repository::insert()`: replaced deprecated `VALUES()` syntax with row-alias syntax for MySQL 8.0.20+.
- `Repository`: exceptions now propagate instead of terminating script on DB errors.
- `ViewFormatter`: fixed XSS by escaping `$vfunding['url']` and other user-controlled values in HTML attributes.
- `HalApiClient::doGet()`: added `E_USER_WARNING` severity level.
- `EnrichmentService::resolveHalProjectIds()`: removed duplicate log message; sanitized cache key.
- `Citations_ViewFormatter`: removed double escaping; fixed unstable compound sort; added ORCID format validation.
- `Citations_Repository`: replaced deprecated MySQL `VALUES()` function; added check for `$docId <= 0`.
- `Citations` entity: fixed `toArray()` key name; fixed `$_updatedAt` type and default.
- ORCID normalization: `cleanLowerCaseOrcid()` fix; applied normalization in Zenodo and ARCHE hooks.
- `findAffiliationsOneAuthorByPaperId()`: fixed potential undefined variable.
- `hasAcronym()`: fixed iteration over nested `id` array.
- `HalTeiParser::getAuthorsFromHalTei()`: fixed logic to prevent enriching the wrong author.
- `ViewFormatter::buildAffiliationListHtml()`: fixed Stored XSS by escaping the affiliation acronym.
- `ViewFormatter`: fixed `html_entity_decode(htmlspecialchars())` no-op.
- `EnrichmentService::mergeExistingAffiliations()`: fixed matching key lookup with `array_search()`.
- `AffiliationHelper::isAcronymDuplicate()`: now iterates all identifiers.
- `AffiliationHelper::setOrUpdateRorAcronym()`: returns first match deterministically.
- `TeiCacheManager::buildApiUrl()`: applied `urlencode()` to prevent Solr query injection.
- `TeiCacheManager::getFromCache()`: removed dead `expiresAfter()` call.
- `Repository`: fixed `JSON_DECODE_FLAGS`.
- `CommentsManager::$_typeLabel`: added missing entry for `TYPE_CONTRIBUTOR_TO_REVIEWER`.
- `CommentsManager::updateUid()`: fixed negative UID guard.
- `FormatIssn::FormatIssn()`: fixed substring length.
- `Log::log()`: fixed uncaught exception from `$logger->log()`.
- `DoiAsLink::DoiAsLink()`: fixed label when no text provided.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass with `preg_quote()` and anchors.
- Fixed RT#277365: added TinyMCE configuration options to handle `<br>` and `<p>` correctly.
- [#886](https://github.com/CCSDForge/episciences/issues/886): reminder logic for lack of reviewers fixed.
- `convertToBytes()`: fixed handling of pure numeric strings and added validation.
- `isHal()`, `isHalUrl()`, `isArxiv()`: fixed regex anchors.
- Added comprehensive test coverage for `Episciences_Tools`.
- [#236](https://github.com/CCSDForge/episciences-front/issues/236): HTML entities (&) displayed in the title.
- [#695](https://github.com/CCSDForge/episciences/issues/695) Fixed CC/BCC fields not clickable in paper status change forms.
- [#830](https://github.com/CCSDForge/episciences/issues/830) Paper number messed up for secondary volume; altered secondary volume rendering.
- [#776](https://github.com/CCSDForge/episciences/issues/776) Action Required: Fix Renovate Configuration.
- [#657](https://github.com/CCSDForge/episciences/issues/657) Conditionally remove the `<hr>` separator in metadata views.
- [#786](https://github.com/CCSDForge/episciences/issues/786) English translation of 'Télécharger le fichier'.
- [#646](https://github.com/CCSDForge/episciences/issues/646) Redirect to article page after submission.
- [#780](https://github.com/CCSDForge/episciences/issues/780) Option to lock volume name when an article is published.
- [#147](https://github.com/CCSDForge/episciences/issues/147) Add new pages Acknowledgements in menu 'About'.
- Fixed Paper Metrics based on wrong DocId.
- Fixed Pre-defined pages not deleted from the database.
- [#77](https://github.com/CCSDForge/episciences-front/issues/77) Fix orphan assignments when deleting sections or volumes.
- `CrossrefTools`: fixed fatal error on constant name; fixed raw `CacheItem` return.
- `OpenalexTools`: fixed `getPages()` return value and trailing separator in `getAuthors()`.
- `OpencitationsTools`: fixed caching of empty responses; fixed DOI extraction and updated API format support.
- `getLinkData`: switched to HTTPS; added null guards for `$valuesResult`.
- `getLicenceDataEnrichment`: fixed ineffective cache.
- `OpenAireResearchGraphTools`: enabled strict mode for `array_search()`.
- `Projects/EnrichmentService`: fixed log path and added `(int)` cast for source ID.
- `Citations/EnrichmentService`: added null guards for OpenAlex API fields.
- [#875](https://github.com/CCSDForge/episciences/issues/875): fixed HTML/Emoji translation issue.

### Deprecated

- `Episciences_Cache` and its parent `Ccsd_Cache` are now marked `@deprecated`; use `Symfony\Component\Cache\Adapter\FilesystemAdapter` instead.
- `ProjectsManager` kept as a thin backward-compatible facade.
- `CitationsManager` kept as backward-compatible facade with `@deprecated` proxies.
- `AuthorsManager` kept as orchestrator with backward-compatible `@deprecated` proxies.
- `Episciences_Paper_Visits::count` is deprecated.

### Removed

- Redundant "Statut actuel :" label prefix from the article status panel.
- Stray `<br>` and inlined `versionCache` script tag in `partials/paper_affiliation_authors.phtml`.
- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`.

### Security

- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`, eliminating a potential PHP object injection vector.
- OAI resumption token cache keys are now MD5-hashed before use, preventing cache-key injection.
- Escaped output in `volume/editors_list.phtml` to prevent potential XSS.
- Fixed XSS vulnerability in `Projects/ViewFormatter`: funding URL was interpolated unescaped into `href` attribute and link text.
- Fixed XSS in `Citations_ViewFormatter`: `href=` attributes for DOI and OA links were unquoted.
- Fixed XSS vulnerability in `ViewFormatter::buildAuthorHtml()` and `buildAffiliationListHtml()` regarding user-controlled values in HTML attributes.
- Fixed potential Solr query injection in `TeiCacheManager::buildApiUrl()`.
- `GetAvatar::asPaperStatusSvg()`: fixed two path traversal vectors ($lang sanitization and $paperStatus casting).
- `DoiAsLink::DoiAsLink()`: added `rel="noopener noreferrer"` to prevent tab-napping.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass that allowed authentication from unauthorized email domains.

### Performances

- `volume/list.phtml`: Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: construction of the editors list optimized.
- `Episciences_Paper_Citations_Logger`: Singleton Monolog logger (avoids Logger recreation on every call).
- Paper metrics Refactored; 1 query instead of 2.

## v1.0.55.3 - 2026-05-20

### Fixed

- [RT#287093]: Failed to submit "acceptedAskAuthorsFinalVersion" Form: CSRF validation failed (CSRF field missing)
- Adapt "acceptedAskAuthorsFinalVersion" form to the requirements for setting a deadline for requesting a revision after acceptance

### Changed

- [#1010](https://github.com/CCSDForge/episciences/issues/1010) The allowed range for modifying the deadline is now between the original deadline minus "rating_deadline_min" and the original deadline plus "rating_deadline_max".
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration
  button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)"

## v1.0.55.2 - 2026-05-19

### Fixed

- Improved article URL resolution in `ExtractBiblioRefsCommand` by adding fallbacks to `getMainPaperUrl()` and `getDocUrl()` when the repository template is empty (e.g., for Zenodo).
- Added debug logging for resolved article URLs and included raw response snippets in JSON decode error messages in `ExtractBiblioRefsCommand`.

## v1.0.55.1 - 2026-05-19

### Added

- Legend for bibliographic reference status icons in both admin and public views (publicly shown only when problematic references are detected).

### Changed

- Replaced bibliographic reference status indicators with standardized square icons (red `fa-square-xmark` for problematic, green `fa-square-check` for validated, and gray `fa-square` for automatically extracted references).
- Improved accessibility of bibliographic references (added ARIA landmarks, screen-reader-only status prefixes, descriptions for status badges, and warnings for links opening in new tabs).
- Optimized bibliographic references layout by moving the status legend inline with the action button.
- Renamed 'Not validated reference' to 'Automatically extracted reference' for clarity.
- Renamed 'Manage' button to 'Manage References' / 'Gérer les références' in the administration view.

### Fixed

- Removed obsolete BibTeX import button (feature migrated to an external application).
- Removed hardcoded Semantic Scholar source attribution from bibliographic references display.
- Skip temporary versions (without external repository URL) during bibliographic reference extraction.
- Fixed a missing dependency (`require_once`) for `GetDoiCommand` in `console.php`.

## v1.0.55 - 2026-05-18

### Added

- Automatic detection of problematic papers cited in the references of the papers. This is heavily based on the huge work provided by the Problematic Paper Screener https://dbrech.irit.fr/pls/apex/f?p=9999:1::::::
  For more information: Cabanac, G., Labbé, C., & Magazinov, A. (2022). The ‘Problematic Paper Screener’ automatically selects suspect publications for post-publication (re)assessment.
  Presented at WCRI 2022: 7th World Conference on Research Integrity. arXiv preprint. https://doi.org/10.48550/arXiv.2210.04895

- [#630](https://github.com/CCSDForge/episciences/issues/630) COI (Conflict of Interest) notifications:
    - Email notification to the editor-in-chief when an editor declares a positive COI (answered "yes")
    - Email notification to other assigned editors (if any) when an editor declares a COI
- Many legacy scripts migrated to Symfony Console commands (e.g., `doi:manage` replaces `getDoi.php`, `sitemap:generate --all`, translations update).
- Expanded unit test coverage for repository connectors (Zenodo, BioMedRxiv, ARCHE, Dspace) and COI notifications (+60 tests).

### Changed

- Sending the invitation in the user’s language, including when using the autocomplete input field for CAS users who already have a profile on the site.
- Simplified news add/edit form for a better user experience.
- Refactored OpenAIRE integration into a dedicated `OpenAireApiClient`.
- Improved PHP 8.1 compatibility and robustness across the codebase (removed deprecations and warnings).
- Optimized the submission workflow processing order.

### Fixed

- A bug that likely appeared in the latest update: section and/or volume editors are not assigned automatically, even if the relevant settings are enabled.
- Panel not opening when updating linked data
- Submission of a dataset without a data descriptor due to an error while uploading attached files (one of the file types exceeded the maximum allowed size for that field type)
- Corrected article position in volumes (1-based instead of 0-based) for CSL generation.
- Resolved relative `hydra:next` URLs in sitemap generation.
- Fixed several potential `TypeError` and crashes when metadata or publication dates are missing.

## v1.0.54.3 - 2026-04-23

### Fixed

- The link to the new version of the data descriptor isn't working; it still points to the first version.
- [RT#285106]: Application error when submitting with 'display secondary volume' option enabled
- Always SELECT RECORD so Paper::getMetadata() / toJson() can resolve titles and abstracts
- Log parser: extract IP from syslog-prefixed Apache log lines

## v1.0.54.2 - 2026-04-16

### Fixed

- Inability to change the review deadline (restore loading js via url).

### Added

- docs: add documentation index to README and update docs
- stats:import-logs command to parse Apache logs into STAT_TEMP. Introduces ImportApacheLogsCommand (stats:import-logs), a Symfony Console command that parses Apache access logs and inserts article visits into STAT_TEMP. Replaces the legacy UpdateStatistics.php . Run src/mysql/2025-08-24-stat-processing-log-table.sql before deploying

## v1.0.54.1 - 2026-04-14

### Changed

- Refactored English email templates, grammar only, no new features, no new tags

### Fixed

- [#962](https://github.com/CCSDForge/episciences/issues/962) Fixed double HTML encoding bug in volume metadata titles where special
  characters like `<`, `>`, and `'` were displayed as `&lt;`, `&gt;`, `&#039;`
- TypeError in enrichment:creators on non-sequential affiliation keys
- isImported detection for published papers when the the publication date has been fixed after publication
- [#985](https://github.com/CCSDForge/episciences/issues/985) Impossible to submit a final version
- Fixed bug in management of journal headers for adding and updating logo and texts

## v1.0.54 - 2026-04-09

### Added

- [#779](https://github.com/CCSDForge/episciences/issues/779) [Feature request] Display the secondary volume on the article page. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Adding "Publish" => "Proposing special issues" template page [#951](https://github.com/CCSDForge/episciences/issues/951)
- New supported servers:
    - Cryptology ePrint Archive (https://eprint.iacr.org)
    - Support for any data repositories powered by Dspace (eg. [repositorium.uminho(University of Minho)](https://repositorium.uminho.pt))
- Statistics: new Symfony Console commands `stats:process` (replaces legacy `scripts/stat.php`) and `stats:update-robots-list` for UA-based bot detection using the COUNTER Robots list; real client IP is now stored in `STAT_TEMP` and anonymized at processing time.
- Documentation: added `docs/console-commands.md` listing all 19 CLI commands available via `scripts/console.php` with options and usage examples.
- OAI-PMH: added `crossref` as a new metadata format (`metadataPrefix=crossref`), exposing Crossref 5.3.1 XML for each published paper. The depositor email is automatically replaced with a generic `noreply@{domain}` address in this context.
- Comprehensive Jest test suite for ORCID authors management (`tests/js/updateOrcidAuthors.test.js`).
- Unit tests for `Episciences_Paper_Projects` entity (setOptions aliases, DateTime handling, toArray serialization, fluent setters).
- Unit tests for `Episciences_Paper_Projects_EnrichmentService` pure functions (EU/ANR response normalization, OpenAIRE relation filtering).
- Unit tests for `Episciences_Paper_Projects_ViewFormatter` (empty-array return, URL HTML-escaping, unidentified title suppression).
- Unit tests for `Episciences_Paper_Citations_ViewFormatter` (20 tests: sort, author formatting, ORCID links, XSS prevention, book-chapter/proceedings-article reordering).
- Unit tests for `Episciences_Paper_Citations` entity (8 tests: `toArray()` key, fluent setters, nullable `updatedAt`, constructor).
- Comprehensive unit tests for `Episciences_Paper_Authors_ViewFormatter` covering HTML display logic and XSS prevention.
- Unit tests for `Episciences_View_Helper_DoiAsLink`, `Episciences_View_Helper_FormatIssn`, `Episciences_View_Helper_Log`, `Episciences_View_Helper_Tag`, `Episciences_View_Helper_UserAvatar`, `Episciences_View_Helper_GetAvatar` and `Episciences_CommentsManager`.
- 89+ unit tests for `Ccsd\Auth` adapters (`CasAbstract`, `Idp`, `Orcid`, `AdapterFactory`, `Asso`), `Ccsd\User` models (`User`, `UserTokens`, `UserFtpQuota`) and `Episciences\User` entities covering pure logic without DB or network access.
- Updated `tests/README.md` with accurate Docker-based testing instructions, `make` target reference, subset-run examples, and contributor guidelines.
- [#883](https://github.com/CCSDForge/episciences/issues/883) Allow json files as attachments.
- Optional Webhook: It is now possible to report status changes to an external entry point (can be configured by journal).
- [#658](https://github.com/CCSDForge/episciences/issues/658) It is now possible to link an invitation that is not intended for you to your account.
- allow the co-author to view the publication.
- Statistics: the script has a new parameter `--all` - Process all statistics (with confirmation prompt).
- New option to allow Editors to receive 'Comments for editors' before declaring a conflict of interest (disabled by default).
- Volume Settings: add New configuration section(displayEmptyVolumes and allowEditVolumeTitleWithPublishedArticles) in journal settings.
- [#679](https://github.com/CCSDForge/episciences/issues/679) "Ask other editors for their opinion" form now includes chief editors (ROLE_CHIEF_EDITOR) in addition to regular editors (ROLE_EDITOR).
- [#691](http://github.com/CCSDForge/episciences/issues/691) Display "(optional)" label below comment and cover letter fields in submission forms to clarify these fields are not required.
- [#350](https://github.com/CCSDForge/episciences/issues/350) Author-Editor Communication Settings:
    - New option to allow authors to contact assigned editors (`authorEditorCommunication`)
    - New option to disclose editor names to authors or keep them anonymized (`discloseEditorNamesToAuthors`)
- feat(navigation): sync predefined page titles with T_PAGES table on menu save.
- New `library/Episciences/Api/` namespace with 5 injectable, independently-testable API clients: `AbstractApiClient`, `CrossrefApiClient`, `OpenAlexApiClient`, `OpenCitationsApiClient`, `OpenAireApiClient`.
- Five Symfony Console commands replacing the legacy JournalScript enrichment scripts: `enrichment:citations`, `enrichment:creators`, `enrichment:funding`, `enrichment:licences`, `enrichment:links`.
- Added MSC2020 Classification in ZBJATS export.
- `Episciences\Notify\NotifySourceConfig` — immutable value object holding per-source configuration (repoId, label, originId, originInbox, acceptedTypes); new repositories can be added without touching `InboxNotifications`
- `Episciences\Notify\NotifySourceRegistry` — registry with a `createFromConstants()` factory; resolves source config from a payload's `origin.inbox`
- `Episciences\Notify\ValidationResult` — pure value object wrapping a validation outcome (success / failure + error message)
- `Episciences\Notify\PreprintUrlParser` — pure helper extracting version and identifier from preprint landing-page URLs (HAL-style)
- `Episciences\Notify\PayloadValidator` — validates COAR Notify 1.0.1 payloads for Offer patterns (Request Review / Request Endorsement): all required fields including `object.id` (HTTP URI) and `object['ietf:item']` (id, type, mediaType)

### Changed

- [#947](https://github.com/CCSDForge/episciences/issues/947) [Feature request] wording on the platform - refused / rejected and refused/declined
- Refactored `public/js/paper/updateOrcidAuthors.js` from jQuery to modern vanilla ES6+ with a class-based architecture.
- Enhanced ORCID form validation: added duplicate detection while maintaining support for empty submissions for deletions.
- Update TinyMCE form 7.3.0 to 8.1.2.
- Increased Volume Description length to 1024 chars.
- DOI panel: requesting, saving, and cancelling a DOI no longer triggers a full page reload; success feedback message is suppressed.
- Refactored `Episciences_Paper_ProjectsManager` into `Episciences_Paper_Projects_Repository`, `Episciences_Paper_Projects_HalApiClient`, `Episciences_Paper_Projects_EnrichmentService`, and `Episciences_Paper_Projects_ViewFormatter`.
- Refactored `Episciences_Paper_CitationsManager` into `Episciences_Paper_Citations_Repository`, `Episciences_Paper_Citations_ViewFormatter`, `Episciences_Paper_Citations_EnrichmentService`, and `Episciences_Paper_Citations_Logger`.
- Redundant PHPDoc removed from all new citations classes via Rector.
- Replaced `Episciences_Cache` with `symfony/cache` 5.4 across all internal usages.
- `PapersManager::getList()`: removed `$cached` parameter; paper list is now always fetched fresh.
- `Review::getPapers()`, `CopyEditor::loadAssignedPapers()`, `Editor::loadAssignedPapers()`, `Reviewer::loadAssignedPapers()`, `Volume::getPaperListFromVolume()`: updated signatures and call sites following the removal of `$cached`.
- `Oai/Server::getIds()`: OAI resumption token cache migrated to PSR-6.
- Statistics: log import script now supports anonymized log files (.access_log_anonym.gz) as a fallback.
- Improved accessibility of the ORCID authors modal: proper `<label>` associations, `aria-modal="true"`, and corrected `aria-labelledby`.
- Modernized ORCID authors row layout using Flexbox.
- Standardized modal button styles for ORCID updates (Cancel: `btn-default`, Save: `btn-primary`).
- ORCID input fields: URLs are now automatically cleaned to keep only the 16-digit identifier.
- `administratepaper/view.phtml`: reordered panels; moved "Volumes & Rubriques" earlier; `paper_versions` moved to the bottom; removed redundant label.
- `paper/paper_datasets.phtml`: "Liens publications – données – logiciels" panel is now collapsed by default.
- `paper/paper_graphical_abstract.phtml`: graphical abstract panel is now collapsed by default when empty.
- `partials/coauthors.phtml`: "Ajouter un co-auteur" panel is now collapsed by default.
- `partials/paper_affiliation_authors.phtml`: "Ajouter une affiliation" panel is now collapsed by default.
- `volume/list.phtml`: Added "Number" and "Year" columns; improved table accessibility; Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: Removed redundant inline scripts; construction of the editors list optimized.
- Updated icons from `fas fa-address-card` to `fa-regular fa-address-card`.
- Improved visual indicators for editor availability in `editor-availability.js`.
- Refactored `scripts/zbjatZipper.php`: renamed class to `ZbjatZipper`, replaced `echo` with Monolog, extracted God method, switched to HTTPS.
- Refactored `Episciences_Paper_AuthorsManager` into `Episciences_Hal_TeiCacheManager`, `Episciences_Paper_Authors_HalTeiParser`, `Episciences_Paper_Authors_Repository`, `Episciences_Paper_Authors_EnrichmentService`, `Episciences_Paper_Authors_AffiliationHelper`, and `Episciences_Paper_Authors_ViewFormatter`.
- Refactored `Episciences_Paper_Authors_ViewFormatter` to separate data fetching from formatting logic.
- Moved `normalizeOrcid()` implementation from `AuthorsManager` to `HalTeiParser`.
- `AuthorsManager::ONE_MONTH` now references `TeiCacheManager::ONE_MONTH`.
- Added `TeiCacheManager::fetchAndGet()` to combine fetch-if-needed + read in a single call.
- The "Encapsulate reviewers" parameter is now hidden.
- [#528](https://github.com/CCSDForge/episciences/issues/528): 'upload a new version' on top; updated contact label.
- Paper metrics Refactored; 1 query instead of 2.
- The Locale is now stored in a cookie instead of the session.
- Redirect to the article to be reviewed when accepting an invitation.
- Allow volume years to be a string (AAAA or AAAA-AAAA).
- Synchronization of predefined page titles between navigation menu and T_PAGES table when saving menu configuration.
- Replaced `cottagelabs/coar-notifications` (Doctrine ORM + Guzzle) with `cottagelabs/coarnotify` (stateless pattern builder) and a custom PDO persistence layer (`Notification` DTO + `NotificationsRepository`)
- Refactored `InboxNotifications` (1390-line God Class) into focused private methods (`processFirstSubmission`, `processSubsequentSubmission`, `setupJournalTranslations`, `resolveNewVersionStatus`, `logVersionUpdate`, …) and pure, dependency-free helper classes
- `checkNotifyPayloads()` and `initSubmission()` are now source-aware: the COAR Notify source is resolved from `origin.inbox` via `NotifySourceRegistry`
- `InitSubmissionsFromHalInbox.php` is now a direct CLI entry point on `InboxNotifications`; the empty subclass has been removed

### Fixed

- [#858](https://github.com/CCSDForge/episciences/issues/858) fix: only use "et al." when 2+ authors are omitted. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Limit the editor’s permissions to allow assigning only the "reviewer" role.
- [#944](https://github.com/CCSDForge/episciences/issues/944): An author informed us that they received this message signed with their name: %%SENDER_FULL_NAME%% and %%RECIPIENT_SCREEN_NAME%% filled with the same value
- [#258](https://github.com/CCSDForge/episciences-front/issues/258): Tables are not handled by default: Explicit table conversion
- Fixed RT#277365: in order to correct the printing of extra lines, two options have been added to the TinyMCE configuration to insert a <br> instead of a <p> and another option to remove extra <br>s at the end of a block
- Fixed tooltip initialization in volume and section assignment after AJAX refreshes and DataTable redraws.
- Fixed Solr search engine indexation of Authors with a middle name.
- Consider an additional review when sending reminders for an insufficient number of reviewers.
- `Projects`: `$_dateUpdated` default fixed; `getDateUpdated()` return type consistency.
- `Projects::setFunding()`: made method fluent.
- `Projects::setOptions()`: fixed `setIdproject()` alias and case-insensitive method lookup.
- `Projects::toArray()`: fixed `DateTime` serialization.
- `Repository::insert()`: replaced deprecated `VALUES()` syntax with row-alias syntax for MySQL 8.0.20+.
- `Repository`: exceptions now propagate instead of terminating script on DB errors.
- `ViewFormatter`: fixed XSS by escaping `$vfunding['url']` and other user-controlled values in HTML attributes.
- `HalApiClient::doGet()`: added `E_USER_WARNING` severity level.
- `EnrichmentService::resolveHalProjectIds()`: removed duplicate log message; sanitized cache key.
- `Citations_ViewFormatter`: removed double escaping; fixed unstable compound sort; added ORCID format validation.
- `Citations_Repository`: replaced deprecated MySQL `VALUES()` function; added check for `$docId <= 0`.
- `Citations` entity: fixed `toArray()` key name; fixed `$_updatedAt` type and default.
- ORCID normalization: `cleanLowerCaseOrcid()` fix; applied normalization in Zenodo and ARCHE hooks.
- `findAffiliationsOneAuthorByPaperId()`: fixed potential undefined variable.
- `hasAcronym()`: fixed iteration over nested `id` array.
- `HalTeiParser::getAuthorsFromHalTei()`: fixed logic to prevent enriching the wrong author.
- `ViewFormatter::buildAffiliationListHtml()`: fixed Stored XSS by escaping the affiliation acronym.
- `ViewFormatter`: fixed `html_entity_decode(htmlspecialchars())` no-op.
- `EnrichmentService::mergeExistingAffiliations()`: fixed matching key lookup with `array_search()`.
- `AffiliationHelper::isAcronymDuplicate()`: now iterates all identifiers.
- `AffiliationHelper::setOrUpdateRorAcronym()`: returns first match deterministically.
- `TeiCacheManager::buildApiUrl()`: applied `urlencode()` to prevent Solr query injection.
- `TeiCacheManager::getFromCache()`: removed dead `expiresAfter()` call.
- `Repository`: fixed `JSON_DECODE_FLAGS`.
- `CommentsManager::$_typeLabel`: added missing entry for `TYPE_CONTRIBUTOR_TO_REVIEWER`.
- `CommentsManager::updateUid()`: fixed negative UID guard.
- `FormatIssn::FormatIssn()`: fixed substring length.
- `Log::log()`: fixed uncaught exception from `$logger->log()`.
- `DoiAsLink::DoiAsLink()`: fixed label when no text provided.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass with `preg_quote()` and anchors.
- Fixed RT#277365: added TinyMCE configuration options to handle `<br>` and `<p>` correctly.
- [#886](https://github.com/CCSDForge/episciences/issues/886): reminder logic for lack of reviewers fixed.
- `convertToBytes()`: fixed handling of pure numeric strings and added validation.
- `isHal()`, `isHalUrl()`, `isArxiv()`: fixed regex anchors.
- Added comprehensive test coverage for `Episciences_Tools`.
- [#236](https://github.com/CCSDForge/episciences-front/issues/236): HTML entities (&) displayed in the title.
- [#695](https://github.com/CCSDForge/episciences/issues/695) Fixed CC/BCC fields not clickable in paper status change forms.
- [#830](https://github.com/CCSDForge/episciences/issues/830) Paper number messed up for secondary volume; altered secondary volume rendering.
- [#776](https://github.com/CCSDForge/episciences/issues/776) Action Required: Fix Renovate Configuration.
- [#657](https://github.com/CCSDForge/episciences/issues/657) Conditionally remove the `<hr>` separator in metadata views.
- [#786](https://github.com/CCSDForge/episciences/issues/786) English translation of 'Télécharger le fichier'.
- [#646](https://github.com/CCSDForge/episciences/issues/646) Redirect to article page after submission.
- [#780](https://github.com/CCSDForge/episciences/issues/780) Option to lock volume name when an article is published.
- [#147](https://github.com/CCSDForge/episciences/issues/147) Add new pages Acknowledgements in menu 'About'.
- Fixed Paper Metrics based on wrong DocId.
- Fixed Pre-defined pages not deleted from the database.
- [#77](https://github.com/CCSDForge/episciences-front/issues/77) Fix orphan assignments when deleting sections or volumes.
- `CrossrefTools`: fixed fatal error on constant name; fixed raw `CacheItem` return.
- `OpenalexTools`: fixed `getPages()` return value and trailing separator in `getAuthors()`.
- `OpencitationsTools`: fixed caching of empty responses; fixed DOI extraction and updated API format support.
- `getLinkData`: switched to HTTPS; added null guards for `$valuesResult`.
- `getLicenceDataEnrichment`: fixed ineffective cache.
- `OpenAireResearchGraphTools`: enabled strict mode for `array_search()`.
- `Projects/EnrichmentService`: fixed log path and added `(int)` cast for source ID.
- `Citations/EnrichmentService`: added null guards for OpenAlex API fields.
- [#875](https://github.com/CCSDForge/episciences/issues/875): fixed HTML/Emoji translation issue.

### Deprecated

- `Episciences_Cache` and its parent `Ccsd_Cache` are now marked `@deprecated`; use `Symfony\Component\Cache\Adapter\FilesystemAdapter` instead.
- `ProjectsManager` kept as a thin backward-compatible facade.
- `CitationsManager` kept as backward-compatible facade with `@deprecated` proxies.
- `AuthorsManager` kept as orchestrator with backward-compatible `@deprecated` proxies.
- `Episciences_Paper_Visits::count` is deprecated.

### Removed

- Redundant "Statut actuel :" label prefix from the article status panel.
- Stray `<br>` and inlined `versionCache` script tag in `partials/paper_affiliation_authors.phtml`.
- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`.

### Security

- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`, eliminating a potential PHP object injection vector.
- OAI resumption token cache keys are now MD5-hashed before use, preventing cache-key injection.
- Escaped output in `volume/editors_list.phtml` to prevent potential XSS.
- Fixed XSS vulnerability in `Projects/ViewFormatter`: funding URL was interpolated unescaped into `href` attribute and link text.
- Fixed XSS in `Citations_ViewFormatter`: `href=` attributes for DOI and OA links were unquoted.
- Fixed XSS vulnerability in `ViewFormatter::buildAuthorHtml()` and `buildAffiliationListHtml()` regarding user-controlled values in HTML attributes.
- Fixed potential Solr query injection in `TeiCacheManager::buildApiUrl()`.
- `GetAvatar::asPaperStatusSvg()`: fixed two path traversal vectors ($lang sanitization and $paperStatus casting).
- `DoiAsLink::DoiAsLink()`: added `rel="noopener noreferrer"` to prevent tab-napping.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass that allowed authentication from unauthorized email domains.

### Performances

- `volume/list.phtml`: Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: construction of the editors list optimized.
- `Episciences_Paper_Citations_Logger`: Singleton Monolog logger (avoids Logger recreation on every call).
- Paper metrics Refactored; 1 query instead of 2.

## v1.0.55.3 - 2026-05-20

### Fixed

- [RT#287093]: Failed to submit "acceptedAskAuthorsFinalVersion" Form: CSRF validation failed (CSRF field missing)
- Adapt "acceptedAskAuthorsFinalVersion" form to the requirements for setting a deadline for requesting a revision after acceptance

### Changed

- [#1010](https://github.com/CCSDForge/episciences/issues/1010) The allowed range for modifying the deadline is now between the original deadline minus "rating_deadline_min" and the original deadline plus "rating_deadline_max".
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration
  button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)"

## v1.0.55.2 - 2026-05-19

### Fixed

- Improved article URL resolution in `ExtractBiblioRefsCommand` by adding fallbacks to `getMainPaperUrl()` and `getDocUrl()` when the repository template is empty (e.g., for Zenodo).
- Added debug logging for resolved article URLs and included raw response snippets in JSON decode error messages in `ExtractBiblioRefsCommand`.

## v1.0.55.1 - 2026-05-19

### Added

- Legend for bibliographic reference status icons in both admin and public views (publicly shown only when problematic references are detected).

### Changed

- Replaced bibliographic reference status indicators with standardized square icons (red `fa-square-xmark` for problematic, green `fa-square-check` for validated, and gray `fa-square` for automatically extracted references).
- Improved accessibility of bibliographic references (added ARIA landmarks, screen-reader-only status prefixes, descriptions for status badges, and warnings for links opening in new tabs).
- Optimized bibliographic references layout by moving the status legend inline with the action button.
- Renamed 'Not validated reference' to 'Automatically extracted reference' for clarity.
- Renamed 'Manage' button to 'Manage References' / 'Gérer les références' in the administration view.

### Fixed

- Removed obsolete BibTeX import button (feature migrated to an external application).
- Removed hardcoded Semantic Scholar source attribution from bibliographic references display.
- Skip temporary versions (without external repository URL) during bibliographic reference extraction.
- Fixed a missing dependency (`require_once`) for `GetDoiCommand` in `console.php`.

## v1.0.55 - 2026-05-18

### Added

- Automatic detection of problematic papers cited in the references of the papers. This is heavily based on the huge work provided by the Problematic Paper Screener https://dbrech.irit.fr/pls/apex/f?p=9999:1::::::
  For more information: Cabanac, G., Labbé, C., & Magazinov, A. (2022). The ‘Problematic Paper Screener’ automatically selects suspect publications for post-publication (re)assessment.
  Presented at WCRI 2022: 7th World Conference on Research Integrity. arXiv preprint. https://doi.org/10.48550/arXiv.2210.04895

- [#630](https://github.com/CCSDForge/episciences/issues/630) COI (Conflict of Interest) notifications:
    - Email notification to the editor-in-chief when an editor declares a positive COI (answered "yes")
    - Email notification to other assigned editors (if any) when an editor declares a COI
- Many legacy scripts migrated to Symfony Console commands (e.g., `doi:manage` replaces `getDoi.php`, `sitemap:generate --all`, translations update).
- Expanded unit test coverage for repository connectors (Zenodo, BioMedRxiv, ARCHE, Dspace) and COI notifications (+60 tests).

### Changed

- Sending the invitation in the user’s language, including when using the autocomplete input field for CAS users who already have a profile on the site.
- Simplified news add/edit form for a better user experience.
- Refactored OpenAIRE integration into a dedicated `OpenAireApiClient`.
- Improved PHP 8.1 compatibility and robustness across the codebase (removed deprecations and warnings).
- Optimized the submission workflow processing order.

### Fixed

- A bug that likely appeared in the latest update: section and/or volume editors are not assigned automatically, even if the relevant settings are enabled.
- Panel not opening when updating linked data
- Submission of a dataset without a data descriptor due to an error while uploading attached files (one of the file types exceeded the maximum allowed size for that field type)
- Corrected article position in volumes (1-based instead of 0-based) for CSL generation.
- Resolved relative `hydra:next` URLs in sitemap generation.
- Fixed several potential `TypeError` and crashes when metadata or publication dates are missing.

## v1.0.54.3 - 2026-04-23

### Fixed

- The link to the new version of the data descriptor isn't working; it still points to the first version.
- [RT#285106]: Application error when submitting with 'display secondary volume' option enabled
- Always SELECT RECORD so Paper::getMetadata() / toJson() can resolve titles and abstracts
- Log parser: extract IP from syslog-prefixed Apache log lines

## v1.0.54.2 - 2026-04-16

### Fixed

- Inability to change the review deadline (restore loading js via url).

### Added

- docs: add documentation index to README and update docs
- stats:import-logs command to parse Apache logs into STAT_TEMP. Introduces ImportApacheLogsCommand (stats:import-logs), a Symfony Console command that parses Apache access logs and inserts article visits into STAT_TEMP. Replaces the legacy UpdateStatistics.php . Run src/mysql/2025-08-24-stat-processing-log-table.sql before deploying

## v1.0.54.1 - 2026-04-14

### Changed

- Refactored English email templates, grammar only, no new features, no new tags

### Fixed

- [#962](https://github.com/CCSDForge/episciences/issues/962) Fixed double HTML encoding bug in volume metadata titles where special
  characters like `<`, `>`, and `'` were displayed as `&lt;`, `&gt;`, `&#039;`
- TypeError in enrichment:creators on non-sequential affiliation keys
- isImported detection for published papers when the the publication date has been fixed after publication
- [#985](https://github.com/CCSDForge/episciences/issues/985) Impossible to submit a final version
- Fixed bug in management of journal headers for adding and updating logo and texts

## v1.0.54 - 2026-04-09

### Added

- [#779](https://github.com/CCSDForge/episciences/issues/779) [Feature request] Display the secondary volume on the article page. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Adding "Publish" => "Proposing special issues" template page [#951](https://github.com/CCSDForge/episciences/issues/951)
- New supported servers:
    - Cryptology ePrint Archive (https://eprint.iacr.org)
    - Support for any data repositories powered by Dspace (eg. [repositorium.uminho(University of Minho)](https://repositorium.uminho.pt))
- Statistics: new Symfony Console commands `stats:process` (replaces legacy `scripts/stat.php`) and `stats:update-robots-list` for UA-based bot detection using the COUNTER Robots list; real client IP is now stored in `STAT_TEMP` and anonymized at processing time.
- Documentation: added `docs/console-commands.md` listing all 19 CLI commands available via `scripts/console.php` with options and usage examples.
- OAI-PMH: added `crossref` as a new metadata format (`metadataPrefix=crossref`), exposing Crossref 5.3.1 XML for each published paper. The depositor email is automatically replaced with a generic `noreply@{domain}` address in this context.
- Comprehensive Jest test suite for ORCID authors management (`tests/js/updateOrcidAuthors.test.js`).
- Unit tests for `Episciences_Paper_Projects` entity (setOptions aliases, DateTime handling, toArray serialization, fluent setters).
- Unit tests for `Episciences_Paper_Projects_EnrichmentService` pure functions (EU/ANR response normalization, OpenAIRE relation filtering).
- Unit tests for `Episciences_Paper_Projects_ViewFormatter` (empty-array return, URL HTML-escaping, unidentified title suppression).
- Unit tests for `Episciences_Paper_Citations_ViewFormatter` (20 tests: sort, author formatting, ORCID links, XSS prevention, book-chapter/proceedings-article reordering).
- Unit tests for `Episciences_Paper_Citations` entity (8 tests: `toArray()` key, fluent setters, nullable `updatedAt`, constructor).
- Comprehensive unit tests for `Episciences_Paper_Authors_ViewFormatter` covering HTML display logic and XSS prevention.
- Unit tests for `Episciences_View_Helper_DoiAsLink`, `Episciences_View_Helper_FormatIssn`, `Episciences_View_Helper_Log`, `Episciences_View_Helper_Tag`, `Episciences_View_Helper_UserAvatar`, `Episciences_View_Helper_GetAvatar` and `Episciences_CommentsManager`.
- 89+ unit tests for `Ccsd\Auth` adapters (`CasAbstract`, `Idp`, `Orcid`, `AdapterFactory`, `Asso`), `Ccsd\User` models (`User`, `UserTokens`, `UserFtpQuota`) and `Episciences\User` entities covering pure logic without DB or network access.
- Updated `tests/README.md` with accurate Docker-based testing instructions, `make` target reference, subset-run examples, and contributor guidelines.
- [#883](https://github.com/CCSDForge/episciences/issues/883) Allow json files as attachments.
- Optional Webhook: It is now possible to report status changes to an external entry point (can be configured by journal).
- [#658](https://github.com/CCSDForge/episciences/issues/658) It is now possible to link an invitation that is not intended for you to your account.
- allow the co-author to view the publication.
- Statistics: the script has a new parameter `--all` - Process all statistics (with confirmation prompt).
- New option to allow Editors to receive 'Comments for editors' before declaring a conflict of interest (disabled by default).
- Volume Settings: add New configuration section(displayEmptyVolumes and allowEditVolumeTitleWithPublishedArticles) in journal settings.
- [#679](https://github.com/CCSDForge/episciences/issues/679) "Ask other editors for their opinion" form now includes chief editors (ROLE_CHIEF_EDITOR) in addition to regular editors (ROLE_EDITOR).
- [#691](http://github.com/CCSDForge/episciences/issues/691) Display "(optional)" label below comment and cover letter fields in submission forms to clarify these fields are not required.
- [#350](https://github.com/CCSDForge/episciences/issues/350) Author-Editor Communication Settings:
    - New option to allow authors to contact assigned editors (`authorEditorCommunication`)
    - New option to disclose editor names to authors or keep them anonymized (`discloseEditorNamesToAuthors`)
- feat(navigation): sync predefined page titles with T_PAGES table on menu save.
- New `library/Episciences/Api/` namespace with 5 injectable, independently-testable API clients: `AbstractApiClient`, `CrossrefApiClient`, `OpenAlexApiClient`, `OpenCitationsApiClient`, `OpenAireApiClient`.
- Five Symfony Console commands replacing the legacy JournalScript enrichment scripts: `enrichment:citations`, `enrichment:creators`, `enrichment:funding`, `enrichment:licences`, `enrichment:links`.
- Added MSC2020 Classification in ZBJATS export.
- `Episciences\Notify\NotifySourceConfig` — immutable value object holding per-source configuration (repoId, label, originId, originInbox, acceptedTypes); new repositories can be added without touching `InboxNotifications`
- `Episciences\Notify\NotifySourceRegistry` — registry with a `createFromConstants()` factory; resolves source config from a payload's `origin.inbox`
- `Episciences\Notify\ValidationResult` — pure value object wrapping a validation outcome (success / failure + error message)
- `Episciences\Notify\PreprintUrlParser` — pure helper extracting version and identifier from preprint landing-page URLs (HAL-style)
- `Episciences\Notify\PayloadValidator` — validates COAR Notify 1.0.1 payloads for Offer patterns (Request Review / Request Endorsement): all required fields including `object.id` (HTTP URI) and `object['ietf:item']` (id, type, mediaType)

### Changed

- [#947](https://github.com/CCSDForge/episciences/issues/947) [Feature request] wording on the platform - refused / rejected and refused/declined
- Refactored `public/js/paper/updateOrcidAuthors.js` from jQuery to modern vanilla ES6+ with a class-based architecture.
- Enhanced ORCID form validation: added duplicate detection while maintaining support for empty submissions for deletions.
- Update TinyMCE form 7.3.0 to 8.1.2.
- Increased Volume Description length to 1024 chars.
- DOI panel: requesting, saving, and cancelling a DOI no longer triggers a full page reload; success feedback message is suppressed.
- Refactored `Episciences_Paper_ProjectsManager` into `Episciences_Paper_Projects_Repository`, `Episciences_Paper_Projects_HalApiClient`, `Episciences_Paper_Projects_EnrichmentService`, and `Episciences_Paper_Projects_ViewFormatter`.
- Refactored `Episciences_Paper_CitationsManager` into `Episciences_Paper_Citations_Repository`, `Episciences_Paper_Citations_ViewFormatter`, `Episciences_Paper_Citations_EnrichmentService`, and `Episciences_Paper_Citations_Logger`.
- Redundant PHPDoc removed from all new citations classes via Rector.
- Replaced `Episciences_Cache` with `symfony/cache` 5.4 across all internal usages.
- `PapersManager::getList()`: removed `$cached` parameter; paper list is now always fetched fresh.
- `Review::getPapers()`, `CopyEditor::loadAssignedPapers()`, `Editor::loadAssignedPapers()`, `Reviewer::loadAssignedPapers()`, `Volume::getPaperListFromVolume()`: updated signatures and call sites following the removal of `$cached`.
- `Oai/Server::getIds()`: OAI resumption token cache migrated to PSR-6.
- Statistics: log import script now supports anonymized log files (.access_log_anonym.gz) as a fallback.
- Improved accessibility of the ORCID authors modal: proper `<label>` associations, `aria-modal="true"`, and corrected `aria-labelledby`.
- Modernized ORCID authors row layout using Flexbox.
- Standardized modal button styles for ORCID updates (Cancel: `btn-default`, Save: `btn-primary`).
- ORCID input fields: URLs are now automatically cleaned to keep only the 16-digit identifier.
- `administratepaper/view.phtml`: reordered panels; moved "Volumes & Rubriques" earlier; `paper_versions` moved to the bottom; removed redundant label.
- `paper/paper_datasets.phtml`: "Liens publications – données – logiciels" panel is now collapsed by default.
- `paper/paper_graphical_abstract.phtml`: graphical abstract panel is now collapsed by default when empty.
- `partials/coauthors.phtml`: "Ajouter un co-auteur" panel is now collapsed by default.
- `partials/paper_affiliation_authors.phtml`: "Ajouter une affiliation" panel is now collapsed by default.
- `volume/list.phtml`: Added "Number" and "Year" columns; improved table accessibility; Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: Removed redundant inline scripts; construction of the editors list optimized.
- Updated icons from `fas fa-address-card` to `fa-regular fa-address-card`.
- Improved visual indicators for editor availability in `editor-availability.js`.
- Refactored `scripts/zbjatZipper.php`: renamed class to `ZbjatZipper`, replaced `echo` with Monolog, extracted God method, switched to HTTPS.
- Refactored `Episciences_Paper_AuthorsManager` into `Episciences_Hal_TeiCacheManager`, `Episciences_Paper_Authors_HalTeiParser`, `Episciences_Paper_Authors_Repository`, `Episciences_Paper_Authors_EnrichmentService`, `Episciences_Paper_Authors_AffiliationHelper`, and `Episciences_Paper_Authors_ViewFormatter`.
- Refactored `Episciences_Paper_Authors_ViewFormatter` to separate data fetching from formatting logic.
- Moved `normalizeOrcid()` implementation from `AuthorsManager` to `HalTeiParser`.
- `AuthorsManager::ONE_MONTH` now references `TeiCacheManager::ONE_MONTH`.
- Added `TeiCacheManager::fetchAndGet()` to combine fetch-if-needed + read in a single call.
- The "Encapsulate reviewers" parameter is now hidden.
- [#528](https://github.com/CCSDForge/episciences/issues/528): 'upload a new version' on top; updated contact label.
- Paper metrics Refactored; 1 query instead of 2.
- The Locale is now stored in a cookie instead of the session.
- Redirect to the article to be reviewed when accepting an invitation.
- Allow volume years to be a string (AAAA or AAAA-AAAA).
- Synchronization of predefined page titles between navigation menu and T_PAGES table when saving menu configuration.
- Replaced `cottagelabs/coar-notifications` (Doctrine ORM + Guzzle) with `cottagelabs/coarnotify` (stateless pattern builder) and a custom PDO persistence layer (`Notification` DTO + `NotificationsRepository`)
- Refactored `InboxNotifications` (1390-line God Class) into focused private methods (`processFirstSubmission`, `processSubsequentSubmission`, `setupJournalTranslations`, `resolveNewVersionStatus`, `logVersionUpdate`, …) and pure, dependency-free helper classes
- `checkNotifyPayloads()` and `initSubmission()` are now source-aware: the COAR Notify source is resolved from `origin.inbox` via `NotifySourceRegistry`
- `InitSubmissionsFromHalInbox.php` is now a direct CLI entry point on `InboxNotifications`; the empty subclass has been removed

### Fixed

- [#858](https://github.com/CCSDForge/episciences/issues/858) fix: only use "et al." when 2+ authors are omitted. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Limit the editor’s permissions to allow assigning only the "reviewer" role.
- [#944](https://github.com/CCSDForge/episciences/issues/944): An author informed us that they received this message signed with their name: %%SENDER_FULL_NAME%% and %%RECIPIENT_SCREEN_NAME%% filled with the same value
- [#258](https://github.com/CCSDForge/episciences-front/issues/258): Tables are not handled by default: Explicit table conversion
- Fixed RT#277365: in order to correct the printing of extra lines, two options have been added to the TinyMCE configuration to insert a <br> instead of a <p> and another option to remove extra <br>s at the end of a block
- Fixed tooltip initialization in volume and section assignment after AJAX refreshes and DataTable redraws.
- Fixed Solr search engine indexation of Authors with a middle name.
- Consider an additional review when sending reminders for an insufficient number of reviewers.
- `Projects`: `$_dateUpdated` default fixed; `getDateUpdated()` return type consistency.
- `Projects::setFunding()`: made method fluent.
- `Projects::setOptions()`: fixed `setIdproject()` alias and case-insensitive method lookup.
- `Projects::toArray()`: fixed `DateTime` serialization.
- `Repository::insert()`: replaced deprecated `VALUES()` syntax with row-alias syntax for MySQL 8.0.20+.
- `Repository`: exceptions now propagate instead of terminating script on DB errors.
- `ViewFormatter`: fixed XSS by escaping `$vfunding['url']` and other user-controlled values in HTML attributes.
- `HalApiClient::doGet()`: added `E_USER_WARNING` severity level.
- `EnrichmentService::resolveHalProjectIds()`: removed duplicate log message; sanitized cache key.
- `Citations_ViewFormatter`: removed double escaping; fixed unstable compound sort; added ORCID format validation.
- `Citations_Repository`: replaced deprecated MySQL `VALUES()` function; added check for `$docId <= 0`.
- `Citations` entity: fixed `toArray()` key name; fixed `$_updatedAt` type and default.
- ORCID normalization: `cleanLowerCaseOrcid()` fix; applied normalization in Zenodo and ARCHE hooks.
- `findAffiliationsOneAuthorByPaperId()`: fixed potential undefined variable.
- `hasAcronym()`: fixed iteration over nested `id` array.
- `HalTeiParser::getAuthorsFromHalTei()`: fixed logic to prevent enriching the wrong author.
- `ViewFormatter::buildAffiliationListHtml()`: fixed Stored XSS by escaping the affiliation acronym.
- `ViewFormatter`: fixed `html_entity_decode(htmlspecialchars())` no-op.
- `EnrichmentService::mergeExistingAffiliations()`: fixed matching key lookup with `array_search()`.
- `AffiliationHelper::isAcronymDuplicate()`: now iterates all identifiers.
- `AffiliationHelper::setOrUpdateRorAcronym()`: returns first match deterministically.
- `TeiCacheManager::buildApiUrl()`: applied `urlencode()` to prevent Solr query injection.
- `TeiCacheManager::getFromCache()`: removed dead `expiresAfter()` call.
- `Repository`: fixed `JSON_DECODE_FLAGS`.
- `CommentsManager::$_typeLabel`: added missing entry for `TYPE_CONTRIBUTOR_TO_REVIEWER`.
- `CommentsManager::updateUid()`: fixed negative UID guard.
- `FormatIssn::FormatIssn()`: fixed substring length.
- `Log::log()`: fixed uncaught exception from `$logger->log()`.
- `DoiAsLink::DoiAsLink()`: fixed label when no text provided.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass with `preg_quote()` and anchors.
- Fixed RT#277365: added TinyMCE configuration options to handle `<br>` and `<p>` correctly.
- [#886](https://github.com/CCSDForge/episciences/issues/886): reminder logic for lack of reviewers fixed.
- `convertToBytes()`: fixed handling of pure numeric strings and added validation.
- `isHal()`, `isHalUrl()`, `isArxiv()`: fixed regex anchors.
- Added comprehensive test coverage for `Episciences_Tools`.
- [#236](https://github.com/CCSDForge/episciences-front/issues/236): HTML entities (&) displayed in the title.
- [#695](https://github.com/CCSDForge/episciences/issues/695) Fixed CC/BCC fields not clickable in paper status change forms.
- [#830](https://github.com/CCSDForge/episciences/issues/830) Paper number messed up for secondary volume; altered secondary volume rendering.
- [#776](https://github.com/CCSDForge/episciences/issues/776) Action Required: Fix Renovate Configuration.
- [#657](https://github.com/CCSDForge/episciences/issues/657) Conditionally remove the `<hr>` separator in metadata views.
- [#786](https://github.com/CCSDForge/episciences/issues/786) English translation of 'Télécharger le fichier'.
- [#646](https://github.com/CCSDForge/episciences/issues/646) Redirect to article page after submission.
- [#780](https://github.com/CCSDForge/episciences/issues/780) Option to lock volume name when an article is published.
- [#147](https://github.com/CCSDForge/episciences/issues/147) Add new pages Acknowledgements in menu 'About'.
- Fixed Paper Metrics based on wrong DocId.
- Fixed Pre-defined pages not deleted from the database.
- [#77](https://github.com/CCSDForge/episciences-front/issues/77) Fix orphan assignments when deleting sections or volumes.
- `CrossrefTools`: fixed fatal error on constant name; fixed raw `CacheItem` return.
- `OpenalexTools`: fixed `getPages()` return value and trailing separator in `getAuthors()`.
- `OpencitationsTools`: fixed caching of empty responses; fixed DOI extraction and updated API format support.
- `getLinkData`: switched to HTTPS; added null guards for `$valuesResult`.
- `getLicenceDataEnrichment`: fixed ineffective cache.
- `OpenAireResearchGraphTools`: enabled strict mode for `array_search()`.
- `Projects/EnrichmentService`: fixed log path and added `(int)` cast for source ID.
- `Citations/EnrichmentService`: added null guards for OpenAlex API fields.
- [#875](https://github.com/CCSDForge/episciences/issues/875): fixed HTML/Emoji translation issue.

### Deprecated

- `Episciences_Cache` and its parent `Ccsd_Cache` are now marked `@deprecated`; use `Symfony\Component\Cache\Adapter\FilesystemAdapter` instead.
- `ProjectsManager` kept as a thin backward-compatible facade.
- `CitationsManager` kept as backward-compatible facade with `@deprecated` proxies.
- `AuthorsManager` kept as orchestrator with backward-compatible `@deprecated` proxies.
- `Episciences_Paper_Visits::count` is deprecated.

### Removed

- Redundant "Statut actuel :" label prefix from the article status panel.
- Stray `<br>` and inlined `versionCache` script tag in `partials/paper_affiliation_authors.phtml`.
- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`.

### Security

- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`, eliminating a potential PHP object injection vector.
- OAI resumption token cache keys are now MD5-hashed before use, preventing cache-key injection.
- Escaped output in `volume/editors_list.phtml` to prevent potential XSS.
- Fixed XSS vulnerability in `Projects/ViewFormatter`: funding URL was interpolated unescaped into `href` attribute and link text.
- Fixed XSS in `Citations_ViewFormatter`: `href=` attributes for DOI and OA links were unquoted.
- Fixed XSS vulnerability in `ViewFormatter::buildAuthorHtml()` and `buildAffiliationListHtml()` regarding user-controlled values in HTML attributes.
- Fixed potential Solr query injection in `TeiCacheManager::buildApiUrl()`.
- `GetAvatar::asPaperStatusSvg()`: fixed two path traversal vectors ($lang sanitization and $paperStatus casting).
- `DoiAsLink::DoiAsLink()`: added `rel="noopener noreferrer"` to prevent tab-napping.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass that allowed authentication from unauthorized email domains.

### Performances

- `volume/list.phtml`: Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: construction of the editors list optimized.
- `Episciences_Paper_Citations_Logger`: Singleton Monolog logger (avoids Logger recreation on every call).
- Paper metrics Refactored; 1 query instead of 2.

## v1.0.55.3 - 2026-05-20

### Fixed

- [RT#287093]: Failed to submit "acceptedAskAuthorsFinalVersion" Form: CSRF validation failed (CSRF field missing)
- Adapt "acceptedAskAuthorsFinalVersion" form to the requirements for setting a deadline for requesting a revision after acceptance

### Changed

- [#1010](https://github.com/CCSDForge/episciences/issues/1010) The allowed range for modifying the deadline is now between the original deadline minus "rating_deadline_min" and the original deadline plus "rating_deadline_max".
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration
  button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)"

## v1.0.55.2 - 2026-05-19

### Fixed

- Improved article URL resolution in `ExtractBiblioRefsCommand` by adding fallbacks to `getMainPaperUrl()` and `getDocUrl()` when the repository template is empty (e.g., for Zenodo).
- Added debug logging for resolved article URLs and included raw response snippets in JSON decode error messages in `ExtractBiblioRefsCommand`.

## v1.0.55.1 - 2026-05-19

### Added

- Legend for bibliographic reference status icons in both admin and public views (publicly shown only when problematic references are detected).

### Changed

- Replaced bibliographic reference status indicators with standardized square icons (red `fa-square-xmark` for problematic, green `fa-square-check` for validated, and gray `fa-square` for automatically extracted references).
- Improved accessibility of bibliographic references (added ARIA landmarks, screen-reader-only status prefixes, descriptions for status badges, and warnings for links opening in new tabs).
- Optimized bibliographic references layout by moving the status legend inline with the action button.
- Renamed 'Not validated reference' to 'Automatically extracted reference' for clarity.
- Renamed 'Manage' button to 'Manage References' / 'Gérer les références' in the administration view.

### Fixed

- Removed obsolete BibTeX import button (feature migrated to an external application).
- Removed hardcoded Semantic Scholar source attribution from bibliographic references display.
- Skip temporary versions (without external repository URL) during bibliographic reference extraction.
- Fixed a missing dependency (`require_once`) for `GetDoiCommand` in `console.php`.

## v1.0.55 - 2026-05-18

### Added

- Automatic detection of problematic papers cited in the references of the papers. This is heavily based on the huge work provided by the Problematic Paper Screener https://dbrech.irit.fr/pls/apex/f?p=9999:1::::::
  For more information: Cabanac, G., Labbé, C., & Magazinov, A. (2022). The ‘Problematic Paper Screener’ automatically selects suspect publications for post-publication (re)assessment.
  Presented at WCRI 2022: 7th World Conference on Research Integrity. arXiv preprint. https://doi.org/10.48550/arXiv.2210.04895

- [#630](https://github.com/CCSDForge/episciences/issues/630) COI (Conflict of Interest) notifications:
    - Email notification to the editor-in-chief when an editor declares a positive COI (answered "yes")
    - Email notification to other assigned editors (if any) when an editor declares a COI
- Many legacy scripts migrated to Symfony Console commands (e.g., `doi:manage` replaces `getDoi.php`, `sitemap:generate --all`, translations update).
- Expanded unit test coverage for repository connectors (Zenodo, BioMedRxiv, ARCHE, Dspace) and COI notifications (+60 tests).

### Changed

- Sending the invitation in the user’s language, including when using the autocomplete input field for CAS users who already have a profile on the site.
- Simplified news add/edit form for a better user experience.
- Refactored OpenAIRE integration into a dedicated `OpenAireApiClient`.
- Improved PHP 8.1 compatibility and robustness across the codebase (removed deprecations and warnings).
- Optimized the submission workflow processing order.

### Fixed

- A bug that likely appeared in the latest update: section and/or volume editors are not assigned automatically, even if the relevant settings are enabled.
- Panel not opening when updating linked data
- Submission of a dataset without a data descriptor due to an error while uploading attached files (one of the file types exceeded the maximum allowed size for that field type)
- Corrected article position in volumes (1-based instead of 0-based) for CSL generation.
- Resolved relative `hydra:next` URLs in sitemap generation.
- Fixed several potential `TypeError` and crashes when metadata or publication dates are missing.

## v1.0.54.3 - 2026-04-23

### Fixed

- The link to the new version of the data descriptor isn't working; it still points to the first version.
- [RT#285106]: Application error when submitting with 'display secondary volume' option enabled
- Always SELECT RECORD so Paper::getMetadata() / toJson() can resolve titles and abstracts
- Log parser: extract IP from syslog-prefixed Apache log lines

## v1.0.54.2 - 2026-04-16

### Fixed

- Inability to change the review deadline (restore loading js via url).

### Added

- docs: add documentation index to README and update docs
- stats:import-logs command to parse Apache logs into STAT_TEMP. Introduces ImportApacheLogsCommand (stats:import-logs), a Symfony Console command that parses Apache access logs and inserts article visits into STAT_TEMP. Replaces the legacy UpdateStatistics.php . Run src/mysql/2025-08-24-stat-processing-log-table.sql before deploying

## v1.0.54.1 - 2026-04-14

### Changed

- Refactored English email templates, grammar only, no new features, no new tags

### Fixed

- [#962](https://github.com/CCSDForge/episciences/issues/962) Fixed double HTML encoding bug in volume metadata titles where special
  characters like `<`, `>`, and `'` were displayed as `&lt;`, `&gt;`, `&#039;`
- TypeError in enrichment:creators on non-sequential affiliation keys
- isImported detection for published papers when the the publication date has been fixed after publication
- [#985](https://github.com/CCSDForge/episciences/issues/985) Impossible to submit a final version
- Fixed bug in management of journal headers for adding and updating logo and texts

## v1.0.54 - 2026-04-09

### Added

- [#779](https://github.com/CCSDForge/episciences/issues/779) [Feature request] Display the secondary volume on the article page. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Adding "Publish" => "Proposing special issues" template page [#951](https://github.com/CCSDForge/episciences/issues/951)
- New supported servers:
    - Cryptology ePrint Archive (https://eprint.iacr.org)
    - Support for any data repositories powered by Dspace (eg. [repositorium.uminho(University of Minho)](https://repositorium.uminho.pt))
- Statistics: new Symfony Console commands `stats:process` (replaces legacy `scripts/stat.php`) and `stats:update-robots-list` for UA-based bot detection using the COUNTER Robots list; real client IP is now stored in `STAT_TEMP` and anonymized at processing time.
- Documentation: added `docs/console-commands.md` listing all 19 CLI commands available via `scripts/console.php` with options and usage examples.
- OAI-PMH: added `crossref` as a new metadata format (`metadataPrefix=crossref`), exposing Crossref 5.3.1 XML for each published paper. The depositor email is automatically replaced with a generic `noreply@{domain}` address in this context.
- Comprehensive Jest test suite for ORCID authors management (`tests/js/updateOrcidAuthors.test.js`).
- Unit tests for `Episciences_Paper_Projects` entity (setOptions aliases, DateTime handling, toArray serialization, fluent setters).
- Unit tests for `Episciences_Paper_Projects_EnrichmentService` pure functions (EU/ANR response normalization, OpenAIRE relation filtering).
- Unit tests for `Episciences_Paper_Projects_ViewFormatter` (empty-array return, URL HTML-escaping, unidentified title suppression).
- Unit tests for `Episciences_Paper_Citations_ViewFormatter` (20 tests: sort, author formatting, ORCID links, XSS prevention, book-chapter/proceedings-article reordering).
- Unit tests for `Episciences_Paper_Citations` entity (8 tests: `toArray()` key, fluent setters, nullable `updatedAt`, constructor).
- Comprehensive unit tests for `Episciences_Paper_Authors_ViewFormatter` covering HTML display logic and XSS prevention.
- Unit tests for `Episciences_View_Helper_DoiAsLink`, `Episciences_View_Helper_FormatIssn`, `Episciences_View_Helper_Log`, `Episciences_View_Helper_Tag`, `Episciences_View_Helper_UserAvatar`, `Episciences_View_Helper_GetAvatar` and `Episciences_CommentsManager`.
- 89+ unit tests for `Ccsd\Auth` adapters (`CasAbstract`, `Idp`, `Orcid`, `AdapterFactory`, `Asso`), `Ccsd\User` models (`User`, `UserTokens`, `UserFtpQuota`) and `Episciences\User` entities covering pure logic without DB or network access.
- Updated `tests/README.md` with accurate Docker-based testing instructions, `make` target reference, subset-run examples, and contributor guidelines.
- [#883](https://github.com/CCSDForge/episciences/issues/883) Allow json files as attachments.
- Optional Webhook: It is now possible to report status changes to an external entry point (can be configured by journal).
- [#658](https://github.com/CCSDForge/episciences/issues/658) It is now possible to link an invitation that is not intended for you to your account.
- allow the co-author to view the publication.
- Statistics: the script has a new parameter `--all` - Process all statistics (with confirmation prompt).
- New option to allow Editors to receive 'Comments for editors' before declaring a conflict of interest (disabled by default).
- Volume Settings: add New configuration section(displayEmptyVolumes and allowEditVolumeTitleWithPublishedArticles) in journal settings.
- [#679](https://github.com/CCSDForge/episciences/issues/679) "Ask other editors for their opinion" form now includes chief editors (ROLE_CHIEF_EDITOR) in addition to regular editors (ROLE_EDITOR).
- [#691](http://github.com/CCSDForge/episciences/issues/691) Display "(optional)" label below comment and cover letter fields in submission forms to clarify these fields are not required.
- [#350](https://github.com/CCSDForge/episciences/issues/350) Author-Editor Communication Settings:
    - New option to allow authors to contact assigned editors (`authorEditorCommunication`)
    - New option to disclose editor names to authors or keep them anonymized (`discloseEditorNamesToAuthors`)
- feat(navigation): sync predefined page titles with T_PAGES table on menu save.
- New `library/Episciences/Api/` namespace with 5 injectable, independently-testable API clients: `AbstractApiClient`, `CrossrefApiClient`, `OpenAlexApiClient`, `OpenCitationsApiClient`, `OpenAireApiClient`.
- Five Symfony Console commands replacing the legacy JournalScript enrichment scripts: `enrichment:citations`, `enrichment:creators`, `enrichment:funding`, `enrichment:licences`, `enrichment:links`.
- Added MSC2020 Classification in ZBJATS export.
- `Episciences\Notify\NotifySourceConfig` — immutable value object holding per-source configuration (repoId, label, originId, originInbox, acceptedTypes); new repositories can be added without touching `InboxNotifications`
- `Episciences\Notify\NotifySourceRegistry` — registry with a `createFromConstants()` factory; resolves source config from a payload's `origin.inbox`
- `Episciences\Notify\ValidationResult` — pure value object wrapping a validation outcome (success / failure + error message)
- `Episciences\Notify\PreprintUrlParser` — pure helper extracting version and identifier from preprint landing-page URLs (HAL-style)
- `Episciences\Notify\PayloadValidator` — validates COAR Notify 1.0.1 payloads for Offer patterns (Request Review / Request Endorsement): all required fields including `object.id` (HTTP URI) and `object['ietf:item']` (id, type, mediaType)

### Changed

- [#947](https://github.com/CCSDForge/episciences/issues/947) [Feature request] wording on the platform - refused / rejected and refused/declined
- Refactored `public/js/paper/updateOrcidAuthors.js` from jQuery to modern vanilla ES6+ with a class-based architecture.
- Enhanced ORCID form validation: added duplicate detection while maintaining support for empty submissions for deletions.
- Update TinyMCE form 7.3.0 to 8.1.2.
- Increased Volume Description length to 1024 chars.
- DOI panel: requesting, saving, and cancelling a DOI no longer triggers a full page reload; success feedback message is suppressed.
- Refactored `Episciences_Paper_ProjectsManager` into `Episciences_Paper_Projects_Repository`, `Episciences_Paper_Projects_HalApiClient`, `Episciences_Paper_Projects_EnrichmentService`, and `Episciences_Paper_Projects_ViewFormatter`.
- Refactored `Episciences_Paper_CitationsManager` into `Episciences_Paper_Citations_Repository`, `Episciences_Paper_Citations_ViewFormatter`, `Episciences_Paper_Citations_EnrichmentService`, and `Episciences_Paper_Citations_Logger`.
- Redundant PHPDoc removed from all new citations classes via Rector.
- Replaced `Episciences_Cache` with `symfony/cache` 5.4 across all internal usages.
- `PapersManager::getList()`: removed `$cached` parameter; paper list is now always fetched fresh.
- `Review::getPapers()`, `CopyEditor::loadAssignedPapers()`, `Editor::loadAssignedPapers()`, `Reviewer::loadAssignedPapers()`, `Volume::getPaperListFromVolume()`: updated signatures and call sites following the removal of `$cached`.
- `Oai/Server::getIds()`: OAI resumption token cache migrated to PSR-6.
- Statistics: log import script now supports anonymized log files (.access_log_anonym.gz) as a fallback.
- Improved accessibility of the ORCID authors modal: proper `<label>` associations, `aria-modal="true"`, and corrected `aria-labelledby`.
- Modernized ORCID authors row layout using Flexbox.
- Standardized modal button styles for ORCID updates (Cancel: `btn-default`, Save: `btn-primary`).
- ORCID input fields: URLs are now automatically cleaned to keep only the 16-digit identifier.
- `administratepaper/view.phtml`: reordered panels; moved "Volumes & Rubriques" earlier; `paper_versions` moved to the bottom; removed redundant label.
- `paper/paper_datasets.phtml`: "Liens publications – données – logiciels" panel is now collapsed by default.
- `paper/paper_graphical_abstract.phtml`: graphical abstract panel is now collapsed by default when empty.
- `partials/coauthors.phtml`: "Ajouter un co-auteur" panel is now collapsed by default.
- `partials/paper_affiliation_authors.phtml`: "Ajouter une affiliation" panel is now collapsed by default.
- `volume/list.phtml`: Added "Number" and "Year" columns; improved table accessibility; Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: Removed redundant inline scripts; construction of the editors list optimized.
- Updated icons from `fas fa-address-card` to `fa-regular fa-address-card`.
- Improved visual indicators for editor availability in `editor-availability.js`.
- Refactored `scripts/zbjatZipper.php`: renamed class to `ZbjatZipper`, replaced `echo` with Monolog, extracted God method, switched to HTTPS.
- Refactored `Episciences_Paper_AuthorsManager` into `Episciences_Hal_TeiCacheManager`, `Episciences_Paper_Authors_HalTeiParser`, `Episciences_Paper_Authors_Repository`, `Episciences_Paper_Authors_EnrichmentService`, `Episciences_Paper_Authors_AffiliationHelper`, and `Episciences_Paper_Authors_ViewFormatter`.
- Refactored `Episciences_Paper_Authors_ViewFormatter` to separate data fetching from formatting logic.
- Moved `normalizeOrcid()` implementation from `AuthorsManager` to `HalTeiParser`.
- `AuthorsManager::ONE_MONTH` now references `TeiCacheManager::ONE_MONTH`.
- Added `TeiCacheManager::fetchAndGet()` to combine fetch-if-needed + read in a single call.
- The "Encapsulate reviewers" parameter is now hidden.
- [#528](https://github.com/CCSDForge/episciences/issues/528): 'upload a new version' on top; updated contact label.
- Paper metrics Refactored; 1 query instead of 2.
- The Locale is now stored in a cookie instead of the session.
- Redirect to the article to be reviewed when accepting an invitation.
- Allow volume years to be a string (AAAA or AAAA-AAAA).
- Synchronization of predefined page titles between navigation menu and T_PAGES table when saving menu configuration.
- Replaced `cottagelabs/coar-notifications` (Doctrine ORM + Guzzle) with `cottagelabs/coarnotify` (stateless pattern builder) and a custom PDO persistence layer (`Notification` DTO + `NotificationsRepository`)
- Refactored `InboxNotifications` (1390-line God Class) into focused private methods (`processFirstSubmission`, `processSubsequentSubmission`, `setupJournalTranslations`, `resolveNewVersionStatus`, `logVersionUpdate`, …) and pure, dependency-free helper classes
- `checkNotifyPayloads()` and `initSubmission()` are now source-aware: the COAR Notify source is resolved from `origin.inbox` via `NotifySourceRegistry`
- `InitSubmissionsFromHalInbox.php` is now a direct CLI entry point on `InboxNotifications`; the empty subclass has been removed

### Fixed

- [#858](https://github.com/CCSDForge/episciences/issues/858) fix: only use "et al." when 2+ authors are omitted. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Limit the editor’s permissions to allow assigning only the "reviewer" role.
- [#944](https://github.com/CCSDForge/episciences/issues/944): An author informed us that they received this message signed with their name: %%SENDER_FULL_NAME%% and %%RECIPIENT_SCREEN_NAME%% filled with the same value
- [#258](https://github.com/CCSDForge/episciences-front/issues/258): Tables are not handled by default: Explicit table conversion
- Fixed RT#277365: in order to correct the printing of extra lines, two options have been added to the TinyMCE configuration to insert a <br> instead of a <p> and another option to remove extra <br>s at the end of a block
- Fixed tooltip initialization in volume and section assignment after AJAX refreshes and DataTable redraws.
- Fixed Solr search engine indexation of Authors with a middle name.
- Consider an additional review when sending reminders for an insufficient number of reviewers.
- `Projects`: `$_dateUpdated` default fixed; `getDateUpdated()` return type consistency.
- `Projects::setFunding()`: made method fluent.
- `Projects::setOptions()`: fixed `setIdproject()` alias and case-insensitive method lookup.
- `Projects::toArray()`: fixed `DateTime` serialization.
- `Repository::insert()`: replaced deprecated `VALUES()` syntax with row-alias syntax for MySQL 8.0.20+.
- `Repository`: exceptions now propagate instead of terminating script on DB errors.
- `ViewFormatter`: fixed XSS by escaping `$vfunding['url']` and other user-controlled values in HTML attributes.
- `HalApiClient::doGet()`: added `E_USER_WARNING` severity level.
- `EnrichmentService::resolveHalProjectIds()`: removed duplicate log message; sanitized cache key.
- `Citations_ViewFormatter`: removed double escaping; fixed unstable compound sort; added ORCID format validation.
- `Citations_Repository`: replaced deprecated MySQL `VALUES()` function; added check for `$docId <= 0`.
- `Citations` entity: fixed `toArray()` key name; fixed `$_updatedAt` type and default.
- ORCID normalization: `cleanLowerCaseOrcid()` fix; applied normalization in Zenodo and ARCHE hooks.
- `findAffiliationsOneAuthorByPaperId()`: fixed potential undefined variable.
- `hasAcronym()`: fixed iteration over nested `id` array.
- `HalTeiParser::getAuthorsFromHalTei()`: fixed logic to prevent enriching the wrong author.
- `ViewFormatter::buildAffiliationListHtml()`: fixed Stored XSS by escaping the affiliation acronym.
- `ViewFormatter`: fixed `html_entity_decode(htmlspecialchars())` no-op.
- `EnrichmentService::mergeExistingAffiliations()`: fixed matching key lookup with `array_search()`.
- `AffiliationHelper::isAcronymDuplicate()`: now iterates all identifiers.
- `AffiliationHelper::setOrUpdateRorAcronym()`: returns first match deterministically.
- `TeiCacheManager::buildApiUrl()`: applied `urlencode()` to prevent Solr query injection.
- `TeiCacheManager::getFromCache()`: removed dead `expiresAfter()` call.
- `Repository`: fixed `JSON_DECODE_FLAGS`.
- `CommentsManager::$_typeLabel`: added missing entry for `TYPE_CONTRIBUTOR_TO_REVIEWER`.
- `CommentsManager::updateUid()`: fixed negative UID guard.
- `FormatIssn::FormatIssn()`: fixed substring length.
- `Log::log()`: fixed uncaught exception from `$logger->log()`.
- `DoiAsLink::DoiAsLink()`: fixed label when no text provided.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass with `preg_quote()` and anchors.
- Fixed RT#277365: added TinyMCE configuration options to handle `<br>` and `<p>` correctly.
- [#886](https://github.com/CCSDForge/episciences/issues/886): reminder logic for lack of reviewers fixed.
- `convertToBytes()`: fixed handling of pure numeric strings and added validation.
- `isHal()`, `isHalUrl()`, `isArxiv()`: fixed regex anchors.
- Added comprehensive test coverage for `Episciences_Tools`.
- [#236](https://github.com/CCSDForge/episciences-front/issues/236): HTML entities (&) displayed in the title.
- [#695](https://github.com/CCSDForge/episciences/issues/695) Fixed CC/BCC fields not clickable in paper status change forms.
- [#830](https://github.com/CCSDForge/episciences/issues/830) Paper number messed up for secondary volume; altered secondary volume rendering.
- [#776](https://github.com/CCSDForge/episciences/issues/776) Action Required: Fix Renovate Configuration.
- [#657](https://github.com/CCSDForge/episciences/issues/657) Conditionally remove the `<hr>` separator in metadata views.
- [#786](https://github.com/CCSDForge/episciences/issues/786) English translation of 'Télécharger le fichier'.
- [#646](https://github.com/CCSDForge/episciences/issues/646) Redirect to article page after submission.
- [#780](https://github.com/CCSDForge/episciences/issues/780) Option to lock volume name when an article is published.
- [#147](https://github.com/CCSDForge/episciences/issues/147) Add new pages Acknowledgements in menu 'About'.
- Fixed Paper Metrics based on wrong DocId.
- Fixed Pre-defined pages not deleted from the database.
- [#77](https://github.com/CCSDForge/episciences-front/issues/77) Fix orphan assignments when deleting sections or volumes.
- `CrossrefTools`: fixed fatal error on constant name; fixed raw `CacheItem` return.
- `OpenalexTools`: fixed `getPages()` return value and trailing separator in `getAuthors()`.
- `OpencitationsTools`: fixed caching of empty responses; fixed DOI extraction and updated API format support.
- `getLinkData`: switched to HTTPS; added null guards for `$valuesResult`.
- `getLicenceDataEnrichment`: fixed ineffective cache.
- `OpenAireResearchGraphTools`: enabled strict mode for `array_search()`.
- `Projects/EnrichmentService`: fixed log path and added `(int)` cast for source ID.
- `Citations/EnrichmentService`: added null guards for OpenAlex API fields.
- [#875](https://github.com/CCSDForge/episciences/issues/875): fixed HTML/Emoji translation issue.

### Deprecated

- `Episciences_Cache` and its parent `Ccsd_Cache` are now marked `@deprecated`; use `Symfony\Component\Cache\Adapter\FilesystemAdapter` instead.
- `ProjectsManager` kept as a thin backward-compatible facade.
- `CitationsManager` kept as backward-compatible facade with `@deprecated` proxies.
- `AuthorsManager` kept as orchestrator with backward-compatible `@deprecated` proxies.
- `Episciences_Paper_Visits::count` is deprecated.

### Removed

- Redundant "Statut actuel :" label prefix from the article status panel.
- Stray `<br>` and inlined `versionCache` script tag in `partials/paper_affiliation_authors.phtml`.
- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`.

### Security

- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`, eliminating a potential PHP object injection vector.
- OAI resumption token cache keys are now MD5-hashed before use, preventing cache-key injection.
- Escaped output in `volume/editors_list.phtml` to prevent potential XSS.
- Fixed XSS vulnerability in `Projects/ViewFormatter`: funding URL was interpolated unescaped into `href` attribute and link text.
- Fixed XSS in `Citations_ViewFormatter`: `href=` attributes for DOI and OA links were unquoted.
- Fixed XSS vulnerability in `ViewFormatter::buildAuthorHtml()` and `buildAffiliationListHtml()` regarding user-controlled values in HTML attributes.
- Fixed potential Solr query injection in `TeiCacheManager::buildApiUrl()`.
- `GetAvatar::asPaperStatusSvg()`: fixed two path traversal vectors ($lang sanitization and $paperStatus casting).
- `DoiAsLink::DoiAsLink()`: added `rel="noopener noreferrer"` to prevent tab-napping.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass that allowed authentication from unauthorized email domains.

### Performances

- `volume/list.phtml`: Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: construction of the editors list optimized.
- `Episciences_Paper_Citations_Logger`: Singleton Monolog logger (avoids Logger recreation on every call).
- Paper metrics Refactored; 1 query instead of 2.

## v1.0.55.3 - 2026-05-20

### Fixed

- [RT#287093]: Failed to submit "acceptedAskAuthorsFinalVersion" Form: CSRF validation failed (CSRF field missing)
- Adapt "acceptedAskAuthorsFinalVersion" form to the requirements for setting a deadline for requesting a revision after acceptance

### Changed

- [#1010](https://github.com/CCSDForge/episciences/issues/1010) The allowed range for modifying the deadline is now between the original deadline minus "rating_deadline_min" and the original deadline plus "rating_deadline_max".
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration
  button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)"

## v1.0.55.2 - 2026-05-19

### Fixed

- Improved article URL resolution in `ExtractBiblioRefsCommand` by adding fallbacks to `getMainPaperUrl()` and `getDocUrl()` when the repository template is empty (e.g., for Zenodo).
- Added debug logging for resolved article URLs and included raw response snippets in JSON decode error messages in `ExtractBiblioRefsCommand`.

## v1.0.55.1 - 2026-05-19

### Added

- Legend for bibliographic reference status icons in both admin and public views (publicly shown only when problematic references are detected).

### Changed

- Replaced bibliographic reference status indicators with standardized square icons (red `fa-square-xmark` for problematic, green `fa-square-check` for validated, and gray `fa-square` for automatically extracted references).
- Improved accessibility of bibliographic references (added ARIA landmarks, screen-reader-only status prefixes, descriptions for status badges, and warnings for links opening in new tabs).
- Optimized bibliographic references layout by moving the status legend inline with the action button.
- Renamed 'Not validated reference' to 'Automatically extracted reference' for clarity.
- Renamed 'Manage' button to 'Manage References' / 'Gérer les références' in the administration view.

### Fixed

- Removed obsolete BibTeX import button (feature migrated to an external application).
- Removed hardcoded Semantic Scholar source attribution from bibliographic references display.
- Skip temporary versions (without external repository URL) during bibliographic reference extraction.
- Fixed a missing dependency (`require_once`) for `GetDoiCommand` in `console.php`.

## v1.0.55 - 2026-05-18

### Added

- Automatic detection of problematic papers cited in the references of the papers. This is heavily based on the huge work provided by the Problematic Paper Screener https://dbrech.irit.fr/pls/apex/f?p=9999:1::::::
  For more information: Cabanac, G., Labbé, C., & Magazinov, A. (2022). The ‘Problematic Paper Screener’ automatically selects suspect publications for post-publication (re)assessment.
  Presented at WCRI 2022: 7th World Conference on Research Integrity. arXiv preprint. https://doi.org/10.48550/arXiv.2210.04895

- [#630](https://github.com/CCSDForge/episciences/issues/630) COI (Conflict of Interest) notifications:
    - Email notification to the editor-in-chief when an editor declares a positive COI (answered "yes")
    - Email notification to other assigned editors (if any) when an editor declares a COI
- Many legacy scripts migrated to Symfony Console commands (e.g., `doi:manage` replaces `getDoi.php`, `sitemap:generate --all`, translations update).
- Expanded unit test coverage for repository connectors (Zenodo, BioMedRxiv, ARCHE, Dspace) and COI notifications (+60 tests).

### Changed

- Sending the invitation in the user’s language, including when using the autocomplete input field for CAS users who already have a profile on the site.
- Simplified news add/edit form for a better user experience.
- Refactored OpenAIRE integration into a dedicated `OpenAireApiClient`.
- Improved PHP 8.1 compatibility and robustness across the codebase (removed deprecations and warnings).
- Optimized the submission workflow processing order.

### Fixed

- A bug that likely appeared in the latest update: section and/or volume editors are not assigned automatically, even if the relevant settings are enabled.
- Panel not opening when updating linked data
- Submission of a dataset without a data descriptor due to an error while uploading attached files (one of the file types exceeded the maximum allowed size for that field type)
- Corrected article position in volumes (1-based instead of 0-based) for CSL generation.
- Resolved relative `hydra:next` URLs in sitemap generation.
- Fixed several potential `TypeError` and crashes when metadata or publication dates are missing.

## v1.0.54.3 - 2026-04-23

### Fixed

- The link to the new version of the data descriptor isn't working; it still points to the first version.
- [RT#285106]: Application error when submitting with 'display secondary volume' option enabled
- Always SELECT RECORD so Paper::getMetadata() / toJson() can resolve titles and abstracts
- Log parser: extract IP from syslog-prefixed Apache log lines

## v1.0.54.2 - 2026-04-16

### Fixed

- Inability to change the review deadline (restore loading js via url).

### Added

- docs: add documentation index to README and update docs
- stats:import-logs command to parse Apache logs into STAT_TEMP. Introduces ImportApacheLogsCommand (stats:import-logs), a Symfony Console command that parses Apache access logs and inserts article visits into STAT_TEMP. Replaces the legacy UpdateStatistics.php . Run src/mysql/2025-08-24-stat-processing-log-table.sql before deploying

## v1.0.54.1 - 2026-04-14

### Changed

- Refactored English email templates, grammar only, no new features, no new tags

### Fixed

- [#962](https://github.com/CCSDForge/episciences/issues/962) Fixed double HTML encoding bug in volume metadata titles where special
  characters like `<`, `>`, and `'` were displayed as `&lt;`, `&gt;`, `&#039;`
- TypeError in enrichment:creators on non-sequential affiliation keys
- isImported detection for published papers when the the publication date has been fixed after publication
- [#985](https://github.com/CCSDForge/episciences/issues/985) Impossible to submit a final version
- Fixed bug in management of journal headers for adding and updating logo and texts

## v1.0.54 - 2026-04-09

### Added

- [#779](https://github.com/CCSDForge/episciences/issues/779) [Feature request] Display the secondary volume on the article page. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Adding "Publish" => "Proposing special issues" template page [#951](https://github.com/CCSDForge/episciences/issues/951)
- New supported servers:
    - Cryptology ePrint Archive (https://eprint.iacr.org)
    - Support for any data repositories powered by Dspace (eg. [repositorium.uminho(University of Minho)](https://repositorium.uminho.pt))
- Statistics: new Symfony Console commands `stats:process` (replaces legacy `scripts/stat.php`) and `stats:update-robots-list` for UA-based bot detection using the COUNTER Robots list; real client IP is now stored in `STAT_TEMP` and anonymized at processing time.
- Documentation: added `docs/console-commands.md` listing all 19 CLI commands available via `scripts/console.php` with options and usage examples.
- OAI-PMH: added `crossref` as a new metadata format (`metadataPrefix=crossref`), exposing Crossref 5.3.1 XML for each published paper. The depositor email is automatically replaced with a generic `noreply@{domain}` address in this context.
- Comprehensive Jest test suite for ORCID authors management (`tests/js/updateOrcidAuthors.test.js`).
- Unit tests for `Episciences_Paper_Projects` entity (setOptions aliases, DateTime handling, toArray serialization, fluent setters).
- Unit tests for `Episciences_Paper_Projects_EnrichmentService` pure functions (EU/ANR response normalization, OpenAIRE relation filtering).
- Unit tests for `Episciences_Paper_Projects_ViewFormatter` (empty-array return, URL HTML-escaping, unidentified title suppression).
- Unit tests for `Episciences_Paper_Citations_ViewFormatter` (20 tests: sort, author formatting, ORCID links, XSS prevention, book-chapter/proceedings-article reordering).
- Unit tests for `Episciences_Paper_Citations` entity (8 tests: `toArray()` key, fluent setters, nullable `updatedAt`, constructor).
- Comprehensive unit tests for `Episciences_Paper_Authors_ViewFormatter` covering HTML display logic and XSS prevention.
- Unit tests for `Episciences_View_Helper_DoiAsLink`, `Episciences_View_Helper_FormatIssn`, `Episciences_View_Helper_Log`, `Episciences_View_Helper_Tag`, `Episciences_View_Helper_UserAvatar`, `Episciences_View_Helper_GetAvatar` and `Episciences_CommentsManager`.
- 89+ unit tests for `Ccsd\Auth` adapters (`CasAbstract`, `Idp`, `Orcid`, `AdapterFactory`, `Asso`), `Ccsd\User` models (`User`, `UserTokens`, `UserFtpQuota`) and `Episciences\User` entities covering pure logic without DB or network access.
- Updated `tests/README.md` with accurate Docker-based testing instructions, `make` target reference, subset-run examples, and contributor guidelines.
- [#883](https://github.com/CCSDForge/episciences/issues/883) Allow json files as attachments.
- Optional Webhook: It is now possible to report status changes to an external entry point (can be configured by journal).
- [#658](https://github.com/CCSDForge/episciences/issues/658) It is now possible to link an invitation that is not intended for you to your account.
- allow the co-author to view the publication.
- Statistics: the script has a new parameter `--all` - Process all statistics (with confirmation prompt).
- New option to allow Editors to receive 'Comments for editors' before declaring a conflict of interest (disabled by default).
- Volume Settings: add New configuration section(displayEmptyVolumes and allowEditVolumeTitleWithPublishedArticles) in journal settings.
- [#679](https://github.com/CCSDForge/episciences/issues/679) "Ask other editors for their opinion" form now includes chief editors (ROLE_CHIEF_EDITOR) in addition to regular editors (ROLE_EDITOR).
- [#691](http://github.com/CCSDForge/episciences/issues/691) Display "(optional)" label below comment and cover letter fields in submission forms to clarify these fields are not required.
- [#350](https://github.com/CCSDForge/episciences/issues/350) Author-Editor Communication Settings:
    - New option to allow authors to contact assigned editors (`authorEditorCommunication`)
    - New option to disclose editor names to authors or keep them anonymized (`discloseEditorNamesToAuthors`)
- feat(navigation): sync predefined page titles with T_PAGES table on menu save.
- New `library/Episciences/Api/` namespace with 5 injectable, independently-testable API clients: `AbstractApiClient`, `CrossrefApiClient`, `OpenAlexApiClient`, `OpenCitationsApiClient`, `OpenAireApiClient`.
- Five Symfony Console commands replacing the legacy JournalScript enrichment scripts: `enrichment:citations`, `enrichment:creators`, `enrichment:funding`, `enrichment:licences`, `enrichment:links`.
- Added MSC2020 Classification in ZBJATS export.
- `Episciences\Notify\NotifySourceConfig` — immutable value object holding per-source configuration (repoId, label, originId, originInbox, acceptedTypes); new repositories can be added without touching `InboxNotifications`
- `Episciences\Notify\NotifySourceRegistry` — registry with a `createFromConstants()` factory; resolves source config from a payload's `origin.inbox`
- `Episciences\Notify\ValidationResult` — pure value object wrapping a validation outcome (success / failure + error message)
- `Episciences\Notify\PreprintUrlParser` — pure helper extracting version and identifier from preprint landing-page URLs (HAL-style)
- `Episciences\Notify\PayloadValidator` — validates COAR Notify 1.0.1 payloads for Offer patterns (Request Review / Request Endorsement): all required fields including `object.id` (HTTP URI) and `object['ietf:item']` (id, type, mediaType)

### Changed

- [#947](https://github.com/CCSDForge/episciences/issues/947) [Feature request] wording on the platform - refused / rejected and refused/declined
- Refactored `public/js/paper/updateOrcidAuthors.js` from jQuery to modern vanilla ES6+ with a class-based architecture.
- Enhanced ORCID form validation: added duplicate detection while maintaining support for empty submissions for deletions.
- Update TinyMCE form 7.3.0 to 8.1.2.
- Increased Volume Description length to 1024 chars.
- DOI panel: requesting, saving, and cancelling a DOI no longer triggers a full page reload; success feedback message is suppressed.
- Refactored `Episciences_Paper_ProjectsManager` into `Episciences_Paper_Projects_Repository`, `Episciences_Paper_Projects_HalApiClient`, `Episciences_Paper_Projects_EnrichmentService`, and `Episciences_Paper_Projects_ViewFormatter`.
- Refactored `Episciences_Paper_CitationsManager` into `Episciences_Paper_Citations_Repository`, `Episciences_Paper_Citations_ViewFormatter`, `Episciences_Paper_Citations_EnrichmentService`, and `Episciences_Paper_Citations_Logger`.
- Redundant PHPDoc removed from all new citations classes via Rector.
- Replaced `Episciences_Cache` with `symfony/cache` 5.4 across all internal usages.
- `PapersManager::getList()`: removed `$cached` parameter; paper list is now always fetched fresh.
- `Review::getPapers()`, `CopyEditor::loadAssignedPapers()`, `Editor::loadAssignedPapers()`, `Reviewer::loadAssignedPapers()`, `Volume::getPaperListFromVolume()`: updated signatures and call sites following the removal of `$cached`.
- `Oai/Server::getIds()`: OAI resumption token cache migrated to PSR-6.
- Statistics: log import script now supports anonymized log files (.access_log_anonym.gz) as a fallback.
- Improved accessibility of the ORCID authors modal: proper `<label>` associations, `aria-modal="true"`, and corrected `aria-labelledby`.
- Modernized ORCID authors row layout using Flexbox.
- Standardized modal button styles for ORCID updates (Cancel: `btn-default`, Save: `btn-primary`).
- ORCID input fields: URLs are now automatically cleaned to keep only the 16-digit identifier.
- `administratepaper/view.phtml`: reordered panels; moved "Volumes & Rubriques" earlier; `paper_versions` moved to the bottom; removed redundant label.
- `paper/paper_datasets.phtml`: "Liens publications – données – logiciels" panel is now collapsed by default.
- `paper/paper_graphical_abstract.phtml`: graphical abstract panel is now collapsed by default when empty.
- `partials/coauthors.phtml`: "Ajouter un co-auteur" panel is now collapsed by default.
- `partials/paper_affiliation_authors.phtml`: "Ajouter une affiliation" panel is now collapsed by default.
- `volume/list.phtml`: Added "Number" and "Year" columns; improved table accessibility; Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: Removed redundant inline scripts; construction of the editors list optimized.
- Updated icons from `fas fa-address-card` to `fa-regular fa-address-card`.
- Improved visual indicators for editor availability in `editor-availability.js`.
- Refactored `scripts/zbjatZipper.php`: renamed class to `ZbjatZipper`, replaced `echo` with Monolog, extracted God method, switched to HTTPS.
- Refactored `Episciences_Paper_AuthorsManager` into `Episciences_Hal_TeiCacheManager`, `Episciences_Paper_Authors_HalTeiParser`, `Episciences_Paper_Authors_Repository`, `Episciences_Paper_Authors_EnrichmentService`, `Episciences_Paper_Authors_AffiliationHelper`, and `Episciences_Paper_Authors_ViewFormatter`.
- Refactored `Episciences_Paper_Authors_ViewFormatter` to separate data fetching from formatting logic.
- Moved `normalizeOrcid()` implementation from `AuthorsManager` to `HalTeiParser`.
- `AuthorsManager::ONE_MONTH` now references `TeiCacheManager::ONE_MONTH`.
- Added `TeiCacheManager::fetchAndGet()` to combine fetch-if-needed + read in a single call.
- The "Encapsulate reviewers" parameter is now hidden.
- [#528](https://github.com/CCSDForge/episciences/issues/528): 'upload a new version' on top; updated contact label.
- Paper metrics Refactored; 1 query instead of 2.
- The Locale is now stored in a cookie instead of the session.
- Redirect to the article to be reviewed when accepting an invitation.
- Allow volume years to be a string (AAAA or AAAA-AAAA).
- Synchronization of predefined page titles between navigation menu and T_PAGES table when saving menu configuration.
- Replaced `cottagelabs/coar-notifications` (Doctrine ORM + Guzzle) with `cottagelabs/coarnotify` (stateless pattern builder) and a custom PDO persistence layer (`Notification` DTO + `NotificationsRepository`)
- Refactored `InboxNotifications` (1390-line God Class) into focused private methods (`processFirstSubmission`, `processSubsequentSubmission`, `setupJournalTranslations`, `resolveNewVersionStatus`, `logVersionUpdate`, …) and pure, dependency-free helper classes
- `checkNotifyPayloads()` and `initSubmission()` are now source-aware: the COAR Notify source is resolved from `origin.inbox` via `NotifySourceRegistry`
- `InitSubmissionsFromHalInbox.php` is now a direct CLI entry point on `InboxNotifications`; the empty subclass has been removed

### Fixed

- [#858](https://github.com/CCSDForge/episciences/issues/858) fix: only use "et al." when 2+ authors are omitted. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Limit the editor’s permissions to allow assigning only the "reviewer" role.
- [#944](https://github.com/CCSDForge/episciences/issues/944): An author informed us that they received this message signed with their name: %%SENDER_FULL_NAME%% and %%RECIPIENT_SCREEN_NAME%% filled with the same value
- [#258](https://github.com/CCSDForge/episciences-front/issues/258): Tables are not handled by default: Explicit table conversion
- Fixed RT#277365: in order to correct the printing of extra lines, two options have been added to the TinyMCE configuration to insert a <br> instead of a <p> and another option to remove extra <br>s at the end of a block
- Fixed tooltip initialization in volume and section assignment after AJAX refreshes and DataTable redraws.
- Fixed Solr search engine indexation of Authors with a middle name.
- Consider an additional review when sending reminders for an insufficient number of reviewers.
- `Projects`: `$_dateUpdated` default fixed; `getDateUpdated()` return type consistency.
- `Projects::setFunding()`: made method fluent.
- `Projects::setOptions()`: fixed `setIdproject()` alias and case-insensitive method lookup.
- `Projects::toArray()`: fixed `DateTime` serialization.
- `Repository::insert()`: replaced deprecated `VALUES()` syntax with row-alias syntax for MySQL 8.0.20+.
- `Repository`: exceptions now propagate instead of terminating script on DB errors.
- `ViewFormatter`: fixed XSS by escaping `$vfunding['url']` and other user-controlled values in HTML attributes.
- `HalApiClient::doGet()`: added `E_USER_WARNING` severity level.
- `EnrichmentService::resolveHalProjectIds()`: removed duplicate log message; sanitized cache key.
- `Citations_ViewFormatter`: removed double escaping; fixed unstable compound sort; added ORCID format validation.
- `Citations_Repository`: replaced deprecated MySQL `VALUES()` function; added check for `$docId <= 0`.
- `Citations` entity: fixed `toArray()` key name; fixed `$_updatedAt` type and default.
- ORCID normalization: `cleanLowerCaseOrcid()` fix; applied normalization in Zenodo and ARCHE hooks.
- `findAffiliationsOneAuthorByPaperId()`: fixed potential undefined variable.
- `hasAcronym()`: fixed iteration over nested `id` array.
- `HalTeiParser::getAuthorsFromHalTei()`: fixed logic to prevent enriching the wrong author.
- `ViewFormatter::buildAffiliationListHtml()`: fixed Stored XSS by escaping the affiliation acronym.
- `ViewFormatter`: fixed `html_entity_decode(htmlspecialchars())` no-op.
- `EnrichmentService::mergeExistingAffiliations()`: fixed matching key lookup with `array_search()`.
- `AffiliationHelper::isAcronymDuplicate()`: now iterates all identifiers.
- `AffiliationHelper::setOrUpdateRorAcronym()`: returns first match deterministically.
- `TeiCacheManager::buildApiUrl()`: applied `urlencode()` to prevent Solr query injection.
- `TeiCacheManager::getFromCache()`: removed dead `expiresAfter()` call.
- `Repository`: fixed `JSON_DECODE_FLAGS`.
- `CommentsManager::$_typeLabel`: added missing entry for `TYPE_CONTRIBUTOR_TO_REVIEWER`.
- `CommentsManager::updateUid()`: fixed negative UID guard.
- `FormatIssn::FormatIssn()`: fixed substring length.
- `Log::log()`: fixed uncaught exception from `$logger->log()`.
- `DoiAsLink::DoiAsLink()`: fixed label when no text provided.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass with `preg_quote()` and anchors.
- Fixed RT#277365: added TinyMCE configuration options to handle `<br>` and `<p>` correctly.
- [#886](https://github.com/CCSDForge/episciences/issues/886): reminder logic for lack of reviewers fixed.
- `convertToBytes()`: fixed handling of pure numeric strings and added validation.
- `isHal()`, `isHalUrl()`, `isArxiv()`: fixed regex anchors.
- Added comprehensive test coverage for `Episciences_Tools`.
- [#236](https://github.com/CCSDForge/episciences-front/issues/236): HTML entities (&) displayed in the title.
- [#695](https://github.com/CCSDForge/episciences/issues/695) Fixed CC/BCC fields not clickable in paper status change forms.
- [#830](https://github.com/CCSDForge/episciences/issues/830) Paper number messed up for secondary volume; altered secondary volume rendering.
- [#776](https://github.com/CCSDForge/episciences/issues/776) Action Required: Fix Renovate Configuration.
- [#657](https://github.com/CCSDForge/episciences/issues/657) Conditionally remove the `<hr>` separator in metadata views.
- [#786](https://github.com/CCSDForge/episciences/issues/786) English translation of 'Télécharger le fichier'.
- [#646](https://github.com/CCSDForge/episciences/issues/646) Redirect to article page after submission.
- [#780](https://github.com/CCSDForge/episciences/issues/780) Option to lock volume name when an article is published.
- [#147](https://github.com/CCSDForge/episciences/issues/147) Add new pages Acknowledgements in menu 'About'.
- Fixed Paper Metrics based on wrong DocId.
- Fixed Pre-defined pages not deleted from the database.
- [#77](https://github.com/CCSDForge/episciences-front/issues/77) Fix orphan assignments when deleting sections or volumes.
- `CrossrefTools`: fixed fatal error on constant name; fixed raw `CacheItem` return.
- `OpenalexTools`: fixed `getPages()` return value and trailing separator in `getAuthors()`.
- `OpencitationsTools`: fixed caching of empty responses; fixed DOI extraction and updated API format support.
- `getLinkData`: switched to HTTPS; added null guards for `$valuesResult`.
- `getLicenceDataEnrichment`: fixed ineffective cache.
- `OpenAireResearchGraphTools`: enabled strict mode for `array_search()`.
- `Projects/EnrichmentService`: fixed log path and added `(int)` cast for source ID.
- `Citations/EnrichmentService`: added null guards for OpenAlex API fields.
- [#875](https://github.com/CCSDForge/episciences/issues/875): fixed HTML/Emoji translation issue.

### Deprecated

- `Episciences_Cache` and its parent `Ccsd_Cache` are now marked `@deprecated`; use `Symfony\Component\Cache\Adapter\FilesystemAdapter` instead.
- `ProjectsManager` kept as a thin backward-compatible facade.
- `CitationsManager` kept as backward-compatible facade with `@deprecated` proxies.
- `AuthorsManager` kept as orchestrator with backward-compatible `@deprecated` proxies.
- `Episciences_Paper_Visits::count` is deprecated.

### Removed

- Redundant "Statut actuel :" label prefix from the article status panel.
- Stray `<br>` and inlined `versionCache` script tag in `partials/paper_affiliation_authors.phtml`.
- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`.

### Security

- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`, eliminating a potential PHP object injection vector.
- OAI resumption token cache keys are now MD5-hashed before use, preventing cache-key injection.
- Escaped output in `volume/editors_list.phtml` to prevent potential XSS.
- Fixed XSS vulnerability in `Projects/ViewFormatter`: funding URL was interpolated unescaped into `href` attribute and link text.
- Fixed XSS in `Citations_ViewFormatter`: `href=` attributes for DOI and OA links were unquoted.
- Fixed XSS vulnerability in `ViewFormatter::buildAuthorHtml()` and `buildAffiliationListHtml()` regarding user-controlled values in HTML attributes.
- Fixed potential Solr query injection in `TeiCacheManager::buildApiUrl()`.
- `GetAvatar::asPaperStatusSvg()`: fixed two path traversal vectors ($lang sanitization and $paperStatus casting).
- `DoiAsLink::DoiAsLink()`: added `rel="noopener noreferrer"` to prevent tab-napping.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass that allowed authentication from unauthorized email domains.

### Performances

- `volume/list.phtml`: Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: construction of the editors list optimized.
- `Episciences_Paper_Citations_Logger`: Singleton Monolog logger (avoids Logger recreation on every call).
- Paper metrics Refactored; 1 query instead of 2.

## v1.0.55.3 - 2026-05-20

### Fixed

- [RT#287093]: Failed to submit "acceptedAskAuthorsFinalVersion" Form: CSRF validation failed (CSRF field missing)
- Adapt "acceptedAskAuthorsFinalVersion" form to the requirements for setting a deadline for requesting a revision after acceptance

### Changed

- [#1010](https://github.com/CCSDForge/episciences/issues/1010) The allowed range for modifying the deadline is now between the original deadline minus "rating_deadline_min" and the original deadline plus "rating_deadline_max".
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration
  button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)"

## v1.0.55.2 - 2026-05-19

### Fixed

- Improved article URL resolution in `ExtractBiblioRefsCommand` by adding fallbacks to `getMainPaperUrl()` and `getDocUrl()` when the repository template is empty (e.g., for Zenodo).
- Added debug logging for resolved article URLs and included raw response snippets in JSON decode error messages in `ExtractBiblioRefsCommand`.

## v1.0.55.1 - 2026-05-19

### Added

- Legend for bibliographic reference status icons in both admin and public views (publicly shown only when problematic references are detected).

### Changed

- Replaced bibliographic reference status indicators with standardized square icons (red `fa-square-xmark` for problematic, green `fa-square-check` for validated, and gray `fa-square` for automatically extracted references).
- Improved accessibility of bibliographic references (added ARIA landmarks, screen-reader-only status prefixes, descriptions for status badges, and warnings for links opening in new tabs).
- Optimized bibliographic references layout by moving the status legend inline with the action button.
- Renamed 'Not validated reference' to 'Automatically extracted reference' for clarity.
- Renamed 'Manage' button to 'Manage References' / 'Gérer les références' in the administration view.

### Fixed

- Removed obsolete BibTeX import button (feature migrated to an external application).
- Removed hardcoded Semantic Scholar source attribution from bibliographic references display.
- Skip temporary versions (without external repository URL) during bibliographic reference extraction.
- Fixed a missing dependency (`require_once`) for `GetDoiCommand` in `console.php`.

## v1.0.55 - 2026-05-18

### Added

- Automatic detection of problematic papers cited in the references of the papers. This is heavily based on the huge work provided by the Problematic Paper Screener https://dbrech.irit.fr/pls/apex/f?p=9999:1::::::
  For more information: Cabanac, G., Labbé, C., & Magazinov, A. (2022). The ‘Problematic Paper Screener’ automatically selects suspect publications for post-publication (re)assessment.
  Presented at WCRI 2022: 7th World Conference on Research Integrity. arXiv preprint. https://doi.org/10.48550/arXiv.2210.04895

- [#630](https://github.com/CCSDForge/episciences/issues/630) COI (Conflict of Interest) notifications:
    - Email notification to the editor-in-chief when an editor declares a positive COI (answered "yes")
    - Email notification to other assigned editors (if any) when an editor declares a COI
- Many legacy scripts migrated to Symfony Console commands (e.g., `doi:manage` replaces `getDoi.php`, `sitemap:generate --all`, translations update).
- Expanded unit test coverage for repository connectors (Zenodo, BioMedRxiv, ARCHE, Dspace) and COI notifications (+60 tests).

### Changed

- Sending the invitation in the user’s language, including when using the autocomplete input field for CAS users who already have a profile on the site.
- Simplified news add/edit form for a better user experience.
- Refactored OpenAIRE integration into a dedicated `OpenAireApiClient`.
- Improved PHP 8.1 compatibility and robustness across the codebase (removed deprecations and warnings).
- Optimized the submission workflow processing order.

### Fixed

- A bug that likely appeared in the latest update: section and/or volume editors are not assigned automatically, even if the relevant settings are enabled.
- Panel not opening when updating linked data
- Submission of a dataset without a data descriptor due to an error while uploading attached files (one of the file types exceeded the maximum allowed size for that field type)
- Corrected article position in volumes (1-based instead of 0-based) for CSL generation.
- Resolved relative `hydra:next` URLs in sitemap generation.
- Fixed several potential `TypeError` and crashes when metadata or publication dates are missing.

## v1.0.54.3 - 2026-04-23

### Fixed

- The link to the new version of the data descriptor isn't working; it still points to the first version.
- [RT#285106]: Application error when submitting with 'display secondary volume' option enabled
- Always SELECT RECORD so Paper::getMetadata() / toJson() can resolve titles and abstracts
- Log parser: extract IP from syslog-prefixed Apache log lines

## v1.0.54.2 - 2026-04-16

### Fixed

- Inability to change the review deadline (restore loading js via url).

### Added

- docs: add documentation index to README and update docs
- stats:import-logs command to parse Apache logs into STAT_TEMP. Introduces ImportApacheLogsCommand (stats:import-logs), a Symfony Console command that parses Apache access logs and inserts article visits into STAT_TEMP. Replaces the legacy UpdateStatistics.php . Run src/mysql/2025-08-24-stat-processing-log-table.sql before deploying

## v1.0.54.1 - 2026-04-14

### Changed

- Refactored English email templates, grammar only, no new features, no new tags

### Fixed

- [#962](https://github.com/CCSDForge/episciences/issues/962) Fixed double HTML encoding bug in volume metadata titles where special
  characters like `<`, `>`, and `'` were displayed as `&lt;`, `&gt;`, `&#039;`
- TypeError in enrichment:creators on non-sequential affiliation keys
- isImported detection for published papers when the the publication date has been fixed after publication
- [#985](https://github.com/CCSDForge/episciences/issues/985) Impossible to submit a final version
- Fixed bug in management of journal headers for adding and updating logo and texts

## v1.0.54 - 2026-04-09

### Added

- [#779](https://github.com/CCSDForge/episciences/issues/779) [Feature request] Display the secondary volume on the article page. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Adding "Publish" => "Proposing special issues" template page [#951](https://github.com/CCSDForge/episciences/issues/951)
- New supported servers:
    - Cryptology ePrint Archive (https://eprint.iacr.org)
    - Support for any data repositories powered by Dspace (eg. [repositorium.uminho(University of Minho)](https://repositorium.uminho.pt))
- Statistics: new Symfony Console commands `stats:process` (replaces legacy `scripts/stat.php`) and `stats:update-robots-list` for UA-based bot detection using the COUNTER Robots list; real client IP is now stored in `STAT_TEMP` and anonymized at processing time.
- Documentation: added `docs/console-commands.md` listing all 19 CLI commands available via `scripts/console.php` with options and usage examples.
- OAI-PMH: added `crossref` as a new metadata format (`metadataPrefix=crossref`), exposing Crossref 5.3.1 XML for each published paper. The depositor email is automatically replaced with a generic `noreply@{domain}` address in this context.
- Comprehensive Jest test suite for ORCID authors management (`tests/js/updateOrcidAuthors.test.js`).
- Unit tests for `Episciences_Paper_Projects` entity (setOptions aliases, DateTime handling, toArray serialization, fluent setters).
- Unit tests for `Episciences_Paper_Projects_EnrichmentService` pure functions (EU/ANR response normalization, OpenAIRE relation filtering).
- Unit tests for `Episciences_Paper_Projects_ViewFormatter` (empty-array return, URL HTML-escaping, unidentified title suppression).
- Unit tests for `Episciences_Paper_Citations_ViewFormatter` (20 tests: sort, author formatting, ORCID links, XSS prevention, book-chapter/proceedings-article reordering).
- Unit tests for `Episciences_Paper_Citations` entity (8 tests: `toArray()` key, fluent setters, nullable `updatedAt`, constructor).
- Comprehensive unit tests for `Episciences_Paper_Authors_ViewFormatter` covering HTML display logic and XSS prevention.
- Unit tests for `Episciences_View_Helper_DoiAsLink`, `Episciences_View_Helper_FormatIssn`, `Episciences_View_Helper_Log`, `Episciences_View_Helper_Tag`, `Episciences_View_Helper_UserAvatar`, `Episciences_View_Helper_GetAvatar` and `Episciences_CommentsManager`.
- 89+ unit tests for `Ccsd\Auth` adapters (`CasAbstract`, `Idp`, `Orcid`, `AdapterFactory`, `Asso`), `Ccsd\User` models (`User`, `UserTokens`, `UserFtpQuota`) and `Episciences\User` entities covering pure logic without DB or network access.
- Updated `tests/README.md` with accurate Docker-based testing instructions, `make` target reference, subset-run examples, and contributor guidelines.
- [#883](https://github.com/CCSDForge/episciences/issues/883) Allow json files as attachments.
- Optional Webhook: It is now possible to report status changes to an external entry point (can be configured by journal).
- [#658](https://github.com/CCSDForge/episciences/issues/658) It is now possible to link an invitation that is not intended for you to your account.
- allow the co-author to view the publication.
- Statistics: the script has a new parameter `--all` - Process all statistics (with confirmation prompt).
- New option to allow Editors to receive 'Comments for editors' before declaring a conflict of interest (disabled by default).
- Volume Settings: add New configuration section(displayEmptyVolumes and allowEditVolumeTitleWithPublishedArticles) in journal settings.
- [#679](https://github.com/CCSDForge/episciences/issues/679) "Ask other editors for their opinion" form now includes chief editors (ROLE_CHIEF_EDITOR) in addition to regular editors (ROLE_EDITOR).
- [#691](http://github.com/CCSDForge/episciences/issues/691) Display "(optional)" label below comment and cover letter fields in submission forms to clarify these fields are not required.
- [#350](https://github.com/CCSDForge/episciences/issues/350) Author-Editor Communication Settings:
    - New option to allow authors to contact assigned editors (`authorEditorCommunication`)
    - New option to disclose editor names to authors or keep them anonymized (`discloseEditorNamesToAuthors`)
- feat(navigation): sync predefined page titles with T_PAGES table on menu save.
- New `library/Episciences/Api/` namespace with 5 injectable, independently-testable API clients: `AbstractApiClient`, `CrossrefApiClient`, `OpenAlexApiClient`, `OpenCitationsApiClient`, `OpenAireApiClient`.
- Five Symfony Console commands replacing the legacy JournalScript enrichment scripts: `enrichment:citations`, `enrichment:creators`, `enrichment:funding`, `enrichment:licences`, `enrichment:links`.
- Added MSC2020 Classification in ZBJATS export.
- `Episciences\Notify\NotifySourceConfig` — immutable value object holding per-source configuration (repoId, label, originId, originInbox, acceptedTypes); new repositories can be added without touching `InboxNotifications`
- `Episciences\Notify\NotifySourceRegistry` — registry with a `createFromConstants()` factory; resolves source config from a payload's `origin.inbox`
- `Episciences\Notify\ValidationResult` — pure value object wrapping a validation outcome (success / failure + error message)
- `Episciences\Notify\PreprintUrlParser` — pure helper extracting version and identifier from preprint landing-page URLs (HAL-style)
- `Episciences\Notify\PayloadValidator` — validates COAR Notify 1.0.1 payloads for Offer patterns (Request Review / Request Endorsement): all required fields including `object.id` (HTTP URI) and `object['ietf:item']` (id, type, mediaType)

### Changed

- [#947](https://github.com/CCSDForge/episciences/issues/947) [Feature request] wording on the platform - refused / rejected and refused/declined
- Refactored `public/js/paper/updateOrcidAuthors.js` from jQuery to modern vanilla ES6+ with a class-based architecture.
- Enhanced ORCID form validation: added duplicate detection while maintaining support for empty submissions for deletions.
- Update TinyMCE form 7.3.0 to 8.1.2.
- Increased Volume Description length to 1024 chars.
- DOI panel: requesting, saving, and cancelling a DOI no longer triggers a full page reload; success feedback message is suppressed.
- Refactored `Episciences_Paper_ProjectsManager` into `Episciences_Paper_Projects_Repository`, `Episciences_Paper_Projects_HalApiClient`, `Episciences_Paper_Projects_EnrichmentService`, and `Episciences_Paper_Projects_ViewFormatter`.
- Refactored `Episciences_Paper_CitationsManager` into `Episciences_Paper_Citations_Repository`, `Episciences_Paper_Citations_ViewFormatter`, `Episciences_Paper_Citations_EnrichmentService`, and `Episciences_Paper_Citations_Logger`.
- Redundant PHPDoc removed from all new citations classes via Rector.
- Replaced `Episciences_Cache` with `symfony/cache` 5.4 across all internal usages.
- `PapersManager::getList()`: removed `$cached` parameter; paper list is now always fetched fresh.
- `Review::getPapers()`, `CopyEditor::loadAssignedPapers()`, `Editor::loadAssignedPapers()`, `Reviewer::loadAssignedPapers()`, `Volume::getPaperListFromVolume()`: updated signatures and call sites following the removal of `$cached`.
- `Oai/Server::getIds()`: OAI resumption token cache migrated to PSR-6.
- Statistics: log import script now supports anonymized log files (.access_log_anonym.gz) as a fallback.
- Improved accessibility of the ORCID authors modal: proper `<label>` associations, `aria-modal="true"`, and corrected `aria-labelledby`.
- Modernized ORCID authors row layout using Flexbox.
- Standardized modal button styles for ORCID updates (Cancel: `btn-default`, Save: `btn-primary`).
- ORCID input fields: URLs are now automatically cleaned to keep only the 16-digit identifier.
- `administratepaper/view.phtml`: reordered panels; moved "Volumes & Rubriques" earlier; `paper_versions` moved to the bottom; removed redundant label.
- `paper/paper_datasets.phtml`: "Liens publications – données – logiciels" panel is now collapsed by default.
- `paper/paper_graphical_abstract.phtml`: graphical abstract panel is now collapsed by default when empty.
- `partials/coauthors.phtml`: "Ajouter un co-auteur" panel is now collapsed by default.
- `partials/paper_affiliation_authors.phtml`: "Ajouter une affiliation" panel is now collapsed by default.
- `volume/list.phtml`: Added "Number" and "Year" columns; improved table accessibility; Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: Removed redundant inline scripts; construction of the editors list optimized.
- Updated icons from `fas fa-address-card` to `fa-regular fa-address-card`.
- Improved visual indicators for editor availability in `editor-availability.js`.
- Refactored `scripts/zbjatZipper.php`: renamed class to `ZbjatZipper`, replaced `echo` with Monolog, extracted God method, switched to HTTPS.
- Refactored `Episciences_Paper_AuthorsManager` into `Episciences_Hal_TeiCacheManager`, `Episciences_Paper_Authors_HalTeiParser`, `Episciences_Paper_Authors_Repository`, `Episciences_Paper_Authors_EnrichmentService`, `Episciences_Paper_Authors_AffiliationHelper`, and `Episciences_Paper_Authors_ViewFormatter`.
- Refactored `Episciences_Paper_Authors_ViewFormatter` to separate data fetching from formatting logic.
- Moved `normalizeOrcid()` implementation from `AuthorsManager` to `HalTeiParser`.
- `AuthorsManager::ONE_MONTH` now references `TeiCacheManager::ONE_MONTH`.
- Added `TeiCacheManager::fetchAndGet()` to combine fetch-if-needed + read in a single call.
- The "Encapsulate reviewers" parameter is now hidden.
- [#528](https://github.com/CCSDForge/episciences/issues/528): 'upload a new version' on top; updated contact label.
- Paper metrics Refactored; 1 query instead of 2.
- The Locale is now stored in a cookie instead of the session.
- Redirect to the article to be reviewed when accepting an invitation.
- Allow volume years to be a string (AAAA or AAAA-AAAA).
- Synchronization of predefined page titles between navigation menu and T_PAGES table when saving menu configuration.
- Replaced `cottagelabs/coar-notifications` (Doctrine ORM + Guzzle) with `cottagelabs/coarnotify` (stateless pattern builder) and a custom PDO persistence layer (`Notification` DTO + `NotificationsRepository`)
- Refactored `InboxNotifications` (1390-line God Class) into focused private methods (`processFirstSubmission`, `processSubsequentSubmission`, `setupJournalTranslations`, `resolveNewVersionStatus`, `logVersionUpdate`, …) and pure, dependency-free helper classes
- `checkNotifyPayloads()` and `initSubmission()` are now source-aware: the COAR Notify source is resolved from `origin.inbox` via `NotifySourceRegistry`
- `InitSubmissionsFromHalInbox.php` is now a direct CLI entry point on `InboxNotifications`; the empty subclass has been removed

### Fixed

- [#858](https://github.com/CCSDForge/episciences/issues/858) fix: only use "et al." when 2+ authors are omitted. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Limit the editor’s permissions to allow assigning only the "reviewer" role.
- [#944](https://github.com/CCSDForge/episciences/issues/944): An author informed us that they received this message signed with their name: %%SENDER_FULL_NAME%% and %%RECIPIENT_SCREEN_NAME%% filled with the same value
- [#258](https://github.com/CCSDForge/episciences-front/issues/258): Tables are not handled by default: Explicit table conversion
- Fixed RT#277365: in order to correct the printing of extra lines, two options have been added to the TinyMCE configuration to insert a <br> instead of a <p> and another option to remove extra <br>s at the end of a block
- Fixed tooltip initialization in volume and section assignment after AJAX refreshes and DataTable redraws.
- Fixed Solr search engine indexation of Authors with a middle name.
- Consider an additional review when sending reminders for an insufficient number of reviewers.
- `Projects`: `$_dateUpdated` default fixed; `getDateUpdated()` return type consistency.
- `Projects::setFunding()`: made method fluent.
- `Projects::setOptions()`: fixed `setIdproject()` alias and case-insensitive method lookup.
- `Projects::toArray()`: fixed `DateTime` serialization.
- `Repository::insert()`: replaced deprecated `VALUES()` syntax with row-alias syntax for MySQL 8.0.20+.
- `Repository`: exceptions now propagate instead of terminating script on DB errors.
- `ViewFormatter`: fixed XSS by escaping `$vfunding['url']` and other user-controlled values in HTML attributes.
- `HalApiClient::doGet()`: added `E_USER_WARNING` severity level.
- `EnrichmentService::resolveHalProjectIds()`: removed duplicate log message; sanitized cache key.
- `Citations_ViewFormatter`: removed double escaping; fixed unstable compound sort; added ORCID format validation.
- `Citations_Repository`: replaced deprecated MySQL `VALUES()` function; added check for `$docId <= 0`.
- `Citations` entity: fixed `toArray()` key name; fixed `$_updatedAt` type and default.
- ORCID normalization: `cleanLowerCaseOrcid()` fix; applied normalization in Zenodo and ARCHE hooks.
- `findAffiliationsOneAuthorByPaperId()`: fixed potential undefined variable.
- `hasAcronym()`: fixed iteration over nested `id` array.
- `HalTeiParser::getAuthorsFromHalTei()`: fixed logic to prevent enriching the wrong author.
- `ViewFormatter::buildAffiliationListHtml()`: fixed Stored XSS by escaping the affiliation acronym.
- `ViewFormatter`: fixed `html_entity_decode(htmlspecialchars())` no-op.
- `EnrichmentService::mergeExistingAffiliations()`: fixed matching key lookup with `array_search()`.
- `AffiliationHelper::isAcronymDuplicate()`: now iterates all identifiers.
- `AffiliationHelper::setOrUpdateRorAcronym()`: returns first match deterministically.
- `TeiCacheManager::buildApiUrl()`: applied `urlencode()` to prevent Solr query injection.
- `TeiCacheManager::getFromCache()`: removed dead `expiresAfter()` call.
- `Repository`: fixed `JSON_DECODE_FLAGS`.
- `CommentsManager::$_typeLabel`: added missing entry for `TYPE_CONTRIBUTOR_TO_REVIEWER`.
- `CommentsManager::updateUid()`: fixed negative UID guard.
- `FormatIssn::FormatIssn()`: fixed substring length.
- `Log::log()`: fixed uncaught exception from `$logger->log()`.
- `DoiAsLink::DoiAsLink()`: fixed label when no text provided.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass with `preg_quote()` and anchors.
- Fixed RT#277365: added TinyMCE configuration options to handle `<br>` and `<p>` correctly.
- [#886](https://github.com/CCSDForge/episciences/issues/886): reminder logic for lack of reviewers fixed.
- `convertToBytes()`: fixed handling of pure numeric strings and added validation.
- `isHal()`, `isHalUrl()`, `isArxiv()`: fixed regex anchors.
- Added comprehensive test coverage for `Episciences_Tools`.
- [#236](https://github.com/CCSDForge/episciences-front/issues/236): HTML entities (&) displayed in the title.
- [#695](https://github.com/CCSDForge/episciences/issues/695) Fixed CC/BCC fields not clickable in paper status change forms.
- [#830](https://github.com/CCSDForge/episciences/issues/830) Paper number messed up for secondary volume; altered secondary volume rendering.
- [#776](https://github.com/CCSDForge/episciences/issues/776) Action Required: Fix Renovate Configuration.
- [#657](https://github.com/CCSDForge/episciences/issues/657) Conditionally remove the `<hr>` separator in metadata views.
- [#786](https://github.com/CCSDForge/episciences/issues/786) English translation of 'Télécharger le fichier'.
- [#646](https://github.com/CCSDForge/episciences/issues/646) Redirect to article page after submission.
- [#780](https://github.com/CCSDForge/episciences/issues/780) Option to lock volume name when an article is published.
- [#147](https://github.com/CCSDForge/episciences/issues/147) Add new pages Acknowledgements in menu 'About'.
- Fixed Paper Metrics based on wrong DocId.
- Fixed Pre-defined pages not deleted from the database.
- [#77](https://github.com/CCSDForge/episciences-front/issues/77) Fix orphan assignments when deleting sections or volumes.
- `CrossrefTools`: fixed fatal error on constant name; fixed raw `CacheItem` return.
- `OpenalexTools`: fixed `getPages()` return value and trailing separator in `getAuthors()`.
- `OpencitationsTools`: fixed caching of empty responses; fixed DOI extraction and updated API format support.
- `getLinkData`: switched to HTTPS; added null guards for `$valuesResult`.
- `getLicenceDataEnrichment`: fixed ineffective cache.
- `OpenAireResearchGraphTools`: enabled strict mode for `array_search()`.
- `Projects/EnrichmentService`: fixed log path and added `(int)` cast for source ID.
- `Citations/EnrichmentService`: added null guards for OpenAlex API fields.
- [#875](https://github.com/CCSDForge/episciences/issues/875): fixed HTML/Emoji translation issue.

### Deprecated

- `Episciences_Cache` and its parent `Ccsd_Cache` are now marked `@deprecated`; use `Symfony\Component\Cache\Adapter\FilesystemAdapter` instead.
- `ProjectsManager` kept as a thin backward-compatible facade.
- `CitationsManager` kept as backward-compatible facade with `@deprecated` proxies.
- `AuthorsManager` kept as orchestrator with backward-compatible `@deprecated` proxies.
- `Episciences_Paper_Visits::count` is deprecated.

### Removed

- Redundant "Statut actuel :" label prefix from the article status panel.
- Stray `<br>` and inlined `versionCache` script tag in `partials/paper_affiliation_authors.phtml`.
- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`.

### Security

- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`, eliminating a potential PHP object injection vector.
- OAI resumption token cache keys are now MD5-hashed before use, preventing cache-key injection.
- Escaped output in `volume/editors_list.phtml` to prevent potential XSS.
- Fixed XSS vulnerability in `Projects/ViewFormatter`: funding URL was interpolated unescaped into `href` attribute and link text.
- Fixed XSS in `Citations_ViewFormatter`: `href=` attributes for DOI and OA links were unquoted.
- Fixed XSS vulnerability in `ViewFormatter::buildAuthorHtml()` and `buildAffiliationListHtml()` regarding user-controlled values in HTML attributes.
- Fixed potential Solr query injection in `TeiCacheManager::buildApiUrl()`.
- `GetAvatar::asPaperStatusSvg()`: fixed two path traversal vectors ($lang sanitization and $paperStatus casting).
- `DoiAsLink::DoiAsLink()`: added `rel="noopener noreferrer"` to prevent tab-napping.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass that allowed authentication from unauthorized email domains.

### Performances

- `volume/list.phtml`: Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: construction of the editors list optimized.
- `Episciences_Paper_Citations_Logger`: Singleton Monolog logger (avoids Logger recreation on every call).
- Paper metrics Refactored; 1 query instead of 2.

## v1.0.55.3 - 2026-05-20

### Fixed

- [RT#287093]: Failed to submit "acceptedAskAuthorsFinalVersion" Form: CSRF validation failed (CSRF field missing)
- Adapt "acceptedAskAuthorsFinalVersion" form to the requirements for setting a deadline for requesting a revision after acceptance

### Changed

- [#1010](https://github.com/CCSDForge/episciences/issues/1010) The allowed range for modifying the deadline is now between the original deadline minus "rating_deadline_min" and the original deadline plus "rating_deadline_max".
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration
  button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)"

## v1.0.55.2 - 2026-05-19

### Fixed

- Improved article URL resolution in `ExtractBiblioRefsCommand` by adding fallbacks to `getMainPaperUrl()` and `getDocUrl()` when the repository template is empty (e.g., for Zenodo).
- Added debug logging for resolved article URLs and included raw response snippets in JSON decode error messages in `ExtractBiblioRefsCommand`.

## v1.0.55.1 - 2026-05-19

### Added

- Legend for bibliographic reference status icons in both admin and public views (publicly shown only when problematic references are detected).

### Changed

- Replaced bibliographic reference status indicators with standardized square icons (red `fa-square-xmark` for problematic, green `fa-square-check` for validated, and gray `fa-square` for automatically extracted references).
- Improved accessibility of bibliographic references (added ARIA landmarks, screen-reader-only status prefixes, descriptions for status badges, and warnings for links opening in new tabs).
- Optimized bibliographic references layout by moving the status legend inline with the action button.
- Renamed 'Not validated reference' to 'Automatically extracted reference' for clarity.
- Renamed 'Manage' button to 'Manage References' / 'Gérer les références' in the administration view.

### Fixed

- Removed obsolete BibTeX import button (feature migrated to an external application).
- Removed hardcoded Semantic Scholar source attribution from bibliographic references display.
- Skip temporary versions (without external repository URL) during bibliographic reference extraction.
- Fixed a missing dependency (`require_once`) for `GetDoiCommand` in `console.php`.

## v1.0.55 - 2026-05-18

### Added

- Automatic detection of problematic papers cited in the references of the papers. This is heavily based on the huge work provided by the Problematic Paper Screener https://dbrech.irit.fr/pls/apex/f?p=9999:1::::::
  For more information: Cabanac, G., Labbé, C., & Magazinov, A. (2022). The ‘Problematic Paper Screener’ automatically selects suspect publications for post-publication (re)assessment.
  Presented at WCRI 2022: 7th World Conference on Research Integrity. arXiv preprint. https://doi.org/10.48550/arXiv.2210.04895

- [#630](https://github.com/CCSDForge/episciences/issues/630) COI (Conflict of Interest) notifications:
    - Email notification to the editor-in-chief when an editor declares a positive COI (answered "yes")
    - Email notification to other assigned editors (if any) when an editor declares a COI
- Many legacy scripts migrated to Symfony Console commands (e.g., `doi:manage` replaces `getDoi.php`, `sitemap:generate --all`, translations update).
- Expanded unit test coverage for repository connectors (Zenodo, BioMedRxiv, ARCHE, Dspace) and COI notifications (+60 tests).

### Changed

- Sending the invitation in the user’s language, including when using the autocomplete input field for CAS users who already have a profile on the site.
- Simplified news add/edit form for a better user experience.
- Refactored OpenAIRE integration into a dedicated `OpenAireApiClient`.
- Improved PHP 8.1 compatibility and robustness across the codebase (removed deprecations and warnings).
- Optimized the submission workflow processing order.

### Fixed

- A bug that likely appeared in the latest update: section and/or volume editors are not assigned automatically, even if the relevant settings are enabled.
- Panel not opening when updating linked data
- Submission of a dataset without a data descriptor due to an error while uploading attached files (one of the file types exceeded the maximum allowed size for that field type)
- Corrected article position in volumes (1-based instead of 0-based) for CSL generation.
- Resolved relative `hydra:next` URLs in sitemap generation.
- Fixed several potential `TypeError` and crashes when metadata or publication dates are missing.

## v1.0.54.3 - 2026-04-23

### Fixed

- The link to the new version of the data descriptor isn't working; it still points to the first version.
- [RT#285106]: Application error when submitting with 'display secondary volume' option enabled
- Always SELECT RECORD so Paper::getMetadata() / toJson() can resolve titles and abstracts
- Log parser: extract IP from syslog-prefixed Apache log lines

## v1.0.54.2 - 2026-04-16

### Fixed

- Inability to change the review deadline (restore loading js via url).

### Added

- docs: add documentation index to README and update docs
- stats:import-logs command to parse Apache logs into STAT_TEMP. Introduces ImportApacheLogsCommand (stats:import-logs), a Symfony Console command that parses Apache access logs and inserts article visits into STAT_TEMP. Replaces the legacy UpdateStatistics.php . Run src/mysql/2025-08-24-stat-processing-log-table.sql before deploying

## v1.0.54.1 - 2026-04-14

### Changed

- Refactored English email templates, grammar only, no new features, no new tags

### Fixed

- [#962](https://github.com/CCSDForge/episciences/issues/962) Fixed double HTML encoding bug in volume metadata titles where special
  characters like `<`, `>`, and `'` were displayed as `&lt;`, `&gt;`, `&#039;`
- TypeError in enrichment:creators on non-sequential affiliation keys
- isImported detection for published papers when the the publication date has been fixed after publication
- [#985](https://github.com/CCSDForge/episciences/issues/985) Impossible to submit a final version
- Fixed bug in management of journal headers for adding and updating logo and texts

## v1.0.54 - 2026-04-09

### Added

- [#779](https://github.com/CCSDForge/episciences/issues/779) [Feature request] Display the secondary volume on the article page. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Adding "Publish" => "Proposing special issues" template page [#951](https://github.com/CCSDForge/episciences/issues/951)
- New supported servers:
    - Cryptology ePrint Archive (https://eprint.iacr.org)
    - Support for any data repositories powered by Dspace (eg. [repositorium.uminho(University of Minho)](https://repositorium.uminho.pt))
- Statistics: new Symfony Console commands `stats:process` (replaces legacy `scripts/stat.php`) and `stats:update-robots-list` for UA-based bot detection using the COUNTER Robots list; real client IP is now stored in `STAT_TEMP` and anonymized at processing time.
- Documentation: added `docs/console-commands.md` listing all 19 CLI commands available via `scripts/console.php` with options and usage examples.
- OAI-PMH: added `crossref` as a new metadata format (`metadataPrefix=crossref`), exposing Crossref 5.3.1 XML for each published paper. The depositor email is automatically replaced with a generic `noreply@{domain}` address in this context.
- Comprehensive Jest test suite for ORCID authors management (`tests/js/updateOrcidAuthors.test.js`).
- Unit tests for `Episciences_Paper_Projects` entity (setOptions aliases, DateTime handling, toArray serialization, fluent setters).
- Unit tests for `Episciences_Paper_Projects_EnrichmentService` pure functions (EU/ANR response normalization, OpenAIRE relation filtering).
- Unit tests for `Episciences_Paper_Projects_ViewFormatter` (empty-array return, URL HTML-escaping, unidentified title suppression).
- Unit tests for `Episciences_Paper_Citations_ViewFormatter` (20 tests: sort, author formatting, ORCID links, XSS prevention, book-chapter/proceedings-article reordering).
- Unit tests for `Episciences_Paper_Citations` entity (8 tests: `toArray()` key, fluent setters, nullable `updatedAt`, constructor).
- Comprehensive unit tests for `Episciences_Paper_Authors_ViewFormatter` covering HTML display logic and XSS prevention.
- Unit tests for `Episciences_View_Helper_DoiAsLink`, `Episciences_View_Helper_FormatIssn`, `Episciences_View_Helper_Log`, `Episciences_View_Helper_Tag`, `Episciences_View_Helper_UserAvatar`, `Episciences_View_Helper_GetAvatar` and `Episciences_CommentsManager`.
- 89+ unit tests for `Ccsd\Auth` adapters (`CasAbstract`, `Idp`, `Orcid`, `AdapterFactory`, `Asso`), `Ccsd\User` models (`User`, `UserTokens`, `UserFtpQuota`) and `Episciences\User` entities covering pure logic without DB or network access.
- Updated `tests/README.md` with accurate Docker-based testing instructions, `make` target reference, subset-run examples, and contributor guidelines.
- [#883](https://github.com/CCSDForge/episciences/issues/883) Allow json files as attachments.
- Optional Webhook: It is now possible to report status changes to an external entry point (can be configured by journal).
- [#658](https://github.com/CCSDForge/episciences/issues/658) It is now possible to link an invitation that is not intended for you to your account.
- allow the co-author to view the publication.
- Statistics: the script has a new parameter `--all` - Process all statistics (with confirmation prompt).
- New option to allow Editors to receive 'Comments for editors' before declaring a conflict of interest (disabled by default).
- Volume Settings: add New configuration section(displayEmptyVolumes and allowEditVolumeTitleWithPublishedArticles) in journal settings.
- [#679](https://github.com/CCSDForge/episciences/issues/679) "Ask other editors for their opinion" form now includes chief editors (ROLE_CHIEF_EDITOR) in addition to regular editors (ROLE_EDITOR).
- [#691](http://github.com/CCSDForge/episciences/issues/691) Display "(optional)" label below comment and cover letter fields in submission forms to clarify these fields are not required.
- [#350](https://github.com/CCSDForge/episciences/issues/350) Author-Editor Communication Settings:
    - New option to allow authors to contact assigned editors (`authorEditorCommunication`)
    - New option to disclose editor names to authors or keep them anonymized (`discloseEditorNamesToAuthors`)
- feat(navigation): sync predefined page titles with T_PAGES table on menu save.
- New `library/Episciences/Api/` namespace with 5 injectable, independently-testable API clients: `AbstractApiClient`, `CrossrefApiClient`, `OpenAlexApiClient`, `OpenCitationsApiClient`, `OpenAireApiClient`.
- Five Symfony Console commands replacing the legacy JournalScript enrichment scripts: `enrichment:citations`, `enrichment:creators`, `enrichment:funding`, `enrichment:licences`, `enrichment:links`.
- Added MSC2020 Classification in ZBJATS export.
- `Episciences\Notify\NotifySourceConfig` — immutable value object holding per-source configuration (repoId, label, originId, originInbox, acceptedTypes); new repositories can be added without touching `InboxNotifications`
- `Episciences\Notify\NotifySourceRegistry` — registry with a `createFromConstants()` factory; resolves source config from a payload's `origin.inbox`
- `Episciences\Notify\ValidationResult` — pure value object wrapping a validation outcome (success / failure + error message)
- `Episciences\Notify\PreprintUrlParser` — pure helper extracting version and identifier from preprint landing-page URLs (HAL-style)
- `Episciences\Notify\PayloadValidator` — validates COAR Notify 1.0.1 payloads for Offer patterns (Request Review / Request Endorsement): all required fields including `object.id` (HTTP URI) and `object['ietf:item']` (id, type, mediaType)

### Changed

- [#947](https://github.com/CCSDForge/episciences/issues/947) [Feature request] wording on the platform - refused / rejected and refused/declined
- Refactored `public/js/paper/updateOrcidAuthors.js` from jQuery to modern vanilla ES6+ with a class-based architecture.
- Enhanced ORCID form validation: added duplicate detection while maintaining support for empty submissions for deletions.
- Update TinyMCE form 7.3.0 to 8.1.2.
- Increased Volume Description length to 1024 chars.
- DOI panel: requesting, saving, and cancelling a DOI no longer triggers a full page reload; success feedback message is suppressed.
- Refactored `Episciences_Paper_ProjectsManager` into `Episciences_Paper_Projects_Repository`, `Episciences_Paper_Projects_HalApiClient`, `Episciences_Paper_Projects_EnrichmentService`, and `Episciences_Paper_Projects_ViewFormatter`.
- Refactored `Episciences_Paper_CitationsManager` into `Episciences_Paper_Citations_Repository`, `Episciences_Paper_Citations_ViewFormatter`, `Episciences_Paper_Citations_EnrichmentService`, and `Episciences_Paper_Citations_Logger`.
- Redundant PHPDoc removed from all new citations classes via Rector.
- Replaced `Episciences_Cache` with `symfony/cache` 5.4 across all internal usages.
- `PapersManager::getList()`: removed `$cached` parameter; paper list is now always fetched fresh.
- `Review::getPapers()`, `CopyEditor::loadAssignedPapers()`, `Editor::loadAssignedPapers()`, `Reviewer::loadAssignedPapers()`, `Volume::getPaperListFromVolume()`: updated signatures and call sites following the removal of `$cached`.
- `Oai/Server::getIds()`: OAI resumption token cache migrated to PSR-6.
- Statistics: log import script now supports anonymized log files (.access_log_anonym.gz) as a fallback.
- Improved accessibility of the ORCID authors modal: proper `<label>` associations, `aria-modal="true"`, and corrected `aria-labelledby`.
- Modernized ORCID authors row layout using Flexbox.
- Standardized modal button styles for ORCID updates (Cancel: `btn-default`, Save: `btn-primary`).
- ORCID input fields: URLs are now automatically cleaned to keep only the 16-digit identifier.
- `administratepaper/view.phtml`: reordered panels; moved "Volumes & Rubriques" earlier; `paper_versions` moved to the bottom; removed redundant label.
- `paper/paper_datasets.phtml`: "Liens publications – données – logiciels" panel is now collapsed by default.
- `paper/paper_graphical_abstract.phtml`: graphical abstract panel is now collapsed by default when empty.
- `partials/coauthors.phtml`: "Ajouter un co-auteur" panel is now collapsed by default.
- `partials/paper_affiliation_authors.phtml`: "Ajouter une affiliation" panel is now collapsed by default.
- `volume/list.phtml`: Added "Number" and "Year" columns; improved table accessibility; Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: Removed redundant inline scripts; construction of the editors list optimized.
- Updated icons from `fas fa-address-card` to `fa-regular fa-address-card`.
- Improved visual indicators for editor availability in `editor-availability.js`.
- Refactored `scripts/zbjatZipper.php`: renamed class to `ZbjatZipper`, replaced `echo` with Monolog, extracted God method, switched to HTTPS.
- Refactored `Episciences_Paper_AuthorsManager` into `Episciences_Hal_TeiCacheManager`, `Episciences_Paper_Authors_HalTeiParser`, `Episciences_Paper_Authors_Repository`, `Episciences_Paper_Authors_EnrichmentService`, `Episciences_Paper_Authors_AffiliationHelper`, and `Episciences_Paper_Authors_ViewFormatter`.
- Refactored `Episciences_Paper_Authors_ViewFormatter` to separate data fetching from formatting logic.
- Moved `normalizeOrcid()` implementation from `AuthorsManager` to `HalTeiParser`.
- `AuthorsManager::ONE_MONTH` now references `TeiCacheManager::ONE_MONTH`.
- Added `TeiCacheManager::fetchAndGet()` to combine fetch-if-needed + read in a single call.
- The "Encapsulate reviewers" parameter is now hidden.
- [#528](https://github.com/CCSDForge/episciences/issues/528): 'upload a new version' on top; updated contact label.
- Paper metrics Refactored; 1 query instead of 2.
- The Locale is now stored in a cookie instead of the session.
- Redirect to the article to be reviewed when accepting an invitation.
- Allow volume years to be a string (AAAA or AAAA-AAAA).
- Synchronization of predefined page titles between navigation menu and T_PAGES table when saving menu configuration.
- Replaced `cottagelabs/coar-notifications` (Doctrine ORM + Guzzle) with `cottagelabs/coarnotify` (stateless pattern builder) and a custom PDO persistence layer (`Notification` DTO + `NotificationsRepository`)
- Refactored `InboxNotifications` (1390-line God Class) into focused private methods (`processFirstSubmission`, `processSubsequentSubmission`, `setupJournalTranslations`, `resolveNewVersionStatus`, `logVersionUpdate`, …) and pure, dependency-free helper classes
- `checkNotifyPayloads()` and `initSubmission()` are now source-aware: the COAR Notify source is resolved from `origin.inbox` via `NotifySourceRegistry`
- `InitSubmissionsFromHalInbox.php` is now a direct CLI entry point on `InboxNotifications`; the empty subclass has been removed

### Fixed

- [#858](https://github.com/CCSDForge/episciences/issues/858) fix: only use "et al." when 2+ authors are omitted. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Limit the editor’s permissions to allow assigning only the "reviewer" role.
- [#944](https://github.com/CCSDForge/episciences/issues/944): An author informed us that they received this message signed with their name: %%SENDER_FULL_NAME%% and %%RECIPIENT_SCREEN_NAME%% filled with the same value
- [#258](https://github.com/CCSDForge/episciences-front/issues/258): Tables are not handled by default: Explicit table conversion
- Fixed RT#277365: in order to correct the printing of extra lines, two options have been added to the TinyMCE configuration to insert a <br> instead of a <p> and another option to remove extra <br>s at the end of a block
- Fixed tooltip initialization in volume and section assignment after AJAX refreshes and DataTable redraws.
- Fixed Solr search engine indexation of Authors with a middle name.
- Consider an additional review when sending reminders for an insufficient number of reviewers.
- `Projects`: `$_dateUpdated` default fixed; `getDateUpdated()` return type consistency.
- `Projects::setFunding()`: made method fluent.
- `Projects::setOptions()`: fixed `setIdproject()` alias and case-insensitive method lookup.
- `Projects::toArray()`: fixed `DateTime` serialization.
- `Repository::insert()`: replaced deprecated `VALUES()` syntax with row-alias syntax for MySQL 8.0.20+.
- `Repository`: exceptions now propagate instead of terminating script on DB errors.
- `ViewFormatter`: fixed XSS by escaping `$vfunding['url']` and other user-controlled values in HTML attributes.
- `HalApiClient::doGet()`: added `E_USER_WARNING` severity level.
- `EnrichmentService::resolveHalProjectIds()`: removed duplicate log message; sanitized cache key.
- `Citations_ViewFormatter`: removed double escaping; fixed unstable compound sort; added ORCID format validation.
- `Citations_Repository`: replaced deprecated MySQL `VALUES()` function; added check for `$docId <= 0`.
- `Citations` entity: fixed `toArray()` key name; fixed `$_updatedAt` type and default.
- ORCID normalization: `cleanLowerCaseOrcid()` fix; applied normalization in Zenodo and ARCHE hooks.
- `findAffiliationsOneAuthorByPaperId()`: fixed potential undefined variable.
- `hasAcronym()`: fixed iteration over nested `id` array.
- `HalTeiParser::getAuthorsFromHalTei()`: fixed logic to prevent enriching the wrong author.
- `ViewFormatter::buildAffiliationListHtml()`: fixed Stored XSS by escaping the affiliation acronym.
- `ViewFormatter`: fixed `html_entity_decode(htmlspecialchars())` no-op.
- `EnrichmentService::mergeExistingAffiliations()`: fixed matching key lookup with `array_search()`.
- `AffiliationHelper::isAcronymDuplicate()`: now iterates all identifiers.
- `AffiliationHelper::setOrUpdateRorAcronym()`: returns first match deterministically.
- `TeiCacheManager::buildApiUrl()`: applied `urlencode()` to prevent Solr query injection.
- `TeiCacheManager::getFromCache()`: removed dead `expiresAfter()` call.
- `Repository`: fixed `JSON_DECODE_FLAGS`.
- `CommentsManager::$_typeLabel`: added missing entry for `TYPE_CONTRIBUTOR_TO_REVIEWER`.
- `CommentsManager::updateUid()`: fixed negative UID guard.
- `FormatIssn::FormatIssn()`: fixed substring length.
- `Log::log()`: fixed uncaught exception from `$logger->log()`.
- `DoiAsLink::DoiAsLink()`: fixed label when no text provided.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass with `preg_quote()` and anchors.
- Fixed RT#277365: added TinyMCE configuration options to handle `<br>` and `<p>` correctly.
- [#886](https://github.com/CCSDForge/episciences/issues/886): reminder logic for lack of reviewers fixed.
- `convertToBytes()`: fixed handling of pure numeric strings and added validation.
- `isHal()`, `isHalUrl()`, `isArxiv()`: fixed regex anchors.
- Added comprehensive test coverage for `Episciences_Tools`.
- [#236](https://github.com/CCSDForge/episciences-front/issues/236): HTML entities (&) displayed in the title.
- [#695](https://github.com/CCSDForge/episciences/issues/695) Fixed CC/BCC fields not clickable in paper status change forms.
- [#830](https://github.com/CCSDForge/episciences/issues/830) Paper number messed up for secondary volume; altered secondary volume rendering.
- [#776](https://github.com/CCSDForge/episciences/issues/776) Action Required: Fix Renovate Configuration.
- [#657](https://github.com/CCSDForge/episciences/issues/657) Conditionally remove the `<hr>` separator in metadata views.
- [#786](https://github.com/CCSDForge/episciences/issues/786) English translation of 'Télécharger le fichier'.
- [#646](https://github.com/CCSDForge/episciences/issues/646) Redirect to article page after submission.
- [#780](https://github.com/CCSDForge/episciences/issues/780) Option to lock volume name when an article is published.
- [#147](https://github.com/CCSDForge/episciences/issues/147) Add new pages Acknowledgements in menu 'About'.
- Fixed Paper Metrics based on wrong DocId.
- Fixed Pre-defined pages not deleted from the database.
- [#77](https://github.com/CCSDForge/episciences-front/issues/77) Fix orphan assignments when deleting sections or volumes.
- `CrossrefTools`: fixed fatal error on constant name; fixed raw `CacheItem` return.
- `OpenalexTools`: fixed `getPages()` return value and trailing separator in `getAuthors()`.
- `OpencitationsTools`: fixed caching of empty responses; fixed DOI extraction and updated API format support.
- `getLinkData`: switched to HTTPS; added null guards for `$valuesResult`.
- `getLicenceDataEnrichment`: fixed ineffective cache.
- `OpenAireResearchGraphTools`: enabled strict mode for `array_search()`.
- `Projects/EnrichmentService`: fixed log path and added `(int)` cast for source ID.
- `Citations/EnrichmentService`: added null guards for OpenAlex API fields.
- [#875](https://github.com/CCSDForge/episciences/issues/875): fixed HTML/Emoji translation issue.

### Deprecated

- `Episciences_Cache` and its parent `Ccsd_Cache` are now marked `@deprecated`; use `Symfony\Component\Cache\Adapter\FilesystemAdapter` instead.
- `ProjectsManager` kept as a thin backward-compatible facade.
- `CitationsManager` kept as backward-compatible facade with `@deprecated` proxies.
- `AuthorsManager` kept as orchestrator with backward-compatible `@deprecated` proxies.
- `Episciences_Paper_Visits::count` is deprecated.

### Removed

- Redundant "Statut actuel :" label prefix from the article status panel.
- Stray `<br>` and inlined `versionCache` script tag in `partials/paper_affiliation_authors.phtml`.
- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`.

### Security

- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`, eliminating a potential PHP object injection vector.
- OAI resumption token cache keys are now MD5-hashed before use, preventing cache-key injection.
- Escaped output in `volume/editors_list.phtml` to prevent potential XSS.
- Fixed XSS vulnerability in `Projects/ViewFormatter`: funding URL was interpolated unescaped into `href` attribute and link text.
- Fixed XSS in `Citations_ViewFormatter`: `href=` attributes for DOI and OA links were unquoted.
- Fixed XSS vulnerability in `ViewFormatter::buildAuthorHtml()` and `buildAffiliationListHtml()` regarding user-controlled values in HTML attributes.
- Fixed potential Solr query injection in `TeiCacheManager::buildApiUrl()`.
- `GetAvatar::asPaperStatusSvg()`: fixed two path traversal vectors ($lang sanitization and $paperStatus casting).
- `DoiAsLink::DoiAsLink()`: added `rel="noopener noreferrer"` to prevent tab-napping.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass that allowed authentication from unauthorized email domains.

### Performances

- `volume/list.phtml`: Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: construction of the editors list optimized.
- `Episciences_Paper_Citations_Logger`: Singleton Monolog logger (avoids Logger recreation on every call).
- Paper metrics Refactored; 1 query instead of 2.

## v1.0.55.3 - 2026-05-20

### Fixed

- [RT#287093]: Failed to submit "acceptedAskAuthorsFinalVersion" Form: CSRF validation failed (CSRF field missing)
- Adapt "acceptedAskAuthorsFinalVersion" form to the requirements for setting a deadline for requesting a revision after acceptance

### Changed

- [#1010](https://github.com/CCSDForge/episciences/issues/1010) The allowed range for modifying the deadline is now between the original deadline minus "rating_deadline_min" and the original deadline plus "rating_deadline_max".
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration
  button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)"

## v1.0.55.2 - 2026-05-19

### Fixed

- Improved article URL resolution in `ExtractBiblioRefsCommand` by adding fallbacks to `getMainPaperUrl()` and `getDocUrl()` when the repository template is empty (e.g., for Zenodo).
- Added debug logging for resolved article URLs and included raw response snippets in JSON decode error messages in `ExtractBiblioRefsCommand`.

## v1.0.55.1 - 2026-05-19

### Added

- Legend for bibliographic reference status icons in both admin and public views (publicly shown only when problematic references are detected).

### Changed

- Replaced bibliographic reference status indicators with standardized square icons (red `fa-square-xmark` for problematic, green `fa-square-check` for validated, and gray `fa-square` for automatically extracted references).
- Improved accessibility of bibliographic references (added ARIA landmarks, screen-reader-only status prefixes, descriptions for status badges, and warnings for links opening in new tabs).
- Optimized bibliographic references layout by moving the status legend inline with the action button.
- Renamed 'Not validated reference' to 'Automatically extracted reference' for clarity.
- Renamed 'Manage' button to 'Manage References' / 'Gérer les références' in the administration view.

### Fixed

- Removed obsolete BibTeX import button (feature migrated to an external application).
- Removed hardcoded Semantic Scholar source attribution from bibliographic references display.
- Skip temporary versions (without external repository URL) during bibliographic reference extraction.
- Fixed a missing dependency (`require_once`) for `GetDoiCommand` in `console.php`.

## v1.0.55 - 2026-05-18

### Added

- Automatic detection of problematic papers cited in the references of the papers. This is heavily based on the huge work provided by the Problematic Paper Screener https://dbrech.irit.fr/pls/apex/f?p=9999:1::::::
  For more information: Cabanac, G., Labbé, C., & Magazinov, A. (2022). The ‘Problematic Paper Screener’ automatically selects suspect publications for post-publication (re)assessment.
  Presented at WCRI 2022: 7th World Conference on Research Integrity. arXiv preprint. https://doi.org/10.48550/arXiv.2210.04895

- [#630](https://github.com/CCSDForge/episciences/issues/630) COI (Conflict of Interest) notifications:
    - Email notification to the editor-in-chief when an editor declares a positive COI (answered "yes")
    - Email notification to other assigned editors (if any) when an editor declares a COI
- Many legacy scripts migrated to Symfony Console commands (e.g., `doi:manage` replaces `getDoi.php`, `sitemap:generate --all`, translations update).
- Expanded unit test coverage for repository connectors (Zenodo, BioMedRxiv, ARCHE, Dspace) and COI notifications (+60 tests).

### Changed

- Sending the invitation in the user’s language, including when using the autocomplete input field for CAS users who already have a profile on the site.
- Simplified news add/edit form for a better user experience.
- Refactored OpenAIRE integration into a dedicated `OpenAireApiClient`.
- Improved PHP 8.1 compatibility and robustness across the codebase (removed deprecations and warnings).
- Optimized the submission workflow processing order.

### Fixed

- A bug that likely appeared in the latest update: section and/or volume editors are not assigned automatically, even if the relevant settings are enabled.
- Panel not opening when updating linked data
- Submission of a dataset without a data descriptor due to an error while uploading attached files (one of the file types exceeded the maximum allowed size for that field type)
- Corrected article position in volumes (1-based instead of 0-based) for CSL generation.
- Resolved relative `hydra:next` URLs in sitemap generation.
- Fixed several potential `TypeError` and crashes when metadata or publication dates are missing.

## v1.0.54.3 - 2026-04-23

### Fixed

- The link to the new version of the data descriptor isn't working; it still points to the first version.
- [RT#285106]: Application error when submitting with 'display secondary volume' option enabled
- Always SELECT RECORD so Paper::getMetadata() / toJson() can resolve titles and abstracts
- Log parser: extract IP from syslog-prefixed Apache log lines

## v1.0.54.2 - 2026-04-16

### Fixed

- Inability to change the review deadline (restore loading js via url).

### Added

- docs: add documentation index to README and update docs
- stats:import-logs command to parse Apache logs into STAT_TEMP. Introduces ImportApacheLogsCommand (stats:import-logs), a Symfony Console command that parses Apache access logs and inserts article visits into STAT_TEMP. Replaces the legacy UpdateStatistics.php . Run src/mysql/2025-08-24-stat-processing-log-table.sql before deploying

## v1.0.54.1 - 2026-04-14

### Changed

- Refactored English email templates, grammar only, no new features, no new tags

### Fixed

- [#962](https://github.com/CCSDForge/episciences/issues/962) Fixed double HTML encoding bug in volume metadata titles where special
  characters like `<`, `>`, and `'` were displayed as `&lt;`, `&gt;`, `&#039;`
- TypeError in enrichment:creators on non-sequential affiliation keys
- isImported detection for published papers when the the publication date has been fixed after publication
- [#985](https://github.com/CCSDForge/episciences/issues/985) Impossible to submit a final version
- Fixed bug in management of journal headers for adding and updating logo and texts

## v1.0.54 - 2026-04-09

### Added

- [#779](https://github.com/CCSDForge/episciences/issues/779) [Feature request] Display the secondary volume on the article page. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Adding "Publish" => "Proposing special issues" template page [#951](https://github.com/CCSDForge/episciences/issues/951)
- New supported servers:
    - Cryptology ePrint Archive (https://eprint.iacr.org)
    - Support for any data repositories powered by Dspace (eg. [repositorium.uminho(University of Minho)](https://repositorium.uminho.pt))
- Statistics: new Symfony Console commands `stats:process` (replaces legacy `scripts/stat.php`) and `stats:update-robots-list` for UA-based bot detection using the COUNTER Robots list; real client IP is now stored in `STAT_TEMP` and anonymized at processing time.
- Documentation: added `docs/console-commands.md` listing all 19 CLI commands available via `scripts/console.php` with options and usage examples.
- OAI-PMH: added `crossref` as a new metadata format (`metadataPrefix=crossref`), exposing Crossref 5.3.1 XML for each published paper. The depositor email is automatically replaced with a generic `noreply@{domain}` address in this context.
- Comprehensive Jest test suite for ORCID authors management (`tests/js/updateOrcidAuthors.test.js`).
- Unit tests for `Episciences_Paper_Projects` entity (setOptions aliases, DateTime handling, toArray serialization, fluent setters).
- Unit tests for `Episciences_Paper_Projects_EnrichmentService` pure functions (EU/ANR response normalization, OpenAIRE relation filtering).
- Unit tests for `Episciences_Paper_Projects_ViewFormatter` (empty-array return, URL HTML-escaping, unidentified title suppression).
- Unit tests for `Episciences_Paper_Citations_ViewFormatter` (20 tests: sort, author formatting, ORCID links, XSS prevention, book-chapter/proceedings-article reordering).
- Unit tests for `Episciences_Paper_Citations` entity (8 tests: `toArray()` key, fluent setters, nullable `updatedAt`, constructor).
- Comprehensive unit tests for `Episciences_Paper_Authors_ViewFormatter` covering HTML display logic and XSS prevention.
- Unit tests for `Episciences_View_Helper_DoiAsLink`, `Episciences_View_Helper_FormatIssn`, `Episciences_View_Helper_Log`, `Episciences_View_Helper_Tag`, `Episciences_View_Helper_UserAvatar`, `Episciences_View_Helper_GetAvatar` and `Episciences_CommentsManager`.
- 89+ unit tests for `Ccsd\Auth` adapters (`CasAbstract`, `Idp`, `Orcid`, `AdapterFactory`, `Asso`), `Ccsd\User` models (`User`, `UserTokens`, `UserFtpQuota`) and `Episciences\User` entities covering pure logic without DB or network access.
- Updated `tests/README.md` with accurate Docker-based testing instructions, `make` target reference, subset-run examples, and contributor guidelines.
- [#883](https://github.com/CCSDForge/episciences/issues/883) Allow json files as attachments.
- Optional Webhook: It is now possible to report status changes to an external entry point (can be configured by journal).
- [#658](https://github.com/CCSDForge/episciences/issues/658) It is now possible to link an invitation that is not intended for you to your account.
- allow the co-author to view the publication.
- Statistics: the script has a new parameter `--all` - Process all statistics (with confirmation prompt).
- New option to allow Editors to receive 'Comments for editors' before declaring a conflict of interest (disabled by default).
- Volume Settings: add New configuration section(displayEmptyVolumes and allowEditVolumeTitleWithPublishedArticles) in journal settings.
- [#679](https://github.com/CCSDForge/episciences/issues/679) "Ask other editors for their opinion" form now includes chief editors (ROLE_CHIEF_EDITOR) in addition to regular editors (ROLE_EDITOR).
- [#691](http://github.com/CCSDForge/episciences/issues/691) Display "(optional)" label below comment and cover letter fields in submission forms to clarify these fields are not required.
- [#350](https://github.com/CCSDForge/episciences/issues/350) Author-Editor Communication Settings:
    - New option to allow authors to contact assigned editors (`authorEditorCommunication`)
    - New option to disclose editor names to authors or keep them anonymized (`discloseEditorNamesToAuthors`)
- feat(navigation): sync predefined page titles with T_PAGES table on menu save.
- New `library/Episciences/Api/` namespace with 5 injectable, independently-testable API clients: `AbstractApiClient`, `CrossrefApiClient`, `OpenAlexApiClient`, `OpenCitationsApiClient`, `OpenAireApiClient`.
- Five Symfony Console commands replacing the legacy JournalScript enrichment scripts: `enrichment:citations`, `enrichment:creators`, `enrichment:funding`, `enrichment:licences`, `enrichment:links`.
- Added MSC2020 Classification in ZBJATS export.
- `Episciences\Notify\NotifySourceConfig` — immutable value object holding per-source configuration (repoId, label, originId, originInbox, acceptedTypes); new repositories can be added without touching `InboxNotifications`
- `Episciences\Notify\NotifySourceRegistry` — registry with a `createFromConstants()` factory; resolves source config from a payload's `origin.inbox`
- `Episciences\Notify\ValidationResult` — pure value object wrapping a validation outcome (success / failure + error message)
- `Episciences\Notify\PreprintUrlParser` — pure helper extracting version and identifier from preprint landing-page URLs (HAL-style)
- `Episciences\Notify\PayloadValidator` — validates COAR Notify 1.0.1 payloads for Offer patterns (Request Review / Request Endorsement): all required fields including `object.id` (HTTP URI) and `object['ietf:item']` (id, type, mediaType)

### Changed

- [#947](https://github.com/CCSDForge/episciences/issues/947) [Feature request] wording on the platform - refused / rejected and refused/declined
- Refactored `public/js/paper/updateOrcidAuthors.js` from jQuery to modern vanilla ES6+ with a class-based architecture.
- Enhanced ORCID form validation: added duplicate detection while maintaining support for empty submissions for deletions.
- Update TinyMCE form 7.3.0 to 8.1.2.
- Increased Volume Description length to 1024 chars.
- DOI panel: requesting, saving, and cancelling a DOI no longer triggers a full page reload; success feedback message is suppressed.
- Refactored `Episciences_Paper_ProjectsManager` into `Episciences_Paper_Projects_Repository`, `Episciences_Paper_Projects_HalApiClient`, `Episciences_Paper_Projects_EnrichmentService`, and `Episciences_Paper_Projects_ViewFormatter`.
- Refactored `Episciences_Paper_CitationsManager` into `Episciences_Paper_Citations_Repository`, `Episciences_Paper_Citations_ViewFormatter`, `Episciences_Paper_Citations_EnrichmentService`, and `Episciences_Paper_Citations_Logger`.
- Redundant PHPDoc removed from all new citations classes via Rector.
- Replaced `Episciences_Cache` with `symfony/cache` 5.4 across all internal usages.
- `PapersManager::getList()`: removed `$cached` parameter; paper list is now always fetched fresh.
- `Review::getPapers()`, `CopyEditor::loadAssignedPapers()`, `Editor::loadAssignedPapers()`, `Reviewer::loadAssignedPapers()`, `Volume::getPaperListFromVolume()`: updated signatures and call sites following the removal of `$cached`.
- `Oai/Server::getIds()`: OAI resumption token cache migrated to PSR-6.
- Statistics: log import script now supports anonymized log files (.access_log_anonym.gz) as a fallback.
- Improved accessibility of the ORCID authors modal: proper `<label>` associations, `aria-modal="true"`, and corrected `aria-labelledby`.
- Modernized ORCID authors row layout using Flexbox.
- Standardized modal button styles for ORCID updates (Cancel: `btn-default`, Save: `btn-primary`).
- ORCID input fields: URLs are now automatically cleaned to keep only the 16-digit identifier.
- `administratepaper/view.phtml`: reordered panels; moved "Volumes & Rubriques" earlier; `paper_versions` moved to the bottom; removed redundant label.
- `paper/paper_datasets.phtml`: "Liens publications – données – logiciels" panel is now collapsed by default.
- `paper/paper_graphical_abstract.phtml`: graphical abstract panel is now collapsed by default when empty.
- `partials/coauthors.phtml`: "Ajouter un co-auteur" panel is now collapsed by default.
- `partials/paper_affiliation_authors.phtml`: "Ajouter une affiliation" panel is now collapsed by default.
- `volume/list.phtml`: Added "Number" and "Year" columns; improved table accessibility; Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: Removed redundant inline scripts; construction of the editors list optimized.
- Updated icons from `fas fa-address-card` to `fa-regular fa-address-card`.
- Improved visual indicators for editor availability in `editor-availability.js`.
- Refactored `scripts/zbjatZipper.php`: renamed class to `ZbjatZipper`, replaced `echo` with Monolog, extracted God method, switched to HTTPS.
- Refactored `Episciences_Paper_AuthorsManager` into `Episciences_Hal_TeiCacheManager`, `Episciences_Paper_Authors_HalTeiParser`, `Episciences_Paper_Authors_Repository`, `Episciences_Paper_Authors_EnrichmentService`, `Episciences_Paper_Authors_AffiliationHelper`, and `Episciences_Paper_Authors_ViewFormatter`.
- Refactored `Episciences_Paper_Authors_ViewFormatter` to separate data fetching from formatting logic.
- Moved `normalizeOrcid()` implementation from `AuthorsManager` to `HalTeiParser`.
- `AuthorsManager::ONE_MONTH` now references `TeiCacheManager::ONE_MONTH`.
- Added `TeiCacheManager::fetchAndGet()` to combine fetch-if-needed + read in a single call.
- The "Encapsulate reviewers" parameter is now hidden.
- [#528](https://github.com/CCSDForge/episciences/issues/528): 'upload a new version' on top; updated contact label.
- Paper metrics Refactored; 1 query instead of 2.
- The Locale is now stored in a cookie instead of the session.
- Redirect to the article to be reviewed when accepting an invitation.
- Allow volume years to be a string (AAAA or AAAA-AAAA).
- Synchronization of predefined page titles between navigation menu and T_PAGES table when saving menu configuration.
- Replaced `cottagelabs/coar-notifications` (Doctrine ORM + Guzzle) with `cottagelabs/coarnotify` (stateless pattern builder) and a custom PDO persistence layer (`Notification` DTO + `NotificationsRepository`)
- Refactored `InboxNotifications` (1390-line God Class) into focused private methods (`processFirstSubmission`, `processSubsequentSubmission`, `setupJournalTranslations`, `resolveNewVersionStatus`, `logVersionUpdate`, …) and pure, dependency-free helper classes
- `checkNotifyPayloads()` and `initSubmission()` are now source-aware: the COAR Notify source is resolved from `origin.inbox` via `NotifySourceRegistry`
- `InitSubmissionsFromHalInbox.php` is now a direct CLI entry point on `InboxNotifications`; the empty subclass has been removed

### Fixed

- [#858](https://github.com/CCSDForge/episciences/issues/858) fix: only use "et al." when 2+ authors are omitted. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Limit the editor’s permissions to allow assigning only the "reviewer" role.
- [#944](https://github.com/CCSDForge/episciences/issues/944): An author informed us that they received this message signed with their name: %%SENDER_FULL_NAME%% and %%RECIPIENT_SCREEN_NAME%% filled with the same value
- [#258](https://github.com/CCSDForge/episciences-front/issues/258): Tables are not handled by default: Explicit table conversion
- Fixed RT#277365: in order to correct the printing of extra lines, two options have been added to the TinyMCE configuration to insert a <br> instead of a <p> and another option to remove extra <br>s at the end of a block
- Fixed tooltip initialization in volume and section assignment after AJAX refreshes and DataTable redraws.
- Fixed Solr search engine indexation of Authors with a middle name.
- Consider an additional review when sending reminders for an insufficient number of reviewers.
- `Projects`: `$_dateUpdated` default fixed; `getDateUpdated()` return type consistency.
- `Projects::setFunding()`: made method fluent.
- `Projects::setOptions()`: fixed `setIdproject()` alias and case-insensitive method lookup.
- `Projects::toArray()`: fixed `DateTime` serialization.
- `Repository::insert()`: replaced deprecated `VALUES()` syntax with row-alias syntax for MySQL 8.0.20+.
- `Repository`: exceptions now propagate instead of terminating script on DB errors.
- `ViewFormatter`: fixed XSS by escaping `$vfunding['url']` and other user-controlled values in HTML attributes.
- `HalApiClient::doGet()`: added `E_USER_WARNING` severity level.
- `EnrichmentService::resolveHalProjectIds()`: removed duplicate log message; sanitized cache key.
- `Citations_ViewFormatter`: removed double escaping; fixed unstable compound sort; added ORCID format validation.
- `Citations_Repository`: replaced deprecated MySQL `VALUES()` function; added check for `$docId <= 0`.
- `Citations` entity: fixed `toArray()` key name; fixed `$_updatedAt` type and default.
- ORCID normalization: `cleanLowerCaseOrcid()` fix; applied normalization in Zenodo and ARCHE hooks.
- `findAffiliationsOneAuthorByPaperId()`: fixed potential undefined variable.
- `hasAcronym()`: fixed iteration over nested `id` array.
- `HalTeiParser::getAuthorsFromHalTei()`: fixed logic to prevent enriching the wrong author.
- `ViewFormatter::buildAffiliationListHtml()`: fixed Stored XSS by escaping the affiliation acronym.
- `ViewFormatter`: fixed `html_entity_decode(htmlspecialchars())` no-op.
- `EnrichmentService::mergeExistingAffiliations()`: fixed matching key lookup with `array_search()`.
- `AffiliationHelper::isAcronymDuplicate()`: now iterates all identifiers.
- `AffiliationHelper::setOrUpdateRorAcronym()`: returns first match deterministically.
- `TeiCacheManager::buildApiUrl()`: applied `urlencode()` to prevent Solr query injection.
- `TeiCacheManager::getFromCache()`: removed dead `expiresAfter()` call.
- `Repository`: fixed `JSON_DECODE_FLAGS`.
- `CommentsManager::$_typeLabel`: added missing entry for `TYPE_CONTRIBUTOR_TO_REVIEWER`.
- `CommentsManager::updateUid()`: fixed negative UID guard.
- `FormatIssn::FormatIssn()`: fixed substring length.
- `Log::log()`: fixed uncaught exception from `$logger->log()`.
- `DoiAsLink::DoiAsLink()`: fixed label when no text provided.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass with `preg_quote()` and anchors.
- Fixed RT#277365: added TinyMCE configuration options to handle `<br>` and `<p>` correctly.
- [#886](https://github.com/CCSDForge/episciences/issues/886): reminder logic for lack of reviewers fixed.
- `convertToBytes()`: fixed handling of pure numeric strings and added validation.
- `isHal()`, `isHalUrl()`, `isArxiv()`: fixed regex anchors.
- Added comprehensive test coverage for `Episciences_Tools`.
- [#236](https://github.com/CCSDForge/episciences-front/issues/236): HTML entities (&) displayed in the title.
- [#695](https://github.com/CCSDForge/episciences/issues/695) Fixed CC/BCC fields not clickable in paper status change forms.
- [#830](https://github.com/CCSDForge/episciences/issues/830) Paper number messed up for secondary volume; altered secondary volume rendering.
- [#776](https://github.com/CCSDForge/episciences/issues/776) Action Required: Fix Renovate Configuration.
- [#657](https://github.com/CCSDForge/episciences/issues/657) Conditionally remove the `<hr>` separator in metadata views.
- [#786](https://github.com/CCSDForge/episciences/issues/786) English translation of 'Télécharger le fichier'.
- [#646](https://github.com/CCSDForge/episciences/issues/646) Redirect to article page after submission.
- [#780](https://github.com/CCSDForge/episciences/issues/780) Option to lock volume name when an article is published.
- [#147](https://github.com/CCSDForge/episciences/issues/147) Add new pages Acknowledgements in menu 'About'.
- Fixed Paper Metrics based on wrong DocId.
- Fixed Pre-defined pages not deleted from the database.
- [#77](https://github.com/CCSDForge/episciences-front/issues/77) Fix orphan assignments when deleting sections or volumes.
- `CrossrefTools`: fixed fatal error on constant name; fixed raw `CacheItem` return.
- `OpenalexTools`: fixed `getPages()` return value and trailing separator in `getAuthors()`.
- `OpencitationsTools`: fixed caching of empty responses; fixed DOI extraction and updated API format support.
- `getLinkData`: switched to HTTPS; added null guards for `$valuesResult`.
- `getLicenceDataEnrichment`: fixed ineffective cache.
- `OpenAireResearchGraphTools`: enabled strict mode for `array_search()`.
- `Projects/EnrichmentService`: fixed log path and added `(int)` cast for source ID.
- `Citations/EnrichmentService`: added null guards for OpenAlex API fields.
- [#875](https://github.com/CCSDForge/episciences/issues/875): fixed HTML/Emoji translation issue.

### Deprecated

- `Episciences_Cache` and its parent `Ccsd_Cache` are now marked `@deprecated`; use `Symfony\Component\Cache\Adapter\FilesystemAdapter` instead.
- `ProjectsManager` kept as a thin backward-compatible facade.
- `CitationsManager` kept as backward-compatible facade with `@deprecated` proxies.
- `AuthorsManager` kept as orchestrator with backward-compatible `@deprecated` proxies.
- `Episciences_Paper_Visits::count` is deprecated.

### Removed

- Redundant "Statut actuel :" label prefix from the article status panel.
- Stray `<br>` and inlined `versionCache` script tag in `partials/paper_affiliation_authors.phtml`.
- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`.

### Security

- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`, eliminating a potential PHP object injection vector.
- OAI resumption token cache keys are now MD5-hashed before use, preventing cache-key injection.
- Escaped output in `volume/editors_list.phtml` to prevent potential XSS.
- Fixed XSS vulnerability in `Projects/ViewFormatter`: funding URL was interpolated unescaped into `href` attribute and link text.
- Fixed XSS in `Citations_ViewFormatter`: `href=` attributes for DOI and OA links were unquoted.
- Fixed XSS vulnerability in `ViewFormatter::buildAuthorHtml()` and `buildAffiliationListHtml()` regarding user-controlled values in HTML attributes.
- Fixed potential Solr query injection in `TeiCacheManager::buildApiUrl()`.
- `GetAvatar::asPaperStatusSvg()`: fixed two path traversal vectors ($lang sanitization and $paperStatus casting).
- `DoiAsLink::DoiAsLink()`: added `rel="noopener noreferrer"` to prevent tab-napping.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass that allowed authentication from unauthorized email domains.

### Performances

- `volume/list.phtml`: Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: construction of the editors list optimized.
- `Episciences_Paper_Citations_Logger`: Singleton Monolog logger (avoids Logger recreation on every call).
- Paper metrics Refactored; 1 query instead of 2.

## v1.0.55.3 - 2026-05-20

### Fixed

- [RT#287093]: Failed to submit "acceptedAskAuthorsFinalVersion" Form: CSRF validation failed (CSRF field missing)
- Adapt "acceptedAskAuthorsFinalVersion" form to the requirements for setting a deadline for requesting a revision after acceptance

### Changed

- [#1010](https://github.com/CCSDForge/episciences/issues/1010) The allowed range for modifying the deadline is now between the original deadline minus "rating_deadline_min" and the original deadline plus "rating_deadline_max".
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration
  button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)"

## v1.0.55.2 - 2026-05-19

### Fixed

- Improved article URL resolution in `ExtractBiblioRefsCommand` by adding fallbacks to `getMainPaperUrl()` and `getDocUrl()` when the repository template is empty (e.g., for Zenodo).
- Added debug logging for resolved article URLs and included raw response snippets in JSON decode error messages in `ExtractBiblioRefsCommand`.

## v1.0.55.1 - 2026-05-19

### Added

- Legend for bibliographic reference status icons in both admin and public views (publicly shown only when problematic references are detected).

### Changed

- Replaced bibliographic reference status indicators with standardized square icons (red `fa-square-xmark` for problematic, green `fa-square-check` for validated, and gray `fa-square` for automatically extracted references).
- Improved accessibility of bibliographic references (added ARIA landmarks, screen-reader-only status prefixes, descriptions for status badges, and warnings for links opening in new tabs).
- Optimized bibliographic references layout by moving the status legend inline with the action button.
- Renamed 'Not validated reference' to 'Automatically extracted reference' for clarity.
- Renamed 'Manage' button to 'Manage References' / 'Gérer les références' in the administration view.

### Fixed

- Removed obsolete BibTeX import button (feature migrated to an external application).
- Removed hardcoded Semantic Scholar source attribution from bibliographic references display.
- Skip temporary versions (without external repository URL) during bibliographic reference extraction.
- Fixed a missing dependency (`require_once`) for `GetDoiCommand` in `console.php`.

## v1.0.55 - 2026-05-18

### Added

- Automatic detection of problematic papers cited in the references of the papers. This is heavily based on the huge work provided by the Problematic Paper Screener https://dbrech.irit.fr/pls/apex/f?p=9999:1::::::
  For more information: Cabanac, G., Labbé, C., & Magazinov, A. (2022). The ‘Problematic Paper Screener’ automatically selects suspect publications for post-publication (re)assessment.
  Presented at WCRI 2022: 7th World Conference on Research Integrity. arXiv preprint. https://doi.org/10.48550/arXiv.2210.04895

- [#630](https://github.com/CCSDForge/episciences/issues/630) COI (Conflict of Interest) notifications:
    - Email notification to the editor-in-chief when an editor declares a positive COI (answered "yes")
    - Email notification to other assigned editors (if any) when an editor declares a COI
- Many legacy scripts migrated to Symfony Console commands (e.g., `doi:manage` replaces `getDoi.php`, `sitemap:generate --all`, translations update).
- Expanded unit test coverage for repository connectors (Zenodo, BioMedRxiv, ARCHE, Dspace) and COI notifications (+60 tests).

### Changed

- Sending the invitation in the user’s language, including when using the autocomplete input field for CAS users who already have a profile on the site.
- Simplified news add/edit form for a better user experience.
- Refactored OpenAIRE integration into a dedicated `OpenAireApiClient`.
- Improved PHP 8.1 compatibility and robustness across the codebase (removed deprecations and warnings).
- Optimized the submission workflow processing order.

### Fixed

- A bug that likely appeared in the latest update: section and/or volume editors are not assigned automatically, even if the relevant settings are enabled.
- Panel not opening when updating linked data
- Submission of a dataset without a data descriptor due to an error while uploading attached files (one of the file types exceeded the maximum allowed size for that field type)
- Corrected article position in volumes (1-based instead of 0-based) for CSL generation.
- Resolved relative `hydra:next` URLs in sitemap generation.
- Fixed several potential `TypeError` and crashes when metadata or publication dates are missing.

## v1.0.54.3 - 2026-04-23

### Fixed

- The link to the new version of the data descriptor isn't working; it still points to the first version.
- [RT#285106]: Application error when submitting with 'display secondary volume' option enabled
- Always SELECT RECORD so Paper::getMetadata() / toJson() can resolve titles and abstracts
- Log parser: extract IP from syslog-prefixed Apache log lines

## v1.0.54.2 - 2026-04-16

### Fixed

- Inability to change the review deadline (restore loading js via url).

### Added

- docs: add documentation index to README and update docs
- stats:import-logs command to parse Apache logs into STAT_TEMP. Introduces ImportApacheLogsCommand (stats:import-logs), a Symfony Console command that parses Apache access logs and inserts article visits into STAT_TEMP. Replaces the legacy UpdateStatistics.php . Run src/mysql/2025-08-24-stat-processing-log-table.sql before deploying

## v1.0.54.1 - 2026-04-14

### Changed

- Refactored English email templates, grammar only, no new features, no new tags

### Fixed

- [#962](https://github.com/CCSDForge/episciences/issues/962) Fixed double HTML encoding bug in volume metadata titles where special
  characters like `<`, `>`, and `'` were displayed as `&lt;`, `&gt;`, `&#039;`
- TypeError in enrichment:creators on non-sequential affiliation keys
- isImported detection for published papers when the the publication date has been fixed after publication
- [#985](https://github.com/CCSDForge/episciences/issues/985) Impossible to submit a final version
- Fixed bug in management of journal headers for adding and updating logo and texts

## v1.0.54 - 2026-04-09

### Added

- [#779](https://github.com/CCSDForge/episciences/issues/779) [Feature request] Display the secondary volume on the article page. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Adding "Publish" => "Proposing special issues" template page [#951](https://github.com/CCSDForge/episciences/issues/951)
- New supported servers:
    - Cryptology ePrint Archive (https://eprint.iacr.org)
    - Support for any data repositories powered by Dspace (eg. [repositorium.uminho(University of Minho)](https://repositorium.uminho.pt))
- Statistics: new Symfony Console commands `stats:process` (replaces legacy `scripts/stat.php`) and `stats:update-robots-list` for UA-based bot detection using the COUNTER Robots list; real client IP is now stored in `STAT_TEMP` and anonymized at processing time.
- Documentation: added `docs/console-commands.md` listing all 19 CLI commands available via `scripts/console.php` with options and usage examples.
- OAI-PMH: added `crossref` as a new metadata format (`metadataPrefix=crossref`), exposing Crossref 5.3.1 XML for each published paper. The depositor email is automatically replaced with a generic `noreply@{domain}` address in this context.
- Comprehensive Jest test suite for ORCID authors management (`tests/js/updateOrcidAuthors.test.js`).
- Unit tests for `Episciences_Paper_Projects` entity (setOptions aliases, DateTime handling, toArray serialization, fluent setters).
- Unit tests for `Episciences_Paper_Projects_EnrichmentService` pure functions (EU/ANR response normalization, OpenAIRE relation filtering).
- Unit tests for `Episciences_Paper_Projects_ViewFormatter` (empty-array return, URL HTML-escaping, unidentified title suppression).
- Unit tests for `Episciences_Paper_Citations_ViewFormatter` (20 tests: sort, author formatting, ORCID links, XSS prevention, book-chapter/proceedings-article reordering).
- Unit tests for `Episciences_Paper_Citations` entity (8 tests: `toArray()` key, fluent setters, nullable `updatedAt`, constructor).
- Comprehensive unit tests for `Episciences_Paper_Authors_ViewFormatter` covering HTML display logic and XSS prevention.
- Unit tests for `Episciences_View_Helper_DoiAsLink`, `Episciences_View_Helper_FormatIssn`, `Episciences_View_Helper_Log`, `Episciences_View_Helper_Tag`, `Episciences_View_Helper_UserAvatar`, `Episciences_View_Helper_GetAvatar` and `Episciences_CommentsManager`.
- 89+ unit tests for `Ccsd\Auth` adapters (`CasAbstract`, `Idp`, `Orcid`, `AdapterFactory`, `Asso`), `Ccsd\User` models (`User`, `UserTokens`, `UserFtpQuota`) and `Episciences\User` entities covering pure logic without DB or network access.
- Updated `tests/README.md` with accurate Docker-based testing instructions, `make` target reference, subset-run examples, and contributor guidelines.
- [#883](https://github.com/CCSDForge/episciences/issues/883) Allow json files as attachments.
- Optional Webhook: It is now possible to report status changes to an external entry point (can be configured by journal).
- [#658](https://github.com/CCSDForge/episciences/issues/658) It is now possible to link an invitation that is not intended for you to your account.
- allow the co-author to view the publication.
- Statistics: the script has a new parameter `--all` - Process all statistics (with confirmation prompt).
- New option to allow Editors to receive 'Comments for editors' before declaring a conflict of interest (disabled by default).
- Volume Settings: add New configuration section(displayEmptyVolumes and allowEditVolumeTitleWithPublishedArticles) in journal settings.
- [#679](https://github.com/CCSDForge/episciences/issues/679) "Ask other editors for their opinion" form now includes chief editors (ROLE_CHIEF_EDITOR) in addition to regular editors (ROLE_EDITOR).
- [#691](http://github.com/CCSDForge/episciences/issues/691) Display "(optional)" label below comment and cover letter fields in submission forms to clarify these fields are not required.
- [#350](https://github.com/CCSDForge/episciences/issues/350) Author-Editor Communication Settings:
    - New option to allow authors to contact assigned editors (`authorEditorCommunication`)
    - New option to disclose editor names to authors or keep them anonymized (`discloseEditorNamesToAuthors`)
- feat(navigation): sync predefined page titles with T_PAGES table on menu save.
- New `library/Episciences/Api/` namespace with 5 injectable, independently-testable API clients: `AbstractApiClient`, `CrossrefApiClient`, `OpenAlexApiClient`, `OpenCitationsApiClient`, `OpenAireApiClient`.
- Five Symfony Console commands replacing the legacy JournalScript enrichment scripts: `enrichment:citations`, `enrichment:creators`, `enrichment:funding`, `enrichment:licences`, `enrichment:links`.
- Added MSC2020 Classification in ZBJATS export.
- `Episciences\Notify\NotifySourceConfig` — immutable value object holding per-source configuration (repoId, label, originId, originInbox, acceptedTypes); new repositories can be added without touching `InboxNotifications`
- `Episciences\Notify\NotifySourceRegistry` — registry with a `createFromConstants()` factory; resolves source config from a payload's `origin.inbox`
- `Episciences\Notify\ValidationResult` — pure value object wrapping a validation outcome (success / failure + error message)
- `Episciences\Notify\PreprintUrlParser` — pure helper extracting version and identifier from preprint landing-page URLs (HAL-style)
- `Episciences\Notify\PayloadValidator` — validates COAR Notify 1.0.1 payloads for Offer patterns (Request Review / Request Endorsement): all required fields including `object.id` (HTTP URI) and `object['ietf:item']` (id, type, mediaType)

### Changed

- [#947](https://github.com/CCSDForge/episciences/issues/947) [Feature request] wording on the platform - refused / rejected and refused/declined
- Refactored `public/js/paper/updateOrcidAuthors.js` from jQuery to modern vanilla ES6+ with a class-based architecture.
- Enhanced ORCID form validation: added duplicate detection while maintaining support for empty submissions for deletions.
- Update TinyMCE form 7.3.0 to 8.1.2.
- Increased Volume Description length to 1024 chars.
- DOI panel: requesting, saving, and cancelling a DOI no longer triggers a full page reload; success feedback message is suppressed.
- Refactored `Episciences_Paper_ProjectsManager` into `Episciences_Paper_Projects_Repository`, `Episciences_Paper_Projects_HalApiClient`, `Episciences_Paper_Projects_EnrichmentService`, and `Episciences_Paper_Projects_ViewFormatter`.
- Refactored `Episciences_Paper_CitationsManager` into `Episciences_Paper_Citations_Repository`, `Episciences_Paper_Citations_ViewFormatter`, `Episciences_Paper_Citations_EnrichmentService`, and `Episciences_Paper_Citations_Logger`.
- Redundant PHPDoc removed from all new citations classes via Rector.
- Replaced `Episciences_Cache` with `symfony/cache` 5.4 across all internal usages.
- `PapersManager::getList()`: removed `$cached` parameter; paper list is now always fetched fresh.
- `Review::getPapers()`, `CopyEditor::loadAssignedPapers()`, `Editor::loadAssignedPapers()`, `Reviewer::loadAssignedPapers()`, `Volume::getPaperListFromVolume()`: updated signatures and call sites following the removal of `$cached`.
- `Oai/Server::getIds()`: OAI resumption token cache migrated to PSR-6.
- Statistics: log import script now supports anonymized log files (.access_log_anonym.gz) as a fallback.
- Improved accessibility of the ORCID authors modal: proper `<label>` associations, `aria-modal="true"`, and corrected `aria-labelledby`.
- Modernized ORCID authors row layout using Flexbox.
- Standardized modal button styles for ORCID updates (Cancel: `btn-default`, Save: `btn-primary`).
- ORCID input fields: URLs are now automatically cleaned to keep only the 16-digit identifier.
- `administratepaper/view.phtml`: reordered panels; moved "Volumes & Rubriques" earlier; `paper_versions` moved to the bottom; removed redundant label.
- `paper/paper_datasets.phtml`: "Liens publications – données – logiciels" panel is now collapsed by default.
- `paper/paper_graphical_abstract.phtml`: graphical abstract panel is now collapsed by default when empty.
- `partials/coauthors.phtml`: "Ajouter un co-auteur" panel is now collapsed by default.
- `partials/paper_affiliation_authors.phtml`: "Ajouter une affiliation" panel is now collapsed by default.
- `volume/list.phtml`: Added "Number" and "Year" columns; improved table accessibility; Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: Removed redundant inline scripts; construction of the editors list optimized.
- Updated icons from `fas fa-address-card` to `fa-regular fa-address-card`.
- Improved visual indicators for editor availability in `editor-availability.js`.
- Refactored `scripts/zbjatZipper.php`: renamed class to `ZbjatZipper`, replaced `echo` with Monolog, extracted God method, switched to HTTPS.
- Refactored `Episciences_Paper_AuthorsManager` into `Episciences_Hal_TeiCacheManager`, `Episciences_Paper_Authors_HalTeiParser`, `Episciences_Paper_Authors_Repository`, `Episciences_Paper_Authors_EnrichmentService`, `Episciences_Paper_Authors_AffiliationHelper`, and `Episciences_Paper_Authors_ViewFormatter`.
- Refactored `Episciences_Paper_Authors_ViewFormatter` to separate data fetching from formatting logic.
- Moved `normalizeOrcid()` implementation from `AuthorsManager` to `HalTeiParser`.
- `AuthorsManager::ONE_MONTH` now references `TeiCacheManager::ONE_MONTH`.
- Added `TeiCacheManager::fetchAndGet()` to combine fetch-if-needed + read in a single call.
- The "Encapsulate reviewers" parameter is now hidden.
- [#528](https://github.com/CCSDForge/episciences/issues/528): 'upload a new version' on top; updated contact label.
- Paper metrics Refactored; 1 query instead of 2.
- The Locale is now stored in a cookie instead of the session.
- Redirect to the article to be reviewed when accepting an invitation.
- Allow volume years to be a string (AAAA or AAAA-AAAA).
- Synchronization of predefined page titles between navigation menu and T_PAGES table when saving menu configuration.
- Replaced `cottagelabs/coar-notifications` (Doctrine ORM + Guzzle) with `cottagelabs/coarnotify` (stateless pattern builder) and a custom PDO persistence layer (`Notification` DTO + `NotificationsRepository`)
- Refactored `InboxNotifications` (1390-line God Class) into focused private methods (`processFirstSubmission`, `processSubsequentSubmission`, `setupJournalTranslations`, `resolveNewVersionStatus`, `logVersionUpdate`, …) and pure, dependency-free helper classes
- `checkNotifyPayloads()` and `initSubmission()` are now source-aware: the COAR Notify source is resolved from `origin.inbox` via `NotifySourceRegistry`
- `InitSubmissionsFromHalInbox.php` is now a direct CLI entry point on `InboxNotifications`; the empty subclass has been removed

### Fixed

- [#858](https://github.com/CCSDForge/episciences/issues/858) fix: only use "et al." when 2+ authors are omitted. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Limit the editor’s permissions to allow assigning only the "reviewer" role.
- [#944](https://github.com/CCSDForge/episciences/issues/944): An author informed us that they received this message signed with their name: %%SENDER_FULL_NAME%% and %%RECIPIENT_SCREEN_NAME%% filled with the same value
- [#258](https://github.com/CCSDForge/episciences-front/issues/258): Tables are not handled by default: Explicit table conversion
- Fixed RT#277365: in order to correct the printing of extra lines, two options have been added to the TinyMCE configuration to insert a <br> instead of a <p> and another option to remove extra <br>s at the end of a block
- Fixed tooltip initialization in volume and section assignment after AJAX refreshes and DataTable redraws.
- Fixed Solr search engine indexation of Authors with a middle name.
- Consider an additional review when sending reminders for an insufficient number of reviewers.
- `Projects`: `$_dateUpdated` default fixed; `getDateUpdated()` return type consistency.
- `Projects::setFunding()`: made method fluent.
- `Projects::setOptions()`: fixed `setIdproject()` alias and case-insensitive method lookup.
- `Projects::toArray()`: fixed `DateTime` serialization.
- `Repository::insert()`: replaced deprecated `VALUES()` syntax with row-alias syntax for MySQL 8.0.20+.
- `Repository`: exceptions now propagate instead of terminating script on DB errors.
- `ViewFormatter`: fixed XSS by escaping `$vfunding['url']` and other user-controlled values in HTML attributes.
- `HalApiClient::doGet()`: added `E_USER_WARNING` severity level.
- `EnrichmentService::resolveHalProjectIds()`: removed duplicate log message; sanitized cache key.
- `Citations_ViewFormatter`: removed double escaping; fixed unstable compound sort; added ORCID format validation.
- `Citations_Repository`: replaced deprecated MySQL `VALUES()` function; added check for `$docId <= 0`.
- `Citations` entity: fixed `toArray()` key name; fixed `$_updatedAt` type and default.
- ORCID normalization: `cleanLowerCaseOrcid()` fix; applied normalization in Zenodo and ARCHE hooks.
- `findAffiliationsOneAuthorByPaperId()`: fixed potential undefined variable.
- `hasAcronym()`: fixed iteration over nested `id` array.
- `HalTeiParser::getAuthorsFromHalTei()`: fixed logic to prevent enriching the wrong author.
- `ViewFormatter::buildAffiliationListHtml()`: fixed Stored XSS by escaping the affiliation acronym.
- `ViewFormatter`: fixed `html_entity_decode(htmlspecialchars())` no-op.
- `EnrichmentService::mergeExistingAffiliations()`: fixed matching key lookup with `array_search()`.
- `AffiliationHelper::isAcronymDuplicate()`: now iterates all identifiers.
- `AffiliationHelper::setOrUpdateRorAcronym()`: returns first match deterministically.
- `TeiCacheManager::buildApiUrl()`: applied `urlencode()` to prevent Solr query injection.
- `TeiCacheManager::getFromCache()`: removed dead `expiresAfter()` call.
- `Repository`: fixed `JSON_DECODE_FLAGS`.
- `CommentsManager::$_typeLabel`: added missing entry for `TYPE_CONTRIBUTOR_TO_REVIEWER`.
- `CommentsManager::updateUid()`: fixed negative UID guard.
- `FormatIssn::FormatIssn()`: fixed substring length.
- `Log::log()`: fixed uncaught exception from `$logger->log()`.
- `DoiAsLink::DoiAsLink()`: fixed label when no text provided.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass with `preg_quote()` and anchors.
- Fixed RT#277365: added TinyMCE configuration options to handle `<br>` and `<p>` correctly.
- [#886](https://github.com/CCSDForge/episciences/issues/886): reminder logic for lack of reviewers fixed.
- `convertToBytes()`: fixed handling of pure numeric strings and added validation.
- `isHal()`, `isHalUrl()`, `isArxiv()`: fixed regex anchors.
- Added comprehensive test coverage for `Episciences_Tools`.
- [#236](https://github.com/CCSDForge/episciences-front/issues/236): HTML entities (&) displayed in the title.
- [#695](https://github.com/CCSDForge/episciences/issues/695) Fixed CC/BCC fields not clickable in paper status change forms.
- [#830](https://github.com/CCSDForge/episciences/issues/830) Paper number messed up for secondary volume; altered secondary volume rendering.
- [#776](https://github.com/CCSDForge/episciences/issues/776) Action Required: Fix Renovate Configuration.
- [#657](https://github.com/CCSDForge/episciences/issues/657) Conditionally remove the `<hr>` separator in metadata views.
- [#786](https://github.com/CCSDForge/episciences/issues/786) English translation of 'Télécharger le fichier'.
- [#646](https://github.com/CCSDForge/episciences/issues/646) Redirect to article page after submission.
- [#780](https://github.com/CCSDForge/episciences/issues/780) Option to lock volume name when an article is published.
- [#147](https://github.com/CCSDForge/episciences/issues/147) Add new pages Acknowledgements in menu 'About'.
- Fixed Paper Metrics based on wrong DocId.
- Fixed Pre-defined pages not deleted from the database.
- [#77](https://github.com/CCSDForge/episciences-front/issues/77) Fix orphan assignments when deleting sections or volumes.
- `CrossrefTools`: fixed fatal error on constant name; fixed raw `CacheItem` return.
- `OpenalexTools`: fixed `getPages()` return value and trailing separator in `getAuthors()`.
- `OpencitationsTools`: fixed caching of empty responses; fixed DOI extraction and updated API format support.
- `getLinkData`: switched to HTTPS; added null guards for `$valuesResult`.
- `getLicenceDataEnrichment`: fixed ineffective cache.
- `OpenAireResearchGraphTools`: enabled strict mode for `array_search()`.
- `Projects/EnrichmentService`: fixed log path and added `(int)` cast for source ID.
- `Citations/EnrichmentService`: added null guards for OpenAlex API fields.
- [#875](https://github.com/CCSDForge/episciences/issues/875): fixed HTML/Emoji translation issue.

### Deprecated

- `Episciences_Cache` and its parent `Ccsd_Cache` are now marked `@deprecated`; use `Symfony\Component\Cache\Adapter\FilesystemAdapter` instead.
- `ProjectsManager` kept as a thin backward-compatible facade.
- `CitationsManager` kept as backward-compatible facade with `@deprecated` proxies.
- `AuthorsManager` kept as orchestrator with backward-compatible `@deprecated` proxies.
- `Episciences_Paper_Visits::count` is deprecated.

### Removed

- Redundant "Statut actuel :" label prefix from the article status panel.
- Stray `<br>` and inlined `versionCache` script tag in `partials/paper_affiliation_authors.phtml`.
- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`.

### Security

- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`, eliminating a potential PHP object injection vector.
- OAI resumption token cache keys are now MD5-hashed before use, preventing cache-key injection.
- Escaped output in `volume/editors_list.phtml` to prevent potential XSS.
- Fixed XSS vulnerability in `Projects/ViewFormatter`: funding URL was interpolated unescaped into `href` attribute and link text.
- Fixed XSS in `Citations_ViewFormatter`: `href=` attributes for DOI and OA links were unquoted.
- Fixed XSS vulnerability in `ViewFormatter::buildAuthorHtml()` and `buildAffiliationListHtml()` regarding user-controlled values in HTML attributes.
- Fixed potential Solr query injection in `TeiCacheManager::buildApiUrl()`.
- `GetAvatar::asPaperStatusSvg()`: fixed two path traversal vectors ($lang sanitization and $paperStatus casting).
- `DoiAsLink::DoiAsLink()`: added `rel="noopener noreferrer"` to prevent tab-napping.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass that allowed authentication from unauthorized email domains.

### Performances

- `volume/list.phtml`: Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: construction of the editors list optimized.
- `Episciences_Paper_Citations_Logger`: Singleton Monolog logger (avoids Logger recreation on every call).
- Paper metrics Refactored; 1 query instead of 2.

## v1.0.55.3 - 2026-05-20

### Fixed

- [RT#287093]: Failed to submit "acceptedAskAuthorsFinalVersion" Form: CSRF validation failed (CSRF field missing)
- Adapt "acceptedAskAuthorsFinalVersion" form to the requirements for setting a deadline for requesting a revision after acceptance

### Changed

- [#1010](https://github.com/CCSDForge/episciences/issues/1010) The allowed range for modifying the deadline is now between the original deadline minus "rating_deadline_min" and the original deadline plus "rating_deadline_max".
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration
  button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)"

## v1.0.55.2 - 2026-05-19

### Fixed

- Improved article URL resolution in `ExtractBiblioRefsCommand` by adding fallbacks to `getMainPaperUrl()` and `getDocUrl()` when the repository template is empty (e.g., for Zenodo).
- Added debug logging for resolved article URLs and included raw response snippets in JSON decode error messages in `ExtractBiblioRefsCommand`.

## v1.0.55.1 - 2026-05-19

### Added

- Legend for bibliographic reference status icons in both admin and public views (publicly shown only when problematic references are detected).

### Changed

- Replaced bibliographic reference status indicators with standardized square icons (red `fa-square-xmark` for problematic, green `fa-square-check` for validated, and gray `fa-square` for automatically extracted references).
- Improved accessibility of bibliographic references (added ARIA landmarks, screen-reader-only status prefixes, descriptions for status badges, and warnings for links opening in new tabs).
- Optimized bibliographic references layout by moving the status legend inline with the action button.
- Renamed 'Not validated reference' to 'Automatically extracted reference' for clarity.
- Renamed 'Manage' button to 'Manage References' / 'Gérer les références' in the administration view.

### Fixed

- Removed obsolete BibTeX import button (feature migrated to an external application).
- Removed hardcoded Semantic Scholar source attribution from bibliographic references display.
- Skip temporary versions (without external repository URL) during bibliographic reference extraction.
- Fixed a missing dependency (`require_once`) for `GetDoiCommand` in `console.php`.

## v1.0.55 - 2026-05-18

### Added

- Automatic detection of problematic papers cited in the references of the papers. This is heavily based on the huge work provided by the Problematic Paper Screener https://dbrech.irit.fr/pls/apex/f?p=9999:1::::::
  For more information: Cabanac, G., Labbé, C., & Magazinov, A. (2022). The ‘Problematic Paper Screener’ automatically selects suspect publications for post-publication (re)assessment.
  Presented at WCRI 2022: 7th World Conference on Research Integrity. arXiv preprint. https://doi.org/10.48550/arXiv.2210.04895

- [#630](https://github.com/CCSDForge/episciences/issues/630) COI (Conflict of Interest) notifications:
    - Email notification to the editor-in-chief when an editor declares a positive COI (answered "yes")
    - Email notification to other assigned editors (if any) when an editor declares a COI
- Many legacy scripts migrated to Symfony Console commands (e.g., `doi:manage` replaces `getDoi.php`, `sitemap:generate --all`, translations update).
- Expanded unit test coverage for repository connectors (Zenodo, BioMedRxiv, ARCHE, Dspace) and COI notifications (+60 tests).

### Changed

- Sending the invitation in the user’s language, including when using the autocomplete input field for CAS users who already have a profile on the site.
- Simplified news add/edit form for a better user experience.
- Refactored OpenAIRE integration into a dedicated `OpenAireApiClient`.
- Improved PHP 8.1 compatibility and robustness across the codebase (removed deprecations and warnings).
- Optimized the submission workflow processing order.

### Fixed

- A bug that likely appeared in the latest update: section and/or volume editors are not assigned automatically, even if the relevant settings are enabled.
- Panel not opening when updating linked data
- Submission of a dataset without a data descriptor due to an error while uploading attached files (one of the file types exceeded the maximum allowed size for that field type)
- Corrected article position in volumes (1-based instead of 0-based) for CSL generation.
- Resolved relative `hydra:next` URLs in sitemap generation.
- Fixed several potential `TypeError` and crashes when metadata or publication dates are missing.

## v1.0.54.3 - 2026-04-23

### Fixed

- The link to the new version of the data descriptor isn't working; it still points to the first version.
- [RT#285106]: Application error when submitting with 'display secondary volume' option enabled
- Always SELECT RECORD so Paper::getMetadata() / toJson() can resolve titles and abstracts
- Log parser: extract IP from syslog-prefixed Apache log lines

## v1.0.54.2 - 2026-04-16

### Fixed

- Inability to change the review deadline (restore loading js via url).

### Added

- docs: add documentation index to README and update docs
- stats:import-logs command to parse Apache logs into STAT_TEMP. Introduces ImportApacheLogsCommand (stats:import-logs), a Symfony Console command that parses Apache access logs and inserts article visits into STAT_TEMP. Replaces the legacy UpdateStatistics.php . Run src/mysql/2025-08-24-stat-processing-log-table.sql before deploying

## v1.0.54.1 - 2026-04-14

### Changed

- Refactored English email templates, grammar only, no new features, no new tags

### Fixed

- [#962](https://github.com/CCSDForge/episciences/issues/962) Fixed double HTML encoding bug in volume metadata titles where special
  characters like `<`, `>`, and `'` were displayed as `&lt;`, `&gt;`, `&#039;`
- TypeError in enrichment:creators on non-sequential affiliation keys
- isImported detection for published papers when the the publication date has been fixed after publication
- [#985](https://github.com/CCSDForge/episciences/issues/985) Impossible to submit a final version
- Fixed bug in management of journal headers for adding and updating logo and texts

## v1.0.54 - 2026-04-09

### Added

- [#779](https://github.com/CCSDForge/episciences/issues/779) [Feature request] Display the secondary volume on the article page. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Adding "Publish" => "Proposing special issues" template page [#951](https://github.com/CCSDForge/episciences/issues/951)
- New supported servers:
    - Cryptology ePrint Archive (https://eprint.iacr.org)
    - Support for any data repositories powered by Dspace (eg. [repositorium.uminho(University of Minho)](https://repositorium.uminho.pt))
- Statistics: new Symfony Console commands `stats:process` (replaces legacy `scripts/stat.php`) and `stats:update-robots-list` for UA-based bot detection using the COUNTER Robots list; real client IP is now stored in `STAT_TEMP` and anonymized at processing time.
- Documentation: added `docs/console-commands.md` listing all 19 CLI commands available via `scripts/console.php` with options and usage examples.
- OAI-PMH: added `crossref` as a new metadata format (`metadataPrefix=crossref`), exposing Crossref 5.3.1 XML for each published paper. The depositor email is automatically replaced with a generic `noreply@{domain}` address in this context.
- Comprehensive Jest test suite for ORCID authors management (`tests/js/updateOrcidAuthors.test.js`).
- Unit tests for `Episciences_Paper_Projects` entity (setOptions aliases, DateTime handling, toArray serialization, fluent setters).
- Unit tests for `Episciences_Paper_Projects_EnrichmentService` pure functions (EU/ANR response normalization, OpenAIRE relation filtering).
- Unit tests for `Episciences_Paper_Projects_ViewFormatter` (empty-array return, URL HTML-escaping, unidentified title suppression).
- Unit tests for `Episciences_Paper_Citations_ViewFormatter` (20 tests: sort, author formatting, ORCID links, XSS prevention, book-chapter/proceedings-article reordering).
- Unit tests for `Episciences_Paper_Citations` entity (8 tests: `toArray()` key, fluent setters, nullable `updatedAt`, constructor).
- Comprehensive unit tests for `Episciences_Paper_Authors_ViewFormatter` covering HTML display logic and XSS prevention.
- Unit tests for `Episciences_View_Helper_DoiAsLink`, `Episciences_View_Helper_FormatIssn`, `Episciences_View_Helper_Log`, `Episciences_View_Helper_Tag`, `Episciences_View_Helper_UserAvatar`, `Episciences_View_Helper_GetAvatar` and `Episciences_CommentsManager`.
- 89+ unit tests for `Ccsd\Auth` adapters (`CasAbstract`, `Idp`, `Orcid`, `AdapterFactory`, `Asso`), `Ccsd\User` models (`User`, `UserTokens`, `UserFtpQuota`) and `Episciences\User` entities covering pure logic without DB or network access.
- Updated `tests/README.md` with accurate Docker-based testing instructions, `make` target reference, subset-run examples, and contributor guidelines.
- [#883](https://github.com/CCSDForge/episciences/issues/883) Allow json files as attachments.
- Optional Webhook: It is now possible to report status changes to an external entry point (can be configured by journal).
- [#658](https://github.com/CCSDForge/episciences/issues/658) It is now possible to link an invitation that is not intended for you to your account.
- allow the co-author to view the publication.
- Statistics: the script has a new parameter `--all` - Process all statistics (with confirmation prompt).
- New option to allow Editors to receive 'Comments for editors' before declaring a conflict of interest (disabled by default).
- Volume Settings: add New configuration section(displayEmptyVolumes and allowEditVolumeTitleWithPublishedArticles) in journal settings.
- [#679](https://github.com/CCSDForge/episciences/issues/679) "Ask other editors for their opinion" form now includes chief editors (ROLE_CHIEF_EDITOR) in addition to regular editors (ROLE_EDITOR).
- [#691](http://github.com/CCSDForge/episciences/issues/691) Display "(optional)" label below comment and cover letter fields in submission forms to clarify these fields are not required.
- [#350](https://github.com/CCSDForge/episciences/issues/350) Author-Editor Communication Settings:
    - New option to allow authors to contact assigned editors (`authorEditorCommunication`)
    - New option to disclose editor names to authors or keep them anonymized (`discloseEditorNamesToAuthors`)
- feat(navigation): sync predefined page titles with T_PAGES table on menu save.
- New `library/Episciences/Api/` namespace with 5 injectable, independently-testable API clients: `AbstractApiClient`, `CrossrefApiClient`, `OpenAlexApiClient`, `OpenCitationsApiClient`, `OpenAireApiClient`.
- Five Symfony Console commands replacing the legacy JournalScript enrichment scripts: `enrichment:citations`, `enrichment:creators`, `enrichment:funding`, `enrichment:licences`, `enrichment:links`.
- Added MSC2020 Classification in ZBJATS export.
- `Episciences\Notify\NotifySourceConfig` — immutable value object holding per-source configuration (repoId, label, originId, originInbox, acceptedTypes); new repositories can be added without touching `InboxNotifications`
- `Episciences\Notify\NotifySourceRegistry` — registry with a `createFromConstants()` factory; resolves source config from a payload's `origin.inbox`
- `Episciences\Notify\ValidationResult` — pure value object wrapping a validation outcome (success / failure + error message)
- `Episciences\Notify\PreprintUrlParser` — pure helper extracting version and identifier from preprint landing-page URLs (HAL-style)
- `Episciences\Notify\PayloadValidator` — validates COAR Notify 1.0.1 payloads for Offer patterns (Request Review / Request Endorsement): all required fields including `object.id` (HTTP URI) and `object['ietf:item']` (id, type, mediaType)

### Changed

- [#947](https://github.com/CCSDForge/episciences/issues/947) [Feature request] wording on the platform - refused / rejected and refused/declined
- Refactored `public/js/paper/updateOrcidAuthors.js` from jQuery to modern vanilla ES6+ with a class-based architecture.
- Enhanced ORCID form validation: added duplicate detection while maintaining support for empty submissions for deletions.
- Update TinyMCE form 7.3.0 to 8.1.2.
- Increased Volume Description length to 1024 chars.
- DOI panel: requesting, saving, and cancelling a DOI no longer triggers a full page reload; success feedback message is suppressed.
- Refactored `Episciences_Paper_ProjectsManager` into `Episciences_Paper_Projects_Repository`, `Episciences_Paper_Projects_HalApiClient`, `Episciences_Paper_Projects_EnrichmentService`, and `Episciences_Paper_Projects_ViewFormatter`.
- Refactored `Episciences_Paper_CitationsManager` into `Episciences_Paper_Citations_Repository`, `Episciences_Paper_Citations_ViewFormatter`, `Episciences_Paper_Citations_EnrichmentService`, and `Episciences_Paper_Citations_Logger`.
- Redundant PHPDoc removed from all new citations classes via Rector.
- Replaced `Episciences_Cache` with `symfony/cache` 5.4 across all internal usages.
- `PapersManager::getList()`: removed `$cached` parameter; paper list is now always fetched fresh.
- `Review::getPapers()`, `CopyEditor::loadAssignedPapers()`, `Editor::loadAssignedPapers()`, `Reviewer::loadAssignedPapers()`, `Volume::getPaperListFromVolume()`: updated signatures and call sites following the removal of `$cached`.
- `Oai/Server::getIds()`: OAI resumption token cache migrated to PSR-6.
- Statistics: log import script now supports anonymized log files (.access_log_anonym.gz) as a fallback.
- Improved accessibility of the ORCID authors modal: proper `<label>` associations, `aria-modal="true"`, and corrected `aria-labelledby`.
- Modernized ORCID authors row layout using Flexbox.
- Standardized modal button styles for ORCID updates (Cancel: `btn-default`, Save: `btn-primary`).
- ORCID input fields: URLs are now automatically cleaned to keep only the 16-digit identifier.
- `administratepaper/view.phtml`: reordered panels; moved "Volumes & Rubriques" earlier; `paper_versions` moved to the bottom; removed redundant label.
- `paper/paper_datasets.phtml`: "Liens publications – données – logiciels" panel is now collapsed by default.
- `paper/paper_graphical_abstract.phtml`: graphical abstract panel is now collapsed by default when empty.
- `partials/coauthors.phtml`: "Ajouter un co-auteur" panel is now collapsed by default.
- `partials/paper_affiliation_authors.phtml`: "Ajouter une affiliation" panel is now collapsed by default.
- `volume/list.phtml`: Added "Number" and "Year" columns; improved table accessibility; Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: Removed redundant inline scripts; construction of the editors list optimized.
- Updated icons from `fas fa-address-card` to `fa-regular fa-address-card`.
- Improved visual indicators for editor availability in `editor-availability.js`.
- Refactored `scripts/zbjatZipper.php`: renamed class to `ZbjatZipper`, replaced `echo` with Monolog, extracted God method, switched to HTTPS.
- Refactored `Episciences_Paper_AuthorsManager` into `Episciences_Hal_TeiCacheManager`, `Episciences_Paper_Authors_HalTeiParser`, `Episciences_Paper_Authors_Repository`, `Episciences_Paper_Authors_EnrichmentService`, `Episciences_Paper_Authors_AffiliationHelper`, and `Episciences_Paper_Authors_ViewFormatter`.
- Refactored `Episciences_Paper_Authors_ViewFormatter` to separate data fetching from formatting logic.
- Moved `normalizeOrcid()` implementation from `AuthorsManager` to `HalTeiParser`.
- `AuthorsManager::ONE_MONTH` now references `TeiCacheManager::ONE_MONTH`.
- Added `TeiCacheManager::fetchAndGet()` to combine fetch-if-needed + read in a single call.
- The "Encapsulate reviewers" parameter is now hidden.
- [#528](https://github.com/CCSDForge/episciences/issues/528): 'upload a new version' on top; updated contact label.
- Paper metrics Refactored; 1 query instead of 2.
- The Locale is now stored in a cookie instead of the session.
- Redirect to the article to be reviewed when accepting an invitation.
- Allow volume years to be a string (AAAA or AAAA-AAAA).
- Synchronization of predefined page titles between navigation menu and T_PAGES table when saving menu configuration.
- Replaced `cottagelabs/coar-notifications` (Doctrine ORM + Guzzle) with `cottagelabs/coarnotify` (stateless pattern builder) and a custom PDO persistence layer (`Notification` DTO + `NotificationsRepository`)
- Refactored `InboxNotifications` (1390-line God Class) into focused private methods (`processFirstSubmission`, `processSubsequentSubmission`, `setupJournalTranslations`, `resolveNewVersionStatus`, `logVersionUpdate`, …) and pure, dependency-free helper classes
- `checkNotifyPayloads()` and `initSubmission()` are now source-aware: the COAR Notify source is resolved from `origin.inbox` via `NotifySourceRegistry`
- `InitSubmissionsFromHalInbox.php` is now a direct CLI entry point on `InboxNotifications`; the empty subclass has been removed

### Fixed

- [#858](https://github.com/CCSDForge/episciences/issues/858) fix: only use "et al." when 2+ authors are omitted. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Limit the editor’s permissions to allow assigning only the "reviewer" role.
- [#944](https://github.com/CCSDForge/episciences/issues/944): An author informed us that they received this message signed with their name: %%SENDER_FULL_NAME%% and %%RECIPIENT_SCREEN_NAME%% filled with the same value
- [#258](https://github.com/CCSDForge/episciences-front/issues/258): Tables are not handled by default: Explicit table conversion
- Fixed RT#277365: in order to correct the printing of extra lines, two options have been added to the TinyMCE configuration to insert a <br> instead of a <p> and another option to remove extra <br>s at the end of a block
- Fixed tooltip initialization in volume and section assignment after AJAX refreshes and DataTable redraws.
- Fixed Solr search engine indexation of Authors with a middle name.
- Consider an additional review when sending reminders for an insufficient number of reviewers.
- `Projects`: `$_dateUpdated` default fixed; `getDateUpdated()` return type consistency.
- `Projects::setFunding()`: made method fluent.
- `Projects::setOptions()`: fixed `setIdproject()` alias and case-insensitive method lookup.
- `Projects::toArray()`: fixed `DateTime` serialization.
- `Repository::insert()`: replaced deprecated `VALUES()` syntax with row-alias syntax for MySQL 8.0.20+.
- `Repository`: exceptions now propagate instead of terminating script on DB errors.
- `ViewFormatter`: fixed XSS by escaping `$vfunding['url']` and other user-controlled values in HTML attributes.
- `HalApiClient::doGet()`: added `E_USER_WARNING` severity level.
- `EnrichmentService::resolveHalProjectIds()`: removed duplicate log message; sanitized cache key.
- `Citations_ViewFormatter`: removed double escaping; fixed unstable compound sort; added ORCID format validation.
- `Citations_Repository`: replaced deprecated MySQL `VALUES()` function; added check for `$docId <= 0`.
- `Citations` entity: fixed `toArray()` key name; fixed `$_updatedAt` type and default.
- ORCID normalization: `cleanLowerCaseOrcid()` fix; applied normalization in Zenodo and ARCHE hooks.
- `findAffiliationsOneAuthorByPaperId()`: fixed potential undefined variable.
- `hasAcronym()`: fixed iteration over nested `id` array.
- `HalTeiParser::getAuthorsFromHalTei()`: fixed logic to prevent enriching the wrong author.
- `ViewFormatter::buildAffiliationListHtml()`: fixed Stored XSS by escaping the affiliation acronym.
- `ViewFormatter`: fixed `html_entity_decode(htmlspecialchars())` no-op.
- `EnrichmentService::mergeExistingAffiliations()`: fixed matching key lookup with `array_search()`.
- `AffiliationHelper::isAcronymDuplicate()`: now iterates all identifiers.
- `AffiliationHelper::setOrUpdateRorAcronym()`: returns first match deterministically.
- `TeiCacheManager::buildApiUrl()`: applied `urlencode()` to prevent Solr query injection.
- `TeiCacheManager::getFromCache()`: removed dead `expiresAfter()` call.
- `Repository`: fixed `JSON_DECODE_FLAGS`.
- `CommentsManager::$_typeLabel`: added missing entry for `TYPE_CONTRIBUTOR_TO_REVIEWER`.
- `CommentsManager::updateUid()`: fixed negative UID guard.
- `FormatIssn::FormatIssn()`: fixed substring length.
- `Log::log()`: fixed uncaught exception from `$logger->log()`.
- `DoiAsLink::DoiAsLink()`: fixed label when no text provided.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass with `preg_quote()` and anchors.
- Fixed RT#277365: added TinyMCE configuration options to handle `<br>` and `<p>` correctly.
- [#886](https://github.com/CCSDForge/episciences/issues/886): reminder logic for lack of reviewers fixed.
- `convertToBytes()`: fixed handling of pure numeric strings and added validation.
- `isHal()`, `isHalUrl()`, `isArxiv()`: fixed regex anchors.
- Added comprehensive test coverage for `Episciences_Tools`.
- [#236](https://github.com/CCSDForge/episciences-front/issues/236): HTML entities (&) displayed in the title.
- [#695](https://github.com/CCSDForge/episciences/issues/695) Fixed CC/BCC fields not clickable in paper status change forms.
- [#830](https://github.com/CCSDForge/episciences/issues/830) Paper number messed up for secondary volume; altered secondary volume rendering.
- [#776](https://github.com/CCSDForge/episciences/issues/776) Action Required: Fix Renovate Configuration.
- [#657](https://github.com/CCSDForge/episciences/issues/657) Conditionally remove the `<hr>` separator in metadata views.
- [#786](https://github.com/CCSDForge/episciences/issues/786) English translation of 'Télécharger le fichier'.
- [#646](https://github.com/CCSDForge/episciences/issues/646) Redirect to article page after submission.
- [#780](https://github.com/CCSDForge/episciences/issues/780) Option to lock volume name when an article is published.
- [#147](https://github.com/CCSDForge/episciences/issues/147) Add new pages Acknowledgements in menu 'About'.
- Fixed Paper Metrics based on wrong DocId.
- Fixed Pre-defined pages not deleted from the database.
- [#77](https://github.com/CCSDForge/episciences-front/issues/77) Fix orphan assignments when deleting sections or volumes.
- `CrossrefTools`: fixed fatal error on constant name; fixed raw `CacheItem` return.
- `OpenalexTools`: fixed `getPages()` return value and trailing separator in `getAuthors()`.
- `OpencitationsTools`: fixed caching of empty responses; fixed DOI extraction and updated API format support.
- `getLinkData`: switched to HTTPS; added null guards for `$valuesResult`.
- `getLicenceDataEnrichment`: fixed ineffective cache.
- `OpenAireResearchGraphTools`: enabled strict mode for `array_search()`.
- `Projects/EnrichmentService`: fixed log path and added `(int)` cast for source ID.
- `Citations/EnrichmentService`: added null guards for OpenAlex API fields.
- [#875](https://github.com/CCSDForge/episciences/issues/875): fixed HTML/Emoji translation issue.

### Deprecated

- `Episciences_Cache` and its parent `Ccsd_Cache` are now marked `@deprecated`; use `Symfony\Component\Cache\Adapter\FilesystemAdapter` instead.
- `ProjectsManager` kept as a thin backward-compatible facade.
- `CitationsManager` kept as backward-compatible facade with `@deprecated` proxies.
- `AuthorsManager` kept as orchestrator with backward-compatible `@deprecated` proxies.
- `Episciences_Paper_Visits::count` is deprecated.

### Removed

- Redundant "Statut actuel :" label prefix from the article status panel.
- Stray `<br>` and inlined `versionCache` script tag in `partials/paper_affiliation_authors.phtml`.
- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`.

### Security

- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`, eliminating a potential PHP object injection vector.
- OAI resumption token cache keys are now MD5-hashed before use, preventing cache-key injection.
- Escaped output in `volume/editors_list.phtml` to prevent potential XSS.
- Fixed XSS vulnerability in `Projects/ViewFormatter`: funding URL was interpolated unescaped into `href` attribute and link text.
- Fixed XSS in `Citations_ViewFormatter`: `href=` attributes for DOI and OA links were unquoted.
- Fixed XSS vulnerability in `ViewFormatter::buildAuthorHtml()` and `buildAffiliationListHtml()` regarding user-controlled values in HTML attributes.
- Fixed potential Solr query injection in `TeiCacheManager::buildApiUrl()`.
- `GetAvatar::asPaperStatusSvg()`: fixed two path traversal vectors ($lang sanitization and $paperStatus casting).
- `DoiAsLink::DoiAsLink()`: added `rel="noopener noreferrer"` to prevent tab-napping.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass that allowed authentication from unauthorized email domains.

### Performances

- `volume/list.phtml`: Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: construction of the editors list optimized.
- `Episciences_Paper_Citations_Logger`: Singleton Monolog logger (avoids Logger recreation on every call).
- Paper metrics Refactored; 1 query instead of 2.
