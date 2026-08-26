# Next.js On-Demand Cache Revalidation

Episciences uses [Next.js ISR](https://nextjs.org/docs/app/building-your-application/data-fetching/incremental-static-regeneration). When data changes in the ZF1 backend, the relevant Next.js cache entries must be invalidated immediately rather than waiting for TTL expiry. This is achieved via an authenticated HTTP `POST` to the Next.js `/api/revalidate` endpoint.

---

## Architecture

Revalidation is queued and consumed through the same Symfony Messenger + Doctrine DBAL transport as Solr indexing (see `docs/console-commands.md#messenger-queues`), on its own `next_revalidation` queue so a slow Solr document build never blocks a cheap Next.js POST.

```
ZF1 Model / Controller
        │
        ▼
RevalidationService::enqueueTag() / enqueueTags()   ←── async, zero web-request impact
        │
        ▼
messenger_messages (queue_name = 'next_revalidation')
        │
        ▼   episciences:worker --transport=next_revalidation
POST /api/revalidate  ──►  Next.js  ──►  revalidateTag()
```

The feature is entirely guarded by the `EPISCIENCES_ENABLE_NEXT_FRONT` constant, checked both at enqueue time (`RevalidationService`) and again when the worker consumes the message (`RevalidateTagMessageHandler`) — the second check only matters if the flag was switched off between the two. All revalidation methods are no-ops when the flag is not defined or falsy.

---

## Configuration

All settings live in `config/pwd.json` (global) and `data/{rvcode}/config/pwd.json` (per-journal). See `config/dist-pwd.json` for the tracked template.

### Global (`config/pwd.json`)

```json
{
  "EPISCIENCES": {
    "ENABLE_NEXT_FRONT": true
  },
  "NEXT": {
    "BASE_URL": "https://episciences.org",
    "REVALIDATION_SECRET": "global_fallback_token"
  }
}
```

| Key | Constant | Description |
|-----|----------|-------------|
| `NEXT.BASE_URL` | `NEXT_BASE_URL` | Base URL of the Next.js application. The revalidation endpoint is `{NEXT_BASE_URL}/api/revalidate`. Required for the worker and `next:revalidate-cache` to start — a missing value aborts the command rather than retrying every message. |
| `NEXT.REVALIDATION_SECRET` | `NEXT_REVALIDATION_SECRET` | Global fallback token used when no per-journal token is found. |
| `EPISCIENCES.ENABLE_NEXT_FRONT` | `EPISCIENCES_ENABLE_NEXT_FRONT` | Master switch. Set to `true` to enable all revalidation hooks. |

### Per-journal (`data/{rvcode}/config/pwd.json`)

```json
{
  "NEXT_REVALIDATION_TOKEN": "journal_specific_token"
}
```

**Token resolution order:**
1. `NEXT_REVALIDATION_TOKEN` from `data/{rvcode}/config/pwd.json`
2. Fall back to global `NEXT_REVALIDATION_SECRET`

Resolved tokens are memoized per journal for the lifetime of the worker process — a rotated token is only picked up the next time the worker recycles (bounded by its `--time-limit`, 3600s by default).

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
| `2xx` | Cache revalidated | Message acknowledged |
| `3xx` (unexpected — Guzzle already follows redirects) | `NEXT_BASE_URL` is misconfigured | Permanent failure, sent straight to `messenger_failed`, no retry |
| `408` / `425` / `429` | Transient (timeout / too early / rate-limited) | Retried with backoff |
| Other `4xx` | Bad token / IP / payload / route | Permanent failure, sent straight to `messenger_failed`, no retry |
| `5xx` | Server error | Retried with backoff |
| Network / DNS / connect timeout | Transport-level failure | Retried with backoff |

Backoff schedule: 1s → 4s → 16s → 60s (4 attempts), landing in `messenger_failed` after roughly 81s if still failing — short on purpose, since a tag revalidated 30 minutes late is worthless (the ISR TTL would have expired on its own by then). Compare with the Solr indexing queue's much longer 5s–30min backoff, appropriate there because a delayed *index* update has no equivalent TTL fallback.

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

Hook: `Episciences_Page_Manager::add()`, `update()`, `delete()` — all go through the same async `enqueueTag()` as every other hook; there is no separate immediate-POST path for pages.

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

`next:revalidate-cache` accepts more than one tag per invocation, which is the easiest way to fire off a batch of these emergency tags in one command (see below).

---

## Worker

`episciences:worker --transport=next_revalidation` continuously consumes the queue and POSTs to Next.js — see `docs/console-commands.md#messenger-queues` for the full command reference, deployment unit, and troubleshooting (`--list-failed`, `--retry`, `--list-dispatch-failures`).

Deployed as its own systemd instance / Docker Compose service, independent from the Solr indexing worker, precisely so a slow Solr document build can never delay a Next.js revalidation:

```
systemctl enable --now episciences-worker@next_revalidation
# or: docker compose up -d worker-next-revalidation
```

If `EPISCIENCES_ENABLE_NEXT_FRONT` is off when the worker starts, it still starts (a message can only exist if the flag was on when it was enqueued) but logs a warning, since a worker that's running but never finding anything to consume can otherwise look broken rather than idle.

---

## Console Command

The `next:revalidate-cache` command sends an immediate HTTP POST for one or more tags, bypassing the queue by default — use it for urgent manual revalidation or smoke testing. Pass `--queue` to enqueue instead (symmetry with `solr:index`, which enqueues by default and has a `--sync` option).

```bash
php scripts/console.php next:revalidate-cache <rvcode> <tag> [<tag> ...]
php scripts/console.php next:revalidate-cache --queue <rvcode> <tag> [<tag> ...]
```

| Argument / option | Description |
|--------------------|-------------|
| `rvcode` | Journal code (e.g. `epijinfo`) |
| `tag` | One or more cache tags to invalidate (e.g. `article-42`); see [Emergency — Broad Invalidation](#emergency--broad-invalidation) for the rvcode-less batch |
| `--queue` | Enqueue instead of POSTing immediately |

Exit code `0` if every tag revalidated successfully (or was enqueued), `1` if any tag failed.

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

End-to-end via the queue: enqueue a tag, then confirm `episciences:queue --transport=next_revalidation --stats` drops its pending count and the worker's log shows the POST — see `docs/console-commands.md#messenger-queues`.

---

## Key Files

| File | Role |
|------|------|
| `library/Episciences/Next/RevalidationService.php` | Static facade: `enqueueTag()`, `enqueueTags()`, `getPort()`/`setPort()` |
| `library/Episciences/Next/Enqueue/NextRevalidationQueuePort.php` | Builds/dedupes/dispatches `RevalidateTagMessage` |
| `library/Episciences/Next/Enqueue/DbalNextRevalidationFailureStore.php` | Producer-side dispatch-failure record (`next_revalidation_enqueue_failures`) |
| `library/Episciences/Next/Messenger/Message/RevalidateTagMessage.php` | The queued message |
| `library/Episciences/Next/Messenger/TokenResolver.php` | Per-journal token resolution (memoized) |
| `library/Episciences/Next/Messenger/Handler/RevalidateTagMessageHandler.php` | Consumes the message: POSTs and applies the HTTP taxonomy above |
| `library/Episciences/Next/Messenger/NextRevalidationTransport.php` | Transport name, retry policy |
| `scripts/Messenger/NextRevalidationProfile.php` | Wires the queue into `episciences:worker` / `episciences:queue` |
| `scripts/RevalidateNextCacheCommand.php` | Symfony Console command `next:revalidate-cache` |
