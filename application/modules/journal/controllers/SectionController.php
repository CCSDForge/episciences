<?php

class SectionController extends Episciences_Controller_Action
{
    public const JSON_MIMETYPE = 'application/json';

    public function indexAction()
    {
        $this->_helper->redirector('list', null, null, [PREFIX_ROUTE => RVCODE]);
    }

    public function listAction()
    {
        if ($this->_helper->getHelper('FlashMessenger')->getMessages()) {
            $this->view->flashMessages = $this->_helper->getHelper('FlashMessenger')->getMessages();
        }

        $sections = Episciences_SectionsManager::getList();
        /** @var Episciences_Section $section */
        foreach ($sections as &$section) {
            $section->loadSettings();
        }

        unset($section);

        $this->view->sections = $sections;
    }

    public function addAction()
    {
        $request = $this->getRequest();
        $form = Episciences_SectionsManager::getForm();

        if ($request->isPost() && array_key_exists('submit', $request->getPost())) {

            if ($form->isValid($request->getPost())) {

                $formValues = $form->getValues();
                $section = new Episciences_Section($formValues);
                $section->setSetting('status', $form->getValue('status'));

                // Convert the data
                $titles = Episciences_SectionsManager::revertSectionTitleToTextArray($formValues) ?? null;
                $descriptions = Episciences_SectionsManager::revertSectionDescriptionToTextareaArray($formValues) ?? null;
                // Update the properties of the object
                $section->setTitles($titles);
                $section->setDescriptions($descriptions);


                if ($section->save()) {
                    $message = '<strong>' . $this->view->translate("La nouvelle rubrique a bien été créée.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)->addMessage($message);
                } else {
                    $message = '<strong>' . $this->view->translate("La nouvelle rubrique n'a pas pu être créée.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                }

                $this->_helper->redirector('index', 'section', null, [PREFIX_ROUTE => RVCODE]);
            } else {
                $message = '<strong>' . $this->view->translate("Ce formulaire comporte des erreurs.") . '</strong>';
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                $this->view->form = $form;
                return;
            }
        }

        $this->view->form = $form;
    }

    /**
     * @return void
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     * @throws Zend_Validate_Exception
     */

    public function editAction()
    {
        $request = $this->getRequest();
        $id = $request->getQuery('id');

        $section = Episciences_SectionsManager::find($id, RVID);
        if (!$section) {
            $message = sprintf("<strong>%s</strong>", $this->view->translate("La section n'a pas été trouvée"));
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            $this->_helper->redirector->gotoUrl($this->url(['action' => 'list', 'controller' => 'section']));
            return;
        }
        $section->loadSettings();
        $defaults = $section->getFormDefaults($section);
        $form = Episciences_SectionsManager::getForm($defaults);

        if ($request->isPost() && array_key_exists('submit', $request->getPost())) {

            if ($form->isValid($request->getPost())) {

                $values = $form->getValues();
                $section->setOptions($values);
                $section->setSetting('status', $form->getValue('status'));

                // Convert the data
                $titles = Episciences_SectionsManager::revertSectionTitleToTextArray($values) ?? null;
                $descriptions = Episciences_SectionsManager::revertSectionDescriptionToTextareaArray($values) ?? null;
                // Update the properties of the object
                $section->setTitles($titles);
                $section->setDescriptions($descriptions);


                if ($section->save()) {

                    $message = '<strong>' . $this->view->translate("Vos modifications ont bien été prises en compte.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                } else {
                    $message = '<strong>' . $this->view->translate("Les modifications n'ont pas pu être enregistrées.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                }

                $this->_helper->redirector('index', 'section', null, [PREFIX_ROUTE => RVCODE]);
            } else {
                $message = '<strong>' . $this->view->translate("Ce formulaire comporte des erreurs.") . '</strong>';
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                $this->view->form = $form;
            }
        }

        $this->view->form = $form;

    }

    /**
     * @return void
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     */

    public function deleteAction(): void
    {
        $respond = false;
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $isAjax = $request->isPost() && $request->getPost('ajax');

        if ($isAjax){
            $params = $request->getPost('params');
            $id = ($params['id']) ?: $request->getQuery('id');
            $respond = Episciences_SectionsManager::delete($id);
        }

        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();
        echo $respond;
    }

    /**
     * @return void
     * @throws Zend_Db_Adapter_Exception
     */
    public function sortAction()
    {
        $request = $this->getRequest();
        $params = $request->getPost();
        $params['rvid'] = RVID;

        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();

        Episciences_VolumesAndSectionsManager::sort($params, 'SID');
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();
    }

    // Affiche le formulaire d'assignation de rédacteurs à une rubrique
    // (utilisé dans un popover en ajax) 
    public function editorsformAction()
    {
        $request = $this->getRequest();
        $sid = $request->getPost('sid');
        if (!$sid) {
            return false;
        }

        $section = Episciences_SectionsManager::find($sid);
        $currentEditors = $section->getEditors();
        $this->view->editorsForm = Episciences_SectionsManager::getEditorsForm($currentEditors);

        $this->_helper->layout->disableLayout();
    }

    // Assignation de rédacteurs à une rubrique
    public function saveeditorsAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $request = $this->getRequest();
        $sid = ($request->getPost('sid')) ? $request->getPost('sid') : $request->getParam('sid');

        if ($request->isPost()) {

            // Rédacteurs nouvellement assignés
            $submittedEditors = $request->getPost('editors');
            $editors = ($submittedEditors) ? array_map('intval', $submittedEditors) : [];

            // Rédacteurs déjà assignés
            $section = Episciences_SectionsManager::find($sid);
            $currentEditors = ($section->getEditors()) ? array_keys($section->getEditors()) : [];

            // Tri des rédacteurs ajoutés des rédacteurs supprimés
            $added = array_diff($editors, $currentEditors);
            $removed = array_diff($currentEditors, $editors);

            if ($added) {

                // Enregistrement des nouveaux rédacteurs
                $section->assign($added);

                // Envoi des mails ?
                // Voir AdministratepaperController l.670
            }

            if ($removed) {
                // Enregistrement des suppressions de rédacteurs
                $section->unassign($removed);
            }

            echo true;
        }

        return;
    }

    /**
     * Render section's editors
     * @return void
     */
    public function displayeditorsAction()
    {
        $request = $this->getRequest();
        $params = $request->getPost();
        $sid = $params['sid'];

        $section = Episciences_SectionsManager::find($sid);
        $editors = $section->getEditors();

        $this->view->editors = $editors;
        $this->renderScript('section/editors_list.phtml');
        $this->_helper->layout->disableLayout();
    }

    /**
     * Render Section info as HTML or JSON
     * @return void
     * @throws Zend_Controller_Exception
     */
    public function viewAction()
    {
        $request = $this->getRequest();
        $sid = $request->getParam('id');
        $errorMessage = false;
        $section = false;
        $arrayOfVolumesOrSections = [];

        if (!$sid || !is_numeric($sid)) {
            $errorMessage = "Identifiant de la rubrique absent ou incorrect.";
        }

        if (!$errorMessage) {
            $section = Episciences_SectionsManager::find($sid);
            if (!$section) {
                $errorMessage = "Cette rubrique n'existe pas.";
            } else {
                $section->getEditors();
            }
        }


        if ($this->getFrontController()->getRequest()->getHeader('Accept') === self::JSON_MIMETYPE) {
            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
            if ($section) {
                try {
                    $arrayOfVolumesOrSections = Episciences_Volume::volumesOrSectionsToPublicArray([$section->getSid() => $section], 'Episciences_Section');
                } catch (Zend_Exception $exception) {
                    trigger_error($exception->getMessage(), E_USER_WARNING);
                    // $arrayOfVolumesOrSections default value
                }
            }
            $this->getResponse()->setHeader('Content-type', self::JSON_MIMETYPE);
            $this->getResponse()->setBody(json_encode($arrayOfVolumesOrSections));
            return;
        }

        if ($errorMessage !== false) {
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage('<strong>' . $this->view->translate($errorMessage) . '</strong>');
            $this->redirect($this->url(['controller' => 'browse', 'action' => 'section']));
            return;
        }

        try {
            $section->loadIndexedPapers();
        } catch (Exception $exception) {
            trigger_error($exception->getMessage(), E_USER_WARNING);
        }


        $this->view->section = $section;
    }

}