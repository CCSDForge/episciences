function %%FCT_NAME%% (btn, name) {
	var container = $(btn).closest('div');
	
	var len = $(container).parent().find('.glyphicon').length;
	$(container).parent().find('.glyphicon').each (function (i) {
		if (i != (len -1)) {
			$(this).parent().addClass('disabled');
		}
	});
	
	var inputGroup = $(container).parent().find('.input-group:last');

	$(container).find("span").removeClass("label-primary").addClass("label-warning");
	$(container).find('button').removeClass("btn-primary").addClass("btn-warning"); 
	
	$(inputGroup).find("input").val($(container).find('input').val());

	var lang = $(container).find('input').attr('lang');
	$(inputGroup).find('.btn-group').val(lang);
	
	var textNode = $(inputGroup).find('.btn-group > button').contents().first();
	textNode.replaceWith($(inputGroup).find('ul li a[val=' + lang + ']').closest('li').text()); 
	
	$(inputGroup).find('.btn-group > button').val(lang);
	
	var clone = $(inputGroup).find(".glyphicon-plus").closest("span").clone();
	$(inputGroup).find(".glyphicon-plus").closest("span").hide();
	$(clone).find(".glyphicon-plus").removeClass("glyphicon-plus").addClass("glyphicon-ok");
	$(clone).find("button").attr('onclick', '%%VALID%%(this, "'+ name +'");');
	$(clone).find("button").attr('title', 'Valider la modification');
	
	$(clone).insertAfter($(inputGroup).find(".glyphicon-plus").closest("span"));
}