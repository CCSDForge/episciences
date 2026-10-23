document.addEventListener('DOMContentLoaded', function () {
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
