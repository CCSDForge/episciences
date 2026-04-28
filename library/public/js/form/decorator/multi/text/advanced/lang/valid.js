function %%FCT_NAME%% (btn, name) {
    var s = $(btn).closest('div').find('input').val();
    var empty = s == ""; 
    if (!empty) {    	
    	var container = $(btn).closest('div');
    	var inputGroup = $(container).parent().find('.input-group:last');

    	var libelle = $(inputGroup).find('.btn-group > button').text();
    	
    	var textNode = $(container).parent().find('.label-warning').contents().first();

    	var value = s;
        value = value.replace(new RegExp("(>)", "g"), '&gt;').replace(new RegExp("(<)", "g"), '&lt;');
        
        var text_value = value;
    	if (%%LENGTH%%) {
    		text_value = text_value.substring(0,%%LENGTH%%) + (text_value.length > %%LENGTH%% ? '...' : '')
        }

    	textNode.replaceWith(text_value + " (" + libelle + ")");

    	var input = $(container).parent().find('.label-warning').find("input");
    	
    	var lang = $(inputGroup).find('.btn-group > button').val();
    	
    	$(input).val(value);
    	$(input).attr('name', name + "[" + lang + "]");
    	$(input).attr('lang', lang);
    	$(input).attr('data-language', "true");

    	var len = $(btn).closest('div').parent().find('.glyphicon').length;
    	$(btn).closest('div').parent().find('.glyphicon').each (function (i) {
    		if (i != (len -1)) {
    			$(this).parent().removeClass('disabled');  
    		}
    	});

    	$(btn).closest('div').parent().find('input:last').val("");
    	
    	$(btn).closest('div').parent().find('.label-warning button').removeClass("btn-warning").addClass("btn-primary");
    	$(btn).closest('div').parent().find('.label-warning').removeClass("label-warning").addClass("label-primary");

    	$(btn).closest('div').parent().find(".glyphicon-plus").closest("span").show();

		$(inputGroup).find('ul li a[val=' + lang + ']').closest('li').addClass('disabled');
		
		var elm = $(inputGroup).find('ul li[class!="disabled"]:first a');
        if (typeof $(elm).html() != 'undefined') {
            $(inputGroup).find('button').val($(elm).attr('val'));
            
            var textNode = $(inputGroup).find('button').contents().first();
            textNode.replaceWith($(elm).text());
        } else {
        	$(inputGroup).find('input:first').attr('disabled', 'disabled');
            $(inputGroup).hide();
        }

    	$(btn).closest('div').parent().find(".glyphicon-ok").closest("span").remove();
    }         
}