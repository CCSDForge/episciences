var openedPopover = null;

function getTags() {
    let paper_title = '';

    if ($.type(paper.title) === 'object') {
        if (paper.title[locale]) {
            paper_title = paper.title[locale];
        } else {
            paper_title = paper.title[0];
        }
    } else {
        paper_title = paper.title;
    }

    let tags = [
        { text: translate('Code de la revue'), value: review['code'] },
        { text: translate('Nom de la revue'), value: review['name'] },
        { text: translate("Id de l'article"), value: paper.id.toString() },
        { text: translate("Titre de l'article"), value: paper_title },
    ];

    if (paper_ratings) {
        tags.push({
            text: translate('Rapports de relecture'),
            value: paper_ratings,
        });
    }

    if (author) {
        if (author.fullname) {
            tags.push({
                text: translate('Nom complet du contributeur'),
                value: author.fullname,
            });
        }
        if (author.email) {
            tags.push({
                text: translate('E-mail du contributeur'),
                value: author.email,
            });
        }

        if (author.user_name) {
            tags.push({
                text: translate('Identifiant du contributeur'),
                value: author.user_name,
            });
        }
        if (author.screen_name) {
            tags.push({
                text: translate("Nom d'affichage du contributeur"),
                value: author.screen_name,
            });
        }
    }

    if (sender) {
        if (sender.fullname) {
            tags.push({
                text: translate("Nom complet de l'expéditeur"),
                value: sender.fullname,
            });
        }
        if (sender.email) {
            tags.push({
                text: translate("E-mail de l'expéditeur"),
                value: sender.email,
            });
        }
        if (sender.screen_name) {
            tags.push({
                text: translate("Nom d'affichage de l'expéditeur"),
                value: sender.screen_name,
            });
        }
    }

    return tags;
}

