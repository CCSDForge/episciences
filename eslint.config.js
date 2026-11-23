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
        rules: {
            ...playwright.configs['flat/recommended'].rules,
            // The suite also runs in manager mode, where every journal URL carries the journal prefix
            // (/dev/...): application paths stay relative to the base URL (tests/e2e/helpers/app-url.js)
            'no-restricted-syntax': [
                'error',
                {
                    selector: "CallExpression[callee.property.name=/^(goto|get|post)$/] > Literal[value=/^\\//]",
                    message: 'Use a path relative to the base URL (no leading "/"): the suite also runs under a journal prefix.',
                },
                {
                    selector: "CallExpression[callee.property.name=/^(goto|get|post)$/] > TemplateLiteral > TemplateElement:first-child[value.raw=/^\\//]",
                    message: 'Use a path relative to the base URL (no leading "/"): the suite also runs under a journal prefix.',
                },
                {
                    selector: "CallExpression[callee.property.name='replace'] > Literal.arguments:first-child[regex.pattern=/^\\^\\\\\\//]",
                    message: 'Use toAppPath() from tests/e2e/helpers/app-url.js to turn an application link into a relative path.',
                },
            ],
        },
    },
];
