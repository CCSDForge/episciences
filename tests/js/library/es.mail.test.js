global.EpMailCombobox = require('../../../public/js/library/es.mail-combobox.js');
const mail = require('../../../public/js/library/es.mail.js');

const ADA = {
    uid: 1,
    fullname: 'Ada Lovelace',
    username: 'ada',
    mail: 'ada@example.org',
};
const ALAN = {
    uid: 2,
    fullname: 'Alan Turing',
    username: 'alan',
    mail: 'alan@example.org',
};

/** Markup of Episciences\Mail\RecipientFieldDecorator */
function field(formId, target, extra = '') {
    return `
        <div class="form-group">
            <label for="${formId}-${target}">${target}</label>
            <div class="col">
                <ul id="${formId}-${target}-tags" class="recipient-tags list-unstyled"
                    data-recipient-target="${target}" data-hidden-input="${formId}-hidden_${target}"></ul>
                <input type="text" id="${formId}-${target}" class="recipient-input"
                       data-recipient-target="${target}" ${extra}>
                <button type="button" class="show_contacts_button" data-target="${target}"
                        data-input-id="${formId}-${target}" aria-expanded="false">Choisir</button>
            </div>
        </div>
        <input type="hidden" id="${formId}-hidden_${target}" value="[]">`;
}

function setUp(toExtra = '') {
    document.body.innerHTML = `
        <form id="f">
            ${field('f', 'to', toExtra)}
            ${field('f', 'cc')}
            ${field('f', 'bcc')}
            <input type="checkbox" id="copy">
        </form>`;
    window.__epContactsForm = document.getElementById('f');
    return document.getElementById('f');
}

function hidden(target) {
    return JSON.parse(document.getElementById('f-hidden_' + target).value);
}

function tags(target) {
    return Array.from(
        document.querySelectorAll('#f-' + target + '-tags .recipient-tag')
    );
}

