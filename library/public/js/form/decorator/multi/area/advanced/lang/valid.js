function %%FCT_NAME%% (btn, name) {
    var s = $(btn).closest('.textarea-group').find('textarea').val();
    var empty = s == ""; 
    if (!empty) {    	
    	var container = $(btn).closest('.textarea-group');
    	var inputGroup = $(container).parent().find('.textarea-group:last');

    	var libelle = $(inputGroup).find('.btn-group > button').text();
    	
    	var textNode = $(container).parent().find('.label-warning').contents().first();

    	var value = s;

    	var input = $(container).parent().find('.label-warning').find("textarea");
    	
    	var lang = $(inputGroup).find('.btn-group > button').val();

    	$(input).val(value);
    	
		if (!$.isEmptyObject($(value).contents().first()[0])) {
			value = $(value).contents().text();
		}
		
		if (%%LENGTH%%) {
        	value = value.substring(0,%%LENGTH%%) + (value.length > %%LENGTH%% ? '...' : '')
        }

    	textNode.replaceWith(value + " (" + libelle + ")");

    	$(input).attr('name', name + "[" + lang + "]");
    	$(input).attr('lang', lang);
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
    	
    	$(btn).closest('.textarea-group').parent().find(".glyphicon-plus").closest("span").show();

		$(inputGroup).find('ul li a[val=' + lang + ']').closest('li').addClass('disabled');
		
		var elm = $(inputGroup).find('ul li[class!="disabled"]:first a');
        if (typeof $(elm).html() != 'undefined') {
            $(inputGroup).find('.pull-right button').val($(elm).attr('val'));
            
            var textNode = $(inputGroup).find('.pull-right button').contents().first();
            textNode.replaceWith($(elm).text());
        } else {
        	$(inputGroup).find('textarea').attr('disabled', 'disabled');
            $(inputGroup).hide();
        }
        
    	$(btn).closest('.textarea-group').parent().find(".glyphicon-ok").closest("span").remove();
    } 
}