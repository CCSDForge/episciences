var $modal_box;
var in_modal;

/**
 * Paper status modals: render initial CC/BCC tags from hidden_* JSON set server-side.
 */
function initPaperModalRecipientTagsFromHidden() {
    document.querySelectorAll('form[id]').forEach(form => {
        if (!form.id) {
            return;
        }
        ['cc', 'bcc'].forEach(target => {
            const span = form.querySelector(
                '#' + form.id + '-' + target + '-tags'
            );
            if (!span || !span.id) {
                return;
            }
            if (span.querySelector('.recipient-tag')) {
                return;
            }
            const hid = form.querySelector('#' + form.id + '-hidden_' + target);
            if (!hid || !hid.value) {
                return;
            }
            let arr;
            try {
                arr = JSON.parse(hid.value);
            } catch (e) {
                return;
            }
            if (!Array.isArray(arr) || !arr.length) {
                return;
            }
            hid.value = '[]';
            window.__epContactsForm = form;
            arr.forEach(r => {
                if (r && r.value) {
                    addRecipient(target, r.value, 'else');
                }
            });
            delete window.__epContactsForm;
        });
    });
}

$(function () {
    initPaperModalRecipientTagsFromHidden();
    $('a#modal-contributor').click(function () {
        waitForElm('input#coAuthorsInfo').then(elm => {
            $('input#coAuthorsInfo').each(function () {
                addRecipient('cc', JSON.parse(this.value), 'known');
            });
        });
    });
});

/**
 * Find CC/BCC text input in paper modals: name "cc", Zend subform "askEditors[cc]",
 * or id "{formId}-cc" (e.g. ask-other-editors-form-cc).
 */
function epFindRecipientTextInput($scope, formEl, target) {
    const fid = formEl && formEl.id ? formEl.id : '';
    if (fid) {
        const byId = document.getElementById(fid + '-' + target);
        if (byId && byId.tagName && byId.tagName.toLowerCase() === 'input') {
            return $(byId);
        }
    }
    let $i = $scope.find('input[name="' + target + '"]');
    if ($i.length) {
        return $i.first();
    }
    const suffix = '[' + target + ']';
    $i = $scope.find('input').filter(function () {
        const n = (this.getAttribute('name') || this.name || '').toString();
        return n === target || n.endsWith(suffix);
    });
    return $i.first();
}

function addContacts() {
    let added_contacts = JSON.parse($('#hidden_added_contacts').val() || '[]');
    for (let i in added_contacts) {
        let uid = added_contacts[i].uid;
        let user;
        for (let i in all_contacts) {
            if (all_contacts[i].uid == uid) {
                user = all_contacts[i];
            }
        }
        addRecipient(target, user, 'known');
    }
    //resizeInput('#' + target, 'add');
    $('#' + target).focus();
    /*global in_modal */
    if (in_modal) {
        $('#add_contacts_box').hide();
        $('#send_form').show();
        updateModalButton('send_mail');
    } else {
        $modal_box.modal('hide');
    }
}

/**
 * add a recipient
 *  create tag
 *  activate delete button
 *  add recipient to hidden input
 *  return tag id
 * @param target
 * @param recipient
 * @param {string} type
 * @returns {string} id
 */
