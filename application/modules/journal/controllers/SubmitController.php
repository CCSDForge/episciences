<?php
require_once APPLICATION_PATH . '/modules/common/controllers/DefaultController.php';

class SubmitController extends DefaultController
{
    /**
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Form_Exception
     */
    public function indexAction()
    {

        $submit = new Episciences_Submit();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();
        $settings = $review->getSettings();
        $form = $submit::getForm($settings);

        if ($request->isPost()) {

            if ($this->isPostMaxSizeReached()) {
                $message = $this->view->translate('Ce formulaire comporte des erreurs.');
                $message .= ' ';
                $message .= $this->view->translate('La taille maximale des fichiers que vous pouvez télécharger est limiée à');
                $message .= ' ';
                $message .= '<code>' . Episciences_Tools::byteConvert(MAX_FILE_SIZE) . '</code>. ';
                $message .= $this->view->translate('Merci de les corriger.');
                $this->_helper->FlashMessenger->setNamespace('error')->addMessage($message);
                $this->_helper->redirector('index', 'submit');
                return;
            }

            if (array_key_exists('submitPaper', $request->getPost())) {
                if ($request->getPost('suggestEditors') && $form->getElement('suggestEditors')) {
                    /** @var Zend_Form_Element_Multi | Zend_Form_Element_Select $suggestionsElement */
                    $suggestionsElement = $form->getElement('suggestEditors');
                    $suggestionsElement->setRegisterInArrayValidator(false);
                }

                if ($form->isValid($request->getPost())) {
                    $canReplace = (boolean)$request->getPost('can_replace');  // On force le remplacement d'une ancienne version dans certains cas
                    $form_values = $form->getValues();

                    foreach ($request->getPost() as $input => $value) {
                        if (!array_key_exists($input, $form_values)) {
                            $form_values[$input] = $value;
                        }
                    }

                    if ($canReplace) { // Possibilité de remplacer un papier déjà été déposé

                        $selfPaper = new Episciences_Paper([
                            'identifier' => $form_values['old_identifier'],
                            'version' => (int)$form_values['old_version'],
                            'repoId' => (int)$form_values['old_repoid'],
                            'status' => (int)$form_values['old_paper_status']
                        ]);


                        // Suppression de variables unitilisables
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

                    if ($result['code'] == 0) {
                        $this->_helper->FlashMessenger->setNamespace('error')->addMessage($message);
                    } else {
                        $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                    }

                    $this->_helper->redirector('submitted', 'paper');
                } else { // End isValid

                    $validationErrors = '<ol  type="i">';
                    foreach ($form->getMessages() as $key => $val) {
                        foreach ($val as $k => $v) {
                            $validationErrors .= '<li>';
                            $validationErrors .= '<code>' . $v . '</code>';
                            $validationErrors .= '</li>';
                        }
                    }
                    $validationErrors .= '</ol>';

                    $message = '<strong>';
                    $message .= $this->view->translate("Ce formulaire comporte des erreurs");
                    $message .= $this->view->translate(' :');
                    $message .= $validationErrors;
                    $message .= $this->view->translate('Merci de les corriger.');
                    $message .= '</strong>';
                    $this->_helper->FlashMessenger->setNamespace('error')->addMessage($message);
                    $this->view->form = $form;
                    $this->view->error = true;
                }

            } // end isPost

        }

        $this->view->form = $form;

        // Récupération des repositories choisis par la revues
        if (array_key_exists('repositories', $settings) && !empty($settings['repositories'])) {
            $repositoriesList = $settings['repositories'];
        } else {
            //all repositories are enabled
            $repositoriesList = array_keys(Episciences_Repositories::getRepositories());
            //remove episciences from repositories list
            unset($repositoriesList[0]);
        }

        $allowedRepositories = [];
        $examples = [];

        foreach ($repositoriesList as $repoId) {
            $allowedRepositories[$repoId] = Episciences_Repositories::getLabel($repoId);
        }

        // Liste des archives ouvertes disponibles pour la revue (string)
        foreach (Episciences_Repositories::getRepositories() as $id => $repository) {
            if ($id == 0) {
                //remove episciences from repositories list
                continue;
            }
            $examples[$id] = $repository['example'];
        }


        $this->view->repositories = implode(', ', $allowedRepositories);
        $this->view->examples = Zend_Json::encode($examples);

    }

    /**
     * @throws Zend_Exception
     */
    public function getdocAction()
    {
        $request = $this->getRequest();
        $params = $request->getPost();

        $submit = new Episciences_Submit();

        $version = (is_numeric($params['version'])) ? $params['version'] : 1;
        $isNewVersionOf = (bool)$params['isNewVersionOf']; //répondre à une demande de modif. par la soumission d'une nouvelle version
        $respond = $submit->getDoc($params['repoId'], $params['docId'], $version, $isNewVersionOf);
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();

        if (array_key_exists('record', $respond)) {
            // transform xml record for display, using xslt
            $respond['record'] = preg_replace('#xmlns="(.*)"#', '', $respond['record']);
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
        $response['volumesOptions'] = $response['canChooseVolumes'] ? $review->getVolumesOptions(true, true) : [];

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
            $cEditors[$editor->getUid()] = ['uid' => $editor->getUid(), 'fullname' => $editor->getFullname()];
        }
        return $cEditors;

    }

}
