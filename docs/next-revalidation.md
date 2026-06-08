# Next.js On-Demand Cache Revalidation

Episciences uses [Next.js ISR](https://nextjs.org/docs/app/building-your-application/data-fetching/incremental-static-regeneration). When data changes in the ZF1 backend, the relevant Next.js cache entries must be invalidated immediately rather than waiting for TTL expiry. This is achieved via an authenticated HTTP `POST` to the Next.js `/api/revalidate` endpoint.

---

## Architecture

```
ZF1 Model / Controller
        │
        ▼
RevalidationService::enqueueTag()   ←── async (no web-request impact)
        │
        ▼
queue_messages table
        │
        ▼   (cron — scripts/NextRevalidationQueue.php)
POST /api/revalidate  ──►  Next.js  ──►  revalidateTag()
```

Two strategies are available:

| Method | When to use |
|--------|-------------|
| `enqueueTag()` / `enqueueTags()` | Default for all model hooks — async, zero web-request impact |
| `revalidateOrEnqueue()` | Critical paths (editorial pages) where near-instant invalidation is preferred; falls back to queue on HTTP error or non-200 |

The feature is entirely guarded by the `EPISCIENCES_ENABLE_NEXT_FRONT` constant. All service methods are no-ops when it is not defined or falsy.

---

## Configuration

All settings live in `config/pwd.json` (global) and `data/{rvcode}/config/pwd.json` (per-journal).

### Global (`config/pwd.json`)

```json
{
  "NEXT_BASE_URL": "https://episciences.org",
  "NEXT_REVALIDATION_SECRET": "global_fallback_token",
  "EPISCIENCES_ENABLE_NEXT_FRONT": true
}
```

| Key | Description |
|-----|-------------|
| `NEXT_BASE_URL` | Base URL of the Next.js application. The revalidation endpoint is `{NEXT_BASE_URL}/api/revalidate`. |
| `NEXT_REVALIDATION_SECRET` | Global fallback token used when no per-journal token is found. |
| `EPISCIENCES_ENABLE_NEXT_FRONT` | Master switch. Set to `true` to enable all revalidation hooks. |

### Per-journal (`data/{rvcode}/config/pwd.json`)

```json
{
  "NEXT_REVALIDATION_TOKEN": "journal_specific_token"
}
```

**Token resolution order:**
1. `NEXT_REVALIDATION_TOKEN` from `data/{rvcode}/config/pwd.json`
2. Fall back to global `NEXT_REVALIDATION_SECRET`

Never commit tokens to version control — they must be set only in `config/pwd.json` and per-journal `pwd.json` files, which are excluded from the repository.

---

## HTTP Contract

```
POST {NEXT_BASE_URL}/api/revalidate
Content-Type: application/json
x-episciences-token: {token}

{
  "journalId": "{rvcode}",
  "tag":       "{cache-tag}"
}
```

One `POST` is sent per tag. The endpoint does not accept multiple tags in a single call.

| HTTP status | Meaning | Action |
|-------------|---------|--------|
| `200` | Cache revalidated | Message deleted from queue |
| `4xx` | Bad token / IP / payload | Logged, message left in queue |
| `5xx` / timeout | Server/network error | Logged, message left in queue |

---

## Cache Tag Reference

### Articles

| ZF1 event | Tags sent |
|-----------|-----------|
| Article metadata updated (title, abstract, authors, DOI) | `article-{id}` |
| Article moved to any "Accepted" status | `article-{id}`, `articles-accepted-{rvcode}` |
| Article published | `article-{id}`, `articles-{rvcode}`, `articles-accepted-{rvcode}`, `sitemap-{rvcode}` |
| Article deleted or removed | `article-{id}`, `articles-{rvcode}`, `sitemap-{rvcode}` |

Hooks: `Episciences_Paper::enqueueNextRevalidationForStatus()` (called on `CODE_STATUS` log) and `Episciences_Paper::save()` (UPDATE path for metadata).

### Volumes

| ZF1 event | Tags sent |
|-----------|-----------|
| Volume metadata updated (title, description, cover) | `volume-{id}` |
| Article added to or removed from a volume | `volume-{id}`, `volumes-{rvcode}` |
| New volume created | `volumes-{rvcode}`, `sitemap-{rvcode}` |
| Volume deleted | `volumes-{rvcode}` |
| Volume display order changed (drag-and-drop) | `volumes-{rvcode}` |

Hooks: `Episciences_Volume::save()`, `Episciences_VolumesManager::delete()`, `Episciences_Volume_PapersManager::updatePaperVolumes()` / `deletePaperVolume()`, `Episciences_VolumesAndSectionsManager::sort()`.

### Sections

| ZF1 event | Tags sent |
|-----------|-----------|
| Section metadata updated (title, description) | `section-{id}-{rvcode}`, `sections-{rvcode}` |
| Article assigned to or removed from a section | `section-articles-{id}-{rvcode}` (+ old section tag when moving) |
| New section created | `sections-{rvcode}` |
| Section deleted | `sections-{rvcode}` |
| Section display order changed (drag-and-drop) | `sections-{rvcode}` |

Hooks: `Episciences_Section::save()`, `Episciences_SectionsManager::delete()`, `AdministratepaperController::savesectionAction()`, `Episciences_VolumesAndSectionsManager::sort()`.

### News

| ZF1 event | Tags sent |
|-----------|-----------|
| News item created, updated, or deleted | `news-{rvcode}` |

Hook: `Episciences_JournalNews::insert()`, `update()`, `deleteByLegacyId()`.

### Editorial Board

| ZF1 event | Tags sent |
|-----------|-----------|
| Board member added, updated (role), or removed | `members-{rvcode}` |

Roles tracked: `ROLE_EDITORIAL_BOARD`, `ROLE_TECHNICAL_BOARD`, `ROLE_SCIENTIFIC_ADVISORY_BOARD`, `ROLE_ADVISORY_BOARD`.

Hook: `Episciences_User::saveUserRoles()` and `saveNewRoles()`.

### Editorial Pages

| Page code | Tag sent |
|-----------|----------|
| `about` | `about-{rvcode}` |
| `indexing` | `indexing-{rvcode}` |
| `indexation-metrics` | `indexation-{rvcode}` |
| `credits` | `credits-{rvcode}` |
| `for-reviewers` | `for-reviewers-{rvcode}` |
| `for-conference-organisers` | `for-conference-organisers-{rvcode}` |
| `proposing-special-issues` | `proposing-special-issues-{rvcode}` |
| `acknowledgements` | `acknowledgements-{rvcode}` |
| Any other page (`X`) | `page-X-{rvcode}` |

Pages with codes `editorial-workflow`, `ethical-charter`, and `prepare-submission` are skipped — they have no corresponding Next.js fetch tag and refresh only on TTL expiry.

These use `revalidateOrEnqueue()` (immediate HTTP POST with queue fallback) because editorial pages are typically edited and reviewed live.

Hook: `Episciences_Page_Manager::add()`, `update()`, `delete()`.

### Statistics

Statistics tags (`stats-{rvcode}`, `statistics-{rvcode}`) are not wired to automatic hooks — the data behind them is updated by batch cron jobs, and TTL-based expiry is acceptable for stats pages. Use the console command for a manual forced refresh if needed.

### Emergency — Broad Invalidation

Omit the `{rvcode}` suffix to affect every journal. Use only for emergencies (e.g. template-level changes deployed to all journals at once).

| Tag | Effect |
|-----|--------|
| `articles` | All articles, all journals |
| `articles-accepted` | All accepted lists |
| `volumes` | All volumes |
| `sections` | All sections |
| `news` | All news |
| `members` | All member lists |
| `pages` | All editorial pages |
| `sitemap` | All sitemaps |

---

## Queue Consumer (Cron)

`scripts/NextRevalidationQueue.php` reads `TYPE_NEXT_REVALIDATION` messages from `queue_messages` and sends the corresponding HTTP POST to Next.js. Successfully delivered messages are deleted; failed ones remain in the queue for retry on the next run.

Message timeout: `3600 s` — the cron must run at least once per hour to avoid expiry.

Recommended cron schedule:

```
*/5 * * * *  www-data  php /var/www/htdocs/scripts/NextRevalidationQueue.php
```

---

## Console Command

The `next:revalidate-cache` command sends an immediate HTTP POST, bypassing the queue. Use it for urgent manual revalidation or smoke testing.

```bash
php scripts/console.php next:revalidate-cache <rvcode> <tag>
```

| Argument | Description |
|----------|-------------|
| `rvcode` | Journal code (e.g. `epijinfo`) |
| `tag` | Cache tag to invalidate (e.g. `article-42`) |

Returns exit code `0` on HTTP 200, `1` otherwise.

---

## Smoke Test

```bash
# Immediate revalidation via console (verifies token and endpoint)
php scripts/console.php next:revalidate-cache epijinfo news-epijinfo

# Direct curl (useful to test credentials independently of PHP)
curl -s -X POST https://epijinfo.episciences.org/api/revalidate \
  -H 'Content-Type: application/json' \
  -H 'x-episciences-token: YOUR_TOKEN' \
  -d '{"journalId":"epijinfo","tag":"news-epijinfo"}'
# Expected: {"revalidated":true,"now":...,"journalId":"epijinfo","tag":"news-epijinfo"}
```

---

## Key Files

| File | Role |
|------|------|
| `library/Episciences/Next/RevalidationService.php` | Static service: `enqueueTag()`, `enqueueTags()`, `revalidateOrEnqueue()` |
| `scripts/NextRevalidationQueue.php` | Cron consumer — reads queue and POSTs to Next.js |
| `scripts/RevalidateNextCacheCommand.php` | Symfony Console command `next:revalidate-cache` |
| `library/Episciences/QueueMessageManager.php` | Defines `TYPE_NEXT_REVALIDATION` and `TYPE_NEXT_REVALIDATION_TIMEOUT` |