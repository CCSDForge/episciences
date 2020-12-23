function %%FCT_NAME%% (elm, name) {            
    var code = $(elm).attr('val');
    var libelle = $(elm).html();

    if ($(elm).closest('li').hasClass('disabled')) {
        return false;
    }     
         
    $(elm).closest(".btn-group").find("button").val(code);
    var textNode = $(elm).closest(".btn-group").find("button").contents().first();            
    textNode.replaceWith(libelle);  

    if ($(elm).closest('.input-group').find('input').attr('data-language')) {
        var lang = $(elm).closest('.input-group').find('input').attr('lang');
        $(elm).closest('.input-group').find('input').attr('data-language', code);
                
        $(elm).closest('.input-group').parent().find('.btn-group').each(function (i) {
            $(this).find('ul li a[val=' + lang + ']').closest('li').removeClass('disabled');
            $(this).find('ul li a[val=' + code + ']').closest('li').addClass('disabled');
        });        

        var inputGroup = $(elm).closest('.input-group').parent().find('.input-group:last');
        var btnGroup = $(inputGroup).find('.btn-group ul li[class!="disabled"]:first a');
        if (typeof $(btnGroup).html() != 'undefined') {
            $(inputGroup).find('.btn-group > button').val($(btnGroup).attr('val'));
            var textNode = $(inputGroup).find('.btn-group > button').contents().first();
            textNode.replaceWith($(btnGroup).text());
            
            var name1 = name + "[" + $(btnGroup).attr('val') + "]";
            
            if ($(elm).closest('.input-group').find('input:first').attr('data-keyword') || $(elm).closest('.textarea-group').find('textarea').attr('data-keyword')) {
            	name1 = name1 + "[]";
            }
            
            $(inputGroup).find("input:first").attr('name', name1);
        } else {
        	$(inputGroup).find("input:first").removeAttr('name');
            $(btnGroup).hide();
        }
    } else if ($(elm).closest('.textarea-group').find('textarea').attr('data-language')) {
        var lang = $(elm).closest('.textarea-group').find('textarea').attr('lang');
        $(elm).closest('.textarea-group').find('textarea').attr('data-language', code);
                
        $(elm).closest('.textarea-group').parent().find('.btn-group').each(function (i) {
            $(this).find('ul li a[val=' + lang + ']').closest('li').removeClass('disabled');
            $(this).find('ul li a[val=' + code + ']').closest('li').addClass('disabled');
        });        

        var inputGroup = $(elm).closest('.textarea-group').parent().find('.textarea-group:last');
        var btnGroup = $(inputGroup).find('.btn-group ul li[class!="disabled"]:first a');
        if (typeof $(btnGroup).html() != 'undefined') {
            $(inputGroup).find('.btn-group > button').val($(btnGroup).attr('val'));
            var textNode = $(inputGroup).find('.btn-group > button').contents().first();
            textNode.replaceWith($(btnGroup).text());
            
            var name2 = name + "[" + $(btnGroup).attr('val') + "]";
            
            if ($(elm).closest('.input-group').find('textarea:first').attr('data-keyword') || $(elm).closest('.textarea-group').find('textarea').attr('data-keyword')) {
            	name2 = name2 + "[]";
            }
            
            $(inputGroup).find("textarea:first").attr('name', name2);
        } else {
        	$(inputGroup).find("textarea:first").removeAttr('name');
            $(btnGroup).hide();
        }        
    }
                
    $(elm).closest('.input-group').find('input').attr('lang', code);
    $(elm).closest('.textarea-group').find('textarea').attr('lang', code);
    
    var name = name + "[" + code + "]";
    
    if ($(elm).closest('.input-group').find('input:first').attr('data-keyword') || $(elm).closest('.textarea-group').find('textarea').attr('data-keyword')) {
    	name = name + "[]";
    }
    
    $(elm).closest('.input-group').find('input').attr('name', name);      
    $(elm).closest('.textarea-group').find('textarea').attr('name', name);  

    return false;
}