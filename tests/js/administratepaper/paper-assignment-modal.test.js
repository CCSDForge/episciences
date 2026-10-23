'use strict';

// ---------------------------------------------------------------------------
// Globals required by paper-assignment-modal.js
// ---------------------------------------------------------------------------

let tomSelectInstances = [];
global.TomSelect = jest.fn().mockImplementation(function (el, opts) {
    const wrapper = document.createElement('div');
    wrapper.classList.add('ts-wrapper');
    const instance = { el, opts, wrapper, destroy: jest.fn(), setTextboxValue: jest.fn() };
    tomSelectInstances.push(instance);
    return instance;
});

global.translate = (s) => s;
global.refreshPaperHistory = jest.fn();
global.fetch = jest.fn();

// ---------------------------------------------------------------------------
// Load the module under test
// ---------------------------------------------------------------------------

const {
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
} = require('../../../public/js/administratepaper/paper-assignment-modal');

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function mockFetchOk(text) {
    global.fetch = jest.fn().mockResolvedValue({
        ok: true,
        text: () => Promise.resolve(text),
    });
}


function flushPromises() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function mockFetchFail() {
    global.fetch = jest.fn().mockResolvedValue({ ok: false, status: 500, text: () => Promise.resolve('') });
}

function buildSectionDOM(sid, sections) {
    const select = document.createElement('select');
    select.id = 'section_select';

    const emptyOpt = document.createElement('option');
    emptyOpt.value = '';
    emptyOpt.textContent = 'Hors rubrique';
    select.appendChild(emptyOpt);

    (sections || []).forEach(function (s) {
        const opt = document.createElement('option');
        opt.value = String(s.value);
        opt.textContent = s.text;
        opt.dataset.hasEditors = s.hasEditors ? 'true' : 'false';
        select.appendChild(opt);
    });

    if (sid) select.value = String(sid);

    const wrapper = document.createElement('div');
    wrapper.className = 'checkbox';
    wrapper.hidden = true;
    const label = document.createElement('label');
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.id = 'assignEditors';
    checkbox.name = 'assignEditors';
    label.appendChild(checkbox);
    wrapper.appendChild(label);

    document.body.appendChild(select);
    document.body.appendChild(wrapper);
    return { select, checkbox, checkboxWrapper: wrapper };
}

function makeFormDOM(formId) {
    const form = document.createElement('form');
    form.id = formId;
    document.body.appendChild(form);
    return form;
}

function makeSelectDOM(selectId) {
    const select = document.createElement('select');
    select.id = selectId;
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = 'None';
    select.appendChild(opt);
    document.body.appendChild(select);
    return select;
}

// ---------------------------------------------------------------------------
// Reset
// ---------------------------------------------------------------------------

beforeEach(() => {
    document.body.innerHTML = '';
    tomSelectInstances = [];
    TomSelect.mockClear();
    _closeDialog();
});

// ---------------------------------------------------------------------------
// _ensureDialog
// ---------------------------------------------------------------------------

describe('_ensureDialog', () => {
    test('creates a dialog element and appends it to body', () => {
        const dialog = _ensureDialog();
        expect(dialog.tagName).toBe('DIALOG');
        expect(document.body.contains(dialog)).toBe(true);
    });

    test('sets aria-modal and aria-labelledby attributes', () => {
        const dialog = _ensureDialog();
        expect(dialog.getAttribute('aria-modal')).toBe('true');
        expect(dialog.getAttribute('aria-labelledby')).toBe('paper-modal-title');
    });

    test('contains header, body, and footer sections', () => {
        const dialog = _ensureDialog();
        expect(dialog.querySelector('.paper-modal__header')).not.toBeNull();
        expect(dialog.querySelector('.paper-modal__body')).not.toBeNull();
        expect(dialog.querySelector('.paper-modal__footer')).not.toBeNull();
    });

    test('contains a title element with id paper-modal-title', () => {
        const dialog = _ensureDialog();
        expect(dialog.querySelector('#paper-modal-title')).not.toBeNull();
    });

    test('contains close, cancel, and save buttons', () => {
        const dialog = _ensureDialog();
        expect(dialog.querySelector('.paper-modal__close')).not.toBeNull();
        expect(dialog.querySelector('.paper-modal__cancel')).not.toBeNull();
        expect(dialog.querySelector('.paper-modal__save')).not.toBeNull();
    });

    test('returns the same instance on repeated calls (singleton)', () => {
        expect(_ensureDialog()).toBe(_ensureDialog());
    });
});

