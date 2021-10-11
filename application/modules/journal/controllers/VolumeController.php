<?php

class VolumeController extends Zend_Controller_Action
{

    public function indexAction()
    {
        $this->_helper->redirector('list');
    }

    public function listAction()
    {
        if ($this->_helper->getHelper('FlashMessenger')->getMessages()) {
            $this->view->flashMessages = $this->_helper->getHelper('FlashMessenger')->getMessages();
        }

        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();
        $this->view->review = $review;
        $this->view->volumes = $review->getVolumes();
    }

    /**
     * Add a new volume
     */
    public function addAction()
    {
        $request = $this->getRequest();
        $form = Episciences_VolumesManager::getForm();

        if ($request->isPost() && array_key_exists('submit', $request->getPost())) {

            if ($form->isValid($request->getPost())) {

                $oVolume = new Episciences_Volume();

                $resVol = $oVolume->save($form->getValues(), null, $request->getPost());
                $oVolume->saveVolumeMetadata($request->getPost());


                if ($resVol) {
                    $message = '<strong>' . $this->view->translate("Le nouveau volume a bien été créé.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                } else {
                    $message = '<strong>' . $this->view->translate("Le nouveau volume n'a pas pu être créé.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace('error')->addMessage($message);
                }

                $this->_helper->redirector('index', 'volume');
            } else {
                $message = '<strong>' . $this->view->translate("Ce formulaire comporte des erreurs.") . '</strong>';
                $this->_helper->FlashMessenger->setNamespace('error')->addMessage($message);
                $this->view->form = $form;
            }
        }

        $this->view->form = $form;
        $this->view->metadataForm = Episciences_VolumesManager::getMetadataForm();
    }

    /**
     * Edit a volume
     * @throws Exception
     */
    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $vid = $request->getParam('id');
        $docId = $request->getParam('docid');
        $from = $request->getParam('from');

        if (!empty($from) && $from === 'view' && !empty($docId)) {
            $referer = '/administratepaper/view?id=' . $docId;
        } elseif ($from === 'list') {
            $referer = '/administratepaper/list'; // papers list
        } else {
            $referer = '/volume/list';
        }

        $volume = Episciences_VolumesManager::find($vid);

        if (empty($volume)) {
            $this->_helper->redirector('add');
        }

        $sorted_papers = $volume->getSortedPapersFromVolume();

        $sorted_papersToBeSaved= [];
        $needsToToBeSaved = false;

        foreach ($sorted_papers as $position => $paper) {
            $sorted_papersToBeSaved[$position] = $paper['paperid'];
            if ( ($paper[Episciences_Volume::PAPER_POSITION_NEEDS_TO_BE_SAVED]) && (!$needsToToBeSaved) ) {
                $needsToToBeSaved = true;
            }
        }

        if (!empty($sorted_papersToBeSaved) && $needsToToBeSaved) {
            Episciences_VolumesManager::savePaperPositionsInVolume($vid, $sorted_papersToBeSaved);
        }

        $gaps = Episciences_Volume::findGapsInPaperOrders($sorted_papers);
        $this->view->gapsInOrderingPapers = $gaps;

        $form = Episciences_VolumesManager::getForm($referer, $volume);

        if ($request->isPost() && array_key_exists('submit', $request->getPost())) {
            $post = $request->getPost();

            if ($form->isValid($post)) {
                $resVol = $volume->save($form->getValues(), $vid, $request->getPost());
                $volume->saveVolumeMetadata($request->getPost());

                if ($resVol) {
                    $message = '<strong>' . $this->view->translate("Vos modifications ont bien été prises en compte.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                } else {
                    $message = '<strong>' . $this->view->translate("Les modifications n'ont pas pu être enregistrées.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace('error')->addMessage($message);
                }

                $this->_helper->redirector->gotoUrl($referer);

            } else {
                $message = '<strong>' . $this->view->translate("Ce formulaire comporte des erreurs.") . '</strong>';
                $this->_helper->FlashMessenger->setNamespace('error')->addMessage($message);
            }
        }
        $defaults = Episciences_VolumesManager::getFormDefaults($volume);
        if ($defaults) {
            $form->setDefaults($defaults);
        }

        $this->view->form = $form;
        $this->view->paperList = $sorted_papers;

        $this->view->volume = $volume;
        $this->view->metadataForm = Episciences_VolumesManager::getMetadataForm();

    }

    /**
     * Delete a volume
     */
    public function deleteAction()
    {
        $request = $this->getRequest();

        $params = $request->getPost('params');
        $id = ($params['id']) ? $params['id'] : $request->getQuery('id');

        $respond = Episciences_VolumesManager::delete($id);
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();
        echo $respond;
    }

    public function sortAction()
    {
        $request = $this->getRequest();
        if (!$request->isXmlHttpRequest()) {
            return false;
        }

        $params = $request->getPost();
        $params['rvid'] = RVID;

        Episciences_VolumesManager::sort($params);
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();
    }

    // Affiche le formulaire d'assignation de rédacteurs à un volume
    // (utilisé dans un popover en ajax)
    /**
     * @throws Zend_Form_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    public function editorsformAction()
    {
        $request = $this->getRequest();
        $vid = $request->getPost('vid');

        if (!$vid) {
            return false;
        }

        $volume = Episciences_VolumesManager::find($vid);
        $currentEditors = $volume->getEditors();
        $this->view->editorsForm = Episciences_VolumesManager::getEditorsForm($currentEditors);

        $this->_helper->layout->disableLayout();
        return true;
    }

    // Assignation de rédacteurs à un volume
    public function saveeditorsAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $request = $this->getRequest();
        $vid = ($request->getPost('vid')) ? $request->getPost('vid') : $request->getParam('vid');

        if ($request->isPost()) {

            // Rédacteurs nouvellement assignés
            $submittedEditors = $request->getPost('editors');
            $editors = ($submittedEditors) ? array_map('intval', $submittedEditors) : [];

            // Rédacteurs déjà assignés
            $volume = Episciences_VolumesManager::find($vid);
            $currentEditors = ($volume->getEditors()) ? array_keys($volume->getEditors()) : [];

            // Tri des rédacteurs ajoutés des rédacteurs supprimés
            $added = array_diff($editors, $currentEditors);
            $removed = array_diff($currentEditors, $editors);

            if ($added) {

                // Enregistrement des nouveaux rédacteurs
                $volume->assign($added);

                // Envoi des mails ?
                // Voir AdministratepaperController l.670
            }

            if ($removed) {
                // Enregistrement des suppressions de rédacteurs
                $volume->unassign($removed);
            }

            echo true;
        }

        return;
    }

    // Rendu partiel
    // Affiche les rédacteurs d'un volume (vid en post)
    public function displayeditorsAction()
    {
        $request = $this->getRequest();
        $params = $request->getPost();
        $vid = $params['vid'];

        $volume = Episciences_VolumesManager::find($vid);
        $editors = $volume->getEditors();

        $this->view->editors = $editors;
        $this->renderScript('volume/editors_list.phtml');
        $this->_helper->layout->disableLayout();
    }


    /**
     *  Upload des fichiers d'une métadonné
     * @throws Zend_File_Transfer_Exception
     */
    public function addfileAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();
        $upload = new Zend_File_Transfer_Adapter_Http();
        $file = $upload->getFileInfo();
        $response = ['file' => $file[0]];
        $newpath = tempnam(REVIEW_TMP_PATH, 'md_');
        if (!rename($file[0]['tmp_name'], $newpath)) {
            $response['error'] = 1;
        } else {
            $response['file']['tmp_name'] = $newpath;
        }

        echo Zend_Json::encode($response);

    }

    public function viewAction()
    {
        $request = $this->getRequest();
        $vid = $request->getParam('id');

        if (!$vid || !is_numeric($vid)) {
            $message = '<strong>' . $this->view->translate("Identifiant du volume absent ou incorrect.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->redirect('/browse/volumes');
        }

        $volume = Episciences_VolumesManager::find($vid);
        if (!$volume) {
            $message = '<strong>' . $this->view->translate("Ce volume n'existe pas.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->redirect('/browse/volumes');
        }

        $volume->loadMetadatas();

        try {
            $volume->loadIndexedPapers();
        } catch (Exception $e) {
            error_log($e->getMessage());
        }

        $this->view->volume = $volume;
    }


}

