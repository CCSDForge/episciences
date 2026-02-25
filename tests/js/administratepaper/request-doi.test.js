/**
 * Test suite for public/js/administratepaper/request-doi.js
 *
 * Covers:
 *  - parseDoiResponse: valid / invalid / edge-case JSON
 *  - safeHtml: sanitizeHTML delegation and fallback
 *  - createAlert: element structure and XSS safety
 *  - init: missing button guard, click handler wiring
 *  - requestNewDoi (via button click): success flow, error flags,
 *    HTTP errors, network errors, JSON parse errors, abort-on-double-click
 */

'use strict';

// ---------------------------------------------------------------------------
// Global mocks required before requiring the module
// ---------------------------------------------------------------------------

// Mock sanitizeHTML (simulates the DOMPurify wrapper from sanitizer.js).
global.sanitizeHTML = jest.fn(html => `[sanitized:${html}]`);

// Mock getLoader (referenced but not defined in this file).
global.getLoader = jest.fn(() => '<span class="spinner"></span>');

// ---------------------------------------------------------------------------
// Load the module
// ---------------------------------------------------------------------------

const { parseDoiResponse, createAlert, safeHtml, init, _internals } = require('../../../public/js/administratepaper/request-doi');

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Build the standard DOM fixture used by most click-handler tests. */
function buildFixture({ docid = '42', hiddenLoader = true, paperid = '100', doiAssignMode = 'automatic' } = {}) {
    document.body.innerHTML = `
        <div id="doi-panel" data-paperid="${paperid}" data-doi-assign-mode="${doiAssignMode}" data-docid="${docid}">
            <div id="doi-status-loader"${hiddenLoader ? ' hidden' : ''}></div>
            <span id="doi-link"></span>
            <div id="doi-status"></div>
            <button id="requestNewDoi" data-docid="${docid}">Request DOI</button>
            <div id="doi-action">Action buttons</div>
        </div>
    `;
}

/** Simulate a resolved fetch with the given JSON payload. */
function mockFetchSuccess(payload) {
    global.fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        text: () => Promise.resolve(JSON.stringify(payload)),
    });
}

/** Simulate a resolved fetch whose response body is a raw string (not JSON). */
function mockFetchRawText(text) {
    global.fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        text: () => Promise.resolve(text),
    });
}

/** Simulate an HTTP error response (e.g. 500). */
function mockFetchHttpError(status = 500) {
    global.fetch.mockResolvedValueOnce({
        ok: false,
        status,
        text: () => Promise.resolve(''),
    });
}

/** Simulate a network-level failure (fetch throws). */
function mockFetchNetworkError() {
    global.fetch.mockRejectedValueOnce(new TypeError('Failed to fetch'));
}

/** Click the button and wait for all microtasks/promises to settle. */
async function clickAndWait() {
    document.getElementById('requestNewDoi').click();
    // Flush the promise queue (three ticks: fetch resolution + text() + async chain).
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();
}

// ---------------------------------------------------------------------------
// Shared beforeEach / afterEach
// ---------------------------------------------------------------------------

beforeEach(() => {
    jest.clearAllMocks();
    // Replace the reload wrapper with a fresh mock before each test.
    _internals.reload = jest.fn();
});

// ===========================================================================
// parseDoiResponse
// ===========================================================================

describe('parseDoiResponse', () => {
    describe('valid JSON objects', () => {
        test('returns the parsed object for a well-formed response', () => {
            const json = JSON.stringify({
                doi: '10.1234/test',
                doi_status: '<p>Assigned</p>',
                feedback: 'DOI successfully assigned.',
                error_message: '',
            });
            const result = parseDoiResponse(json);
            expect(result).not.toBeNull();
            expect(result.doi).toBe('10.1234/test');
            expect(result.doi_status).toBe('<p>Assigned</p>');
            expect(result.feedback).toBe('DOI successfully assigned.');
        });

        test('returns the parsed object when both fields signal Error', () => {
            const json = JSON.stringify({
                doi: 'Error',
                doi_status: 'Error',
                feedback: '',
                error_message: 'Something went wrong.',
            });
            const result = parseDoiResponse(json);
            expect(result).not.toBeNull();
            expect(result.doi).toBe('Error');
            expect(result.error_message).toBe('Something went wrong.');
        });
    });

    describe('invalid input', () => {
        test.each([
            ['empty string', ''],
            ['plain text', 'not json at all'],
            ['malformed JSON', '{doi: "missing quotes"}'],
            ['null literal', 'null'],
            ['number literal', '42'],
            ['string literal', '"just a string"'],
            ['boolean literal', 'true'],
        ])('returns null for %s', (_label, input) => {
            expect(parseDoiResponse(input)).toBeNull();
        });

        test('returns null for a JSON array', () => {
            expect(parseDoiResponse('[{"doi":"x"}]')).toBeNull();
        });

        test('returns null when called with null', () => {
            // JSON.parse(null) → JSON.parse("null") → null; our guard catches it.
            expect(parseDoiResponse(null)).toBeNull();
        });
    });
});

