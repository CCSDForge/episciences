/**
 *
 */
$(function () {
    $('#q')
        .autocomplete({
            minLength: 1,
            source: '/search/autocomplete',
            focus: function (event, ui) {
                $('#project').val(ui.item.label);
                return false;
            },
            select: function (event, ui) {
                $('#q').val(ui.item.label);
                // $( "#project-id" ).val( ui.item.value );
                // $( "#project-description" ).html( ui.item.desc );
                // $( "#project-icon" ).attr( "src", "images/" + ui.item.icon );

                return false;
            },
        })
        .data('ui-autocomplete')._renderItem = function (ul, item) {
        return $('<li>')
            .append(
                '<a>' +
                    item.label +
                    ' <span class="muted pull-right">(' +
                    item.count +
                    ' - ' +
                    item.category +
                    ')</span></a>'
            )
            .appendTo(ul);
    };
});
