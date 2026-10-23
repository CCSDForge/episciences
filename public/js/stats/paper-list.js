/**
 * Papers of the statistics: the DOCIDs of a figure are displayed when its list is opened, by batches.
 */
const STAT_PAPERS_BATCH_SIZE = 100;
const STAT_PAPERS_URL = '/administratepaper/view/id/';

function appendStatPapers(details) {
    const docIds = JSON.parse(details.dataset.docids || '[]');
    const list = details.querySelector('.stat-papers-items');
    const start = list.children.length;
    const end = Math.min(start + STAT_PAPERS_BATCH_SIZE, docIds.length);

    for (let i = start; i < end; i++) {
        const item = document.createElement('li');
        const link = document.createElement('a');
        link.href = STAT_PAPERS_URL + encodeURIComponent(docIds[i]);
        link.textContent = docIds[i];
        link.target = '_blank';
        link.rel = 'noopener';
        item.appendChild(link);
        list.appendChild(item);
    }

    let more = details.querySelector('.stat-papers-more');

    if (end >= docIds.length) {
        more?.remove();
        return;
    }

    if (!more) {
        more = document.createElement('button');
        more.type = 'button';
        more.className = 'btn btn-default btn-xs stat-papers-more';
        more.textContent = translate('Afficher plus');
        more.addEventListener('click', () => appendStatPapers(details));
        details.appendChild(more);
    }
}

function initStatPapers() {
    document.querySelectorAll('details.stat-papers-list').forEach(details => {
        details.addEventListener('toggle', () => {
            if (
                details.open &&
                !details.querySelector('.stat-papers-items').children.length
            ) {
                appendStatPapers(details);
            }
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStatPapers);
} else {
    initStatPapers();
}