// ---------------------------------------------------------------------------
// _closeDialog
// ---------------------------------------------------------------------------

describe('_closeDialog', () => {
    test('calls destroy() on all active Tom Select instances', () => {
        const s1 = makeSelectDOM('s1');
        const s2 = makeSelectDOM('s2');
        _initTomSelectSingle(s1);
        _initTomSelectMulti(s2);

        _closeDialog();

        tomSelectInstances.forEach(ts => expect(ts.destroy).toHaveBeenCalledTimes(1));
    });

    test('calls dialog.close() when the dialog is open', () => {
        const dialog = _ensureDialog();
        dialog.close = jest.fn();
        Object.defineProperty(dialog, 'open', { get: () => true, configurable: true });

        _closeDialog();

        expect(dialog.close).toHaveBeenCalled();
    });

    test('does not throw when no Tom Select instances are active', () => {
        expect(() => _closeDialog()).not.toThrow();
    });
});

// ---------------------------------------------------------------------------
// _showModal
// ---------------------------------------------------------------------------

describe('_showModal', () => {
    test('sets the dialog title as textContent', () => {
        _showModal('My Title', '', 'form-1');
        expect(_ensureDialog().querySelector('.paper-modal__title').textContent).toBe('My Title');
    });

    test('sets the save button form attribute to the formId', () => {
        _showModal('Title', '', 'my-form-id');
        expect(_ensureDialog().querySelector('.paper-modal__save').getAttribute('form')).toBe('my-form-id');
    });
});

// ---------------------------------------------------------------------------
// _initTomSelectSingle
// ---------------------------------------------------------------------------

describe('_initTomSelectSingle', () => {
    test('instantiates TomSelect with allowEmptyOption: true', () => {
        _initTomSelectSingle(makeSelectDOM('sel1'));
        expect(TomSelect).toHaveBeenCalledTimes(1);
        expect(TomSelect.mock.calls[0][1].allowEmptyOption).toBe(true);
    });

    test('sets maxOptions: null and create: false', () => {
        _initTomSelectSingle(makeSelectDOM('sel2'));
        const opts = TomSelect.mock.calls[0][1];
        expect(opts.maxOptions).toBeNull();
        expect(opts.create).toBe(false);
    });

    test('includes dropdown_input plugin', () => {
        _initTomSelectSingle(makeSelectDOM('sel4'));
        expect(TomSelect.mock.calls[0][1].plugins).toContain('dropdown_input');
    });

    test('includes remove_button plugin', () => {
        _initTomSelectSingle(makeSelectDOM('sel5'));
        expect(TomSelect.mock.calls[0][1].plugins).toContain('remove_button');
    });

    test('returns null when TomSelect is not defined', () => {
        const orig = global.TomSelect;
        delete global.TomSelect;
        expect(_initTomSelectSingle(makeSelectDOM('sel3'))).toBeNull();
        global.TomSelect = orig;
    });
});

// ---------------------------------------------------------------------------
// _initTomSelectMulti
// ---------------------------------------------------------------------------

