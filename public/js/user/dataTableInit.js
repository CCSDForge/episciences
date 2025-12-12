$(document).ready(function () {
    $('.dataTable').dataTable({
        fnPreDrawCallback: function () {
            $(this)
                .closest('.dataTables_wrapper')
                .find("input[type='search']")
                .prop('spellcheck', false);
        },

        stateSave: true,

        dom: "<'dt-header row'<'left col-xs-6'l><'right col-xs-6'f>r>t<'dt-footer row'<'left col-xs-6'i><'right col-xs-6'p>>",
        pagingType: 'numbers',
        order: [[1, 'asc']],
        autoWidth: false,

        ColumnDefs: [
            {
                orderable: false,
                searchable: false,
                width: '20px',
                className: 'center',
                targets: [0],
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

    // Bouton de suppression d'un utilisateur
    $('.dataTable').on('click', 'a[id^="delete_"]', function () {
        var params = $(this).attr('id').substr(7).split('_');
        var table = params[0];
        var id = params[1];

        bootbox.setDefaults({ locale: locale });
        bootbox.confirm(translate('Êtes-vous sûr ?'), function (result) {
            if (result) {
                $.post(
                    JS_PREFIX_URL + 'user/delete/',
                    { ajax: 1, userId: id, table: table },
                    function (respond) {
                        if (respond == 1) {
                            $('#' + table)
                                .dataTable()
                                .fnDeleteRow(
                                    document.getElementById(table + '_' + id)
                                );
                        } else {
                            bootbox.alert(translate('La suppression a échoué'));
                        }
                    }
                );
            }
        });
    });

    addToggleButton('.dataTable', '.dt-header .right');
});
