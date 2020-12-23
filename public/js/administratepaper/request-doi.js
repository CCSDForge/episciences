$(document).ready(function () {
    $('#requestNewDoi').each(function () {
        var $this = $(this);
        $this.on("click", function () {
            var docid = $(this).data('docid');
            $('#doi-status').html(getLoader());
            $.post(
                '/administratepaper/ajaxrequestnewdoi',
                {
                    docid: docid
                },
                function (data) {
                    let json = data,
                        objData = JSON.parse(json);

                    if (objData.doi !== 'Error') {
                        $("#doi-link").html('&nbsp;' + objData.doi);
                        $("#requestNewDoi").remove();
                        $("#doi-action").remove();
                        $("#doi-status").prepend('<div class="alert alert-success alert-dismissible" role="alert">' + objData.feedback + '</div>');
                    } else {
                        $("#doi-status").prepend('<div class="alert alert-danger alert-dismissible" role="alert">' + objData.error_message + '</div>');
                    }
                    if (objData.doi_status !== 'Error') {
                        $("#doi-status").html(objData.doi_status);
                        $("#requestNewDoi").remove();
                        $("#doi-action").remove();
                        $("#doi-status").prepend('<div class="alert alert-success alert-dismissible" role="alert">' + objData.feedback + '</div>');
                    } else {
                        $("#doi-status").prepend('<div class="alert alert-danger alert-dismissible" role="alert">' + objData.error_message + '</div>');

                    }
                },
                'text'
            );
        });
    });
});
