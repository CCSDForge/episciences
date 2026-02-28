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
### Changed
- Update TinyMCE form 7.3.0 to 8.1.2
- Increased Volume Description length to 1014 chars
- DOI panel (`paper_doi.phtml`, `request-doi.js`, `view.js`): requesting, saving, and cancelling a DOI no longer triggers a full page reload — the DOM is updated in place; the success feedback message after "Request a DOI" is suppressed since the newly rendered DOI link is sufficient.
- Refactored `Episciences_Paper_ProjectsManager` God Class into 4 single-responsibility classes:
- `Episciences_Paper_Projects_Repository` — database CRUD for `paper_projects`
- `Episciences_Paper_Projects_HalApiClient` — HTTP calls to the HAL API
- `Episciences_Paper_Projects_EnrichmentService` — funding enrichment orchestration, cache and logging
- `Episciences_Paper_Projects_ViewFormatter` — HTML rendering of funding metadata
- `ProjectsManager` kept as a thin backward-compatible facade; all public method signatures preserved
Refactored `Episciences_Paper_CitationsManager` (356-line God Class) into 4 single-responsibility classes:
- `Episciences_Paper_Citations_Repository` — Database I/O (upsert + fetch)
- `Episciences_Paper_Citations_ViewFormatter` — HTML rendering of citation lists
- `Episciences_Paper_Citations_EnrichmentService` — OpenCitations → OpenAlex → Crossref enrichment pipeline
- `Episciences_Paper_Citations_Logger` — Singleton Monolog logger (avoids Logger recreation on every call)
- `CitationsManager` kept as backward-compatible facade with `@deprecated` proxies
- Redundant PHPDoc (types duplicating signatures) removed from all new citations classes via Rector
- Replaced `Episciences_Cache` (file-based, backed by `Ccsd_Cache`) with `symfony/cache` 5.4 (PSR-6 `FilesystemAdapter`) across all internal usages
- `PapersManager::getList()`: removed `$cached` parameter; paper list is now always fetched fresh from the database
- `Review::getPapers()`, `CopyEditor::loadAssignedPapers()`, `Editor::loadAssignedPapers()`, `Reviewer::loadAssignedPapers()`, `Volume::getPaperListFromVolume()`: updated signatures and call sites following the removal of `$cached`
- `Oai/Server::getIds()`: OAI resumption token cache migrated to PSR-6 (`getItem` / `isHit` / `set` / `expiresAfter` / `save`); token conf is now stored natively by the adapter without manual `serialize()`/`unserialize()`
- Statistics: log import script now supports anonymized log files (.access_log_anonym.gz) as a fallback, with priority order: .access_log, .access_log.gz, .access_log_anonym.gz


### Deprecated
- `Episciences_Cache` and its parent `Ccsd_Cache` are now marked `@deprecated`; use `Symfony\Component\Cache\Adapter\FilesystemAdapter` instead

### Changed (UI)
- `administratepaper/view.phtml`: reordered panels — paper files, article status, contributor, co-authors, affiliations, and graphical abstract are now grouped at the top of the page; "Volumes & Rubriques" moved earlier; `paper_versions` moved to the bottom (before history); removed redundant "Statut actuel :" label prefix from the article status panel
- `paper/paper_datasets.phtml`: "Liens publications – données – logiciels" panel is now collapsed by default
- `paper/paper_graphical_abstract.phtml`: graphical abstract panel is now collapsed by default when no image has been uploaded
- `partials/coauthors.phtml`: "Ajouter un co-auteur" panel is now collapsed by default; minor HTML cleanup
- `partials/paper_affiliation_authors.phtml`: "Ajouter une affiliation" panel is now collapsed by default; removed stray `<br>`, inlined `versionCache` script tag
- `volume/list.phtml`: Added "Number" and "Year" columns; improved table accessibility (ARIA labels, column scopes).
- `volume/editors_list.phtml`: Removed redundant inline scripts; construction of the editors list optimized.
- Updated icons from `fas fa-address-card` to `fa-regular fa-address-card` in several views and partials.
- Improved visual indicators for editor availability in `editor-availability.js`.

