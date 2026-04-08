function onSelect(event, ui) {
    var label = ui.item.LASTNAME;
    if (ui.item.FIRSTNAME) label = ui.item.FIRSTNAME + ' ' + ui.item.LASTNAME;

    if (event.type == 'autocompleteselect') {
        $('#selectedUserId').val(ui.item.UID);
        getRoles(ui.item.UID);
    }

    $('#autocompletedUserSelection').val(label);
}

function getRoles(uid) {
    var request = $.ajax({
        type: 'POST',
        url: JS_PREFIX_URL + 'user/rolesform',
        data: { uid: uid },
    });

    request.done(function (result) {
        $('#autocompletedUserSelection')
            .popover({
                html: true,
                content: result,
                title: $('#autocompletedUserSelection').val(),
            })
            .popover('show');
    });
}

function closeResult() {
    $('#selectedUserId').val('0');
    $('#autocompletedUserSelection').val('');
    $('#autocompletedUserSelection').popover('destroy');
}