// ===========================================================================
// safeHtml
// ===========================================================================

describe('safeHtml', () => {
    test('delegates to sanitizeHTML when available', () => {
        const result = safeHtml('<b>bold</b>');
        expect(sanitizeHTML).toHaveBeenCalledWith('<b>bold</b>');
        expect(result).toBe('[sanitized:<b>bold</b>]');
    });

    describe('falls back to textContent encoding when sanitizeHTML is absent', () => {
        let savedSanitizeHTML;
        beforeEach(() => {
            savedSanitizeHTML = global.sanitizeHTML;
            global.sanitizeHTML = undefined;
        });
        afterEach(() => {
            global.sanitizeHTML = savedSanitizeHTML;
        });

        test('encodes the HTML string so no script tag is present', () => {
            const result = safeHtml('<script>alert(1)</script>');
            expect(result).not.toContain('<script>');
            expect(result).toContain('&lt;script&gt;');
        });
    });

    test('handles an empty string', () => {
        const result = safeHtml('');
        expect(result).toBe('[sanitized:]');
    });
});

// ===========================================================================
// createAlert
// ===========================================================================

describe('createAlert', () => {
    test('creates a div with role="alert"', () => {
        const el = createAlert('success', 'Great!');
        expect(el.tagName).toBe('DIV');
        expect(el.getAttribute('role')).toBe('alert');
    });

    test('applies the correct Bootstrap classes for success', () => {
        const el = createAlert('success', 'OK');
        expect(el.className).toContain('alert-success');
        expect(el.className).toContain('alert-dismissible');
    });

    test('applies the correct Bootstrap classes for danger', () => {
        const el = createAlert('danger', 'Oops');
        expect(el.className).toContain('alert-danger');
        expect(el.className).toContain('alert-dismissible');
    });

    test('passes the message through sanitizeHTML', () => {
        sanitizeHTML.mockReturnValueOnce('safe content');
        const el = createAlert('success', '<b>message</b>');
        expect(sanitizeHTML).toHaveBeenCalledWith('<b>message</b>');
        expect(el.innerHTML).toBe('safe content');
    });

    describe('falls back to plain-text rendering when sanitizeHTML is absent', () => {
        let savedSanitizeHTML;
        beforeEach(() => {
            savedSanitizeHTML = global.sanitizeHTML;
            global.sanitizeHTML = undefined;
        });
        afterEach(() => {
            global.sanitizeHTML = savedSanitizeHTML;
        });

        test('does not create a real <img> element that could execute onerror', () => {
            const el = createAlert('danger', '<img src=x onerror=alert(1)>');
            // The fallback sets content via textContent; the raw <img> tag must
            // not be parsed into the DOM — checking for the element is definitive.
            expect(el.querySelector('img')).toBeNull();
            // The markup must also not contain an un-encoded opening tag.
            expect(el.innerHTML).not.toContain('<img');
        });
    });
});

// ===========================================================================
// init — button wiring
// ===========================================================================

describe('init', () => {
    test('does nothing when #requestNewDoi is absent', () => {
        document.body.innerHTML = '<div id="doi-status"></div>';
        expect(() => init()).not.toThrow();
    });

    test('attaches a click listener when the button is present', () => {
        buildFixture();
        init();
        global.fetch.mockResolvedValueOnce({
            ok: true,
            status: 200,
            text: () => Promise.resolve('{}'),
        });
        const button = document.getElementById('requestNewDoi');
        expect(() => button.click()).not.toThrow();
    });

    test('does not fire a fetch when data-docid is empty', async () => {
        buildFixture({ docid: '' });
        init();
        document.getElementById('requestNewDoi').click();
        await Promise.resolve();
        expect(global.fetch).not.toHaveBeenCalled();
    });
});

