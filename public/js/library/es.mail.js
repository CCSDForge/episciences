var $modal_box;
var in_modal;

// Same bridge as administratemail/send.js so the renderItem option is honored (htmlLabel layout).
(function ($) {
    if (!$ || !$.ui || !$.ui.autocomplete) {
        return;
    }
    if (window.__epAutocompleteRenderItemBridge) {
        return;
    }
    window.__epAutocompleteRenderItemBridge = true;
    $.widget('ui.autocomplete', $.ui.autocomplete, {
        options: {
            renderItem: null,
            renderMenu: null,
        },
        _renderItem: function (ul, item) {
            if ($.isFunction(this.options.renderItem)) {
                return this.options.renderItem(ul, item);
            }
            return this._super(ul, item);
        },
        _renderMenu: function (ul, items) {
            if ($.isFunction(this.options.renderMenu)) {
                this.options.renderMenu(ul, items);
            }
            this._super(ul, items);
        },
    });
})(typeof jQuery !== 'undefined' ? jQuery : null);

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

    if (
        typeof window.users !== 'undefined' &&
        window.users &&
        window.users.length &&
        $.ui &&
        $.ui.autocomplete
    ) {
        initRecipientsAutocompleteInScope($(document));
    }
});

/**
 * Remove accents from a string for search matching.
 * Uses String.normalize() for robust Unicode handling.
 */
