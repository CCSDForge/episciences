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
        ProfileCardDialog.init(document);
    });

    afterEach(() => {
        delete global.fetch;
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
        const event = click(document.getElementById('trigger'), init);

        expect(event.defaultPrevented).toBe(false);
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

    test('ignores clicks elsewhere', () => {
        const other = document.createElement('a');
        other.href = '/elsewhere';
        document.body.appendChild(other);

        expect(click(other).defaultPrevented).toBe(false);
    });
});
