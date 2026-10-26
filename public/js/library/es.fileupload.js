$(function () {
    let $docId = null;

    if (typeof paperCommentId === 'undefined' || paperCommentId === 0) {
        paperCommentId = null;
    }

    let formData = {};
    let idCpt = 0;

    // Per-session request token, sent with every upload/delete call
    let csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        formData.csrf_token = csrfMeta.content;
    }

    // comment
    if (null !== paperCommentId) {
        formData.pcId = paperCommentId;
        // to change  comment path
        let $hiddenPath = $('#attachments_path_type_' + paperCommentId);
        let isHiddenPath = $hiddenPath.length > 0;
        formData.path = isHiddenPath ? $hiddenPath.val() : '';
        formData.docId = isHiddenPath ? $hiddenPath.attr('docId') : 0;
        formData.paperId =
            isHiddenPath && $hiddenPath.attr('paperId')
                ? $hiddenPath.attr('paperId')
                : 0;
    } else if ($('#docid').length > 0 || $('input:hidden[name="docid"]')) {
        if ($('#docid').length > 0) {
            $docId = $('#docid');
        } else if ($('input:hidden[name="docid"]').length > 0) {
            $docId = $('input:hidden[name="docid"]');
        }

        if ($docId) {
            formData.docId = $docId.val();
        }
    }

    $('.upload_button').on('click', function () {
        // Simulate a click on the file input button
        // to show the file browser dialog
        $(this).parent().children('input:first').click();
    });

    // Initialize the jQuery File Upload plugin
    $('.upload_widget').fileupload({
        url: JS_PREFIX_URL + 'file/upload/',
        formData: formData,
        dataType: 'json',

        // This function is called when a file is added to the queue;
        // via the browse button
        add: function (e, data) {
            $('button[id^="submit-modal"]').prop('disabled', true);
            idCpt++;

            let file_name = data.files[0].name;
            let file_size = readableBytes(data.files[0].size);

            let tpl_content = '<div class="file_container working">';
            tpl_content +=
                '<input class="upload_filename" type="hidden" name="attachments[]" value="' +
                file_name +
                '" />';
            // Sent back to remove the file or to attach it to the answer: set once the upload succeeded
            tpl_content +=
                '<input class="upload_token" type="hidden" name="attachment_tokens[]" value="" />';
            tpl_content += '<p>';
            tpl_content +=
                '<a target="_blank" id="href-' +
                idCpt +
                '"><span class="file_name truncate"></span></a> ';
            tpl_content += '<span class="file_size"></span>';
            tpl_content += '</p>';
            tpl_content += '<span class="remove_file">❌</span>';
            tpl_content += '<progress max="100" value="0"></progress>';
            tpl_content += '<span class="progress_value"></span>';
            tpl_content += '<span></span>';
            tpl_content +=
                '<div id="file_container_errors_' +
                idCpt +
                '" style="margin-top: 10px;"></div>';
            tpl_content += '</div>';

            let tpl = $(tpl_content);

            // Append the file name and file size
            tpl.find('.file_name').text(file_name);
            tpl.find('.file_size').text(file_size);

            // Add the HTML to the uploaded files container
            let uploads_container = $(this).find('.uploads_container');
            data.context = tpl.appendTo(uploads_container);
            data.idCpt = idCpt;

            // Listen for clicks on the cancel icon
            tpl.find('.remove_file').click(function () {
                if (tpl.hasClass('working')) {
                    jqXHR.abort();
                }
                tpl.fadeOut('fast', function () {
                    let file = tpl.find('.upload_filename').val();
                    let token = tpl.find('.upload_token').val();

                    // No token: the file is not stored on the server (upload failed or cancelled)
                    if ('' === token) {
                        tpl.remove();
                        refreshSubmitButtons();
                        return;
                    }

                    let data = $.extend({}, formData, {
                        file: file,
                        token: token,
                    });
                    // ajax call: delete
                    ajaxDeleteFile(data).always(function () {
                        tpl.remove();
                        refreshSubmitButtons();
                    });
                });
            });

            // Automatically upload the file once it is added to the queue
            let jqXHR = data.submit();
        },

        done: function (e, data) {
            let result = data.result;

            // Anything but an explicit success means that the file is not stored on the server
            if (!result || result.status !== 'success') {
                let errors =
                    result && result.messages
                        ? Object.values(result.messages)
                        : [];
                showUploadError(data, errors);
            } else {
                if (result.filename) {
                    // replace filename string
                    data.context.find('.file_name').text(result.filename);
                    // update filename in hidden input
                    data.context.find('.upload_filename').val(result.filename);
                    data.context
                        .find('.upload_token')
                        .val(result.fileToken || '');
                    //update href
                    data.context
                        .find('[id^=href-]')
                        .attr('href', result.fileUrl);
                }

                data.context.removeClass('working');
                refreshSubmitButtons();
            }
        },

        progress: function (e, data) {
            // Calculate the completion percentage of the upload
            let progress = parseInt((data.loaded / data.total) * 100, 10);

            // Update the hidden input field and trigger a change
            data.context.find('progress').val(progress);
            data.context.find('.progress_value').html(progress + '%');
            // the upload is over only once the server has answered (see done / fail)
        },

        // HTTP error, expired session, network failure...
        fail: function (e, data) {
            if (data.errorThrown === 'abort') {
                // cancelled by the user (see remove_file)
                return;
            }

            showUploadError(data, []);
        },
    });
});

/**
 * Flag a file whose upload failed: the author is warned and has to remove it
 * (and try again) before submitting the form
 * @param data jQuery File Upload data of the file
 * @param {Array} errors error messages returned by the server
 */
function showUploadError(data, errors) {
    const container = data.context[0];
    container.classList.remove('working');
    container.classList.add('error');

    // An empty name tells the server that this file is missing
    container.querySelector('.upload_filename').value = '';
    container.querySelector('.upload_token').value = '';

    if (!errors.length) {
        errors = [
            translate(
                'Le téléversement du fichier a échoué. Veuillez le retirer et réessayer.'
            ),
        ];
    }

    const errorsBlock = document.createElement('div');
    errorsBlock.className = 'errors';
    errorsBlock.setAttribute('role', 'alert');
    errorsBlock.style.paddingLeft = '15px';

    const title = document.createElement('div');
    title.style.marginBottom = '5px';
    title.style.color = 'red';
    const strong = document.createElement('strong');
    strong.textContent = translate('Erreurs :');
    title.appendChild(strong);
    errorsBlock.appendChild(title);

    errors.forEach(function (error) {
        const line = document.createElement('div');
        line.style.marginLeft = '10px';
        line.style.color = 'red';
        // messages may contain markup: only keep their text
        line.textContent =
            ' * ' +
            new DOMParser().parseFromString(String(error), 'text/html').body
                .textContent;
        errorsBlock.appendChild(line);
    });

    const target = document.getElementById(
        'file_container_errors_' + data.idCpt
    );
    target.replaceChildren(errorsBlock);

    refreshSubmitButtons();
}

/**
 * The form can only be submitted once every upload is over and successful
 */
function refreshSubmitButtons() {
    const isBlocked =
        document.querySelector(
            '.uploads_container .file_container.working, .uploads_container .file_container.error'
        ) !== null;

    document
        .querySelectorAll('button[id^="submit-modal"]')
        .forEach(function (button) {
            button.disabled = isBlocked;
        });
}

/**
 * @param formData
 * @returns {*}
 */
function ajaxDeleteFile(formData) {
    return $.ajax({
        url: JS_PREFIX_URL + 'file/delete',
        type: 'POST',
        data: formData,
    });
}
