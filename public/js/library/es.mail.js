/* global EpMailCombobox, ajaxRequest, getLoader, tinyMCE, $modal_button */
var $modal_box;
var in_modal;

/*
 * Recipients of the mail forms (To, Cc, Bcc).
 *
 * Each field has a list of the chosen recipients (<ul class="recipient-tags">, see
 * Episciences\Mail\RecipientFieldDecorator) and a hidden input holding them as JSON
 * ([{key, value, uid}]), read by the server. Recipients are searched and browsed with
 * es.mail-combobox.js. Every value is rendered as text.
 */

const EP_AUTHOR_ROLES = ['author', 'coauthor'];
const EP_REVIEWER_ROLE = 'reviewer';
let epRecipientTagCount = 0;

function epT(key) {
    return EpMailCombobox.t(key);
}

function epFormat(message, ...args) {
    return EpMailCombobox.format(message, ...args);
}

/** UID of the logged user (see var sender in the views), null if unknown */
function epSenderUid() {
    return typeof window.sender !== 'undefined' &&
        window.sender &&
        window.sender.uid
        ? String(window.sender.uid)
        : null;
}

function epFormOf(element) {
    if (element && element.closest) {
        const form = element.closest('form');
        if (form) {
            return form;
        }
    }
    return window.__epContactsForm instanceof HTMLElement
        ? window.__epContactsForm
        : null;
}

/** List of the chosen recipients of a field */
function epTagsContainer(formEl, target) {
    const scope = formEl || document;
    const byData = scope.querySelector(
        '.recipient-tags[data-recipient-target="' + target + '"]'
    );
    if (byData) {
        return byData;
    }
    if (formEl && formEl.id) {
        const prefixed = document.getElementById(
            formEl.id + '-' + target + '-tags'
        );
        if (prefixed) {
            return prefixed;
        }
    }
    return document.getElementById(target + '_tags');
}

/** Hidden input holding the recipients of a field (JSON) */
function epHiddenInput(formEl, target, container) {
    const scope = formEl || document;
    const id =
        container && container.dataset ? container.dataset.hiddenInput : '';
    if (id) {
        const byData =
            scope.querySelector('#' + CSS.escape(id)) ||
            document.getElementById(id);
        if (byData) {
            return byData;
        }
    }
    if (formEl && formEl.id) {
        const prefixed = scope.querySelector(
            '#' + CSS.escape(formEl.id + '-hidden_' + target)
        );
        if (prefixed) {
            return prefixed;
        }
    }
    return (
        scope.querySelector('input[name="hidden_' + target + '"]') ||
        document.getElementById('hidden_' + target)
    );
}

/** Text input of a field: name "cc", subform "x[cc]", or id "{formId}-cc" */
function epRecipientInput(formEl, target) {
    const scope = formEl || document;
    const byData = scope.querySelector(
        'input[data-recipient-target="' + target + '"]'
    );
    if (byData) {
        return byData;
    }
    if (formEl && formEl.id) {
        const prefixed = document.getElementById(formEl.id + '-' + target);
        if (prefixed && prefixed.tagName === 'INPUT') {
            return prefixed;
        }
    }
    return (
        Array.from(scope.querySelectorAll('input')).find(input => {
            const name = input.getAttribute('name') || '';
            return name === target || name.endsWith('[' + target + ']');
        }) || null
    );
}

function epReadRecipients(hidden) {
    if (!hidden || !hidden.value) {
        return [];
    }
    try {
        const parsed = JSON.parse(hidden.value);
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
        return [];
    }
}

function epWriteRecipients(hidden, recipients) {
    if (hidden) {
        hidden.value = JSON.stringify(recipients);
    }
}

// add recipient to recipients hidden input ($field: element, selector string or jQuery)
function addUser($field, key, value, uid, paperRole) {
    let input = $field;
    if (typeof $field === 'string') {
        input = document.querySelector($field);
    } else if ($field && $field.jquery) {
        input = $field[0];
    }
    if (!input) {
        return;
    }
    const recipients = epReadRecipients(input);
    const recipient = { key: key, value: value, uid: uid || null };
    // Kept to show the recipient again as such when the form comes back (validation error)
    if (paperRole) {
        recipient.paperRole = paperRole;
    }
    recipients.push(recipient);
    epWriteRecipients(input, recipients);
}

