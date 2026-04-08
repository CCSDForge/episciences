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
        // "dom": "<\'row-fluid\'<\'span6\'l><\'span6\'f>r>t<\'row-fluid\'<\'span6\'i><\'span6\'p>>",
        pagingType: 'numbers',
        order: [[3, 'desc']],
        autoWidth: true,

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

    addToggleButton('.dataTable', '.dt-header .right');
});
