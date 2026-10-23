/**
 * Papers of the statistics figures: window loading the papers of a figure,
 * with search, sort and pagination
 */

const fs = require('fs');
const path = require('path');

global.translate = jest.fn(key => key);

const SCRIPT_PATH = path.join(
    __dirname,
    '../../../public/js/stats/paper-list.js'
);

function makeRows(count) {
    return Array.from({ length: count }, (_, i) => ({
        docid: i + 1,
        paperid: 1000 + i,
        title: 'Paper ' + (i + 1),
        status: 16,
        statusLabel: 'publié',
        statusGroup: 'published',
        submissionDate: '2025-01-' + String((i % 28) + 1).padStart(2, '0'),
        publicationDate: i % 2 ? null : '2025-06-01',
    }));
}

const DIALOG = `
    <button type="button" class="stat-papers-open" data-figure="nbAccepted">View</button>
    <button type="button" class="stat-papers-open" data-figure="nbRefused">View</button>
    <dialog id="stat-papers-dialog" data-url="/stats/papers" data-year="2025"
            data-titles='{"nbAccepted":"Accepted articles","nbRefused":"Refused articles"}'>
        <h2 id="stat-papers-dialog-title"></h2>
        <button type="button" class="stat-papers-close">x</button>
        <p class="stat-papers-hidden" hidden></p>
        <p class="stat-papers-message"></p>
        <input type="search" class="stat-papers-search">
        <span class="stat-papers-count"></span>
        <table><thead><tr>
            <th data-sort="docid" aria-sort="descending"><button type="button">ID</button></th>
            <th data-sort="title" aria-sort="none"><button type="button">Title</button></th>
            <th data-sort="statusLabel" aria-sort="none"><button type="button">Status</button></th>
            <th data-sort="submissionDate" aria-sort="none"><button type="button">Submitted</button></th>
            <th data-sort="publicationDate" aria-sort="none"><button type="button">Published</button></th>
        </tr></thead><tbody></tbody></table>
        <button type="button" class="stat-papers-prev">Prev</button>
        <span class="stat-papers-page"></span>
        <button type="button" class="stat-papers-next">Next</button>
    </dialog>
`;

function flush() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function mockFetch(responses) {
    global.fetch = jest.fn(url => {
        const figure = new URL(url, 'http://localhost').searchParams.get(
            'figure'
        );
        const { status = 200, body = {} } = responses[figure] || {
            status: 404,
        };
        return Promise.resolve({
            ok: status >= 200 && status < 300,
            status: status,
            json: () => Promise.resolve(body),
        });
    });
}

describe('statPapersView', function () {
    const { statPapersView } = require(SCRIPT_PATH);

    it('sorts, filters and paginates by 50', function () {
        const rows = makeRows(120);

        const first = statPapersView(rows, '', 'docid', 'descending', 1);
        expect(first.total).toBe(120);
        expect(first.pages).toBe(3);
        expect(first.rows).toHaveLength(50);
        expect(first.rows[0].docid).toBe(120);

        const last = statPapersView(rows, '', 'docid', 'ascending', 3);
        expect(last.rows).toHaveLength(20);
        expect(last.rows[0].docid).toBe(101);

        const search = statPapersView(
            rows,
            'paper 11',
            'docid',
            'ascending',
            1
        );
        expect(search.rows.map(r => r.docid)).toEqual([
            11, 110, 111, 112, 113, 114, 115, 116, 117, 118, 119,
        ]);
    });

    it('searches the identifiers', function () {
        const rows = makeRows(5);
        expect(
            statPapersView(rows, '1003', 'docid', 'ascending', 1).rows.map(
                r => r.docid
            )
        ).toEqual([4]);
    });

    it('keeps the empty values last whatever the direction', function () {
        const rows = makeRows(4);
        const asc = statPapersView(rows, '', 'publicationDate', 'ascending', 1);
        const desc = statPapersView(
            rows,
            '',
            'publicationDate',
            'descending',
            1
        );

        expect(asc.rows.slice(-2).every(r => r.publicationDate === null)).toBe(
            true
        );
        expect(desc.rows.slice(-2).every(r => r.publicationDate === null)).toBe(
            true
        );
    });

    it('keeps the page in the bounds', function () {
        const view = statPapersView(makeRows(10), '', 'docid', 'ascending', 9);
        expect(view.page).toBe(1);
        expect(view.pages).toBe(1);
    });
});

