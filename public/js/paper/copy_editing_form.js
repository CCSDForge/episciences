let paperCommentId;
$(function () {
    $('[id^=replyFormBtn_]').on('click', function () {
        paperCommentId = $(this).attr('id').match(/\d+/)[0];
        // Load the upload widget script now, once the reply form (and its
        // attachments_path_type_<pcId> hidden input) is in the DOM.
        const script = document.createElement('script');
        script.src = '/js/library/es.filepond.js';
        script.onerror = () =>
            console.log('loading failed: /js/library/es.filepond.js');
        document.body.appendChild(script);
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
        let $ceHiddenPath = $('#attachments_path_type_' + paperCommentId);

        let context = {
            pcId: paperCommentId,
            path: $ceHiddenPath.length > 0 ? $ceHiddenPath.val() : '',
            docId: $ceHiddenPath.length > 0 ? $ceHiddenPath.attr('docId') : 0,
        };

        let csrfMeta = document.querySelector('meta[name="csrf-token"]');
        let csrfToken = csrfMeta ? csrfMeta.content : '';

        $('#replyForm_' + paperCommentId)
            .find('.upload_filename')
            .each(function (index, hiddenInput) {
                let body = new URLSearchParams({
                    ...context,
                    file: hiddenInput.value,
                });

                fetch('/file/delete', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken },
                    body,
                }).then(() => hiddenInput.remove());
            });
    }

    $(window).on('unload', function () {
        deleteAllAttachedFiles();
    });

    if (isFromZSubmit) {
        $('.auto-clickable').click();
    }
});
