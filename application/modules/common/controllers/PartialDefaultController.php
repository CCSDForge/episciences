<?php

/**
 * Controller permettant le rendu de scripts partiels
 *
 */
class PartialDefaultController extends Episciences_Controller_Action
{
    /** Request parameters accepted by modalAction(), cast to booleans */
    private const MODAL_BOOLEAN_PARAMS = ['buttons', 'hideSubmit'];

    /**
     * Action récupérant la structure d'une modalbox
     */
    public function modalAction(): void
    {
        $this->_helper->getHelper('layout')->disableLayout();

        foreach (self::extractModalOptions($this->getRequest()) as $name => $value) {
            $this->view->$name = $value;
        }

        $this->renderScript('partials/modal.phtml');

    }


    /**
     * Only the layout options used by the JS caller are read from the request:
     * never let the client choose the markup or the style of the modal.
     * `buttons` and `hideSubmit` are cast to booleans ('false', '0', 'off' => false).
     *
     * @return array<string, bool>
     */
    public static function extractModalOptions(Zend_Controller_Request_Abstract $request): array
    {
        $options = [];
        foreach (self::MODAL_BOOLEAN_PARAMS as $name) {
            $value = $request->getParam($name);
            if ($value !== null) {
                $options[$name] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        }
        return $options;
    }
}
