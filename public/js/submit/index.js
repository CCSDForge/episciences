$(document).ready(function () {
    let $searchDocRepoId = $('#search_doc-repoId');
    let $searchDocDocId = $('#search_doc-docId');
    let $versionBloc = $('#search_doc-version-element');


    setPlaceholder();

    $(window).on('load', function () {
        if ($versionBloc.length > 0)
            toggleVersionBloc();
    });

    $searchDocRepoId.on('change', function () {
        toggleVersionBloc();
    });

    function toggleVersionBloc() {
        let repoValue = $searchDocRepoId.val();

        $searchDocDocId.val('');

        setPlaceholder();

        let hasHookRequest = ajaxRequest('/submit/ajaxhashook', {repoId: repoValue});
        hasHookRequest.done(function (response) {
            if (JSON.parse(response)) {
                hasHook = response;
                $versionBloc.hide();

                if($searchDocRepoId.val() === '4'){
                    let $zButton = $("<blockquote id='z-submit-element' style='margin-top: 5px;'><small>" + translate("Si votre article n'a pas encore été publié dans l'archive sélectionnée :") + "<br></small><a class='btn btn-default btn-sm' href= '" + zSubmitUrl  + "' target='_blank' role='button' style='margin-top: 5px;'>" + translate('Déposer dans Zenodo') + "</a></blockquote>");
                    $searchDocRepoId.after($zButton);
                }

            } else {
                $versionBloc.show();
                $('#z-submit-element').remove();
            }
        });

    }

    function setPlaceholder() {
        $searchDocDocId.attr('placeholder', translate('exemple : ') + examples[$searchDocRepoId.val()]);
        $searchDocDocId.attr('size', $searchDocDocId.attr('placeholder').length);
    }

// Extracting the ID from URL

    $searchDocDocId.change(function () {

        let input = $(this).val();
        if (isValidHttpUrl(input)) {

            let url = new URL(input);
            let identifier = url.pathname;

            identifier = identifier.replace(/\/\w+\//, '')
            identifier = identifier.replace(/^\//, '');
            identifier = identifier.replace(/v\d+/, '')

            // Delete VERSION from IDENTIFIER
            $(this).val(identifier);

        }

    });

});