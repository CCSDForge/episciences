$(function () {
    let flagError = 0
    $('#add-dataset').on('click',function (e) {
        showBtnLd();
        if ($("input#input-ld").length > 0){
            $("input#input-ld").remove();
        }
        $("#container-datasets").append("<input id=\"input-ld\" type=\"text\" data-typeld=\"dataset\" class=\"form-control input-sm\" placeholder='exemple: hal-0182641v1'>");
    });
    $('#add-software').on('click',function (e) {
        showBtnLd();
        if ($("input#input-ld").length > 0) {
            $("input#input-ld").remove();
        }
        $("#container-datasets").append("<input id=\"input-ld\" type=\"text\" data-typeld=\"software\" class=\"form-control input-sm\" placeholder='exemple: swh:1:dir:d198bc9d7a6bcf6db04f476d29314f157507d505'>");
    });
    $('#add-publication').on('click',function (e) {
        showBtnLd();
        if ($("input#input-ld").length > 0) {
            $("input#input-ld").remove();
        }
        $("#container-datasets").append("<input id=\"input-ld\" type=\"text\" data-typeld=\"publication\" class=\"form-control input-sm\" placeholder='exemple: 10.46298/epi.7337'>");
    });
    $('form#addld').submit(function (e){
       e.preventDefault();
        let typeLd = $('#input-ld').data('typeld');
        let valueLd = $('#input-ld').val().trim();
        let docId = $('#paper_docId').val();
        let paperId = $('#paper_id').val();
        if (!valueLd){
            return;
        }
        if ($('a#link-ld').length > 0) {
            let flagDoubleValue = 0
            $('a#link-ld').each(function (){
                if (this.innerHTML === valueLd){
                    flagDoubleValue = 1;
                    return false;
                }
            });
            if (flagError === 0 && flagDoubleValue === 1) {
                $("<em id='error-input-ld' class='help-block' style='color: red;'>"+$("span#error_msg_same_val").text()+"</em>").insertBefore($("input#input-ld"));
                flagError = 1;
            }
            if (flagDoubleValue) {
                return;
            }

        }
        $.ajax({
            type: "POST",
            url: "/administratelinkeddata/addld/",
            data:
            {
                typeld:typeLd,
                valueld:valueLd,
                docId:docId,
                paperId:paperId,
            },
            beforeSend: function () {
                window.scroll({
                    top: 0,
                    left: 0,
                    behavior: 'smooth'
                });
            }
        }).success(function (response) {
            window.location.hash = "";
            window.location.reload();
        });
    });
    $("button#remove-ld").on('click',function(e){
        let answer = window.confirm($('span#alert_msg_remove').text());
        if (answer) {
            let idLd = $(this).data('ld');
            let docId = $('#paper_docId').val();
            let paperId = $('#paper_id').val();
            $.ajax({
                type: "POST",
                url: "/administratelinkeddata/removeld/",
                data:
                    {
                        id: idLd,
                        docId:docId,
                        paperId:paperId,
                    },
                beforeSend: function () {
                    window.scroll({
                        top: 0,
                        left: 0,
                        behavior: 'smooth'
                    })
                }
            }).success(function (response) {
                if (JSON.parse(response)[0] === true){
                    window.location.hash = "";
                    window.location.reload();
                }
            });
        }
    });
    $("#btn-cancel-dataset").on('click',function (e){
        if ($("input#input-ld").length > 0) {
            $("input#input-ld").remove();
        }
        hideBtnLd();
    });
    function showBtnLd(){
        removeError();
        $("#btn-dataset").removeClass('hidden');
        $("#btn-cancel-dataset").removeClass('hidden');
    }
    function hideBtnLd(){
        removeError();
        $("#btn-dataset").removeClass('show').addClass('hidden');
        $("#btn-cancel-dataset").removeClass('show').addClass('hidden');
    }
    function removeError(){
        if ($('#error-input-ld').length > 0){
            $('#error-input-ld').remove();
        }
    }
    $('#anchor-publication-add').on('click',function () {
       $('#add-publication').click();
    });
    $('#anchor-software-add').on('click',function () {
        $('#add-software').click();
    });
    $('#anchor-dataset-add').on('click',function () {
        $('#add-dataset').click();
    });
});