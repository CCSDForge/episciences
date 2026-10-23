'use strict';

// public/js/common/utils.js is a plain global script (no module.exports),
// loaded via a <script> tag alongside jQuery in the browser. We eval it at
// module scope (like tests/js/readableBytes.test.js does for functions.js)
// so getCommonForm() and its shared `openedPopover` state become directly
// usable from the tests below.
const fs = require('fs');
const path = require('path');

const utilsJsSource = fs.readFileSync(
    path.join(__dirname, '../../../public/js/common/utils.js'),
    'utf8'
);

function makeJQueryMock() {
    const jqObj = {};
    jqObj.popover = jest.fn(() => jqObj);
    const $ = jest.fn(() => jqObj);
    return { $, jqObj };
}

let $mock;
let ajaxRequestMock;
let ajaxResult;

beforeEach(() => {
    const mock = makeJQueryMock();
    $mock = mock.$;
    global.$ = mock.$;

    global.getLoader = jest.fn(() => '<div class="loader"></div>');

    ajaxResult = { done: jest.fn(), fail: jest.fn() };
    ajaxRequestMock = jest.fn(() => ajaxResult);
    global.ajaxRequest = ajaxRequestMock;

    // Safe: not arbitrary/external input — utilsJsSource is this repo's own
    // public/js/common/utils.js, read from disk at test time (same pattern
    // as tests/js/readableBytes.test.js). It's plain source with no
    // module.exports, so eval() is how the test gets a fresh
    // `var openedPopover = null;` and getCommonForm() for every test.
    // Indirect eval (the `(0, eval)` form) runs in global scope, so the
    // declarations are visible from every test()/describe() callback below
    // rather than being scoped to just this beforeEach closure.
    (0, eval)(utilsJsSource);
});

describe('getCommonForm', () => {
    test('wraps the button with $() before doing anything else', () => {
        const button = { id: 'btn-1' };
        getCommonForm(button, 1, '/some/form');

        expect($mock).toHaveBeenCalledWith(button);
    });

    test('destroys any previous popover on the button', () => {
        const button = {};
        getCommonForm(button, 1, '/some/form');

        expect($mock.mock.results[0].value.popover).toHaveBeenCalledWith('destroy');
    });

    test('opens the popover and fetches the form on first click for a docId', () => {
        const button = {};
        const result = getCommonForm(button, 7, '/paper/getmasterfileform');

        expect(ajaxRequestMock).toHaveBeenCalledWith('/paper/getmasterfileform', { docid: 7 });
        expect(result).toBe(ajaxResult);
    });

    test('shows the popover with merged default and custom params', () => {
        const button = {};
        getCommonForm(button, 7, '/url', { placement: 'top' });

        const showCall = $mock.mock.results[0].value.popover.mock.calls.find(
            (call) => typeof call[0] === 'object'
        );

        expect(showCall[0]).toMatchObject({
            placement: 'top', // overridden
            container: 'body', // default kept
            html: true, // default kept
        });
    });

    test('defaults the url to "#" when not provided', () => {
        const button = {};
        getCommonForm(button, 3);

        expect(ajaxRequestMock).toHaveBeenCalledWith('#', { docid: 3 });
    });

    test('a second call for the same docId closes the popover instead of reopening it', () => {
        const button = {};
        getCommonForm(button, 5, '/url'); // opens

        ajaxRequestMock.mockClear();
        const result = getCommonForm(button, 5, '/url'); // toggled closed

        expect(result).toBe(false);
        expect(ajaxRequestMock).not.toHaveBeenCalled();
    });

    test('a third call for the same docId re-opens it (toggle flips back)', () => {
        const button = {};
        getCommonForm(button, 5, '/url'); // open
        getCommonForm(button, 5, '/url'); // close
        ajaxRequestMock.mockClear();

        const result = getCommonForm(button, 5, '/url'); // open again

        expect(result).toBe(ajaxResult);
        expect(ajaxRequestMock).toHaveBeenCalledWith('/url', { docid: 5 });
    });

    test('opening a different docId while one is open does not close the first one first', () => {
        const button = {};
        getCommonForm(button, 1, '/url'); // openedPopover = 1

        ajaxRequestMock.mockClear();
        const result = getCommonForm(button, 2, '/url'); // openedPopover moves to 2

        expect(result).toBe(ajaxResult);
        expect(ajaxRequestMock).toHaveBeenCalledWith('/url', { docid: 2 });
    });
});
