/**
 * Statistics page: selector "moyenne"/"médiane" of the delay table
 */

const path = require('path');

const { initStatsDelayMode } = require(
    path.join(__dirname, '../../../public/js/stats/submissions.js')
);

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
});