function addRecipient(target, recipient, type) {
    // Prefer tags container within the active mail/status form if available.
    const formEl =
        typeof window.__epContactsForm !== 'undefined'
            ? window.__epContactsForm
            : null;
    const $scope = formEl ? $(formEl) : $(document);

    // Search by id with form prefix first (paper status modals)
    let $tags_container = $();
    if (formEl && formEl.id) {
        $tags_container = $scope.find('#' + formEl.id + '-' + target + '-tags');
    }
    // Fallback: id without prefix (main mail form)
    if (!$tags_container.length) {
        $tags_container = $('#' + target + '_tags');
    }
    let label = '';
    let value = '';
    let rawValue = '';
    let tooltip = '';
    let style = 'unknown';
    let uid = null;

    // recipient has been found via autocomplete
    if (type === 'known') {
        label = htmlEntities(recipient.fullname);
        rawValue = recipient.fullname + ' <' + recipient.mail + '>';
        value = htmlEntities(rawValue);

        // Build tooltip securely using DOM elements
        const $tooltipDiv1 = $('<div>').addClass('white');
        const $strong = $('<strong>').text(recipient.fullname);
        $tooltipDiv1.append($strong);
        if (recipient.username) {
            $tooltipDiv1.append(' (' + htmlEntities(recipient.username) + ')');
        }

        const $tooltipDiv2 = $('<div>').addClass('white').text(recipient.mail);

        const $tooltipContainer = $('<div>')
            .append($tooltipDiv1)
            .append($tooltipDiv2);
        tooltip = $tooltipContainer.html();

        style = 'default';
        uid = recipient.uid;

        // if sender added himself as bcc, check the checkbox
        if (
            !$('#copy').prop('checked') &&
            uid == sender_uid &&
            target === 'bcc'
        ) {
            $('#copy').prop('checked', true);
        }
    }
    // recipient has been manually inserted
    else {
        label = value = htmlEntities(recipient);
        const $tooltipDiv = $('<div>')
            .addClass('white')
            .text(
                translate(
                    "Cette adresse est inconnue et n'est peut-être pas valide."
                )
            );
        tooltip = $tooltipDiv.prop('outerHTML');
    }

    let tag = getTag(label, value, uid, tooltip, style);

    // Fallback for fields without tags container (e.g. disabled "to" field in modals).
    // Write to plain input (semicolon-separated) and hidden JSON field.
    if (!$tags_container.length) {
        const $input = epFindRecipientTextInput($scope, formEl, target);
        const $hidden = $scope.find('#hidden_' + target).first();
        if ($input.length) {
            const currentRaw = ($input.val() || '').toString();
            const current = currentRaw.replace(/\s*;+\s*$/, '').trimEnd();
            const toAppend = rawValue || value;
            const next = current ? current + '; ' + toAppend : toAppend;
            $input.val(next);
        }
        if ($hidden.length) {
            const key =
                'fallback-' +
                target +
                '-' +
                (uid ? uid : Date.now().toString());
            const recipients = $hidden.val() ? JSON.parse($hidden.val()) : [];
            const exists =
                uid &&
                recipients.some(
                    r => r && r.uid && String(r.uid) === String(uid)
                );
            if (!exists) {
                recipients.push({
                    key,
                    value: rawValue || value,
                    uid: uid || null,
                });
                $hidden.val(JSON.stringify(recipients));
            }
        }
        return '';
    }

    // insert tag
    $tags_container.append(tag);
    $tags_container.find('.recipient-tag:last').uniqueId();
    let id = $tags_container.find('.recipient-tag:last').attr('id');

    // activate tooltip
    activateTooltip(
        $tags_container.find('.recipient-tag:last').find('.recipient-name')
    );

    // activate delete button
    let $button = $tags_container
        .find('.recipient-tag:last')
        .find('.remove-recipient');
    activateDeleteButton($button, target);

    // add recipient to hidden input (if the form uses JSON hidden fields)
    let $hidden = $();
    if (formEl && formEl.id) {
        $hidden = $scope.find('#' + formEl.id + '-hidden_' + target);
    }
    if (!$hidden.length) {
        $hidden = $scope.find('input[name="hidden_' + target + '"]');
    }
    if (!$hidden.length) {
        $hidden = $('#hidden_' + target);
    }
    if ($hidden.length) {
        addUser($hidden, id, value, uid);
    }

    return id;
}

function activateDeleteButton($button, target) {
    $button.on('click', function () {
        const $tag = $(this).parent('.recipient-tag');
        const $form = $tag.closest('form');
        const formNode = $form.length ? $form[0] : null;
        removeRecipient($tag);
        const $inp = $form.length
            ? epFindRecipientTextInput($form, formNode, target)
            : $();
        if ($inp.length) {
            $inp.focus();
        } else if ($('#' + target).length) {
            $('#' + target).focus();
        }
    });
}

/* remove a recipient
 * destroy tooltip
 * delete tag
 * remove recipient from hidden input
 */
function removeRecipient($tag) {
    if (!$tag || !$tag.length) {
        return;
    }
    const $parentSpan = $tag.parent('span');
    if (!$parentSpan.length) {
        return;
    }
    const mailFormEl = $tag.closest('form').get(0);
    let $recipients_hidden_input = $();
    let suffix = '';

    // Extract target from span id (e.g., "acceptance-form-cc-tags" → "cc")
    if (mailFormEl && mailFormEl.id) {
        const spanId = $parentSpan.attr('id') || '';
        let scopedTarget = null;
        if (spanId.endsWith('-cc-tags')) {
            scopedTarget = 'cc';
        } else if (spanId.endsWith('-bcc-tags')) {
            scopedTarget = 'bcc';
        }
        if (scopedTarget) {
            $recipients_hidden_input = $(mailFormEl).find(
                '#' + mailFormEl.id + '-hidden_' + scopedTarget
            );
            suffix = scopedTarget;
        }
    }

    if (!$recipients_hidden_input.length) {
        const parentId = $parentSpan.attr('id');
        if (!parentId) {
            return;
        }
        suffix = parentId.replace('_tags', '');
        const $mailForm = $tag.closest('form');
        $recipients_hidden_input = $mailForm.length
            ? $mailForm.find('#hidden_' + suffix)
            : $();
        if (!$recipients_hidden_input.length) {
            $recipients_hidden_input = $('#hidden_' + suffix);
        }
    }

    if (!$recipients_hidden_input.length) {
        return;
    }

    let $copy_checkbox = $('#copy');

    let recipients = JSON.parse($recipients_hidden_input.val() || '[]');
    for (let i in recipients) {
        if (recipients[i].key === $tag.attr('id')) {
            recipients.splice(i, 1);
            break;
        }
    }
    $recipients_hidden_input.val(JSON.stringify(recipients));

    $tag.tooltip('destroy');
    $tag.remove();

    // if user removed himself from bcc, uncheck the checkbox
    if (
        $copy_checkbox.prop('checked') &&
        $tag.data('uid') == sender_uid &&
        suffix == 'bcc'
    ) {
        $copy_checkbox.prop('checked', false);
    }
}

