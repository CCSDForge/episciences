<?php

class ReviewController extends Zend_Controller_Action
{
    public const SETTING_JOURNAL_PUBLISHER = 'journalPublisher';
    public const SETTING_JOURNAL_PUBLISHER_LOC = 'journalPublisherLoc';

    public function indexAction()
    {
        $this->renderScript('index/submenu.phtml');
    }

    /**
     * Saves journal settings
     * Merge settings from this controller with DOI controller settings
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     * @throws Zend_Validate_Exception
     */
    public function settingsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();
        $reviewDefaults = $review->getSettings();

        $defaults = ($request->isPost() && array_key_exists('submit', $request->getPost())) ? $request->getPost() : $reviewDefaults;

        $form = $review->settingsForm();

        if ($request->isPost() && array_key_exists('submit', $request->getPost())) {

            if ($form->isValid($request->getPost())) {

                $reviewSettingsToSave = array_merge($form->getValues(), [
                    'rating_deadline_unit' => $request->getPost('rating_deadline_unit'),
                    'rating_deadline_min_unit' => $request->getPost('rating_deadline_min_unit'),
                    'rating_deadline_max_unit' => $request->getPost('rating_deadline_max_unit'),
                    'invitation_deadline_unit' => $request->getPost('invitation_deadline_unit')]);


                // DOI Settings are managed in another controller with different ACL, do not forget to merge them with new settings
                $reviewDefaultsDoi = $review->getDoiSettings();
                $reviewSettingsToSave = array_merge($reviewSettingsToSave, $reviewDefaultsDoi->__toArray());

                // Normalize checkbox values: ensure unchecked checkboxes are '0' instead of empty/null
                $checkboxSettings = [
                    Episciences_Review::SETTING_ENCAPSULATE_EDITORS,
                    Episciences_Review::SETTING_EDITORS_CAN_ACCEPT_PAPERS,
                    Episciences_Review::SETTING_EDITORS_CAN_PUBLISH_PAPERS,
                    Episciences_Review::SETTING_EDITORS_CAN_REJECT_PAPERS,
                    Episciences_Review::SETTING_EDITORS_CAN_ASK_PAPER_REVISIONS,
                    Episciences_Review::SETTING_EDITORS_CAN_EDIT_TEMPLATES,
                    Episciences_Review::SETTING_EDITORS_CAN_ABANDON_CONTINUE_PUBLICATION_PROCESS,
                    Episciences_Review::SETTING_EDITORS_CAN_REASSIGN_ARTICLES,
                    Episciences_Review::SETTING_AUTHORS_CAN_CONTACT_EDITORS,
                    Episciences_Review::SETTING_DISCLOSE_EDITOR_NAMES_TO_AUTHORS,
                    Episciences_Review::SETTING_DO_NOT_ALLOW_EDITOR_IN_CHIEF_SELECTION,
                    Episciences_Review::SETTING_CAN_CHOOSE_VOLUME,
                    Episciences_Review::SETTING_CAN_PICK_SECTION,
                    Episciences_Review::SETTING_CAN_SUGGEST_REVIEWERS,
                    Episciences_Review::SETTING_CAN_SPECIFY_UNWANTED_REVIEWERS,
                    Episciences_Review::SETTING_CAN_ANSWER_WITH_TMP_VERSION,
                    Episciences_Review::SETTING_CAN_RESUBMIT_REFUSED_PAPER,
                    Episciences_Review::SETTING_CAN_ABANDON_CONTINUE_PUBLICATION_PROCESS,
                    Episciences_Review::SETTING_REVIEWERS_CAN_COMMENT_ARTICLES,
                    Episciences_Review::SETTING_SHOW_RATINGS,
                    Episciences_Review::SETTING_AUTOMATICALLY_REASSIGN_SAME_REVIEWERS_WHEN_NEW_VERSION,
                    Episciences_Review::SETTING_SPECIAL_ISSUE_ACCESS_CODE,
                    Episciences_Review::SETTING_ENCAPSULATE_REVIEWERS,
                    Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS,
                    Episciences_Review::SETTING_SYSTEM_NOTIFICATIONS,
                    Episciences_Review::SETTING_ARXIV_PAPER_PASSWORD,
                    Episciences_Review::SETTING_DISPLAY_STATISTICS,
                    Episciences_Review::SETTING_REFUSED_ARTICLE_AUTHORS_MESSAGE_AUTOMATICALLY_SENT_TO_REVIEWERS,
                    Episciences_Review::SETTING_TO_REQUIRE_REVISION_DEADLINE,
                    Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED,
                    Episciences_Review::SETTING_SYSTEM_COI_COMMENTS_TO_EDITORS_ENABLED,
                    Episciences_Review::SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION,
                    Episciences_Review::SETTING_DISPLAY_EMPTY_VOLUMES,
                    Episciences_Review::SETTING_ALLOW_EDIT_VOLUME_TITLE_WITH_PUBLISHED_ARTICLES,
                ];

                foreach ($checkboxSettings as $checkboxSetting) {
                    // If checkbox is not set, empty, or null, set it to '0'
                    if (!isset($reviewSettingsToSave[$checkboxSetting]) || $reviewSettingsToSave[$checkboxSetting] === '' || $reviewSettingsToSave[$checkboxSetting] === null) {
                        $reviewSettingsToSave[$checkboxSetting] = '0';
                    }
                }

                $review->setOptions($reviewSettingsToSave);
                if ($review->save()) {
                    $message = '<strong>' . $this->view->translate("Les modifications ont bien été enregistrées.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                    $url = $this->_helper->url($this->getRequest()->getActionName(), $this->getRequest()->getControllerName());
                    $this->_helper->redirector->gotoUrl($url);
                } else {
                    if ($reviewSettingsToSave[self::SETTING_JOURNAL_PUBLISHER] === '' && $reviewSettingsToSave[self::SETTING_JOURNAL_PUBLISHER_LOC] !== ''){
                        $message = '<strong>' . $this->view->translate("Le lieu de publication est renseigné, veuillez saisir l'éditeur également") . '</strong>';
                        $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
                    }
                    $message = '<strong>' . $this->view->translate("Les modifications n'ont pas pu être enregistrées.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                }
            } else {
                $message = '<strong>' . $this->view->translate("Le formulaire comporte des erreurs.") . '</strong>';
                $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            }
        }

        if ($defaults) {

            if ($request->isPost() && array_key_exists('submit', $request->getPost())) {
                $intervals = ['invitation_deadline', 'rating_deadline', 'rating_deadline_min', 'rating_deadline_max'];
                foreach ($intervals as $interval_name) {
                    $defaults[$interval_name] = $defaults[$interval_name] . ' ' . $defaults[$interval_name . '_unit'];
                }
            }
            $form->setDefaults($defaults);
        }
        $this->view->form = $form;
    }

    public function staffAction()
    {

        $page = new Episciences_Website_Navigation_Page_EditorialStaff();
        $page->load();


        // list for each editor to which section they belong
        // don't list an editor if he is already listed as a chief

        $chief_editors = Episciences_Review::getChiefEditors();
        $editors = Episciences_Review::getEditors();

        /* @var $editor Episciences_Editor */

        foreach ($editors as $editor) {
            $editor->loadAssignedSections();
        }


        $secretaries = Episciences_Review::getSecretaries();
        foreach ($secretaries as $secretary) {
            if (array_key_exists($secretary->getUid(), $chief_editors) ||
                array_key_exists($secretary->getUid(), $editors)) {
                unset($secretaries[$secretary->getUid()]);
            }
        }
        $webmasters = Episciences_Review::getWebmasters();
        foreach ($webmasters as $webmaster) {
            if (array_key_exists($webmaster->getUid(), $chief_editors) ||
                array_key_exists($webmaster->getUid(), $editors) ||
                array_key_exists($webmaster->getUid(), $secretaries)) {
                unset($webmasters[$webmaster->getUid()]);
            }
        }

        $staff = [
            Episciences_Acl::ROLE_CHIEF_EDITOR => $chief_editors,
            Episciences_Acl::ROLE_EDITOR => $editors,
            Episciences_Acl::ROLE_SECRETARY => $secretaries,
            Episciences_Acl::ROLE_WEBMASTER => $webmasters
        ];
        $this->view->isDisplayPhotos = $page->isDisplayPhotos();
        $this->view->staff = $staff;
    }

    /**
     * Assignation automatique de rédacteurs
     */
    public function assignationmodeAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();

        $editorsAssignmentMode = $request->getParam('editors_assignment_mode');

        if (
            $request->isXmlHttpRequest() &&
            $request->isPost() &&
            array_key_exists('editors_assignment_mode', $request->getPost()) &&
            $review->getSetting(Episciences_Review::SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT) !== $editorsAssignmentMode
        ) {
            $review->setSetting(Episciences_Review::SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT, $editorsAssignmentMode);
            $review->save();
        }

        try {
            $review->loadSettings();
            $form = $review->getEditorsAssignationDetailForm();
            $html = (null === $form) ? '' : $form;
        } catch (Zend_Exception $e) {
            error_log('APPLICATION_EXCEPTION_ASSIGNATION_MODE_ACTION : CODE_' . $e->getCode() . 'MESSAGE_' . $e->getMessage());
            $message = '<strong>' . $this->view->translate("Les modifications n'ont pas pu être enregistrées.") . '</strong>';
            $html = $message;
        }

        echo $html;
    }

    /**
     * @deprecated see suggestion: git #182
     * Assignation automatique des rédacteurs
     */
    public function editorsassignationAction()
    {
        /** @var Episciences_Review $review */
        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $post = $request->getPost();

        if (!$review->getSetting($review::SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT)) {//Au  premier lancement, si ces variables ne sont pas définies, on les initialises
            $review->setSetting($review::SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT, $review::ASSIGNMENT_EDITORS_MODE['predefined']);
            $review->save();
        }

        try {
            $form = $review->getEditorsAssignationForm();

            if ($request->isPost() && isset($post['systemAutoEditorsAssignment']) && $post['systemAutoEditorsAssignment'] === $review::ASSIGNMENT_EDITORS_MODE['advanced'] && $form->isValid($post)) {

                $doSave = false;
                $selectedOptions = [];

                if (isset($post['editorsAssignmentDetails'])) {
                    $selectedOptions = $post['editorsAssignmentDetails'];
                    $doSave = array_key_exists('save', $post);
                } elseif (isset($post['advancedAssignation'])) {
                    $selectedOptions = $post['advancedAssignation']['editorsAssignmentDetails'];
                    $doSave = array_key_exists('save', $post['advancedAssignation']);
                }

                if ($doSave) {
                    $tmp = array_keys($review::ASSIGNMENT_EDITORS_DETAIL);
                    foreach ($selectedOptions as $value) {
                        $setting = array_search($value, $review::ASSIGNMENT_EDITORS_DETAIL, true);

                        if($setting !== false){
                            $review->setSetting($setting, $review::ENABLED);
                            unset($tmp[(int)$value]);

                        }

                    }

                    if (!empty($tmp)) {
                        foreach ($tmp as $setting) { // RAZ du reste des options d'assignation auto de rédacteurs
                            $review->setSetting($setting, $review::DISABLED);
                        }
                    }

                    if ($review->save()) {
                        $message = '<strong>' . $this->view->translate("Les modifications ont bien été enregistrées.") . '</strong>';
                        $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                    } else {
                        $message = '<strong>' . $this->view->translate("Les modifications n'ont pas pu être enregistrées.") . '</strong>';
                        $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                    }
                }
            }

            $this->view->form = $form;

        } catch (Exception $e) {
            error_log('APPLICATION_EXCEPTION_EDITORS_ASSIGNATION_ACTION : CODE_' . $e->getCode() . 'MESSAGE_' . $e->getMessage());
        }
    }
}

