# Developer Setup Guide

This guide walks through setting up a local Episciences development environment from scratch using Docker.

---

## Prerequisites

| Requirement | Minimum version |
|-------------|-----------------|
| Docker | 24.x |
| Docker Compose | v2 (plugin, not standalone `docker-compose`) |
| Git | any recent version |

Verify your installation:

```bash
docker --version
docker compose version
```

---

## /etc/hosts Configuration

The application uses virtual-host-based routing. Add the following line to `/etc/hosts` **before** starting the containers:

```bash
sudo sh -c 'echo "127.0.0.1 localhost dev.episciences.org oai-dev.episciences.org data-dev.episciences.org manager-dev.episciences.org" >> /etc/hosts'
```

Verify:

```bash
ping -c1 dev.episciences.org
```

---

## First-time Setup

```bash
git clone <repository-url>
cd episciences
make dev-setup
```

`make dev-setup` performs the following steps automatically:

1. **`make build`** — builds all Docker images (Apache vhost, PHP-FPM, etc.)
2. **`make copy-config`** — copies `config/dist-dev.pwd.json` → `config/pwd.json`
3. **`make setup-logs`** — creates log directories with correct permissions
4. **`make up`** — starts all containers in detached mode
5. **`make wait-for-db`** — waits until MySQL is accepting connections
6. **`make init-data-dir`** — creates `data/dev/` with sub-directories and seeds `navigation.json`
7. **`make composer-install`** — installs PHP dependencies
8. **`make load-dev-db`** — loads the development SQL dataset
9. **`make init-dev-users`** — creates 30 test users and prints a summary table
10. **`make create-bot-user`** — creates the fixed `episciences-bot` account
11. **`make collection`** — creates the Solr `episciences` collection
12. **`make index`** — indexes sample content into Solr

When it completes, open **http://dev.episciences.org/** in your browser.

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

## Available Services

| Service | URL |
|---------|-----|
| Journal | http://dev.episciences.org/ |
| Manager | http://manager-dev.episciences.org/dev/ |
| OAI-PMH | http://oai-dev.episciences.org/ |
| Data | http://data-dev.episciences.org/ |
| PhpMyAdmin | http://localhost:8001/ |
| Apache Solr | http://localhost:8983/solr |

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

To start over with a clean database:

```bash
make down
make clean-mysql    # WARNING: deletes all MySQL volumes — prompts for confirmation
make dev-setup
```

---

## Database Operations Reference

| Command | Description                                                 |
|---------|-------------------------------------------------------------|
| `make load-dev-db` | Load the development SQL dataset, inludes the 'Dev' journal |
| `make load-db-episciences` | Restore from `~/tmp/episciences.sql`                        |
| `make load-db-auth` | Restore from `~/tmp/cas_users.sql`                          |
| `make backup-db` | Dump current databases to `~/tmp/`                          |
| `make shell-mysql` | Open a MySQL shell in the container                         |
| `make clean-mysql` | Delete MySQL volumes (irreversible, prompts)                |
