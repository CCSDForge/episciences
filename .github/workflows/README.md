# GitHub Actions Workflows

This directory contains automated CI/CD workflows for the Episciences project.

## Workflows

### 🔄 `ci.yml` - Continuous Integration
**Triggers:** 
- **Pull requests to:** `main`, `preprod`, `preprod-epi-manager`, `main-epi-manager`
- **Pushes to:** `main`, `preprod`, `preprod-epi-manager`, `main-epi-manager`

**Jobs:**
- **PHP Tests** - ⏸️ Currently disabled (commented out)
- **JavaScript Tests** - Runs Jest tests on Node.js 18.x & 20.x  
- **Integration Status** - Reports JavaScript test results and comments on PRs

**Features:**
- ✅ Automated testing on every PR
- 📊 Code coverage reports (Codecov integration)  
- 💬 Automatic PR comments with test results
- 🔄 Matrix testing across multiple PHP/Node versions
- ⚡ Dependency caching for faster builds

### 🧪 `js-tests.yml` - JavaScript-only Tests
**Triggers:** Changes to JavaScript files on:
- **Pull requests to:** `main`, `preprod`, `preprod-epi-manager`, `main-epi-manager`
- **Pushes to:** `main`, `preprod`, `preprod-epi-manager`, `main-epi-manager`
- **File paths:** `public/js/**`, `tests/js/**`, `package.json`, `yarn.lock`

**Features:**
- 🎯 Focused testing - only runs when JS files change
- 📈 Coverage reports with thresholds (80% minimum)
- 🚀 Fast feedback for JS-only changes

### 🔍 `codeql-analysis.yml` - Security Analysis
**Triggers:** 
- **Pull requests to:** `main`, `preprod`, `preprod-epi-manager`, `main-epi-manager`
- **Pushes to:** `main`, `preprod`, `preprod-epi-manager`, `main-epi-manager`
- **Scheduled:** Weekly (Sundays at 22:00 UTC)

**Languages Analyzed:**
- **JavaScript** - Security vulnerabilities in frontend code

**Features:**
- 🛡️ Automated security vulnerability detection
- 📊 SARIF results uploaded to GitHub Security tab
- 🕒 Regular scheduled scans for continuous monitoring

## Usage

### For Developers
Tests run automatically when you:
1. **Open pull requests** to any protected branch (`main`, `preprod`, `preprod-epi-manager`, `main-epi-manager`)
2. **Push commits** to a PR branch  
3. **Push directly** to `main`, `preprod`, `preprod-epi-manager`, or `main-epi-manager` branches

### Branch Strategy
- **`main`** - Production branch, full CI on PRs and pushes
- **`preprod`** - Pre-production branch, full CI on PRs and pushes  
- **`preprod-epi-manager`** - Pre-production EPI manager branch, full CI on PRs and pushes
- **`main-epi-manager`** - Main EPI manager branch, full CI on PRs and pushes

### Coverage Requirements
- **JavaScript**: 75% branches, 60% functions, 85% lines/statements (actively enforced)
- **PHP**: Tests currently disabled

### Manual Testing
```bash
# Run JavaScript tests locally
yarn test

# Run with coverage
yarn test --coverage

# Run PHP tests locally  
./bin/phpunit

# Run with coverage
./bin/phpunit --coverage-html coverage-html
```

## Configuration

### Codecov (Optional)
To enable coverage reporting, add `CODECOV_TOKEN` to repository secrets:
1. Go to repository Settings → Secrets and variables → Actions
2. Add `CODECOV_TOKEN` with your Codecov project token

### Re-enabling PHP Tests
To re-enable PHP tests in the future:
1. Uncomment the `php-tests` job in `.github/workflows/ci.yml`
2. Update the `needs: [js-tests]` to `needs: [php-tests, js-tests]` in the integration job
3. Update the integration job's check and PR comment scripts to include PHP results

### Node.js/PHP Versions
Update the matrix in workflow files to test additional versions:
```yaml
strategy:
  matrix:
    node-version: [18.x, 20.x, 22.x]  # Add more versions
    php-version: [8.1, 8.2, 8.3]     # Add more versions
```

## Status Badges

Add these to your README.md:

```markdown
[![CI Status](https://github.com/your-org/episciences-gpl/workflows/Continuous%20Integration/badge.svg)](https://github.com/your-org/episciences-gpl/actions)
[![JS Tests](https://github.com/your-org/episciences-gpl/workflows/JavaScript%20Tests/badge.svg)](https://github.com/your-org/episciences-gpl/actions)
```