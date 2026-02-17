<?php

use Psr\Cache\InvalidArgumentException as InvalidArgumentExceptionAlias;

require_once APPLICATION_PATH . '/modules/common/controllers/DefaultController.php';

class SubmitController extends DefaultController
{
    /**
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Form_Exception
     * @throws InvalidArgumentExceptionAlias
     */

    public function indexAction(): void
    {
        $settings = Zend_Registry::get('reviewSettings');
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $isPost = $request->isPost();
        $post = $isPost ? $request->getPost() : [];
        $default = [];
        $isFromZSubmit = false;

        if ($isPost && $this->isPostMaxSizeReached()) {
            $this->handlePostMaxSizeReached();
            return;
        }

        if (array_key_exists('episciences_form', $post)) {
            $this->handleZSubmit($request, $settings, $default, $isFromZSubmit);
        }

        $submit = new Episciences_Submit();
        $form = $submit::getForm($settings, $default, $isFromZSubmit);

        if ($isPost && array_key_exists('submitPaper', $post)) {
            $this->handleSubmitPaper($request, $form, $submit, $post);
            return;
        }

        $this->prepareViewData($settings, $isFromZSubmit, $form);
    }

    /**
     * Handle case when POST max size is reached.
     */
    private function handlePostMaxSizeReached(): void
    {
        $this->_helper
            ->FlashMessenger
            ->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)
            ->addMessage($this->buildReachedMessage());

