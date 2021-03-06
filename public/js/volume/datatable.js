$(document).ready(function () {

    $(".dataTable").dataTable({
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, translate("all")]],
        stateSave: true,
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
