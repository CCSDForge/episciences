# CLAUDE.md

## Framework & Structure
- zf1-future (PHP 8.1+ compatible Zend Framework 1.x)
- MySQL 8.4 dual database (main + auth)
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

## Testing & Linting Commands
- `make test-php` - PHP tests
- `make test-js` - JavaScript tests (Jest)
- `make phpstan [TARGET=path] [LEVEL=X]` - PHPStan static analysis (level 0-9)
- `make rector [TARGET=path] [DRY_RUN=1]` - Rector refactoring
- `yarn format` - Format JS with Prettier
- `make check-source-reading-tests` - Refuse new tests that read the application source
- Tests must run the code (call the method, dispatch the action, render the view), never read
  the source (`file_get_contents()` on code, `extractMethod()`, `getStartLine()`).
  Controller actions: extend `tests/unit/modules/ControllerTestCase.php`. Details: `tests/README.md`

## Code
- Write comments, documentation in English
- Do not use Jquery for JavaScript

## Key Files
- `application/configs/application.ini` - Main config
- `config/pwd.json` - Credentials (not in repo)
- `public/bdd_const.php` - Database constants

# General information
- writing outside the project directory is forbidden, even in /tmp
- when asked to write a plan, write it in the ./tmp/ directory of the project
- when creating new code files, chmod 644
- never use `git add -A` or `git add .`: list the files to commit explicitly (`git add path/to/file ...`)

# Directories to ignore
- `data/`
- `cache/`
- `tmp/`
- `log/`
- `logs/`

## Conventions
- New PHP classes use a real namespace (`Episciences\...`), not the legacy underscore naming
- New PHP files must pass `make phpstan LEVEL=6`
- Logging in new code: `AppRegistry::getMonoLogger()?->warning(...)`, not `Ccsd_Log::message()`
- Cache: Symfony Cache PSR-6; `Episciences_Cache` / `Ccsd_Cache` are deprecated
- Validate external URLs (HTTP/HTTPS only) before fetching them (SSRF)
- SQL (MySQL 8, `ONLY_FULL_GROUP_BY`): non-aggregated SELECT columns go in GROUP BY; count paginated grouped queries with `SELECT COUNT(*) FROM (...) AS sub`; use `->order('COL DESC')`, the second argument of `Zend_Db_Select::order()` is ignored
- `date_create()->format()` and `preg_replace()` may return false/null: handle it (see `Repositories\Common::safeDateFormat`)
- Tests: typed properties without default need `= null` (PHP 8.1); do not mock `Zend_Translate`; use `ArrayAdapter(0, false)` for `assertSame()` on cached objects
