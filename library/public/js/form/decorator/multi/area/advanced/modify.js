function %%FCT_NAME%% (btn, name) {
	var container = $(btn).closest('.textarea-group');
	
	var len = $(container).parent().find('.glyphicon').length;
	$(container).parent().find('.glyphicon').each (function (i) {
		if (i != (len -1)) {
			$(this).parent().addClass('disabled');
		}
	});

	var inputGroup = $(container).parent().find('.textarea-group:last');
	
	$(container).find("span").removeClass("label-primary").addClass("label-warning");
	$(container).find('button').removeClass("btn-primary").addClass("btn-warning"); 
	
	$(inputGroup).find("textarea").val($(container).find('textarea').val());

	var clone = $(inputGroup).find(".glyphicon-plus").closest("button").clone();
	$(inputGroup).find(".glyphicon-plus").closest("button").hide();
	$(clone).find(".glyphicon-plus").removeClass("glyphicon-plus").addClass("glyphicon-ok");
	$(clone).attr('onclick', '%%VALID%%(this, "'+ name +'");');
	$(clone).attr('title', 'Valider la modification');
	
	$(clone).insertAfter($(inputGroup).find(".glyphicon-plus").closest("button"));
}