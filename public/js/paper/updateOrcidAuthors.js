function updateOrcidAuthors() {
    let authors = $("div#paper-authors").text();
    let orcidExisting = $("div#orcid-author-existing").text();
    authors = authors.substring(authors.indexOf(":") + 1);
    authors = authors.split(';');
    authors = authors.map(Function.prototype.call, String.prototype.trim);
    orcidExisting = orcidExisting.split("##");
    let arrayMergeAuOr = [];
    authors.forEach(function (author, index) {
        let tmp = [author, orcidExisting[index]]
        arrayMergeAuOr.push(tmp);
    });
    arrayMergeAuOr.forEach(function (author, index) {
        let fullname = author[0];
        let orcid = author[1];
        if (orcid === 'NULL') {
            orcid = '';
        }
        // append one time even if modal is recalled
        if ($("input#modal-called").val() === "0") {
            let orcidReg = '\\d{4}-\\d{4}-\\d{4}-\\d{3}(?:\\d|X)';
            let htmlrow = "<div style='margin-bottom: 15px;'><span id=fullname__"+index+ " style='width=100%;height: auto;'>" + fullname + "</span><input id=ORCIDauthor__"+index+" pattern="+orcidReg+" placeholder=1111-2222-3333-4444 style='float: right; overflow: hidden' value=" + orcid + "></div>";
            $(htmlrow).appendTo("#modal-body-authors");
        }
    });

    $("input#modal-called").val("1");
}
$(document).ready(function() {
    $("form#post-orcid-author").submit(function (e) {
        let url = $(this).attr('action');
        let arrayAuthor = [];
        let arrayOrcid =  [];
        let arrayMerge = [];
        $("span[id^='fullname__']").each(function (i, el) {
            let fullname = $(el).text();
            arrayAuthor.push(fullname);
        });
        $("input[id^='ORCIDauthor__']").each(function (i, el) {
            let orcid = $(el).val();
            if (!orcid) {
                arrayOrcid.push('');
            } else {
                arrayOrcid.push(orcid);
            }
        });

        arrayAuthor.forEach(function (author, index) {
            let tmp = [author, arrayOrcid[index]]
            arrayMerge.push(tmp);
        });
        let dataT = {
            "paperid": $("div#paperid-for-author").text(),
            "authors":arrayMerge};
        $.ajax({
            url: url,
            type: 'POST',
            data: JSON.stringify(dataT),
            success: function success(result) {
                window.location.reload();
            }
        });
        e.preventDefault();
    });

});