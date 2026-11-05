const EpMailCombobox = require('../../../public/js/library/es.mail-combobox.js');

const ADA = {
    uid: 1,
    fullname: 'Ada Lovelace',
    username: 'ada',
    mail: 'ada@example.org',
    roles: ['editor'],
    group: 'staff',
    paperRole: null,
};
const AUTHOR = {
    uid: 2,
    fullname: 'Alan Author',
    username: 'alan',
    mail: 'alan@example.org',
    roles: [],
    group: 'paper',
    paperRole: 'author',
};
const OTHER = {
    uid: 3,
    fullname: 'Grace Other',
    username: 'grace',
    mail: 'grace@example.org',
    roles: ['reviewer'],
    group: 'other',
    paperRole: null,
};

function setUp(fetcher, extra = {}) {
    document.body.innerHTML = `
        <form>
            <div class="col">
                <input type="text" id="cc" aria-describedby="cc-help">
                <span id="cc-help">help</span>
            </div>
        </form>`;
    const input = document.getElementById('cc');
    const callbacks = {
        onSelect: jest.fn(),
        onFreeEntry: jest.fn(() => true),
        onBackspaceEmpty: jest.fn(),
    };
    const combobox = EpMailCombobox.attach(
        input,
        Object.assign({ docId: 12, fetcher, debounceMs: 250 }, callbacks, extra)
    );
    return { input, combobox, callbacks };
}