function epNormalize(term) {
    if (!term || typeof term !== 'string') {
        return '';
    }
    return term.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

/**
 * Extract recipient target (to/cc/bcc) from input element.
 */
function epGetRecipientTargetFromInput(el) {
    const n = (el.getAttribute('name') || el.name || '').toString();
    if (n === 'to' || n === 'cc' || n === 'bcc') {
        return n;
    }
    const id = (el.getAttribute('id') || '').toString();
    if (id.endsWith('-to')) {
        return 'to';
    }
    if (id.endsWith('-cc')) {
        return 'cc';
    }
    if (id.endsWith('-bcc')) {
        return 'bcc';
    }
    return id;
}

/**
 * Get tags container selector for a form and target field.
 */
function epGetTagsSelector(formEl, targetField) {
    if (
        formEl &&
        formEl.id &&
        document.getElementById(formEl.id + '-' + targetField + '-tags')
    ) {
        return '#' + formEl.id + '-' + targetField + '-tags';
    }
    return '#' + targetField + '_tags';
}

/**
 * Execute callback with form context set.
 */
function epWithContactsForm(formEl, cb) {
    const prev = window.__epContactsForm;
    if (formEl) {
        window.__epContactsForm = formEl;
    }
    try {
        cb();
    } finally {
        window.__epContactsForm = prev;
    }
}

function initRecipientsAutocompleteInScope($scope) {
    $scope.find('form .autocomplete').each(function () {
        const input = this;
        const $input = $(input);
        if ($input.hasClass('ui-autocomplete-input')) {
            return;
        }
        const input_id = $input.attr('id');
        if (!input_id) {
            return;
        }
        const formEl = $input.closest('form').get(0) || null;
        const targetField = epGetRecipientTargetFromInput(input);
        const tagsSel = epGetTagsSelector(formEl, targetField);
        let input_val = 0;

        $input.autocomplete({
            appendTo: $input.closest('form'),
            minLength: 0,
            source: function (request, response) {
                const matcher = new RegExp(
                    $.ui.autocomplete.escapeRegex(request.term),
                    'i'
                );
                response(
                    $.grep(window.users, function (value) {
                        value = value.label || value.value || value;
                        return (
                            matcher.test(value) ||
                            matcher.test(epNormalize(value))
                        );
                    })
                );
            },
            focus: function () {
                return false;
            },
            select: function (event, ui) {
                epWithContactsForm(formEl, function () {
                    addRecipient(targetField, ui.item, 'known');
                });
                $input.val('');
                return false;
            },
            renderItem: function (ul, item) {
                return $('<li>')
                    .append('<a>' + item.htmlLabel + '</a>')
                    .appendTo(ul);
            },
        });

        $input.on('focus', function () {
            try {
                $input.autocomplete('search', $input.val() || '');
            } catch (e) {
                // ignore
            }
        });

        $input.on('blur', function () {
            if ($input.val() !== '') {
                epWithContactsForm(formEl, function () {
                    addRecipient(targetField, $input.val(), 'unknown');
                });
                $input.autocomplete('close');
                $input.val('');
            }
        });

        $input.on('keydown', function (e) {
            const code = e.keyCode || e.which;
            input_val = ($input.val() || '').length;
            if (code === 13) {
                e.preventDefault();
                if ($input.val() !== '') {
                    epWithContactsForm(formEl, function () {
                        addRecipient(targetField, $input.val(), 'unknown');
                    });
                    $input.autocomplete('close');
                    $input.val('');
                }
            }
        });

        $input.on('keyup', function (e) {
            const code = e.keyCode || e.which;
            if (
                code === 8 &&
                input_val === 0 &&
                $(tagsSel).find('.recipient-tag').length
            ) {
                removeRecipient($(tagsSel).find('.recipient-tag:last'));
            }
        });
    });
}

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

/** Focus Cc/Bcc input: handles {formId}-cc as well as plain #cc (send form). */
function epFocusRecipientField(target) {
    if (!target) {
        return;
    }
    const formEl = window.__epContactsForm;
    if (formEl && formEl.id) {
        const byPrefixed = document.getElementById(formEl.id + '-' + target);
        if (byPrefixed && typeof byPrefixed.focus === 'function') {
            byPrefixed.focus();
            return;
        }
    }
    const plain = document.getElementById(target);
    if (plain && typeof plain.focus === 'function') {
        plain.focus();
    }
}

/**
 * Apply pick-list selection to the mail form (Cc/Bcc tags + hidden JSON).
 * Same flow as the historical function, with safe JSON parse, picker hide, and
 * prefixed input focus for paper modals ({formId}-cc).
 *
 * @param {boolean} [skipModalChrome] Pass true from paper status modals: merge
 *   only; do not toggle #send_form or call $modal_box.modal('hide').
 */
function addContacts(skipModalChrome) {
    if (document.getElementById('hidden_added_contacts')) {
        window.__epContactsMergeInProgress = true;
        try {
            let added_contacts = [];
            try {
                added_contacts = JSON.parse(
                    $('#hidden_added_contacts').val() || '[]'
                );
            } catch (e) {
                added_contacts = [];
            }

            // Hide inline / paper hosts only (not #modal-box — Bootstrap owns visibility).
            const $pickerHost = $('#added_contacts_tags').closest(
                '#add_contacts_box, .contacts-container'
            );
            if ($pickerHost.length) {
                $pickerHost.hide();
            }

            // target is a global variable set by get-contacts.js (cc or bcc)
            const recipientTarget = typeof target !== 'undefined' ? target : 'cc';
            for (const contact of Object.values(added_contacts)) {
                const user = Object.values(all_contacts).find(
                    c => c.uid == contact.uid
                );
                if (user) {
                    addRecipient(recipientTarget, user, 'known');
                }
            }

            $('#added_contacts_tags').empty();
            $('#hidden_added_contacts').val('[]');
        } finally {
            window.__epContactsMergeInProgress = false;
        }
    }

    if (typeof target !== 'undefined' && target) {
        epFocusRecipientField(target);
    }
    //resizeInput('#' + target, 'add');

    if (skipModalChrome === true) {
        return;
    }

    /*global in_modal */
    if (in_modal) {
        $('#add_contacts_box').hide();
        $('#send_form').show();
        updateModalButton('send_mail');
    } else {
        $modal_box.modal('hide');
    }
}

function epMailUsersList() {
    const u = typeof window.users !== 'undefined' ? window.users : null;
    if (!u) {
        return [];
    }
    return Array.isArray(u) ? u : Object.values(u);
}

function epManualRecipientToRawString(recipient) {
    if (typeof recipient === 'string') {
        return recipient;
    }
    if (recipient && typeof recipient === 'object' && recipient.mail) {
        return recipient.fullname
            ? recipient.fullname + ' <' + recipient.mail + '>'
            : '<' + recipient.mail + '>';
    }
    return recipient == null ? '' : String(recipient);
}

/** Extract a single email from "u@h", "<u@h>", or "Name <u@h>". */
function epExtractLookupEmail(rawStr) {
    if (!rawStr || typeof rawStr !== 'string') {
        return '';
    }
    const t = rawStr.trim();
    const bracketOnly = t.match(/^<\s*([^<>@\s][^<>]*@[^<>]+)\s*>$/);
    if (bracketOnly) {
        return bracketOnly[1].trim();
    }
    if (/^[^\s<>]+@[^\s<>]+$/.test(t)) {
        return t;
    }
    const tail = t.match(/<\s*([^<>@\s][^<>]*@[^<>]+)\s*>$/);
    if (tail) {
        return tail[1].trim();
    }
    return '';
}

/** For display/storage: drop outer <email> chevrons when there is nothing else. */
function epRecipientLabelWithoutRedundantChevrons(rawStr) {
    if (!rawStr || typeof rawStr !== 'string') {
        return rawStr;
    }
    const t = rawStr.trim();
    const m = t.match(/^<\s*([^<>]+@[^<>]+)\s*>$/);
    if (m) {
        return m[1].trim();
    }
    return rawStr;
}

/**
 * If manual input matches an entry in window.users (e.g. "<uid@journal.fr>"), treat as known.
 */
function epResolveManualRecipientToKnown(recipient) {
    const list = epMailUsersList();
    if (!list.length) {
        return { recipient, type: 'unknown' };
    }
    if (recipient && typeof recipient === 'object' && recipient.uid) {
        return { recipient, type: 'known' };
    }
    const rawStr = epManualRecipientToRawString(recipient);
    const email = epExtractLookupEmail(rawStr);
    if (!email) {
        return { recipient, type: 'unknown' };
    }
    const found = list.find(
        u =>
            u &&
            u.mail &&
            String(u.mail).toLowerCase() === email.toLowerCase()
    );
    if (found) {
        return { recipient: found, type: 'known' };
    }
    return { recipient, type: 'unknown' };
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
    if (type !== 'known') {
        const resolved = epResolveManualRecipientToKnown(recipient);
        if (resolved.type === 'known') {
            recipient = resolved.recipient;
            type = 'known';
        }
    }

    const formEl =
        typeof window.__epContactsForm !== 'undefined'
            ? window.__epContactsForm
            : null;
    const $scope = formEl ? $(formEl) : $(document);

    let $tags_container = $('#' + target + '_tags');
    if (!$tags_container.length && formEl && formEl.id) {
        $tags_container = $scope.find('#' + formEl.id + '-' + target + '-tags');
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

        if ($tags_container.length) {
            const $dup = $tags_container.find(
                '.recipient-tag[data-uid="' + uid + '"]'
            );
            if ($dup.length) {
                return $dup.attr('id');
            }
        }

        // if sender added himself as bcc, check the checkbox
        if (
            !$('#copy').prop('checked') &&
            uid == sender_uid &&
            target === 'bcc'
        ) {
            $('#copy').prop('checked', true);
        }
    }
    // recipient has been manually inserted (or rehydrated from hidden JSON as plain text)
    else {
        const rawStr = epManualRecipientToRawString(recipient);
        const shown = epRecipientLabelWithoutRedundantChevrons(rawStr);
        label = shown;
        value = htmlEntities(shown === rawStr ? rawStr : shown);
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
        } else {
            epFocusRecipientField(target);
        }
    });
}

