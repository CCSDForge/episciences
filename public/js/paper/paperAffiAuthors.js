$(document).ready(function() {
    affiliationManagement();
});

function affiliationManagement(){
    $("#select-author-affi").change(function(e){
        let idAuthor = $(this).find(":selected").attr("id");
        $('#id-edited-affi-author').val(idAuthor);
        if (typeof idAuthor !== 'undefined') {
            $.ajax({
                url: '/paper/getaffiliationsbyauthor/',
                type: 'POST',
                data: JSON.stringify({
                    'idAuthor' : idAuthor,
                    'paperId' : $("div#paperid-for-author").text()
                }),
                success: function success(result) {
                    $('div#affi-body').empty();
                    $('div#affi-body').append(result);
                    $.getScript('/js/user/affiliations.js?_=v'+versionCache).fail(function () {
                        console.log('affiliations.js loading failed');
                    });
                }
            });
        }else{
            $('div#affi-body').empty();
        }
    });
    $("form#form-affi-authors").submit(function (e) {
        if (!$('#id-edited-affi-author').val() || $('#id-edited-affi-author').val().length === 0) {
            e.preventDefault();
        }
    });

}