$(document).ready(function () {
    // Désactivation de la possibilité de mettre à jour des métadonnées pour les versions temporaires d'un article.
    $('#update_metadata').prop('disabled', true);

    if (paper.repository != 0) {
        $('#update_metadata').prop('disabled', false);
    }

    let options = {
        convert_urls: false,
        menubar: false,
        tags: getTags(),
        height: 400,
        plugins: 'link image code fullscreen table',
        external_plugins: {
            inserttag: '/js/tinymce/plugins/es_tags/plugin.min.js',
        },
        toolbar1:
            'bold italic underline | inserttag | undo redo | alignleft aligncenter alignright alignjustify | bullist numlist | link image | code ',
    };

    __initMCE('.full_mce', undefined, options);

    $('#confirmNewVersion').on('click', function (e) {
        if (!$('#commentNewVersion').tinymce().getContent()) {
            alert(
                translate(
                    'Veuillez renseigner le champ "Modifications à apporter"'
                )
            );
            e.preventDefault();
        }
    });

    $('.submit-modal').click(function () {
        $(this).closest('.modal-content').find('form').submit();
    });

    // Initialisation du menu des rédacteurs
    $('#editors')
        .find('.editor .popover-link')
        .each(function () {
            $(this).on('click', function () {
                getUserMenu(this);
            });
        });

    // Initialisation du menu de contributeur
    $('#contributor')
        .find('.contributor .popover-link')
        .each(function () {
            $(this).on('click', function () {
                getUserMenu(this);
            });
        });

    // Initialisation du menu des préparateurs de copie
    $('#copy-editors')
        .find('.copy-editor .popover-link')
        .each(function () {
            $(this).on('click', function () {
                getUserMenu(this);
            });
        });

    // logs ******************************

    // search in logs (input)
    let history_search = $('.history-search');
    history_search.keyup(function () {
        searchLogs();
    });
    history_search.on('paste', function () {
        setTimeout(function () {
            searchLogs();
        }, 100);
    });

    // filter logs by date (popover)
    let history_popover_content = $('#history-popover-content');
    let popover_button = $('.popover-button');
    popover_button
        .popover({
            html: true,
            placement: 'bottom',
            content: function () {
                return history_popover_content.html();
            },
        })
        .on('shown.bs.popover', function () {
            // datepickers init
            let history_filter_start = $('#history-filter-start');
            let history_filter_end = $('#history-filter-end');
            history_filter_start.datepicker();
            history_filter_end.datepicker();
            $('.history-filters .datepicker-button').click(function () {
                $(this).next().datepicker('show');
            });

            // default values

            let default_start = history_popover_content
                .find('input[name="history-filter-start"]')
                .val();
            let default_end = history_popover_content
                .find('input[name="history-filter-end"]')
                .val();
            history_filter_start.val(default_start);
            history_filter_end.val(default_end);

            // popover buttons init
            $('.popover .cancel').click(function () {
                history_popover_content
                    .find('input[name="history-filter-start"]')
                    .val('');
                history_popover_content
                    .find('input[name="history-filter-end"]')
                    .val('');
                filterLogs('', '');
                popover_button.popover('hide');
            });
            $('.popover .submit').click(function () {
                history_popover_content
                    .find('input[name="history-filter-start"]')
                    .val(history_filter_start.val());
                history_popover_content
                    .find('input[name="history-filter-end"]')
                    .val(history_filter_end.val());
                filterLogs(
                    history_filter_start.val(),
                    history_filter_end.val()
                );
                popover_button.popover('hide');
            });
        });

    // update deadline
    $("[id$='-revision-deadline']").on('change keyup past', function () {
        let $minorSubmit = $('button[id^="submit-modal-minor-revision"]');
        let $majorSubmit = $('button[id^="submit-modal-major-revision"]');

        let locale =
            author && author.langueid !== siteLocale
                ? defaultLocale
                : author.langueid;

        let deadline = $(this).val();

        let isValid = isRequiredRevisionDeadline ? deadline !== '' : true; // configurable, see journal's settings

        if (deadline !== '') {
            if (!isISOdate(deadline) || !isValidDate(deadline)) {
                alert(
                    translate(
                        "La date limite de révision n'est pas valide : Veuillez saisir une date limite de révision au format : AAAA-mm-jj."
                    )
                );
                disableModalSubmitButton($minorSubmit);
                disableModalSubmitButton($majorSubmit);
                return;
            }
        }

        if (isValid) {
            let id = $(this).attr('id');

            let messageId = id.substring(0, id.length - 8) + 'message'; // 8: length (deadline)

            let oBody = getObjectNameFromTinyMce(messageId); // object

            updateDeadlineTag(oBody, 'revision_deadline', deadline, locale);

            enableModalSubmitButton($minorSubmit);
            enableModalSubmitButton($majorSubmit);
        } else {
            disableModalSubmitButton($minorSubmit);
            disableModalSubmitButton($majorSubmit);
        }
    });

    // show and hide citations to avoid big listing page
    $('button[id^="btn-show-citations"]').click(function () {
        $('div#list-citations').show();
        $('#btn-hide-citations').show();
        $('#btn-show-citations').hide();
    });
    $('button[id^="btn-hide-citations"]').click(function () {
        $('div#list-citations').hide();
        $('#btn-hide-citations').hide();
        $('#btn-show-citations').show();
    });
    $('input#copycoauthor').click(function () {
        let coAuthorsMailStr = $('input#coauthormail').val();
        if ($(this).prop('checked')) {
            $("input[name='cc']").val(coAuthorsMailStr);
        } else {
            let inputcc = $("input[name='cc']");
            inputcc.val(inputcc.val().replace(coAuthorsMailStr, ''));
        }
    });
});

// filter logs
function filterLogs(from, to) {
    if (from || to) {
        $('.history-datepicker-button').css('border', '1px solid #ff9d00');
    } else {
        $('.history-datepicker-button').css('border', '1px solid #ccc');
    }
    $('.history-logs div.log-entry')
        .show()
        .filter(function () {
            return (
                (from && Date.parse($(this).data('date')) < Date.parse(from)) ||
                (to && Date.parse($(this).data('date')) > Date.parse(to))
            );
        })
        .hide();
}

// search logs (paper history)
function searchLogs() {
    let input = $('.history-search').val();

    if (input) {
        $('.history-search').css('border', '1px solid #ff9d00');
    } else {
        $('.history-search').css('border', '1px solid #ccc');
    }

    let re = new RegExp(input, 'i'); // "i" means it's case-insensitive
    $('.history-logs div.log-entry')
        .show()
        .filter(function () {
            return !re.test($(this).text());
        })
        .hide();
}

