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
        sDom: "<'dt-header row'<'left col-xs-6'l><'right col-xs-6'f>r>t<'dt-footer row'<'left col-xs-6'i><'right col-xs-6'p>>",
        aoColumnDefs: [
            {
                bSearchable: false,
                //"sWidth": "20px",
                //"sClass": "center",
                aTargets: [1],
            },
        ],
        oLanguage: {
            sLengthMenu:
                translate('Afficher') + ' _MENU_ ' + translate('lignes'),
            sSearch: translate('Rechercher') + ' :',
            sZeroRecords: translate('Aucun résultat'),
            sInfo:
                translate('Lignes') +
                ' _START_ ' +
                translate('à') +
                ' _END_, ' +
                translate('sur') +
                ' _TOTAL_ ',
            sInfoEmpty: translate('Aucun résultat affiché'),
            sInfoFiltered: '(' + translate('filtrés sur les') + ' _MAX_)',
            oPaginate: { sPrevious: '', sNext: '' },
        },
    });

    $('.sortable').sortable({
        handle: '.sortable-handle',
        placeholder: 'sortable-placeholder',
        update: function (event, ui) {
            $.ajax({
                url: JS_PREFIX_URL + 'section/sort',
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
