$(document).ready(function () {
    let $search;
    let $sortWithSearchFilterAlert = $('#sort-with-search-filter-alert');

    $('.dataTable').dataTable({
        fnPreDrawCallback: function () {
            $search = $(this)
                .closest('.dataTables_wrapper')
                .find("input[type='search']");
            $search.prop('spellcheck', false);
            if ($search.val() !== '') {
                $sortWithSearchFilterAlert.show();
            } else {
                $sortWithSearchFilterAlert.hide();
            }
        },
        lengthMenu: [[-1], [translate('all')]],
        stateSave: true,
        ordering: false,
        pagingType: 'numbers',
        columnDefs: [
            {
                searchable: false,
                targets: [1],
            },
        ],

        language: {
            lengthMenu:
                translate('Afficher') + ' _MENU_ ' + translate('lignes'),
            search: translate('Rechercher') + ' :',
            zeroRecords: translate('Aucun résultat'),
            info:
                translate('Lignes') +
                ' _START_ ' +
                translate('à') +
                ' _END_, ' +
                translate('sur') +
                ' _TOTAL_ ',
            infoEmpty: translate('Aucun résultat affiché'),
            infoFiltered: '(' + translate('filtrés sur les') + ' _MAX_)',
            paginate: { sPrevious: '', sNext: '' },
        },
    });

    $('.sortable').sortable({
        handle: '.sortable-handle',
        placeholder: 'sortable-placeholder',
        update: function (event, ui) {
            $.ajax({
                url: JS_PREFIX_URL + 'volume/sort',
                type: 'POST',
                data: { sorted: $(this).sortable('toArray') },
                dataType: 'json',
            }).done(function (result) {
                if (result > 1) {
                    location.reload();
                }
            });
        },
    });

    $search.on('change', function (e) {
        $sortWithSearchFilterAlert.show();
    });
});