describe('_initTomSelectMulti', () => {
    test('includes checkbox_options in plugins', () => {
        _initTomSelectMulti(makeSelectDOM('m1'));
        expect(TomSelect.mock.calls[0][1].plugins).toContain('checkbox_options');
    });

    test('includes dropdown_input in plugins', () => {
        _initTomSelectMulti(makeSelectDOM('m3'));
        expect(TomSelect.mock.calls[0][1].plugins).toContain('dropdown_input');
    });

    test('includes remove_button in plugins', () => {
        _initTomSelectMulti(makeSelectDOM('m4'));
        expect(TomSelect.mock.calls[0][1].plugins).toContain('remove_button');
    });

    test('sets maxOptions: null and create: false', () => {
        _initTomSelectMulti(makeSelectDOM('m2'));
        const opts = TomSelect.mock.calls[0][1];
        expect(opts.maxOptions).toBeNull();
        expect(opts.create).toBe(false);
    });
});

// ---------------------------------------------------------------------------
// _initSectionAssignEditors
// ---------------------------------------------------------------------------

describe('_initSectionAssignEditors', () => {
    afterEach(() => { document.body.innerHTML = ''; });

    test('shows checkbox wrapper as disabled+unchecked when no section is selected', () => {
        const { checkboxWrapper, checkbox } = buildSectionDOM('', [{ value: 1, text: 'S1', hasEditors: true }]);
        _initSectionAssignEditors();
        expect(checkboxWrapper.hidden).toBe(false);
        expect(checkbox.disabled).toBe(true);
        expect(checkbox.checked).toBe(false);
    });

    test('shows and enables checkbox when selected section has editors', () => {
        const { checkboxWrapper, checkbox } = buildSectionDOM(1, [{ value: 1, text: 'S1', hasEditors: true }]);
        _initSectionAssignEditors();
        expect(checkboxWrapper.hidden).toBe(false);
        expect(checkbox.disabled).toBe(false);
        expect(checkbox.checked).toBe(true);
    });

    test('shows but disables checkbox when selected section has no editors', () => {
        const { checkboxWrapper, checkbox } = buildSectionDOM(1, [{ value: 1, text: 'S1', hasEditors: false }]);
        _initSectionAssignEditors();
        expect(checkboxWrapper.hidden).toBe(false);
        expect(checkbox.disabled).toBe(true);
        expect(checkbox.checked).toBe(false);
    });

    test('disables and unchecks checkbox when select changes back to empty', () => {
        const { select, checkboxWrapper, checkbox } = buildSectionDOM(1, [{ value: 1, text: 'S1', hasEditors: true }]);
        _initSectionAssignEditors();
        // Initially a section with editors is selected → enabled + checked
        expect(checkbox.disabled).toBe(false);
        select.value = '';
        select.dispatchEvent(new Event('change'));
        expect(checkboxWrapper.hidden).toBe(false);
        expect(checkbox.disabled).toBe(true);
        expect(checkbox.checked).toBe(false);
    });

    test('enables checkbox when switching to a section that has editors', () => {
        const { select, checkbox } = buildSectionDOM(1, [
            { value: 1, text: 'No editors', hasEditors: false },
            { value: 2, text: 'Has editors', hasEditors: true },
        ]);
        _initSectionAssignEditors();
        expect(checkbox.disabled).toBe(true);

        select.value = '2';
        select.dispatchEvent(new Event('change'));
        expect(checkbox.disabled).toBe(false);
        expect(checkbox.checked).toBe(true);
    });

    test('does not throw when section_select is absent', () => {
        expect(() => _initSectionAssignEditors()).not.toThrow();
    });
});

// ---------------------------------------------------------------------------
// Event delegation
// ---------------------------------------------------------------------------

