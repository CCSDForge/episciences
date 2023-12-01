$(function () {
    visualizeBiblioRefs();
});

function visualizeBiblioRefs() {
    if ($("#visualize-biblio-refs").length) {
        let alreadyCalled = false;
        if (!alreadyCalled) {
            let url = $("#visualize-biblio-refs").data("api") + "/visualize-citations?url=" + $("#visualize-biblio-refs").data("value");
            if ($("#visualize-biblio-refs").data("all")) {
                url += "&all=" + $("#visualize-biblio-refs").data("all");
            }
            $.ajax({
                url: url,
                dataType: "json",
            }).success(function (response) {
                $.each(response, function (i, obj) {
                    if (obj.ref !== undefined) {
                        let strBiblioRef = '';
                        let parsedRawRef = JSON.parse(obj.ref);
                        let isAuthorizedToSeeAcc = false;
                        if (typeof $("#visualize-biblio-refs").data('all') !== 'undefined' && $("#visualize-biblio-refs").data('all') === 1) {
                            isAuthorizedToSeeAcc = true
                        }
                        if (obj.isAccepted === 1 && isAuthorizedToSeeAcc) {
                            strBiblioRef += "<i class=\"fa-sharp fa-solid fa-check\" style='color: #009527;'></i> ";
                        }
                        strBiblioRef += parsedRawRef.raw_reference;
                        if (parsedRawRef.doi !== undefined) {
                            strBiblioRef += " " + "<a href='https://doi.org/" + parsedRawRef.doi + "' rel='noopener' target='_blank'>" + parsedRawRef.doi + "</a>"
                        }
                        $("<div>" + strBiblioRef + "</div><br>").appendTo("#biblio-refs-container");

                    }
                });
                if (response.message !== undefined) {
                    // $( "<div>"+response.message+"</div>" ).appendTo( "#biblio-refs-container" );
                    alreadyCalled = true;
                } else {
                    $("#biblio-refs").show();
                }
            }).error(function (xhr) {
                try {
                    let apiResponse = JSON.parse(xhr.responseText);
                    $("<div>" + apiResponse.message + "</div>").appendTo("#biblio-refs-container");

                } catch (e) {
                    console.log(e.name + " : " + e.message);
                }

            });
        }
    }
}