'use strict';

/**
 * Test suite for pure helper functions exported from
 * public/js/administratepaper/view.js.
 *
 * Covers:
 *  - DOI_PATTERN: regex matches expected valid / invalid DOI strings
 *  - validateDoiInput: empty, pattern-mismatch, and valid cases
 *  - updateDoiDisplay: DOM updates for #doi-link and div.paper-doi a
 *
 * Excluded from this suite:
 *  - getDoiForm: depends on jQuery Bootstrap popovers (getCommunForm),
 *    tested at a higher level (integration/E2E).
 */

// ---------------------------------------------------------------------------
// Globals required to load view.js without errors
// ---------------------------------------------------------------------------

// view.js contains a top-level $(document).ready(...) call.
// We provide a minimal jQuery-like stub so that module-load succeeds.
// The stub is intentionally thin — only the methods called at module scope
// need to be implemented; everything else can be a no-op.
const jQueryStub = () => ({
    ready: (fn) => { try { fn(); } catch (_e) { /* swallow errors from unrelated code */ } },
    prop: () => jQueryStub(),
    on: () => jQueryStub(),
    off: () => jQueryStub(),
    popover: () => jQueryStub(),
    data: () => null,
    html: () => jQueryStub(),
    text: () => jQueryStub(),
    serialize: () => '',
    find: () => jQueryStub(),
    val: () => '',
    hide: () => jQueryStub(),
    show: () => jQueryStub(),
    fadeIn: () => jQueryStub(),
    fadeOut: () => jQueryStub(),
    append: () => jQueryStub(),
    empty: () => jQueryStub(),
    remove: () => jQueryStub(),
    each: () => jQueryStub(),
    attr: () => null,
    css: () => jQueryStub(),
    closest: () => jQueryStub(),
    is: () => false,
    length: 0,
});
jQueryStub.type = () => 'string';
global.$ = jQueryStub;

// Other globals used by view.js function bodies.
global.sanitizeHTML = undefined;
global.getLoader = () => '';
global.translate = (s) => s;
global.ajaxRequest = undefined;
global.paper = { repository: 0, title: 'Test', id: { toString: () => '1' } };
global.review = { code: 'test', name: 'Test Review' };
global.locale = 'en';
global.author = '';
global.paper_ratings = '';
global.isRequiredRevisionDeadline = false;

// ---------------------------------------------------------------------------
// Load the module under test
// ---------------------------------------------------------------------------

const { validateDoiInput, updateDoiDisplay, DOI_PATTERN } = require('../../../public/js/administratepaper/view');

// ---------------------------------------------------------------------------
// DOI_PATTERN
// ---------------------------------------------------------------------------

describe('DOI_PATTERN', () => {
    test('is a RegExp', () => {
        expect(DOI_PATTERN).toBeInstanceOf(RegExp);
    });

    const valid = [
        '10.1234/suffix',
        '10.18452/23693',
        '10.1000/xyz123',
        '10.9999/ABC-DEF',
        '10.12345/a.b_c;d(e)f',
        '10.1234/UPPER',
        '10.1234/lower',
    ];
    valid.forEach((doi) => {
        test(`accepts valid DOI: ${doi}`, () => {
            expect(DOI_PATTERN.test(doi)).toBe(true);
        });
    });

    const invalid = [
        '',
        'not-a-doi',
        '10/nodot',
        '10.12/too-short-prefix',     // fewer than 4 digits after 10.
        '10.1234',                    // missing suffix after slash
        '20.1234/suffix',             // must start with 10.
        '10.abc/suffix',              // non-numeric registrant
    ];
    invalid.forEach((doi) => {
        test(`rejects invalid DOI: "${doi}"`, () => {
            expect(DOI_PATTERN.test(doi)).toBe(false);
        });
    });

    test('unescaped dot does not match arbitrary character', () => {
        // "10X1234/suffix" must NOT match — the dot must be literal.
        expect(DOI_PATTERN.test('10X1234/suffix')).toBe(false);
    });
});

// ---------------------------------------------------------------------------
// validateDoiInput
// ---------------------------------------------------------------------------

