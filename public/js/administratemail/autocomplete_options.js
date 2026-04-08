$(document).ready(function () {
    var input = '#recipientsForm-ac_newRecipient';
    $(input).autocomplete('option', 'delay', 50);
    $(input).autocomplete('option', 'minLength', 3);
});
