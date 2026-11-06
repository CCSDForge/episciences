document.addEventListener('DOMContentLoaded', function () {
    if (
        typeof statsChartsFromView === 'undefined' ||
        !statsChartsFromView ||
        !statsChartsFromView.users
    ) {
        return;
    }

    // One series, sorted from the largest number: horizontal bars (long role names), values at the end of the bars
    createBarChart(
        'users-by-roles-chart',
        statsChartsFromView.users.labels,
        statsChartsFromView.users.datasets,
        {
            horizontal: true,
            valueLabels: true,
            legend: false,
            categoryLabel: statTranslate('Rôle'),
            // the total of the users (not the sum of the roles)
            csvFooterRows: statsChartsFromView.users.csvFooterRows || [],
        }
    );
});