### Security
- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`, eliminating a potential PHP object injection vector
- OAI resumption token cache keys are now MD5-hashed before use, preventing cache-key injection via crafted token values
- Escaped output in `volume/editors_list.phtml` to prevent potential XSS.
- Fixed XSS vulnerability in `Projects/ViewFormatter`: funding URL was interpolated unescaped into `href` attribute and link text, allowing HTML injection via malicious project metadata
- Fixed XSS in `Citations_ViewFormatter`: `href=` attributes for DOI and OA links were unquoted, allowing attribute injection when values contained spaces or special characters; now wrapped in double quotes with `ENT_QUOTES`
- Fixed XSS vulnerability in `ViewFormatter::buildAuthorHtml()` and `buildAffiliationListHtml()` where user-controlled values were interpolated into unquoted or improperly escaped HTML attributes (ORCID URL, data-title, and affiliation acronym)
- Fixed potential Solr query injection in `TeiCacheManager::buildApiUrl()`
- `GetAvatar::asPaperStatusSvg()`: fixed two path traversal vectors — `$lang` is now sanitized to `[a-z]+` before being interpolated into a filesystem path, and `$paperStatus` is cast to `int`
- `DoiAsLink::DoiAsLink()`: added `rel="noopener noreferrer"` to prevent tab-napping on external DOI links
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: regex bypass allowed authentication from unauthorized email domains (see Fixed)

### Performance
- `volume/list.phtml`: Eager load volume settings to prevent N+1 queries in the display loop.

### Fixed
- Fixed tooltip initialization in volume and section assignment by calling `activateTooltips()` after AJAX refreshes and on DataTable redraws.
- Fixed Solr search engine indexation of Authors with a middle name
-  consider an additional review when sending reminders for an insufficient number of reviewers
- `Projects`: `$_dateUpdated` default was the string `'CURRENT_TIMESTAMP'` instead of `null`; `getDateUpdated()` declared `DateTime` return type but could return a string
- `Projects::setFunding()`: method was not fluent (`void` return), inconsistent with all other setters
- `Projects::setOptions()`: key `'idproject'` produced `setIdproject()` which does not exist; fixed with a method alias `setIdproject()` → `setProjectId()` and case-insensitive method lookup so `'paperid'` resolves to `setPaperId()`
- `Projects::toArray()`: included a raw `DateTime` object, breaking serialization; `dateUpdated` is now formatted as `'Y-m-d H:i:s'` string
- `Repository::insert()`: deprecated `VALUES(funding)` syntax in `ON DUPLICATE KEY UPDATE` replaced with row-alias syntax compatible with MySQL 8.0.20+
- `Repository`: `trigger_error(E_USER_ERROR)` terminated the script on DB errors; exceptions now propagate so callers can handle failures
- `ViewFormatter`: `$vfunding['url']` was interpolated raw into `href="…"` and the link text; both are now escaped with `htmlspecialchars(ENT_QUOTES)`
- `HalApiClient::doGet()`: `trigger_error()` was called without a severity level; now uses `E_USER_WARNING`
- `EnrichmentService::resolveHalProjectIds()`: ANR project discovery message was echoed twice; duplicate removed
- `EnrichmentService::resolveHalProjectIds()`: cache key used raw `$identifier` which could contain filesystem-unsafe characters; sanitized with `preg_replace('/[^a-zA-Z0-9_\-]/', '_', …)`
- `Citations_ViewFormatter`: double `htmlspecialchars()` on author metadata — values were escaped twice (once before `reduceAuthorsView`, then again before `formatAuthors`); now escaped exactly once
- `Citations_ViewFormatter`: unstable compound sort — two sequential `usort()` calls (author then year) caused the year sort to discard author ordering; replaced with a single comparator (year desc, author asc)
- `Citations_ViewFormatter`: `createOrcidStringForView()` did not validate the ORCID format before building the URL; invalid values now return an empty string
- `Citations_Repository`: deprecated MySQL `VALUES()` function in `ON DUPLICATE KEY UPDATE` replaced with alias syntax (MySQL 8.0.20+)
- `Citations_Repository`: `findByDocId()` now rejects `$docId <= 0` instead of issuing a useless query
- `Citations` entity: `toArray()` returned key `'licence'` instead of `'citation'`
- `Citations` entity: `$_updatedAt` was typed as `string` with default `'CURRENT_TIMESTAMP'`; changed to `?DateTime = null` to match the declared return type of `getUpdatedAt()`
- ORCID normalization: `cleanLowerCaseOrcid()` did not strip `https://orcid.org/` URL prefix; new `normalizeOrcid()` method handles URL stripping, trimming, and lowercase `x` → `X` fix
- Applied `normalizeOrcid()` in Zenodo and ARCHE hooks where raw ORCID values were stored without normalization
- `findAffiliationsOneAuthorByPaperId()`: fixed potential undefined variable when author rows are empty
- `hasAcronym()`: fixed iteration over nested `id` array (was comparing top-level keys instead of inspecting each identifier sub-array, consistent with `hasRor()`)
- `HalTeiParser::getAuthorsFromHalTei()`: fixed logic to prevent enriching the wrong author when `persName` is missing in TEI XML
- `ViewFormatter`: fixed XSS via unquoted HTML attributes (`href`, `data-original-title`); values are now properly quoted and escaped with `htmlspecialchars()`
- `ViewFormatter::buildAffiliationListHtml()`: fixed Stored XSS by escaping the affiliation acronym
- `ViewFormatter`: fixed `html_entity_decode(htmlspecialchars())` no-op; plain-text author list now uses raw name, HTML template uses escaped name
- `EnrichmentService::mergeExistingAffiliations()`: fixed `key()` always returning 0 instead of the actual matching DB affiliation key; now uses `array_search()`
- `AffiliationHelper::isAcronymDuplicate()`: fixed hardcoded `[0]` index; now iterates all identifiers (consistent with `hasRor()`/`hasAcronym()`)
- `AffiliationHelper::setOrUpdateRorAcronym()`: returns first match deterministically instead of last
- `TeiCacheManager::buildApiUrl()`: applied `urlencode()` on identifier to prevent Solr query injection
- `TeiCacheManager::getFromCache()`: removed dead `expiresAfter()` call on the read path
- `Repository`: `JSON_DECODE_FLAGS` no longer includes encode-only flags (`JSON_UNESCAPED_SLASHES`, `JSON_UNESCAPED_UNICODE`)
- `CommentsManager::$_typeLabel`: added missing entry for `TYPE_CONTRIBUTOR_TO_REVIEWER` (type 11); lookups on that type silently returned `null`
- `CommentsManager::updateUid()`: negative UIDs were not rejected by the guard; changed `== 0` to `<= 0`
- `FormatIssn::FormatIssn()`: second `substr()` call used length `8` instead of `4`; worked by PHP leniency on short strings but was semantically wrong
- `Log::log()`: exception thrown by `$logger->log()` was not caught; only `Zend_Registry::get()` was inside the `try/catch` block
- `DoiAsLink::DoiAsLink()`: when no `$text` was provided, the link label displayed the bare DOI instead of the full `https://doi.org/…` URL
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: unescaped dot in regex allowed partial-match bypass (e.g. `user@inraXfr`); fixed with `preg_quote()` and a trailing `$` anchor to also prevent subdomain injection (e.g. `attacker@inra.fr.evil.com`)
