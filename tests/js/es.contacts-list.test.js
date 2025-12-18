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

        // Create mock DOM structure
        document.body.innerHTML = `
            <form id="test-form">
                <div id="cc-element">
                    <label>Cliquer pour charger les contacts</label>
                </div>
            </form>
            <div class="contacts_container" style="display: none;"></div>
        `;

        // Mock fetch API
        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                status: 200,
                text: () =>
                    Promise.resolve(
                        '<div class="contact">Contact 1</div><div class="contact">Contact 2</div>'
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
    });

    describe('Label click event', function () {
        it('should hide form and show contacts container on label click', function () {
            const label = document.querySelector('#cc-element label');
            const form = document.getElementById('test-form');
            const container = document.querySelector('.contacts_container');

            label.click();

            expect(form.style.display).toBe('none');
            expect(container.style.display).toBe('block');
        });

        it('should display loader while loading contacts', function () {
            const label = document.querySelector('#cc-element label');
            const container = document.querySelector('.contacts_container');

            label.click();

            expect(mockGetLoader).toHaveBeenCalled();
            expect(container.innerHTML).toContain('loader');
        });

        it('should make POST request to correct endpoint', function () {
            const label = document.querySelector('#cc-element label');

            label.click();

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

        it('should populate container with fetched content', async function () {
            const label = document.querySelector('#cc-element label');
            const container = document.querySelector('.contacts_container');

            label.click();

            // Wait for async fetch to complete
            await new Promise(resolve => setTimeout(resolve, 50));

            expect(container.innerHTML).toContain('Contact 1');
            expect(container.innerHTML).toContain('Contact 2');
        });
    });

    describe('Error handling', function () {
        it('should display error message when fetch fails', async function () {
            // Override fetch to simulate error
            global.fetch = jest.fn(() =>
                Promise.reject(new Error('Network error'))
            );

            const label = document.querySelector('#cc-element label');
            const container = document.querySelector('.contacts_container');

            // Mock console.error to avoid test output noise
            const consoleErrorSpy = jest
                .spyOn(console, 'error')
                .mockImplementation(() => {});

            label.click();

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

            const label = document.querySelector('#cc-element label');
            const container = document.querySelector('.contacts_container');

            const consoleErrorSpy = jest
                .spyOn(console, 'error')
                .mockImplementation(() => {});

            label.click();

            await new Promise(resolve => setTimeout(resolve, 50));

            expect(consoleErrorSpy).toHaveBeenCalled();
            expect(container.innerHTML).toContain('text-danger');

            consoleErrorSpy.mockRestore();
        });
    });

    describe('Edge cases', function () {
        it('should do nothing if cc-element is not found', function () {
            document.body.innerHTML = '<div></div>';

            // Re-execute script with missing element
            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            // Should not throw error
            expect(global.fetch).not.toHaveBeenCalled();
        });

        it('should do nothing if form is not found', function () {
            document.body.innerHTML = `
                <div id="cc-element">
                    <label>Label without form parent</label>
                </div>
            `;

            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            const label = document.querySelector('#cc-element label');
            label.click();

            // Should not throw error and not call fetch
            expect(global.fetch).not.toHaveBeenCalled();
        });
    });
});
