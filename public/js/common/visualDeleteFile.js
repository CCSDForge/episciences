/**
 * Deletes contents
 * @param fileInputId the id of the <input type="file"> to clear (see
 *   Episciences_Form_Decorator_BootstrapInputFile, which renders it alongside a
 *   paired "value_<fileInputId>" text input)
 */
function clearFile(fileInputId) {
    $('#value_' + fileInputId).val(''); // to clear the value from the input field
    $('#' + fileInputId)
        .val('')
        .trigger('change'); // The .trigger('change') is crucial here: if the input is marked as required in a form to prevent submission (for example data descriptor file; if a listener "change" is attached to it)
    $('#tempFile_content_' + fileInputId).html('');
}

/**
 *
 * @param label
 * @param fileInputId
 * @returns {string}
 */

function formatFileLabel(label, fileInputId) {
    let html = '';
    html += '<div class="small grey">';
    html +=
        '<span class="glyphicon glyphicon-remove-circle" title="' +
        translate('Annuler') +
        '"' +
        'onclick="clearFile(\'' +
        fileInputId +
        '\')" style="margin-right: 5px; cursor: pointer">' +
        '</span>';
    html += $('<div>').text(label).html();
    html += '</div>';
    return html;
}

/**
 *
 * @param element
 * @param fileInputId
 * @returns {string}
 */

function getContainer(element, fileInputId) {
    let container_id = 'tempFile_content_' + fileInputId;
    if (!$('#' + container_id).length) {
        $(element)
            .parent('div')
            .append(
                '<div id="' +
                    container_id +
                    '" style="padding-top: 10px">' +
                    '</div>'
            );
    }
    return '#' + container_id;
}

$(document).ready(function () {
    // Use event delegation to handle both existing and dynamically added file inputs.
    // Each file input's own id (unique per form/element) directly identifies its
    // paired "value_<id>" input and "tempFile_content_<id>" container — no need to
    // correlate positions across separately-selected lists of inputs on the page.
    $(document)
        .off('change', 'input[id^=file]')
        .on('change', 'input[id^=file]', function () {
            let container = getContainer($(this), this.id);
            let filename = this.files.length ? this.files[0].name : '';
            $(container).html(formatFileLabel(filename, this.id));
        });
});
