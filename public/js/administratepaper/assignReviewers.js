var openedPopover = null;

function assignReviewers(button, docid) {
    // Configuration du popup
    var placement = 'bottom';

    // Destruction des anciens popups
    $('button').popover('destroy');

    // Toggle : est-ce qu'on ouvre ou est-ce qu'on ferme le popup ?
    if (openedPopover && openedPopover == docid) {
        openedPopover = null;
        return false;
    } else {
        openedPopover = docid;
    }

    // Récupération du formulaire
    var request = $.ajax({
        type: 'POST',
        url: JS_PREFIX_URL + 'administratepaper/reviewersform',
        data: { docid: docid },
    });

    $(button)
        .popover({
            placement: placement,
            container: 'body',
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
                placement: placement,
                container: 'body',
                html: true,
                content: result,
            })
            .popover('show');

        // Handlers du filtre des relecteurs
        $('#filter').on('keyup', function () {
            filterList('#filter', '.editors-list label');
        });
        $('#filter').on('paste', function () {
            setTimeout(function () {
                filterList('#filter', '.reviewers-list label');
            }, 4);
        });

        let actionForm = JS_PREFIX_URL + 'administratepaper/assignreviewers';

        $('form[action^="' + actionForm + '"]').on('submit', function () {
            // Traitement AJAX du formulaire
            $.ajax({
                url: actionForm,
                type: 'POST',
                datatype: 'json',
                data: $(this).serialize() + '&docid=' + docid,
                success: function (result) {
                    if (result == 1) {
                        // Destruction du popup
                        $(button).popover('destroy');

                        var container = $(button).closest('#reviewers');
                        $(container).hide();
                        $(container).html(getLoader());
                        $(container).fadeIn();

                        // Refresh de l'affichage
                        $.ajax({
                            url:
                                JS_PREFIX_URL +
                                'administratepaper/reviewerslist',
                            type: 'POST',
                            data: { docid: docid },
                            success: function (result) {
                                $(container).hide();
                                $(container).html(result);
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
