const playwright = require('eslint-plugin-playwright');

// Only the Playwright E2E suite is linted: it catches guards (`if (await x.isVisible())`), missing
// awaits on assertions and fixed waits, which the suite forbids (see tests/e2e/README.md)
module.exports = [
    {
        files: ['tests/e2e/**/*.js'],
        ...playwright.configs['flat/recommended'],
        languageOptions: {
            ...playwright.configs['flat/recommended'].languageOptions,
            ecmaVersion: 2022,
            sourceType: 'commonjs',
            globals: {
                ...playwright.configs['flat/recommended'].languageOptions?.globals,
                require: 'readonly',
                module: 'writable',
                process: 'readonly',
                __dirname: 'readonly',
                console: 'readonly',
                URL: 'readonly',
                setTimeout: 'readonly',
            },
        },
    },
];
