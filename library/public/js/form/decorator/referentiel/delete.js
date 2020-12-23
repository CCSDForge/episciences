if (typeof("delete_ref") != "function") {
	function delete_ref (a, b, c)
	{
		$(document.body).find('> .tooltip').remove();
	    
		if ("false" == c || !c) {
			$(a).closest("blockquote").parent().find('input:last').removeAttr('disabled').show();
		}
		
		$(a).closest("blockquote").remove();	
		
	    $(document.body).tooltip({ selector: '[data-toggle="tooltip"]' , html: true, container: 'body'});
	}
}