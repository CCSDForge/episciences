let $deadline_id;
let $reviewerGuideline;

$(document).ready(function () {
    $('[data-toggle="tooltip"]').tooltip();

    $deadline_id = $('#deadline-id');
    $reviewerGuideline = $('#invitereviewer_guideline');

    if (!uid) {
        step1();
    } else {
        let reviewer = allJsReviewers[uid];
        reviewer.id = uid;
        $('#step-2 .panel-title').text(translate('Réinviter ce relecteur'));
        setInvitationValues(reviewer, reviewer.type);
        step2();
    }

    // reviewers of the previous versions (git #1387)
    document.addEventListener('click', onReinviteClick);

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

// back to the choice of the reviewer (step 1): the title and the errors of step 2 are reset
function resetStep1() {
    $('#step-2 .panel-title').text(translate('Inviter ce relecteur'));
    clearErrors();
}

// step1: reviewer selection
function step1() {
    $reviewerGuideline.show();
    $('#step-2').hide();
    $('#step-1').fadeIn();
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
// type=1: known reviewer, type=2: new user, type=3: person without an account
// templateKey: template of the message, the one of the type by default ('reinvitation': reviewer of a previous
// version invited again, git #1387)
function setInvitationValues(user, type, templateKey = type) {
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
    // kept with the person, so that changing the request type keeps the template asked for (git #1387)
    if (templateKey !== type) {
        user.template_key = templateKey;
    } else {
        delete user.template_key;
    }
    $('#reviewer').val(JSON.stringify(user));
    showReviewerWorkload(user);

    const requestType = applyRequestType(user);
    // a template asked for explicitly (reviewer of a previous version invited again, git #1387) wins over the
    // template of the request type
    let tpl =
        templateKey !== type && templates[templateKey]
            ? templates[templateKey]
            : invitationTemplate(requestType, type);
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

/**
 * What the reviewer is already asked in the journal (git #320), one item per line; empty when nothing is known.
 * An item is a text, with the paper it is about when the logged user may see it (link before or after the text).
 * @param {object|null|undefined} workload as given by WorkloadPresenter::present()
 * @returns {{text: string, paper: (object|null), paperFirst: boolean}[]}
 */
function reviewerWorkloadItems(workload) {
    if (!workload) {
        return [];
    }

    const answers = {
        pending: translate('sans réponse pour le moment'),
        accepted: translate('acceptée'),
        declined: translate('refusée'),
        cancelled: translate('annulée'),
        expired: translate('expirée'),
    };
    const items = [];
    const count = (label, value) =>
        items.push({
            text: translate(label).replace('%d', String(value)),
            paper: null,
            paperFirst: false,
        });

    if (workload.reviews_in_progress > 0) {
        count('Relectures en cours : %d', workload.reviews_in_progress);
    }

    if (workload.pending_invitations > 0) {
        count('Invitations en attente : %d', workload.pending_invitations);
    }

    for (const paper of workload.papers || []) {
        const label =
            paper.kind === 'review'
                ? 'relecture à rendre le %s'
                : 'invitation envoyée le %s';
        items.push({
            text:
                ' (' +
                translate(label).replace('%s', paper.date_label || '') +
                ')',
            paper: paper,
            paperFirst: true,
        });
    }

    if (workload.other_papers > 0) {
        count('Autres articles : %d', workload.other_papers);
    }

    if (workload.last_invitation_date) {
        const text = translate('Dernière invitation : %s (%s)')
            .replace(
                '%s',
                workload.last_invitation_date_label ||
                    workload.last_invitation_date.substring(0, 10)
            )
            .replace('%s', answers[workload.last_invitation_answer] || '');
        const paper = workload.last_invitation_paper || null;
        items.push({
            text: paper ? text + ' – ' : text,
            paper: paper,
            paperFirst: false,
        });
    }

    return items;
}

/**
 * Link to a paper of the workload, in a new tab: the editor keeps the invitation page
 * @param {{docid: number, url: string}} paper
 * @returns {HTMLAnchorElement}
 */
function workloadPaperLink(paper) {
    const link = document.createElement('a');
    link.href = paper.url;
    link.target = '_blank';
    link.rel = 'noopener';
    link.textContent = '#' + paper.docid;
    const newTab = document.createElement('span');
    newTab.className = 'sr-only';
    newTab.textContent = ' (' + translate('nouvel onglet') + ')';
    link.appendChild(newTab);

    return link;
}

/**
 * Fills the alert with a title and the list of the items, as text (no HTML is parsed)
 * @param {HTMLElement} alert
 * @param {{text: string, paper: (object|null), paperFirst: boolean}[]} items
 */
function renderReviewerWorkload(alert, items) {
    alert.replaceChildren();
    alert.hidden = items.length === 0;

    if (items.length === 0) {
        return;
    }

    const title = document.createElement('strong');
    title.textContent = translate(
        'Sollicitations de ce relecteur dans la revue'
    );
    const list = document.createElement('ul');
    list.className = 'list-unstyled';
    list.style.margin = '0';

    for (const item of items) {
        const line = document.createElement('li');
        const text = document.createTextNode(item.text);

        if (item.paper && item.paperFirst) {
            line.append(workloadPaperLink(item.paper), text);
        } else if (item.paper) {
            line.append(text, workloadPaperLink(item.paper));
        } else {
            line.append(text);
        }

        list.appendChild(line);
    }

    alert.append(title, list);
}

/**
 * SHA-256 hash, in hexadecimal, of an e-mail address in lower case: the key of the people invited without an account
 * (Workload::splitByAccount())
 * @param {string} email
 * @returns {Promise<string>}
 */
async function emailHash(email) {
    const data = new TextEncoder().encode(email.trim().toLowerCase());
    const digest = await globalThis.crypto.subtle.digest('SHA-256', data);

    return Array.from(new Uint8Array(digest))
        .map(byte => byte.toString(16).padStart(2, '0'))
        .join('');
}

/**
 * Shows above the invitation message what the chosen person is already asked in the journal (git #320): a reviewer
 * with an account is found by UID, else by e-mail address among the people invited without an account
 * @param {object} user the invited person (id: UID, when they have an account; email)
 * @returns {Promise<void>} settled once the alert is up to date
 */
function showReviewerWorkload(user) {
    const alert = document.getElementById('reviewer-workload-alert');

    if (!alert) {
        return Promise.resolve();
    }

    const render = workload =>
        renderReviewerWorkload(alert, reviewerWorkloadItems(workload));
    const byUid =
        typeof reviewer_workloads !== 'undefined' && reviewer_workloads
            ? reviewer_workloads
            : {};
    const byEmail =
        typeof reviewer_workloads_by_email !== 'undefined' &&
        reviewer_workloads_by_email
            ? reviewer_workloads_by_email
            : {};
    const uid = user && user.id ? String(user.id) : '';
    const email = user && user.email ? String(user.email) : '';

    // the latest choice wins over an answer still being computed for a previous one
    alert.dataset.for = 'uid:' + uid + '|email:' + email;

    if (uid !== '' && byUid[uid]) {
        render(byUid[uid]);
        return Promise.resolve();
    }

    // without an account, or an account with no request yet: the person may have been invited with this address
    // before, without an account
    render(null);

    if (
        email === '' ||
        Object.keys(byEmail).length === 0 ||
        !globalThis.crypto ||
        !globalThis.crypto.subtle
    ) {
        return Promise.resolve();
    }

    const expected = alert.dataset.for;

    return emailHash(email)
        .then(hash => {
            if (alert.dataset.for === expected) {
                render(byEmail[hash]);
            }
        })
        .catch(() => render(null));
}

/**
 * Invites again a reviewer of a previous version (git #1387): the message is the reassignment template, the account
 * state of the person (reviewer of the journal, or without an account) is kept, and the previous version is posted
 * with them so that the message links to it.
 * @param {string} key key of the row of the previous reviewers table
 * @returns {boolean} whether the invitation step is shown
 */
function reinvitePreviousReviewer(key) {
    const rows =
        typeof previous_reviewers !== 'undefined' && previous_reviewers
            ? previous_reviewers
            : {};
    const row = rows[key];

    if (!row) {
        return false;
    }

    const user = { ...row };
    setInvitationValues(user, row.invitation_type, 'reinvitation');
    step2();
    // after step2(): going back to the reviewer selection writes the usual title again
    document
        .querySelectorAll('#step-2 .panel-title')
        .forEach(
            title => (title.textContent = translate('Réinviter ce relecteur'))
        );

    return true;
}

/**
 * @param {MouseEvent} event
 */
function onReinviteClick(event) {
    const button =
        event.target instanceof Element
            ? event.target.closest('[data-reinvite]')
            : null;

    if (button) {
        reinvitePreviousReviewer(button.getAttribute('data-reinvite'));
    }
}

function translateInvitationDeadline(str, locale) {
    let sStr = str.split(' ');
    return sStr[0] + ' ' + translate(sStr[1], locale);
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        resetStep1,
        step1,
        step2,
        validate_step2,
        setInvitationValues,
        reinvitePreviousReviewer,
        onReinviteClick,
        reviewerWorkloadItems,
        workloadPaperLink,
        renderReviewerWorkload,
        showReviewerWorkload,
        emailHash,
        replaceTags,
        show_errors,
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
        setInvitationValues(
            user,
            user.invitation_type,
            user.template_key || user.invitation_type
        );
    });
});
