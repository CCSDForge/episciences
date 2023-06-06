$(function() {
    $textTile = $('div#title-element > label').text();
    if ($("input#is_proceeding").prop("checked")) {
        $('div#title-element > label').text($('input#translate_text').val());
        $('div[id^="conference_"]').each(function () {
            $(this).css('display', '');
            setRequiredInput("conference_name");
            setRequiredInput("conference_start-id");
            setRequiredInput("conference_end-id");
        });
    }else{
        $('div[id^="conference_"]').each(function () {
            $(this).css('display', 'none');
        });
    }
    addDoi();
    $("input#is_proceeding").on("click", function() {
        if ($(this).prop("checked")) {
            $('div[id^="conference_"]').each(function () {
                $(this).css('display','');
            });
            $('div#title-element > label').text($('input#translate_text').val());
            setRequiredInput("conference_name");
            setRequiredInput("conference_start-id");
            setRequiredInput("conference_end-id");
        } else {
            $('div[id^="conference_"]').each(function () {
                $(this).css('display','none');
            });
            unsetRequiredInput("conference_name");
            unsetRequiredInput("conference_start");
            unsetRequiredInput("conference_end");

            $('div#title-element > label').text($textTile);
        }
    });
    if($("input#conference_proceedings_doi").val() === ''){
        $('input#btn-request-proceedings').prop('disabled',"disabled");
        $('input#btn-cancel-request-proceedings').hide();
    }
    $("input#conference_proceedings_doi").on('keyup change',function () {
        if ($(this).val().length === 0) {
            $('input#btn-request-proceedings').prop('disabled',"disabled");
            $('em#display-doi-proceeding').text("");
        } else {
            $('input#btn-request-proceedings').prop('disabled',"");

        }
    });

    $('input#btn-request-proceedings').on("click", function() {
        let prefix = $('input#doi_proceedings_prefix_input').val();
        let doiSuffix = $('input#conference_proceedings_doi').val();
        if (!$('em#display-doi-proceeding').length) {
            $('input#btn-request-proceedings').after( "<div class=\"col-sm-3 d-inline-block\"><em style='padding-top: 2%;display: inline-block;vertical-align: middle;' id='display-doi-proceeding'>"+$('input#translate_text_doi_request').val()+" -> "+prefix+doiSuffix+"</em></div>" );
        } else {
            $('em#display-doi-proceeding').text($('input#translate_text_doi_request').val()+" -> "+prefix+doiSuffix);
        }
        $('input#conference_proceedings_doi').prop('readonly',true);
        $('input#btn-request-proceedings').hide();
        $('input#btn-cancel-request-proceedings').show();
    });
    function setRequiredInput(confName) {
        $("#"+confName).attr('required',"required");
        $("label[for='" + confName + "']").removeClass('optional');
        $("label[for='" + confName + "']").addClass("required");
    }

    function unsetRequiredInput(confName){
        $("#"+confName).attr('required',"");
        $("label[for='" + confName + "']").removeClass('required');
        $("label[for='" + confName + "']").addClass("optional");
    }

    function addDoi(){
        if ($("input#journalprefixDoi").length) {
            $("input#conference_proceedings_doi").attr("placeholder", $("input#journalprefixDoi").val());
            $("label[for='conference_proceedings_doi']").text($("label[for='conference_proceedings_doi']").text() +' '+ $("input#journalprefixDoi").val());

        }
    }

    $("input#btn-cancel-request-proceedings").on("click", function() {
        $('em#display-doi-proceeding').parent().remove();
        $('input#btn-request-proceedings').show();
        $('input#btn-cancel-request-proceedings').hide();
        $('input#conference_proceedings_doi').prop('readonly',"");
    });

    if ($("input#doi_status").val() !== "assigned" && $("input#doi_status").val() !== "not-assigned"){
        let prefix = $('input#doi_proceedings_prefix_input').val();
        let doiSuffix = $('input#conference_proceedings_doi').val();
        $('input#conference_proceedings_doi').remove();
        $('input#doi_proceedings_prefix_input').replaceWith( "<div><em id='display-doi-proceeding'>"+prefix+doiSuffix+"</em></div>" );
        $('input#btn-request-proceedings').remove();
    }
});


