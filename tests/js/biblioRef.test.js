/**
 * Tests for BiblioRef module
 */

const {
    BiblioRefService,
    BiblioRefParser,
    BiblioRefRenderer,
    BiblioRefManager,
} = require('../../public/js/paper/biblioRef');

// Mock fetch globally
global.fetch = jest.fn();

describe('BiblioRefService', () => {
    let service;

    beforeEach(() => {
        service = new BiblioRefService();
        fetch.mockClear();
    });

    describe('fetchCitations', () => {
        it('should fetch citations successfully', async () => {
            const mockResponse = {
                0: { ref: '{"raw_reference": "Test citation"}', isAccepted: 1 },
            };

            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockResponse,
            });

            const result = await service.fetchCitations(
                'https://api.test.com',
                'https://paper.com/123'
            );

            expect(fetch).toHaveBeenCalledWith(
                'https://api.test.com/visualize-citations?url=https%3A%2F%2Fpaper.com%2F123'
            );
            expect(result).toEqual(mockResponse);
        });

        it('should include showAll parameter when true', async () => {
            const mockResponse = {};

            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockResponse,
            });

            await service.fetchCitations(
                'https://api.test.com',
                'https://paper.com/123',
                true
            );

            expect(fetch).toHaveBeenCalledWith(
                'https://api.test.com/visualize-citations?url=https%3A%2F%2Fpaper.com%2F123&all=1'
            );
        });

        it('should throw error when apiUrl is missing', async () => {
            await expect(
                service.fetchCitations('', 'https://paper.com/123')
            ).rejects.toThrow('API URL and paper URL are required');
        });

        it('should throw error when paperUrl is missing', async () => {
            await expect(
                service.fetchCitations('https://api.test.com', '')
            ).rejects.toThrow('API URL and paper URL are required');
        });

        it('should handle HTTP errors', async () => {
            fetch.mockResolvedValueOnce({
                ok: false,
                status: 404,
                json: async () => ({ message: 'Not found' }),
            });

            await expect(
                service.fetchCitations(
                    'https://api.test.com',
                    'https://paper.com/123'
                )
            ).rejects.toThrow('Not found');
        });

        it('should handle HTTP errors without message', async () => {
            fetch.mockResolvedValueOnce({
                ok: false,
                status: 500,
                json: async () => {
                    throw new Error('Invalid JSON');
                },
            });

            await expect(
                service.fetchCitations(
                    'https://api.test.com',
                    'https://paper.com/123'
                )
            ).rejects.toThrow('HTTP error! status: 500');
        });

        it('should encode URL parameters properly', async () => {
            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({}),
            });

            await service.fetchCitations(
                'https://api.test.com',
                'https://paper.com/test?q=foo&bar=baz'
            );

            expect(fetch).toHaveBeenCalledWith(
                expect.stringContaining(
                    'url=https%3A%2F%2Fpaper.com%2Ftest%3Fq%3Dfoo%26bar%3Dbaz'
                )
            );
        });
    });
});

