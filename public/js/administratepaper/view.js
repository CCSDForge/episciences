var openedPopover = null;

function getTags() {
    let paper_title = '';

    if ($.type(paper.title) === 'object') {
        if (paper.title[locale]) {
            paper_title = paper.title[locale];
        }
        else {
            for (let i in paper.title) {
                paper_title = paper.title[i];
                break;
            }
        }
    } else {
        paper_title = paper.title;
    }

    let tags = [{text: translate('Code de la revue'), value: review['code']},
        {text: translate('Nom de la revue'), value: review['name']},
        {text: translate("Id de l'article"), value: paper.id},
        {text: translate("Titre de l'article"), value: paper_title}];

    if (paper_ratings) {
        tags.push({text: translate('Rapports de relecture'), value: paper_ratings});
    }

    if (author) {
        if (author.fullname) {
            tags.push({text: translate("Nom complet du contributeur"), value: author.fullname});
        }
        if (author.email) {
            tags.push({text: translate("E-mail du contributeur"), value: author.email});
        }

        if (author.user_name) {
            tags.push({text: translate("Identifiant du contributeur"), value: author.user_name});
        }
        if (author.screen_name) {
            tags.push({text: translate("Nom d'affichage du contributeur"), value: author.screen_name});
        }
    }

    if (sender) {
        if (sender.fullname) {
            tags.push({text: translate("Nom complet de l'expéditeur"), value: sender.fullname});
        }
        if (sender.email) {
            tags.push({text: translate("E-mail de l'expéditeur"), value: sender.email});
        }
        if (sender.screen_name) {
            tags.push({text: translate("Nom d'affichage de l'expéditeur"), value: sender.screen_name});
        }
    }

    return tags;
}

