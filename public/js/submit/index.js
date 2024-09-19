$(document).ready(function () {
  let $searchDocRepoId = $("#search_doc-repoId");
  let $searchDocDocId = $("#search_doc-docId");
  let $versionBloc = $("#search_doc-version-element");
  let $isDataverseRepo = false;
  let $submitEntry = $("a[href='/submit/index']");

  setPlaceholder();

  $(window).on("load", function () {
    checkDataverse();

    if ($versionBloc.length > 0) toggleVersionBloc();
  });

  $searchDocRepoId.on("change", function () {
    toggleVersionBloc();
  });

  function toggleVersionBloc() {
    let repoValue = $searchDocRepoId.val();

    $searchDocDocId.val("");

    setPlaceholder();

    let hasHookRequest = ajaxRequest(JS_PREFIX_URL + "submit/ajaxhashook", {
      repoId: repoValue,
    });
    hasHookRequest.done(function (response) {
      let oResponse = JSON.parse(response);

      if (oResponse) {
        // initialized in submit/index.phtml
        hasHook = oResponse.hasHook;
        isRequiredVersion = oResponse.isRequiredVersion.result;

        if (!isRequiredVersion) {
          $versionBloc.hide();
        } else {
          $versionBloc.show();
        }

        if ($searchDocRepoId.val() === zenodoRepoId) {
          if (zSubmitStatus) {
            // to be enabled : @ see /config/dist-pwd.json
            insertZSubmitElement(zSubmitUrl);
          }
        } else {
          $("#z-submit-element").remove();
        }
      } else {
        $versionBloc.show();
      }
    });

    $searchDocRepoId.change(function () {
      checkDataverse();
    });
  }

  function setPlaceholder() {
    $searchDocDocId.attr(
      "placeholder",
      translate("exemple : ") + examples[$searchDocRepoId.val()],
    );
    $searchDocDocId.attr("size", $searchDocDocId.attr("placeholder").length);
  }

  // Extracting the ID from URL

  $searchDocDocId.change(function () {
    let input = $(this).val();

    if (isValidHttpUrl(input)) {
      let url = new URL(input);
      let identifier = url.pathname;
      let urlSearch = url.search;

      if (!$isDataverseRepo && urlSearch === "") {
        identifier = identifier.replace(/\/\w+\//, "");
        identifier = identifier.replace(/^\//, "");
      } else {
        identifier = urlSearch.replace("?persistentId=", "");
      }

      identifier = identifier.replace(/v\d+|(&version=\d+).\d+/, ""); // Delete VERSION from IDENTIFIER
      $(this).val(identifier);
    }
  });

  function checkDataverse() {
    let isDataverseRequest = ajaxRequest(JS_PREFIX_URL + "submit/ajaxisdataverse", {
      repoId: $searchDocRepoId.val(),
    });
    isDataverseRequest.done(function (response) {
      let oResponse = JSON.parse(response);
      $isDataverseRepo = oResponse.hasOwnProperty("isDataverse")
        ? oResponse.isDataverse
        : false;
      if ($submitEntry.length > 0) {
        let submitEntryTitle = $isDataverseRepo
          ? "Proposer un jeu de données"
          : "Proposer un article";
        $submitEntry.text(translate(submitEntryTitle));
      }
    });
  }
});
