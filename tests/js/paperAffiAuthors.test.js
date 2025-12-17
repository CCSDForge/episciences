const {
    PaperAffiAuthorsManager,
} = require('../../public/js/paper/paperAffiAuthors.js');

describe('PaperAffiAuthorsManager', () => {
    let manager;
    let mockDOM;

    beforeEach(() => {
        // Reset DOM
        document.body.innerHTML = '';

        // Create mock DOM structure
        mockDOM = {
            authorSelect: document.createElement('select'),
            affiBody: document.createElement('div'),
            form: document.createElement('form'),
            hiddenAuthorInput: document.createElement('input'),
            paperIdDiv: document.createElement('div'),
        };

        mockDOM.authorSelect.id = 'select-author-affi';
        mockDOM.affiBody.id = 'affi-body';
        mockDOM.form.id = 'form-affi-authors';
        mockDOM.hiddenAuthorInput.id = 'id-edited-affi-author';
        mockDOM.paperIdDiv.id = 'paperid-for-author';
        mockDOM.hiddenAuthorInput.type = 'hidden';
        mockDOM.paperIdDiv.textContent = '12345';

        // Add options to select
        const option1 = document.createElement('option');
        option1.id = 'author-1';
        option1.value = 'Author 1';
        option1.textContent = 'Author 1';

        const option2 = document.createElement('option');
        option2.id = 'author-2';
        option2.value = 'Author 2';
        option2.textContent = 'Author 2';

        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = 'Select an author';

        mockDOM.authorSelect.appendChild(emptyOption);
        mockDOM.authorSelect.appendChild(option1);
        mockDOM.authorSelect.appendChild(option2);

        // Append to body
        document.body.appendChild(mockDOM.authorSelect);
        document.body.appendChild(mockDOM.affiBody);
        document.body.appendChild(mockDOM.form);
        document.body.appendChild(mockDOM.hiddenAuthorInput);
        document.body.appendChild(mockDOM.paperIdDiv);

        // Create fresh manager instance
        manager = new PaperAffiAuthorsManager();

        // Mock global variables used by the application
        global.JS_PREFIX_URL = '/';

        // Mock fetch
        global.fetch = jest.fn();

        // Mock window.versionCache
        window.versionCache = '1.0.0';

        // Mock console methods
        jest.spyOn(console, 'error').mockImplementation(() => {});
        jest.spyOn(console, 'log').mockImplementation(() => {});
    });

    afterEach(() => {
        jest.restoreAllMocks();
        delete window.versionCache;
        delete window.initializeAffiliationsAutocomplete;
        delete global.JS_PREFIX_URL;
    });

    describe('Constructor', () => {
        test('should initialize with null properties', () => {
            expect(manager.authorSelect).toBeNull();
            expect(manager.affiBody).toBeNull();
            expect(manager.form).toBeNull();
            expect(manager.hiddenAuthorInput).toBeNull();
            expect(manager.paperIdDiv).toBeNull();
        });
    });

    describe('initialize()', () => {
        test('should find and store DOM elements', () => {
            manager.initialize();

            expect(manager.authorSelect).toBe(mockDOM.authorSelect);
            expect(manager.affiBody).toBe(mockDOM.affiBody);
            expect(manager.form).toBe(mockDOM.form);
            expect(manager.hiddenAuthorInput).toBe(mockDOM.hiddenAuthorInput);
            expect(manager.paperIdDiv).toBe(mockDOM.paperIdDiv);
        });

        test('should return early if authorSelect is not found', () => {
            document.body.innerHTML = '';
            const attachSpy = jest.spyOn(manager, 'attachEventListeners');

            manager.initialize();

            expect(manager.authorSelect).toBeNull();
            expect(attachSpy).not.toHaveBeenCalled();
        });

        test('should attach event listeners when elements exist', () => {
            const attachSpy = jest.spyOn(manager, 'attachEventListeners');
            manager.initialize();

            expect(attachSpy).toHaveBeenCalledTimes(1);
        });
    });

    describe('attachEventListeners()', () => {
        beforeEach(() => {
            manager.initialize();
        });

        test('should attach change listener to author select', () => {
            const handleChangeSpy = jest
                .spyOn(manager, 'handleAuthorChange')
                .mockImplementation(() => {});

            mockDOM.authorSelect.selectedIndex = 1;
            mockDOM.authorSelect.dispatchEvent(new Event('change'));

            expect(handleChangeSpy).toHaveBeenCalled();
        });

        test('should attach submit listener to form', () => {
            const handleSubmitSpy = jest
                .spyOn(manager, 'handleFormSubmit')
                .mockImplementation(() => {});

            mockDOM.form.dispatchEvent(new Event('submit'));

            expect(handleSubmitSpy).toHaveBeenCalled();
        });
    });

    describe('handleAuthorChange()', () => {
        beforeEach(() => {
            manager.initialize();
        });

        test('should update hidden input with selected author ID', async () => {
            jest.spyOn(manager, 'loadAffiliations').mockResolvedValue();

            mockDOM.authorSelect.selectedIndex = 1; // author-1
            await manager.handleAuthorChange();

            expect(mockDOM.hiddenAuthorInput.value).toBe('author-1');
        });

        test('should clear affiBody when no author is selected', async () => {
            mockDOM.affiBody.innerHTML = '<p>Some content</p>';
            mockDOM.authorSelect.selectedIndex = 0; // empty option

            await manager.handleAuthorChange();

            expect(mockDOM.affiBody.innerHTML).toBe('');
            expect(mockDOM.hiddenAuthorInput.value).toBe('');
        });

        test('should load affiliations for selected author', async () => {
            const loadSpy = jest
                .spyOn(manager, 'loadAffiliations')
                .mockResolvedValue();

            mockDOM.authorSelect.selectedIndex = 1; // author-1
            await manager.handleAuthorChange();

            expect(loadSpy).toHaveBeenCalledWith('author-1');
        });

        test('should handle errors gracefully', async () => {
            jest.spyOn(manager, 'loadAffiliations').mockRejectedValue(
                new Error('Network error')
            );

            mockDOM.authorSelect.selectedIndex = 1;
            await manager.handleAuthorChange();

            expect(console.error).toHaveBeenCalledWith(
                'Error loading affiliations:',
                expect.any(Error)
            );
            expect(mockDOM.affiBody.innerHTML).toContain(
                'Error loading affiliations'
            );
        });
    });

    describe('loadAffiliations()', () => {
        beforeEach(() => {
            manager.initialize();
        });

        test('should fetch affiliations from backend', async () => {
            const mockHtml = '<div>Affiliation form</div>';
            global.fetch.mockResolvedValueOnce({
                ok: true,
                text: jest.fn().mockResolvedValue(mockHtml),
            });

            jest.spyOn(manager, 'loadAffiliationsScript').mockResolvedValue();

            await manager.loadAffiliations('author-1');

            expect(global.fetch).toHaveBeenCalledWith(
                '/paper/getaffiliationsbyauthor/',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        idAuthor: 'author-1',
                        paperId: '12345',
                    }),
                }
            );
        });

        test('should update affiBody with returned HTML', async () => {
            const mockHtml = '<div>Affiliation form</div>';
            global.fetch.mockResolvedValueOnce({
                ok: true,
                text: jest.fn().mockResolvedValue(mockHtml),
            });

            jest.spyOn(manager, 'loadAffiliationsScript').mockResolvedValue();
            jest.spyOn(manager, 'insertHTMLWithScripts');

            await manager.loadAffiliations('author-1');

            expect(manager.insertHTMLWithScripts).toHaveBeenCalledWith(
                mockDOM.affiBody,
                mockHtml
            );
        });

        test('should load affiliations script after updating HTML', async () => {
            const mockHtml = '<div>Form</div>';
            global.fetch.mockResolvedValueOnce({
                ok: true,
                text: jest.fn().mockResolvedValue(mockHtml),
            });

            const loadScriptSpy = jest
                .spyOn(manager, 'loadAffiliationsScript')
                .mockResolvedValue();

            await manager.loadAffiliations('author-1');

            expect(loadScriptSpy).toHaveBeenCalled();
        });

        test('should throw error on HTTP error', async () => {
            global.fetch.mockResolvedValueOnce({
                ok: false,
                status: 404,
            });

            await expect(manager.loadAffiliations('author-1')).rejects.toThrow(
                'HTTP error! status: 404'
            );
        });

        test('should handle missing paperIdDiv', async () => {
            manager.paperIdDiv = null;
            const mockHtml = '<div>Form</div>';
            global.fetch.mockResolvedValueOnce({
                ok: true,
                text: jest.fn().mockResolvedValue(mockHtml),
            });

            jest.spyOn(manager, 'loadAffiliationsScript').mockResolvedValue();

            await manager.loadAffiliations('author-1');

            expect(global.fetch).toHaveBeenCalledWith(
                '/paper/getaffiliationsbyauthor/',
                expect.objectContaining({
                    body: JSON.stringify({
                        idAuthor: 'author-1',
                        paperId: '',
                    }),
                })
            );
        });
    });

    describe('loadAffiliationsScript()', () => {
        beforeEach(() => {
            manager.initialize();
        });

        test('should call existing initializeAffiliationsAutocomplete if already loaded', async () => {
            const mockInit = jest.fn();
            window.initializeAffiliationsAutocomplete = mockInit;

            await manager.loadAffiliationsScript();

            expect(mockInit).toHaveBeenCalled();
        });

        test('should load script if not already loaded', async () => {
            const loadScriptSpy = jest
                .spyOn(manager, 'loadScript')
                .mockResolvedValue();

            await manager.loadAffiliationsScript();

            expect(loadScriptSpy).toHaveBeenCalledWith(
                '/js/user/affiliations.js?_=v1.0.0'
            );
        });

        test('should initialize autocomplete after loading script', async () => {
            jest.spyOn(manager, 'loadScript').mockImplementation(() => {
                window.initializeAffiliationsAutocomplete = jest.fn();
                return Promise.resolve();
            });

            await manager.loadAffiliationsScript();

            expect(
                window.initializeAffiliationsAutocomplete
            ).toHaveBeenCalled();
        });

        test('should use versionCache for cache-busting', async () => {
            window.versionCache = '2.5.3';
            const loadScriptSpy = jest
                .spyOn(manager, 'loadScript')
                .mockResolvedValue();

            await manager.loadAffiliationsScript();

            expect(loadScriptSpy).toHaveBeenCalledWith(
                '/js/user/affiliations.js?_=v2.5.3'
            );
        });

        test('should handle missing versionCache', async () => {
            delete window.versionCache;
            const loadScriptSpy = jest
                .spyOn(manager, 'loadScript')
                .mockResolvedValue();

            await manager.loadAffiliationsScript();

            expect(loadScriptSpy).toHaveBeenCalledWith(
                '/js/user/affiliations.js?_=v'
            );
        });

        test('should handle script loading errors', async () => {
            jest.spyOn(manager, 'loadScript').mockRejectedValue(
                new Error('Script load failed')
            );

            await expect(manager.loadAffiliationsScript()).rejects.toThrow(
                'Script load failed'
            );
            expect(console.error).toHaveBeenCalledWith(
                'affiliations.js loading failed',
                expect.any(Error)
            );
        });
    });

    describe('loadScript()', () => {
        beforeEach(() => {
            manager.initialize();
        });

        test('should create and append script element', async () => {
            const promise = manager.loadScript('/test.js');

            const scripts = document.head.querySelectorAll(
                'script[src="/test.js"]'
            );
            expect(scripts.length).toBe(1);
            expect(scripts[0].src).toContain('/test.js');

            // Trigger onload to resolve the promise
            const script = document.head.querySelector(
                'script[src="/test.js"]'
            );
            script.onload();

            await promise;
        });

        test('should resolve when script loads', async () => {
            const promise = manager.loadScript('/test.js');

            const script = document.head.querySelector(
                'script[src="/test.js"]'
            );
            script.onload();

            await expect(promise).resolves.toBeUndefined();
        });

        test('should reject when script fails to load', async () => {
            const promise = manager.loadScript('/test.js');

            const script = document.head.querySelector(
                'script[src="/test.js"]'
            );
            script.onerror(new Error('Load failed'));

            await expect(promise).rejects.toThrow();
        });
    });

    describe('handleFormSubmit()', () => {
        beforeEach(() => {
            manager.initialize();
        });

        test('should allow submission when author ID is present', () => {
            mockDOM.hiddenAuthorInput.value = 'author-1';
            const mockEvent = { preventDefault: jest.fn() };

            const result = manager.handleFormSubmit(mockEvent);

            expect(result).toBe(true);
            expect(mockEvent.preventDefault).not.toHaveBeenCalled();
        });

        test('should prevent submission when author ID is empty', () => {
            mockDOM.hiddenAuthorInput.value = '';
            const mockEvent = { preventDefault: jest.fn() };

            const result = manager.handleFormSubmit(mockEvent);

            expect(result).toBe(false);
            expect(mockEvent.preventDefault).toHaveBeenCalled();
        });

        test('should prevent submission when author ID is missing', () => {
            mockDOM.hiddenAuthorInput.value = null;
            const mockEvent = { preventDefault: jest.fn() };

            const result = manager.handleFormSubmit(mockEvent);

            expect(result).toBe(false);
            expect(mockEvent.preventDefault).toHaveBeenCalled();
        });

        test('should handle missing hiddenAuthorInput element', () => {
            manager.hiddenAuthorInput = null;
            const mockEvent = { preventDefault: jest.fn() };

            const result = manager.handleFormSubmit(mockEvent);

            expect(result).toBe(false);
            expect(mockEvent.preventDefault).toHaveBeenCalled();
        });
    });

    describe('Integration tests', () => {
        beforeEach(() => {
            manager.initialize();
        });

        test('should complete full flow: select author -> load affiliations -> initialize autocomplete', async () => {
            const mockHtml =
                '<div class="affiliation-form"><input type="text" id="ror-input"></div>';
            global.fetch.mockResolvedValueOnce({
                ok: true,
                text: jest.fn().mockResolvedValue(mockHtml),
            });

            const mockInitAutocomplete = jest.fn();
            window.initializeAffiliationsAutocomplete = mockInitAutocomplete;

            // Select an author
            mockDOM.authorSelect.selectedIndex = 1;
            await manager.handleAuthorChange();

            // Verify the complete flow
            expect(mockDOM.hiddenAuthorInput.value).toBe('author-1');
            expect(global.fetch).toHaveBeenCalledWith(
                '/paper/getaffiliationsbyauthor/',
                expect.objectContaining({
                    method: 'POST',
                    body: JSON.stringify({
                        idAuthor: 'author-1',
                        paperId: '12345',
                    }),
                })
            );
            expect(mockDOM.affiBody.innerHTML).toBe(mockHtml);
            expect(mockInitAutocomplete).toHaveBeenCalled();
        });

        test('should handle network errors in full flow', async () => {
            global.fetch.mockRejectedValueOnce(new Error('Network error'));

            mockDOM.authorSelect.selectedIndex = 1;
            await manager.handleAuthorChange();

            expect(console.error).toHaveBeenCalledWith(
                'Error loading affiliations:',
                expect.any(Error)
            );
            expect(mockDOM.affiBody.innerHTML).toContain(
                'Error loading affiliations'
            );
        });

        test('should clear affiBody when switching to empty option', async () => {
            mockDOM.affiBody.innerHTML = '<div>Previous content</div>';
            mockDOM.hiddenAuthorInput.value = 'author-1';

            mockDOM.authorSelect.selectedIndex = 0; // empty option
            await manager.handleAuthorChange();

            expect(mockDOM.affiBody.innerHTML).toBe('');
            expect(mockDOM.hiddenAuthorInput.value).toBe('');
        });

        test('should prevent form submission without author selection', () => {
            mockDOM.hiddenAuthorInput.value = '';
            const submitEvent = new Event('submit', { cancelable: true });
            const preventDefaultSpy = jest.spyOn(submitEvent, 'preventDefault');

            manager.handleFormSubmit(submitEvent);

            expect(preventDefaultSpy).toHaveBeenCalled();
        });

        test('should allow form submission with author selection', () => {
            mockDOM.hiddenAuthorInput.value = 'author-1';
            const submitEvent = new Event('submit', { cancelable: true });
            const preventDefaultSpy = jest.spyOn(submitEvent, 'preventDefault');

            manager.handleFormSubmit(submitEvent);

            expect(preventDefaultSpy).not.toHaveBeenCalled();
        });
    });

    describe('Error handling', () => {
        beforeEach(() => {
            manager.initialize();
        });

        test('should handle HTTP 500 error', async () => {
            global.fetch.mockResolvedValueOnce({
                ok: false,
                status: 500,
            });

            await expect(manager.loadAffiliations('author-1')).rejects.toThrow(
                'HTTP error! status: 500'
            );
        });

        test('should handle HTTP 404 error', async () => {
            global.fetch.mockResolvedValueOnce({
                ok: false,
                status: 404,
            });

            await expect(manager.loadAffiliations('author-1')).rejects.toThrow(
                'HTTP error! status: 404'
            );
        });

        test('should display error message on affiliation load failure', async () => {
            global.fetch.mockRejectedValueOnce(new Error('Network timeout'));

            mockDOM.authorSelect.selectedIndex = 1;
            await manager.handleAuthorChange();

            expect(mockDOM.affiBody.innerHTML).toBe(
                '<p class="error">Error loading affiliations. Please try again.</p>'
            );
        });

        test('should handle missing affiBody during error', async () => {
            manager.affiBody = null;
            jest.spyOn(manager, 'loadAffiliations').mockRejectedValue(
                new Error('Error')
            );

            mockDOM.authorSelect.selectedIndex = 1;

            // Should not throw
            await expect(manager.handleAuthorChange()).resolves.toBeUndefined();
            expect(console.error).toHaveBeenCalled();
        });
    });

    describe('insertHTMLWithScripts()', () => {
        beforeEach(() => {
            manager.initialize();
        });

        test('should insert HTML without script tags', () => {
            const html = '<div>Content</div><p>More content</p>';
            manager.insertHTMLWithScripts(mockDOM.affiBody, html);

            expect(mockDOM.affiBody.innerHTML).toBe(html);
        });

        test('should extract and execute script tags', () => {
            const html =
                '<div>Content</div><script>window.testVar = "executed";</script>';
            manager.insertHTMLWithScripts(mockDOM.affiBody, html);

            expect(mockDOM.affiBody.innerHTML).toBe('<div>Content</div>');
            expect(window.testVar).toBe('executed');
            delete window.testVar;
        });

        test('should execute multiple scripts in order', () => {
            const html =
                '<script>window.order = [];</script><div>Content</div><script>window.order.push(1);</script><script>window.order.push(2);</script>';
            manager.insertHTMLWithScripts(mockDOM.affiBody, html);

            expect(window.order).toEqual([1, 2]);
            delete window.order;
        });

        test('should append scripts to document head', () => {
            const initialScriptCount =
                document.head.querySelectorAll('script').length;
            const html = '<div>Content</div><script>var test = 1;</script>';

            manager.insertHTMLWithScripts(mockDOM.affiBody, html);

            const finalScriptCount =
                document.head.querySelectorAll('script').length;
            expect(finalScriptCount).toBe(initialScriptCount + 1);
        });

        test('should handle HTML without scripts', () => {
            const html = '<div>Content</div><p>No scripts here</p>';
            manager.insertHTMLWithScripts(mockDOM.affiBody, html);

            expect(mockDOM.affiBody.innerHTML).toBe(html);
        });

        test('should handle complex HTML with inline scripts', () => {
            const html =
                '<script>function testFunc() { return "test"; }</script><div><button onclick="testFunc()">Click</button></div>';
            manager.insertHTMLWithScripts(mockDOM.affiBody, html);

            expect(typeof window.testFunc).toBe('function');
            expect(window.testFunc()).toBe('test');
            delete window.testFunc;
        });
    });

    describe('Edge cases', () => {
        beforeEach(() => {
            manager.initialize();
        });

        test('should handle option without id attribute', async () => {
            const optionNoId = document.createElement('option');
            optionNoId.value = 'No ID Option';
            optionNoId.textContent = 'No ID Option';
            mockDOM.authorSelect.appendChild(optionNoId);

            mockDOM.authorSelect.selectedIndex = 3; // The option without id
            await manager.handleAuthorChange();

            expect(mockDOM.hiddenAuthorInput.value).toBe('');
            expect(mockDOM.affiBody.innerHTML).toBe('');
        });

        test('should handle empty paper ID', async () => {
            mockDOM.paperIdDiv.textContent = '';
            const mockHtml = '<div>Form</div>';
            global.fetch.mockResolvedValueOnce({
                ok: true,
                text: jest.fn().mockResolvedValue(mockHtml),
            });

            jest.spyOn(manager, 'loadAffiliationsScript').mockResolvedValue();

            await manager.loadAffiliations('author-1');

            expect(global.fetch).toHaveBeenCalledWith(
                '/paper/getaffiliationsbyauthor/',
                expect.objectContaining({
                    body: JSON.stringify({
                        idAuthor: 'author-1',
                        paperId: '',
                    }),
                })
            );
        });

        test('should handle multiple rapid author changes', async () => {
            const mockHtml = '<div>Form</div>';
            global.fetch.mockResolvedValue({
                ok: true,
                text: jest.fn().mockResolvedValue(mockHtml),
            });

            jest.spyOn(manager, 'loadAffiliationsScript').mockResolvedValue();

            // Simulate rapid changes
            mockDOM.authorSelect.selectedIndex = 1;
            const promise1 = manager.handleAuthorChange();

            mockDOM.authorSelect.selectedIndex = 2;
            const promise2 = manager.handleAuthorChange();

            await Promise.all([promise1, promise2]);

            expect(global.fetch).toHaveBeenCalledTimes(2);
        });
    });
});
