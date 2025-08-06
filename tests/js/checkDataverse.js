// Extracted checkDataverse function from public/js/submit/index.js for testing

// Mock globals for testing
let mockGlobals = {
    $isDataverseRepo: false,
    translate: text => text,
};

function setMockGlobals(globals) {
    mockGlobals = { ...mockGlobals, ...globals };
}

// Mock DOM functions
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

// Mock fetch for testing
let mockFetch = null;

function setMockFetch(fetchFn) {
    mockFetch = fetchFn;
}

async function checkDataverse(options = {}) {
    const {
        mockRepoIdField = null,
        mockSubmitEntry = null,
        customFetch = mockFetch || fetch,
    } = options;

    const searchDocRepoId =
        mockRepoIdField || mockGetElementById('search_doc-repoId');
    const submitEntry =
        mockSubmitEntry || mockQuerySelector("a[href='/submit/index']");

    if (!searchDocRepoId) return;

    const formData = new FormData();
    formData.append('repoId', searchDocRepoId.value);

    try {
        const response = await customFetch('/submit/ajaxisdataverse', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        const responseText = await response.text();
        const oResponse = JSON.parse(responseText);

        // Update global state
        mockGlobals.$isDataverseRepo = oResponse.hasOwnProperty('isDataverse')
            ? oResponse.isDataverse
            : false;

        if (submitEntry) {
            const submitEntryTitle = mockGlobals.$isDataverseRepo
                ? 'Proposer un jeu de données'
                : 'Proposer un article';
            submitEntry.textContent = mockGlobals.translate(submitEntryTitle);
        }

        return {
            isDataverse: mockGlobals.$isDataverseRepo,
            submitEntryText: submitEntry ? submitEntry.textContent : null,
        };
    } catch (error) {
        console.error('Error checking dataverse:', error);
        throw error;
    }
}

// Helper function to create mock DOM elements
function createMockElement(value = '', textContent = '') {
    return {
        value,
        textContent,
        attributes: {},
        setAttribute: function (name, val) {
            this.attributes[name] = val;
        },
        getAttribute: function (name) {
            return this.attributes[name];
        },
    };
}

module.exports = {
    checkDataverse,
    setMockDom,
    setMockGlobals,
    setMockFetch,
    createMockElement,
};
