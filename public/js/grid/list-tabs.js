'use strict';

/**
 * Tabs of the review settings page (rating grids / review request types).
 * The server renders the active tab; without JavaScript, each tab is a link reloading the page on its tab.
 * With JavaScript, the tabs switch without reloading, follow the WAI-ARIA tabs pattern (arrow keys, Home, End)
 * and keep the active tab in the URL, so that a reload or a shared link opens the same tab.
 * Also keeps the title buttons of the collapsed volume grids in sync with their panels.
 */

/**
 * @param {HTMLElement} tablist
 * @returns {HTMLAnchorElement[]}
 */
function getTabs(tablist) {
    return Array.from(tablist.querySelectorAll('[role="tab"]'));
}

/**
 * Shows the panel of a tab and hides the others
 * @param {HTMLElement} tablist
 * @param {HTMLAnchorElement} selected
 * @param {Window|null} win window whose URL keeps the tab, null to leave the URL alone
 */
function activateTab(tablist, selected, win) {
    getTabs(tablist).forEach(tab => {
        const isSelected = tab === selected;
        const panel = document.getElementById(
            tab.getAttribute('aria-controls')
        );

        tab.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        tab.tabIndex = isSelected ? 0 : -1;
        tab.parentElement.classList.toggle('active', isSelected);

        if (panel) {
            panel.hidden = !isSelected;
            panel.classList.toggle('active', isSelected);
        }
    });

    if (win && win.history && typeof win.history.replaceState === 'function') {
        const url = new URL(win.location.href);
        url.searchParams.set('tab', selected.dataset.tab);
        url.hash = '';
        win.history.replaceState(null, '', url.toString());
    }

    // the DataTables of a panel shown after being hidden need their columns sized again
    tablist.dispatchEvent(
        new CustomEvent('grid-list:tab-shown', {
            bubbles: true,
            detail: { tab: selected.dataset.tab },
        })
    );
}

/**
 * Index of the tab reached by a navigation key, null for another key
 * @param {string} key
 * @param {number} current
 * @param {number} count
 * @returns {number|null}
 */
function targetIndex(key, current, count) {
    const moves = {
        ArrowRight: (current + 1) % count,
        ArrowLeft: (current - 1 + count) % count,
        Home: 0,
        End: count - 1,
    };

    return Object.prototype.hasOwnProperty.call(moves, key) ? moves[key] : null;
}

/**
 * @param {Document|HTMLElement} root
 * @param {Window|null} win
 * @returns {boolean} whether the page holds the tabs
 */
function initGridListTabs(root, win) {
    const tablist = root.querySelector('[data-grid-list-tabs]');

    if (!tablist) {
        return false;
    }

    tablist.addEventListener('click', event => {
        const tab = event.target.closest('[role="tab"]');
        if (!tab || !tablist.contains(tab)) {
            return;
        }
        event.preventDefault();
        activateTab(tablist, tab, win);
    });

    tablist.addEventListener('keydown', event => {
        const tabs = getTabs(tablist);
        const index = targetIndex(
            event.key,
            tabs.indexOf(document.activeElement),
            tabs.length
        );
        if (index === null) {
            return;
        }
        event.preventDefault();
        tabs[index].focus();
        activateTab(tablist, tabs[index], win);
    });

    return true;
}

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
    initGridListTabs(document, window);
    initGridToggles(document, callback => setTimeout(callback, 0));
});

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initGridListTabs,
        initGridToggles,
        activateTab,
        targetIndex,
    };
}
