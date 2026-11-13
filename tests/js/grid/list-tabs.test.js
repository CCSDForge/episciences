'use strict';

const {
    initGridListTabs,
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
