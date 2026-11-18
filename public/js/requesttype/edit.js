'use strict';

/**
 * Review request type form (#319): the instructions are only used by the answer without an account, so they are
 * shown only while "Answer without an account" is checked. Hidden fields are still posted: the instructions are kept.
 * An answer without an account is not a rating report: "count as review reports" is disabled while it is checked
 * (the server ignores it too). Without JavaScript, the fields stay visible and enabled.
 */

/**
 * Shows or hides the instructions according to the checkbox
 * @param {HTMLInputElement} checkbox
 * @param {HTMLElement} group
 */
function syncLightAnswerInstructions(checkbox, group) {
    group.hidden = !checkbox.checked;
    checkbox.setAttribute('aria-expanded', checkbox.checked ? 'true' : 'false');
}

/**
 * Disables "count as review reports" while the answer without an account is checked
 * @param {HTMLInputElement} checkbox the answer without an account
 * @param {HTMLInputElement|null} countsAsReport
 */
function syncCountsAsReport(checkbox, countsAsReport) {
    if (countsAsReport) {
        countsAsReport.disabled = checkbox.checked;
    }
}

/**
 * @param {Document|HTMLElement} root
 * @returns {boolean} whether the form holds the checkbox and the instructions
 */
function initLightAnswerInstructions(root) {
    const checkbox = root.querySelector('#light_answer');
    const group = root.querySelector('#light-answer-instructions');

    if (!checkbox || !group) {
        return false;
    }

    const countsAsReport = root.querySelector('#counts_as_report');
    const sync = () => {
        syncLightAnswerInstructions(checkbox, group);
        syncCountsAsReport(checkbox, countsAsReport);
    };

    sync();
    checkbox.addEventListener('change', sync);

    return true;
}

document.addEventListener('DOMContentLoaded', () =>
    initLightAnswerInstructions(document)
);

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initLightAnswerInstructions,
        syncLightAnswerInstructions,
        syncCountsAsReport,
    };
}
