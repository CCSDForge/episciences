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
				$('#mFile_content').html( formatFileLabel(file.name + ' (' + readabeBytes(file.size, lang) + ')') );
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
	var html = '';
	html += '<div class="small grey">';
	html += '<span class="glyphicon glyphicon-remove-circle remove-file" onclick="removeFile()" style="margin-right: 5px; cursor: pointer" /> ';
	html += label;
	html += '<input type="hidden" name="" />';
	html += '</div>';
	return html;
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
			$('#mFile_content').html(formatFileLabel(values.file));
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
			$(container).html(formatFileLabel(filename));
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

	var html = '';
	html += '<div class="metadata input-group" style="margin-bottom: 2px">';
	html += '<span style="font-size: inherit; display: block; text-align: justify; white-space: normal; padding: 1px  0px 1px 10px;" class="label label-primary">';
	html += title;
	html += ' <a class="modal-opener" data-width="50%" data-init="init" data-callback="submit" title="Modifier une métadonnée">';
	html += '<button class="btn btn-xs btn-primary edit-metadata" type="button"><span class="glyphicon glyphicon-pencil"></span></button>';
	html += '</a>';
	html += '<button onclick="removeMetadata($(this))" data-placement="right" style="border-radius:0; height: 20px; padding-top:0; padding-bottom: 0;" class="btn btn-xs btn-primary" type="button"><span class="glyphicon glyphicon-trash"></span></button>';
	html += '</span>';
	
	html += '<input type="hidden" value="">';
	html += '</div>';
	
	var tag = $(html);
	$(tag).find('input').val(JSON.stringify(value));
	$(tag).find('input').uniqueId();
	$(tag).find('input').attr('name', 'md_'+$(tag).find('input').attr('id'));
	
	return tag;
	
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
		var html = '<div class="col-md-offset-3" style="padding-left: 15px">';
		html += '<div style="margin-bottom: 5px; color: red"><strong>' + translate('Erreurs :') + '</strong></div>';
		for(var i in errors) {
			html += '<div style="margin-left: 10px; color: red"> * ' + errors[i] + '</div>';
		}
		html += '</div>';
		
		if (!$('.modal-body .errors').length) {
			$('.modal-body').append('<div class="errors">'+html+'</div>');
		} else {
			$('.modal-body .errors').html(html);
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