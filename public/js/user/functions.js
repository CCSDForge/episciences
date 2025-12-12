var openedPopover = null;

$(function () {
    // Initialize user autocomplete using the utility function
    createUserAutocomplete({
        appendTo: $('#autocomplete').closest('.modal-body'),
    });
});

function subForm() {
    $('#selectedUserId').val('0');
    $('#fuser').submit();
}

function getRoles(button, uid) {
    // Destruction des anciens popups
    $('button').popover('destroy');

    // Toggle : est-ce qu'on ouvre ou est-ce qu'on ferme le popup ?
    if (openedPopover && openedPopover == uid) {
        openedPopover = null;
        return false;
    } else {
        openedPopover = uid;
    }

    // Récupération du formulaire
    var request = $.ajax({
        type: 'POST',
        url: JS_PREFIX_URL + 'user/rolesform',
        data: { uid: uid },
    });

    $(button)
        .popover({
            delay: 0,
            container: 'body',
            placement: 'bottom',
            html: true,
            content: getLoader(),
        })
        .popover('show');

    request.done(function (result) {
        // Destruction du popup de chargement
        $(button).popover('destroy');

        // Affichage du formulaire dans le popover
        $(button)
            .popover({
                container: 'body',
                placement: 'bottom',
                html: true,
                content: result,
            })
            .popover('show');
        let saveRolesUrl = JS_PREFIX_URL + 'user/saveroles';

        $('form[action="' + saveRolesUrl + '"]').on('submit', function () {
            $(this).parent().html(getLoader());

            // Traitement AJAX du formulaire
            $.ajax({
                url: saveRolesUrl,
                type: 'POST',
                datatype: 'json',
                // data: {uid:uid, data: $(this).serialize()},
                data: $(this).serialize() + '&uid=' + uid,
                success: function (result) {
                    if (result == 1) {
                        // Destruction du popup des roles
                        $(button).popover('destroy');
                        openedPopover = null;
                        var td = $('#localUsers_' + uid + ' td:nth-child(5)');
                        var container = $(td).find('.tags');
                        $(container).html(getLoader());

                        // Refresh de l'affichage des rôles pour cet utilisateur
                        $.ajax({
                            url: JS_PREFIX_URL + 'user/displaytags',
                            type: 'POST',
                            data: { uid: uid },
                            success: function (tags) {
                                $(container).hide();
                                $(container).html(tags);
                                $(container).fadeIn();
                            },
                        });
                    }
                },
            });

            return false;
        });
    });
}

function closeResult() {
    $('button').popover('destroy');
}
