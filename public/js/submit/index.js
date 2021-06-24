$(document).ready(function () {
    let $searchDocRepoId = $('#search_doc-repoId');
    let $searchDocDocId = $('#search_doc-docId');
    let $versionBloc = $('#search_doc-version-element');


    setPlaceholder();

    $searchDocRepoId.change(function () {
        $searchDocDocId.val('');

        $versionBloc.show();

        setPlaceholder();

        let hasHookRequest = ajaxRequest('/submit/ajaxhashook', {repoId: $(this).val()});
        hasHookRequest.done(function(response){
            if(JSON.parse(response)){
                hasHook = response;
                $versionBloc.hide();
            }
        });

    });

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
            identifier = identifier.replace('/', '');
            identifier = identifier.replace(/v\d+/, '')

            // Delete VERSION from IDENTIFIER
            $(this).val(identifier);

        }

    });

});