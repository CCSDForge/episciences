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
        imported: false,
        submissionDate: '2025-01-' + String((i % 28) + 1).padStart(2, '0'),
        acceptanceDate:
            i % 3 ? '2025-04-' + String((i % 28) + 1).padStart(2, '0') : null,
        publicationDate: i % 2 ? null : '2025-06-01',
    }));
}

const ACCEPTED_COLUMNS = [
    'submissionDate',
    'acceptanceDate',
    'publicationDate',
];

function headerLabels() {
    return Array.from(document.querySelectorAll('thead th')).map(th =>
        th.textContent.trim()
    );
}

function date(isoDate) {
    return new Date(isoDate + 'T00:00:00').toLocaleDateString();
}

const DIALOG = `
    <button type="button" class="stat-papers-open" data-figure="nbAccepted">View</button>
    <button type="button" class="stat-papers-open" data-figure="nbRefused">View</button>
    <dialog id="stat-papers-dialog" data-url="/stats/papers" data-year="2025"
            data-titles='{"nbAccepted":"Accepted articles","nbRefused":"Refused articles"}'
            data-column-labels='{"submitted":"Submitted on","imported":"Imported on","mixed":"Submitted on","importedTag":"imported","refusalDate":"Refused on","acceptanceDate":"Accepted on","publicationDate":"Published on"}'>
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

describe('buildPapersCsvRows', function () {
    const { buildPapersCsvRows } = require(SCRIPT_PATH);
    const labels = {
        id: 'ID',
        title: 'Title',
        status: 'Status',
        submissionDate: 'Submitted on',
        acceptanceDate: 'Accepted on',
        imported: 'Imported',
        yes: 'yes',
    };

    it('exports the columns of the window, raw values', function () {
        const rows = makeRows(2);

        expect(
            buildPapersCsvRows(
                rows,
                ['submissionDate', 'acceptanceDate'],
                labels
            )
        ).toEqual([
            ['ID', 'PAPERID', 'Title', 'Status', 'Submitted on', 'Accepted on'],
            [1, 1000, 'Paper 1', 'publié', '2025-01-01', ''],
            [2, 1001, 'Paper 2', 'publié', '2025-01-02', '2025-04-02'],
        ]);
    });

    it('marks the imported papers when the list has some', function () {
        const rows = makeRows(2);
        rows[1].imported = true;

        const csv = buildPapersCsvRows(rows, ['submissionDate'], labels);

        expect(csv[0]).toEqual([
            'ID',
            'PAPERID',
            'Title',
            'Status',
            'Submitted on',
            'Imported',
        ]);
        expect(csv[1][5]).toBe('');
        expect(csv[2][5]).toBe('yes');
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

    it('accepted papers: submission, acceptance and publication dates', async function () {
        const rows = makeRows(1);
        rows[0].submissionDate = '2025-01-15';
        rows[0].acceptanceDate = '2025-06-03';
        rows[0].publicationDate = null;
        mockFetch({
            nbAccepted: {
                body: {
                    columns: ACCEPTED_COLUMNS,
                    submissionLabel: 'submitted',
                    total: 1,
                    hidden: 0,
                    rows: rows,
                },
            },
        });

        document.querySelector('[data-figure="nbAccepted"]').click();
        await flush();

        expect(headerLabels()).toEqual([
            'ID',
            'Title',
            'Status',
            'Submitted on',
            'Accepted on',
            'Published on',
        ]);

        const cells = document.querySelectorAll('tbody td.stat-papers-date');
        expect(cells).toHaveLength(3);
        expect(cells[0].textContent).toBe(date('2025-01-15'));
        expect(cells[1].textContent).toBe(date('2025-06-03'));
        expect(cells[2].textContent).toBe('');
    });

    it('refused papers: submission and refusal dates only', async function () {
        const rows = makeRows(1);
        rows[0].refusalDate = '2025-03-10';
        mockFetch({
            nbRefused: {
                body: {
                    columns: ['submissionDate', 'refusalDate'],
                    submissionLabel: 'submitted',
                    total: 1,
                    hidden: 0,
                    rows: rows,
                },
            },
        });

        document.querySelector('[data-figure="nbRefused"]').click();
        await flush();

        expect(headerLabels()).toEqual([
            'ID',
            'Title',
            'Status',
            'Submitted on',
            'Refused on',
        ]);
        const cells = document.querySelectorAll('tbody td.stat-papers-date');
        expect(cells).toHaveLength(2);
        expect(cells[1].textContent).toBe(date('2025-03-10'));
    });

    it('imported papers: import date', async function () {
        const rows = makeRows(2).map(row => ({ ...row, imported: true }));
        mockFetch({
            nbAccepted: {
                body: {
                    columns: ['submissionDate', 'publicationDate'],
                    submissionLabel: 'imported',
                    total: 2,
                    hidden: 0,
                    rows: rows,
                },
            },
        });

        document.querySelector('[data-figure="nbAccepted"]').click();
        await flush();

        expect(headerLabels().slice(3)).toEqual([
            'Imported on',
            'Published on',
        ]);
        // a single kind of papers: no mark on the rows
        expect(document.querySelector('.stat-papers-imported')).toBeNull();
    });

    it('submissions: submitted or imported papers, refusal, acceptance and publication dates', async function () {
        const rows = makeRows(2);
        rows[0].imported = true;
        rows[0].refusalDate = null;
        rows[1].refusalDate = '2025-02-01';
        mockFetch({
            nbAccepted: {
                body: {
                    columns: [
                        'submissionDate',
                        'refusalDate',
                        'acceptanceDate',
                        'publicationDate',
                    ],
                    submissionLabel: 'mixed',
                    total: 2,
                    hidden: 0,
                    rows: rows,
                },
            },
        });

        document.querySelector('[data-figure="nbAccepted"]').click();
        await flush();

        expect(headerLabels().slice(3)).toEqual([
            'Submitted on',
            'Refused on',
            'Accepted on',
            'Published on',
        ]);

        // sorted by ID, descending: docid 2, then docid 1 (imported)
        const [submitted, imported] = document.querySelectorAll('tbody tr');
        expect(submitted.querySelector('.stat-papers-imported')).toBeNull();
        expect(
            imported.querySelector('.stat-papers-imported').textContent
        ).toBe('imported');
        expect(
            submitted.querySelectorAll('td.stat-papers-date')[1].textContent
        ).toBe(date('2025-02-01'));
    });

    it('resets a sort on a date column the next figure does not have', async function () {
        mockFetch({
            nbAccepted: {
                body: {
                    columns: ACCEPTED_COLUMNS,
                    total: 1,
                    hidden: 0,
                    rows: makeRows(1),
                },
            },
            nbRefused: {
                body: {
                    columns: ['submissionDate', 'refusalDate'],
                    total: 2,
                    hidden: 0,
                    rows: makeRows(2),
                },
            },
        });

        document.querySelector('[data-figure="nbAccepted"]').click();
        await flush();
        document.querySelector('th[data-sort="acceptanceDate"] button').click();
        expect(
            document
                .querySelector('th[data-sort="acceptanceDate"]')
                .getAttribute('aria-sort')
        ).toBe('ascending');

        document.querySelector('[data-figure="nbRefused"]').click();
        await flush();

        expect(
            document.querySelector('th[data-sort="acceptanceDate"]')
        ).toBeNull();
        expect(
            document
                .querySelector('th[data-sort="docid"]')
                .getAttribute('aria-sort')
        ).toBe('descending');
        expect(
            document.querySelector('tbody tr').querySelectorAll('td')
        ).toHaveLength(5);
    });

    it('sorts by a date, the papers without one last', async function () {
        mockFetch({
            nbAccepted: {
                body: {
                    columns: ACCEPTED_COLUMNS,
                    total: 6,
                    hidden: 0,
                    rows: makeRows(6),
                },
            },
        });

        document.querySelector('[data-figure="nbAccepted"]').click();
        await flush();
        document.querySelector('th[data-sort="acceptanceDate"] button').click();

        const dates = Array.from(document.querySelectorAll('tbody tr')).map(
            tr => tr.querySelectorAll('td.stat-papers-date')[1].textContent
        );
        expect(dates.slice(-2)).toEqual(['', '']);
        expect(dates.slice(0, 4).every(value => value !== '')).toBe(true);
    });

    it('asks for the lists of all the data when the filter of the journal is off', async function () {
        mockFetch({
            nbAccepted: { body: { total: 1, hidden: 0, rows: makeRows(1) } },
        });
        document.getElementById('stat-papers-dialog').dataset.fullHistory = '1';

        document.querySelector('[data-figure="nbAccepted"]').click();
        await flush();

        expect(fetch.mock.calls[0][0]).toBe(
            '/stats/papers?figure=nbAccepted&year=2025&fullHistory=1'
        );
    });

    it('exports all the rows filtered and sorted, not only the page', async function () {
        document
            .querySelector('.stat-papers-count')
            .insertAdjacentHTML(
                'afterend',
                '<button type="button" class="stat-papers-export">Export</button>'
            );
        let exported = null;
        global.downloadCsv = jest.fn((fileName, rows) => {
            exported = { fileName, rows };
        });
        global.statCsvFileName = parts => parts.join('-') + '.csv';
        global.statsExportName = () => 'statistiques-dev-tout';
        new Function('module', script)(undefined);

        mockFetch({
            nbAccepted: {
                body: {
                    columns: ACCEPTED_COLUMNS,
                    total: 120,
                    hidden: 0,
                    rows: makeRows(120),
                },
            },
        });
        document.querySelector('[data-figure="nbAccepted"]').click();
        await flush();
        const search = document.querySelector('.stat-papers-search');
        search.value = 'paper 1';
        search.dispatchEvent(new Event('input'));

        document.querySelector('.stat-papers-export').click();

        // named after the indicator, as shown (the title of the window)
        expect(exported.fileName).toBe(
            'statistiques-dev-tout-Accepted articles.csv'
        );
        // header + the 32 matching rows (1, 10-19, 100-120), all of them: not only the page of the window
        expect(exported.rows).toHaveLength(1 + 32);
        expect(exported.rows[0].slice(0, 4)).toEqual([
            'ID',
            'PAPERID',
            'Title',
            'Status',
        ]);
        // sorted as the window: by ID, descending
        expect(exported.rows[1][0]).toBe(120);

        delete global.downloadCsv;
        delete global.statCsvFileName;
        delete global.statsExportName;
    });

    it('takes the title of the button: a list shown under another title', async function () {
        document.body.insertAdjacentHTML(
            'afterbegin',
            '<button type="button" class="stat-papers-open" data-figure="nbSubmissions" data-title="Articles">View</button>'
        );
        document
            .querySelector('.stat-papers-count')
            .insertAdjacentHTML(
                'afterend',
                '<button type="button" class="stat-papers-export">Export</button>'
            );
        let exported = null;
        global.downloadCsv = jest.fn(fileName => {
            exported = fileName;
        });
        global.statCsvFileName = parts => parts.join('-') + '.csv';
        global.statsExportName = () => 'statistics-dev-all';
        new Function('module', script)(undefined);

        mockFetch({
            nbSubmissions: {
                body: { columns: [], total: 1, hidden: 0, rows: makeRows(1) },
            },
        });
        document.querySelector('[data-figure="nbSubmissions"]').click();
        await flush();

        expect(
            document.getElementById('stat-papers-dialog-title').textContent
        ).toBe('Articles');

        document.querySelector('.stat-papers-export').click();
        expect(exported).toBe('statistics-dev-all-Articles.csv');
    });

    it('lists the reviews: a row by invitation, its answer as text, the counts given by the server', async function () {
        document.body.insertAdjacentHTML(
            'afterbegin',
            '<button type="button" class="stat-papers-open" data-figure="reviewsRequested">View</button>'
        );
        const dialog = document.getElementById('stat-papers-dialog');
        const labels = JSON.parse(dialog.dataset.columnLabels);
        labels.invitationDate = 'Invitation sent on';
        labels.invitationAnswer = 'Answer';
        dialog.dataset.columnLabels = JSON.stringify(labels);
        new Function('module', script)(undefined);

        const rows = makeRows(2).map((row, i) => ({
            ...row,
            docid: 201,
            invitationDate: '2024-03-0' + (i + 1),
            invitationAnswer: i ? 'declined' : 'accepted',
        }));
        mockFetch({
            reviewsRequested: {
                body: {
                    columns: ['invitationDate', 'invitationAnswer'],
                    total: 2,
                    hidden: 1,
                    rows: rows,
                    countLabel: 'invitation(s)',
                    hiddenLabel: 'review(s) not listed',
                },
            },
        });
        document.querySelector('[data-figure="reviewsRequested"]').click();
        await flush();

        expect(headerLabels().slice(3)).toEqual([
            'Invitation sent on',
            'Answer',
        ]);
        // all the rows (the two invitations have the same paper: their order is not tested)
        const cells = Array.from(document.querySelectorAll('tbody td')).map(
            td => td.textContent
        );
        expect(cells).toContain(date('2024-03-01'));
        expect(cells).toContain('accepted');
        expect(document.querySelector('.stat-papers-count').textContent).toBe(
            '2 invitation(s)'
        );
        expect(document.querySelector('.stat-papers-hidden').textContent).toBe(
            '1 review(s) not listed'
        );
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

    it('ignores the response of a figure no longer shown', async function () {
        let resolveAccepted;
        global.fetch = jest.fn(url => {
            const figure = new URL(url, 'http://localhost').searchParams.get(
                'figure'
            );
            const body =
                figure === 'nbAccepted'
                    ? { total: 3, hidden: 0, rows: makeRows(3) }
                    : { total: 1, hidden: 0, rows: makeRows(1) };
            const response = {
                ok: true,
                status: 200,
                json: () => Promise.resolve(body),
            };
            return figure === 'nbAccepted'
                ? new Promise(resolve => {
                      resolveAccepted = () => resolve(response);
                  })
                : Promise.resolve(response);
        });

        document.querySelector('[data-figure="nbAccepted"]').click();
        document.querySelector('.stat-papers-close').click();
        document.querySelector('[data-figure="nbRefused"]').click();
        await flush();
        resolveAccepted();
        await flush();

        const dialog = document.getElementById('stat-papers-dialog');
        expect(
            document.getElementById('stat-papers-dialog-title').textContent
        ).toBe('Refused articles');
        expect(dialog.querySelectorAll('tbody tr')).toHaveLength(1);
    });

    it('ignores the error of a figure no longer shown', async function () {
        let rejectAccepted;
        global.fetch = jest.fn(url => {
            const figure = new URL(url, 'http://localhost').searchParams.get(
                'figure'
            );
            return figure === 'nbAccepted'
                ? new Promise(resolve => {
                      rejectAccepted = () =>
                          resolve({ ok: false, status: 500, json: () => ({}) });
                  })
                : Promise.resolve({
                      ok: true,
                      status: 200,
                      json: () =>
                          Promise.resolve({
                              total: 1,
                              hidden: 0,
                              rows: makeRows(1),
                          }),
                  });
        });

        document.querySelector('[data-figure="nbAccepted"]').click();
        document.querySelector('.stat-papers-close').click();
        document.querySelector('[data-figure="nbRefused"]').click();
        await flush();
        rejectAccepted();
        await flush();

        expect(document.querySelector('.stat-papers-message').textContent).toBe(
            ''
        );
        expect(
            document.querySelectorAll('#stat-papers-dialog tbody tr')
        ).toHaveLength(1);
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