describe('es.mail recipients list', () => {
    afterEach(() => {
        delete window.__epContactsForm;
        delete window.sender;
    });

    test('a known user becomes a tag with a real remove button and is stored with its uid', () => {
        setUp();

        const id = mail.addRecipient('cc', ADA, 'known');

        const tag = document.getElementById(id);
        expect(tag.tagName).toBe('LI');
        expect(tag.dataset.uid).toBe('1');
        expect(tag.querySelector('.recipient-name').textContent).toBe(
            'Ada Lovelace'
        );
        const button = tag.querySelector('button.remove-recipient');
        expect(button.type).toBe('button');
        expect(button.textContent).toBe('Retirer Ada Lovelace');
        expect(
            button.querySelector('.glyphicon').getAttribute('aria-hidden')
        ).toBe('true');
        expect(hidden('cc')).toEqual([
            { key: id, value: 'Ada Lovelace <ada@example.org>', uid: '1' },
        ]);
        expect(
            document.getElementById('f-cc-tags').getAttribute('aria-label')
        ).toBe('Destinataires (Cc), 1');
        expect(document.getElementById('ep-mail-live').textContent).toBe(
            'Ada Lovelace ajouté aux destinataires (Cc).'
        );
    });

    test('a typed address is stored as typed, without uid (resolved by the server)', () => {
        setUp();

        const id = mail.addRecipient(
            'bcc',
            'Some One <some@example.org>',
            'manual'
        );

        expect(document.getElementById(id).classList).toContain(
            'recipient-tag-manual'
        );
        expect(hidden('bcc')).toEqual([
            { key: id, value: 'Some One <some@example.org>', uid: null },
        ]);
    });

    test('names are rendered as text', () => {
        setUp();

        mail.addRecipient(
            'cc',
            Object.assign({}, ADA, {
                fullname: '<img src=x onerror=alert(1)>',
            }),
            'known'
        );

        expect(document.querySelector('#f-cc-tags img')).toBeNull();
    });

    test('a recipient already chosen in another field is not added twice', () => {
        setUp();
        mail.addRecipient('cc', ADA, 'known');

        mail.addRecipient('bcc', 'ADA@example.org', 'manual');

        expect(tags('bcc')).toHaveLength(0);
        expect(document.getElementById('ep-mail-live').textContent).toBe(
            'ADA@example.org est déjà destinataire en Cc.'
        );
    });

    test('removing a tag moves the focus to the next tag, then the previous one, then the input', () => {
        setUp();
        mail.addRecipient('cc', ADA, 'known');
        mail.addRecipient('cc', ALAN, 'known');
        mail.addRecipient('cc', 'c@example.org', 'manual');
        const [first, second, third] = tags('cc');

        first.querySelector('button').click();
        expect(document.activeElement).toBe(second.querySelector('button'));

        third.querySelector('button').click();
        expect(document.activeElement).toBe(second.querySelector('button'));

        second.querySelector('button').click();
        expect(document.activeElement).toBe(document.getElementById('f-cc'));
        expect(hidden('cc')).toEqual([]);
        expect(
            document.getElementById('f-cc-tags').getAttribute('aria-label')
        ).toBe('Destinataires (Cc), 0');
    });

    test('Backspace and Delete on a focused tag remove it', () => {
        setUp();
        mail.addRecipient('cc', ADA, 'known');
        mail.addRecipient('cc', ALAN, 'known');
        const button = tags('cc')[1].querySelector('button');
        button.focus();

        button.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'Backspace', bubbles: true })
        );

        expect(tags('cc')).toHaveLength(1);
        expect(document.getElementById('ep-mail-live').textContent).toBe(
            'Alan Turing retiré des destinataires (Cc).'
        );
        expect(document.activeElement).toBe(
            tags('cc')[0].querySelector('button')
        );
    });

    test('the "receive a copy" box follows the sender in Bcc', () => {
        setUp();
        window.sender = ADA;

        const id = mail.addRecipient('bcc', ADA, 'known');
        expect(document.getElementById('copy').checked).toBe(true);

        mail.removeRecipient(document.getElementById(id));
        expect(document.getElementById('copy').checked).toBe(false);
    });

    test('the recipients set by the server are shown when the page opens', () => {
        setUp();
        document.getElementById('f-hidden_bcc').value = JSON.stringify([
            { key: 'init-0', value: 'r1@example.org', uid: null },
            {
                key: 'init-1',
                value: 'Reviewer Two <r2@example.org>',
                uid: null,
            },
        ]);

        mail.initPaperModalRecipientTagsFromHidden();

        expect(tags('bcc').map(tag => tag.dataset.email)).toEqual([
            'r1@example.org',
            'r2@example.org',
        ]);
        expect(hidden('bcc').map(r => r.value)).toEqual([
            'r1@example.org',
            'Reviewer Two <r2@example.org>',
        ]);
    });

    test('a form shown again keeps its recipients known, with their role in the paper', () => {
        setUp();
        mail.addRecipient(
            'cc',
            {
                uid: 9,
                fullname: 'Rev Iewer',
                mail: 'rev@example.org',
                paperRole: 'reviewer',
            },
            'known'
        );
        const posted = document.getElementById('f-hidden_cc').value;
        // the server sends the posted JSON back after a validation error
        setUp();
        document.getElementById('f-hidden_to').value = JSON.stringify([
            {
                key: 'k',
                value: 'Au Thor <au@example.org>',
                uid: '8',
                paperRole: 'author',
            },
        ]);
        document.getElementById('f-hidden_cc').value = posted;

        mail.initPaperModalRecipientTagsFromHidden();

        expect(
            tags('to').map(tag => [tag.dataset.uid, tag.dataset.paperRole])
        ).toEqual([['8', 'author']]);
        expect(
            tags('cc').map(tag => [tag.dataset.uid, tag.dataset.paperRole])
        ).toEqual([['9', 'reviewer']]);
        expect(hidden('to')[0]).toMatchObject({
            value: 'Au Thor <au@example.org>',
            uid: '8',
            paperRole: 'author',
        });
        expect(document.querySelector('.ep-anonymity-warning')).not.toBeNull();
    });

    test('epRemoveRecipientByEmail removes the tag of an address', () => {
        const form = setUp();
        mail.addRecipient('cc', 'co@example.org', 'manual');

        mail.epRemoveRecipientByEmail(form, 'CO@example.org');

        expect(tags('cc')).toHaveLength(0);
    });

    describe('peer review anonymity', () => {
        const REVIEWER = {
            uid: 9,
            fullname: 'Rev Iewer',
            mail: 'rev@example.org',
            paperRole: 'reviewer',
        };
        const AUTHOR = {
            uid: 8,
            fullname: 'Au Thor',
            mail: 'au@example.org',
            paperRole: 'author',
        };

        test('an author and a reviewer of the paper in To/Cc raise a warning', () => {
            const form = setUp();

            mail.addRecipient('to', AUTHOR, 'known');
            expect(form.querySelector('.ep-anonymity-warning')).toBeNull();

            mail.addRecipient('cc', REVIEWER, 'known');
            const warning = form.querySelector('.ep-anonymity-warning');
            expect(warning.getAttribute('role')).toBe('alert');
        });

        test('the fixed "To" of the paper modals counts as the author', () => {
            const form = setUp('data-paper-role="author" disabled');

            mail.addRecipient('cc', REVIEWER, 'known');

            expect(form.querySelector('.ep-anonymity-warning')).not.toBeNull();
        });

        test('a reviewer in Bcc does not raise the warning', () => {
            const form = setUp();
            mail.addRecipient('to', AUTHOR, 'known');

            mail.addRecipient('bcc', REVIEWER, 'known');

            expect(form.querySelector('.ep-anonymity-warning')).toBeNull();
        });

        test('the warning button moves the reviewers to Bcc', () => {
            const form = setUp();
            mail.addRecipient('to', AUTHOR, 'known');
            mail.addRecipient('cc', REVIEWER, 'known');

            form.querySelector('.ep-anonymity-warning button').click();

            expect(tags('cc')).toHaveLength(0);
            expect(tags('bcc').map(tag => tag.dataset.uid)).toEqual(['9']);
            expect(tags('bcc')[0].dataset.paperRole).toBe('reviewer');
            expect(form.querySelector('.ep-anonymity-warning')).toBeNull();
            expect(document.activeElement).toBe(
                document.getElementById('f-bcc')
            );
        });
    });

    describe('search inputs', () => {
        test('Backspace in an empty input focuses the last tag without removing it', () => {
            setUp();
            mail.epInitRecipientFields(document);
            mail.addRecipient('cc', ADA, 'known');
            const input = document.getElementById('f-cc');
            input.focus();

            input.dispatchEvent(
                new KeyboardEvent('keydown', {
                    key: 'Backspace',
                    bubbles: true,
                    cancelable: true,
                })
            );

            expect(tags('cc')).toHaveLength(1);
            expect(document.activeElement).toBe(
                tags('cc')[0].querySelector('button')
            );
        });

        test('a typed address goes to the field of the input', () => {
            setUp();
            mail.epInitRecipientFields(document);
            delete window.__epContactsForm;
            const input = document.getElementById('f-bcc');

            input.value = 'x@example.org';
            input.dispatchEvent(
                new KeyboardEvent('keydown', {
                    key: 'Enter',
                    bubbles: true,
                    cancelable: true,
                })
            );

            expect(tags('bcc').map(tag => tag.dataset.email)).toEqual([
                'x@example.org',
            ]);
        });
    });
});
