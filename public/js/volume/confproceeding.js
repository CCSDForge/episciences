/**
 * Proceedings part of the volume form (volume/proceedings_fieldset.phtml and
 * volume/doi_proceedings_row.phtml).
 *
 * - The "Actes de conférences" checkbox shows the conference fieldset; hidden inputs are disabled
 *   so that they are neither posted nor able to block the submit.
 * - The DOI suffix shows the resulting DOI in a live region; saving the form queues the deposit.
 * - A DOI sent to Crossref (status requested or public) is read-only.
 * - Client checks mirror Episciences\Volume\ProceedingsValidator, the server stays the reference.
 *
 * @module ConferenceProceeding
 */
const ConferenceProceeding = (() => {
    const CHECKBOX_ID = 'is_proceeding';
    const FIELDSET_ID = 'conference-fields';
    const DOI_GROUP_ID = 'conference_doi_group';
    const DOI_INPUT_ID = 'conference_proceedings_doi';
    const DOI_PREVIEW_ID = 'conference_proceedings_doi-preview';
    const START_ID = 'conference_start';
    const END_ID = 'conference_end';
    const ERROR_SUMMARY_ID = 'form-error-summary';
    const LOCKED_STATUSES = ['requested', 'public'];
    const SUFFIX_PATTERN = /^[A-Za-z0-9._-]{1,20}$/;

    /**
     * @param {Document|Element} root
     * @param {string} id
     * @returns {HTMLElement|null}
     */
    function byId(root, id) {
        return root.querySelector(`#${CSS.escape(id)}`);
    }

    /**
     * @param {string} prefix e.g. "10.46298/mbj.proceedings."
     * @param {string} suffix
     * @returns {string} the DOI, empty without suffix
     */
    function buildDoi(prefix, suffix) {
        const trimmed = (suffix || '').trim();
        return trimmed === '' ? '' : `${prefix || ''}${trimmed}`;
    }

    /**
     * @param {string} suffix
     * @returns {boolean}
     */
    function isSuffixValid(suffix) {
        return SUFFIX_PATTERN.test(suffix);
    }

    /**
     * @param {string} status
     * @returns {boolean} the DOI was sent to Crossref and can no longer change
     */
    function isLocked(status) {
        return LOCKED_STATUSES.includes(status);
    }

    /**
     * Show or hide the conference fields; hidden inputs are disabled and never required.
     *
     * @param {Document|Element} root
     * @param {boolean} isProceeding
     */
    function toggleConferenceFields(root, isProceeding) {
        const fieldset = byId(root, FIELDSET_ID);
        if (!fieldset) {
            return;
        }

        fieldset.hidden = !isProceeding;
        fieldset.querySelectorAll('input').forEach(input => {
            input.disabled = !isProceeding;
            if (input.hasAttribute('data-proceedings-required')) {
                input.required = isProceeding;
                input.setAttribute('aria-required', String(isProceeding));
            }
        });

        const checkbox = byId(root, CHECKBOX_ID);
        if (checkbox) {
            checkbox.setAttribute('aria-expanded', String(isProceeding));
        }
    }

    /**
     * Refresh the resulting DOI shown under the suffix (live region).
     *
     * @param {Document|Element} root
     */
    function updateDoiPreview(root) {
        const group = byId(root, DOI_GROUP_ID);
        const input = byId(root, DOI_INPUT_ID);
        const preview = byId(root, DOI_PREVIEW_ID);
        if (!group || !input || !preview || isLocked(group.dataset.status)) {
            return;
        }

        const doi = buildDoi(group.dataset.prefix, input.value);
        preview.textContent =
            doi === ''
                ? group.dataset.textEmpty || ''
                : (group.dataset.textPreview || '%s').replace('%s', doi);
    }

    /**
     * @param {HTMLElement} input
     * @param {string} id
     * @param {boolean} add
     */
    function toggleDescribedBy(input, id, add) {
        const ids = (input.getAttribute('aria-describedby') || '')
            .split(/\s+/)
            .filter(value => value !== '' && value !== id);
        if (add) {
            ids.push(id);
        }
        if (ids.length > 0) {
            input.setAttribute('aria-describedby', ids.join(' '));
        } else {
            input.removeAttribute('aria-describedby');
        }
    }

    /**
     * Error shown by a client check, next to the field, with the server message.
     *
     * @param {HTMLElement} input
     * @param {string} message empty to clear the error set by a client check
     */
    function setClientError(input, message) {
        const errorId = `${input.id}-error`;
        // Paired fields carry their error state on their own column
        const group =
            input.closest('[id$="-element"]') || input.closest('.form-group');
        const container = input.closest('.input-group') || input;
        let error = input.ownerDocument.getElementById(errorId);

        if (message === '') {
            if (error && error.hasAttribute('data-client-error')) {
                error.remove();
                input.removeAttribute('aria-invalid');
                group?.classList.remove('has-error');
                toggleDescribedBy(input, errorId, false);
            }
            return;
        }

        if (!error) {
            error = document.createElement('p');
            error.id = errorId;
            error.className = 'help-block';
            container.insertAdjacentElement('afterend', error);
        }
        error.setAttribute('data-client-error', '');
        error.textContent = message;
        input.setAttribute('aria-invalid', 'true');
        group?.classList.add('has-error');
        toggleDescribedBy(input, errorId, true);
    }

    /**
     * @param {Document|Element} root
     */
    function validateDoiSuffix(root) {
        const group = byId(root, DOI_GROUP_ID);
        const input = byId(root, DOI_INPUT_ID);
        if (!group || !input || input.readOnly) {
            return;
        }

        const suffix = input.value.trim();
        setClientError(
            input,
            suffix === '' || isSuffixValid(suffix)
                ? ''
                : group.dataset.textInvalid || ''
        );
    }

    /**
     * @param {HTMLInputElement} input date input carrying the range accepted by Crossref (min, max)
     * @param {string} message
     * @returns {string} the error, empty when the date is empty or in range
     */
    function rangeError(input, message) {
        const { value, min, max } = input;
        const isOutOfRange =
            value !== '' &&
            ((min !== '' && value < min) || (max !== '' && value > max));
        return isOutOfRange ? message : '';
    }

    /**
     * Years accepted by Crossref, end date not before the start date.
     *
     * @param {Document|Element} root
     */
    function validateDates(root) {
        const fieldset = byId(root, FIELDSET_ID);
        const start = byId(root, START_ID);
        const end = byId(root, END_ID);
        if (!fieldset || !start || !end) {
            return;
        }

        const outOfRange = fieldset.dataset.textOutOfRange || '';
        const startError = rangeError(start, outOfRange);
        let endError = rangeError(end, outOfRange);
        if (
            startError === '' &&
            endError === '' &&
            start.value !== '' &&
            end.value !== '' &&
            end.value < start.value
        ) {
            endError = fieldset.dataset.textEndBeforeStart || '';
        }

        setClientError(start, startError);
        setClientError(end, endError);
    }

    /**
     * After a refused submit, move the focus to the error summary.
     *
     * @param {Document|Element} root
     */
    function focusErrorSummary(root) {
        const summary = byId(root, ERROR_SUMMARY_ID);
        if (summary) {
            summary.focus();
        }
    }

    /**
     * @param {Document|Element} root
     */
    function bindEvents(root) {
        const checkbox = byId(root, CHECKBOX_ID);
        checkbox?.addEventListener('change', () =>
            toggleConferenceFields(root, checkbox.checked)
        );

        const doiInput = byId(root, DOI_INPUT_ID);
        doiInput?.addEventListener('input', () => updateDoiPreview(root));
        ['change', 'blur'].forEach(type =>
            doiInput?.addEventListener(type, () => validateDoiSuffix(root))
        );

        [byId(root, START_ID), byId(root, END_ID)].forEach(input =>
            ['change', 'blur'].forEach(type =>
                input?.addEventListener(type, () => validateDates(root))
            )
        );
    }

    /**
     * @param {Document|Element} [root=document]
     */
    function init(root = document) {
        const checkbox = byId(root, CHECKBOX_ID);
        if (checkbox) {
            toggleConferenceFields(root, checkbox.checked);
        }

        const group = byId(root, DOI_GROUP_ID);
        const doiInput = byId(root, DOI_INPUT_ID);
        if (group && doiInput && isLocked(group.dataset.status)) {
            doiInput.readOnly = true;
        }

        bindEvents(root);
        focusErrorSummary(root);
    }

    return {
        buildDoi,
        isSuffixValid,
        isLocked,
        toggleConferenceFields,
        updateDoiPreview,
        validateDoiSuffix,
        validateDates,
        focusErrorSummary,
        init,
    };
})();

// Auto-initialize in the browser (Jest loads the module through CommonJS)
if (typeof module === 'undefined' && typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () =>
            ConferenceProceeding.init()
        );
    } else {
        ConferenceProceeding.init();
    }
}

// CommonJS export for Jest testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ConferenceProceeding;
}
