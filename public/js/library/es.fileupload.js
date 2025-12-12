$(function () {
    let $docId = null;

    if (typeof paperCommentId === 'undefined' || paperCommentId === 0) {
        paperCommentId = null;
    }

    let formData = {};
    let idCpt = 0;

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
        url: '/file/upload/',
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
                    // if not errors in ajax file/upload response
                    //if ('' !== file) {
                    let data = $.extend({}, formData, { file: file });
                    // ajax call: delete
                    ajaxDeleteFile(data).done(function () {
                        tpl.remove();
                        if ($('.errors').length > 0) {
                            $('button[id^="submit-modal"]').prop(
                                'disabled',
                                true
                            );
                        } else {
                            $('button[id^="submit"]').prop('disabled', false);
                        }
                    });
                    //}
                });
            });

            // Automatically upload the file once it is added to the queue
            let jqXHR = data.submit();
        },

        done: function (e, data) {
            let result = data.result;

            if (result.status === 'error') {
                data.context.find('.upload_filename').val('');
                let errors = Object.values(result.messages);
                if (errors.length) {
                    $('button[id^="submit-modal"]').prop('disabled', true);
                    let html =
                        '<div style="padding-left: 15px" class="errors">';
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

                    $('#file_container_errors_' + data.idCpt).html(html);
                }
            } else {
                if (result.filename) {
                    // replace filename string
                    data.context.find('.file_name').text(result.filename);
                    // update filename in hidden input
                    data.context.find('.upload_filename').val(result.filename);
                    //update href
                    data.context
                        .find('[id^=href-]')
                        .attr('href', result.fileUrl);
                }

                $('button[id^="submit-modal"]').prop('disabled', false);
            }
        },

        progress: function (e, data) {
            // Calculate the completion percentage of the upload
            let progress = parseInt((data.loaded / data.total) * 100, 10);

            // Update the hidden input field and trigger a change
            data.context.find('progress').val(progress);
            data.context.find('.progress_value').html(progress + '%');

            if (progress === 100) {
                data.context.removeClass('working');
            }
        },

        fail: function (e, data) {
            if (data.jqXHR.statusText === 'OK') {
                $('button[id^="submit-modal"]').prop('disabled', false);
            }

            data.context.addClass('error');
        },
    });
});

/**
 * @param formData
 * @returns {*}
 */
function ajaxDeleteFile(formData) {
    return $.ajax({
        url: '/file/delete',
        type: 'POST',
        data: formData,
    });
}
