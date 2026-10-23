document.addEventListener('DOMContentLoaded', function () {
    const allPercentageOfSubmissionsStats = {
        labels: seriesFromView.allSubmissionsPercentage.labels,
        datasets: seriesFromView.allSubmissionsPercentage.datasets
            ? seriesFromView.allSubmissionsPercentage.datasets
            : null,
    };

    const statsByYearData = {
        labels: yearCategoriesFromView,
        datasets: seriesFromView.submissionsByYear.datasets
            ? seriesFromView.submissionsByYear.datasets
            : null,
    };

    const statsByRepoData = {
        labels: yearCategoriesFromView,
        datasets: seriesFromView.submissionsByRepo.repositories.datasets
            ? seriesFromView.submissionsByRepo.repositories.datasets
            : null,
    };

    const submissionDelay = {
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
            document.getElementById('all-submissions-percentage'),
            allPercentageOfSubmissionsStats,
            seriesFromView.allSubmissionsPercentage.chartType
        );
    }

    if (
        statsByYearData.datasets &&
        !isEmptyData(statsByYearData.datasets[0].data)
    ) {
        createChart(
            document.getElementById('submissions-by-year-chart'),
            statsByYearData,
            seriesFromView.submissionsByYear.chartType
        );
    }

    if (
        statsByRepoData.datasets &&
        !isEmptyData(statsByRepoData.datasets[0].data)
    ) {
        createChart(
            document.getElementById('submissions-by-repo-chart'),
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
            document.getElementById('submissions-delay-chart'),
            submissionDelay,
            seriesFromView.submissionDelay.chartType
        );
    }
});
