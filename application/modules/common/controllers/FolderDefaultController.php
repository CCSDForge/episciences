<?php

/**
 * Controller permettant le rendu d'une page personnalisable
 *
 */
class FolderDefaultController extends Zend_Controller_Action
{

    public function init ()
    {
        // Récupération du nom de la page
        $this->_action = $this->getRequest()->getActionName();
        $this->getRequest()->setActionName('render');
    }

    public function renderAction ()
    {
    	//$active = false;//$this->nav()->findActive($this->nav()->getContainer());
    	//$title = $active ? $active['page']->getLabel() : '';
    	//$this->view->title = $title;
    	$this->renderScript('index/submenu.phtml');
    }
}