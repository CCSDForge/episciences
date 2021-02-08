document.addEventListener('DOMContentLoaded', function () {
    const chart = Highcharts.chart('submissionByYear', {
        chart: {
            type: 'column'
        },
        title: {
            text: translate('Répartition des ') + allSubmissionsFromView + ' ' + translate('articles soumis') + ' ' + translate('par année')
        },

        credits: {
            enabled: false
        },

        xAxis: {
            categories: yearCategoriesFromView
        },
        yAxis: {
            title: {
                text: translate('Nombre de soumissions')
            }
        },
        series: seriesFromView

    });
});


