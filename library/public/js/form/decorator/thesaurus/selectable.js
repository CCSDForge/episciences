var fct_click_check_%%IDFILTER%% = function (event, b) {

    b = typeof b != "undefined" ? b : true;
    
    //$(".tree").find(':input[value="' + $(this).closest('li').find('input').val() + '"]').closest('li').find('span.click').unbind('click');
    $(this).unbind('click');
    
    var tooltip = document.createElement("DIV");
    
    function create_tooltip ( root, item ) {	
    	var label = $(item).find('.libelle:first').html(), span = document.createElement("SPAN");
        span.appendChild(document.createTextNode(label));

    	root.insertBefore (span, root.childNodes[0]);

        var parentNode = $(item).closest('ul').closest('li');
        
        if (parentNode.length) {
        	root = create_tooltip ( root, parentNode );
        }
        
        return root;
    }
    
    if (%%TIP%%) {

    	tooltip = create_tooltip ( tooltip, $(this).closest('li') );
    	
    	var children = tooltip.children;
        for (var i = 0; i < children.length; i++) {
        	children[i].setAttribute("style", "display: block; text-align: left; padding-left: " + i*15 + "px");
        	if (i) {
        		var icon = document.createElement("I");
        		icon.setAttribute('class', 'glyphicon glyphicon-share-alt');
        		icon.setAttribute('style', 'transform: scaleY(-1);');
        		children[i].insertBefore (document.createTextNode(" "), children[i].childNodes[0]);
        		children[i].insertBefore (icon, children[i].childNodes[0]);
        	}
        }

        tooltip.setAttribute('style', 'text-align: left;');
        
        addSelectedItem_%%IDFILTER%%($(this).closest('li').find('input:first').val(), tooltip.innerHTML, $(this).closest('li').find('.libelle:first').html(), b);
        
    } else {
    	
    	tooltip = create_tooltip ( tooltip, $(this).closest('li') );
    	
    	var children = tooltip.children;
        for (var i = 0; i < children.length; i++) {
        	
        	if (i) {
        		children[i].insertBefore (document.createTextNode(" / "), children[i].childNodes[0]);
        	}
        }

        addSelectedItem_%%IDFILTER%%($(this).closest('li').find('input:first').val(), undefined, tooltip.innerHTML.replace(/<[^>]*>/g, ""), b);
    }
    
    

    if (b) {
        %%EVENTS%%
    }

};

var fct_click_not_check_%%IDFILTER%% = function (event, b) {
    b = typeof b != "undefined" ? b : true;
	$('#list_%%N%% input[value="' + $(this).closest('li').find('input:first').val() + '"]').closest('li').remove();
	
    $(this).unbind('click');
    $(this).click(fct_click_check_%%IDFILTER%%);

	if (b) {
	    %%EVENTS%%
	}
};

function addSelectedItem_%%IDFILTER%% (code, tooltip, libelle, b) 
{
    var li = document.createElement('LI');
    li.setAttribute("style", "display: block");
    li.setAttribute("class", "margin-top-5");

    var input = document.createElement("INPUT");
    input.setAttribute("type", "hidden");
    input.setAttribute("value", code);
    input.setAttribute("name", "%%NAME_S%%");

    var move = document.createElement("I");
    move.setAttribute("style",   "border-radius: 0px; height: 20px; padding: 0px; margin: 0px 7px; top: 1px; bottom: 0px;");
    move.setAttribute("class",   "glyphicon glyphicon-move move");
    move.setAttribute("data-toggle", "tooltip");
    move.setAttribute("data-original-title", "Déplacer");
    move.setAttribute("data-placement", "left");

	var del = document.createElement("BUTTON");
	del.setAttribute("style",   "height: 20px; padding-top: 0px; padding-bottom: 0px; margin-left: 10px; margin-top: -2px; margin-right: 0px; border: medium none ! important; padding-right: 6px;");
	del.setAttribute("class",   "btn btn-xs btn-primary");
	del.setAttribute("type",    "button");
	del.setAttribute("data-toggle", "tooltip");
	del.setAttribute("data-original-title", "Supprimer");
	del.setAttribute("data-placement", "right");
	
	var icon2 = document.createElement("I");
	icon2.setAttribute("class", "glyphicon glyphicon-trash");
	
	del.appendChild(icon2);

    $(del).bind('click', function () {    
        $(this).closest('li').remove();
        $('#%%IDFILTER%%').closest('.form-group').find(".tree input[value='" + code + "']").closest("li").find(".libelle:first").click(fct_click_check_%%IDFILTER%%);
        %%EVENTS%%
    });

    var text = document.createElement("SPAN");
    text.setAttribute("style", "padding-top: 4px; padding-bottom: 0px; margin-top: 0px; height: 20px; display: inline-block;");
    text.appendChild(document.createTextNode(libelle));

    var span = document.createElement("SPAN");
    span.setAttribute("class", "label label-primary");
    span.setAttribute("style", "font-size: inherit; display: inline-block; text-align: justify; white-space: normal; padding: 0px; height: 20px;");
    
    if (typeof tooltip != "undefined") {
        span.setAttribute("data-html", "true");
    	span.setAttribute('data-toggle', 'tooltip');
    	span.setAttribute('data-original-title', tooltip);
	}
    
    span.appendChild(move);
    span.appendChild(text);
    span.appendChild(del);

    li.appendChild(input);
    li.appendChild(span);
    
    $(li).appendTo($("#list_%%N%%"));
}

function setRequiredItems_%%IDFILTER%% ( code ) {
	$("#list_%%N%%").find("input[value='" + code + "']:first").closest('li').find('button').remove();
	$("#list_%%N%%").find("input[value='" + code + "']:first").closest('li').find('.label span').attr('style', 'padding-top: 4px; padding-bottom: 0px; padding-right: 10px; margin-top: 0px; height: 20px; display: inline-block;');
	$('#%%IDFILTER%%').closest('.form-group').find(".tree li input[value='" + code + "']:first").closest('li').find('> .libelle:first').unbind("click");        
}
            
$('#panel_%%N%%').find('.click').click(fct_click_check_%%IDFILTER%% );