describe('BiblioRefParser', () => {
    describe('parseCitation', () => {
        it('should parse valid citation', () => {
            const citation = {
                ref: '{"raw_reference": "Test citation", "doi": "10.1234/test"}',
                isAccepted: 1,
            };

            const result = BiblioRefParser.parseCitation(citation, true);

            expect(result).toEqual({
                rawReference: 'Test citation',
                doi: '10.1234/test',
                isAccepted: true,
                showAccepted: true,
            });
        });

        it('should parse citation without DOI', () => {
            const citation = {
                ref: '{"raw_reference": "Test citation"}',
                isAccepted: 0,
            };

            const result = BiblioRefParser.parseCitation(citation, false);

            expect(result).toEqual({
                rawReference: 'Test citation',
                doi: undefined,
                isAccepted: false,
                showAccepted: false,
            });
        });

        it('should hide acceptance status when not authorized', () => {
            const citation = {
                ref: '{"raw_reference": "Test citation"}',
                isAccepted: 1,
            };

            const result = BiblioRefParser.parseCitation(citation, false);

            expect(result.showAccepted).toBe(false);
        });

        it('should return null for missing citation', () => {
            expect(BiblioRefParser.parseCitation(null)).toBeNull();
        });

        it('should return null for citation without ref', () => {
            expect(BiblioRefParser.parseCitation({ isAccepted: 1 })).toBeNull();
        });

        it('should handle invalid JSON gracefully', () => {
            const citation = {
                ref: 'invalid json',
                isAccepted: 1,
            };

            const consoleSpy = jest
                .spyOn(console, 'error')
                .mockImplementation(() => {});
            const result = BiblioRefParser.parseCitation(citation);

            expect(result).toBeNull();
            expect(consoleSpy).toHaveBeenCalled();
            consoleSpy.mockRestore();
        });
    });

    describe('formatDoi', () => {
        it('should format valid DOI with URL', () => {
            const result = BiblioRefParser.formatDoi('10.1234/test.doi');

            expect(result).toEqual({
                url: 'https://doi.org/10.1234/test.doi',
                text: '10.1234/test.doi',
            });
        });

        it('should handle DOI with special characters', () => {
            const doi = '10.1234/test-doi_2023(v1):test';
            const result = BiblioRefParser.formatDoi(doi);

            expect(result).toEqual({
                url: `https://doi.org/${doi}`,
                text: doi,
            });
        });

        it('should handle DOI with path separator', () => {
            const doi = '10.1234/test/subpath';
            const result = BiblioRefParser.formatDoi(doi);

            expect(result.url).toBe('https://doi.org/10.1234/test/subpath');
        });

        it('should pass through invalid DOI as-is', () => {
            const invalidDoi = 'https://doi.org/10.1234/test';
            const result = BiblioRefParser.formatDoi(invalidDoi);

            expect(result).toEqual({
                url: invalidDoi,
                text: invalidDoi,
            });
        });

        it('should return null for empty DOI', () => {
            expect(BiblioRefParser.formatDoi('')).toBeNull();
            expect(BiblioRefParser.formatDoi(null)).toBeNull();
        });

        it('should validate DOI format with regex', () => {
            // Valid DOIs
            expect(BiblioRefParser.formatDoi('10.1234/test').url).toContain(
                'https://doi.org/'
            );
            expect(BiblioRefParser.formatDoi('10.12345/test').url).toContain(
                'https://doi.org/'
            );
            expect(
                BiblioRefParser.formatDoi('10.123456789/test').url
            ).toContain('https://doi.org/');

            // Invalid DOIs (wrong prefix)
            expect(BiblioRefParser.formatDoi('11.1234/test').url).toBe(
                '11.1234/test'
            );
            expect(BiblioRefParser.formatDoi('10.123/test').url).toBe(
                '10.123/test'
            ); // Too few digits
        });

        it('should handle case-insensitive DOI', () => {
            const result = BiblioRefParser.formatDoi('10.1234/TEST-DOI');

            expect(result.url).toBe('https://doi.org/10.1234/TEST-DOI');
        });
    });

    describe('DOI_REGEX', () => {
        it('should validate correct DOI formats', () => {
            const validDOIs = [
                '10.1234/test',
                '10.12345/test',
                '10.123456789/test.doi',
                '10.1234/test-doi',
                '10.1234/test_doi',
                '10.1234/test.doi',
                '10.1234/test(v1)',
                '10.1234/test:2023',
                '10.1234/TEST-DOI',
            ];

            validDOIs.forEach(doi => {
                expect(BiblioRefParser.DOI_REGEX.test(doi)).toBe(true);
            });
        });

        it('should reject incorrect DOI formats', () => {
            const invalidDOIs = [
                '11.1234/test', // Wrong prefix
                '10.123/test', // Too few digits in prefix
                '10.12345678901/test', // Too many digits in prefix
                'not-a-doi',
                '10.1234', // Missing suffix
                '',
            ];

            invalidDOIs.forEach(doi => {
                expect(BiblioRefParser.DOI_REGEX.test(doi)).toBe(false);
            });
        });
    });
});

