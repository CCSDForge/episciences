if (typeof("add_ref") != "function") {
	function add_ref (a, b, c)
	{
		if (typeof c == "undefined") {
			$.ajax({
				url  : "/ajax/ajaxgetreferentiel/element/" + b + "/type/" + a + "/new/true",
				type : "post",
				data : $(".modal-body form").serializeArray(),
				async : false,
				success : function (msg) {
					if ($(msg).find(".modal-body").length) {
						$(msg).filter('.modal').modal({keyboard : true});
					} else {
						$(msg).insertBefore($("#" + b));
						
						if (!$("#" + b).attr('multiple')) {
							$("#" + b).attr('disabled', 'disabled').hide();
						}
					}
				}
			});
		} else {
			$(c).insertBefore($("#" + b));
			
			if (!$("#" + b).attr('multiple')) {
				$("#" + b).attr('disabled', 'disabled').hide();
			}
		}
	}
}
