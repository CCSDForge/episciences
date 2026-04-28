function %%FCT_NAME%% (btn, name) {
	var empty = $(btn).closest('.textarea-group').find('textarea').val() == "";      
	if (!empty) {
		var value = $(btn).closest(".textarea-group").find('textarea').val();

        var container = $(btn).closest(".textarea-group").parent();
        var inputGroup = $(container).find('.textarea-group:last');
        var clone = $(inputGroup).clone();		
        var lang = $(clone).find('.btn-group > button').val();

        $(clone).attr('style', $(clone).attr('style') + ' margin-top: 45px;');
        $(clone).find('textarea').attr('name', name + "[" + lang + "]");
        $(clone).find('textarea').attr('lang', lang);
        $(clone).find('textarea').val(value);
		$(clone).find(".errors").remove();
		$(clone).find('.glyphicon-plus').removeClass("glyphicon-plus").addClass("glyphicon-trash").parent().attr('onclick', '%%DELETE%%(this)');
		$(clone).find('.glyphicon-trash').parent('button').attr('title', 'Supprimer');
		$(clone).insertBefore($(container).find('> :last'));

		$(btn).closest('.textarea-group').find('textarea:first').val("");   
		$(btn).closest('.textarea-group').find('textarea:first').focus();
	}
}  