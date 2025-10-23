$(document).ready(function() {

	$('#metadatas div').each(function() {
		$(this).find('input').uniqueId();
		$(this).find('input').attr('name', 'md_'+$(this).find('input').attr('id'));
	});
	
	$('#metadatas').sortable();
	$('#metadatas').disableSelection();
	
	// Préparation des uploads	
	$('#mFile').change(function(event) {
		
		// Barre de chargement
		var container = getContainer($(this));
		$(container).html(getLoader());
		
		// Préparation des données
		var data = new FormData();
		$.each(event.target.files, function(key, value)
		{
			data.append(key, value);
		});
		
		// Envoi des données
		$.ajax({
			url: '/volume/addfile',
			type: 'POST',
			data: data,
			cache: false,
			processData: false,
			contentType: false, 
			success: function(response) {
				var response = $.parseJSON(response);
				var file = response.file;
				var values = (($('#mTmpData').val())) ? JSON.parse($('#mTmpData').val()) : new Object();
				values.tmpfile = JSON.stringify(file);
				$('#mTmpData').val(JSON.stringify(values));
				$('#mFile_content').empty().append(formatFileLabel(file.name + ' (' + readableBytes(file.size, lang) + ')'));
				$('#value_mFile').val('');
			}
		});	
	});
	
	
	// Gestion des positions des articles au sein d'un volume
	if ($('#papers').length) {
		$('#papers').sortable({
			placeholder: "volume-paper-placeholder",
			update: function(event, ui ) {
				$('#paper_positions').val($( "#papers" ).sortable("toArray"));
			}});
		$('#papers').disableSelection();
	}
	
});

function getContainer(element)
{
	var container_id = 'mFile_content'; 
	if (!$('#'+container_id).length) {
		$(element).parent('div').append('<div id="'+container_id+'" style="padding-top: 10px"></div>');
	}
	return '#'+container_id;
}

function formatFileLabel(label) 
{
	// Create DOM elements securely instead of HTML string concatenation
	var $div = $('<div>').addClass('small grey');
	
	var $span = $('<span>')
		.addClass('glyphicon glyphicon-remove-circle remove-file')
		.attr('onclick', 'removeFile()')
		.css({
			'margin-right': '5px',
			'cursor': 'pointer'
		});
	
	var $hiddenInput = $('<input>')
		.attr('type', 'hidden')
		.attr('name', '');
	
	// Safely add text content and append elements
	$div.append($span)
		.append(' ')
		.append(document.createTextNode(label))
		.append($hiddenInput);
	
	return $div;
}

function removeFile()
{
	var values = JSON.parse($('#mTmpData').val());
	var deletelist = (values.deletelist) ? values.deletelist : new Array();
	
	if (values.tmpfile) {
		var tmp_file = JSON.parse(values.tmpfile);
		deletelist.push({type:'tmp_file', path:tmp_file.tmp_name});
		values.deletelist = deletelist;
		values.tmpfile = '';
		$('#mFile_content').html('');
		if (values.file) {
			$('#mFile_content').empty().append(formatFileLabel(values.file));
		}
		$('#mTmpData').val(JSON.stringify(values));
		
	} else {
		deletelist.push({type:'current_file', name:values.file});
		values.deletelist = deletelist;
		values.file = '';
		$('#mTmpData').val(JSON.stringify(values));
		$('#mFile_content').html('');
	}
		
}


/**
 * init form in modal
 * @param source
 */
function init(source) {


	reset();
	source = $(source).closest('div.metadata');
	if (source.length) {
		$('#mTmpData').val($(source).closest('div').find('input').val());
		var values = JSON.parse($('#mTmpData').val());
		
		for (var lang in values.title) {
			$('#mTitle').val(values.title[lang]);
			$('#mTitle').attr('lang', lang);
			$('#mTitle').next('span').find('button').attr('value', lang);
			$('#mTitle').parent('div').find('li a[val="'+lang+'"]').trigger('click');
			$('#mTitle').parent('div').find('span:last button').trigger('click');
		}


		for (var lang in values.content) {


			//tinyMCE.get('mContent').setContent(values.content[lang]);
            $('#mContent').val(values.content[lang]);
			$('#mContent').attr('lang', lang);
			$('#mContent').next('div').find('button').attr('value', lang);
			$('#mContent').parent('div').find('li a[val="'+lang+'"]').trigger('click');
			$('#mContent').parent('div').find('span:last button').trigger('click');
		}
		
		if (values.file || values.tmpfile) {
			if (values.tmpfile) {
				var tmpfile = JSON.parse(values.tmpfile);
				var filename = tmpfile.name;
			} else {
				var filename = values.file;
			}
			var container = getContainer($('#mFile'));
			$(container).empty().append(formatFileLabel(filename));
		}
	}
}