describe('BiblioRefRenderer', () => {
    let container;
    let renderer;

    beforeEach(() => {
        container = document.createElement('ul');
        renderer = new BiblioRefRenderer(container);
    });

    describe('constructor', () => {
        it('should throw error when container is missing', () => {
            expect(() => new BiblioRefRenderer(null)).toThrow(
                'Container element is required'
            );
        });

        it('should set container property', () => {
            expect(renderer.container).toBe(container);
        });
    });

    describe('renderCitation', () => {
        it('should render citation with all fields', () => {
            const citation = {
                rawReference: 'Test citation',
                doi: '10.1234/test',
                showAccepted: true,
            };

            const li = renderer.renderCitation(citation);

            expect(li.tagName).toBe('LI');
            expect(li.innerHTML).toContain('Test citation');
            expect(li.innerHTML).toContain('fa-check');
            expect(li.innerHTML).toContain('https://doi.org/10.1234/test');
        });

        it('should render citation without acceptance icon', () => {
            const citation = {
                rawReference: 'Test citation',
                showAccepted: false,
            };

            const li = renderer.renderCitation(citation);

            expect(li.innerHTML).not.toContain('fa-check');
            expect(li.innerHTML).toContain('Test citation');
        });

        it('should render citation without DOI', () => {
            const citation = {
                rawReference: 'Test citation',
                showAccepted: false,
            };

            const li = renderer.renderCitation(citation);

            expect(li.innerHTML).toContain('Test citation');
            expect(li.innerHTML).not.toContain('href=');
        });

        it('should escape HTML in citation text', () => {
            const citation = {
                rawReference: '<script>alert("xss")</script>',
                showAccepted: false,
            };

            const li = renderer.renderCitation(citation);

            expect(li.innerHTML).toContain('&lt;script&gt;');
            expect(li.innerHTML).not.toContain('<script>');
        });

        it('should escape HTML in DOI', () => {
            const citation = {
                rawReference: 'Test',
                doi: '"><script>alert("xss")</script>',
                showAccepted: false,
            };

            const li = renderer.renderCitation(citation);

            expect(li.innerHTML).not.toContain('<script>');
        });
    });

    describe('renderSource', () => {
        it('should render source attribution', () => {
            const source = renderer.renderSource();

            expect(source.tagName).toBe('SMALL');
            expect(source.className).toBe('label label-default');
            expect(source.textContent).toBe('Sources : Semantic Scholar');
        });
    });

    describe('renderError', () => {
        it('should render error message', () => {
            const error = renderer.renderError('Test error');

            expect(error.tagName).toBe('LI');
            expect(error.textContent).toBe('Test error');
            expect(error.className).toBe('biblio-ref-error');
        });
    });

    describe('renderCitations', () => {
        it('should render multiple citations', () => {
            const citations = [
                { rawReference: 'Citation 1', showAccepted: false },
                { rawReference: 'Citation 2', showAccepted: true },
                {
                    rawReference: 'Citation 3',
                    doi: '10.1234/test',
                    showAccepted: false,
                },
            ];

            renderer.renderCitations(citations);

            expect(container.children.length).toBe(4); // 3 citations + 1 source
            expect(container.innerHTML).toContain('Citation 1');
            expect(container.innerHTML).toContain('Citation 2');
            expect(container.innerHTML).toContain('Citation 3');
            expect(container.innerHTML).toContain('Semantic Scholar');
        });

        it('should clear existing content', () => {
            container.innerHTML = '<li>Old content</li>';

            renderer.renderCitations([
                { rawReference: 'New citation', showAccepted: false },
            ]);

            expect(container.innerHTML).not.toContain('Old content');
            expect(container.innerHTML).toContain('New citation');
        });

        it('should use document fragment for performance', () => {
            const createFragmentSpy = jest.spyOn(
                document,
                'createDocumentFragment'
            );

            renderer.renderCitations([
                { rawReference: 'Test', showAccepted: false },
            ]);

            expect(createFragmentSpy).toHaveBeenCalled();
            createFragmentSpy.mockRestore();
        });
    });

    describe('clear', () => {
        it('should clear container content', () => {
            container.innerHTML = '<li>Test</li>';
            renderer.clear();

            expect(container.innerHTML).toBe('');
        });
    });

    describe('escapeHtml', () => {
        it('should escape HTML special characters', () => {
            expect(renderer.escapeHtml('<div>test</div>')).toBe(
                '&lt;div&gt;test&lt;/div&gt;'
            );
            expect(renderer.escapeHtml('a & b')).toBe('a &amp; b');
            // textContent escapes HTML tags but not quotes
            expect(renderer.escapeHtml('"quotes"')).toBe('"quotes"');
        });

        it('should return empty string for null/undefined', () => {
            expect(renderer.escapeHtml(null)).toBe('');
            expect(renderer.escapeHtml(undefined)).toBe('');
            expect(renderer.escapeHtml('')).toBe('');
        });

        it('should handle XSS attempts', () => {
            const xssAttempts = [
                '<script>alert("xss")</script>',
                '<img src=x onerror=alert("xss")>',
                '<iframe src="javascript:alert(\'xss\')"></iframe>',
            ];

            xssAttempts.forEach(attempt => {
                const escaped = renderer.escapeHtml(attempt);
                // Verify HTML tags are escaped
                expect(escaped).not.toContain('<script>');
                expect(escaped).not.toContain('<img');
                expect(escaped).not.toContain('<iframe');
                expect(escaped).toContain('&lt;');
            });

            // javascript: protocol URLs are escaped for context when used in attributes
            // but the escapeHtml function primarily targets HTML tag injection
            const jsProtocol = 'javascript:alert("xss")';
            const escaped = renderer.escapeHtml(jsProtocol);
            // No HTML tags to escape in this case
            expect(escaped).toBe(jsProtocol);
        });
    });
});

