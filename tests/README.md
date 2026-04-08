# Testing Guide

> **Requirement:** All PHP tests run inside the Docker container.
> Running `phpunit` directly on the host will fail (no database connection).

## Quick start

```bash
# Run all tests (PHP + JavaScript)
make test

# PHP tests only
make test-php

# JavaScript tests only (Jest)
make test-js
```

## PHP tests (PHPUnit)

Tests are executed inside the PHP container via `make test-php`, which runs:

```
docker compose exec <php-container> ./vendor/bin/phpunit
```

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

### Coverage report

```bash
make test-coverage
```

### Static analysis (PHPStan)

```bash
# Default level
make lint-php

# Override level (0–9)
make lint-php LEVEL=5
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
└── unit/
    └── library/
        ├── Episciences/          # Tests for library/Episciences/
        │   ├── paper/
        │   ├── user/
        │   └── View/
        │       └── Helper/
        └── Ccsd/                 # Tests for library/Ccsd/
```

## Writing new tests

- PHP test classes extend `PHPUnit\Framework\TestCase`.
- The class name must match the filename: `Episciences_ToolsTest` → `Episciences_ToolsTest.php`.
- Place the file under `tests/unit/library/` mirroring the source path under `library/`.
- **DB-dependent tests** should be avoided in unit tests. Use mocks or skip with `$this->markTestSkipped()` when a real DB adapter is required.
- After creating a test file, set its permissions: `chmod 644 <file>`.

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| `No such file or directory: vendor/bin/phpunit` | Running on host | Use `make test-php` (Docker) |
| `Zend_Db_Table metadataCache` error | Missing writable cache dir | Normal in unit env — skip test or mock |
| `No entry is registered for key 'appLogger'` | Expected — logged during tests | Not an error, see `Log.php` catch block |