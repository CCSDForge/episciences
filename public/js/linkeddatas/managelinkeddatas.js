$(function () {
  let flagError = 0;

  function callAddForm(typeld, option = []) {
    removeFormLd();

    $.ajax({
      type: "POST",
      url: "/administratelinkeddata/ajaxgetldform/",
      data: {
        typeld: typeld,
        option: option,
      },
    }).success(function (response) {
      $("#container-manager-linkeddatas").append(response);
      $("#container-datasets").prepend(
        createSelectTypeLd(),
        createSelectRelationship(),
      );
      $("#select-ld-type").val(typeld);
      changePlaceholder(typeld);
      $("#select-ld-type").on("change", function () {
        let type = this.value;
        $("input#input-ld").attr("data-typeld", type);
        changePlaceholder(type);
      });
      if (option.hasOwnProperty("relationship")) {
        if (option["relationship"] !== undefined) {
          $("select#select-relationship").val(option["relationship"]);
        }
      }
      ajaxsubmissionAdd();
      ajaxModifyLd();
      $("#btn-cancel-dataset").on("click", function () {
        removeFormLd();
      });
    });
  }

  function removeFormLd() {
    if ($("form#addld").length > 0 || $("form#modifyLd").length > 0) {
      $("form#addld").remove();
      $("form#modifyLd").remove();
    }
  }

  function changePlaceholder(typeLd) {
    if (typeLd === "publication") {
      $("#input-ld").attr("placeholder", "exemple: 10.46298/epi.7337");
    } else if (typeLd === "software") {
      $("#input-ld").attr(
        "placeholder",
        "exemple: swh:1:dir:d198bc9d7a6bcf6db04f476d29314f157507d505",
      );
    } else {
      $("#input-ld").attr("placeholder", "exemple: hal-02832821v1");
    }
  }

  $("button#add-linkdata").on("click", function () {
    removeFormLd();
    callAddForm("dataset");
  });

  $("#anchor-dataset-add").on("click", function () {
    removeFormLd();
    callAddForm("dataset");
    $("#input-ld").attr("placeholder", "exemple: hal-02832821v1");
  });
  $("#anchor-software-add").on("click", function () {
    removeFormLd();
    callAddForm("software");
    $("#input-ld").attr(
      "placeholder",
      "exemple: swh:1:dir:d198bc9d7a6bcf6db04f476d29314f157507d505",
    );
  });
  $("#anchor-publication-add").on("click", function () {
    removeFormLd();
    callAddForm("publication");
    $("#input-ld").attr("placeholder", "exemple: 10.46298/epi.7337");
  });

  function ajaxModifyLd() {
    $('form[id="modifyLd"]').submit(function (e) {
      e.preventDefault();
      let newType = $("input#input-ld").data("typeld");
      let ldId = $("input#input-ld").data("id");
      let newRelationship = $("#select-relationship").find(":selected").val();
      let valueLd = $("input#input-ld").val();
      let docId = $("#paper_docId").val();
      let paperId = $("#paper_id").val();
      if (newRelationship.length === 0) {
        $("#error-relationship").remove();
        let text = translate(
          "Veuillez selectionner une relation pour la donnée",
        );
        $("#container-datasets").after(
          "<i id='error-relationship' class='pull-right' style='color: red;'>" +
            text +
            "</i>",
        );
        return;
      }
      $.ajax({
        type: "POST",
        url: "/administratelinkeddata/setnewinfold/",
        data: {
          docId: docId,
          paperId: paperId,
          typeld: newType,
          valueLd: valueLd,
          ldId: ldId,
          relationship: newRelationship,
        },
        beforeSend: function () {
          window.scroll({
            top: 0,
            left: 0,
            behavior: "smooth",
          });
        },
      }).success(function () {
        window.location.hash = "";
        window.location.reload();
      });
    });
  }

  function ajaxsubmissionAdd() {
    $('form[id="addld"]').submit(function (e) {
      e.preventDefault();
      let typeLd = $("#input-ld").data("typeld");
      let valueLd = $("#input-ld").val();
      let docId = $("#paper_docId").val();
      let paperId = $("#paper_id").val();
      let relationship = $("#select-relationship").find(":selected").val();
      if (!valueLd || !relationship) {
        $("#error-form-ld").remove();
        let text = translate("Veuillez saisir tous les champs du formulaire");
        $("#container-datasets").after(
          "<i id='error-form-ld' class='pull-right' style='color: red;'>" +
            text +
            "</i>",
        );
        return;
      }
      if ($("a#link-ld").length > 0) {
        let flagDoubleValue = 0;
        $("a#link-ld").each(function () {
          if (this.innerHTML === valueLd) {
            flagDoubleValue = 1;
            return false;
          }
        });
        if (flagError === 0 && flagDoubleValue === 1) {
          $(
            "<em id='error-input-ld' class='help-block' style='color: red;'>" +
              $("span#error_msg_same_val").text() +
              "</em>",
          ).insertBefore($("input#input-ld"));
          flagError = 1;
        }
        if (flagDoubleValue) {
          return;
        }
      }
      $.ajax({
        type: "POST",
        url: "/administratelinkeddata/addld/",
        data: {
          typeld: typeLd,
          valueld: valueLd,
          docId: docId,
          paperId: paperId,
          relationship: relationship,
        },
        beforeSend: function () {
          window.scroll({
            top: 0,
            left: 0,
            behavior: "smooth",
          });
        },
      }).success(function (response) {
        window.location.hash = "";
        window.location.reload();
      });
    });
  }

  function removeError() {
    if ($("#error-input-ld").length > 0) {
      $("#error-input-ld").remove();
    }
  }

  function createSelectRelationship() {
    return (
      '<div class="form-group">' +
      '<label class="col-sm-2 control-label" for="select-relationship">' +
      translate("Type de relation") +
      "</label>" +
      '<div class="col-sm-10">' +
      '<select class="form-control" name="select-relationship" id="select-relationship"  style="width: auto">\n' +
      '  <option value=""></option>\n' +
      '  <optgroup label="Basis">\n' +
      '  <option value="isBasedOn">isBasedOn</option>\n' +
      '  <option value="isBasisFor">isBasisFor</option>\n' +
      '  <option value="basedOnData">basedOnData</option>\n' +
      '  <option value="isDataBasisFor">isDataBasisFor</option>\n' +
      "  </optgroup>\n" +
      '  <optgroup label="Comment">\n' +
      '  <option value="isCommentOn">isCommentOn</option>\n' +
      '  <option value="hasComment">hasComment</option>\n' +
      "  </optgroup>\n" +
      '  <optgroup label="Continuation">\n' +
      '  <option value="isContinuedBy">isContinuedBy</option>\n' +
      '  <option value="continues">continues</option>\n' +
      "  </optgroup>\n" +
      '  <optgroup label="Derivation">\n' +
      '  <option value="isDerivedFrom">isDerivedFrom</option>\n' +
      '  <option value="hasDerivation">hasDerivation</option>\n' +
      "  </optgroup>\n" +
      '  <optgroup label="Documentation">\n' +
      '  <option value="isDocumentedBy">isDocumentedBy</option>\n' +
      '  <option value="documents">documents</option>\n' +
      "  </optgroup>\n" +
      '  <optgroup label="Funding">\n' +
      //'  <option value="finances">finances</option>\n' +
      '  <option value="isFinancedBy">isFinancedBy</option>\n' +
      "  </optgroup>\n" +
      '  <optgroup label="Part">\n' +
      '  <option value="isPartOf">isPartOf</option>\n' +
      '  <option value="hasPart">hasPart</option>\n' +
      "  </optgroup>\n" +
      '  <optgroup label="Peer review">\n' +
      '  <option value="isReviewOf">isReviewOf</option>\n' +
      '  <option value="hasReview">hasReview</option>\n' +
      "  </optgroup>\n" +
      '  <optgroup label="References">\n' +
      '  <option value="references">references</option>\n' +
      '  <option value="isReferencedBy">isReferencedBy</option>\n' +
      "  </optgroup>\n" +
      '  <optgroup label="Related material">\n' +
      '  <option value="hasRelatedMaterial">hasRelatedMaterial</option>\n' +
      '  <option value="isRelatedMaterial">isRelatedMaterial</option>\n' +
      "  </optgroup>\n" +
      '  <optgroup label="Reply">\n' +
      '  <option value="isReplyTo">isReplyTo</option>\n' +
      '  <option value="hasReply">hasReply</option>\n' +
      "  </optgroup>\n" +
      '  <optgroup label="Requirement">\n' +
      '  <option value="requires">requires</option>\n' +
      '  <option value="isRequiredBy">isRequiredBy</option>\n' +
      "  </optgroup>\n" +
      '  <optgroup label="Software compilation">\n' +
      '  <option value="isCompiledBy">isCompiledBy</option>\n' +
      '  <option value="compiles">compiles</option>\n' +
      "  </optgroup>\n" +
      '  <optgroup label="Supplement">\n' +
      '  <option value="isSupplementTo">isSupplementTo</option>\n' +
      '  <option value="isSupplementedBy">isSupplementedBy</option>\n' +
      "  </optgroup>\n" +
      "</select>\n" +
      "</div>" +
      "</div>"
    );
  }

  function createSelectTypeLd() {
    return (
        '<div class="form-group">'+
        '<label class="col-sm-2 control-label" for="select-ld-type">' +
        translate("Type de document") +
        "</label>" +
        '<div class="col-sm-10">' +
      '<select class="form-control" name="select-ld-type" id="select-ld-type" style="width: auto">\n' +
      '  <option value="publication">' +
      translate("Publication") +
      "</option>\n" +
      '  <option value="dataset">' +
      translate("Jeu de données") +
      "</option>\n" +
      '  <option value="software">' +
      translate("Logiciel") +
      "</option>\n" +
      "</select>\n" +
        "</div>"

    );
  }

  $("a#edit-ld").on("click", function () {
    const option = {};
    option.relationship = $(this).data("relationship");
    option.valueLd = $(this).data("ldval");
    option.idLd = $(this).data("ld");
    option.modifyForm = true;
    if ($(this).data("type") === "swhidId_s") {
      $(this).data("type", "software");
    }
    callAddForm($(this).data("type"), option);
  });
});

document.addEventListener("click", function (e) {
  // Check if the clicked element is a button with the class 'btn' and the 'data-ld' attribute
  if (e.target.closest("button.btn[data-ld]")) {
    let button = e.target.closest("button");

    // Get the confirmation message from the button's data-confirm-rm attribute
    let confirmMessage = translate("Merci de confirmer cette suppression");
    let answer = window.confirm(confirmMessage);

    if (answer) {
      let idLd = button.getAttribute("data-ld");
      let docId = document.querySelector("#paper_docId").value;
      let paperId = document.querySelector("#paper_id").value;

      // Scroll to the top before sending the request
      window.scroll({
        top: 0,
        left: 0,
        behavior: "smooth",
      });

      // Send the POST request using fetch
      fetch("/administratelinkeddata/removeld/", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
          credentials: "same-origin",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: new URLSearchParams({
          id: idLd,
          docId: docId,
          paperId: paperId,
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data[0] === true) {
            window.location.hash = "";
            window.location.reload();
          }
        })
        .catch((error) => console.error("Error:", error));
    }
  }
});