function getReviewerMenu(button) {
    let docid = $(button).data('docid');
    let uid = $(button).data('uid');
    let byUid = $(button).data('by_uid');
    let aid = $(button).data('aid');
    let tmp = $(button).data('tmp');
    let status = $(button).data('status');
    let rating = $(button).data('rating');
    let canBeReviewed = JSON.parse($(button).data('can_be_reviewed'));

    // Toggle : est-ce qu'on ouvre ou est-ce qu'on ferme le popup ?
    if (openedPopover && openedPopover === uid) {
        openedPopover = null;
        return false;
    } else {
        openedPopover = uid;
    }

    $('.popover-link').popover('destroy');

    let content = '<ul class="context-menu">';

    if (!tmp) {
        content += '<li>';
        content +=
            '<a href="' +
            JS_PREFIX_URL +
            'user/view/userid/' +
            uid +
            '" target="_blank">';
        content +=
            '<span class="glyphicon glyphicon-user" style="margin-right: 5px"></span> ' +
            translate('Voir le profil');
        content += '</a>';
        content += '</li>';
    }

    if (uid !== byUid) {
        content += '<li>';
        content +=
            '<a class="modal-opener" href="' +
            JS_PREFIX_URL +
            'administratemail/send/recipient/' +
            uid +
            '/paper/' +
            docid;
        if (tmp) content += '/tmp/1';
        content += '" ';
        content += 'data-width="50%" ';
        content += 'title="' + translate('Contacter un relecteur') + '">';
        content +=
            '<span class="glyphicon glyphicon-envelope" style="margin-right: 5px"></span> ' +
            translate('Contacter ce relecteur');
        content += '</a>';
        content += '</li>';
    }

    if (canBeReviewed && status === 'expired') {
        let href = '#';

        content += '<li>';
        content += '<a href="' + href + '" ';
        content += 'onclick = "reinviteReviewer(' + docid + ',' + uid + ')" ';
        content += 'title="' + translate('Réinviter ce relecteur') + '">';
        content +=
            '<span class="glyphicon glyphicon-repeat" style="margin-right: 5px"></span> ' +
            translate('Réinviter ce relecteur') +
            '</a></li>';
    }

    if (
        canBeReviewed &&
        rating !== 2 &&
        (status === 'active' || status === 'pending')
    ) {
        content += '<li>';
        content +=
            '<a class="modal-opener" href="' +
            JS_PREFIX_URL +
            'administratepaper/updatedeadline/aid/' +
            aid +
            '" ';
        content += 'data-width="50%" ';
        content += 'data-callback="submit" ';
        content +=
            'title="' +
            translate('Modification de la date limite de rendu de relecture') +
            '">';
        content +=
            '<span class="glyphicon glyphicon-calendar" style="margin-right: 5px"></span> ' +
            translate('Modifier la date limite de rendu de la relecture') +
            '</a></li>';
    }

    if (
        status === 'pending' ||
        ((status === 'active' || status === 'uninvited') && rating === 0)
    ) {
        let href =
            JS_PREFIX_URL + 'administratepaper/removereviewer/aid/' + aid;

        if (status === 'uninvited') {
            href += '/status/' + status;
        }

        content += '<li>';
        content += '<a class="modal-opener" href="' + href + '" ';
        content += 'data-width="50%" ';
        content += 'data-callback="submit" ';
        content += 'title="' + translate('Retirer un relecteur') + '">';
        content +=
            '<span class="glyphicon glyphicon-remove" style="margin-right: 5px"></span> ' +
            translate('Retirer ce relecteur') +
            '</a></li>';
    }

    if (
        canBeReviewed &&
        (status === 'active' || status === 'uninvited') &&
        (rating === 0 || rating === 1)
    ) {
        content += '<li>';
        content +=
            '<a href="' +
            JS_PREFIX_URL +
            'paper/rating?id=' +
            docid +
            '&reviewer_uid=' +
            uid +
            '&byUid=' +
            byUid +
            '"  ';
        content += 'data-callback="submit"';
        content +=
            'title="' +
            translate("Remplir l'évaluation pour le compte de ce relecteur") +
            '">';
        content +=
            '<span class="glyphicon glyphicon-edit" style="margin-right: 5px"></span> ' +
            translate('Télécharger le rapport du relecteur') +
            '</a></li>';
    }
    if (
        canBeReviewed &&
        (status === 'active' || status === 'uninvited') &&
        rating === 2
    ) {
        content += '<li>';
        content +=
            '<a href="' +
            JS_PREFIX_URL +
            'administratepaper/refreshrating/id/' +
            docid +
            '/reviewer_uid/' +
            uid +
            '"  ';
        content += 'data-callback="submit"';
        content +=
            'title="' +
            translate('Permettre au relecteur de modifier son évaluation') +
            '">';
        content +=
            '<span class="glyphicon glyphicon-refresh" style="margin-right: 5px"></span> ' +
            translate('Autoriser la modification de la relecture') +
            '</a></li>';
    }

    content += '</ul>';

    $(button)
        .popover({
            placement: 'bottom',
            html: true,
            content: content,
        })
        .popover('show');
}

