/**
 * Statistics page: selector "moyenne"/"médiane" of the delay table
 */

const path = require('path');

const {
    STATS_DELAY_MODE_EVENT,
    initStatsDelayMode,
    currentStatsDelayMode,
    createDelayChart,
} = require(path.join(__dirname, '../../../public/js/stats/submissions.js'));

describe('initStatsDelayMode', function () {
    beforeEach(function () {
        document.body.innerHTML = `
            <div>
                <h3>Délai</h3>
                <div class="stat-delay-mode" role="group" aria-label="Valeur du délai à afficher">
                    <button type="button" class="stat-delay-mode-btn" data-delay-mode="mean">Moyenne</button>
                    <button type="button" class="stat-delay-mode-btn" data-delay-mode="median">Médiane</button>
                </div>
                <table class="delay table stat-delays">
                    <thead>
                        <tr>
                            <th scope="col"></th>
                            <th scope="col" data-delay="mean">Moyenne (jours)</th>
                            <th scope="col" data-delay="median">Médiane (jours)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th scope="row">Soumission-publication</th>
                            <td class="stat-value" data-delay="mean">12</td>
                            <td class="stat-value" data-delay="median">10</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        `;
    });

    // the chosen button is pressed and only its column is shown
    const expectMode = function (mode) {
        document
            .querySelectorAll('.stat-delay-mode-btn')
            .forEach(function (btn) {
                const chosen = btn.getAttribute('data-delay-mode') === mode;
                expect(btn.classList.contains('active')).toBe(chosen);
                expect(btn.getAttribute('aria-pressed')).toBe(
                    chosen ? 'true' : 'false'
                );
            });

        document.querySelectorAll('[data-delay]').forEach(function (cell) {
            expect(cell.hidden).toBe(cell.getAttribute('data-delay') !== mode);
        });
    };

    it('shows the median column and hides the mean by default', function () {
        initStatsDelayMode();

        expectMode('median');
    });

    it('keeps the mode of the button active in the page', function () {
        document
            .querySelector('[data-delay-mode="mean"]')
            .classList.add('active');

        initStatsDelayMode();

        expectMode('mean');
    });

    it('shows the mean column when its button is clicked', function () {
        initStatsDelayMode();

        document
            .querySelector('[data-delay-mode="mean"]')
            .dispatchEvent(new Event('click'));

        expectMode('mean');
    });

    it('goes back to the median column when its button is clicked again', function () {
        initStatsDelayMode();

        document
            .querySelector('[data-delay-mode="mean"]')
            .dispatchEvent(new Event('click'));
        document
            .querySelector('[data-delay-mode="median"]')
            .dispatchEvent(new Event('click'));

        expectMode('median');
    });

    it('does nothing without a selector', function () {
        document.body.innerHTML = '<p>No selector</p>';

        expect(() => initStatsDelayMode()).not.toThrow();
    });

    it('sends the chosen mode to the document', function () {
        const modes = [];
        const listener = event => modes.push(event.detail.mode);
        document.addEventListener(STATS_DELAY_MODE_EVENT, listener);

        initStatsDelayMode();
        document
            .querySelector('[data-delay-mode="mean"]')
            .dispatchEvent(new Event('click'));

        document.removeEventListener(STATS_DELAY_MODE_EVENT, listener);

        expect(modes).toEqual(['median', 'mean']);
        expect(currentStatsDelayMode()).toBe('mean');
    });
});

describe('currentStatsDelayMode', function () {
    it('is the median without a selector', function () {
        document.body.innerHTML = '<p>No selector</p>';

        expect(currentStatsDelayMode()).toBe('median');
    });
});

describe('createDelayChart', function () {
    const delays = {
        years: ['2023', '2024'],
        datasets: [{ label: 'Acceptance', data: [57, 162] }],
        medianDatasets: [{ label: 'Acceptance', data: [41, 150] }],
        titles: { mean: 'Mean delay', median: 'Median delay' },
    };
    let destroy;

    beforeEach(function () {
        document.body.innerHTML = `
            <div class="panel">
                <div class="panel-heading">
                    <h2 class="panel-title"><span class="stat-chart-title">Mean delay</span><div class="collapseButton"></div></h2>
                </div>
                <canvas id="submissions-delay-chart" aria-label="Mean delay"></canvas>
            </div>
        `;
        destroy = jest.fn();
        global.createBarChart = jest.fn(() => ({ destroy: destroy }));
        global.statTranslate = jest.fn(text => text);
    });

    afterEach(function () {
        delete global.createBarChart;
        delete global.statTranslate;
    });

    it('plots the mean, then the median with its title', function () {
        const show = createDelayChart('submissions-delay-chart', delays);

        show('mean');
        expect(createBarChart).toHaveBeenLastCalledWith(
            'submissions-delay-chart',
            delays.years,
            delays.datasets,
            expect.any(Object)
        );

        show('median');
        expect(destroy).toHaveBeenCalledTimes(1);
        expect(createBarChart).toHaveBeenLastCalledWith(
            'submissions-delay-chart',
            delays.years,
            delays.medianDatasets,
            expect.any(Object)
        );
        expect(document.querySelector('.stat-chart-title').textContent).toBe(
            'Median delay'
        );
        expect(
            document
                .getElementById('submissions-delay-chart')
                .getAttribute('aria-label')
        ).toBe('Median delay');
        // the collapse button of the panel is kept
        expect(document.querySelector('.collapseButton')).not.toBeNull();
    });

    it('keeps the mean without the median by year', function () {
        const show = createDelayChart(
            'submissions-delay-chart',
            Object.assign({}, delays, { medianDatasets: null })
        );

        show('median');

        expect(createBarChart).toHaveBeenLastCalledWith(
            'submissions-delay-chart',
            delays.years,
            delays.datasets,
            expect.any(Object)
        );
        expect(document.querySelector('.stat-chart-title').textContent).toBe(
            'Mean delay'
        );
    });
});
