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

To set up a local development environment using Docker, run:

```bash
make dev-setup
```

This command will:
1. **Initialize Configuration**: It will copy `config/dist-dev.pwd.json` to `config/pwd.json` (asking for confirmation if it already exists).
2. Start the Docker containers.
3. Install PHP dependencies via Composer.
4. **Generate Sample Data**: It will automatically generate **30 random test users** using Faker for the 'dev' journal (RVID 1). These users are distributed as follows: 1 Chief Editor, 2 Administrators, 5 Editors, and 22 Members.
5. **Create Bot User**: A fixed `episciences-bot` user will be created (login: `episciences-bot`, password: `botPassword123`, role: `member`).
6. Set up the Solr search engine and index the sample content.

#### Test User Credentials
The generated users all have the default password: `password123`.
You can check the logs during `make dev-setup` to see the generated usernames.
The `episciences-bot` user has a fixed login (`episciences-bot`) and password (`botPassword123`).

#### Database Operations
You can also manually load or backup databases:
- `make load-dev-db`: Load the development datasets with sample data.
- `make load-db-episciences`: Load a dump from `~/tmp/episciences.sql`.
- `make load-db-auth`: Load a dump from `~/tmp/cas_users.sql`.
- `make backup-db`: Create backups of current databases in `~/tmp/`.

### License
Episciences is free software licensed under the terms of the GPL Version 3. See [LICENSE](./LICENSE).