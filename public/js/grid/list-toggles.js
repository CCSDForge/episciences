'use strict';

/**
 * Review settings page: keeps the title buttons of the collapsed volume grids in sync with their panels.
 */

/**
 * Gives each grid title button the state of its panel body, which functions.js shows or hides
 * @param {Document|HTMLElement} root
 */
function syncGridToggles(root) {
    root.querySelectorAll('[data-grid-toggle]').forEach(toggle => {
        const body = document.getElementById(
            toggle.getAttribute('aria-controls')
        );
        const expanded = !!body && body.style.display !== 'none';
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    });
}

/**
 * Collapsed volume grids: their headings are toggled by functions.js (also when the URL targets a grid);
 * the state of the title buttons follows, and the tables of an opened grid are sized again.
 * @param {Document|HTMLElement} root
 * @param {function(Function): void} defer runs a callback once functions.js has toggled the panel
 * @returns {boolean} whether the page holds collapsed grids
 */
function initGridToggles(root, defer) {
    if (!root.querySelector('[data-grid-toggle]')) {
        return false;
    }

    root.addEventListener('click', event => {
        const heading = event.target.closest('.panel-heading');
        if (!heading || !heading.querySelector('[data-grid-toggle]')) {
            return;
        }
        defer(() => {
            syncGridToggles(root);
            heading.dispatchEvent(
                new CustomEvent('grid-list:panel-toggled', { bubbles: true })
            );
        });
    });
    defer(() => syncGridToggles(root));

    return true;
}

document.addEventListener('DOMContentLoaded', () => {
    initGridToggles(document, callback => setTimeout(callback, 0));
});

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initGridToggles,
    };
}
