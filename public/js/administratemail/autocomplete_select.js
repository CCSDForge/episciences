var recipientsContainer = '#recipientsContainer';

if ($('#recipientsForm-recipientsList').val().length > 0) {
    recipientsList = JSON.parse($('#recipientsForm-recipientsList').val());
}

if (!recipientsList || !('user_' + ui.item.uid in recipientsList)) {
    var result = '';
    result += "<span class='label label-default'>";
    result += htmlEntities(ui.item.label);
    result +=
        "<input type='hidden' name='to_" +
        ui.item.uid +
        "' value='" +
        JSON.stringify(ui.item.uid) +
        "'>";
    result +=
        "<span onclick='removeRecipient(this)' class='glyphicon glyphicon-remove icon-action'></span>";
    result += '</span> ';

    $(recipientsContainer).append(result);

    recipientsList['user_' + ui.item.uid] = ui.item;
    $('#recipientsForm-recipientsList').val(JSON.stringify(recipientsList));
} else {
    alert(translate('Ce destinataire figure déjà dans la liste.'));
}

$('#recipientsForm-ac_newRecipient').val('');