/**
 * Normalizes a recipient: a known user ({uid, fullname, mail...}) or a typed address ("Name <a@b>", "a@b")
 * @returns {{uid: (string|null), name: string, mail: string, username: string, paperRole: (string|null), value: string, known: boolean}}
 */
function epNormalizeRecipient(recipient, type) {
    if (recipient && typeof recipient === 'object' && recipient.mail) {
        const uid =
            type === 'known' && recipient.uid ? String(recipient.uid) : null;
        const name = recipient.fullname || recipient.screen_name || '';
        return {
            uid,
            name,
            mail: String(recipient.mail),
            username: recipient.username || '',
            paperRole: recipient.paperRole || null,
            value: name
                ? name + ' <' + recipient.mail + '>'
                : String(recipient.mail),
            known: uid !== null,
        };
    }

    const raw = String(recipient ?? '').trim();
    const mail = EpMailCombobox.extractEmail(raw) || raw;
    const name = raw
        .replace(/<[^<>]*>\s*$/, '')
        .trim()
        .replace(/^"(.*)"$/, '$1');
    return {
        uid: null,
        name: name !== mail ? name : '',
        mail,
        username: '',
        paperRole: null,
        value: name && name !== mail ? name + ' <' + mail + '>' : mail,
        known: false,
    };
}

function epFieldLabel(target) {
    return EpMailCombobox.fieldLabel(target);
}

function epUpdateListLabel(container) {
    const target = container.dataset.recipientTarget;
    if (!target) {
        return;
    }
    const count = container.querySelectorAll('.recipient-tag').length;
    container.setAttribute(
        'aria-label',
        epFormat(epT('Destinataires (%s), %d'), epFieldLabel(target), count)
    );
}

/** Live region of a field (created by the combobox), or of the page */
function epAnnounce(formEl, target, message) {
    const input = epRecipientInput(formEl, target);
    let region = input ? document.getElementById(input.id + '-status') : null;
    if (!region) {
        region = document.getElementById('ep-mail-live');
    }
    if (!region) {
        region = document.createElement('div');
        region.id = 'ep-mail-live';
        region.className = 'sr-only';
        region.setAttribute('role', 'status');
        region.setAttribute('aria-live', 'polite');
        document.body.appendChild(region);
    }
    region.textContent = message;
}

/**
 * Limits checked when the mail is sent (Episciences\Mail\RecipientListValidator, data-max-* of the list):
 * message when the field, or the form, cannot take another recipient, '' otherwise
 */
function epRecipientLimitError(formEl, container) {
    const max = Number(container.dataset.maxRecipients) || 0;
    const maxTotal = Number(container.dataset.maxTotal) || 0;
    const tooMany = epT('Trop de destinataires (%d au maximum).');
    if (max && container.querySelectorAll('.recipient-tag').length >= max) {
        return epFormat(tooMany, max);
    }
    const scope = formEl || document;
    if (
        maxTotal &&
        scope.querySelectorAll('.recipient-tags .recipient-tag').length >=
            maxTotal
    ) {
        return epFormat(tooMany, maxTotal);
    }
    return '';
}

/** Visible message under the recipients of a field, read at once by screen readers */
function epShowLimitError(container, text) {
    const id = container.id + '-limit';
    let error = document.getElementById(id);
    if (!error) {
        error = document.createElement('p');
        error.id = id;
        error.className = 'text-danger recipient-limit';
        error.setAttribute('role', 'alert');
        container.insertAdjacentElement('afterend', error);
    }
    error.textContent = text;
}

/** Field (to, cc, bcc) where this address is already chosen in the form, null if none */
function epFindRecipientField(formEl, recipient) {
    for (const target of ['to', 'cc', 'bcc']) {
        const container = epTagsContainer(formEl, target);
        if (!container) {
            continue;
        }
        const tags = Array.from(container.querySelectorAll('.recipient-tag'));
        const found = tags.find(
            tag =>
                (recipient.uid && tag.dataset.uid === recipient.uid) ||
                (tag.dataset.email || '').toLowerCase() ===
                    recipient.mail.toLowerCase()
        );
        if (found) {
            return { target, tag: found };
        }
    }
    return null;
}

