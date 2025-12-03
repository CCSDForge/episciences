/**
 * BiblioRef - Modern bibliographic references visualization
 * Handles fetching and displaying citations from Semantic Scholar API
 */

/**
 * Service class for API calls
 */
class BiblioRefService {
    /**
     * Fetch citations from API
     * @param {string} apiUrl - Base API URL
     * @param {string} paperUrl - Paper URL to fetch citations for
     * @param {boolean} showAll - Whether to show all citations including non-accepted
     * @returns {Promise<Object>} API response data
     * @throws {Error} When API call fails
     */
    async fetchCitations(apiUrl, paperUrl, showAll = false) {
        if (!apiUrl || !paperUrl) {
            throw new Error('API URL and paper URL are required');
        }

        let url = `${apiUrl}/visualize-citations?url=${encodeURIComponent(paperUrl)}`;
        if (showAll) {
            url += `&all=${showAll ? 1 : 0}`;
        }

        const response = await fetch(url);

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(
                errorData.message || `HTTP error! status: ${response.status}`
            );
        }

        return response.json();
    }
}

/**
 * Parser class for citation data processing
 */
class BiblioRefParser {
    /**
     * DOI regex pattern (validates DOI format)
     */
    static DOI_REGEX = /^10\.\d{4,9}\/[-._;()/:A-Z0-9]+$/i;

    /**
     * Parse and format a single citation
     * @param {Object} citation - Raw citation object
     * @param {boolean} isAuthorizedToSeeAcc - Whether user can see acceptance status
     * @returns {Object|null} Formatted citation or null if invalid
     */
    static parseCitation(citation, isAuthorizedToSeeAcc = false) {
        if (!citation || !citation.ref) {
            return null;
        }

        try {
            const parsedRef = JSON.parse(citation.ref);

            return {
                rawReference: parsedRef.raw_reference,
                doi: parsedRef.doi,
                isAccepted: citation.isAccepted === 1,
                showAccepted: isAuthorizedToSeeAcc && citation.isAccepted === 1,
            };
        } catch (error) {
            console.error('Failed to parse citation reference:', error);
            return null;
        }
    }

    /**
     * Validate and format DOI
     * @param {string} doi - DOI to validate
     * @returns {Object|null} Object with url and display text, or null if invalid
     */
    static formatDoi(doi) {
        if (!doi) {
            return null;
        }

        const match = doi.match(this.DOI_REGEX);
        const url = match ? `https://doi.org/${match[0]}` : doi;

        return {
            url,
            text: doi,
        };
    }
}

/**
 * Renderer class for DOM manipulation
 */
class BiblioRefRenderer {
    /**
     * Create a new renderer
     * @param {HTMLElement} container - Container element for citations list
     */
    constructor(container) {
        if (!container) {
            throw new Error('Container element is required');
        }
        this.container = container;
    }

    /**
     * Render a single citation as list item
     * @param {Object} citation - Formatted citation object
     * @returns {HTMLLIElement} List item element
     */
    renderCitation(citation) {
        const li = document.createElement('li');

        let html = '';

        // Add acceptance icon if authorized and accepted
        if (citation.showAccepted) {
            html +=
                '<i class="fa-sharp fa-solid fa-check" style="color: #009527;"></i> ';
        }

        // Add raw reference text
        html += this.escapeHtml(citation.rawReference);

        // Add DOI link if present
        if (citation.doi) {
            const formattedDoi = BiblioRefParser.formatDoi(citation.doi);
            if (formattedDoi) {
                html += ` <a href="${this.escapeHtml(formattedDoi.url)}" rel="noopener" target="_blank">${this.escapeHtml(formattedDoi.text)}</a>`;
            }
        }

        li.innerHTML = html;
        return li;
    }

    /**
     * Render source attribution
     * @returns {HTMLElement} Source element
     */
    renderSource() {
        const source = document.createElement('small');
        source.className = 'label label-default';
        source.textContent = 'Sources : Semantic Scholar';
        return source;
    }

