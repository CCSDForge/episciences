$(function () {
    // Show or hide the detailed access rights management for the page
    // (depends on the value of the "visibility" select)
    $('.multicheckbox').each(function () {
        if (
            $(this)
                .parent('div')
                .prev('div')
                .find("select[id$='visibility']")
                .val() < 2
        ) {
            $(this).css('display', 'none');
        } else {
            $(this).css('display', '');
        }
    });
});

function setVisibility(id, element) {
    // Find the multicheckbox container using ID selector
    var multicheckbox = $('#pages_' + id + '-acl-element');
    // Find the label for "Visible par:"
    var label = multicheckbox.prev('label');

    // Show checkboxes and label if custom visibility is selected
    if (element.value == 2) {
        multicheckbox.removeAttr('hidden').fadeIn();
        label.fadeIn();
    } else {
        multicheckbox.attr('hidden', 'hidden').hide();
        label.hide();
    }
}
