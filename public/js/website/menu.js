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
    // Find the multicheckbox container by explicit ID (avoids conflict with auto-generated form-group ID)
    var multicheckbox = $('#pages_' + id + '-acl-options');
    var label = multicheckbox.siblings('label');

    // Show checkboxes and label if custom visibility is selected
    if (element.value == 2) {
        // Remove hidden attribute AND clear inline display style
        multicheckbox.removeAttr('hidden').css('display', '');
        label.css('display', '');
    } else {
        multicheckbox.attr('hidden', 'hidden').css('display', 'none');
        label.css('display', 'none');
    }
}
