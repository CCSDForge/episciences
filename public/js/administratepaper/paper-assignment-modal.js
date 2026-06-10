'use strict';

// Single <dialog> element reused across all three form types
let _dialog = null;
// Tom Select instances active in the current modal; destroyed on close
let _activeTomSelects = [];
// True while a modal open sequence is in progress; prevents concurrent openers
let _isOpen = false;
// Incremented on every close; pending fetch callbacks compare against their snapshot to detect stale calls
let _callToken = 0;

/**
 * Create a DOM element with optional attributes and text content.
 * @param {string} tag
 * @param {Object} [attrs]
 * @param {string} [text]
 * @returns {HTMLElement}
 */
function _el(tag, attrs, text) {
    var node = document.createElement(tag);
    if (attrs) {
        Object.keys(attrs).forEach(function (k) { node.setAttribute(k, attrs[k]); });
    }
    if (text !== undefined) node.textContent = text;
    return node;
}

/**
 * Return (creating on first call) the single <dialog> element used for all
 * volume and section assignment forms.
 * @returns {HTMLDialogElement}
 */
function _ensureDialog() {
    if (_dialog) return _dialog;

    _dialog = _el('dialog', { class: 'paper-modal', 'aria-modal': 'true', 'aria-labelledby': 'paper-modal-title' });

    var header = _el('div', { class: 'paper-modal__header' });
    var titleEl = _el('h2', { class: 'paper-modal__title', id: 'paper-modal-title' });
    var closeBtn = _el('button', { type: 'button', class: 'paper-modal__close', 'aria-label': translate('Fermer') });
    closeBtn.appendChild(_el('span', { 'aria-hidden': 'true' }, '×'));
    header.appendChild(titleEl);
    header.appendChild(closeBtn);

    var body = _el('div', { class: 'paper-modal__body' });

    var footer = _el('div', { class: 'paper-modal__footer' });
    var cancelBtn = _el('button', { type: 'button', class: 'btn btn-default paper-modal__cancel' }, translate('Annuler'));
    var saveBtn = _el('button', { type: 'submit', class: 'btn btn-primary paper-modal__save' }, translate('Enregistrer'));
    footer.appendChild(cancelBtn);
    footer.appendChild(saveBtn);

    _dialog.appendChild(header);
    _dialog.appendChild(body);
    _dialog.appendChild(footer);
    document.body.appendChild(_dialog);

    closeBtn.addEventListener('click', _closeDialog);
    cancelBtn.addEventListener('click', _closeDialog);

    // Close when clicking directly on the backdrop (outside the dialog box)
    _dialog.addEventListener('click', function (e) {
        if (e.target === _dialog) _closeDialog();
    });

    // Native <dialog> fires 'close' on Escape; reset state so next open works
    _dialog.addEventListener('close', function () {
        _isOpen = false;
        _callToken++;
        _activeTomSelects.forEach(function (ts) { try { ts.destroy(); } catch (e) { /* ignore */ } });
        _activeTomSelects = [];
    });

    return _dialog;
}

/**
 * Close the modal and destroy any active Tom Select instances.
 */
function _closeDialog() {
    _isOpen = false;
    _callToken++;
    _activeTomSelects.forEach(function (ts) { try { ts.destroy(); } catch (e) { /* ignore */ } });
    _activeTomSelects = [];
    if (_dialog && _dialog.open) _dialog.close();
}

/**
 * Populate the dialog body with server-rendered form HTML and open the modal.
 * bodyHtml originates from our own PHP templates which escape all user content
 * via $this->escape() — it is trusted server-rendered markup.
 * @param {string} title
 * @param {string} bodyHtml
 * @param {string} formId
 */
function _showModal(title, bodyHtml, formId) {
    var dialog = _ensureDialog();
    dialog.querySelector('.paper-modal__title').textContent = title;
    // Server-rendered PHP template output (all user values escaped server-side)
    // eslint-disable-next-line no-unsanitized/property
    dialog.querySelector('.paper-modal__body').innerHTML = bodyHtml;
    dialog.querySelector('.paper-modal__save').setAttribute('form', formId);
    if (!dialog.open) dialog.showModal();
}

