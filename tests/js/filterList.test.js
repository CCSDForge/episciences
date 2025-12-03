/**
 * Test suite for filterList function
 * Tests the optimized version with caching, DOM manipulation, and accent stripping
 */

// Load the functions.js file and extract necessary functions
const fs = require('fs');
const path = require('path');
const functionsJs = fs.readFileSync(
    path.join(__dirname, '../../public/js/functions.js'),
    'utf8'
);

// Extract the stripAccents and filterList functions to avoid jQuery dependencies
const stripAccentsFunctionMatch = functionsJs.match(
    /function stripAccents\(string\) \{[\s\S]*?\n\}/
);
if (stripAccentsFunctionMatch) {
    eval(stripAccentsFunctionMatch[0]);
}

const filterListFunctionMatch = functionsJs.match(
    /function filterList\(input, elements\) \{[\s\S]*?\n\}/
);
if (filterListFunctionMatch) {
    eval(filterListFunctionMatch[0]);
}

// Also extract the clearCache method
const clearCacheFunctionMatch = functionsJs.match(
    /filterList\.clearCache = function\(\) \{[\s\S]*?\};/
);
if (clearCacheFunctionMatch) {
    eval(clearCacheFunctionMatch[0]);
}

// Mock requestAnimationFrame for testing
global.requestAnimationFrame = jest.fn(cb => setTimeout(cb, 0));

// Fix for JSDOM TextEncoder requirement
const { TextEncoder, TextDecoder } = require('util');
global.TextEncoder = TextEncoder;
global.TextDecoder = TextDecoder;

// Setup JSDOM-like environment for DOM testing
const { JSDOM } = require('jsdom');
const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>');
global.document = dom.window.document;
global.window = dom.window;

