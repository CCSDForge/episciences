/**
 * Submission checklist editor (/submissionchecklist): "+" adds a checkbox block, "Delete" removes
 * one. Nothing is saved before the form is submitted.
 *
 * The form (.submission-checklist-editor) holds the blocks (.submission-checklist-row) and a
 * <template> whose placeholders __INDEX__ (field names) and __POSITION__ (legend, ids) are
 * replaced here. data-max-items: limit of blocks; data-legend / data-remove-label: translated
 * texts with __POSITION__, used to renumber the blocks after a deletion.
 * Unsaved changes (typing, "+", "Delete", or a list refused with errors: data-unsaved) make the
 * browser ask before leaving the page; "Save" and "Cancel" leave without asking.
 * Vanilla JS, no dependency.
 */

const SUBMISSION_CHECKLIST_ROW = '.submission-checklist-row';
const SUBMISSION_CHECKLIST_ID = /^submission-checklist-[^-]+-/;

/** Unsaved changes: typed text, added or removed block, list refused with errors */
function markChecklistUnsaved(form) {
    form.dataset.unsaved = '1';
}

function hasUnsavedChecklist(root = document) {
    return Array.from(
        root.querySelectorAll('.submission-checklist-editor')
    ).some(form => form.dataset.unsaved === '1');
}

/**
 * beforeunload: the browser shows its own "Leave page?" dialog (its text cannot be changed).
 */
function onChecklistBeforeUnload(event, root = document) {
    if (!hasUnsavedChecklist(root)) {
        return;
    }
    event.preventDefault();
    // Older browsers need returnValue set
    event.returnValue = '';
}

function getChecklistRows(form) {
    return Array.from(
        form.querySelectorAll(
            `.submission-checklist-rows > ${SUBMISSION_CHECKLIST_ROW}`
        )
    );
}

function canAddChecklistRow(form) {
    const max = parseInt(form.dataset.maxItems || '0', 10);
    return getChecklistRows(form).length < max;
}

/**
 * Next free index of the field names (items[i][...]): an index is never reused, so two blocks
 * never share their fields.
 */
function nextChecklistIndex(form) {
    let next = 0;
    form.querySelectorAll('input[name$="[key]"]').forEach(input => {
        const match = /^items\[(\d+)\]/.exec(input.name);
        if (match) {
            next = Math.max(next, parseInt(match[1], 10) + 1);
        }
    });
    return next;
}

/**
 * "+" disabled at the limit; "Delete" disabled when only one block is left (a list needs one).
 */
function updateChecklistButtons(form) {
    const addButton = form.querySelector('.js-submission-checklist-add');
    if (addButton) {
        addButton.disabled = !canAddChecklistRow(form);
    }

    const rows = getChecklistRows(form);
    rows.forEach(row => {
        const removeButton = row.querySelector(
            '.js-submission-checklist-remove'
        );
        if (removeButton) {
            removeButton.hidden = false;
            removeButton.disabled = rows.length <= 1;
        }
    });
}

/**
 * Position-dependent texts and ids of one block: legend, ids of the fields and their labels,
 * aria references, label of the "Delete" button.
 */
function renumberChecklistRow(form, row, position) {
    const newPrefix = `submission-checklist-${position}-`;
    const legend = row.querySelector('legend');
    if (legend && form.dataset.legend) {
        legend.textContent = form.dataset.legend.replace(
            '__POSITION__',
            position
        );
    }

    row.querySelectorAll('[id], [for], [aria-describedby]').forEach(element => {
        ['id', 'for', 'aria-describedby'].forEach(attribute => {
            const value = element.getAttribute(attribute);
            if (value) {
                element.setAttribute(
                    attribute,
                    value.replace(SUBMISSION_CHECKLIST_ID, newPrefix)
                );
            }
        });
    });

    const removeButton = row.querySelector('.js-submission-checklist-remove');
    if (removeButton && form.dataset.removeLabel) {
        removeButton.setAttribute(
            'aria-label',
            form.dataset.removeLabel.replace('__POSITION__', position)
        );
    }
}

function renumberChecklistRows(form) {
    getChecklistRows(form).forEach((row, index) =>
        renumberChecklistRow(form, row, index + 1)
    );
}

/**
 * Adds an empty block at the end and focuses its English text.
 * @returns {HTMLElement|null} the new block, null at the limit
 */
function addChecklistRow(form) {
    const template = form.querySelector('.submission-checklist-row-template');
    const container = form.querySelector('.submission-checklist-rows');
    if (!template || !container || !canAddChecklistRow(form)) {
        return null;
    }

    // Placeholders are numbers: no user text goes through innerHTML
    const html = template.innerHTML
        .replace(/__INDEX__/g, String(nextChecklistIndex(form)))
        .replace(/__POSITION__/g, String(getChecklistRows(form).length + 1));
    container.insertAdjacentHTML('beforeend', html);

    const row = container.lastElementChild;
    markChecklistUnsaved(form);
    updateChecklistButtons(form);
    row.querySelector('textarea')?.focus();
    return row;
}

/**
 * Removes a block (not the last one), renumbers the others and moves the focus to the "+" button.
 * @returns {boolean} whether the block was removed
 */
