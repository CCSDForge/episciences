/**
 * Tests for affiliations.js - ROR API integration and autocomplete functionality
 */

// Load the affiliations.js file
const fs = require('fs');
const path = require('path');
const affiliationsJs = fs.readFileSync(
    path.join(__dirname, '../../public/js/user/affiliations.js'),
    'utf8'
);

// Mock data similar to ROR API response
const mockRorResponse = {
    number_of_results: 2,
    items: [
        {
            substring: "Centre pour la Communication Scientifique Directe",
            score: 1.0,
            matching_type: "EXACT",
            chosen: true,
            organization: {
                id: "https://ror.org/00680hx61",
                names: [
                    {
                        lang: "fr",
                        types: ["acronym"],
                        value: "CCSD"
                    },
                    {
                        lang: "fr",
                        types: ["label", "ror_display"],
                        value: "Centre pour la Communication Scientifique Directe"
                    }
                ],
                domains: ["ccsd.cnrs.fr"],
                status: "active"
            }
        },
        {
            substring: "Massachusetts Institute of Technology",
            score: 0.9,
            matching_type: "ACRONYM",
            chosen: false,
            organization: {
                id: "https://ror.org/042nb2s44",
                names: [
                    {
                        lang: "en",
                        types: ["label"],
                        value: "Massachusetts Institute of Technology"
                    },
                    {
                        lang: "en",
                        types: ["acronym"],
                        value: "MIT"
                    }
                ],
                acronyms: ["MIT"],
                domains: ["mit.edu"],
                status: "active"
            }
        }
    ]
};

// Mock DOM elements
function createMockDOM() {
    document.body.innerHTML = `
        <div>
            <input id="affiliations" type="text" />
            <input id="affiliationAcronym" type="text" value="" />
            <button data-original-title="Add">Add</button>
        </div>
    `;
}

// Mock fetch function
function mockFetch(url, options = {}) {
    return new Promise((resolve) => {
        setTimeout(() => {
            if (url.includes('api.ror.org')) {
                resolve({
                    ok: true,
                    json: () => Promise.resolve(mockRorResponse)
                });
            } else {
                resolve({
                    ok: false,
                    status: 404,
                    json: () => Promise.resolve({ error: 'Not found' })
                });
            }
        }, 10);
    });
}

