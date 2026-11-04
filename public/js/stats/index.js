document.addEventListener('DOMContentLoaded', function () {
    initStatsHistorySwitch();

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

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { initStatsHistorySwitch };
}
