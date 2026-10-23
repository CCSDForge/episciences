/**
 * Charts of the statistics page (Chart.js 4, chartjs-plugin-datalabels 2)
 */

/**
 * Pure helper: Chart.js configuration of a chart of the statistics page
 * @param {object} data labels and datasets
 * @param {string|null} type pie, doughnut, bar, barH (horizontal bars) or line; pie by default
 * @param {string} title
 * @returns {object}
 */
function buildChartConfig(data, type = null, title = '') {
    const isPieChart = type === 'pie' || type === 'doughnut';
    const isHorizontal = type === 'barH';

    data.datasets.forEach(dataset => {
        Object.assign(dataset, { maxBarThickness: 50 }); // pixels
    });

    const options = {
        plugins: {
            legend: {
                display: true,
                position: 'bottom',
            },
            title: {
                display: title !== '',
                text: title,
            },
            datalabels: {
                formatter: value => (isPieChart ? value + '%' : value),
                align: isPieChart ? 'center' : 'end',
                anchor: isPieChart ? 'center' : 'end',
                font: {
                    size: 8,
                    weight: 'bold',
                },
                color: '#5627a8',
            },
        },
    };

    if (!isPieChart) {
        const valueAxis = isHorizontal ? 'x' : 'y';
        const categoryAxis = isHorizontal ? 'y' : 'x';

        options.scales = {
            [categoryAxis]: {
                grid: { display: true },
            },
            [valueAxis]: {
                beginAtZero: true,
                grid: { display: true },
                // room for the data labels at the end of the bars
                grace: '10%',
            },
        };
    }

    if (isHorizontal) {
        options.indexAxis = 'y';
    }

    if (isPieChart) {
        // as with Chart.js 2: Chart.js 4 draws the pie and doughnut charts in a square by default
        options.aspectRatio = 2;
    }

    return {
        type: isHorizontal ? 'bar' : type !== null ? type : 'pie',
        data: data,
        options: options,
        plugins:
            typeof ChartDataLabels !== 'undefined' ? [ChartDataLabels] : [],
    };
}

/**
 * @param {HTMLCanvasElement|null} canvas
 * @param {object} data labels and datasets
 * @param {string|null} type
 * @param {string} title
 * @returns {Chart|null}
 */
function createChart(canvas, data, type = null, title = '') {
    if (!canvas || typeof Chart === 'undefined') {
        return null;
    }

    return new Chart(canvas, buildChartConfig(data, type, title));
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { buildChartConfig, createChart };
}
