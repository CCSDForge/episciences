$(function () {
  let flagError = 0;

  function callAddForm(typeld, option = []) {
    removeFormLd();

    $.ajax({
      type: "POST",
      url: JS_PREFIX_URL + "administratelinkeddata/ajaxgetldform/",
      data: {
        typeld: typeld,
        option: option,
      },
    }).success(function (response) {
      $("#container-manager-linkeddatas").append(response);
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

  function changePlaceholder(typeLd = "unnamed") {
    const exTranslated = translate("Exemple : ");
    const inputLd = document.getElementById("input-ld");
    const labelValue = document.getElementById("input-ld-label");

    if (typeLd === "unnamed") {
      typeLd = "dataset";
    }
    if (typeLd === "publication") {
      labelValue.textContent = translate("Publication");
      inputLd.setAttribute(
        "placeholder",
        exTranslated + "https://doi.org/10.48550/arXiv.2103.16574",
      );
    } else if (typeLd === "software") {
      labelValue.textContent = translate("Logiciel");
      inputLd.setAttribute(
        "placeholder",
        exTranslated + "swh:1:dir:ebaa23a36a1a72a2362f34d14f44997e8392671b",
      );
    } else if (typeLd === "dataset") {
      labelValue.textContent = translate("Jeu de données");
      inputLd.setAttribute(
        "placeholder",
        exTranslated + "https://doi.org/10.57745/MQDGI6",
      );
    }
  }

  $("button#add-linkdata").on("click", function () {
    removeFormLd();
    callAddForm("dataset");
    changePlaceholder("dataset");
  });

  $("#anchor-dataset-add").on("click", function () {
    removeFormLd();
    callAddForm("dataset");
    changePlaceholder("dataset");
  });
  $("#anchor-software-add").on("click", function () {
    removeFormLd();
    callAddForm("software");
    changePlaceholder("software");
  });
  $("#anchor-publication-add").on("click", function () {
    removeFormLd();
    callAddForm("publication");
    changePlaceholder("publication");
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
        let text = translate("Veuillez sélectionner un type de relation");
        $("#container-datasets").after(
          "<i id='error-relationship' class='pull-right' style='color: red;'>" +
            text +
            "</i>",
        );
        return;
      }
      $.ajax({
        type: "POST",
        url: JS_PREFIX_URL + "administratelinkeddata/setnewinfold/",
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

      let textError = "";
      $("#error-form-ld").remove();
      if (!typeLd) {
        textError = translate("Le type de document est obligatoire");
      }

      if (!relationship) {
        textError =
          textError + "<br>" + translate("Le type de relation est obligatoire");
      }

      if (!valueLd) {
        textError =
          textError + "<br>" + translate("Le document à lier est obligatoire");
      }

      if (textError !== "") {
        $("#container-datasets").after(
          "<i id='error-form-ld' class='pull-right' style='color: red;'>" +
            textError +
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
        url: JS_PREFIX_URL + "administratelinkeddata/addld/",
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
      fetch(JS_PREFIX_URL + "administratelinkeddata/removeld/", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
          "credentials": "same-origin",
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
