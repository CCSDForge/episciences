$(document).ready(function () {
    let $action = selfAction;
    let $controller = selfController;
    let $status = $('#status');
    let $volume = $('#vid');
    let $section = $('#sid');
    let $editors = $('#editors');
    let $ratingStatus = $('#ratingStatus');
    let $reviewers = $('#reviewers');
    let $doi = $('#doi');
    let $repositories = $('#repositories');
    let $oTable;
    let searchLength = 3;
    const $KeyboardNavigationKeys = [37, 39, 38, 40, 36, 33, 35, 34]; // [Turn left, Turn right, Forward, Backward, Home (start), Pitch Up, End, Pitch Down ]

    if ($('.dataTable').length && $action !== '') {
        let params = getUrlParams();

        if (
            typeof params.onlyEditableWithoutEditors !== 'undefined' &&
            $controller === 'administratepaper' &&
            $action === 'list'
        ) {
            delete params.onlyEditableWithoutEditors;

            let filters = {
                status: editableWithoutEditorsData.status,
                editors: editableWithoutEditorsData.editors,
            };

            $oTable = applyMultipleFilters($controller, $action, {}, filters);
        } else {
            $oTable = applyMultipleFilters($controller, $action, params);
        }

        $('#submit').on('click', function () {
            let filter_status = $status.length > 0 ? $status.val() : [''];
            let filter_volume = $volume.length > 0 ? $volume.val() : [''];
            let filter_section = $section.length > 0 ? $section.val() : [''];
            let filter_editors = $editors.length > 0 ? $editors.val() : [''];
            let filter_ratingStatus =
                $ratingStatus.length > 0 ? $ratingStatus.val() : [''];
            let filter_reviewers =
                $reviewers.length > 0 ? $reviewers.val() : [''];
            let filter_doi = $doi.length > 0 ? $doi.val() : [''];
            let filter_repositories =
                $repositories.length > 0 ? $repositories.val() : [''];

            let filters = {
                status: filter_status,
                volume: filter_volume,
                section: filter_section,
                editors: filter_editors,
                ratingStatus: filter_ratingStatus,
                reviewers: filter_reviewers,
                doi: filter_doi,
                repositories: filter_repositories,
            };

            let isFilter = checkFilterParams(filters);

            if (isFilter) {
                $oTable = applyMultipleFilters(
                    $controller,
                    $action,
                    {},
                    filters
                );
            } else {
                $oTable = applyMultipleFilters($controller, $action);
            }
        });

        $('.dataTables_filter input')
            .unbind()
            .bind('keyup change', function (e) {
                let isNotValidKeyCode =
                    $KeyboardNavigationKeys.indexOf(e.keyCode) !== -1;

                // If the length is 3 or more characters, or the user pressed ENTER, search
                if (!isNotValidKeyCode) {
                    if (this.value.length >= searchLength || e.keyCode === 13) {
                        $oTable.search(this.value).draw();
                    }

                    // Ensure we clear the search if they backspace far enough
                    if (this.value === '') {
                        $oTable.search('').draw();
                    }
                }
            });
    } else {
        console.log(new ReferenceError());
    }
});

/**
 *
 * @param controller
 * @param action
 * @param get
 * @param filters
 * @returns {*|jQuery}
 */