function getUserMenu(button) {
    let uid = $(button).data('uid');
    let docid = $(button).data('docid');
    let dataId = $(button).data('id');
    let modalTitle = '';
    let userTitle = '';

    switch (dataId) {
        case 'editors':
            modalTitle = 'Contacter un rédacteur';
            userTitle = 'Contacter ce rédacteur';
            break;
        case 'copy-editors':
            modalTitle = 'Contacter un préparateur de copie';
            userTitle = 'Contacter ce préparateur de copie';
            break;
        case 'contributor':
            modalTitle = 'Contacter un contributeur';
            userTitle = 'Contacter ce contributeur';
            break;
        default:
            modalTitle = 'Contacter un utilisateur';
            userTitle = 'Contacter cet utilisateur';
            break;
    }

    // Toggle : est-ce qu'on ouvre ou est-ce qu'on ferme le popup ?
    if (openedPopover && openedPopover === uid) {
        openedPopover = null;
        return false;
    } else {
        openedPopover = uid;
    }

    $('.popover-link').popover('destroy');

    let content = '<ul class="context-menu">';

    content += '<li>';
    content +=
        '<a href="' +
        JS_PREFIX_URL +
        'user/view/userid/' +
        uid +
        '" target="_blank">';
    content +=
        '<span class="glyphicon glyphicon-user" style="margin-right: 5px"></span> ' +
        translate('Voir le profil');
    content += '</a>';
    content += '</li>';

    content += '<li>';
    content +=
        '<a class="modal-opener" href="' +
        JS_PREFIX_URL +
        'administratemail/send/recipient/' +
        uid +
        '/paper/' +
        docid +
        '" ';
    content += 'data-width="50%" ';
    content += 'title="' + translate(modalTitle) + '">';
    content +=
        '<span class="glyphicon glyphicon-envelope" style="margin-right: 5px"></span> ' +
        translate(userTitle);
    content += '</a>';
    content += '</li>';

    content += '</ul>';

    $(button)
        .popover({
            placement: 'bottom',
            html: true,
            content: content,
        })
        .popover('show');
}

function showForm(name) {
    console.log('ok');
    hideForms();
    $('#change-status-group').hide();
    $('#' + name + '-form').fadeIn();
    // scrollTo($('#'+name+'-form'), $('#suggeststatus'));
}

function hideForm(name) {
    $('#' + name + '-form').hide();
}

function hideForms() {
    $('div[id$=-form]').hide();
}

function cancel() {
    hideForms();
    $('#change-status-group').fadeIn();
}

/**
 *
 * @param button
 * @param docId
 * @param url
 * @param popoverParams
 * @returns {boolean|*}
 */
function getCommunForm(
    button,
    docId,
    url = JS_PREFIX_URL + 'administratepaper/doiform',
    popoverParams = {}
) {
    const defaultParams = {
        placement: 'bottom',
        container: 'body',
        html: true,
        content: getLoader(),
    };

    if (typeof popoverParams.placement === 'undefined') {
        popoverParams.placement = defaultParams.placement;
    }

    if (typeof popoverParams.container === 'undefined') {
        popoverParams.container = defaultParams.container;
    }

    if (typeof popoverParams.html === 'undefined') {
        popoverParams.html = defaultParams.html;
    }

    if (typeof popoverParams.content === 'undefined') {
        popoverParams.content = defaultParams.content;
    }

    // Destruction des anciens popups
    $(button).popover('destroy');

    // Toggle : est-ce qu'on ouvre ou est-ce qu'on ferme le popup ?
    if (openedPopover && openedPopover == docId) {
        openedPopover = null;
        return false;
    } else {
        openedPopover = docId;
    }

    $(button).popover(popoverParams).popover('show');

    // Récupération du formulaire
    return ajaxRequest(url, { docid: docId });
}

/**
 *
 * @param button
 * @param docId
 */
function getPublicationDateForm(button, docId) {
    let request = getCommunForm(
        button,
        docId,
        JS_PREFIX_URL + 'administratepaper/publicationdateform'
    );

    let popoverParams = {
        placement: 'bottom',
        container: 'body',
        html: true,
        content: getLoader(),
    };

    request.done(function (result) {
        // Destruction du popup de chargement
        $(button).popover('destroy');
        openedPopover = null;
        // Affichage du formulaire dans le popover
        popoverParams.content = result;
        $(button).popover(popoverParams).popover('show');

        let formAction =
            JS_PREFIX_URL + 'administratepaper/savepublicationdate';

        $('form[action^="' + formAction + '"]').on('submit', function () {
            let $publicationDate = $('#publication-date');
            // Traitement AJAX du formulaire
            let sRequest = ajaxRequest(
                formAction,
                $(this).serialize() + '&docid=' + docId,
                'POST',
                'json'
            );
            sRequest.done(function (response) {
                // Destruction du popup
                $(button).popover('destroy');

                if (response) {
                    $publicationDate.html(response);
                    refreshPaperHistory(docId);
                } else {
                        alert(translate('Veuillez indiquer une date valide'));
                }
            });
            return false;
        });
    });
}

