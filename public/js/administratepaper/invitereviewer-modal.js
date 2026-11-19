let $reviewer_type;
let $autocomplete;
let $tmp_user_form;
let $deadline_id;
let $email;
let $invite_this_reviewer_btn;
let $alert_existlogin;
let $firstName;
let $lastName;
let $homonym_users;
let userIndex;
let values;
let $new_user_button;
let $new_user;
let $required_tmp_user;
let $known_reviewers_body;
let clicks;
let $reviewerGuideline;
let $user_lang;
let $lastname_element;
let $firstname_element;
let $user_lang_element;
let canReplaceClass = false; // Le retour au choix des relecteurs n'impacte pas l'apparence du botton "inviter ce relecteur"

let $loading_container;

$(document).ready(function () {
    $('[data-toggle="tooltip"]').tooltip();

    $alert_existlogin = $('#alert_exist_login');
    $reviewer_type = $('#existing-reviewer');
    $autocomplete = $('#autocomplete');
    $tmp_user_form = $('#tmp-user-form');
    $deadline_id = $('#deadline-id');
    $email = $('#email');
    $firstName = $('#firstname');
    $lastName = $('#lastname');
    $invite_this_reviewer_btn = $('#next');
    $homonym_users = $('#homonym_users');
    $new_user_button = $('#new_user_button');
    $new_user = $('#new-user');
    $required_tmp_user = $('#required_tmp_user');
    $known_reviewers_body = $('#known-reviewers-body');
    $reviewerGuideline = $('#invitereviewer_guideline');
    $user_lang = $('#user_lang');
    $lastname_element = $('#lastname-element');
    $firstname_element = $('#firstname-element');
    $user_lang_element = $('#user_lang-element');
    $loading_container = $('#loading_container');

    // datatable init: known reviewers list
    dt_init('known-reviewers', reviewers);
    set_reviewer_type($reviewer_type.val());

    if (!uid) {
        step1();
    } else {
        let reviewer = allJsReviewers[uid];
        reviewer.id = uid;
        $('#step-2 .panel-title').html(translate('Réinviter ce relecteur'));
        setInvitationValues(reviewer, reviewer.type);
        step2();
    }

    $new_user_button.on('click', function () {
        $(this).hide();
        $('#show-known-reviewers').trigger('click');
        $required_tmp_user.show();
        $new_user.show();
    });

    // Modern autocomplete init (existing user selection)
    const autocompleteInstance = createUserAutocomplete({
        inputId: 'autocomplete',
        selectedUserIdField: null, // We handle selection differently
        selectButtonId: null,
        url: '/user/findcasusers',
        maxResults: 100,
        // reviewers with a pending or accepted invitation for this paper
        ignoreList:
            typeof ignore_list !== 'undefined' && Array.isArray(ignore_list)
                ? ignore_list
                : [],
        onSelectCallback: function (user) {
            $('#autocomplete').val('');
            setInvitationValues(
                {
                    id: user.id,
                    email: user.email,
                    full_name: user.full_name,
                    user_name: user.user_name || '',
                    label: user.label || user.full_name,
                    locale: user.locale,
                },
                2
            );
            step2();
        },
    });

    // Vérification de l'adresse mail
    $email.on('input propertychange', function () {
        // onpropertychange : IE < 9

        let withoutSpaces = $.trim($email.val());
        $email.val(withoutSpaces);

        resetStep1();
        showElements();

        $invite_this_reviewer_btn.show();

        if (isEmail($email.val())) {
            $loading_container.html(getLoader());
            $loading_container.show();

            // Vérifier si un utilisateur est déjà associé à ce mail
            $invite_this_reviewer_btn.prop('disabled', true);
            findUserByMail($email.val()).done(function (result) {
                values = Object.values(result);
                let keys = Object.keys(result);
                checkDuplicateUser(values, keys);
                $invite_this_reviewer_btn.prop('disabled', false);
                $loading_container.hide();
            });

            findUserByMail($email.val()).fail(function (jqXHR, textStatus) {
                ajaxAlertFail();
                console.log('FIND_USER_BY_MAIL: ' + textStatus);
            });
        } else {
            $alert_existlogin.hide();
        }
    });

    $lastName.on('input propertychange', function () {
        resetStep1();
    });

    $firstName.on('input propertychange', function () {
        resetStep1();
    });

    $user_lang.on('change', function () {
        resetStep1();
    });

    // Bouton "inviter ce relecteur...": Affichage des homonymes (clicks = 1) ou passer à l'écran 2 d'invitation (clicks = 2)

    clicks = 0;
    let timer = 100;
    let delay = 1;
    $invite_this_reviewer_btn
        .on('click', function () {
            timer = setTimeout(function () {
                clicks++;
                if (validateTmpFormInvitation()) {
                    if (clicks === 1) {
                        //if(findUserByMail())
                        checkHomonyms(values);
                        replaceClass(
                            $invite_this_reviewer_btn,
                            'btn-default',
                            'btn-success'
                        );
                    } else if (clicks === 2) {
                        validate_step1();
                    }
                } else {
                    clicks = 0;
                }
            }, delay);
        })
        .on('dblclick', function () {
            clearTimeout(timer);
        });

    // Bouton "retour": revient à l'écran 1 (sélection du relecteur)
    $('#back-button').click(function () {
        resetStep1();
        step1();
    });

    // Modification de la deadline de relecture
    $deadline_id.change(function () {
        let deadline = $(this).val();

        if (
            isISOdate(deadline) &&
            isValidDate(deadline) &&
            dateIsBetween(
                deadline,
                $(this).attr('attr-mindate'),
                $(this).attr('attr-maxdate')
            )
        ) {
            let msg = tinymce.get('body').getContent();
            msg = msg.replaceAll(
                /<span class="rating_deadline">(.*?)<\/span>/g,
                '<span class="rating_deadline">' +
                    getLocaleDate(deadline, {
                        language: locale,
                        country: locale,
                    }) +
                    '</span>'
            );
            tinymce.get('body').setContent(msg);
        }
    });
});

