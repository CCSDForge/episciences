function %%FCT_NAME%% (btn, name) {
    var s = $(btn).closest('div').find('input').val();
	var empty = s == ""; 
	if (!empty) {
		var clone = $(btn).closest("div").clone();		
		$(clone).find('input').attr('type', 'text');
		$(clone).find('input').val(s);
		$(clone).find(".errors").remove();
		$(clone).find('.glyphicon-plus').removeClass("glyphicon-plus").addClass("glyphicon-trash").parent().attr('onclick', '%%DELETE%%(this)');
		$(clone).find('.glyphicon-trash').parent('button').attr('title', 'Supprimer');

		$(clone).insertBefore($(btn).closest('div').parent().find('> :last'));
		
		$(btn).closest('div').find('input:first').val("");
		$(btn).closest('div').find('input:first').focus();
	}
}  