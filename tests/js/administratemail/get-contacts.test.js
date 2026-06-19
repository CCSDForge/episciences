const fs = require('fs');
const path = require('path');

/**
 * get-contacts.js exposes free (non-exported) functions and relies on a few
 * module-level jQuery references. The file is evaluated in an isolated function
 * scope; a short appended snippet captures the helpers under test and lets the
 * test inject the jQuery containers showList() reads from.
 */
function loadGetContacts() {
    const code = fs.readFileSync(
        path.join(
            __dirname,
            '../../../public/js/administratemail/get-contacts.js'
        ),
        'utf8'
    );
    const api = {};
    // eslint-disable-next-line no-new-func
    const factory = new Function(
        'api',
        code +
            '\n;api.showList = showList;' +
            '\napi.escapeHtml = escapeHtml;' +
            '\napi.setContext = function (list, dropdown) {' +
            '  $contact_list = list; $contact_type_dropdown = dropdown;' +
            '};'
    );
    factory(api);
    return api;
}

describe('get-contacts.js', () => {
    let api;
    let captured;

    beforeEach(() => {
        captured = { tableHtml: null };

        global.translate = jest.fn(text => text);

        // Minimal jQuery stand-in: only the chains showList() exercises.
        const trCollection = {
            length: 0,
            off() {
                return this;
            },
            on() {
                return this;
            },
            each() {
                return this;
            },
        };
        const tableNode = {
            html(value) {
                captured.tableHtml = value;
                return tableNode;
            },
        };
        const contactList = {
            find(selector) {
                return selector === 'table' ? tableNode : trCollection;
            },
        };
        const dropdown = {
            find() {
                return { html() {} };
            },
        };

        global.$ = jest.fn(() => ({
            find() {
                return { each() {} };
            },
        }));

        api = loadGetContacts();
        api.setContext(contactList, dropdown);
    });

    afterEach(() => {
        delete global.$;
        delete global.translate;
        delete window.all_contacts;
        delete window.reviewers;
        jest.clearAllMocks();
    });

    function makeLi(dataValue) {
        return {
            find() {
                return { html: () => 'Tous les contacts' };
            },
            data: () => dataValue,
        };
    }

    describe('escapeHtml', () => {
        test('encodes markup-significant characters', () => {
            expect(
                api.escapeHtml('<img src=x onerror=alert(1)>')
            ).not.toContain('<img');
            expect(api.escapeHtml('<b>')).toBe('&lt;b&gt;');
        });

        test('returns an empty string for null or undefined', () => {
            expect(api.escapeHtml(null)).toBe('');
            expect(api.escapeHtml(undefined)).toBe('');
        });
    });

    describe('showList', () => {
        test('resolves the collection by variable name without eval', () => {
            window.reviewers = [
                {
                    uid: 7,
                    fullname: 'Ada',
                    username: 'ada',
                    mail: 'ada@x.org',
                    role: [],
                },
            ];

            api.showList(makeLi('reviewers'));

            expect(captured.tableHtml).toContain('Ada');
            expect(captured.tableHtml).toContain('ada@x.org');
            expect(captured.tableHtml).toContain('id="contact_7"');
        });

        test('does not throw when the named collection is absent', () => {
            expect(() => api.showList(makeLi('does_not_exist'))).not.toThrow();
            expect(captured.tableHtml).toBe('');
        });

        test('escapes contact fields rendered into the table', () => {
            window.all_contacts = [
                {
                    uid: 12,
                    fullname: '<img src=x onerror=alert(1)>',
                    username: '<script>bad()</script>',
                    mail: 'a"b@x.org',
                    role: [],
                },
            ];

            api.showList(makeLi('all_contacts'));

            expect(captured.tableHtml).not.toContain('<img src=x');
            expect(captured.tableHtml).not.toContain('<script>bad()');
            expect(captured.tableHtml).toContain('&lt;img');
            expect(captured.tableHtml).toContain('&lt;script&gt;');
        });

        test('keeps only digits in the row id derived from uid', () => {
            window.all_contacts = [
                {
                    uid: '9"><svg onload=evil()>',
                    fullname: 'X',
                    username: 'x',
                    mail: 'x@x.org',
                    role: [],
                },
            ];

            api.showList(makeLi('all_contacts'));

            expect(captured.tableHtml).toContain('id="contact_9"');
            expect(captured.tableHtml).not.toContain('<svg');
        });

        test('escapes role labels and skips the member role', () => {
            window.all_contacts = [
                {
                    uid: 3,
                    fullname: 'Y',
                    username: 'y',
                    mail: 'y@x.org',
                    role: ['member', 'reviewer'],
                },
            ];

            api.showList(makeLi('all_contacts'));

            expect(captured.tableHtml).toContain('role-reviewer');
            expect(captured.tableHtml).not.toContain('role-member');
        });
    });
});
