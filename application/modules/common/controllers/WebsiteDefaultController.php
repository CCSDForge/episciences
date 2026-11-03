<?php

use Episciences\Security\AccessLog;
use Episciences\Upload\PublicFileStore;
use Episciences\Upload\UploadChecker;

class WebsiteDefaultController extends Zend_Controller_Action
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
        if (!$this->allowSiteManagers()) {
            return;
        }

        $common = new Ccsd_Website_Common(RVID, ['languages' => Episciences_Translation_Plugin::getAvailableLanguages()]);
        $form = $common->getForm();
        Episciences_Csrf_Helper::addSessionTokenElement($form);
        if ($this->getRequest()->isPost() && !$this->_validateCsrf()) {
            $this->addCsrfError();
        } elseif ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            $common->save($form->getValues());
            unset($this->_session->website);
            Zend_Registry::set('languages', $form->getValue('languages'));
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_SUCCESS)->addMessage("Les modifications ont bien été enregistrées.");
            $this->redirect('/website/common');
        }
        $this->view->form = $form;
    }

    /**
     * Personnalisation du style du site
     */
    public function styleAction()
    {
        if (!$this->allowSiteManagers()) {
            return;
        }

        $styles = new Episciences_Website_Style();
        Episciences_Csrf_Helper::addSessionTokenElement($styles->getForm());
        if ($this->getRequest()->isPost() && !$this->_validateCsrf()) {
            $this->addCsrfError();
        } elseif ($this->getRequest()->isPost() && $styles->getForm()->isValid($this->getRequest()->getParams())) {
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
        if (!$this->allowSiteManagers()) {
            return;
        }

        $isSubmitted = $this->getRequest()->isPost() && isset($_POST['header']);
        if ($isSubmitted && !$this->_validateCsrf()) {
            // The submitted values are not shown again: they may come from another site
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->addMessage("Les modifications n'ont pas été enregistrées car le formulaire a expiré. Veuillez les saisir à nouveau.");
            $this->redirect('/website/header');
            return;
        }

        $header = new Episciences_Website_Header();
        if ($isSubmitted) {
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
        if (!$this->allowSiteManagers()) {
            return;
        }

        $header = new Episciences_Website_Header();
        $this->view->form = $header->getLogoForm($this->getRequest()->getParam('id', '0'));
        $this->render('header-logo-form');
    }

    /**
     * Affichage des ressources publiques d'un site
     */
    public function publicAction(): void
    {
        if (!$this->allowSiteManagers()) {
            return;
        }

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
        }

        if (isset($params['method']) && $request->isPost()) {
            if (!$this->_validateCsrf()) {
                $this->addCsrfError();
                $this->_helper->redirector->goToUrl($request->getRequestUri());
                return;
            }

            if ($params['method'] === 'remove') {
                //Suppression d'un fichier
                // Keep only the file name so the removal stays within $dir.
                $fileToRemove = isset($params['name']) ? basename((string)$params['name']) : '';
                if ($fileToRemove !== '' && is_file($dir . $fileToRemove)) {
                    unlink($dir . $fileToRemove);
                }
            } else if (isset($_FILES['file']['tmp_name']) && $_FILES['file']['tmp_name'] !== '') {

                //Ajout d'un fichier

                $uploadError = UploadChecker::firstPublicFileError(
                    (string)$_FILES['file']['tmp_name'],
                    (string)($_FILES['file']['name'] ?? ''),
                    (int)($_FILES['file']['error'] ?? UPLOAD_ERR_OK)
                );

                if ($uploadError !== null) {
                    // Flash messages are displayed as HTML and the message contains the name sent by the browser
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->addMessage(htmlspecialchars($uploadError, ENT_QUOTES));
                    $this->_helper->redirector->goToUrl($request->getRequestUri());
                    return;
                }

                $isOverwritten = isset($params['overwriteFile']) && $params['overwriteFile'] === 'on';

                preg_match('/[^a-z0-9_\.-\/\\\\]/i', $_FILES['file']['name'], $matches);

                $renamedFile = Ccsd_File::renameFile(PublicFileStore::storedName((string)$_FILES['file']['name']), $dir, !$isOverwritten);

                copy($_FILES['file']['tmp_name'], $dir . $renamedFile);

                $message = $translator->translate('Le fichier a été déposé.');

                if ($renamedFile !== $_FILES['file']['name']) {
                    $message = $translator->translate('Le fichier a été téléchargé et a été renommé');
                    $message .= ' "';
                    $message .= $renamedFile;
                    $message .= '"';
                    $message .= ' ';
                    $message .= $translator->translate('car');
                    $message .= ' "';
                    $message .= htmlspecialchars((string)$_FILES['file']['name'], ENT_QUOTES);
                    $message .= '" ';
                    $message .= empty($matches) ? $translator->translate('existe déjà.') : $translator->translate('contient des caractères non valides');
                }

                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_SUCCESS)->addMessage($message);
                //Prevent the file from being saved repeatedly each time the page is refreshed.
                $this->_helper->redirector->goToUrl($request->getRequestUri());
            }
        }

        $files = [];
        foreach (scandir($dir) as $file) {
            if (!in_array($file, ['.', '..', 'paper-status'])) {
                $files[$file] = $dir . $file;
            }
        }
        $this->view->files = $files;
    }

    /**
     * The site settings are managed by the secretaries (which include administrators, chief
     * editors and root) and the webmasters: other users get a 403, logged in the security log,
     * and nothing is rendered.
     */
    private function allowSiteManagers(): bool
    {
        if (Episciences_Auth::isSecretary() || Episciences_Auth::isWebmaster()) {
            return true;
        }

        AccessLog::denied(AccessLog::ACL_DENIED, 'role_check');
        $this->getResponse()->setHttpResponseCode(403);
        $this->_helper->viewRenderer->setNoRender();

        return false;
    }

    /**
     * Guard of the AJAX changes of the site pages: the role first, then the request token
     * (a refused token is logged by Episciences_Csrf_Helper). Nothing is rendered unless the
     * action asks for it.
     */
    private function allowAjaxSiteChange(): bool
    {
        $this->_helper->layout()->disableLayout();
        if (!$this->allowSiteManagers()) {
            return false;
        }

        $this->_helper->viewRenderer->setNoRender();
        if (!$this->_validateCsrf()) {
            $this->getResponse()->setHttpResponseCode(403);
            return false;
        }

        return true;
    }

    /**
     * The navigation being edited, kept in session until it is saved or reset
     *
     * @return Episciences_Website_Navigation
     */
    private function sessionNavigation()
    {
        if (!isset($this->_session->website)) {
            //Récupération de la navigation du portail ou d'un journal
            $this->_session->website = new Episciences_Website_Navigation(['languages' => Zend_Registry::get('languages'), 'sid' => RVID]);
            $this->_session->website->load();
        }

        return $this->_session->website;
    }

    /**
     * Flash the message shown when a form is submitted with a missing or wrong token
     */
    private function addCsrfError(): void
    {
        $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->addMessage(Zend_Registry::get('Zend_Translate')->translate('Votre session a expiré, veuillez réessayer.'));
    }

    /**
     * Valide le jeton CSRF pour les requêtes AJAX et POST
     * @return bool
     */
    protected function _validateCsrf()
    {
        return Episciences_Csrf_Helper::validateRequestToken($this->getRequest());
    }

    /**
     * Gestion des pages du site
     */
    public function menuAction(): void
    {
        if (!$this->allowSiteManagers()) {
            return;
        }

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        // Si ce n'est pas une requête AJAX ou POST (donc un accès direct en GET)
        // On force le rechargement depuis la base de données
        if (!$request->isXmlHttpRequest() && !$request->isPost()) {
            unset($this->_session->website);
        }

        $this->sessionNavigation();

        if ($request->isPost() && !$this->_validateCsrf()) {
            $this->addCsrfError();
        } elseif ($request->isPost()) {
            $valid = true;
            $pagesDisplay = [];

            foreach ($request->getPost() as $id => $options) {

                if (!str_starts_with($id, 'pages_')) {
                    continue;
                }

                $pageid = str_replace('pages_', '', $id);

                // The file of a page only comes from an upload, never from a field of the form
                unset($options['src']);

                if (isset($_FILES[$id]['name']) && is_array($_FILES[$id]['name'])) {
                    $options = array_merge($options, $_FILES[$id]['name']);
                }

                //Cas particulier des filtres
                if (isset($options['filter']) && is_array($options['filter'])) {
                    $options['filter'] = implode(';', $options['filter']);
                }

                // The class of the page stored in session decides, never the type sent by the browser
                $isFilePage = $this->_session->website->getPage($pageid) instanceof Episciences_Website_Navigation_Page_File;
                $uploadError = $isFilePage ? Episciences_Website_Navigation_Page_File::validateUpload($_FILES[$id] ?? null) : null;

                if ($uploadError !== null) {
                    // Keep the file that was already attached to the page
                    unset($options['src']);
                }

                $this->_session->website->setPage($pageid, $options);
                $this->_session->website->getPage($pageid)->initForm();

                if ($uploadError !== null) {
                    $this->_session->website->getPage($pageid)->setUploadError($uploadError);
                    $pagesDisplay[$pageid] = true;
                    $valid = false;
                } elseif (!$isFilePage && !$this->_session->website->getPage($pageid)->getForm($pageid)->isValid($options)) {
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
                $this->redirect('/website/menu');
            } else {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->addMessage("Erreur de saisie");
            }
            $this->view->pagesDisplay = $pagesDisplay;
        }
        $this->view->pages = $this->_session->website->getPages();
        $this->view->order = $this->_session->website->getOrder();
        $this->view->pageTypes = $this->_session->website->getPageTypes(true);

    }

    /**
     * Ajout d'une nouvelle page
     */
    public function ajaxformpageAction()
    {
        if (!$this->allowAjaxSiteChange()) {
            return;
        }

        $request = $this->getRequest();
        $params = $request->getPost();
        if ($request->isXmlHttpRequest() && $request->isPost() && isset($params['type']) && $params['type'] != '') {
            $navigation = $this->sessionNavigation();
            $this->view->i = $navigation->addPage($params['type']);
            $this->view->page = $navigation->getPage($this->view->i);
            $this->render('menu-page-form');
        }
    }

    /**
     * Modification de l'ordre des pages
     */
    public function ajaxorderAction()
    {
        if (!$this->allowAjaxSiteChange()) {
            return;
        }

        $request = $this->getRequest();
        $params = $request->getPost();
        if ($request->isXmlHttpRequest() && $request->isPost() && isset($params['page']) && is_array($params['page'])) {
            $this->sessionNavigation()->changeOrder($params['page']);
        }
    }

    /**
     * Suppression d'une page du site
     */
    public function ajaxrmpageAction(): void
    {
        if (!$this->allowAjaxSiteChange()) {
            return;
        }

        $pageCodeToRemove = null;

        $request = $this->getRequest();
        $params = $request->getPost();
        if ($request->isXmlHttpRequest() && $request->isPost() && isset($params['idx'])) {
            $nav = $this->sessionNavigation();
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
        if (!$this->allowSiteManagers()) {
            return;
        }

        unset($this->_session->website);
        $this->redirect('/website/menu');
    }

    /**
     * News management — add, edit, delete.
     */
    public function newsAction(): void
    {
        if (!$this->allowSiteManagers()) {
            return;
        }

        $newsObj = new Episciences_News();
        $newsid  = (int) $this->getRequest()->getParam('newsid', 0);

        if ($this->getRequest()->isPost()) {
            if (!$this->_validateCsrf()) {
                $this->_helper->FlashMessenger
                    ->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)
                    ->addMessage("Requête invalide.");
                $this->redirect('/website/news');
                return;
            }

            $post = $this->getRequest()->getPost();

            // Delete
            if ($this->getRequest()->getPost('action') === 'delete') {
                $delId = (int) ($post['newsid'] ?? 0);
                if ($newsObj->delete($delId)) {
                    Episciences_JournalNews::deleteByLegacyId($delId);
                    $this->_helper->FlashMessenger
                        ->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_SUCCESS)
                        ->addMessage("L'actualité a été supprimée.");
                } else {
                    $this->_helper->FlashMessenger
                        ->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)
                        ->addMessage("Actualité introuvable.");
                }
                $this->redirect('/website/news');
                return;
            }

            // Add / edit
            $newsid = (int) ($post['newsid'] ?? 0);
            if ($newsid > 0 && !$newsObj->belongsToSite($newsid)) {
                $this->_helper->FlashMessenger
                    ->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)
                    ->addMessage("Actualité introuvable.");
                $this->redirect('/website/news');
                return;
            }
            $form   = $newsObj->getForm($newsid);
            if ($form->isValid($post)) {
                $newsObj->save(array_merge($form->getValues(), ['newsid' => $newsid, 'uid' => Episciences_Auth::getUid()]));
                $this->_helper->FlashMessenger
                    ->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_SUCCESS)
                    ->addMessage("Les modifications ont bien été enregistrées.");
                $this->redirect('/website/news');
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
}

