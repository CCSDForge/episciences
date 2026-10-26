/**
 * Charts of the statistics page: Chart.js 4 configuration and table view
 */

const path = require('path');

const {
    STAT_SERIES_COLORS,
    statGroupWidth,
    statChartMaxWidth,
    buildBarChartConfig,
    buildChartTable,
    buildChartCsvRows,
    createBarChart,
    statArticlesText,
} = require(path.join(__dirname, '../../../public/js/stats/common.js'));

function datasets() {
    return [
        { label: 'Submissions', data: [3, 27, 29] },
        { label: 'Accepted', data: [0, 11, 13] },
    ];
}

describe('buildBarChartConfig', function () {
    afterEach(function () {
        delete global.ChartDataLabels;
    });

    it('builds a vertical bar chart with Chart.js 4 options', function () {
        const config = buildBarChartConfig(
            ['2022', '2023', '2024'],
            datasets()
        );

        expect(config.type).toBe('bar');
        expect(config.options.indexAxis).toBeUndefined();
        expect(config.options.scales.y.beginAtZero).toBe(true);
        expect(config.options.scales.y.ticks.precision).toBe(0);
        expect(config.options.scales.x.grid.display).toBe(false);
        expect(config.options.maintainAspectRatio).toBe(false);
        expect(config.options.interaction).toEqual({
            mode: 'index',
            intersect: false,
        });
        expect(config.options.plugins.legend.display).toBe(true);
        // no value on every bar
        expect(config.options.plugins.datalabels.display).toBe(false);
        expect(config.plugins).toEqual([]);
    });

    it('colours the series by their position, in the fixed order', function () {
        const config = buildBarChartConfig(
            ['2022'],
            [
                { label: 'a', data: [1] },
                { label: 'b', data: [2] },
                { label: 'c', data: [3] },
            ]
        );

        expect(config.data.datasets.map(d => d.backgroundColor)).toEqual(
            STAT_SERIES_COLORS.slice(0, 3)
        );
        expect(STAT_SERIES_COLORS.slice(0, 4)).toEqual([
            '#2a78d6',
            '#eb6834',
            '#1baf7a',
            '#eda100',
        ]);
    });

    it('does not change the data nor the given datasets', function () {
        const given = datasets();
        const config = buildBarChartConfig(['2022', '2023', '2024'], given);

        expect(config.data.datasets[0].data).toEqual([3, 27, 29]);
        expect(given[0].backgroundColor).toBeUndefined();
    });

    it('keeps the bars of a group side by side', function () {
        expect(statGroupWidth(1)).toBe(0.6);
        expect(statGroupWidth(2)).toBeCloseTo(0.36);
        expect(statGroupWidth(4)).toBeCloseTo(0.68);
        expect(statGroupWidth(8)).toBe(0.8);
        expect(
            buildBarChartConfig(['2022'], datasets()).options.datasets.bar
                .categoryPercentage
        ).toBeCloseTo(0.36);
    });

    it('builds horizontal bars with the values at their end', function () {
        global.ChartDataLabels = { id: 'datalabels' };

        const config = buildBarChartConfig(
            ['Member', 'Reviewer'],
            [{ label: 'Users', data: [203, 88] }],
            { horizontal: true, valueLabels: true, legend: false }
        );

        expect(config.options.indexAxis).toBe('y');
        expect(config.options.scales.x.beginAtZero).toBe(true);
        expect(config.options.scales.y.grid.display).toBe(false);
        expect(config.options.plugins.legend.display).toBe(false);
        expect(config.plugins).toEqual([global.ChartDataLabels]);
        expect(config.options.plugins.datalabels.display).toBe(true);
        expect(config.options.plugins.datalabels.anchor).toBe('end');
    });
});

describe('statChartMaxWidth', function () {
    it('does not widen the chart of a single year', function () {
        // one year, four series: about 48px by bar
        expect(statChartMaxWidth(1, 4)).toBe(332);
        expect(statChartMaxWidth(1, 1)).toBe(320);
        // five years: wider than the panel, the chart takes the whole width
        expect(statChartMaxWidth(5, 4)).toBeGreaterThan(1200);
    });
});

describe('stacked bars', function () {
    it('stacks the series in one bar by category, with the total in the tooltip', function () {
        const config = buildBarChartConfig(['2023', '2024'], datasets(), {
            stacked: true,
        });

        expect(config.options.scales.x.stacked).toBe(true);
        expect(config.options.scales.y.stacked).toBe(true);
        expect(config.options.datasets.bar.categoryPercentage).toBe(
            statGroupWidth(1)
        );
        expect(config.data.datasets[0].borderWidth).toEqual({ top: 2 });
        expect(config.data.datasets[0].borderColor).toBe('#fff');
        expect(
            config.options.plugins.tooltip.callbacks.footer([
                { parsed: { y: 27 } },
                { parsed: { y: 3 } },
            ])
        ).toBe('Total : 30');
    });

    it('does not stack by default', function () {
        const config = buildBarChartConfig(['2023'], datasets());

        expect(config.options.scales.y.stacked).toBeUndefined();
        expect(config.options.plugins.tooltip.callbacks).toBeUndefined();
    });
});

describe('buildChartCsvRows', function () {
    it('gives a header and a row by category, with the raw values', function () {
        expect(
            buildChartCsvRows(
                'Year',
                ['2023', '2024'],
                [
                    { label: 'Acceptance', data: [57, null] },
                    { label: 'Publication', data: [234, 277] },
                ]
            )
        ).toEqual([
            ['Year', 'Acceptance', 'Publication'],
            ['2023', 57, 234],
            ['2024', '', 277],
        ]);
    });
});

