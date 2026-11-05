/* global addRecipient, removeRecipient, epInitRecipientFields, updateModalButton, refreshPaperHistory, tinyMCE, modalStructureExists, createModalStructure, in_modal, message, translate */

function initModal() {
    if (!modalStructureExists()) {
        createModalStructure();
    }
}

$(document).ready(function () {
    let $jsDocId = $('#docid').val();
    const sendForm = document.getElementById('send_form');

    // already in a modal, do not open another one
    initModal();

    // "send me a copy" checkbox
    $('#copy').on('click', function () {
        if (!window.sender || !window.sender.uid) {
            return;
        }
        window.__epContactsForm = sendForm;
        if ($('#copy').prop('checked')) {
            addRecipient('bcc', window.sender, 'known');
        } else {
            const tag = sendForm
                ? sendForm.querySelector(
                      '.recipient-tags[data-recipient-target="bcc"] .recipient-tag[data-uid="' +
                          String(window.sender.uid) +
                          '"]'
                  )
                : null;
            removeRecipient(tag);
        }
        delete window.__epContactsForm;
    });

    // The script runs again each time the modal is opened: its handlers replace the former ones
    let isSent = false;
    if (in_modal) {
        updateModalButton('send_mail');

        $('#modal-box')
            .off('hidden.bs.modal.epSendMail')
            .on('hidden.bs.modal.epSendMail', function () {
                // The next opening creates the editor again, on a new textarea with the same id: the former
                // editor must be gone, or the new one loses its formatting
                this.querySelectorAll('textarea').forEach(textarea => {
                    const editor = textarea.id
                        ? tinyMCE.get(textarea.id)
                        : null;
                    if (editor) {
                        editor.remove();
                    }
                });
                if (
                    isSent &&
                    $jsDocId &&
                    typeof refreshPaperHistory === 'function'
                ) {
                    refreshPaperHistory($jsDocId);
                }
                // #modal-box is shared with the other modals of the page: they must not run these handlers
                $('#modal-box')
                    .off('hide.bs.modal.epSendMail')
                    .off('hidden.bs.modal.epSendMail');
            });

        // Sent: the modal closes without asking, the confirmation is a toast
        if (sendForm) {
            sendForm.addEventListener('ep:mail-sent', event => {
                isSent = true;
                $('#modal-box').modal('hide');
                message(event.detail.message, 'alert-success');
            });
        }
    }

    setDefaultRecipient(sendForm);
    if (sendForm) {
        epInitRecipientFields(sendForm);
    }

    if (tinyMCE.activeEditor !== null) {
        tinyMCE.activeEditor.on('keyup', function () {
            let msgContent = this.getContent();
            setWithExpiry('mailContent' + $jsDocId, msgContent, 7200000);
        });

        tinyMCE.activeEditor.on('init', function () {
            if (getWithExpiry('mailContent' + $jsDocId) !== null) {
                tinyMCE.activeEditor.setContent(
                    getWithExpiry('mailContent' + $jsDocId)
                );
            }
        });
    }
    let isdirty = 0;
    $('#send_form').on('change input', function () {
        isdirty = 1;
    });
    $('#modal-box')
        .off('hide.bs.modal.epSendMail')
        .on('hide.bs.modal.epSendMail', function () {
            // Another content may be shown in the shared modal: only the mail form asks for a confirmation
            if (isSent || sendForm === null || !sendForm.isConnected) {
                return;
            }
            const editor = tinyMCE.activeEditor;
            const editorContent = editor ? editor.getContent() : '';
            const hasRecipients =
                sendForm.querySelector(
                    '.recipient-tags[data-recipient-target="cc"] .recipient-tag, .recipient-tags[data-recipient-target="bcc"] .recipient-tag'
                ) !== null;
            if (isdirty || hasRecipients || editorContent !== '') {
                if (!confirm(translate('Fermer sans envoyer ce message ?'))) {
                    return false;
                }
            }
        });
});

function setDefaultRecipient(sendForm) {
    if (typeof recipient !== 'undefined' && recipient) {
        window.__epContactsForm = sendForm;
        addRecipient('to', recipient, 'known', { silent: true });
        delete window.__epContactsForm;
    }
}

function setWithExpiry(key, value, ttl) {
    const now = new Date();

    // `item` is an object which contains the original value
    // as well as the time when it's supposed to expire
    const item = {
        value: value,
        expiry: now.getTime() + ttl,
    };
    localStorage.setItem(key, JSON.stringify(item));
}
function getWithExpiry(key) {
    const itemStr = localStorage.getItem(key);
    // if the item doesn't exist, return null
    if (!itemStr) {
        return null;
    }
    const item = JSON.parse(itemStr);
    const now = new Date();
    // compare the expiry time of the item with the current time
    if (now.getTime() > item.expiry) {
        // If the item is expired, delete the item from storage
        // and return null
        localStorage.removeItem(key);
        return null;
    }
    return item.value;
}
