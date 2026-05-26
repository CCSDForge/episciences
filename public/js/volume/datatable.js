$(document).ready(function () {
    const $table = $('#volumes');

    $table.dataTable({
        fnPreDrawCallback: function () {
            const $search = $(this)
                .closest('.dataTables_wrapper')
                .find("input[type='search']");
            $search.prop('spellcheck', false);
        },
        fnDrawCallback: function () {
            activateTooltips();
        },
        lengthMenu: [
            [10, 25, 50, 100, -1],
            [10, 25, 50, 100, translate('all')],
        ],
        stateSave: true,
        ordering: false,
        pagingType: 'numbers',
        columnDefs: [
            // Exclude the position input and the drag-handle columns from search
            { searchable: false, targets: [0, 1] },
        ],
        language: {
            lengthMenu: translate('Afficher') + ' _MENU_ ' + translate('lignes'),
            search: translate('Rechercher') + ' :',
            zeroRecords: translate('Aucun résultat'),
            info:
                translate('Lignes') + ' _START_ ' + translate('à') +
                ' _END_, ' + translate('sur') + ' _TOTAL_ ',
            infoEmpty: translate('Aucun résultat affiché'),
            infoFiltered: '(' + translate('filtrés sur les') + ' _MAX_)',
            paginate: { sPrevious: '', sNext: '' },
        },
    });

    initReorderableDataTable({
        $table,
        dt: $table.DataTable(),
        sortUrl: JS_PREFIX_URL + 'volume/sort',
    });
});
