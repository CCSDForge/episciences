document.addEventListener('DOMContentLoaded', function () {
    initStatsDelayMode();

    if (typeof statsChartsFromView === 'undefined' || !statsChartsFromView) {
        return;
    }

    const charts = statsChartsFromView;
    const years = charts.years || [];

    // Submissions (without the imported papers), decisions and publications by year
    if (charts.byYear) {
        createBarChart(
            'submissions-by-year-chart',
            years,
            charts.byYear.datasets,
            { categoryLabel: statTranslate('Année') }
        );
    }

    // Submissions by repository (without the imported papers)
    if (charts.byRepo) {
        createBarChart(
            'submissions-by-repo-chart',
            years,
            charts.byRepo.datasets,
            {
                stacked: charts.byRepo.stacked === true,
                categoryLabel: statTranslate('Année'),
            }
        );
    }

    // Average delays, in days
    if (charts.delays && charts.delays.years.length > 0) {
        createBarChart(
            'submissions-delay-chart',
            charts.delays.years,
            charts.delays.datasets,
            {
                categoryLabel: statTranslate('Année'),
                format: value =>
                    value === null || value === undefined
                        ? '–'
                        : value + ' ' + statTranslate('jours'),
            }
        );
    }

    // the figures of the page (CSV)
    const figuresButton = document.querySelector('.stats-export-figures');

    if (
        figuresButton &&
        typeof statsFiguresFromView !== 'undefined' &&
        typeof downloadCsv === 'function'
    ) {
        figuresButton.addEventListener('click', function () {
            downloadCsv(
                statCsvFileName([statsExportName(), 'chiffres']),
                [[statTranslate('Indicateur'), statTranslate('Valeur')]].concat(
                    statsFiguresFromView
                )
            );
        });
    }
});

/**
 * Selector "moyenne" / "médiane" of the delay table: only the chosen column is shown.
 * A client-side toggle (no page reload); the delays-by-year chart is not affected
 * (it always plots the per-year mean).
 */
function initStatsDelayMode() {
    document.querySelectorAll('.stat-delay-mode').forEach(function (group) {
        const table = group.parentElement
            ? group.parentElement.querySelector('.stat-delays')
            : null;

        if (!table) {
            return;
        }

        // :target cells of the table, e.g. header <th> and value <td> of the chosen column
        const selectMode = function (mode) {
            group
                .querySelectorAll('.stat-delay-mode-btn')
                .forEach(function (btn) {
                    const active = btn.getAttribute('data-delay-mode') === mode;
                    btn.classList.toggle('active', active);
                    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                });

            table.querySelectorAll('[data-delay]').forEach(function (cell) {
                cell.hidden = cell.getAttribute('data-delay') !== mode;
            });
        };

        // the page works without JavaScript: the mean column is the visible default
        selectMode('mean');

        group.querySelectorAll('.stat-delay-mode-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selectMode(btn.getAttribute('data-delay-mode'));
            });
        });
    });
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { initStatsDelayMode };
}
