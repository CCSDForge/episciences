function %%FCT_NAME%% (btn, name) {
	var empty = $(btn).closest('div').find('input').val() == "";      
	if (!empty) {
		
		var span = document.createElement("SPAN");
		span.className = "label label-primary";
		span.style     = "font-size: inherit; display: inline-block; text-align: justify; white-space: normal; padding: 1px  0px 1px 10px;";

		var value = $(btn).closest("div").find('input').val();
        value = value.replace(new RegExp("(>)", "g"), '&gt;').replace(new RegExp("(<)", "g"), '&lt;');
        
        var text_value = value;
        if (%%LENGTH%%) {
        	text_value = text_value.substring(0,%%LENGTH%%) + (text_value.length > %%LENGTH%% ? '...' : '')
        }
        
        var btnGroup = $(btn).closest('.input-group').find('.btn-group');
        
        var libelle = $(btnGroup).find('button').text();
		
		var text = document.createTextNode(text_value + " (" + libelle + ")");

		var btn1 = document.createElement("BUTTON");
		btn1.setAttribute("style",   "border-radius:0; height: 20px; padding-top:0; padding-bottom: 0; margin-left: 5px;");
		btn1.setAttribute("class",   "btn btn-xs btn-primary");
		btn1.setAttribute("onclick", '%%MODIFY%%(this, \'' + name + '\');');
		btn1.setAttribute("type",    "button");
		btn1.setAttribute('title',   'Modifier')
		
		var icon1 = document.createElement("I");
		icon1.setAttribute("class", "glyphicon glyphicon-pencil");

		btn1.appendChild(icon1);

		var btn2 = document.createElement("BUTTON");
		btn2.setAttribute("style",   "border-radius:0; height: 20px; padding-top:0; padding-bottom: 0;");
		btn2.setAttribute("class",   "btn btn-xs btn-primary");
		btn2.setAttribute("onclick", '%%DELETE%%(this);');
		btn2.setAttribute("type",    "button");
		btn2.setAttribute('title',   'Supprimer')
		
		var icon2 = document.createElement("I");
		icon2.setAttribute("class", "glyphicon glyphicon-trash");
		
		btn2.appendChild(icon2);

		var lang = $(btnGroup).find('button').val();
		
		var input = document.createElement("INPUT");
		input.setAttribute("name", name + "[" + lang + "]");
		input.setAttribute("type", "hidden");
		input.setAttribute("value", value);
		input.setAttribute("lang", lang);

		span.appendChild(text); 
		span.appendChild(btn1); 
		span.appendChild(btn2); 
		span.appendChild(input);
		
		var div = $(btn).closest("div").clone();

		$(btnGroup).find('ul li a[val=' + lang + ']').closest('li').addClass('disabled');
		
		var elm = $(btnGroup).find('ul li[class!="disabled"]:first a');
        if (typeof $(elm).html() != 'undefined') {
            $(btnGroup).find('button').val($(elm).attr('val'));
            $(btn).closest('.input-group').find('input').attr("name", name + "[" + $(elm).attr('val') + "]");
            var textNode = $(btnGroup).find('button').contents().first();
            textNode.replaceWith($(elm).text());
        } else {
        	$(btn).closest('div').find('input:first').attr('disabled', 'disabled');
            $(btn).closest('div').hide();
        }

		$(div).html(span);

		$(div).insertBefore($(btn).closest("div"));
		
		$(btn).closest('div').find('input:first').val(""); 
		$(btn).closest('div').find('input:first').focus();        
	}
}  
	
        

        
        
