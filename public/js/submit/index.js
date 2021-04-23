$(document).ready(function () {
    let $searchDocRepoId = $('#search_doc-repoId');
    let $searchDocDocId = $('#search_doc-docId');
    let $versionBloc = $('#search_doc-version-element');


    setPlaceholder();

    $searchDocRepoId.change(function () {

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

        if (isValidHttpUrl($(this).val())) {
            let tab = $(this).val().split('/');
            if (tab.length > 1) {
                if (tab[tab.length - 1] !== '') {
                    let str = tab[tab.length - 1];
                    // Delete VERSION from ID
                    let id = str.substr(0, str.length - 2);
                    $(this).val(id);
                }
            }
        }

    });

});