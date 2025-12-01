/**
 * Jest setup file for DOM testing
 */

// Set up global fetch mock
global.fetch = jest.fn();

// Mock console methods to avoid noise in tests
global.console = {
    ...console,
    log: jest.fn(),
    debug: jest.fn(),
    info: jest.fn(),
    warn: jest.fn(),
    error: jest.fn(),
};

// Mock global functions that functions.js expects
global.translate = function (text, locale) {
    return text; // Simple passthrough for tests
};

// Mock jQuery-like $ function for functions.js compatibility
global.$ = function (selector) {
    // Minimal jQuery mock for tests
    const element = {
        attr: () => element,
        tooltip: () => element,
        prepend: () => element,
        find: () => element,
        hasClass: () => false,
        ready: callback => {
            // For document ready, call immediately in test environment
            if (typeof callback === 'function') {
                callback();
            }
            return element;
        },
    };
    return element;
};

// Mock CSS.escape for tests (not available in jsdom)
global.CSS = {
    escape: str => {
        // Simple polyfill for CSS.escape
        return str.replace(/[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, '\\$&');
    },
};

// Clean up after each test
afterEach(() => {
    jest.clearAllMocks();
    document.body.innerHTML = '';
    document.head.innerHTML = '';

    // Clear any global state
    if (window.AffiliationsAutocomplete) {
        if (window.AffiliationsAutocomplete.cache) {
            window.AffiliationsAutocomplete.cache.clear();
        }
        if (window.AffiliationsAutocomplete.cacheAcronym) {
            window.AffiliationsAutocomplete.cacheAcronym.length = 0;
        }
    }
});
