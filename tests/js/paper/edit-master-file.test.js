'use strict';

// public/js/paper/edit_master_file.js is a plain global script (no
// module.exports) that depends on getCommonForm() (public/js/common/utils.js)
// and jQuery. As with tests/js/common/utils.test.js and
// tests/js/readableBytes.test.js, we eval() the repo's own source at test
// time to get real, unmodified functions under test rather than reimplementing
// their logic in the test file.
const fs = require('fs');
const path = require('path');

const utilsJsSource = fs.readFileSync(
    path.join(__dirname, '../../../public/js/common/utils.js'),
    'utf8'
);
const editMasterFileJsSource = fs.readFileSync(
    path.join(__dirname, '../../../public/js/paper/edit_master_file.js'),
    'utf8'
);

/**
 * A tiny jQuery-alike backed by real jsdom nodes — just enough of the API
 * surface (find/first/closest/remove/after/css/attr/popover/off/on/serialize)
 * for edit_master_file.js and utils.js to run against real DOM state.
 */
function makeRealDomJQuery({ popoverMock, submitHandlers } = {}) {
    function wrap(el) {
        const self = {
            el,
            get length() {
                return el ? 1 : 0;
            },
            find(selector) {
                return wrap(el ? el.querySelector(selector) : null);
            },
            first() {
                return self;
            },
            closest(selector) {
                return wrap(el && el.closest ? el.closest(selector) : null);
            },
            remove() {
                if (el && el.parentNode) el.parentNode.removeChild(el);
                return self;
            },
            after(other) {
                if (el && el.parentNode) el.parentNode.insertBefore(other.el, el.nextSibling);
                return self;
            },
            css(props) {
                if (el) Object.assign(el.style, props);
                return self;
            },
            attr(name, value) {
                if (el) el.setAttribute(name, value);
                return self;
            },
            html(value) {
                if (el) el.innerHTML = value;
                return self;
            },
            popover: popoverMock || jest.fn(() => self),
            off() {
                return self;
            },
            on(event, handler) {
                if (submitHandlers && event === 'submit') submitHandlers.push(handler);
                return self;
            },
            serialize() {
                return 'master-file=99';
            },
        };
        return self;
    }

    const $ = jest.fn((selector) => {
        if (typeof selector === 'string' && selector.trim().startsWith('<')) {
            // Safe: `selector` here is always a fixed markup literal written
            // by the source under test (e.g. '<i class="fa-solid ...">'),
            // never external/user input — mirrors how jQuery's own $('<tag>')
            // constructor works.
            const holder = document.createElement('div');
            holder.innerHTML = selector.trim();
            return wrap(holder.firstElementChild);
        }
        if (typeof selector === 'string') {
            return wrap(document.querySelector(selector));
        }
        return wrap(selector);
    });

    return $;
}

beforeEach(() => {
    document.body.innerHTML = '';
    global.getLoader = jest.fn(() => '<div class="loader"></div>');
    global.console.error = jest.fn();
});

// -----------------------------------------------------------------------
// refreshMasterFileIcon()
// -----------------------------------------------------------------------

describe('refreshMasterFileIcon', () => {
    beforeEach(() => {
        global.$ = makeRealDomJQuery();
        // Indirect eval: runs in global scope so refreshMasterFileIcon() is
        // reachable from every test() below, not just this beforeEach.
        (0, eval)(utilsJsSource);
        (0, eval)(editMasterFileJsSource);
    });

    function buildFileRow(id) {
        const td = document.createElement('td');
        td.id = `td-${id}`;
        const small = document.createElement('small');
        small.textContent = 'checksum info';
        td.appendChild(small);
        document.body.appendChild(td);
        return td;
    }

    test('returns false and adds nothing when the target row has no <small>', () => {
        const td = document.createElement('td');
        td.id = 'td-1';
        document.body.appendChild(td);

        const result = refreshMasterFileIcon(1);

        expect(result).toBe(false);
        expect(td.querySelector('.fa-user-check')).toBeNull();
    });

    test('returns false when the target row does not exist', () => {
        expect(refreshMasterFileIcon(999)).toBe(false);
    });

    test('creates and places a new icon right after <small> when none exists yet', () => {
        const td = buildFileRow(1);

        const result = refreshMasterFileIcon(1);

        expect(result).toBe(true);
        const icon = td.querySelector('.fa-user-check');
        expect(icon).not.toBeNull();
        expect(icon.previousElementSibling.tagName).toBe('SMALL');
    });

    test('moves an existing icon from its previous row to the new one', () => {
        const oldTd = buildFileRow(1);
        const newTd = buildFileRow(2);

        refreshMasterFileIcon(1); // icon now lives under td-1
        expect(oldTd.querySelector('.fa-user-check')).not.toBeNull();

        const result = refreshMasterFileIcon(2);

        expect(result).toBe(true);
        expect(oldTd.querySelector('.fa-user-check')).toBeNull();
        expect(newTd.querySelector('.fa-user-check')).not.toBeNull();
    });

    test('does not duplicate the icon when called twice for the same row', () => {
        const td = buildFileRow(1);

        refreshMasterFileIcon(1);
        refreshMasterFileIcon(1);

        expect(td.querySelectorAll('.fa-user-check')).toHaveLength(1);
    });
});