/** Recipient tag: name, address for screen readers and a real button to remove it */
function epBuildTag(container, recipient) {
    const tag = document.createElement(
        container.tagName === 'UL' ? 'li' : 'span'
    );
    epRecipientTagCount += 1;
    tag.id = 'ep-recipient-' + epRecipientTagCount;
    tag.className =
        'recipient-tag' + (recipient.known ? '' : ' recipient-tag-manual');
    if (recipient.uid) {
        tag.dataset.uid = recipient.uid;
    }
    tag.dataset.email = recipient.mail;
    tag.dataset.value = recipient.value;
    if (recipient.paperRole) {
        tag.dataset.paperRole = recipient.paperRole;
    }

    const shownName = recipient.name || recipient.mail;
    const name = document.createElement('span');
    name.className = 'recipient-name';
    name.textContent = shownName;
    name.title = recipient.mail;
    tag.appendChild(name);

    const details = document.createElement('span');
    details.className = 'sr-only';
    details.textContent = recipient.name ? ' <' + recipient.mail + '>' : '';
    if (!recipient.known) {
        details.textContent += ' ' + epT('(adresse saisie)');
    }
    tag.appendChild(details);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'remove-recipient';
    const icon = document.createElement('span');
    icon.className = 'glyphicon glyphicon-remove';
    icon.setAttribute('aria-hidden', 'true');
    const label = document.createElement('span');
    label.className = 'sr-only';
    label.textContent = epFormat(epT('Retirer %s'), shownName);
    button.appendChild(icon);
    button.appendChild(label);
    tag.appendChild(button);

    return tag;
}

/** Field without recipients list (e.g. the disabled "To" of the modals): plain text + hidden JSON */
function epAddToPlainField(formEl, target, recipient) {
    const scope = formEl || document;
    const input = epRecipientInput(formEl, target);
    // The server may already have filled the field with this recipient
    if (
        input &&
        !(input.value || '')
            .toLowerCase()
            .includes(recipient.mail.toLowerCase())
    ) {
        const current = (input.value || '').replace(/\s*;+\s*$/, '').trimEnd();
        input.value = current
            ? current + '; ' + recipient.value
            : recipient.value;
    }
    const hidden =
        scope.querySelector('#hidden_' + target) ||
        epHiddenInput(formEl, target, null);
    if (hidden) {
        const recipients = epReadRecipients(hidden);
        const exists =
            recipient.uid &&
            recipients.some(r => r && String(r.uid) === recipient.uid);
        if (!exists) {
            recipients.push({
                key:
                    'fallback-' +
                    target +
                    '-' +
                    (recipient.uid || Date.now().toString()),
                value: recipient.value,
                uid: recipient.uid,
                paperRole: recipient.paperRole,
            });
            epWriteRecipients(hidden, recipients);
        }
    }
    return '';
}

function epSyncCopyCheckbox(formEl, target, recipient, isAdded) {
    const copy = (formEl || document).querySelector('#copy');
    const senderUid = epSenderUid();
    if (
        !copy ||
        target !== 'bcc' ||
        !senderUid ||
        recipient.uid !== senderUid
    ) {
        return;
    }
    copy.checked = isAdded;
}

/**
 * add a recipient: tag + hidden input
 * @param {string} target to, cc, bcc
 * @param {Object|string} recipient known user or typed address
 * @param {string} type 'known' for a user of the journal, anything else for a typed address
 * @param {{silent?: boolean}} [options]
 * @returns {string} id of the tag ('' when the field has no recipients list)
 */
function addRecipient(target, recipient, type, options) {
    const formEl =
        window.__epContactsForm instanceof HTMLElement
            ? window.__epContactsForm
            : null;
    const normalized = epNormalizeRecipient(recipient, type);
    if (!normalized.mail) {
        return '';
    }
    const container = epTagsContainer(formEl, target);

    if (!container) {
        return epAddToPlainField(formEl, target, normalized);
    }

    const scope = formEl || container.closest('form');
    const existing = epFindRecipientField(scope, normalized);
    if (existing) {
        if (!(options && options.silent)) {
            epAnnounce(
                scope,
                target,
                existing.target === target
                    ? epFormat(
                          epT('%s est déjà destinataire.'),
                          normalized.name || normalized.mail
                      )
                    : epFormat(
                          epT('%s est déjà destinataire en %s.'),
                          normalized.name || normalized.mail,
                          epFieldLabel(existing.target)
                      )
            );
        }
        return existing.tag.id;
    }

    const limitError = epRecipientLimitError(scope, container);
    if (limitError) {
        epShowLimitError(container, limitError);
        return '';
    }

    const tag = epBuildTag(container, normalized);
    container.appendChild(tag);
    addUser(
        epHiddenInput(scope, target, container),
        tag.id,
        normalized.value,
        normalized.uid,
        normalized.paperRole
    );
    epUpdateListLabel(container);
    epSyncCopyCheckbox(scope, target, normalized, true);
    epCheckAnonymity(scope);
    epCheckLargeMailing(scope);

    if (!(options && options.silent)) {
        epAnnounce(
            scope,
            target,
            epFormat(
                epT('%s ajouté aux destinataires (%s).'),
                normalized.name || normalized.mail,
                epFieldLabel(target)
            )
        );
    }

    return tag.id;
}

