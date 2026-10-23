/**
 * Papers of the statistics (journal administrators): a window lists the papers of a figure,
 * loaded from /stats/papers, with search, sort and pagination.
 */
const STAT_PAPERS_PAGE_SIZE = 50;
const STAT_PAPERS_VIEW_URL = '/administratepaper/view/id/';
const STAT_PAPERS_STATUS_ICONS = {
    published: 'fa-check-circle',
    accepted: 'fa-check',
    refused: 'fa-times-circle',
    closed: 'fa-ban',
    pending: 'fa-hourglass-half',
};

function statPapersTranslate(key) {
    return typeof translate === 'function' ? translate(key) : key;
}

function statPapersFormatDate(isoDate) {
    if (!isoDate) {
        return '';
    }

    const date = new Date(isoDate + 'T00:00:00');

    if (Number.isNaN(date.getTime())) {
        return isoDate;
    }

    const lang = typeof locale === 'string' ? locale : undefined;
    return date.toLocaleDateString(lang);
}

function statPapersCompare(a, b, key) {
    const x = a[key];
    const y = b[key];

    if (x === y) {
        return 0;
    }

    // empty values last
    if (x === null || x === undefined || x === '') {
        return 1;
    }

    if (y === null || y === undefined || y === '') {
        return -1;
    }

    if (typeof x === 'number' && typeof y === 'number') {
        return x - y;
    }

    return String(x).localeCompare(String(y), undefined, {
        sensitivity: 'base',
        numeric: true,
    });
}

/**
 * Pure helper: rows filtered by the query, sorted, and the requested page
 */
function statPapersView(rows, query, sortKey, sortDir, page) {
    const q = query.trim().toLowerCase();
    const filtered = q
        ? rows.filter(
              row =>
                  String(row.title || '')
                      .toLowerCase()
                      .includes(q) ||
                  String(row.docid).includes(q) ||
                  String(row.paperid).includes(q)
          )
        : rows.slice();

    filtered.sort((a, b) => {
        const result = statPapersCompare(a, b, sortKey);
        // empty values stay last whatever the direction
        const emptyA = a[sortKey] === null || a[sortKey] === '';
        const emptyB = b[sortKey] === null || b[sortKey] === '';
        if (emptyA || emptyB) {
            return result;
        }
        return sortDir === 'ascending' ? result : -result;
    });

    const pages = Math.max(
        1,
        Math.ceil(filtered.length / STAT_PAPERS_PAGE_SIZE)
    );
    const current = Math.min(Math.max(1, page), pages);
    const start = (current - 1) * STAT_PAPERS_PAGE_SIZE;

    return {
        total: filtered.length,
        page: current,
        pages: pages,
        rows: filtered.slice(start, start + STAT_PAPERS_PAGE_SIZE),
    };
}

