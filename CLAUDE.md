# CLAUDE.md

## Framework & Structure
- zf1-future (PHP 8.1+ compatible Zend Framework 1.x)
- MySQL 8.0 dual database (main + auth)
- Apache Solr integration
- Webpack Encore + Sass

## Key Directories
- `application/` - MVC structure (modules: journal, portal, oai, common)
- `library/Episciences/` + `library/Ccsd/` - Main application classes
- `public/` - Web root + JavaScript files
- `tests/` - PHPUnit + Jest test suite

## Core Classes
- `Episciences\Paper` - Paper/article entity
- `Episciences\Review` - Journal management
- `*Manager` classes - Business logic layer
- `*Controller` classes - MVC controllers

## Testing Commands
- `phpunit` - PHP tests
- `phpstan` - Static analysis
- `yarn test` - JavaScript tests (Jest)
- `yarn format` - Format JS with Prettier

## PhpUnit
- run php tests inside the container with `make phpunit`

## Key Files
- `application/configs/application.ini` - Main config
- `config/pwd.json` - Credentials (not in repo)
- `public/bdd_const.php` - Database constants

# Directories to ignore
- `data/`
- `cache/`
- `tmp/`
- `log/`
- `logs/` 