function removeChecklistRow(form, row) {
    if (getChecklistRows(form).length <= 1 || !row) {
        return false;
    }

    row.remove();
    markChecklistUnsaved(form);
    renumberChecklistRows(form);
    updateChecklistButtons(form);
    form.querySelector('.js-submission-checklist-add')?.focus();
    return true;
}

/** Pending removal of each confirmation dialog: what to do on "Delete", where to put the focus back */
const pendingChecklistRemovals = new WeakMap();

function checklistRowHasText(row) {
    return Array.from(row.querySelectorAll('textarea')).some(
        textarea => textarea.value.trim() !== ''
    );
}

/**
 * Asks before removing a block; calls onConfirm when the manager confirms.
 * Uses the page dialog (blue "Delete" button), or window.confirm() without <dialog> support.
 */
function askChecklistRowRemoval(form, row, onConfirm, returnFocus = null) {
    const position = getChecklistRows(form).indexOf(row) + 1;
    const message = (form.dataset.removeConfirm || '').replace(
        '__POSITION__',
        String(position)
    );
    const dialog = document.getElementById(form.dataset.confirmDialog || '');

    if (!dialog || typeof dialog.showModal !== 'function') {
        if (window.confirm(message)) {
            onConfirm();
        }
        return;
    }

    dialog.querySelector('.submission-checklist-confirm-message').textContent =
        message;
    pendingChecklistRemovals.set(dialog, { onConfirm, returnFocus });
    dialog.returnValue = '';
    dialog.showModal();
    // Safer default: Enter on the focused button cancels
    dialog.querySelector('.js-submission-checklist-confirm-cancel')?.focus();
}

/**
 * Buttons of the confirmation dialog, bound once. Closing it (buttons, Escape) runs the removal
 * only after "Delete"; otherwise the focus goes back to the "Delete" button of the block.
 */
function initChecklistConfirmDialog(dialog) {
    if (!dialog || dialog.dataset.checklistConfirmReady) {
        return;
    }
    dialog.dataset.checklistConfirmReady = '1';

    dialog
        .querySelector('.js-submission-checklist-confirm-ok')
        ?.addEventListener('click', () => dialog.close('confirm'));
    dialog
        .querySelector('.js-submission-checklist-confirm-cancel')
        ?.addEventListener('click', () => dialog.close('cancel'));

    dialog.addEventListener('close', () => {
        const pending = pendingChecklistRemovals.get(dialog);
        pendingChecklistRemovals.delete(dialog);
        if (!pending) {
            return;
        }
        if (dialog.returnValue === 'confirm') {
            pending.onConfirm();
        } else {
            pending.returnFocus?.focus();
        }
    });
}

/**
 * Click on a "Delete" button: a block holding text is removed after confirmation, an empty block
 * (e.g. added by mistake with "+") at once.
 */
function onChecklistRemoveClick(form, removeButton) {
    const row = removeButton.closest(SUBMISSION_CHECKLIST_ROW);
    if (!row) {
        return;
    }

    const remove = () => removeChecklistRow(form, row);
    if (!checklistRowHasText(row) || !form.dataset.removeConfirm) {
        remove();
        return;
    }
    askChecklistRowRemoval(form, row, remove, removeButton);
}

/**
 * Typing marks the list unsaved; "Save" (submit) and "Cancel" leave the page without warning:
 * the manager chose to save, or to drop the changes.
 */
function bindChecklistUnsavedState(form) {
    const clear = () => delete form.dataset.unsaved;
    form.addEventListener('input', () => markChecklistUnsaved(form));
    form.addEventListener('submit', clear);
    form.querySelector('.js-submission-checklist-cancel')?.addEventListener(
        'click',
        clear
    );
}

function initChecklistEditor(root = document) {
    root.querySelectorAll('.submission-checklist-editor').forEach(form => {
        // Script loaded twice: bind the buttons once only
        if (form.dataset.checklistEditorReady) {
            return;
        }
        form.dataset.checklistEditorReady = '1';

        const addButton = form.querySelector('.js-submission-checklist-add');
        if (addButton) {
            addButton.hidden = false;
            addButton.addEventListener('click', () => addChecklistRow(form));
        }

        // One listener for the saved blocks and the added ones
        form.addEventListener('click', event => {
            const removeButton = event.target.closest(
                '.js-submission-checklist-remove'
            );
            if (removeButton) {
                onChecklistRemoveClick(form, removeButton);
            }
        });

        bindChecklistUnsavedState(form);
        initChecklistConfirmDialog(
            document.getElementById(form.dataset.confirmDialog || '')
        );
        updateChecklistButtons(form);
    });

    // One listener for the page, whatever the number of editors
    if (!window.checklistBeforeUnloadReady) {
        window.checklistBeforeUnloadReady = true;
        window.addEventListener('beforeunload', event =>
            onChecklistBeforeUnload(event, root)
        );
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        addChecklistRow,
        canAddChecklistRow,
        askChecklistRowRemoval,
        hasUnsavedChecklist,
        initChecklistEditor,
        nextChecklistIndex,
        onChecklistBeforeUnload,
        removeChecklistRow,
    };
} else {
    document.addEventListener('DOMContentLoaded', () => initChecklistEditor());
}