function submit(source) {
	source = $(source).closest('div.metadata');
	if (validate()) {
		if (source.length) {
			editMetadata(source);
		} else {
			addMetadata();
		}
		$('#modal-box').modal('hide');
	   	return false;
	} else {
		return false;			
	}
}

function getInput(id, mce)
{
	var langs = new Array();
	var value = new Object();
		
	if (mce) {
		$('#'+id).next('div').find('li a').each(function() {
			langs.push($(this).attr('val'));
		});
		for (var i in langs) {

			if ($('#modal-box form textarea[name="'+id+'['+langs[i]+']"]').val()) {
				value[langs[i]] = $('#modal-box form textarea[name="'+id+'['+langs[i]+']"]').val();
			} /*else if (tinyMCE.activeEditor.getContent() && $('#'+id+'-element').find('button[data-toggle="dropdown"]').val() == langs[i]) {
				value[langs[i]] = tinyMCE.activeEditor.getContent();
			}*/

		}
	} else {
		$('#'+id).next('span').find('li a').each(function() {
			langs.push($(this).attr('val'));
		});
		for (i in langs) {
			value[langs[i]] = $('#modal-box form input[name="'+id+'['+langs[i]+']"]').val();
		}
	}
	
	
	return value;
}

function getMetadata()
{
	var tmpData = ($('#mTmpData').val()) ? $.parseJSON($('#mTmpData').val()) : {};
	var value = new Object();
	value.id = tmpData.id;
	value.title = getInput('mTitle');
	value.content = getInput('mContent', true);
	value.tmpfile = tmpData.tmpfile;
	value.file = tmpData.file;
	value.deletelist = tmpData.deletelist;
	
	var title = (value.title[lang]) ? value.title[lang] : getFirstOf(value.title); 
	var content = (value.content[lang]) ? value.content[lang] : getFirstOf(value.content);	

	// Create DOM elements securely instead of HTML string concatenation
	var $div = $('<div>').addClass('metadata input-group').css('margin-bottom', '2px');
	
	var $span = $('<span>')
		.addClass('label label-primary')
		.css({
			'font-size': 'inherit',
			'display': 'block',
			'text-align': 'justify',
			'white-space': 'normal',
			'padding': '1px 0px 1px 10px'
		});
	
	// Safely add title text
	$span.append(document.createTextNode(title));
	
	// Create edit button
	var $editLink = $('<a>')
		.addClass('modal-opener')
		.attr({
			'data-width': '50%',
			'data-init': 'init',
			'data-callback': 'submit',
			'title': translate('Modifier une métadonnée')
		});
	
	var $editButton = $('<button>')
		.addClass('btn btn-xs btn-primary edit-metadata')
		.attr('type', 'button')
		.append($('<span>').addClass('glyphicon glyphicon-pencil'));
	
	$editLink.append($editButton);
	$span.append(' ').append($editLink);
	
	// Create delete button
	var $deleteButton = $('<button>')
		.addClass('btn btn-xs btn-primary')
		.attr({
			'type': 'button',
			'onclick': 'removeMetadata($(this))',
			'data-placement': 'right'
		})
		.css({
			'border-radius': '0',
			'height': '20px',
			'padding-top': '0',
			'padding-bottom': '0'
		})
		.append($('<span>').addClass('glyphicon glyphicon-trash'));
	
	$span.append($deleteButton);
	$div.append($span);
	
	// Add hidden input
	var $hiddenInput = $('<input>')
		.attr('type', 'hidden')
		.attr('value', '');
	
	$div.append($hiddenInput);
	
	// Set the input value and attributes
	$hiddenInput.val(JSON.stringify(value));
	$hiddenInput.uniqueId();
	$hiddenInput.attr('name', 'md_' + $hiddenInput.attr('id'));
	
	return $div;
	
}

