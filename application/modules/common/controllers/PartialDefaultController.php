<?php

/**
 * Controller permettant le rendu de scripts partiels
 *
 */
class PartialDefaultController extends Zend_Controller_Action
{
    /** Request parameters accepted by modalAction(), cast to booleans */
    private const MODAL_BOOLEAN_PARAMS = ['buttons', 'hideSubmit'];

    /**
     * Action récupérant la structure d'une modalbox
     */
    public function modalAction(): void
    {
        $this->_helper->getHelper('layout')->disableLayout();

        // Only the layout options used by the JS caller are read from the request:
        // never let the client choose the markup (content) or the style of the modal.
        $request = $this->getRequest();
        foreach (self::MODAL_BOOLEAN_PARAMS as $name) {
            $value = $request->getParam($name);
            if ($value !== null) {
                $this->view->$name = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        }

        $this->renderScript('partials/modal.phtml');

    }

}
