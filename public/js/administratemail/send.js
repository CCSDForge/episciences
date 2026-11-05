/* global addRecipient, removeRecipient, epInitRecipientFields, updateModalButton, refreshPaperHistory, tinyMCE, modalStructureExists, createModalStructure, in_modal */

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

    if (in_modal) {
        updateModalButton('send_mail');

        $('#modal-box').on('hidden.bs.modal', function () {
            location.reload();
        });
    }

    setDefaultRecipient(sendForm);
    if (sendForm) {
        epInitRecipientFields(sendForm);
    }

    // refresh parent page on close modal
    $('button.close').on('click', function () {
        if ($jsDocId) {
            refreshPaperHistory($jsDocId);
        }
    });

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
    $('#modal-box').on('hide.bs.modal', function () {
        let editorContent = tinyMCE.activeEditor.getContent();
        const hasRecipients =
            sendForm !== null &&
            sendForm.querySelector(
                '.recipient-tags[data-recipient-target="cc"] .recipient-tag, .recipient-tags[data-recipient-target="bcc"] .recipient-tag'
            ) !== null;
        if (isdirty || hasRecipients || editorContent !== '') {
            if (!confirm('Are you sure, you want to close?')) return false;
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
