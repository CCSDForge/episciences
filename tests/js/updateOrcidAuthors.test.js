const { OrcidAuthorsManager } = require('../../public/js/paper/updateOrcidAuthors');

describe('OrcidAuthorsManager', () => {
    let manager;

    beforeEach(() => {
        // Setup DOM
        document.body.innerHTML = `
            <div id="authors-list">John Doe; Jane Smith;</div>
            <div id="orcid-author-existing">0000-0001-2345-6789##NULL</div>
            <input id="modal-called" value="0">
            <div id="modal-body-authors"></div>
            <label id="affiliations-label"></label>
            <div id="docid-for-author">123</div>
            <div id="paperid-for-author">456</div>
            <div id="rightOrcid">true</div>
            <form id="post-orcid-author" action="/submit">
                <button type="submit">Submit</button>
            </form>
        `;

        manager = new OrcidAuthorsManager();
        // Mock reloadPage
        manager.reloadPage = jest.fn();
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    test('sanitizeOrcid should return valid ORCID or empty string', () => {
        expect(OrcidAuthorsManager.sanitizeOrcid('0000-0001-2345-6789')).toBe('0000-0001-2345-6789');
        expect(OrcidAuthorsManager.sanitizeOrcid('https://orcid.org/0000-0001-2345-6789')).toBe('0000-0001-2345-6789');
        expect(OrcidAuthorsManager.sanitizeOrcid('invalid')).toBe('');
        expect(OrcidAuthorsManager.sanitizeOrcid('')).toBe('');
    });

    test('generateSelectAuthors should create select with authors and aria-label', () => {
        const select = document.querySelector('#select-author-affi');
        expect(select).not.toBeNull();
        expect(select.getAttribute('aria-label')).toBe('Sélectionner un auteur');
        expect(select.options.length).toBe(3); // Empty + 2 authors
        expect(select.options[1].textContent).toBe('John Doe');
        expect(select.options[2].textContent).toBe('Jane Smith');
    });

    test('updateOrcidAuthors should populate modal body with labels and inputs', () => {
        manager.updateOrcidAuthors();

        const label0 = document.querySelector('#fullname__0');
        const orcidInput0 = document.querySelector('#ORCIDauthor__0');
        const label1 = document.querySelector('#fullname__1');
        const orcidInput1 = document.querySelector('#ORCIDauthor__1');

        expect(label0.tagName).toBe('LABEL');
        expect(label0.htmlFor).toBe('ORCIDauthor__0');
        expect(label0.textContent).toBe('John Doe');
        expect(orcidInput0.value).toBe('0000-0001-2345-6789');
        
        expect(label1.tagName).toBe('LABEL');
        expect(label1.htmlFor).toBe('ORCIDauthor__1');
        expect(label1.textContent).toBe('Jane Smith');
        expect(orcidInput1.value).toBe('');

        expect(document.querySelector('#modal-called').value).toBe('1');
    });

    test('updateOrcidAuthors should not re-populate if already called', () => {
        document.querySelector('#modal-called').value = '1';
        manager.updateOrcidAuthors();
        expect(document.querySelector('#modal-body-authors').children.length).toBe(0);
    });

    test('ORCID inputs should sanitize on blur', () => {
        manager.updateOrcidAuthors();
        const orcidInput = document.querySelector('#ORCIDauthor__1');
        orcidInput.value = 'https://orcid.org/0000-0001-2345-678X';
        
        // Trigger blur
        orcidInput.dispatchEvent(new Event('blur'));
        
        expect(orcidInput.value).toBe('0000-0001-2345-678X');
    });

    test('form submission should send correct data', async () => {
        manager.updateOrcidAuthors();
        const form = document.querySelector('#post-orcid-author');
        // Define .action on the form mock
        Object.defineProperty(form, 'action', { value: 'http://localhost/submit' });
        
        // Mock fetch
        global.fetch = jest.fn().mockImplementation(() => 
            Promise.resolve({ ok: true })
        );

        // Submit form
        const submitEvent = new Event('submit', { cancelable: true });
        form.dispatchEvent(submitEvent);

        // Wait for async operations
        await new Promise(resolve => setTimeout(resolve, 0));

        expect(global.fetch).toHaveBeenCalledWith('http://localhost/submit', expect.objectContaining({
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: JSON.stringify({
                docid: '123',
                paperid: '456',
                authors: [
                    ['John Doe', '0000-0001-2345-6789'],
                    ['Jane Smith', '']
                ],
                rightOrcid: 'true'
            })
        }));

        expect(manager.reloadPage).toHaveBeenCalled();
    });

    test('form submission should allow empty ORCIDs for deletion', async () => {
        manager.updateOrcidAuthors();
        const form = document.querySelector('#post-orcid-author');
        Object.defineProperty(form, 'action', { value: 'http://localhost/submit' });
        
        // Clear all ORCID inputs
        document.querySelectorAll("input[id^='ORCIDauthor__']").forEach(input => {
            input.value = '';
        });

        const alertMock = jest.spyOn(window, 'alert').mockImplementation(() => {});
        global.fetch = jest.fn().mockImplementation(() => 
            Promise.resolve({ ok: true })
        );

        // Submit form
        const submitEvent = new Event('submit', { cancelable: true });
        form.dispatchEvent(submitEvent);

        // Wait for async operations
        await new Promise(resolve => setTimeout(resolve, 0));

        expect(alertMock).not.toHaveBeenCalled();
        expect(global.fetch).toHaveBeenCalledWith('http://localhost/submit', expect.objectContaining({
            method: 'POST',
            body: expect.stringContaining('"authors":[["John Doe",""],["Jane Smith",""]]')
        }));
        
        alertMock.mockRestore();
    });

    test('form submission should block duplicate ORCIDs', () => {
        manager.updateOrcidAuthors();
        const form = document.querySelector('#post-orcid-author');
        
        // Set duplicate ORCID inputs
        const inputs = document.querySelectorAll("input[id^='ORCIDauthor__']");
        inputs[0].value = '0000-0001-2222-3333';
        inputs[1].value = '0000-0001-2222-3333';

        // Mock alert
        const alertMock = jest.spyOn(window, 'alert').mockImplementation(() => {});
        global.fetch = jest.fn();

        // Submit form
        const submitEvent = new Event('submit', { cancelable: true });
        form.dispatchEvent(submitEvent);

        expect(alertMock).toHaveBeenCalledWith('orcid-duplicate');
        expect(global.fetch).not.toHaveBeenCalled();
        
        alertMock.mockRestore();
    });
});
