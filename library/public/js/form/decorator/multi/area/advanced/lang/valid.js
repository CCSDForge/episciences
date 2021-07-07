function %%FCT_NAME%% (btn, name) {
	let textNode;
	let s = $(btn).closest('.textarea-group').find('textarea').val();
    let empty = (s === "");
    if (!empty) {
    	let container = $(btn).closest('.textarea-group');
    	let inputGroup = $(container).parent().find('.textarea-group:last');

    	let libelle = $(inputGroup).find('.btn-group > button').text();

    	textNode = $(container).parent().find('.label-warning').contents().first();

    	let value = s;

    	let input = $(container).parent().find('.label-warning').find("textarea");

    	let lang = $(inputGroup).find('.btn-group > button').val();

    	$(input).val(value);

		if (!$.isEmptyObject($(name).contents().first()[0])) {
			value = $(name).contents().text();
		}

		if (%%LENGTH%%) {
        	value = value.substring(0,%%LENGTH%%) + (value.length > %%LENGTH%% ? '...' : '')
        }

    	textNode.replaceWith(value + " (" + libelle + ")");

    	$(input).attr('name', name + "[" + lang + "]");
    	$(input).attr('lang', lang);
    	$(input).attr("style", "display: none;");

    	let len = $(btn).closest('.textarea-group').parent().find('.glyphicon').length;
    	$(btn).closest('.textarea-group').parent().find('.glyphicon').each (function (i) {
    		if (i !== (len - 1)) {
    			$(this).parent().removeClass('disabled');
    		}
    	});

    	$(btn).closest('.textarea-group').parent().find('textarea:last').val("");
    	$(btn).closest('.textarea-group').parent().find('.label-warning button').removeClass("btn-warning").addClass("btn-primary");
    	$(btn).closest('.textarea-group').parent().find('.label-warning').removeClass("label-warning").addClass("label-primary");

    	$(btn).closest('.textarea-group').parent().find(".glyphicon-plus").closest("span").show();

		$(inputGroup).find('ul li a[val=' + lang + ']').closest('li').addClass('disabled');

		let elm = $(inputGroup).find('ul li[class!="disabled"]:first a');
		if (typeof $(elm).html() != 'undefined') {
			$(inputGroup).find('.pull-right button').val($(elm).attr('val'));
			textNode = $(inputGroup).find('.pull-right button').contents().first();
			textNode.replaceWith($(elm).text());
		} else {
			$(inputGroup).find('textarea').attr('disabled', 'disabled');
			$(inputGroup).hide();
		}

    	$(btn).closest('.textarea-group').parent().find(".glyphicon-ok").closest("span").remove();
    }
}