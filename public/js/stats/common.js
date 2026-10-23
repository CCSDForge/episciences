/**
 * Charts of the statistics page (Chart.js 4)
 *
 * Categorical colours: validated palette (light surface), always in this order and never cycled,
 * so that a series keeps its colour. Two of them are below 3:1 on white: every chart has a table view.
 */
const STAT_SERIES_COLORS = [
    '#2a78d6', // blue
    '#eb6834', // orange
    '#1baf7a', // aqua
    '#eda100', // yellow
    '#e87ba4', // magenta
    '#008300', // green
    '#6250d6', // violet
    '#e34948', // red
];
const STAT_TEXT_COLOR = '#52514e';
const STAT_GRID_COLOR = '#e8e7e3';

function statTranslate(key) {
    return typeof translate === 'function' ? translate(key) : key;
}

/**
 * Width of a group of bars from its number of series: the bars of a year stay side by side
 */
function statGroupWidth(seriesCount) {
    if (seriesCount <= 1) {
        return 0.6; // a single series: no group to keep together
    }

    return Math.min(0.8, 0.16 * seriesCount + 0.04);
}

/**
 * A bar series, coloured by its position
 */
function statBarDataset(dataset, index) {
    return Object.assign({}, dataset, {
        backgroundColor:
            STAT_SERIES_COLORS[Math.min(index, STAT_SERIES_COLORS.length - 1)],
        borderRadius: 4,
        borderSkipped: 'start', // rounded end, anchored to the baseline
        barPercentage: 0.94, // ~2px between the bars of a group
    });
}

/**
 * Pure helper: Chart.js configuration of a bar chart of the statistics page
 * @param {string[]} labels categories (years, roles…)
 * @param {object[]} datasets [{label, data}]
 * @param {object} options horizontal: bars along the x axis; valueLabels: the values at the end of the bars
 *                         (only for a single series); legend: false to hide it
 */
function buildBarChartConfig(labels, datasets, options = {}) {
    const horizontal = options.horizontal === true;
    const valueAxis = {
        beginAtZero: true,
        grid: { color: STAT_GRID_COLOR },
        border: { display: false },
        ticks: { color: STAT_TEXT_COLOR, precision: 0 },
    };
    const categoryAxis = {
        grid: { display: false },
        border: { color: STAT_GRID_COLOR },
        ticks: { color: STAT_TEXT_COLOR },
    };

    const config = {
        type: 'bar',
        data: {
            labels: labels,
            datasets: datasets.map(statBarDataset),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // the height is the one of the container
            interaction: { mode: 'index', intersect: false },
            datasets: {
                bar: { categoryPercentage: statGroupWidth(datasets.length) },
            },
            scales: horizontal
                ? { x: valueAxis, y: categoryAxis }
                : { x: categoryAxis, y: valueAxis },
            plugins: {
                legend: {
                    display: options.legend !== false,
                    position: 'top',
                    align: 'start',
                    labels: {
                        color: STAT_TEXT_COLOR,
                        boxWidth: 12,
                        boxHeight: 12,
                        useBorderRadius: true,
                        borderRadius: 2,
                    },
                },
                tooltip: { padding: 8 },
                datalabels: { display: false },
            },
        },
        plugins: [],
    };

    if (horizontal) {
        config.options.indexAxis = 'y';
    }

    if (options.valueLabels && typeof ChartDataLabels !== 'undefined') {
        config.plugins.push(ChartDataLabels);
        config.options.plugins.datalabels = {
            display: true,
            anchor: 'end',
            align: 'end',
            color: '#0b0b0b',
            font: { size: 11 },
        };
        config.options.layout = { padding: { right: 32, top: 16 } };
    }

    return config;
}

/**
 * Table view of a chart: the same values, readable without the colours
 * @param {string} caption
 * @param {string[]} labels
 * @param {object[]} datasets [{label, data}]
 * @param {function} format of a value
 * @returns {HTMLDetailsElement}
 */
function buildChartTable(caption, labels, datasets, format = null) {
    const formatValue =
        format ||
        (value => (value === null || value === undefined ? '–' : value));
    const details = document.createElement('details');
    details.className = 'stat-chart-table';

    const summary = document.createElement('summary');
    summary.textContent = statTranslate('Voir les données');
    details.appendChild(summary);

    const table = document.createElement('table');
    table.className = 'table table-condensed';

    const tableCaption = document.createElement('caption');
    tableCaption.className = 'sr-only';
    tableCaption.textContent = caption;
    table.appendChild(tableCaption);

    const head = table.createTHead().insertRow();
    head.appendChild(document.createElement('th'));
    datasets.forEach(dataset => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.textContent = dataset.label;
        head.appendChild(th);
    });

    const body = table.createTBody();
    labels.forEach((label, i) => {
        const row = body.insertRow();
        const th = document.createElement('th');
        th.scope = 'row';
        th.textContent = label;
        row.appendChild(th);
        datasets.forEach(dataset => {
            row.insertCell().textContent = formatValue(dataset.data[i]);
        });
    });

    details.appendChild(table);
    return details;
}

/**
 * Draws a bar chart in its canvas, and its table view in the panel
 * @returns {Chart|null}
 */
function createBarChart(canvasId, labels, datasets, options = {}) {
    const canvas = document.getElementById(canvasId);

    if (!canvas || !Array.isArray(datasets) || datasets.length === 0) {
        return null;
    }

    const tableContainer = document.querySelector(
        '.stat-chart-data[data-chart="' + canvasId + '"]'
    );

    if (tableContainer) {
        tableContainer.replaceChildren(
            buildChartTable(
                canvas.getAttribute('aria-label') || '',
                labels,
                datasets,
                options.format
            )
        );
    }

    if (typeof Chart === 'undefined') {
        return null;
    }

    return new Chart(canvas, buildBarChartConfig(labels, datasets, options));
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        STAT_SERIES_COLORS,
        statGroupWidth,
        buildBarChartConfig,
        buildChartTable,
        createBarChart,
    };
}
