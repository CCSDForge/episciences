function %%FCT_NAME%% (btn, name) {
	var empty = $(btn).closest('.textarea-group').find('textarea').val() == "";      
	if (!empty) {
		var value = $(btn).closest(".textarea-group").find('textarea').val();

        var container = $(btn).closest(".textarea-group").parent();
        var inputGroup = $(container).find('.textarea-group:last');
        var clone = $(inputGroup).clone();		
        var lang = $(clone).find('.btn-group > button').val();

        $(clone).attr('style', $(clone).attr('style') + ' margin-top: 45px;');
        $(clone).find('textarea').attr('lang', lang);
        $(clone).find('textarea').attr('name', name + "[" + lang + "]");
        $(clone).find('textarea').val(value);
        $(clone).find('textarea').attr('data-language', lang);
		$(clone).find(".errors").remove();
		$(clone).find('.glyphicon-plus').removeClass("glyphicon-plus").addClass("glyphicon-trash").parent().attr('onclick', '%%DELETE%%(this,"' + name + '")');
		$(clone).find('.glyphicon-trash').parent('button').attr('title', 'Supprimer');
		
		$(clone).insertBefore($(container).find('> :last'));

		$(container).find('.textarea-group .btn-group').each(function (i) {
        	$(this).find('ul li a[val=' + lang + ']').closest('li').addClass('disabled');
        });

		var elm = $(inputGroup).find('.btn-group > ul li[class!="disabled"]:first a');
        if (typeof $(elm).html() != 'undefined') {
            $(inputGroup).find('.btn-group > button').val($(elm).attr('val'));
            $(inputGroup).find('textarea').attr('name', name + "[" + $(elm).attr('val') + "]");
            var textNode = $(inputGroup).find('.btn-group > button').contents().first();
            textNode.replaceWith($(elm).text());
        } else {
        	$(inputGroup).find('textarea').attr('name', '');
        	$(inputGroup).find('textarea').attr('disabled', 'disabled');
            $(inputGroup).hide();
        }

		$(btn).closest('.textarea-group').find('textarea:first').val("");    
		$(btn).closest('.textarea-group').find('textarea:first').focus();
	}
}  