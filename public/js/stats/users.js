$(function () {
    let context = $('#users-by-roles-chart');
    let statsByRolesData = {
        labels: rolesFromView,
        datasets: nbUsersByRoleFromView.datasets,
    };
    createChart(context, statsByRolesData, nbUsersByRoleFromView.chartType);
});
