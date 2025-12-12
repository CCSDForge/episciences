let lengthMenu;
let columnDefs;

$(document).ready(function () {
    let $lengthMenu =
        typeof lengthMenu === 'undefined'
            ? [
                  [5, 1, 2, 10, 25, 50, -1],
                  [5, 1, 2, 10, 25, 50, translate('all')],
              ]
            : lengthMenu;
    let $columnDefs =
        typeof columnDefs === 'undefined'
            ? [
                  { searchable: false, targets: [1] },
                  {
                      orderable: false,
                      targets: [4],
                  },
              ]
            : columnDefs;

    $('.dataTable').dataTable({
        fnPreDrawCallback: function () {
            $('.dataTables_wrapper ').css('padding-top', 5);
            $(this)
                .closest('.dataTables_wrapper')
                .find("input[type='search']")
                .prop('spellcheck', false);
        },
        lengthMenu: $lengthMenu,
        stateSave: true,
        pagingType: 'numbers',
        columnDefs: $columnDefs,
        language: DATATABLE_LANGUAGE,
    });
});