// send reviewer invitation
function submit() {
    let url = $('#invitation-form').url();
    let docid = url.param('docid');

    if (validate_step2()) {
        tinyMCE.triggerSave();

        $.ajax({
            url: $('#invitation-form').attr('action'),
            type: 'POST',
            data: $('form').serialize(),
            success: function () {
                // refresh reviewers list
                let reviewers_container = $('#reviewers');
                reviewers_container.hide();
                reviewers_container.html(getLoader());
                reviewers_container.fadeIn();
                $.ajax({
                    url: '/administratepaper/displayinvitations',
                    type: 'POST',
                    data: { docid: docid, partial: false },
                    success: function (reviewers) {
                        $(reviewers_container).hide();
                        $(reviewers_container).html(reviewers);
                        $(reviewers_container).fadeIn();
                    },
                });

                // refresh paper history
                let logs_container = $('#history .panel-body');
                logs_container.hide();
                logs_container.html(getLoader());
                logs_container.fadeIn();
                $.ajax({
                    url: '/administratepaper/displaylogs',
                    type: 'POST',
                    data: { docid: docid },
                    success: function (logs) {
                        $(logs_container).hide();
                        $(logs_container).html(logs);
                        $(logs_container).fadeIn();
                    },
                });
            },
        });
    }
}

function resetStep1() {
    $('#step-2 .panel-title').html(translate('Inviter ce relecteur'));
    clearErrors();
    clicks = 0;

    // Effacer les liste des homonymes
    $homonym_users.empty();

    //Cacher les alerts
    if (!$alert_existlogin.text()) {
        $alert_existlogin.hide();
    }

    replaceClass($invite_this_reviewer_btn, 'btn-success', 'btn-default');
    $invite_this_reviewer_btn.prop('disabled', false);
}

/**
 *Retourne les logins associés à une adresse email et les détails de chaque compte
 * @param email
 */
function findUserByMail(email) {
    return $.ajax({
        url: '/user/ajaxfindusersbymail',
        type: 'POST',
        dataType: 'json',
        data: { email: email },
    });
}

/**
 * Retourne tous les utilisateurs qui ont le même prénom et nom
 * @param lastName
 * @returns {*}
 */
function findUsers(lastName) {
    return $.ajax({
        url: '/user/findusersbyfirstnameandname',
        type: 'POST',
        dataType: 'json',
        data: { lastName: lastName },
    });
}

function set_reviewer_type(type) {
    $alert_existlogin.hide();
    type = Number(type);
    if (type === 0) {
        $homonym_users.hide();
    } else {
        $new_user.show();
        $homonym_users.show();
    }
}

// step1: reviewer selection
function step1() {
    $reviewerGuideline.show();
    $('#step-2').hide();
    $('#step-1').fadeIn();
}

