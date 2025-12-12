function createChart(context, data, type = null, title = '') {
    let isPieChart = type === 'pie';

    for (let i = 0; i < data.datasets.length; i++) {
        Object.assign(data.datasets[i], { maxBarThickness: 50 }); // number (pixels)
    }

    let options = {
        legend: {
            display: true,
            position: 'bottom',
        },
        plugins: {
            title: {
                display: true,
                text: title,
            },
            datalabels: {
                formatter: function (value, context) {
                    return !isPieChart ? value : value + '%';
                },
                align: isPieChart ? 'center' : 'end',
                anchor: isPieChart ? 'center' : 'end',
                font: {
                    size: '8',
                    weight: 'bold',
                },
                color: '#5627a8',
            },
        },
    };

    if (!isPieChart) {
        let scalesOptions = {
            scales: {
                xAxes: [
                    {
                        ticks: {
                            beginAtZero: true,
                        },
                        gridLines: {
                            display: true,
                        },
                        scaleLabel: {
                            display: true,
                            labelString: '',
                        },
                        afterDataLimits(scale) {
                            // Prevent hiding data label value when the constraints of the graph are reached.
                            scale.max += 1;
                        },
                    },
                ],
                yAxes: [
                    {
                        ticks: {
                            beginAtZero: true,
                        },
                        gridLines: {
                            display: true,
                        },
                        scaleLabel: {
                            display: true,
                            labelString: '',
                        },
                        afterDataLimits(scale) {
                            // Prevent hiding data label value when the constraints of the graph are reached.
                            scale.max += 20;
                        },
                    },
                ],
            },
        };
        Object.assign(options, scalesOptions);
    }

    if (type === 'barH') {
        //options['indexAxis'] = 'y'; possible migration to v3 (currently in beta version)
        type = 'horizontalBar';
    }

    return new Chart(context, {
        data: data,
        type: type !== null ? type : 'pie',
        options: options,
    });
}
