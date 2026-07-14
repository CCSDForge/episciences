// FilePond-based upload widget for attachments (paper comment threads, copy-editing replies,
// admin mail attachments). Every ".upload_widget .upload_input" file input still not turned into
// a FilePond instance is initialized and wired to the existing /file/upload and /file/delete
// endpoints (see FileController::uploadAction()/deleteAction()) — the server-side contract is
// untouched, only the client changes.
//
// Successfully uploaded files are tracked with a hidden "attachments[]" input per file, appended
// to the widget, so the final form submit (e.g. AdministratemailController, copy-editing reply)
// still lists them exactly like the previous jQuery File Upload widget did. Any "submit"/
// "submit-modal" button in the same form is disabled while a file is uploading or in error,
// exactly as the previous widget did.

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initUploadWidgets);
} else {
    initUploadWidgets();
}

function initUploadWidgets() {
    const csrfToken = getCsrfToken();

    document
        .querySelectorAll(
            '.upload_widget .upload_input:not([data-filepond-initialized])'
        )
        .forEach(input => createUploadWidget(input, csrfToken));
}

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

// The upload context (path type, docId, paperId, pcId) is read from the hidden
// "attachments_path_type_*" element rendered alongside the widget for comment/copy-editing
// forms (see Episciences_CommentsManager / Episciences_Submit), or falls back to the "docid"
// field used by plain submission forms. Scoped to the widget's own <form> so several widgets can
// coexist on the same page (e.g. several copy-editing reply threads) without sharing context.
function resolveUploadContext(widget) {
    const scope = widget.closest('form') || document;
    const pathTypeInput = scope.querySelector(
        'input[id^="attachments_path_type_"]'
    );

    if (pathTypeInput) {
        return {
            path: pathTypeInput.value || '',
            docId: pathTypeInput.getAttribute('docId') || 0,
            paperId: pathTypeInput.getAttribute('paperId') || 0,
            pcId: pathTypeInput.getAttribute('pcId') || 0,
        };
    }

    const docIdInput = scope.querySelector('#docid, input[name="docid"]');

    return {
        path: '',
        docId: docIdInput ? docIdInput.value : 0,
        paperId: 0,
        pcId: 0,
    };
}

function buildRequestBody(context, extra) {
    const formData = new FormData();
    Object.entries({ ...context, ...extra }).forEach(([key, value]) => {
        formData.append(key, value);
    });
    return formData;
}

function setSubmitButtonsDisabled(widget, disabled) {
    const form = widget.closest('form') || document;
    form.querySelectorAll('button[id^="submit"]').forEach(button => {
        button.disabled = disabled;
    });
}

function createUploadWidget(input, csrfToken) {
    input.setAttribute('data-filepond-initialized', 'true');

    const widget = input.closest('.upload_widget');
    const context = resolveUploadContext(widget);
    const maxFileSize = widget.dataset.maxFileSize;
    const acceptedFileTypes = widget.dataset.allowedMimeTypes
        ? widget.dataset.allowedMimeTypes.split(',')
        : undefined;

    const options = {
        allowMultiple: true,
        allowRevert: true,
        credits: false,
        maxFileSize: maxFileSize ? Number(maxFileSize) : undefined,
        acceptedFileTypes,
        server: {
            process: (
                fieldName,
                file,
                metadata,
                load,
                error,
                progress,
                abort
            ) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '/file/upload/');
                xhr.setRequestHeader('X-CSRF-Token', csrfToken);

                xhr.upload.onprogress = event => {
                    if (event.lengthComputable) {
                        progress(true, event.loaded, event.total);
                    }
                };

                xhr.onload = () => {
                    if (xhr.status < 200 || xhr.status >= 300) {
                        error(translate("Erreur lors de l'envoi du fichier."));
                        return;
                    }

                    let result;
                    try {
                        result = JSON.parse(xhr.responseText);
                    } catch (e) {
                        error(translate('Réponse du serveur invalide.'));
                        return;
                    }

                    if (result.status === 'error') {
                        const messages = result.messages
                            ? Object.values(result.messages)
                            : [translate('Erreur inconnue.')];
                        error(messages.join(' '));
                        return;
                    }

                    trackAttachedFile(widget, result.filename);
                    load(result.filename);
                };

                xhr.onerror = () => error(translate('Erreur réseau.'));

                const body = buildRequestBody(context, {});
                body.append('files[]', file, file.name);
                xhr.send(body);

                return {
                    abort: () => {
                        xhr.abort();
                        abort();
                    },
                };
            },

            revert: (filename, load, error) => {
                const body = buildRequestBody(context, { file: filename });

                fetch('/file/delete', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken },
                    body,
                })
                    .then(response => {
                        // fetch() only rejects on network failure, not on HTTP error status
                        // (403 CSRF failure, 500, ...) — check response.ok explicitly so a
                        // rejected deletion isn't reported to the user as a success.
                        if (!response.ok) {
                            throw new Error(
                                'delete request failed: ' + response.status
                            );
                        }
                        untrackAttachedFile(widget, filename);
                        load();
                    })
                    .catch(() =>
                        error(
                            translate(
                                'Erreur lors de la suppression du fichier.'
                            )
                        )
                    );
            },
        },
    };

    if (
        typeof locale !== 'undefined' &&
        locale === 'fr' &&
        window.FilePondLocaleFrFr
    ) {
        Object.assign(options, window.FilePondLocaleFrFr);
    }

    const pond = window.FilePond.create(input, options);

    // Track each file's pending/error state so submit buttons stay disabled while anything is
    // uploading, or while any file is in error — mirrors the previous widget's behaviour.
    const fileStates = new Map();

    const refreshSubmitButtonsState = () => {
        const busyOrError = Array.from(fileStates.values()).some(
            state => state === 'pending' || state === 'error'
        );
        setSubmitButtonsDisabled(widget, busyOrError);
    };

    pond.on('addfilestart', file => {
        fileStates.set(file.id, 'pending');
        refreshSubmitButtonsState();
    });

    pond.on('processfile', (error, file) => {
        fileStates.set(file.id, error ? 'error' : 'done');
        refreshSubmitButtonsState();
    });

    pond.on('removefile', (error, file) => {
        fileStates.delete(file.id);
        refreshSubmitButtonsState();
    });

    return pond;
}

function trackAttachedFile(widget, filename) {
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'attachments[]';
    hidden.className = 'upload_filename';
    hidden.value = filename;
    widget.appendChild(hidden);
}

function untrackAttachedFile(widget, filename) {
    const hidden = Array.from(
        widget.querySelectorAll('input.upload_filename')
    ).find(el => el.value === filename);
    if (hidden) {
        hidden.remove();
    }
}
