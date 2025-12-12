$(function () {
    // Affiche ou masque la gestion détaillée des droits d'accès à la page
    // (dépend de la valeur du select "visibility")
    $('.multicheckbox').each(function () {
        //console.log($(this).parent('div').prev('div').find("select[id$='visibility']").val());
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
    var multicheckbox = $(element)
        .parent()
        .parent()
        .next('div')
        .find('.multicheckbox');

    // Si les droits d'accès à la page sont personnalisés
    if (element.value == 2) {
        $(multicheckbox).fadeIn();
    } else {
        $(multicheckbox).hide();
    }
}
