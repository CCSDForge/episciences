# Adding a New Submission Repository

This guide explains how to plug a new external repository (like arXiv, HAL, Zenodo,
Dataverse, DSpace, Cryptology ePrint...) into Episciences as a submission source, so
authors can submit a paper by identifier and the platform can fetch its metadata,
files and linked data automatically.

## How it fits together

There is no central "repository registry" in code. A repository is the combination
of three independent pieces that `Episciences_Repositories` wires together at
runtime:

1. **A row in the `metadata_sources` table** — the repository's id, label, type,
   and the URL templates used to build links to it (OAI base URL, document page,
   PDF, API). Loaded once per request into `Zend_Registry::get('metadataSources')`
   by `Episciences_Paper_MetaDataSourcesManager::all()`
   (`application/Bootstrap.php::_initModule()`), and read everywhere through
   `Episciences_Repositories::getRepositories()`.
2. **A hooks class** — a plain static class the platform looks up **by naming
   convention**, not by explicit registration. Given a `repoId`,
   `Episciences_Repositories::hasHook()` derives the expected class name from the
   repository's `name` (or its `type`, for Dataverse/DSpace — see
   [Shared hook classes](#shared-hook-classes-dataverse--dspace) below) and simply
   checks whether that class exists:

   ```php
   // Episciences_Repositories::makeHookClassNameByRepoId()
   $label = self::getLabel($repoId);                 // e.g. "Cryptology ePrint"
   $label = str_replace(' ', '', $label);             // "CryptologyePrint"
   $className = 'Episciences_Repositories_' . ucfirst($label) . '_Hooks';
   // => Episciences_Repositories_CryptologyePrint_Hooks
   ```

   Thanks to the ZF1 autoloader, that class name maps directly to a file path:
   underscores become directory separators, so the class above lives at
   `library/Episciences/Repositories/CryptologyePrint/Hooks.php`. **The label in
   the database and the class/directory name must match exactly** (once spaces are
   stripped and the first letter is upper-cased) or the hooks class is silently
   never found.
3. **Capability interfaces** the hooks class opts into — see
   [Capability interfaces](#capability-interfaces). These are what the rest of the
   codebase actually asks about; nothing should ever branch on "does this
   repository have a hooks class" (see [Common pitfall](#common-pitfall-hashook-is-not-a-capability)).

There is no other list to update: no switch statement, no `RepoType` enum, no
front-end mapping. Once the database row and the hooks class exist, the repository
appears in the submission form and every consumer that calls
`Episciences_Repositories::callHook(...)` or the capability methods picks it up
automatically.

## Metadata Formats & Ingestion Pipeline

### 1. Ingestion Pipeline & Canonical Storage

Regardless of the external source's protocol or native serialization (REST JSON,
OpenAIRE XML, DataCite XML, or JATS XML), Episciences uses a two-tier storage model:

1. **Canonical Dublin Core XML (`PAPERS.RECORD`)**:
   Episciences normalizes the primary record into standard OAI Dublin Core XML
   (`<record><header>...</header><metadata><oai_dc:dc>...</oai_dc:dc></metadata></record>`).
   Every consumer across the platform — including document view pages, XSLT
   transforms (`Paper::getXslt()`), TEI exports, and the platform's own OAI-PMH
   server — relies on this normalized XML structure.

   When fetching from non-DC sources, the hooks class compiles Dublin Core XML
   using `Episciences_Repositories_Common::toDublinCore()` or `assembleData()`.

2. **Structured Relational Enrichments**:
   Metadata that exceeds basic Dublin Core (rich author affiliations, ORCIDs,
   licensing terms, cited bibliographic references, linked datasets, and downloadable
   files) is extracted by the hooks class into an `$enrichment` array structure and
   persisted into dedicated relational tables:
   - `PAPER_AUTHORS` (via `Episciences_Paper_AuthorsManager`): names, affiliations,
     normalized ORCIDs.
   - `PAPER_FILES` (via `Episciences_Paper_FilesManager`): file name, MIME type,
     file size, checksum, and download URL.
   - `PAPER_DATASETS` (via `Episciences_Submit::processDatasets()`): related DOIs/handles,
     relation types (`IsSupplementTo`, `References`, etc.).
   - `PAPER_CITATIONS`: parsed bibliographic reference strings from article reference lists.

### 2. Supported Metadata Formats

| Format / Schema | Namespace / MIME | Used By / Ingested Via | Key Elements Extracted | Episciences Processing Helpers |
|---|---|---|---|---|
| **Dublin Core (`oai_dc`)** | `http://www.openarchives.org/OAI/2.0/oai_dc/` | Generic OAI-PMH, EPrints, HAL, arXiv | `dc:title`, `dc:creator`, `dc:subject`, `dc:description`, `dc:date`, `dc:type`, `dc:identifier`, `dc:rights`, `dc:source` | `Episciences_Oai_Client`, `DataSanitizerInterface::hookCleanXMLRecordInput()` |
| **OpenAIRE v4 (`oai_openaire`)** | Combination of DataCite Kernel 4 & OpenAIRE namespaces | DSpace (v7+) OAI-PMH | `datacite:titles`, `datacite:creators` (names, ORCID, affiliations), `datacite:subjects`, `oaire:resourceType`, `datacite:rights` | `Episciences_Repositories_Common::extractPersons()`, `toDublinCore()` |
| **DataCite XML (`oai_datacite`)** | `http://datacite.org/schema/kernel-3` or `kernel-4` | ARCHE OAI-PMH, DataCite providers | `titles` (multilingual), `creators` & `contributors` (names, affiliations, nameIdentifiers), `subjects`, `descriptions`, `rightsList`, `relatedIdentifiers` | `extractPersons()`, `extractMultilingualContent()`, `extractRelatedIdentifiersFromMetadata()` |
| **JATS XML** | NLM / ISO JATS Journal Archiving and Interchange Tag Suite | bioRxiv, medRxiv (secondary fetch) | `front/article-meta` (`contrib-group` with authors, affiliations, ORCIDs; `permissions`/`license`; `kwd-group`), `back/ref-list` (structured citations: `article-title`, `authorsStr`, `fpage`, `lpage`) | `Episciences_Repositories_BioMedRxiv::enrichmentFromJatsXmlProcess()`, `referencesProcess()`, `formatReferences()` |
| **InvenioRDM JSON** | `application/json` | Zenodo REST API | `metadata` (title, publication_date, description, creators, keywords, license, access_right), `files.entries` (key, size, checksum, links.self), `conceptdoi` | `Episciences_Repositories_Zenodo_Hooks::hookApiRecords()`, `toDublinCore()` |
| **Dataverse JSON** | `application/json` | Dataverse Native REST API | `metadataBlocks.citation.fields` (title, author, dsDescription, subject, keyword...), `files` (dataFile.id, filename, filesize, checksum, contentType) | `Episciences_Repositories_Dataverse_Hooks::hookApiRecords()`, `toDublinCore()` |
| **bioRxiv / medRxiv API JSON** | `application/json` | Cold Spring Harbor REST API | Collection of versions, DOI, server, authors, title, type, category, date, license | `Episciences_Repositories_BioMedRxiv::hookApiRecords()` |
| **Scholexplorer JSON** | `application/json` | ScholeXplorer API (`metadata_sources.api_url`) | Links between literature and research data (source, target, relationship) | `Episciences_Submit::datasetsProcessing()` (generic fallback for repositories without `LinkedDataEnrichmentInterface`) |

### 3. Supported Repository Platforms & APIs

| Platform | Type in DB (`metadata_sources`) | Ingestion Protocol & APIs | Consumed Formats | Extracted Enrichments & Capabilities | Existing Hook Implementation |
|---|---|---|---|---|---|
| **DSpace (v7+)** | `dspace` | OAI-PMH (`GetRecord`) + DSpace 7 REST API (`/server/api/core/bitstreams/{uuid}`) | `oai_openaire` (OpenAIRE XML) + DSpace JSON | Authors with ORCID & affiliations, mirrored files with MD5/SHA checksums (`FilesEnrichmentInterface`), Handle cleaning (`InputSanitizerInterface`), stable concept identifier (`ConceptIdentifierInterface`) | `Episciences_Repositories_Dspace_Hooks` (shared by all DSpace instances) |
| **Dataverse** | `dataverse` | Dataverse Native REST API v1 (`/api/v1/datasets/:persistentId/?persistentId=...`) | Dataverse JSON (Citation metadataBlock & files) | Authors with affiliations, license, mirrored files with checksums (`FilesEnrichmentInterface`), persistent identifier cleaning (`InputSanitizerInterface`), minor/major version management | `Episciences_Repositories_Dataverse_Hooks` (shared by all Dataverse instances) |
| **InvenioRDM / Zenodo** | `repository` | Zenodo / InvenioRDM REST API v1 (`/api/records/{id}`) | InvenioRDM JSON | Authors with ORCIDs, license (Open Access check), mirrored files (`FilesEnrichmentInterface`), related datasets (`LinkedDataEnrichmentInterface`), concept DOI (`ConceptIdentifierInterface`), XML sanitization (`DataSanitizerInterface`) | `Episciences_Repositories_Zenodo_Hooks` |
| **EPrints** | `repository` | OAI-PMH 2.0 (`GetRecord`) | `oai_dc` (Dublin Core XML) | Resource types, mirrored PDF file (`FilesEnrichmentInterface`), identifier URL cleaning (`InputSanitizerInterface`), stable identifier & timestamped versioning (`ConceptIdentifierInterface`) | `Episciences_Repositories_CryptologyePrint_Hooks` (IACR Cryptology ePrint Archive) |
| **bioRxiv / medRxiv** | `repository` | CSHL REST API (`/details/...`) + HTTP retrieval of JATS XML | API JSON + JATS XML | Authors with institutional affiliations and ORCIDs, Creative Commons license detection, bibliographic references and citations, keywords, version tracking | `Episciences_Repositories_BioRxiv_Hooks` / `Episciences_Repositories_MedRxiv_Hooks` (extends `Episciences_Repositories_BioMedRxiv`) |
| **HAL** | `repository` | Generic OAI-PMH 2.0 (`https://api.archives-ouvertes.fr/oai/hal/`) | `oai_dc` (Dublin Core XML) | XML sanitation stripping non-abstract audience notes (`DataSanitizerInterface::hookCleanXMLRecordInput`), direct PDF link via `paper_url` | `Episciences_Repositories_HAL_Hooks` |
| **arXiv** | `repository` | Generic OAI-PMH 2.0 (`https://oaipmh.arxiv.org/oai`) | `oai_dc` (Dublin Core XML) | XML and metadata sanitation keeping only primary abstract (`hookFilterMetadata`, `hookCleanXMLRecordInput`), direct PDF link via `paper_url` | `Episciences_Repositories_ArXiv_Hooks` |
| **ARCHE (ACDH-CH)** | `repository` | OAI-PMH 2.0 (`https://arche.acdh.oeaw.ac.at/oaipmh/`) + ACDH-CH REST API | `oai_datacite` (DataCite Kernel 3 XML) | Multilingual titles & descriptions (`extractMultilingualContent`), authors (`extractPersons`), license, related dataset identifiers (`LinkedDataEnrichmentInterface`) | `Episciences_Repositories_ARCHE_Hooks` |
| **Generic OAI-PMH Archive** | `repository` | Standard OAI-PMH 2.0 (`metadata_sources.base_url`) | `oai_dc` (Dublin Core XML) | Standard Dublin Core XML directly into `PAPERS.RECORD`, direct PDF linking via `metadata_sources.paper_url` | No custom hook needed (or minimal `CommonHooksInterface` returning `[]`) |

## Checklist

- [ ] Insert the repository into `metadata_sources` (new date-prefixed SQL file
      under `src/mysql/`, e.g. `YYYY-MM-DD-<description>.sql`), picking a `type`
      of `repository` (default), `dataverse` or `dspace`. Update the dev seed dump
      in `src/mysql/docker/episciences/dev-episciences.sql` if applicable.
- [ ] Add a `public const <NAME>_REPO_ID` to `Episciences_Repositories` if other
      code will need to reference this repository by id.
- [ ] Create `library/Episciences/Repositories/<Name>/Hooks.php`, named to match
      the database `name` column exactly (spaces stripped, first letter upper-cased).
- [ ] Implement `Episciences\Repositories\CommonHooksInterface` (required — see
      [Hook reference](#hook-reference-required)).
- [ ] Implement only the optional capability interfaces this repository actually
      needs (`FilesEnrichmentInterface`, `LinkedDataEnrichmentInterface`,
      `InputSanitizerInterface`, `DataSanitizerInterface`,
      `ConceptIdentifierInterface`) — see
      [Capability interfaces](#capability-interfaces). Do not implement one "just
      in case"; an unused capability flag changes behaviour for every caller that
      asks about it.
- [ ] Add full PHPDoc iterable typing on all hook parameters and returns (e.g.
      `@param array<string, mixed> $hookParams`, `@return array<string, mixed>`)
      to ensure clean analysis at PHPStan level 6.
- [ ] If the repository needs credentials (API token, OAuth), read them through
      `config/pwd.json` (see existing entries for the pattern) — never hardcode
      secrets in the hooks class.
- [ ] Add `self::<NAME>_REPO_ID => '<example identifier>'` to
      `Episciences_Repositories::IDENTIFIER_EXEMPLES` so the submission form shows
      a placeholder.
- [ ] Write `tests/unit/library/Episciences/Repositories/Episciences_Repositories_<Name>_HooksTest.php`
      covering every public hook method (see [Testing](#testing)).
- [ ] Add the new repository to the fake sources map in
      `Episciences_Repositories_CapabilitiesTest::setUpBeforeClass()` and assert
      its capabilities across all providers (`hookClassProvider`,
      `filesEnrichmentProvider`, `linkedDataEnrichmentProvider`,
      `ownEnrichmentProvider`, `conceptIdentifierProvider`, `versionRequiredProvider`).
- [ ] Extend `tests/unit/library/Episciences/paper/Episciences_Paper_MainPaperUrlTest.php`:
      add the repository to `setUpBeforeClass()`, and test `hasFilesEnrichment()`,
      direct PDF URL resolution in `getMainPaperUrl()`, or `hasConceptIdentifier()`
      / `setConcept_identifier()` if applicable.
- [ ] Run `make phpstan LEVEL=6 TARGET=library/Episciences/Repositories/<Name>`
      — new files must be clean at level 6.
- [ ] Run PHPUnit inside the container (e.g.
      `docker compose exec -u www-data -w /var/www/htdocs php-fpm ./vendor/bin/phpunit --no-coverage tests/unit/library/Episciences/Repositories/`
      or `make test-php`) and confirm all repository tests pass.
- [ ] Add an entry under `### Added` in `CHANGELOG.md`.
- [ ] Manually submit a real paper from the new repository in a dev environment
      and confirm: metadata retrieval, file download link (or mirrored file, if
      `FilesEnrichmentInterface` is implemented), and the version field's
      required/optional behaviour on the submission form.

## Hook reference (required)

Every hooks class **must** implement `Episciences\Repositories\CommonHooksInterface`:

| Hook | Called from | Purpose | Expected return |
|---|---|---|---|
| `hookApiRecords(array $hookParams)` | `Episciences_Submit::getDoc()`, `AdministratepaperController` | Fetch the record for `$hookParams['identifier']` / `['repoId']` / `['version']`, either from a REST API or by returning `[]` to fall back to the platform's generic OAI-PMH retrieval. | `[]` to use the generic OAI path, or an array describing the record (may include `Episciences_Repositories_Common::CONCEPT_IDENTIFIER_KEY`, `UPDATE_DATETIME`, etc. depending on what downstream hooks expect). |
| `hookIsRequiredVersion()` | `Episciences_Repositories::isVersionRequired()` (used by the submission form and `/submit/ajaxhashook`) | Declare whether a version number is mandatory when submitting from this repository. | `['result' => bool]`, or `[]` to keep the historical default (`true`, version required). |
| `hookIsIdentifierCommonToAllVersions()` | `Episciences_Submit` (new-version form) | Declare whether the identifier field must be re-entered when submitting a new version, or stays the one used at first submission. | `['result' => bool]`, or `[]` to keep the default (`true`). |

A repository with no special behaviour for these three (e.g. arXiv, which is still
fetched through the generic OAI path) simply returns `[]`/`[]`/`[]` — see
`Episciences_Repositories_ArXiv_Hooks` for a minimal, fully documented example.

## Capability interfaces

These are **opt-in**. Implement only what the repository actually does; every one
of them is checked independently by `Episciences_Repositories`, and callers key
real behaviour off the answer.

| Interface | Method(s) to implement | Grants | Checked via |
|---|---|---|---|
| `Episciences\Repositories\InputSanitizerInterface` | `hookCleanIdentifiers(array $hookParams)`, `hookVersion(array $hookParams)` | Identifier normalization before lookup, and version extraction/rewriting from the fetched record. | Called unconditionally by `Episciences_Submit::getDoc()` / `cleanIdentifier()`; return `[]` for a no-op. |
| `Episciences\Repositories\FilesEnrichmentInterface` | `hookFilesProcessing(array $hookParams)` | The repository mirrors its files into the `PAPER_FILES` table (typically via `Episciences_Paper_FilesManager::insert()`), instead of only linking out to the repository's own PDF. | `Episciences_Repositories::hasFilesEnrichment($repoId)`. Drives `Paper::getMainPaperUrl()` and the "Download the article" button. |
| `Episciences\Repositories\LinkedDataEnrichmentInterface` | `hookLinkedDataProcessing(array $hookParams)` | The repository resolves its own linked datasets, instead of relying on the platform's generic `Episciences_Submit::datasetsProcessing()` (which needs a `metadata_sources.api_url` and a Scholexplorer-shaped response). | `Episciences_Repositories::hasLinkedDataEnrichment($repoId)`. |
| `Episciences\Repositories\DataSanitizerInterface` | `hookCleanXMLRecordInput(array $input)` | Strip or rewrite XML nodes from the raw OAI record **before** it is persisted to `PAPERS.RECORD`, so both `Paper::getMetadata()` and the XSLT render path (`Paper::getXslt()`) stay consistent. | Called unconditionally where the raw record is ingested; return `$input` unchanged for a no-op. |
| `Episciences\Repositories\ConceptIdentifierInterface` | *(marker only, no method)* | The repository exposes a single stable identifier shared by every version of a record (Zenodo's concept DOI, Cryptology ePrint's and DSpace's version-independent identifier). Set it via `Episciences_Repositories_Common::CONCEPT_IDENTIFIER_KEY` in `hookApiRecords()`. | `Episciences_Repositories::hasConceptIdentifier($repoId)`. Required before `Episciences_Paper::setConcept_identifier()` will accept a non-null value for that repository. |

`Episciences_Repositories::handlesOwnEnrichment($repoId)` is a convenience OR of
`hasFilesEnrichment()` and `hasLinkedDataEnrichment()` — a repository implementing
either is excluded from the generic `datasetsProcessing()` pass.

A repository can also declare **extra, repository-specific hooks** beyond the
common set by defining its own interface next to its hooks class — see
`Episciences\Repositories\Zenodo\HooksInterface` (`hookIsOpenAccessRight`,
`hookGetConceptIdentifierFromRecord`, `hookConceptIdentifier`) for the pattern.
Only do this if the platform has a concrete caller for the extra hook; an
interface with no caller is dead weight.

## Helpers

`Episciences_Repositories_Common` (`library/Episciences/Repositories/Common.php`)
has most of what a `hookApiRecords()`/enrichment implementation needs, so check it
before writing new XML/OAI parsing code:

- `getRecord()` — perform the OAI-PMH `GetRecord` call.
- `extractPersons()`, `extractMultilingualContent()`, `extractRelatedIdentifiersFromMetadata()` — common DataCite/Dublin Core XML extraction.
- `toDublinCore()`, `assembleData()` — build the record array the rest of the platform expects.
- `safeDateFormat()`, `replaceYMDHMSWithTimestamp()`, `getVersionFromIdentifier()`, `getConceptIdentifierFromString()` — small formatting utilities repeated across existing hooks classes.

## Shared hook classes (Dataverse & DSpace)

Dataverse and DSpace are *types*, not single repositories: a journal can have
several `metadata_sources` rows with `type = 'dataverse'` (or `'dspace'`), each
pointing at a different instance. `makeHookClassNameByRepoId()` special-cases
these two types to always resolve to `Episciences_Repositories_Dataverse_Hooks` /
`Episciences_Repositories_Dspace_Hooks`, regardless of the row's `name`. If you are
adding a new instance of an existing type, you only need the new `metadata_sources`
row — do not create another hooks class. If you are adding a genuinely new type
that several journals will instantiate multiple times, follow the same pattern:
special-case it in `makeHookClassNameByRepoId()`/`isDataverse()`/`isDspace()`-style
helpers rather than deriving the class name from `name`.

## Testing

Follow the existing per-repository test files
(`tests/unit/library/Episciences/Repositories/Episciences_Repositories_<Name>_HooksTest.php`)
as a template — HAL, ARCHE, Dataverse, DSpace, Zenodo and Cryptology ePrint all
have one. They are DB-free and network-free: mock `hookParams`, feed canned XML/JSON
fixtures, and assert on the returned array shape.

Two shared suites also need extending whenever capabilities change:

- `Episciences_Repositories_CapabilitiesTest` (`tests/unit/library/Episciences/Repositories/Episciences_Repositories_CapabilitiesTest.php`)
  — add the new repository to `setUpBeforeClass()`'s fake sources map (label matters:
  it drives hook class resolution exactly like production), then extend each relevant data
  provider:
  - `hookClassProvider` (sanity check: label resolves to hook class)
  - `filesEnrichmentProvider` (mirrors files to `PAPER_FILES`)
  - `linkedDataEnrichmentProvider` (resolves datasets itself)
  - `ownEnrichmentProvider` (bypasses generic `datasetsProcessing`)
  - `conceptIdentifierProvider` (stable cross-version identifier)
  - `versionRequiredProvider` (mandatory vs optional version)
- `Episciences_Paper_MainPaperUrlTest` (`tests/unit/library/Episciences/paper/Episciences_Paper_MainPaperUrlTest.php`
  — note lowercase `paper/`) — add the repository to `setUpBeforeClass()`'s fake sources,
  and add dedicated assertions to verify:
  - `hasFilesEnrichment()` behaviour
  - `getMainPaperUrl()` resolution (especially for repositories with direct external PDF links)
  - `hasConceptIdentifier()` / `setConcept_identifier()` if `ConceptIdentifierInterface` is implemented.

`Episciences_Repositories_CapabilityDeclarationTest` needs no manual test cases: it
discovers `library/Episciences/Repositories/*/Hooks.php` automatically and fails if a
hooks class declares a capability interface it does not implement, or implements one
it does not declare (including writing `CONCEPT_IDENTIFIER_KEY` without
`ConceptIdentifierInterface`). Note: update its `MINIMUM_HOOK_CLASSES` constant if
needed to account for the new class.

## Common pitfall: `hasHook()` is not a capability

`Episciences_Repositories::hasHook($repoId)` only answers "does a hooks class
exist for this repository", nothing more. It says nothing about what that class
implements. Historically, code branched directly on `hasHook`/`$paper->hasHook`
to decide things like "does this repository mirror files into `PAPER_FILES`" or
"is a version required" — so giving arXiv and HAL a hooks class purely for
metadata cleanup (`hookFilterMetadata`, `hookCleanXMLRecordInput`) silently broke
their article download, their required-version field and their generic dataset
enrichment, because they now "had a hook".

`Episciences_Paper::$hasHook` is `@deprecated` for exactly this reason. **Never
introduce a new `hasHook`-driven branch.** Always ask a specific capability
(`hasFilesEnrichment()`, `hasLinkedDataEnrichment()`, `hasConceptIdentifier()`,
`isVersionRequired()`) or call the hook itself through
`Episciences_Repositories::callHook()` and check its return value.
