const TYPE_UNANSWERED_INVITATION = 0;
const TYPE_BEFORE_REVIEWING_DEADLINE = 1;
const TYPE_AFTER_REVIEWING_DEADLINE = 2;
const TYPE_BEFORE_REVISION_DEADLINE = 3;
const TYPE_AFTER_REVISION_DEADLINE = 4;
const TYPE_NOT_ENOUGH_REVIEWERS = 5;
const TYPE_ARTICLE_BLOCKED_IN_ACCEPTED_STATE = 6;
const TYPE_ARTICLES_BLOCKED_IN_SUBMITTED_STATE = 7;
const TYPE_ARTICLES_BLOCKED_IN_REVIEWED_STATE = 8;

function deleteReminder(btn) {
    bootbox.setDefaults({ locale: locale });
    bootbox.confirm(translate('Êtes-vous sûr ?'), function (result) {
        if (result) {
            let container = $(btn).parent('.reminder');
            $(container).html(getLoader());

            $.ajax({
                url: $(btn).attr('href'),
                type: 'POST',
                success: function (response) {
                    // Suppression du séparateur suivant si le reminder était le premier de la liste
                    if (!$(container).prevAll('.reminder').length) {
                        $(container).next('hr').remove();
                    }
                    // Suppression du séparateur précédent si le reminder était le dernier de la liste
                    if (!$(container).nextAll('.reminder').length) {
                        $(container).prev('hr').remove();
                    }
                    // Suppression du séparateur précédent si le reminder était directement entouré de 2 séparateurs
                    if (
                        $(container).prev('hr').length &&
                        $(btn).parent('.reminder').next('hr').length
                    ) {
                        $(container).prev('hr').remove();
                    }
                    // Suppression du reminder
                    $(container).remove();
                },
                error: function (response) {
                    bootbox.alert(
                        translate('La suppression a échoué : ') +
                            translate(response)
                    );
                },
            });
        }
    });
}

function submit() {
    if (validate()) {
        tinyMCE.triggerSave();
        $.ajax({
            url: $('#reminder_form').attr('action'),
            type: 'POST',
            datatype: 'json',
            data: $('#reminder_form').serialize(),
            success: function (response) {
                $('#modal-box').modal('hide');
                let container = $('#reminders');
                $(container).hide();
                $(container).html(getLoader());
                $(container).fadeIn();

                let request = $.ajax({
                    url: JS_PREFIX_URL + 'administratemail/refreshreminders',
                    type: 'POST',
                });
                request.done(function (result) {
                    $(container).hide();
                    $(container).html(result);
                    $(container).fadeIn();
                });

                request.fail(function (jqXHR, textStatus) {
                    console.log('REFRESH_REMINDERS_FAILED: ' + textStatus);
                });
            },
        });
    } else {
        return false;
    }
}

function validate() {
    let errors = [];

    if (!$('#delay').val()) {
        errors.push(translate('Le champ Délai est obligatoire'));
    } else if (!$.isNumeric($('#delay').val())) {
        errors.push(translate('Le délai doit être une valeur numérique'));
    } else if (!isPositiveInteger($('#delay').val())) {
        errors.push(translate('Le délai doit être un entier positif'));
    }

    let type = $('#type').val();
    let recipient = $('#recipient').val();

    if (in_array(recipient, Object.keys(templates[type])) === -1) {
        // not found
        errors.push(
            translate(
                "Le champ Destinataire n'est pas valable pour ce type de rappel"
            )
        );
    }

    /*
    for (i in langs) {
        if (!$('#'+i+'_tpl_name').val()) {
            errors.push(translate("Le champ Nom du template est obligatoire dans toutes les langues"));
            break;
        }
    }
    */

    if (errors.length) {
        let html = '<div class="col-md-offset-3" style="padding-left: 15px">';
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

        if (!$('.modal-body .errors').length) {
            $('.modal-body').append('<div class="errors">' + html + '</div>');
        } else {
            $('.modal-body .errors').html(html);
        }
        return false;
    } else {
        return true;
    }
}

function default_template(lang) {
    $('#' + lang + '_custom_template').val(0);

    $('#' + lang + '_custom_subject-element').hide();
    $('#' + lang + '_default_subject-element').show();

    $('#' + lang + '_custom_body-element').hide();
    $('#' + lang + '_default_body-element').show();
}

