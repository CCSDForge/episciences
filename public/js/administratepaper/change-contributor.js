/**
 * Change contributor modal functionality
 * Uses createUserAutocomplete from autocomplete-utils.js
 */
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btn-change-contributor');
    const modal = document.getElementById('changeContributorModal');
    const btnConfirm = document.getElementById('confirmChangeContributor');

    if (!btn || !modal) return;

    /**
     * Show a modal
     * @param {HTMLElement} modalElement - The modal element
     * @param {Function} onShown - Callback executed after modal is shown
     */
    function showModal(modalElement, onShown) {
        modalElement.style.display = 'block';
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade in';
        backdrop.id = 'modal-backdrop';
        document.body.appendChild(backdrop);

        showTimer = setTimeout(() => {
            showTimer = null;
            modalElement.classList.add('in');
            document.body.classList.add('modal-open');
            if (onShown) onShown();
        }, 10);
    }

    let autocompleteInitialized = false;
    let cleanupDraggable = null;
    let showTimer = null;

    /**
     * Close the modal when the Escape key is pressed
     * @param {KeyboardEvent} e
     */
    function onKeydown(e) {
        if (e.key === 'Escape') hideModal(modal);
    }

    /**
     * Hide a modal
     * Also releases the listeners bound while the modal was open
     * @param {HTMLElement} modalElement - The modal element
     */
    function hideModal(modalElement) {
        if (showTimer !== null) {
            clearTimeout(showTimer);
            showTimer = null;
        }
        modalElement.style.display = 'none';
        modalElement.classList.remove('in');
        document.body.classList.remove('modal-open');
        const backdrop = document.getElementById('modal-backdrop');
        if (backdrop) backdrop.remove();

        document.removeEventListener('keydown', onKeydown);
        if (cleanupDraggable) {
            cleanupDraggable();
            cleanupDraggable = null;
        }

        // Reopen centered: drop the position set by dragging
        modalElement.querySelector('.modal-dialog')?.removeAttribute('style');

        // Give the focus back to the trigger (WCAG 2.4.3)
        btn.focus();
    }

    /**
     * Make an element draggable by a handle
     * @param {HTMLElement} element - The element to make draggable
     * @param {string} handleSelector - CSS selector for the drag handle
     * @returns {Function} Cleanup function removing the bound listeners
     */
    function makeDraggable(element, handleSelector) {
        const headerEl = element.querySelector(handleSelector);
        if (!headerEl) return () => {};

        let offsetX = 0,
            offsetY = 0,
            isDragging = false;
        headerEl.style.cursor = 'move';
        element.style.position = 'relative';

        const onMouseDown = e => {
            isDragging = true;
            const rect = element.getBoundingClientRect();
            offsetX = e.clientX - rect.left;
            offsetY = e.clientY - rect.top;
            e.preventDefault();
        };

        const onMouseMove = e => {
            if (!isDragging) return;
            element.style.left = e.clientX - offsetX + 'px';
            element.style.top = e.clientY - offsetY + 'px';
            element.style.margin = '0';
        };

        const onMouseUp = () => {
            isDragging = false;
        };

        headerEl.addEventListener('mousedown', onMouseDown);
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);

        return () => {
            headerEl.removeEventListener('mousedown', onMouseDown);
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        };
    }

    /**
     * Reset modal to initial state
     * Clears input fields and disables confirm button
     */
    function resetModal() {
        const input = document.getElementById('newContributorInput');
        const hiddenUid = document.getElementById('newContributorUid');
        document.getElementById('addAsCoauthorCheckbox').checked = true;
        if (input) input.value = '';
        if (hiddenUid) hiddenUid.value = '0';
        if (btnConfirm) {
            btnConfirm.disabled = true;
        }
    }

    // Open modal on button click
    btn.addEventListener('click', function () {
        resetModal();
        showModal(modal, function () {
            // Make modal draggable by its header
            cleanupDraggable = makeDraggable(
                modal.querySelector('.modal-dialog'),
                '.modal-header'
            );

            // The autocomplete binds its own listeners: create it only once
            if (!autocompleteInitialized) {
                createUserAutocomplete({
                    inputId: 'newContributorInput',
                    selectedUserIdField: 'newContributorUid',
                    selectButtonId: 'confirmChangeContributor',
                });
                autocompleteInitialized = true;
            }

            document.addEventListener('keydown', onKeydown);
            document.getElementById('newContributorInput')?.focus();
        });
    });

    // Handle close buttons (X and Cancel)
    modal.querySelectorAll('[data-dismiss="modal"]').forEach(function (closeBtn) {
        closeBtn.addEventListener('click', function () {
            hideModal(modal);
        });
    });

    // Close on backdrop click (the .modal wrapper itself, not its content)
    modal.addEventListener('click', function (e) {
        if (e.target === modal) hideModal(modal);
    });

    // Handle confirm button click
    btnConfirm?.addEventListener('click', submitChangeContributor);

    /**
     * Submit the change contributor form via AJAX
     * Sends POST request to /administratepaper/changecontributor with:
     * - docid: the paper document ID
     * - new_contributor_uid: UID of the new contributor
     * - add_as_coauthor: whether to add old contributor as co-author (1 or 0)
     * - csrf_token: session CSRF token for security
     */
    function submitChangeContributor() {
        const docId = document.getElementById('changeContributorDocId').value;
        const newUid = document.getElementById('newContributorUid').value;
        const addAsCoauthor = document.getElementById(
            'addAsCoauthorCheckbox'
        ).checked;

        // Validate that a new contributor has been selected
        if (!newUid || newUid === '0') {
            alert(translate('Veuillez sélectionner un nouveau contributeur'));
            return;
        }

        // Disable button to prevent double submission
        btnConfirm.disabled = true;

        // Get CSRF token from meta tag
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.content : '';

        // Build form data
        const formData = new URLSearchParams();
        formData.append('docId', docId);
        formData.append('new_contributor_uid', newUid);
        formData.append('add_as_coauthor', addAsCoauthor ? '1' : '0');
        formData.append('csrf_token', csrfToken);

        // Send POST request to backend
        fetch('/administratepaper/changecontributor', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData.toString(),
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal and redirect to force GET request (avoids POST resubmission dialog)
                    hideModal(modal);
                    window.location.href =
                        window.location.pathname + window.location.search;
                } else {
                    // Show error message and re-enable button
                    alert(
                        data.error || translate('Une erreur est survenue.')
                    );
                    btnConfirm.disabled = false;
                }
            })
            .catch(error => {
                // Handle network or parsing errors
                console.error('Error:', error);
                alert(translate('Une erreur est survenue.'));
                btnConfirm.disabled = false;
            });
    }
});