function applyMultipleFilters(controller, action, get = {}, filters = {}) {
    let badRequest = translate(
        "Une erreur interne s'est produite, veuillez recommencer."
    );
    let url = '/' + controller + '/' + action;
    let data =
        Object.keys(get).length !== 0
            ? get
            : {
                  status: 'status' in filters ? filters['status'] : [],
                  vid: 'volume' in filters ? filters['volume'] : [],
                  sid: 'section' in filters ? filters['section'] : [],
                  editors: 'editors' in filters ? filters['editors'] : [],
                  ratingStatus:
                      'ratingStatus' in filters ? filters['ratingStatus'] : [],
                  reviewers: 'reviewers' in filters ? filters['reviewers'] : [],
                  doi: 'doi' in filters ? filters['doi'] : [],
                  repositories:
                      'repositories' in filters ? filters['repositories'] : [],
              };

    let columnDefs = [];
    let order = [];
    let isAutoWidth = false;

    if (
        controller === 'administratepaper' &&
        (action === 'list' || action === 'assigned')
    ) {
        columnDefs.push({ targets: [3, 6, 7, 8], orderable: false });
        columnDefs.push({ className: 'text-center', targets: [0, 1, 2] });
    } else if (controller === 'paper') {
        if (action === 'submitted') {
            columnDefs.push({ targets: [3], orderable: false });
            columnDefs.push({ className: 'text-center', targets: [4, 5] });
        } else if (action === 'ratings') {
            columnDefs.push({ targets: [0, 1, 2, 4, 5], orderable: false });
            columnDefs.push({ className: 'text-center', targets: [6, 7] });
        }
    } else if (controller === 'administratemail' && action === 'history') {
        order.push([3, 'desc']);
        columnDefs.push({ targets: [2], orderable: false });
        columnDefs.push({ className: 'text-center', targets: [2] });
        isAutoWidth = true;
    }

    let oTable = $('.dataTable').DataTable({
        fnPreDrawCallback: function () {
            $("div[id$='_processing']").removeAttr('class');
            $(this)
                .closest('.dataTables_wrapper')
                .find("input[type='search']")
                .prop('spellcheck', false);
        },
        fnDrawCallback: function () {
            activateTooltips();
            // create modal structure
            if (
                controller === 'administratemail' &&
                action === 'history' &&
                $('.modal-opener').length &&
                !modalStructureExists()
            ) {
                createModalStructure();
            }
        },
        //lengthMenu: [ [10, 25, 50, 100, -1], [10, 25, 50, 100, "All"] ],
        columnDefs: columnDefs,
        destroy: true,
        processing: true,
        serverSide: true,
        order: order,
        searching: true,
        searchDelay: 1500,
        ajax: {
            url: url,
            data: data,
            type: 'POST',
            dataSrc: function (response) {
                return response.data;
            },
            error: function (jqXHR, textStatus, errorThrown) {
                let tableInfo = $('.dataTables_info');
                let tableProcessing = $('.dataTables_processing');
                tableInfo.empty();
                tableInfo.html('<p style="color: red">' + badRequest + '</p>');
                tableProcessing.css('display', 'none');
                console.log(errorThrown);
            },
        },
        scrollX: true,
        stateSave: false,
        autoWidth: isAutoWidth,
        dom: "<'dt-header row'<'left col-xs-6'l><'right col-xs-6'f>r>t<'dt-footer row'<'left col-xs-6'i><'right col-xs-6'p>>",
        pagingType: 'numbers',
        // columnDefs: [{"bVisible": false, "aTargets": [0, 4, 9]}],
        language: {
            processing:
                "<div class='col-sm-offset-4 col-sm-4'>" +
                getLoader() +
                '</div>',
            lengthMenu:
                translate('Afficher') + ' _MENU_ ' + translate('lignes'),
            search: translate('Rechercher') + ' ',
            zeroRecords: translate('Aucun résultat'),
            info:
                translate('Lignes') +
                ' _START_ ' +
                translate('à') +
                ' _END_, ' +
                translate('sur') +
                ' _TOTAL_ ',
            infoEmpty: translate('Aucun résultat affiché'),
            infoFiltered: '(' + translate('filtrés sur les') + ' _MAX_)',
            paginate: { sPrevious: '', sNext: '' },
        },
    });

    // oTable.column(9).visible(false);
    addToggleButton('.dataTable', '.dt-header .right');

    return oTable;
}

/**
 * Checks if at least one of the filters has been selected
 * @param filters
 * @returns {boolean}
 */

function checkFilterParams(
    filters = {
        status: [],
        volume: [],
        section: [],
        editors: [],
        ratingStatus: [],
        reviewers: [],
        doi: [],
        repositories: [],
    }
) {
    return (
        'status' in filters &&
        filters['status'].length > 0 &&
        'volume' in filters &&
        filters['volume'].length > 0 &&
        'section' in filters &&
        filters['section'].length > 0 &&
        'editors' in filters &&
        filters['editors'].length > 0 &&
        'ratingStatus' in filters &&
        filters['ratingStatus'].length > 0 &&
        'reviewers' in filters &&
        filters['reviewers'].length > 0 &&
        'doi' in filters &&
        filters['doi'].length > 0 &&
        'repositories' in filters &&
        filters['repositories'].length > 0 &&
        (filters['status'][0] !== '' ||
            filters['volume'][0] !== '' ||
            filters['section'][0] !== '' ||
            filters['editors'][0] !== '' ||
            filters['ratingStatus'][0] !== '' ||
            filters['reviewers'][0] !== '' ||
            filters['doi'][0] !== '' ||
            filters['repositories'][0] !== '')
    );
}

/**
 * Recupère les paramètres passés en GET
 * @param param
 * @returns {*}
 */
function getUrlParams(param = null) {
    let params = {};
    let aParams = [];
    let pathname = window.location.pathname.substr(1).split('/');
    let urlSearch = window.location.search;

    if (urlSearch !== '') {
        window.location.href
            .replace(location.hash, '')
            .replace(/[?&]+([^=&]+)=?([^&]*)?/gi, function (m, k, v) {
                aParams[k] = v !== undefined ? v : '';
            });
    } else {
        // delete controller name and action name
        pathname.splice(0, 2);
        pathname.forEach(function (element, index) {
            aParams[element] =
                pathname[index + 1] !== undefined ? pathname[index + 1] : '';
            pathname.splice(index, 1);
        });
    }

    if (param) {
        return aParams[param] ? Object.assign(params, aParams[param]) : {};
    }
    return Object.assign(params, aParams);
}
