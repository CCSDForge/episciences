<?php

/**
 * Controller permettant le rendu de scripts partiels
 *
 */
class PartialDefaultController extends Zend_Controller_Action
{

    /**
     * Action récupérant la structure d'une modalbox
     */
    public function modalAction()
    {
        $this->_helper->getHelper('layout')->disableLayout();

        // Only expose the options the XHR caller (createModalStructure() in functions.js) may set:
        // copying every request param into the view allowed injecting markup into the template
        $this->view->hideSubmit = filter_var($this->getRequest()->getPost('hideSubmit'), FILTER_VALIDATE_BOOLEAN);

        $this->renderScript('partials/modal.phtml');

    }

}