/**
 * Remove a recipient tag and update the hidden input
 * @param {(HTMLElement|jQuery)} tagOrJquery
 * @param {{moveFocus?: boolean, silent?: boolean}} [options]
 */
function removeRecipient(tagOrJquery, options) {
    const tag =
        tagOrJquery && tagOrJquery.jquery ? tagOrJquery[0] : tagOrJquery;
    if (!tag || !tag.parentElement) {
        return;
    }
    const container = tag.parentElement;
    const target =
        container.dataset.recipientTarget ||
        epTargetFromContainerId(container.id);
    if (!target) {
        return;
    }
    const formEl = container.closest('form');
    const hidden = epHiddenInput(formEl, target, container);
    epWriteRecipients(
        hidden,
        epReadRecipients(hidden).filter(r => r && r.key !== tag.id)
    );

    const moveFocus = options && options.moveFocus;
    const nextFocus = moveFocus
        ? epFocusAfterRemoval(tag, formEl, target)
        : null;
    const name = tag.querySelector('.recipient-name');
    const recipient = { uid: tag.dataset.uid || null };

    tag.remove();
    // A recipient less: the limits are no longer reached
    (formEl || document)
        .querySelectorAll('.recipient-limit')
        .forEach(error => error.remove());
    epUpdateListLabel(container);
    epSyncCopyCheckbox(formEl, target, recipient, false);
    epCheckAnonymity(formEl);
    epCheckLargeMailing(formEl);

    if (nextFocus) {
        nextFocus.focus();
    }
    if (!(options && options.silent)) {
        epAnnounce(
            formEl,
            target,
            epFormat(
                epT('%s retiré des destinataires (%s).'),
                name ? name.textContent : '',
                epFieldLabel(target)
            )
        );
    }
}

/** Legacy ids: cc_tags, {formId}-bcc-tags */
function epTargetFromContainerId(id) {
    const match = (id || '').match(/(?:^|-)(to|cc|bcc)(?:_tags|-tags)$/);
    return match ? match[1] : null;
}

/** Focus after a removal: next tag, else previous tag, else the input of the field */
function epFocusAfterRemoval(tag, formEl, target) {
    const sibling = tag.nextElementSibling || tag.previousElementSibling;
    if (sibling && sibling.querySelector('.remove-recipient')) {
        return sibling.querySelector('.remove-recipient');
    }
    return epRecipientInput(formEl, target);
}

/** Removes the tag of an address in a form (e.g. co-authors copy unchecked) */
function epRemoveRecipientByEmail(formEl, email) {
    const found = epFindRecipientField(formEl, {
        uid: null,
        mail: String(email || ''),
    });
    if (found) {
        removeRecipient(found.tag);
    }
}

/**
 * An author and a reviewer of the paper in To/Cc would learn the identity of each other:
 * warning with a button moving the reviewers to Bcc. The server refuses such a mail.
 */
function epCheckAnonymity(formEl) {
    if (!formEl) {
        return false;
    }
    const visibleTags = ['to', 'cc']
        .map(target => epTagsContainer(formEl, target))
        .filter(Boolean)
        .flatMap(container =>
            Array.from(container.querySelectorAll('.recipient-tag'))
        );
    const toInput = epRecipientInput(formEl, 'to');
    // This form only: other mail forms of the page have their own hidden_to
    const plainTo = formEl.querySelector('input[name="hidden_to"]');
    const roles = visibleTags.map(tag => tag.dataset.paperRole || '');

    if (toInput && toInput.dataset.paperRole) {
        roles.push(toInput.dataset.paperRole);
    }
    epReadRecipients(plainTo).forEach(r =>
        roles.push((r && r.paperRole) || '')
    );

    const isBroken =
        roles.some(role => EP_AUTHOR_ROLES.includes(role)) &&
        roles.includes(EP_REVIEWER_ROLE);

    let warning = formEl.querySelector('.ep-anonymity-warning');
    if (!isBroken) {
        if (warning) {
            warning.remove();
        }
        return false;
    }
    if (!warning) {
        warning = epBuildAnonymityWarning(formEl);
        const ccContainer = epTagsContainer(formEl, 'cc');
        const anchor = ccContainer ? ccContainer.closest('.form-group') : null;
        if (anchor) {
            anchor.insertAdjacentElement('afterend', warning);
        } else {
            formEl.prepend(warning);
        }
    }
    return true;
}

