$(document).ready(function () {
    let $doiStatusLoader = $('#doi-status-loader');
    $('#requestNewDoi').each(function () {
        var $this = $(this);
        $this.on('click', function () {
            var docid = $(this).data('docid');
            let isError = false;

            $doiStatusLoader.html(getLoader());
            $doiStatusLoader.show();
            $.post(
                '/administratepaper/ajaxrequestnewdoi',
                {
                    docid: docid,
                },
                function (data) {
                    let json = data,
                        objData = JSON.parse(json);

                    if (objData.doi !== 'Error') {
                        $('#doi-link').html('&nbsp;' + objData.doi);
                        $('#requestNewDoi').remove();
                        $('#doi-action').remove();
                        $('#doi-status').prepend(
                            '<div class="alert alert-success alert-dismissible" role="alert">' +
                                objData.feedback +
                                '</div>'
                        );
                    } else {
                        isError = true;
                    }
                    if (objData.doi_status !== 'Error') {
                        $('#doi-status').html(objData.doi_status);
                        $('#requestNewDoi').remove();
                        $('#doi-action').remove();
                        $('#doi-status').prepend(
                            '<div class="alert alert-success alert-dismissible" role="alert">' +
                                objData.feedback +
                                '</div>'
                        );
                    } else {
                        isError = true;
                    }

                    if (isError) {
                        $doiStatusLoader.hide();
                        $('#doi-status').prepend(
                            '<div class="alert alert-danger alert-dismissible" role="alert">' +
                                objData.error_message +
                                '</div>'
                        );
                    }
                },
                'text'
            ).done(function () {
                if (!isError) {
                    location.reload();
                }
            });
        });
    });
});
