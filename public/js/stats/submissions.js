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

    // Delays by year, in days: the mean or the median, following the selector of the delay table
    if (charts.delays && charts.delays.years.length > 0) {
        const showDelays = createDelayChart(
            'submissions-delay-chart',
            charts.delays
        );
        showDelays(currentStatsDelayMode());

        document.addEventListener(STATS_DELAY_MODE_EVENT, function (event) {
            showDelays(event.detail.mode);
        });
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

// event sent (on the selector, it bubbles up to the document) when the delay value to display changes
const STATS_DELAY_MODE_EVENT = 'stats:delay-mode';

/**
 * Selector "moyenne" / "médiane" of the delay table: only the chosen column is shown.
 * A client-side toggle (no page reload); the delays-by-year chart follows it (STATS_DELAY_MODE_EVENT).
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

            group.dispatchEvent(
                new CustomEvent(STATS_DELAY_MODE_EVENT, {
                    bubbles: true,
                    detail: { mode: mode },
                })
            );
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

/**
 * Delay value chosen with the selector of the delay table ("mean" without selector)
 * @returns {string} "mean" or "median"
 */
function currentStatsDelayMode() {
    const active = document.querySelector(
        '.stat-delay-mode .stat-delay-mode-btn.active'
    );

    return active && active.getAttribute('data-delay-mode') === 'median'
        ? 'median'
        : 'mean';
}

/**
 * Chart of the delays by year, which can show the mean or the median
 * @param {string} canvasId
 * @param {{years: string[], datasets: Array, medianDatasets: ?Array, titles: ?{mean: string, median: string}}} delays
 * @returns {function(string): void} shows the given mode ("mean" or "median")
 */
function createDelayChart(canvasId, delays) {
    let chart = null;

    return function (mode) {
        // without the median by year (older API), the chart keeps the mean
        const shownMode =
            mode === 'median' && Array.isArray(delays.medianDatasets)
                ? 'median'
                : 'mean';
        const title = delays.titles ? delays.titles[shownMode] : null;
        const canvas = document.getElementById(canvasId);

        if (!canvas) {
            return;
        }

        // the title of the panel and the label of the chart (also the caption of its table view)
        if (title) {
            canvas.setAttribute('aria-label', title);

            // the text only: the panel title also holds the collapse button
            const panel = canvas.closest('.panel');
            const panelTitle = panel
                ? panel.querySelector('.stat-chart-title')
                : null;

            if (panelTitle) {
                panelTitle.textContent = title;
            }
        }

        if (chart) {
            chart.destroy();
        }

        chart = createBarChart(
            canvasId,
            delays.years,
            shownMode === 'median' ? delays.medianDatasets : delays.datasets,
            {
                categoryLabel: statTranslate('Année'),
                format: value =>
                    value === null || value === undefined
                        ? '–'
                        : value + ' ' + statTranslate('jours'),
            }
        );
    };
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        STATS_DELAY_MODE_EVENT,
        initStatsDelayMode,
        currentStatsDelayMode,
        createDelayChart,
    };
}
