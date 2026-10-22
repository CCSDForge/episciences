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
        const replyForm = document.getElementById(
            'replyForm_' + paperCommentId
        );
        if (!replyForm) {
            return;
        }

        const ceHiddenPath = document.getElementById(
            'attachments_path_type_' + paperCommentId
        );

        const context = {
            pcId: paperCommentId,
            path: ceHiddenPath ? ceHiddenPath.value : '',
            docId: ceHiddenPath ? ceHiddenPath.getAttribute('docId') : 0,
        };

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.content : '';

        replyForm.querySelectorAll('.upload_filename').forEach(hiddenInput => {
            const body = new URLSearchParams({
                ...context,
                file: hiddenInput.value,
            });

            fetch('/file/delete', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken },
                body,
            }).then(response => {
                // fetch() only rejects on network failure, not on HTTP error status
                // (403 CSRF failure, 500, ...) — only drop the tracking element once the
                // server has actually confirmed the deletion.
                if (response.ok) {
                    hiddenInput.remove();
                } else {
                    console.log(
                        'file delete failed: ' +
                            hiddenInput.value +
                            ' (' +
                            response.status +
                            ')'
                    );
                }
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
