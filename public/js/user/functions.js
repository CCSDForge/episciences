var openedPopover = null;
var openedRolesButton = null;

$(function () {
    // Initialize user autocomplete using the utility function
    createUserAutocomplete({
        appendTo: $('#autocomplete').closest('.modal-body'),
    });

    // DataTables detaches the rows that are filtered out or on another page:
    // close the roles popover on redraw so it does not outlive its button
    $('#localUsers').on('draw.dt', closeRolesPopover);
});

/**
 * Closes the opened roles popover, if any, and resets its state.
 */
function closeRolesPopover() {
    if (openedRolesButton) {
        $(openedRolesButton).popover('destroy');
    }
    openedRolesButton = null;
    openedPopover = null;
}

function subForm() {
    $('#selectedUserId').val('0');
    $('#fuser').submit();
}

/**
 * Bootstrap popover placement callback for the roles form: opens the popover
 * on the side of the button with the most room, and caps the height of the
 * roles list so the whole popover (including the submit button) fits in the viewport.
 */
function placeRolesPopover(tip, element) {
    var margin = 20; // popover arrow + breathing room
    var rect = element.getBoundingClientRect();
    var spaceBelow = window.innerHeight - rect.bottom - margin;
    var spaceAbove = rect.top - margin;
    var placement = spaceBelow >= spaceAbove ? 'bottom' : 'top';

    var list = tip.querySelector('.roles-form-list');
    if (list) {
        // Height of everything but the roles list (buttons, paddings). When Bootstrap 3.2
        // first calls this, the tip is not attached to the DOM yet and cannot be measured:
        // fall back to an estimate, corrected by refreshRolesPopover() once it is displayed.
        var reservedHeight =
            tip.offsetHeight > 0 ? tip.offsetHeight - list.offsetHeight : 80;
        var available = Math.max(spaceBelow, spaceAbove) - reservedHeight;
        // Never exceed the available space; keep a small floor so the list stays usable
        list.style.maxHeight = Math.max(available, 40) + 'px';
    }

    return placement;
}

/**
 * Recomputes the placement and the list height of the opened roles popover,
 * e.g. when the window is resized or the page is scrolled while it is open.
 * Relies on Bootstrap 3 tooltip internals (getPosition, getCalculatedOffset, applyPlacement).
 */
function refreshRolesPopover() {
    if (openedRolesButton && !document.body.contains(openedRolesButton)) {
        // The button has been removed from the DOM (e.g. DataTable redraw): its position
        // can no longer be computed and would move the popover to the top-left corner
        closeRolesPopover();
        return;
    }

    var popover = openedRolesButton && $(openedRolesButton).data('bs.popover');
    if (!popover || !popover.$tip || !popover.$tip.is(':visible')) {
        return;
    }

    var tip = popover.$tip[0];
    var placement = placeRolesPopover(tip, openedRolesButton);
    popover.$tip.removeClass('top bottom').addClass(placement);

    var offset = popover.getCalculatedOffset(
        placement,
        popover.getPosition(),
        tip.offsetWidth,
        tip.offsetHeight
    );
    popover.applyPlacement(offset, placement);
}

var rolesPopoverRefreshPending = false;
function scheduleRolesPopoverRefresh() {
    // Throttle to one refresh per animation frame
    if (rolesPopoverRefreshPending) {
        return;
    }
    rolesPopoverRefreshPending = true;
    window.requestAnimationFrame(function () {
        rolesPopoverRefreshPending = false;
        refreshRolesPopover();
    });
}

window.addEventListener('resize', scheduleRolesPopoverRefresh);
window.addEventListener('scroll', scheduleRolesPopoverRefresh, {
    passive: true,
});

function getRoles(button, uid) {
    // Destruction des anciens popups
    $('button').popover('destroy');

    // Toggle : est-ce qu'on ouvre ou est-ce qu'on ferme le popup ?
    if (openedPopover && openedPopover == uid) {
        openedPopover = null;
        return false;
    } else {
        openedPopover = uid;
    }

    // Récupération du formulaire
    var request = $.ajax({
        type: 'POST',
        url: '/user/rolesform',
        data: { uid: uid },
    });

    $(button)
        .popover({
            delay: 0,
            container: 'body',
            placement: 'bottom',
            html: true,
            content: getLoader(),
        })
        .popover('show');

    request.done(function (result) {
        // Destruction du popup de chargement
        $(button).popover('destroy');

        // Affichage du formulaire dans le popover
        $(button)
            .popover({
                container: 'body',
                placement: placeRolesPopover,
                html: true,
                content: result,
            })
            .popover('show');
        openedRolesButton = button;
        // Fix the initial estimate now that the real heights can be measured
        refreshRolesPopover();

        $('form[action="/user/saveroles"]').on('submit', function () {
            $(this).parent().html(getLoader());
            // The loader is much smaller than the form: with a top placement, the tip
            // would keep its top coordinate and leave a gap above the button
            refreshRolesPopover();

            // Traitement AJAX du formulaire
            $.ajax({
                url: '/user/saveroles',
                type: 'POST',
                // data: {uid:uid, data: $(this).serialize()},
                data: $(this).serialize() + '&uid=' + uid,
                success: function (result) {
                    if (result == 1) {
                        // Destruction du popup des roles
                        $(button).popover('destroy');
                        openedPopover = null;
                        var td = $('#localUsers_' + uid + ' td:nth-child(5)');
                        var container = $(td).find('.tags');
                        $(container).html(getLoader());

                        // Refresh de l'affichage des rôles pour cet utilisateur
                        $.ajax({
                            url: '/user/displaytags',
                            type: 'POST',
                            data: { uid: uid },
                            success: function (tags) {
                                $(container).hide();
                                $(container).html(tags);
                                $(container).fadeIn();
                            },
                        });
                    }
                },
            });

            return false;
        });
    });
}

function closeResult() {
    $('button').popover('destroy');
}
