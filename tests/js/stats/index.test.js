/**
 * Statistics page: on / off switch of the start date of the statistics
 */

const path = require('path');

const { initStatsHistorySwitch } = require(
    path.join(__dirname, '../../../public/js/stats/index.js')
);

describe('initStatsHistorySwitch', function () {
    beforeEach(function () {
        document.body.innerHTML = `
            <form class="stats-history-switch" method="get" action="/stats/index">
                <input type="hidden" name="year" value="2025">
                <label class="stats-switch">
                    <input type="checkbox" class="stats-switch-input" role="switch" name="fullHistory" value="1">
                </label>
            </form>
        `;
    });

    it('applies the switch as soon as it is switched', function () {
        const form = document.querySelector('form');
        form.submit = jest.fn();
        initStatsHistorySwitch();

        const input = document.querySelector('input[role="switch"]');
        input.checked = true;
        input.dispatchEvent(new Event('change'));

        expect(form.submit).toHaveBeenCalledTimes(1);
        expect(form.classList.contains('is-loading')).toBe(true);
        expect(form.getAttribute('aria-busy')).toBe('true');
        // still sent with the form
        expect(input.disabled).toBe(false);
        expect(new FormData(form).get('fullHistory')).toBe('1');
        expect(new FormData(form).get('year')).toBe('2025');
    });

    it('sends no fullHistory parameter when switched off', function () {
        const form = document.querySelector('form');
        form.submit = jest.fn();
        const input = document.querySelector('input[role="switch"]');
        input.checked = true;
        initStatsHistorySwitch();

        input.checked = false;
        input.dispatchEvent(new Event('change'));

        expect(form.submit).toHaveBeenCalledTimes(1);
        expect(new FormData(form).get('fullHistory')).toBeNull();
    });

    it('does nothing without switch', function () {
        document.body.innerHTML = '<p>No switch</p>';

        expect(() => initStatsHistorySwitch()).not.toThrow();
    });
});
