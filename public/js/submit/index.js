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


            let oResponse = JSON.parse(response);

            if (oResponse) {

                // initialized in submit/index.phtml
                hasHook = oResponse.hasHook;
                isRequiredVersion = oResponse.isRequiredVersion.result;

                if(!isRequiredVersion){
                    $versionBloc.hide();

                } else {
                    $versionBloc.show();
                }

                if($searchDocRepoId.val() === zenodoRepoId){
                    if(zSubmitStatus){ // to be enabled : @ see /config/dist-pwd.json
                        insertZSubmitElement(zSubmitUrl);
                    }
                } else {

                    $('#z-submit-element').remove();

                }

            } else {
                $versionBloc.show();

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