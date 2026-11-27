// Message to display once the page has been reloaded after a successful update
const UPDATE_METADATA_PENDING_MESSAGE = 'episciences.updateMetaData.message';
// Its alert class: the record may be updated while some of its data could not be stored
const UPDATE_METADATA_PENDING_LEVEL = 'episciences.updateMetaData.level';

function showUpdateMetaDataError() {
    message(
        translate(
            'Une erreur est survenue lors de la mise à jour des métadonnées.'
        ),
        'alert-danger'
    );
}

async function updateMetaData(button, docId) {
    const recordLoading = document.getElementById('record-loading');
    recordLoading.innerHTML = getLoader();
    recordLoading.style.display = 'block';

    // Remove all event listeners from the button
    const newButton = button.cloneNode(true);
    button.parentNode.replaceChild(newButton, button);

    try {
        const formData = new URLSearchParams();
        formData.append('docid', docId);

        const response = await fetch('/paper/updaterecorddata', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        const result = await response.text();

        let obj_result;
        try {
            obj_result = JSON.parse(result);
        } catch (error) {
            // e.g. access denied or expired session (empty body or HTML page)
            console.error('Unexpected response:', response.status, error);
            showUpdateMetaDataError();
            return;
        }

        if ('error' in obj_result) {
            message(obj_result.message, 'alert-danger');
        } else if (obj_result.affectedRows !== 0) {
            // Reload to display the updated metadata, then show the message
            sessionStorage.setItem(
                UPDATE_METADATA_PENDING_MESSAGE,
                obj_result.message
            );
            sessionStorage.setItem(
                UPDATE_METADATA_PENDING_LEVEL,
                obj_result.warning ? 'alert-warning' : 'alert-success'
            );
            location.reload();
        } else if (obj_result.warning) {
            // Nothing changed, and some data could not be stored
            message(obj_result.message, 'alert-warning');
        } else {
            // Metadata already up to date, display info toast without reloading
            message(obj_result.message, 'alert-info');
        }
    } catch (error) {
        console.error('Fetch error:', error);
        showUpdateMetaDataError();
    } finally {
        recordLoading.style.display = 'none';
    }
}

function showPendingUpdateMetaDataMessage() {
    const pendingMessage = sessionStorage.getItem(
        UPDATE_METADATA_PENDING_MESSAGE
    );
    if (pendingMessage !== null) {
        const pendingLevel =
            sessionStorage.getItem(UPDATE_METADATA_PENDING_LEVEL) ??
            'alert-success';
        sessionStorage.removeItem(UPDATE_METADATA_PENDING_MESSAGE);
        sessionStorage.removeItem(UPDATE_METADATA_PENDING_LEVEL);
        message(pendingMessage, pendingLevel);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        showPendingUpdateMetaDataMessage
    );
} else {
    showPendingUpdateMetaDataMessage();
}