describe('Statistics papers window', function () {
    let script;

    beforeAll(function () {
        script = fs.readFileSync(SCRIPT_PATH, 'utf8');
    });

    beforeEach(function () {
        document.body.innerHTML = DIALOG;
        const dialog = document.getElementById('stat-papers-dialog');
        dialog.showModal = jest.fn(function () {
            this.setAttribute('open', '');
        });
        dialog.close = jest.fn(function () {
            this.removeAttribute('open');
        });
        // the script declares top-level constants: evaluate it in its own scope
        new Function('module', script)(undefined);
    });

    it('loads the papers of the figure and of the year', async function () {
        mockFetch({
            nbAccepted: { body: { total: 3, hidden: 2, rows: makeRows(3) } },
        });

        document.querySelector('[data-figure="nbAccepted"]').click();
        await flush();

        const dialog = document.getElementById('stat-papers-dialog');
        expect(dialog.showModal).toHaveBeenCalled();
        expect(fetch.mock.calls[0][0]).toBe(
            '/stats/papers?figure=nbAccepted&year=2025'
        );
        expect(
            document.getElementById('stat-papers-dialog-title').textContent
        ).toBe('Accepted articles');

        const links = dialog.querySelectorAll('tbody a');
        expect(links).toHaveLength(3);
        expect(links[0].getAttribute('href')).toBe(
            '/administratepaper/view/id/3'
        );
        expect(dialog.querySelector('tbody tr').textContent).toContain(
            'Paper 3'
        );

        const hidden = dialog.querySelector('.stat-papers-hidden');
        expect(hidden.hidden).toBe(false);
        expect(hidden.textContent).toContain('2');
    });

    it('renders the titles as text', async function () {
        const rows = makeRows(1);
        rows[0].title = '<img src=x onerror=alert(1)>';
        mockFetch({
            nbAccepted: { body: { total: 1, hidden: 0, rows: rows } },
        });

        document.querySelector('[data-figure="nbAccepted"]').click();
        await flush();

        expect(document.querySelector('tbody img')).toBeNull();
        expect(document.querySelector('tbody').textContent).toContain(
            '<img src=x onerror=alert(1)>'
        );
    });

    it('loads a figure only once', async function () {
        mockFetch({
            nbAccepted: { body: { total: 1, hidden: 0, rows: makeRows(1) } },
        });

        document.querySelector('[data-figure="nbAccepted"]').click();
        await flush();
        document.querySelector('.stat-papers-close').click();
        document.querySelector('[data-figure="nbAccepted"]').click();
        await flush();

        expect(fetch).toHaveBeenCalledTimes(1);
    });

    it('searches, sorts and paginates', async function () {
        mockFetch({
            nbAccepted: {
                body: { total: 120, hidden: 0, rows: makeRows(120) },
            },
        });

        document.querySelector('[data-figure="nbAccepted"]').click();
        await flush();

        const dialog = document.getElementById('stat-papers-dialog');
        expect(dialog.querySelectorAll('tbody tr')).toHaveLength(50);
        expect(dialog.querySelector('.stat-papers-page').textContent).toBe(
            '1 / 3'
        );
        expect(dialog.querySelector('.stat-papers-prev').disabled).toBe(true);

        dialog.querySelector('.stat-papers-next').click();
        expect(dialog.querySelector('.stat-papers-page').textContent).toBe(
            '2 / 3'
        );

        // sort by ID, ascending
        dialog.querySelector('th[data-sort="docid"] button').click();
        expect(
            dialog
                .querySelector('th[data-sort="docid"]')
                .getAttribute('aria-sort')
        ).toBe('ascending');
        expect(dialog.querySelector('tbody a').textContent).toBe('1');
        expect(dialog.querySelector('.stat-papers-page').textContent).toBe(
            '1 / 3'
        );

        const search = dialog.querySelector('.stat-papers-search');
        search.value = 'paper 42';
        search.dispatchEvent(new Event('input'));
        expect(dialog.querySelectorAll('tbody tr')).toHaveLength(1);
        expect(dialog.querySelector('.stat-papers-count').textContent).toBe(
            '1 article(s)'
        );
        expect(dialog.querySelector('.stat-papers-next').disabled).toBe(true);
    });

    it('asks to reload the page when the list has expired', async function () {
        mockFetch({});

        document.querySelector('[data-figure="nbRefused"]').click();
        await flush();

        const dialog = document.getElementById('stat-papers-dialog');
        expect(
            dialog.querySelector('.stat-papers-message').textContent
        ).toContain('La liste a expiré : veuillez recharger la page.');
        expect(dialog.querySelectorAll('tbody tr')).toHaveLength(0);
    });

    it('offers to reload the page when the list has expired', async function () {
        mockFetch({});

        document.querySelector('[data-figure="nbRefused"]').click();
        await flush();

        const reload = document.querySelector(
            '.stat-papers-message .stat-papers-reload'
        );
        expect(reload).not.toBeNull();
        expect(reload.textContent).toBe('Recharger la page');
    });

    it('asks to log in again when the session has expired', async function () {
        mockFetch({ nbRefused: { status: 403 } });

        document.querySelector('[data-figure="nbRefused"]').click();
        await flush();

        const message = document.querySelector('.stat-papers-message');
        expect(message.textContent).toContain(
            'Votre session a expiré : veuillez vous reconnecter.'
        );
        expect(message.querySelector('.stat-papers-reload')).not.toBeNull();
    });

    it('clears the reload button when another list is loaded', async function () {
        mockFetch({
            nbAccepted: { body: { total: 1, hidden: 0, rows: makeRows(1) } },
        });

        document.querySelector('[data-figure="nbRefused"]').click();
        await flush();
        document.querySelector('[data-figure="nbAccepted"]').click();
        await flush();

        expect(document.querySelector('.stat-papers-reload')).toBeNull();
    });

    it('shows an error when the request fails', async function () {
        mockFetch({ nbRefused: { status: 500 } });

        document.querySelector('[data-figure="nbRefused"]').click();
        await flush();

        expect(document.querySelector('.stat-papers-message').textContent).toBe(
            'Une erreur est survenue, veuillez réessayer.'
        );
    });

    it('closes the window', async function () {
        mockFetch({ nbAccepted: { body: { total: 0, hidden: 0, rows: [] } } });

        document.querySelector('[data-figure="nbAccepted"]').click();
        await flush();
        document.querySelector('.stat-papers-close').click();

        expect(
            document.getElementById('stat-papers-dialog').hasAttribute('open')
        ).toBe(false);
    });
});
