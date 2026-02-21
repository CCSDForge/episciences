function setEvaluationType(type) {
    if (type == 'quantitative') {
        $('#quantitative_rating_type-element').show();
        $('#qualitative_rating_type-element').hide();
        $('#coef-element').show();
        $('#fieldset-options li label').show();
        setRatingType($('#quantitative_rating_type').val());
    } else if (type == 'qualitative') {
        $('#qualitative_rating_type-element').show();
        $('#quantitative_rating_type-element').hide();
        $('#coef-element').hide();
        $('#fieldset-options li label').hide();
        setRatingType($('#qualitative_rating_type').val());
    } else if (type == 'free') {
        $('#quantitative_rating_type-element').hide();
        $('#qualitative_rating_type-element').hide();
        $('#coef-element').hide();
        $('#type-element').hide();
        $('#fieldset-options').hide();
    }
}

function setRatingType(type) {
    if (type == 1) {
        $('#fieldset-options').show();
    } else {
        $('#fieldset-options').hide();
    }
}

function hideRatingLabels() {
    $('#fieldset-options li label:first').hide();
}

function showRatingLabels() {
    $('#fieldset-options li label:first').show();
}

function addValue(btn) {
    // var prev = $(btn).prev('.control-group');
    var prev = $(btn).prev('ul').children('li:last');
    var pos = $(prev).find('input[id^="position_"]').val();
    pos++;
    var max = $('.sortable').children().length;

    var clone = $(prev).clone();

    $(clone).html(
        $(clone)
            .html()
            .replace(/option_[0-9]+/gi, 'option_' + pos)
    );
    $(clone).attr('id', 'li_' + pos); // Nouvel id du <li>
    $(clone)
        .find('button[id^="close_"]')
        .attr('id', 'close_' + pos); // Nouvel id du <button> close
    $(clone)
        .find('button[id^="close_"]')
        .attr('name', 'close_' + pos); // Nouveau name du <button> close
    $(clone)
        .find('button[id^="close_"]')
        .parent('div')
        .attr('id', 'close_' + pos + '-element'); // Nouveau name du <div> du <button> close
    $(clone)
        .find('input:last')
        .attr('id', 'position_' + pos);
    $(clone)
        .find('input:last')
        .attr('name', 'position_' + pos);
    $(clone)
        .find('label')
        .text(pos + '/' + max); // Nouveau label

    $(clone).find('input').val(''); // Vidage de la valeur de l'input

    if ($(clone).find('div[class="input-group"]').length > 1) {
        $(clone)
            .find('div[class="input-group"]:not(:last)')
            .each(function () {
                $(this).find('button:last').trigger('click'); // Suppression des valeurs de l'élément précédent
            });
        $(clone).find('div[class="input-group"]:last').css('display', ''); // Fix d'un bug sur l'affichage du input
    }

    $(clone).find('input:last').val(pos); // Met à jour l'id de position

    // Update de tous les autres labels
    $('.sortable')
        .children()
        .find('label')
        .each(function (i) {
            var label = $(this).text().split('/')[0] + '/' + max;
            $(this).text(label);
        });

    $(clone).insertAfter(prev);
}

function removeValue(btn) {
    // var prev = $(btn).closest('.control-group').parent();
    var prev = $(btn).closest('li');
    var pos = $(prev).find('input[id^="position_"]').val();

    if ($('.sortable').children().length > 1) {
        $(prev).remove();
    } else {
        alert(translate('Vous devez laisser au moins une valeur.'));
    }

    // Update des labels
    var max = $('.sortable').children().length - 1;
    $('.sortable')
        .children()
        .each(function (i) {
            var thisPos = $(this).find('input[id^="position_"]').val();
            if (thisPos > pos) {
                var thisPos = thisPos - 1;
            }

            var label = thisPos + '/' + max;
            $(this).find('label').text(label);
            $(this).find('input[id^="position_"]').val(thisPos);
        });

    // Update des positions

    // Input id et name
    // Label for et label

    // alert ($(prev).length);
    // $(prev).css('border', '1px solid red');
}

$(document).ready(function () {
    setEvaluationType($('#evaluation_type').val());

    $('.sortable').sortable({
        update: function (event, ui) {
            var sorted = $('.sortable').sortable('toArray');

            for (var pos in sorted) {
                // Mise à jour de la position (champ hidden)
                var id = sorted[pos].substr(3);
                $('#position_' + id).val(pos);

                // Mise à jour des labels
                var max = $('label[for^="option_' + id + '"]')
                    .text()
                    .split('/')[1];
                $('label[for="option_' + id + '"]').text(pos + '/' + max);
            }
        },
        placeholder: 'sortable-placeholder',
        forcePlaceholderSize: true,
    });
    // $( ".sortable" ).disableSelection();
});
