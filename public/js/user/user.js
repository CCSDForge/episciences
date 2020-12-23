$(document).ready(function() {

	$("#delete-photo").click(function() {
        var ajax = $.ajax({
			url : "/user/ajaxdeletephoto",
			type : "POST",
			dataType : "html",
            data: {uid: $(this).attr('attr-uid')},
			success : function(data) {
                if (data == '1') {
                    $(".user-photo-normal, .user-photo-thumb").fadeOut("slow");
                }
                $(".user-photo").fadeOut("slow");
                $("#delete-photo").addClass('hidden');
                message('Photo supprimée.', 'alert-success');
			},
			error : message('La suppression a échoué.', 'alert-danger')
		});
		return false;
	});

});