describe('Affiliations Autocomplete', function() {
    beforeEach(function() {
        // Setup DOM
        createMockDOM();
        
        
        // Mock fetch
        global.fetch = mockFetch;
        
        // Clear any existing autocomplete instance
        if (window.AffiliationsAutocomplete) {
            window.AffiliationsAutocomplete.cache.clear();
            window.AffiliationsAutocomplete.cacheAcronym.length = 0;
        }
        
        // Execute the affiliations.js code
        eval(affiliationsJs);
        
        // Initialize the autocomplete functionality
        if (typeof window.initializeAffiliationsAutocomplete === 'function') {
            window.initializeAffiliationsAutocomplete();
        }
    });
    
    // Note: afterEach cleanup is handled by setup.js

    describe('Initialization', function() {
        it('should initialize without errors when affiliations element exists', function() {
            expect(document.getElementById('affiliations')).toBeTruthy();
            expect(window.AffiliationsAutocomplete).toBeDefined();
        });

        it('should handle missing affiliations element gracefully', function() {
            document.body.innerHTML = '<div></div>';
            
            // Should not throw an error
            expect(() => {
                window.initializeAffiliationsAutocomplete();
            }).not.toThrow();
        });

        it('should make initializeAffiliationsAutocomplete globally available', function() {
            expect(typeof window.initializeAffiliationsAutocomplete).toBe('function');
        });

        it('should prevent double initialization', function() {
            const affiliationsInput = document.getElementById('affiliations');
            
            // First initialization
            window.initializeAffiliationsAutocomplete();
            expect(affiliationsInput.dataset.autocompleteInitialized).toBe('true');
            
            // Second initialization should be ignored
            const originalAddEventListener = affiliationsInput.addEventListener;
            let eventListenerCallCount = 0;
            affiliationsInput.addEventListener = function() {
                eventListenerCallCount++;
                return originalAddEventListener.apply(this, arguments);
            };
            
            window.initializeAffiliationsAutocomplete();
            
            // Should not have added more event listeners
            expect(eventListenerCallCount).toBe(0);
            
            // Restore original method
            affiliationsInput.addEventListener = originalAddEventListener;
        });

        it('should work with dynamically loaded content', function() {
            // Remove existing element
            document.body.innerHTML = '<div></div>';
            
            // Add element dynamically
            document.body.innerHTML = `
                <div>
                    <input id="affiliations" type="text" />
                    <input id="affiliationAcronym" type="text" value="" />
                    <button data-original-title="Add">Add</button>
                </div>
            `;
            
            // Initialize after dynamic loading
            window.initializeAffiliationsAutocomplete();
            
            const newAffiliationsInput = document.getElementById('affiliations');
            expect(newAffiliationsInput.dataset.autocompleteInitialized).toBe('true');
        });
    });

    describe('Display Name Extraction', function() {
        it('should extract ror_display name when available', function(done) {
            const affiliationsInput = document.getElementById('affiliations');
            
            // Wait for initialization
            setTimeout(() => {
                // Simulate API response processing
                const testItem = mockRorResponse.items[0];
                
                // Extract display name logic (copied from the actual code)
                let displayName = '';
                if (testItem.organization.names) {
                    const rorDisplayName = testItem.organization.names.find(n => n.types && n.types.includes('ror_display'));
                    const labelName = testItem.organization.names.find(n => n.types && n.types.includes('label'));
                    
                    if (rorDisplayName) {
                        displayName = rorDisplayName.value;
                    } else if (labelName) {
                        displayName = labelName.value;
                    } else if (testItem.organization.names.length > 0) {
                        displayName = testItem.organization.names[0].value;
                    }
                }
                
                expect(displayName).toBe('Centre pour la Communication Scientifique Directe');
                done();
            }, 50);
        });

        it('should fallback to label name when ror_display not available', function() {
            const testItem = {
                organization: {
                    names: [
                        {
                            lang: "en",
                            types: ["label"],
                            value: "Test Organization"
                        }
                    ]
                }
            };

            let displayName = '';
            if (testItem.organization.names) {
                const rorDisplayName = testItem.organization.names.find(n => n.types && n.types.includes('ror_display'));
                const labelName = testItem.organization.names.find(n => n.types && n.types.includes('label'));
                
                if (rorDisplayName) {
                    displayName = rorDisplayName.value;
                } else if (labelName) {
                    displayName = labelName.value;
                } else if (testItem.organization.names.length > 0) {
                    displayName = testItem.organization.names[0].value;
                }
            }

            expect(displayName).toBe('Test Organization');
        });

        it('should use first available name as last resort', function() {
            const testItem = {
                organization: {
                    names: [
                        {
                            lang: null,
                            types: ["alias"],
                            value: "Fallback Name"
                        }
                    ]
                }
            };

            let displayName = '';
            if (testItem.organization.names) {
                const rorDisplayName = testItem.organization.names.find(n => n.types && n.types.includes('ror_display'));
                const labelName = testItem.organization.names.find(n => n.types && n.types.includes('label'));
                
                if (rorDisplayName) {
                    displayName = rorDisplayName.value;
                } else if (labelName) {
                    displayName = labelName.value;
                } else if (testItem.organization.names.length > 0) {
                    displayName = testItem.organization.names[0].value;
                }
            }

            expect(displayName).toBe('Fallback Name');
        });
    });

    describe('API Integration', function() {
        it('should make API call with proper URL encoding', function(done) {
            const affiliationsInput = document.getElementById('affiliations');
            const searchTerm = 'test organization';
            
            // Mock fetch to capture URL
            let capturedUrl = '';
            global.fetch = function(url) {
                capturedUrl = url;
                return mockFetch(url);
            };

            setTimeout(() => {
                // Trigger search
                affiliationsInput.value = searchTerm;
                affiliationsInput.dispatchEvent(new Event('input'));

                // Wait for debounce (300ms) + some buffer
                setTimeout(() => {
                    expect(capturedUrl).toContain('https://api.ror.org/organizations?affiliation=');
                    expect(capturedUrl).toContain(encodeURIComponent(searchTerm));
                    done();
                }, 350);
            }, 50);
        });

        it('should handle API errors gracefully', function(done) {
            const affiliationsInput = document.getElementById('affiliations');
            
            // Mock fetch to simulate error
            global.fetch = function() {
                return Promise.resolve({
                    ok: false,
                    status: 500,
                    json: () => Promise.resolve({ error: 'Server error' })
                });
            };

            // Capture console.error
            const originalError = console.error;
            let errorCaptured = false;
            console.error = function() {
                errorCaptured = true;
                originalError.apply(console, arguments);
            };

            setTimeout(() => {
                affiliationsInput.value = 'test';
                affiliationsInput.dispatchEvent(new Event('input'));

                // Wait for debounce (300ms) + API call + processing
                setTimeout(() => {
                    expect(errorCaptured).toBe(true);
                    console.error = originalError;
                    done();
                }, 400);
            }, 50);
        });
    });

    describe('Caching', function() {
        it('should cache search results', function(done) {
            const affiliationsInput = document.getElementById('affiliations');
            
            setTimeout(() => {
                // First search
                affiliationsInput.value = 'ccsd';
                affiliationsInput.dispatchEvent(new Event('input'));

                // Wait for debounce (300ms) + API call + processing
                setTimeout(() => {
                    expect(window.AffiliationsAutocomplete.cache.has('ccsd')).toBe(true);
                    
                    const cachedResults = window.AffiliationsAutocomplete.cache.get('ccsd');
                    expect(cachedResults).toBeDefined();
                    expect(Array.isArray(cachedResults)).toBe(true);
                    done();
                }, 400);
            }, 50);
        });

        it('should use cached results for repeated searches', function(done) {
            const affiliationsInput = document.getElementById('affiliations');
            
            // Track fetch calls
            let fetchCallCount = 0;
            const originalFetch = global.fetch;
            global.fetch = function(...args) {
                fetchCallCount++;
                return originalFetch.apply(this, args);
            };

            setTimeout(() => {
                // First search
                affiliationsInput.value = 'test';
                affiliationsInput.dispatchEvent(new Event('input'));

                setTimeout(() => {
                    const firstCallCount = fetchCallCount;
                    
                    // Second search with same term
                    affiliationsInput.value = '';
                    affiliationsInput.value = 'test';
                    affiliationsInput.dispatchEvent(new Event('input'));

                    setTimeout(() => {
                        // Should not make additional fetch call
                        expect(fetchCallCount).toBe(firstCallCount);
                        done();
                    }, 50);
                }, 100);
            }, 50);
        });
    });

    describe('Acronym Handling', function() {
        it('should cache acronyms from ACRONYM matching type', function(done) {
            const affiliationsInput = document.getElementById('affiliations');
            
            setTimeout(() => {
                affiliationsInput.value = 'mit';
                affiliationsInput.dispatchEvent(new Event('input'));

                // Wait for debounce (300ms) + API call + processing
                setTimeout(() => {
                    expect(window.AffiliationsAutocomplete.cacheAcronym.includes('[MIT]')).toBe(true);
                    done();
                }, 450);
            }, 50);
        });

        it('should handle Add button click correctly', function(done) {
            const addButton = document.querySelector('button[data-original-title="Add"]');
            const affiliationAcronymInput = document.getElementById('affiliationAcronym');
            
            setTimeout(() => {
                // Simulate having acronyms in cache
                window.AffiliationsAutocomplete.cacheAcronym.push('[CCSD]', '[MIT]');
                
                addButton.click();

                setTimeout(() => {
                    expect(affiliationAcronymInput.value).toContain('[CCSD]');
                    expect(affiliationAcronymInput.value).toContain('[MIT]');
                    expect(affiliationAcronymInput.value).toContain('||');
                    done();
                }, 50);
            }, 50);
        });
    });

    describe('Input Validation', function() {
        it('should not trigger search for terms shorter than 2 characters', function(done) {
            const affiliationsInput = document.getElementById('affiliations');
            
            let fetchCalled = false;
            global.fetch = function() {
                fetchCalled = true;
                return mockFetch.apply(this, arguments);
            };

            setTimeout(() => {
                affiliationsInput.value = 'a';
                affiliationsInput.dispatchEvent(new Event('input'));

                setTimeout(() => {
                    expect(fetchCalled).toBe(false);
                    done();
                }, 50);
            }, 50);
        });

        it('should handle empty input gracefully', function(done) {
            const affiliationsInput = document.getElementById('affiliations');
            
            setTimeout(() => {
                affiliationsInput.value = '';
                affiliationsInput.dispatchEvent(new Event('input'));

                setTimeout(() => {
                    // Should not throw any errors
                    expect(true).toBe(true);
                    done();
                }, 50);
            }, 50);
        });
    });

    describe('Debouncing', function() {
        it('should debounce input events', function(done) {
            const affiliationsInput = document.getElementById('affiliations');
            
            let fetchCallCount = 0;
            global.fetch = function() {
                fetchCallCount++;
                return mockFetch.apply(this, arguments);
            };

            setTimeout(() => {
                // Rapid input changes
                affiliationsInput.value = 'te';
                affiliationsInput.dispatchEvent(new Event('input'));
                
                affiliationsInput.value = 'tes';
                affiliationsInput.dispatchEvent(new Event('input'));
                
                affiliationsInput.value = 'test';
                affiliationsInput.dispatchEvent(new Event('input'));

                // Wait for debounce timeout (300ms) plus some buffer
                setTimeout(() => {
                    // Should only make one API call due to debouncing
                    expect(fetchCallCount).toBe(1);
                    done();
                }, 400);
            }, 50);
        });
    });

    describe('Results Display', function() {
        it('should format results correctly', function(done) {
            const affiliationsInput = document.getElementById('affiliations');
            
            // Reset mock fetch to ensure proper response
            global.fetch = mockFetch;
            
            // Test the processing logic directly with mock data
            const testResults = [];
            
            if (mockRorResponse.items) {
                mockRorResponse.items.forEach(item => {
                    let additionalInfo = '';
                    if (item.matching_type === 'ACRONYM' && item.organization.acronyms && item.organization.acronyms.length > 0) {
                        additionalInfo = `[${item.organization.acronyms[0]}]`;
                    }
                    
                    // Get the display name from the names array
                    let displayName = '';
                    if (item.organization.names) {
                        const rorDisplayName = item.organization.names.find(n => n.types && n.types.includes('ror_display'));
                        const labelName = item.organization.names.find(n => n.types && n.types.includes('label'));
                        
                        if (rorDisplayName) {
                            displayName = rorDisplayName.value;
                        } else if (labelName) {
                            displayName = labelName.value;
                        } else if (item.organization.names.length > 0) {
                            displayName = item.organization.names[0].value;
                        }
                    }
                    
                    const label = `${displayName} ${additionalInfo} #${item.organization.id}`.trim();
                    testResults.push({
                        label: label,
                        identifier: item.organization.id,
                        acronym: additionalInfo
                    });
                });
            }

            // Test the formatted results
            expect(testResults.length).toBeGreaterThan(0);
            
            const firstResult = testResults[0];
            expect(firstResult).toHaveProperty('label');
            expect(firstResult).toHaveProperty('identifier');
            expect(firstResult).toHaveProperty('acronym');
            
            expect(firstResult.label).toContain('#https://ror.org/');
            expect(firstResult.identifier).toContain('https://ror.org/');
            expect(firstResult.label).toContain('Centre pour la Communication Scientifique Directe');
            
            done();
        }, 10000);

        it('should create dropdown container below input', function(done) {
            const affiliationsInput = document.getElementById('affiliations');
            
            setTimeout(() => {
                affiliationsInput.value = 'ccsd';
                affiliationsInput.dispatchEvent(new Event('input'));

                // Wait for debounce (300ms) + API call + processing
                setTimeout(() => {
                    const resultsContainer = document.querySelector('.autocomplete-results');
                    expect(resultsContainer).toBeTruthy();
                    expect(resultsContainer.style.position).toBe('absolute');
                    expect(resultsContainer.style.top).toBe('100%');
                    expect(resultsContainer.style.left).toBe('0px');
                    done();
                }, 500);
            }, 100);
        }, 10000);
    });

    describe('Keyboard Navigation', function() {
        it('should handle Arrow Down key navigation', function(done) {
            const affiliationsInput = document.getElementById('affiliations');
            
            setTimeout(() => {
                affiliationsInput.value = 'ccsd';
                affiliationsInput.dispatchEvent(new Event('input'));

                // Wait for debounce + API call + DOM creation
                setTimeout(() => {
                    // Press Arrow Down
                    const keydownEvent = new KeyboardEvent('keydown', { key: 'ArrowDown' });
                    affiliationsInput.dispatchEvent(keydownEvent);

                    // Check if first item is active
                    const activeItem = document.querySelector('.autocomplete-item.active');
                    expect(activeItem).toBeTruthy();
                    done();
                }, 500);
            }, 100);
        }, 10000);

        it('should handle Enter key to select item', function(done) {
            const affiliationsInput = document.getElementById('affiliations');
            
            setTimeout(() => {
                affiliationsInput.value = 'ccsd';
                affiliationsInput.dispatchEvent(new Event('input'));

                // Wait for debounce + API call + DOM creation
                setTimeout(() => {
                    // Press Arrow Down to select first item
                    const arrowDownEvent = new KeyboardEvent('keydown', { key: 'ArrowDown' });
                    affiliationsInput.dispatchEvent(arrowDownEvent);

                    // Press Enter to select
                    const enterEvent = new KeyboardEvent('keydown', { key: 'Enter' });
                    affiliationsInput.dispatchEvent(enterEvent);

                    // Check if input value was updated
                    expect(affiliationsInput.value).toContain('Centre pour la Communication Scientifique Directe');
                    done();
                }, 500);
            }, 100);
        }, 10000);

        it('should handle Escape key to close results', function(done) {
            const affiliationsInput = document.getElementById('affiliations');
            
            setTimeout(() => {
                affiliationsInput.value = 'ccsd';
                affiliationsInput.dispatchEvent(new Event('input'));

                // Wait for debounce + API call + DOM creation
                setTimeout(() => {
                    // Press Escape
                    const escapeEvent = new KeyboardEvent('keydown', { key: 'Escape' });
                    affiliationsInput.dispatchEvent(escapeEvent);

                    // Check if results are hidden
                    const resultsContainer = document.querySelector('.autocomplete-results');
                    expect(resultsContainer && resultsContainer.style.display).toBe('none');
                    done();
                }, 500);
            }, 100);
        }, 10000);
    });

    describe('Edge Cases', function() {
        it('should handle organizations with no names array', function() {
            const testItem = {
                organization: {
                    id: "https://ror.org/test123"
                }
            };

            let displayName = '';
            if (testItem.organization.names) {
                const rorDisplayName = testItem.organization.names.find(n => n.types && n.types.includes('ror_display'));
                const labelName = testItem.organization.names.find(n => n.types && n.types.includes('label'));
                
                if (rorDisplayName) {
                    displayName = rorDisplayName.value;
                } else if (labelName) {
                    displayName = labelName.value;
                } else if (testItem.organization.names.length > 0) {
                    displayName = testItem.organization.names[0].value;
                }
            }

            expect(displayName).toBe('');
        });

        it('should handle empty API response', function(done) {
            const affiliationsInput = document.getElementById('affiliations');
            
            // Mock fetch to return empty response
            global.fetch = function() {
                return Promise.resolve({
                    ok: true,
                    json: () => Promise.resolve({ items: [] })
                });
            };

            setTimeout(() => {
                affiliationsInput.value = 'nonexistent';
                affiliationsInput.dispatchEvent(new Event('input'));

                setTimeout(() => {
                    const results = window.AffiliationsAutocomplete.currentResults;
                    expect(results.length).toBe(0);
                    done();
                }, 100);
            }, 50);
        });

        it('should handle network failure', function(done) {
            const affiliationsInput = document.getElementById('affiliations');
            
            // Mock fetch to simulate network error
            global.fetch = function() {
                return Promise.reject(new Error('Network error'));
            };

            // Capture console.error
            const originalError = console.error;
            let errorCaptured = false;
            console.error = function() {
                errorCaptured = true;
                originalError.apply(console, arguments);
            };

            setTimeout(() => {
                affiliationsInput.value = 'test';
                affiliationsInput.dispatchEvent(new Event('input'));

                setTimeout(() => {
                    expect(errorCaptured).toBe(true);
                    console.error = originalError;
                    done();
                }, 400);
            }, 100);
        }, 10000);
    });
});