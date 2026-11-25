/**
 * Test suite for the paper list filter payload built by public/js/paper/submitted.js
 *
 * Focuses on checkFilterParams(), which decides whether the DataTable AJAX request
 * carries filters or not. Every filter must be present AND at least one must be set,
 * otherwise the unfiltered list is requested.
 */

const fs = require('fs');
const path = require('path');

// submitted.js registers a jQuery document-ready handler at load time. A no-op `ready`
// keeps that handler from running so the top-level function declarations can be evaluated
// without a DOM or a real jQuery.
global.$ = jest.fn(() => ({
    length: 0,
    ready: jest.fn(),
    on: jest.fn(),
    val: jest.fn(),
}));

const submittedJs = fs.readFileSync(
    path.join(__dirname, '../../../public/js/paper/submitted.js'),
    'utf8'
);

// eval() is safe and intentional here: the input is a source file from this repository,
// never external data, and submitted.js is a plain script with no module exports — this is
// the same loading pattern as tests/js/collapsible-message.test.js. eval returns the value
// of its last expression, i.e. the function under test.
// eslint-disable-next-line no-eval
const checkFilters = eval(submittedJs + '\n; checkFilterParams');

const ALL_FILTERS = [
    'status',
    'volume',
    'section',
    'editors',
    'ratingStatus',
    'reviewers',
    'doi',
    'repositories',
    'suggestion',
];

/**
 * Every filter present, all empty ("no filter selected") unless overridden.
 */
function emptyFilters(overrides = {}) {
    const filters = {};
    ALL_FILTERS.forEach(name => {
        filters[name] = [''];
    });
    return Object.assign(filters, overrides);
}

describe('checkFilterParams', function () {
    it('returns false when no filter is set', function () {
        expect(checkFilters(emptyFilters())).toBe(false);
    });

    it.each(ALL_FILTERS)('returns true when only %s is set', function (name) {
        expect(checkFilters(emptyFilters({ [name]: ['8'] }))).toBe(true);
    });

    it('returns true when the suggestion filter is set to a suggestion type', function () {
        // 8 = Episciences_CommentsManager::TYPE_SUGGESTION_ACCEPTATION
        expect(checkFilters(emptyFilters({ suggestion: ['8'] }))).toBe(true);
    });

    it('returns true when the suggestion filter is set to "any"', function () {
        expect(checkFilters(emptyFilters({ suggestion: ['any'] }))).toBe(true);
    });

    it.each(ALL_FILTERS)(
        'returns false when the %s key is missing altogether',
        function (name) {
            const filters = emptyFilters({ suggestion: ['8'] });
            delete filters[name];

            expect(checkFilters(filters)).toBe(false);
        }
    );

    it('returns false when a filter is present but holds an empty list', function () {
        expect(checkFilters(emptyFilters({ suggestion: [] }))).toBe(false);
    });

    it('defaults every filter to an empty list when called without arguments', function () {
        expect(checkFilters()).toBe(false);
    });
});
