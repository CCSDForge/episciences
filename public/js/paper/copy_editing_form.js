let paperCommentId;
$(function () {
    $('[id^=replyFormBtn_]').on('click', function () {
        paperCommentId = $(this).attr('id').match(/\d+/)[0];
        // Charger ce fichier en ce moment et initialisation de la variable paperCommentId
        $.getScript('/js/library/es.fileupload.js').fail(function () {
            console.log('loading failed: /js/library/es.fileupload.js');
        });
    });

    $('[id^=ce_cancel_]').on('click', function (evt) {
        evt.preventDefault();
        $('#replyForm_' + paperCommentId).hide();
        $('#replyFormBtn_' + paperCommentId).show();
        //Supprimer les fichiers attachés du serveur , si on valide pas la réponse
        deleteAllAttachedFiles();
    });

    $('[id^=ce_reply_]').on('click', function () {
        let parentFormId = $(this).parents('form:first').attr('id');
        let params = [
            {
                name: $(this).attr('id'),
                value: true,
            },
        ];

        //adding post parameters
        $('#' + parentFormId).submit(function () {
            $(this).append(
                $.map(params, function (param) {
                    return $('<input>', {
                        type: 'hidden',
                        name: param.name,
                        value: param.value,
                    });
                })
            );
        });
    });

    function deleteAllAttachedFiles() {
        let formData = {};
        let files = $('#replyForm_' + paperCommentId).find('.upload_filename');
        let $ceHiddenPath = $('#attachments_path_type_' + paperCommentId);

        formData.pcId = paperCommentId;
        formData.path = $ceHiddenPath.length > 0 ? $ceHiddenPath.val() : '';
        formData.docId =
            $ceHiddenPath.length > 0 ? $ceHiddenPath.attr('docId') : 0;

        files.each(function (index, value) {
            formData.file = $(value).val();
            ajaxDeleteFile(formData).done(function () {
                $(value).parent().remove();
            });
        });
    }

    $(window).on('unload', function () {
        deleteAllAttachedFiles();
    });

    if (isFromZSubmit) {
        $('.auto-clickable').click();
    }
});