describe('event delegation', () => {
    test('click on data-modal="master-volume" button calls fetch for volumeform', () => {
        mockFetchOk('');
        const btn = document.createElement('button');
        btn.dataset.modal = 'master-volume';
        btn.dataset.docid = '42';
        btn.dataset.vid = '0';
        btn.dataset.partial = 'false';
        document.body.appendChild(btn);

        btn.click();

        expect(fetch).toHaveBeenCalledWith(
            '/administratepaper/volumeform',
            expect.objectContaining({ method: 'POST' })
        );
    });

    test('click on data-modal="other-volumes" button calls fetch for othervolumesform', () => {
        mockFetchOk('');
        const btn = document.createElement('button');
        btn.dataset.modal = 'other-volumes';
        btn.dataset.docid = '7';
        btn.dataset.partial = 'false';
        document.body.appendChild(btn);

        btn.click();

        expect(fetch).toHaveBeenCalledWith(
            '/administratepaper/othervolumesform',
            expect.objectContaining({ method: 'POST' })
        );
    });

    test('click on data-modal="section" button calls fetch for sectionform', () => {
        mockFetchOk('');
        const btn = document.createElement('button');
        btn.dataset.modal = 'section';
        btn.dataset.docid = '3';
        btn.dataset.partial = 'false';
        document.body.appendChild(btn);

        btn.click();

        expect(fetch).toHaveBeenCalledWith(
            '/administratepaper/sectionform',
            expect.objectContaining({ method: 'POST' })
        );
    });

    test('disabled buttons are ignored', () => {
        global.fetch = jest.fn();
        const btn = document.createElement('button');
        btn.dataset.modal = 'master-volume';
        btn.dataset.docid = '99';
        btn.disabled = true;
        document.body.appendChild(btn);

        btn.click();
        expect(fetch).not.toHaveBeenCalled();
    });

    test('aria-disabled="true" buttons are ignored', () => {
        global.fetch = jest.fn();
        const btn = document.createElement('button');
        btn.dataset.modal = 'section';
        btn.dataset.docid = '5';
        btn.setAttribute('aria-disabled', 'true');
        document.body.appendChild(btn);

        btn.click();
        expect(fetch).not.toHaveBeenCalled();
    });
});

// ---------------------------------------------------------------------------
// openVolumeModal
// ---------------------------------------------------------------------------