function custom_template(lang) {
    $('#' + lang + '_custom_template').val(1);

    $('#' + lang + '_default_subject-element').hide();
    $('#' + lang + '_default_body-element').hide();

    setTemplateValues(lang);

    $('#' + lang + '_custom_body-element').show();
    $('#' + lang + '_custom_subject-element').show();
}

function setTemplateValues(lang) {
    let body_value = '';
    let subject_value = '';

    let type = $('#type').val();
    let recipient = $('#recipient').val();

    // Valeurs par défaut
    if (templates[type][recipient].body[lang]) {
        body_value = templates[type][recipient].body[lang];
        subject_value = templates[type][recipient].subject[lang];
    }
    $('#' + lang + '_default_body').html(body_value);
    $('#' + lang + '_default_subject').val(subject_value);

    // Modification d'un reminder existant
    if (edit && reminder.custom[lang] == 1) {
        if (reminder.subject[lang]) {
            subject_value = reminder.subject[lang];
        }
        if (reminder.body[lang]) {
            body_value = reminder.body[lang];
        }
    }

    if (tinyMCE.get(lang + '_custom_body')) {
        tinyMCE.get(lang + '_custom_body').setContent(nl2br(body_value));
    } else {
        $('#' + lang + '_custom_body').html(nl2br(body_value));
    }
    $('#' + lang + '_custom_subject').val(subject_value);
}

function setRecipient(recipient) {
    setReminder($('#type').val(), recipient);
}

function setReminderType(type, recipient) {
    if (!recipient) {
        recipient = $('#recipient').val();
    }
    setReminder(type, recipient);
}

function setReminder(type, recipient) {
    if (type in templates && recipient in templates[type]) {
        for (let lang in langs) {
            if (edit && reminder.custom[lang] == 1) {
                custom_template(lang);
            } else {
                setTemplateValues(lang);
                default_template(lang);
            }
        }
    }
    if ($('#recipient').val() != recipient) {
        $('#recipient').val(recipient);
    }
}

function buildReminderMessage(reminderType) {
    let type = parseInt(reminderType);
    let message = translate('Saisir un nombre de jours');

    message += ' (';

    if (type === TYPE_UNANSWERED_INVITATION) {
        message += translate(
            'un rappel automatique pour une absence de réponse à une invitation de relecture peut être envoyé x jours après l’invitation (définie dans Gérer la revue/Revue/Paramètres)'
        );
    } else if (type === TYPE_BEFORE_REVIEWING_DEADLINE) {
        message += translate(
            'un rappel automatique de la date limite pour une relecture peut être envoyé x jours avant cette date (définie dans Gérer la revue/Revue/Paramètres)'
        );
    } else if (type === TYPE_AFTER_REVIEWING_DEADLINE) {
        message += translate(
            'un rappel automatique de la date limite pour une relecture peut être envoyé x jours après cette date (définie dans Gérer la revue/Revue/Paramètres)'
        );
    } else if (type === TYPE_BEFORE_REVISION_DEADLINE) {
        message += translate(
            'un rappel automatique de la date limite de modification peut être envoyé x jours avant cette date (définie dans la demande de modification)'
        );
    } else if (type === TYPE_AFTER_REVISION_DEADLINE) {
        message += translate(
            'un rappel automatique de la date limite de modification peut être envoyé x jours après cette date (définie dans la demande de modification)'
        );
    } else if (type === TYPE_NOT_ENOUGH_REVIEWERS) {
        message += translate(
            "si il y a pas suffisamment d'invitations acceptées, un rappel automatique peut être envoyé x jours après la date de la dernière invitation, si des invitations ont été envoyées. Sinon, après la date d'assignation de l'article au rédacteur. On n'envoie pas de relances, si on n'a pas spécifié de nombre minimum de relecteurs (définie dans Gérer la revue/Revue/Paramètres)"
        );
    } else if (type === TYPE_ARTICLE_BLOCKED_IN_ACCEPTED_STATE) {
        message += translate(
            "la relance sera envoyée x jours après la date d'acceptation de l'article"
        );
    } else if (
        type === TYPE_ARTICLES_BLOCKED_IN_SUBMITTED_STATE ||
        type === TYPE_ARTICLES_BLOCKED_IN_REVIEWED_STATE
    ) {
        message += translate(
            'un rappel sera déclenché lorsqu’une soumission restera dans cet état au-delà de x jours, où x est la valeur entrée dans le champ'
        );
    }

    message += ').';
    return message;
}