// add recipient to recipients hidden input ($field: selector string or jQuery)
function addUser($field, key, value, uid) {
    uid = uid || null;
    const $input = $field instanceof jQuery ? $field : $($field);
    let recipients = [];
    if ($input.val()) {
        recipients = JSON.parse($input.val());
    }
    recipients.push({ key: key, value: value, uid: uid });

    $input.val(JSON.stringify(recipients));
}

// generate tag (html)
function getTag(label, value, uid, tooltip, style) {
    tooltip = tooltip || false;
    style = style || 'default';

    let css = style === 'default' ? '' : ' ' + style;

    // Create DOM elements instead of HTML strings for security
    const $tag = $('<div>').addClass('recipient-tag' + css);

    if (uid) {
        $tag.attr('data-uid', uid);
    }
    $tag.attr('data-value', value);

    const $nameDiv = $('<div>').addClass('recipient-name').text(label);
    if (tooltip) {
        $nameDiv.attr('data-toggle', 'tooltip').attr('title', tooltip);
    }

    const $removeSpan = $('<span>').addClass(
        'grey glyphicon glyphicon-remove icon-action remove-recipient'
    );

    $tag.append($nameDiv).append($removeSpan);

    return $tag;
}

// resize input
function resizeInput(input, action) {
    let inputY = $(input).position().top;
    let lineHeight = 24;
    let offsetY = action === 'add' ? lineHeight : 0;
    let availableWidth = getAvailableWidth(input, inputY - offsetY);

    // Si c'est une suppression et que l'input occupe toute la largeur de sa ligne, on teste si il y a de la place au dessus
    if (
        action === 'remove' &&
        availableWidth == $(input).parent('div').width()
    ) {
        availableWidth = getAvailableWidth(input, inputY - lineHeight);
    }

    if (availableWidth >= parseInt($(input).css('min-width'))) {
        $(input).css('width', availableWidth + 'px');
    } else {
        $(input).css('width', $(input).parent('div').width() + 'px');
    }
}

// Calcule la place restante pour un input
function getAvailableWidth(field, y) {
    let availableWidth = 0;
    let offset = 0;
    // On parcourt tous les tags déjà insérés
    $(field + '_tags')
        .find('.recipient-tag')
        .each(function () {
            // On ne prend en compte que les span qui se situent sur la ligne désirée
            if (
                y ==
                parseInt($(this).position().top) -
                    parseInt($(this).css('padding-top'))
            ) {
                offset += Math.ceil(
                    parseInt($(this).width()) +
                        parseInt($(this).css('border-left-width')) +
                        parseInt($(this).css('border-right-width')) +
                        parseInt($(this).css('padding-left')) +
                        parseInt($(this).css('padding-right')) +
                        5
                );
            }
        });
    return $(field).parent('div').width() - offset;
}

// update modal submit button callback
function updateModalButton(step) {
    //var original_callback = $('button#submit-modal').click;
    $modal_button.off('click');
    if (step === 'addContacts') {
        $modal_button.on('click', function (e) {
            e.preventDefault();
            addContacts();
        });
    } else if (step === 'send_mail') {
        $modal_button.on('click', function (e) {
            tinyMCE.triggerSave();
            let form_values = $('#send_form').serialize();
            let docId = $('#docid').val();
            $modal_body.html(getLoader());
            let request = ajaxRequest('/administratemail/send', form_values);
            request.done(function (result, xhr) {
                if (xhr === 'success') {
                    localStorage.removeItem('mailContent' + docId);
                }
                $modal_body.html(translate(result));
                $modal_footer.hide();
            });
        });
    }
}
function waitForElm(selector) {
    return new Promise(resolve => {
        if (document.querySelector(selector)) {
            return resolve(document.querySelector(selector));
        }

        const observer = new MutationObserver(mutations => {
            if (document.querySelector(selector)) {
                resolve(document.querySelector(selector));
                observer.disconnect();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    });
}
