var openedPopover = null;

function getEditors(button, sid) {
    // Destruction des anciens popups
    $('button').popover('destroy');

    // Toggle : est-ce qu'on ouvre ou est-ce qu'on ferme le popup ?
    if (openedPopover && openedPopover == sid) {
        openedPopover = null;
        return false;
    } else {
        openedPopover = sid;
    }

    // Récupération du formulaire
    let editorsFormRequest = ajaxRequest(
        JS_PREFIX_URL + 'section/editorsform',
        { sid: sid }
    );

    $(button)
        .popover({
            container: 'body',
            placement: 'bottom',
            html: true,
            content: getLoader(),
        })
        .popover('show');

    editorsFormRequest.done(function (result) {
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

        // Handlers du filtre des rédacteurs
        $('#filter').on('keyup', function () {
            filterList('#filter', '.editors-list label');
        });
        $('#filter').on('paste', function () {
            setTimeout(function () {
                filterList('#filter', '.editors-list label');
            }, 4);
        });
        let saveEditorsAction = JS_PREFIX_URL + 'section/saveeditors';

        $('form[action="' + saveEditorsAction + '"]').on('submit', function () {
            if (!$(this).data('submitted')) {
                // to fix duplicate ajax request
                $(this).data('submitted', true);

                // Traitement AJAX du formulaire
                let data = $(this).serialize() + '&sid=' + sid;
                let saveEditorsRequest = ajaxRequest(saveEditorsAction, data);

                saveEditorsRequest.done(function (response) {
                    if (response == 1) {
                        // Destruction du popup des rédacteurs
                        $(button).popover('destroy');

                        // Refresh de l'affichage des rédacteurs pour cette rubrique
                        let refreshEditorsRequest = ajaxRequest(
                            JS_PREFIX_URL + 'section/displayeditors',
                            { sid: sid }
                        );
                        refreshEditorsRequest.done(function (editors) {
                            let td = $('#section_' + sid + ' td:nth-child(4)');
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
