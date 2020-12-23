<?php

/**
 * Controller permettant le rendu d'une page personnalisable
 *
 */
class PageDefaultController extends Zend_Controller_Action
{

    /**
     * Récupération du nom de la page à afficher
     *
     * @see Zend_Controller_Action::init()
     */
    public function init()
    {
        // Récupération du nom de la page
        $this->_page = $this->getRequest()->getActionName();
        $this->getRequest()->setActionName('render');
    }

    /**
     * Action récupérant le contenu de la page à afficher
     */
    public function renderAction()
    {
        $page = new Episciences_Website_Navigation_Page_Custom([
            'languages' => Zend_Registry::get('languages'),
            'page' => $this->_page
        ]);
        if (Episciences_Auth::isSecretary()|| Episciences_Auth::isWebmaster()) {
            $this->view->canEdit = true;
            if ($this->getRequest()->isPost()) {
                $params = $this->getRequest()->getPost();
                if (isset($params['method']) && $params['method'] == "edit") {
                    $this->view->mode = 'edit';
                    $this->view->form = $page->getContentForm();
                } elseif (isset($params['content'])) {
                    $params['content'] = array_filter($params['content']);
                    $page->setContent($params['content'], array_diff_key(Episciences_Tools::getLanguages(), $params['content']));
                }
            }
        }
        $this->view->content = $page->getContent(Zend_Registry::get('lang'));
        $this->view->page = $this->_page;
    }

}