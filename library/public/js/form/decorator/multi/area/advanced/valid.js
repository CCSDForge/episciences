function %%FCT_NAME%% (btn, name, fct) {
	var s = $(btn).closest('.textarea-group').find('textarea').val();
    var empty = s == ""; 
    if (!empty) {
    	var container = $(btn).closest('.textarea-group');
    	var inputGroup = $(container).parent().find('.textarea-group:last');

    	var textNode = $(container).parent().find('.label-warning').contents().first();

    	var value = s;
    	
    	var input = $(container).parent().find('.label-warning').find("textarea");
    	
    	$(input).val(value);

		if (!$.isEmptyObject($(value).contents().first()[0])) {
			value = $(value).contents().text();
		}
		
		if (%%LENGTH%%) {
        	value = value.substring(0,%%LENGTH%%) + (value.length > %%LENGTH%% ? '...' : '')
        }

    	textNode.replaceWith(value);

    	$(input).attr('name', name + "[]");
    	$(input).attr("style", "display: none;");
    	
    	var len = $(btn).closest('.textarea-group').parent().find('.glyphicon').length;
    	$(btn).closest('.textarea-group').parent().find('.glyphicon').each (function (i) {
    		if (i != (len -1)) {
    			$(this).parent().removeClass('disabled');
    		}
    	});
    	
        $(btn).closest('.textarea-group').parent().find('textarea:last').val("");

    	$(btn).closest('.textarea-group').parent().find('.label-warning button').removeClass("btn-warning").addClass("btn-primary");
    	$(btn).closest('.textarea-group').parent().find('.label-warning').removeClass("label-warning").addClass("label-primary");
    	
    	$(btn).closest('.textarea-group').parent().find(".glyphicon-plus").closest("button").show();
    	$(btn).closest('.textarea-group').parent().find(".glyphicon-ok").closest("button").remove();
    }         
}