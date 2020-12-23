/**
 * Effacer le contenu de l'element en cours
 * @param index
 */
function clearFile(index){
    var object_inputs = getElements('input', 'value');
    var self_id = object_inputs[index].id;
    $('#' + self_id).val('');
    $("#tempFile_content_" + index).html('');
}

/**
 *
 * @param label
 * @param index
 * @returns {string}
 */

function formatFileLabel(label, index)
{
    var html = '';
    html += '<div class="small grey">';
    html += '<span class="glyphicon glyphicon-remove-circle" title="' + translate("Annuler") + '"'
         +  'onclick="clearFile(' + index +')" style="margin-right: 5px; cursor: pointer">'
         +  '</span>';
    html += label;
    html += '</div>';
    return html;
}

/**
 *
 * @param element
 * @param index
 * @returns {string}
 */

function getContainer(element, index){
    var container_id = 'tempFile_content_' + index ;
    if(!$('#'+container_id).length){
        $(element).parent('div').append(
            '<div id="'+container_id+'" style="padding-top: 10px">' +
            '</div>');
    }
    return'#'+container_id;
}

/**
 * Recupérer l'object contenant tous les elements avec un id qui commence par la valeur attr
 * @param element
 * @param attr
 * @returns {*|jQuery|HTMLElement}
 */
function getElements(element, attr){
    var object_elements = element + "[id^=" + attr + "]";
    return $(object_elements);
}

$(document).ready(function(){
    var object_inputs = getElements('input', 'file');
    $.each(object_inputs, function(index, value){
        var element = "#" + value.id;
        $(element).change(function(){
            var container = getContainer($(this), index);
            var filename = $(this)[0].files.length ? ($(this))[0].files[0].name : "";
            $(container).html(formatFileLabel(filename, index));
        });
    });
});

