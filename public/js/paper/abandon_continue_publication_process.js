let $controller = controller;
let $action = action;
let $docId = docId;
$(function () {
    let $url = '/' + $controller + '/' + $action;
    //Supprimer un événement de click précédemment associé.
    $('#submit-modal').unbind('click');
    $('#submit-modal').on('click', function () {
        let $request = doAction($url, $docId);
        $request.done(function (response) {
            if (response && !response['error']) {
                $('#modal-box').modal('hide');
                // reload the current page
                window.location.href = location.href.toString();
            } else {
                $('#message')
                    .addClass('alert alert-danger')
                    .html(response['error']);
            }
        });

        $request.fail(function (jqXHR, textStatus) {
            console.log(
                "Une erreur interne s'est produite lors de l'appel de la fonction doAction : " +
                    textStatus
            );
        });
    });
});

/**
 *
 * @param url
 * @param docId
 * @param doAction
 * @returns {*}
 */

function doAction(url, docId) {
    return $.ajax({
        url: url,
        type: 'POST',
        dataType: 'json',
        data: { docid: docId, doaction: 1 },
    });
}