// ===========================================================================
// Click handler — loader visibility
// ===========================================================================

describe('loader behaviour on click', () => {
    test('shows the loader when the button is clicked', async () => {
        buildFixture();
        init();
        mockFetchSuccess({
            doi: '10.1/ok',
            doi_status: '<p>ok</p>',
            feedback: 'Assigned',
            error_message: '',
        });
        const loader = document.getElementById('doi-status-loader');
        expect(loader.hidden).toBe(true);

        document.getElementById('requestNewDoi').click();

        // Loader should be visible immediately after click (before fetch resolves).
        expect(loader.hidden).toBe(false);
        await clickAndWait(); // let promises settle
    });

    test('injects getLoader() markup into the loader element', () => {
        buildFixture();
        init();
        global.fetch.mockResolvedValueOnce({
            ok: true,
            status: 200,
            text: () => new Promise(() => {}), // never resolves — keeps loader visible
        });
        document.getElementById('requestNewDoi').click();
        const loader = document.getElementById('doi-status-loader');
        expect(loader.innerHTML).toBe('<span class="spinner"></span>');
    });

    test('hides the loader when the server returns an error flag', async () => {
        buildFixture();
        init();
        mockFetchSuccess({
            doi: 'Error',
            doi_status: 'Error',
            feedback: '',
            error_message: 'DOI assignment failed.',
        });
        await clickAndWait();
        expect(document.getElementById('doi-status-loader').hidden).toBe(true);
    });
});

// ===========================================================================
// Success flow
// ===========================================================================

describe('success flow', () => {
    const successPayload = {
        doi: '10.5802/xyz',
        doiStr: '10.5802/xyz',
        doi_status: '<p>Status: assigned</p>',
        feedback: 'Your DOI has been assigned.',
        error_message: '',
    };

    beforeEach(async () => {
        buildFixture();
        init();
        mockFetchSuccess(successPayload);
        await clickAndWait();
    });

    test('sends a POST to the correct endpoint', () => {
        expect(global.fetch).toHaveBeenCalledWith(
            '/administratepaper/ajaxrequestnewdoi',
            expect.objectContaining({ method: 'POST' })
        );
    });

    test('includes the docid in the request body', () => {
        const callArgs = global.fetch.mock.calls[0][1];
        expect(callArgs.body.toString()).toContain('docid=42');
    });

    test('sets Content-Type to application/x-www-form-urlencoded', () => {
        const callArgs = global.fetch.mock.calls[0][1];
        expect(callArgs.headers['Content-Type']).toBe(
            'application/x-www-form-urlencoded'
        );
    });

    test('sets X-Requested-With header so PHP isXmlHttpRequest() returns true', () => {
        const callArgs = global.fetch.mock.calls[0][1];
        expect(callArgs.headers['X-Requested-With']).toBe('XMLHttpRequest');
    });

    test('updates #doi-link with the sanitized DOI HTML', () => {
        const doiLink = document.getElementById('doi-link');
        // sanitizeHTML mock returns '[sanitized:<input>]'
        expect(doiLink.innerHTML).toBe('[sanitized:10.5802/xyz]');
    });

    test('updates #doi-status with sanitized doi_status HTML', () => {
        expect(sanitizeHTML).toHaveBeenCalledWith(successPayload.doi_status);
    });

    test('does not prepend a success alert to #doi-status (DOI link is visible feedback)', () => {
        const doiStatus = document.getElementById('doi-status');
        expect(doiStatus.querySelector('.alert-success')).toBeNull();
    });

    test('removes #requestNewDoi from the DOM', () => {
        expect(document.getElementById('requestNewDoi')).toBeNull();
    });

    test('removes #doi-action from the DOM', () => {
        expect(document.getElementById('doi-action')).toBeNull();
    });

    test('does NOT call location.reload()', () => {
        expect(_internals.reload).not.toHaveBeenCalled();
    });

    test('hides the loader after a successful response', () => {
        expect(document.getElementById('doi-status-loader').hidden).toBe(true);
    });

    test('inserts a #removeDoi button after #doi-link', () => {
        expect(document.getElementById('removeDoi')).not.toBeNull();
    });

    test('#removeDoi button carries data-paperid and data-docid from the panel', () => {
        const btn = document.getElementById('removeDoi');
        expect(btn.dataset.paperid).toBe('100');
        expect(btn.dataset.docid).toBe('42');
    });
});

