function refuseInvitation() {
    $('#refuse-button').parent('.form-actions').hide();
    $('#refuse_form').fadeIn();
}

function cancel() {
    $('#accept_form').hide();
    $('#refuse_form').hide();
    $('#user_form').hide();
    $('#refuse-button').parent('.form-actions').fadeIn();
}

function show_user_form() {
    $('#refuse_form').hide();
    $('#accept_form').fadeIn();
    $('#refuse-button').parent('.form-actions').hide();
}

function display_errors() {
    show_user_form();
}
