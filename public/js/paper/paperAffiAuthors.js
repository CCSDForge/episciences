$(document).ready(function () {
    affiliationManagement();
});

function affiliationManagement() {
    $("#select-author-affi").on('change', function () {
            let idAuthor = $(this).find(":selected").attr("id");
            $('#id-edited-affi-author').val(idAuthor);
            if (typeof idAuthor !== 'undefined') {

                let data = JSON.stringify({
                    'idAuthor': idAuthor,
                    'paperId': $("div#paperid-for-author").text()
                });

                let ajaxR = ajaxRequest('/paper/getaffiliationsbyauthor/', data);

                ajaxR.done(function (result) {

                    $('div#affi-body').empty();
                    $('div#affi-body').append(result);

                    let url = '/js/user/affiliations.js?_=v' + versionCache;

                    $.getScript(url).fail(function () {
                        console.log('affiliations.js loading failed');
                    });

                });

            } else {
                $('div#affi-body').empty();
            }


        }
    );
    $("form#form-affi-authors").submit(function (e) {
        if (!$('#id-edited-affi-author').val() || $('#id-edited-affi-author').val().length === 0) {
            e.preventDefault();
        }
    });

}