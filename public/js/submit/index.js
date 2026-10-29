$(document).ready(function () {
    let $searchDocRepoId = $('#search_doc-repoId');
    let $searchDocDocId = $('#search_doc-docId');
    let $versionBloc = $('#search_doc-version-element');
    let $isDataverseRepo = false;
    let $submitEntry = $("a[href='/submit/index']");

    setPlaceholder();

    $(window).on('load', function () {
        checkDataverse();

        if ($versionBloc.length > 0) toggleVersionBloc();
    });

    $searchDocRepoId.on('change', function () {
        toggleVersionBloc();
        checkDataverse();
    });

    function toggleVersionBloc() {
        let repoValue = $searchDocRepoId.val();

        $searchDocDocId.val('');

        setPlaceholder();

        // isRequiredVersion is only known once this request resolves; block the
        // identifier field in the meantime so SubmitIdentifier.parse() can't
        // run against the previous repository's stale value (e.g. wrongly
        // stripping "v1011" from a BAOBAB slug pasted right after switching repo).
        $searchDocDocId.prop('disabled', true);

        let hasHookRequest = ajaxRequest('/submit/ajaxhashook', {
            repoId: repoValue,
        });
        hasHookRequest.always(function () {
            $searchDocDocId.prop('disabled', false);
        });
        hasHookRequest.done(function (response) {
            let oResponse = JSON.parse(response);

            if (oResponse) {
                // initialized in submit/index.phtml
                isRequiredVersion = oResponse.isRequiredVersion.result;

                if (!isRequiredVersion) {
                    $versionBloc.hide();
                } else {
                    $versionBloc.show();
                }

                if ($searchDocRepoId.val() === zenodoRepoId) {
                    if (zSubmitStatus) {
                        // to be enabled : @ see /config/dist-pwd.json
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
        const searchDocDocId = document.getElementById('search_doc-docId');
        const searchDocRepoId = document.getElementById('search_doc-repoId');

        if (searchDocDocId && searchDocRepoId) {
            const placeholderText =
                translate('exemple : ') + examples[searchDocRepoId.value];
            searchDocDocId.setAttribute('placeholder', placeholderText);
            searchDocDocId.setAttribute('size', placeholderText.length);
        }
    }

    // Extracting the identifier and the version from the pasted identifier or URL
    // @see public/js/submit/identifier.js

    $searchDocDocId.change(function () {
        const input = this.value.trim();
        if (!input) {
            return;
        }

        const versionField = document.getElementById('search_doc-version');

        // Without a version field, the version suffix is kept in the identifier
        const { identifier, version } = SubmitIdentifier.parse(input, {
            isDataverseRepo: $isDataverseRepo,
            isRequiredVersion: isRequiredVersion && versionField !== null,
        });

        this.value = identifier;
        SubmitIdentifier.applyVersion(versionField, version);
    });

    // Once edited by the author, the version field is no longer cleared automatically
    const versionInput = document.getElementById('search_doc-version');
    if (versionInput) {
        versionInput.addEventListener('input', function () {
            delete this.dataset.fromIdentifier;
        });
    }

    function checkDataverse() {
        const searchDocRepoId = document.getElementById('search_doc-repoId');
        const submitEntry = document.querySelector("a[href='/submit/index']");

        if (!searchDocRepoId) return;

        const formData = new FormData();
        formData.append('repoId', searchDocRepoId.value);

        fetch('/submit/ajaxisdataverse', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        })
            .then(response => response.text())
            .then(responseText => {
                const oResponse = JSON.parse(responseText);
                $isDataverseRepo = oResponse.hasOwnProperty('isDataverse')
                    ? oResponse.isDataverse
                    : false;

                if (submitEntry) {
                    const submitEntryTitle = $isDataverseRepo
                        ? 'Proposer un jeu de données'
                        : 'Proposer un article';
                    submitEntry.textContent = translate(submitEntryTitle);
                }
            })
            .catch(error => {
                console.error('Error checking dataverse:', error);
            });
    }
});
