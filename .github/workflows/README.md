# GitHub Actions Workflows

This directory contains automated CI/CD workflows for the Episciences project.

## Workflows

### 🔄 `ci.yml` - Continuous Integration
**Triggers:**
- **Pull requests and pushes to:** `main`, `preprod`, `preprod-epi-manager`, `main-epi-manager`, `staging`
- **Paths ignored:** `docs/**`, `**.md`, `.gitignore` (documentation-only changes skip CI to save runner minutes)

**Jobs:**
1. **Code Quality & Build** (Fast-fail, ~45s):
   - Dependency vulnerability checks (`composer audit --abandoned=ignore`)
   - Static analysis with **PHPStan** (runs in advisory mode with `--error-format=github`, non-blocking for legacy code)
   - Frontend asset compilation with **Webpack Encore** (`yarn build`)
2. **JavaScript Tests**:
   - Runs Jest tests under **Node.js 20.x LTS**
   - Collects coverage and uploads to Codecov (`flag:javascript`)
3. **PHP Tests (PHPUnit)**:
   - Matrix testing across **PHP 8.1, 8.2, and 8.3**
   - Standalone **MySQL 8.4** service container with automated schema/seed provisioning
   - Code coverage generated via Xdebug on **PHP 8.1** and uploaded to Codecov (`flag:php`)
   - Optimized PHP 8.2 & 8.3 execution with `--no-coverage`
   - Test suites separation (`unit` and `integration`) with `.phpunit.cache` caching
4. **Integration Status**:
   - Aggregates results from quality, JS, and PHP jobs
   - Updates a single marked comment (`<!-- ci-test-results -->`) on the pull request

---

### 🔍 `codeql-analysis.yml` - Security Analysis
**Triggers:** 
- **Pull requests and pushes to:** `main`, `preprod`, `preprod-epi-manager`, `main-epi-manager`, `staging`
- **Scheduled:** Weekly (Sundays at 22:00 UTC)

**Languages Analyzed:**
- **JavaScript/TypeScript** - Security vulnerabilities in frontend code
- **Actions** - Security analysis of GitHub Actions workflows

---

### 📊 `phpmetrics.yml` - Weekly Code Metrics
**Triggers:** Weekly (Sundays at midnight) & manual dispatch (`workflow_dispatch`).
- Computes maintainability metrics and deploys an interactive report to GitHub Pages.

---

## Code Coverage Reports

### Viewing Coverage Online (Codecov)
On every pull request, coverage reports are published to Codecov:
- **Dashboard:** [https://app.codecov.io/gh/CCSDForge/episciences](https://app.codecov.io/gh/CCSDForge/episciences)
- **Flags:**
  - `flag:php` : Backend coverage from PHPUnit
  - `flag:javascript` : Frontend coverage from Jest
- Pull request comments report coverage diffs and highlight modified lines in GitHub's *Files changed* tab.

### Viewing Coverage Locally

```bash
# --- JavaScript Coverage (Jest) ---
# Generate HTML report
make test-js-coverage

# Open visual HTML dashboard in your browser
xdg-open coverage/index.html

# --- PHP Coverage (PHPUnit) ---
# Generate Clover XML & terminal summary
make test-coverage

# Generate navigable visual HTML report
docker compose exec -u www-data -e XDEBUG_MODE=coverage php-fpm ./vendor/bin/phpunit --coverage-html build/coverage-html

# Open in your browser
xdg-open build/coverage-html/index.html
```

---

## Testing Commands in Makefile

| Command | Action |
| :--- | :--- |
| `make test` | Run all tests (PHP + JavaScript) |
| `make test-php` | Run all PHPUnit tests in the Docker container |
| `make test-php-unit` | Run PHP unit tests only (`--testsuite unit`) |
| `make test-php-integration` | Run PHP integration tests only (`--testsuite integration`) |
| `make test-php SUITE=unit FILTER=MyMethod` | Target a specific testsuite or filter pattern |
| `make test-js` | Run JavaScript tests (Jest) |
| `make test-js-watch` | Run JavaScript tests in watch mode |
| `make test-js-coverage` | Run JavaScript tests with coverage report |
| `make test-coverage` | Run all tests with coverage |