function addMetadata()
{	var tag = getMetadata();
	$('#metadatas').append(tag);
	return;
}

function editMetadata(source)
{
	var tag = getMetadata();
	$(source).replaceWith(tag);	
	return;
}

function removeMetadata(btn)
{
	$(btn).closest('div.metadata').remove();
}

function validate()
{
	var errors = new Array();

	if (!validMultilangInput('mTitle')) {
		errors.push(translate("Le champ Nom est obligatoire"));
	}
	/*
	if (!validMultilangInput('mContent', true)) {
		errors.push(translate("Le champ Contenu est obligatoire"));
	}
	*/
	
	if (errors.length) {
		// Create DOM elements securely instead of HTML string concatenation
		var $errorContainer = $('<div>')
			.addClass('col-md-offset-3')
			.css('padding-left', '15px');
		
		var $errorHeader = $('<div>')
			.css({
				'margin-bottom': '5px',
				'color': 'red'
			})
			.append($('<strong>').text(translate('Erreurs :')));
		
		$errorContainer.append($errorHeader);
		
		for(var i in errors) {
			var $errorItem = $('<div>')
				.css({
					'margin-left': '10px',
					'color': 'red'
				})
				.text(' * ' + errors[i]);
			$errorContainer.append($errorItem);
		}
		
		if (!$('.modal-body .errors').length) {
			$('.modal-body').append($('<div>').addClass('errors').append($errorContainer));
		} else {
			$('.modal-body .errors').empty().append($errorContainer);
		}
		return false;
	} else {
		return true;
	}
}


// Contrôle si toutes les langues d'un champ multilangue ont été remplies
function validMultilangInput(id, mce)
{
	var errors = false;
	var langs = new Array();
	
	if (mce) {
		$('#'+id).next('div').find('li a').each(function() {
			langs.push($(this).attr('val'));
		});
		for (var i in langs) {
			if (!$('#modal-box form textarea[name="'+id+'['+langs[i]+']"]').val() && ( !tinyMCE.activeEditor.getContent() || $('#'+id+'-element').find('button[data-toggle="dropdown"]').val() != langs[i])) {
				return false;
			}
		}
	} else {
		$('#'+id).next('span').find('li a').each(function() {
			langs.push($(this).attr('val'));
		});
		for (i in langs) {
			if (!$('#modal-box form input[name="'+id+'['+langs[i]+']"]').val()) {
				return false;
			}
		}
	}
	
	return true;
}

function reset()
{
	$('#modal-box form').trigger('reset');

	// Title
	$('#mTitle').val('');
	$('#mTitle').prop('disabled', false);
	$('#modal-box input[name^="mTitle["]').each(function() {
		$(this).prev('button').trigger('click');
	});

	// Content
	//tinyMCE.get('mContent').setContent('');
	$('#modal-box textarea[name^="mContent["]').each(function() {
		$(this).prev('button').trigger('click');
	});

	// File
	$('#mTmpData').val('');
	$('#mFile_content').html('');

	// Errors
	$('.modal-body .errors').remove();
}

/**
 * Volume title editing protection
 * Prevents modification of volume titles when articles are already associated
 * This provides frontend protection complementing the backend validation
 */
$(document).ready(function() {
	var form = $('form[data-library="ccsd"]');

	// Check if volume has articles (data attribute from PHP)
	var hasArticles = form.data('volume-has-articles') === 'true';

	if (hasArticles) {
		// Store original values to prevent any modification attempts
		var originalTitles = {};
		var titleFields = $('[name^="title_"]');

		titleFields.each(function() {
			var field = $(this);
			// Save original value
			originalTitles[field.attr('name')] = field.val();

			// Apply readonly protection (if not already done by PHP)
			if (!field.attr('readonly')) {
				field.attr('readonly', 'readonly');
				field.addClass('readonly-field');
				field.attr('title', field.data('readonly-message') || 'Cannot modify volume name with associated articles');
			}
		});

		// Additional protection: restore original values on form submit
		if (form.length) {
			form.on('submit', function(e) {
				// Restore all title fields to their original values
				titleFields.each(function() {
					var field = $(this);
					var fieldName = field.attr('name');
					if (field.val() !== originalTitles[fieldName]) {
						console.warn('Volume title modification attempt blocked (frontend)');
						field.val(originalTitles[fieldName]);
					}
				});
			});
		}
	}
});