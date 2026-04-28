/**
 * Volume Special Issue - Access Code Toggle Module
 *
 * Manages the display of an access code field based on the "special issue"
 * dropdown selection. When the dropdown value is "1" (Yes), the access code
 * field is shown; otherwise, it is hidden.
 *
 * @module VolumeSpecial
 */
const VolumeSpecial = {
    /**
     * Creates the access code HTML element
     *
     * @param {string} [accessCode=''] - The access code value to display
     * @param {Function} [translateFn=(text => text)] - Translation function (defaults to identity function)
     * @returns {string} HTML string for the access code element
     */
    createAccessCodeElement(accessCode = '', translateFn = text => text) {
        const code = accessCode || '';
        const label = translateFn("Code d'accès");

        return `<div id="access_code-element" class="form-group row">
            <label class='col-md-3' style='text-align: right'>${label}</label>
            <div class='col-md-9'>${code}</div>
            <input id='access_code' name='access_code' type='hidden' value='${code}'>
        </div>`;
    },

    /**
     * Shows the access code field by inserting it after the special issue element
     * Prevents duplicate insertion if element already exists
     */
    showAccessCode() {
        // Prevent duplicate
        if (document.getElementById('access_code-element')) {
            return;
        }

        const specialIssueElement = document.getElementById(
            'special_issue-element'
        );
        if (!specialIssueElement) {
            return;
        }

        const accessCode = window.access_code ?? '';
        const translate = window.translate ?? (text => text);
        const html = this.createAccessCodeElement(accessCode, translate);

        specialIssueElement.insertAdjacentHTML('afterend', html);
    },

    /**
     * Hides the access code field by removing it from the DOM
     * Safe to call even if element doesn't exist
     */
    hideAccessCode() {
        const element = document.getElementById('access_code-element');
        element?.remove();
    },

    /**
     * Toggles the access code field visibility based on special issue selection
     * Shows the field when value is "1", hides otherwise
     */
    toggleAccessCode() {
        const specialIssueSelect = document.getElementById('special_issue');
        if (!specialIssueSelect) {
            return;
        }

        if (specialIssueSelect.value === '1') {
            this.showAccessCode();
        } else {
            this.hideAccessCode();
        }
    },

    /**
     * Initializes the module by setting up event listeners
     * Should be called when DOM is ready
     */
    init() {
        const specialIssueSelect = document.getElementById('special_issue');
        if (!specialIssueSelect) {
            console.warn('VolumeSpecial: special_issue element not found');
            return;
        }

        console.log('VolumeSpecial: Initialized');

        // Initial toggle on page load
        this.toggleAccessCode();

        // Toggle on change
        specialIssueSelect.addEventListener('change', () => {
            this.toggleAccessCode();
        });
    },
};

// Auto-initialize when DOM is ready (only in browser, not in tests)
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        // DOM is still loading, wait for DOMContentLoaded
        document.addEventListener('DOMContentLoaded', () => {
            VolumeSpecial.init();
        });
    } else {
        // DOM is already loaded, initialize immediately
        VolumeSpecial.init();
    }
}

// CommonJS export for Jest testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = VolumeSpecial;
}
