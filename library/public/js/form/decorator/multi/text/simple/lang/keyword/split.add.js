function %%FCT_NAME%% (btn, name) {
    var s = $(btn).closest('div').find('input').val();
	var empty = s == "";      
	if (!empty) {
		var r = new RegExp (",|;", "g");
		matches = s.split(r);

		for (i in matches) {                    
		    s = matches[i].replace(/^"(.*)"$/, '$1').replace(/^'(.*)'$/, '$1').trim();
		    if (s != "") {

		    	var value = s;
		        value = value.replace(new RegExp("(>)", "g"), '&gt;').replace(new RegExp("(<)", "g"), '&lt;');
		        
		        if (%%LENGTH%%) {
		        	value = value.substring(0,%%LENGTH%%) + (value.length > %%LENGTH%% ? '...' : '')
		        }
		        
		        var container = $(btn).closest("div").parent();
		        var inputGroup = $(container).find('.input-group:last');
		        var clone = $(inputGroup).clone();		
		        var lang = $(clone).find('.btn-group > button').val();
		        
		        $(clone).find('input').attr('type', 'text');
		        $(clone).find('input').attr('name', name + "[" + lang + "][]");
		        $(clone).find('input').attr('lang', lang);
		        $(clone).find('input').val(value);
		    	$(clone).find(".errors").remove();
		    	$(clone).find('.glyphicon-plus').removeClass("glyphicon-plus").addClass("glyphicon-trash").parent().attr('onclick', '%%DELETE%%(this)');

		    	$(clone).insertBefore($(container).find('> :last'));
		    }
		}       
		$(btn).closest('div').find('input:first').val("");
		$(btn).closest('div').find('input:first').focus();
	}
}  