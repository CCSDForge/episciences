$(document).ready(function () {
    set_editor($('#editor').val());

    $('#editor').change(function () {
        set_editor($(this).val());
    });
});

function set_editor(uid) {
    if (!uid) {
        return false;
    }

    // Get template
    var tpl = template;
    var user = editors[uid];

    // Selection de la langue du template
    // Si la langue préférée de l'utilisateur n'existe pas dans les langues disponibles du site
    var locale = user.locale;
    if (!(locale in available_languages)) {
        if (available_languages.length > 1 && 'en' in available_languages) {
            // Si le site propose plusieurs langues, dont l'anglais, on sélectionne l'anglais par défaut
            locale = 'en';
        } else {
            // Sinon, on sélectionne la première langue disponible
            for (i in available_languages) {
                locale = i;
                break;
            }
        }
    }

    var recipient = user.full_name + ' <' + user.email + '>';
    var subject = replaceTags(tpl.subject[locale], user, locale);
    var body = replaceTags(tpl.body[locale], user, locale);

    $('#recipient').val(recipient);
    $('#subject').val(subject);
    $('#body').val(body);

    if ($.type(paper.title) == 'object') {
        if (paper.title[locale]) paper_title = paper.title[locale];
        else
            for (i in paper.title) {
                paper_title = paper.title[i];
                break;
            }
    } else {
        paper_title = paper.title;
    }

    var tags = [
        { text: translate('Code de la revue'), value: review['code'] },
        { text: translate('Nom de la revue'), value: review['name'] },
        { text: translate("Id de l'article"), value: paper.id.toString() },
        { text: translate("Url de l'article"), value: paper.url },
        { text: translate("Titre de l'article"), value: paper_title },
        {
            text: translate('Nom complet du destinataire'),
            value: user.full_name,
        },
        { text: translate('E-mail du destinataire'), value: user.email },
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

    var options = {
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
        convert_urls: false,
        tags: tags,
    };
    if (tinymce.get('body')) {
        tinymce.get('body').remove();
    }
    __initMCE('#body', undefined, options);
}

//Remplacement des tags du template par leur valeur réelle
function replaceTags(string, reviewer, locale) {
    if ($.type(paper.title) == 'object') {
        if (paper.title[locale]) paper_title = paper.title[locale];
        else
            for (i in paper.title) {
                paper_title = paper.title[i];
                break;
            }
    } else {
        paper_title = paper.title;
    }

    string = string.replace('%%REVIEW_CODE%%', review['code']);
    string = string.replace('%%REVIEW_NAME%%', review['name']);
    // if we don't have a screen_name, we use the full_name
    string = reviewer.screen_name
        ? string.replace('%%RECIPIENT_SCREEN_NAME%%', reviewer.screen_name)
        : string.replace('%%RECIPIENT_SCREEN_NAME%%', reviewer.full_name);
    string = string.replace('%%RECIPIENT_USERNAME%%', reviewer.user_name);
    string = string.replace('%%RECIPIENT_FULL_NAME%%', reviewer.full_name);
    string = string.replace('%%ARTICLE_ID%%', paper.id);
    string = string.replace('%%ARTICLE_TITLE%%', paper_title);
    string = string.replace('%%PAPER_URL%%', paper.url);

    return string;
}

function submit() {
    var url = $('#modal-box form').url();
    var docid = url.param('docid');

    tinyMCE.triggerSave();

    $.ajax({
        url: $('#paper-reassignment-form').attr('action'),
        type: 'POST',
        datatype: 'json',
        data: $('#modal-box form').serialize(),
        success: function (response) {
            $('#modal-box').modal('hide');
            $('#reassign-button').remove();

            var container = $('#editors');
            container.hide();
            container.html(getLoader());
            container.fadeIn();

            // Refresh de l'affichage des rédacteurs
            $.ajax({
                url: '/administratepaper/displayeditors',
                type: 'POST',
                data: { docid: docid, partial: false },
                success: function (editors) {
                    $(container).hide();
                    $(container).html(editors);
                    $(container).fadeIn();
                },
            });
        },
    });

    return;
}
