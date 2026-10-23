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
            charts.byYear.datasets
        );
    }

    // Submissions by repository (without the imported papers)
    if (charts.byRepo) {
        createBarChart(
            'submissions-by-repo-chart',
            years,
            charts.byRepo.datasets,
            { stacked: charts.byRepo.stacked === true }
        );
    }

    // Average delays, in days
    if (charts.delays && charts.delays.years.length > 0) {
        createBarChart(
            'submissions-delay-chart',
            charts.delays.years,
            charts.delays.datasets,
            {
                format: value =>
                    value === null || value === undefined
                        ? '–'
                        : value + ' ' + statTranslate('jours'),
            }
        );
    }
});
