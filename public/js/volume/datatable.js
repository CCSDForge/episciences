$(document).ready(function () {

    $(".dataTable").dataTable({
        fnPreDrawCallback: function () {
            $(this).closest('.dataTables_wrapper').find( "input[type='search']").prop('spellcheck', false);
        },
        "lengthMenu": [[-1], [translate("all")]],
        stateSave: true,
        ordering: false,
        "pagingType": "numbers",
        "columnDefs": [{
            "searchable": false,
            "targets": [1]
        }],

        "language": {
            "lengthMenu": translate("Afficher") + " _MENU_ " + translate("lignes"),
            "search": translate("Rechercher") + " :",
            "zeroRecords": translate("Aucun résultat"),
            "info": translate("Lignes") + " _START_ " + translate("à") + " _END_, " + translate("sur") + " _TOTAL_ ",
            "infoEmpty": translate("Aucun résultat affiché"),
            "infoFiltered": "(" + translate("filtrés sur les") + " _MAX_)",
            "paginate": {"sPrevious": "", "sNext": ""},
        }

    });

    $('.sortable').sortable({
        handle: ".sortable-handle",
        placeholder: "sortable-placeholder",
        update: function (event, ui) {

            $.ajax({
                url: "/volume/sort",
                type: 'POST',
                data: {sorted: $(this).sortable("toArray")},
                dataType: "json"
            });
        }
    });

});