describe('BiblioRefManager', () => {
    let manager;

    beforeEach(() => {
        document.body.innerHTML = '';
        manager = new BiblioRefManager();
        fetch.mockClear();
    });

    describe('constructor', () => {
        it('should initialize with default config', () => {
            expect(manager.config.containerSelector).toBe(
                '#biblio-refs-container'
            );
            expect(manager.config.triggerSelector).toBe(
                '#visualize-biblio-refs'
            );
            expect(manager.config.sectionSelector).toBe('#biblio-refs');
            expect(manager.initialized).toBe(false);
        });

        it('should allow custom config', () => {
            const customManager = new BiblioRefManager({
                containerSelector: '#custom-container',
            });

            expect(customManager.config.containerSelector).toBe(
                '#custom-container'
            );
            expect(customManager.config.triggerSelector).toBe(
                '#visualize-biblio-refs'
            );
        });

        it('should create service instance', () => {
            expect(manager.service).toBeInstanceOf(BiblioRefService);
        });
    });

    describe('getConfigFromElement', () => {
        it('should extract config from element data attributes', () => {
            const element = document.createElement('div');
            element.dataset.api = 'https://api.test.com';
            element.dataset.value = 'https://paper.com/123';
            element.dataset.all = '1';

            const config = manager.getConfigFromElement(element);

            expect(config).toEqual({
                apiUrl: 'https://api.test.com',
                paperUrl: 'https://paper.com/123',
                showAll: true,
            });
        });

        it('should handle missing showAll parameter', () => {
            const element = document.createElement('div');
            element.dataset.api = 'https://api.test.com';
            element.dataset.value = 'https://paper.com/123';

            const config = manager.getConfigFromElement(element);

            expect(config.showAll).toBe(false);
        });
    });

    describe('initialize', () => {
        it('should do nothing when trigger element is missing', async () => {
            await manager.initialize();

            expect(fetch).not.toHaveBeenCalled();
            expect(manager.initialized).toBe(false);
        });

        it('should initialize and render citations', async () => {
            // Setup DOM
            document.body.innerHTML = `
                <div id="visualize-biblio-refs"
                     data-api="https://api.test.com"
                     data-value="https://paper.com/123">
                </div>
                <ul id="biblio-refs-container"></ul>
                <div id="biblio-refs" style="display: none;"></div>
            `;

            // Mock API response
            const mockResponse = {
                0: {
                    ref: '{"raw_reference": "Test citation", "doi": "10.1234/test"}',
                    isAccepted: 1,
                },
            };

            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockResponse,
            });

            await manager.initialize();

            expect(manager.initialized).toBe(true);
            expect(fetch).toHaveBeenCalled();

            const container = document.getElementById('biblio-refs-container');
            expect(container.innerHTML).toContain('Test citation');
            expect(container.innerHTML).toContain('10.1234/test');

            const section = document.getElementById('biblio-refs');
            expect(section.style.display).toBe('block');
        });

        it('should prevent multiple initializations', async () => {
            document.body.innerHTML = `
                <div id="visualize-biblio-refs"
                     data-api="https://api.test.com"
                     data-value="https://paper.com/123">
                </div>
                <ul id="biblio-refs-container"></ul>
            `;

            fetch.mockResolvedValue({
                ok: true,
                json: async () => ({}),
            });

            await manager.initialize();
            await manager.initialize();

            expect(fetch).toHaveBeenCalledTimes(1);
        });

        it('should handle API errors gracefully', async () => {
            document.body.innerHTML = `
                <div id="visualize-biblio-refs"
                     data-api="https://api.test.com"
                     data-value="https://paper.com/123">
                </div>
                <ul id="biblio-refs-container"></ul>
            `;

            fetch.mockRejectedValueOnce(new Error('Network error'));

            const consoleSpy = jest
                .spyOn(console, 'error')
                .mockImplementation(() => {});

            await manager.initialize();

            const container = document.getElementById('biblio-refs-container');
            expect(container.innerHTML).toContain('Network error');

            consoleSpy.mockRestore();
        });

        it('should handle message-only response', async () => {
            document.body.innerHTML = `
                <div id="visualize-biblio-refs"
                     data-api="https://api.test.com"
                     data-value="https://paper.com/123">
                </div>
                <ul id="biblio-refs-container"></ul>
            `;

            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({ message: 'No citations found' }),
            });

            await manager.initialize();

            const container = document.getElementById('biblio-refs-container');
            expect(container.innerHTML).toBe('');
        });

        it('should log error when container is missing', async () => {
            document.body.innerHTML = `
                <div id="visualize-biblio-refs"
                     data-api="https://api.test.com"
                     data-value="https://paper.com/123">
                </div>
            `;

            const consoleSpy = jest
                .spyOn(console, 'error')
                .mockImplementation(() => {});

            await manager.initialize();

            expect(consoleSpy).toHaveBeenCalledWith(
                'Biblio refs container not found'
            );
            consoleSpy.mockRestore();
        });

        it('should filter out invalid citations', async () => {
            document.body.innerHTML = `
                <div id="visualize-biblio-refs"
                     data-api="https://api.test.com"
                     data-value="https://paper.com/123">
                </div>
                <ul id="biblio-refs-container"></ul>
                <div id="biblio-refs" style="display: none;"></div>
            `;

            const mockResponse = {
                0: {
                    ref: '{"raw_reference": "Valid citation"}',
                    isAccepted: 0,
                },
                1: {
                    // Invalid - no ref
                    isAccepted: 1,
                },
                2: {
                    ref: 'invalid json',
                    isAccepted: 0,
                },
            };

            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockResponse,
            });

            const consoleSpy = jest
                .spyOn(console, 'error')
                .mockImplementation(() => {});

            await manager.initialize();

            const container = document.getElementById('biblio-refs-container');
            // Should only have 1 valid citation + source
            expect(container.children.length).toBe(2);
            expect(container.innerHTML).toContain('Valid citation');

            consoleSpy.mockRestore();
        });

        it('should work without section element', async () => {
            document.body.innerHTML = `
                <div id="visualize-biblio-refs"
                     data-api="https://api.test.com"
                     data-value="https://paper.com/123">
                </div>
                <ul id="biblio-refs-container"></ul>
            `;

            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    0: {
                        ref: '{"raw_reference": "Test"}',
                        isAccepted: 0,
                    },
                }),
            });

            await manager.initialize();

            const container = document.getElementById('biblio-refs-container');
            expect(container.innerHTML).toContain('Test');
        });
    });
});
