/**
 * public/js/paper/submitted.js is loaded with $.getScript() once the page is ready: jQuery then
 * runs its document-ready handler at once, while the script itself is still being evaluated.
 * The handler must build the papers table and bind the filter button and the search box.
 */

const fs = require('fs');
const path = require('path');

const submittedJs = fs.readFileSync(
    path.join(__dirname, '../../../public/js/paper/submitted.js'),
    'utf8'
);

let bound;
let tables;

/**
 * Minimal jQuery of a page that is already ready: ready() runs its handler immediately.
 */
function readyPageJquery() {
    return jest.fn(selector => {
        const element = {
            length: selector === '.dataTable' ? 1 : 0,
            ready: handler => handler(),
            val: () => null,
            on: (event, handler) => {
                bound.push(selector + ' ' + event);
                return element;
            },
            not: () => element,
            unbind: () => element,
            bind: (events, handler) => {
                bound.push(selector + ' ' + events);
                return element;
            },
            DataTable: options => {
                tables.push(options);
                return {
                    table: () => ({
                        node: () => document.createElement('table'),
                    }),
                };
            },
        };

        return element;
    });
}

describe('submitted.js loaded into a ready page', function () {
    beforeEach(function () {
        bound = [];
        tables = [];
        global.$ = readyPageJquery();
        global.selfController = 'administratepaper';
        global.selfAction = 'list';
        global.translate = jest.fn(text => text);
        global.getLoader = jest.fn(() => '');
        global.addToggleButton = jest.fn();
    });

    afterEach(function () {
        delete global.selfController;
        delete global.selfAction;
    });

    it('builds the table and binds the filter button and the search box', function () {
        // eval() is safe and intentional here: the input is a source file from this repository,
        // never external data, and submitted.js is a plain script with no module exports.
        // eslint-disable-next-line no-eval
        expect(() => eval(submittedJs)).not.toThrow();

        expect(tables).toHaveLength(1);
        expect(tables[0].ajax.url).toBe('/administratepaper/list');
        expect(bound).toEqual([
            '#submit click',
            '.dataTables_filter input keyup change',
        ]);
    });
});
