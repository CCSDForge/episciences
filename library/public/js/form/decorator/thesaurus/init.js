function toJSON( obj )
{
   return typeof JSON !="undefined" ?  JSON.parse(obj) : eval('(' + obj + ')');
};



$('#%%IDFILTER%%').keyup(function(event) {    
	if ($('#%%IDFILTER%%').val().trim().length < 2) {
		$('#%%IDFILTER%%').closest('.form-group').find('.tree li input[type="checkbox"]').closest('li').show();
		$('#%%IDFILTER%%').closest('.form-group').find('.tree li input[type="hidden"]').closest('li').show();
		$('#%%IDFILTER%%').closest('.form-group').find('.tree').parent().find('.msg_no_result').hide();
		$('#%%IDFILTER%%').closest('.form-group').find('.tree li input[type="checkbox"]:checked').each(function (i) {
    		$(this).trigger('click');
        });
    }
}).typeahead({
	pass : 2,
	source : function (query, process) {
        states = [];
        map = [];
        count = 0;
        j = 0;
             
        var data = $('#%%IDFILTER%%').attr('data-json');
     
        $.each(toJSON(data), function (i, state) {
            map[j] = state;
            states.push(state);
            j++;
        });

        process(states);      
    },
    matcher: function (item, e) {
        reg = new RegExp ("\/+", "g");
    	this.query = this.query.trim().replace(reg, "/");

    	if (this.query.substring(0,1) == "/") {
    	    this.query = this.query.substring(1);
    	}

    	if (this.query.substring(this.query.length - 1) == "/") {
    	    this.query = this.query.substring(0, this.query.length - 1);
    	}

    	if (this.query.length < 2) {
        	$('#%%IDFILTER%%').closest('.form-group').find('.tree').parent().find('.msg_no_result').hide();
    	    return true;
    	}

    	var condition = "";

    	count = this.query.trim().match(/\//g);

        //if (count == null) {
        	condition = "item.%%LABEL%%.toLowerCase().indexOf(this.query.trim().toLowerCase()) != -1";
        /*} else {
        	queries = this.query.split("/");
    	
        	condition = "(item.%%LABEL%%.toLowerCase().indexOf('" + queries.shift().trim().toLowerCase() + "') != -1 && item.%%CODE%%.split('.').length == 1)";

        	$.each(queries, function (i) {
        		s = queries[i].trim().toLowerCase();
        		n = i+2;
        		condition += " || (item.%%LABEL%%.toLowerCase().indexOf('" + s + "') != -1 && item.%%CODE%%.split('.').length == " + n + ")";
        	});
        }*/

    	if (eval(condition)) {
        	if ($.isEmptyObject(e)) {
        		return true; 
        	}
        	var c = true;
        	
        	$.each(e, function (i, f) {
        		if (item.%%CODE%%.substr(0, f.%%CODE%%.length+1) == (f.%%CODE%% + ".")) {
	    			c = false;
	    			return false;
	    		}
        	});
        	
        	/*if (count && !$.isEmptyObject(e)) {
        		p = 1;
        		while (p <= count.length) {
        			
        		}
        		return $.grep(e, function (f) {

        		});
        	}*/
        
        	return c;
        }	
    },
    sorter: function (items) {
    	$('#%%IDFILTER%%').closest('.form-group').find('.tree li').hide();

    	$('#%%IDFILTER%%').closest('.form-group').find('.tree li input[type="checkbox"]:checked').each(function (i) {
    		$(this).trigger('click');
        });
    	
    	if ($.isEmptyObject(items)) {
    		$('#%%IDFILTER%%').closest('.form-group').find('.tree').parent().find('.msg_no_result').show();
    	} else {
    		$('#%%IDFILTER%%').closest('.form-group').find('.tree').parent().find('.msg_no_result').hide();
    	}

		deepth = 1;
		
		$.each(items, function (i, item) {
			n = map[map.indexOf(item)].%%CODE%%.match(/:/g);
			n = n != null ? n.length : 0;
			if (n > deepth) {
			    deepth = n;
			}
		});

		code = $.grep(items, function (item) { 
			n = map[map.indexOf(item)].%%CODE%%.match(/:/g);
			n = n != null ? n.length : 0;
			return n == 0;
    	});

		for (var i = 1; i <= deepth; i++) {
			$.each(items, function (k, item) {
    			n = map[map.indexOf(item)].%%CODE%%.match(/:/g);
    			n = n != null ? n.length : 0;

    		    if (n == i) {
        		    var found = false;

    		        $.each(code, function (j, c) {
    		        	if (map[map.indexOf(item)].%%CODE%%.substring (0, map[map.indexOf(c)].%%CODE%%.length) == map[map.indexOf(c)].%%CODE%%) {
    		        	    found = true;
    		        	}
        		    });

        		    if (!found) {
        		        code.push(item);
        		    }
    		    }      			
    		});
		}

		items = code;

        function show_parent (elm)
        {
        	var id_parent = $(elm).closest('ul').closest('li').find('input[type="checkbox"]:first:not(:checked)').val();

    	    if (id_parent != undefined) {
        	    var parent = $('#%%IDFILTER%%').closest('.form-group').find('.tree input[type="checkbox"][value="' + id_parent + '"]');
    	    	$(parent).closest('li').show();
    	    	
    	    	/*var c= false;
    	    	$.each(items, function (i, item) {
    	    		if (map[map.indexOf(item)].%%CODE%% == id_parent) {
    	    			c = true;
    	    		}
    	    	});
    	    	
    	    	if (!c) {*/
    	    		$(parent).trigger('click');
    	    	//}
    	    	
        	    show_parent ($(parent));
    	    }
        }

        $.each(items, function (i, item) {
        	$('#%%IDFILTER%%').closest('.form-group').find('.tree input[value="' + map[map.indexOf(item)].%%CODE%% + '"]').each(function (i) {     
            	$(this).closest('li').show();

        	    if ($(this).attr('type') == 'checkbox') {
        	    	$(this).closest('li').find('li').show();
        	    } 

        	    show_parent( this );
            });
        });

        return false;
    },
    highlighter: function (item) {
        return false;
    },
    updater: function (item) {
    	return true;
    }
    }); 




$('#%%NAMEBLOCK%%').find(".tree li input[type='checkbox']").click(function () {
    if ($(this).is(':checked')) {
        $(this).closest('li').find('i.%%CLOSE%%:first').removeClass('%%CLOSE%%').addClass('%%OPEN%%');        
    	$(this).closest('li').find('ul:first').css('height', 'auto');
        $(this).closest('li').find('ul:first').css('opacity', '1');
    } else {
        $(this).closest('li').find('i.%%OPEN%%:first').removeClass('%%OPEN%%').addClass('%%CLOSE%%');   
    	$(this).closest('li').find('ul:first').css('height', '0');
        $(this).closest('li').find('ul:first').css('opacity', '0');
    }
});