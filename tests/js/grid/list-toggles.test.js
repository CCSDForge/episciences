'use strict';

const { initGridToggles } = require('../../../public/js/grid/list-toggles');

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
