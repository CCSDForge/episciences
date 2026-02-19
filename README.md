# Episciences
## An overlay journal platform
![GPL](https://img.shields.io/github/license/CCSDForge/episciences)
![Language](https://img.shields.io/github/languages/top/CCSDForge/episciences)
![JavaScript Tests](https://github.com/CCSDForge/episciences/workflows/JavaScript%20Tests/badge.svg)
![PHPUnit Tests](https://github.com/CCSDForge/episciences/workflows/PHPUnit%20Tests/badge.svg)

[![SWH](https://archive.softwareheritage.org/badge/origin/https://github.com/CCSDForge/episciences/)](https://archive.softwareheritage.org/browse/origin/?origin_url=https://github.com/CCSDForge/episciences)
[![SWH](https://archive.softwareheritage.org/badge/swh:1:dir:309043823a5dd0f53bd0b05b19c94f68e2a389f7/)](https://archive.softwareheritage.org/swh:1:dir:309043823a5dd0f53bd0b05b19c94f68e2a389f7;origin=https://github.com/CCSDForge/episciences;visit=swh:1:snp:4a3c0b105e08da2f8348cbfe1145c0270f5fc80f;anchor=swh:1:rev:dd7b51889f2d2ec5e1a25c1fbd935adaf14662f6)

This repository hosts the software used for the Episciences publishing platform.

More information about Episciences: https://www.episciences.org/

[All Episciences overlay journals](https://www.episciences.org/journals/)

The software is developed by the [Center for the Direct Scientific Communication (CCSD)](https://www.ccsd.cnrs.fr/en/). See [AUTHORS](./AUTHORS).

### Acknowledgments
Episciences has received funding from:
- [CNRS](https://www.cnrs.fr/)
- [European Commission grant 101017452](https://cordis.europa.eu/project/id/101017452) “OpenAIRE Nexus - OpenAIRE-Nexus Scholarly Communication Services for EOSC users”

### Changelog
All notable changes to this project will be documented in the [CHANGELOG.md](./CHANGELOG.md)

### Development Setup

For a detailed guide including troubleshooting, see [docs/dev-setup.md](./docs/dev-setup.md).

#### Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and [Docker Compose v2](https://docs.docker.com/compose/install/)
- Add the following line to `/etc/hosts`:
  ```
  127.0.0.1 localhost dev.episciences.org oai-dev.episciences.org data-dev.episciences.org manager-dev.episciences.org
  ```

#### First-time Setup

On a fresh machine, Docker images must be built before starting containers:

```bash
make dev-setup
```

`make dev-setup` automatically runs `make build` first (safe to run repeatedly — Docker uses the cache on subsequent runs).

This command will:
1. **Build Docker images** (`make build`).
2. **Initialize Configuration**: Copy `config/dist-dev.pwd.json` to `config/pwd.json` (asking for confirmation if it already exists).
3. Start the Docker containers.
4. **Initialize `data/dev`**: Create the required journal data directory and seed it with `navigation.json` so the application bootstraps correctly.
5. Install PHP dependencies via Composer.
6. **Generate Sample Data**: Automatically generate **30 random test users** for the 'dev' journal (RVID 1): 1 Chief Editor, 2 Administrators, 5 Editors, and 22 Members.
7. **Create Bot User**: A fixed `episciences-bot` user (login: `episciences-bot`, password: `botPassword123`, role: `administrator`).
8. Set up the Solr search engine and index the sample content.

#### Accessing the Journal

After setup, open: **http://dev.episciences.org/**

> Make sure the `/etc/hosts` entry above is in place before trying to access the site.

#### Test User Credentials

After `make dev-setup`, a summary table of all generated usernames, roles, and email addresses is printed in the terminal.

| Account | Login | Password | Role |
|---------|-------|----------|------|
| Generated users (×30) | see terminal table | `password123` | various |
| Bot user | `episciences-bot` | `botPassword123` | administrator |

#### Rootless Docker / Permission Issues

If `composer-install` fails with a permission error, override the container user:

```bash
make dev-setup CNTR_USER_ID=0:0
```

#### Database Operations

You can also manually load or backup databases:
- `make load-dev-db`: Load the development datasets with sample data.
- `make load-db-episciences`: Load a dump from `~/tmp/episciences.sql`.
- `make load-db-auth`: Load a dump from `~/tmp/cas_users.sql`.
- `make backup-db`: Create backups of current databases in `~/tmp/`.

### License
Episciences is free software licensed under the terms of the GPL Version 3. See [LICENSE](./LICENSE).