$(document).ready(function () {
    createModalStructure({ hideSubmit: true });

    $('.dataTable').each(function () {
        let nbCols = $(this).find('th').length;
        let targets = [];
        for (let i = 1; i < nbCols; i++) {
            targets.push(i);
        }

        $(this).dataTable({
            stateSave: true,

            fnDrawCallback: function (oSettings) {
                let displayed = $(this)
                    .dataTable()
                    .fnSettings()
                    .fnRecordsDisplay();
                let total = $(this).dataTable().fnSettings().fnRecordsTotal();
                if (displayed !== total) {
                    $(this).find('tbody').sortable('disable');
                    if (!$(this).find('span.handle').hasClass('disabled')) {
                        $(this).find('span.handle').addClass('disabled');
                    }
                } else if (this.find('span.handle').hasClass('disabled')) {
                    $(this).find('tbody').sortable('enable');
                    $(this).find('span.handle').removeClass('disabled');
                }
            },
            sDom: "<\'row-fluid\'<\'span6\'l><\'span6\'f>r>t<\'row-fluid\'<\'span6\'i><\'span6\'p>>",
            sPaginationType: 'simple_numbers',
            bAutoWidth: true,
            bSort: false,
            lengthMenu: [25, 50, 75, 100],

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
    });
});