describe('validateDoiInput', () => {
    describe('empty / missing value', () => {
        test('empty string is invalid', () => {
            const result = validateDoiInput('');
            expect(result.valid).toBe(false);
            expect(result.error).toMatch(/empty/i);
        });

        test('empty string returns a non-empty error message', () => {
            expect(validateDoiInput('').error).toBeTruthy();
        });
    });

    describe('format mismatch', () => {
        test('plain text is invalid', () => {
            const result = validateDoiInput('not-a-doi');
            expect(result.valid).toBe(false);
            expect(result.error).toBeTruthy();
        });

        test('missing slash is invalid', () => {
            expect(validateDoiInput('10.1234').valid).toBe(false);
        });

        test('too-short registrant is invalid', () => {
            // registrant must have 4–9 digits; "10.12/suffix" has only 2
            expect(validateDoiInput('10.12/suffix').valid).toBe(false);
        });

        test('wrong prefix is invalid', () => {
            expect(validateDoiInput('11.1234/suffix').valid).toBe(false);
        });

        test('error message for format mismatch mentions expected format', () => {
            const { error } = validateDoiInput('not-a-doi');
            expect(error).toMatch(/10\./);
        });
    });

    describe('valid DOI', () => {
        test('standard DOI is valid', () => {
            const result = validateDoiInput('10.1234/suffix');
            expect(result.valid).toBe(true);
            expect(result.error).toBe('');
        });

        test('DOI with complex suffix is valid', () => {
            expect(validateDoiInput('10.18452/23693').valid).toBe(true);
        });

        test('DOI with uppercase suffix is valid (case-insensitive)', () => {
            expect(validateDoiInput('10.1234/UPPERCASE').valid).toBe(true);
        });

        test('DOI with lowercase suffix is valid', () => {
            expect(validateDoiInput('10.1234/lowercase').valid).toBe(true);
        });

        test('valid result has empty error string', () => {
            expect(validateDoiInput('10.1234/suffix').error).toBe('');
        });
    });
});

// ---------------------------------------------------------------------------
// updateDoiDisplay
// ---------------------------------------------------------------------------

describe('updateDoiDisplay', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    test('sets textContent of #doi-link with leading non-breaking space', () => {
        document.body.innerHTML = '<span id="doi-link"></span>';
        updateDoiDisplay('10.1234/test');
        expect(document.getElementById('doi-link').textContent).toBe('\u00A0' + '10.1234/test');
    });

    test('sets textContent of div.paper-doi a', () => {
        document.body.innerHTML = '<div class="paper-doi"><a href="#">old</a></div>';
        updateDoiDisplay('10.1234/test');
        expect(document.querySelector('div.paper-doi a').textContent).toBe('10.1234/test');
    });

    test('updates both elements when both are present', () => {
        document.body.innerHTML =
            '<span id="doi-link"></span>' +
            '<div class="paper-doi"><a href="#">old</a></div>';
        updateDoiDisplay('10.5678/new-doi');
        expect(document.getElementById('doi-link').textContent).toBe('\u00A010.5678/new-doi');
        expect(document.querySelector('div.paper-doi a').textContent).toBe('10.5678/new-doi');
    });

    test('does not throw when #doi-link is absent', () => {
        document.body.innerHTML = '<div class="paper-doi"><a href="#">old</a></div>';
        expect(() => updateDoiDisplay('10.1234/test')).not.toThrow();
    });

    test('does not throw when div.paper-doi a is absent', () => {
        document.body.innerHTML = '<span id="doi-link"></span>';
        expect(() => updateDoiDisplay('10.1234/test')).not.toThrow();
    });

    test('does not throw when both elements are absent', () => {
        expect(() => updateDoiDisplay('10.1234/test')).not.toThrow();
    });

    test('uses textContent — HTML tags are treated as plain text (XSS safety)', () => {
        document.body.innerHTML =
            '<span id="doi-link"></span>' +
            '<div class="paper-doi"><a href="#"></a></div>';
        const xssPayload = '10.1234/<img onerror=alert(1)>';
        updateDoiDisplay(xssPayload);

        const doiLink = document.getElementById('doi-link');
        // The value must be stored as text; no <img> element must be created.
        expect(doiLink.children).toHaveLength(0);
        expect(doiLink.textContent).toContain('<img');
    });
});
