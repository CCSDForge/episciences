// $(function() {
//     $textTile = $('div#title-element > label').text();
//     if ($("input#is_proceeding").prop("checked")) {
//         $('div#title-element > label').text($('input#translate_text').val());
//         $('div[id^="conference_"]').each(function () {
//             $(this).css('display', '');
//             setRequiredInput("conference_name");
//             setRequiredInput("conference_start-id");
//             setRequiredInput("conference_end-id");
//         });
//     }else{
//         $('div[id^="conference_"]').each(function () {
//             $(this).css('display', 'none');
//         });
//     }
//     addDoi();
//     $("input#is_proceeding").on("click", function() {
//         if ($(this).prop("checked")) {
//             $('div[id^="conference_"]').each(function () {
//                 $(this).css('display','');
//             });
//             $('div#title-element > label').text($('input#translate_text').val());
//             setRequiredInput("conference_name");
//             setRequiredInput("conference_start-id");
//             setRequiredInput("conference_end-id");
//         } else {
//             $('div[id^="conference_"]').each(function () {
//                 $(this).css('display','none');
//             });
//             unsetRequiredInput("conference_name");
//             unsetRequiredInput("conference_start");
//             unsetRequiredInput("conference_end");
//
//             $('div#title-element > label').text($textTile);
//         }
//     });
//     if($("input#conference_proceedings_doi_input").val() === ""){
//         $('input#btn-request-proceedings').prop('disabled',"disabled");
//     }
//     $("input#conference_proceedings_doi_input").on('keyup change',function (){
//         if ($(this).val().length === 0) {
//             $('input#btn-request-proceedings').prop('disabled',"disabled");
//         } else {
//             $('input#btn-request-proceedings').prop('disabled',"");
//         }
//     });
//
//     $('input#btn-request-proceedings').on("click", function() {
//         let prefix = $('input#doi_proceedings_prefix_input').val();
//         let doiSuffix = $('input#conference_proceedings_doi').val()
//         if (!$('span#display-doi-proceeding').length) {
//             $('input#conference_proceedings_doi').after( "<span id='display-doi-proceeding'> Doi formatted -> "+prefix+doiSuffix+"</span>" );
//         } else {
//             $('span#display-doi-proceeding').text("Doi formatted -> "+prefix+doiSuffix);
//         }
//
//     });
//     function setRequiredInput(confName) {
//         $("#"+confName).attr('required',"required");
//         $("label[for='" + confName + "']").removeClass('optional');
//         $("label[for='" + confName + "']").addClass("required");
//     }
//
//     function unsetRequiredInput(confName){
//         $("#"+confName).attr('required',"");
//         $("label[for='" + confName + "']").removeClass('required');
//         $("label[for='" + confName + "']").addClass("optional");
//     }
//
//     function addDoi(){
//         if ($("input#journalprefixDoi").length) {
//             $("input#conference_proceedings_doi_input").attr("placeholder", $("input#journalprefixDoi").val());
//             $("label[for='conference_proceedings_doi_input']").text($("label[for='conference_proceedings_doi_input']").text() +' '+ $("input#journalprefixDoi").val());
//
//         }
//     }
// });
//
//
