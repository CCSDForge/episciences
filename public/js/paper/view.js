$(function () {

	$('button[id^="cancel"]').click(function() 
	{
		$('#submitNewVersion').hide();
		$('#submitTmpVersion').hide();
		$('#comment_only').fadeIn();
	});
	
	$('#displayRevisionForm').click(function() 
	{
		$('#comment_only').hide();
		$('#submitTmpVersion').hide();
		$('#submitNewVersion').fadeIn();
		// window.location.hash = 'answer';
	});	
	
	$('#displayTmpVersionForm').click(function() 
	{
		$('#comment_only').hide();
		$('#submitNewVersion').hide();
		$('#submitTmpVersion').fadeIn();
	});	
	
	$('.replyButton').each(function() {
		$(this).click(function()
		{
			var form = $(this).parent().next('.replyForm');
			$(form).parent().find('.replyForm').hide();
			$(form).parent().find('.replyButton').show();
			$(this).hide();
			$(form).fadeIn();
		});
	});
		
	$('button[id^="cancel"]').each(function() {
		$(this).click(function()
		{
			$(this).closest('.replyForm').hide();
			$(this).closest('.replyForm').prev().find('.replyButton').fadeIn();
		});
	});


	if (isFromZSubmit) {

		if ($('#answer-request').length > 0) {

			$('#answer-request').click();
		}

		setTimeout(function () {

			if ($('#new-version').length > 0) {
				$('#new-version').click();
			}
		}, 0.1);

	}

});
