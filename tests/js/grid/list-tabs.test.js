'use strict';

const {
    initGridListTabs,
    initGridToggles,
    targetIndex,
} = require('../../../public/js/grid/list-tabs');

describe('review settings tabs (grids / request types)', () => {
    const render = () => {
        document.body.innerHTML = `
            <ul role="tablist" data-grid-list-tabs>
                <li class="active"><a href="/grid/list?tab=grids" id="tab-grids" role="tab" data-tab="grids"
                       aria-controls="tabpanel-grids" aria-selected="true" tabindex="0">Grids</a></li>
                <li><a href="/grid/list?tab=types" id="tab-types" role="tab" data-tab="types"
                       aria-controls="tabpanel-types" aria-selected="false" tabindex="-1">Types</a></li>
            </ul>
            <div role="tabpanel" id="tabpanel-grids" class="tab-pane active">grids</div>
            <div role="tabpanel" id="tabpanel-types" class="tab-pane" hidden>types</div>`;
        return {
            grids: document.getElementById('tab-grids'),
            types: document.getElementById('tab-types'),
            gridsPanel: document.getElementById('tabpanel-grids'),
            typesPanel: document.getElementById('tabpanel-types'),
        };
    };

    const fakeWindow = () => ({
        location: {
            href: 'https://journal.example.org/grid/list#rating-grid-0',
        },
        history: { replaceState: jest.fn() },
    });

    test('nothing happens on a page without the tabs', () => {
        document.body.innerHTML = '<p>no tabs</p>';

        expect(initGridListTabs(document, null)).toBe(false);
    });

    test('clicking a tab shows its panel without reloading and keeps the tab in the URL', () => {
        const { types, grids, gridsPanel, typesPanel } = render();
        const win = fakeWindow();
        initGridListTabs(document, win);

        const click = new MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        });
        types.dispatchEvent(click);

        expect(click.defaultPrevented).toBe(true);
        expect(typesPanel.hidden).toBe(false);
        expect(gridsPanel.hidden).toBe(true);
        expect(types.getAttribute('aria-selected')).toBe('true');
        expect(grids.getAttribute('aria-selected')).toBe('false');
        expect(types.tabIndex).toBe(0);
        expect(grids.tabIndex).toBe(-1);
        expect(types.parentElement.classList.contains('active')).toBe(true);
        expect(win.history.replaceState).toHaveBeenCalledWith(
            null,
            '',
            'https://journal.example.org/grid/list?tab=types'
        );
    });

    test('the arrow keys move to the next tab and show it', () => {
        const { grids, types, typesPanel } = render();
        initGridListTabs(document, null);
        grids.focus();

        grids.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true })
        );

        expect(document.activeElement).toBe(types);
        expect(typesPanel.hidden).toBe(false);
    });

    test('showing a tab tells the page, so that its tables are sized again', () => {
        const { types } = render();
        initGridListTabs(document, null);
        const listener = jest.fn();
        document.addEventListener('grid-list:tab-shown', listener);

        types.click();

        expect(listener).toHaveBeenCalledTimes(1);
        expect(listener.mock.calls[0][0].detail).toEqual({ tab: 'types' });
        document.removeEventListener('grid-list:tab-shown', listener);
    });

    test.each([
        ['ArrowRight', 1, 0],
        ['ArrowLeft', 0, 1],
        ['Home', 1, 0],
        ['End', 0, 1],
        ['Enter', 0, null],
    ])('%s from tab %i reaches tab %p', (key, current, expected) => {
        expect(targetIndex(key, current, 2)).toBe(expected);
    });
});

describe('collapsed volume grids', () => {
    const render = () => {
        document.body.innerHTML = `
            <div class="panel collapsable" id="rating-grid-12">
                <div class="panel-heading"><h3 class="panel-title">
                    <button type="button" data-grid-toggle aria-expanded="false"
                            aria-controls="rating-grid-body-12">Volume 12</button>
                </h3></div>
                <div class="panel-body" id="rating-grid-body-12" style="display: none">criteria</div>
            </div>`;
        return {
            heading: document.querySelector('.panel-heading'),
            toggle: document.querySelector('[data-grid-toggle]'),
            body: document.getElementById('rating-grid-body-12'),
        };
    };

    // runs the deferred callbacks on demand, as setTimeout would once functions.js has toggled the panel
    const deferred = () => {
        const callbacks = [];
        const defer = callback => callbacks.push(callback);
        defer.flush = () => callbacks.splice(0).forEach(callback => callback());
        return defer;
    };

    test('nothing happens on a page without collapsed grids', () => {
        document.body.innerHTML = '<p>no grid</p>';

        expect(initGridToggles(document, deferred())).toBe(false);
    });

    test('a grid opened from the URL marks its title button as expanded', () => {
        const { toggle, body } = render();
        const defer = deferred();
        initGridToggles(document, defer);

        body.style.display = ''; // functions.js opens the panel targeted by the URL
        defer.flush();

        expect(toggle.getAttribute('aria-expanded')).toBe('true');
    });

    test('opening then closing a grid updates its title button and tells the page', () => {
        const { heading, toggle, body } = render();
        const defer = deferred();
        initGridToggles(document, defer);
        defer.flush();
        const listener = jest.fn();
        document.addEventListener('grid-list:panel-toggled', listener);

        toggle.click();
        body.style.display = ''; // functions.js toggles the body on a click on the heading
        defer.flush();
        expect(toggle.getAttribute('aria-expanded')).toBe('true');

        heading.click();
        body.style.display = 'none';
        defer.flush();
        expect(toggle.getAttribute('aria-expanded')).toBe('false');

        expect(listener).toHaveBeenCalledTimes(2);
        document.removeEventListener('grid-list:panel-toggled', listener);
    });
});