/**
 * Show a loading placeholder while the form is being fetched.
 * @param {string} title
 */
function _showLoading(title) {
    var dialog = _ensureDialog();
    dialog.querySelector('.paper-modal__title').textContent = title;
    dialog.querySelector('.paper-modal__body').textContent = translate('Chargement…');
    dialog.querySelector('.paper-modal__save').removeAttribute('form');
    if (!dialog.open) dialog.showModal();
}

/**
 * POST body to url via fetch and return the response text.
 * @param {string} url
 * @param {URLSearchParams} params
 * @returns {Promise<string>}
 */
function _post(url, params) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            // ZF1 uses isXmlHttpRequest() to identify AJAX calls; without this
            // header the router/auth layer may return a redirect or 404.
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: params.toString(),
    }).then(function (resp) {
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        return resp.text();
    });
}

/**
 * Initialize Tom Select in single-select mode.
 * @param {HTMLSelectElement} selectEl
 * @returns {object|null}
 */
function _initTomSelectSingle(selectEl) {
    if (typeof TomSelect === 'undefined') return null;
    var ts = new TomSelect(selectEl, {
        plugins: ['dropdown_input', 'remove_button'],
        maxOptions: null,
        create: false,
        allowEmptyOption: true,
        onItemAdd: function () { this.setTextboxValue(''); },
    });
    selectEl.style.setProperty('display', 'none', 'important');
    ts.wrapper.classList.remove('form-control');
    _activeTomSelects.push(ts);
    return ts;
}

/**
 * Initialize Tom Select in multi-select mode with the checkbox_options plugin.
 * @param {HTMLSelectElement} selectEl
 * @returns {object|null}
 */
function _initTomSelectMulti(selectEl) {
    if (typeof TomSelect === 'undefined') return null;
    var ts = new TomSelect(selectEl, {
        plugins: ['checkbox_options', 'dropdown_input', 'remove_button'],
        maxOptions: null,
        create: false,
        onItemAdd: function () { this.setTextboxValue(''); },
    });
    selectEl.style.setProperty('display', 'none', 'important');
    ts.wrapper.classList.remove('form-control');
    _activeTomSelects.push(ts);
    return ts;
}

/**
 * Wire the "assign editors" checkbox to the section <select>.
 * Each <option> carries data-has-editors="true|false".
 * Fires once immediately to reflect any pre-selected section.
 */
function _initSectionAssignEditors() {
    var select = document.getElementById('section_select');
    var checkbox = document.getElementById('assignEditors');
    if (!select || !checkbox) return;

    var wrapper = checkbox.closest('.checkbox');
    if (!wrapper) return;

    function update() {
        var sid = select.value;
        wrapper.hidden = false;
        if (!sid) {
            checkbox.disabled = true;
            checkbox.checked = false;
            wrapper.removeAttribute('title');
            return;
        }
        var selected = select.options[select.selectedIndex];
        var hasEditors = selected && selected.dataset.hasEditors === 'true';
        if (hasEditors) {
            checkbox.disabled = false;
            checkbox.checked = true;
            wrapper.removeAttribute('title');
        } else {
            checkbox.disabled = true;
            checkbox.checked = false;
            wrapper.title = translate("Cette rubrique n'a pas de rédacteurs assignés");
        }
    }

    select.addEventListener('change', update);
    // Tom Select fires native change events on the underlying <select>
    update();
}

// ---------------------------------------------------------------------------
// Partial-refresh helpers (read from DB after save — no user content in params)
// ---------------------------------------------------------------------------

function _refreshOtherVolumes(docid) {
    _post('/administratepaper/refreshothervolumes', new URLSearchParams({ docid: docid }))
        .then(function (html) {
            var container = document.getElementById('other_volumes_list_' + docid);
            // Server-rendered list of volume names (escaped PHP output)
            if (container) container.innerHTML = html;
        });
}

function _refreshPaperHistory(docid) {
    if (typeof refreshPaperHistory === 'function') refreshPaperHistory(docid);
}

