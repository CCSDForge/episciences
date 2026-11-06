document.addEventListener('DOMContentLoaded', function () {
    initStatsHistorySwitch();
    showCurrentStatsYear();

    const loadingStats = document.getElementById('loading-stats');

    if (!loadingStats) {
        return;
    }

    loadingStats.innerHTML = getLoader();
    loadingStats.style.display = '';

    window.addEventListener('load', function () {
        loadingStats.style.display = 'none';
    });
});

/**
 * On / off switch of the start date of the statistics (administrators): applied as soon as it is switched
 */
function initStatsHistorySwitch() {
    document.querySelectorAll('.stats-history-switch').forEach(function (form) {
        const input = form.querySelector('input[role="switch"]');

        if (!input) {
            return;
        }

        input.addEventListener('change', function () {
            // the input is not disabled: a disabled input would not be sent
            form.classList.add('is-loading');
            form.setAttribute('aria-busy', 'true');
            form.submit();
        });
    });
}

/**
 * The years scrolled sideways (narrow screens): the current year is brought into view
 */
function showCurrentStatsYear() {
    const years = document.querySelector('.stats-toolbar .stats-years');
    const current = years ? years.querySelector('.stats-year.active') : null;

    if (!current || years.scrollWidth <= years.clientWidth) {
        return;
    }

    // centred in the row, without scrolling the page
    years.scrollLeft =
        current.offsetLeft -
        years.offsetLeft -
        (years.clientWidth - current.offsetWidth) / 2;
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { initStatsHistorySwitch, showCurrentStatsYear };
}