describe('openVolumeModal', () => {
    test('POSTs to volumeform with the docid', () => {
        mockFetchOk('');
        const btn = document.createElement('button');
        btn.dataset.docid = '55';
        btn.dataset.vid = '';
        btn.dataset.partial = 'false';

        openVolumeModal(btn);

        expect(fetch).toHaveBeenCalledWith(
            '/administratepaper/volumeform',
            expect.objectContaining({ body: expect.stringContaining('docid=55') })
        );
    });

    test('sets the modal title when opening the volume form', async () => {
        makeFormDOM('volume-form-10');
        makeSelectDOM('master_volume_select');
        mockFetchOk('');

        const btn = document.createElement('button');
        btn.dataset.docid = '10';
        btn.dataset.vid = '0';
        btn.dataset.partial = 'false';

        openVolumeModal(btn);

        // The loading title is set synchronously
        expect(_ensureDialog().querySelector('.paper-modal__title').textContent).toBe('Volume principal');
    });

    test('does not throw when fetch fails', async () => {
        mockFetchFail();
        const btn = document.createElement('button');
        btn.dataset.docid = '11';
        btn.dataset.vid = '0';
        btn.dataset.partial = 'false';

        await expect(async () => {
            openVolumeModal(btn);
            await Promise.resolve();
            await Promise.resolve();
        }).not.toThrow();
    });

    test('second call while open is ignored (concurrent guard)', () => {
        mockFetchOk('');
        const btn = document.createElement('button');
        btn.dataset.docid = '60';
        btn.dataset.vid = '';
        btn.dataset.partial = 'false';

        openVolumeModal(btn);
        openVolumeModal(btn);

        expect(fetch).toHaveBeenCalledTimes(1);
    });

    test('does not inject fetched HTML after the dialog was closed during loading', async () => {
        mockFetchOk('<p id="sentinel">fetched</p>');
        const btn = document.createElement('button');
        btn.dataset.docid = '70';
        btn.dataset.vid = '';
        btn.dataset.partial = 'false';

        openVolumeModal(btn);
        _closeDialog();
        await flushPromises();

        // If the token guard works, the body should NOT contain the fetched HTML
        expect(_ensureDialog().querySelector('#sentinel')).toBeNull();
    });

    test('on view page (partial=false) save calls refreshmastervolume, not page reload', async () => {
        // Form and select added directly to body (pattern followed by other submit tests)
        const form = makeFormDOM('volume-form-80');
        const select = makeSelectDOM('master_volume_select');
        const opt = document.createElement('option');
        opt.value = '3';
        opt.selected = true;
        select.appendChild(opt);
        form.appendChild(select);

        const nameSpan = document.createElement('span');
        nameSpan.id = 'master_volume_name_80';
        nameSpan.textContent = 'Volume 1';
        document.body.appendChild(nameSpan);

        // fetch 1: volumeform (returns '' — form is already in DOM)
        // fetch 2: savemastervolume → '1'
        // fetch 3: refreshmastervolume → volume name
        global.fetch = jest.fn()
            .mockResolvedValueOnce({ ok: true, text: () => Promise.resolve('') })
            .mockResolvedValueOnce({ ok: true, text: () => Promise.resolve('1') })
            .mockResolvedValueOnce({ ok: true, text: () => Promise.resolve('Volume 3') });

        const btn = document.createElement('button');
        btn.dataset.docid = '80';
        btn.dataset.vid = '1';
        btn.dataset.partial = 'false';

        openVolumeModal(btn);
        await flushPromises();

        form.dispatchEvent(new Event('submit', { bubbles: true }));
        await flushPromises();

        // Should have called refreshmastervolume (3rd fetch), NOT location.replace
        expect(fetch).toHaveBeenCalledTimes(3);
        expect(fetch.mock.calls[2][0]).toBe('/administratepaper/refreshmastervolume');
        expect(fetch.mock.calls[2][1].body).toContain('from=view');
    });
});

// ---------------------------------------------------------------------------
// _refreshMasterVolumeView
// ---------------------------------------------------------------------------

describe('_refreshMasterVolumeView', () => {
    test('updates #master_volume_name_{docid} with server HTML', async () => {
        mockFetchOk('Volume 5');
        const span = document.createElement('span');
        span.id = 'master_volume_name_99';
        span.textContent = 'Volume 1';
        document.body.appendChild(span);

        _refreshMasterVolumeView('99', '5');
        await flushPromises();

        expect(document.getElementById('master_volume_name_99').innerHTML).toBe('Volume 5');
    });

    test('updates btn.dataset.vid after refresh', async () => {
        mockFetchOk('Volume 5');
        const span = document.createElement('span');
        span.id = 'master_volume_name_98';
        document.body.appendChild(span);

        const btn = document.createElement('button');
        btn.dataset.modal = 'master-volume';
        btn.dataset.docid = '98';
        btn.dataset.vid = '1';
        document.body.appendChild(btn);

        _refreshMasterVolumeView('98', '5');
        await flushPromises();

        expect(btn.dataset.vid).toBe('5');
    });

    test('POSTs to refreshmastervolume with docId (capital D), vid, and from=view', () => {
        mockFetchOk('');
        _refreshMasterVolumeView('42', '7');

        expect(fetch).toHaveBeenCalledWith(
            '/administratepaper/refreshmastervolume',
            expect.objectContaining({
                body: expect.stringMatching(/docId=42.*vid=7.*from=view|from=view.*docId=42/),
            })
        );
    });
});

// ---------------------------------------------------------------------------
// openOtherVolumesModal
// ---------------------------------------------------------------------------