function type(input, value) {
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

function key(input, name) {
    const event = new KeyboardEvent('keydown', {
        key: name,
        bubbles: true,
        cancelable: true,
    });
    input.dispatchEvent(event);
    return event;
}

async function searchFor(input, value) {
    type(input, value);
    jest.advanceTimersByTime(250);
    // let the fetcher promise and the rendering settle
    await Promise.resolve();
    await Promise.resolve();
}

describe('es.mail-combobox', () => {
    beforeEach(() => {
        jest.useFakeTimers();
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    describe('ARIA structure', () => {
        test('the input is a combobox controlling a listbox, with a status region from the start', () => {
            const { input, combobox } = setUp(jest.fn());

            expect(input.getAttribute('role')).toBe('combobox');
            expect(input.getAttribute('aria-autocomplete')).toBe('list');
            expect(input.getAttribute('aria-expanded')).toBe('false');
            expect(input.getAttribute('aria-controls')).toBe('cc-listbox');
            expect(input.getAttribute('autocomplete')).toBe('off');
            expect(combobox.listbox.getAttribute('role')).toBe('listbox');
            expect(combobox.listbox.hidden).toBe(true);
            expect(combobox.status.getAttribute('role')).toBe('status');
            expect(document.getElementById('cc-status')).not.toBeNull();
        });

        test('the suggestions share a container with the input only, so that they overlay the content below', () => {
            const { input, combobox } = setUp(jest.fn());

            const container = input.parentElement;
            expect(container.classList).toContain('ep-combobox');
            expect(Array.from(container.children)).toEqual([
                input,
                combobox.listbox,
            ]);
            expect(container.parentElement.classList).toContain('col');
        });

        test('attaching twice is ignored', () => {
            const { input } = setUp(jest.fn());

            expect(EpMailCombobox.attach(input, {})).toBeNull();
            expect(document.querySelectorAll('[role="listbox"]')).toHaveLength(
                1
            );
        });
    });

    describe('search', () => {
        test('waits for 2 characters and announces it', () => {
            const fetcher = jest.fn(() => Promise.resolve([]));
            const { input, combobox } = setUp(fetcher);

            type(input, ' a ');
            jest.advanceTimersByTime(1000);

            expect(fetcher).not.toHaveBeenCalled();
            expect(combobox.status.textContent).toBe(
                'Saisissez au moins 2 caractères.'
            );
        });

        test('is debounced and sent with the paper of the mail', () => {
            const fetcher = jest.fn(() => Promise.resolve([]));
            const { input } = setUp(fetcher);

            type(input, 'ad');
            type(input, 'ada');
            jest.advanceTimersByTime(249);
            expect(fetcher).not.toHaveBeenCalled();

            jest.advanceTimersByTime(1);
            expect(fetcher).toHaveBeenCalledTimes(1);
            expect(fetcher.mock.calls[0][0]).toMatchObject({
                query: 'ada',
                docId: 12,
            });
        });

        test('aborts the previous request', async () => {
            const signals = [];
            const fetcher = jest.fn(params => {
                signals.push(params.signal);
                return new Promise(() => {});
            });
            const { input } = setUp(fetcher);

            await searchFor(input, 'ad');
            await searchFor(input, 'ada');

            expect(signals[0].aborted).toBe(true);
            expect(signals[1].aborted).toBe(false);
        });

        test('groups the suggestions: paper participants, editorial staff, others', async () => {
            const { input, combobox } = setUp(() =>
                Promise.resolve([OTHER, ADA, AUTHOR])
            );

            await searchFor(input, 'a');
            await searchFor(input, 'al');

            const groups = combobox.listbox.querySelectorAll('[role="group"]');
            expect(groups).toHaveLength(3);
            const labels = Array.from(groups).map(
                group =>
                    document.getElementById(
                        group.getAttribute('aria-labelledby')
                    ).textContent
            );
            expect(labels).toEqual([
                "Intervenants de l'article",
                'Équipe éditoriale',
                'Autres utilisateurs',
            ]);
            const options =
                combobox.listbox.querySelectorAll('[role="option"]');
            expect(
                Array.from(options).map(
                    o => o.querySelector('.ep-recipient-email').textContent
                )
            ).toEqual([
                'alan@example.org',
                'ada@example.org',
                'grace@example.org',
            ]);
            expect(input.getAttribute('aria-expanded')).toBe('true');
            expect(combobox.status.textContent).toContain('3 suggestions');
        });

        test('shows the name, username, roles and email of a suggestion', async () => {
            const { input, combobox } = setUp(() => Promise.resolve([AUTHOR]));

            await searchFor(input, 'al');

            const option = combobox.listbox.querySelector('[role="option"]');
            expect(option.textContent).toContain('Alan Author');
            expect(option.textContent).toContain('(alan)');
            expect(option.querySelector('.ep-recipient-role').textContent).toBe(
                'author'
            );
            expect(option.textContent).toContain('alan@example.org');
        });

        test('the role badges have the colors of the users list, a co-author the color of the authors', async () => {
            const coauthor = Object.assign({}, AUTHOR, {
                paperRole: 'coauthor',
                roles: ['guest_editor', 'reviewer'],
            });
            const { input, combobox } = setUp(() =>
                Promise.resolve([coauthor])
            );

            await searchFor(input, 'al');

            const badges = Array.from(
                combobox.listbox.querySelectorAll('.ep-recipient-role')
            );
            expect(badges.map(badge => badge.className)).toEqual([
                'label ep-recipient-role role-author',
                'label ep-recipient-role role-guest_editor',
                'label ep-recipient-role role-reviewer',
            ]);
        });

        test('renders the values as text', async () => {
            const evil = Object.assign({}, ADA, {
                fullname: '<img src=x onerror=alert(1)>',
            });
            const { input, combobox } = setUp(() => Promise.resolve([evil]));

            await searchFor(input, 'ad');

            expect(combobox.listbox.querySelector('img')).toBeNull();
            expect(combobox.listbox.textContent).toContain(
                '<img src=x onerror=alert(1)>'
            );
        });

        test('announces when nothing is found', async () => {
            const { input, combobox } = setUp(() => Promise.resolve([]));

            await searchFor(input, 'zz');

            expect(input.getAttribute('aria-expanded')).toBe('false');
            expect(combobox.status.textContent).toBe(
                'Aucun résultat, vous pouvez saisir une adresse e-mail.'
            );
        });

        test('a refused or failed search still lets the user type an address', async () => {
            const error = new Error('Forbidden');
            error.status = 403;
            const { input, combobox, callbacks } = setUp(() =>
                Promise.reject(error)
            );

            await searchFor(input, 'ada@example.org');
            await Promise.resolve();

            expect(combobox.status.textContent).toBe(
                'Recherche indisponible, vous pouvez saisir une adresse e-mail.'
            );
            key(input, 'Enter');
            expect(callbacks.onFreeEntry).toHaveBeenCalledWith(
                'ada@example.org'
            );
        });
    });

    describe('keyboard', () => {
        async function opened() {
            const context = setUp(() => Promise.resolve([AUTHOR, ADA, OTHER]));
            await searchFor(context.input, 'al');
            return context;
        }

        test('arrows move the active option', async () => {
            const { input, combobox } = await opened();
            const options =
                combobox.listbox.querySelectorAll('[role="option"]');

            expect(key(input, 'ArrowDown').defaultPrevented).toBe(true);
            expect(input.getAttribute('aria-activedescendant')).toBe(
                options[0].id
            );
            expect(options[0].getAttribute('aria-selected')).toBe('true');

            key(input, 'ArrowDown');
            expect(input.getAttribute('aria-activedescendant')).toBe(
                options[1].id
            );
            expect(options[0].getAttribute('aria-selected')).toBe('false');

            key(input, 'ArrowUp');
            expect(input.getAttribute('aria-activedescendant')).toBe(
                options[0].id
            );
        });

        test('Home and End go to the first and last options', async () => {
            const { input, combobox } = await opened();
            const options =
                combobox.listbox.querySelectorAll('[role="option"]');

            key(input, 'ArrowDown');
            key(input, 'End');
            expect(input.getAttribute('aria-activedescendant')).toBe(
                options[2].id
            );
            key(input, 'Home');
            expect(input.getAttribute('aria-activedescendant')).toBe(
                options[0].id
            );
        });

        test('Enter chooses the active option', async () => {
            const { input, callbacks } = await opened();

            key(input, 'ArrowDown');
            key(input, 'ArrowDown');
            expect(key(input, 'Enter').defaultPrevented).toBe(true);

            expect(callbacks.onSelect).toHaveBeenCalledWith(ADA);
            expect(input.value).toBe('');
            expect(input.getAttribute('aria-expanded')).toBe('false');
            expect(input.hasAttribute('aria-activedescendant')).toBe(false);
        });

        test('Enter chooses the only suggestion', async () => {
            const { input, callbacks } = setUp(() => Promise.resolve([ADA]));
            await searchFor(input, 'Lovelace');

            expect(key(input, 'Enter').defaultPrevented).toBe(true);

            expect(callbacks.onSelect).toHaveBeenCalledWith(ADA);
        });

        test('a click on a suggestion chooses it', async () => {
            const { combobox, callbacks } = await opened();

            combobox.listbox.querySelectorAll('[role="option"]')[2].click();

            expect(callbacks.onSelect).toHaveBeenCalledWith(OTHER);
        });

        test('Escape closes the list, then clears the input, without closing the modal', async () => {
            const { input } = await opened();
            const modalHandler = jest.fn();
            document.body.addEventListener('keydown', modalHandler);

            key(input, 'Escape');
            expect(input.getAttribute('aria-expanded')).toBe('false');
            expect(input.value).toBe('al');

            key(input, 'Escape');
            expect(input.value).toBe('');
            expect(modalHandler).not.toHaveBeenCalled();

            key(input, 'Escape');
            expect(modalHandler).toHaveBeenCalledTimes(1);
        });

        test('Backspace in the empty input hands over to the recipients list, without removing', () => {
            const { input, callbacks } = setUp(jest.fn());

            expect(key(input, 'Backspace').defaultPrevented).toBe(true);
            expect(callbacks.onBackspaceEmpty).toHaveBeenCalledTimes(1);

            input.value = 'a';
            key(input, 'Backspace');
            expect(callbacks.onBackspaceEmpty).toHaveBeenCalledTimes(1);
        });
    });

    describe('typed addresses', () => {
        test.each([['Enter'], [','], [';']])(
            '%s adds the typed address',
            separator => {
                const { input, callbacks } = setUp(jest.fn());

                input.value = 'Some One <some.one@example.org>';
                expect(key(input, separator).defaultPrevented).toBe(true);

                expect(callbacks.onFreeEntry).toHaveBeenCalledWith(
                    'Some One <some.one@example.org>'
                );
                expect(input.value).toBe('');
            }
        );

        test('Tab adds the typed address without trapping the focus', () => {
            const { input, callbacks } = setUp(jest.fn());

            input.value = 'a@example.org';
            expect(key(input, 'Tab').defaultPrevented).toBe(false);
            expect(callbacks.onFreeEntry).toHaveBeenCalledWith('a@example.org');
        });

        test('an invalid address stays in the input with an error tied to it', () => {
            const { input, callbacks } = setUp(jest.fn());

            input.value = 'not an address';
            key(input, 'Enter');

            expect(callbacks.onFreeEntry).not.toHaveBeenCalled();
            expect(input.value).toBe('not an address');
            expect(input.getAttribute('aria-invalid')).toBe('true');
            const error = document.getElementById('cc-error');
            expect(error.hidden).toBe(false);
            expect(error.textContent).toBe(
                'Adresse e-mail invalide : not an address'
            );
            expect(input.getAttribute('aria-describedby')).toBe(
                'cc-help cc-error'
            );

            type(input, 'a@example.org');
            expect(input.hasAttribute('aria-invalid')).toBe(false);
            expect(input.getAttribute('aria-describedby')).toBe('cc-help');
        });

        test('pasting several addresses adds the valid ones and keeps the others', () => {
            const { input, callbacks } = setUp(jest.fn());
            const event = new Event('paste', {
                bubbles: true,
                cancelable: true,
            });
            event.clipboardData = {
                getData: () => 'a@example.org; wrong\nb@example.org',
            };

            input.dispatchEvent(event);

            expect(event.defaultPrevented).toBe(true);
            expect(callbacks.onFreeEntry.mock.calls).toEqual([
                ['a@example.org'],
                ['b@example.org'],
            ]);
            expect(input.value).toBe('wrong');
            expect(input.getAttribute('aria-invalid')).toBe('true');
        });

        test.each([['blur'], ['Tab']])(
            'a name typed without choosing a suggestion stays, without error (%s)',
            way => {
                const { input, callbacks } = setUp(jest.fn());

                input.value = 'Dupont';
                if (way === 'Tab') {
                    key(input, 'Tab');
                } else {
                    input.dispatchEvent(new Event('blur'));
                }

                expect(callbacks.onFreeEntry).not.toHaveBeenCalled();
                expect(input.value).toBe('Dupont');
                expect(input.hasAttribute('aria-invalid')).toBe(false);
            }
        );

        test('leaving the input adds the typed address', () => {
            const { input, callbacks } = setUp(jest.fn());

            input.value = 'a@example.org';
            input.dispatchEvent(new Event('blur'));

            expect(callbacks.onFreeEntry).toHaveBeenCalledWith('a@example.org');
        });
    });

    describe('helpers', () => {
        test.each([
            ['a@example.org', 'a@example.org'],
            ['<a@example.org>', 'a@example.org'],
            ['Ada Lovelace <a@example.org>', 'a@example.org'],
            ['Ada Lovelace', ''],
        ])('extractEmail(%s)', (raw, email) => {
            expect(EpMailCombobox.extractEmail(raw)).toBe(email);
        });

        test('splitAddresses splits on commas, semicolons and new lines', () => {
            expect(
                EpMailCombobox.splitAddresses(' a@x.org,b@x.org;\nc@x.org ;; ')
            ).toEqual(['a@x.org', 'b@x.org', 'c@x.org']);
        });

        test('fetchRecipients sends a same-origin XHR GET and reads the results', async () => {
            global.fetch = jest.fn(() =>
                Promise.resolve({
                    ok: true,
                    json: () => Promise.resolve({ results: [ADA] }),
                })
            );

            const results = await EpMailCombobox.fetchRecipients({
                query: 'ad a',
                docId: 12,
                role: '',
            });

            expect(results).toEqual([ADA]);
            const [url, options] = global.fetch.mock.calls[0];
            const parsed = new URL(url);
            expect(parsed.pathname).toBe('/administratemail/getrecipients');
            expect(parsed.searchParams.get('q')).toBe('ad a');
            expect(parsed.searchParams.get('docid')).toBe('12');
            expect(parsed.searchParams.has('role')).toBe(false);
            expect(options.method).toBe('GET');
            expect(options.credentials).toBe('same-origin');
            expect(options.headers['X-Requested-With']).toBe('XMLHttpRequest');
        });

        test('fetchRecipients rejects a refused search', async () => {
            global.fetch = jest.fn(() =>
                Promise.resolve({ ok: false, status: 403 })
            );

            await expect(
                EpMailCombobox.fetchRecipients({ query: 'ad' })
            ).rejects.toMatchObject({
                status: 403,
            });
        });
    });
});
