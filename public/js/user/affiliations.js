// Function to initialize affiliations autocomplete
function initializeAffiliationsAutocomplete() {
    const affiliationsElement = document.getElementById('affiliations');
    if (!affiliationsElement) return;

    // Check if already initialized
    if (affiliationsElement.dataset.autocompleteInitialized) return;
    affiliationsElement.dataset.autocompleteInitialized = 'true';

    let cache = new Map();
    let cacheAcronym = [];
    let flagAddPreviousAffiliationWithNew = 0;
    let currentResults = [];
    let activeIndex = -1;
    let resultsContainer = null;

    // Create autocomplete functionality
    function createAutocomplete() {
        let debounceTimer;

        affiliationsElement.addEventListener('input', function (e) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                handleInput(e.target.value);
            }, 300);
        });

        affiliationsElement.addEventListener('keydown', handleKeydown);
        affiliationsElement.addEventListener('focus', handleFocus);

        // Close autocomplete when clicking outside
        document.addEventListener('click', function (e) {
            if (
                !affiliationsElement.contains(e.target) &&
                !resultsContainer?.contains(e.target)
            ) {
                hideResults();
            }
        });
    }

    function handleInput(term) {
        if (!term || term.length < 2) {
            hideResults();
            return;
        }

        // Check cache first
        if (cache.has(term)) {
            displayResults(cache.get(term));
            return;
        }

        searchAffiliations(term);
    }

    function handleKeydown(e) {
        if (!resultsContainer || currentResults.length === 0) return;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                activeIndex = Math.min(
                    activeIndex + 1,
                    currentResults.length - 1
                );
                updateActiveItem();
                break;
            case 'ArrowUp':
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, -1);
                updateActiveItem();
                break;
            case 'Enter':
                e.preventDefault();
                if (activeIndex >= 0) {
                    selectItem(currentResults[activeIndex]);
                }
                break;
            case 'Escape':
                hideResults();
                break;
        }
    }

    function handleFocus() {
        if (affiliationsElement.value) {
            handleInput(affiliationsElement.value);
        }
    }

    function searchAffiliations(term) {
        const url = `https://api.ror.org/organizations?affiliation=${encodeURIComponent(term)}`;

        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(rorResponse => {
                const availableAffiliations = [];

                if (rorResponse.items) {
                    rorResponse.items.forEach(item => {
                        let additionalInfo = '';
                        if (
                            item.matching_type === 'ACRONYM' &&
                            item.organization.acronyms &&
                            item.organization.acronyms.length > 0
                        ) {
                            additionalInfo = `[${item.organization.acronyms[0]}]`;
                            cacheAcronym.push(additionalInfo);
                            cacheAcronym = [...new Set(cacheAcronym)];
                        }

                        // Get the display name from the names array
                        let displayName = '';
                        if (item.organization.names) {
                            const rorDisplayName = item.organization.names.find(
                                n => n.types && n.types.includes('ror_display')
                            );
                            const labelName = item.organization.names.find(
                                n => n.types && n.types.includes('label')
                            );

                            if (rorDisplayName) {
                                displayName = rorDisplayName.value;
                            } else if (labelName) {
                                displayName = labelName.value;
                            } else if (item.organization.names.length > 0) {
                                displayName = item.organization.names[0].value;
                            }
                        }

                        const label =
                            `${displayName} ${additionalInfo} #${item.organization.id}`.trim();
                        availableAffiliations.push({
                            label: label,
                            identifier: item.organization.id,
                            acronym: additionalInfo,
                        });
                    });

                    cache.set(term, availableAffiliations);
                    displayResults(availableAffiliations);
                }
            })
            .catch(error => {
                console.error('Error fetching affiliations:', error);
            });
    }

    function displayResults(results) {
        currentResults = results;
        activeIndex = -1;

        if (!resultsContainer) {
            createResultsContainer();
        }

        if (results.length === 0) {
            hideResults();
            return;
        }

        resultsContainer.innerHTML = '';

        results.forEach((result, index) => {
            const item = document.createElement('div');
            item.className = 'autocomplete-item';
            item.textContent = result.label;
            item.addEventListener('click', () => selectItem(result));
            item.addEventListener('mouseenter', () => {
                activeIndex = index;
                updateActiveItem();
            });
            resultsContainer.appendChild(item);
        });

        showResults();
    }

    function createResultsContainer() {
        resultsContainer = document.createElement('div');
        resultsContainer.className = 'autocomplete-results';
        resultsContainer.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            background: white;
            border: 1px solid #ccc;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            width: 100%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        `;

        // Add CSS for items
        const style = document.createElement('style');
        style.textContent = `
            .autocomplete-results .autocomplete-item {
                padding: 8px 12px;
                cursor: pointer;
                border-bottom: 1px solid #eee;
            }
            .autocomplete-results .autocomplete-item:hover,
            .autocomplete-results .autocomplete-item.active {
                background-color: #f0f0f0;
            }
            .autocomplete-results .autocomplete-item:last-child {
                border-bottom: none;
            }
        `;
        document.head.appendChild(style);

        affiliationsElement.parentNode.style.position = 'relative';
        affiliationsElement.parentNode.appendChild(resultsContainer);
    }

    function showResults() {
        if (resultsContainer) {
            resultsContainer.style.display = 'block';
        }
    }

    function hideResults() {
        if (resultsContainer) {
            resultsContainer.style.display = 'none';
        }
        currentResults = [];
        activeIndex = -1;
    }

    function updateActiveItem() {
        if (!resultsContainer) return;

        const items = resultsContainer.querySelectorAll('.autocomplete-item');
        items.forEach((item, index) => {
            item.classList.toggle('active', index === activeIndex);
        });
    }

    function selectItem(item) {
        affiliationsElement.value = item.label;
        hideResults();

        // Trigger change event for any listeners
        const changeEvent = new Event('change', { bubbles: true });
        affiliationsElement.dispatchEvent(changeEvent);
    }

    // Handle Add button click
    function handleAddButton() {
        const addButton = document.querySelector(
            'button[data-original-title="Add"]'
        );
        if (addButton) {
            addButton.addEventListener('click', function (e) {
                const affiliationAcronymInput =
                    document.getElementById('affiliationAcronym');
                if (affiliationAcronymInput) {
                    let strAcronym = '';
                    const numberOfAcronyms = cacheAcronym.length;

                    if (
                        flagAddPreviousAffiliationWithNew !== 1 &&
                        affiliationAcronymInput.value !== ''
                    ) {
                        strAcronym += affiliationAcronymInput.value + '||';
                        flagAddPreviousAffiliationWithNew = 1;
                    }

                    cacheAcronym.forEach((acronym, index) => {
                        strAcronym += acronym;
                        if (index < numberOfAcronyms - 1) {
                            strAcronym += '||';
                        }
                    });

                    affiliationAcronymInput.value = strAcronym;
                }
            });
        }
    }

    // Initialize
    createAutocomplete();
    handleAddButton();

    // Export functions for testing
    window.AffiliationsAutocomplete = {
        searchAffiliations,
        displayResults,
        selectItem,
        cache,
        cacheAcronym,
        currentResults,
    };
}

// Make the function globally available
window.initializeAffiliationsAutocomplete = initializeAffiliationsAutocomplete;

// Initialize on DOM ready for pages that have the form initially
document.addEventListener(
    'DOMContentLoaded',
    initializeAffiliationsAutocomplete
);

// Also initialize immediately if DOM is already loaded
if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        initializeAffiliationsAutocomplete
    );
} else {
    initializeAffiliationsAutocomplete();
}