/** DOI regex pattern — must match the pattern used in savedoiAction PHP. */
const DOI_PATTERN = /^10\.\d{4,9}\/[-._;()/:A-Z0-9]+$/i;

/**
 * Validate a DOI string: non-empty and matching the expected format.
 *
 * Pure function — no DOM access, easy to unit-test.
 *
 * @param {string} value
 * @returns {{ valid: boolean, error: string }}
 */
function validateDoiInput(value) {
    if (!value) {
        return { valid: false, error: 'DOI cannot be empty.' };
    }
    if (!DOI_PATTERN.test(value)) {
        return { valid: false, error: 'Invalid DOI format. Expected: 10.XXXX/suffix' };
    }
    return { valid: true, error: '' };
}

/**
 * Update the DOI text displayed on the page after a successful save.
 *
 * Pure DOM function — no network access, easy to unit-test.
 *
 * @param {string} doi
 */
function updateDoiDisplay(doi) {
    const doiLink = document.getElementById('doi-link');
    if (doiLink) {
        // Use textContent to prevent any HTML injection from a server-provided DOI.
        doiLink.textContent = '\u00A0' + doi;
    }

    const paperDoiAnchor = document.querySelector('div.paper-doi a');
    if (paperDoiAnchor) {
        paperDoiAnchor.textContent = doi;
    }
}

/**
 * Load and display the DOI edit form in a popover, then handle submission.
 *
 * Fixes over the original implementation:
 *  - The `url` parameter is now forwarded to getCommunForm.
 *  - Empty DOI and format validation before sending the request.
 *  - Native fetch() replaces the jQuery ajaxRequest wrapper.
 *  - Event handler is attached to a fresh form element on every popover open,
 *    preventing duplicate handler stacking.
 *  - All server-provided HTML is sanitized before insertion.
 *  - Errors are surfaced to the user inside the popover.
 *
 * @param {HTMLElement} button
 * @param {string|number} docId
 * @param {string} [url]
 */
