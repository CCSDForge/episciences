function updateMetaData(button, docId) {
    let $recordLoading = $("#record-loading");
    $recordLoading.html(getLoader());
    $recordLoading.show();

    $(button).unbind(); // Remove a previously-attached event handler from the elements

    let post = $.ajax({
        type: "POST",
        url: "/paper/updaterecorddata",
        data: {docid: docId}
    });

    post.done(function (result) {
        let obj_result = JSON.parse(result);

        $recordLoading.hide();
        alert(obj_result.message);

        if (!('error' in obj_result) && obj_result.affectedRows !== 0) {
            location.reload();
        }

    });
}