// step 1 validation (new user)
function validate_step1() {
    let email = $email.val();
    let firstname = $('#firstname').val();
    let lastname = $('#lastname').val();
    let lang = $('#user_lang').val();

    let full_name = firstname ? firstname : '';
    full_name += lastname ? ' ' + lastname : '';

    let user = {
        id: null,
        email: email,
        full_name: full_name,
        locale: lang,
        firstname: firstname,
        lastname: lastname,
    };
    setInvitationValues(user, 3);
    step2();
}

// step 2: invitation e-mail
function step2() {
    // Erreurs step1
    clearErrors();
    $reviewerGuideline.hide();
    $('#step-1').hide();
    $('#step-2').fadeIn();
}

// step 2 validation
function validate_step2() {
    let errors = [];

    if (!$('#invitation-form').is(':visible')) {
        errors.push(translate('Veuillez choisir un relecteur'));
    }

    if (!isISOdate($deadline_id.val())) {
        errors.push(
            translate(
                'Veuillez saisir une date limite de relecture au format : AAAA-mm-jj'
            )
        );
    }

    if (!isValidDate($deadline_id.val())) {
        errors.push(translate("La date limite de relecture n'est pas valide"));
    }

    if (
        !dateIsBetween(
            $deadline_id.val(),
            $deadline_id.attr('attr-mindate'),
            $deadline_id.attr('attr-maxdate')
        )
    ) {
        let betweenMsg = translate(
            'La date limite de relecture doit être comprise entre'
        );
        betweenMsg += ' ';
        betweenMsg += $deadline_id.attr('attr-mindate');
        betweenMsg += ' ';
        betweenMsg += translate('et');
        betweenMsg += ' ';
        betweenMsg += $deadline_id.attr('attr-maxdate');

        errors.push(betweenMsg);
    }

    let body = tinymce.get('body').getContent();
    if (!body) {
        errors.push(
            translate('Veuillez saisir un message à destination du relecteur')
        );
    }
    if (!(body.indexOf('%%INVITATION_URL%%') >= 0)) {
        errors.push(
            translate(
                "Pensez à utiliser le tag %%INVITATION_URL%% pour que le relecteur puisse répondre à l'invitation"
            )
        );
    }

    if (errors.length) {
        show_errors(errors);
        return false;
    } else {
        return true;
    }
}