function getDoiForm(button, docId, url = JS_PREFIX_URL + 'administratepaper/doiform') {
    const jqxhr = getCommunForm(button, docId, url);
    if (jqxhr === false) {
        return;
    }

    // Wrap the jQuery JQXHR in a native Promise so the handler below is
    // jQuery-free and straightforward to reason about.
    Promise.resolve(jqxhr).then(function (formHtml) {
        // Replace the loading popover with the actual form.
        $(button).popover('destroy');
        openedPopover = null;

        const popoverParams = {
            placement: 'bottom',
            container: 'body',
            html: true,
            content: typeof sanitizeHTML === 'function' ? sanitizeHTML(formHtml) : formHtml,
        };
        $(button).popover(popoverParams).popover('show');
        let saveDoiUrl = JS_PREFIX_URL + 'administratepaper/savedoi';
        // Bootstrap 3 popover('show') is synchronous — the form is in the DOM now.
        const form = document.querySelector('form[action^="' + saveDoiUrl + '"]');
        if (!form) {
            return;
        }

        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            // Clear any previous inline error message.
            const existingError = form.querySelector('.doi-form-error');
            if (existingError) {
                existingError.remove();
            }

            const doiInput = form.querySelector('input[name="doi"]');
            const doiValue = doiInput ? doiInput.value.trim() : '';

            const validation = validateDoiInput(doiValue);
            if (!validation.valid) {
                const errEl = document.createElement('p');
                errEl.className = 'doi-form-error text-danger';
                errEl.textContent = validation.error;
                form.prepend(errEl);
                return;
            }

            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            try {
                const body = new URLSearchParams(new FormData(form));
                body.set('paperid', String(docId));

                const response = await fetch(saveDoiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body,
                });

                if (!response.ok) {
                    throw new Error('Server error ' + response.status);
                }

                const data = await response.json();

                if (!data.success) {
                    const errEl = document.createElement('p');
                    errEl.className = 'doi-form-error text-danger';
                    errEl.textContent = data.error || 'An error occurred.';
                    form.prepend(errEl);
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    return;
                }

                // Extract the real DOCID from the hidden form field. The form element
                // remains accessible in JS memory even after popover('destroy') removes
                // it from the DOM.
                const docidHidden = form.querySelector('input[name="docid"]');
                const actualDocId = docidHidden ? String(docidHidden.value) : String(docId);

                $(button).popover('destroy');

                // 1. Update the DOI link — build an <a> element via DOM API (XSS-safe).
                const doiLinkEl = document.getElementById('doi-link');
                if (doiLinkEl) {
                    const doiUrl = 'https://doi.org/' + data.doi;
                    const anchor = document.createElement('a');
                    anchor.rel = 'noopener noreferrer';
                    anchor.href = doiUrl;
                    anchor.textContent = doiUrl;
                    doiLinkEl.replaceChildren(anchor);
                }

                // 2. Replace the status badge with "Assigned" — built via DOM API.
                const doiStatusEl = document.getElementById('doi-status');
                if (doiStatusEl) {
                    const badgeLabel = typeof translate === 'function' ? translate('Assigned') : 'Assigned';
                    const badge = document.createElement('span');
                    badge.className = 'label label-primary';
                    badge.textContent = badgeLabel;
                    doiStatusEl.replaceChildren(badge, document.createTextNode('\u00A0'));
                }

                // 3. Build the "Cancel DOI" button and replace the trigger button.
                const cancelLabelText = typeof translate === 'function' ? translate('Annuler le DOI') : 'Cancel DOI';
                const cancelBtn = document.createElement('button');
                cancelBtn.id = 'removeDoi';
                cancelBtn.className = 'btn btn-default btn-sm popover-link';
                cancelBtn.style.marginLeft = '5px';
                cancelBtn.dataset.paperid = String(docId);
                cancelBtn.dataset.docid = actualDocId;

                const icon = document.createElement('span');
                icon.className = 'fa-solid fa-rotate-left';
                icon.style.marginRight = '5px';
                cancelBtn.appendChild(icon);
                cancelBtn.appendChild(document.createTextNode(cancelLabelText));
                cancelBtn.addEventListener('click', function () {
                    removeDoi(cancelBtn, docId, actualDocId, data.doi);
                });
                button.replaceWith(cancelBtn);

                // 4. Update other DOI references on the page (e.g. paper header).
                const paperDoiAnchor = document.querySelector('div.paper-doi a');
                if (paperDoiAnchor) {
                    paperDoiAnchor.textContent = data.doi;
                }
            } catch (err) {
                const errEl = document.createElement('p');
                errEl.className = 'doi-form-error text-danger';
                errEl.textContent = 'Network error. Please try again.';
                form.prepend(errEl);
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                console.error('[getDoiForm] Fetch error:', err);
            }
        });
    }).catch(function () {
        $(button).popover('destroy');
        openedPopover = null;
    });
}

function closeResult() {
    $('button').popover('destroy');
}

/**
 * Redirige vers le formulaire de l’invitation d’un relecteur
 * @param docid : l'id de document  à relire
 * @param uid : l'id de relecteur à reinviter
 */
function reinviteReviewer(docid, uid) {
    let $formId = $('#invitereviewer_form_' + docid);
    $formId.append(
        '<input id = "reinvite_uid" type="hidden" name="reinvite_uid" value="' +
            uid +
            '">'
    );
    $formId.submit();
}

/**
 *
 * @param docid
 * @returns {*}
 */
function refreshPaperHistory(docid) {
    let logs_container = $('#history .panel-body');
    logs_container.hide();
    logs_container.html(getLoader());
    logs_container.fadeIn();
    let refreshLogs = ajaxRequest(
        JS_PREFIX_URL + 'administratepaper/displaylogs',
        { docid: docid }
    );

    refreshLogs.done(function (logs) {
        $(logs_container).hide();
        $(logs_container).html(logs);
        $(logs_container).fadeIn();
    });

    return refreshLogs;
}

/**
 * edit attachment description : additional information
 * @param target
 */
function editAttachmentDescription(target) {
    let $submitButton = $('button[id^="submit-modal-review-formatting"]');
    $submitButton.prop('disabled', true);
    let selector = target.attr('data-target');
    let $helpBlock = $(selector)
        .closest('div' + selector)
        .find('span.help-block');

    let $checkBox = '<p style="margin-top: 5px;">';
    $checkBox += '<label for="no-attachments-checkbox">';
    $checkBox += translate('OK, continuer sans fichier(s) joint(s).');
    $checkBox += '</label>';
    $checkBox +=
        '<input type="checkbox" id="no-attachments-checkbox" style="vertical-align:middle;">';
    $checkBox += '</p>';

    if ($helpBlock.find('div.additional-description').length < 1) {
        let html = $helpBlock.html();
        html += '<div class="additional-description">';
        html += '<span class="text-danger">';
        html += '<strong>';
        html += translate(
            "Merci de penser à bien joindre les fichiers pour l'auteur ou ajouter un lien vers un site de téléchargement des fichiers."
        );
        html += '</strong>';
        html += $checkBox;
        html += '</span>';
        html += '</div>';
        $helpBlock.empty();
        $helpBlock.html(html);
    }

    $('#no-attachments-checkbox').on('change', function () {
        if ($(this).prop('checked')) {
            $submitButton.prop('disabled', false);
        } else {
            $submitButton.prop('disabled', true);
        }
    });
}

