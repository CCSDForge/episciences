<?php

class WebsiteDefaultController extends Zend_Controller_Action
{
    protected $_session = null;

    public function init()
    {
        //Session courante
        $this->_session = new Zend_Session_Namespace(SESSION_NAMESPACE);
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
        $common = new Ccsd_Website_Common(RVID, ['languages' => Episciences_Translation_Plugin::getAvalaibleLanguages()]);
        $form = $common->getForm();
        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            $common->save($form->getValues());
            unset($this->_session->website);
            Zend_Registry::set('languages', $form->getValue('languages'));
            $this->_helper->FlashMessenger->setNamespace('success')->addMessage("Les modifications ont bien été enregistrées.");
            $this->redirect('/website/common');
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
            $this->_helper->FlashMessenger->setNamespace('success')->addMessage("Les modifications ont bien été enregistrées.");
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
                $this->_helper->FlashMessenger->setNamespace('success')->addMessage("Les modifications ont bien été enregistrées.");
            } else { //Erreur sur le formulaire
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
        $this->_helper->viewRenderer->setNoRender();
        $header = new Episciences_Website_Header();
        echo $header->getLogoForm($this->getRequest()->getParam('id', '0'));
    }

    /**
     * Affichage des ressources publiques d'un site
     */
    public function publicAction()
    {
        $dir = REVIEW_PATH . 'public/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $params = $this->getRequest()->getParams();
        if ($this->getRequest()->isPost() && isset($params['method'])) {
            if ($params['method'] == 'remove') {
                //Suppression d'un fichier
                if (isset($params['name']) && is_file($dir . $params['name'])) {
                    unlink($dir . $params['name']);
                }
            } else if (isset($_FILES['file']['tmp_name']) && $_FILES['file']['tmp_name'] != '') {
                //Ajout d'un fichier
                copy($_FILES['file']['tmp_name'], $dir . Ccsd_File::renameFile($_FILES['file']['name'], $dir));
                $this->_helper->FlashMessenger->setNamespace('success')->addMessage("Le fichier a été déposé.");
            }
        }

        $files = [];
        foreach (scandir($dir) as $file) {
            if (!in_array($file, ['.', '..'])) {
                $files[$file] = $dir . $file;
            }
        }
        $this->view->files = $files;
    }

    /**
     * Gestion des pages du site
     */
    public function menuAction()
    {
        if (!isset($this->_session->website)) {
            //Récupération de la navigation du portail ou d'un journal
            $this->_session->website = new Episciences_Website_Navigation(['languages' => Zend_Registry::get('languages'), 'sid' => RVID]);
            $this->_session->website->load();
        }
        if ($this->getRequest()->isPost()) {
            $valid = true;
            $pagesDisplay = [];

            foreach ($this->getRequest()->getPost() as $id => $options) {
                if (substr($id, 0, 6) != 'pages_') continue;
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

                if ($options['type'] != 'Episciences_Website_Navigation_Page_File' && !$this->_session->website->getPage($pageid)->getForm($pageid)->isValid($options)) {
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
                $this->_helper->FlashMessenger->setNamespace('success')->addMessage("Les modifications ont bien été enregistrées.");
                $this->redirect('/website/menu');
            } else {
                $this->_helper->FlashMessenger->setNamespace('danger')->addMessage("Erreur de saisie");
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
    public function ajaxrmpageAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $request = $this->getRequest();
        $params = $request->getPost();
        if ($request->isXmlHttpRequest() && $request->isPost() && isset($params['idx'])) {
            $this->_session->website->deletePage($params['idx']);
        }
    }

    /**
     * Réinitialisation du menu
     */
    public function resetAction()
    {
        unset($this->_session->website);
        $this->redirect('/website/menu');
    }

    /**
     * Gestion des actualités
     */
    public function newsAction()
    {
        $news = new Episciences_News();
        $form = $news->getForm($this->getRequest()->getParam('newsid', 0));

        if ($this->getRequest()->isPost()) {
            if ($form->isValid($this->getRequest()->getParams())) {
                $news->save(array_merge($form->getValues(), ['uid' => Episciences_Auth::getUid()]));
                $this->_helper->FlashMessenger->setNamespace(MSG_SUCCESS)->addMessage("Les modifications ont bien été enregistrées.");
                $this->redirect('/website/news');
            } else {
                $this->_helper->FlashMessenger->setNamespace(MSG_ERROR)->addMessage("Erreur dans la saisie");
                $this->view->errors = $this->getRequest()->getParams();
            }
        }

        $this->view->news = $news->getListNews(false);
        $this->view->form = $news->getForm();
    }

    /**
     * Récupération du formulaire d'ajout/édition d'une actu
     */
    public function ajaxnewsformAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $request = $this->getRequest();
        $params = $request->getPost();
        if ($request->isXmlHttpRequest() && $request->isPost() && isset($params['newsid'])) {
            $news = new Episciences_News();
            echo $news->getForm($params['newsid']);
        }
    }

    /**
     * Suppression d'une actualité
     */
    public function ajaxnewsdeleteAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $request = $this->getRequest();
        $params = $request->getPost();
        if ($request->isXmlHttpRequest() && $request->isPost() && isset($params['newsid'])) {
            $news = new Episciences_News();
            $news->delete($params['newsid']);
        }
    }
}

