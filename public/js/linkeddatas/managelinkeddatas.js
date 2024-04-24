$(function () {
    let flagError = 0
    function callAddForm(typeld,option = []) {
        removeFormLd();

        $.ajax({
            type: "POST",
            url: "/administratelinkeddata/ajaxgetldform/",
            data:
                {
                    'typeld': typeld,
                    'option': option
                },
        }).success(function (response) {
            $("#container-manager-linkeddatas").append(response);
            $("#container-datasets").prepend(createSelectTypeLd(),createSelectRelationship());
            $("#select-ld-type").val(typeld);
            changePlaceholder(typeld);
            $('#select-ld-type').on('change', function() {
                let type = this.value;
                $("input#input-ld").attr("data-typeld", type);
                changePlaceholder(type);
            });
            if (option.hasOwnProperty('relationship')) {
                if (option['relationship'] !== undefined) {
                    $('select#select-relationship').val(option['relationship']);
                }
            }
            ajaxsubmissionAdd();
            ajaxModifyLd();
            $("#btn-cancel-dataset").on('click',function (){
                removeFormLd();
            });
        });
    }
    function removeFormLd(){
        if ($("form#addld").length > 0 || $("form#modifyLd").length > 0 ){
            $("form#addld").remove();
            $("form#modifyLd").remove();
        }
    }
    function changePlaceholder(typeLd){
        if (typeLd === 'publication') {
            $("#input-ld").attr("placeholder", "exemple: 10.46298/epi.7337");
        } else if (typeLd === 'software') {
            $("#input-ld").attr("placeholder", "exemple: swh:1:dir:d198bc9d7a6bcf6db04f476d29314f157507d505");
        } else {
            $("#input-ld").attr("placeholder", "exemple: hal-02832821v1");
        }
    }
    $('button#add-linkdata').on('click',function () {
        removeFormLd();
        callAddForm("dataset");
    });

    $('#anchor-dataset-add').on('click',function () {
        removeFormLd();
        callAddForm("dataset");
        $("#input-ld").attr("placeholder", "exemple: hal-02832821v1");
    });
    $('#anchor-software-add').on('click',function () {
            removeFormLd();
            callAddForm("software");
            $("#input-ld").attr("placeholder", "exemple: swh:1:dir:d198bc9d7a6bcf6db04f476d29314f157507d505");
    });
    $('#anchor-publication-add').on('click',function () {
        removeFormLd();
        callAddForm("publication");
        $("#input-ld").attr("placeholder", "exemple: 10.46298/epi.7337");
    });
    function ajaxModifyLd(){
        $('form[id="modifyLd"]').submit(function (e){
            e.preventDefault();
            let newType =  $("input#input-ld").data('typeld');
            let ldId = $('input#input-ld').data('id');
            let newRelationship = $('#select-relationship').find(":selected").val();
            let valueLd = $('input#input-ld').val();
            let docId = $('#paper_docId').val();
            let paperId = $('#paper_id').val();
            if (newRelationship.length === 0){
                $('#error-relationship').remove();
                let text = translate("Veuillez selectionner une relation pour la donnée");
                $('#container-datasets').after("<i id='error-relationship' class='pull-right' style='color: red;'>"+text+"</i>");
                return;
            }
            $.ajax({
                type: "POST",
                url: "/administratelinkeddata/setnewinfold/",
                data:
                    {
                        docId: docId,
                        paperId: paperId,
                        typeld: newType,
                        valueLd: valueLd,
                        ldId: ldId,
                        relationship: newRelationship
                    },
                beforeSend: function () {
                    window.scroll({
                        top: 0,
                        left: 0,
                        behavior: 'smooth'
                    });
                }
            }).success(function () {
                window.location.hash = "";
                window.location.reload();
            });
        });
    }
    function ajaxsubmissionAdd(){

        $('form[id="addld"]').submit(function (e){
            e.preventDefault();
            let typeLd = $('#input-ld').data('typeld');
            let valueLd = $('#input-ld').val();
            let docId = $('#paper_docId').val();
            let paperId = $('#paper_id').val();
            let relationship = $('#select-relationship').find(":selected").val();
            if (!valueLd || !relationship){
                $('#error-form-ld').remove();
                let text = translate("Veuillez saisir tous les champs du formulaire");
                $('#container-datasets').after("<i id='error-form-ld' class='pull-right' style='color: red;'>"+text+"</i>");
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
                        relationship:relationship
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
    }

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
    function removeError(){
        if ($('#error-input-ld').length > 0){
            $('#error-input-ld').remove();
        }
    }
    function createSelectRelationship(){
      return "<select name=\"select-relationship\" id=\"select-relationship\" style='flex-basis: fit-content;'>\n" +
          "  <option value=\"\">Relationship</option>\n" +
          "  <optgroup label=\"Basis\">\n" +
          "  <option value=\"isBasedOn\">isBasedOn</option>\n" +
          "  <option value=\"isBasisFor\">isBasisFor</option>\n" +
          "  <option value=\"basedOnData\">basedOnData</option>\n" +
          "  <option value=\"isDataBasisFor\">isDataBasisFor</option>\n" +
          "  </optgroup>\n" +
          "  <optgroup label=\"Comment\">\n" +
          "  <option value=\"isCommentOn\">isCommentOn</option>\n" +
          "  <option value=\"hasComment\">hasComment</option>\n" +
          "  </optgroup>\n" +
          "  <optgroup label=\"Continuation\">\n" +
          "  <option value=\"isContinuedBy\">isContinuedBy</option>\n" +
          "  <option value=\"continues\">continues</option>\n" +
          "  </optgroup>\n" +
          "  <optgroup label=\"Derivation\">\n" +
          "  <option value=\"isDerivedFrom\">isDerivedFrom</option>\n" +
          "  <option value=\"hasDerivation\">hasDerivation</option>\n" +
          "  </optgroup>\n" +
          "  <optgroup label=\"Documentation\">\n" +
          "  <option value=\"isDocumentedBy\">isDocumentedBy</option>\n" +
          "  <option value=\"documents\">documents</option>\n" +
          "  </optgroup>\n" +
          "  <optgroup label=\"Funding\">\n" +
          "  <option value=\"finances\">finances</option>\n" +
          "  <option value=\"isFinancedBy\">isFinancedBy</option>\n" +
          "  </optgroup>\n" +
          "  <optgroup label=\"Part\">\n" +
          "  <option value=\"isPartOf\">isPartOf</option>\n" +
          "  <option value=\"hasPart\">hasPart</option>\n" +
          "  </optgroup>\n" +
          "  <optgroup label=\"Peer review\">\n" +
          "  <option value=\"isReviewOf\">isReviewOf</option>\n" +
          "  <option value=\"hasReview\">hasReview</option>\n" +
          "  </optgroup>\n" +
          "  <optgroup label=\"References\">\n" +
          "  <option value=\"references\">references</option>\n" +
          "  <option value=\"isReferencedBy\">isReferencedBy</option>\n" +
          "  </optgroup>\n" +
          "  <optgroup label=\"Related material\">\n" +
          "  <option value=\"hasRelatedMaterial\">hasRelatedMaterial</option>\n" +
          "  <option value=\"isRelatedMaterial\">isRelatedMaterial</option>\n" +
          "  </optgroup>\n" +
          "  <optgroup label=\"Reply\">\n" +
          "  <option value=\"isReplyTo\">isReplyTo</option>\n" +
          "  <option value=\"hasReply\">hasReply</option>\n" +
          "  </optgroup>\n" +
          "  <optgroup label=\"Requirement\">\n" +
          "  <option value=\"requires\">requires</option>\n" +
          "  <option value=\"isRequiredBy\">isRequiredBy</option>\n" +
          "  </optgroup>\n" +
          "  <optgroup label=\"Software compilation\">\n" +
          "  <option value=\"isCompiledBy\">isCompiledBy</option>\n" +
          "  <option value=\"compiles\">compiles</option>\n" +
          "  </optgroup>\n" +
          "  <optgroup label=\"Supplement\">\n" +
          "  <option value=\"isSupplementTo\">isSupplementTo</option>\n" +
          "  <option value=\"isSupplementedBy\">isSupplementedBy</option>\n" +
          "  </optgroup>\n" +
          "</select>\n";
    }
    function createSelectTypeLd() {
        return "<select name=\"select-ld-type\" id=\"select-ld-type\" style='flex-basis: fit-content;'>\n" +
            "  <option value=\"publication\">Publication</option>\n" +
            "  <option value=\"dataset\">Dataset</option>\n" +
            "  <option value=\"software\">Software</option>\n" +
            "</select>\n";
    }
    $('a#edit-ld').on('click',function () {
        const option = {};
        option.relationship = $(this).data('relationship');
        option.valueLd = $(this).data('ldval');
        option.idLd = $(this).data('ld');
        option.modifyForm = true;
        if ($(this).data('type') === 'swhidId_s'){
            $(this).data('type',"software");
        }
        callAddForm($(this).data('type'),option);
    });
});