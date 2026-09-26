/**
 * @jest-environment jsdom
 */
const ProfileCardDialog = require('../../../public/js/user/profile-card-dialog.js');

function renderPage() {
    document.body.innerHTML = `
        <a id="trigger" href="/user/view?userid=42" data-user-card="42" data-user-name="Jane Doe">card</a>
        <dialog id="user-card-dialog">
            <h2 data-user-card-title></h2>
            <div data-user-card-body></div>
            <template data-user-card-loading><p class="loading">Loading…</p></template>
        </dialog>`;

    const dialog = document.getElementById('user-card-dialog');
    // jsdom has no <dialog> support
    dialog.showModal = jest.fn(() => dialog.setAttribute('open', ''));
    dialog.close = jest.fn(() => {
        dialog.removeAttribute('open');
        dialog.dispatchEvent(new Event('close'));
    });
    return dialog;
}

function click(element, init = {}) {
    const event = new MouseEvent('click', {
        bubbles: true,
        cancelable: true,
        button: 0,
        ...init,
    });
    element.dispatchEvent(event);
    return event;
}

const flush = () => new Promise(resolve => setTimeout(resolve, 0));

describe('profile-card-dialog.js', () => {
    let dialog;

    beforeEach(() => {
        dialog = renderPage();
        global.fetch = jest.fn();
        ProfileCardDialog.lastTrigger = null;
        ProfileCardDialog.pending = null;
        ProfileCardDialog.init(document);
    });

    afterEach(() => {
        delete global.fetch;
        jest.restoreAllMocks();
    });

    test('opens the dialog with the name and loads the card fragment', async () => {
        global.fetch.mockResolvedValue({
            ok: true,
            text: () => Promise.resolve('<div class="card">Jane</div>'),
        });

        const event = click(document.getElementById('trigger'));
        expect(event.defaultPrevented).toBe(true);
        expect(dialog.showModal).toHaveBeenCalled();
        expect(dialog.querySelector('[data-user-card-title]').textContent).toBe(
            'Jane Doe'
        );
        expect(dialog.querySelector('.loading')).not.toBeNull();

        await flush();

        expect(global.fetch).toHaveBeenCalledWith(
            '/user/card?userid=42',
            expect.objectContaining({ credentials: 'same-origin' })
        );
        expect(dialog.querySelector('.card').textContent).toBe('Jane');
        expect(dialog.hasAttribute('aria-busy')).toBe(false);
    });

    test.each([
        ['ctrl', { ctrlKey: true }],
        ['meta', { metaKey: true }],
        ['shift', { shiftKey: true }],
        ['middle button', { button: 1 }],
    ])('lets a %s click follow the link (new tab/window)', (_label, init) => {
        // Window listeners run last: record what the script decided, then stop jsdom from
        // attempting the (unimplemented) navigation, which would log in a later test.
        let preventedByScript = null;
        window.addEventListener(
            'click',
            event => {
                preventedByScript = event.defaultPrevented;
                event.preventDefault();
            },
            { once: true }
        );

        click(document.getElementById('trigger'), init);

        expect(preventedByScript).toBe(false);
        expect(dialog.showModal).not.toHaveBeenCalled();
    });

    test('closes on a backdrop click and gives the focus back to the trigger', () => {
        global.fetch.mockReturnValue(new Promise(() => {}));
        const trigger = document.getElementById('trigger');
        click(trigger);
        trigger.blur();

        click(dialog);

        expect(dialog.close).toHaveBeenCalled();
        expect(document.activeElement).toBe(trigger);
    });

    test("never shows a late response under another user's name", async () => {
        document.body.insertAdjacentHTML(
            'afterbegin',
            '<a id="other" href="/user/view?userid=7" data-user-card="7" data-user-name="John Roe">card</a>'
        );
        const responses = {};
        global.fetch.mockImplementation(
            (url, options) =>
                new Promise((resolve, reject) => {
                    responses[url] = resolve;
                    options.signal.addEventListener('abort', () =>
                        reject(new DOMException('Aborted', 'AbortError'))
                    );
                })
        );
        const jane = {
            ok: true,
            text: () => Promise.resolve('<div class="card">Jane</div>'),
        };
        const john = {
            ok: true,
            text: () => Promise.resolve('<div class="card">John</div>'),
        };

        click(document.getElementById('trigger'));
        click(document.getElementById('other'));
        responses['/user/card?userid=7'](john);
        responses['/user/card?userid=42'](jane);
        await flush();

        expect(dialog.querySelector('[data-user-card-title]').textContent).toBe(
            'John Roe'
        );
        expect(dialog.querySelector('.card').textContent).toBe('John');
        expect(dialog.hasAttribute('aria-busy')).toBe(false);
    });

    test('does not navigate away when a request fails after the user closed the dialog', async () => {
        let fail;
        global.fetch.mockImplementation(
            () =>
                new Promise((_resolve, reject) => {
                    fail = reject;
                })
        );
        const assign = jest
            .spyOn(ProfileCardDialog, 'navigate')
            .mockImplementation(() => {});

        click(document.getElementById('trigger'));
        dialog.close();
        fail(new TypeError('network error'));
        await flush();

        expect(assign).not.toHaveBeenCalled();
        expect(dialog.hasAttribute('aria-busy')).toBe(false);
    });

    test('falls back to the profile page when the request fails', async () => {
        global.fetch.mockResolvedValue({ ok: false, status: 500 });
        const assign = jest
            .spyOn(ProfileCardDialog, 'navigate')
            .mockImplementation(() => {});

        click(document.getElementById('trigger'));
        await flush();

        expect(dialog.close).toHaveBeenCalled();
        expect(assign).toHaveBeenCalledWith(
            expect.stringContaining('/user/view?userid=42')
        );
    });

    test('ignores clicks elsewhere', () => {
        const other = document.createElement('a');
        other.href = '/elsewhere';
        document.body.appendChild(other);

        expect(click(other).defaultPrevented).toBe(false);
    });
});