function initStatPapers() {
    const dialog = document.getElementById('stat-papers-dialog');

    if (!dialog) {
        return;
    }

    const titles = JSON.parse(dialog.dataset.titles || '{}');
    const tbody = dialog.querySelector('tbody');
    const titleEl = dialog.querySelector('#stat-papers-dialog-title');
    const hiddenEl = dialog.querySelector('.stat-papers-hidden');
    const messageEl = dialog.querySelector('.stat-papers-message');
    const searchEl = dialog.querySelector('.stat-papers-search');
    const countEl = dialog.querySelector('.stat-papers-count');
    const pageEl = dialog.querySelector('.stat-papers-page');
    const prevEl = dialog.querySelector('.stat-papers-prev');
    const nextEl = dialog.querySelector('.stat-papers-next');
    const columnLabels = JSON.parse(dialog.dataset.columnLabels || '{}');
    const headerRow = dialog.querySelector('thead tr');
    const fixedHeaders = headerRow.querySelectorAll('th').length;
    const cache = new Map();

    const state = {
        rows: [],
        // date columns of the figure (only the relevant dates), sent by the server
        columns: [],
        // label of the submission date: submitted, imported or mixed
        submissionLabel: 'submitted',
        query: '',
        sortKey: 'docid',
        sortDir: 'descending',
        page: 1,
    };

    function renderRow(row) {
        const tr = document.createElement('tr');

        const idCell = document.createElement('td');
        const link = document.createElement('a');
        link.href = STAT_PAPERS_VIEW_URL + encodeURIComponent(row.docid);
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = row.docid;
        idCell.appendChild(link);
        tr.appendChild(idCell);

        const titleCell = document.createElement('td');
        titleCell.textContent = row.title;
        tr.appendChild(titleCell);

        const statusCell = document.createElement('td');
        const icon = document.createElement('span');
        icon.className =
            'fas fa-sm ' +
            (STAT_PAPERS_STATUS_ICONS[row.statusGroup] ||
                STAT_PAPERS_STATUS_ICONS.pending);
        icon.setAttribute('aria-hidden', 'true');
        statusCell.appendChild(icon);
        statusCell.appendChild(document.createTextNode(' ' + row.statusLabel));
        tr.appendChild(statusCell);

        state.columns.forEach(key => {
            const cell = document.createElement('td');
            cell.className = 'stat-papers-date';
            cell.textContent = statPapersFormatDate(row[key]);

            // submitted and imported papers in the same column: the imported ones are marked
            if (
                key === 'submissionDate' &&
                state.submissionLabel === 'mixed' &&
                row.imported
            ) {
                const tag = document.createElement('span');
                tag.className = 'help-block small stat-papers-imported';
                tag.textContent = columnLabels.importedTag || 'importé';
                cell.appendChild(tag);
            }

            tr.appendChild(cell);
        });

        return tr;
    }

    function render() {
        const view = statPapersView(
            state.rows,
            state.query,
            state.sortKey,
            state.sortDir,
            state.page
        );
        state.page = view.page;

        tbody.replaceChildren(...view.rows.map(renderRow));

        countEl.textContent =
            view.total + ' ' + statPapersTranslate('article(s)');
        pageEl.textContent = view.page + ' / ' + view.pages;
        prevEl.disabled = view.page <= 1;
        nextEl.disabled = view.page >= view.pages;

        headerRow.querySelectorAll('th[data-sort]').forEach(th => {
            th.setAttribute(
                'aria-sort',
                th.dataset.sort === state.sortKey ? state.sortDir : 'none'
            );
        });
    }

    function showMessage(message, withReload = false) {
        messageEl.textContent = message;
        messageEl.hidden = !message;

        if (withReload) {
            const reload = document.createElement('button');
            reload.type = 'button';
            reload.className = 'btn btn-default btn-xs stat-papers-reload';
            reload.textContent = statPapersTranslate('Recharger la page');
            reload.addEventListener('click', () => window.location.reload());
            messageEl.appendChild(document.createTextNode(' '));
            messageEl.appendChild(reload);
        }
    }

    function columnLabel(key) {
        return key === 'submissionDate'
            ? columnLabels[state.submissionLabel] || columnLabels.submitted
            : columnLabels[key];
    }

    /**
     * Date columns of the figure: headers after the fixed ones (ID, title, status)
     */
    function setColumns(columns, submissionLabel) {
        state.columns = (Array.isArray(columns) ? columns : []).filter(
            key => key === 'submissionDate' || columnLabels[key]
        );
        state.submissionLabel = submissionLabel || 'submitted';

        while (headerRow.children.length > fixedHeaders) {
            headerRow.lastElementChild.remove();
        }

        state.columns.forEach(key => {
            const th = document.createElement('th');
            th.scope = 'col';
            th.dataset.sort = key;
            th.setAttribute('aria-sort', 'none');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-link btn-xs';
            button.textContent = columnLabel(key) || key;
            th.appendChild(button);
            headerRow.appendChild(th);
        });

        // the sort on a column the figure does not have is reset
        const fixedKeys = ['docid', 'title', 'statusLabel'];
        if (
            !fixedKeys.includes(state.sortKey) &&
            !state.columns.includes(state.sortKey)
        ) {
            state.sortKey = 'docid';
            state.sortDir = 'descending';
        }
    }

    function showData(data) {
        state.rows = Array.isArray(data.rows) ? data.rows : [];
        state.page = 1;
        setColumns(data.columns, data.submissionLabel);

        const hidden = Number(data.hidden) || 0;
        hiddenEl.hidden = hidden === 0;
        hiddenEl.textContent = hidden
            ? hidden +
              ' ' +
              statPapersTranslate(
                  "article(s) non listé(s) : conflit d'intérêts déclaré"
              )
            : '';

        showMessage(
            state.rows.length < (Number(data.total) || 0)
                ? statPapersTranslate(
                      "Certains articles n'ont pas été trouvés dans la revue."
                  )
                : ''
        );

        render();
    }

    async function load(figure) {
        if (cache.has(figure)) {
            return cache.get(figure);
        }

        const params = new URLSearchParams({ figure: figure });

        if (dialog.dataset.year) {
            params.set('year', dialog.dataset.year);
        }

        const response = await fetch(
            dialog.dataset.url + '?' + params.toString(),
            {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            }
        );

        if (!response.ok) {
            const error = new Error('HTTP ' + response.status);
            error.status = response.status;
            throw error;
        }

        const data = await response.json();
        cache.set(figure, data);
        return data;
    }

    async function open(figure) {
        titleEl.textContent = titles[figure] || '';
        state.rows = [];
        state.query = '';
        searchEl.value = '';
        hiddenEl.hidden = true;
        setColumns([], null);
        render();
        showMessage(statPapersTranslate('Chargement…'));

        if (typeof dialog.showModal === 'function') {
            if (!dialog.open) {
                dialog.showModal();
            }
        } else {
            dialog.setAttribute('open', '');
        }

        try {
            showData(await load(figure));
        } catch (error) {
            if (error.status === 404) {
                // lists kept in session for 30 minutes
                showMessage(
                    statPapersTranslate(
                        'La liste a expiré : veuillez recharger la page.'
                    ),
                    true
                );
            } else if (error.status === 403) {
                // logged out, or the session of the user has expired
                showMessage(
                    statPapersTranslate(
                        'Votre session a expiré : veuillez vous reconnecter.'
                    ),
                    true
                );
            } else {
                showMessage(
                    statPapersTranslate(
                        'Une erreur est survenue, veuillez réessayer.'
                    )
                );
            }
        }
    }

    function close() {
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
    }

    document.querySelectorAll('.stat-papers-open').forEach(button => {
        button.addEventListener('click', () => open(button.dataset.figure));
    });

    dialog.querySelector('.stat-papers-close').addEventListener('click', close);

    // click on the backdrop
    dialog.addEventListener('click', event => {
        if (event.target === dialog) {
            close();
        }
    });

    searchEl.addEventListener('input', () => {
        state.query = searchEl.value;
        state.page = 1;
        render();
    });

    // sort: the date headers change with the figure, so the clicks are listened on the header row
    headerRow.addEventListener('click', event => {
        const th = event.target.closest('th[data-sort]');

        if (!th || !event.target.closest('button')) {
            return;
        }

        const key = th.dataset.sort;
        state.sortDir =
            state.sortKey === key && state.sortDir === 'ascending'
                ? 'descending'
                : 'ascending';
        state.sortKey = key;
        state.page = 1;
        render();
    });

    prevEl.addEventListener('click', () => {
        state.page -= 1;
        render();
    });

    nextEl.addEventListener('click', () => {
        state.page += 1;
        render();
    });
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { statPapersView, statPapersCompare, initStatPapers };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStatPapers);
} else {
    initStatPapers();
}