describe('openOtherVolumesModal', () => {
    test('POSTs to othervolumesform with the docid', () => {
        mockFetchOk('');
        const btn = document.createElement('button');
        btn.dataset.docid = '31';
        btn.dataset.partial = 'false';

        openOtherVolumesModal(btn);

        expect(fetch).toHaveBeenCalledWith(
            '/administratepaper/othervolumesform',
            expect.objectContaining({ body: expect.stringContaining('docid=31') })
        );
    });

    test('initializes multi Tom Select with checkbox_options after fetch', async () => {
        const form = makeFormDOM('volumes-form-30');
        const select = makeSelectDOM('other_volumes_select');
        select.multiple = true;
        form.appendChild(select);
        mockFetchOk('');

        const btn = document.createElement('button');
        btn.dataset.docid = '30';
        btn.dataset.partial = 'false';

        openOtherVolumesModal(btn);
        await flushPromises();

        expect(TomSelect).toHaveBeenCalledTimes(1);
        expect(TomSelect.mock.calls[0][1].plugins).toContain('checkbox_options');
    });
});

// ---------------------------------------------------------------------------
// openSectionModal
// ---------------------------------------------------------------------------

describe('openSectionModal', () => {
    test('POSTs to sectionform with the docid', () => {
        mockFetchOk('');
        const btn = document.createElement('button');
        btn.dataset.docid = '40';
        btn.dataset.partial = 'false';

        openSectionModal(btn);

        expect(fetch).toHaveBeenCalledWith(
            '/administratepaper/sectionform',
            expect.objectContaining({ body: expect.stringContaining('docid=40') })
        );
    });

    test('initializes single Tom Select on section_select after fetch', async () => {
        const form = makeFormDOM('section-assignment-form-50');
        const select = makeSelectDOM('section_select');
        const wrapper = document.createElement('div');
        wrapper.className = 'checkbox';
        wrapper.hidden = true;
        const lbl = document.createElement('label');
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.id = 'assignEditors';
        lbl.appendChild(cb);
        wrapper.appendChild(lbl);
        form.appendChild(select);
        form.appendChild(wrapper);
        mockFetchOk('');

        const btn = document.createElement('button');
        btn.dataset.docid = '50';
        btn.dataset.partial = 'false';

        openSectionModal(btn);
        await flushPromises();

        expect(TomSelect).toHaveBeenCalledTimes(1);
        expect(TomSelect.mock.calls[0][1].allowEmptyOption).toBe(true);
    });

    test('on view page (partial=false) save calls displaysection, not page reload', async () => {
        // Form, select and checkbox wrapper added directly to body
        const form = makeFormDOM('section-assignment-form-90');
        const select = makeSelectDOM('section_select');
        const wrapper = document.createElement('div');
        wrapper.className = 'checkbox';
        wrapper.hidden = true;
        const lbl = document.createElement('label');
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.id = 'assignEditors';
        lbl.appendChild(cb);
        wrapper.appendChild(lbl);
        form.appendChild(select);
        form.appendChild(wrapper);

        // fetch 1: sectionform ('' — form already in DOM)
        // fetch 2: savesection → '1'
        // fetch 3: displaysection → section HTML
        global.fetch = jest.fn()
            .mockResolvedValueOnce({ ok: true, text: () => Promise.resolve('') })
            .mockResolvedValueOnce({ ok: true, text: () => Promise.resolve('1') })
            .mockResolvedValueOnce({ ok: true, text: () => Promise.resolve('<div class="section">S2</div>') });

        const btn = document.createElement('button');
        btn.dataset.docid = '90';
        btn.dataset.partial = 'false';

        openSectionModal(btn);
        await flushPromises();

        form.dispatchEvent(new Event('submit', { bubbles: true }));
        await flushPromises();

        // Should have called displaysection (3rd fetch), NOT location.replace
        expect(fetch).toHaveBeenCalledTimes(3);
        expect(fetch.mock.calls[2][0]).toBe('/administratepaper/displaysection');
        expect(fetch.mock.calls[2][1].body).toContain('partial=0');
    });
});
