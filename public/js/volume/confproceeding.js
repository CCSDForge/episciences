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
            // Create DOM elements securely instead of HTML string concatenation
            const $div = $('<div>').addClass('col-sm-3 d-inline-block');
            const $em = $('<em>')
                .attr('id', 'display-doi-proceeding')
                .css({
                    'padding-top': '2%',
                    'display': 'inline-block',
                    'vertical-align': 'middle'
                })
                .text($('input#translate_text_doi_request').val() + " -> " + prefix + doiSuffix);
            $div.append($em);
            $('input#btn-request-proceedings').after($div);
        } else {
            $('em#display-doi-proceeding').text($('input#translate_text_doi_request').val()+" -> "+prefix+doiSuffix);
        }
        $('input#conference_proceedings_doi').prop('readonly',true);
        $('input#btn-request-proceedings').hide();
        $('input#btn-cancel-request-proceedings').show();
    });
    function setRequiredInput(confName) {
        // Use attribute selector for safer DOM querying
        $("[id='" + confName.replace(/'/g, "\\'") + "']").attr('required',"required");
        $("label[for='" + confName.replace(/'/g, "\\'") + "']").removeClass('optional').addClass("required");
    }

    function unsetRequiredInput(confName){
        // Use attribute selector for safer DOM querying
        $("[id='" + confName.replace(/'/g, "\\'") + "']").attr('required',"");
        $("label[for='" + confName.replace(/'/g, "\\'") + "']").removeClass('required').addClass("optional");
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
        // Create DOM elements securely instead of HTML string concatenation
        const $div = $('<div>');
        const $em = $('<em>')
            .attr('id', 'display-doi-proceeding')
            .text(prefix + doiSuffix);
        $div.append($em);
        $('input#doi_proceedings_prefix_input').replaceWith($div);
        $('input#btn-request-proceedings').remove();
    }
});


