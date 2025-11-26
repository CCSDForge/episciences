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

        const response = await fetch('/paper/getaffiliationsbyauthor/', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                idAuthor: authorId,
                paperId: paperId,
            }),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const html = await response.text();

        if (this.affiBody) {
            this.affiBody.innerHTML = '';
            // Insert HTML and execute any scripts it contains
            this.insertHTMLWithScripts(this.affiBody, html);
        }

        // Initialize affiliations autocomplete
        await this.loadAffiliationsScript();
    }

    /**
     * Inserts HTML content and executes any script tags within it
     * innerHTML doesn't execute scripts for security reasons, so we need to do it manually
     * @param {HTMLElement} container - Container element
     * @param {string} html - HTML content with potential script tags
     */
    insertHTMLWithScripts(container, html) {
        // Create a temporary container
        const temp = document.createElement('div');
        temp.innerHTML = html;

        // Extract and remove script tags
        const scripts = temp.querySelectorAll('script');
        const scriptContents = [];

        scripts.forEach(script => {
            scriptContents.push(script.textContent);
            script.remove();
        });

        // Insert the HTML without scripts
        container.innerHTML = temp.innerHTML;

        // Execute each script
        scriptContents.forEach(scriptContent => {
            const script = document.createElement('script');
            script.textContent = scriptContent;
            document.head.appendChild(script);
        });
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
