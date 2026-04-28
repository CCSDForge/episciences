// Extracted from public/js/submit/index.js for testing

// Mock DOM functions for testing
let mockDom = {};

function setMockDom(dom) {
    mockDom = dom;
}

function mockGetElementById(id) {
    return mockDom[id] || null;
}

function mockQuerySelector(selector) {
    return mockDom[selector] || null;
}

// Mock global variables
let mockGlobals = {
    $isDataverseRepo: false,
    examples: {},
    translate: text => text,
};

function setMockGlobals(globals) {
    mockGlobals = { ...mockGlobals, ...globals };
}

// Extracted function: removeVersionFromIdentifier
function removeVersionFromIdentifier(identifier, options = {}) {
    const { mockVersionField = null } = options;

    // For testing, use mockVersionField if provided, otherwise try DOM
    const versionField =
        mockVersionField || mockGetElementById('search_doc-version');
    const versionMatch = identifier.match(/v(\d+)$/);

    if (versionMatch && versionField) {
        // Extract the version number (without the 'v' prefix)
        versionField.value = versionMatch[1];

        // Return identifier without the version
        return identifier.replace(/v\d+$/, '');
    }

    // If no version found, clear the version field and return original identifier
    if (versionField) {
        versionField.value = '';
    }
    return identifier;
}

// Extracted function: processUrlIdentifier
function processUrlIdentifier(input, options = {}) {
    const {
        mockVersionField = null,
        isDataverseRepo = mockGlobals.$isDataverseRepo,
    } = options;

    try {
        const url = new URL(input);
        let identifier = '';
        let versionFromUrl = null;

        // Handle different repository types
        if (isDataverseRepo || url.search) {
            // For Dataverse repos or URLs with query parameters
            if (url.search.includes('persistentId=')) {
                identifier =
                    url.searchParams.get('persistentId') ||
                    url.search.replace(/^\?.*persistentId=/, '').split('&')[0];

                // Check for version parameter in URL
                const versionParam = url.searchParams.get('version');
                if (versionParam) {
                    versionFromUrl = versionParam;
                    const versionField =
                        mockVersionField ||
                        mockGetElementById('search_doc-version');
                    if (versionField) {
                        // Keep full version number (e.g. "1.1", "2.0", "1.5")
                        versionField.value = versionParam;
                    }
                }
            } else {
                // Fallback to pathname if no persistentId found
                identifier = url.pathname.replace(/^\/+|\/+$/g, '');
            }
        } else {
            // For non-Dataverse repos without query parameters
            // Remove leading path segments and slashes
            identifier = url.pathname
                .replace(/^\/+/, '') // Remove leading slashes
                .replace(/\/\w+\/$/, '') // Remove trailing /word/ pattern
                .replace(/\/+$/, ''); // Remove trailing slashes
        }

        // Clean up empty identifier
        if (!identifier.trim()) {
            identifier = url.pathname.replace(/^\/+|\/+$/g, '') || url.href;
        }

        // Only call removeVersionFromIdentifier if we didn't already handle version from URL params
        if (versionFromUrl) {
            return identifier; // Don't process further since we already handled the version
        } else {
            return removeVersionFromIdentifier(identifier, {
                mockVersionField,
            });
        }
    } catch (error) {
        // If URL parsing fails, return the original input
        console.warn('URL parsing failed for:', input, error);
        return removeVersionFromIdentifier(input, { mockVersionField });
    }
}

// Extracted function: setPlaceholder (simplified for testing)
function setPlaceholder(options = {}) {
    const {
        mockDocIdField = null,
        mockRepoIdField = null,
        translate = mockGlobals.translate,
        examples = mockGlobals.examples,
    } = options;

    const searchDocDocId =
        mockDocIdField || mockGetElementById('search_doc-docId');
    const searchDocRepoId =
        mockRepoIdField || mockGetElementById('search_doc-repoId');

    if (searchDocDocId && searchDocRepoId) {
        const placeholderText =
            translate('exemple : ') + examples[searchDocRepoId.value];
        searchDocDocId.setAttribute('placeholder', placeholderText);
        searchDocDocId.setAttribute('size', placeholderText.length);
        return placeholderText; // Return for testing
    }
    return null;
}

// Helper function to create mock DOM elements
function createMockElement(value = '', attributes = {}) {
    return {
        value,
        attributes: { ...attributes },
        setAttribute: function (name, val) {
            this.attributes[name] = val;
        },
        getAttribute: function (name) {
            return this.attributes[name];
        },
    };
}

module.exports = {
    removeVersionFromIdentifier,
    processUrlIdentifier,
    setPlaceholder,
    setMockDom,
    setMockGlobals,
    createMockElement,
};
