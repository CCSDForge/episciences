var $reviewer_type;
var $autocomplete;
var $tmp_user_form;
var $deadline_id;
var $email;
var $invite_this_reviewer_btn;
var $alert_existlogin;
var $firstName;
var $lastName;
var $homonym_users;
var userIndex;
var values;
var $new_user_button;
var $new_user;
var $required_tmp_user;
var $known_reviewers_body;
var clicks;
var $reviewerGuideline;
var $user_lang;
var $lastname_element;
var $firstname_element;
var $user_lang_element;
var canReplaceClass = false; // Le retour au choix des relecteurs n'impacte pas l'apparence du botton "inviter ce relecteur"

var $loading_container;

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
    $new_user = $("#new-user");
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
        var reviewer = allJsReviewers[uid];
        reviewer.id = uid;
        $('#step-2 .panel-title').html(translate('Réinviter ce relecteur'));
        setInvitationValues(reviewer, reviewer.type);
        step2();
    }

    $new_user_button.on('click', function () {
        $(this).hide();
        $("#show-known-reviewers").trigger('click');
        $required_tmp_user.show();
        $new_user.show();
    });

    // autocomplete init (existing user selection)
    var cache = {};

    $autocomplete.autocomplete({
        html: true,
        source: function (request, response) {
            var term = request.term;
            if (term in cache) {
                response(cache[term]);
                return;
            }
            if (ignore_list) {
                request.ignore_list = ignore_list;
            }
            $.getJSON("/user/findcasusers", request, function (data) {
                cache[term] = data;
                response(data);
            });
        },
        select: function (event, ui) {
            $(this).val('');
            setInvitationValues(ui.item, 2);
            step2();
            return false;
        }
    });

    // Autocomplete: au focus, on affiche les résultats en cache
    $autocomplete.focus(function () {
        $(this).autocomplete("search", this.value);
    });

    // Vérification de l'adresse mail
    $email.on('input propertychange', function () { // onpropertychange : IE < 9

        var withoutSpaces = $.trim($email.val());
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
                var keys = Object.keys(result);
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
    var timer = 100;
    var delay = 1;
    $invite_this_reviewer_btn
        .on("click", function () {
            timer = setTimeout(function () {
                clicks++;
                if (validateTmpFormInvitation()) {
                    if (clicks === 1) {
                        //if(findUserByMail())
                        checkHomonyms(values);
                        replaceClass($invite_this_reviewer_btn, 'btn-default', 'btn-success');
                    } else if (clicks === 2) {
                        validate_step1();
                    }
                } else {
                    clicks = 0;
                }
            }, delay);
        })
        .on("dblclick", function () {
            clearTimeout(timer);
        });

    // Bouton "retour": revient à l'écran 1 (sélection du relecteur)
    $('#back-button').click(function () {
        resetStep1();
        step1();
    });

    // Modification de la deadline de relecture
    $deadline_id.change(function () {
        var deadline = $(this).val();

        if (isISOdate(deadline) &&
            isValidDate(deadline) &&
            dateIsBetween(deadline, $(this).attr("attr-mindate"), $(this).attr("attr-maxdate"))) {
            var msg = tinymce.get('body').getContent();
            msg = msg.replace(/<span class="rating_deadline">(.*?)<\/span>/, '<span class="rating_deadline">' + getLocaleDate(deadline, {language: locale, country: locale}) + '</span>');
            tinymce.get('body').setContent(msg);
        }
    });
});

