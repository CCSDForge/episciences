$(document).ready(function () {
    $('.dataTable').on('click', 'a.delete', function () {
        var url = $(this).url();
        var parent = $(this).closest('.reviewer');
        var action = url.attr('path');
        var params = url.param();

        bootbox.setDefaults({ locale: locale });
        bootbox.confirm(translate('Êtes-vous sûr ?'), function (result) {
            if (result) {
                $.post(action, { ajax: 1, params: params }, function (respond) {
                    if (respond == 1) {
                        $(parent).remove();
                    } else {
                        bootbox.alert(respond);
                    }
                });
            }
        });
        return false;
    });
});
