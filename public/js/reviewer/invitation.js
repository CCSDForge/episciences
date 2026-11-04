/**
 * Reviewer invitation answer (reviewer/invitation_response_form.phtml).
 *
 * The accept and decline buttons open their form in place of the buttons; cancelling closes it and
 * gives the focus back to the button that opened it. A form sent back by the server (invalid, or
 * with a refused request token) is opened again on load.
 */
(function () {
    'use strict';

    const ACTIONS_ID = 'invitation-answer-actions';

    /**
     * @param {string} panelId
     * @returns {HTMLElement|null} the button opening the panel
     */
    function getOpener(panelId) {
        return document.querySelector('[aria-controls="' + panelId + '"]');
    }

    /**
     * The first invalid field, or else the heading of the panel.
     * @param {HTMLElement} panel
     */
    function focusPanel(panel) {
        const target =
            panel.querySelector('[aria-invalid="true"]') ||
            panel.querySelector('.panel-title[tabindex]');
        if (target) {
            target.focus();
        }
    }

    /**
     * @param {string} panelId
     */
    function openPanel(panelId) {
        const panel = document.getElementById(panelId);
        const actions = document.getElementById(ACTIONS_ID);
        if (!panel || !actions) {
            return;
        }

        actions.querySelectorAll('[aria-controls]').forEach(function (button) {
            const isOpener = button.getAttribute('aria-controls') === panelId;
            button.setAttribute('aria-expanded', String(isOpener));
            const other = document.getElementById(
                button.getAttribute('aria-controls')
            );
            if (!isOpener && other) {
                other.hidden = true;
            }
        });

        actions.hidden = true;
        panel.hidden = false;
        focusPanel(panel);
    }

    /**
     * @param {HTMLElement} panel
     */
    function closePanel(panel) {
        const actions = document.getElementById(ACTIONS_ID);
        panel.hidden = true;
        if (actions) {
            actions.hidden = false;
        }

        const opener = getOpener(panel.id);
        if (opener) {
            opener.setAttribute('aria-expanded', 'false');
            opener.focus();
        }
    }

    function init() {
        const actions = document.getElementById(ACTIONS_ID);
        if (!actions) {
            return;
        }

        const openers = Array.from(
            actions.querySelectorAll('button[aria-controls]')
        );
        openers.forEach(function (button) {
            button.addEventListener('click', function () {
                openPanel(button.getAttribute('aria-controls'));
            });
        });

        document
            .querySelectorAll('[data-answer-cancel]')
            .forEach(function (button) {
                button.addEventListener('click', function () {
                    const panel = openers
                        .map(opener =>
                            document.getElementById(
                                opener.getAttribute('aria-controls')
                            )
                        )
                        .find(
                            candidate => candidate && candidate.contains(button)
                        );
                    if (panel) {
                        closePanel(panel);
                    }
                });
            });

        if (actions.dataset.openOnLoad) {
            openPanel(actions.dataset.openOnLoad);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
