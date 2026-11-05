/* global EpMailCombobox, addRecipient, removeRecipient, epWithContactsForm, epContextDocId, epAnnounce, epTagsContainer */
/**
 * Contacts picker of the mail forms: panel opened under a recipient field by its "Choose" button
 * (button.show_contacts_button, see Episciences\Mail\RecipientFieldDecorator).
 *
 * It lists the participants of the paper of the mail, the members of an editorial group, or the result
 * of a search (2 characters at least), with native checkboxes. Recipients already in the field are checked.
 * There is no "select all": mailing lists are meant for mails to many recipients.
 *
 * Disclosure pattern: the button has aria-expanded, the focus goes to the search field when the panel
 * opens and back to the button when it closes. Vanilla JS.
 */
(function (root) {
    'use strict';

    if (root.__epContactsListBound) {
        return;
    }
    root.__epContactsListBound = true;

    const STAFF_GROUPS = [
        ['chief_editor', 'Rédacteurs en chef'],
        ['secretary', 'Secrétaires de rédaction'],
        ['editor', 'Rédacteurs'],
        ['guest_editor', 'Rédacteurs invités'],
        ['copyeditor', 'Préparateurs de copie'],
        ['webmaster', 'Webmasters'],
    ];
    const t = key => EpMailCombobox.t(key);
    const format = (message, ...args) =>
        EpMailCombobox.format(message, ...args);

    function element(tag, attributes, text) {
        const node = document.createElement(tag);
        Object.entries(attributes || {}).forEach(([name, value]) => {
            if (value !== null && value !== undefined) {
                node.setAttribute(name, value);
            }
        });
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function chosenTags(form, target) {
        const container = form ? epTagsContainer(form, target) : null;
        return container
            ? Array.from(container.querySelectorAll('.recipient-tag[data-uid]'))
            : [];
    }

    /**
     * @param {HTMLButtonElement} button
     */
    function Picker(button) {
        this.button = button;
        this.target = button.dataset.target;
        this.input = document.getElementById(button.dataset.inputId);
        this.form = button.closest('form');
        this.id = (this.input ? this.input.id : 'ep-recipients') + '-picker';
        this.docId =
            typeof epContextDocId === 'function'
                ? epContextDocId(this.form)
                : null;
        // uid => recipient
        this.selected = new Map();
        this.initial = new Set(
            chosenTags(this.form, this.target).map(tag => tag.dataset.uid)
        );
        this.initial.forEach(uid => this.selected.set(uid, null));
        this.timer = null;
        // Pending request of each results area: a newer one replaces it
        this.controllers = new Map();
        this.build();
    }

    Picker.prototype.build = function () {
        const fieldName = EpMailCombobox.fieldLabel(this.target);
        const panel = element('section', {
            id: this.id,
            class: 'ep-contacts-picker',
            'aria-labelledby': this.id + '-title',
        });
        panel.appendChild(
            element(
                'h3',
                { id: this.id + '-title', class: 'ep-contacts-picker-title' },
                format(t('Choisir des destinataires (%s)'), fieldName)
            )
        );

        const searchGroup = element('div', { class: 'form-group' });
        searchGroup.appendChild(
            element(
                'label',
                { for: this.id + '-search' },
                t('Rechercher un utilisateur')
            )
        );
        this.search = element('input', {
            id: this.id + '-search',
            type: 'search',
            class: 'form-control input-sm',
            autocomplete: 'off',
            'aria-describedby': this.id + '-search-help',
        });
        searchGroup.appendChild(this.search);
        searchGroup.appendChild(
            element(
                'p',
                { id: this.id + '-search-help', class: 'help-block' },
                format(
                    t(
                        'Nom, identifiant ou adresse e-mail, au moins %d caractères.'
                    ),
                    EpMailCombobox.MIN_QUERY_LENGTH
                )
            )
        );
        panel.appendChild(searchGroup);

        const roleGroup = element('div', { class: 'form-group' });
        roleGroup.appendChild(
            element('label', { for: this.id + '-role' }, t('Équipe éditoriale'))
        );
        this.role = element('select', {
            id: this.id + '-role',
            class: 'form-control input-sm',
        });
        this.role.appendChild(
            element('option', { value: '' }, t('Choisir un groupe'))
        );
        STAFF_GROUPS.forEach(([role, label]) => {
            this.role.appendChild(element('option', { value: role }, t(label)));
        });
        roleGroup.appendChild(this.role);
        panel.appendChild(roleGroup);

        this.status = element('div', {
            id: this.id + '-status',
            class: 'sr-only',
            role: 'status',
            'aria-live': 'polite',
        });
        panel.appendChild(this.status);

        this.results = element('div', { class: 'ep-contacts-results' });
        this.paperResults = element('div');
        this.groupResults = element('div');
        this.searchResults = element('div');
        this.results.append(
            this.paperResults,
            this.groupResults,
            this.searchResults
        );
        panel.appendChild(this.results);

        if (this.button.dataset.mailingListsUrl) {
            const hint = element(
                'p',
                { class: 'help-block' },
                t('Pour écrire à de nombreux destinataires :') + ' '
            );
            hint.appendChild(
                element(
                    'a',
                    { href: this.button.dataset.mailingListsUrl },
                    t('utiliser une liste de diffusion')
                )
            );
            panel.appendChild(hint);
        }

        const actions = element('div', { class: 'ep-contacts-actions' });
        this.validate = element('button', {
            type: 'button',
            class: 'btn btn-primary btn-sm',
        });
        this.cancel = element(
            'button',
            { type: 'button', class: 'btn btn-default btn-sm' },
            t('Annuler')
        );
        actions.append(this.validate, this.cancel);
        panel.appendChild(actions);

        this.panel = panel;
        this.updateValidateLabel();

        this.search.addEventListener('input', () => this.scheduleSearch());
        this.role.addEventListener('change', () => this.loadGroup());
        this.validate.addEventListener('click', () => this.apply());
        this.cancel.addEventListener('click', () => this.close());
        panel.addEventListener('change', event => this.onCheckboxChange(event));
        panel.addEventListener('keydown', event => this.onKeyDown(event));
    };

    Picker.prototype.open = function () {
        const anchor =
            this.button.closest('.form-group') || this.button.parentElement;
        anchor.insertAdjacentElement('afterend', this.panel);
        this.button.setAttribute('aria-expanded', 'true');
        this.search.focus();
        if (this.docId) {
            this.load(this.paperResults, t("Intervenants de l'article"), {
                docId: this.docId,
            });
        }
    };

    /**
     * @param {boolean} [restoreFocus] false when the whole form goes away (modal closed)
     */
    Picker.prototype.close = function (restoreFocus) {
        this.abort();
        this.panel.remove();
        this.button.setAttribute('aria-expanded', 'false');
        delete this.button.epPicker;
        if (restoreFocus !== false) {
            this.button.focus();
        }
    };

    Picker.prototype.abort = function () {
        if (this.timer !== null) {
            clearTimeout(this.timer);
            this.timer = null;
        }
        this.controllers.forEach(controller => controller.abort());
        this.controllers.clear();
    };

    Picker.prototype.onKeyDown = function (event) {
        if (event.key === 'Escape') {
            // Closes the panel only, not the modal around the form
            event.preventDefault();
            event.stopPropagation();
            this.close();
        } else if (event.key === 'Enter' && event.target === this.search) {
            // Never submit the mail form from the search field
            event.preventDefault();
        }
    };

    Picker.prototype.scheduleSearch = function () {
        const query = this.search.value.trim();
        if (this.timer !== null) {
            clearTimeout(this.timer);
            this.timer = null;
        }
        if (query.length < EpMailCombobox.MIN_QUERY_LENGTH) {
            if (this.controllers.has(this.searchResults)) {
                this.controllers.get(this.searchResults).abort();
            }
            this.searchResults.replaceChildren();
            return;
        }
        this.timer = setTimeout(() => {
            this.timer = null;
            this.load(this.searchResults, t('Résultats de la recherche'), {
                query,
                docId: this.docId,
            });
        }, EpMailCombobox.DEBOUNCE_MS);
    };

    Picker.prototype.loadGroup = function () {
        const option = this.role.options[this.role.selectedIndex];
        if (!this.role.value) {
            this.groupResults.replaceChildren();
            return;
        }
        this.load(this.groupResults, option.textContent, {
            role: this.role.value,
            docId: this.docId,
        });
    };

    Picker.prototype.load = async function (area, legend, params) {
        const controller =
            typeof AbortController === 'function'
                ? new AbortController()
                : null;
        if (this.controllers.has(area)) {
            this.controllers.get(area).abort();
        }
        if (controller) {
            this.controllers.set(area, controller);
        }
        area.setAttribute('aria-busy', 'true');
        this.status.textContent = t('Recherche en cours…');
        try {
            const items = await EpMailCombobox.fetchRecipients(
                Object.assign({}, params, {
                    signal: controller ? controller.signal : undefined,
                })
            );
            if (controller && controller.signal.aborted) {
                return;
            }
            this.render(area, legend, items);
            this.status.textContent = items.length
                ? format(t('%s : %d utilisateurs'), legend, items.length)
                : format(t('%s : aucun utilisateur'), legend);
        } catch (e) {
            if (e && e.name === 'AbortError') {
                return;
            }
            area.replaceChildren(
                element(
                    'p',
                    { class: 'text-danger' },
                    t(
                        'Recherche indisponible, vous pouvez saisir une adresse e-mail.'
                    )
                )
            );
            this.status.textContent = t(
                'Recherche indisponible, vous pouvez saisir une adresse e-mail.'
            );
        } finally {
            if (this.controllers.get(area) === controller) {
                this.controllers.delete(area);
                area.removeAttribute('aria-busy');
            }
        }
    };

    Picker.prototype.render = function (area, legend, items) {
        const fieldset = element('fieldset', { class: 'ep-contacts-group' });
        fieldset.appendChild(element('legend', {}, legend));
        if (!items.length) {
            fieldset.appendChild(
                element('p', { class: 'help-block' }, t('Aucun utilisateur'))
            );
        }
        const prefix = this.id + '-' + Math.random().toString(36).slice(2, 8);
        items.forEach(recipient => {
            const uid = String(recipient.uid);
            const row = element('div', { class: 'checkbox' });
            const id = prefix + '-' + uid;
            // No name: the checkboxes are inside the mail form and must not be posted
            const checkbox = element('input', {
                type: 'checkbox',
                id,
                'data-uid': uid,
            });
            checkbox.checked = this.selected.has(uid);
            checkbox.epRecipient = recipient;
            const label = element('label', { for: id });
            label.appendChild(EpMailCombobox.renderRecipientContent(recipient));
            row.append(checkbox, label);
            fieldset.appendChild(row);
        });
        area.replaceChildren(fieldset);
    };

    Picker.prototype.onCheckboxChange = function (event) {
        const checkbox = event.target;
        if (
            !(checkbox instanceof HTMLInputElement) ||
            checkbox.type !== 'checkbox'
        ) {
            return;
        }
        const uid = checkbox.dataset.uid;
        if (checkbox.checked) {
            this.selected.set(uid, checkbox.epRecipient);
        } else {
            this.selected.delete(uid);
        }
        // The same user may be listed in several groups
        this.panel
            .querySelectorAll('input[type="checkbox"][data-uid="' + uid + '"]')
            .forEach(other => {
                other.checked = checkbox.checked;
            });
        this.updateValidateLabel();
    };

    Picker.prototype.additions = function () {
        return Array.from(this.selected.entries()).filter(
            ([uid, recipient]) => !this.initial.has(uid) && recipient
        );
    };

    Picker.prototype.removals = function () {
        return Array.from(this.initial).filter(uid => !this.selected.has(uid));
    };

    Picker.prototype.updateValidateLabel = function () {
        const count = this.additions().length;
        this.validate.textContent =
            count === 0
                ? t('Valider la sélection')
                : format(
                      t(
                          count === 1
                              ? 'Ajouter %d destinataire'
                              : 'Ajouter %d destinataires'
                      ),
                      count
                  );
    };

    Picker.prototype.apply = function () {
        const removals = this.removals();
        chosenTags(this.form, this.target)
            .filter(tag => removals.includes(tag.dataset.uid))
            .forEach(tag => removeRecipient(tag, { silent: true }));

        const additions = this.additions();
        epWithContactsForm(this.form, () => {
            additions.forEach(([, recipient]) => {
                addRecipient(this.target, recipient, 'known', { silent: true });
            });
        });
        this.close();
        if (
            typeof epAnnounce === 'function' &&
            (additions.length || removals.length)
        ) {
            epAnnounce(
                this.form,
                this.target,
                format(
                    t('Destinataires mis à jour : %d ajoutés, %d retirés.'),
                    additions.length,
                    removals.length
                )
            );
        }
    };

    function onButtonClick(event) {
        const button = event.target.closest
            ? event.target.closest('button.show_contacts_button')
            : null;
        if (!button) {
            return;
        }
        event.preventDefault();
        if (button.epPicker) {
            button.epPicker.close();
            return;
        }
        button.epPicker = new Picker(button);
        button.epPicker.open();
    }

    document.addEventListener('click', onButtonClick);

    // A modal closed with an open picker: the picker goes away with it
    if (typeof root.jQuery === 'function') {
        root.jQuery(document).on('hidden.bs.modal', '.modal', function () {
            this.querySelectorAll('button.show_contacts_button').forEach(
                button => {
                    if (button.epPicker) {
                        button.epPicker.close(false);
                    }
                }
            );
        });
    }

    const api = { Picker, STAFF_GROUPS };
    root.EpContactsPicker = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : globalThis);