// ===========================================================================
// Server-side error flags
// ===========================================================================

describe('server-side error flags', () => {
    async function triggerError(payload) {
        buildFixture();
        init();
        mockFetchSuccess(payload);
        await clickAndWait();
    }

    test('shows a danger alert when doi === "Error"', async () => {
        await triggerError({
            doi: 'Error',
            doi_status: '<p>ok</p>',
            feedback: '',
            error_message: 'DOI assignment failed.',
        });
        const alert = document.querySelector('.alert-danger');
        expect(alert).not.toBeNull();
    });

    test('shows a danger alert when doi_status === "Error"', async () => {
        await triggerError({
            doi: '10.1/ok',
            doi_status: 'Error',
            feedback: '',
            error_message: 'Status update failed.',
        });
        const alert = document.querySelector('.alert-danger');
        expect(alert).not.toBeNull();
    });

    test('shows a danger alert when both fields are "Error"', async () => {
        await triggerError({
            doi: 'Error',
            doi_status: 'Error',
            feedback: '',
            error_message: 'Complete failure.',
        });
        expect(document.querySelectorAll('.alert-danger').length).toBeGreaterThan(0);
    });

    test('does NOT call location.reload() when there is an error', async () => {
        await triggerError({
            doi: 'Error',
            doi_status: 'Error',
            feedback: '',
            error_message: 'Fail.',
        });
        expect(_internals.reload).not.toHaveBeenCalled();
    });

    test('falls back to generic message when error_message is absent', async () => {
        buildFixture();
        init();
        mockFetchSuccess({ doi: 'Error', doi_status: 'Error' });
        await clickAndWait();
        const alert = document.querySelector('.alert-danger');
        expect(alert).not.toBeNull();
    });

    test('does NOT update #doi-link on error', async () => {
        await triggerError({
            doi: 'Error',
            doi_status: 'Error',
            feedback: '',
            error_message: 'Fail.',
        });
        const doiLink = document.getElementById('doi-link');
        expect(doiLink.textContent.trim()).toBe('');
    });
});

// ===========================================================================
// HTTP-level errors
// ===========================================================================

describe('HTTP error responses', () => {
    test('shows a danger alert on a 500 response', async () => {
        buildFixture();
        init();
        mockFetchHttpError(500);
        await clickAndWait();
        expect(document.querySelector('.alert-danger')).not.toBeNull();
    });

    test('shows a danger alert on a 404 response', async () => {
        buildFixture();
        init();
        mockFetchHttpError(404);
        await clickAndWait();
        expect(document.querySelector('.alert-danger')).not.toBeNull();
    });

    test('does not call location.reload() on an HTTP error', async () => {
        buildFixture();
        init();
        mockFetchHttpError(503);
        await clickAndWait();
        expect(_internals.reload).not.toHaveBeenCalled();
    });

    test('hides the loader on an HTTP error', async () => {
        buildFixture();
        init();
        mockFetchHttpError(500);
        await clickAndWait();
        expect(document.getElementById('doi-status-loader').hidden).toBe(true);
    });
});

// ===========================================================================
// Network errors
// ===========================================================================

describe('network errors', () => {
    test('shows a danger alert when fetch throws a TypeError', async () => {
        buildFixture();
        init();
        mockFetchNetworkError();
        await clickAndWait();
        expect(document.querySelector('.alert-danger')).not.toBeNull();
    });

    test('does not call location.reload() after a network error', async () => {
        buildFixture();
        init();
        mockFetchNetworkError();
        await clickAndWait();
        expect(_internals.reload).not.toHaveBeenCalled();
    });

    test('hides the loader after a network error', async () => {
        buildFixture();
        init();
        mockFetchNetworkError();
        await clickAndWait();
        expect(document.getElementById('doi-status-loader').hidden).toBe(true);
    });
});

// ===========================================================================
// Invalid / unparseable server responses
// ===========================================================================

