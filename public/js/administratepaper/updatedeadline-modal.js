$(document).ready(function () {
    var locale = reviewer.locale;
    if (!(locale in available_languages)) {
        if (available_languages.length > 1 && 'en' in available_languages) {
            // Si le site propose plusieurs langues, dont l'anglais, on sélectionne l'anglais par défaut
            locale = 'en';
        } else {
            // Sinon, on sélectionne la première langue disponible
            for (var i in available_languages) {
                locale = i;
                break;
            }
        }
    }

    var paper_title = '';
    if ($.type(paper.title) == 'object') {
        if (paper.title[locale]) {
            paper_title = paper.title[locale];
        } else
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
        { text: translate("Titre de l'article"), value: paper_title },
        {
            text: translate('Nom complet du relecteur'),
            value: reviewer.full_name,
        },
        { text: translate('E-mail du relecteur'), value: reviewer.email },
        {
            text: translate('Nom complet du rédacteur'),
            value: editor.full_name,
        },
        { text: translate('E-mail du rédacteur'), value: editor.email },
    ];
    if (reviewer.user_name) {
        tags.push({
            text: translate('Identifiant du destinataire'),
            value: reviewer.user_name,
        });
    }
    if (reviewer.screen_name) {
        tags.push({
            text: translate("Nom d'affichage du destinataire"),
            value: reviewer.screen_name,
        });
    }
    var options = {
        //init_instance_callback : function(editor) {editor.setContent(nl2br(body));},
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

    // Modification de la deadline de relecture
    $('#deadline-id').change(function () {
        var deadline = $(this).val();
        if (
            isISOdate(deadline) &&
            isValidDate(deadline) &&
            dateIsBetween(
                deadline,
                $(this).attr('attr-mindate'),
                $(this).attr('attr-maxdate')
            )
        ) {
            var msg = tinymce.get('body').getContent();
            msg = msg.replace(
                /<span class="updated_deadline">(.*?)<\/span>/,
                '<span class="updated_deadline">' +
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

function submit() {
    //valider formulaire (date de relecture correcte, supérieure à l'ancienne, sujet et corps du message présents)

    tinyMCE.triggerSave();
    $.ajax({
        url: $('#deadline-form').attr('action'),
        type: 'POST',
        datatype: 'json',
        data: $('#modal-box form').serialize(),
        success: function (response) {
            response = JSON.parse(response);

            if (response.status === 1) {
                refreshPaperHistory(response.docId);
                $('#modal-box').modal('hide');
                $('#invitation-' + response.id)
                    .find('.rating_deadline')
                    .html(response.deadline)
                    .fadeIn();
            } else {
                $('#modal-box')
                    .find('.errors-message')
                    .html('* ' + response.message);
                $('#modal-box').find('.errors').fadeIn();
            }
        },
    });
}
