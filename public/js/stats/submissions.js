$(function () {
    let context0 = $('#all-submissions-percentage');
    let context1 = $("#submissions-by-year-chart");
    let context2 = $("#submissions-by-repo-chart");
    let context3 = $('#submissions-delay-chart');

    let allPercentageOfSubmissionsStats = {
        labels: seriesFromView.allSubmissionsPercentage.labels,
        datasets: seriesFromView.allSubmissionsPercentage.datasets
    };

    let statsByYearData = {
        labels: yearCategoriesFromView,
        datasets: seriesFromView.submissionsByYear.datasets
    };


    let statsByRepoData = {
        labels: yearCategoriesFromView,
        datasets: seriesFromView.submissionsByRepo.repositories.datasets
    };

    let submissionDelay = {
        labels: yearCategoriesFromView,
        datasets: seriesFromView.submissionDelay.datasets
    }

    //figure1
    createChart(context0, allPercentageOfSubmissionsStats, seriesFromView.allSubmissionsPercentage.chartType, seriesFromView.allSubmissionsPercentage.title);
    //figure2
    createChart(context1, statsByYearData, seriesFromView.submissionsByYear.chartType, seriesFromView.submissionsByYear.title);
    createChart(context2, statsByRepoData, seriesFromView.submissionsByRepo.repositories.chartType, seriesFromView.submissionsByRepo.repositories.title);
    createChart(context3, submissionDelay, seriesFromView.submissionDelay.chartType, seriesFromView.submissionDelay.title);
});





