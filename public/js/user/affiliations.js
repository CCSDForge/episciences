$(function () {

    let $affiliations = $('#affiliations');
    let cache = [];
    let cacheAcronym = [];
    $affiliations.autocomplete({
        source: function (request, response) {

            let term = request.term;
            let availableAffiliations = [];

            if (term in cache) {
                response(cache[term]);
                return;
            }

            let url = 'https://api.ror.org/organizations?affiliation=' + term;

            let ajaxReq = ajaxRequest(url, {}, 'GET');

            $affiliations.before(getLoaderAffi());

            ajaxReq.done(function (rorResponse) {

                $('.loader-affi').remove();

                if ('items' in rorResponse) {

                    rorResponse.items.forEach(function (item) {
                        let additionnalInfo = "";
                        if (item.matching_type === "ACRONYM") {
                            additionnalInfo = "["+item.organization.acronyms[0]+"]";
                            cacheAcronym.push(additionnalInfo);
                            cacheAcronym = [... new Set(cacheAcronym)];
                        }
                        availableAffiliations.push({'label': item.organization.name + ' ' + additionnalInfo + ' #' + item.organization.id, 'identifier': item.organization.id, 'acronym': additionnalInfo});
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

    $('button[data-original-title="Add"]').on('click',function (e) {
        if ($("input#affiliationAcronym").length) {
            let strAcronym = "";
            let numberOfAcronyms = cacheAcronym.length;
            let i = 1;
            cacheAcronym.forEach(function (acronym){
                strAcronym += acronym
                if (numberOfAcronyms !== i) {
                    strAcronym += "||";
                }
                i++;
            })
            $("input#affiliationAcronym").val(strAcronym);
        }
    });
});
