$(function () {
    let context0 = $('#all-submissions-percentage');
    let context1 = $('#submissions-by-year-chart');
    let context2 = $('#submissions-by-repo-chart');
    let context3 = $('#submissions-delay-chart');

    let allPercentageOfSubmissionsStats = {
        labels: seriesFromView.allSubmissionsPercentage.labels,
        datasets: seriesFromView.allSubmissionsPercentage.datasets
            ? seriesFromView.allSubmissionsPercentage.datasets
            : null,
    };

    let statsByYearData = {
        labels: yearCategoriesFromView,
        datasets: seriesFromView.submissionsByYear.datasets
            ? seriesFromView.submissionsByYear.datasets
            : null,
    };

    let statsByRepoData = {
        labels: yearCategoriesFromView,
        datasets: seriesFromView.submissionsByRepo.repositories.datasets
            ? seriesFromView.submissionsByRepo.repositories.datasets
            : null,
    };

    let submissionDelay = {
        labels: yearCategoriesFromView,
        datasets: seriesFromView.submissionDelay.datasets
            ? seriesFromView.submissionDelay.datasets
            : null,
    };

    if (
        allPercentageOfSubmissionsStats.datasets &&
        !isEmptyData(allPercentageOfSubmissionsStats.datasets[0].data)
    ) {
        createChart(
            context0,
            allPercentageOfSubmissionsStats,
            seriesFromView.allSubmissionsPercentage.chartType
        );
    }

    if (
        statsByYearData.datasets &&
        !isEmptyData(statsByYearData.datasets[0].data)
    ) {
        createChart(
            context1,
            statsByYearData,
            seriesFromView.submissionsByYear.chartType
        );
    }

    if (
        statsByRepoData.datasets &&
        !isEmptyData(statsByRepoData.datasets[0].data)
    ) {
        createChart(
            context2,
            statsByRepoData,
            seriesFromView.submissionsByRepo.repositories.chartType
        );
    }

    if (
        submissionDelay.datasets &&
        (submissionDelay.datasets[0].data.length > 0 ||
            submissionDelay.datasets[1].data.length > 0)
    ) {
        createChart(
            context3,
            submissionDelay,
            seriesFromView.submissionDelay.chartType
        );
    }
});