$(document).ready(function () {
    // Désactivation de la possibilité de mettre à jour des métadonnées pour les version temporaires d'un article.
    $('#update_metadata').prop('disabled', true);

    if (paper.repository != 0) {
        $('#update_metadata').prop('disabled', false);
    }
    
    let options = {
        convert_urls: false,
        menubar: false,
        tags: getTags(),
        height: 400,
        plugins: "link image code fullscreen table textcolor",
        external_plugins: {"inserttag": "/js/tinymce/plugins/es_tags/plugin.min.js"},
        toolbar1: "bold italic underline | inserttag | undo redo | alignleft aligncenter alignright alignjustify | bullist numlist | link image | code "
    };

    __initMCE(".full_mce", undefined, options);

    $('#confirmNewVersion').on('click', function (e) {
        if (!$('#commentNewVersion').tinymce().getContent()) {
            alert(translate('Veuillez renseigner le champ "Modifications à apporter"'));
            e.preventDefault();
        }
    });

    $('.submit-modal').click(function () {
        $(this).closest('.modal-content').find('form').submit();
    });

    // Initialisation du menu des rédacteurs
    $('#editors').find('.editor .popover-link').each(function () {
        $(this).on('click', function () {
            getUserMenu(this);
        });
    });

    // Initialisation du menu de contributeur
    $('#contributor').find('.contributor .popover-link').each(function () {
        $(this).on('click', function () {
            getUserMenu(this);
        });
    });

    // Initialisation du menu des préparateurs de copie
    $('#copy-editors').find('.copy-editor .popover-link').each(function () {
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
    popover_button.popover({
        html: true,
        placement: 'bottom',
        content: function () {
            return history_popover_content.html();
        }
    }).on('shown.bs.popover', function () {

        // datepickers init
        let history_filter_start = $('#history-filter-start');
        let history_filter_end = $('#history-filter-end');
        history_filter_start.datepicker();
        history_filter_end.datepicker();
        $('.history-filters .datepicker-button').click(function () {
            $(this).next().datepicker('show');
        });

        // default values

        let default_start = history_popover_content.find('input[name="history-filter-start"]').val();
        let default_end = history_popover_content.find('input[name="history-filter-end"]').val();
        history_filter_start.val(default_start);
        history_filter_end.val(default_end);

        // popover buttons init
        $('.popover .cancel').click(function () {
            history_popover_content.find('input[name="history-filter-start"]').val('');
            history_popover_content.find('input[name="history-filter-end"]').val('');
            filterLogs('', '');
            popover_button.popover('hide');
        });
        $('.popover .submit').click(function () {
            history_popover_content.find('input[name="history-filter-start"]').val(history_filter_start.val());
            history_popover_content.find('input[name="history-filter-end"]').val(history_filter_end.val());
            filterLogs(history_filter_start.val(), history_filter_end.val());
            popover_button.popover('hide');
        });
    });


    // cc
    /*
    $('#cc-element').find('label').click(function() {

        var $form = $(this).closest('form');
        var $contacts_container = $form.next('.contacts_container');

        $form.hide();
        $contacts_container.show();
        $contacts_container.html(getLoader());

        $.ajax({
            url: '/administratemail/getcontacts?target=cc',
            type: 'POST',
            data: {ajax: true},
            success: function (content) {
                $contacts_container.html(content);
            }
        });
    });
    */

    // update deadline
    $('#majorrevisiondeadline-id, #minorrevisiondeadline-id').on('change', (function () {
        let locale = (author) ? author.langueid : 'en';
        let deadline = $(this).val();
        let attrName = $(this).attr('name');
        let name = attrName.substring(0, attrName.length - 8) + 'message'; // 8: length (deadline)

        $.map(available_languages, function (val, index) {
            let firstLanguage = index ;

            if (index === locale) {
                return false;
            }

            locale = firstLanguage;
            return false;
        });


        if (isISOdate(deadline) && isValidDate(deadline)) {
            let body = getObjectNameFromTinyMce(name); // object
            updateDeadlineTag(body, 'revision_deadline', deadline, locale);
        }
    }));
});

// filter logs 
function filterLogs(from, to) {
    if (from || to) {
        $('.history-datepicker-button').css('border', '1px solid #ff9d00');
    } else {
        $('.history-datepicker-button').css('border', '1px solid #ccc');
    }
    $('.history-logs div.log-entry').show().filter(function () {
        return (from && Date.parse($(this).data('date')) < Date.parse(from)) || (to && Date.parse($(this).data('date')) > Date.parse(to));
    }).hide();

}

// search logs (paper history)
function searchLogs() {
    let input = $('.history-search').val();

    if (input) {
        $('.history-search').css('border', '1px solid #ff9d00');
    } else {
        $('.history-search').css('border', '1px solid #ccc');
    }

    let re = new RegExp(input, "i"); // "i" means it's case-insensitive
    $('.history-logs div.log-entry').show().filter(function () {
        return !re.test($(this).text());
    }).hide();
}


function getReviewerMenu(button) {
    let docid = $(button).data('docid');
    let uid = $(button).data('uid');
    let byUid = $(button).data('by_uid');
    let aid = $(button).data('aid');
    let tmp = $(button).data('tmp');
    let status = $(button).data('status');
    let rating = $(button).data('rating');
    let isMyPaper = JSON.parse($(button).data('is_my_paper'));
    let isEditable = JSON.parse($(button).data('is_editable'));

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
        content += '<a href="/user/view/userid/' + uid + '" target="_blank">';
        content += '<span class="glyphicon glyphicon-user" style="margin-right: 5px"></span> ' + translate('Voir le profil');
        content += '</a>';
        content += '</li>';
    }

    content += '<li>';
    content += '<a class="modal-opener" href="/administratemail/send/recipient/' + uid + '/paper/' + docid;
    if (tmp) content += '/tmp/1';
    content += '" ';
    content += 'data-width="50%" ';
    content += 'title="' + translate("Contacter un relecteur") + '">';
    content += '<span class="glyphicon glyphicon-envelope" style="margin-right: 5px"></span> ' + translate('Contacter ce relecteur');
    content += '</a>';
    content += '</li>';

    if (status === 'expired') {

        let href = '#';

        content += '<li>';
        content += '<a href="' + href + '" ';
        content += 'onclick = "reinviteReviewer(' + docid + ',' + uid + ')" '
        content += 'title="' + translate("Réinviter ce relecteur") + '">';
        content += '<span class="glyphicon glyphicon-repeat" style="margin-right: 5px"></span> ' + translate('Réinviter ce relecteur') + '</a></li>';
    }

    if (isEditable && (status === 'active' || status === 'pending')) {

        content += '<li>';
        content += '<a class="modal-opener" href="/administratepaper/updatedeadline/aid/' + aid + '" ';
        content += 'data-width="50%" ';
        content += 'data-callback="submit" ';
        content += 'title="' + translate("Modification de la date limite de rendu de relecture") + '">';
        content += '<span class="glyphicon glyphicon-calendar" style="margin-right: 5px"></span> ' + translate('Modifier la date limite de rendu de la relecture') + '</a></li>';

    }

    if (status === 'pending' || ((status === 'active' || status === 'uninvited') && rating === 0)) {
        let href = '/administratepaper/removereviewer/aid/' + aid;

        if (status === 'uninvited') {
            href += '/status/' + status;
        }

        content += '<li>';
        content += '<a class="modal-opener" href="' + href + '" ';
        content += 'data-width="50%" ';
        content += 'data-callback="submit" ';
        content += 'title="' + translate("Retirer un relecteur") + '">';
        content += '<span class="glyphicon glyphicon-remove" style="margin-right: 5px"></span> ' + translate('Retirer ce relecteur') + '</a></li>';
    }

    if (isEditable && (status === 'active' || status === 'uninvited') && (rating === 0 || rating === 1)) {

        content += '<li>';
        content += '<a href="/paper/rating?id=' + docid + '&reviewer_uid=' + uid + '&byUid=' + byUid + '"  ';
        content += 'data-callback="submit"';
        content += 'title="' + translate("Remplir l'évaluation pour le compte de ce relecteur") + '">';
        content += '<span class="glyphicon glyphicon-edit" style="margin-right: 5px"></span> ' + translate('Télécharger le rapport du relecteur') + '</a></li>';

    }
    if (!isMyPaper && isEditable && (status === 'active' || status === 'uninvited') && rating === 2) {
        content += '<li>';
        content += '<a href="/administratepaper/refreshrating/id/' + docid + '/reviewer_uid/' + uid + '"  ';
        content += 'data-callback="submit"';
        content += 'title="' + translate("Le relecteur et vous-même pourrez modifier la relecture") + '">';
        content += '<span class="glyphicon glyphicon-refresh" style="margin-right: 5px"></span> ' + translate('Autoriser la modification de la relecture') + '</a></li>';

    }

    //content += '<li><a href="#"><span class="glyphicon glyphicon-remove" style="margin-right: 5px"></span> ' + translate('Supprimer ce relecteur')+'</a></li>';
    content += '</ul>';

    $(button).popover({
        'placement': 'bottom',
        'html': true,
        'content': content
    }).popover('show');
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
        case 'copy-editors' :
            modalTitle = 'Contacter un préparateur de copie';
            userTitle = 'Contacter ce préparateur de copie';
            break;
        case 'contributor' :
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
    content += '<a href="/user/view/userid/' + uid + '" target="_blank">';
    content += '<span class="glyphicon glyphicon-user" style="margin-right: 5px"></span> ' + translate('Voir le profil');
    content += '</a>';
    content += '</li>';

    content += '<li>';
    content += '<a class="modal-opener" href="/administratemail/send/recipient/' + uid + '/paper/' + docid + '" ';
    content += 'data-width="50%" ';
    content += 'title="' + translate(modalTitle) + '">';
    content += '<span class="glyphicon glyphicon-envelope" style="margin-right: 5px"></span> ' + translate(userTitle);
    content += '</a>';
    content += '</li>';

    content += '</ul>';

    $(button).popover({
        'placement': 'bottom',
        'html': true,
        'content': content
    }).popover('show');
}

function showForm(name) {
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

function updateMetaData(button, docId) {
    let $recordLoading = $("#record-loading");
    $recordLoading.html(getLoader());
    $recordLoading.show();

    $(button).unbind(); // Remove a previously-attached event handler from the elements

    let post = $.ajax({
        type: "POST",
        url: "/administratepaper/updaterecorddata",
        data: {docid: docId}
    });

    post.done(function (result) {
        let obj_result = JSON.parse(result);

        $recordLoading.hide();
        alert(obj_result.message);

        if (!('error' in obj_result) && obj_result.affectedRows !== 0) {
            location.reload();
        }

    });
}

function getDoiForm(button, docid) {
    // Configuration du popup
    let placement = 'bottom';

    // Destruction des anciens popups
    $('button').popover('destroy');

    // Toggle : est-ce qu'on ouvre ou est-ce qu'on ferme le popup ?
    if (openedPopover && openedPopover == docid) {
        openedPopover = null;
        return false;
    } else {
        openedPopover = docid;
    }

    // Récupération du formulaire
    let request = $.ajax({
        type: "POST",
        url: "/administratepaper/doiform",
        data: {docid: docid}
    });

    $(button).popover({
        'placement': placement,
        'container': 'body',
        'html': true,
        'content': getLoader()
    }).popover('show');

    request.done(function (result) {

        // Destruction du popup de chargement
        $(button).popover('destroy');
        openedPopover = null;

        // Affichage du formulaire dans le popover
        $(button).popover({
            'placement': placement,
            'container': 'body',
            'html': true,
            'content': result
        }).popover('show');

        $('form[action^="/administratepaper/savedoi"]').on('submit', function () {

            let doiContainer = $(button).closest('.paper-doi-value');
            //var editorsContainer = $(button).closest('tr').find('.editors');

            // Traitement AJAX du formulaire
            $.ajax({
                url: '/administratepaper/savedoi',
                type: 'POST',
                datatype: 'json',
                data: $(this).serialize() + "&docid=" + docid,
                success: function (result) {

                    // Destruction du popup
                    $(button).popover('destroy');

                    $(doiContainer).hide();
                    $(doiContainer).html(result);
                    $(doiContainer).fadeIn();
                    location.reload(); //prise en charge des changements
                }
            });
            return false;
        });
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
function reinviteReviewer(docid, uid){
    let $formId = $('#invitereviewer_form_' + docid);
    $formId.append('<input id = "reinvite_uid" type="hidden" name="reinvite_uid" value="' + uid + '">');
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
    let refreshLogs = ajaxRequest('/administratepaper/displaylogs', {docid: docid});

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
    let $validateButton = $('#submit-modal-review-formatting-submitted');
    $validateButton.prop('disabled', true);
    let selector = target.attr('data-target');
    let $helpBlock = $(selector).closest('div' + selector).find('span.help-block');

    let $checkBox = '<p style="margin-top: 5px;">';
    $checkBox += '<label for="no-attachments-checkbox">';
    $checkBox += translate('OK, continuer sans fichier(s) joint(s).');
    $checkBox += '</label>';
    $checkBox += '<input type="checkbox" id="no-attachments-checkbox" style="vertical-align:middle;">';
    $checkBox += '</p>';

    if ($helpBlock.find('div.additional-description').length < 1) {
        let html = $helpBlock.html();
        html += '<div class="additional-description">';
        html += '<span class="text-danger">';
        html += '<strong>';
        html += translate("Merci de penser à bien joindre les fichiers pour l'auteur ou ajouter un lien vers un site de téléchargement des fichiers.");
        html += '</strong>';
        html += $checkBox;
        html += '</span>';
        html += '</div>';
        $helpBlock.empty();
        $helpBlock.html(html);
    }

    $("#no-attachments-checkbox").on('change', function () {

        if ($(this).prop('checked')) {
            $validateButton.prop('disabled', false);
        } else {
            $validateButton.prop('disabled', true);
        }
    });

}