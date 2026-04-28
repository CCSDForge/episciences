/**
 * Lors de l'invitation d'un relecteur, cette fonction est exécutée aprés l'ouverture de la popup de confirmation.
 * @param button
 */
function confirmInvitereviewer(button) {
    var id_form = 'invitereviewer_form_' + $(button).parents().eq(3).attr('id');
    document.getElementById(id_form).submit();
}
