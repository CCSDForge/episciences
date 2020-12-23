function %%FCT_NAME%% (btn, name) {
    var s = $(btn).closest('.textarea-group').find('textarea').val();
    var empty = s == ""; 
    if (!empty) {    	
    	var container = $(btn).closest('.textarea-group');
    	var inputGroup = $(container).parent().find('.textarea-group:last');

    	var libelle = $(inputGroup).find('.btn-group > button').text();
    	
    	var textNode = $(container).parent().find('.label-warning').contents().first();

    	var value = s;
    	var text_node = value;

    	var input = $(container).parent().find('.label-warning').find("textarea");
    	
    	var lang = $(inputGroup).find('.btn-group > button').val();

    	$(input).val(value);
    	
    	if (!$.isEmptyObject($(text_node).contents().first()[0])) {
    		text_node = $(text_node).contents().text();
		}
		
		if (%%LENGTH%%) {
			text_node = text_node.substring(0,%%LENGTH%%) + (text_node.length > %%LENGTH%% ? '...' : '')
        }

    	textNode.replaceWith(text_node + " (" + libelle + ")");
    	
    	$(input).attr('name', name + "[" + lang + "][]");
    	$(input).attr('lang', lang);
    	$(input).attr('style', 'display: none;');

    	var len = $(btn).closest('.textarea-group').parent().find('.glyphicon').length;
    	$(btn).closest('.textarea-group').parent().find('.glyphicon').each (function (i) {
    		if (i != (len -1)) {
    			$(this).parent().removeClass('disabled');
    		}
    	});
    	
    	$(btn).closest('.textarea-group').parent().find('textarea:last').val("");
    	$(btn).closest('.textarea-group').parent().find('.label-warning button').removeClass("btn-warning").addClass("btn-primary");
    	$(btn).closest('.textarea-group').parent().find('.label-warning').removeClass("label-warning").addClass("label-primary");
    	
    	$(btn).closest('.textarea-group').parent().find(".glyphicon-plus").closest("span").show();

    	$(btn).closest('.textarea-group').parent().find(".glyphicon-ok").closest("span").remove();
    } 
}