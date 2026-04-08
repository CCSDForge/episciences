/**
 * @deprecated see suggestions: git #182
 */
$(document).ready(function () {
    let $mode = $('#systemAutoEditorsAssignment');
    let $details = $('#advancedAssignation-editorsAssignmentDetails-element');
    let oldVal = $mode.val();
    $mode.on('change', function () {
        let currentVal = $(this).val();
        let title = $('#systemAutoEditorsAssignment option:selected').text();
        let message = 'Êtes-vous sûr ?';

        bootbox.setDefaults({ locale: locale });
        bootbox.confirm({
            title: translate(title),
            message: translate(message),
            buttons: {
                cancel: {
                    label:
                        '<i class="fas fa-times"></i> ' + translate('Annuler'),
                },
                confirm: {
                    label:
                        '<i class="fas fa-check"></i> ' +
                        translate('Confirmer'),
                },
            },
            callback: function (result) {
                if (result) {
                    $details.empty();
                    if ($('#save-content').length > 0) {
                        $('#save-content').empty();
                    }
                    let request = $.ajax({
                        type: 'POST',
                        url: JS_PREFIX_URL + 'review/assignationmode',
                        data: { editors_assignment_mode: currentVal },
                    });
                    request.done(function (html) {
                        $details.append(html);
                    });
                    request.fail(function (jqXHR, textStatus) {
                        console.log(
                            'SYSTEM_AUTO_EDITORS_ASSIGNMENT: ' + textStatus
                        );
                    });
                } else {
                    $mode.val(oldVal);
                }
            },
        });
    });
});
