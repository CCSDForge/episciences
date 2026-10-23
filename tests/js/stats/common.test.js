/**
 * Charts of the statistics page: Chart.js 4 configuration
 */

const path = require('path');

const { buildChartConfig, createChart } = require(
    path.join(__dirname, '../../../public/js/stats/common.js')
);

function data() {
    return {
        labels: [2024, 2025],
        datasets: [{ label: 'Submissions', data: [10, 20] }],
    };
}

describe('buildChartConfig', function () {
    afterEach(function () {
        delete global.ChartDataLabels;
    });

    it('builds a vertical bar chart with Chart.js 4 scales', function () {
        const config = buildChartConfig(data(), 'bar');

        expect(config.type).toBe('bar');
        expect(config.options.indexAxis).toBeUndefined();
        expect(config.options.scales.y).toEqual({
            beginAtZero: true,
            grid: { display: true },
            grace: '10%',
        });
        expect(config.options.scales.x).toEqual({ grid: { display: true } });
        // no Chart.js 2 options left
        expect(config.options.scales.xAxes).toBeUndefined();
        expect(config.options.scales.yAxes).toBeUndefined();
        expect(config.options.legend).toBeUndefined();
        expect(config.options.aspectRatio).toBeUndefined();
    });

    it('builds horizontal bars with indexAxis instead of horizontalBar', function () {
        const config = buildChartConfig(data(), 'barH');

        expect(config.type).toBe('bar');
        expect(config.options.indexAxis).toBe('y');
        expect(config.options.scales.x.beginAtZero).toBe(true);
        expect(config.options.scales.x.grace).toBe('10%');
        expect(config.options.scales.y).toEqual({ grid: { display: true } });
    });

    it('builds pie and doughnut charts without scales, with percentages', function () {
        ['pie', 'doughnut'].forEach(type => {
            const config = buildChartConfig(data(), type);

            expect(config.type).toBe(type);
            expect(config.options.scales).toBeUndefined();
            expect(config.options.plugins.datalabels.formatter(42.5)).toBe(
                '42.5%'
            );
            expect(config.options.plugins.datalabels.anchor).toBe('center');
            expect(config.options.aspectRatio).toBe(2);
        });
    });

    it('is a pie chart by default', function () {
        expect(buildChartConfig(data()).type).toBe('pie');
    });

    it('keeps the values of the bars as they are', function () {
        const config = buildChartConfig(data(), 'bar');

        expect(config.options.plugins.datalabels.formatter(12)).toBe(12);
        expect(config.options.plugins.datalabels.anchor).toBe('end');
    });

    it('puts the legend and the title in the plugins options', function () {
        const config = buildChartConfig(data(), 'bar', 'Submissions by year');

        expect(config.options.plugins.legend).toEqual({
            display: true,
            position: 'bottom',
        });
        expect(config.options.plugins.title).toEqual({
            display: true,
            text: 'Submissions by year',
        });
        expect(
            buildChartConfig(data(), 'bar').options.plugins.title.display
        ).toBe(false);
    });

    it('limits the bar thickness', function () {
        const config = buildChartConfig(data(), 'bar');

        expect(config.data.datasets[0].maxBarThickness).toBe(50);
    });

    it('registers the data labels plugin for the chart when it is loaded', function () {
        expect(buildChartConfig(data(), 'bar').plugins).toEqual([]);

        global.ChartDataLabels = { id: 'datalabels' };
        expect(buildChartConfig(data(), 'bar').plugins).toEqual([
            global.ChartDataLabels,
        ]);
    });
});

describe('createChart', function () {
    afterEach(function () {
        delete global.Chart;
    });

    it('does nothing without canvas or without Chart.js', function () {
        expect(createChart(null, data(), 'bar')).toBeNull();
        expect(
            createChart(document.createElement('canvas'), data(), 'bar')
        ).toBeNull();
    });

    it('creates the chart on the canvas', function () {
        global.Chart = jest.fn(function (canvas, config) {
            this.canvas = canvas;
            this.config = config;
        });
        const canvas = document.createElement('canvas');

        const chart = createChart(canvas, data(), 'barH');

        expect(global.Chart).toHaveBeenCalledTimes(1);
        expect(chart.canvas).toBe(canvas);
        expect(chart.config.options.indexAxis).toBe('y');
    });
});
