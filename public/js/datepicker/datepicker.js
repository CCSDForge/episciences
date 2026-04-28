$(function () {
    //Initialisation des elements datepicker
    datepicker('.datepicker');
});

/**
 * Initialisation des elements datepicker
 *
 * pour faire fonctionner le bouzin :
 * - cas simple : l'élement input doit avoir  class="datepicker"
 * - cas plus compliqué : saisie d'un intervalle il faut que les 2 champs de saisie des dates possèdent un id
 * de la forme XXX_start et XXX_end
 *
 * On peut rajouter des attributs HTML aux éléments input :
 * - attr-mindate="yyyy-mm-dd" : date limite inférieure
 * - attr-maxdate="yyyy-mm-dd" : date limite supérieur
 * - attr-changemonth="1" : Possibilité de changer rapidement le mois
 * - attr-changeyear="1" : Possibilité de changer rapidement l'année
 *
 * @param selector
 */
function datepicker(selector) {
    var dateFormat = 'yy-mm-dd'; // Format ISO 8601
    var lang; // Declare lang at function start

    $(selector).each(function (index) {
        var params = { dateFormat: dateFormat, constrainInput: true };
        var id = $(this).attr('id');

        //Cas des intervalles
        if (id != undefined) {
            if (id.match(/_start/i)) {
                params.onSelect = function (dateText, inst) {
                    var end = id.replace('_start', '_end');

                    if (!$('#' + end).val()) {
                        $('#' + end).val(dateText);
                    } else {
                        if (
                            $('#' + id).datepicker('getDate') >
                            $('#' + end).datepicker('getDate')
                        ) {
                            $('#' + end).val('');
                        }
                    }
                    $('#' + end).datepicker(
                        'option',
                        'defaultDate',
                        $.datepicker.parseDate(dateFormat, dateText)
                    );
                    $('#' + end).datepicker(
                        'option',
                        'minDate',
                        $.datepicker.parseDate(dateFormat, dateText)
                    );
                };
            }
            if (id.match(/_end/i)) {
                params.onSelect = function (dateText, inst) {
                    var start = id.replace('_end', '_start');
                    if (
                        $('#' + id).datepicker('getDate') <
                        $('#' + start).datepicker('getDate')
                    ) {
                        $('#' + start).val('');
                    }
                };
            }
        }

        //Trigger pour ouvrir le calendrier sur le click d'un bouton
        if ($(this).attr('attr-trigger')) {
            params.buttonImageOnly = true;
            params.showOn = 'button';
        }

        // Accès rapide pour le changement de mois
        if ($(this).attr('attr-changemonth')) params.changeMonth = true;

        // Accès rapide pour le changement d'année
        if ($(this).attr('attr-changeyear')) params.changeYear = true;

        // limite de date inférieure
        if ($(this).attr('attr-mindate'))
            params.minDate = $(this).attr('attr-mindate');

        //Limite de date supérieure
        if ($(this).attr('attr-maxdate'))
            params.maxDate = $(this).attr('attr-maxdate');

        //traduction du datepicker
        if (typeof lang === 'undefined' || lang === undefined) {
            lang = 'fr';
        }

        $(this).datepicker($.datepicker.regional[lang]);

        var value = $(this).val();

        $(this).datepicker('option', params);

        $(this).val(value);
    });
}
