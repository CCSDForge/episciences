/**
 * Test suite for the papers table built by public/js/paper/submitted.js (applyMultipleFilters()).
 *
 * When an image of the rows is loaded after the draw, the column widths must be measured again
 * (once per frame), the header being a separate table with scrollX.
 */

const fs = require('fs');
const path = require('path');

let tableNode;
let adjust;
let apis;
let frames;

// Minimal jQuery: DataTable() returns an API on a real <table>; the document-ready handler of
// submitted.js is not run.
global.$ = jest.fn(() => ({
    length: 0,
    ready: jest.fn(),
    on: jest.fn(),
    val: jest.fn(),
    DataTable: jest.fn(() => {
        const api = {
            table: () => ({ node: () => tableNode }),
            columns: { adjust: jest.fn(() => adjust()) },
        };
        apis.push(api);
        return api;
    }),
}));
global.requestAnimationFrame = jest.fn(callback => frames.push(callback));
global.translate = jest.fn(text => text);
global.getLoader = jest.fn(() => '');
global.addToggleButton = jest.fn();
global.JS_PREFIX_URL = '/';

const submittedJs = fs.readFileSync(
    path.join(__dirname, '../../../public/js/paper/submitted.js'),
    'utf8'
);

// eval() is safe and intentional here: the input is a source file from this repository,
// never external data, and submitted.js is a plain script with no module exports.
// eslint-disable-next-line no-eval
const initTable = eval(submittedJs + '\n; applyMultipleFilters');

describe('applyMultipleFilters column widths', function () {
    beforeEach(function () {
        tableNode = document.createElement('table');
        tableNode.innerHTML =
            '<tbody><tr><td><img alt="a"></td><td><img alt="b"></td><td><span></span></td></tr></tbody>';
        adjust = jest.fn();
        apis = [];
        frames = [];
    });

    function load(selector, type = 'load') {
        tableNode.querySelector(selector).dispatchEvent(new Event(type));
    }

    it('measures the columns again once the images of a frame are loaded', function () {
        initTable('administratepaper', 'list');

        load('img[alt="a"]');
        load('img[alt="b"]');
        expect(adjust).not.toHaveBeenCalled();

        frames.forEach(callback => callback());
        expect(adjust).toHaveBeenCalledTimes(1);

        load('img[alt="a"]');
        frames.slice(1).forEach(callback => callback());
        expect(adjust).toHaveBeenCalledTimes(2);
    });

    it('measures the columns again when an image fails to load', function () {
        initTable('administratepaper', 'list');

        load('img[alt="a"]', 'error');
        frames.forEach(callback => callback());

        expect(adjust).toHaveBeenCalledTimes(1);
    });

    it('ignores the load events of other elements', function () {
        initTable('administratepaper', 'list');

        load('span');

        expect(frames).toHaveLength(0);
    });

    it('adds the listener once when the table is rebuilt', function () {
        const addEventListener = jest.spyOn(tableNode, 'addEventListener');

        initTable('administratepaper', 'list');
        initTable('administratepaper', 'list', {}, { status: ['4'] });

        // Several frames would be merged by tablesToAdjust: check the listeners themselves
        const listeners = addEventListener.mock.calls
            .filter(call => call[0] === 'load' && call[2] === true)
            .map(call => call[1]);
        expect(listeners).toHaveLength(2);
        expect(new Set(listeners).size).toBe(1);
    });

    it('measures the columns with the API of the rebuilt table', function () {
        initTable('administratepaper', 'list');
        initTable('administratepaper', 'list', {}, { status: ['4'] });

        load('img[alt="a"]');
        frames.forEach(callback => callback());

        expect(apis[0].columns.adjust).not.toHaveBeenCalled();
        expect(apis[1].columns.adjust).toHaveBeenCalledTimes(1);
    });
});
