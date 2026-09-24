/**
 * Change contributor modal functionality
 * Uses createUserAutocomplete from autocomplete-utils.js
 */
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('btn-change-contributor');
    const modal = document.getElementById('changeContributorModal');
    const btnConfirm = document.getElementById('confirmChangeContributor');

    if (!btn || !modal) return;

    // Open modal on button click
    btn.addEventListener('click', function() {
        resetModal();
        $('#changeContributorModal').modal('show');
    });

    // Initialize autocomplete and make modal draggable when it opens
    $(modal).on('shown.bs.modal', function() {
        // Make modal draggable by its header
        $(this).find('.modal-dialog').draggable({ handle: '.modal-header' });

        createUserAutocomplete({
            inputId: 'newContributorInput',
            selectedUserIdField: 'newContributorUid',
            selectButtonId: 'confirmChangeContributor'
        });
        document.getElementById('newContributorInput')?.focus();
    });

    // Handle confirm button click
    btnConfirm?.addEventListener('click', submitChangeContributor);

    /**
     * Reset modal to initial state
     * Clears input fields and disables confirm button
     */
    function resetModal() {
        const input = document.getElementById('newContributorInput');
        const hiddenUid = document.getElementById('newContributorUid');
        if (input) input.value = '';
        if (hiddenUid) hiddenUid.value = '0';
        if (btnConfirm) {
            btnConfirm.disabled = true;
        }
    }

    /**
     * Submit the change contributor form via AJAX
     * Sends POST request to /administratepaper/changecontributor with:
     * - docid: the paper document ID
     * - new_contributor_uid: UID of the new contributor
     * - add_as_coauthor: whether to add old contributor as co-author (1 or 0)
     */
    function submitChangeContributor() {
        const docId = document.getElementById('changeContributorDocId').value;
        const newUid = document.getElementById('newContributorUid').value;
        const addAsCoauthor = document.getElementById('addAsCoauthorCheckbox').checked;

        // Validate that a new contributor has been selected
        if (!newUid || newUid === '0') {
            alert(translate('Veuillez sélectionner un nouveau contributeur'));
            return;
        }

        // Disable button to prevent double submission
        btnConfirm.disabled = true;

        // Build form data
        const formData = new URLSearchParams();
        formData.append('docId', docId);
        formData.append('new_contributor_uid', newUid);
        formData.append('add_as_coauthor', addAsCoauthor ? '1' : '0');

        // Send POST request to backend
        fetch('/administratepaper/changecontributor', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData.toString()
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal and redirect to force GET request (avoids POST resubmission dialog)
                    $('#changeContributorModal').modal('hide');
                    window.location.href = window.location.pathname + window.location.search;
                } else {
                    // Show error message and re-enable button
                    alert(data.message || translate('Une erreur est survenue'));
                    btnConfirm.disabled = false;
                }
            })
            .catch(error => {
                // Handle network or parsing errors
                console.error('Error:', error);
                alert(translate('Une erreur est survenue'));
                btnConfirm.disabled = false;
            });
    }
});