describe('buildChartTable', function () {
    it('lists the values of each series by category', function () {
        const details = buildChartTable(
            'Submissions by year',
            ['2022', '2023'],
            [
                { label: 'Submissions', data: [3, 27] },
                { label: 'Delay', data: [null, 57] },
            ]
        );

        expect(details.tagName).toBe('DETAILS');
        expect(details.querySelector('summary').textContent).toBe(
            'Voir les données'
        );
        expect(details.querySelector('caption').textContent).toBe(
            'Submissions by year'
        );
        expect(
            Array.from(details.querySelectorAll('thead th')).map(
                th => th.textContent
            )
        ).toEqual(['', 'Submissions', 'Delay']);

        const rows = Array.from(details.querySelectorAll('tbody tr')).map(tr =>
            Array.from(tr.children).map(cell => cell.textContent)
        );
        expect(rows).toEqual([
            ['2022', '3', '–'],
            ['2023', '27', '57'],
        ]);
    });

    it('formats the values', function () {
        const details = buildChartTable(
            '',
            ['2023'],
            [{ label: 'Delay', data: [57] }],
            value => value + ' days'
        );

        expect(details.querySelector('tbody td').textContent).toBe('57 days');
    });

    it('inserts the labels as text', function () {
        const details = buildChartTable(
            '',
            ['<img src=x onerror=alert(1)>'],
            [{ label: '<b>HAL</b>', data: [1] }]
        );

        expect(details.querySelector('img')).toBeNull();
        expect(details.querySelector('b')).toBeNull();
        expect(details.querySelector('tbody th').textContent).toBe(
            '<img src=x onerror=alert(1)>'
        );
    });
});

describe('createBarChart', function () {
    beforeEach(function () {
        document.body.innerHTML = `
            <canvas id="by-year" aria-label="Submissions by year"></canvas>
            <div class="stat-chart-data" data-chart="by-year"></div>
        `;
    });

    afterEach(function () {
        delete global.Chart;
    });

    it('draws the chart and its table view', function () {
        global.Chart = jest.fn(function (canvas, config) {
            this.canvas = canvas;
            this.config = config;
        });

        const chart = createBarChart(
            'by-year',
            ['2022', '2023', '2024'],
            datasets()
        );

        expect(global.Chart).toHaveBeenCalledTimes(1);
        expect(chart.canvas.id).toBe('by-year');
        expect(chart.config.type).toBe('bar');
        expect(
            document.querySelectorAll('.stat-chart-data tbody tr')
        ).toHaveLength(3);
        // several years: the whole width
        expect(chart.canvas.parentElement.style.maxWidth).toBe('');
    });

    it('narrows the chart of a single year', function () {
        global.Chart = jest.fn();
        document.body.innerHTML = `
            <div class="stat-chart"><canvas id="one-year"></canvas></div>
        `;

        createBarChart('one-year', ['2026'], datasets());

        expect(document.querySelector('.stat-chart').style.maxWidth).toBe(
            statChartMaxWidth(1, 2) + 'px'
        );
    });

    it('does nothing without canvas or data', function () {
        global.Chart = jest.fn();

        expect(createBarChart('missing', ['2022'], datasets())).toBeNull();
        expect(createBarChart('by-year', ['2022'], [])).toBeNull();
        expect(global.Chart).not.toHaveBeenCalled();
    });

    it('adds the CSV export of the chart under its table', function () {
        const csv = require(
            path.join(__dirname, '../../../public/js/stats/csv.js')
        );
        Object.assign(global, csv);
        global.Chart = jest.fn();

        createBarChart('by-year', ['2022', '2023', '2024'], datasets(), {
            categoryLabel: 'Year',
        });

        expect(
            document.querySelector('.stat-chart-data .stat-csv-button')
        ).not.toBeNull();

        Object.keys(csv).forEach(key => delete global[key]);
    });

    it('still shows the table view without Chart.js', function () {
        expect(
            createBarChart('by-year', ['2022', '2023', '2024'], datasets())
        ).toBeNull();
        expect(document.querySelector('.stat-chart-table')).not.toBeNull();
    });
});

describe('number of articles of a value', function () {
    const delays = [
        { label: 'Delay', data: [57, 162, null], counts: [4, 1, null] },
    ];
    const format = value =>
        value === null || value === undefined ? '–' : value + ' jours';

    it('is written in the singular or the plural, empty when unknown', function () {
        expect(statArticlesText(4)).toBe('4 articles');
        expect(statArticlesText(1)).toBe('1 article');
        expect(statArticlesText(null)).toBe('');
    });

    it('follows the value in the tooltip', function () {
        const label = buildBarChartConfig(['2023', '2024', '2025'], delays, {
            format: format,
        }).options.plugins.tooltip.callbacks.label;

        expect(label({ datasetIndex: 0, dataIndex: 0 })).toBe(
            'Delay : 57 jours (4 articles)'
        );
        expect(label({ datasetIndex: 0, dataIndex: 2 })).toBe('Delay : –');
    });

    it('follows the value in the table view', function () {
        const rows = Array.from(
            buildChartTable(
                'Delays',
                ['2023', '2024'],
                delays,
                format
            ).querySelectorAll('tbody tr')
        ).map(tr => tr.children[1].textContent);

        expect(rows).toEqual([
            '57 jours (4 articles)',
            '162 jours (1 article)',
        ]);
    });

    it('leaves the tooltip of the series without counts unchanged', function () {
        expect(
            buildBarChartConfig(['2022'], datasets()).options.plugins.tooltip
                .callbacks
        ).toBeUndefined();
    });
});
