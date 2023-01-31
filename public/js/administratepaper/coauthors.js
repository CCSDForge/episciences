$(function () {
    console.log('totottotoot');
    let tmp = '';

    // initialisation de l'autocomplete
    let cache = {};
    $('#autocompletedUserSelection').autocomplete({
        appendTo: $('#autocomplete').closest(".modal-body"),
        html: true,
        minLength: 2,
        source: function (request, response) {
            let term = request.term;
            if (term in cache) {
                response(cache[term]);
                return;
            }
            $.getJSON("/user/findcasusers", request, function (data, status, xhr) {
                cache[term] = data;
                response(data);
            });
        },
        select: function (event, ui) {
            onSelect(event, ui);
            return false;
        }
    });

    // Autocomplete: au focus, on affiche les résultats en cache
    $('#autocompletedUserSelection').focus(function () {
        $(this).autocomplete("search", this.value);
    });

    $('#autocompletedUserSelection').bind('keyup paste', function (event) {

        let keyCode = parseInt(event.keyCode);

        // On exclut les touches fléchées et enter, pour ne pas détecter
        // les changements provoqués par l'autocomplétion
        if ((keyCode < 37 || keyCode > 40) && keyCode != 13) {
            if ($('#autocompletedUserSelection').val() != tmp) {
                $("#selectedUserId").val('0');
                $("#select_user").attr('disabled', 'disabled');
            }
        }

        tmp = $('#autocompletedUserSelection').val();

    });
    function onSelect(event, ui) {
        let label = ui.item.full_name;
        if (event.type == 'autocompleteselect')
            $("#selectedUserId").val(ui.item.id);
        $("#autocompletedUserSelection").val(label);
        $("#select_user").removeAttr('disabled');
    }
})