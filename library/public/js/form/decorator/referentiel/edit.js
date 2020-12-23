if (typeof("edit_ref") != "function") {
	function edit_ref (a, b, c)
	{
		var url = "/ajax/ajaxgetreferentiel/element/" + b + "/type/" + a + "/edit/true";

		var options = {
            url : url,
            async: false,
            type : "post"
        };

		if (typeof c == "undefined") {
			options = $.extend (options, {
				url : url + "/valid/true",
				data : $(".modal-body form").serializeArray(),
				success : function (msg) {
					$(document).find('.form-group div[modify]').parent().replaceWith(msg);
				}
			});
		} else {
			$(c).closest('.form-group').find('div[modify]').removeAttr('modify');
			var e = $(c).closest('.referentiel');
			$(e).attr('modify', 'modify');

			options = $.extend(options, {
				data : $(c).closest('.referentiel').find(':input').serialize(),
				success : function (msg) {
					$(this).attr('disabled', 'disabled'); 
                    $(msg).filter('.modal').modal({keyboard : true}).on('hidden.bs.modal', function() { $(e).removeAttr('modify'); });
				}
			});
		}
		
		$.ajax(options);
	}
}