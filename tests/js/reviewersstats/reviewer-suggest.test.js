/**
 * @jest-environment jsdom
 */
const ReviewerSuggest = require('../../../public/js/reviewersstats/reviewer-suggest.js');

function renderForm() {
    document.body.innerHTML = `
        <form action="/reviewersstats">
            <div class="reviewer-suggest">
                <input type="search" id="search" name="search"
                       data-suggest-url="/reviewersstats/suggest"
                       data-label-overdue="Overdue"
                       data-label-none="No reviewer found"
                       data-label-one="1 suggestion"
                       data-label-many="%d suggestions">
                <ul id="reviewer-suggestions" role="listbox" hidden></ul>
                <div data-suggest-status></div>
            </div>
            <select name="period"><option value="6" selected>6</option></select>
            <input type="checkbox" name="only_my_responsibility" value="1" checked>
        </form>`;
    return document.getElementById('search');
}

const SUGGESTIONS = [
    {
        name: 'Jane Doe',
        email: 'jane@example.org',
        overdue: 2,
        url: '/reviewersstats/detail/email/jane',
    },
    {
        name: '<img src=x onerror=alert(1)>',
        email: '',
        overdue: 0,
        url: '/reviewersstats/detail/uid/7',
    },
];

function type(input, value) {
    input.value = value;
    input.dispatchEvent(new Event('input'));
}

function key(input, name) {
    const event = new KeyboardEvent('keydown', {
        key: name,
        cancelable: true,
        bubbles: true,
    });
    input.dispatchEvent(event);
    return event;
}

async function typeAndWait(input, value) {
    type(input, value);
    jest.advanceTimersByTime(ReviewerSuggest.DELAY_MS);
    // let the fetch/json promises resolve
    jest.useRealTimers();
    await new Promise(resolve => setTimeout(resolve, 0));
    jest.useFakeTimers();
}

describe('reviewer-suggest.js', () => {
    let input;
    let list;
    let status;
    let assign;

    beforeEach(() => {
        jest.useFakeTimers();
        input = renderForm();
        list = document.getElementById('reviewer-suggestions');
        status = document.querySelector('[data-suggest-status]');
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(SUGGESTIONS),
        });
        Element.prototype.scrollIntoView = jest.fn();
        assign = jest
            .spyOn(ReviewerSuggest, 'navigate')
            .mockImplementation(() => {});
        ReviewerSuggest.init(input);
    });

    afterEach(() => {
        assign.mockRestore();
        jest.useRealTimers();
        delete global.fetch;
    });

    test('turns the field into an ARIA combobox', () => {
        expect(input.getAttribute('role')).toBe('combobox');
        expect(input.getAttribute('aria-controls')).toBe(
            'reviewer-suggestions'
        );
        expect(input.getAttribute('aria-expanded')).toBe('false');
        expect(input.getAttribute('autocomplete')).toBe('off');
    });

    test('does not query below 2 characters', () => {
        type(input, 'j');
        jest.advanceTimersByTime(1000);

        expect(global.fetch).not.toHaveBeenCalled();
    });

    test('queries once after the delay, with the form period and scope filters', async () => {
        type(input, 'ja');
        type(input, 'jan');
        await typeAndWait(input, 'jane');

        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(global.fetch.mock.calls[0][0]).toBe(
            '/reviewersstats/suggest?q=jane&period=6&only_my_responsibility=1'
        );
    });

    test('renders options as text only and announces the count', async () => {
        await typeAndWait(input, 'jane');

        const options = list.querySelectorAll('[role="option"]');
        expect(list.hidden).toBe(false);
        expect(input.getAttribute('aria-expanded')).toBe('true');
        expect(options).toHaveLength(2);
        expect(options[0].textContent).toContain('Overdue');
        expect(options[0].textContent).toContain('jane@example.org');
        expect(list.querySelector('img')).toBeNull();
        expect(options[1].textContent).toBe('<img src=x onerror=alert(1)>');
        expect(status.textContent).toBe('2 suggestions');
    });

    test('arrow keys move the active option, Enter opens its detail page', async () => {
        await typeAndWait(input, 'jane');

        key(input, 'ArrowDown');
        key(input, 'ArrowDown');
        expect(input.getAttribute('aria-activedescendant')).toBe(
            'reviewer-suggestions-1'
        );
        key(input, 'ArrowDown');
        expect(input.getAttribute('aria-activedescendant')).toBe(
            'reviewer-suggestions-0'
        );
        expect(list.querySelector('[aria-selected="true"]').id).toBe(
            'reviewer-suggestions-0'
        );

        const enter = key(input, 'Enter');
        expect(enter.defaultPrevented).toBe(true);
        expect(assign).toHaveBeenCalledWith(
            '/reviewersstats/detail/email/jane'
        );
    });

    test('Enter without an active option submits the typed text (list filter)', async () => {
        await typeAndWait(input, 'jane');

        expect(key(input, 'Enter').defaultPrevented).toBe(false);
        expect(assign).not.toHaveBeenCalled();
    });

    test('a click on an option opens its detail page', async () => {
        await typeAndWait(input, 'jane');

        list.querySelectorAll('[role="option"]')[1].dispatchEvent(
            new MouseEvent('mousedown', { bubbles: true, cancelable: true })
        );

        expect(assign).toHaveBeenCalledWith('/reviewersstats/detail/uid/7');
    });

    test('Escape closes the list', async () => {
        await typeAndWait(input, 'jane');

        key(input, 'Escape');

        expect(list.hidden).toBe(true);
        expect(input.getAttribute('aria-expanded')).toBe('false');
    });

    test('no result: list stays closed, "no reviewer found" is announced', async () => {
        global.fetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve([]),
        });

        await typeAndWait(input, 'zzz');

        expect(list.hidden).toBe(true);
        expect(status.textContent).toBe('No reviewer found');
    });

    test('a failed request closes the list silently', async () => {
        global.fetch.mockResolvedValue({ ok: false, status: 500 });

        await typeAndWait(input, 'jane');

        expect(list.hidden).toBe(true);
    });
});
