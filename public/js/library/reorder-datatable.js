/**
 * Reorderable DataTable — shared module
 *
 * Augments an existing jQuery DataTable with position-input reordering.
 * Uses jQuery throughout (consistent with the rest of the codebase) and
 * fetch() for AJAX (no jQuery wrapper needed for a simple POST).
 *
 * Callers pass:
 *   $table  – jQuery object wrapping the <table> element
 *   dt      – DataTables API instance ($table.DataTable())
 *   sortUrl – POST endpoint, e.g. '/volume/sort'
 *
 * @param {jQuery}           config.$table
 * @param {DataTables.Api}   config.dt
 * @param {string}           config.sortUrl
 */
function initReorderableDataTable({ $table, dt, sortUrl }) {
    // ── Constants ─────────────────────────────────────────────────────────────

    // Must match the CSS animation duration on .reorder-row--landing > td
    const LANDING_MS = 500;

    // ── State ─────────────────────────────────────────────────────────────────

    let reorderMode = false;
    let savedPageLen = null;
    let savedSearch = null;

    // One active landing timer per row id — prevents stacking when the same
    // row is moved again before the previous pulse finishes.
    const landingTimers = new Map();

    // ── Cached jQuery references ──────────────────────────────────────────────

    const $saveBar = $('#reorder-bar');
    const $saveBtn = $('#reorder-save');
    const $cancelBtn = $('#reorder-cancel');
    const $changesEl = $('#reorder-changes');
    const $alertEl = $('#sort-with-search-filter-alert');
    const $wrapper = $table.closest('.dataTables_wrapper');

    // ── Unsaved-changes indicator ─────────────────────────────────────────────

    function updateChangesIndicator() {
        const count = $table.find('tbody tr.reorder-row--modified').length;

        $changesEl.empty();
        if (count === 0) return;

        const label =
            count === 1
                ? count + ' ' + translate('modification non sauvegardée')
                : count + ' ' + translate('modifications non sauvegardées');

        $changesEl.append(
            $('<span>', { class: 'reorder-changes-badge' }).append(
                $('<span>', {
                    class: 'glyphicon glyphicon-exclamation-sign',
                    'aria-hidden': 'true',
                }),
                document.createTextNode(' ' + label)
            )
        );
    }

    // ── Row modification marker ───────────────────────────────────────────────

    function markRowModified($tr) {
        const id = $tr.attr('id');

        if (landingTimers.has(id)) {
            clearTimeout(landingTimers.get(id));
            $tr.removeClass('reorder-row--landing');
        }

        // Toggle the landing class off/on so the browser restarts the animation
        // even when the class was already present (row moved a second time).
        // The offsetHeight read forces a synchronous reflow between the two ops.
        $tr.removeClass('reorder-row--landing');
        void $tr[0].offsetHeight; // eslint-disable-line no-void
        $tr.addClass('reorder-row--modified reorder-row--landing');

        const timer = setTimeout(() => {
            $tr.removeClass('reorder-row--landing');
            landingTimers.delete(id);
        }, LANDING_MS);

        landingTimers.set(id, timer);
    }

    function clearAllMarkers() {
        landingTimers.forEach(clearTimeout);
        landingTimers.clear();
        $table
            .find('.reorder-row--modified, .reorder-row--landing')
            .removeClass('reorder-row--modified reorder-row--landing');
    }

    // ── DataTable controls visibility ─────────────────────────────────────────

    function setControlsVisible(visible) {
        $wrapper
            .find(
                '.dataTables_filter, .dataTables_length, .dataTables_paginate, .dataTables_info'
            )
            .toggle(visible);
        $alertEl.hide();
    }

    // ── Reorder mode ──────────────────────────────────────────────────────────

    function enterReorderMode() {
        if (reorderMode) return;
        reorderMode = true;

        savedPageLen = dt.page.len();
        savedSearch = dt.search();

        dt.search('');
        dt.page.len(-1).draw();

        setControlsVisible(false);
        $saveBar.css('display', 'flex');
        $('body').addClass('has-reorder-bar');
    }

    function cancelReorder() {
        reorderMode = false; // before draw() so fnPreDrawCallback sees the correct state

        clearAllMarkers();

        dt.search(savedSearch);
        dt.page.len(savedPageLen).draw();

        // Strip again: DataTable may reuse DOM nodes, preserving class attributes
        clearAllMarkers();

        setControlsVisible(true);
        updateChangesIndicator();

        $saveBar.hide();
        $('body').removeClass('has-reorder-bar');
    }

    function saveReorder() {
        const $idle = $saveBtn.find('.reorder-save__idle');
        const $busy = $saveBtn.find('.reorder-save__busy');

        $saveBtn.prop('disabled', true);
        $idle.hide();
        $busy.show();

        const body = $table
            .find('tbody tr')
            .toArray()
            .map(tr => 'sorted[]=' + encodeURIComponent(tr.id))
            .join('&');

        fetch(sortUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                // ZF1's isXmlHttpRequest() check requires this header; without it,
                // actions not listed in acl.ini are redirected to the notfound page.
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        })
            .then(response => {
                if (!response.ok) throw new Error(response.status);
                location.reload();
            })
            .catch(() => {
                $saveBtn.prop('disabled', false);
                $idle.show();
                $busy.hide();
                alert(translate('Une erreur est survenue.'));
            });
    }

    // ── Row repositioning ─────────────────────────────────────────────────────

    function applyPositionChange($input) {
        const $tr = $input.closest('tr');
        const $tbody = $table.find('tbody');
        const newPos = parseInt($input.val(), 10);
        const total = $tbody.find('tr').length;

        if (isNaN(newPos) || newPos < 1 || newPos > total) {
            $input.addClass('reorder-position-input--error');
            $input[0].setCustomValidity(translate('Position invalide'));
            $input[0].reportValidity();
            return;
        }

        $input.removeClass('reorder-position-input--error');
        $input[0].setCustomValidity('');

        $tr.detach();
        const $rows = $tbody.find('tr');
        if (newPos - 1 < $rows.length) {
            $rows.eq(newPos - 1).before($tr);
        } else {
            $tbody.append($tr);
        }

        $tbody.find('tr').each((i, tr) => {
            $(tr)
                .find('.reorder-position-input')
                .val(i + 1);
        });

        markRowModified($tr);
        $tr[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        updateChangesIndicator();
    }

    // ── Events ────────────────────────────────────────────────────────────────

    $table.on('focus', '.reorder-position-input', function () {
        enterReorderMode();
        this.select();
    });

    $table.on('change', '.reorder-position-input', function () {
        applyPositionChange($(this));
    });

    $table.on('keydown', '.reorder-position-input', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            applyPositionChange($(this));
        }
    });

    $saveBtn.on('click', saveReorder);
    $cancelBtn.on('click', cancelReorder);

    $wrapper.on('input', "input[type='search']", function () {
        if (!reorderMode) {
            $alertEl.toggle($(this).val() !== '');
        }
    });

    // ── Drag and drop (SortableJS) ────────────────────────────────────────────

    const tbody = $table.find('tbody.sortable')[0];
    if (tbody && window.Sortable) {
        Sortable.create(tbody, {
            handle: '.sortable-handle',
            animation: 150,
            ghostClass: 'sortable-placeholder',
            onStart() {
                enterReorderMode();
            },
            onEnd(evt) {
                // Renumber all position inputs to reflect the new DOM order
                $(tbody)
                    .find('tr')
                    .each((i, tr) => {
                        $(tr)
                            .find('.reorder-position-input')
                            .val(i + 1);
                    });
                markRowModified($(evt.item));
                updateChangesIndicator();
            },
        });
    }
}
