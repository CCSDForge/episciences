<?php

/**
 * Controller permettant le rendu d'une page personnalisable
 *
 */
class PageDefaultController extends Zend_Controller_Action
{
    const WIDGET_BROWSE_LATEST_DIV = '<div id="widget-browse-latest"></div>';

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
        $pageContent = $page->getContent(Zend_Registry::get('lang'));
        if (str_contains($pageContent, self::WIDGET_BROWSE_LATEST_DIV)) {
            $view = new Zend_View();
            $view->articles = Episciences_Website_Navigation_Page_BrowseLatest::getLatestPublications();
            $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/browse/');
            $view->addHelperPath('Episciences/View/Helper', 'Episciences_View_Helper');
            $latest = $view->render('latest.phtml');
            unset($view);
            $pageContent = str_replace(self::WIDGET_BROWSE_LATEST_DIV, sprintf('<div id="browse-latest">%s</div>', $latest), $pageContent);
        }
        $this->view->content = $pageContent;
        $this->view->page = $this->_page;
    }

}