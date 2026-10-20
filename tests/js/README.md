# JavaScript Testing

This directory contains JavaScript unit tests using Jest.

## Running Tests

### Install dependencies first:
```bash
yarn install
```

### Run all JavaScript tests:
```bash
yarn test
```

### Run tests in watch mode (automatically re-run on file changes):
```bash
yarn test:watch
```

### Run specific test file:
```bash
yarn jest tests/js/isEmail.test.js
```

### Run with coverage:
```bash
yarn test --coverage
```

## Automated Testing (CI/CD)

JavaScript tests run automatically on:
- 🔄 **Pull Requests** - Every PR to `main`, `preprod`, `preprod-epi-manager`, `main-epi-manager`
- 🚀 **Direct pushes** - To `main`, `preprod`, `preprod-epi-manager` branches  
- 📁 **File changes** - Smart triggers on `public/js/**` and `tests/js/**` changes

**Coverage Requirements:** 80% minimum for branches, functions, lines, and statements.

See `.github/workflows/` for CI configuration.

## Test Structure

- `tests/js/` - Test files (`.test.js` extension)
- Tests for functions extracted from `public/js/` files

## Current Tests

- `isEmail.test.js` - Tests for the email validation function
  - Validates correct email formats
  - Rejects invalid email patterns  
  - Tests performance and security (ReDoS protection)
  - 17 test cases covering edge cases and real-world examples

- `submitIdentifier.test.js` - Tests for `public/js/submit/identifier.js` (imported directly, no copy)
  - `SubmitIdentifier.parse()` - Extracts the identifier and the version from a pasted identifier or URL
  - `SubmitIdentifier.applyVersion()` - Fills the version field, never clearing a version typed by the author
  - Covers HAL, arXiv, bioRxiv/medRxiv, Zenodo, IACR ePrint, handle, BAOBAB and Dataverse URLs, direct identifiers

- `submitFunctions.test.js` - Tests for submit form processing functions
  - `setPlaceholder()` - Sets form placeholder text based on repository type (8 tests)

- `checkDataverse.test.js` - Tests for dataverse repository detection
  - `checkDataverse()` - Async function that checks if repository is Dataverse type (13 tests)
  - Tests fetch API calls, response handling, DOM updates
  - Covers network errors, JSON parsing errors, and edge cases
  - Mock fetch implementation for isolated testing

## Adding New Tests

1. Extract function from `public/js/` files into a testable module in `tests/js/`
2. Create corresponding `.test.js` file
3. Run `yarn test` to execute

Example test structure:
```javascript
const { functionName } = require('./functionName');

describe('functionName', () => {
  test('should do something', () => {
    expect(functionName('input')).toBe('expected');
  });
});
```