// invitation default values
// type=1: known reviewer, type=2: new user
function setInvitationValues(user, type) {
    // Selection de la langue du template

    let locale = user.locale !== siteLocale ? defaultLocale : user.locale;

    // Si la langue préférée de l'utilisateur n'existe pas dans les langues disponibles du site
    if (!(locale in available_languages)) {
        if (available_languages.length > 1 && 'en' in available_languages) {
            // Si le site propose plusieurs langues, dont l'anglais, on sélectionne l'anglais par défaut
            locale = 'en';
        } else {
            // Sinon, on sélectionne la première langue disponible
            for (let lang in available_languages) {
                locale = lang;
                break;
            }
        }
    }

    user.invitation_type = type;
    $('#reviewer').val(JSON.stringify(user));
    showReviewerWorkload(user);

    const requestType = applyRequestType(user);
    let tpl = invitationTemplate(requestType, type);
    let recipient = user.full_name + ' <' + user.email + '>';
    let subject = replaceTags(tpl.subject[locale], user, locale);
    let body = replaceTags(tpl.body[locale], user, locale, true);

    $('#recipient').val(recipient);
    $('#subject').val(subject);
    //$('#body').val(body);

    let paper_title;
    if ($.type(paper.title) == 'object') {
        if (paper.title[locale]) {
            paper_title = paper.title[locale];
        } else
            for (let i in paper.title) {
                paper_title = paper.title[i];
                break;
            }
    } else {
        paper_title = paper.title;
    }

    let tags = [
        {
            text: translate("Délai de réponse à l'invitation"),
            value: translateInvitationDeadline(
                review['invitation_deadline'],
                locale
            ),
        },
        {
            text: translate("URL de réponse à l'invitation"),
            value: '%%INVITATION_URL%%',
        },
        { text: translate('Code de la revue'), value: review['code'] },
        { text: translate('Nom de la revue'), value: review['name'] },
        { text: translate("Id de l'article"), value: paper.id.toString() },
        { text: translate("Titre de l'article"), value: paper_title },
        { text: translate('Auteur(s)'), value: allAuthors },
        {
            text: translate('Nom complet du destinataire'),
            value: user.full_name,
        },
        { text: translate('E-mail du destinataire'), value: user.email },
        {
            text: translate("Nom complet de l'expéditeur"),
            value: editor.full_name,
        },
        { text: translate("E-mail de l'expéditeur"), value: editor.email },
        {
            text: translate("La page de l'article sur Episciences"),
            value: '%%PAPER_URL%%',
        },
        {
            text: translate("La page de l'article sur l'archive ouverte"),
            value: '%%PAPER_REPO_URL%%',
        },
    ];
    if (user.user_name) {
        tags.push({
            text: translate('Identifiant du destinataire'),
            value: user.user_name,
        });
    }
    if (user.screen_name) {
        tags.push({
            text: translate("Nom d'affichage du destinataire"),
            value: user.screen_name,
        });
    }
    if (contributor) {
        if (contributor.fullname) {
            tags.push({
                text: translate('Nom complet du contributeur'),
                value: contributor.fullname,
            });
        }
        if (contributor.email) {
            tags.push({
                text: translate('E-mail du contributeur'),
                value: contributor.email,
            });
        }

        if (contributor.user_name) {
            tags.push({
                text: translate('Identifiant du contributeur'),
                value: contributor.user_name,
            });
        }
        if (contributor.screen_name) {
            tags.push({
                text: translate("Nom d'affichage du contributeur"),
                value: contributor.screen_name,
            });
        }
    }
    // The editor inserts the values of the tags as HTML
    tags = tags.map(tag => ({ ...tag, value: htmlEntities(tag.value) }));

    let options = {
        init_instance_callback: function (editor) {
            editor.setContent(nl2br(body));
        },
        menubar: false,
        height: 400,
        plugins: 'link code',
        external_plugins: {
            inserttag: '/js/tinymce/plugins/es_tags/plugin.min.js',
        },
        toolbar1:
            'bold italic underline | inserttag | undo redo | alignleft aligncenter alignright alignjustify | bullist numlist | link',
        tags: tags,
    };
    if (tinymce.get('body')) {
        tinymce.get('body').remove();
    }
    __initMCE('#body', undefined, options);
}

/**
 * replace all occurrences of a template tags by their real values
 * @param string
 * @param reviewer
 * @param locale
 * @param asHtml the template is HTML: the values are escaped
 * @returns {string}
 */
function replaceTags(string, reviewer, locale, asHtml = false) {
    const value = v => (asHtml ? htmlEntities(v) : v);
    let language = locale;
    let country = locale;
    let paper_title;

    if ($.type(paper.title) == 'object') {
        if (paper.title[locale]) {
            paper_title = paper.title[locale];
        } else
            for (let i in paper.title) {
                paper_title = paper.title[i];
                break;
            }
    } else {
        paper_title = paper.title;
    }

    string = string.replaceAll(
        '%%RATING_DEADLINE%%',
        value(getLocaleDate(review['rating_deadline'], { language, country }))
    );
    string = string.replaceAll(
        '%%INVITATION_DEADLINE%%',
        value(
            translateInvitationDeadline(review['invitation_deadline'], locale)
        )
    );
    string = string.replaceAll('%%REVIEW_CODE%%', value(review['code']));
    string = string.replaceAll('%%REVIEW_NAME%%', value(review['name']));
    // if we don't have a screen_name, we use the full_name
    string = reviewer.screen_name
        ? string.replaceAll(
              '%%RECIPIENT_SCREEN_NAME%%',
              value(reviewer.screen_name)
          )
        : string.replaceAll(
              '%%RECIPIENT_SCREEN_NAME%%',
              value(reviewer.full_name)
          );
    string = string.replaceAll(
        '%%RECIPIENT_USERNAME%%',
        value(reviewer.user_name)
    );
    string = string.replaceAll(
        '%%RECIPIENT_FULL_NAME%%',
        value(reviewer.full_name)
    );
    string = string.replaceAll('%%SENDER_FULL_NAME%%', value(editor.full_name));
    string = string.replaceAll('%%SENDER_EMAIL%%', value(editor.email));
    string = string.replaceAll('%%ARTICLE_ID%%', value(paper.id));
    string = string.replaceAll(
        '%%PERMANENT_ARTICLE_ID%%',
        value(paper.paperId)
    );
    string = string.replaceAll('%%ARTICLE_TITLE%%', value(paper_title));
    string = string.replaceAll(
        '%%CONTRIBUTOR_FULL_NAME%%',
        value(contributor.full_name)
    );
    string = string.replaceAll(
        '%%CONTRIBUTOR_EMAIL%%',
        value(contributor.email)
    );
    string = string.replaceAll('%%AUTHORS_NAMES%%', value(allAuthors));

    return string;
}

