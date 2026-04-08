$(document).ready(function () {
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
                            bootbox.alert(
                                translate('La suppression a échoué : ') +
                                    respond
                            );
                        }
                    }
                );
            }
        });
    });
});
