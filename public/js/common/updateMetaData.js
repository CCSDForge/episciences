function updateMetaData(button, docId) {
    let $recordLoading = $("#record-loading");
    $recordLoading.html(getLoader());
    $recordLoading.show();

    $(button).unbind(); // Remove a previously-attached event handler from the elements

    let post = $.ajax({
        type: "POST",
        url: JS_PREFIX_URL + "paper/updaterecorddata",
        data: {docid: docId}
    });

    post.done(function (result) {

        try {
            let obj_result = JSON.parse(result);
            alert(obj_result.message);

            if (!('error' in obj_result) && obj_result.affectedRows !== 0) {
                location.reload();
            }

        } catch (error) {
            console.log(error);
        }

        $recordLoading.hide();

    });
}