function show_errors(errors) {
    let html = '<div style="padding-left: 15px">';
    html +=
        '<div style="margin-bottom: 5px; color: red"><strong>' +
        translate('Erreurs :') +
        '</strong></div>';
    for (let i in errors) {
        html +=
            '<div style="margin-left: 10px; color: red"> * ' +
            errors[i] +
            '</div>';
    }
    html += '</div>';

    if (!$('.form-errors .errors').length) {
        $('.form-errors').append('<div class="errors">' + html + '</div>');
    } else {
        $('.form-errors .errors').html(html);
    }
}

function reviewers_init(self_users) {
    $('.dataTable.hover tr').each(function () {
        $(this).click(function () {
            let uid = $(this)
                .attr('id')
                .match(/(\d+)$/)[1];
            let reviewer = self_users[uid];
            reviewer.id = uid;
            setInvitationValues(reviewer, 1);
            step2();
        });
    });
}

function dt_init(self_id, self_reviewers) {
    // initialisation
    $('#' + self_id)
        .on('init.dt', function () {
            reviewers_init(self_reviewers);
        })
        .on('draw.dt', function () {
            reviewers_init(self_reviewers);
        })
        .dataTable({
            stateSave: true,
            dom: "<'dt-header row'<'left col-xs-6'l><'right col-xs-6'f>r>t<'dt-footer row'<'left col-xs-6'i><'right col-xs-6'p>>",
            pagingType: 'numbers',
            order: [
                [1, 'asc'],
                [0, 'asc'],
            ],
            autoWidth: true,
            language: {
                lengthMenu:
                    translate('Afficher') + ' _MENU_ ' + translate('lignes'),
                search: translate('Filtrer les relecteurs') + ' ',
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
}

/**
 * Crée un objet depuis un autre.
 * @param oUser
 * @returns {{email: *, full_name: string, locale: (*|jQuery), firstname: *, lastname: *, user_name: *}}
 */
function createUser(oUser) {
    let firstname = oUser.FIRSTNAME;
    let lastname = oUser.LASTNAME;
    let lang = $('#user_lang').val();
    let full_name = firstname ? firstname : '';
    full_name += lastname ? ' ' + lastname : '';
    return {
        email: oUser.EMAIL,
        full_name: full_name,
        locale: lang,
        firstname: firstname,
        lastname: lastname,
        user_name: oUser.USERNAME,
        id: oUser.UID,
    };
}

/**
 * Affiche tous les utilisateurs ayant le même prénom et nom lors de l'invitation d'un nouvel utilisateur
 * @param html
 */
function displayHomonymUsers(html) {
    $homonym_users.empty();
    $homonym_users.append(html);
    $homonym_users.show();
}

function displayDuplicateUsers(html, keys_length, existe_users) {
    $alert_existlogin.empty();

    if (keys_length === 0) {
        canReplaceClass = true;
    } else {
        canReplaceClass = false;
        $invite_this_reviewer_btn.hide();
    }

    $alert_existlogin.append(html);

    if (existe_users) {
        hideElements();
    }

    $alert_existlogin.show();
}

/**
 * Vérification et affichage des homonymes
 */

function checkHomonyms(values) {
    if (values !== undefined && values.length > 0) {
        setInvitationValues(createUser(values[userIndex]), 2);
        step2();
    } else {
        $loading_container.html(getLoader());
        $loading_container.show();

        // Détection d'homonymes(recherche par prénom et nom)
        $invite_this_reviewer_btn.prop('disabled', true);
        findUsers($lastName.val().trim()).done(function (result) {
            if (result.length === 0) {
                // Nouvel utilisateur (compte temporaire)
                validate_step1();
                $loading_container.hide();
                $invite_this_reviewer_btn.prop('disabled', false);
            } else {
                let ajax_data = {
                    post: JSON.stringify(result),
                    user_lang: $('#user_lang').val(),
                    paper_id: paper.id,
                    ignore_list: JSON.stringify(ignore_list),
                };
                let request = displayCcsdUsers(ajax_data);

                request.done(function (response) {
                    displayHomonymUsers(response);
                    $loading_container.hide();
                    $invite_this_reviewer_btn.prop('disabled', false);
                });

                request.fail(function (jqXHR, textStatus) {
                    ajaxAlertFail();
                    console.log('DSIPLAY_CCSD_USERS_FAIL:' + textStatus);
                });
            }
        });

        findUsers($lastName.val().trim()).fail(function (jqXHR, textStatus) {
            ajaxAlertFail();
            console.log('FIND_USERS_FAIL:' + textStatus);
        });
    }
}

/**
 * Valide le formulaire : inviter un nouveau relecteur
 * @returns {boolean}
 */

function validateTmpFormInvitation() {
    let errors = [];
    if (!$email.val() || !isEmail($email.val())) {
        errors.push(translate('Veuillez entrer une adresse e-mail valide'));
    }

    if (!$lastName.val().trim()) {
        if (errors.length > 0) {
            errors[0] = errors[0] + ' ' + translate('et un nom');
        } else {
            errors.push(translate('Veuillez entrer un nom'));
        }
    }

    if (errors.length) {
        show_errors(errors);
        return false;
    }
    return true;
}

/**
 * remplace la classe d'un element
 * @param element
 * @param old_name
 * @param new_name
 */
function replaceClass(element, old_name, new_name) {
    if (canReplaceClass && element.hasClass(old_name)) {
        element.removeClass(old_name);
        element.addClass(new_name);
    }
}

/**
 * Vérification et affichage des doublons
 * @param data
 * @param keys
 */
function checkDuplicateUser(data, keys) {
    let existe_users = false;
    let html = '';
    let json_values = JSON.stringify(data);
    let ajax_data = {
        post: json_values,
        user_lang: $('#user_lang').val(),
        paper_id: paper.id,
        ignore_list: JSON.stringify(ignore_list),
        is_search_with_mail: true,
    };

    // Alert doublon
    $alert_existlogin.empty();
    resetStep1();
    canReplaceClass = true;

    if (keys.length > 0) {
        let request = displayCcsdUsers(ajax_data);
        existe_users = true;
        request.done(function (response) {
            html += response;
            displayDuplicateUsers(html, keys.length, existe_users);
        });

        request.fail(function (jqXHR, textStatus) {
            ajaxAlertFail();
            console.log('DSIPLAY_CCSD_USERS_FAIL:' + textStatus);
        });
    }
}

/**
 *
 * @param data
 * @returns {jqXHR}
 */
function displayCcsdUsers(data) {
    return $.ajax({
        url: '/administratepaper/displayccsdusers',
        type: 'POST',
        dataType: 'html',
        data: data,
    });
}

/**
 *
 * @param uid
 * @returns {*}
 */
function findCasUser(uid) {
    return $.ajax({
        url: '/user/ajaxfindcasuser',
        type: 'POST',
        dataType: 'json',
        data: { uid: uid },
    });
}

function hideElements() {
    $lastname_element.hide();
    $firstname_element.hide();
    $user_lang_element.hide();
}

function showElements() {
    $lastname_element.show();
    $firstname_element.show();
    $user_lang_element.show();
}

function ajaxAlertFail() {
    $email.val('');
    let error = new Error();
    alert(
        translate("Une erreur interne s'est produite, veuillez recommencer.")
    );
    console.log(error.stack);
    $loading_container.hide();
    resetStep1();
    return false;
}

/**
 * What the reviewer is already asked in the journal (git #320), as a sentence; empty when nothing is known
 * @param {object|null|undefined} workload reviews_in_progress, pending_invitations, last_invitation_date, last_invitation_answer
 * @returns {string}
 */
function reviewerWorkloadMessage(workload) {
    if (!workload) {
        return '';
    }

    const answers = {
        pending: translate('sans réponse pour le moment'),
        accepted: translate('acceptée'),
        declined: translate('refusée'),
        cancelled: translate('annulée'),
        expired: translate('expirée'),
    };
    const parts = [];

    if (workload.reviews_in_progress > 0) {
        parts.push(
            translate('Relectures en cours : %d').replace(
                '%d',
                String(workload.reviews_in_progress)
            )
        );
    }

    if (workload.pending_invitations > 0) {
        parts.push(
            translate('Invitations en attente : %d').replace(
                '%d',
                String(workload.pending_invitations)
            )
        );
    }

    if (workload.last_invitation_date) {
        parts.push(
            translate('Dernière invitation : %s (%s)')
                .replace('%s', workload.last_invitation_date.substring(0, 10))
                .replace('%s', answers[workload.last_invitation_answer] || '')
        );
    }

    if (parts.length === 0) {
        return '';
    }

    return (
        translate('Sollicitations de ce relecteur dans la revue') +
        ' : ' +
        parts.join(' ; ') +
        '.'
    );
}

/**
 * Shows above the invitation message what the chosen person is already asked in the journal (git #320)
 * @param {object} user the invited person (id: UID, when they have an account)
 */
function showReviewerWorkload(user) {
    const alert = document.getElementById('reviewer-workload-alert');

    if (!alert) {
        return;
    }

    const workloads =
        typeof reviewer_workloads !== 'undefined' && reviewer_workloads
            ? reviewer_workloads
            : {};
    const message = reviewerWorkloadMessage(
        user && user.id ? workloads[user.id] : null
    );

    alert.textContent = message;
    alert.hidden = message === '';
}

function translateInvitationDeadline(str, locale) {
    let sStr = str.split(' ');
    return sStr[0] + ' ' + translate(sStr[1], locale);
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        resetStep1,
        findUserByMail,
        findUsers,
        set_reviewer_type,
        step1,
        validate_step1,
        step2,
        validate_step2,
        setInvitationValues,
        reviewerWorkloadMessage,
        showReviewerWorkload,
        replaceTags,
        show_errors,
        reviewers_init,
        dt_init,
        createUser,
        displayHomonymUsers,
        displayDuplicateUsers,
        checkHomonyms,
        validateTmpFormInvitation,
        replaceClass,
        checkDuplicateUser,
        displayCcsdUsers,
        findCasUser,
        hideElements,
        showElements,
        ajaxAlertFail,
        translateInvitationDeadline,
        submit,
        requestTypeSelect,
        applyRequestType,
        invitationTemplate,
    };
}

// Review request types (#319) ***********************************************************************************

/**
 * The request type select of the invitation form
 * @returns {HTMLSelectElement|null}
 */
function requestTypeSelect() {
    return document.querySelector(
        '#invitation-form select[name="request_type_id"]'
    );
}

/**
 * Disables the types of the pending or accepted requests of the reviewer, selects an available type,
 * then applies its default deadline
 * @param user the invited person
 * @returns {object|null} the selected request type (id, label, rating_deadline)
 */
function applyRequestType(user) {
    const select = requestTypeSelect();

    if (
        !select ||
        typeof request_types === 'undefined' ||
        !Array.isArray(request_types)
    ) {
        return null;
    }

    const busyByReviewer =
        typeof busy_request_types !== 'undefined' && busy_request_types
            ? busy_request_types
            : {};
    const busy = (user.id && busyByReviewer[user.id]) || [];
    let firstAvailable = null;

    for (const option of select.options) {
        option.disabled = busy.includes(Number(option.value));
        if (!option.disabled && firstAvailable === null) {
            firstAvailable = option.value;
        }
    }

    if (
        select.selectedOptions.length === 0 ||
        select.selectedOptions[0].disabled
    ) {
        if (firstAvailable !== null) {
            select.value = firstAvailable;
        }
    }

    const requestType =
        request_types.find(rt => String(rt.id) === select.value) || null;

    if (requestType) {
        review['rating_deadline'] = requestType.rating_deadline;
        $deadline_id.val(requestType.rating_deadline);
    }

    return requestType;
}

/**
 * Invitation template of the request type for the account state of the invited person
 * @param requestType selected request type, or null
 * @param accountState 1: known reviewer, 2: existing account, 3: no account
 */
function invitationTemplate(requestType, accountState) {
    const byType =
        requestType &&
        typeof templates_by_request_type !== 'undefined' &&
        templates_by_request_type
            ? templates_by_request_type[requestType.id]
            : null;

    return (byType && byType[accountState]) || templates[accountState];
}

document.addEventListener('DOMContentLoaded', () => {
    const select = requestTypeSelect();

    if (!select) {
        return;
    }

    select.addEventListener('change', () => {
        const reviewerField = document.getElementById('reviewer');

        if (!reviewerField || !reviewerField.value) {
            return;
        }

        const user = JSON.parse(reviewerField.value);
        setInvitationValues(user, user.invitation_type);
    });
});
