var openedPopover = null;
var openedRolesButton = null;

$(function () {
    // Initialize user autocomplete using the utility function
    createUserAutocomplete({
        appendTo: $('#autocomplete').closest('.modal-body'),
    });

    // DataTables detaches the rows that are filtered out or on another page and
    // moves the others: on redraw, close the roles popover if its button is gone,
    // otherwise keep it (and its unsaved selections) and follow the button
    $('#localUsers').on('draw.dt', refreshRolesPopover);
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
 * Top of the visible area of the viewport: bottom of the fixed top navbar, if any.
 */
function getVisibleViewportTop() {
    var navbar = document.querySelector('.navbar-fixed-top');
    if (!navbar) {
        return 0;
    }
    return Math.max(navbar.getBoundingClientRect().bottom, 0);
}

/**
 * Bootstrap popover placement callback for the roles form: opens the popover
 * on the side of the button with the most room, and caps the height of the
 * roles list so the whole popover (including the submit button) fits in the viewport.
 * When currentPlacement is given (popover already displayed), it is kept as long as
 * the popover still fits on that side, so that it does not flip while scrolling.
 */
function placeRolesPopover(tip, element, currentPlacement) {
    var margin = 20; // popover arrow + breathing room
    var rect = element.getBoundingClientRect();
    var spaceBelow = window.innerHeight - rect.bottom - margin;
    // Do not count the space hidden by the fixed top navbar
    var spaceAbove = rect.top - getVisibleViewportTop() - margin;
    var placement = spaceBelow >= spaceAbove ? 'bottom' : 'top';

    var list = tip.querySelector('.roles-form-list');
    // Height of everything but the roles list (buttons, paddings). When Bootstrap 3.2
    // first calls this, the tip is not attached to the DOM yet and cannot be measured:
    // fall back to an estimate, corrected by refreshRolesPopover() once it is displayed.
    var reservedHeight = 80;
    var neededHeight = tip.offsetHeight;
    if (list && tip.offsetHeight > 0) {
        reservedHeight = tip.offsetHeight - list.offsetHeight;
        // Full height of the popover with an uncapped roles list
        neededHeight = reservedHeight + list.scrollHeight;
    }

    if (currentPlacement) {
        var currentSpace = currentPlacement === 'top' ? spaceAbove : spaceBelow;
        if (currentSpace >= neededHeight) {
            placement = currentPlacement;
        }
    }

    if (list) {
        var available =
            (placement === 'top' ? spaceAbove : spaceBelow) - reservedHeight;
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

    // Hide the popover (without destroying it, to keep the checked roles) while its
    // button is scrolled out of view or hidden by the fixed navbar: it would otherwise
    // follow the button and cover the navbar, cut off and detached from any visible row
    var buttonRect = openedRolesButton.getBoundingClientRect();
    var buttonVisible =
        buttonRect.bottom > getVisibleViewportTop() &&
        buttonRect.top < window.innerHeight;
    tip.style.visibility = buttonVisible ? '' : 'hidden';
    if (!buttonVisible) {
        return;
    }

    var currentPlacement = popover.$tip.hasClass('top') ? 'top' : 'bottom';
    var placement = placeRolesPopover(tip, openedRolesButton, currentPlacement);
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
    openedRolesButton = null;

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
            // Same side as the form will use, so that the popover does not jump
            placement: placeRolesPopover,
            html: true,
            content: getLoader(),
        })
        .popover('show');
    // Track the button from now on, so that the loader popover is closed too
    // if the table is redrawn while the form is loading
    openedRolesButton = button;

    request.done(function (result) {
        var loaderPopover = $(button).data('bs.popover');

        // The popover has been closed or replaced while the form was loading
        if (openedRolesButton !== button) {
            return;
        }
        // Closed by the page-wide click handler, which does not reset openedRolesButton
        if (!loaderPopover) {
            openedRolesButton = null;
            return;
        }

        // Open the form on the side of the loader, unless it does not fit there
        // (checked by refreshRolesPopover() once the form can be measured)
        var loaderPlacement = loaderPopover.$tip.hasClass('top')
            ? 'top'
            : 'bottom';

        // Destruction du popup de chargement
        $(button).popover('destroy');

        // Affichage du formulaire dans le popover
        $(button)
            .popover({
                container: 'body',
                placement: function (tip, element) {
                    return placeRolesPopover(tip, element, loaderPlacement);
                },
                html: true,
                content: result,
            })
            .popover('show');
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
                        // Destruction du popup des roles, unless another user's
                        // popover has been opened while saving
                        if (openedRolesButton === button) {
                            closeRolesPopover();
                        }
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
    openedRolesButton = null;
    openedPopover = null;
}
