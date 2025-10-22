<?php
require_once APPLICATION_PATH . '/modules/common/controllers/DefaultController.php';

class SubmitController extends DefaultController
{
    /**
     * @throws JsonException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Form_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    public function indexAction(): void
    {
        $isFromZSubmit = false;
        $default = [];
        $settings = Zend_Registry::get('reviewSettings');
        $post = [];

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $isPost = $request->isPost();

        if ($isPost) {

            $post = $request->getPost();

            if ($this->isPostMaxSizeReached()) {
                $message = $this->view->translate('Ce formulaire comporte des erreurs.');
                $message .= ' ';
                $message .= $this->view->translate('La taille maximale des fichiers que vous pouvez télécharger est limitée à');
                $message .= ' ';
                $message .= '<code>' . Episciences_Tools::toHumanReadable(MAX_FILE_SIZE) . '</code>. ';
                $message .= $this->view->translate('Merci de les corriger.');
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                $this->_helper->redirector('index', 'submit', null, [PREFIX_ROUTE => RVCODE]);
                return;
            }
        }

        if (array_key_exists('episciences_form', $post)) { // posted from z-submit application

            $zPost = $request->getPost()['episciences_form'] ?? null;

            $zConceptIdentifier = $zPost['ci'] ?? null;
            $repoId = $zPost['repoid'] ?? null;
            $zIdentifier = null;

            if ($zPost) {
                $zIdentifier = Episciences_Repositories::callHook('hookCleanIdentifiers', ['id' => $zPost['doi_show'], 'repoId' => $repoId])['identifier'];
                $isFromZSubmit = EPISCIENCES_Z_SUBMIT['STATUS'] && $zIdentifier && $zConceptIdentifier && in_array($repoId, $settings['repositories'], true);
            }

            if ($isFromZSubmit) {

                if (!Episciences_Auth::hasRealIdentity()) {

                    $message = $this->view->translate("Vous avez été redirigé vers cette page, votre compte sur cette application ne semble pas être le bon !");

                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);

                }

                $paper = Episciences_PapersManager::findByIdentifier($zConceptIdentifier); // latest version

                $isFirstSubmission = !$paper || (
                        $paper->getConcept_identifier() === $zConceptIdentifier &&
                        in_array($paper->getStatus(), [Episciences_Paper::STATUS_SUBMITTED, Episciences_Paper::STATUS_OK_FOR_REVIEWING, Episciences_Paper::STATUS_REFUSED], true)
                    );

                if (!$isFirstSubmission && ($paper->isRevisionRequested() || $paper->isFormattingCompleted())) {
                    $rOptions = ['controller' => 'paper', 'action' => 'view', 'id' => $paper->getDocid(), 'z-identifier' => $zIdentifier];
                    $this->redirect($this->view->url($rOptions, null, true));
                    return;
                }

                $default ['repoId'] = Episciences_Repositories::ZENODO_REPO_ID;
                $default ['docId'] = $zIdentifier;

            }

        }

        $submit = new Episciences_Submit();

        $form = $submit::getForm($settings, $default, $isFromZSubmit);

        if ($isPost && array_key_exists('submitPaper', $post)) { // form EPI

            $canReplace = (boolean)$request->getPost('can_replace');  // On force le remplacement d'une ancienne version dans certains cas

            if (isset($post['search_doc']['repoId'])) {
                $repoId = (int)$post['search_doc']['repoId'];
                $hookCleanIdentifiers = Episciences_Repositories::callHook('hookCleanIdentifiers', ['id' => $post['search_doc']['docId'], 'repoId' => $repoId]);
                if (!empty($hookCleanIdentifiers)) {
                    $post['search_doc']['docId'] = $hookCleanIdentifiers['identifier'];
                }
            }

            if($canReplace) { // validation not required
                $form->removeElement('suggestEditors');
                $form->removeElement('sections');

            } elseif ($request->getPost('suggestEditors') && $form->getElement('suggestEditors')) {
                /** @var Zend_Form_Element_Multi | Zend_Form_Element_Select $suggestionsElement */
                $suggestionsElement = $form->getElement('suggestEditors');
                $suggestionsElement->setRegisterInArrayValidator(false);
            }

            $requiredDdKey = sprintf('%s_is_required', Episciences_Submit::DD_FILE_ELEMENT_NAME);

            if (isset($post[$requiredDdKey])) {
                $form->getElement(Episciences_Submit::DD_FILE_ELEMENT_NAME)?->setRequired($post[$requiredDdKey] === 'true');
            }

            if ($form->isValid($post)) {
                $form_values = $form->getValues();

                foreach ($post as $input => $value) {
                    if (!array_key_exists($input, $form_values)) {
                        $form_values[$input] = $value;
                    }
                }

                if ($canReplace) { // Possibility to replace a paper

                    $selfPaper = new Episciences_Paper([
                        'identifier' => $form_values['old_identifier'],
                        'version' => (int)$form_values['old_version'],
                        'repoId' => (int)$form_values['old_repoid'],
                        'status' => (int)$form_values['old_paper_status']
                    ]);


                    // Deletion of unused variables
                    unset(
                        $form_values['old_identifier'],
                        $form_values['old_repoid']
                    );

                    $result = $selfPaper->updatePaper($form_values);
                    $message = '<strong>' . $result['message'] . '</strong>';

                } else {
                    $result = $submit->saveDoc($form_values);
                    $message = $result['message'];
                }

                if ($result['code'] === 0) {
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                    $this->_helper->redirector('submitted', 'paper', null, [PREFIX_ROUTE => RVCODE]);
                } else {
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                    // Redirect to paper detail page for possible edits
                    $docId = $result['docId'] ?? null;
                    if ($docId) {
                        $this->_helper->redirector->gotoUrl($this->url(['controller' => 'paper', 'action' => 'view', 'id' => $docId]));
                    } else {
                        $this->_helper->redirector('submitted', 'paper', null, [PREFIX_ROUTE => RVCODE]);
                    }
                }
                return;
            } // End isValid

            $this->renderFormErrors($form);

        }

        $this->view->form = $form;

        $examples = [];

        // available repositories (string)
        foreach (Episciences_Repositories::getRepositories() as $id => $repository) {
            if ((int)$id === 0) {
                //remove episciences from repositories list
                continue;
            }

            $examples[$id] = Episciences_Repositories::getIdentifierExemple($repository['id']);
        }

        $allowedRepositories = Episciences_Submit::getRepositoriesLabels($settings);

        $this->view->repositories = implode(', ', $allowedRepositories);
        $this->view->examples = Zend_Json::encode($examples);
        $this->view->isFromZSubmit = $isFromZSubmit;
        $this->view->zSubmitUrl = !$isFromZSubmit ? $this->getZSubmitUrl($allowedRepositories) : null;
        $this->view->zenodoRepoId = Episciences_Repositories::ZENODO_REPO_ID;

    }


    /**
     * @return void
     * @throws Zend_Exception
     * @throws Exception
     */
    public function getdocAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $params = $request->getPost();
        $version = (isset($params['version']) && is_numeric($params['version'])) ? (int)$params['version'] : 1;
        $latestObsoleteDocId = $params['latestObsoleteDocId']; //répondre à une demande de modif. par la soumission d'une nouvelle version
        $respond = Episciences_Submit::getDoc($params['repoId'], $params['docId'], $version, $latestObsoleteDocId);
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();

        if (!array_key_exists('error', $respond) && array_key_exists('record', $respond)) {
            // transform xml record for display, using xslt
            $respond['record'] = preg_replace('#xmlns="(.*)"#', '', $respond['record']);

            if($params['repoId'] === Episciences_Repositories::CWI_REPO_ID){
                $respond['record'] = Episciences_Repositories_Common::checkAndCleanRecord($respond['record']);
            }

            $input = array_merge($respond, ['repoId' => $params['repoId']]);

            $result = Episciences_Repositories::callHook('hookCleanXMLRecordInput', $input);
            unset ($result['repoId']);

            $respond = !empty($result) ? $result : $respond;
            $respond['ddOptions'] = ['displayDDForm' => Episciences_Repositories::isDataverse($params['repoId']) , 'isSoftware' => false];

            // form repository
            $type = null;

            if (isset($respond[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::RESOURCE_TYPE_ENRICHMENT])) {

                $types = $respond[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::RESOURCE_TYPE_ENRICHMENT];

                if (!is_array($types)) {
                    $types = (array)$types;
                }

                $type = $types[array_key_last($types)] ?? null;
            }

            if ($type) {
                $isSoftware = strtolower($type) === Episciences_Paper::SOFTWARE_TYPE_TITLE;
                $isSoftwareOrDataset = $isSoftware || (strtolower($type) === Episciences_Paper::DATASET_TYPE_TITLE);
                $respond['ddOptions']['displayDDForm'] = $isSoftwareOrDataset || $respond['ddOptions']['displayDDForm'];
                $respond['ddOptions']['isSoftware'] = $isSoftware;
            }

            $respond['xslt'] = Ccsd_Tools::xslt($respond['record'], APPLICATION_PUBLIC_PATH . '/xsl/full_paper.xsl');
        }

        $this->_helper->json($respond);
    }

    /**
     * @throws Zend_Exception
     */
    public function accesscodeAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();

        $request = $this->getRequest();
        $code = trim($request->getPost('code'));

        /** @var Episciences_Review $review */
        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();

        $response['status'] = 0;
        $response['allEditorsSelected'] = (bool)Episciences_Review::DISABLED;
        $response['canPickEditor'] = (int)$review->getSetting(Episciences_Review::SETTING_CAN_PICK_EDITOR);
        $response['canChooseVolumes'] = filter_var($review->getSetting(Episciences_Review::SETTING_CAN_CHOOSE_VOLUME), FILTER_VALIDATE_BOOLEAN);
        $response['editors'] = $response['canPickEditor'] > 0 ? $this->compileEditors($review->getEditors()) : [];
        $response['volumesOptions'] = $response['canChooseVolumes'] ? $review->getVolumesOptions() : [];

        if (strlen($code) != 13) {
            $response['error'] = 'Code invalide';
        } else {

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();

            $sql = $db->select()->from(array('s' => T_VOLUME_SETTINGS), array('VID'))
                ->join(array('v' => T_VOLUMES), 's.VID = v.VID')
                ->where('RVID = ?', RVID)
                ->where('SETTING = ?', Episciences_Volume::SETTING_ACCESS_CODE)
                ->where('VALUE = ?', $code);
            $vid = $db->fetchOne($sql);

            // Get Volume Name
            // Get Volume Editors

            if ($vid) {
                /** @var Episciences_Volume $volume */
                $volume = Episciences_VolumesManager::find($vid);
                $volume->loadSettings();
                $response['status'] = 1;
                $response['vid'] = $vid;
                $response['volume'] = $volume->getName();
                $response['editors'] = $this->compileEditors($volume->getEditors());
                if ($volume->getSetting(Episciences_Volume::SETTING_SPECIAL_ISSUE)) {
                    $response['allEditorsSelected'] = $review->getSetting(Episciences_Review::SETTING_SYSTEM_CAN_ASSIGN_SPECIAL_VOLUME_EDITORS) == Episciences_Review::ENABLED;
                }

            }

        }

        $this->_helper->json($response);
    }

    /**
     * @param Episciences_Editor[] $editors
     * @return array
     */
    private function compileEditors(array $editors)
    {
        $cEditors = [];

        foreach ($editors as $editor) {
            // Only include editors who marked themselves as available
            $isAvailable = Episciences_UsersManager::isEditorAvailable($editor->getUid(), RVID);

            if ($isAvailable) {
                $cEditors[$editor->getUid()] = ['uid' => $editor->getUid(), 'fullname' => $editor->getFullname()];
            }
        }

        return $cEditors;
    }

    public function ajaxhashookAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if (!$request->isXmlHttpRequest() || !$request->isPost()) {
            return;
        }

        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $repoId = (int)$request->get('repoId');
        $hasHook = !empty(Episciences_Repositories::hasHook($repoId));

        if ($repoId !== (int)Episciences_Repositories::CWI_REPO_ID) {

            $isRequiredVersion = $hasHook ?
                Episciences_Repositories::callHook('hookIsRequiredVersion', ['repoId' => $repoId]) :
                ['result' => true];

        } else {
            $isRequiredVersion = ['result' => false];

        }

        $response = ['hasHook' => $hasHook, 'isRequiredVersion' =>$isRequiredVersion];

        try {
            echo json_encode($response, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            trigger_error($e->getMessage());
        }

    }

    public function ajaxisdataverseAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if (!$request->isXmlHttpRequest() || !$request->isPost()) {
            return;
        }

        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $repoId = (int)$request->get('repoId');

        try {
            echo json_encode(['isDataverse' => Episciences_Repositories::isDataverse($repoId)], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            trigger_error($e->getMessage());
        }

    }
}
