global.EpMailCombobox = require('../../public/js/library/es.mail-combobox.js');
Object.assign(global, require('../../public/js/library/es.mail.js'));
require('../../public/js/library/es.contacts-list.js');

const ADA = {
    uid: 1,
    fullname: 'Ada Lovelace',
    username: 'ada',
    mail: 'ada@example.org',
    roles: ['editor'],
    group: 'staff',
};
const ALAN = {
    uid: 2,
    fullname: 'Alan Author',
    username: 'alan',
    mail: 'alan@example.org',
    roles: [],
    group: 'paper',
    paperRole: 'author',
};

/** Markup of Episciences\Mail\RecipientFieldDecorator in a paper modal form */
function setUp(buttonExtra = '') {
    document.body.innerHTML = `
        <div class="modal"><div class="modal-body">
            <form id="f">
                <input type="hidden" name="docid" value="12">
                <div class="form-group" id="cc-group">
                    <label for="f-cc">Cc</label>
                    <div class="col">
                        <ul id="f-cc-tags" class="recipient-tags" data-recipient-target="cc"
                            data-hidden-input="f-hidden_cc"></ul>
                        <input type="text" id="f-cc" class="recipient-input" data-recipient-target="cc">
                        <button type="button" class="show_contacts_button" data-target="cc"
                                data-input-id="f-cc" aria-expanded="false" aria-controls="f-cc-picker"
                                ${buttonExtra}>Choisir</button>
                    </div>
                </div>
                <input type="hidden" id="f-hidden_cc" value="[]">
            </form>
        </div></div>`;
    return document.querySelector('.show_contacts_button');
}

function mockSearch(resultsFor) {
    global.fetch = jest.fn(url => {
        const params = new URL(url).searchParams;
        return Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ results: resultsFor(params) }),
        });
    });
}

async function flush() {
    for (let i = 0; i < 5; i++) {
        await Promise.resolve();
    }
}

function picker() {
    return document.getElementById('f-cc-picker');
}

function tags() {
    return Array.from(document.querySelectorAll('#f-cc-tags .recipient-tag'));
}

