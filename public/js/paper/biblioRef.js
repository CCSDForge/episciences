$(function () {
    if ($("#visualize-biblio-refs").length) {
        visualizeBiblioRefs();
    }
    hideBiblioRefs();
    processBiblioRefs();
});

function hideBiblioRefs(){
    $("#hide-biblio-refs").click(function (){
        $("#biblio-refs-container").hide();
        $("#visualize-biblio-refs").show();
        $("#hide-biblio-refs").hide();
    });
    $("#hide-process-biblio-refs").click(function (){
        $("#process-biblio-refs-container").hide();
        $("#process-biblio-refs").show();
        $("#hide-process-biblio-refs").hide();
    });
}
function visualizeBiblioRefs(){
    let alreadyCalled = false;
        if (!alreadyCalled){
            $.ajax({
                url: $("#visualize-biblio-refs").data("api")+"/visualize-citations?url="+$("#visualize-biblio-refs").data("value"),
                dataType: "json",
            }).success(function (response) {
                $.each(response,function(i,obj){
                    if (obj.ref !== undefined) {
                        let strBiblioRef = ''
                        let parsedRawRef = JSON.parse(obj.ref);
                        strBiblioRef+= parsedRawRef.raw_reference;
                        if (parsedRawRef.doi !== undefined){
                            strBiblioRef+= " "+"<a href='https://doi.org/"+parsedRawRef.doi+"' rel='noopener' target='_blank'>"+parsedRawRef.doi+"</a>"
                        }
                        $( "<div>"+strBiblioRef+"</div><br>" ).appendTo( "#biblio-refs-container" );

                    }
                });
                if (response.message !== undefined){
                    $( "<div>"+response.message+"</div>" ).appendTo( "#biblio-refs-container" );
                }
            }).error(function (xhr){
                let apiResponse = JSON.parse(xhr.responseText);
                $( "<div>"+apiResponse.message+"</div>" ).appendTo( "#biblio-refs-container" );
            });
            alreadyCalled = true;
        }

}

function processBiblioRefs(){
    $("#hide-process-biblio-refs").hide();
    $("#epi-citation-app").hide();
    $("#loading-biblio").hide();
    let alreadyCalled = false;
    $("#process-biblio-refs").click(function (e){
        if (!alreadyCalled){
            $.ajax({
                url: $(this).data( "api" )+"/process-citations?url="+$(this).val(),
                dataType: "json",
                beforeSend: function() {
                    // setting a timeout
                    $("#process-biblio-refs").hide();
                    $("#loading-biblio").show();
                },
            }).success(function (response) {
                $("#loading-biblio").hide();
                $.each(response,function(i,obj){
                    if (obj.ref !== undefined){
                        let strBiblioRef = ''
                        let parsedRawRef = JSON.parse(obj.ref);
                        strBiblioRef+= parsedRawRef.raw_reference;
                        if (parsedRawRef.doi !== undefined){
                            strBiblioRef+= " "+"<a href='https://doi.org/"+parsedRawRef.doi+"' rel='noopener' target='_blank'>"+parsedRawRef.doi+"</a>"
                        }
                        $( "<div>"+strBiblioRef+"</div><br>" ).appendTo( "#process-biblio-refs-container" );

                    }
                });
                if (response.message !== undefined) {
                    $( "<div>"+response.message+"</div>" ).appendTo( "#process-biblio-refs-container");
                }
            }).error(function (xhr){
                let apiResponse = JSON.parse(xhr.responseText);
                $( "<div>"+apiResponse.message+"</div>" ).appendTo( "#process-biblio-refs-container");
            });
            alreadyCalled = true;
        } else {
            $("#process-biblio-refs-container").show();
        }

        setTimeout(
            function()
            {
                $("#process-biblio-refs").hide();
                $("#hide-process-biblio-refs").show();
                $("#epi-citation-app").show();
            }, 1000);

    });
}