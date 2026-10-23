/**
 * Papers of the statistics figures: lists displayed by batches when opened
 */

const fs = require('fs');
const path = require('path');

global.translate = jest.fn(key => key);

describe('Statistics paper lists', function () {
    let script;

    beforeAll(function () {
        script = fs.readFileSync(
            path.join(__dirname, '../../../public/js/stats/paper-list.js'),
            'utf8'
        );
    });

    function setUp(docIds) {
        document.body.innerHTML = `
            <details class="stat-papers-list" data-docids='${JSON.stringify(docIds)}'>
                <summary>View</summary>
                <ul class="stat-papers-items"></ul>
            </details>
        `;
        // the script declares top-level constants: evaluate it in its own scope
        new Function(script)();
        return document.querySelector('details');
    }

    function open(details) {
        details.open = true;
        details.dispatchEvent(new Event('toggle'));
    }

    it('renders nothing until the list is opened', function () {
        const details = setUp([1, 2, 3]);

        expect(details.querySelectorAll('li')).toHaveLength(0);
    });

    it('renders links to the papers when opened', function () {
        const details = setUp([10, 11]);
        open(details);

        const links = details.querySelectorAll('li a');
        expect(links).toHaveLength(2);
        expect(links[0].getAttribute('href')).toBe(
            '/administratepaper/view/id/10'
        );
        expect(links[0].textContent).toBe('10');
        expect(details.querySelector('.stat-papers-more')).toBeNull();
    });

    it('does not render the list twice', function () {
        const details = setUp([10, 11]);
        open(details);
        details.open = false;
        details.dispatchEvent(new Event('toggle'));
        open(details);

        expect(details.querySelectorAll('li')).toHaveLength(2);
    });

    it('renders long lists by batches of 100', function () {
        const docIds = Array.from({ length: 250 }, (_, i) => i + 1);
        const details = setUp(docIds);
        open(details);

        expect(details.querySelectorAll('li')).toHaveLength(100);

        details.querySelector('.stat-papers-more').click();
        expect(details.querySelectorAll('li')).toHaveLength(200);

        details.querySelector('.stat-papers-more').click();
        expect(details.querySelectorAll('li')).toHaveLength(250);
        expect(details.querySelector('.stat-papers-more')).toBeNull();
    });
});