/**
 * Warns that the mail goes to many people at once, above the number of recipients given by the lists
 * (data-warning-above, see Episciences\Mail\RecipientListValidator::WARNING_ABOVE). The text does not change
 * with the number: it is read once, when the warning appears.
 */
function epCheckLargeMailing(formEl) {
    const list = formEl ? formEl.querySelector('.recipient-tags') : null;
    const threshold = list ? Number(list.dataset.warningAbove) || 0 : 0;
    if (!threshold) {
        return;
    }
    const count = formEl.querySelectorAll(
        '.recipient-tags .recipient-tag'
    ).length;
    let warning = formEl.querySelector('.ep-large-mailing-warning');
    if (count <= threshold) {
        if (warning) {
            warning.remove();
        }
        return;
    }
    if (warning) {
        return;
    }
    warning = document.createElement('div');
    warning.className = 'alert alert-warning ep-large-mailing-warning';
    warning.setAttribute('role', 'alert');
    warning.textContent = epFormat(
        epT(
            "Vous allez écrire à plus de %d personnes en même temps. Vérifiez les destinataires avant d'envoyer le message."
        ),
        threshold
    );
    const bccList = epTagsContainer(formEl, 'bcc');
    const anchor = bccList ? bccList.closest('.form-group') : null;
    if (anchor) {
        anchor.insertAdjacentElement('afterend', warning);
    } else {
        formEl.prepend(warning);
    }
}

function epBuildAnonymityWarning(formEl) {
    const warning = document.createElement('div');
    warning.className = 'alert alert-warning ep-anonymity-warning';
    warning.setAttribute('role', 'alert');

    const text = document.createElement('p');
    text.textContent = epT(
        "Un relecteur et un auteur de l'article sont destinataires en À ou Cc : leur identité serait révélée l'un à l'autre."
    );
    warning.appendChild(text);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-default btn-sm';
    button.textContent = epT('Déplacer les relecteurs en Cci');
    button.addEventListener('click', () => epMoveReviewersToBcc(formEl));
    warning.appendChild(button);

    return warning;
}

function epMoveReviewersToBcc(formEl) {
    const bccInput = epRecipientInput(formEl, 'bcc');
    ['to', 'cc'].forEach(target => {
        const container = epTagsContainer(formEl, target);
        if (!container) {
            return;
        }
        container
            .querySelectorAll(
                '.recipient-tag[data-paper-role="' + EP_REVIEWER_ROLE + '"]'
            )
            .forEach(tag => {
                const recipient = {
                    uid: tag.dataset.uid,
                    fullname: tag.querySelector('.recipient-name').textContent,
                    mail: tag.dataset.email,
                    paperRole: tag.dataset.paperRole,
                };
                removeRecipient(tag, { silent: true });
                epWithContactsForm(formEl, () =>
                    addRecipient(
                        'bcc',
                        recipient,
                        recipient.uid ? 'known' : 'manual',
                        {
                            silent: true,
                        }
                    )
                );
            });
    });
    epAnnounce(formEl, 'bcc', epT('Les relecteurs ont été déplacés en Cci.'));
    if (bccInput) {
        bccInput.focus();
    }
}

/** Execute callback with form context set */
function epWithContactsForm(formEl, cb) {
    const prev = window.__epContactsForm;
    if (formEl) {
        window.__epContactsForm = formEl;
    }
    try {
        return cb();
    } finally {
        window.__epContactsForm = prev;
    }
}

/** Paper of the mail, for the suggestions (participants first) */
function epContextDocId(formEl) {
    const field = formEl ? formEl.querySelector('input[name="docid"]') : null;
    if (field && field.value) {
        return field.value;
    }
    return typeof window.paper !== 'undefined' &&
        window.paper &&
        window.paper.id
        ? window.paper.id
        : null;
}

