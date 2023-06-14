$(function () {
    visualizeBiblioRefs();
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
    $("#hide-biblio-refs").hide();
    let alreadyCalled = false;
    $("#visualize-biblio-refs").click(function (e){
        if (!alreadyCalled){
            $.ajax({
                url: $(this).data("api")+"/visualize-citations?url="+$(this).val(),
                dataType: "json",
            }).success(function (response) {
                $.each(response,function(i,obj){
                    if (obj.ref !== undefined){
                        let strBiblioRef = ''
                        let parsedRawRef = JSON.parse(obj.ref);
                        console.log(parsedRawRef.raw_reference);
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
        }else{
            $("#biblio-refs-container").show();
        }
        alreadyCalled = true;
        $("#visualize-biblio-refs").hide();
        $("#hide-biblio-refs").show();
    });
}

function processBiblioRefs(){
    $("#hide-process-biblio-refs").hide();
    let alreadyCalled = false;
    $("#process-biblio-refs").click(function (e){
        if (!alreadyCalled){
            $.ajax({
                url: $(this).data( "api" )+"/process-citations?url="+$(this).val(),
                dataType: "json",
            }).success(function (response) {
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
        $("#process-biblio-refs").hide();
        $("#hide-process-biblio-refs").show();
    });
}