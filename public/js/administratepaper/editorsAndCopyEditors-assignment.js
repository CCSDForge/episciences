var openedPopover = null;

/**
 *
 * @param button
 * @param docid
 * @param vid
 * @param partial
 * @returns {boolean}
 */
function getAssignUserForm(button, docid, vid, partial) {
    let isPartial = partial !== '' ? JSON.parse(partial) : false;

    let buttonId = $(button).attr('id');

    // destroy previous popups
    $(button).popover('destroy');
    openedPopover = null;

    // toggle: do we open or close popup ?
    if (openedPopover && openedPopover === docid) {
        openedPopover = null;
        return false;
    } else {
        openedPopover = docid;
    }

    let formUrl = JS_PREFIX_URL + 'administratepaper/' + buttonId + 'form';

    // fetch form
    let request = $.ajax({
        type: 'POST',
        url: formUrl,
        data: { docid: docid, vid: vid },
    });

    $(button)
        .popover({
            container: 'body',
            placement: 'bottom',
            html: true,
            content: getLoader(),
        })
        .popover('show');

    request.done(function (result) {
        // destroy loading popup
        $(button).popover('destroy');

        // show form in the popover
        $(button)
            .popover({
                container: 'body',
                placement: 'bottom',
                html: true,
                content: result,
            })
            .popover('show');

        // editors or copy editors filter handlers
        $('#filter').on('keyup', function () {
            filterList('#filter', '.' + buttonId + '-list label');
        });
        $('#filter').on('paste', function () {
            setTimeout(function () {
                filterList('#filter', '.' + buttonId + '-list label');
            }, 4);
        });

        // Initialize editor availability handling
        if (buttonId === 'editors') {
            initializeEditorAvailability();
        }

        let saveAction = JS_PREFIX_URL + 'administratepaper/save' + buttonId;

        $('form[id^=assign]').on('submit', function () {
            if (!$(this).data('submitted')) {
                // to fix duplicate ajax request
                $(this).data('submitted', true);
                // ajax form processing
                let saveActionRequest = ajaxRequest(
                    saveAction,
                    $(this).serialize(),
                    'POST',
                    'json'
                );
                saveActionRequest.done(function (response) {
                    if (JSON.parse(response).result) {
                        let displayAction =
                            JS_PREFIX_URL +
                            'administratepaper/display' +
                            buttonId;
                        let container = $(button)
                            .closest('.' + buttonId)
                            .parent();

                        // destroy editor or copy editor sassignment popup
                        $(button).popover('destroy');
                        $(container).hide();
                        $(container).html(getLoader());
                        $(container).fadeIn();

                        // refresh section editors or copy editors
                        let refreshRequest = ajaxRequest(displayAction, {
                            docid: docid,
                            partial: isPartial,
                        });
                        refreshRequest.done(function (refResponse) {
                            $(container).hide();
                            $(container).html(refResponse);
                            $(container).fadeIn();
                        });

                        // refresh paper history
                        if (!isPartial) {
                            // not partial
                            refreshPaperHistory(docid);
                        }
                    }
                });
            }
            return false;
        });
    });
}

/**
 *
 * @param docId
 * @param uid
 */
function getRefusedMonitoringForm(docId, uid) {
    let refuseManagingFormRequest = $.ajax({
        type: 'POST',
        url: JS_PREFIX_URL + 'administratepaper/refusedmonitoringform',
        data: { docId: docId, uid: uid },
    });

    refuseManagingFormRequest.done(function (form) {
        $('#editors').after(form);
        // Le re-affichage de ce même boutton est géré dans le formulaire.
        $("button[id^='refused_monitoring_button-']").hide();
    });
}

function closeResult() {
    $('button').popover('destroy');
}
