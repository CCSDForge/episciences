$(function () {
    let actualBtn = document.getElementById('upload-gabs');
    let fileChosen = document.getElementById('file-chosen');
    let formBtn = $("#i-graph-abs");
    let cancelBtn = $("#btn-cancel-graph");
    formBtn.hide();
    cancelBtn.hide();
    actualBtn.addEventListener('change', function(){
        fileChosen.textContent = this.files[0].name;
        formBtn.show();
        cancelBtn.show();
    });
    cancelBtn.on('click', function(){
        actualBtn.value = "";
        fileChosen.textContent = "";
        formBtn.hide();
        cancelBtn.hide();
    })
    $('#f-graph-abs').submit(function(e){
        e.preventDefault();
        let file_data = $('#upload-gabs').prop('files')[0];
        let form_data = new FormData();
        form_data.append('file', file_data);
        form_data.append('docId', $('#paper_docId').val());
        form_data.append('paperId', $('#paper_id').val());
        $.ajax({
            url: "/administrategraphabstract/addgraphabs/",
            dataType: 'text',
            cache: false,
            contentType: false,
            processData: false,
            data: form_data,
            type: 'post',
            success: function(e){
               location.reload();
            }
        });

    });
})