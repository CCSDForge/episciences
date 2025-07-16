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
     input = $(this).val().trim();
    if (!input) {
      return;
    }

    let processedIdentifier;

    if (isValidHttpUrl(input)) {
      processedIdentifier = processUrlIdentifier(input);
    } else {
      processedIdentifier = processDirectIdentifier(input);
    }
    $(this).val(processedIdentifier);
  });

  function processUrlIdentifier(input) {
    try {
      const url = new URL(input);
      let identifier = url.pathname;
      const urlSearch = url.search;

      if (!$isDataverseRepo && urlSearch === "") {
        identifier = identifier.replace(/\/\w+\//, "").replace(/^\//, "");
      } else {
        identifier = urlSearch.replace("?persistentId=", "");
      }

      return removeVersionFromIdentifier(identifier);
    } catch (error) {
      return input;
    }
  }

  function processDirectIdentifier(input) {
    return removeVersionFromIdentifier(input);
  }

//Removes version information from an identifier string.
  function removeVersionFromIdentifier(identifier) {
    // Remove "v1", "v2", etc. at the end
    return identifier.replace(/v\d+$/, '')
  }


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
