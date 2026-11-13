# Developer Setup Guide

This guide walks through setting up a local Episciences development environment from scratch using Docker.

---

## Prerequisites

| Requirement | Minimum version | Notes |
|-------------|-----------------|-------|
| Docker | 24.x | |
| Docker Compose | v2 | Plugin (`docker compose`), not standalone `docker-compose` |
| GNU Make | any recent version | Used for all setup and dev shortcuts |
| Git | any recent version | |
| Node.js & Yarn | see `.nvmrc` | Required on the host for `make test-js` and `make test-e2e` |

Verify your installation:

```bash
docker --version
docker compose version
make --version
```

---

## /etc/hosts Configuration

The application and shared infrastructure services use virtual-host-based routing via Traefik. Add the following line to `/etc/hosts` **before** starting the containers:

```bash
sudo sh -c 'echo "127.0.0.1 localhost dev.episciences.org oai-dev.episciences.org data-dev.episciences.org manager-dev.episciences.org mailpit.episciences.org pma.episciences.org solr.episciences.org traefik.episciences.org sonar.episciences.org" >> /etc/hosts'
```

Verify:

```bash
ping -c1 dev.episciences.org
```

---

## Architecture & Dependency: `episciences-infrastructure`

Episciences does **not** run its own database, search engine, or reverse proxy. Those stateful and shared services are managed centrally by [CCSDForge/episciences-infrastructure](https://github.com/CCSDForge/episciences-infrastructure) so that all services in the Episciences ecosystem (`episciences`, `episciences-api`, `episciences-citations`, `episciences-oai`, `episciences-manager-ng`, `coar-notify-inbox`) can share the same infrastructure.

### Why `episciences-infrastructure` is required

| Repository | Responsibility |
|---|---|
| **[episciences-infrastructure](https://github.com/CCSDForge/episciences-infrastructure)** | **Shared infrastructure & service engines:** creates the external Docker network `epi-network` and runs MySQL 8.4 (`db-episciences`, `db-auth`, `db-indexing`), Apache Solr + ZooKeeper, Traefik (TLS termination with self-signed certificates), Mailpit, phpMyAdmin, and SonarQube. |
| **episciences** *(this repository)* | **Application & database schema:** runs the web/PHP containers (`httpd`, `php-fpm`, `worker-solr-index`, `worker-next-revalidation`) attached to `epi-network`, and **owns the `episciences` database schema, SQL migrations (`src/mysql/`), and dev dataset (`src/mysql/docker/episciences/dev-episciences.sql`)**. |

Because `docker-compose.yml` declares `epi-network` as `external: true` and connects `php-fpm` directly to `db-episciences`, `db-auth`, `db-indexing`, `solr`, and `mailpit`, **`episciences-infrastructure` must be started first**; otherwise the `episciences` containers will fail to start or connect.

Meanwhile, because schema changes happen per branch and pull request in **this** repository, `make dev-setup` (via `make load-dev-db`) loads `dev-episciences.sql` and `dev-cas_users.sql` from your current `episciences` branch into the MySQL containers provided by `episciences-infrastructure`.

### Start `episciences-infrastructure` first

Clone it alongside this repository (if not already done) and start it **before** running `make dev-setup`:

```bash
git clone https://github.com/CCSDForge/episciences-infrastructure.git ../episciences-infrastructure
cd ../episciences-infrastructure
make up          # creates epi-network, starts Traefik + MySQL DBs + Solr + Mailpit + phpMyAdmin
make db-import   # first-time DB init (creates/initializes shared DB volumes when empty)
make solr-setup  # uploads Solr configset to ZooKeeper and creates the 'episciences' collection
```

---

## First-time Setup

Once `episciences-infrastructure` is running, return to this repository and run:

```bash
git clone https://github.com/CCSDForge/episciences.git
cd episciences
make dev-setup
```

`make dev-setup` performs the following steps automatically:

1. **`make build`** — builds all Docker images (Apache vhost, PHP-FPM, etc.)
2. **`make copy-config`** — copies `config/dist-dev.pwd.json` → `config/pwd.json`
3. **`make setup-logs`** — creates log directories with correct permissions
4. **`make up`** — starts all application containers (`httpd`, `php-fpm`, `worker-solr-index`, `worker-next-revalidation`) on `epi-network`
5. **`make wait-for-db`** — waits until `db-episciences`, `db-indexing`, and `db-auth` (from `episciences-infrastructure`) are accepting connections
6. **`make init-data-dir`** — creates `data/dev/` with sub-directories and seeds `navigation.json`
7. **`make composer-install`** — installs PHP dependencies
8. **`make yarn-encore-dev`** — installs JS dependencies and compiles frontend assets for dev
9. **`make load-dev-db`** — prompts for confirmation (`y/N`) and loads `src/mysql/docker/episciences/dev-episciences.sql` and `src/mysql/docker/auth/dev-cas_users.sql`
10. **`make init-dev-users`** — creates 30 test users and prints a summary table
11. **`make create-bot-user`** — creates the fixed `episciences-bot` account
12. **`make collection`** — ensures the Solr `episciences` configset and collection exist
13. **`make solr-index sqlwhere='1=1' sync=1`** — indexes sample content into Solr

When it completes, open **https://dev.episciences.org/** in your browser (accept the self-signed certificate warning).

---

## Regular Workflow

```bash
# Start containers
make up

# Stop containers
make down

# Restart everything
make restart

# View logs
make logs
# Or for a specific container:
make logs CONTAINER=php-fpm
```

---

## Application CLI Commands

Episciences ships a Symfony Console application at `scripts/console.php`. All commands run inside
the PHP container. Use `make <target>` as a shortcut during development, or invoke the script
directly in production.

```bash
# List all available commands
docker compose exec -u www-data php-fpm php scripts/console.php list

# Get help for a specific command
docker compose exec -u www-data php-fpm php scripts/console.php enrichment:creators --help
```

### Command reference

| Namespace | Command | Description |
|-----------|---------|-------------|
| **app** | `app:generate-users` | Generate test user accounts |
| | `app:init-dev-users` | Seed development users (called by `make init-dev-users`) |
| | `app:create-bot-user` | Create the `episciences-bot` account |
| **enrichment** | `enrichment:citations` | Enrich citation metadata (OpenCitations / OpenAlex / Crossref) |
| | `enrichment:creators` | Enrich author ORCID data (OpenAIRE / HAL TEI) |
| | `enrichment:licences` | Enrich licence data from repository APIs |
| | `enrichment:links` | Enrich dataset links (Scholexplorer) |
| | `enrichment:funding` | Enrich funding data (OpenAIRE / HAL) |
| | `enrichment:classifications-jel` | Enrich JEL classification codes (OpenAIRE) |
| | `enrichment:classifications-msc` | Enrich MSC 2020 classification codes (zbMATH) |
| | `enrichment:zb-reviews` | Discover zbMATH Open reviews |
| **sitemap** | `sitemap:generate` | Generate XML sitemaps |
| **volume** | `volume:merge-pdf` | Merge per-volume PDFs into a single file |
| **doaj** | `doaj:export-volumes` | Create DOAJ XML exports per volume |
| **zbjats** | `zbjats:zip` | Package PDF + zbJATS XML files into a ZIP archive |
| **import** | `import:papers` | Import or update papers from a CSV file |
| | `import:sections` | Import journal sections from a CSV file |
| | `import:volumes` | Import journal volumes from a CSV file |
| | `import:ref-pps` | Import PPS data from a CSV file into Solr |
| | `download:ref-pps` | Download the PPS CSV file from IRIT |
| **export** | `export:papers` | Export papers to a CSV file, same format as `import:papers` |
| **stats** | `stats:import-logs` | Parse Apache access logs into `STAT_TEMP` |
| | `stats:download-kpi` | Aggregate download KPIs and write `data/kpi_downloads.json` |
| | `stats:update-robots-list` | Download the COUNTER Robots list for bot detection |
| | `stats:process` | Process raw visit records from `STAT_TEMP` into `PAPER_STAT` |
| **geoip** | `geoip:update` | Download or update the GeoLite2-City database |

### Common options

| Option | Applies to | Description |
|--------|-----------|-------------|
| `--dry-run` | most commands (`enrichment:*`, `import:*`, `stats:*`, etc.) | Simulate without writing to the database or filesystem |
| `--rvcode=<code>` | all `enrichment:*` commands | Restrict processing to one journal |
| `-q` / `--quiet` | all commands | Suppress console output (log file only) |
| `-vv` | `enrichment:zb-reviews`, `enrichment:classifications-msc` | Print each zbMATH API URL as it is called |

### Makefile shortcuts (development)

Each command has a corresponding `make` target that runs it inside the container as `www-data`.
Run `make help` or inspect the `Makefile` for the full list. Example:

```bash
make enrich-creators
make enrich-zb-reviews ARGS="--rvcode=jdmdh --dry-run"
```

### Production usage

In production there is no Make or Docker. Commands are run directly on the server:

```bash
sudo -u www-data php /var/www/htdocs/scripts/console.php enrichment:creators --rvcode=jdmdh
```

---

## Code Quality & Static Analysis

The project includes several tools to ensure code quality and maintainability.

### PHPStan (Static Analysis)

Analyzes your code for potential bugs and type errors without running it.

```bash
# Run on the whole project
make phpstan

# Run on a specific file or directory
make phpstan TARGET=library/Episciences/Api
```

### Rector (Automated Refactoring)

Helps with automated code upgrades and refactoring.

```bash
# Run in dry-run mode (see what would change)
make rector DRY_RUN=1

# Apply changes
make rector
```

### PhpMetrics (Metrics & Visualization)

[![PhpMetrics](https://github.com/CCSDForge/episciences/actions/workflows/phpmetrics.yml/badge.svg)](https://ccsdforge.github.io/episciences/metrics/)

Generates a comprehensive HTML report about code complexity, coupling, and maintainability.

```bash
# Optional: Run PHP tests first to include assertions count in the report
make test-php

# Generate/Update the report
make phpmetrics
```

The report is generated in `phpmetrics/report/index.html`. Open it in your browser to view the dashboard.

---

## Testing

The project uses PHPUnit for backend tests and Jest for frontend tests.

```bash
# Run all tests (PHP + JS)
make test

# Run all tests with coverage reports
make test-coverage

# Run only PHP tests
make test-php

# Run only JS tests
make test-js
```

PHP coverage is generated in `build/coverage` (if Xdebug/PCOV is available).
JS coverage is generated in `coverage/lcov-report/index.html`.

---

## Step-debugging with Xdebug

Xdebug is installed in the `php-fpm` image but off (`xdebug.mode=off`), so tests, console commands and
workers run without its overhead. Turn step-debugging on when you need it:

```bash
make up-xdebug   # containers with docker-compose.xdebug.yml (and docker-compose.override.yml if present)
make up          # back to containers without Xdebug
```

In the IDE (PhpStorm):

1. Listen for debug connections on port `9003`.
2. Declare a server named exactly `dev.episciences.org` (*Settings → PHP → Servers*), with *Use path
   mappings* and the **project root** mapped to `/var/www/htdocs`. The port does not matter: the IDE
   picks the server by name (`PHP_IDE_CONFIG`).
3. Set a breakpoint and load a page: every web request starts a debugging session.

Console scripts connect only with a trigger:

```bash
docker compose exec -e XDEBUG_TRIGGER=1 php-fpm php scripts/<script>.php
```

Where the settings live:

| File | Role |
| --- | --- |
| `src/php-fpm/xdebug.ini` (image) | `xdebug.mode=off` by default |
| `docker-compose.xdebug.yml` | `XDEBUG_MODE=debug`, `XDEBUG_CONFIG` (IDE host and port), `PHP_IDE_CONFIG` |
| `src/php-fpm/xdebug-fpm.conf` | `start_with_request=yes` for PHP-FPM only (web requests) |

Troubleshooting: if breakpoints are not hit, check in the container that the IDE can be reached
(`php -r 'var_dump((bool) @fsockopen("host.docker.internal", 9003));'`), then log one request with
`-d xdebug.log=/proc/self/fd/2 -d xdebug.log_level=7` on a console script run with `XDEBUG_TRIGGER=1`.

---

## Available Services

| Service | URL | Note |
|---------|-----|------|
| Journal | https://dev.episciences.org/ | via Traefik |
| Manager | https://manager-dev.episciences.org/dev/ | via Traefik |
| OAI-PMH | https://oai-dev.episciences.org/ | via Traefik |
| Data | https://data-dev.episciences.org/ | via Traefik |
| Mailpit | https://mailpit.episciences.org/ | via Traefik (infra) |
| PhpMyAdmin | https://pma.episciences.org/ | via Traefik (infra) |
| Apache Solr | https://solr.episciences.org (or http://localhost:8983/solr) | via Traefik / direct port (infra) |
| SonarQube | https://sonar.episciences.org/ | via Traefik (infra) |

> All HTTPS services use Traefik's self-signed certificate — accept the browser warning on first access.

---

## Credentials

| Account | Login | Password | Role |
|---------|-------|----------|------|
| Bot user | `episciences-bot` | `botPassword123` | administrator |
| Generated users (×30) | see terminal table after setup | `password123` | various |

The terminal prints a table of all generated usernames after `make init-dev-users` completes.
Users are distributed as: 1 Chief Editor, 2 Administrators, 5 Editors, 22 Members.

---

## Troubleshooting

### Rootless Docker / `composer-install` permission error

If you see a permission-denied error during `composer-install`, your host UID does not match the
container's expected `1000:1000`. Override it:

```bash
make dev-setup CNTR_USER_ID=0:0
```

Or, to run only composer as root without redoing the full setup:

```bash
make composer-install CNTR_USER_ID=0:0
```

### `data/dev` not created / "navigation.json" fatal error

The application needs `data/dev/config/navigation.json` to bootstrap. This is created automatically
by `make init-data-dir` (which is part of `make dev-setup`). If you skipped it or the directory was
deleted, run:

```bash
make init-data-dir
```

This requires the containers to be running (`make up` first).

### `copy-config` prompt hangs in CI or non-interactive shell

`make copy-config` asks for confirmation interactively when `config/pwd.json` already exists.
In non-interactive environments, pre-create the file before running `make dev-setup`:

```bash
cp config/dist-dev.pwd.json config/pwd.json
make dev-setup
```

### Finding generated usernames

After `make init-dev-users` runs, a table listing every username, role, and email is printed to
stdout. If you missed it, you can re-run the command alone:

```bash
make init-dev-users
```

Note: this will attempt to create users again; duplicate-email errors are reported but do not
break the existing accounts.

### Database reset

To reload the development databases (`episciences` and `cas_users`) from your current branch without touching `episciences-infrastructure` volumes:

```bash
make load-dev-db
make init-dev-users
make create-bot-user
```

If you need to wipe the MySQL volumes completely, manage them from `episciences-infrastructure` (`make down` and volume cleanup there), then re-run `make up` in `episciences-infrastructure` followed by `make dev-setup` here.

---

## Database Operations Reference

| Command | Description |
|---------|-------------|
| `make load-dev-db` | Load the development SQL datasets (`dev-episciences.sql` and `dev-cas_users.sql`), includes the `'dev'` journal |
| `make load-db-episciences` | Restore from `~/tmp/episciences.sql` |
| `make load-db-auth` | Restore from `~/tmp/cas_users.sql` |
| `make backup-db` | Dump current `episciences` and `cas_users` databases to `~/tmp/` |
| `make shell-mysql-episciences` | Open a MySQL shell on the `episciences` database |
| `make shell-mysql-auth` | Open a MySQL shell on the `cas_users` database |
| `make shell-mysql-indexing` | Open a MySQL shell on the `solr_index_queue` database |
| `make wait-for-db` | Wait until all 3 MySQL containers (`episciences-infrastructure`) accept connections |
