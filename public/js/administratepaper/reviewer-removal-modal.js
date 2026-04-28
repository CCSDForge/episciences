$(document).ready(function () {
    if (isUninvited !== '1') {
        var locale = reviewer.locale;
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

        //initialisation du tinyMCE
        if ($.type(paper.title) == 'object') {
            if (paper.title[locale]) paper_title = paper.title[locale];
            else
                for (let i in paper.title) {
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
    }
});

function submit() {
    let url = $('#reviewer-removal-form').url();
    let docid = url.param('docid');

    tinyMCE.triggerSave();
    $.ajax({
        url: $('#reviewer-removal-form').attr('action'),
        type: 'POST',
        datatype: 'json',
        data: $('#modal-box form').serialize(),
        success: function (response) {
            console.log(response);
            response = JSON.parse(response);
            if (response.status == 1) {
                $('#modal-box').modal('hide');

                let container = $('#reviewers');
                container.hide();
                container.html(getLoader());
                container.fadeIn();

                // refresh reviewers list
                $.ajax({
                    url: '/administratepaper/displayinvitations',
                    type: 'POST',
                    data: { docid: docid, partial: false },
                    success: function (reviewers) {
                        $(container).hide();
                        $(container).html(reviewers);
                        $(container).fadeIn();
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
            } else {
                $('#modal-box .errors-message').html('* ' + response.message);
                $('#modal-box .errors').fadeIn();
            }
        },
    });
}
