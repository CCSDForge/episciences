# Metadata Enrichment Pipeline

Episciences enriches published papers with external scholarly metadata harvested from authoritative third-party academic APIs (OpenAIRE, Crossref, OpenCitations, OpenAlex, zbMATH Open, Scholexplorer, and Software Heritage).

This pipeline runs asynchronously (typically via automated nightly cron jobs or manual CLI execution) to supplement author-provided submission metadata with persistent identifiers, citation metrics, funding acknowledgments, classifications, dataset links, and peer review notices.

---

## Architecture Overview

```
                      ┌──────────────────────────────────────────────┐
                      │              Published Paper                 │
                      │             (PAPERS.DOCUMENT)                │
                      └──────────────────────┬───────────────────────┘
                                             │
             ┌───────────────────────────────┴───────────────────────────────┐
             │                                                               │
             ▼                                                               ▼
   External Scholarly APIs                                        Repository APIs
   ┌─────────────────────────────┐                                ┌─────────────────────────────┐
   │ • OpenAIRE Graph v3         │                                │ • HAL TEI XML               │
   │ • Crossref & OpenCitations  │                                │ • arXiv API                 │
   │ • OpenAlex                  │                                │ • Zenodo & BAOBAB           │
   │ • zbMATH Open               │                                └──────────────┬──────────────┘
   │ • Scholexplorer v3          │                                               │
   └──────────────┬──────────────┘                                               │
                  │                                                              │
                  ▼                                                              │
   ┌─────────────────────────────┐                                               │
   │ PSR-6 Cache Pools (cache/)  │                                               │
   │ • openAireResearchGraph     │                                               │
   │ • enrichmentAuthors         │                                               │
   │ • enrichmentFunding         │                                               │
   │ • scholexplorerLinkData     │                                               │
   └──────────────┬──────────────┘                                               │
                  │                                                              │
                  └──────────────────────────────┬───────────────────────────────┘
                                                 │
                                                 ▼
                               ┌───────────────────────────────────┐
                               │       Database Enrichment         │
                               │  • PAPERS.DOCUMENT (JSON cache)   │
                               │  • PAPERS.RECORD (OAI-DC / TEI)   │
                               │  • PAPER_CITATIONS / PAPER_LINK   │
                               └─────────────────┬─────────────────┘
                                                 │
                                                 ▼
                               ┌───────────────────────────────────┐
                               │ Solr Reindexing & Cache Eviction  │
                               │ (solr:index / next:revalidate)    │
                               └───────────────────────────────────┘
```

---

## Enrichment Channels & Commands

All enrichment commands live in `scripts/` and run via `php scripts/console.php`. Every command supports:
- `--dry-run`: Preview updates without writing to the database.
- `--rvcode=<code>`: Restrict processing to a single journal.

