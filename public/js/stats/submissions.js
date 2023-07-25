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

    if(!isEmptyData(allPercentageOfSubmissionsStats.datasets[0].data)){
        createChart(context0, allPercentageOfSubmissionsStats, seriesFromView.allSubmissionsPercentage.chartType);
    }

    if(!isEmptyData(statsByYearData.datasets[0].data)){
        createChart(context1, statsByYearData, seriesFromView.submissionsByYear.chartType);
    }

    if(!isEmptyData(statsByRepoData.datasets[0].data)){
        createChart(context2, statsByRepoData, seriesFromView.submissionsByRepo.repositories.chartType);
    }

    if(!isEmptyData(submissionDelay.datasets[0].data)){
        createChart(context3, submissionDelay, seriesFromView.submissionDelay.chartType);
    }
});





