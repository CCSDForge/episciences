<?php


class PageDefaultController extends Zend_Controller_Action
{
    const WIDGET_BROWSE_LATEST_DIV = '<div id="widget-browse-latest"></div>';
    private const MENU_ITEM_LABEL = 'label';
    private const MENU_ITEM_PRIVILEGE = 'privilege';
    private const MENU_ITEM_PERMALIEN = 'permalien';
    private const MENU_ITEM_PAGES = 'pages';
    public string $_page;

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
                    $pageVisibility = ['public'];
                    if ($pageCode !== 'index') {

                        $menu = new Episciences_Website_Navigation(["sid" => RVID]);
                        $menu->load();
                        $pagesFromMenu = $menu->toArray();
                        $labelAndPrivilege = self::findLabelAndPrivilegeByPageCode($pagesFromMenu, $pageCode);

                        if ($labelAndPrivilege[self::MENU_ITEM_LABEL] !== null) {
                            foreach (Episciences_Tools::getLanguages() as $languageCode => $languageLabel) {
                                $titles[$languageCode] = $this->view->translate($labelAndPrivilege[self::MENU_ITEM_LABEL], $languageCode);
                            }
                        }

                        if ($labelAndPrivilege[self::MENU_ITEM_PRIVILEGE] !== null) {
                            $pageVisibility = [$labelAndPrivilege[self::MENU_ITEM_PRIVILEGE]];
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

    private static function findLabelAndPrivilegeByPageCode(array $menuItemArray, string $pageCode): array
    {
        $label = null;
        $privilege = null;

        // Define a recursive helper function
        $findLabelAndPrivilege = function ($arr) use (&$findLabelAndPrivilege, $pageCode, &$label, &$privilege) {
            foreach ($arr as $item) {
                if (isset($item[self::MENU_ITEM_PERMALIEN]) && $item[self::MENU_ITEM_PERMALIEN] == $pageCode) {
                    $label = $item[self::MENU_ITEM_LABEL];
                    $privilege = $item[self::MENU_ITEM_PRIVILEGE] ?? null;
                    return;
                } elseif (is_array($item) && array_key_exists(self::MENU_ITEM_PAGES, $item)) {
                    $findLabelAndPrivilege($item[self::MENU_ITEM_PAGES]);
                }
            }
        };

        // Call the recursive helper function
        $findLabelAndPrivilege($menuItemArray);

        return array(self::MENU_ITEM_LABEL => $label, self::MENU_ITEM_PRIVILEGE => $privilege);
    }


}