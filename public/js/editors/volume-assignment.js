var openedPopover = null;

function getEditors(button, vid) {
    // Destruction des anciens popups
    $('button').popover('destroy');

    // Toggle : est-ce qu'on ouvre ou est-ce qu'on ferme le popup ?
    if (openedPopover && openedPopover == vid) {
        openedPopover = null;
        return false;
    } else {
        openedPopover = vid;
    }

    // Récupération du formulaire
    let editorFormRequest = ajaxRequest(JS_PREFIX_URL + 'volume/editorsform', {
        vid: vid,
    });

    $(button)
        .popover({
            container: 'body',
            placement: 'bottom',
            html: true,
            content: getLoader(),
        })
        .popover('show');

    editorFormRequest.done(function (result) {
        // Destruction du popup de chargement
        $(button).popover('destroy');

        activateTooltips();

        // Affichage du formulaire dans le popover
        $(button)
            .popover({
                container: 'body',
                placement: 'bottom',
                html: true,
                content: result,
            })
            .popover('show');

        // Handlers du filtre des rédacteurs
        $('#filter').on('keyup', function () {
            filterList('#filter', '.editors-list label');
        });
        $('#filter').on('paste', function () {
            setTimeout(function () {
                filterList('#filter', '.editors-list label');
            }, 4);
        });

        let saveEditorsAction = JS_PREFIX_URL + 'volume/saveeditors';

        $('form[action="' + saveEditorsAction + '"]').on('submit', function () {
            if (!$(this).data('submitted')) {
                // to fix duplicate ajax request
                $(this).data('submitted', true);
                // Traitement AJAX du formulaire
                let data = $(this).serialize() + '&vid=' + vid;
                let saveEditorsRequest = ajaxRequest(saveEditorsAction, data);
                saveEditorsRequest.done(function (response) {
                    if (response == 1) {
                        // Destruction du popup des rédacteurs
                        $(button).popover('destroy');

                        // Refresh de l'affichage des rédacteurs pour cette rubrique
                        let refreshEditorsRequest = ajaxRequest(
                            JS_PREFIX_URL + 'volume/displayeditors',
                            { vid: vid }
                        );
                        refreshEditorsRequest.done(function (editors) {
                            let td = $('#volume_' + vid + ' td:nth-child(4)');
                            let container = $(td).find('.editors');
                            $(container).hide();
                            $(container).html(editors);
                            $(container).fadeIn();
                            activateTooltips();
                        });
                    }
                });
            }
            return false;
        });
    });
}

function closeResult() {
    $('button').popover('destroy');
}