/** Keyboard on the remove buttons of the tags */
function epOnTagKeyDown(event) {
    const button = event.target.closest
        ? event.target.closest('.remove-recipient')
        : null;
    if (!button) {
        return;
    }
    const tag = button.closest('.recipient-tag');
    if (event.key === 'Backspace' || event.key === 'Delete') {
        event.preventDefault();
        removeRecipient(tag, { moveFocus: true });
    } else if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        const sibling =
            event.key === 'ArrowLeft'
                ? tag.previousElementSibling
                : tag.nextElementSibling;
        if (sibling && sibling.querySelector('.remove-recipient')) {
            event.preventDefault();
            sibling.querySelector('.remove-recipient').focus();
        } else if (event.key === 'ArrowRight') {
            const container = tag.parentElement;
            const input = epRecipientInput(
                container.closest('form'),
                container.dataset.recipientTarget
            );
            if (input) {
                event.preventDefault();
                input.focus();
            }
        }
    }
}

function epOnTagClick(event) {
    const button = event.target.closest
        ? event.target.closest('.remove-recipient')
        : null;
    if (button) {
        event.preventDefault();
        removeRecipient(button.closest('.recipient-tag'), { moveFocus: true });
    }
}

/**
 * Search and typed addresses on the recipient inputs of a scope (document, loaded form)
 * @param {(Document|HTMLElement)} [scope]
 */
function epInitRecipientFields(scope) {
    if (typeof EpMailCombobox === 'undefined') {
        return;
    }
    (scope || document)
        .querySelectorAll('input.recipient-input')
        .forEach(input => {
            if (input.disabled) {
                return;
            }
            const formEl = input.closest('form');
            const target = input.dataset.recipientTarget;
            EpMailCombobox.attach(input, {
                docId: epContextDocId(formEl),
                onSelect: (recipient, options) =>
                    epWithContactsForm(formEl, () =>
                        addRecipient(target, recipient, 'known', options)
                    ),
                // Already in To, Cc or Bcc: shown as such in the browsable list
                isChosen: recipient =>
                    epFindRecipientField(
                        formEl,
                        epNormalizeRecipient(recipient, 'known')
                    ) !== null,
                onFreeEntry: text =>
                    epWithContactsForm(formEl, () =>
                        addRecipient(target, text, 'manual')
                    ),
                onBackspaceEmpty: () => {
                    // Focus the last recipient, without removing it: a second Backspace removes it
                    const container = epTagsContainer(formEl, target);
                    const buttons = container
                        ? container.querySelectorAll('.remove-recipient')
                        : [];
                    if (buttons.length) {
                        buttons[buttons.length - 1].focus();
                    }
                },
            });
        });
}

/**
 * Recipients set server-side in the hidden_* JSON (paper modals defaults, form shown again after a validation
 * error): rendered as tags, known users staying known.
 */
function initPaperModalRecipientTagsFromHidden() {
    document.querySelectorAll('form[id]').forEach(form => {
        ['to', 'cc', 'bcc'].forEach(target => {
            const container = epTagsContainer(form, target);
            if (!container || container.querySelector('.recipient-tag')) {
                return;
            }
            const hidden = epHiddenInput(form, target, container);
            const recipients = epReadRecipients(hidden);
            if (!recipients.length) {
                return;
            }
            epWriteRecipients(hidden, []);
            epWithContactsForm(form, () => {
                recipients.forEach(r => {
                    if (!r || !r.value) {
                        return;
                    }
                    if (r.uid) {
                        const typed = epNormalizeRecipient(r.value, 'manual');
                        addRecipient(
                            target,
                            {
                                uid: r.uid,
                                fullname: typed.name,
                                mail: typed.mail,
                                paperRole: r.paperRole,
                            },
                            'known',
                            { silent: true }
                        );
                    } else {
                        addRecipient(target, r.value, 'manual', {
                            silent: true,
                        });
                    }
                });
            });
        });
    });
}

document.addEventListener('click', epOnTagClick);
document.addEventListener('keydown', epOnTagKeyDown);

