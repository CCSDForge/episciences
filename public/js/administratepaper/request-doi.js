/**
 * Request DOI
 *
 * Handles DOI assignment requests for papers.
 * On click of #requestNewDoi, sends a POST request to assign a new DOI,
 * then updates the UI on success or displays an error alert on failure.
 *
 * Requires: public/js/utils/sanitizer.js (sanitizeHTML global)
 */

(function () {
    'use strict';

    const ENDPOINT = '/administratepaper/ajaxrequestnewdoi';

    /** @type {AbortController|null} */
    let activeRequest = null;

    /**
     * Exposed via _internals so tests can inspect internal state.
     * (reload was removed: handleSuccess now updates the DOM directly.)
     */
    const _internals = {};

    /**
     * Parse the server response text as a JSON object.
     * Returns null when the text is not valid JSON or not a plain object.
     *
     * @param {string} text
     * @returns {{ doi: string, doi_status: string, feedback: string, error_message: string }|null}
     */
    function parseDoiResponse(text) {
        try {
            const data = JSON.parse(text);
            if (data === null || typeof data !== 'object' || Array.isArray(data)) {
                return null;
            }
            return data;
        } catch (_e) {
            return null;
        }
    }

    /**
     * Sanitize a server-provided HTML string.
     * Delegates to the global sanitizeHTML (DOMPurify wrapper) when available,
     * otherwise falls back to plain-text rendering to prevent XSS.
     *
     * @param {string} html
     * @returns {string}
     */
    function safeHtml(html) {
        if (typeof sanitizeHTML === 'function') {
            return sanitizeHTML(html);
        }
        // Safe fallback: encode the string as text so no HTML executes.
        const div = document.createElement('div');
        div.textContent = html;
        return div.innerHTML;
    }

    /**
     * Create a Bootstrap dismissible alert element with a sanitized message.
     *
     * @param {'success'|'danger'} type - Bootstrap alert variant
     * @param {string} message - Message content (may contain server-provided HTML)
     * @returns {HTMLDivElement}
     */
    function createAlert(type, message) {
        const div = document.createElement('div');
        div.setAttribute('role', 'alert');
        div.className = `alert alert-${type} alert-dismissible`;
        div.innerHTML = safeHtml(message);
        return div;
    }

    /**
     * Remove a DOM element by ID if it exists.
     *
     * @param {string} id
     */
    function removeById(id) {
        const el = document.getElementById(id);
        if (el) {
            el.remove();
        }
    }

    /**
     * Apply a successful DOI response to the DOM without reloading the page.
     *
     * @param {{ doi: string, doiStr: string, doi_status: string, feedback: string }} data
     * @param {HTMLElement|null} loader
     */
    function handleSuccess(data, loader) {
        if (loader) {
            loader.hidden = true;
        }

        // Update #doi-link with the sanitized DOI anchor HTML returned by DoiAsLink().
        const doiLink = document.getElementById('doi-link');
        if (doiLink) {
            doiLink.innerHTML = safeHtml(data.doi);
        }

        const doiStatus = document.getElementById('doi-status');
        if (doiStatus) {
            // doi_status may contain server-generated markup — sanitize before insertion.
            doiStatus.innerHTML = safeHtml(data.doi_status);
        }

        // Capture docId before removing the request button.
        const requestBtn = document.getElementById('requestNewDoi');
        const docId = requestBtn ? requestBtn.dataset.docid : '';

        removeById('requestNewDoi');
        removeById('doi-action');

        // Build the "Cancel DOI" button and insert it after #doi-link.
        const panel = document.getElementById('doi-panel');
        const paperId = panel ? panel.dataset.paperid : '';
        if (docId && paperId && doiLink) {
            const cancelBtn = document.createElement('button');
            cancelBtn.id = 'removeDoi';
            cancelBtn.dataset.paperid = String(paperId);
            cancelBtn.dataset.docid = String(docId);
            cancelBtn.className = 'btn btn-default btn-sm popover-link';
            cancelBtn.style.marginLeft = '5px';

            const icon = document.createElement('span');
            icon.className = 'fa-solid fa-rotate-left';
            icon.style.marginRight = '5px';
            cancelBtn.appendChild(icon);
            cancelBtn.appendChild(document.createTextNode(
                typeof translate === 'function' ? translate('Annuler le DOI') : 'Cancel DOI'
            ));
            cancelBtn.addEventListener('click', function () {
                if (typeof removeDoi === 'function') {
                    removeDoi(cancelBtn, Number(paperId), Number(docId), data.doiStr || '');
                }
            });
            doiLink.insertAdjacentElement('afterend', cancelBtn);
        }

        // Update the paper-doi anchor in the page header (if present).
        const paperDoiAnchor = document.querySelector('div.paper-doi a');
        if (paperDoiAnchor) {
            paperDoiAnchor.textContent = data.doiStr || '';
        }
    }

    /**
     * Display an error alert and hide the loader.
     *
     * @param {string} errorMessage - Error text (may contain server-provided HTML)
     * @param {HTMLElement|null} loader
     */
    function handleError(errorMessage, loader) {
        if (loader) {
            loader.hidden = true;
        }

        const doiStatus = document.getElementById('doi-status');
        if (doiStatus) {
            doiStatus.prepend(createAlert('danger', errorMessage));
        }
    }

    /**
     * Perform the fetch, returning { ok, text } or null on network failure.
     * Handles AbortError separately to distinguish cancellation from failure.
     *
     * @param {string} docid
     * @param {AbortSignal} signal
     * @returns {Promise<{ ok: boolean, status: number, text: string }|null|'aborted'>}
     */
    async function fetchDoi(docid, signal) {
        const response = await fetch(ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                // Required so the PHP controller can verify this is an XHR request
                // via Zend_Controller_Request_Http::isXmlHttpRequest().
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new URLSearchParams({ docid }),
            signal,
        });

        const text = await response.text();
        return { ok: response.ok, status: response.status, text };
    }

    /**
     * Send a POST request to assign a DOI and process the response.
     * Cancels any previously in-flight request before starting a new one.
     *
     * @param {string} docid - Document ID
     * @param {HTMLElement|null} loader
     * @returns {Promise<void>}
     */
    async function requestNewDoi(docid, loader) {
        // Cancel a previous in-flight request if the user clicked again.
        if (activeRequest) {
            activeRequest.abort();
        }

        // Keep a local reference so the finally block can safely check ownership.
        const controller = new AbortController();
        activeRequest = controller;

        let result;
        try {
            result = await fetchDoi(docid, controller.signal);
        } catch (err) {
            if (err.name === 'AbortError') {
                // A newer click already took over — exit silently.
                return;
            }
            handleError('Network error. Please try again.', loader);
            return;
        } finally {
            // Only clear the slot when this request is still considered active.
            // A concurrent second click may have already replaced activeRequest.
            if (activeRequest === controller) {
                activeRequest = null;
            }
        }

        if (!result.ok) {
            handleError('Server error. Please try again.', loader);
            return;
        }

        const data = parseDoiResponse(result.text);
        if (!data) {
            handleError('Unexpected server response.', loader);
            return;
        }

        if (data.doi === 'Error' || data.doi_status === 'Error') {
            handleError(data.error_message || 'An error occurred.', loader);
        } else {
            handleSuccess(data, loader);
        }
    }

    /**
     * Named click handler used with event delegation.
     * Defined outside init() so the same reference can be passed to both
     * removeEventListener (deduplication) and addEventListener.
     *
     * @param {MouseEvent} event
     */
    function handleRequestDoiClick(event) {
        const button = event.target.closest('#requestNewDoi');
        if (!button) {
            return;
        }

        const loader = document.getElementById('doi-status-loader');
        const docid = button.dataset.docid;
        if (!docid) {
            return;
        }

        if (loader) {
            loader.innerHTML = typeof getLoader === 'function' ? getLoader() : '';
            loader.hidden = false;
        }

        // Errors are handled inside requestNewDoi; .catch() prevents
        // any unexpected rejection from surfacing as an unhandled promise.
        requestNewDoi(docid, loader).catch(function (err) {
            handleError('An unexpected error occurred.', loader);
            console.error('[request-doi] Unhandled error:', err);
        });
    }

    /**
     * Wire up the DOI request handler via event delegation on the document.
     * Using event delegation (rather than direct attachment) means that
     * dynamically injected #requestNewDoi buttons (e.g. after a DOI cancellation)
     * are handled without needing a second init() call.
     * removeEventListener + addEventListener with the same stable reference
     * prevents duplicate handlers when init() is called more than once.
     */
    function init() {
        document.removeEventListener('click', handleRequestDoiClick);
        document.addEventListener('click', handleRequestDoiClick);
    }

    document.addEventListener('DOMContentLoaded', init);

    // Expose internals for unit testing only.
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { parseDoiResponse, createAlert, safeHtml, init, _internals };
    }
})();