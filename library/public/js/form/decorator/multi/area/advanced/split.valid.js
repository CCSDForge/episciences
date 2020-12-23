function %%FCT_NAME%% (btn, name, fct) {
	var div = $(btn).closest('.textarea-group');
    var s = $(div).find('textarea').val();
    var empty = s == "";
    
    if (!empty) {
    	var r = new RegExp (",|;", "g");
		matches = s.split(r);

        for (i in matches) {
            s = matches[i].replace(/^"(.*)"$/, '$1').replace(/^'(.*)'$/, '$1').trim();
            if (s != "") {
            	
            	var value = s;
            	var text_node = value;
            	
            	var span = document.createElement("SPAN");
            	span.className = "label label-primary";
        		span.style     = "font-size: inherit; display: inline-block; text-align: justify; white-space: normal; padding: 1px  0px 1px 10px;";

        		var value = s;
        		var text_node = value;
        		
        		if (!$.isEmptyObject($(text_node).contents().first()[0])) {
        			text_node = $(text_node).contents().text();
        		}

                if (%%LENGTH%%) {
                	text_node = text_node.substring(0,%%LENGTH%%) + (text_node.length > %%LENGTH%% ? '...' : '')
                }
                
        		var text = document.createTextNode(text_node);

        		var btn1 = document.createElement("BUTTON");
        		btn1.setAttribute("style",   "border-radius:0; height: 20px; padding-top:0; padding-bottom: 0; margin-left: 5px;");
        		btn1.setAttribute("class",   "btn btn-xs btn-primary");
        		btn1.setAttribute("onclick", '%%MODIFY%%(this, \'' + name + '\');');
        		btn1.setAttribute("type",    "button");
        		
        		var icon1 = document.createElement("I");
        		icon1.setAttribute("class", "glyphicon glyphicon-pencil");

        		btn1.appendChild(icon1);

        		var btn2 = document.createElement("BUTTON");
        		btn2.setAttribute("style",   "border-radius:0; height: 20px; padding-top:0; padding-bottom: 0;");
        		btn2.setAttribute("class",   "btn btn-xs btn-primary");
        		btn2.setAttribute("onclick", '%%DELETE%%(this);');
        		btn2.setAttribute("type",    "button");
        		
        		var icon2 = document.createElement("I");
        		icon2.setAttribute("class", "glyphicon glyphicon-trash");
        		
        		btn2.appendChild(icon2);
				
				var input = document.createElement("TEXTAREA");
				input.setAttribute("name", name + "[]");
				input.setAttribute("style", "display: none;");
				
				$(input).val(value);

				span.appendChild(text); 
				span.appendChild(btn1); 
				span.appendChild(btn2); 
				span.appendChild(input);
			
				var clone = $(div).clone();
				
				$(clone).addClass('advanced');
				$(clone).html(span);
				
				$(clone).insertBefore($(div).parent().find('.label-warning').closest(".textarea-group"));
            }
        }

        var len = $(div).parent().find('.glyphicon').length;
        $(div).parent().find('.glyphicon').each (function (i) {
    		if (i != (len -1)) {
    			$(this).parent().removeClass('disabled');
    		}
    	});
    	
    	$(div).parent().find('textarea:last').val("");
    	
    	$(div).parent().find('.label-warning button').removeClass("btn-warning").addClass("btn-primary");
    	$(div).parent().find('.label-warning').closest("div").remove();
    	
    	$(div).parent().find(".glyphicon-plus").closest("button").show();
    	$(div).parent().find(".glyphicon-ok").closest("button").remove();
    }         
}