/**
 *
 * @param button
 * @param docId
 */

function getVersionEditingForm(button, docId) {
    let request = getCommunForm(
        button,
        docId,
        JS_PREFIX_URL + 'administratepaper/latestversioneditingform'
    );

    let popoverParams = {
        placement: 'bottom',
        container: 'body',
        html: true,
        content: getLoader(),
    };

    request.done(function (result) {
        // Destruction du popup de chargement
        $(button).popover('destroy');
        openedPopover = null;
        // Affichage du formulaire dans le popover
        popoverParams.content = result;
        $(button).popover(popoverParams).popover('show');

        let actionStr =
            JS_PREFIX_URL + 'administratepaper/savenewpostedversion';

        $('form[action^="' + actionStr + '"]').on('submit', function () {
            let $inProgress = $('#in-progress');
            // Traitement AJAX du formulaire
            let sRequest = ajaxRequest(
                actionStr,
                $(this).serialize() + '&docid=' + docId,
                'POST',
                'json'
            );

            popoverParams.content = getLoader;

            $inProgress.html(getLoader());

            sRequest.done(function (response) {
                $inProgress.html('');
                $(button).popover('destroy');

                let result = JSON.parse(response);

                if (result.version > 0) {
                    if (result.isDataRecordUpdated) {
                        location.reload();
                    } else {
                        $('#version-of-paper-' + docId).text(result.version);
                        refreshPaperHistory(docId);
                    }
                }

                sRequest.fail(function () {
                    alert(
                        '<span class="fas fa-exclamation-triangle fa-lg" style="margin-right: 5px"></span>' +
                            translate(
                                "Une erreur interne s'est produite, veuillez recommencer."
                            )
                    );
                });
            });

            return false;
        });
    });
}

/**
 * Request the cancellation of a pending DOI assignment.
 *
 * Sends a POST request to remove the paper from the DOI queue and clear its
 * DOI field, then reloads the page on success or displays an error on failure.
 *
 * @param {HTMLElement} button
 * @param {string|number} paperId
 * @param {string|number} docId
 * @param {string} doi
 */