// -----------------------------------------------------------------------
// getMasterFileForm() — parameter validation
// -----------------------------------------------------------------------

describe('getMasterFileForm parameter validation', () => {
    beforeEach(() => {
        global.$ = makeRealDomJQuery();
        (0, eval)(utilsJsSource);
        (0, eval)(editMasterFileJsSource);
    });

    test('rejects and logs when docId is missing', async () => {
        const button = document.createElement('button');

        await expect(getMasterFileForm(button, '')).rejects.toThrow('Invalid parameters');
        expect(console.error).toHaveBeenCalledWith(expect.stringContaining('EPMTY docId'));
    });

    // NOTE: `$button = $(button)` always yields a (possibly empty) jQuery
    // object, which is truthy even when `button` is null/undefined — jQuery
    // itself behaves this way ($(null) is an empty set, not falsy). So
    // `!$button` in the source's `if (!$button || !docId)` guard can never
    // be true: the "EMPTY SELECTOR" branch is effectively dead code, and a
    // missing button with a valid docId is *not* rejected. This test
    // documents that actual (surprising) behaviour rather than the
    // seemingly-intended one.
    test('does not reject when the button is missing but docId is present (dead validation branch)', () => {
        global.ajaxRequest = jest.fn(() => ({ done: () => ({ fail: () => {} }), fail: () => {} }));

        expect(() => getMasterFileForm(null, '42')).not.toThrow();
        expect(console.error).not.toHaveBeenCalled();
    });
});

// -----------------------------------------------------------------------
// getMasterFileForm() — success path wiring
// -----------------------------------------------------------------------

describe('getMasterFileForm success path', () => {
    let popoverMock;
    let submitHandlers;
    let ajaxRequestMock;

    beforeEach(() => {
        popoverMock = jest.fn(function () {
            return this;
        });
        submitHandlers = [];
        global.$ = makeRealDomJQuery({ popoverMock, submitHandlers });

        // getCommonForm's ajax call (fetching the form) resolves with a
        // marker string; the save-file ajax call resolves with a JSON result.
        const getFormDeferred = { done: (cb) => { cb('<form></form>'); return getFormDeferred; }, fail: () => getFormDeferred };
        ajaxRequestMock = jest.fn()
            .mockReturnValueOnce(getFormDeferred)
            .mockReturnValueOnce({
                done: (cb) => { cb({ success: true, targetId: 7 }); return { fail: () => {} }; },
            });
        global.ajaxRequest = ajaxRequestMock;

        const popoverContent = document.createElement('div');
        popoverContent.className = 'popover-content';
        const form = document.createElement('form');
        form.setAttribute('action', '/paper/savemasterfile');
        popoverContent.appendChild(form);
        document.body.appendChild(popoverContent);

        const td = document.createElement('td');
        td.id = 'td-7';
        const small = document.createElement('small');
        td.appendChild(small);
        document.body.appendChild(td);

        (0, eval)(utilsJsSource);
        (0, eval)(editMasterFileJsSource);
    });

    test('fetches the form for the given docId via getCommonForm', () => {
        const button = document.createElement('button');
        getMasterFileForm(button, '42');

        expect(ajaxRequestMock).toHaveBeenCalledWith('/paper/getmasterfileform', { docid: '42' });
    });

    test('submitting the popover form posts to savemasterfile with the serialized data and docid', () => {
        const button = document.createElement('button');
        getMasterFileForm(button, '42');

        expect(submitHandlers).toHaveLength(1);
        const fakeEvent = { preventDefault: jest.fn() };
        submitHandlers[0].call({}, fakeEvent);

        expect(fakeEvent.preventDefault).toHaveBeenCalled();
        expect(ajaxRequestMock).toHaveBeenNthCalledWith(
            2,
            '/paper/savemasterfile',
            'master-file=99&docid=42',
            'POST',
            'json'
        );
    });

    test('refreshes the master file icon once the save succeeds', () => {
        const button = document.createElement('button');
        getMasterFileForm(button, '42');

        const fakeEvent = { preventDefault: jest.fn() };
        submitHandlers[0].call({}, fakeEvent);

        expect(document.getElementById('td-7').querySelector('.fa-user-check')).not.toBeNull();
    });
});
