function updateNote() {
    /*
     * nx = note x
     * cx = coefficient de la note x
     * maxValue = valeur maximale de la note totale
     * Formule : ( ( (n1*c1) + (n2*c2) + ... ) / (c1 + c2 + ...) ) * maxValue
     */

    if (coefs) {
        var valuesCount = 0;
        var overallValue = 0;

        $('select[name^="note_"]').each(function () {
            var id = $(this).attr('name').substr(5);
            if (!$.isNumeric(coefs[id])) {
                return;
            }
            var max = parseInt($(this).find('option:last').val());
            var coef = (parseInt($(this).val()) / max) * coefs[id];
            overallValue += coef;
        });

        for (var i in coefs) {
            valuesCount += parseInt(coefs[i]);
        }

        overallValue = (overallValue / valuesCount) * maxValue;
        overallValue = Math.round(overallValue);

        // On récupère la valeur dans un champ hidden
        $('#noteGlobale').val(overallValue);

        // On affiche la note
        $('.overallValue').html(
            translate('Note globale') +
                ' : ' +
                "<span style='color: #ED8C1C'>" +
                overallValue +
                '/' +
                maxValue +
                '</span>'
        );
    }
}

/**
 * Confirme avant la suppression
 * @param url_file
 */

function confirmDeleteAttachment(url_file) {
    if (confirm(translate('Voulez-vous supprimer ce fichier ?'))) {
        location.href = url_file;
    }
}
