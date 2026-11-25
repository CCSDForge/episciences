// Extracted from public/js/submit/index.js for testing (identifier parsing: see public/js/submit/identifier.js)

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
    examples: {},
    translate: text => text,
};

function setMockGlobals(globals) {
    mockGlobals = { ...mockGlobals, ...globals };
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
    setPlaceholder,
    setMockDom,
    setMockGlobals,
    createMockElement,
};
