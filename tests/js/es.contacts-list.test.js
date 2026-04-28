const fs = require('fs');
const path = require('path');

describe('es.contacts-list', function () {
    let contactsListJs;
    let mockGetLoader;

    beforeEach(function () {
        // Load the actual JavaScript file
        contactsListJs = fs.readFileSync(
            path.join(__dirname, '../../public/js/library/es.contacts-list.js'),
            'utf8'
        );

        // Mock getLoader function
        mockGetLoader = jest.fn(
            () => '<div class="loader"><div class="progress-bar"></div></div>'
        );
        global.getLoader = mockGetLoader;

        // Mock sanitizeHTML function (for XSS prevention)
        global.sanitizeHTML = jest.fn(html => html);

        // Mock jQuery and $.getScript
        global.$ = jest.fn((selector) => {
            if (selector === '#' || typeof selector === 'function') {
                return; // Handle jQuery ready
            }
            return {
                ajaxSetup: jest.fn(),
            };
        });
        global.$.ajaxSetup = jest.fn();
        global.$.getScript = jest.fn(() => ({
            done: jest.fn(function(callback) {
                // Simulate successful script load
                if (typeof callback === 'function') {
                    global.initGetContacts = jest.fn();
                    callback();
                }
                return this;
            }),
            fail: jest.fn(function() { return this; })
        }));

        // Create mock DOM structure matching the new modal structure
        document.body.innerHTML = `
            <div class="modal-body">
                <form id="test-form">
                    <label>
                        <a class="show_contacts_button"
                           href="/administratemail/getcontacts?target=cc">
                            CC
                        </a>
                    </label>
                    <label>
                        <a class="show_contacts_button"
                           href="/administratemail/getcontacts?target=bcc">
                            BCC
                        </a>
                    </label>
                </form>
                <span class="ccsd_form_required">* Required fields</span>
                <div class="contacts-container" style="display: none;"></div>
            </div>
        `;

        // Mock fetch API
        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                status: 200,
                text: () =>
                    Promise.resolve(
                        '<script>var target="cc"; var all_contacts={};</script>' +
                        '<div class="contact">Contact 1</div>' +
                        '<div class="contact">Contact 2</div>'
                    ),
            })
        );

        // Execute the script to bind event listeners
        eval(contactsListJs);

        // Trigger DOMContentLoaded manually
        const event = new Event('DOMContentLoaded');
        document.dispatchEvent(event);
    });

    afterEach(function () {
        jest.clearAllMocks();
        delete global.getLoader;
        delete global.fetch;
        delete global.sanitizeHTML;
        delete global.$;
        delete global.initGetContacts;
    });

    describe('Label click event', function () {
        it('should hide form and show contacts container on CC button click', function () {
            const button = document.querySelector('.show_contacts_button[href*="target=cc"]');
            const form = document.getElementById('test-form');
            const container = document.querySelector('.contacts-container');

            button.click();

            expect(form.style.display).toBe('none');
            expect(container.style.display).toBe('block');
        });

        it('should hide required fields indicator', function () {
            const button = document.querySelector('.show_contacts_button[href*="target=cc"]');
            const requiredFields = document.querySelector('.ccsd_form_required');

            button.click();

            expect(requiredFields.style.display).toBe('none');
        });

        it('should display loader while loading contacts', function () {
            const button = document.querySelector('.show_contacts_button[href*="target=cc"]');
            const container = document.querySelector('.contacts-container');

            button.click();

            expect(mockGetLoader).toHaveBeenCalled();
            expect(container.innerHTML).toContain('loader');
        });

        it('should make POST request to correct endpoint for CC', async function () {
            const button = document.querySelector('.show_contacts_button[href*="target=cc"]');

            button.click();

            // Wait a bit for async operation
            await new Promise(resolve => setTimeout(resolve, 10));

            expect(global.fetch).toHaveBeenCalledWith(
                '/administratemail/getcontacts?target=cc',
                expect.objectContaining({
                    method: 'POST',
                    headers: expect.objectContaining({
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                    }),
                    body: 'ajax=true',
                })
            );
        });

        it('should make POST request to correct endpoint for BCC', async function () {
            const button = document.querySelector('.show_contacts_button[href*="target=bcc"]');

            button.click();

            await new Promise(resolve => setTimeout(resolve, 10));

            expect(global.fetch).toHaveBeenCalledWith(
                '/administratemail/getcontacts?target=bcc',
                expect.objectContaining({
                    method: 'POST',
                })
            );
        });

        it('should populate container with fetched content', async function () {
            const button = document.querySelector('.show_contacts_button[href*="target=cc"]');
            const container = document.querySelector('.contacts-container');

            button.click();

            // Wait for async fetch to complete
            await new Promise(resolve => setTimeout(resolve, 50));

            expect(container.innerHTML).toContain('Contact 1');
            expect(container.innerHTML).toContain('Contact 2');
        });

        it('should load get-contacts.js script', async function () {
            const button = document.querySelector('.show_contacts_button[href*="target=cc"]');

            button.click();

            await new Promise(resolve => setTimeout(resolve, 50));

            expect(global.$.getScript).toHaveBeenCalledWith('/js/administratemail/get-contacts.js');
            expect(global.initGetContacts).toHaveBeenCalled();
        });
    });

    describe('Error handling', function () {
        it('should display error message when fetch fails', async function () {
            // Override fetch to simulate error
            global.fetch = jest.fn(() =>
                Promise.reject(new Error('Network error'))
            );

            const button = document.querySelector('.show_contacts_button[href*="target=cc"]');
            const container = document.querySelector('.contacts-container');

            // Mock console.error to avoid test output noise
            const consoleErrorSpy = jest
                .spyOn(console, 'error')
                .mockImplementation(() => {});

            button.click();

            // Wait for async operation and error handling
            await new Promise(resolve => setTimeout(resolve, 50));

            expect(consoleErrorSpy).toHaveBeenCalledWith(
                'Error loading contacts:',
                expect.any(Error)
            );
            expect(container.innerHTML).toContain('text-danger');
            expect(container.innerHTML).toContain('Error loading contacts');

            consoleErrorSpy.mockRestore();
        });

        it('should display error message when response is not ok', async function () {
            // Override fetch to simulate HTTP error
            global.fetch = jest.fn(() =>
                Promise.resolve({
                    ok: false,
                    status: 404,
                    text: () => Promise.resolve('Not found'),
                })
            );

            const button = document.querySelector('.show_contacts_button[href*="target=cc"]');
            const container = document.querySelector('.contacts-container');

            const consoleErrorSpy = jest
                .spyOn(console, 'error')
                .mockImplementation(() => {});

            button.click();

            await new Promise(resolve => setTimeout(resolve, 50));

            expect(consoleErrorSpy).toHaveBeenCalled();
            expect(container.innerHTML).toContain('text-danger');

            consoleErrorSpy.mockRestore();
        });
    });

    describe('Edge cases', function () {
        it('should do nothing if modal-body is not found', function () {
            document.body.innerHTML = '<div></div>';

            // Re-execute script with missing element
            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            // Should not throw error
            expect(global.fetch).not.toHaveBeenCalled();
        });

        it('should not proceed if form is not found in modal body', function () {
            document.body.innerHTML = `
                <div class="modal-body">
                    <a class="show_contacts_button"
                       href="/administratemail/getcontacts?target=cc">
                        CC
                    </a>
                    <div class="contacts-container"></div>
                </div>
            `;

            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            const button = document.querySelector('.show_contacts_button');
            button.click();

            // Should not call fetch when form is missing
            expect(global.fetch).not.toHaveBeenCalled();
        });

        it('should not proceed if contacts-container is not found', function () {
            document.body.innerHTML = `
                <div class="modal-body">
                    <form>
                        <a class="show_contacts_button"
                           href="/administratemail/getcontacts?target=cc">
                            CC
                        </a>
                    </form>
                </div>
            `;

            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            const button = document.querySelector('.show_contacts_button');
            button.click();

            // Should not call fetch when container is missing
            expect(global.fetch).not.toHaveBeenCalled();
        });
    });
});