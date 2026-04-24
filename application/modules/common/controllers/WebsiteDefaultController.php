<?php

class WebsiteDefaultController extends Episciences_Controller_Action
{
    protected $_session = null;

    public function init()
    {
        //Session courante
        $this->_session = new Zend_Session_Namespace(SESSION_NAMESPACE);

        // Initialisation du jeton CSRF
        if (!isset($this->_session->csrfToken)) {
            $this->_session->csrfToken = bin2hex(random_bytes(32));
        }
        $this->view->csrfToken = $this->_session->csrfToken;
    }

    /**
     * Liste des sous menus du controller
     */
    public function indexAction()
    {
        $this->view->title = "Site Web";
        $this->renderScript('index/submenu.phtml');
    }

    /**
     * Configuration générale (langue)
     */
    public function commonAction()
    {
        $common = new Ccsd_Website_Common(RVID, ['languages' => Episciences_Translation_Plugin::getAvailableLanguages()]);
        $form = $common->getForm();
        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            $common->save($form->getValues());
            unset($this->_session->website);
            Zend_Registry::set('languages', $form->getValue('languages'));
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_SUCCESS)->addMessage("Les modifications ont bien été enregistrées.");
            $this->redirect($this->url(['controller' => 'website', 'action' => 'common']));
        }
        $this->view->form = $form;
    }

    /**
     * Personnalisation du style du site
     */
    public function styleAction()
    {
        $styles = new Episciences_Website_Style();
        if ($this->getRequest()->isPost() && $styles->getForm()->isValid($this->getRequest()->getParams())) {
            $styles->save($styles->getForm()->getValues());
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_SUCCESS)->addMessage("Les modifications ont bien été enregistrées.");
        }
        $styles->populate();
        $this->view->form = $styles->getForm();
    }

    /**
     * Modification de l'en-tête d'un site
     */
    public function headerAction()
    {
        $header = new Episciences_Website_Header();
        if ($this->getRequest()->isPost() && isset($_POST['header'])) {
            $isValid = $header->isValid($this->getRequest()->getPost(), $_FILES);
            if (true === $isValid) { //Formulaire valide
                $header->save($this->getRequest()->getPost(), $_FILES);
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_SUCCESS)->addMessage("Les modifications ont bien été enregistrées.");
            } else {
                $this->view->errors = $isValid;
                $header->setHeader($this->getRequest()->getPost(), $_FILES);
                $this->view->forms = $header->getForms(false);
            }
        }
        if (!isset($this->view->forms)) {
            $this->view->forms = $header->getForms();
        }
    }

    /**
     * Modification de l'en-tête d'un site
     */
    public function ajaxheaderAction()
    {
        $this->_helper->layout()->disableLayout();
        $header = new Episciences_Website_Header();
        $this->view->form = $header->getLogoForm($this->getRequest()->getParam('id', '0'));
        $this->render('header-logo-form');
    }

    /**
     * Affichage des ressources publiques d'un site
     */
    public function publicAction(): void
    {


        $dir = REVIEW_PATH . 'public/';
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $params = $request->getParams();

        try {
            $translator = Zend_Registry::get('Zend_Translate');
        } catch (Zend_Exception $e) {
            error_log($e->getMessage());
            $translator = null;
        }

        if (isset($params['method']) && $request->isPost()) {
            if ($params['method'] === 'remove') {
                // Suppression d'un fichier
                if (isset($params['name'])) {
                    $fileName = basename($params['name']);
                    $filePath = $dir . $fileName;
                    if (is_file($filePath)) {
                        unlink($filePath);
                    }
                }
            } elseif (isset($_FILES['file']['tmp_name']) && $_FILES['file']['tmp_name'] !== '') {
                // Ajout d'un fichier
                $isOverwritten = isset($params['overwriteFile']) && $params['overwriteFile'] === 'on';
                preg_match('/[^a-z0-9_\.-]/i', $_FILES['file']['name'], $matches);
                $renamedFile = Ccsd_File::renameFile($_FILES['file']['name'], $dir, !$isOverwritten);
                move_uploaded_file($_FILES['file']['tmp_name'], $dir . $renamedFile);
                if ($translator) {
                    $message = $translator->translate('Le fichier a été déposé.');
                    if ($renamedFile !== $_FILES['file']['name']) {
                        $message = sprintf(
                            '%s "%s" %s "%s" %s',
                            $translator->translate('Le fichier a été téléchargé et a été renommé'),
                            $renamedFile,
                            $translator->translate('car'),
                            $_FILES['file']['name'],
                            empty($matches) ? $translator->translate('existe déjà.') : $translator->translate('contient des caractères non valides')
                        );
                    }
                }

                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_SUCCESS)->addMessage($message);
                // Prevent the file from being saved repeatedly each time the page is refreshed.
                $this->_helper->redirector->goToUrl($request->getRequestUri());
            }
        }

        $files = $this->getFileCollectionForUser($dir);
        $this->view->files = $files;
    }

    /**
     * Valide le jeton CSRF pour les requêtes AJAX et POST
     * @return bool
     */
    protected function _validateCsrf()
    {
        $request = $this->getRequest();
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $expectedToken = $session->csrfToken;

        if (!$expectedToken) {
            return false;
        }

        if ($request->isXmlHttpRequest()) {
            return $request->getHeader('X-CSRF-Token') === $expectedToken;
        }

        return $request->getPost('csrf_token') === $expectedToken;
    }

    /**
     * Gestion des pages du site
     */
    public function menuAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        // Si ce n'est pas une requête AJAX ou POST (donc un accès direct en GET)
        // On force le rechargement depuis la base de données
        if (!$request->isXmlHttpRequest() && !$request->isPost()) {
            unset($this->_session->website);
        }

        if (!isset($this->_session->website)) {
            //Récupération de la navigation du portail ou d'un journal
            $this->_session->website = new Episciences_Website_Navigation(['languages' => Zend_Registry::get('languages'), 'sid' => RVID]);
            $this->_session->website->load();
        }

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ($request->isPost() && !$request->getPost('lang')) {
            $valid = true;
            $pagesDisplay = [];

            foreach ($request->getPost() as $id => $options) {

                if (!str_starts_with($id, 'pages_')) {
                    continue;
                }

                $pageid = str_replace('pages_', '', $id);

                if (isset($_FILES[$id]['name']) && is_array($_FILES[$id]['name'])) {
                    $options = array_merge($options, $_FILES[$id]['name']);
                }

                //Cas particulier des filtres
                if (isset($options['filter']) && is_array($options['filter'])) {
                    $options['filter'] = implode(';', $options['filter']);
                }

                $this->_session->website->setPage($pageid, $options);
                $this->_session->website->getPage($pageid)->initForm();

                if ($options['type'] !== 'Episciences_Website_Navigation_Page_File' && !$this->_session->website->getPage($pageid)->getForm($pageid)->isValid($options)) {
                    $pagesDisplay[$pageid] = true;
                    $valid = false;
                } else {
                    $pagesDisplay[$pageid] = false;
                }
            }

            if ($valid) {
                //Tous les elements sont valides
                //Enregistrement du menu

                $this->_session->website->save();
                //Création de la navigation du site et des ACL
                $this->_session->website->createNavigation(REVIEW_PATH . 'config/' . 'navigation.json');
                if (is_file(REVIEW_PATH . 'config/' . 'acl.ini')) {
                    unlink(REVIEW_PATH . 'config/' . 'acl.ini');
                }
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_SUCCESS)->addMessage("Les modifications ont bien été enregistrées.");
                $this->redirect($this->url(['controller' => 'website', 'action' => 'menu']));
            } else {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->addMessage("Error while saving, please try again, it might be a temporary problem");
            }
            $this->view->pagesDisplay = $pagesDisplay;
        }
        $pageTypes = $this->_session->website->getPageTypes(true);
        $groupedPageTypes = $this->processPageTypes($pageTypes);
        $predefinedPageTypes = $this->getPredefinedPageTypes($pageTypes);
        $this->view->pages = $this->_session->website->getPages();
        $this->view->order = $this->_session->website->getOrder();
        $this->view->pageTypes = $pageTypes;
        $this->view->groupedPageTypes = $groupedPageTypes;
        $this->view->predefinedPageTypes = $predefinedPageTypes;

    }

    private function processPageTypes(array $pageTypes = []): array
    {

        $journal = Episciences_ReviewsManager::find(RVCODE);
        $isSwitched = $journal->isNewFrontSwitched();

        $processed = [];

        if (!$isSwitched) { // old sites
            //Flat mode
            $processed[''] = array_keys($pageTypes);
        } else {
            // Group mode
            $typeToGroup = [];
            foreach (Episciences_Website_Navigation::$groupedPages as $group => $gTypes) {
                foreach ($gTypes as $t) {
                    $typeToGroup[$t] = $group;
                }
            }

            foreach ($pageTypes as $type => $label) {
                if (!isset($typeToGroup[$type])) {
                    continue;
                }
                $group = $typeToGroup[$type];
                $processed[$group][] = $type;
            }
        }
        $this->sort($processed);

        return $processed;
    }

    private function sort(array &$processed): void
    {
        foreach ($processed as $group => $types) {
            asort($types, SORT_STRING); // ordered values, keys retained
            $processed[$group] = $types;
        }

        ksort($processed, SORT_STRING); //  Sort groups by their key
    }

    /**
     * Identifie les types de pages prédéfinies
     * @param array $pageTypes
     * @return array Tableau avec les types comme clés et true si prédéfini
     */
    private function getPredefinedPageTypes(array $pageTypes = []): array
    {
        $predefined = [];
        foreach ($pageTypes as $type => $label) {
            $className = sprintf('Episciences_Website_Navigation_Page_%s', ucfirst($type));
            $predefined[$type] = class_exists($className) && is_a($className, 'Episciences_Website_Navigation_Page_Predefined', true);
        }
        return $predefined;
    }

    /**
     * Ajout d'une nouvelle page
     */
    public function ajaxformpageAction()
    {
        if (!$this->_validateCsrf()) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }

        if (!isset($this->_session->website)) {
            $this->_session->website = new Episciences_Website_Navigation(['languages' => Zend_Registry::get('languages'), 'sid' => RVID]);
            $this->_session->website->load();
        }

        if (!Episciences_Auth::isAdministrator()
            && !Episciences_Auth::isChiefEditor()
            && !Episciences_Auth::isSecretary()
            && !Episciences_Auth::isWebmaster()
            && !Episciences_Auth::isRoot()) {
            return;
        }
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $request = $this->getRequest();
        $params = $request->getPost();
        if ($request->isXmlHttpRequest() && $request->isPost() && isset($params['type']) && $params['type'] != '') {
            $this->view->i = $this->_session->website->addPage($params['type']);
            $this->view->page = $this->_session->website->getPage($this->view->i);
            $this->render('menu-page-form');
        }
    }

    /**
     * Modification de l'ordre des pages
     */
    public function ajaxorderAction()
    {
        if (!$this->_validateCsrf()) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }

        if (!isset($this->_session->website)) {
            $this->_session->website = new Episciences_Website_Navigation(['languages' => Zend_Registry::get('languages'), 'sid' => RVID]);
            $this->_session->website->load();
        }

        if (!Episciences_Auth::isAdministrator()
            && !Episciences_Auth::isChiefEditor()
            && !Episciences_Auth::isSecretary()
            && !Episciences_Auth::isWebmaster()
            && !Episciences_Auth::isRoot()) {
            return;
        }
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $request = $this->getRequest();
        $params = $request->getPost();
        if ($request->isXmlHttpRequest() && $request->isPost() && isset($params['page']) && is_array($params['page'])) {
            $this->_session->website->changeOrder($params['page']);
        }
    }

    /**
     * Suppression d'une page du site
     */
    public function ajaxrmpageAction(): void
    {
        if (!$this->_validateCsrf()) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }

        if (!isset($this->_session->website)) {
            $this->_session->website = new Episciences_Website_Navigation(['languages' => Zend_Registry::get('languages'), 'sid' => RVID]);
            $this->_session->website->load();
        }

        if (!Episciences_Auth::isAdministrator()
            && !Episciences_Auth::isChiefEditor()
            && !Episciences_Auth::isSecretary()
            && !Episciences_Auth::isWebmaster()
            && !Episciences_Auth::isRoot()) {
            return;
        }
        $pageCodeToRemove = null;
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $request = $this->getRequest();
        $params = $request->getPost();
        if ($request->isXmlHttpRequest() && $request->isPost() && isset($params['idx'])) {
            $nav = $this->_session->website;
            /** @var Ccsd_Website_Navigation $nav */
            if (!empty($params['page_id'])) {
                $pageCodeToRemove = $params['page_id'];
            }
            $nav->deletePage((int)$params['idx'], $pageCodeToRemove);

        }
    }

    /**
     * Réinitialisation du menu
     */
    public function resetAction()
    {
        unset($this->_session->website);
        $this->redirect($this->url(['controller' => 'website', 'action' => 'menu']));
    }

    /**
     * Gestion des actualités
     */
    public function newsAction(): void
    {
        $newsObj = new Episciences_News();
        $newsid  = (int) $this->getRequest()->getParam('newsid', 0);

        if ($this->getRequest()->isPost()) {
            if (!$this->_validateCsrf()) {
                $this->_helper->FlashMessenger
                    ->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)
                    ->addMessage("Requête invalide.");
                $this->redirect($this->url(['controller' => 'website', 'action' => 'news']));
                return;
            }

            $post = $this->getRequest()->getPost();

            // Delete
            if ($this->getRequest()->getPost('action') === 'delete') {
                $delId = (int) ($post['newsid'] ?? 0);
                if ($delId > 0) {
                    $newsObj->delete($delId);
                    Episciences_JournalNews::deleteByLegacyId($delId);
                    $this->_helper->FlashMessenger
                        ->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_SUCCESS)
                        ->addMessage("L'actualité a été supprimée.");
                }
                $this->redirect('/website/news');
                return;
            }

            // Add / edit
            $newsid = (int) ($post['newsid'] ?? 0);
            $form   = $newsObj->getForm($newsid);
            if ($form->isValid($post)) {
                $newsObj->save(array_merge($form->getValues(), ['uid' => Episciences_Auth::getUid()]));
                $this->_helper->FlashMessenger
                    ->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_SUCCESS)
                    ->addMessage("Les modifications ont bien été enregistrées.");
                $this->redirect($this->url(['controller' => 'website', 'action' => 'news']));
                return;
            }

            $this->view->editNewsid  = $newsid;
            $this->view->editNews    = $newsObj->getNews($newsid);
            $this->view->formValues  = $post;
        } elseif ($newsid > 0) {
            $this->view->editNewsid = $newsid;
            $this->view->editNews   = $newsObj->getNews($newsid);
        }

        $this->view->news      = $newsObj->getListNews(false);
        $this->view->languages = Zend_Registry::get('languages');
    }

    /**
     * @param string $dir
     * @return array
     */
    private function getFileCollectionForUser(string $dir): array
    {
        $files = [];
        foreach (new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS) as $fileInfo) {
            if ($fileInfo->isFile() && $fileInfo->getFilename() !== 'style.css') {
                $files[$fileInfo->getFilename()] = $fileInfo->getPathname();
            }
        }
        return $files;
    }

}

