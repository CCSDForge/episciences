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
| [`stats:update-robots-list`](#statsupdate-robots-list) | Download the COUNTER Robots list for bot detection |
| [`stats:process`](#statsprocess) | Process raw visit records from `STAT_TEMP` into `PAPER_STAT` |
| [`update-geoip` *(make)*](#update-geoip-make-target) | Download or update the GeoLite2-City.mmdb database |

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

Generates a sitemap XML file for a given journal. The `rvcode` argument is required.

```bash
php scripts/console.php sitemap:generate <rvcode> [options]
```

| Argument / Option | Description |
|-------------------|-------------|
| `rvcode` | The RV code of the journal (required) |
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
| `--rvid <id>` | RVID (integer) of the journal to process |
| `--zip-prefix <prefix>` | Optional prefix for the ZIP filename (e.g. `2024_`) |
| `--dry-run` | Simulate without downloading files or writing the ZIP |

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

## Statistics

`stats:process` depends on two external data files that must be present before the first run:

| File | Provided by |
|------|-------------|
| `scripts/geoip/GeoLite2-City.mmdb` | [`make update-geoip`](#update-geoip-make-target) |
| `cache/counter-robots/COUNTER_Robots_list.txt` | [`stats:update-robots-list`](#statsupdate-robots-list) |

### `update-geoip` *(make target)*

Downloads or updates the [GeoLite2-City](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) database required by `stats:process` for IP geolocation. This is a **shell script** invoked via `make`, not a Symfony Console command.

**Prerequisites:** a free MaxMind account and license key — sign up at <https://www.maxmind.com/en/geolite2/signup>.

```bash
# Via Make (recommended)
make update-geoip MAXMIND_LICENSE_KEY=your_license_key

# Directly
MAXMIND_LICENSE_KEY=your_license_key bash scripts/update-geoip.sh
```

The script:
1. Downloads `GeoLite2-City.tar.gz` from the MaxMind API.
2. Extracts `GeoLite2-City.mmdb` into `scripts/geoip/`.
3. Sets permissions to `644`.
4. Prints the database date and reminds you to configure `config/pwd.json`.

After the first download, update `config/pwd.json` so the application can find the file:

```json
"GEO_IP": {
  "DATABASE_PATH": "/absolute/path/to/scripts/geoip/",
  "DATABASE": "GeoLite2-City.mmdb"
}
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

Recommended cron schedule: daily (e.g. every day at 02:00).

> **Note:** Run `stats:update-robots-list` at least once before the first execution of `stats:process`.
