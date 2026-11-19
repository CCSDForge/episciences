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
        data.csrf_token =
            document.querySelector('meta[name="csrf-token"]')?.content || '';
        bootbox.setDefaults({ locale: locale });
        bootbox.confirm(translate('Êtes-vous sûr ?'), function (result) {
            if (result) {
                $.post(action, data, function (respond) {
                    if (respond == 1) {
                        if (action === '/grid/deletecriterion') {
                            // back on the grid of the criterion, opened even when the volume grids are folded:
                            // the page is loaded again, also when only its anchor changes
                            window.history.replaceState(
                                null,
                                '',
                                '/grid/list' +
                                    (params.rgid
                                        ? '#rating-grid-' +
                                          encodeURIComponent(params.rgid)
                                        : '')
                            );
                            window.location.reload();
                            return false;
                        } else if (isDeleteGridAction) {
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