describe('es.contacts-list (contacts picker)', () => {
    beforeEach(() => {
        mockSearch(params =>
            params.has('q') || params.has('role') ? [ADA] : [ALAN]
        );
    });

    test('opens under the field, with the focus on the search field', async () => {
        const button = setUp();

        button.click();
        await flush();

        const panel = picker();
        expect(panel.previousElementSibling.id).toBe('cc-group');
        expect(button.getAttribute('aria-expanded')).toBe('true');
        expect(document.activeElement).toBe(
            document.getElementById('f-cc-picker-search')
        );
        expect(panel.getAttribute('aria-labelledby')).toBe('f-cc-picker-title');
        expect(document.getElementById('f-cc-picker-title').textContent).toBe(
            'Choisir des destinataires (Cc)'
        );
        expect(panel.querySelector('[role="status"]')).not.toBeNull();
    });

    test('lists the participants of the paper of the mail', async () => {
        const button = setUp();

        button.click();
        await flush();

        const url = new URL(global.fetch.mock.calls[0][0]);
        expect(url.searchParams.get('docid')).toBe('12');
        expect(url.searchParams.has('q')).toBe(false);
        const legend = picker().querySelector('legend');
        expect(legend.textContent).toBe("Intervenants de l'article");
        const checkbox = picker().querySelector('input[type="checkbox"]');
        expect(checkbox.hasAttribute('name')).toBe(false);
        expect(
            picker().querySelector('label[for="' + checkbox.id + '"]')
                .textContent
        ).toContain('alan@example.org');
    });

    test('has no "select all" and offers no mailing lists link by default', async () => {
        setUp().click();
        await flush();

        expect(picker().textContent).not.toMatch(/tout sélectionner/i);
        expect(picker().querySelector('a')).toBeNull();
    });

    test('points to the mailing lists when allowed', async () => {
        setUp(
            'data-mailing-lists-url="/administratemailinglist/index"'
        ).click();
        await flush();

        expect(picker().querySelector('a').getAttribute('href')).toBe(
            '/administratemailinglist/index'
        );
    });

    test('lists an editorial group', async () => {
        setUp().click();
        await flush();
        const select = document.getElementById('f-cc-picker-role');
        expect(
            document.querySelector('label[for="f-cc-picker-role"]')
        ).not.toBeNull();

        select.value = 'editor';
        select.dispatchEvent(new Event('change', { bubbles: true }));
        await flush();

        const url = new URL(global.fetch.mock.calls[1][0]);
        expect(url.searchParams.get('role')).toBe('editor');
        expect(picker().textContent).toContain('ada@example.org');
    });

    test('a former group answer does not replace the group now selected', async () => {
        const answers = {};
        global.fetch = jest.fn((url, options) => {
            const role = new URL(url).searchParams.get('role');
            return new Promise(resolve => {
                answers[role || 'paper'] = () =>
                    resolve({
                        ok: true,
                        json: () =>
                            Promise.resolve({
                                results:
                                    role === 'editor'
                                        ? [ADA]
                                        : role
                                          ? [ALAN]
                                          : [],
                            }),
                    });
            });
        });
        setUp().click();
        answers.paper();
        await flush();
        const select = document.getElementById('f-cc-picker-role');

        select.value = 'secretary';
        select.dispatchEvent(new Event('change'));
        select.value = 'editor';
        select.dispatchEvent(new Event('change'));
        answers.editor();
        await flush();
        answers.secretary();
        await flush();

        expect(picker().textContent).toContain('ada@example.org');
        expect(picker().textContent).not.toContain('alan@example.org');
    });

    test('searches from 2 characters, debounced', async () => {
        jest.useFakeTimers();
        try {
            setUp().click();
            await flush();
            const search = document.getElementById('f-cc-picker-search');

            search.value = 'a';
            search.dispatchEvent(new Event('input'));
            jest.advanceTimersByTime(1000);
            expect(global.fetch).toHaveBeenCalledTimes(1);

            search.value = 'ad';
            search.dispatchEvent(new Event('input'));
            jest.advanceTimersByTime(EpMailCombobox.DEBOUNCE_MS);
            expect(global.fetch).toHaveBeenCalledTimes(2);
            expect(
                new URL(global.fetch.mock.calls[1][0]).searchParams.get('q')
            ).toBe('ad');
        } finally {
            jest.useRealTimers();
        }
    });

    test('adds the checked users and gives the focus back to the button', async () => {
        const button = setUp();
        button.click();
        await flush();
        const checkbox = picker().querySelector('input[type="checkbox"]');
        const validate = picker().querySelector('.btn-primary');
        expect(validate.textContent).toBe('Valider la sélection');

        checkbox.click();
        expect(validate.textContent).toBe('Ajouter 1 destinataire');
        validate.click();

        expect(tags().map(tag => tag.dataset.uid)).toEqual(['2']);
        expect(tags()[0].dataset.paperRole).toBe('author');
        expect(picker()).toBeNull();
        expect(button.getAttribute('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(button);
    });

    test('recipients already in the field are checked, and unchecking removes them', async () => {
        const button = setUp();
        window.__epContactsForm = document.getElementById('f');
        addRecipient('cc', ALAN, 'known');
        delete window.__epContactsForm;

        button.click();
        await flush();
        const checkbox = picker().querySelector('input[type="checkbox"]');
        expect(checkbox.checked).toBe(true);

        checkbox.click();
        picker().querySelector('.btn-primary').click();

        expect(tags()).toHaveLength(0);
    });

    test('Escape closes the picker only, not the modal', async () => {
        const button = setUp();
        const modalHandler = jest.fn();
        document
            .querySelector('.modal')
            .addEventListener('keydown', modalHandler);
        button.click();
        await flush();

        document
            .getElementById('f-cc-picker-search')
            .dispatchEvent(
                new KeyboardEvent('keydown', {
                    key: 'Escape',
                    bubbles: true,
                    cancelable: true,
                })
            );

        expect(picker()).toBeNull();
        expect(modalHandler).not.toHaveBeenCalled();
        expect(document.activeElement).toBe(button);
    });

    test('Enter in the search field does not submit the mail form', async () => {
        setUp().click();
        await flush();

        const event = new KeyboardEvent('keydown', {
            key: 'Enter',
            bubbles: true,
            cancelable: true,
        });
        document.getElementById('f-cc-picker-search').dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
    });

    test('a second click on the button closes the picker', async () => {
        const button = setUp();
        button.click();
        await flush();

        button.click();

        expect(picker()).toBeNull();
        expect(button.getAttribute('aria-expanded')).toBe('false');
    });

    test('a failed search is reported in the picker', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({ ok: false, status: 403 })
        );
        setUp().click();
        await flush();

        expect(picker().querySelector('.text-danger').textContent).toBe(
            'Recherche indisponible, vous pouvez saisir une adresse e-mail.'
        );
    });
});