if (typeof $ === 'function') {
    $(function () {
        initPaperModalRecipientTagsFromHidden();
        epInitRecipientFields(document);

        $('a#modal-contributor').click(function () {
            waitForElm('input#coAuthorsInfo').then(() => {
                $('input#coAuthorsInfo').each(function () {
                    // The form of these hidden inputs: other mail forms of the page have a Cc field too
                    const coAuthor = JSON.parse(this.value);
                    epWithContactsForm(this.closest('form'), () =>
                        addRecipient('cc', coAuthor, 'known')
                    );
                });
            });
        });
    });
}

// update modal submit button callback
function updateModalButton(step) {
    $modal_button.off('click');
    if (step === 'send_mail') {
        $modal_button.on('click', () =>
            epSendModalMail(document.getElementById('send_form'))
        );
    }
}

/**
 * Message of the server (HTML built by AdministratemailController::sendMail()) as text:
 * its title and its lines, never inserted as HTML
 * @param {string} html
 * @returns {{title: string, lines: string[]}}
 */
function epServerMessage(html) {
    const doc = new DOMParser().parseFromString(
        String(html || ''),
        'text/html'
    );
    const lines = Array.from(doc.body.querySelectorAll('div'))
        .map(div => div.textContent.trim())
        .filter(Boolean);
    const title = doc.body.querySelector('p');
    return {
        title: (title ? title.textContent : doc.body.textContent).trim(),
        lines,
    };
}

/** Error shown above the mail form of the modal, which stays filled */
function epShowSendError(form, html) {
    let alert = form.querySelector('.ep-send-error');
    if (!alert) {
        alert = document.createElement('div');
        alert.className = 'alert alert-danger ep-send-error';
        alert.setAttribute('role', 'alert');
        alert.tabIndex = -1;
        form.prepend(alert);
    }
    const message = epServerMessage(html);
    const title = document.createElement('strong');
    title.textContent =
        message.title || epT("Votre message n'a pas pu être envoyé :");
    alert.replaceChildren(title);
    if (message.lines.length) {
        const list = document.createElement('ul');
        message.lines.forEach(line => {
            const item = document.createElement('li');
            item.textContent = line;
            list.appendChild(item);
        });
        alert.appendChild(list);
    }
    alert.focus();
}

/** Fields of the form, as posted (the attachments are uploaded apart) */
function epFormQuery(form) {
    const params = new URLSearchParams();
    new FormData(form).forEach((value, name) => {
        if (typeof value === 'string') {
            params.append(name, value);
        }
    });
    return params.toString();
}

/**
 * Sends the mail of the modal. The form is only hidden while sending: when the mail is refused, it is shown
 * again with the error above it, as typed. When it is sent, the form dispatches "ep:mail-sent" (detail.message:
 * text of the server message), the modal closing itself (see administratemail/send.js).
 * @param {HTMLFormElement} form
 */
function epSendModalMail(form) {
    if (!form) {
        return;
    }
    tinyMCE.triggerSave();
    const docId = (form.querySelector('#docid') || {}).value;
    const loader = document.createElement('div');
    loader.innerHTML = getLoader();
    form.hidden = true;
    form.insertAdjacentElement('afterend', loader);
    $modal_button.prop('disabled', true);

    const request = ajaxRequest('/administratemail/send', epFormQuery(form));
    request.done(function (result) {
        localStorage.removeItem('mailContent' + docId);
        loader.remove();
        // The button of the modal is shared with the other modals of the page
        $modal_button.prop('disabled', false);
        form.dispatchEvent(
            new CustomEvent('ep:mail-sent', {
                bubbles: true,
                detail: { message: epServerMessage(result).title },
            })
        );
    });
    // Refused: the typed text and the recipients stay in the form
    request.fail(function (xhr) {
        loader.remove();
        form.hidden = false;
        $modal_button.prop('disabled', false);
        epShowSendError(form, xhr.responseText);
    });
}

function waitForElm(selector) {
    return new Promise(resolve => {
        if (document.querySelector(selector)) {
            return resolve(document.querySelector(selector));
        }

        const observer = new MutationObserver(() => {
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

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        addRecipient,
        removeRecipient,
        addUser,
        epAnnounce,
        epCheckAnonymity,
        epContextDocId,
        epInitRecipientFields,
        epNormalizeRecipient,
        epRemoveRecipientByEmail,
        epSendModalMail,
        epTagsContainer,
        epWithContactsForm,
        initPaperModalRecipientTagsFromHidden,
    };
}
