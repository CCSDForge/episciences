/**
 * Deletes contents
 * @param index
 */
function clearFile(index) {
    let object_inputs = getElements('input', 'value');
    let self_id = object_inputs[index].id;
    let $identifier = $('#' + self_id.replace('value_', ''));
    $('#' + self_id).val('');
    $identifier.val('');
    $('#tempFile_content_' + index).html('');
}

/**
 *
 * @param label
 * @param index
 * @returns {string}
 */

function formatFileLabel(label, index) {
    let html = '';
    html += '<div class="small grey">';
    html +=
        '<span class="glyphicon glyphicon-remove-circle" title="' +
        translate('Annuler') +
        '"' +
        'onclick="clearFile(' +
        index +
        ')" style="margin-right: 5px; cursor: pointer">' +
        '</span>';
    html += $('<div>').text(label).html();
    html += '</div>';
    return html;
}

/**
 *
 * @param element
 * @param index
 * @returns {string}
 */

function getContainer(element, index) {
    let container_id = 'tempFile_content_' + index;
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

/**
 *
 * @param element
 * @param attr
 * @returns {*|jQuery|HTMLElement}
 */
function getElements(element, attr) {
    let object_elements = element + '[id^=' + attr + ']';
    return $(object_elements);
}

$(document).ready(function () {
    let object_inputs = getElements('input', 'file');
    $.each(object_inputs, function (index, value) {
        let element = '#' + value.id;
        $(element).change(function () {
            let container = getContainer($(this), index);
            let filename = $(this)[0].files.length
                ? $(this)[0].files[0].name
                : '';
            $(container).html(formatFileLabel(filename, index));
        });
    });
});
