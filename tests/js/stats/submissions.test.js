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

    it('shows the mean column and hides the median by default', function () {
        initStatsDelayMode();

        const meanBtn = document.querySelector('[data-delay-mode="mean"]');
        const medianBtn = document.querySelector('[data-delay-mode="median"]');

        expect(meanBtn.classList.contains('active')).toBe(true);
        expect(meanBtn.getAttribute('aria-pressed')).toBe('true');
        expect(medianBtn.classList.contains('active')).toBe(false);
        expect(medianBtn.getAttribute('aria-pressed')).toBe('false');

        const meanCells = document.querySelectorAll('[data-delay="mean"]');
        const medianCells = document.querySelectorAll('[data-delay="median"]');
        meanCells.forEach(function (cell) {
            expect(cell.hidden).toBe(false);
        });
        medianCells.forEach(function (cell) {
            expect(cell.hidden).toBe(true);
        });
    });

    it('shows the median column when its button is clicked', function () {
        initStatsDelayMode();

        const medianBtn = document.querySelector('[data-delay-mode="median"]');
        medianBtn.dispatchEvent(new Event('click'));

        expect(medianBtn.classList.contains('active')).toBe(true);
        expect(medianBtn.getAttribute('aria-pressed')).toBe('true');
        expect(
            document
                .querySelector('[data-delay-mode="mean"]')
                .getAttribute('aria-pressed')
        ).toBe('false');

        const meanCells = document.querySelectorAll('[data-delay="mean"]');
        const medianCells = document.querySelectorAll('[data-delay="median"]');
        meanCells.forEach(function (cell) {
            expect(cell.hidden).toBe(true);
        });
        medianCells.forEach(function (cell) {
            expect(cell.hidden).toBe(false);
        });
    });

    it('goes back to the mean column when its button is clicked again', function () {
        initStatsDelayMode();

        document
            .querySelector('[data-delay-mode="median"]')
            .dispatchEvent(new Event('click'));
        document
            .querySelector('[data-delay-mode="mean"]')
            .dispatchEvent(new Event('click'));

        const meanCells = document.querySelectorAll('[data-delay="mean"]');
        const medianCells = document.querySelectorAll('[data-delay="median"]');
        meanCells.forEach(function (cell) {
            expect(cell.hidden).toBe(false);
        });
        medianCells.forEach(function (cell) {
            expect(cell.hidden).toBe(true);
        });
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
            .querySelector('[data-delay-mode="median"]')
            .dispatchEvent(new Event('click'));

        document.removeEventListener(STATS_DELAY_MODE_EVENT, listener);

        expect(modes).toEqual(['mean', 'median']);
        expect(currentStatsDelayMode()).toBe('median');
    });
});

describe('currentStatsDelayMode', function () {
    it('is the mean without a selector', function () {
        document.body.innerHTML = '<p>No selector</p>';

        expect(currentStatsDelayMode()).toBe('mean');
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
