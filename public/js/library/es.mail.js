var $modal_box;
var in_modal;
$(function () {
    $('a#modal-contributor').click(function () {
        waitForElm('input#coAuthorsInfo').then(elm => {
            $('input#coAuthorsInfo').each(function () {
                addRecipient('cc', JSON.parse(this.value), 'known');
            });
        });
    });
});

function addContacts() {
    let added_contacts = JSON.parse($('#hidden_added_contacts').val());
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
    let $tags_container = $('#' + target + '_tags');
    let label = '';
    let value = '';
    let tooltip = '';
    let style = 'unknown';
    let uid = null;

    // recipient has been found via autocomplete
    if (type === 'known') {
        label = htmlEntities(recipient.fullname);
        value = htmlEntities(recipient.fullname + ' <' + recipient.mail + '>');

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

    // add recipient to hidden input
    addUser('#hidden_' + target, id, value, uid);

    return id;
}

function activateDeleteButton($button, target) {
    $button.on('click', function () {
        removeRecipient($(this).parent('.recipient-tag'));
        if ($('#' + target).length) {
            $('#' + target).focus();
            //resizeInput('#' + target, 'remove');
        }
    });
}

/* remove a recipient
 * destroy tooltip
 * delete tag
 * remove recipient from hidden input
 */
function removeRecipient($tag) {
    // hidden input where recipients list is stored
    let suffix = $tag.parent('span').attr('id').replace('_tags', '');
    let $recipients_hidden_input = $('#hidden_' + suffix);

    let $copy_checkbox = $('#copy');

    let recipients = JSON.parse($recipients_hidden_input.val());
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

// add recipient to recipients hidden input
function addUser(input, key, value, uid) {
    uid = uid || null;
    let recipients = [];
    if ($(input).val()) {
        recipients = JSON.parse($(input).val());
    }
    recipients.push({ key: key, value: value, uid: uid });

    $(input).val(JSON.stringify(recipients));
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
