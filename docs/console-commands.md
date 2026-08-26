# Console Commands

All Episciences CLI commands are registered in `scripts/console.php` and run via:

```bash
php scripts/console.php <command> [options]
```

Use `--help` on any command to display its full usage:

```bash
php scripts/console.php <command> --help
```

---

## Quick Reference

| Command | Description |
|---------|-------------|
| [`app:generate-users`](#appgenerate-users) | Generate random test users |
| [`app:init-dev-users`](#appinit-dev-users) | Seed the dev journal with 30 predefined users |
| [`app:create-bot-user`](#appcreate-bot-user) | Create the `episciences-bot` service account |
| [`enrichment:extract-biblio-refs`](#enrichmentextract-biblio-refs) | Pre-extract bibliographic references via the Biblioref API (designed for cron) |
| [`enrichment:citations`](#enrichmentcitations) | Enrich citation metadata from OpenCitations, OpenAlex, and Crossref |
| [`enrichment:creators`](#enrichmentcreators) | Enrich author ORCID data from OpenAIRE Research Graph and HAL |
| [`enrichment:licences`](#enrichmentlicences) | Enrich licence data from repository APIs |
| [`enrichment:links`](#enrichmentlinks) | Enrich dataset links from Scholexplorer (OpenAIRE) |
| [`enrichment:funding`](#enrichmentfunding) | Enrich funding data from OpenAIRE Research Graph and HAL |
| [`enrichment:classifications-jel`](#enrichmentclassifications-jel) | Enrich JEL classification codes from OpenAIRE Research Graph |
| [`enrichment:classifications-msc`](#enrichmentclassifications-msc) | Enrich MSC 2020 classification codes from zbMath Open API |
| [`enrichment:zb-reviews`](#enrichmentzb-reviews) | Discover and store zbMATH Open reviews |
| [`sitemap:generate`](#sitemapgenerate) | Generate a sitemap for a journal |
| [`volume:merge-pdf`](#volumemerge-pdf) | Merge all volume PDFs for a journal |
| [`doaj:export-volumes`](#doajexport-volumes) | Create DOAJ XML exports for journal volumes |
| [`zbjats:zip`](#zbjatszip) | Package PDF + zbJATS XML into a ZIP archive per volume |
| [`import:sections`](#importsections) | Import journal sections from a CSV file |
| [`import:volumes`](#importvolumes) | Import journal volumes from a CSV file |
| [`import:ref-pps`](#importref-pps) | Import PPS data from a CSV file into Solr |
| [`download:ref-pps`](#downloadref-pps) | Download the PPS CSV file from IRIT |
| [`stats:import-logs`](#statsimport-logs) | Parse Apache access logs and insert article visits into `STAT_TEMP` |
| [`stats:download-kpi`](#statsdownload-kpi) | Aggregate download KPIs for all published articles and write a JSON file |
| [`stats:update-robots-list`](#statsupdate-robots-list) | Download the COUNTER Robots list for bot detection |
| [`stats:process`](#statsprocess) | Process raw visit records from `STAT_TEMP` into `PAPER_STAT` |
| [`geoip:update`](#geoipupdate) | Download or update the GeoLite2-City.mmdb database |
| [`solr:index`](#solrindex) | Enqueue (or synchronously run) Solr re-indexing for one or more papers |
| [`solr:delete`](#solrdelete) | Enqueue (or synchronously run) a Solr deletion, by DOCID or by raw query |
| [`episciences:worker`](#episciencesworker) | Continuously consume one Messenger queue (`solr_index` or `next_revalidation`) |
| [`episciences:queue`](#episciencesqueue) | Inspect and manage one Messenger queue |
| [`next:revalidate-cache`](#nextrevalidate-cache) | Immediately trigger (or enqueue) Next.js cache revalidation for a journal and one or more tags |

---

## Development

### `app:generate-users`

Generates random test users using Faker. Intended for development environments only.

```bash
php scripts/console.php app:generate-users [options]
```

| Option | Default | Description |
|--------|---------|-------------|
| `--count` / `-c` | `5` | Number of users to generate |
| `--role` / `-r` | `member` | Role to assign: `member`, `editor`, `admin`, `chiefeditor` |
| `--password` / `-p` | `password123` | Fixed password for all generated users |
| `--rvcode` | `dev` | Journal code used to assign roles |

---

### `app:init-dev-users`

Seeds the dev journal (RVID 1) with 30 users: 1 chief editor, 2 administrators, 5 editors, and 22 members. Runs automatically during `make dev-setup`.

```bash
php scripts/console.php app:init-dev-users
```

---

### `app:create-bot-user`

Creates the `episciences-bot` service account with a predefined UID and credentials. Runs automatically during `make dev-setup`.

```bash
php scripts/console.php app:create-bot-user
```

---

## Enrichment

All enrichment commands accept `--dry-run` (preview changes without writing to the database) and most accept `--rvcode` to restrict processing to a single journal.

### `enrichment:extract-biblio-refs`

Pre-extracts bibliographic references for papers by calling the Biblioref `GET /api/extract` endpoint. The API runs GROBID on the paper's PDF and caches the result server-side; already-processed papers return immediately. Skips execution entirely when `EPISCIENCES_BIBLIOREF['ENABLE']` is false.

Designed to run as a cron job so that references are ready before users request them.

**Prerequisites:** configure `EPISCIENCES_BIBLIOREF` in `config/pwd.json`:

```json
"EPISCIENCES": {
  "BIBLIOREF": {
    "URL": "https://citations.episciences.org",
    "ENABLE": true,
    "SSL_VERIFY": true,
    "TOKEN": "your-bearer-token"
  }
}
```

```bash
php scripts/console.php enrichment:extract-biblio-refs [options]
```

| Option | Description |
|--------|-------------|
| `--docid <id>` | Process only this DOCID (any status) |
| `--rvcode <code>` | Restrict processing to one journal |
| `--dry-run` | Log what would be sent without calling the API |
| `--published` | Also include `STATUS_PUBLISHED` papers (default: `STATUS_SUBMITTED` only) |
| `--accepted` | Also include `STATUS_ACCEPTED` papers (default: `STATUS_SUBMITTED` only) |
| `--api-url <url>` | Override `EPISCIENCES_BIBLIOREF[URL]` at runtime (e.g. Docker-internal address) |

By default the command targets papers with `STATUS_SUBMITTED`. Use `--published` and/or `--accepted` to broaden the scope. When `--docid` is given, no status filter is applied.

Each API call has a 360-second timeout (GROBID extraction can take several minutes). The command returns exit code 1 if any calls fail, so cron alerts fire on partial failures.

> **Docker note:** if the script runs inside a Docker container that cannot resolve the public hostname configured in `pwd.json`, use `--api-url` to point to the Docker-internal service address:
> ```bash
> php scripts/console.php enrichment:extract-biblio-refs \
>   --api-url=http://citations-php-fpm \
>   --published
> ```

Recommended cron schedule: daily (e.g. every night at 00:30).

---

### `enrichment:citations`

Enriches citation metadata for all published papers by querying OpenCitations, OpenAlex, and Crossref.

```bash
php scripts/console.php enrichment:citations [options]
```

| Option | Description |
|--------|-------------|
| `--dry-run` | Preview changes without writing to the database |
| `--rvcode <code>` | Restrict processing to one journal |

---

### `enrichment:creators`

Enriches author ORCID identifiers from the OpenAIRE Research Graph and HAL TEI metadata.

```bash
php scripts/console.php enrichment:creators [options]
```

| Option | Description |
|--------|-------------|
| `--doi <doi>` | Process a single paper by DOI |
| `--paperid <id>` | Process a single paper by paper ID |
| `--dry-run` | Preview changes without writing to the database |
| `--no-cache` | Bypass cache and fetch fresh data |
| `--rvcode <code>` | Restrict processing to one journal (ignored when `--doi` or `--paperid` is set) |

---

### `enrichment:licences`

Enriches licence data for all papers by querying the source repository APIs.

```bash
php scripts/console.php enrichment:licences [options]
```

| Option | Description |
|--------|-------------|
| `--dry-run` | Preview changes without writing to the database |
| `--rvcode <code>` | Restrict processing to one journal |

---

### `enrichment:links`

Enriches dataset and software link metadata from Scholexplorer (OpenAIRE).

```bash
php scripts/console.php enrichment:links [options]
```

| Option | Description |
|--------|-------------|
| `--dry-run` | Preview changes without writing to the database |
| `--rvcode <code>` | Restrict processing to one journal |

---

### `enrichment:funding`

Enriches funding information from the OpenAIRE Research Graph and HAL metadata.

```bash
php scripts/console.php enrichment:funding [options]
```

| Option | Description |
|--------|-------------|
| `--doi <doi>` | Process a single paper by DOI |
| `--paperid <id>` | Process a single paper by paper ID |
| `--dry-run` | Preview changes without writing to the database |
| `--no-cache` | Bypass cache and fetch fresh data |
| `--rvcode <code>` | Restrict processing to one journal (ignored when `--doi` or `--paperid` is set) |

---

### `enrichment:classifications-jel`

Enriches JEL (Journal of Economic Literature) classification codes for economics papers from the OpenAIRE Research Graph.

```bash
php scripts/console.php enrichment:classifications-jel [options]
```

| Option | Description |
|--------|-------------|
| `--dry-run` | Preview changes without writing to the database |
| `--rvcode <code>` | Restrict processing to one journal |

---

### `enrichment:classifications-msc`

Enriches MSC 2020 (Mathematics Subject Classification) codes from the zbMath Open API.

```bash
php scripts/console.php enrichment:classifications-msc [options]
```

| Option | Description |
|--------|-------------|
| `--dry-run` | Preview changes without writing to the database |
| `--rvcode <code>` | Restrict processing to one journal |

---

### `enrichment:zb-reviews`

Discovers and stores zbMATH Open peer reviews for published papers.

```bash
php scripts/console.php enrichment:zb-reviews [options]
```

| Option | Description |
|--------|-------------|
| `--dry-run` | Preview changes without writing to the database |
| `--rvcode <code>` | Restrict processing to one journal |

---

## Sitemap

### `sitemap:generate`

Generates a sitemap XML file for one journal or for all active journals (STATUS = 1).
Exactly one of `--rvcode` or `--all` must be provided.

```bash
# One journal
php scripts/console.php sitemap:generate --rvcode=<code> [--pretty]

# All active journals
php scripts/console.php sitemap:generate --all [--pretty]
```

| Option | Description |
|--------|-------------|
| `--rvcode=<code>` | RV code of the journal to process — mutually exclusive with `--all` |
| `--all` | Process all active journals (STATUS = 1) — mutually exclusive with `--rvcode` |
| `--pretty` | Pretty-print the XML output |

---

## Volumes

### `volume:merge-pdf`

Merges the PDFs of all papers in each volume for a given journal. Requires `pdfunite` to be installed.

```bash
php scripts/console.php volume:merge-pdf [options]
```

| Option | Description |
|--------|-------------|
| `--rvcode <code>` | Journal RV code, or `allJournals` |
| `--dry-run` | Simulate without downloading or merging PDFs |
| `--ignore-cache` | Bypass cache and force re-merge |
| `--remove-cache` | Clear the cache for the given RV code before processing |

---

## DOAJ

### `doaj:export-volumes`

Creates DOAJ-compliant XML export files for journal volumes.

```bash
php scripts/console.php doaj:export-volumes [options]
```

| Option | Description |
|--------|-------------|
| `--rvcode <code>` | Journal RV code, or `allJournals` |
| `--dry-run` | Simulate without writing files or updating cache |
| `--ignore-cache` | Bypass cache and force re-export |
| `--remove-cache` | Clear the cache for the given RV code before processing |

---

## zbJATS

### `zbjats:zip`

Downloads the PDF and zbJATS XML for each paper in every volume, then packages them into a ZIP archive.

```bash
php scripts/console.php zbjats:zip [options]
```

| Option | Description |
|--------|-------------|
| `--rvid <id>` | RVID (integer) or comma-separated list of RVIDs to process |
| `--rvcode <code>` | RV code (string) or comma-separated list of RV codes to process |
| `--config <path>` | Path to INI file containing journal list (default: `scripts/zbjats/journals.ini`) |
| `--zip-prefix <prefix>` | Optional prefix for the ZIP filename (e.g. `2024_`) |
| `--dry-run` | Simulate without downloading files or writing the ZIP |
| `--remove-cache` | Clear the PDF/XML cache for the processed journal(s) |

When neither `--rvcode` nor `--rvid` is provided, the command automatically processes journals defined in `scripts/zbjats/journals.ini`.

---

## Import

### `import:sections`

Imports journal sections from a semicolon-separated CSV file.

```bash
php scripts/console.php import:sections [options]
```

| Option | Description |
|--------|-------------|
| `--csv-file <path>` | Path to the CSV file containing sections data |
| `--dry-run` | Simulate the import without writing to the database |

---

### `import:volumes`

Imports journal volumes from a semicolon-separated CSV file.

```bash
php scripts/console.php import:volumes [options]
```

| Option | Description |
|--------|-------------|
| `--rvid <id>` | Journal RVID (integer) |
| `--csv-file <path>` | Path to the CSV file containing volumes data |
| `--dry-run` | Simulate the import without writing to the database |

---

### `import:ref-pps`

Imports PPS data from a CSV file into the `ref_pps` Solr core.

```bash
php scripts/console.php import:ref-pps [csv-file]
```

| Argument | Default | Description |
|----------|---------|-------------|
| `csv-file` | `data/ref_pps/pps-current.csv` | Path to the CSV file to import |

---

### `download:ref-pps`

Downloads the PPS CSV file from IRIT. Includes a 48h limit check and keeps timestamped backups of previous versions.

```bash
php scripts/console.php download:ref-pps [options]
```

| Option | Description |
|--------|-------------|
| `--force` / `-f` | Force download even if the 48h limit is not reached |

---

## Statistics

### `stats:import-logs`

Parses Apache Combined Log Format access logs for one or all journals and bulk-inserts raw article
visits into `STAT_TEMP`. Supports plain and `.gz`-compressed log files. Duplicate runs are skipped
via the `STAT_PROCESSING_LOG` table (use `--force` to reprocess).

> **Prerequisite:** run `src/mysql/2025-08-24-stat-processing-log-table.sql` before the first use.
> See [docs/STATISTICS_UPDATE_README.md](./STATISTICS_UPDATE_README.md) for the full pipeline overview.

```bash
php scripts/console.php stats:import-logs [options]
```

| Option | Description |
|--------|-------------|
| `--rvcode <code>` | Journal to process — mutually exclusive with `--all` |
| `--all` | Process all journals with `is_new_front_switched = yes` |
| `--date <YYYY-MM-DD>` | Single day (default: yesterday) |
| `--month <YYYY-MM>` | Entire month |
| `--year <YYYY>` | Entire year |
| `--start-date <YYYY-MM-DD>` | Start of custom range (requires `--end-date`) |
| `--end-date <YYYY-MM-DD>` | End of custom range (requires `--start-date`) |
| `--force` | Reprocess dates already in `STAT_PROCESSING_LOG` |
| `--logs-path <path>` | Override the base Apache log directory (default: `../logs/httpd`) |

```bash
# Via Make (recommended)
make import-apache-logs rvcode=epiga              # yesterday's logs
make import-apache-logs all=1                     # all journals, yesterday
make import-apache-logs rvcode=epiga month=2025-06
make import-apache-logs rvcode=epiga start-date=2025-06-01 end-date=2025-06-30
make import-apache-logs rvcode=epiga force=1      # reprocess already-processed dates
```

Recommended cron schedule: daily at 01:00, before `stats:process`.

---

### `stats:download-kpi`

Aggregates download and page-view statistics for all published papers (those with a DOI and `STATUS = 16`) and writes the result to `data/kpi_downloads.json`. The output is keyed by journal `rvcode` and includes per-year breakdowns and per-country geographic data. See [docs/kpi-downloads-format.md](./kpi-downloads-format.md) for the full JSON schema.

```bash
php scripts/console.php stats:download-kpi [options]
```

| Option | Description |
|--------|-------------|
| `--output <path>` | Destination path for the JSON file (default: `data/kpi_downloads.json`) |
| `--rvcode <code>` | Restrict to one journal |
| `--pretty` | Pretty-print the JSON output |
| `--dry-run` | Print a summary without writing any file |

```bash
# Via Make (recommended)
make stats-download-kpi pretty=1           # all journals, pretty-printed
make stats-download-kpi rvcode=epiga       # one journal only
make stats-download-kpi output=/srv/kpi.json  # custom output path
make stats-download-kpi dry-run=1          # summary only, no file written
```

---

`stats:process` depends on two external data files that must be present before the first run:

| File | Provided by |
|------|-------------|
| `scripts/geoip/GeoLite2-City.mmdb` | [`geoip:update`](#geoipupdate) |
| `cache/counter-robots/COUNTER_Robots_list.txt` | [`stats:update-robots-list`](#statsupdate-robots-list) |

### `geoip:update`

Downloads and installs the [GeoLite2-City](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) database required by `stats:process` for IP geolocation. Verifies the SHA-256 checksum before installation and backs up the existing database with a timestamped suffix.

**Prerequisites:** a free MaxMind account — sign up at <https://www.maxmind.com/en/geolite2/signup>. Set your credentials in `config/pwd.json`:

```json
"GEO_IP": {
  "DATABASE_PATH": "/absolute/path/to/scripts/geoip/",
  "DATABASE": "GeoLite2-City.mmdb",
  "ACCOUNT_ID": "your_account_id",
  "LICENSE_KEY": "your_license_key",
  "DB_URL": "https://download.maxmind.com/geoip/databases/GeoLite2-City/download?suffix=tar.gz",
  "DB_SHA256": "https://download.maxmind.com/geoip/databases/GeoLite2-City/download?suffix=tar.gz.sha256"
}
```

```bash
php scripts/console.php geoip:update [options]
```

| Option | Description |
|--------|-------------|
| `--force` | Re-download even if the local database is already up to date |
| `--dry-run` | Show what would be done without writing any file |

```bash
# Via Make (recommended)
make update-geoip

# Force re-download
make update-geoip force=1
```

Recommended schedule: run monthly (MaxMind updates GeoLite2 on the first Tuesday of each month).

---

### `stats:update-robots-list`

Downloads the [COUNTER Robots list](https://github.com/atmire/COUNTER-Robots) and stores it locally so that `stats:process` can perform UA-based bot detection.

```bash
php scripts/console.php stats:update-robots-list [options]
```

| Option | Description |
|--------|-------------|
| `--force` | Re-download even if the local file is recent |
| `--dry-run` | Show what would be done without writing any file |

Recommended cron schedule: weekly (e.g. every Monday at 03:00).

---

### `stats:process`

Processes raw visit records from `STAT_TEMP` into the `PAPER_STAT` table. For each record it validates the IP, performs a GeoIP lookup, detects bots via the COUNTER Robots list, anonymizes the IP (255.255.0.0 mask), then inserts or increments the hit counter. Bot visits are discarded. Replaces the legacy `scripts/stat.php`.

```bash
php scripts/console.php stats:process [options]
```

| Option | Description |
|--------|-------------|
| `--date-s <yyyy-mm-dd>` | Process records up to this date (default: yesterday) |
| `--all` | Process **all** records regardless of date (mutually exclusive with `--date-s`) |
| `--dry-run` | Display each row's classification without writing to the database |
| `--no-dns` | Skip reverse-DNS lookup — the `DOMAIN` column is left empty but processing is much faster |

Recommended cron schedule: daily (e.g. every day at 02:00).

> **Performance note:** By default, the command performs a reverse-DNS lookup (`gethostbyaddr`) for each unique IP to populate the `DOMAIN` column. This call is blocking and can take 5–30 seconds per IP with no timeout. On datasets with many unique IPs (> a few hundred), this makes the command appear frozen. **Use `--no-dns` for large backfills or when domain data is not required.**
>
> ```bash
> # Recommended for backfills or large datasets
> php scripts/console.php stats:process --all --no-dns
>
> # Default (with DNS — suitable for small daily batches)
> php scripts/console.php stats:process
> ```

> **Note:** Run `stats:update-robots-list` at least once before the first execution of `stats:process`.

---

## Next.js Cache Revalidation

### `next:revalidate-cache`

Immediately sends a revalidation request for one or more cache tags to the Next.js frontend, bypassing the async queue by default — or enqueues them with `--queue` instead (symmetry with `solr:index`'s default-enqueue + `--sync`). Use the immediate form for urgent manual invalidation or smoke testing. Requires `NEXT_BASE_URL` and a valid token to be configured in `config/pwd.json` or `data/{rvcode}/config/pwd.json`.

See [docs/next-revalidation.md](./next-revalidation.md) for the full feature documentation (architecture, tag reference, worker setup, configuration).

```bash
php scripts/console.php next:revalidate-cache <rvcode> <tag> [<tag> ...]
php scripts/console.php next:revalidate-cache --queue <rvcode> <tag> [<tag> ...]
```

| Argument / option | Description |
|--------------------|-------------|
| `rvcode` | Journal code (e.g. `epijinfo`) |
| `tag` | One or more cache tags to invalidate (e.g. `article-42`, `news-epijinfo`, `volumes-epiga`) |
| `--queue` | Enqueue the tag(s) on the `next_revalidation` Messenger queue instead of POSTing immediately |

Exit code `0` if every tag succeeded (or was enqueued), `1` if any tag failed.

```bash
# Invalidate the news list for epijinfo
php scripts/console.php next:revalidate-cache epijinfo news-epijinfo

# Force-refresh the article list for epiga
php scripts/console.php next:revalidate-cache epiga articles-epiga

# Invalidate a specific article
php scripts/console.php next:revalidate-cache epijinfo article-1234

# Emergency broad invalidation, enqueued rather than blocking on N sequential POSTs
php scripts/console.php next:revalidate-cache --queue epijinfo articles articles-accepted sitemap
```

---

## Messenger queues

Two independent queues share the same generic Symfony Messenger + Doctrine
DBAL transport infrastructure (`library/Episciences/Messenger/*`), each with
its own `messenger_messages`/`messenger_failed` rows (scoped by the
`queue_name` column) and its own dispatch-failure table:

| Transport (`--transport=`) | Producer | Purpose | Failure table |
|---|---|---|---|
| `solr_index` | `Episciences\Solr\Indexing\Enqueue\SolrIndexing` | Index/delete a paper in Solr | `solr_enqueue_failures` |
| `next_revalidation` | `Episciences\Next\RevalidationService` | POST a Next.js `revalidateTag()` request | `next_revalidation_enqueue_failures` |

Both are consumed and administered by the same two generic commands,
`episciences:worker` and `episciences:queue`, selected via `--transport`
(see `scripts/Messenger/TransportProfileRegistry.php`). This is the **only**
path for either kind of work — paper publication/deletion/import and every
Next.js-facing model hook all enqueue here; there is no synchronous fallback
and no legacy cron for either.

The Doctrine bridge filters every read (`get()`, `find()`, `getMessageCount()`)
by `queue_name`, so `--transport=solr_index` and `--transport=next_revalidation`
never see each other's rows even though they share the same tables — verify
this with `episciences:queue --transport=<name> --stats` on both while both
workers are running. One exception: `messenger_failed.id` is a single shared
sequence across every transport, so a `--retry=<id>` for the wrong transport
correctly answers "not found" rather than replaying the wrong queue's
message, but `--list-failed` ids will look non-contiguous per transport —
that's expected, not data loss.

Retries and failures are handled natively by Messenger instead of being
silently swallowed. Backoff differs by transport, tuned to how much a delayed
message is still worth:

| Transport | Retries | Backoff | Dead-letter after |
|---|---|---|---|
| `solr_index` | 5 | 5s, 15s, 45s, ... (×3, capped at 30min) | up to ~30min |
| `next_revalidation` | 4 | 1s, 4s, 16s, 60s (×4) | ~81s |

A failed message stays in `messenger_failed` until inspected or retried via
`episciences:queue --transport=<name>`.

**Both workers must run continuously, supervised** (a dedicated Docker
Compose service each, or one systemd unit instance per transport on bare
metal), **in every environment.** There is no synchronous fallback and no
periodic-cron alternative for either: if a worker isn't running, its enqueued
work is simply never processed, with no error visible anywhere else in the
app. See [Deploying the workers](#deploying-the-workers) below.

Before first use in an environment, set up each transport once (the second
call skips re-diffing the shared `messenger_messages`/`messenger_failed`
tables the first one already created — see `--setup` below):

```bash
php scripts/console.php episciences:queue --transport=solr_index --setup
php scripts/console.php episciences:queue --transport=next_revalidation --setup
```

### Deploying the workers

**Docker Compose (this repo's own stack — dev, and any environment using this
`docker-compose.yml`):** two dedicated services, `worker-solr-index` and
`worker-next-revalidation`, run them, reusing the `php-fpm` image
(`docker-compose.yml`). They start with `make up` / `docker compose up -d`
like any other service — no manual step needed. `restart: always` covers
crash recovery, and `--time-limit=3600 --memory-limit=...` (same bounds as
the systemd unit below) make each periodically recycle its process. Check
with:

```bash
docker compose logs -f worker-solr-index worker-next-revalidation
docker compose exec -u www-data -w /var/www/htdocs php-fpm php scripts/console.php episciences:queue --transport=solr_index --stats
docker compose exec -u www-data -w /var/www/htdocs php-fpm php scripts/console.php episciences:queue --transport=next_revalidation --stats
```

A container does **not** run systemd as PID 1 (confirmed by
`systemctl status` failing with "not booted with systemd" inside `php-fpm`),
so nothing under `/etc/systemd/system` inside that container is ever started
automatically — this was the actual cause of a stuck queue after a container
rebuild: the systemd unit below is inert unless installed on a real
systemd-managed host.

**Bare-metal / VM without this Compose stack:** a ready-to-use systemd
*template* unit ships in the repo at
[`src/php-fpm/episciences-worker@.service`](../src/php-fpm/episciences-worker@.service)
(it's also baked into the `php-fpm` Docker image at
`/etc/systemd/system/episciences-worker@.service` at build time, so a
`docker cp episciences-php-fpm:/etc/systemd/system/episciences-worker@.service .`
against the image always gets the current version without checking out the
repo). Install it on each such server that must run these workers, then
enable one instance per transport (the `%i` in the unit becomes the
`--transport` value):

```bash
# From a checkout, or via docker cp from a running/built php-fpm image as above:
sudo cp "src/php-fpm/episciences-worker@.service" /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now episciences-worker@solr_index episciences-worker@next_revalidation

# Check status / logs
sudo systemctl status episciences-worker@solr_index episciences-worker@next_revalidation
sudo journalctl -u episciences-worker@solr_index -f
# (StandardOutput/StandardError are also appended to /var/www/logs/<transport>-worker.log)
```

Each instance runs as `www-data`, restarts automatically on crash
(`Restart=always`), and self-recycles hourly (`--time-limit=3600`) or past
512M memory (`--memory-limit=512M`) so `Restart=always` periodically refreshes
the process instead of a single PHP process running forever — edit
`ExecStart` in the unit file to change either bound (or override per-instance
with a `systemctl edit episciences-worker@next_revalidation` drop-in).
`WorkingDirectory` and `ExecStart` assume the app lives at `/var/www/htdocs`,
matching this repo's Docker/prod convention (`CNTR_APP_DIR` in the
`Makefile`); adjust both paths if a given server checks out the app
elsewhere.

After editing the unit file (in the repo or on a server), re-run
`systemctl daemon-reload && systemctl restart episciences-worker@solr_index episciences-worker@next_revalidation`
to pick up the change.

### `solr:index`

Enqueues (or synchronously runs) Solr re-indexing for one or more papers.

```bash
php scripts/console.php solr:index [options]
```

| Option | Description |
|--------|-------------|
| `--docid <id>` | Index only this DOCID |
| `--sqlwhere <clause>` | SQL `WHERE` clause to select DOCIDs (e.g. `'RVID = 42'`), always ANDed with `STATUS = 16` (published). **Trusted input only.** |
| `--file <path>` | Path to a file of DOCIDs, one per line |
| `--priority <n>` | Message priority (informational only — the Doctrine DBAL transport does not reorder by priority, unlike legacy `INDEX_QUEUE.PRIORITY`) |
| `--sync` | Build and send each document immediately instead of enqueuing it (bypasses Messenger entirely) |

`--docid`, `--sqlwhere` and `--file` are mutually exclusive; exactly one is required.

Only published papers (`STATUS = 16`) are ever indexed: `--sqlwhere` always ANDs
`STATUS = 16` onto the caller-supplied clause, and `IndexPaperMessageHandler`
re-checks status before building/sending the document — so a non-published
docid passed via `--docid` or `--file` is silently skipped rather than indexed.

---

### `solr:delete`

Enqueues (or synchronously runs) a Solr deletion, by DOCID or by raw query.

```bash
php scripts/console.php solr:delete [options]
```

| Option | Description |
|--------|-------------|
| `--docid <id>` | Delete this DOCID |
| `--query <query>` | Raw Solr delete query, e.g. `'docid:19'`. **Trusted input only.** |
| `--sync` | Run the deletion immediately instead of enqueuing it |

Exactly one of `--docid` or `--query` is required.

---

### `episciences:worker`

Continuously consumes one Messenger queue, selected by `--transport`. For
`solr_index`, index and delete messages share the same transport and are
routed to the correct handler automatically; there is no update/delete mode
flag. For `next_revalidation`, refuses to start if `NEXT_BASE_URL` is not
configured (see [docs/next-revalidation.md](./next-revalidation.md)).

```bash
php scripts/console.php episciences:worker --transport=<solr_index|next_revalidation> [options]
```

| Option | Description |
|--------|-------------|
| `--transport <name>` | **Required.** `solr_index` or `next_revalidation` |
| `--limit <n>` | Stop after processing this many messages |
| `--time-limit <seconds>` | Stop after this many seconds |
| `--memory-limit <size>` | Stop once memory usage exceeds this limit (e.g. `512M`) |

**Must run continuously under a process supervisor (systemd/supervisord),
one process per transport.** A periodic cron tick is not sufficient — this
is the only path either kind of work gets processed through, with no
synchronous fallback. Running one process per transport (rather than one
process alternating between both) means a slow Solr document build never
delays a Next.js revalidation POST.

---

### `episciences:queue`

Inspects and manages one Messenger queue, selected by `--transport` — a
minimal, hand-rolled equivalent of Symfony FrameworkBundle's
`messenger:failed:*` commands (this app has no bundle system to auto-register
them).

```bash
php scripts/console.php episciences:queue --transport=<solr_index|next_revalidation> [options]
```

| Option | Description |
|--------|-------------|
| `--transport <name>` | **Required.** `solr_index` or `next_revalidation` |
| `--stats` | Show pending and failed message counts |
| `--list-failed` | List failed messages (id, message class, exception, error) |
| `--retry <id>` | Retry the failed message with this id, synchronously, in this process |
| `--setup` | Create the `messenger_messages`/`messenger_failed` tables and this transport's own dispatch-failure table if they don't exist yet (one-time, per environment *and per transport* — see above) |
| `--list-dispatch-failures` | List enqueue calls that failed even after their bounded producer-side retry — these never made it into `messenger_messages` at all, so they don't show up in `--list-failed` |
| `--retry-dispatch-failure <id>` | Re-attempt a recorded dispatch failure; removes it from the dispatch-failure table on success |
| `--limit <n>` | Limit for `--list-failed` / `--list-dispatch-failures` (default: 50) |

Exactly one of `--stats`, `--list-failed`, `--retry`, `--setup`,
`--list-dispatch-failures` or `--retry-dispatch-failure` is required, and
both it and `--transport` are validated before any bootstrap.
