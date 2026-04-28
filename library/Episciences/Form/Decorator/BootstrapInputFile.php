<?php

class Episciences_Form_Decorator_BootstrapInputFile extends Zend_Form_Decorator_File
{
    public function buildLabel()
    {
        $element = $this->getElement();
        $label = $element->getLabel();
        if ($translator = $element->getTranslator()) {
            $label = $translator->translate($label);
        }
        $required = ($element->isRequired()) ? 'required' : '';
        return $element->getView()->formLabel($element->getName(), $label, array('class'=> $required.' control-label'));
    }
    	
	public function render($content)
	{
	    $element = $this->getElement();
	    if (!$element instanceof Zend_Form_Element) {
	        return $content;
	    }
	
	    $view = $element->getView();
	    if (!$view instanceof Zend_View_Interface) {
	        return $content;
	    }
	    
	    
	    $separator = $this->getSeparator();
	    $placement = $this->getPlacement();
		$label = $this->buildLabel();
		
		$description = $element->getDescription();
	    $name      = $element->getName();
		$attribs   = $this->getAttribs();
		$attribs['style'] = 'display: none';
	    
	    
	    if (!array_key_exists('id', $attribs)) {
	        $attribs['id'] = $name;
	    }
	
	    $markup    = array();
	    $size      = $element->getMaxFileSize();
	    if ($size > 0) {
	        $element->setMaxFileSize(0);
	        $markup[] = $view->formHidden('MAX_FILE_SIZE', $size);
	    }
	
	    if (Zend_File_Transfer_Adapter_Http::isApcAvailable()) {
	        $markup[] = $view->formHidden(ini_get('apc.rfc1867_name'), uniqid(), array('id' => 'progress_key'));
	    } else if (Zend_File_Transfer_Adapter_Http::isUploadProgressAvailable()) {
	        $markup[] = $view->formHidden('UPLOAD_IDENTIFIER', uniqid(), array('id' => 'progress_key'));
	    }
	    
	    if ($element->isArray()) {
	        $name .= "[]";
	        $count = $element->getMultiFile();
	        for ($i = 0; $i < $count; ++$i) {
	            $htmlAttribs        = $attribs;
	            $htmlAttribs['id'] .= '-' . $i;
	            $markup[] = $view->formFile($name, $htmlAttribs);
	        }
	    } else {
	        $markup[] = $view->formFile($name, $attribs);
	    }
	    
	    
	    $inputId = $attribs['id'];
	    $translator = $element->getTranslator();
	    $buttonLabel = $translator->translate('Parcourir');
	    
	    $errors = $element->getMessages();
	    $status = ($errors) ? 'error' : '';
	    
	    $markup[] = "<div class='control-group $status'>";
	    $markup[] = $label;
	    
	    $markup[] = "<div class='controls'>";
	    
	    $markup[] = "<div style='margin-left: 10px'>$description</div>";
	    
	    $markup[] = "<div class='input-append'>";
	    $markup[] = "<input id='value_$inputId' class='input-large' type='text' style='margin-left: 10px'>";
	    $markup[] = "<a class='btn' onclick='$(\"input[id=$inputId]\").click();'><span class='glyphicon glyphicon-file'></span> $buttonLabel</a>";
	    $markup[] = "</div>";
	    	    	    	    
	    $markup[] = "</div>";
	    
	    $markup[] = "</div>";
	    
	    $markup[] = "<script type='text/javascript'>";
	    $markup[] = "$('input[id=$inputId]').change(function() {";
	    $markup[] = "	$('#value_$inputId').val( $(this).val() );";
	    $markup[] = "});";
	    $markup[] = "</script>";
	
	    $markup = implode($separator, $markup);
	    
	    switch ($placement) {
	    	case self::PREPEND:
	    	    return $markup . $separator . $content;
	    	case self::APPEND:
	    	default:
	    	    return $content . $separator . $markup;
	    }
	}
}