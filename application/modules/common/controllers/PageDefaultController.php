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


        if (Episciences_Auth::isSecretary() || Episciences_Auth::isWebmaster()) {
            $this->view->canEdit = true;
            if ($this->getRequest()->isPost()) {
                $params = $this->getRequest()->getPost();

                if (array_key_exists('content', $params)) {
                    $pageContent = $params['content'];
                }

                if (isset($params['method']) && $params['method'] == "edit") {
                    $this->view->mode = 'edit';
                    $this->view->form = $page->getContentForm();
                } elseif (isset($pageContent)) {
                    $pageCode = $this->_page;
                    $titles = [];
                    if ($pageCode !== 'index') {

                        $menu = new Episciences_Website_Navigation(["sid" => RVID]);
                        $menu->load();
                        $pagesFromMenu = $menu->toArray();

                        foreach ($pagesFromMenu as $menuItem) {
                            if (isset($menuItem['permalien']) && $menuItem['permalien'] === $pageCode) {
                                foreach (Episciences_Tools::getLanguages() as $languageCode => $languageLabel) {
                                    $titles[$languageCode] = $this->view->translate($menuItem['label'], $languageCode);
                                }
                            }

                            if (!empty($menuItem['privilege'])) {
                                $pageVisibility = [$menuItem['privilege']];
                            } else {
                                $pageVisibility = ['public'];
                            }
                        }


                        $pageToSave = new Episciences_Page();
                        $pageToSave->setContent($pageContent);
                        $pageToSave->setTitle($titles);
                        $pageToSave->setCode(RVCODE);
                        $pageToSave->setUid(Episciences_Auth::getUid());
                        $pageToSave->setPageCode($pageCode);
                        $pageToSave->setVisibility($pageVisibility);

                        $previousPageToSave = Episciences_Page_Manager::findByCodeAndPageCode($pageToSave->getCode(), $pageToSave->getPageCode());

                        if ($previousPageToSave->getId() === 0) {
                            Episciences_Page_Manager::add($pageToSave);
                        } else {
                            $pageToSave->setId($previousPageToSave->getId());
                            Episciences_Page_Manager::update($pageToSave);
                        }
                    }
                    $page->setContent($pageContent, array_diff_key(Episciences_Tools::getLanguages(), $pageContent));


                }
            }
        }
        $pageContentForOutput = $page->getContent(Zend_Registry::get('lang'));
        if (str_contains($pageContentForOutput, self::WIDGET_BROWSE_LATEST_DIV)) {
            $view = new Zend_View();
            $view->articles = Episciences_Website_Navigation_Page_BrowseLatest::getLatestPublications();
            $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/browse/');
            $view->addHelperPath('Episciences/View/Helper', 'Episciences_View_Helper');
            $latest = $view->render('latest.phtml');
            unset($view);
            $pageContentForOutput = str_replace(self::WIDGET_BROWSE_LATEST_DIV, sprintf('<div id="browse-latest">%s</div>', $latest), $pageContentForOutput);
        }
        $this->view->content = $pageContentForOutput;
        $this->view->page = $this->_page;
    }

}