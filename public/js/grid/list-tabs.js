'use strict';

/**
 * Tabs of the review settings page (rating grids / review request types).
 * The server renders the active tab; without JavaScript, each tab is a link reloading the page on its tab.
 * With JavaScript, the tabs switch without reloading, follow the WAI-ARIA tabs pattern (arrow keys, Home, End)
 * and keep the active tab in the URL, so that a reload or a shared link opens the same tab.
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

document.addEventListener('DOMContentLoaded', () =>
    initGridListTabs(document, window)
);

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { initGridListTabs, activateTab, targetIndex };
}
