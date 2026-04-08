$(document).ready(function () {
    $('#templates').on('click', 'a.delete-template', function (e) {
        e.preventDefault();
        var url = $(this).attr('href');

        bootbox.setDefaults({ locale: locale });
        bootbox.confirm(translate('Êtes-vous sûr ?'), function (result) {
            if (result) {
                window.location = url;
            }
        });
    });
});