function _refreshMasterVolumesInList(docid, newVid, oldVid) {
    _post('/administratepaper/refreshallmastervolumes', new URLSearchParams({
        docid: docid,
        vid: newVid,
        old_vid: oldVid,
        from: 'list',
    })).then(function (result) {
        if (!result) return;
        var parsed;
        try { parsed = JSON.parse(result); } catch (e) { return; }
        Object.keys(parsed).forEach(function (idx) {
            var container = document.getElementById('master_volume_name_' + idx);
            // Server-rendered volume name (escaped PHP output)
            if (container) container.innerHTML = parsed[idx]; // eslint-disable-line no-unsanitized/property
        });
    });
}

function _refreshMasterVolumeView(docid, newVid) {
    _post('/administratepaper/refreshmastervolume', new URLSearchParams({
        docId: docid,
        vid: newVid,
        from: 'view',
    })).then(function (html) {
        var container = document.getElementById('master_volume_name_' + docid);
        // Server-rendered volume name + position (escaped PHP output)
        if (container) container.innerHTML = html; // eslint-disable-line no-unsanitized/property
        // Keep data-vid in sync so the next modal open passes the correct oldVid
        var btn = document.querySelector('[data-modal="master-volume"][data-docid="' + docid + '"]');
        if (btn) btn.dataset.vid = newVid;
    });
}

function _refreshSectionBlock(docid, isPartial) {
    _post('/administratepaper/displaysection', new URLSearchParams({
        docid: docid,
        partial: isPartial ? '1' : '0',
    })).then(function (html) {
        // Update all .section containers visible on screen
        document.querySelectorAll('.section').forEach(function (c) {
            // Server-rendered section block (escaped PHP output)
            c.innerHTML = html;
        });
    });
}

function _refreshEditorsBlock(docid, isPartial) {
    _post('/administratepaper/displayeditors', new URLSearchParams({
        docid: docid,
        partial: isPartial ? '1' : '0',
    })).then(function (html) {
        var container = document.getElementById('editors');
        // Server-rendered editors block (escaped PHP output)
        if (container) container.innerHTML = html;
    });
}

// ---------------------------------------------------------------------------
// Form openers
// ---------------------------------------------------------------------------

/**
 * Fetch and open the master volume assignment modal.
 * @param {HTMLElement} btn  Must carry data-docid, data-vid, data-partial
 */
function openVolumeModal(btn) {
    if (_isOpen) return;
    _isOpen = true;

    var docid = btn.dataset.docid;
    var oldVid = btn.dataset.vid || '';
    var isPartial = btn.dataset.partial === 'true';
    var title = translate('Volume principal');
    var token = ++_callToken;

    _showLoading(title);

    _post('/administratepaper/volumeform', new URLSearchParams({ docid: docid }))
        .then(function (html) {
            if (_callToken !== token) return;
            var formId = 'volume-form-' + docid;
            _showModal(title, html, formId);

            var select = document.getElementById('master_volume_select');
            if (select) _initTomSelectSingle(select);

            var form = document.getElementById(formId);
            if (!form) return;

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (form.dataset.submitting) return;
                form.dataset.submitting = '1';

                var vidSelect = document.getElementById('master_volume_select');
                var newVid = vidSelect ? vidSelect.value : '';

                _post('/administratepaper/savemastervolume', new URLSearchParams({ docid: docid, vid: newVid }))
                    .then(function (result) {
                        if (parseInt(result, 10) !== 1) return;
                        _closeDialog();
                        if (isPartial) {
                            // Keep data-vid in sync so the next modal open has the correct oldVid
                            btn.dataset.vid = newVid;
                            _refreshMasterVolumesInList(docid, newVid, oldVid);
                        } else {
                            _refreshMasterVolumeView(docid, newVid);
                        }
                    })
                    .catch(function () { delete form.dataset.submitting; });
            });
        })
        .catch(_closeDialog);
}

/**
 * Fetch and open the secondary volumes assignment modal.
 * @param {HTMLElement} btn  Must carry data-docid, data-partial
 */
