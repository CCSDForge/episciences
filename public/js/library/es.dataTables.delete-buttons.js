$(document).ready(function () {
    $('.dataTable, #grid-actions').on('click', 'a.delete', function () {
        let url = $(this).url();
        let action = url.attr('path');
        let params = url.param();
        let isDeleteGridAction = action === '/grid/delete';
        let table = null;
        let tr = null;
        let data = {};

        if (!isDeleteGridAction) {
            data.ajax = 1;
            table = $(this).closest('table').attr('id');
            tr = $(this).closest('tr').attr('id');
        }

        data.params = params;
        bootbox.setDefaults({ locale: locale });
        bootbox.confirm(translate('Êtes-vous sûr ?'), function (result) {
            if (result) {
                $.post(action, data, function (respond) {
                    if (respond == 1) {
                        if (
                            isDeleteGridAction ||
                            action === '/grid/deletecriterion'
                        ) {
                            window.location.replace('/grid/list');
                            return false;
                        } else {
                            if (table && tr) {
                                console.log(table, tr);
                                $('#' + table)
                                    .dataTable()
                                    .fnDeleteRow(document.getElementById(tr));
                                location.reload();
                            }
                        }
                    } else {
                        bootbox.alert(
                            translate('La suppression a échoué : ') +
                                translate(respond)
                        );
                    }
                });
            }
        });
        return false;
    });
});
