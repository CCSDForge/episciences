$(document).ready(function() {
	toggle_access_code();
	$('#special_issue').change(function() {
		toggle_access_code();
	});
});

function toggle_access_code()
{
	if ($('#special_issue').val() == 1) {
		show_access_code();
	} else {
		hide_access_code();
	}
}

function access_code_element()
{
	return '<div id="access_code-element" class="form-group row">'
	+ "<label class='col-md-3' style='text-align: right'>"+translate("Code d'accès")+"</label>"
	+ "<div class='col-md-9'>" + access_code + "</div>"
	+ "<input id='access_code' name='access_code' type='hidden' value='" + access_code + "'>"
	+ '</div>';
}

function show_access_code()
{
	$('#special_issue-element').after(access_code_element);
}

function hide_access_code()
{
	$('#access_code-element').remove();
}