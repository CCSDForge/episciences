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

        foreach ($this->getRequest()->getParams() as $name => $value) {
            $this->view->$name = $value;
        }

        $this->renderScript('partials/modal.phtml');

    }

}