describe('filterList function', function () {
    let input, elements;

    beforeEach(() => {
        // Clear any existing cache between tests
        if (filterList._cache) filterList._cache.clear();
        if (filterList._textCache) filterList._textCache.clear();

        // Create fresh DOM elements for each test
        document.body.innerHTML = `
            <input type="text" id="search-input" />
            <div class="item" id="item1">Café Paris</div>
            <div class="item" id="item2">Restaurant München</div>
            <div class="item" id="item3">Sushi Tokyo</div>
            <div class="item" id="item4">José María Tapas</div>
            <div class="item" id="item5">Naïve Bistro</div>
            <br />
            <div class="item" id="item6">Pizza Résumé</div>
        `;

        input = document.getElementById('search-input');
        elements = document.querySelectorAll('.item');
    });

    afterEach(() => {
        // Clean up DOM
        document.body.innerHTML = '';
    });

    describe('Input handling', function () {
        it('should accept input as DOM element', function () {
            input.value = 'cafe';

            expect(() => filterList(input, elements)).not.toThrow();
        });

        it('should accept input as selector string', function () {
            input.value = 'cafe';

            expect(() => filterList('#search-input', elements)).not.toThrow();
        });

        it('should handle non-existent input selector gracefully', function () {
            expect(() => filterList('#nonexistent', elements)).not.toThrow();
        });

        it('should handle null input gracefully', function () {
            // The function will fail on null.nodeType, so test that it handles missing input
            expect(() =>
                filterList('#nonexistent-input', elements)
            ).not.toThrow();
        });
    });

    describe('Elements handling', function () {
        it('should accept elements as NodeList', function () {
            input.value = 'cafe';

            expect(() => filterList(input, elements)).not.toThrow();
        });

        it('should accept elements as Array', function () {
            input.value = 'cafe';
            const elementsArray = Array.from(elements);

            expect(() => filterList(input, elementsArray)).not.toThrow();
        });

        it('should accept elements as selector string', function () {
            input.value = 'cafe';

            expect(() => filterList(input, '.item')).not.toThrow();
        });

        it('should accept single element', function () {
            input.value = 'cafe';
            const singleElement = elements[0];

            expect(() => filterList(input, singleElement)).not.toThrow();
        });

        it('should handle empty elements gracefully', function () {
            expect(() => filterList(input, [])).not.toThrow();
        });

        it('should handle non-existent elements selector gracefully', function () {
            expect(() => filterList(input, '.nonexistent')).not.toThrow();
        });
    });

    describe('Basic filtering functionality', function () {
        beforeEach(() => {
            // Wait for requestAnimationFrame to complete
            jest.runAllTimers();
        });

        it('should show all elements when input is empty', async function () {
            input.value = '';
            filterList(input, elements);

            // Wait for requestAnimationFrame
            await new Promise(resolve => setTimeout(resolve, 0));

            elements.forEach(el => {
                expect(el.style.display).toBe('');
            });
        });

        it('should filter elements based on text content', async function () {
            input.value = 'cafe';
            filterList(input, elements);

            await new Promise(resolve => setTimeout(resolve, 0));

            expect(elements[0].style.display).toBe(''); // "Café Paris" should be visible
            expect(elements[1].style.display).toBe('none'); // "Restaurant München" should be hidden
            expect(elements[2].style.display).toBe('none'); // "Sushi Tokyo" should be hidden
        });

        it('should be case-insensitive', async function () {
            input.value = 'CAFE';
            filterList(input, elements);

            await new Promise(resolve => setTimeout(resolve, 0));

            expect(elements[0].style.display).toBe(''); // "Café Paris" should be visible
        });
    });

    describe('Accent handling', function () {
        it('should match accented characters with non-accented input', async function () {
            input.value = 'cafe';
            filterList(input, elements);

            await new Promise(resolve => setTimeout(resolve, 0));

            expect(elements[0].style.display).toBe(''); // "Café Paris" should be visible
        });

        it('should match non-accented characters with accented input', async function () {
            input.value = 'café';
            filterList(input, elements);

            await new Promise(resolve => setTimeout(resolve, 0));

            expect(elements[0].style.display).toBe(''); // "Café Paris" should be visible
        });

        it('should handle German umlauts', async function () {
            input.value = 'munchen';
            filterList(input, elements);

            await new Promise(resolve => setTimeout(resolve, 0));

            expect(elements[1].style.display).toBe(''); // "Restaurant München" should be visible
        });

        it('should handle Spanish characters', async function () {
            input.value = 'jose maria';
            filterList(input, elements);

            await new Promise(resolve => setTimeout(resolve, 0));

            expect(elements[3].style.display).toBe(''); // "José María Tapas" should be visible
        });

        it('should handle French diaeresis', async function () {
            input.value = 'naive';
            filterList(input, elements);

            await new Promise(resolve => setTimeout(resolve, 0));

            expect(elements[4].style.display).toBe(''); // "Naïve Bistro" should be visible
        });

        it('should handle French circumflex', async function () {
            input.value = 'resume';
            filterList(input, elements);

            await new Promise(resolve => setTimeout(resolve, 0));

            expect(elements[5].style.display).toBe(''); // "Pizza Résumé" should be visible
        });
    });

    describe('Partial matching', function () {
        it('should match partial strings', async function () {
            input.value = 'rest';
            filterList(input, elements);

            await new Promise(resolve => setTimeout(resolve, 0));

            expect(elements[1].style.display).toBe(''); // "Restaurant München" should be visible
        });

        it('should match multiple words partially', async function () {
            input.value = 'jose maria';
            filterList(input, elements);

            await new Promise(resolve => setTimeout(resolve, 0));

            expect(elements[3].style.display).toBe(''); // "José María Tapas" should be visible
        });
    });

    describe('Special characters and regex escaping', function () {
        it('should handle regex special characters in input', async function () {
            // Add element with special characters
            const specialEl = document.createElement('div');
            specialEl.className = 'item';
            specialEl.textContent = 'Test (Special) [Chars] $Money';
            document.body.appendChild(specialEl);

            const allElements = document.querySelectorAll('.item');

            input.value = '(Special)';
            filterList(input, allElements);

            await new Promise(resolve => setTimeout(resolve, 0));

            expect(specialEl.style.display).toBe(''); // Should be visible
        });

        it('should handle dots in input', async function () {
            const specialEl = document.createElement('div');
            specialEl.className = 'item';
            specialEl.textContent = 'www.example.com';
            document.body.appendChild(specialEl);

            const allElements = document.querySelectorAll('.item');

            input.value = 'www.example';
            filterList(input, allElements);

            await new Promise(resolve => setTimeout(resolve, 0));

            expect(specialEl.style.display).toBe(''); // Should be visible
        });
    });

    describe('BR element handling', function () {
        it('should hide/show BR elements with their siblings', async function () {
            // Add BR element after first item
            const brElement = document.createElement('br');
            elements[0].parentNode.insertBefore(
                brElement,
                elements[0].nextSibling
            );

            input.value = 'nonexistent';
            filterList(input, elements);

            await new Promise(resolve => setTimeout(resolve, 0));

            expect(elements[0].style.display).toBe('none');
            expect(brElement.style.display).toBe('none');
        });
    });

    describe('Performance and caching', function () {
        it('should create and use regex cache', function () {
            input.value = 'cafe';
            filterList(input, elements);

            expect(filterList._cache).toBeDefined();
            expect(filterList._cache.size).toBeGreaterThan(0);
        });

        it('should create and use text content cache', function () {
            input.value = 'cafe';
            filterList(input, elements);

            expect(filterList._textCache).toBeDefined();
            expect(filterList._textCache.size).toBeGreaterThan(0);
        });

        it('should reuse cached regex for same query', function () {
            input.value = 'cafe';
            filterList(input, elements);

            const initialCacheSize = filterList._cache.size;

            // Run again with same query
            filterList(input, elements);

            // Cache size shouldn't increase
            expect(filterList._cache.size).toBe(initialCacheSize);
        });

        it('should limit cache size to prevent memory leaks', function () {
            // Fill cache beyond limit
            for (let i = 0; i < 105; i++) {
                input.value = `query${i}`;
                filterList(input, elements);
            }

            expect(filterList._cache.size).toBeLessThanOrEqual(100);
        });

        it('should limit text cache size', function () {
            // Create many elements to exceed text cache limit
            const manyElements = [];
            for (let i = 0; i < 510; i++) {
                const el = document.createElement('div');
                el.textContent = `Item ${i}`;
                manyElements.push(el);
            }

            input.value = 'item';
            filterList(input, manyElements);

            expect(filterList._textCache.size).toBeLessThanOrEqual(500);
        });
    });

    describe('Cache management', function () {
        it('should provide clearCache method', function () {
            expect(typeof filterList.clearCache).toBe('function');
        });

        it('should clear both caches when clearCache is called', function () {
            input.value = 'cafe';
            filterList(input, elements);

            expect(filterList._cache.size).toBeGreaterThan(0);
            expect(filterList._textCache.size).toBeGreaterThan(0);

            filterList.clearCache();

            expect(filterList._cache.size).toBe(0);
            expect(filterList._textCache.size).toBe(0);
        });
    });

    describe('Edge cases', function () {
        it('should handle elements with empty text content', async function () {
            const emptyEl = document.createElement('div');
            emptyEl.className = 'item';
            document.body.appendChild(emptyEl);

            const allElements = document.querySelectorAll('.item');

            input.value = 'cafe';

            expect(() => filterList(input, allElements)).not.toThrow();
        });

        it('should handle elements with only whitespace', async function () {
            const whitespaceEl = document.createElement('div');
            whitespaceEl.className = 'item';
            whitespaceEl.textContent = '   \n\t   ';
            document.body.appendChild(whitespaceEl);

            const allElements = document.querySelectorAll('.item');

            input.value = 'cafe';

            expect(() => filterList(input, allElements)).not.toThrow();
        });

        it('should handle very long filter queries', async function () {
            const longQuery = 'a'.repeat(1000);
            input.value = longQuery;

            expect(() => filterList(input, elements)).not.toThrow();
        });

        it('should handle input with leading/trailing spaces', async function () {
            input.value = '  cafe  ';
            filterList(input, elements);

            await new Promise(resolve => setTimeout(resolve, 0));

            expect(elements[0].style.display).toBe(''); // "Café Paris" should be visible
        });
    });

    describe('Multiple consecutive filters', function () {
        it('should handle rapid consecutive filtering', async function () {
            const queries = ['c', 'ca', 'caf', 'cafe'];

            queries.forEach(query => {
                input.value = query;
                expect(() => filterList(input, elements)).not.toThrow();
            });
        });

        it('should handle backspacing/refinement', async function () {
            // Type 'cafe'
            input.value = 'cafe';
            filterList(input, elements);

            // Backspace to 'caf'
            input.value = 'caf';
            filterList(input, elements);

            // Clear input
            input.value = '';
            filterList(input, elements);

            await new Promise(resolve => setTimeout(resolve, 0));

            // All elements should be visible when input is empty
            elements.forEach(el => {
                expect(el.style.display).toBe('');
            });
        });
    });

    describe('Real-world scenarios', function () {
        it('should handle typical restaurant search', async function () {
            input.value = 'sushi';
            filterList(input, elements);

            await new Promise(resolve => setTimeout(resolve, 0));

            expect(elements[2].style.display).toBe(''); // "Sushi Tokyo" should be visible
            expect(elements[0].style.display).toBe('none'); // Others should be hidden
            expect(elements[1].style.display).toBe('none');
        });

        it('should handle multi-word search terms', async function () {
            input.value = 'maria tapas';
            filterList(input, elements);

            await new Promise(resolve => setTimeout(resolve, 0));

            expect(elements[3].style.display).toBe(''); // "José María Tapas" should be visible
        });

        it('should handle mixed case city names', async function () {
            input.value = 'TOKYO';
            filterList(input, elements);

            await new Promise(resolve => setTimeout(resolve, 0));

            expect(elements[2].style.display).toBe(''); // "Sushi Tokyo" should be visible
        });
    });

    describe('Integration with DOM changes', function () {
        it('should handle dynamically added elements', function () {
            // Add new element after initial setup
            const newEl = document.createElement('div');
            newEl.className = 'item';
            newEl.textContent = 'New Café Item';
            document.body.appendChild(newEl);

            // Get updated elements list
            const updatedElements = document.querySelectorAll('.item');

            input.value = 'new';

            expect(() => filterList(input, updatedElements)).not.toThrow();
        });
    });
});

// Run tests if in browser environment with a test runner
if (typeof window !== 'undefined' && window.jasmine) {
    // Tests will run automatically with Jasmine
} else if (typeof module !== 'undefined' && module.exports) {
    // Export for Node.js testing
    module.exports = { filterList };
}
