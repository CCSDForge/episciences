$(function () {
    setDisplayElements('body');
});

function setDisplayElements(elem) {
    $(elem)
        .find('.elem-link')
        .each(function () {
            displayElements(this);
            $(this).change(function () {
                displayElements(this);
            });
        });
}

function displayElements(parent) {
    var closest = $(parent).closest('.div-form');
    if (closest.length == 0) {
        closest = $(parent).closest('form');
    }
    closest
        .find('[elem-link="' + $(parent).attr('elem') + '"]')
        .each(function (index) {
            if (
                $(parent).is(':visible') &&
                $(this).attr('elem-value') == $(parent).val()
            ) {
                $(this).closest('.form-group').show();
            } else {
                $(this).closest('.form-group').hide();
            }

            if ($(this).hasClass('elem-link')) {
                displayElements($(this));
            }
        });
}