function openOtherVolumesModal(btn) {
    if (_isOpen) return;
    _isOpen = true;

    var docid = btn.dataset.docid;
    var title = translate('Volumes secondaires');
    var token = ++_callToken;

    _showLoading(title);

    _post('/administratepaper/othervolumesform', new URLSearchParams({ docid: docid }))
        .then(function (html) {
            if (_callToken !== token) return;
            var formId = 'volumes-form-' + docid;
            _showModal(title, html, formId);

            var select = document.getElementById('other_volumes_select');
            if (select) _initTomSelectMulti(select);

            var form = document.getElementById(formId);
            if (!form) return;

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (form.dataset.submitting) return;
                form.dataset.submitting = '1';

                var data = new URLSearchParams(new FormData(form));
                data.set('docid', docid);

                _post('/administratepaper/saveothervolumes', data)
                    .then(function (result) {
                        if (result.trim() !== '1') return;
                        _closeDialog();
                        _refreshOtherVolumes(docid);
                        _refreshPaperHistory(docid);
                    })
                    .catch(function () { delete form.dataset.submitting; });
            });
        })
        .catch(_closeDialog);
}

/**
 * Fetch and open the section assignment modal.
 * @param {HTMLElement} btn  Must carry data-docid, data-partial
 */
function openSectionModal(btn) {
    if (_isOpen) return;
    _isOpen = true;

    var docid = btn.dataset.docid;
    var isPartial = btn.dataset.partial === 'true';
    var title = translate('Rubrique');
    var token = ++_callToken;

    _showLoading(title);

    _post('/administratepaper/sectionform', new URLSearchParams({ docid: docid }))
        .then(function (html) {
            if (_callToken !== token) return;
            var formId = 'section-assignment-form-' + docid;
            _showModal(title, html, formId);

            var select = document.getElementById('section_select');
            if (select) _initTomSelectSingle(select);

            _initSectionAssignEditors();

            var form = document.getElementById(formId);
            if (!form) return;

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (form.dataset.submitting) return;
                form.dataset.submitting = '1';

                var assignEditors = document.getElementById('assignEditors');
                var shouldRefreshEditors = assignEditors && assignEditors.checked && !assignEditors.disabled;

                var data = new URLSearchParams(new FormData(form));
                data.set('docid', docid);

                _post('/administratepaper/savesection', data)
                    .then(function (result) {
                        var ok = result && result.trim() !== '' && result.trim() !== 'false' && result.trim() !== '0';
                        if (!ok) return;
                        _closeDialog();
                        _refreshSectionBlock(docid, isPartial);
                        if (shouldRefreshEditors) _refreshEditorsBlock(docid, isPartial);
                    })
                    .catch(function () { delete form.dataset.submitting; });
            });
        })
        .catch(_closeDialog);
}

// ---------------------------------------------------------------------------
// Bootstrap: event delegation for [data-modal] buttons
// Guard prevents duplicate listeners when the script is evaluated more than once
// ---------------------------------------------------------------------------

if (!window._paperAssignmentModalLoaded) {
    window._paperAssignmentModalLoaded = true;

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-modal]');
        if (!btn) return;
        if (btn.disabled || btn.getAttribute('aria-disabled') === 'true') return;

        // Dismiss any Bootstrap tooltips or popovers open in the background;
        // they stay visible behind the native <dialog> top-layer backdrop otherwise.
        document.querySelectorAll('.tooltip, .popover').forEach(function (el) {
            if (el.parentNode) el.parentNode.removeChild(el);
        });

        var type = btn.dataset.modal;
        if (type === 'master-volume') openVolumeModal(btn);
        else if (type === 'other-volumes') openOtherVolumesModal(btn);
        else if (type === 'section') openSectionModal(btn);
    });
}

// ---------------------------------------------------------------------------
// Exports for Jest unit tests
// ---------------------------------------------------------------------------

if (typeof module !== 'undefined') {
    module.exports = {
        _ensureDialog,
        _closeDialog,
        _showModal,
        _initTomSelectSingle,
        _initTomSelectMulti,
        _initSectionAssignEditors,
        _refreshMasterVolumeView,
        openVolumeModal,
        openOtherVolumesModal,
        openSectionModal,
    };
}
