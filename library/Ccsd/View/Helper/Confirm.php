<?php

/**
 * Confirmation (alerte Bootstrap)
 * @author yannick
 *
 */
class Ccsd_View_Helper_Confirm extends Zend_View_Helper_Abstract
{
	/**
	 * Titre de la popup
	 * @var string
	 */
	protected $_title = '';
	
	/**
	 * Contenu de la popup
	 * @var string
	 */
	protected $_content = '';

	/**
	 * Element(s) déclanchant l'ouverture de la popup
	 * @var string (peut être classe CSS (prefixé par .) ou ID (prefixé par #)
	 */
	protected $_triggerClass = '';
	
	/**
	 * Javascript excecuté avant l'ouverture de la popup
	 * @var string
	 */
	protected $_jsInit = '';
	
	/**
	 * Javascript excecuté après la fermeture de la popup (lorsque l'utilisateur clique sur le bouton princial)
	 * @var string (nom de fonction  ou code javascript)
	 */
	protected $_jsCallback = '';
	
	/**
	 * Identifiant de la confirmbox
	 * @var string
	 */
	protected $_id = 'confirmModal';
	
	/**
	 * 
	 * @param string $title
	 * @param string $content
	 * @return Ccsd_View_Helper_Confirm
	 */
	public function confirm($title = '', $content = '', $trigger = '')
	{
		$this->setTitle($title);
		$this->setContent($content);
		$this->setTrigger($trigger);
		return $this;
	}
	
	/**
	 * initialisation du titre de la popup
	 * @param string $title
	 * @return Ccsd_View_Helper_Confirm
	 */
	public function setTitle($title) 
	{
		$this->_title = $title;
		return $this;
	}
	
	/**
	 * initialisation du contenu de la popup
	 * @param string $content
	 * @return Ccsd_View_Helper_Confirm
	 */
	public function setContent($content)
	{
		$this->_content = $content;
		return $this;
	}
	
	/**
	 * initialisation de l'élément qui va déclancher l'affichage de la popup
	 * @param string $class
	 * @return Ccsd_View_Helper_Confirm
	 */
	public function setTrigger($class)
	{
		$this->_triggerClass = $class;
		return $this;
	}
	
	/**
	 * initialisation du code javascript excecuté avant l'ouverture de la popup
	 * @param string $js
	 * @return Ccsd_View_Helper_Confirm
	 */
	public function setJsInit($js)
	{
		$this->_jsInit = $js;
		return $this;
	}
	
	/**
	 * initialisation du code javascript excecuté après l'ouverture de la popup
	 * @param string $js
	 * @return Ccsd_View_Helper_Confirm
	 */
	public function setJsCallback($js)
	{
		$this->_jsCallback = $js;
		return $this;
	}
	
	/**
	 * initialisation de l'id de la popup
	 * @param string $js
	 * @return Ccsd_View_Helper_Confirm
	 */
	public function setId($id)
	{
		$this->_id = $id;
		return $this;
	}
	
	/**
	 * Affichage du helper
	 * @return string
	 */
	public function render()
    {
		$render = '<div id="' . $this->_id . '" class="modal fade" tabindex="-1" role="dialog" style="display:none;">';
		$render .= '<div class="modal-dialog">';
		$render .= '<div class="modal-content">';
		$render .= ' <div class="modal-header">';
		$render .= '  <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>';
		$render .= '  <h3>' . $this->view->translate($this->_title) . '</h3>';
    	$render .= ' </div>';
    	$render .= ' <div class="modal-body">';
    	$render .= '  <p>' . $this->view->translate($this->_content) . '</p>';
    	$render .= '  <input type="hidden" id="confirm-id" value="" />';
    	$render .= '  <input type="hidden" id="page-id-input" value="" />';
    	$render .= ' </div>';
    	$render .= ' <div class="modal-footer">';
    	$render .= '  <button class="btn btn-default btn-sm" type="button" data-dismiss="modal" aria-hidden="true">' . $this->view->translate("Annuler") . '</button>';
    	$render .= '  <button type="button" class="btn btn-primary" ';
    	if ($this->_jsCallback != '') {
    		$render .= '   onclick="' .$this->_jsCallback . ';' . "$('#" . $this->_id . "').modal('hide');" . '"';
    	}
    	$render .= '   >' . $this->view->translate("Oui") . '</button>';
    	$render .= ' </div>';
    	$render .= '</div>';
    	$render .= '</div>';
    	$render .= '</div>';

    	if ($this->_triggerClass != '') {
    		$render .= '<script>';
    		$render .= ' $(document).ready(function(){';
    		$render .= '  $("body").delegate("' . $this->_triggerClass. '", "click", function() {';
    		if ($this->_jsInit != '') {
    			$render .= $this->_jsInit;
    		}
    		$render .= '   $("#' . $this->_id . '").modal({"keyboard" : true});';
    		$render .= '  });';
    		$render .= ' });';
    		$render .= '</script>';
    		
    	}
    	
        return $render;
	}

    public function __toString()
    {
    	return $this->render();
	}
	
}