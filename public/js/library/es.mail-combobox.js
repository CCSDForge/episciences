/**
 * Recipients search of the mail forms (To, Cc, Bcc): WAI-ARIA 1.2 combobox with a listbox popup.
 *
 * - suggestions are searched on the server (/administratemail/getrecipients), from 2 characters,
 *   debounced, the previous request being aborted;
 * - with an empty input, the list can be browsed (click on the input, its arrow button or Down arrow):
 *   participants of the paper of the mail, then the editorial groups, each opening the list of its members;
 *   the list stays open to add several recipients;
 * - addresses can also be typed or pasted (separated by "," ";" or new lines);
 * - every value is rendered as text (textContent): the server returns raw values.
 *
 * The host (es.mail.js) owns the recipients list: it receives the chosen recipients through callbacks.
 * Vanilla JS, no dependency.
 */
(function (root) {
    'use strict';

    const ENDPOINT = '/administratemail/getrecipients';
    const MIN_QUERY_LENGTH = 2;
    const DEBOUNCE_MS = 250;
    const SEPARATORS = /[,;\n\r]+/;
    const GROUPS = [
        { key: 'paper', label: "Intervenants de l'article" },
        { key: 'staff', label: 'Équipe éditoriale' },
        { key: 'other', label: 'Autres utilisateurs' },
    ];

    /** Editorial groups that can be browsed (RecipientSearchService::BROWSEABLE_STAFF_ROLES) */
    const STAFF_GROUPS = [
        ['chief_editor', 'Rédacteurs en chef'],
        ['secretary', 'Secrétaires de rédaction'],
        ['editor', 'Rédacteurs'],
        ['guest_editor', 'Rédacteurs invités'],
        ['copyeditor', 'Préparateurs de copie'],
        ['webmaster', 'Webmasters'],
    ];

    const FIELD_LABELS = { to: 'À', cc: 'Cc', bcc: 'Bcc' };

    let instances = 0;

    function t(key) {
        return typeof root.translate === 'function' ? root.translate(key) : key;
    }

    /** Replaces the %s / %d placeholders, in order */
    function format(message, ...args) {
        let i = 0;
        return message.replace(/%[sd]/g, () => String(args[i++] ?? ''));
    }

    /** Name of a recipient field (to, cc, bcc), as shown to the user */
    function fieldLabel(target) {
        return t(FIELD_LABELS[target] || target);
    }

    /** Extracts the address of "u@h", "<u@h>" or "Name <u@h>"; '' when there is none */
    function extractEmail(raw) {
        if (!raw || typeof raw !== 'string') {
            return '';
        }
        const value = raw.trim();
        const bracket = value.match(/<\s*([^<>\s]+@[^<>\s]+)\s*>$/);
        if (bracket) {
            return bracket[1].trim();
        }
        return /^[^\s<>]+@[^\s<>]+$/.test(value) ? value : '';
    }

    function isValidAddress(raw) {
        return /^[^\s@<>"(),;:]+@[^\s@<>"(),;:]+\.[^\s@<>"(),;:]+$/.test(
            extractEmail(raw)
        );
    }

    /** Splits a typed or pasted text into addresses */
    function splitAddresses(text) {
        return String(text || '')
            .split(SEPARATORS)
            .map(part => part.trim())
            .filter(part => part !== '');
    }

    /**
     * @param {{query?: string, role?: string, docId?: (string|number|null), signal?: AbortSignal}} params
     * @returns {Promise<Array<Object>>}
     */
    async function fetchRecipients(params) {
        const url = new URL(ENDPOINT, root.location.origin);
        if (params.query) {
            url.searchParams.set('q', params.query);
        }
        if (params.role) {
            url.searchParams.set('role', params.role);
        }
        if (params.docId) {
            url.searchParams.set('docid', String(params.docId));
        }
        const response = await fetch(url.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: params.signal,
        });
        if (!response.ok) {
            const error = new Error('Recipients search failed');
            error.status = response.status;
            throw error;
        }
        const data = await response.json();
        return Array.isArray(data && data.results) ? data.results : [];
    }

    /**
     * Color of a role, shared with the users list (sass/components/_user-role-colors.scss)
     * @param {string} role
     * @returns {string}
     */
    function roleColorClass(role) {
        const colorRole = role === 'coauthor' ? 'author' : role;
        return /^[a-z_]+$/.test(colorRole) ? 'role-' + colorRole : '';
    }

    /** Label of a role, for the badges of the suggestions */
    function roleLabel(role) {
        return t(role);
    }

    /**
     * Content of a suggestion (name, username, roles, email), as text nodes
     * @param {Object} recipient
     * @returns {DocumentFragment}
     */
    function renderRecipientContent(recipient) {
        const fragment = document.createDocumentFragment();

        const identity = document.createElement('span');
        identity.className = 'ep-recipient-identity';

        const line = document.createElement('span');
        line.className = 'ep-recipient-main';
        const name = document.createElement('span');
        name.className = 'ep-recipient-name';
        name.textContent = recipient.fullname || recipient.mail || '';
        line.appendChild(name);

        if (recipient.username) {
            const username = document.createElement('span');
            username.className = 'ep-recipient-username';
            username.textContent = ' (' + recipient.username + ')';
            line.appendChild(username);
        }
        identity.appendChild(line);

        const email = document.createElement('span');
        email.className = 'ep-recipient-email';
        email.textContent = recipient.mail || '';
        identity.appendChild(email);
        fragment.appendChild(identity);

        const roles = [];
        if (recipient.paperRole) {
            roles.push(recipient.paperRole);
        }
        (recipient.roles || []).forEach(role => {
            if (!roles.includes(role)) {
                roles.push(role);
            }
        });
        if (roles.length > 0) {
            const badges = document.createElement('span');
            badges.className = 'ep-recipient-roles';
            roles.forEach(role => {
                const badge = document.createElement('span');
                badge.className =
                    'label ep-recipient-role ' + roleColorClass(role);
                badge.textContent = roleLabel(role);
                badges.appendChild(document.createTextNode(' '));
                badges.appendChild(badge);
            });
            fragment.appendChild(badges);
        }

        return fragment;
    }

    /**
     * @param {HTMLInputElement} input
     * @param {{
     *   docId?: (string|number|null),
     *   onSelect: function(Object, Object=): *,
     *   onFreeEntry: function(string): boolean,
     *   onBackspaceEmpty?: function(): void,
     *   isChosen?: function(Object): boolean,
     *   fetcher?: function(Object): Promise<Array<Object>>,
     *   debounceMs?: number
     * }} options onSelect returns a truthy value when the recipient was added
     */
    function attach(input, options) {
        if (!input || input.dataset.epCombobox === 'true') {
            return null;
        }
        input.dataset.epCombobox = 'true';
        instances += 1;

        const baseId = input.id || 'ep-mail-recipient-' + instances;
        input.id = baseId;
        const fetcher = options.fetcher || fetchRecipients;
        const debounceMs =
            typeof options.debounceMs === 'number'
                ? options.debounceMs
                : DEBOUNCE_MS;

        const listbox = document.createElement('div');
        listbox.id = baseId + '-listbox';
        listbox.className = 'ep-mail-ac-listbox';
        listbox.setAttribute('role', 'listbox');
        listbox.setAttribute('aria-label', t('Suggestions de destinataires'));
        listbox.hidden = true;

        const status = document.createElement('div');
        status.id = baseId + '-status';
        status.className = 'sr-only';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');

        const error = document.createElement('div');
        error.id = baseId + '-error';
        error.className = 'text-danger recipient-error';
        error.hidden = true;

        // Opens the browsable list; out of the tab sequence, as the Down arrow does the same (WAI-ARIA APG)
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'ep-combobox-toggle';
        toggle.tabIndex = -1;
        toggle.setAttribute('aria-label', t('Parcourir les destinataires'));
        toggle.setAttribute('aria-controls', listbox.id);
        toggle.setAttribute('aria-expanded', 'false');
        const caret = document.createElement('span');
        caret.className = 'caret';
        caret.setAttribute('aria-hidden', 'true');
        toggle.appendChild(caret);

        // The suggestions overlay the content below, aligned on the input
        const container = document.createElement('div');
        container.className = 'ep-combobox';
        input.insertAdjacentElement('beforebegin', container);
        container.appendChild(input);
        container.appendChild(toggle);
        container.appendChild(listbox);
        container.insertAdjacentElement('afterend', error);
        error.insertAdjacentElement('afterend', status);

        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', listbox.id);
        input.setAttribute('autocomplete', 'off');

        const isChosen = options.isChosen || (() => false);
        // Entries of the options, in order: {kind: 'user', recipient} | {kind: 'group', role, label}
        // | {kind: 'back'} | {kind: 'all', recipients}
        let results = [];
        let activeIndex = -1;
        // 'search' (typed text), 'browse' (participants and groups) or 'group' (members of a group)
        let mode = 'search';
        let currentGroup = null;
        let participants = null;
        let timer = null;
        let controller = null;
        let requestId = 0;

        function announce(message) {
            status.textContent = message;
        }

        function setError(message) {
            error.textContent = message;
            error.hidden = false;
            input.setAttribute('aria-invalid', 'true');
            addDescribedBy(error.id);
        }

        function clearError() {
            error.textContent = '';
            error.hidden = true;
            input.removeAttribute('aria-invalid');
            removeDescribedBy(error.id);
        }

        function addDescribedBy(id) {
            const ids = (input.getAttribute('aria-describedby') || '')
                .split(/\s+/)
                .filter(Boolean);
            if (!ids.includes(id)) {
                ids.push(id);
            }
            input.setAttribute('aria-describedby', ids.join(' '));
        }

        function removeDescribedBy(id) {
            const ids = (input.getAttribute('aria-describedby') || '')
                .split(/\s+/)
                .filter(value => value && value !== id);
            if (ids.length) {
                input.setAttribute('aria-describedby', ids.join(' '));
            } else {
                input.removeAttribute('aria-describedby');
            }
        }

        function isOpen() {
            return !listbox.hidden;
        }

        function open() {
            listbox.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            toggle.setAttribute('aria-expanded', 'true');
        }

        function close() {
            listbox.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            activeIndex = -1;
        }

        function cancelPending() {
            if (timer !== null) {
                clearTimeout(timer);
                timer = null;
            }
            if (controller) {
                controller.abort();
                controller = null;
            }
            listbox.removeAttribute('aria-busy');
        }

        function optionElements() {
            return Array.from(listbox.querySelectorAll('[role="option"]'));
        }

        function setActive(index) {
            const elements = optionElements();
            if (!elements.length) {
                return;
            }
            activeIndex = Math.max(0, Math.min(index, elements.length - 1));
            elements.forEach((element, i) => {
                element.setAttribute(
                    'aria-selected',
                    i === activeIndex ? 'true' : 'false'
                );
                element.classList.toggle('active', i === activeIndex);
            });
            const active = elements[activeIndex];
            input.setAttribute('aria-activedescendant', active.id);
            if (typeof active.scrollIntoView === 'function') {
                active.scrollIntoView({ block: 'nearest' });
            }
        }

        function appendOption(groupElement, entry) {
            const option = document.createElement('div');
            option.id = listbox.id + '-option-' + results.length;
            option.className = 'ep-mail-ac-option';
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            option.dataset.index = String(results.length);

            if (entry.kind === 'user') {
                option.appendChild(renderRecipientContent(entry.recipient));
                if (isChosen(entry.recipient)) {
                    option.setAttribute('aria-disabled', 'true');
                    option.classList.add('ep-mail-ac-option-chosen');
                    const chosen = document.createElement('span');
                    chosen.className = 'ep-recipient-chosen';
                    chosen.textContent = t('Déjà destinataire');
                    option.appendChild(chosen);
                }
            } else {
                option.classList.add('ep-mail-ac-action');
                const text = document.createElement('span');
                text.className = 'ep-mail-ac-action-label';
                if (entry.kind === 'group') {
                    text.textContent = t(entry.label);
                } else if (entry.kind === 'back') {
                    text.textContent = t('Retour aux groupes');
                } else {
                    text.textContent = format(
                        t('Ajouter tout le groupe (%d)'),
                        entry.recipients.length
                    );
                }
                if (entry.kind === 'back') {
                    option.appendChild(chevron('ep-mail-ac-back'));
                }
                option.appendChild(text);
                if (entry.kind === 'group') {
                    option.appendChild(chevron('ep-mail-ac-more'));
                }
            }
            groupElement.appendChild(option);
            results.push(entry);
        }

        function chevron(className) {
            const icon = document.createElement('span');
            icon.className = className;
            icon.setAttribute('aria-hidden', 'true');
            icon.textContent = className === 'ep-mail-ac-back' ? '‹' : '›';
            return icon;
        }

        /**
         * @param {Array<{key: string, label: string, entries: Array<Object>}>} sections
         */
        function renderSections(sections) {
            listbox.replaceChildren();
            activeIndex = -1;
            // The options are rebuilt with the same ids: none of them is active yet
            input.removeAttribute('aria-activedescendant');
            results = [];

            sections.forEach(section => {
                if (!section.entries.length) {
                    return;
                }
                const groupElement = document.createElement('div');
                groupElement.className = 'ep-mail-ac-group';
                groupElement.setAttribute('role', 'group');
                const label = document.createElement('div');
                label.id = listbox.id + '-group-' + section.key;
                label.className = 'ep-mail-ac-group-label';
                label.setAttribute('role', 'presentation');
                label.textContent = section.label;
                groupElement.setAttribute('aria-labelledby', label.id);
                groupElement.appendChild(label);
                section.entries.forEach(entry =>
                    appendOption(groupElement, entry)
                );
                listbox.appendChild(groupElement);
            });
        }

        function userEntries(items) {
            return items.map(recipient => ({ kind: 'user', recipient }));
        }

        function render(items) {
            renderSections(
                GROUPS.map(group => ({
                    key: group.key,
                    label: t(group.label),
                    entries: userEntries(
                        items.filter(
                            item => (item.group || 'other') === group.key
                        )
                    ),
                }))
            );
        }

        function renderBrowse() {
            renderSections([
                {
                    key: 'paper',
                    label: t("Intervenants de l'article"),
                    entries: userEntries(participants || []),
                },
                {
                    key: 'staff',
                    label: t('Équipe éditoriale'),
                    entries: STAFF_GROUPS.map(([role, label]) => ({
                        kind: 'group',
                        role,
                        label,
                    })),
                },
            ]);
        }

        function renderGroup() {
            const free = currentGroup.members.filter(r => !isChosen(r));
            const entries = [{ kind: 'back' }];
            if (free.length > 1) {
                entries.push({ kind: 'all', recipients: free });
            }
            renderSections([
                {
                    key: 'members',
                    label: t(currentGroup.label),
                    entries: entries.concat(userEntries(currentGroup.members)),
                },
            ]);
        }

        /** Shows the current view again (the chosen recipients changed), keeping the active option */
        function refresh() {
            const index = activeIndex;
            if (mode === 'browse') {
                renderBrowse();
            } else if (mode === 'group') {
                renderGroup();
            }
            if (index >= 0 && results.length) {
                setActive(Math.min(index, results.length - 1));
            }
        }

        /**
         * Runs a request, the previous one being aborted; ignores a response arriving after a newer request
         * @returns {Promise<{stale?: boolean, failed?: boolean, items?: Array<Object>}>}
         */
        async function request(params) {
            cancelPending();
            controller =
                typeof AbortController === 'function'
                    ? new AbortController()
                    : null;
            const current = ++requestId;
            listbox.setAttribute('aria-busy', 'true');
            announce(t('Recherche en cours…'));
            try {
                const items = await fetcher(
                    Object.assign({ docId: options.docId }, params, {
                        signal: controller ? controller.signal : undefined,
                    })
                );
                if (current !== requestId) {
                    return { stale: true };
                }
                listbox.removeAttribute('aria-busy');
                return { items };
            } catch (e) {
                if ((e && e.name === 'AbortError') || current !== requestId) {
                    return { stale: true };
                }
                listbox.removeAttribute('aria-busy');
                announce(
                    t(
                        'Recherche indisponible, vous pouvez saisir une adresse e-mail.'
                    )
                );
                return { failed: true };
            }
        }

        function announceCount(count) {
            announce(
                format(
                    t(
                        '%d suggestions, flèches haut et bas pour parcourir, Entrée pour choisir'
                    ),
                    count
                )
            );
        }

        async function browse() {
            mode = 'browse';
            currentGroup = null;
            if (participants === null && options.docId) {
                const response = await request({});
                if (response.stale || mode !== 'browse') {
                    return;
                }
                // Not cached after a failure: the next opening asks again
                if (response.failed) {
                    renderBrowse();
                    open();
                    return;
                }
                participants = response.items;
            }
            cancelPending();
            renderBrowse();
            open();
            announceCount(results.length);
        }

        async function openGroup(role, label) {
            mode = 'group';
            const response = await request({ role });
            if (response.stale || mode !== 'group') {
                return;
            }
            if (response.failed) {
                mode = 'browse';
                return;
            }
            const items = response.items;
            currentGroup = { role, label, members: items };
            renderGroup();
            open();
            setActive(0);
            announce(
                items.length
                    ? format(t('%s : %d utilisateurs'), t(label), items.length)
                    : format(t('%s : aucun utilisateur'), t(label))
            );
        }

        async function search(query) {
            mode = 'search';
            const response = await request({ query });
            if (response.stale) {
                return;
            }
            if (response.failed) {
                render([]);
                close();
                return;
            }
            const items = response.items;
            render(items);
            if (items.length) {
                open();
                announceCount(items.length);
            } else {
                close();
                announce(
                    t('Aucun résultat, vous pouvez saisir une adresse e-mail.')
                );
            }
        }

        function scheduleSearch() {
            const query = input.value.trim();
            cancelPending();
            mode = 'search';
            if (query.length < MIN_QUERY_LENGTH) {
                close();
                if (query.length > 0) {
                    announce(
                        format(
                            t('Saisissez au moins %d caractères.'),
                            MIN_QUERY_LENGTH
                        )
                    );
                }
                return;
            }
            timer = setTimeout(() => {
                timer = null;
                search(query);
            }, debounceMs);
        }

        function selectIndex(index) {
            const entry = results[index];
            if (!entry) {
                return;
            }
            if (entry.kind === 'group') {
                openGroup(entry.role, entry.label);
                return;
            }
            if (entry.kind === 'back') {
                browse().then(() => setActive(0));
                return;
            }
            if (entry.kind === 'all') {
                // The host may refuse some of them (limits of the mail)
                const added = entry.recipients.filter(recipient =>
                    options.onSelect(recipient, { silent: true })
                ).length;
                refresh();
                announce(format(t('%d destinataires ajoutés.'), added));
                return;
            }
            if (isChosen(entry.recipient)) {
                return;
            }
            if (mode !== 'search') {
                // Browsing: the list stays open to add other recipients
                options.onSelect(entry.recipient);
                refresh();
                return;
            }
            cancelPending();
            close();
            input.value = '';
            clearError();
            options.onSelect(entry.recipient);
        }

        /** Opens the browsable list (participants and groups), its first option being active */
        function openBrowse() {
            if (input.value.trim().length >= MIN_QUERY_LENGTH) {
                return;
            }
            browse().then(() => {
                if (mode === 'browse' && isOpen()) {
                    setActive(0);
                }
            });
        }

        /**
         * Adds the typed addresses; invalid ones stay in the input with an error message
         * @returns {boolean} true when the input text was entirely added
         */
        function commitFreeText(text) {
            const parts = splitAddresses(text);
            if (!parts.length) {
                input.value = '';
                return true;
            }
            cancelPending();
            close();
            const invalid = [];
            parts.forEach(part => {
                if (isValidAddress(part)) {
                    options.onFreeEntry(part);
                } else {
                    invalid.push(part);
                }
            });
            input.value = invalid.join(', ');
            if (invalid.length) {
                setError(
                    format(
                        t('Adresse e-mail invalide : %s'),
                        invalid.join(', ')
                    )
                );
                return false;
            }
            clearError();
            return true;
        }

        function onKeyDown(event) {
            const hasText = input.value.trim() !== '';

            switch (event.key) {
                case 'ArrowDown':
                    if (
                        !isOpen() &&
                        input.value.trim().length < MIN_QUERY_LENGTH
                    ) {
                        event.preventDefault();
                        openBrowse();
                    } else if (results.length) {
                        event.preventDefault();
                        open();
                        setActive(activeIndex + 1);
                    }
                    break;
                case 'ArrowUp':
                    if (isOpen() && results.length) {
                        event.preventDefault();
                        setActive(activeIndex <= 0 ? 0 : activeIndex - 1);
                    }
                    break;
                case 'Home':
                case 'End':
                    if (isOpen() && activeIndex >= 0) {
                        event.preventDefault();
                        setActive(
                            event.key === 'Home' ? 0 : results.length - 1
                        );
                    }
                    break;
                case 'Enter':
                    if (isOpen() && activeIndex >= 0) {
                        event.preventDefault();
                        selectIndex(activeIndex);
                    } else if (
                        isOpen() &&
                        mode === 'search' &&
                        results.length === 1
                    ) {
                        // A single suggestion: Enter chooses it
                        event.preventDefault();
                        selectIndex(0);
                    } else if (hasText) {
                        event.preventDefault();
                        commitFreeText(input.value);
                    }
                    break;
                case 'Tab':
                    // The focus is never trapped: the typed text is only added
                    if (isOpen() && mode === 'search' && activeIndex >= 0) {
                        selectIndex(activeIndex);
                    } else {
                        commitTypedAddresses();
                    }
                    break;
                case ',':
                case ';':
                    if (hasText) {
                        event.preventDefault();
                        commitFreeText(input.value);
                    }
                    break;
                case 'Escape':
                    if (isOpen()) {
                        event.preventDefault();
                        event.stopPropagation();
                        cancelPending();
                        close();
                    } else if (input.value !== '') {
                        event.preventDefault();
                        event.stopPropagation();
                        input.value = '';
                        clearError();
                    }
                    break;
                case 'Backspace':
                    if (input.value === '' && options.onBackspaceEmpty) {
                        event.preventDefault();
                        options.onBackspaceEmpty();
                    }
                    break;
                default:
                    break;
            }
        }

        function onPaste(event) {
            const data = event.clipboardData
                ? event.clipboardData.getData('text')
                : '';
            if (!SEPARATORS.test(data)) {
                return;
            }
            event.preventDefault();
            commitFreeText(input.value + data);
        }

        /**
         * Leaving the input adds what looks like addresses only: a name typed without choosing a
         * suggestion stays in the input, without error
         */
        function commitTypedAddresses() {
            if (input.value.includes('@')) {
                commitFreeText(input.value);
            }
        }

        function onBlur() {
            cancelPending();
            close();
            commitTypedAddresses();
        }

        // Clicking a suggestion must not blur the input (the blur would add the typed text)
        function onListboxMouseDown(event) {
            event.preventDefault();
        }

        function onListboxClick(event) {
            const option = event.target.closest('[role="option"]');
            if (option) {
                selectIndex(Number(option.dataset.index));
                input.focus();
            }
        }

        input.addEventListener('keydown', onKeyDown);
        input.addEventListener('input', () => {
            clearError();
            scheduleSearch();
        });
        input.addEventListener('paste', onPaste);
        input.addEventListener('click', () => {
            if (!isOpen() && input.value.trim() === '') {
                openBrowse();
            }
        });
        // Keeps the focus in the input: its blur would close the list
        toggle.addEventListener('mousedown', event => event.preventDefault());
        toggle.addEventListener('click', () => {
            if (isOpen()) {
                cancelPending();
                close();
            } else {
                openBrowse();
            }
            input.focus();
        });
        input.addEventListener('blur', onBlur);
        listbox.addEventListener('mousedown', onListboxMouseDown);
        listbox.addEventListener('click', onListboxClick);

        return {
            input,
            listbox,
            toggle,
            status,
            announce,
            close,
            commitFreeText,
        };
    }

    const api = {
        ENDPOINT,
        MIN_QUERY_LENGTH,
        DEBOUNCE_MS,
        STAFF_GROUPS,
        attach,
        extractEmail,
        fetchRecipients,
        fieldLabel,
        format,
        isValidAddress,
        renderRecipientContent,
        splitAddresses,
        t,
    };

    root.EpMailCombobox = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : globalThis);
