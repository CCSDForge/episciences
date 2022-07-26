var $modal_box;
var in_modal;

function addContacts() {

    var added_contacts = JSON.parse($('#hidden_added_contacts').val());
    for (var i in added_contacts) {
        var uid = added_contacts[i].uid;
        var user;
        for (var i in all_contacts) {
            if (all_contacts[i].uid == uid) {
                user = all_contacts[i];
            }
        }
        addRecipient(target, user, 'known');
    }
    resizeInput('#' + target, 'add');
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

    var $tags_container = $('#' + target + '_tags');

    // recipient has been found via autocomplete
    if (type == 'known') {

        var label = htmlEntities(recipient.fullname);
        var value = htmlEntities(recipient.fullname + ' <' + recipient.mail + '>');
        var tooltip = "<div class='white'><strong>" + recipient.fullname + "</strong>";
        if (recipient.username) {
            tooltip += " (" + recipient.username + ")";
        }
        tooltip += "</div>";
        tooltip += "<div class='white'>" + recipient.mail + "</div>";
        var style = 'default';

        var uid = recipient.uid;

        // if sender added himself as bcc, check the checkbox
        if (!$('#copy').prop('checked') && uid == sender_uid && target == 'bcc') {
            $('#copy').prop('checked', true);
        }

    }
    // recipient has been manually inserted
    else {
        var label = value = htmlEntities(recipient);
        var tooltip = "<div class='white'>" + translate("Cette adresse est inconnue et n'est peut-être pas valide.") + "</div>";
        var uid = null;
        var style = 'unknown';
    }
    var tag = getTag(label, value, uid, htmlEntities(tooltip), style);

    // insert tag
    $tags_container.append(tag);
    $tags_container.find('.recipient-tag:last').uniqueId();
    var id = $tags_container.find('.recipient-tag:last').attr('id');

    // activate tooltip
    activateTooltip($tags_container.find('.recipient-tag:last').find('.recipient-name'));

    // activate delete button
    var $button = $tags_container.find('.recipient-tag:last').find('.remove-recipient');
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
            resizeInput('#' + target, 'remove');
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
    var suffix = $tag.parent('span').attr('id').replace('_tags', '');
    var $recipients_hidden_input = $('#hidden_' + suffix);

    var $copy_checkbox = $('#copy');

    var recipients = JSON.parse($recipients_hidden_input.val());
    for (var i in recipients) {
        if (recipients[i].key === $tag.attr('id')) {
            recipients.splice(i, 1);
            break;
        }
    }
    $recipients_hidden_input.val(JSON.stringify(recipients));

    $tag.tooltip('destroy');
    $tag.remove();

    // if user removed himself from bcc, uncheck the checkbox
    if ($copy_checkbox.prop('checked') && $tag.data('uid') == sender_uid && suffix == 'bcc') {
        $copy_checkbox.prop('checked', false);
    }
}


// add recipient to recipients hidden input
function addUser(input, key, value, uid) {
    uid = uid || null;
    var recipients = [];
    if ($(input).val()) {
        recipients = JSON.parse($(input).val());
    }
    recipients.push({'key': key, 'value': value, 'uid': uid});

    $(input).val(JSON.stringify(recipients));
}

// generate tag (html)
function getTag(label, value, uid, tooltip, style) {
    uid = uid || null;
    tooltip = tooltip || false;
    style = style || 'default';

    var css = (style === 'default') ? '' : ' ' + style;
    var tag = '';
    tag += "<div class='recipient-tag" + css + "'";
    if (uid) {
        tag += "data-uid='" + uid + "'";
    }
    tag += "data-value='" + value + "'>";
    tag += "<div class='recipient-name'";
    if (tooltip) {
        tag += " data-toggle='tooltip' title='" + tooltip + "'";
    }
    tag += ">" + label + "</div>";
    tag += "<span class='grey glyphicon glyphicon-remove icon-action remove-recipient'></span>";
    tag += "</div> ";

    return tag;
}

// resize input
function resizeInput(input, action) {
    var inputY = $(input).position().top;
    var lineHeight = 24;
    var offsetY = (action === 'add') ? lineHeight : 0;
    var availableWidth = getAvailableWidth(input, (inputY - offsetY));

    // Si c'est une suppression et que l'input occupe toute la largeur de sa ligne, on teste si il y a de la place au dessus
    if (action == 'remove' && availableWidth == $(input).parent('div').width()) {
        availableWidth = getAvailableWidth(input, (inputY - lineHeight));
    }

    if (availableWidth >= parseInt($(input).css('min-width'))) {
        $(input).css('width', availableWidth + 'px');
    } else {
        $(input).css('width', $(input).parent('div').width() + 'px');
    }
}

// Calcule la place restante pour un input
function getAvailableWidth(field, y) {
    var availableWidth = 0;
    var offset = 0;
    // On parcourt tous les tags déjà insérés
    $(field + '_tags').find('.recipient-tag').each(function () {
        // On ne prend en compte que les span qui se situent sur la ligne désirée
        if (y == parseInt($(this).position().top) - parseInt($(this).css('padding-top'))) {
            offset += Math.ceil(
                parseInt($(this).width()) +
                parseInt($(this).css('border-left-width')) + parseInt($(this).css('border-right-width')) +
                parseInt($(this).css('padding-left')) + parseInt($(this).css('padding-right')) + 5
            );
        }
    });
    return ($(field).parent('div').width() - offset);
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
            $modal_body.html(getLoader());
            let request = ajaxRequest('/administratemail/send', form_values );
            request.done(function(result){
                $modal_body.html(translate(result));
                $modal_footer.hide();
            });
        });
    }
}