function removeDoi(button, paperId, docId, doi) {
    const loader = document.getElementById('doi-status-loader');
    if (loader) {
        loader.innerHTML = typeof getLoader === 'function' ? getLoader() : '';
        loader.hidden = false;
    }

    fetch(JS_PREFIX_URL + 'administratepaper/ajaxrequestremovedoi', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: new URLSearchParams({ paperId: String(paperId), docId: String(docId), doi }),
    })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Server error ' + response.status);
            }
            return response.json();
        })
        .then(function (data) {
            if (data.success) {
                if (loader) {
                    loader.hidden = true;
                }

                // 1. Clear the DOI link.
                const doiLinkEl = document.getElementById('doi-link');
                if (doiLinkEl) {
                    doiLinkEl.replaceChildren();
                }

                // 2. Reset the status badge to "not-assigned".
                const doiStatusEl = document.getElementById('doi-status');
                if (doiStatusEl) {
                    const badgeLabel = typeof translate === 'function' ? translate('not-assigned') : 'Not Assigned';
                    const badge = document.createElement('span');
                    badge.className = 'label label-default';
                    badge.textContent = badgeLabel;
                    doiStatusEl.replaceChildren(badge, document.createTextNode('\u00A0'));
                }

                // 3. Replace the "Cancel DOI" button with the appropriate action button.
                const panel = document.getElementById('doi-panel');
                const assignMode = panel ? panel.dataset.doiAssignMode : '';
                const cancelBtn = document.getElementById('removeDoi');
                if (cancelBtn) {
                    if (assignMode !== 'disabled') {
                        // Auto / manual mode: rebuild "Demander un DOI".
                        // The click handler is wired via event delegation in request-doi.js.
                        const requestBtn = document.createElement('button');
                        requestBtn.id = 'requestNewDoi';
                        requestBtn.dataset.docid = String(docId);
                        requestBtn.className = 'btn btn-default btn-sm popover-link';
                        requestBtn.style.marginLeft = '5px';
                        const reqIcon = document.createElement('span');
                        reqIcon.className = 'fas fa-robot';
                        reqIcon.style.marginRight = '5px';
                        requestBtn.appendChild(reqIcon);
                        requestBtn.appendChild(document.createTextNode(
                            typeof translate === 'function' ? translate('Demander un DOI') : 'Request a DOI'
                        ));
                        cancelBtn.replaceWith(requestBtn);
                    } else {
                        // Disabled mode: rebuild "Add a DOI".
                        const addBtn = document.createElement('button');
                        addBtn.className = 'btn btn-default btn-sm popover-link edit-doi';
                        addBtn.style.marginLeft = '5px';
                        const addIcon = document.createElement('span');
                        addIcon.className = 'glyphicon glyphicon-cog';
                        addIcon.style.marginRight = '5px';
                        addBtn.appendChild(addIcon);
                        addBtn.appendChild(document.createTextNode(
                            typeof translate === 'function' ? translate('Ajouter un DOI') : 'Ajouter un DOI'
                        ));
                        addBtn.addEventListener('click', function () {
                            getDoiForm(addBtn, paperId);
                        });
                        cancelBtn.replaceWith(addBtn);
                    }
                }

                // 4. Clear the paper-doi anchor in the page header (if present).
                const paperDoiAnchor = document.querySelector('div.paper-doi a');
                if (paperDoiAnchor) {
                    paperDoiAnchor.textContent = '';
                }

                return;
            }
            if (loader) {
                loader.hidden = true;
            }
            const doiStatus = document.getElementById('doi-status');
            if (doiStatus) {
                const errEl = document.createElement('div');
                errEl.className = 'alert alert-danger alert-dismissible';
                errEl.setAttribute('role', 'alert');
                errEl.textContent = data.error || 'An error occurred.';
                doiStatus.prepend(errEl);
            }
        })
        .catch(function (err) {
            if (loader) {
                loader.hidden = true;
            }
            const doiStatus = document.getElementById('doi-status');
            if (doiStatus) {
                const errEl = document.createElement('div');
                errEl.className = 'alert alert-danger alert-dismissible';
                errEl.setAttribute('role', 'alert');
                errEl.textContent = 'Network error. Please try again.';
                doiStatus.prepend(errEl);
            }
            console.error('[removeDoi] Fetch error:', err);
        });
}

function removeCoAuthor(docId, uid, rvid) {
    let removeCoAuthor = ajaxRequest(
        JS_PREFIX_URL + 'administratepaper/ajaxrequestremovecoauthor',
        {
            docId: docId,
            uid: uid,
            rvid: rvid,
        }
    );
    removeCoAuthor.done(function (response) {
        location.reload();
    });
}

function getRevisionDeadlineForm(button, docId, commentId = null) {
    let request = getCommunForm(
        button,
        docId,
        JS_PREFIX_URL + 'administratepaper/revisiondeadlineform'
    );

    let popoverParams = {
        placement: 'bottom',
        container: 'body',
        html: true,
        content: getLoader(),
    };

    request.done(function (result) {
        // Destruction du popup de chargement
        $(button).popover('destroy');
        openedPopover = null;
        // Affichage du formulaire dans le popover
        popoverParams.content = result;
        $(button).popover(popoverParams).popover('show');
        let actionForm =
            JS_PREFIX_URL + 'administratepaper/updaterevisiondeadline';

        $('form[action^="' + actionForm + '"]').on('submit', function () {
            let $revisionDeadline = $('#revision-deadline');
            // Traitement AJAX du formulaire
            let sRequest = ajaxRequest(
                actionForm,
                $(this).serialize() + '&docid=' + docId + '&pcid=' + commentId,
                'POST',
                'json'
            );
            sRequest.done(function (response) {
                // Destruction du popup
                $(button).popover('destroy');

                if (response) {
                    $revisionDeadline.html(response);
                    refreshPaperHistory(docId);
                } else {
                        alert(translate('Veuillez indiquer une date valide'));
                }
            });
            return false;
        });
    });
}

function valide($target) {
    let $selector = $target.attr('data-target');
    let id = $selector.substring(1, $selector.length);

    if (isRequiredRevisionDeadline) {
        $('#submit-modal-' + id).prop('disabled', true);
    } else {
        $('#submit-modal-' + id).prop('disabled', false);
    }
}

// Expose pure helpers for unit testing only.
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { validateDoiInput, updateDoiDisplay, DOI_PATTERN };
}
