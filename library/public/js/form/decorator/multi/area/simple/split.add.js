function %%FCT_NAME%% (btn, name) {
	var div = $(btn).closest('.textarea-group');
    var s = $(div).find('textarea').val();
	var empty = s == "";
	
	if (!empty) { 
		var r = new RegExp (",|;", "g");
		matches = s.split(r);

		for (i in matches) {
		    s = matches[i].replace(/^"(.*)"$/, '$1').replace(/^'(.*)'$/, '$1').trim();
		    if (s != "") {
				var clone = $(div).clone();		

				$(clone).attr('style', $(clone).attr('style') + ' margin-top: 45px;');
				
				$(clone).find('textarea').val(s);
				$(clone).find(".errors").remove();
				$(clone).find('.glyphicon-plus').removeClass("glyphicon-plus").addClass("glyphicon-trash").parent().attr('onclick', '%%DELETE%%(this)');
				
				$(clone).insertBefore($(div).parent().find('> :last'));
				
				$(div).find('textarea:first').val("");
		    }
		}
		$(div).find('textarea:first').val("");
		$(div).find('textarea:first').focus();
	}
}  