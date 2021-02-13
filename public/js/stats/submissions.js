$(function () {
    let context0 = $('#all-submissions-percentage');
    let context1 = $("#submissions-by-year-chart");
    let context2 = $("#submissions-by-repo-chart");
    let context3 = $('#submissions-delay-chart');

    let type = chartTypeFromView;

    let allPercentageOfSubmissionsStats = {
        labels: seriesFromView.allSubmissionsPercentage.labels,
        datasets: seriesFromView.allSubmissionsPercentage.datasets
    };

    let statsByYearData = {
        labels: yearCategoriesFromView,
        datasets: seriesFromView.submissionsByYear
    };


    let statsByRepoData = {
        labels: yearCategoriesFromView,
        datasets: seriesFromView.submissionsByRepo.repositories
    };

    let submissionDelay = {
        labels: yearCategoriesFromView,
        datasets: seriesFromView.submissionDelay.datasets
    }

    createChart(context0, allPercentageOfSubmissionsStats, seriesFromView.allSubmissionsPercentage.chartType, translate("Soumissions en %"));
    createChart(context1, statsByYearData, type, translate("Par année, la répartition des soumissions, articles publiés et articles refusés"));
    createChart(context2, statsByRepoData, type, translate("Répartition des soumissions par année et par archive"));
    createChart(context3, submissionDelay, seriesFromView.submissionDelay.chartType, translate('Délai moyen en jours entre "dépôt et acceptation" et "dépôt et publication"'));
});