/**
 * Extract target (cc/bcc) from tags container span id.
 */
function epExtractTargetFromSpanId(spanId) {
    if (!spanId) {
        return null;
    }
    if (spanId.endsWith('-cc-tags') || spanId === 'cc_tags') {
        return 'cc';
    }
    if (spanId.endsWith('-bcc-tags') || spanId === 'bcc_tags') {
        return 'bcc';
    }
    if (spanId.endsWith('-to-tags') || spanId === 'to_tags') {
        return 'to';
    }
    if (spanId === 'added_contacts_tags') {
        return 'added_contacts';
    }
    // Fallback: remove _tags suffix
    return spanId.replace('_tags', '').replace(/-tags$/, '');
}

/**
 * Find hidden input for recipients based on form and target.
 */
function epFindHiddenInput($form, formEl, target) {
    if (target === 'added_contacts') {
        return $('#hidden_added_contacts');
    }
    if (formEl && formEl.id) {
        const $prefixed = $form.find('#' + formEl.id + '-hidden_' + target);
        if ($prefixed.length) {
            return $prefixed;
        }
    }
    const $inForm = $form.find('#hidden_' + target);
    if ($inForm.length) {
        return $inForm;
    }
    return $('#hidden_' + target);
}

/**
 * Remove a recipient tag, destroy tooltip, and update hidden input.
 */
function removeRecipient($tag) {
    if (!$tag || !$tag.length) {
        return;
    }
    const $parentSpan = $tag.parent('span');
    if (!$parentSpan.length) {
        return;
    }

    const $mailForm = $tag.closest('form');
    const mailFormEl = $mailForm.get(0) || null;
    const spanId = $parentSpan.attr('id') || '';
    const suffix = epExtractTargetFromSpanId(spanId);

    if (!suffix) {
        return;
    }

    const $recipients_hidden_input = epFindHiddenInput($mailForm, mailFormEl, suffix);
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
