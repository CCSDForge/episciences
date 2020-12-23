function %%FCT_NAME%% (btn, name) {
	var empty = $(btn).closest('div').find('input').val() == "";      
	if (!empty) {
		$.ajax({
			url     : "/ajax/ajaxgetreferentiel?idType=",
			type    : "POST",
			async   : false,
			data : $('#form_meta').serializeArray(),
			success : function (msg) {
						var j = $.parseJSON (msg);
						if (j['success']) {
							$("#panel-body-meta").html(j['form']);
						} else {
							$(j['errors']).filter('.modal').modal({keyboard : true});
						}
				
		 			  }
		});
	}
}  