    /**
     * Render error message
     * @param {string} message - Error message
     * @returns {HTMLLIElement} List item with error
     */
    renderError(message) {
        const li = document.createElement('li');
        li.textContent = message;
        li.className = 'biblio-ref-error';
        return li;
    }

    /**
     * Render all citations
     * @param {Array} citations - Array of formatted citations
     */
    renderCitations(citations) {
        // Create fragment for better performance
        const fragment = document.createDocumentFragment();

        citations.forEach(citation => {
            fragment.appendChild(this.renderCitation(citation));
        });

        // Add source attribution
        fragment.appendChild(this.renderSource());

        // Clear and append all at once
        this.container.innerHTML = '';
        this.container.appendChild(fragment);
    }

    /**
     * Clear container
     */
    clear() {
        this.container.innerHTML = '';
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} str - String to escape
     * @returns {string} Escaped string
     */
    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

/**
 * Main manager class
 */
class BiblioRefManager {
    /**
     * Create a new manager
     * @param {Object} config - Configuration object
     * @param {string} config.containerSelector - Selector for biblio refs container
     * @param {string} config.triggerSelector - Selector for visualization trigger element
     * @param {string} config.sectionSelector - Selector for the section to show/hide
     */
    constructor(config = {}) {
        this.config = {
            containerSelector: '#biblio-refs-container',
            triggerSelector: '#visualize-biblio-refs',
            sectionSelector: '#biblio-refs',
            ...config,
        };

        this.service = new BiblioRefService();
        this.initialized = false;
    }

    /**
     * Get configuration from DOM element data attributes
     * @param {HTMLElement} element - Trigger element
     * @returns {Object} Configuration object
     */
    getConfigFromElement(element) {
        return {
            apiUrl: element.dataset.api,
            paperUrl: element.dataset.value,
            showAll: parseInt(element.dataset.all, 10) === 1,
        };
    }

    /**
     * Initialize and visualize bibliographic references
     */
    async initialize() {
        // Prevent multiple initializations
        if (this.initialized) {
            return;
        }

        const triggerElement = document.querySelector(
            this.config.triggerSelector
        );
        if (!triggerElement) {
            return; // Element not present on this page
        }

        const container = document.querySelector(this.config.containerSelector);
        if (!container) {
            console.error('Biblio refs container not found');
            return;
        }

        const section = document.querySelector(this.config.sectionSelector);
        const renderer = new BiblioRefRenderer(container);

        try {
            // Mark as initialized to prevent duplicate calls
            this.initialized = true;

            // Get configuration from DOM
            const config = this.getConfigFromElement(triggerElement);

            // Fetch citations
            const response = await this.service.fetchCitations(
                config.apiUrl,
                config.paperUrl,
                config.showAll
            );

            // Handle empty or message-only response
            if (response.message && Object.keys(response).length === 1) {
                // API returned only a message, no citations to display
                return;
            }

            // Parse citations
            const citations = Object.values(response)
                .map(citation =>
                    BiblioRefParser.parseCitation(citation, config.showAll)
                )
                .filter(citation => citation !== null);

            // Render citations if we have any
            if (citations.length > 0) {
                renderer.renderCitations(citations);

                // Show section if available
                if (section) {
                    section.style.display = 'block';
                }
            }
        } catch (error) {
            console.error('Failed to visualize biblio refs:', error);
            renderer.clear();
            renderer.container.appendChild(
                renderer.renderError(
                    error.message || 'Failed to load citations'
                )
            );
        }
    }
}

/**
 * Initialize on DOM ready
 */
if (typeof document !== 'undefined') {
    // jQuery ready handler for backward compatibility
    if (typeof $ !== 'undefined' && typeof $.fn !== 'undefined') {
        $(function () {
            const manager = new BiblioRefManager();
            manager.initialize();
        });
    } else {
        // Vanilla JS fallback
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                const manager = new BiblioRefManager();
                manager.initialize();
            });
        } else {
            // DOM already loaded
            const manager = new BiblioRefManager();
            manager.initialize();
        }
    }
}

// Export for testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        BiblioRefService,
        BiblioRefParser,
        BiblioRefRenderer,
        BiblioRefManager,
    };
}