describe('invalid server response body', () => {
    test('shows a danger alert when the response is not valid JSON', async () => {
        buildFixture();
        init();
        mockFetchRawText('<!DOCTYPE html><html>Internal error</html>');
        await clickAndWait();
        expect(document.querySelector('.alert-danger')).not.toBeNull();
    });

    test('shows a danger alert when the response is a JSON array', async () => {
        buildFixture();
        init();
        mockFetchRawText('[{"doi":"x"}]');
        await clickAndWait();
        expect(document.querySelector('.alert-danger')).not.toBeNull();
    });

    test('shows a danger alert for a JSON string literal', async () => {
        buildFixture();
        init();
        mockFetchRawText('"just a string"');
        await clickAndWait();
        expect(document.querySelector('.alert-danger')).not.toBeNull();
    });
});

// ===========================================================================
// Double-click / abort behaviour
// ===========================================================================

describe('abort on double-click', () => {
    test('fires fetch only twice when button is clicked twice rapidly', async () => {
        buildFixture();
        init();

        // Both calls return the same success payload.
        mockFetchSuccess({
            doi: '10.1/a',
            doi_status: '<p>ok</p>',
            feedback: 'OK',
            error_message: '',
        });
        mockFetchSuccess({
            doi: '10.1/b',
            doi_status: '<p>ok</p>',
            feedback: 'OK',
            error_message: '',
        });

        const button = document.getElementById('requestNewDoi');
        button.click();
        button.click();

        // Flush the promise queue without clicking a third time.
        await Promise.resolve();
        await Promise.resolve();
        await Promise.resolve();

        // The second click replaces the first request; fetch is called exactly twice.
        expect(global.fetch).toHaveBeenCalledTimes(2);
    });
});

// ===========================================================================
// XSS safety
// ===========================================================================

describe('XSS safety', () => {
    test('sanitizeHTML is NOT called for feedback (success alert removed — DOI link is sufficient feedback)', async () => {
        buildFixture();
        init();
        const payload = {
            doi: '10.1/ok',
            doiStr: '10.1/ok',
            doi_status: '<p>status</p>',
            feedback: '<img src=x onerror=alert(1)>',
            error_message: '',
        };
        mockFetchSuccess(payload);
        await clickAndWait();
        // feedback is ignored by handleSuccess — sanitizeHTML must not be called with it.
        expect(sanitizeHTML).not.toHaveBeenCalledWith(payload.feedback);
    });

    test('sanitizeHTML is called for doi_status in success path', async () => {
        buildFixture();
        init();
        const payload = {
            doi: '10.1/ok',
            doi_status: '<script>stealCookies()</script>',
            feedback: 'OK',
            error_message: '',
        };
        mockFetchSuccess(payload);
        await clickAndWait();
        expect(sanitizeHTML).toHaveBeenCalledWith(payload.doi_status);
    });

    test('sanitizeHTML is called for error_message in error path', async () => {
        buildFixture();
        init();
        const payload = {
            doi: 'Error',
            doi_status: 'Error',
            feedback: '',
            error_message: '<b onmouseover=alert(1)>Error</b>',
        };
        mockFetchSuccess(payload);
        await clickAndWait();
        expect(sanitizeHTML).toHaveBeenCalledWith(payload.error_message);
    });

    test('sanitizeHTML is called for data.doi so #doi-link is updated safely (no raw-HTML flash)', async () => {
        buildFixture();
        init();
        // data.doi from the server is DoiAsLink() HTML markup.
        // It is now injected via safeHtml() (sanitizeHTML wrapper) rather than
        // textContent, so the link renders correctly without a raw-HTML flash.
        const htmlDoi = '<a rel="noopener noreferrer" href="https://doi.org/10.1/ok">https://doi.org/10.1/ok</a>';
        mockFetchSuccess({
            doi: htmlDoi,
            doiStr: '10.1/ok',
            doi_status: '<p>ok</p>',
            feedback: 'OK',
            error_message: '',
        });
        await clickAndWait();
        expect(sanitizeHTML).toHaveBeenCalledWith(htmlDoi);
        const doiLink = document.getElementById('doi-link');
        // The mock returns '[sanitized:<input>]'; #doi-link must not be empty.
        expect(doiLink.innerHTML.trim()).not.toBe('');
    });
});
