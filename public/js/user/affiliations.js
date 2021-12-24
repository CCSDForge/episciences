$(function () {

    let $affiliations = $('#affiliations');
    let cache = [];

    $affiliations.autocomplete({
        source: function (request, response) {

            let term = request.term;
            let availableAffiliations = [];

            if (term in cache) {
                response(cache[term]);
                return;
            }

            let url = 'https://api.ror.org/organizations?query=' + term;

            let ajaxReq =  ajaxRequest(url, {}, 'GET');

           ajaxReq.done(function (rorResponse) {

               if( 'items' in rorResponse){

                   rorResponse.items.forEach(function (item) {
                       availableAffiliations.push({'label': item.name + ' #' + item.id, 'identifier': item.id});
                   });

                   cache[term] = availableAffiliations;
                   response(availableAffiliations);
               }

            });

        },

        focus: function () { // At focus, we display the results in cache
            $(this).autocomplete("search", this.value);
        },

        open: function () {
            $(this).autocomplete('widget').css('z-index', 2).css('position', 'absolute');
            return false;
        }


    });


});
