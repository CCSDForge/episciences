document.addEventListener('DOMContentLoaded', function () {
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
