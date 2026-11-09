/**
 * Statistics page: on / off switch of the start date of the statistics
 */

const path = require('path');

const { initStatsHistorySwitch, showCurrentStatsYear } = require(
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

describe('showCurrentStatsYear', function () {
    // jsdom has no layout: the sizes of the row of the years are given
    function setUp(scrollWidth, clientWidth) {
        document.body.innerHTML = `
            <div class="stats-toolbar">
                <nav class="stats-years">
                    <a class="stats-year">Tout</a>
                    <a class="stats-year active">2024</a>
                </nav>
            </div>
        `;
        const years = document.querySelector('.stats-years');
        const current = document.querySelector('.stats-year.active');
        const define = (element, name, value) =>
            Object.defineProperty(element, name, { configurable: true, value });

        define(years, 'scrollWidth', scrollWidth);
        define(years, 'clientWidth', clientWidth);
        define(years, 'offsetLeft', 10);
        define(current, 'offsetLeft', 410);
        define(current, 'offsetWidth', 60);

        return years;
    }

    it('centres the current year in the row scrolled sideways', function () {
        const years = setUp(900, 300);
        showCurrentStatsYear();

        // 410 - 10 - (300 - 60) / 2
        expect(years.scrollLeft).toBe(280);
    });

    it('leaves the row as it is when all the years are shown', function () {
        const years = setUp(300, 300);
        showCurrentStatsYear();

        expect(years.scrollLeft).toBe(0);
    });
});
