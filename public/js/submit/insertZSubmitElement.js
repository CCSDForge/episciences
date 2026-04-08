function insertZSubmitElement(href = '') {
    let $zButton = $(
        "<blockquote id='z-submit-element' style='margin-top: 5px;'><small>" +
            translate(
                "Si votre article n'a pas encore été publié dans l'archive sélectionnée :"
            ) +
            "<br></small><a class='btn btn-default btn-sm' href= '" +
            href +
            "' target='_blank' role='button' style='margin-top: 5px;'>" +
            translate('Déposer dans Zenodo') +
            '</a></blockquote>'
    );
    $('#search_doc-repoId').after($zButton);
}
