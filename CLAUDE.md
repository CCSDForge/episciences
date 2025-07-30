# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## About Episciences

Episciences is an overlay journal platform developed by the Center for the Direct Scientific Communication (CCSD). It's a PHP application built on zf1-future, a PHP 8.1 compatible version of Zend Framework 1.x . It provides a complete platform for managing scientific journals.

## Development Commands

### Docker Environment
The project uses Docker for development. Core commands:

- `make up` - Start all Docker containers (includes Apache Solr, PHP-FPM, HTTPd, MySQL)
- `make down` - Stop containers and remove orphans
- `make build` - Build Docker containers
- `composer-install` - Install PHP dependencies via Docker
- `yarn-encore-production` - Build frontend assets for production

### Database Operations
- `make load-db-episciences` - Load main database from `~/tmp/episciences.sql`
- `make load-db-auth` - Load authentication database from `~/tmp/cas_users.sql`

### Solr Search
- `make collection` - Create Solr collection (after containers are up)
- `make index` - Index content into Solr

### Testing and Quality
- `phpunit` - Run tests (configured in phpunit.xml)
- `rector` - PHP code modernization tool
- `phpstan` - Static analysis (configured in phpstan.neon)
- `phpmetrics` - Code quality metrics

## Architecture Overview

### Framework and Structure
- **Framework**: zf1-future, a PHP 8.1 compatible version of Zend Framework 1.x (legacy framework) 
- **PHP Version**: 8.1+
- **Database**: MySQL with dual database setup (main + auth)
- **Search**: Apache Solr integration
- **Frontend**: Webpack Encore for asset compilation, Sass for styling

### Key Directories
- `application/` - Main application code (MVC structure)
  - `modules/` - Application modules (journal, portal, oai, common)
  - `configs/` - Configuration files (INI format)
  - `languages/` - Internationalization files
- `library/` - Core libraries and business logic
  - `Ccsd/` - CCSD framework components
  - `Episciences/` - Main application classes
- `public/` - Web root with entry point (index.php)
- `scripts/` - Command line scripts and maintenance tools
- `tests/` - PHPUnit test suite
- `src/mysql/docker/episciences/episciences.sql` - SQL dump for main database
- `src/mysql/docker/auth/cas_users.sql` - SQL dump for authentication database
- `src/mysql/docker/solr/solr_index.sql` - SQL dump for Solr indexing queue

### Module System
The application uses a modular architecture:
- **journal** - Main journal functionality
- **portal** - Multi-journal portal
- **oai** - OAI-PMH protocol implementation  
- **common** - Shared components

### Key Classes and Patterns
- `Episciences\Paper` - Core paper/article entity
- `Episciences\Review` - Journal management
- `Episciences\User` - User management with role-based access
- `*Manager` classes - Business logic layer (e.g., PapersManager, UsersManager)
- `*Controller` classes - MVC controllers in each module

### Database Architecture
- Dual database setup: main application DB + separate authentication DB
- Uses Zend_Db_Table for ORM-like functionality
- Database constants defined in `public/bdd_const.php`

### Authentication System
- CAS authentication
- Role-based access control with ACL

### File Management
- Paper file uploads and versioning
- PDF processing capabilities
- Document backup system

### Email System
- Template-based email system in `library/Episciences/Mail/`
- Multi-language email templates
- Automated reminder system

## Configuration Files

### Core Configuration
- `application/configs/application.ini` - Main application configuration
- `config/pwd.json` - Database and service credentials (not in repo)
- `composer.json` - PHP dependencies
- `package.json` - Node.js dependencies for frontend build

### Environment Setup
- Copy `config/dist-pwd.json` to `config/pwd.json` and configure
- Ensure hosts file includes development domains mentioned in Makefile
- Default development URLs:
  - Journal: http://dev.episciences.org/
  - Manager: http://manager-dev.episciences.org/dev/
  - OAI: http://oai-dev.episciences.org/
  - Data: http://data-dev.episciences.org/

## Common Development Workflows

### Paper Submission and Review Process
The core workflow involves paper submission, peer review, and publication. Key classes:
- Paper submission: `Episciences\Submit`
- Journal management: `Episciences\Review`
- Editor assignment: `Episciences\EditorsManager`
- Reviewer management: `Episciences\ReviewersManager`

### DOI Management
Automated DOI creation and management through `Episciences\DoiTools` and related queue systems.

### Volume and Section Management
Journals are organized into volumes and sections with dedicated management classes.

### Solr Integration
Full-text search powered by Apache Solr with custom indexing for papers and metadata.

## Important Notes

- The project uses zf1-future, a PHP 8.1 compatible version of Zend Framework 1.x . Follow existing patterns or upgrade up to PHP 8.1 patterns.
- The codebase is complex and requires familiarity with legacy Zend Framework 1.x concepts.
- Use the provided Docker environment for development to avoid issues with dependencies and configurations.
- The codebase includes extensive multilingual support (French/English primarily)
- Docker is the primary development environment - avoid running directly on host system
- The application handles sensitive academic publishing workflows - be careful with data integrity
- Uses extensive email templating for scholarly communication workflows
- The project is actively maintained, so check for updates and changes in the repository.
- Refer to the `README.md` for additional setup and usage instructions.
- All notable changes to this project will be documented in the CHANGELOG.md file
- Parts of the codebase in the process of being moved to Symfony and React.
- Never use the `main` or `preprod` branch for development - always create feature branches.
- New features should be developed in separate branches of `preprod` or `develop` and merged via pull requests.
- Commit regularly, Commit messages should follow conventional commit standards for clarity
- Use the provided Makefile for common tasks to ensure consistency across environments
- Follow the coding standards and best practices outlined in the repository documentation
- Use the provided Docker environment for development to ensure consistency and avoid dependency issues
- Always run tests before pushing changes to ensure code quality and functionality
- **NEVER use `git add -A`** - This command stages all changes indiscriminately and can lead to committing unintended files. Use `git add .` for current directory or specify files explicitly.
- The project is open source and contributions are welcome.
- The project is licensed under the GNU General Public License v3.0 (GPL-3.0). Please review the LICENSE file for details.