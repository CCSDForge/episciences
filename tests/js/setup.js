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
    error: jest.fn()
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