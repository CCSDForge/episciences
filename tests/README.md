# Testing Guide

> **Requirement:** All PHP tests run inside the Docker container.
> Running `phpunit` directly on the host will fail (no database connection).

## Quick start

```bash
# Run all tests (PHP + JavaScript)
make test

# PHP tests only (all suites)
make test-php

# PHP unit tests only
make test-php-unit

# PHP integration tests only
make test-php-integration

# PHP tests with filter or suite
make test-php FILTER=testMyMethod
make test-php SUITE=unit

# JavaScript tests only (Jest)
make test-js

# End-to-end tests (Playwright), see tests/e2e/README.md
make test-e2e   # or: yarn test:e2e
```

## PHP tests (PHPUnit)

Tests are executed inside the PHP container via `make test-php`, which runs:

```
docker compose exec <php-container> ./vendor/bin/phpunit
```

### Test the behaviour, not the source

A test must run the code it covers: call the method, dispatch the controller action, render the view.
Reading the source instead (`file_get_contents()` on a controller, `extractMethod()`,
`ReflectionMethod::getStartLine()`, regular expressions on a `.phtml`) is not accepted: such tests
break on any refactoring and pass on code that does not work.

Controller actions run without database on top of `tests/unit/modules/ControllerTestCase.php`
(recorded queries and writes, requests with or without token, logged or impersonated user, journal
settings). See `ControllerTestCaseTest` and `AdministratepaperSaveSectionTest` for examples.

`make check-source-reading-tests` (also run by the CI) refuses new tests that read the source. The
older ones are listed in `tests/legacy-source-reading-tests.txt`: the list may only shrink, remove a
file from it once converted.

### Run a subset of tests

To target a specific directory or file, open a shell in the container first:

```bash
# Open a shell in the PHP container
docker compose exec <php-container> bash

# Then inside the container:

# All tests in a directory
./vendor/bin/phpunit tests/unit/library/Episciences

# A single test class
./vendor/bin/phpunit tests/unit/library/Episciences/Episciences_ToolsTest.php

# A single test method
./vendor/bin/phpunit --filter testMyMethod tests/unit/library/Episciences/Episciences_ToolsTest.php
```

### Coverage reports (PHP & JavaScript)

#### JavaScript (Jest) HTML report
```bash
# Generate the report
make test-js-coverage

# Open the visual HTML dashboard in your browser
xdg-open coverage/index.html
# or
google-chrome coverage/index.html
```
The report shows statement, branch, and line coverage percentages, with visual highlighting (green = covered, red = uncovered lines).

#### PHP (PHPUnit + Xdebug)

Xdebug is pre-installed in the `php-fpm` container (`xdebug.mode=off` by default) and enabled on demand with `XDEBUG_MODE=coverage`.

```bash
# Generate Clover XML (build/coverage.xml) & console summary for all suites
make test-coverage

# Target a single test file or suite (much faster than running the full suite)
make test-php-coverage TEST=tests/unit/library/Episciences/Tca/Manuscript/RepositoryManuscriptSourceTest.php

# Generate a navigable visual HTML report (build/coverage-html/index.html)
make test-php-coverage TEST=tests/unit/library/Episciences/Tca/Manuscript/RepositoryManuscriptSourceTest.php HTML=1

# Open in your browser
xdg-open build/coverage-html/index.html
```

#### New-code coverage check (85% target)

After generating `build/coverage.xml` (with `make test-php-coverage` or `make test-coverage`), check line coverage on added/modified PHP lines against a base branch:

```bash
# Compare against origin/main with the default 85% threshold
make test-diff-coverage

# Custom base branch or threshold
make test-diff-coverage BASE=main THRESHOLD=85
```

#### Online Coverage (Codecov)
On every pull request, coverage reports are uploaded to Codecov:
- **Dashboard:** https://app.codecov.io/gh/CCSDForge/episciences
- **Flags:** Filter by `flag:php` for backend coverage, or `flag:javascript` for frontend coverage.
- Pull request comments show coverage deltas (+/-%) and line-by-line diff annotations directly on GitHub.

### Static analysis (PHPStan)

```bash
# Default level
make phpstan

# Target a specific file/directory and override level (0–9)
make phpstan TARGET=library/Episciences/Tca LEVEL=6
```

## JavaScript tests (Jest)

```bash
# Run once
make test-js

# Watch mode (re-runs on file change)
make test-js-watch

# With coverage report
make test-js-coverage
```

JS test files live alongside the source files and follow the `*.test.js` convention.

## Directory structure

```
tests/
├── config/                       # Test configuration templates
├── e2e/                          # Playwright end-to-end tests
├── fixtures/                     # SQL schemas, seeds, and sample data
├── integration/                  # Integration tests
├── js/                           # JavaScript unit test helpers/suites
└── unit/
    ├── library/
    │   ├── Episciences/          # Tests for library/Episciences/
    │   └── Ccsd/                 # Tests for library/Ccsd/
    ├── modules/                  # Controller & module tests (ControllerTestCase)
    └── scripts/                  # CLI/worker script tests
```

## Writing new tests

- PHP test classes extend `PHPUnit\Framework\TestCase` (or `ControllerTestCase` for controller actions).
- Use namespaces matching the source structure and match the class name to the filename (e.g. `RepositoryManuscriptSourceTest` → `RepositoryManuscriptSourceTest.php`).
- Place the file under `tests/unit/` mirroring the source path (`library/`, `modules/`, `scripts/`).
- **DB-dependent tests** should be avoided in unit tests. Use mocks or skip with `$this->markTestSkipped()` when a real DB adapter is required.
- After creating a test file, set its permissions: `chmod 644 <file>`.

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| `No such file or directory: vendor/bin/phpunit` | Running on host | Use `make test-php` (Docker) |
| `Zend_Db_Table metadataCache` error | Missing writable cache dir | Normal in unit env — skip test or mock |
| `No entry is registered for key 'appLogger'` | Expected — logged during tests | Not an error, see `Log.php` catch block |