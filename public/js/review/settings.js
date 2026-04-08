$(document).ready(function () {
    $('#canPickEditors').val() > 0
        ? $('#max_editors-element').show()
        : $('#max_editors-element').hide();

    $('#canPickEditors').change(function () {
        $(this).val() > 0
            ? $('#max_editors-element').show()
            : $('#max_editors-element').hide();
    });
});
