function processForm(object) {
    object.hide();
    $('#dd-new-version-form').show();
}

function cancel() {
    $('#dd-new-version-form').hide();
    $('#btn-add').show();
}
