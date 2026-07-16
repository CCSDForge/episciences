/**
 * Manages paper author affiliations
 * Handles the interface for editing author affiliations including:
 * - Loading affiliation forms based on author selection
 * - Form validation
 * - Integration with ROR autocomplete
 */
class PaperAffiAuthorsManager {
    /**
     * Creates a new PaperAffiAuthorsManager instance
     */
    constructor() {
        this.authorSelect = null;
        this.affiBody = null;
        this.form = null;
        this.hiddenAuthorInput = null;
        this.paperIdDiv = null;
    }

    /**
     * Initializes the manager by finding DOM elements and attaching event listeners
     */
    initialize() {
        this.authorSelect = document.querySelector('#select-author-affi');
        this.affiBody = document.querySelector('div#affi-body');
        this.form = document.querySelector('form#form-affi-authors');
        this.hiddenAuthorInput = document.querySelector(
            '#id-edited-affi-author'
        );
        this.paperIdDiv = document.querySelector('div#paperid-for-author');

        if (!this.authorSelect) {
            return;
        }

        this.attachEventListeners();
        this.attachAffiliationBadgeHandlers();
    }

    /**
     * Attaches event listeners to form elements
     */
    attachEventListeners() {
        this.authorSelect.addEventListener('change', () =>
            this.handleAuthorChange()
        );

        if (this.form) {
            this.form.addEventListener('submit', e => this.handleFormSubmit(e));
        }
    }

    /**
     * Wires up the edit/delete buttons rendered on each existing affiliation
     * badge. The server markup relies on inline onclick attributes and an
     * inline <script> block (legacy Ccsd_Form multi-text widget), but the
     * response is injected via innerHTML and passed through DOMPurify, which
     * strips both <script> tags and on* attributes. Delegating the click
     * handler from the (stable) container lets it survive innerHTML swaps.
     */
    attachAffiliationBadgeHandlers() {
        if (!this.affiBody || this.affiBody.dataset.badgeHandlersAttached) {
            return;
        }
        this.affiBody.dataset.badgeHandlersAttached = 'true';

        this.affiBody.addEventListener('click', event => {
            const button = event.target.closest('button');
            if (!button || !this.affiBody.contains(button)) {
                return;
            }

            if (button.querySelector('.glyphicon-trash')) {
                event.preventDefault();
                this.removeAffiliationBadge(button);
            } else if (button.querySelector('.glyphicon-pencil')) {
                event.preventDefault();
                this.editAffiliationBadge(button);
            }
        });
    }

    /**
     * Removes an existing affiliation badge from the DOM
     * @param {HTMLButtonElement} deleteButton - The clicked delete button
     */
    removeAffiliationBadge(deleteButton) {
        deleteButton.closest('.input-group')?.remove();
    }

    /**
     * Sends an existing affiliation badge's value back to the free-text
     * input for editing, then removes the badge
     * @param {HTMLButtonElement} editButton - The clicked edit button
     */
    editAffiliationBadge(editButton) {
        const inputGroup = editButton.closest('.input-group');
        const hiddenInput = inputGroup?.querySelector('input[type="hidden"]');
        const freeTextInput = document.getElementById('affiliations');

        if (!hiddenInput || !freeTextInput) {
            return;
        }

        freeTextInput.value = hiddenInput.value;
        inputGroup.remove();
        freeTextInput.focus();
        freeTextInput.dispatchEvent(new Event('input', { bubbles: true }));
    }

    /**
     * Handles author selection changes
     * Loads affiliations for the selected author
     */
    async handleAuthorChange() {
        const selectedOption =
            this.authorSelect.options[this.authorSelect.selectedIndex];
        const authorId = selectedOption?.id;

        if (this.hiddenAuthorInput) {
            this.hiddenAuthorInput.value = authorId || '';
        }

        if (!authorId) {
            if (this.affiBody) {
                this.affiBody.innerHTML = '';
            }
            return;
        }

        try {
            await this.loadAffiliations(authorId);
        } catch (error) {
            console.error('Error loading affiliations:', error);
            if (this.affiBody) {
                this.affiBody.innerHTML =
                    '<p class="error">Error loading affiliations. Please try again.</p>';
            }
        }
    }

    /**
     * Loads affiliation form for the specified author
     * @param {string} authorId - The author ID
     * @returns {Promise<void>}
     */
    async loadAffiliations(authorId) {
        const paperId = this.paperIdDiv?.textContent || '';

        const response = await fetch(
            JS_PREFIX_URL + 'paper/getaffiliationsbyauthor/',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    idAuthor: authorId,
                    paperId: paperId,
                }),
            }
        );

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const html = await response.text();

        if (this.affiBody) {
            this.affiBody.innerHTML = '';
            this.insertHtml(this.affiBody, html);
        }

        // Initialize affiliations autocomplete
        await this.loadAffiliationsScript();
    }

    /**
     * Renders returned markup into the container.
     * The markup is passed through the shared sanitizer when available; any
     * behaviour (autocomplete setup) is wired separately via
     * loadAffiliationsScript(), so embedded <script> tags are not needed.
     * @param {HTMLElement} container - Container element
     * @param {string} html - HTML content
     */
    insertHtml(container, html) {
        if (typeof sanitizeHTML === 'function') {
            container.innerHTML = sanitizeHTML(html);
            return;
        }
        // Fallback when the sanitizer is unavailable.
        container.innerHTML = html;
    }

    /**
     * Loads and initializes the affiliations autocomplete script
     * @returns {Promise<void>}
     */
    async loadAffiliationsScript() {
        const versionCache = window.versionCache || '';
        const scriptUrl = `/js/user/affiliations.js?_=v${versionCache}`;

        try {
            // Check if script is already loaded
            if (
                typeof window.initializeAffiliationsAutocomplete === 'function'
            ) {
                window.initializeAffiliationsAutocomplete();
                return;
            }

            // Load script dynamically
            await this.loadScript(scriptUrl);

            if (
                typeof window.initializeAffiliationsAutocomplete === 'function'
            ) {
                window.initializeAffiliationsAutocomplete();
            }
        } catch (error) {
            console.error('affiliations.js loading failed', error);
            throw error;
        }
    }

    /**
     * Dynamically loads a script
     * @param {string} url - Script URL
     * @returns {Promise<void>}
     */
    loadScript(url) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = url;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    /**
     * Handles form submission validation
     * @param {Event} event - Submit event
     * @returns {boolean} - Whether to allow submission
     */
    handleFormSubmit(event) {
        const authorId = this.hiddenAuthorInput?.value;

        if (!authorId || authorId.length === 0) {
            event.preventDefault();
            return false;
        }

        return true;
    }
}

/**
 * Initialize the paper affiliation authors manager
 * Handles both cases: DOM already loaded or still loading
 */
function initializePaperAffiAuthors() {
    const manager = new PaperAffiAuthorsManager();
    manager.initialize();
}

// Initialize on DOM ready or immediately if already loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePaperAffiAuthors);
} else {
    initializePaperAffiAuthors();
}

// Export for testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { PaperAffiAuthorsManager };
}
