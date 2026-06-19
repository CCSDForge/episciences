$(document).ready(function () {
    $('.dataTable').on('click', 'a[id^="delete_"]', function () {
        var params = $(this).attr('id').substr(7).split('_');
        var table = params[0];
        var id = params[1];
        var csrfName = $(this).data('csrf-name');
        var csrfValue = $(this).data('csrf-value');

        bootbox.setDefaults({ locale: locale });
        bootbox.confirm(translate('Êtes-vous sûr ?'), function (result) {
            if (result) {
                var postData = { ajax: 1, userId: id, table: table };
                if (csrfName) {
                    postData[csrfName] = csrfValue;
                }
                $.post('/user/delete/', postData, function (respond) {
                    if (respond == 1) {
                        $('#' + table)
                            .dataTable()
                            .fnDeleteRow(
                                document.getElementById(table + '_' + id)
                            );
                    } else {
                        bootbox.alert(
                            translate('La suppression a échoué : ') + respond
                        );
                    }
                });
            }
        });
    });
});
