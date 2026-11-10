var openedPopover = null;

/**
 * Closes the popovers opened by .popover-link buttons. Bootstrap 3 keeps a destroyed
 * tip in the DOM until the end of its fade-out: remove it right away, otherwise
 * page-wide selectors (#filter, form[id^=assign]) could still match the old form.
 */
function destroyLinkPopovers() {
    document.querySelectorAll('.popover-link').forEach(function (link) {
        let popover = $(link).data('bs.popover');
        if (!popover) {
            return;
        }
        if (popover.$tip) {
            popover.$tip.remove();
        }
        $(link).popover('destroy');
    });
}

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

    let buttonId = button.id;

    // destroy previous popups, including the ones opened by other buttons:
    // two assignment forms open at once would share the same #filter id
    destroyLinkPopovers();
    openedPopover = null;

    // toggle: do we open or close popup ?
    if (openedPopover && openedPopover === docid) {
        openedPopover = null;
        return false;
    } else {
        openedPopover = docid;
    }

    let formUrl = '/administratepaper/' + buttonId + 'form';

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

    // Every close path (another form opened, click elsewhere on the page,
    // closeResult()) destroys the loader, which triggers 'hide.bs.popover':
    // stop loading a form that would no longer be displayed
    let abortRequest = function () {
        request.abort();
    };
    $(button).one('hide.bs.popover', abortRequest);

    request.done(function (result) {
        // destroy loading popup, without aborting the request that just completed
        $(button).off('hide.bs.popover', abortRequest);
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

        // editors or copy editors filter handlers, bound to the form just displayed
        let filter = $(button)
            .data('bs.popover')
            .tip()[0]
            .querySelector('#filter');
        if (filter) {
            filter.addEventListener('keyup', function () {
                filterList(filter, '.' + buttonId + '-list label');
            });
            filter.addEventListener('paste', function () {
                setTimeout(function () {
                    filterList(filter, '.' + buttonId + '-list label');
                }, 4);
            });
        }

        // Initialize editor availability handling
        if (buttonId === 'editors') {
            initializeEditorAvailability();
        }

        let saveAction = '/administratepaper/save' + buttonId;

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
                    // dataType 'json': jQuery already parsed the response
                    if (response && response.result) {
                        let displayAction =
                            '/administratepaper/display' + buttonId;
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
        url: '/administratepaper/refusedmonitoringform',
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
