function %%FCT_NAME%% (btn, name) {
	var empty = $(btn).closest('div').find('input').val() == "";      
	if (!empty) {
		var value = $(btn).closest("div").find('input').val();
        
		var container = $(btn).closest("div").parent();
        var inputGroup = $(container).find('.input-group:last');
        var clone = $(inputGroup).clone();		
        var lang = $(clone).find('.btn-group > button').val();
        
        $(clone).find('input').attr('type', 'text');
        $(clone).find('input').attr('lang', lang);
        $(clone).find('input').attr('name', name + "[" + lang + "]");
        $(clone).find('input').attr('data-language', lang);
        $(clone).find('input').val(value);
		$(clone).find(".errors").remove();
		$(clone).find('.glyphicon-plus').removeClass("glyphicon-plus").addClass("glyphicon-trash").parent().attr('onclick', '%%DELETE%%(this)');
		$(clone).find('.glyphicon-trash').parent('button').attr('title', 'Supprimer');

		$(clone).insertBefore($(container).find('> :last'));

		$(container).find('.input-group .btn-group').each(function (i) {
        	$(this).find('ul li a[val=' + lang + ']').closest('li').addClass('disabled');
        });

		var elm = $(inputGroup).find('.btn-group > ul li[class!="disabled"]:first a');
        if (typeof $(elm).html() != 'undefined') {
            $(inputGroup).find('.btn-group > button').val($(elm).attr('val'));
            $(inputGroup).find('input').attr('name', name + "[" + $(elm).attr('val') + "]");
            var textNode = $(inputGroup).find('.btn-group > button').contents().first();
            textNode.replaceWith($(elm).text());
        } else {
        	$(inputGroup).find('input:first').attr('disabled', 'disabled');
            $(inputGroup).hide();
        }

		$(btn).closest('div').find('input:first').val("");   
		$(btn).closest('div').find('input:first').focus();   
	}
}  