| Data Domain | Console Command | External Service / API | Stored In |
|-------------|-----------------|------------------------|-----------|
| **Bibliographic References** | [`enrichment:extract-biblio-refs`](./console-commands.md#enrichmentextract-biblio-refs) | [Biblioref API](./biblioref-extraction.md) (GROBID) | `DOCUMENT.bibliographical_references` |
| **Author ORCID & Affiliations** | [`enrichment:creators`](./console-commands.md#enrichmentcreators) | OpenAIRE Graph API v3 & HAL TEI | `DOCUMENT.authors`, `RECORD` |
| **Funding & Grants** | [`enrichment:funding`](./console-commands.md#enrichmentfunding) | OpenAIRE Graph API v3 & HAL | `DOCUMENT.funding` |
| **Citation Counts & Metrics** | [`enrichment:citations`](./console-commands.md#enrichmentcitations) | OpenCitations v2, OpenAlex, Crossref | `PAPER_CITATIONS`, `DOCUMENT.citations` |
| **Dataset & Software Links** | [`enrichment:links`](./console-commands.md#enrichmentlinks) | Scholexplorer API v3, Software Heritage | `PAPER_LINK`, `DOCUMENT.datasets` |
| **Economics Classifications** | [`enrichment:classifications-jel`](./console-commands.md#enrichmentclassifications-jel) | OpenAIRE Graph API v3 | `DOCUMENT.classifications.jel` |
| **Math Classifications** | [`enrichment:classifications-msc`](./console-commands.md#enrichmentclassifications-msc) | zbMATH Open API | `DOCUMENT.classifications.msc` |
| **Peer Review Notices** | [`enrichment:zb-reviews`](./console-commands.md#enrichmentzb-reviews) | zbMATH Open API | `DOCUMENT.reviews.zbmath` |
| **SPDX Licences** | [`enrichment:licences`](./console-commands.md#enrichmentlicences) | Source Repository APIs | `PAPERS.DOCUMENT`, `PAPERS.LICENCE` |

---

## Service Integrations & Protocols

### 1. OpenAIRE Research Graph API v3
- **Class:** `Episciences\Api\OpenAireApiClient` / `Episciences_OpenAireResearchGraphTools`
- **Protocol:** REST API with OAuth2 / AAI client-credentials flow (`OPENAIRE.CLIENT_ID`, `OPENAIRE.CLIENT_SECRET`).
- **Enrichments provided:**
  - Author ORCIDs and verified ROR affiliation identifiers.
  - Research grants, project names, and funder IDs (EU Horizon, ANR, etc.).
  - JEL classification codes for economics articles.
- **Throttling:** Honors API quotas and exponential backoff on HTTP 429.

### 2. OpenCitations & OpenAlex & Crossref
- **Classes:** `Episciences_OpencitationsTools`, `Episciences_OpenalexTools`, `Episciences_CrossrefTools`
- **Protocol:** REST APIs querying by paper DOI (`doi:10.xxxx/...`).
- **OpenCitations v2:** Queries `https://api.opencitations.net/index/v2/citations/` with rate-limiting.
- **OpenAlex:** Enriches normalized citation counts, concepts, and related works.

### 3. Scholexplorer v3 & Software Heritage
- **Class:** `Episciences\Api\ScholexplorerApiClient` / `Episciences_SoftwareHeritageTools`
- **Protocol:** Scholexplorer REST API v3 with JWT authentication token flow.
- **Dataset Linking:** Identifies bilateral links between papers and research datasets (Zenodo, Dataverse, Figshare, Pangaea).
- **Software Heritage:** Generates SWH persistent identifiers (SWHIDs) for software deposits deposited via HAL.

### 4. zbMATH Open
- **Class:** `Episciences_ZbjatsTools`
- **Protocol:** REST queries using journal ISSN and paper MSC/zbMATH identifiers.
- **Enrichments:** Extracts MSC 2020 classification codes and links to published zbMATH Open expert reviews.

---

## Caching Strategy & Cache Purge

To prevent redundant API queries across repeated cron ticks or web requests, responses from external providers are cached using Symfony PSR-6 `FilesystemAdapter` pools inside `cache/`:

| Pool Name | Used By | Default TTL |
|-----------|---------|-------------|
| `openAireResearchGraph` | `enrichment:creators`, `enrichment:funding`, `enrichment:classifications-jel` | 30 days |
| `enrichmentAuthors` | `enrichment:creators` (HAL & OpenAIRE parsed authors) | 30 days |
| `enrichmentFunding` | `enrichment:funding` | 30 days |
| `scholexplorerLinkData` | `enrichment:links` | 30 days |
| `scholexplorerAuthToken` | `ScholexplorerApiClient` (JWT token) | Dynamic (from token expiry) |

### Invalidation: `enrichment:clear-cache`
When external API response formats change (e.g. migration from OpenAIRE v1 to v3), existing cache keys (`md5($doi)`) would otherwise continue returning stale schemas. Purge the cache using:

```bash
# Purge OpenAIRE cache pools
php scripts/console.php enrichment:clear-cache

# Purge Scholexplorer pools
php scripts/console.php enrichment:clear-cache --scholexplorer

# Purge all enrichment cache pools
php scripts/console.php enrichment:clear-cache --all
```

---

## Downstream Effects (Search & Frontend)

Whenever an enrichment modifies a paper's metadata in `PAPERS.DOCUMENT`:
1. **Solr Search Index:** An indexing task is automatically queued (`SolrIndexing::enqueueIndex($docid)`). The worker re-extracts the updated keywords, authors, classifications, and funding into the Solr core.
2. **Next.js Frontend:** A cache revalidation tag message is dispatched (`RevalidationService::enqueueTag('article-' . $docid)`) to invalidate the static ISR cache on the public frontend.

---

## Recommended Production Cron Schedule

Enrichment tasks are non-blocking and should run periodically during low-traffic windows:

```cron
# Pre-extract bibliographic references (GROBID / Biblioref)
30 0 * * * www-data php /var/www/htdocs/scripts/console.php enrichment:extract-biblio-refs -q

# Enrich author ORCIDs and affiliations (OpenAIRE & HAL)
0 1 * * 1 www-data php /var/www/htdocs/scripts/console.php enrichment:creators -q

# Enrich funding acknowledgments
0 2 * * 1 www-data php /var/www/htdocs/scripts/console.php enrichment:funding -q

# Refresh citations counts (OpenCitations, OpenAlex, Crossref)
0 3 * * 0 www-data php /var/www/htdocs/scripts/console.php enrichment:citations -q

# Discover zbMATH reviews & MSC classifications
0 4 * * 0 www-data php /var/www/htdocs/scripts/console.php enrichment:zb-reviews -q
30 4 * * 0 www-data php /var/www/htdocs/scripts/console.php enrichment:classifications-msc -q

# Enrich dataset links (Scholexplorer v3)
0 5 * * 0 www-data php /var/www/htdocs/scripts/console.php enrichment:links -q
```
