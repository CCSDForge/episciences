<?php

class VolumeController extends Episciences_Controller_Action
{
    public const JSON_MIMETYPE = 'application/json';
    public const MIN_VOLUME_DATE = '1950';
    public function indexAction()
    {
        $this->_helper->redirector('list',null, null, [PREFIX_ROUTE => RVCODE]);
    }

    public function listAction()
    {
        if ($this->_helper->getHelper('FlashMessenger')->getMessages()) {
            $this->view->flashMessages = $this->_helper->getHelper('FlashMessenger')->getMessages();
        }

        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();
        $this->view->review = $review;
        $volumes = $review->getVolumes();
        $this->view->volumes = $volumes;
    }

    /**
     * Add a new volume
     */
    public function addAction()
    {
        $request = $this->getRequest();
        $form = Episciences_VolumesManager::getForm();
        $journal = Episciences_ReviewsManager::find(Episciences_Review::getCurrentReviewId());
        $journal->loadSettings();
        $journalPrefixDoi = $journal->getDoiSettings()->getDoiPrefix();
        if ($journalPrefixDoi !== '') {
            $form->addElement('hidden', "journalprefixDoi", [
                'label' => "journalprefixDoi",
                'value' => $journalPrefixDoi . '/' . RVCODE . ".proceedings.",
                'data-none' => true
            ]);
        }
        if ($request->isPost() && array_key_exists('submit', $request->getPost())) {

            if ($form->isValid($request->getPost())) {

                $oVolume = new Episciences_Volume();

                $this->checkValidateDate($request->getPost()['year'], $request);
                $resVol = $oVolume->save($form->getValues(), null, $request->getPost());
                $oVolume->saveVolumeMetadata($request->getPost());
                $post = $request->getPost();
                if ($post['conference_proceedings_doi'] !== '') {
                    $volumequeue = new Episciences_Volume_DoiQueue();
                    $volumequeue->setVid($oVolume->getVid());
                    $volumequeue->setDoi_status(Episciences_Volume_DoiQueue::STATUS_ASSIGNED);
                    Episciences_Volume_DoiQueueManager::add($volumequeue);
                }

                if ($resVol) {
                    $message = '<strong>' . $this->view->translate("Le nouveau volume a bien été créé.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                } else {
                    $message = '<strong>' . $this->view->translate("Le nouveau volume n'a pas pu être créé.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                }

                $this->_helper->redirector('index', 'volume', null, [PREFIX_ROUTE => RVCODE]);
            } else {
                $message = '<strong>' . $this->view->translate("Ce formulaire comporte des erreurs.") . '</strong>';
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                $this->view->form = $form;
            }
        }

        $this->view->form = $form;
        $this->view->metadataForm = Episciences_VolumesManager::getMetadataForm();
    }

    /**
     * @param $year
     * @param Zend_Controller_Request_Http $request
     * @return void
     */
    private function checkValidateDate($year, Zend_Controller_Request_Http $request): void
    {
        if ($year !== '' && is_string($year)) {
            $maxDate = date('Y', strtotime('+ 5 years'));
            if (!($year >= self::MIN_VOLUME_DATE && $year <= $maxDate)) {
                $message = '<strong>' . $this->view->translate("L'année du volume est incorrecte veuillez saisir entre: ") . self::MIN_VOLUME_DATE . ' ' . $this->view->translate('et') . ' ' . $maxDate . '</strong>';
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                $this->_helper->redirector->gotoUrl($request->getRequestUri());
            }
        }
    }

    /**
     * Delete a volume
     */
    public function deleteAction()
    {
        $request = $this->getRequest();

        $params = $request->getPost('params');
        $id = (int) $params['id'] ?? $request->getQuery('id');

        $respond = Episciences_VolumesManager::delete($id);
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();
        echo $respond;
    }




    /**
     * @return false|void
     * @throws Zend_Db_Adapter_Exception
     */
    public function sortAction()
    {
        $request = $this->getRequest();
        if (!$request->isXmlHttpRequest()) {
            return false;
        }

        $params = $request->getPost();
        $params['rvid'] = RVID;

        Episciences_VolumesAndSectionsManager::sort($params);
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
        $formData = Episciences_VolumesManager::getEditorsForm($currentEditors);

        if ($formData) {
            $this->view->editorsForm = $formData['form'];
            $this->view->unavailableEditors = $formData['unavailableEditors'];
        } else {
            $this->view->editorsForm = false;
            $this->view->unavailableEditors = [];
        }

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
        $errorMessage = false;
        $volume = false;
        $arrayOfVolumesOrSections = [];

        if (!$vid || !is_numeric($vid)) {
            $errorMessage = "Identifiant du volume absent ou incorrect.";
        }

        if (!$errorMessage) {
            $volume = Episciences_VolumesManager::find($vid);
            if (!$volume) {
                $errorMessage = "Ce volume n'existe pas.";
            } else {
                $volume->loadMetadatas();
            }
        }


        if ($this->getFrontController()->getRequest()->getHeader('Accept') === self::JSON_MIMETYPE) {
            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
            if ($volume) {
                try {
                    $arrayOfVolumesOrSections = Episciences_Volume::volumesOrSectionsToPublicArray([$volume->getVid() => $volume], 'Episciences_Volume');
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
            $this->redirect($this->url(['controller' => 'browse', 'action' => 'volume']));        }


        try {
            $volume->loadIndexedPapers();
        } catch (Exception $exception) {
            trigger_error($exception->getMessage(), E_USER_WARNING);
        }

        $this->view->volume = $volume;
    }

    /**
     * @throws Exception
     */
    public function allAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getFrontController()->getRequest();

        if ($request->getHeader('Accept') !== self::JSON_MIMETYPE) {

            $request->getParam('id') ?
                $this->redirect($this->url(['controller' => 'volume', 'action' => 'edit', 'id' => $this->getRequest()->getParam('id')]))
                :
                $this->redirect($this->url(['controller' => 'volume', 'action' => 'list']));

            return;
        }

        $this->editAction();

    }

    /**
     * Edit a volume
     * @throws Exception
     */
    public function editAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $vid = (int)$request->getParam('id');
        $docId = $request->getParam('docid');
        $from = $request->getParam('from');

        if (!empty($from) && $from === 'view' && !empty($docId)) {
            $referer = $this->url(['controller' => 'administratepaper', 'action' => 'view', 'id' => $docId]);
        } elseif ($from === 'list') {
            $referer = $this->url(['controller' => 'administratepaper', 'action' => 'list']); // papers list
        } else {
            $referer = $this->url(['controller' => 'volume', 'action' => 'list']);
        }

        $volume = Episciences_VolumesManager::find($vid, RVID);

        if (!$volume) {
            $message = sprintf("<strong>%s</strong>", $this->view->translate("Le volume n'a pas été trouvé"));
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            $this->_helper->redirector->gotoUrl($this->url(['action' => 'list', 'controller' => 'volume']));
            return;
        }

        $sorted_papers = $volume->getSortedPapersFromVolume();


        if ($request->getHeader('Accept') === self::JSON_MIMETYPE && $request->getActionName() === 'all') {
            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
            $arrayOfVolumesOrSections = [];
            try {
                $arrayOfVolumesOrSections = Episciences_Volume::volumesOrSectionsToPublicArray([$volume->getVid() => $volume], 'Episciences_Volume');
                $sorted_papersForJson = [];

                if (!empty($sorted_papers)) {
                    foreach ($sorted_papers as $papersForJson) {
                        unset($papersForJson['needsToBeSaved']);
                        $papersForJson['statusLabel'] = $this->view->translate(Episciences_Paper::$_statusLabel[$papersForJson['status']], 'en');
                        $sorted_papersForJson[] = $papersForJson;
                    }
                    // add papers to volume array
                    $arrayOfVolumesOrSections[$volume->getVid()]['papers'] = $sorted_papersForJson;
                }

            } catch (Zend_Exception $exception) {
                trigger_error($exception->getMessage(), E_USER_WARNING);
                // $arrayOfVolumesOrSections default value
            }

            $this->getResponse()->setHeader('Content-type', self::JSON_MIMETYPE);
            $this->getResponse()->setBody(json_encode($arrayOfVolumesOrSections));
            return;
        }


        $sorted_papersToBeSaved = [];
        $needsToToBeSaved = false;

        foreach ($sorted_papers as $position => $paper) {
            $sorted_papersToBeSaved[$position] = $paper['paperid'];
            if (($paper[Episciences_Volume::PAPER_POSITION_NEEDS_TO_BE_SAVED]) && (!$needsToToBeSaved)) {
                $needsToToBeSaved = true;
            }
        }

        if (!empty($sorted_papersToBeSaved) && $needsToToBeSaved) {
            Episciences_VolumesManager::savePaperPositionsInVolume($vid, $sorted_papersToBeSaved);
        }

        $gaps = Episciences_Volume::findGapsInPaperOrders($sorted_papers);
        $this->view->gapsInOrderingPapers = $gaps;

        $form = Episciences_VolumesManager::getForm($referer, $volume);
        $journal = Episciences_ReviewsManager::find(Episciences_Review::getCurrentReviewId());
        $journal->loadSettings();
        $journalPrefixDoi = $journal->getDoiSettings()->getDoiPrefix();
        if ($journalPrefixDoi !== '') {
            $form->addElement('hidden', "journalprefixDoi", [
                'label' => "journalprefixDoi",
                'value' => $journalPrefixDoi . '/' . RVCODE . ".proceedings.",
                'data-none' => true
            ]);
        }
        if ($request->isPost() && array_key_exists('submit', $request->getPost())) {
            $post = $request->getPost();

            if ($form->isValid($post)) {
                $this->checkValidateDate($post['year'], $request);
                $resVol = $volume->save($form->getValues(), $vid, $request->getPost());

                $volume->saveVolumeMetadata($request->getPost());

                if ($post['conference_proceedings_doi'] !== '' &&
                    (
                        $post['doi_status'] === Episciences_Volume_DoiQueue::STATUS_ASSIGNED ||
                        $post['doi_status'] === Episciences_Volume_DoiQueue::STATUS_NOT_ASSIGNED
                    )
                ) {
                    $volumequeue = new Episciences_Volume_DoiQueue();
                    $volumequeue->setVid($volume->getVid());
                    $volumequeue->setDoi_status(Episciences_Volume_DoiQueue::STATUS_ASSIGNED);
                    Episciences_Volume_DoiQueueManager::add($volumequeue);
                }
                if ($resVol) {
                    $message = '<strong>' . $this->view->translate("Vos modifications ont bien été prises en compte.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                } else {
                    $message = '<strong>' . $this->view->translate("Les modifications n'ont pas pu être enregistrées.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                }

                $this->_helper->redirector->gotoUrl($referer);

            } else {
                $message = '<strong>' . $this->view->translate("Ce formulaire comporte des erreurs.") . '</strong>';
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
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

    public function exportAction()
    {
        $request = $this->getRequest();
        $vid = $request->getParam('id');
        
        if (!$vid || !is_numeric($vid)) {
            $errorMessage = "Identifiant du volume absent ou incorrect.";
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->addMessage('<strong>' . $this->view->translate($errorMessage) . '</strong>');
            $this->_helper->redirector->gotoUrl($this->url(['controller' => 'browse', 'action' => 'volumes']));
            return;
        }

        $volume = Episciences_VolumesManager::find($vid, RVID);



        if (!$volume) {
            $errorMessage = "Ce volume n'existe pas.";
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->addMessage('<strong>' . $this->view->translate($errorMessage) . '</strong>');
            $this->redirect($this->url(['controller' => 'browse', 'action' => 'volume']));
            return;
        }



        if (!$volume->isProceeding()) {
            $errorMessage = "Type de volume non pris en charge pour l'export Crossref.";
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->addMessage('<strong>' . $this->view->translate($errorMessage) . '</strong>');
            $this->redirect($this->url(['controller' => 'browse', 'action' => 'volume']));
            return;
        }

        $volume->loadMetadatas();

        try {
            $dateString =  $volume->getEarliestPublicationDateFromVolume();
        } catch (Exception $exception) {
            trigger_error($exception->getMessage(), E_USER_WARNING);
        }

        $dateObject = DateTime::createFromFormat('d/m/Y', $dateString);

        // Check if the date object was created successfully
        if ($dateObject) {
            // Return the date in the desired format Y-m-d
            $publicationDate =  $dateObject->format('Y');
        } else {
            $publicationDate =  date('Y');
        }

        $journal = Episciences_ReviewsManager::findByRvcode(RVCODE);

        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        header('Content-Type: text/xml; charset=UTF-8');

        $this->view->publicationDate = $publicationDate;
        $this->view->journal = $journal;
        $this->view->volume = $volume;

        $this->renderScript('volume/crossref.phtml');
    }

}

