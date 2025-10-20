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
    checkDataverse();
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
  }

  function setPlaceholder() {
    const searchDocDocId = document.getElementById("search_doc-docId");
    const searchDocRepoId = document.getElementById("search_doc-repoId");
    
    if (searchDocDocId && searchDocRepoId) {
      const placeholderText = translate("exemple : ") + examples[searchDocRepoId.value];
      searchDocDocId.setAttribute("placeholder", placeholderText);
      searchDocDocId.setAttribute("size", placeholderText.length);
    }
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
      let identifier = "";
      let versionFromUrl = null;
      
      // Handle different repository types
      if ($isDataverseRepo || url.search) {
        // For Dataverse repos or URLs with query parameters
        if (url.search.includes("persistentId=")) {
          identifier = url.searchParams.get("persistentId") || 
                      url.search.replace(/^\?.*persistentId=/, "").split("&")[0];
          
          // Check for version parameter in URL
          const versionParam = url.searchParams.get("version");
          if (versionParam) {
            versionFromUrl = versionParam;
            const versionField = document.getElementById('search_doc-version');
            if (versionField) {
              // Keep full version number (e.g. "1.1", "2.0", "1.5")
              versionField.value = versionParam;
            }
          }
        } else {
          // Fallback to pathname if no persistentId found
          identifier = url.pathname.replace(/^\/+|\/+$/g, "");
        }
      } else {
        // For non-Dataverse repos without query parameters
        if (url.hostname === 'arxiv.org' && url.pathname.includes('/abs/')) {
          // Special handling for ArXiv URLs: extract only the ArXiv ID
          // For new format (e.g., 2310.02192), use as-is
          // For old format (e.g., hep-th/9704188, alg-geom/9202002), extract only the final identifier
          identifier = url.pathname.replace(/^\/abs\//, '');
          // If it contains a slash (old format), take only the part after the last slash
          if (identifier.includes('/')) {
            identifier = identifier.split('/').pop();
          }
        } else {
          // General handling for other repos
        // Remove leading path segments and slashes
        identifier = url.pathname
          .replace(/^\/+/, "")           // Remove leading slashes
          .replace(/\/\w+\/$/, "")       // Remove trailing /word/ pattern
          .replace(/\/+$/, "");          // Remove trailing slashes
      }
      }

      // Clean up empty identifier
      if (!identifier.trim()) {
        identifier = url.pathname.replace(/^\/+|\/+$/g, "") || url.href;
      }

      // Only call removeVersionFromIdentifier if we didn't already handle version from URL params
      if (versionFromUrl) {
        return identifier; // Don't process further since we already handled the version
      } else {
        return removeVersionFromIdentifier(identifier);
      }
    } catch (error) {
      // If URL parsing fails, return the original input
      console.warn("URL parsing failed for:", input, error);
      return removeVersionFromIdentifier(input);
    }
  }

  function processDirectIdentifier(input) {
    return removeVersionFromIdentifier(input);
  }

//Extracts version information from an identifier string and populates the version field.
  function removeVersionFromIdentifier(identifier) {
    const versionField = document.getElementById('search_doc-version');
    const versionMatch = identifier.match(/v(\d+)$/);
    
    if (versionMatch && versionField) {
      // Extract the version number (without the 'v' prefix)
      versionField.value = versionMatch[1];
      
      // Return identifier without the version
      return identifier.replace(/v\d+$/, '');
    }
    
    // If no version found, clear the version field and return original identifier
    if (versionField) {
      versionField.value = '';
    }
    return identifier;
  }


  function checkDataverse() {
    const searchDocRepoId = document.getElementById("search_doc-repoId");
    const submitEntry = document.querySelector("a[href='/submit/index']");
    
    if (!searchDocRepoId) return;
    
    const formData = new FormData();
    formData.append("repoId", searchDocRepoId.value);
    
    fetch(JS_PREFIX_URL + "submit/ajaxisdataverse", {
      method: "POST",
      headers: {
        "X-Requested-With": "XMLHttpRequest"
      },
      body: formData
    })
    .then(response => response.text())
    .then(responseText => {
      const oResponse = JSON.parse(responseText);
      $isDataverseRepo = oResponse.hasOwnProperty("isDataverse")
        ? oResponse.isDataverse
        : false;
      
      if (submitEntry) {
        const submitEntryTitle = $isDataverseRepo
          ? "Proposer un jeu de données"
          : "Proposer un article";
        submitEntry.textContent = translate(submitEntryTitle);
      }
    })
    .catch(error => {
      console.error("Error checking dataverse:", error);
    });
  }
});