        $this->_helper->redirector('index', 'submit');
    }

    /**
     * Handle requests coming from Z-Submit application.
     */
    private function handleZSubmit(
        Zend_Controller_Request_Http $request,
        array                        $settings,
        array                        &$default,
        bool                         &$isFromZSubmit
    ): void
    {
        $zPost = $request->getPost()['episciences_form'] ?? null;

        if (!$zPost) {
            return;
        }

        $zConceptIdentifier = $zPost['ci'] ?? null;
        $repoId = $zPost['repoid'] ?? null;

        $cleanedIdentifier = Episciences_Repositories::callHook(
            'hookCleanIdentifiers',
            ['id' => $zPost['doi_show'] ?? null, 'repoId' => $repoId]
        );

        $zIdentifier = $cleanedIdentifier['identifier'] ?? null;

        $isFromZSubmit = EPISCIENCES_Z_SUBMIT['STATUS']
            && $zIdentifier
            && $zConceptIdentifier
            && in_array($repoId, $settings['repositories'], true);

        if (!$isFromZSubmit) {
            return;
        }

        if (!Episciences_Auth::hasRealIdentity()) {
            $message = $this->view->translate(
                "Vous avez été redirigé vers cette page, votre compte sur cette application ne semble pas être le bon !"
            );
            $this->_helper
                ->FlashMessenger
                ->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)
                ->addMessage($message);
        }

        $paper = Episciences_PapersManager::findByIdentifier($zConceptIdentifier);

        $isFirstSubmission = !$paper || (
                $paper->getConcept_identifier() === $zConceptIdentifier
                && in_array(
                    $paper->getStatus(),
                    [
                        Episciences_Paper::STATUS_SUBMITTED,
                        Episciences_Paper::STATUS_OK_FOR_REVIEWING,
                        Episciences_Paper::STATUS_REFUSED,
                    ],
                    true
                )
            );

        if (!$isFirstSubmission && ($paper->isRevisionRequested() || $paper->isFormattingCompleted())) {
            $rOptions = [
                'controller' => 'paper',
                'action' => 'view',
                'id' => $paper->getDocid(),
                'z-identifier' => $zIdentifier,
            ];

            $this->redirect($this->view->url($rOptions, null, true));
            return;
        }

        $default['repoId'] = Episciences_Repositories::ZENODO_REPO_ID;
        $default['docId'] = $zIdentifier;
    }

    /**
     * Handle the EPI form submission (submitPaper).
     * @param Zend_Controller_Request_Http $request
     * @param Zend_Form $form
     * @param Episciences_Submit $submit
     * @param array $post
     * @return void
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Form_Exception
     * @throws InvalidArgumentExceptionAlias
     */
    private function handleSubmitPaper(
        Zend_Controller_Request_Http $request,
        Zend_Form                    $form,
        Episciences_Submit           $submit,
        array                        $post
    ): void
    {
        $canReplace = (bool)$request->getPost('can_replace');

        $post = $this->normalizeSearchDocIdentifiers($post);

        $this->adjustFormForReplacementOrSuggestions($request, $form, $canReplace);
        $this->setDdFileRequiredFlag($form, $post);

        if (!$form->isValid($post)) {
            $this->renderFormErrors($form);
            return;
        }

        $formValues = $this->mergeFormValuesWithPost($form->getValues(), $post);

        if ($canReplace) {
            [$result, $message] = $this->handlePaperReplacement($formValues);
        } else {
            [$result, $message] = $this->handleNewSubmission($submit, $formValues);
        }

        $this->handleSubmissionResult($result, $message);
    }

    /**
     * Normalize repository identifiers coming from search_doc.
     */
    private function normalizeSearchDocIdentifiers(array $post): array
    {
        if (isset($post['search_doc']['repoId'])) {
            $repoId = (int)$post['search_doc']['repoId'];

            $hookCleanIdentifiers = Episciences_Repositories::callHook(
                'hookCleanIdentifiers',
                [
                    'id' => $post['search_doc']['docId'] ?? null,
                    'repoId' => $repoId,
                ]
            );

            if (!empty($hookCleanIdentifiers)) {
                $post['search_doc']['docId'] = $hookCleanIdentifiers['identifier'];
            }
        }

        return $post;
    }

    /**
     * Adjust the form depending on replacement mode or suggested editors.
     */
    private function adjustFormForReplacementOrSuggestions(
        Zend_Controller_Request_Http $request,
        Zend_Form                    $form,
        bool                         $canReplace
    ): void
    {
        if ($canReplace) {
            $form->removeElement('suggestEditors');
            $form->removeElement('sections');
            return;
        }

        if ($request->getPost('suggestEditors') && $form->getElement('suggestEditors')) {
            /** @var Zend_Form_Element_Multi|Zend_Form_Element_Select $suggestionsElement */
            $suggestionsElement = $form->getElement('suggestEditors');
            $suggestionsElement->setRegisterInArrayValidator(false);
        }
    }

    /**
     * Set Dataverse file element required flag based on POST.
     */
    private function setDdFileRequiredFlag(Zend_Form $form, array $post): void
    {
        $requiredDdKey = sprintf('%s_is_required', Episciences_Submit::DD_FILE_ELEMENT_NAME);

        if (!isset($post[$requiredDdKey])) {
            return;
        }

        $form->getElement(Episciences_Submit::DD_FILE_ELEMENT_NAME)
            ?->setRequired($post[$requiredDdKey] === 'true');
    }

    /**
     * Merge Zend_Form values with raw POST (keep unmapped inputs).
     */
    private function mergeFormValuesWithPost(array $formValues, array $post): array
    {
        foreach ($post as $input => $value) {
            if (!array_key_exists($input, $formValues)) {
                $formValues[$input] = $value;
            }
        }

        return $formValues;
    }

    /**
     * Handle the case where a paper is replaced by a new version.
     *
     * @param array $formValues
     * @return array{0: array, 1: string} [result, message]
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    private function handlePaperReplacement(array &$formValues): array
    {
        $selfPaper = new Episciences_Paper([
            'identifier' => $formValues['old_identifier'],
            'version' => (int)$formValues['old_version'],
            'repoId' => (int)$formValues['old_repoid'],
            'status' => (int)$formValues['old_paper_status'],
        ]);

        unset(
            $formValues['old_identifier'],
            $formValues['old_repoid']
        );

        $result = $selfPaper->updatePaper($formValues);
        $message = '<strong>' . $result['message'] . '</strong>';

        return [$result, $message];
    }

    /**
     * Handle the case of a brand-new submission.
     *
     * @param Episciences_Submit $submit
     * @param array $formValues
     * @return array{0: array, 1: string} [result, message]
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws InvalidArgumentExceptionAlias
     */
    private function handleNewSubmission(Episciences_Submit $submit, array $formValues): array
    {
        $result = $submit->saveDoc($formValues);
        $message = $result['message'];

        return [$result, $message];
    }

    /**
     * Process the result of the submission and redirect accordingly.
     */
    private function handleSubmissionResult(array $result, string $message): void
    {
        if ($result['code'] === 0) {
            $this->_helper
                ->FlashMessenger
                ->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)
                ->addMessage($message);

            $this->_helper->redirector('submitted', 'paper');
            return;
        }

        $this->_helper
            ->FlashMessenger
            ->setNamespace('success')
            ->addMessage($message);

        $docId = $result['docId'] ?? null;

        if ($docId) {
            $this->_helper->redirector('view', 'paper', null, ['id' => $docId]);
        } else {
            $this->_helper->redirector('submitted', 'paper');
        }
    }

    /**
     * Prepare all data needed by the view.
     */
    private function prepareViewData(array $settings, bool $isFromZSubmit, Zend_Form $form): void
    {
        $this->view->form = $form;
        $examples = [];

        foreach (Episciences_Repositories::getRepositories() as $id => $repository) {
            if ((int)$id === 0) {
                continue; // skip Episciences itself
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
     */

    public function getdocAction(): void
    {
        $this->disableViewAndLayout();

        $params = $this->getRequest()->getPost();
        $version = $this->extractVersion($params);
        $response = $this->fetchDocument($params, $version);

        if ($this->isValidResponse($response)) {
            $response = $this->prepareRecord($response, $params);
        }

        $this->_helper->json($response);
    }

    /**
     * Disable view rendering and layout.
     */
    private function disableViewAndLayout(): void
    {
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();
    }

    /**
     * Extract version number or default to 1.
     */
    private function extractVersion(array $params): int
    {
        return (isset($params['version']) && is_numeric($params['version']))
            ? (int)$params['version']
            : 1;
    }

    /**
     * Retrieve document data from repository.
     * @throws Zend_Exception
     */
    private function fetchDocument(array $params, int $version): array
    {
        $latestObsoleteDocId = $params['latestObsoleteDocId'] ?? null;

        return Episciences_Submit::getDoc(
            $params['repoId'],
            $params['docId'],
            $version,
            $latestObsoleteDocId
        );
    }

    /**
     * Check if response is valid and ready for processing.
     */
    private function isValidResponse(array $response): bool
    {
        return !array_key_exists('error', $response)
            && array_key_exists('record', $response);
    }

    /**
     * Transform and clean the XML record for display.
     */
    private function prepareRecord(array $response, array $params): array
    {
        $response['record'] = preg_replace('#xmlns="(.*)"#', '', $response['record']);

        if ($params['repoId'] === Episciences_Repositories::CWI_REPO_ID) {
            $response['record'] = Episciences_Repositories_Common::checkAndCleanRecord($response['record']);
        }

        // Apply repository hook
        $hookData = array_merge($response, ['repoId' => $params['repoId']]);
        $hookResult = Episciences_Repositories::callHook('hookCleanXMLRecordInput', $hookData);
        unset($hookResult['repoId']);

        $response = !empty($hookResult) ? $hookResult : $response;

        // Determine data display options
        $response['ddOptions'] = [
            'displayDDForm' => Episciences_Repositories::isDataverse($params['repoId']),
            'isSoftware' => false
        ];

        $type = $this->extractType($response);
        if ($type) {
            $this->updateDdOptions($response['ddOptions'], strtolower($type));
        }

        // Apply XSLT transformation
        $response['xslt'] = Ccsd_Tools::xslt(
            $response['record'],
            APPLICATION_PUBLIC_PATH . '/xsl/full_paper.xsl'
        );

        return $response;
    }

    /**
     * Extract latest type from enrichment data.
     */
    private function extractType(array $response): ?string
    {
        $enrichment = Episciences_Repositories_Common::ENRICHMENT;
        $typeKey = Episciences_Repositories_Common::RESOURCE_TYPE_ENRICHMENT;

        if (!isset($response[$enrichment][$typeKey])) {
            return null;
        }

        $types = (array)$response[$enrichment][$typeKey];
        return $types[array_key_last($types)] ?? null;
    }

    /**
     * Update DD options based on resource type.
     */
    private function updateDdOptions(array &$options, string $type): void
    {
        $isSoftware = $type === Episciences_Paper::SOFTWARE_TYPE_TITLE;
        $isDataset = $type === Episciences_Paper::DATASET_TYPE_TITLE;

        if ($isSoftware || $isDataset) {
            $options['displayDDForm'] = true;
        }

        $options['isSoftware'] = $isSoftware;
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

        $response = ['hasHook' => $hasHook, 'isRequiredVersion' => $isRequiredVersion];

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

    private function buildReachedMessage(): string
    {
        $message = $this->view->translate('Ce formulaire comporte des erreurs.');
        $message .= ' ';
        $message .= $this->view->translate('La taille maximale des fichiers que vous pouvez télécharger est limitée à');
        $message .= ' ';
        $message .= '<code>' . Episciences_Tools::toHumanReadable(MAX_FILE_SIZE) . '</code>. ';
        $message .= $this->view->translate('Merci de les corriger.');
        return $message;


    }
}
