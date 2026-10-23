var openedPopover = null;
/**
 *
 * @param button
 * @param docId
 * @param url
 * @param popoverParams
 * @returns {boolean|*}
 */
function getCommonForm(
    button,
    docId,
    url = '#',
    popoverParams = {}
) {

    const $button = $(button);

    const defaultParams = {
        placement: 'bottom',
        container: 'body',
        html: true,
        content: getLoader(),
    };

    const params = { ...defaultParams, ...popoverParams };

    // Removing Old Pop-ups
    $button.popover('destroy');

    //  Toggle: Should we open or close the popup?
    if (openedPopover && openedPopover === docId) {
        openedPopover = null;
        return false;
    } else {
        openedPopover = docId;
    }

    $(button).popover(params).popover('show');

    // Retrieving the form
    return ajaxRequest(url, { docid: docId });
}

