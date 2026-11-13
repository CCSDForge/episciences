'use strict';

/**
 * Review request type form (#319): the instructions are only used by the answer without an account, so they are
 * shown only while "Answer without an account" is checked. Hidden fields are still posted: the instructions are kept.
 * Without JavaScript, the fields stay visible.
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
 * @param {Document|HTMLElement} root
 * @returns {boolean} whether the form holds the checkbox and the instructions
 */
function initLightAnswerInstructions(root) {
    const checkbox = root.querySelector('#light_answer');
    const group = root.querySelector('#light-answer-instructions');

    if (!checkbox || !group) {
        return false;
    }

    syncLightAnswerInstructions(checkbox, group);
    checkbox.addEventListener('change', () =>
        syncLightAnswerInstructions(checkbox, group)
    );

    return true;
}

document.addEventListener('DOMContentLoaded', () =>
    initLightAnswerInstructions(document)
);

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initLightAnswerInstructions,
        syncLightAnswerInstructions,
    };
}
