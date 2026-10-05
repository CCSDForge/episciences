/**
 * Activity timeline category filters (/activity/view).
 * Vanilla JS: toggles visibility of .activity-timeline-row entries by data-category
 * and announces the resulting count via the aria-live region for screen reader users.
 */
document.addEventListener('DOMContentLoaded', function () {
    var page = document.querySelector('.activity-page');

    if (!page) {
        return;
    }

    var toggles = page.querySelectorAll('.activity-filter-switch');
    var items = page.querySelectorAll('.activity-timeline-row');
    var liveRegion = page.querySelector('.activity-filter-live-region');

    if (!toggles.length || !items.length) {
        return;
    }

    function activeCategories() {
        var active = [];
        toggles.forEach(function (toggle) {
            if (toggle.checked) {
                active.push(toggle.getAttribute('data-category'));
            }
        });
        return active;
    }

    function applyFilters() {
        var active = activeCategories();
        var visibleCount = 0;

        items.forEach(function (item) {
            var category = item.getAttribute('data-category');
            var isVisible = active.indexOf(category) !== -1;

            if (isVisible) {
                item.removeAttribute('hidden');
                visibleCount += 1;
            } else {
                item.setAttribute('hidden', '');
            }
        });

        if (liveRegion) {
            var label =
                typeof translate === 'function'
                    ? translate('activity_timeline_events_shown')
                    : 'event(s) shown';
            liveRegion.textContent = visibleCount + ' ' + label;
        }
    }

    toggles.forEach(function (toggle) {
        toggle.addEventListener('change', applyFilters);
    });

    applyFilters();
});
