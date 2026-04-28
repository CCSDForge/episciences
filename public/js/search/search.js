$(document).ready(function () {
    // Activation des filtres sur les facettes
    $('input[id$="-facet-input"]').each(function (i) {
        $(this).fastLiveFilter($(this).next('ul'));
    });

    // Activation des tooltips
    $('a[data-toggle="tooltip"]').tooltip();

    // checkbox redirect
    $("input[type='checkbox']").change(function () {
        var item = $(this);
        if (item.data('target')) {
            window.location.href = item.data('target');
        }
    });

    // option de recherche
    $('#search-options').popover({
        html: true,
        placement: 'bottom',
        //trigger : 'manual',
        title:
            'Options' +
            '<span style="float:right"><a class="close" href="javascript:void(0);" onclick="$(\'#search-options\').click(); return false;">&times;</a></span>',
        content: function () {
            return $('#search-options-content').html();
        },
        delay: {
            show: 0,
            hide: 0,
        },
    });

    $('.facet-label').click(function () {
        // facet-content
        $(this).next('.facet-content').fadeToggle({ duration: 100 });
        $(this).find('span:eq(1)').toggleClass('glyphicon-chevron-up');
        $(this).find('span:eq(1)').toggleClass('glyphicon-chevron-down');

        /*
		
		var index = $(this).index() + 2;
		var index_input = $(this).index() + 1;
			
		// console.log(index);
		var text = $(this).parent().find('> :eq(' + index + ')');
		var text_input = $(this).parent().find('> :eq(' + index_input + ')');

		if (text.is(':hidden')) {
			text_input.slideDown(50);
			text.slideDown(50);
			$(this).children('span').html('<span class="glyphicon glyphicon-chevron-up"></span>');
		} else {
			text.slideUp(50);
			text_input.slideUp(50);
			$(this).children('span').html('<span class="glyphicon glyphicon-chevron-down"></span>');
		}
		*/
    });

    // facettes
    $('#q').focus();
    $('.keepopen').click(function (event) {
        event.stopPropagation();
    });
});