// send reviewer invitation
function submit() {
    var url = $('#invitation-form').url();
    var docid = url.param('docid');

    if (validate_step2()) {

        tinyMCE.triggerSave();

        $.ajax({
            url: $('#invitation-form').attr('action'),
            type: 'POST',
            datatype: 'json',
            data: $('form').serialize(),
            success: function () {

                // refresh reviewers list
                var reviewers_container = $('#reviewers');
                reviewers_container.hide();
                reviewers_container.html(getLoader());
                reviewers_container.fadeIn();
                $.ajax({
                    url: "/administratepaper/displayinvitations",
                    type: "POST",
                    data: {docid: docid, partial: false},
                    success: function (reviewers) {
                        $(reviewers_container).hide();
                        $(reviewers_container).html(reviewers);
                        $(reviewers_container).fadeIn();
                    }
                });

                // refresh paper history
                var logs_container = $('#history .panel-body');
                logs_container.hide();
                logs_container.html(getLoader());
                logs_container.fadeIn();
                $.ajax({
                    url: "/administratepaper/displaylogs",
                    type: "POST",
                    data: {docid: docid},
                    success: function (logs) {
                        $(logs_container).hide();
                        $(logs_container).html(logs);
                        $(logs_container).fadeIn();
                    }
                });

            }
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
        data: {email: email}
    });
}

/**
 * Retourne tous les utilisateurs qui ont le même prénom et nom
 * @param firstName
 * @param name
 * @returns {*}
 */
function findUsers(firstName, name) {
    return $.ajax({
        url: '/user/findusersbyfirstnameandname',
        type: 'POST',
        dataType: 'json',
        data: {firstname: firstName, name: name}
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
    var email = $email.val();
    var firstname = $('#firstname').val();
    var lastname = $('#lastname').val();
    var lang = $('#user_lang').val();

    var full_name = (firstname) ? firstname : '';
    full_name += (lastname) ? ' ' + lastname : '';

    var user = {
        id: null,
        email: email,
        full_name: full_name,
        locale: lang,
        firstname: firstname,
        lastname: lastname
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
    var errors = [];

    if (!$('#invitation-form').is(':visible')) {
        errors.push(translate('Veuillez choisir un relecteur'));
    }

    if (!isISOdate($deadline_id.val())) {
        errors.push(translate('Veuillez saisir une date limite de relecture au format : AAAA-mm-jj'));
    }

    if (!isValidDate($deadline_id.val())) {
        errors.push(translate("La date limite de relecture n'est pas valide"));
    }

    if (!dateIsBetween($deadline_id.val(), $deadline_id.attr("attr-mindate"), $deadline_id.attr("attr-maxdate"))) {
        errors.push(translate("La date limite de relecture doit être comprise entre le ") + $deadline_id.attr("attr-mindate") + ' et le ' + $deadline_id.attr("attr-maxdate"));
    }

    var body = tinymce.get('body').getContent();
    if (!body) {
        errors.push(translate('Veuillez saisir un message à destination du relecteur'));
    }
    if (!(body.indexOf("%%INVITATION_URL%%") >= 0)) {
        errors.push(translate("Pensez à utiliser le tag %%INVITATION_URL%% pour que le relecteur puisse répondre à l'invitation"));
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
    // Si la langue préférée de l'utilisateur n'existe pas dans les langues disponibles du site
    var locale = user.locale;
    if (!(locale in available_languages)) {
        if (available_languages.length > 1 && 'en' in available_languages) {
            // Si le site propose plusieurs langues, dont l'anglais, on sélectionne l'anglais par défaut
            locale = 'en';
        } else {
            // Sinon, on sélectionne la première langue disponible
            for (var lang in available_languages) {
                locale = lang;
                break;
            }
        }
    }

    user.invitation_type = type;
    $('#reviewer').val(JSON.stringify(user));

    var tpl = templates[type];
    var recipient = user.full_name + ' <' + user.email + '>';
    var subject = replaceTags(tpl.subject[locale], user, locale);
    var body = replaceTags(tpl.body[locale], user, locale);


    $('#recipient').val(recipient);
    $('#subject').val(subject);
    //$('#body').val(body);

    var paper_title;
    if ($.type(paper.title) == 'object') {
        if (paper.title[locale]) {
            paper_title = paper.title[locale];
        }
        else for (var i in paper.title) {
            paper_title = paper.title[i];
            break;
        }
    } else {
        paper_title = paper.title;
    }

    var tags = [{text: translate("Délai de réponse à l'invitation"), value: translateInvitationDeadline(review['invitation_deadline'], locale)},
        {text: translate("URL de réponse à l'invitation"), value: '%%INVITATION_URL%%'},
        {text: translate('Code de la revue'), value: review['code']},
        {text: translate('Nom de la revue'), value: review['name']},
        {text: translate("Id de l'article"), value: paper.id.toString()},
        {text: translate("Titre de l'article"), value: paper_title},
        {text: translate("Les noms des auteurs"), value: allAuthors},
        {text: translate('Nom complet du destinataire'), value: user.full_name},
        {text: translate('E-mail du destinataire'), value: user.email},
        {text: translate("Nom complet de l'expéditeur"), value: editor.full_name},
        {text: translate("E-mail de l'expéditeur"), value: editor.email},
        {text: translate("La page de l'article sur Episciences"), value: '%%PAPER_URL%%'},
        {text: translate("La page de l'article sur l'archive ouverte"), value: '%%PAPER_REPO_URL%%'},
    ];
    if (user.user_name) {
        tags.push({text: translate('Identifiant du destinataire'), value: user.user_name});
    }
    if (user.screen_name) {
        tags.push({text: translate("Nom d'affichage du destinataire"), value: user.screen_name});
    }
    if (contributor) {
        if (contributor.fullname) {
            tags.push({text: translate("Nom complet du contributeur"), value: contributor.fullname});
        }
        if (contributor.email) {
            tags.push({text: translate("E-mail du contributeur"), value: contributor.email});
        }

        if (contributor.user_name) {
            tags.push({text: translate("Identifiant du contributeur"), value: contributor.user_name});
        }
        if (contributor.screen_name) {
            tags.push({text: translate("Nom d'affichage du contributeur"), value: contributor.screen_name});
        }
    }


    var options = {
        init_instance_callback: function (editor) {
            editor.setContent(nl2br(body));
        },
        menubar: false,
        height: 400,
        plugins: "link code",
        external_plugins: {"inserttag": "/js/tinymce/plugins/es_tags/plugin.min.js"},
        toolbar1: "bold italic underline | inserttag | undo redo | alignleft aligncenter alignright alignjustify | bullist numlist | link",
        tags: tags
    };
    if (tinymce.get('body')) {
        tinymce.get('body').remove();
    }
    __initMCE("#body", undefined, options);

}


// replace template tags with their real value
function replaceTags(string, reviewer, locale) {

    let language = locale;
    let country = locale;
    let paper_title;

    if ($.type(paper.title) == 'object') {
        if (paper.title[locale]) {
            paper_title = paper.title[locale];
        } else for (var i in paper.title) {
            paper_title = paper.title[i];
            break;
        }
    } else {
        paper_title = paper.title;
    }

    string = string.replace("%%RATING_DEADLINE%%", getLocaleDate(review['rating_deadline'], {language, country}));
    string = string.replace("%%INVITATION_DEADLINE%%", translateInvitationDeadline(review['invitation_deadline'], locale));
    string = string.replace("%%REVIEW_CODE%%", review['code']);
    string = string.replace("%%REVIEW_NAME%%", review['name']);
    // if we don't have a screen_name, we use the full_name
    string = (reviewer.screen_name) ? string.replace("%%RECIPIENT_SCREEN_NAME%%", reviewer.screen_name) : string.replace("%%RECIPIENT_SCREEN_NAME%%", reviewer.full_name);
    string = string.replace("%%RECIPIENT_USERNAME%%", reviewer.user_name);
    string = string.replace("%%RECIPIENT_FULL_NAME%%", reviewer.full_name);
    string = string.replace("%%SENDER_FULL_NAME%%", editor.full_name);
    string = string.replace("%%SENDER_EMAIL%%", editor.email);
    string = string.replace("%%ARTICLE_ID%%", paper.id);
    string = string.replace("%%ARTICLE_TITLE%%", paper_title);
    string = string.replace("%%CONTRIBUTOR_FULL_NAME%%", contributor.full_name);
    string = string.replace("%%CONTRIBUTOR_EMAIL%%", contributor.email);
    string = string.replace("%%AUTHORS_NAMES%%", allAuthors);

    return string;
}

function show_errors(errors) {
    var html = '<div style="padding-left: 15px">';
    html += '<div style="margin-bottom: 5px; color: red"><strong>' + translate('Erreurs :') + '</strong></div>';
    for (var i in errors) {
        html += '<div style="margin-left: 10px; color: red"> * ' + errors[i] + '</div>';
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
            var uid = $(this).attr('id').match(/(\d+)$/)[1];
            var reviewer = self_users[uid];
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
            "dom": "<'dt-header row'<'left col-xs-6'l><'right col-xs-6'f>r>t<'dt-footer row'<'left col-xs-6'i><'right col-xs-6'p>>",
            "pagingType": "numbers",
            // "aaSorting": [[ 2, "asc" ]],
            "autoWidth": true,
            "language": {
                "lengthMenu": translate("Afficher") + " _MENU_ " + translate("lignes"),
                "search": translate("Filtrer les relecteurs") + " ",
                "zeroRecords": translate("Aucun résultat"),
                "info": translate("Lignes") + " _START_ " + translate("à") + " _END_, " + translate("sur") + " _TOTAL_ ",
                "infoEmpty": translate("Aucun résultat affiché"),
                "infoFiltered": "(" + translate("filtrés sur les") + " _MAX_)",
                "paginate": {"sPrevious": "", "sNext": ""},
            }
        });

}

/**
 * Crée un objet depuis un autre.
 * @param oUser
 * @returns {{email: *, full_name: string, locale: (*|jQuery), firstname: *, lastname: *, user_name: *}}
 */
function createUser(oUser) {
    var firstname = oUser.FIRSTNAME;
    var lastname = oUser.LASTNAME;
    var lang = $('#user_lang').val();
    var full_name = (firstname) ? firstname : '';
    full_name += (lastname) ? ' ' + lastname : '';
    return {
        email: oUser.EMAIL,
        full_name: full_name,
        locale: lang,
        firstname: firstname,
        lastname: lastname,
        user_name: oUser.USERNAME,
        id: oUser.UID
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
        findUsers($firstName.val().trim(), $lastName.val().trim()).done(function (result) {
            if (result.length === 0) { // Nouvel utilisateur (compte temporaire)
                validate_step1();
                $loading_container.hide();
                $invite_this_reviewer_btn.prop('disabled', false);
            } else {
                var ajax_data = {post: JSON.stringify(result), user_lang: $('#user_lang').val(), paper_id: paper.id, ignore_list: JSON.stringify(ignore_list)};
                var request = displayCcsdUsers(ajax_data);

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

        findUsers($firstName.val().trim(), $lastName.val().trim()).fail(function (jqXHR, textStatus) {
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
    var errors = [];
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
    var existe_users = false;
    var html = '';
    var json_values = JSON.stringify(data);
    var ajax_data = {post: json_values, user_lang: $('#user_lang').val(), paper_id: paper.id, ignore_list: JSON.stringify(ignore_list), is_search_with_mail: true};

    // Alert doublon
    $alert_existlogin.empty();
    resetStep1();
    canReplaceClass = true;

    if (keys.length > 0) {
        var request = displayCcsdUsers(ajax_data);
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
        data: data
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
        data: {uid: uid}
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
    var error = new Error();
    alert(translate("Une erreur interne s'est produite, veuillez recommencer."));
    console.log(error.stack);
    $loading_container.hide();
    resetStep1();
    return false;
}

function translateInvitationDeadline(str, locale){
    let sStr = str.split(' ');
    return sStr[0] + ' ' + translate(sStr[1], locale);
}
