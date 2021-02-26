function createChart(context, data, type = null, title = '') {
    let isPieChart = type === 'pie';

    for (let i = 0; i < data.datasets.length; i++) {
        Object.assign(data.datasets[i], {maxBarThickness: 50}); // number (pixels)
    }

    let options = {
        legend: {
            display: true,
            position: 'bottom'
        },
        plugins: {
            title: {
                display: true,
                text: title
            },
            datalabels: {
                formatter: function (value, context) {
                    return !isPieChart ? value : value + '%';
                },
                align: isPieChart ? 'center' : 'end',
                anchor: isPieChart ? 'center' : 'end',
                font: {
                    size: "11",
                    weight: "bold"
                },
                color: '#5627a8'
            }
        },
    };

    if (!isPieChart) {
        let scalesOptions = {
            scales: {
                xAxes: [{
                    gridLines: {
                        display: true
                    },
                    scaleLabel: {
                        display: true,
                        labelString: ""
                    },
                }],
                yAxes: [{
                    gridLines: {
                        display: true
                    },
                    scaleLabel: {
                        display: true,
                        labelString: ""
                    },
                }],
            }
        }
        Object.assign(options, scalesOptions);
    }

    if (type === 'barH') {
        //options['indexAxis'] = 'y'; possible migration to v3 (currently in beta version)
        type = 'horizontalBar';
    }

    return new Chart(context, {
        data: data,
        type: (type !== null) ? type : 'pie',
        options: options
    });
}