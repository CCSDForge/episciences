function %%FCT_NAME%% (btn, name, fct) {
    var s = $(btn).closest('div').find('input').val();
    var empty = s == ""; 
    if (!empty) {
    	var textNode = $(btn).closest('div').parent().find('.label-warning').contents().first();

    	s = s.replace(new RegExp("(>)", "g"), '&gt;').replace(new RegExp("(<)", "g"), '&lt;');
		
        var text_value = s;
        if (%%LENGTH%%) {
        	text_value = text_value.substring(0,%%LENGTH%%) + (text_value.length > %%LENGTH%% ? '...' : '')
        }

    	textNode.replaceWith(text_value);
    	
    	$(btn).closest('div').parent().find('.label-warning').find("input").val(s);

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
    	$(btn).closest('div').parent().find(".glyphicon-ok").closest("span").remove();
    }         
}