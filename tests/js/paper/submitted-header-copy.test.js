/**
 * With scrollX, DataTables copies the header of the papers table into a second table of the same
 * class. Rebuilding the table (filter button) must only build the table of the rows again:
 * building the copy as well sent each request twice.
 */

const fs = require('fs');
const path = require('path');

const submittedJs = fs.readFileSync(
    path.join(__dirname, '../../../public/js/paper/submitted.js'),
    'utf8'
);

let built;
let toggled;

/**
 * Minimal jQuery over the real DOM: a selector string selects the matching elements,
 * not() removes the elements matching a selector, DataTable() records the tables it builds.
 */
function domJquery(target) {
    const elements =
        typeof target === 'string'
            ? Array.from(document.querySelectorAll(target))
            : target;

    return {
        elements,
        length: elements.length,
        ready: jest.fn(),
        not: selector =>
            domJquery(elements.filter(element => !element.matches(selector))),
        DataTable: () => {
            built.push(...elements);
            return {
                table: () => ({ node: () => elements[0] }),
            };
        },
    };
}

global.$ = jest.fn(domJquery);
global.translate = jest.fn(text => text);
global.getLoader = jest.fn(() => '');
global.addToggleButton = jest.fn(table => toggled.push(...table.elements));

// eval() is safe and intentional here: the input is a source file from this repository,
// never external data, and submitted.js is a plain script with no module exports.
// eslint-disable-next-line no-eval
const applyFilters = eval(submittedJs + '\n; applyMultipleFilters');

describe('applyMultipleFilters with the header copy of scrollX', function () {
    beforeEach(function () {
        built = [];
        toggled = [];
        // layout of a table already built by DataTables with scrollX
        document.body.innerHTML = `
            <div class="dataTables_wrapper">
                <div class="dataTables_scroll">
                    <div class="dataTables_scrollHead"><div class="dataTables_scrollHeadInner">
                        <table class="table dataTable"><thead><tr><th>Id</th></tr></thead></table>
                    </div></div>
                    <div class="dataTables_scrollBody">
                        <table class="table dataTable" id="papers"><tbody></tbody></table>
                    </div>
                </div>
            </div>`;
    });

    it('builds the table of the rows only', function () {
        applyFilters('administratepaper', 'list', {}, { volume: ['596'] });

        const papers = document.getElementById('papers');
        expect(built).toEqual([papers]);
        expect(toggled).toEqual([papers]);
    });

    it('builds the table of a page not built yet', function () {
        document.body.innerHTML =
            '<table class="table dataTable" id="papers"><tbody></tbody></table>';

        applyFilters('administratepaper', 'list');

        expect(built).toEqual([document.getElementById('papers')]);
    });
});
