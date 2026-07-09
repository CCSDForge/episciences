document.addEventListener('DOMContentLoaded', function () {
    const button = document.getElementById('btn-edit-master-file');
    if (button) {
        button.addEventListener('click', function () {
            const docId = button.dataset.docid;
            getMasterFileForm(button, docId);
        });
    }
});

function getMasterFileForm(button, docId) {
    const $button = $(button);
    const masterFileEp = {
        ENDPOINTS: {
            GET_FORM:  JS_PREFIX_URL + 'paper/getmasterfileform',
            SAVE_FILE: JS_PREFIX_URL + 'paper/savemasterfile'
        }
    };

    // Parameters Validation
    if (!$button || !docId) {
        let errorMsg = '[getMasterFileForm] Invalid parameters:';

        if (!docId) {
            errorMsg += 'EPMTY docId';
        } else {
            errorMsg += 'EMPTY SELECTOR';
        }
        console.error(errorMsg);
        return Promise.reject(new Error('Invalid parameters'));
    }

    const $commonForm = getCommonForm(button, docId, masterFileEp.ENDPOINTS.GET_FORM);

    // Destroy any existing popovers to avoid conflicts
    $button.popover('destroy');
    openedPopover = null;

    // Show loading spinner in popover
    let popoverParams = {
        placement: 'bottom',
        container: 'body',
        html: true,
        content: getLoader()
    };

    return $commonForm.done(function (result) {
        // Update popover with form content
        popoverParams.content = result;
        $button.popover(popoverParams).popover('show');

        // Store reference to close later
        openedPopover = docId;

        const actionStr = masterFileEp.ENDPOINTS.SAVE_FILE;

        // Find the form INSIDE the popover only
        const $formInPopover = $('.popover-content').find('form[action^="' + actionStr + '"]');

        // Unbind previous handlers before adding new ones
        $formInPopover.off('submit');

        $formInPopover.on('submit', function (e) {
            e.preventDefault();  // Prevent default form submission


            const $loaderContainer = $('#in-progress');

            // Show loader
            popoverParams.content = getLoader();
            $button.popover(popoverParams);
            if ($loaderContainer.length) {
                $loaderContainer.html(getLoader());
            }

            // Make AJAX request
            return ajaxRequest(
                actionStr,
                $(this).serialize() + '&docid=' + encodeURIComponent(docId),
                'POST',
                'json'
            )
                .done(function (response) {

                    let result;

                    try {
                        // Handle already-parsed response or raw string
                        result = typeof response === 'string' ? JSON.parse(response) : response;
                    } catch (err) {
                        console.error('[getMasterFileForm] Parse error:', err);
                        return false;
                    }

                    // Close popover and cleanup
                    $button.popover('hide');
                    $button.popover('destroy');
                    openedPopover = null;

                    // refresh master file icon
                    if (result.success && result.targetId) {
                        refreshMasterFileIcon(result.targetId);
                    }

                    return result;
                })
                .fail(function (xhr, status, error) {
                    console.error('[getMasterFileForm] Request failed:', error);
                    $button.popover('hide');
                    $button.popover('destroy');
                    openedPopover = null;

                    return false;
                });
        });

        return $formInPopover;
    }).fail(function (error) {
        console.error('[getMasterFileForm] Get form failed:', error);
        return Promise.reject(error);
    });
}


/**
 * places icon after <small> in target TD
 * @param {number|string} targetId
 * @returns {boolean}
 */
function refreshMasterFileIcon(targetId) {
    const $td = $(`#td-${targetId}`);
    const $small = $td.find('small');

    if (!$small.length) return false;

    // Existing or new icon
    let $icon = $('.fa-user-check').first();

    if (!$icon.length) {
        $icon = $('<i class="fa-solid fa-user-check"></i>')
            .css({'margin-left': '5px', 'cursor': 'help'})
            .attr('title', 'Primary file');
    }

    // Remove old position (if any)
    $icon.closest('td').find('.fa-user-check').remove();

    // Clear any existing icon in destination
    $td.find('.fa-user-check').remove();

    // Place after <small>
    $small.after($icon);

    return true;
}



