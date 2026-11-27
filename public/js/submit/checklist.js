/**
 * Submission checklist on the submission and new version forms.
 *
 * Every checklist checkbox carries the class js-submission-checklist (set by
 * Episciences\Submit\Checklist\ChecklistFormBuilder), whatever its ID: disclaimer1 / disclaimer2
 * (list of the code) or disclaimer_c_xxxxxx (journal or default list). Every checkbox is required.
 * Used by public/js/submit/functions.js, loaded before it.
 */

const SUBMISSION_CHECKLIST_SELECTOR = '.js-submission-checklist';

/**
 * Checkboxes of the checklist in root.
 *
 * @param {ParentNode} root
 * @returns {HTMLInputElement[]}
 */
function getChecklistBoxes(root = document) {
    return Array.from(root.querySelectorAll(SUBMISSION_CHECKLIST_SELECTOR));
}

/**
 * True when every checkbox of the checklist is checked (also true without checklist).
 *
 * @param {ParentNode} root
 * @returns {boolean}
 */
function areChecklistBoxesChecked(root = document) {
    return getChecklistBoxes(root).every(box => box.checked);
}

/**
 * Unchecks every checkbox of the checklist (new search: the author confirms again).
 *
 * @param {ParentNode} root
 */
function uncheckChecklistBoxes(root = document) {
    getChecklistBoxes(root).forEach(box => {
        box.checked = false;
    });
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        SUBMISSION_CHECKLIST_SELECTOR,
        areChecklistBoxesChecked,
        getChecklistBoxes,
        uncheckChecklistBoxes,
    };
}
