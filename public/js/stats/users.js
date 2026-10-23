document.addEventListener('DOMContentLoaded', function () {
    const statsByRolesData = {
        labels: rolesFromView,
        datasets: nbUsersByRoleFromView.datasets,
    };
    createChart(
        document.getElementById('users-by-roles-chart'),
        statsByRolesData,
        nbUsersByRoleFromView.chartType
    );
});
