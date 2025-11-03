<?php

use Episciences\Files\Uploader;
use GuzzleHttp\Exception\GuzzleException;

require_once APPLICATION_PATH . '/modules/common/controllers/PaperDefaultController.php';

/**
 * Class PaperController
 */
class PaperController extends PaperDefaultController
{
    use Episciences\Notify\Headers;
    use Episciences\Signposting\Headers;

    /**
     *  display paper pdf
     * @throws GuzzleException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    public function pdfAction(): void
    {
        $docId = $this->getRequest()->getParam('id');
        $paper = Episciences_PapersManager::get($docId);

        // check if paper exists
        if (!$paper || $paper->getRvid() !== RVID || $paper->getRepoid() === 0) {
            Episciences_Tools::header('HTTP/1.1 404 Not Found');
            $this->renderScript('index/notfound.phtml');
            return;
        }

        $this->requestingAnUnpublishedFile($paper);
        $this->redirectWithFlashMessageIfPaperIsRemovedOrDeleted($paper);
        $url = $paper->getMainPaperUrl();

        if (!$url) {
            Episciences_Tools::header('HTTP/1.1 404 Not Found');
            $this->view->message = 'no PDF files found';
            $this->renderScript('error/http_error.phtml');
            return;
        }

        $pdf_name = $paper->getIdentifier() . '.pdf';

        $mainDocumentContent = $this->getMainDocumentContent($paper, $url);

        $this->updatePaperStats($paper, Episciences_Paper_Visits::CONSULT_TYPE_FILE);

        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        header("Content-Disposition: inline; filename=$pdf_name");
        header("Content-type: application/pdf");
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $mainDocumentContent;
    }

    /**
     * display paper public page
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Form_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception|JsonException
     */
    public function viewAction(): void
    {
        if ($this->getFrontController()->getRequest()->getHeader('Accept') === Episciences_Settings::MIME_LD_JSON) {
            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
            echo $this->addInboxAutodiscoveryLDN();
            exit;
        }

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $this->view->doctype(Zend_View_Helper_Doctype::XHTML1_RDFA);

        $docId = (int)$request->getParam('id');

        $zIdentifier = $request->get('z-identifier');

        $papersManager = new Episciences_PapersManager();

        $paper = $papersManager::get($docId, true, RVID);

        // check if paper exists
        if (!$paper) {
            Episciences_Tools::header('HTTP/1.1 404 Not Found');
            $this->renderScript('index/notfound.phtml');
            return;
        }

        $loggedUid = Episciences_Auth::getUid();
        $isSecretary = Episciences_Auth::isSecretary();
        $isFromZSubmit = false;

        if ($paper->isDataSetOrSoftware()) {
            $isAllowedToAddDataDescriptor =  ($paper->isOwner() || $isSecretary) && !in_array($paper->getStatus(), Episciences_Paper::$_noEditableStatus, true);
            $url = $this->view->url(['controller' => 'paper', 'action' => 'view', 'id' => $paper->getDocid()]);
            $ddForm = Episciences_Submit::getDDNewVersionForm();
            $this->view->ddNewVersionForm = $ddForm;
            $this->view->isAllowedToAddNewVersion = $isAllowedToAddDataDescriptor;

            if (
                $isAllowedToAddDataDescriptor &&
                isset($_FILES[Episciences_Submit::DD_FILE_ELEMENT_NAME]['size']) &&
                $request->isPost() &&
                $request->getPost('postDdNewVersion') &&
                $ddForm->isValid($request->getPost())
            ) {

                $uploader = new Uploader(sprintf('%s/dd/', REVIEW_FILES_PATH . $paper->getDocid()));
                try {
                    $allMd5 = \Episciences\Files\FileManager::findByMd5($paper->getDocid());
                    /** @var \Episciences\Files\File $dFile */
                    $dFile = $uploader->upload(true)->getInfo()[$uploader::UPLOADED_FILES_KEY][Episciences_Submit::DD_FILE_ELEMENT_NAME];
                    if(in_array($dFile->getMd5(), $allMd5, true)) {
                        $message = $this->view->translate("La version que vous essayez d'envoyer existe déjà.");
                        $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
                        $this->_helper->redirector->gotoUrl($url);
                        return;
                    }
                    $uploadsInfo = $uploader->upload()->getInfo();
                } catch (Zend_File_Transfer_Exception $e) {
                    trigger_error($e->getMessage());
                    $uploadsInfo = [];
                }

                if (Episciences_Submit::saveDataDescriptor($uploadsInfo, $paper)) {
                    $message = $this->view->translate("La nouvelle version a bien été enregistrée.");
                    $this->_helper->FlashMessenger->setNamespace(self::SUCCESS)->addMessage($message);
                }

                $this->_helper->redirector->gotoUrl($url);
                return;

            }

        }

        $this->view->metadata = $paper->getDatasetsFromEnrichment();
        $this->view->classifications = $paper->getClassifications();

        if ($this->isRestrictedAccess($paper)) {

            $paperId = $paper->getPaperid() ?: $paper->getDocid();
            $id = Episciences_PapersManager::getPublishedPaperId($paperId);

            if ($id !== 0) {
                // redirect to published version
                $this->redirect('/' . $id);
            } elseif (!Episciences_Auth::isLogged()) {
                // redirect to login if user is not logged in
                $this->redirect($this->url(['controller' => 'user', 'action' => 'login', 'forward-controller' => 'paper', 'forward-action' => 'view', 'id' => $docId ]));
            }

            $this->redirectsIfHaveNotEnoughPermissions($paper);

        }

        $this->redirectWithFlashMessageIfPaperIsRemovedOrDeleted($paper);
        $this->updatePaperStats($paper);
        $paperUrl = $this->publicPaperUrl($paper->getDocid());


        // INBOX autodiscovery @see https://www.w3.org/TR/ldn/#discovery
        $headerLinks[] = $this->getInboxHeaderString();

        $paperHasDoi = $paper->hasDoi();
        $paperDoi = $paper->getDoi();

        if ($request->getMethod() === 'HEAD') {
            $allHeaderLinks = self::getPaperHeaderLinks($paperHasDoi, $paperUrl, $paperDoi, $headerLinks);
            $this->getResponse()->setHeader('Link', implode(', ', $allHeaderLinks));
        }


        // if paper is obsolete, display a warning
        if ($paper->isObsolete()) {
            $latestDocId = $paper->getLatestVersionId();
            $this->view->latestDocId = $latestDocId;
            $this->view->linkToLatestDocId = $this->publicPaperUrl($latestDocId);
        }

        // paper *************************************************************
        $paperMetrics = Episciences_Paper_Visits::getPaperMetricsByPaperId($paper->getPaperid());
        $this->view->paper = $paper;
        $this->view->page_count = $paperMetrics[Episciences_Paper_Visits::PAGE_COUNT_METRICS_NAME];
        $this->view->file_count = $paperMetrics[Episciences_Paper_Visits::FILE_COUNT_METRICS_NAME];

        // other versions block
        $versions = [];

        foreach ($paper->getVersionsIds() as $version => $docId) {
            $versions[$version] = Episciences_PapersManager::get($docId, false);
        }

        $this->view->versions = array_reverse($versions, true);

        // review settings
        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();
        $this->view->review = $review;


        // ratings **************************************************
        //[#169]: https://github.com/CCSDForge/episciences/issues/169
        $isVisibleRatings = (Episciences_Auth::isSecretary() || $paper->getEditor($loggedUid) || $paper->getCopyEditor($loggedUid)) ||
            $paper->isReportsVisibleToAuthor() ||
            ($review->getSetting(Episciences_Review::SETTING_SHOW_RATINGS) && $paper->isPublished());

        if ($isVisibleRatings) {
            $paper->loadRatings();
            $this->view->reports = $paper->getRatings(null, Episciences_Rating_Report::STATUS_COMPLETED, Episciences_Auth::getUser());
            $this->view->isAllowedToSeeReportDetails = !$paper->isOwner() && (Episciences_Auth::isSecretary() || $paper->getEditor($loggedUid));

        }


        // COI
        $isConflictDetected = self::isConflictDetected($paper, $review);
        $this->view->isConflictDetected = $isConflictDetected;

        try {

            $commonTest =
                $isSecretary ||
                (!$review->getSetting(Episciences_Review::SETTING_ENCAPSULATE_EDITORS) && Episciences_Auth::isEditor()) ||
                (!$review->getSetting(Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS) && Episciences_Auth::isCopyEditor()) ||
                $paper->getEditor($loggedUid) ||
                $paper->getCopyEditor($loggedUid);

        } catch (Zend_Db_Statement_Exception $e) {
            $commonTest = false;
            trigger_error($e->getMessage());
        }


        $isAllowedToSeeNoPublicDetails = Episciences_Auth::isLogged() &&
            (
                $paper->isOwner() ||
                (
                    !$isConflictDetected &&
                    (
                        $commonTest ||
                        $paper->getReviewer($loggedUid)
                    )
                )
            );


        $this->view->isAllowedToSeeNoPublicDetails = $isAllowedToSeeNoPublicDetails;


        $isAllowedToAnswerNewVersion =
            Episciences_Auth::isLogged() &&
            (
                $paper->isOwner() ||
                (!$isConflictDetected && $commonTest)
            );


        // paper password bloc

        $displayPaperPasswordBloc = (
            Episciences_Auth::isLogged() &&
            in_array(Episciences_Repositories::ARXIV_REPO_ID, $review->getSetting($review::SETTING_REPOSITORIES)) &&
            $review->getSetting($review::SETTING_ARXIV_PAPER_PASSWORD) &&
            $paper->getRepoid() === (int)Episciences_Repositories::ARXIV_REPO_ID &&
            !in_array($paper->getStatus(), $paper::$_noEditableStatus, true) &&

            (
                $paper->isOwner() ||
                (
                    !$isConflictDetected &&
                    (
                        $isSecretary ||
                        $paper->getEditor($loggedUid) ||
                        $paper->getCopyEditor($loggedUid)

                    )
                )
            )
        );

        if ($displayPaperPasswordBloc) {
            $plainPaperPassword = $this->getPlainPaperPassword($paper);
            $this->view->paperPassword = $plainPaperPassword;
        }


        $this->view->displayPaperPasswordBloc = $displayPaperPasswordBloc;

        $this->savePaperPassword($request, $paper, $displayPaperPasswordBloc);

        $this->view->isAllowedToAnswerNewVersion = $isAllowedToAnswerNewVersion;

        // reviewers comments **************************************************
        // fetch reviewers comments
        $settings = [self::TYPES_STR => [
            Episciences_CommentsManager::TYPE_INFO_REQUEST,
            Episciences_CommentsManager::TYPE_INFO_ANSWER,
            Episciences_CommentsManager::TYPE_CONTRIBUTOR_TO_REVIEWER
        ]];
        $comments = Episciences_CommentsManager::getList($paper->getDocid(), $settings);
        $this->view->comments = $comments;


        $author_comments = Episciences_CommentsManager::getList(
            $paper->getDocid(),
            [
                'type' => Episciences_CommentsManager::TYPE_AUTHOR_COMMENT
            ]);

        $this->view->author_comments = $author_comments;


        // reviewer comments answer forms
        if (!$paper->isAccepted() && !$paper->isPublished() && !$paper->isRefused() && !$paper->isReviewed()) {
            $replyForms = Episciences_CommentsManager::getReplyForms($comments);
            $this->view->replyForms = $replyForms;
        }

        // process comment answer
        if (!empty($replyForms)) {

            /** @var Ccsd_Form $replyForm */
            foreach ($replyForms as $id => $replyForm) {

                if ($request->getPost('postReply_' . $id) !== null) {

                    if ($replyForm->isValid($request->getPost())) {

                        if ($this->save_contributor_answer($id, $paper)) {
                            // success message
                            $message = $this->view->translate("Votre réponse a bien été enregistrée.");
                            $this->_helper->FlashMessenger->setNamespace(self::SUCCESS)->addMessage($message);

                        } else {
                            // error message
                            $message = $this->view->translate("Votre réponse n'a pas pu être enregistrée.");
                            $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
                        }

                        // redirect
                        $this->_helper->redirector->gotoUrl($this->url(['controller' => 'paper', 'action' => 'view', 'id' => $paper->getDocid()]));
                        return;

                    }

                    $this->view->jQuery()->addJavascript('$(function() {$("#replyForm_' . $id . '").show();});');

                }
            }
        }

        // revision requests ******************************************************
        // fetch revision requests
        $settings = [
            self::TYPES_STR => [
                Episciences_CommentsManager::TYPE_REVISION_REQUEST,
                Episciences_CommentsManager::TYPE_REVISION_ANSWER_COMMENT,
                Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION,
                Episciences_CommentsManager::TYPE_REVISION_ANSWER_NEW_VERSION,
                Episciences_CommentsManager::TYPE_REVISION_CONTACT_COMMENT
            ]
        ];

        $revision_requests = Episciences_CommentsManager::getRevisionRequests($paper->getDocid(), $settings);

        // if revision requests were made on previous versions, fetch them too
        $previousVersions = $paper->getPreviousVersions();
        if ($previousVersions) {
            /* @var Episciences_Paper $version */
            $previousComments = [];

            foreach ($previousVersions as $version) {
                $vComments = $version->getComments($settings);
                // ne pas afficher le bloc demande de modification si aucune demande de modification n'a été demandés suite au copy editing
                if ($vComments) {
                    $previousComments[$version->getDocid()] = $version->getComments($settings);
                }
            }

            $this->view->previousVersions = $previousVersions;
            $this->view->previousVersionsDemands = $previousComments;
        }

        // check if author answered the latest revision request
        $currentDemand = null;
        $revisionDeadline = null;

        if (!empty($revision_requests)) {

            $currentDemand = current($revision_requests);

            if (
                !array_key_exists('replies', $currentDemand) ||
                (
                    isset($currentDemand['replies']) &&
                    (int)$currentDemand['replies'][array_key_first($currentDemand['replies'])]['TYPE'] === Episciences_CommentsManager::TYPE_REVISION_CONTACT_COMMENT)
            ) {

                $revisionDeadline = $currentDemand['DEADLINE'];
            }

        }

        $paper->_revisionDeadline = $revisionDeadline;

        $doNotDisplayContactChoice = in_array(
            $paper->getStatus(), Episciences_Paper::All_STATUS_WAITING_FOR_FINAL_VERSION, true
        );

        $this->view->revision_requests = $revision_requests;
        $this->view->currentDemand = $currentDemand;
        $this->view->doNotDisplayContactChoice = $doNotDisplayContactChoice;

        // préparation de copie

        $copyEditingSettings = ['types' => [
            Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST,
            Episciences_CommentsManager::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER,
            Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST,
            Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_ANSWER,
            Episciences_CommentsManager::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST,
            Episciences_CommentsManager::TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED,
            Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST,
            Episciences_CommentsManager::TYPE_ACCEPTED_ASK_AUTHOR_VALIDATION
        ]];

        $copyEditingDemands = Episciences_CommentsManager::getList($paper->getDocid(), $copyEditingSettings);

        $this->view->copyEditingDemands = $copyEditingDemands;

        // reply copy editing answer form
        if ($isAllowedToAnswerNewVersion) {
            $copyEditingReplyForms = Episciences_CommentsManager::getCopyEditingReplyForms($copyEditingDemands, $paper, $zIdentifier);
            $this->view->copyEditingReplyForms = $copyEditingReplyForms;
        }

        // author copy editing process replay

        if (isset($copyEditingReplyForms) && !empty($copyEditingReplyForms)) {
            /** @var Ccsd_Form $ceForm */
            foreach ($copyEditingReplyForms as $id => $ceForm) {
                if (!empty($request->getPost('ce_reply_' . $id))) {
                    if ($ceForm->isValid($request->getPost())) {
                        // le type de de la demande
                        $commentRequestType = (int)$request->getPost('request_comment_type' . $id);

                        if (
                            $this->saveAuthorFormattingAnswer($paper, self::CE_REQUEST_REPLY_ARRAY[$commentRequestType], (int)$id) &&
                            !in_array($paper->getStatus(), Episciences_Paper::$_noEditableStatus, true)
                        ) {        // success message
                            $message = $this->view->translate("Votre réponse a bien été enregistrée.");
                            $this->_helper->FlashMessenger->setNamespace(self::SUCCESS)->addMessage($message);
                        } else {
                            // error message
                            $message = $this->view->translate("Votre réponse n'a pas pu être enregistrée.");
                            $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
                        }

                        // redirect
                        $this->_helper->redirector->gotoUrl($this->url(['controller' => 'paper', 'action' => 'view', 'id' => $paper->getDocid()]));
                        return;

                    }
                    $this->view->jQuery()->addJavascript(
                        '$(function() {$("#replyForm_' . $id . '").show(); $("#replyFormBtn_' . $id . '").hide(); });'
                    );

                }
            }
        }

        $hasHook = $paper->hasHook;

        if ($paper->isTmp()) {
            $firstPaper = Episciences_PapersManager::get($paper->getPaperid(), false);
            $hasHook = $firstPaper->hasHook;
        }

        $this->view->hasHook = $hasHook;
        $this->view->isRequiredVersion = $hasHook ? Episciences_Repositories::callHook(
            'hookIsRequiredVersion', [
            'repoId' => $paper->getRepoid()
        ])['result'] : true;

        $this->view->isAllowedToBackToAdminPage = Episciences_Auth::isLogged() && $commonTest;

        $getterCiting = Episciences_Paper_CitationsManager::formatCitationsForViewPaper($paper->getDocid());
        $this->view->citations = $getterCiting['template'];
        $this->view->counterCitations = $getterCiting['counterCitations'];

        if ($zIdentifier && ($paper->isRevisionRequested() || $paper->isFormattingCompleted())) { // new version submitted from z-submit application

            $this->view->zIdentifier = $zIdentifier;
            $isFromZSubmit = $paper->isFromZenodo();
        }

        $this->view->isFromZSubmit = Zend_Json::encode($isFromZSubmit);

        $zSubmitUrl = null;

        if (!$isFromZSubmit) {

            $zSubmitUrl = $this->getZSubmitUrl(null, [
                'newVersion' => true,
                'epi-docid' => $paper->getDocid(), 'epi-rvcode' => RVCODE,
                'epi-cdoi' => $paper->getConcept_identifier()
            ]);
        }

        $this->view->zSubmitUrl = $zSubmitUrl;
        $this->view->zSubmitStatus = EPISCIENCES_Z_SUBMIT['STATUS'];
        /** @see /config/dist-pwd.json */


        /**
         * Bibliographical References
         */
        $enabledBib = false;
        $enabledManageFromPublicPage = false;
        if (EPISCIENCES_BIBLIOREF['ENABLE'] &&
            ($paper->getStatus() === Episciences_Paper::STATUS_CE_READY_TO_PUBLISH ||
                $paper->getStatus() === Episciences_Paper::STATUS_PUBLISHED)) {
            $this->view->urlcallapibib = APPLICATION_URL . '/' . $docId . '/pdf';
            $this->view->apiEpiBibCitation = EPISCIENCES_BIBLIOREF['URL'];
            $enabledBib = true;
            if (
                Episciences_Auth::isLogged() &&
                (
                    $paper->isOwner() ||
                    Episciences_Auth::isSecretary() ||
                    $paper->isEditor(Episciences_Auth::getUid()) ||
                    $paper->getCopyEditor(Episciences_Auth::getUid())
                )
            ) {
                $enabledManageFromPublicPage = true;
            }
        }
        $this->view->enabledBib = $enabledBib;
        $this->view->enabledManageFromPublicPage = $enabledManageFromPublicPage;

    }

    /**
     * @param Zend_Controller_Request_Http $request
     * @param Episciences_Paper $paper
     * @param bool $displayPaperPasswordBloc
     * @return void
     * @throws JsonException
     * @throws Zend_Db_Adapter_Exception
     */
    private function savePaperPassword(Zend_Controller_Request_Http $request, Episciences_Paper $paper, bool $displayPaperPasswordBloc = false): void
    {


        if ($request->isPost() && $displayPaperPasswordBloc && $paper->isOwner()) {

            $params = $request->getPost();

            if (!empty($params['savePaperPassword'])) {

                $isErrors = true;
                $message = $this->view->translate("Le mot de passe n'a pas été enregistré");

                $postedPwd = trim($params['paperPassword']);
                $detectedSize = mb_strlen($postedPwd);
                if (empty($postedPwd)) {
                    $message .= $this->view->translate(': ');
                    $message .= $this->view->translate('le champ est vide.');

                } elseif ($detectedSize > MAX_PWD_INPUT_SIZE) {
                    $message .= ', ';
                    $message .= $this->view->translate('car');
                    $message .= ' ';
                    $message .= sprintf($this->view->translate("le nombre maximum de caractères autorisé est de <code>%u</code>"), MAX_PWD_INPUT_SIZE);
                    $message .= ' ';
                    $message .= sprintf($this->view->translate('mais </code>%u</code> a été détecté.'), $detectedSize);
                } elseif ($this->getPlainPaperPassword($paper) === $postedPwd) {
                    $message .= ', ';
                    $message .= $this->view->translate('car il est identique à celui déjà enregistré.');
                } else {
                    $paper->setPassword($postedPwd, true);

                    if ($paper->save()) {
                        $message = $this->view->translate("Votre mot de passe a bien été enregistré.");
                        $isErrors = false;
                    }
                }

                $isErrors ? $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message) : $this->_helper->FlashMessenger->setNamespace(self::SUCCESS)->addMessage($message);
                $this->_helper->redirector->gotoUrl($this->url(['controller' => self::CONTROLLER_NAME, 'action' => 'view', 'id' => $paper->getDocid()]));


            }
        }

    }

    /**
     * save contributor answer to a reviewer comment
     * @param int $id : request comment
     * @param Episciences_Paper $paper
     * @param int $commentType : comment type
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     * @throws JsonException
     */
    private function save_contributor_answer(int $id, Episciences_Paper $paper, int $commentType = Episciences_CommentsManager::TYPE_INFO_ANSWER): bool
    {
        // fetch comment from database *******************************************
        $oComment = new Episciences_Comment;
        $oComment->find($id);
        // save comment to database ****************************************
        $oAnswer = $this->saveEpisciencesUserComment($paper, $commentType, $id); // response comment

        if (empty($oAnswer)) {
            return false;
        }

        // paper status update  *******************************************
        $settings = ['unanswered' => true, 'type' => Episciences_CommentsManager::TYPE_INFO_REQUEST];
        $noReplies = Episciences_CommentsManager::getList($paper->getDocid(), $settings);
        if (!$noReplies) {
            // if there is a reply to each comment, update paper status
            $paper->updateStatus($paper::STATUS_BEING_REVIEWED);
            $paper->save();
        }

        // fetch the reviewer who sent the comment
        $reviewer = new Episciences_Reviewer();
        if ($reviewer->findWithCAS($oComment->getUid())) {

            // paper rating page url
            $paper_url = $this->view->url([self::CONTROLLER => self::CONTROLLER_NAME, self::ACTION => self::RATING_ACTION, 'id' => $paper->getDocid()]);
            $paper_url = SERVER_PROTOCOL . '://' . $_SERVER[self::SERVER_NAME_STR] . $paper_url;

            $locale = $reviewer->getLangueid();

            $reviewerTags = [
                Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => $reviewer->getUsername(),
                Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => $reviewer->getScreenName(),
                Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $reviewer->getFullName(),
                Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocid(),
                Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
                Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($locale, true),
                Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata(),
                Episciences_Mail_Tags::TAG_SUBMISSION_DATE => $this->view->Date($paper->getSubmission_date(), $locale),
                Episciences_Mail_Tags::TAG_COMMENT => $oComment->getMessage(),
                Episciences_Mail_Tags::TAG_COMMENT_DATE => $this->view->Date($oComment->getWhen(), $locale),
                Episciences_Mail_Tags::TAG_ANSWER => $oAnswer->getMessage(),
                Episciences_Mail_Tags::TAG_PAPER_URL => $paper_url

            ];

            // Prendre en compte des fichiers attachés aux commentaires dans le mail envoyé

            $attachmentsFiles = [];

            if ($oComment->getFile()) {
                $attachmentsFiles[$oComment->getFile()] = $oComment->getFilePath();
            }

            if ($oAnswer->getFile()) {
                $attachmentsFiles[$oAnswer->getFile()] = $oAnswer->getFilePath();
            }


            return
                Episciences_Mail_Send::sendMailFromReview(
                    $reviewer, Episciences_Mail_TemplatesManager::TYPE_PAPER_COMMENT_ANSWER_REVIEWER_COPY, $reviewerTags,
                    $paper, Episciences_Auth::getUid(), $attachmentsFiles, true
                ) &&
                $this->newCommentNotifyManager($paper, $oAnswer, $reviewerTags, [$oComment->getFile() => $oComment->getFilePath()], ['replayedTo' => $oComment]);
        }

        return true;
    }

    /**
     * Sauvegarde la réponse à un commentaire(demande)
     * @param Episciences_Paper $paper
     * @param int $commentType
     * @param int $parentCommentId
     * @return Episciences_Comment | bool
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Json_Exception
     */
    private function saveEpisciencesUserComment(Episciences_Paper $paper, int $commentType = Episciences_CommentsManager::TYPE_INFO_REQUEST, int $parentCommentId = 0): Episciences_Comment
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $post = $request->getPost();
        $docId = $paper->getDocid();
        $path = REVIEW_FILES_PATH . $paper->getDocid() . self::COMMENTS_STR;

        $comment = new Episciences_Comment();
        $comment->setType($commentType);
        $comment->setDocid($docId);
        $comment->setFilePath($path);

        if ($parentCommentId > 0) { // réponse à ce commentaire
            $comment->setParentid($parentCommentId);
            $comment->setMessage($post['comment_' . $parentCommentId]);

        } else {
            $comment->setMessage($post[self::COMMENT_STR]);
        }

        $comment->isCopyEditingComment();

        // save comment to database
        $result = $comment->save();

        if ($comment->isCopyEditingComment()) {
            $cePath = Episciences_PapersManager::buildDocumentPath($docId);
            $cePath .= DIRECTORY_SEPARATOR;
            $cePath .= Episciences_CommentsManager::COPY_EDITING_SOURCES;
            $cePath .= DIRECTORY_SEPARATOR;
            $cePath .= $comment->getPcid();
            $cePath .= DIRECTORY_SEPARATOR;

            $comment->setFilePath($cePath);
        }

        return !$result ? false : $comment;
    }

    /**
     * @param Episciences_Paper $paper
     * @param int $commentType
     * @param int $parentCommentId
     * @param bool $sendMail
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception|JsonException
     * @throws Exception
     */
    private function saveAuthorFormattingAnswer(Episciences_Paper $paper, int $commentType, int $parentCommentId, bool $sendMail = true): bool
    {
        // prevent "Add sources files" and "Add the formatted version" buttons JS reactivation
        $isExit = (
                $commentType === Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_ANSWER &&
                !in_array($paper->getStatus(), [Episciences_Paper::STATUS_CE_WAITING_AUTHOR_FINAL_VERSION, Episciences_Paper::STATUS_CE_AUTHOR_FINAL_VERSION_DEPOSED], true)
            ) ||
            (

                $commentType === Episciences_CommentsManager::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER &&
                !in_array($paper->getStatus(), [Episciences_Paper::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES, Episciences_Paper::STATUS_CE_AUTHOR_SOURCES_DEPOSED], true)

            );

        if ($isExit) {
            return false;
        }

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $attachments = $request->getPost(Episciences_Mail_Send::ATTACHMENTS); // see js/library/es.fileupload.js
        $attachments = is_array($attachments) ? $attachments : [];

        $templateAuthorType = '';
        $templateEditorType = '';

        // Demande de sources$templateA
        $ceComment = new Episciences_Comment;
        $ceComment->find($parentCommentId);

        // La réponse à la demande de sources
        $cAnswer = $this->saveEpisciencesUserComment($paper, $commentType, $parentCommentId);

        if (empty($cAnswer)) {
            return false;
        }

        if (!empty($attachments)) {
            // Errors : si une erreur s'est produite lors de la validation d'un fichier attaché par exemple(voir es.fileupload.js)
            $attachments = Episciences_Tools::arrayFilterEmptyValues($attachments);
            $cAnswer->setFile(json_encode($attachments));
        }

        // update comment
        $cAnswer->save(true);

        // log comment
        $cAnswer->logComment();

        // path des sources
        $parentPath = REVIEW_FILES_PATH . $cAnswer->getDocid();
        $parentPath .= DIRECTORY_SEPARATOR;
        $parentPath .= Episciences_CommentsManager::COPY_EDITING_SOURCES;
        $parentPath .= DIRECTORY_SEPARATOR;
        $parentPath .= $cAnswer->getParentid();
        $parentPath .= DIRECTORY_SEPARATOR;

        $path = $cAnswer->getFilePath();
        $mailPath = Episciences_Tools::getAttachmentsPath((string)$paper->getPaperid());

        // Le fichier joint à la réponse se trouve dans un autre path : l'ID de la réponse n'est pas encore connu.
        $delete = [];

        $parentPathContent = scandir($parentPath);

        foreach ($parentPathContent as $file) {
            if (!in_array($file, ['.', '..'])
                && in_array($file, $attachments, true) &&
                Episciences_Tools::cpFiles((array)$file, $parentPath, $path)) {
                $delete[] = $file;
            }
        }

        // Suppression des fichiers précédemment déplacés du répertoire parent
        foreach ($delete as $file) {

            if ($sendMail) {
                $file = Episciences_Tools::filenameRotate($mailPath, $file);
                Episciences_Tools::cpFiles((array)$file, $parentPath, $mailPath, true);
            }

            unlink($parentPath . $file);
        }

        $settings = ['unanswered' => true, 'type' => $commentType];
        $noReplies = Episciences_CommentsManager::getList($paper->getDocid(), $settings, false);
        // Initialisation avec le dernier statut connu
        $newStatus = $paper->getStatus();

        if (!$noReplies && $paper->getStatus() !== $paper::STATUS_CE_READY_TO_PUBLISH) {
            if ($commentType === Episciences_CommentsManager::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER) {
                $newStatus = $paper::STATUS_CE_AUTHOR_SOURCES_DEPOSED;
                $templateAuthorType = Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_AUTHOR_SOURCES_DEPOSED_RESPONSE_AUTHOR_COPY;
                $templateEditorType = Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_AUTHOR_SOURCES_DEPOSED_RESPONSE_COPYEDITORS_AND_EDITORS_COPY;
            } elseif ($commentType === Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_ANSWER) {
                $newStatus = $paper::STATUS_CE_AUTHOR_FINAL_VERSION_DEPOSED;
                $templateAuthorType = Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_AUTHOR_COPY;
                $templateEditorType = Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_EDITOR_AND_COPYEDITOR_COPY;
            }
            //Changement état : sources auteurs déposées
            $paper->setStatus($newStatus);
            $paper->save();
            //lOG NEW STATUS
            $paper->log(Episciences_Paper_Logger::CODE_STATUS, null, [self::STATUS => $paper->getStatus()]);
        }

        if ($sendMail) {
            $this->notifyAuthorAndManagersPaper($paper, $ceComment, $cAnswer, $templateAuthorType, $templateEditorType);
        }

        return true;
    }

    /**
     * @param Episciences_Paper $paper
     * @param Episciences_Comment $commentRequest
     * @param Episciences_Comment $commentAnswer
     * @param string $templateAuthorType
     * @param $templateEditorType
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception|JsonException
     */
    private function notifyAuthorAndManagersPaper(Episciences_Paper $paper, Episciences_Comment $commentRequest, Episciences_Comment $commentAnswer, string $templateAuthorType, $templateEditorType): void
    {
        $requester = new Episciences_User();
        $requester->find($commentRequest->getUid());
        $commentFiles = Episciences_Tools::isJson($commentAnswer->getFile()) ? json_decode($commentAnswer->getFile(), true) : (array)$commentAnswer->getFile();

        $attachmentsMail = [];

        foreach ($commentFiles as $file) {
            $attachmentsMail[$file] = $commentAnswer->getFilePath();
        }

        // Notifier l'auteur
        $this->informContributor($paper, $templateAuthorType, $attachmentsMail);

        // Notifier les rédacteurs et les préparateurs de copie
        // + autres: selon les paramètres de la revue, notifier aussi les rédacteurs en chefs, administrateurs et secrétaires de rédaction

        $adminPaperUrl = $this->view->url(['controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'action' => 'view', 'id' => $paper->getDocid()]);
        $adminPaperUrl = SERVER_PROTOCOL . '://' . $_SERVER['SERVER_NAME'] . $adminPaperUrl;

        // Tous les rédacteurs
        $allEditors = $this->getAllEditors($paper);

        // tous les corrceteurs
        $assignedCopyEditors = $this->getAllCopyEditors($paper);

        // autres
        $recipients = $allEditors + $assignedCopyEditors;

        Episciences_Review::checkReviewNotifications($recipients);

        Episciences_PapersManager::keepOnlyUsersWithoutConflict($paper->getPaperid(), $recipients);


        $CC = $paper->extractCCRecipients($recipients, $requester->getUid());

        if ($requester->getUid()) {
            $recipients = [$requester->getUid() => $requester];

        } elseif (empty($recipients)) {
            $recipients = $CC;
            $CC = [];
        }

        foreach ($recipients as $recipient) {
            $locale = $recipient->getLangueid();
            $adminTags = [
                Episciences_Mail_Tags::TAG_REQUESTER_SCREEN_NAME => $requester->getScreenName(),
                Episciences_Mail_Tags::TAG_REQUEST_DATE => $this->view->Date($commentRequest->getWhen(), $locale),
                Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocid(),
                Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
                Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($locale, true),
                Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata($locale),
                Episciences_Mail_Tags::TAG_REQUEST_MESSAGE => $commentRequest->getMessage(),
                Episciences_Mail_Tags::TAG_COMMENT_DATE => $this->view->Date($commentRequest->getWhen(), $locale),
                Episciences_Mail_Tags::TAG_PAPER_URL => $adminPaperUrl
            ];

            Episciences_Mail_Send::sendMailFromReview($recipient, $templateEditorType, $adminTags, $paper, null, $attachmentsMail, false, $CC);
            //reset $CC
            $CC = [];
        }
    }

    /**
     * send mail to author
     * @param Episciences_Paper $paper
     * @param string $templateType
     * @param array $attachments
     * @param array $additionalTags
     * @param int|null $senderUid
     * @return bool
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     */
    private function informContributor(Episciences_Paper $paper, string $templateType, array $attachments = [], array $additionalTags = [], int $senderUid = null): bool
    {
        // Récup. des infos sur l'auteur
        $contributor = $paper->getSubmitter();
        $locale = $contributor->getLangueid();
        $docId = $paper->getDocid();

        // La page de l'article
        $paperUrl = $this->url(['controller' => self::CONTROLLER_NAME, 'action' => 'view', 'id' => $docId ]);
        $paperUrl = SERVER_PROTOCOL . '://' . $_SERVER['SERVER_NAME'] . $paperUrl;

        $tags = [
            Episciences_Mail_Tags::TAG_PAPER_URL => $paperUrl,
            Episciences_Mail_Tags::TAG_ARTICLE_ID => $docId,
            Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
            Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($locale, true),
            Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata($locale),
            Episciences_Mail_Tags::TAG_SUBMISSION_DATE => Episciences_View_Helper_Date::Date($paper->getSubmission_date(), $locale),
            Episciences_Mail_Tags::TAG_ACTION_DATE => Episciences_View_Helper_Date::Date(Zend_Date::now()->toString('dd-MM-yyy'), $locale),
            Episciences_Mail_Tags::TAG_ACTION_TIME => Zend_Date::now()->get(Zend_Date::TIME_MEDIUM),
        ];

        if (!empty($additionalTags)) {
            $tags = array_merge($additionalTags, $tags);
        }

        return Episciences_Mail_Send::sendMailFromReview($contributor, $templateType, $tags, $paper, $senderUid, $attachments);
    }

    public function postorcidauthorAction()
    {

        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $request = $this->getRequest();
        $body = $request->getRawBody();
        $data = json_decode($body, true, JSON_UNESCAPED_UNICODE);

        if ($request->isXmlHttpRequest() && $request->isPost()) {

            $docId = $data['docid'] ?? null;

            if (!$docId) {
                trigger_error('postOrcidAuthorAction: EMPTY docID');
                return;
            }

            try {
                $paper = Episciences_PapersManager::get($docId, false, RVID);

                if (!$paper) {
                    trigger_error('postOrcidAuthorAction: PAPER OBJECT not found');
                    return;
                }

                $isAllowedToManageOrcidAuthor = $paper->isAllowedToManageOrcidAuthor(true);

                if (!$isAllowedToManageOrcidAuthor) {
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->addMessage("Vous ne disposez pas des droits nécessaires pour mettre à jours les ORCID");
                    return;
                }


            } catch (Zend_Db_Statement_Exception $e) {
                trigger_error($e->getMessage());
                return;
            }


            $dbAuthor = Episciences_Paper_AuthorsManager::getAuthorByPaperId($data['paperid']);
            $arrayAuthorDb = [];
            foreach ($dbAuthor as $value) {
                $arrayAuthorDb = json_decode($value['authors'], true, JSON_UNESCAPED_UNICODE);
            }

            //we can do that because we have the same number of author at the same place
            $arrayAuthorForm = $data['authors'];
            foreach ($arrayAuthorDb as $key => $value) {
                if ($arrayAuthorForm[$key][1] !== '') {
                    if (isset($value['orcid'])) {
                        if ($value['orcid'] !== $arrayAuthorForm[$key][1]) {
                            $arrayAuthorDb[$key]['orcid'] = $arrayAuthorForm[$key][1];
                        }
                    } else {
                        $arrayAuthorDb[$key]['orcid'] = $arrayAuthorForm[$key][1];
                    }
                }
                // remove orcid from db if orcid is removed in the form
                if (isset($arrayAuthorDb[$key]['orcid']) && $arrayAuthorDb[$key]['orcid'] !== '' && $arrayAuthorForm[$key][1] === '') {
                    unset($arrayAuthorDb[$key]['orcid']);
                }
            }
            $newAuthorInfos = new Episciences_Paper_Authors();
            $newAuthorInfos->setAuthors(json_encode($arrayAuthorDb, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT));
            $newAuthorInfos->setPaperId($data['paperid']);
            $updateAuthor = Episciences_Paper_AuthorsManager::update($newAuthorInfos);
            if ($updateAuthor > 0) {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_SUCCESS)->addMessage('Vos modifications ont bien été prises en compte');
            } else {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_SUCCESS)->addMessage('Informations déjà prises en compte');
            }

        } else {
            trigger_error('Someone tried to do request for orcid modifications');
        }
    }

    /**
     * @return void
     * @throws JsonException
     * @throws Zend_Form_Exception
     */

    public function getaffiliationsbyauthorAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $request = $this->getRequest();

        if (($request->isXmlHttpRequest() && $request->isPost()) && (Episciences_Auth::isAllowedToManageOrcidAuthor() || Episciences_Auth::isAuthor())) {
            $body = $request->getRawBody();
            $data = json_decode($body, true, JSON_UNESCAPED_UNICODE);
            $affi = Episciences_Paper_AuthorsManager::findAffiliationsOneAuthorByPaperId($data['paperId'], $data['idAuthor']);
            $arrayFormOption = [
                'paperid' => $data['paperId'],
                'idAuthor' => $data['idAuthor'],
            ];
            if ($affi !== "") {
                $formattedAffiliationForInput = Episciences_Paper_AuthorsManager::formatAffiliationForInputRor($affi);
                $arrayFormOption['affiliations'] = $formattedAffiliationForInput;
                //avoid future duplicate
                $acronymAlreadyExisting = Episciences_Paper_AuthorsManager::getAcronymExisting($affi);
                if ($acronymAlreadyExisting !== '') {
                    $arrayFormOption['acronymList'] = $acronymAlreadyExisting;
                }
            }
            $affiForm = Episciences_PapersManager::getAffiliationsForm($arrayFormOption);

            echo $affiForm;
        }
    }

    public function addaffiliationsauthorAction()
    {

        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $rorDomain = "https://ror.org/";
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $affiliations = $request->getPost('affiliations');
        $affiliations = array_unique($affiliations);
        // the authors in the html selection and the database are sorted in the same way, so we just need to get the index of the chosen author.

        $authorKeyJson = $request->getPost('ideditedaffiauthor');
        $paperId = $request->getPost('paperidauthors');
        $authorsInfo = Episciences_Paper_AuthorsManager::getAuthorByPaperId($paperId);
        foreach ($authorsInfo as $key => $value) {
            $jsonAuthorDecoded = json_decode($value['authors'], true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }
        $arrayAffi = [];
        $acronyms = $request->getPost("affiliationAcronym");
        $acronyms = explode('||', $acronyms);
        foreach ($affiliations as $key => $affiliation) {
            if ($affiliation !== "") {
                $affiliation = explode('#', $affiliation);
                //check if we have ROR url
                $nameRor = ["name" => rtrim($affiliation[0])];
                $idArray = [];
                if ((isset($affiliation[1]) && $affiliation[1] !== "") && str_contains(rtrim($affiliation[1]), $rorDomain)) {
                    $rawstrAcronym = Episciences_Paper_AuthorsManager::setOrUpdateRorAcronym($acronyms, $affiliation[0]);
                    $strAcronym = Episciences_Paper_AuthorsManager::cleanAcronym($rawstrAcronym);
                    $idArray["id"] = [
                        ['id' => rtrim($affiliation[1]), 'id-type' => "ROR"]
                    ];
                    if ($strAcronym !== '') {
                        $idArray["id"][0]['acronym'] = trim($strAcronym);
                        $nameRor['name'] = Episciences_Paper_AuthorsManager::eraseAcronymInName($nameRor['name'], $rawstrAcronym);
                    }
                    $arrayAffi[] = array_merge($nameRor, $idArray);
                } else {
                    $arrayAffi[] = $nameRor;
                }

            }
        }
        // avoid space in url to avoid duplicate affiliations
        $currentUrlchecked = '';
        foreach ($arrayAffi as $keyAffi => $affi) {
            if (isset($affi['id'])) {
                if ($currentUrlchecked !== '' && $currentUrlchecked === $affi['id'][0]['id']) {
                    unset($arrayAffi[$keyAffi]);
                }
                $currentUrlchecked = $affi['id'][0]['id'];
            }
        }
        $jsonAuthorDecoded[$authorKeyJson]["affiliation"] = $arrayAffi;
        $newAuthorInfos = new Episciences_Paper_Authors();
        $newAuthorInfos->setAuthors(json_encode($jsonAuthorDecoded, JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $newAuthorInfos->setPaperId($paperId);
        Episciences_Paper_AuthorsManager::update($newAuthorInfos);
        $this->_helper->FlashMessenger->setNamespace('success')->addMessage('Modifications des affiliations bien prise en compte');


        try {
            // Attempt to redirect to the latest version
            $paper = Episciences_PapersManager::getLastPaper($paperId, true);
            $docid = $paper->getDocid();
        } catch (Zend_Db_Statement_Exception $e) {
            $docid = $paperId;
        }

        $this->_helper->redirector->gotoUrl($this->url([self::CONTROLLER => self::ADMINISTRATE_PAPER_CONTROLLER, self::ACTION => 'view', 'id' => $docid ]));
    }

    /**
     * revision request answer form: answer without any modifications
     * @throws Zend_Form_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    public function answerrequestAction(): void
    {
        $this->_helper->layout->disableLayout();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $id = $request->getParam('id');

        $oComment = new Episciences_Comment;
        $oComment->find($id);


        $form = Episciences_CommentsManager::answerRevisionForm();

        $papersManager = new Episciences_PapersManager();

        $paper = $papersManager::get($oComment->getDocid(), false);

        if ($paper->isContributorCanShareArXivPaperPwd()) {
            $form = Episciences_Submit::addPaperArxivPwdElement($form, $paper->isRequiredPaperPwd());
        }


        $form->setAction($this->url(['controller' => 'paper', 'action' => 'saveanswer', 'docid' => $oComment->getDocid(), 'pcid' => $oComment->getPcid()]));
        $this->view->form = $form;
        $this->view->comment = $oComment->toArray();
    }

    /**
     * save author's answer to a revision request (comment only)
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     * @throws JsonException
     */
    public function saveanswerAction(): void
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $post = $request->getPost();
        $docId = $request->getQuery(self::DOC_ID_STR);

        $message = "Votre réponse n'a pas pu être enregistrée : merci de bien vouloir compléter les champs marqués d'un astérisque (*).";
        $nameSpace = 'error';

        // get paper object
        $paper = Episciences_PapersManager::get($docId, false);

        $type = !Ccsd_Tools::ifsetor($post['type'], null) ?
            Episciences_CommentsManager::TYPE_REVISION_ANSWER_COMMENT :
            Episciences_CommentsManager::TYPE_REVISION_CONTACT_COMMENT;

        if (
            !empty($post['comment']) &&
            $request->isPost() &&
            (
                $type === Episciences_CommentsManager::TYPE_REVISION_CONTACT_COMMENT ||
                (
                    !$paper->isContributorCanShareArXivPaperPwd() ||
                    (
                        $paper->isOptionalPaperPwd() ||
                        (
                            $paper->isRequiredPaperPwd() &&
                            !empty($post['paperPassword'])
                        )
                    )
                )
            )
        ) {
            $parentId = $request->getQuery('pcid');

            // get revision request
            $oComment = new Episciences_Comment;
            $oComment->find($parentId);

            // save author's answer to revision request
            $oAnswer = new Episciences_Comment();
            $oAnswer->setFilePath(Episciences_PapersManager::buildDocumentPath($docId) . '/comments/');

            $oAnswer->setParentid($parentId);

            $oAnswer->setType($type);
            $oAnswer->setDocid($docId);
            $oAnswer->setMessage($post[self::COMMENT_STR]);
            $oAnswer->save(false, $paper->getUid()); // admin can save answer

            // send mail to chief editors and editors
            $recipients = $paper->getEditors(true, true);

            foreach ($recipients as $recipient) {

                $locale = $recipient->getLangueid();

                $tags = [
                    Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => $recipient->getUsername(),
                    Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => $recipient->getScreenName(),
                    Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $recipient->getFullName(),
                    Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocid(),
                    Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
                    Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($locale, true),
                    Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata(),
                    Episciences_Mail_Tags::TAG_REQUEST_DATE => $this->view->Date($oComment->getWhen(), $locale),
                    Episciences_Mail_Tags::TAG_REQUEST_MESSAGE => $oComment->getMessage(),
                    Episciences_Mail_Tags::TAG_REQUEST_ANSWER => $oAnswer->getMessage(),
                    Episciences_Mail_Tags::TAG_PAPER_URL => $this->adminPaperUrl($paper->getDocid()) //paper management page url
                ];

                Episciences_Mail_Send::sendMailFromReview(
                    $recipient,
                    Episciences_Mail_TemplatesManager::TYPE_PAPER_REVISION_ANSWER,
                    $tags,
                    $paper,
                    Episciences_Auth::getUid(),
                    [$oAnswer->getFile() => $oAnswer->getFilePath()],
                    true
                );
            }

            if ($type !== Episciences_CommentsManager::TYPE_REVISION_CONTACT_COMMENT) {


                $journalSettings = Zend_Registry::get('reviewSettings');

                if ($paper->getStatus() === Episciences_Paper::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION && isset($journalSettings[Episciences_Review::SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION]) && $journalSettings[Episciences_Review::SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION]) {
                    $newStatus = Episciences_Paper::STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING;
                } else {
                    $newStatus = Episciences_Paper::STATUS_NO_REVISION;
                }

                // update paper status & paper password

                if ($this->dataProcessing($post)['isValid']) {
                    $paper->setPassword($post['paperPassword']);
                }

                if ($paper->getStatus() !== $newStatus) {
                    $paper->setStatus($newStatus);
                    $paper->save();
                    // log status change
                    $paper->log(Episciences_Paper_Logger::CODE_STATUS, null, [self::STATUS => $paper->getStatus()]);
                }
            }

            $nameSpace = self::SUCCESS;
            $message = "Votre réponse a bien été enregistrée.";

            if (!$paper->isOwner()) {
                $url = $this->view->url(
                    [
                        self::CONTROLLER => self::ADMINISTRATE_PAPER_CONTROLLER,
                        self::ACTION => 'view',
                        'id' => $paper->getDocid()
                    ]);
            }

        }

        // redirection and success message
        $this->_helper->FlashMessenger->setNamespace($nameSpace)->addMessage($this->view->translate($message));
        $this->_helper->redirector->gotoUrl($this->url(['controller' => 'paper', 'action' => 'view', 'id' => $docId]));

    }

    /**
     * @param array $post
     * @return array
     */

    private function dataProcessing(array &$post): array
    {

        $paperPwdDetails = [
            'isValid' => false,
            'isValidStrlen' => false,
            'isEmpty' => true
        ];

        if (isset($post['paperPassword'])) {
            $paperPwdDetails['isValidStrlen'] = mb_strlen($post['paperPassword']) <= MAX_PWD_INPUT_SIZE;
            $paperPwdDetails['isEmpty'] = empty(trim($post['paperPassword']));
            $paperPwdDetails['isValid'] = $paperPwdDetails['isValidStrlen'] && !$paperPwdDetails['isEmpty'];

        }

        return $paperPwdDetails;
    }

    /**
     * temporary version form (revision request answer)
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public function tmpversionAction(): void
    {
        $this->_helper->layout->disableLayout();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $id = $request->getParam('id');

        $oComment = new Episciences_Comment;
        $oComment->find($id);

        $form = Episciences_Submit::getTmpVersionForm($oComment);
        $form->setAction($this->url(['controller' => 'paper', 'action' => 'savetmpversion', 'docid' => $oComment->getDocid(), 'pcid' => $oComment->getPcid()]));
        $form->setAttrib('method', 'post');
        $this->view->form = $form;
        $this->view->comment = $oComment->toArray();
    }

    /**
     * save a temporary version (contributor answer to a revision request)
     * unassign reviewers from previous version
     * unassign editors from previous version
     * update previous version status
     * reassign editors to new version
     * optional: reassign reviewers to new version
     * @throws JsonException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    public function savetmpversionAction(): void
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $post = $request->getPost();

        $attachments = $post[Episciences_Mail_Send::ATTACHMENTS] ?? []; // see js/library/es.fileupload.js

        // previous version detail
        $docId = $request->getQuery(self::DOC_ID_STR);
        $paper = Episciences_PapersManager::get($docId, false);
        $paper->loadOtherVolumes();
        $paperId = ($paper->getPaperid()) ?: $paper->getDocid();
        $reviewers = $paper->getReviewers(null, true);
        $editors = $paper->getEditors(true, true);
        $coAuthors = $paper->getCoAuthors();
        // revision request detail
        $requestId = $request->getQuery('pcid');
        $requestComment = new Episciences_Comment;
        $requestComment->find($requestId);

        $isAlreadyAccepted = $requestComment->getOption('isAlreadyAccepted');
        $reassignReviewers = $requestComment->getOption('reassign_reviewers');

        // admin can submit tmp version
        $answerCommentUid = (Episciences_Auth::isSecretary() && ($paper->getUid() !== Episciences_Auth::getUid())) ? $paper->getUid() : Episciences_Auth::getUid();

        // save answer (comment)
        $answerComment = new Episciences_Comment;
        $answerComment->setParentid($requestId);
        $answerComment->setType(Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION);
        $answerComment->setDocid($docId);
        $answerComment->setUid($answerCommentUid);
        $answerComment->setMessage(array_key_exists(self::COMMENT_STR, $post) ? $post[self::COMMENT_STR] : '');
        $answerComment->setFilePath(REVIEW_FILES_PATH . $paperId . '/tmp/');

        if (!empty($attachments)) {
            // Errors : si une erreur s'est produite lors de la validation d'un fichier attaché par exemple(voir es.fileupload.js)
            $attachments = Episciences_Tools::arrayFilterEmptyValues($attachments);
            $answerComment->setFile(json_encode($attachments));
        }

        $isSaved = $answerComment->save(false, $answerCommentUid);

        if (!$isSaved) {
            $message = 'TMP_VERSION : ';
            $message .= $this->view->translate("Une erreur s'est produite pendant l'enregistrement de votre commentaire.");
            $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
            $this->_helper->redirector->gotoUrl($this->url(['controller' => 'paper', 'action' => 'view', 'id' => $docId]));
        }

        // unassign reviewers from previous version
        if ($reviewers) {
            foreach ($reviewers as $reviewer) {
                if (!$reviewer->getInvitation($docId)) {
                    continue;
                }
                $aid = $paper->unassign($reviewer->getUid(), Episciences_User_Assignment::ROLE_REVIEWER);
                // log reviewer unassignment
                $paper->log(Episciences_Paper_Logger::CODE_REVIEWER_UNASSIGNMENT, null, ['aid' => $aid, 'user' => $reviewer->toArray()]);
            }
        }

        // unassign editors from previous version
        if ($editors) {
            foreach ($editors as $editor) {
                $aid = $paper->unassign($editor->getUid(), Episciences_User_Assignment::ROLE_EDITOR);
                // log editor unassignment
                $paper->log(Episciences_Paper_Logger::CODE_EDITOR_UNASSIGNMENT, null, ["aid" => $aid, "user" => $editor->toArray()]);
            }
        }

        // update previous version status
        $paper->setStatus($paper::STATUS_OBSOLETE);
        $paper->save();
        // log status change
        $paper->log(Episciences_Paper_Logger::CODE_STATUS, null, [self::STATUS => $paper->getStatus()]);


        // tmp version init
        $tmpPaper = clone($paper);
        $tmpPaper->setDocid(null);
        $tmpPaper->setPaperid($paperId);

        $isAssignedReviewers = $reassignReviewers && $reviewers;

        if ($isAlreadyAccepted && !$isAssignedReviewers) {
            $status = Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED_AFTER_AUTHOR_MODIFICATION;
        } else {
            $status = $isAssignedReviewers ? $tmpPaper::STATUS_OK_FOR_REVIEWING : $tmpPaper::STATUS_SUBMITTED;
        }

        $tmpPaper->setStatus($status);
        $tmpPaper->setIdentifier($paperId . '/' . $answerComment->getFile());
        $tmpPaper->setVersion((float)$paper->getVersion() + 0.01);
        $tmpPaper->setRepoid(0);
        // update xml
        $xml = $paper->getRecord();
        try {
            $tmpPaper->setRecord($xml);
        } catch (DOMException|Zend_Db_Statement_Exception $e) {
            trigger_error($e->getMessage());
        }
        $tmpPaper->setConcept_identifier($paper->getConcept_identifier());

        // save tmp version
        if ($tmpPaper->save()) {

            if ($tmpPaper->getOtherVolumes()) {
                $tmpPaper->saveOtherVolumes();
            }

            $tmpPaperStatusDetails = [self::STATUS => $status];

            if ($isAlreadyAccepted) {
                $tmpPaperStatusDetails['isAlreadyAccepted'] = $isAlreadyAccepted;
            }

            // log tmp version submission
            $tmpPaper->log(Episciences_Paper_Logger::CODE_STATUS, Episciences_Auth::getUid(), $tmpPaperStatusDetails);
        } else {
            $message = $this->view->translate("Une erreur s'est produite pendant l'enregistrement de votre article.");
            $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
            $this->_helper->redirector->gotoUrl($this->url(['controller' => 'paper', 'action' => 'view', 'id' => $docId]));
        }


        // success message
        // set before send mails, for avoiding template translations conflict
        // TODO : à résoudre
        $message = $this->view->translate("Votre version temporaire a bien été enregistrée.");
        $this->_helper->FlashMessenger->setNamespace(self::SUCCESS)->addMessage($message);


        // reassign reviewers to tmp version
        if ($reviewers && $requestComment->getOption('reassign_reviewers')) {
            $sender = new Episciences_Editor();
            if (!$sender->findWithCAS($requestComment->getUid())) {
                $sender = null;
            }
            $this->reinviteReviewers($reviewers, $paper, $tmpPaper, $sender, self::TMP_VERSION_TYPE);
        }

        $recipients = [];

        // reassign editors to new version
        if ($editors) {
            $recipients += $this->reassignPaperManagers($editors, $tmpPaper);
        }

        // reassign co authors
        if (!empty($coAuthors)) {
            Episciences_User_AssignmentsManager::reassignPaperCoAuthors($coAuthors, $tmpPaper);
        }

        //Mail aux rédacteurs + selon les paramètres de la revue, aux admins et secrétaires de rédactions.

        Episciences_Review::checkReviewNotifications($recipients);
        unset($recipients[$paper->getUid()]);
        Episciences_PapersManager::keepOnlyUsersWithoutConflict($paper->getPaperid(), $recipients);

        if ($tmpPaper->isEditor($requestComment->getUid())) {

            $revisionInitiator = new Episciences_User();
            $revisionInitiator->find($requestComment->getUid());
            $principalRecipient = $revisionInitiator;
        } else {
            $principalRecipient = !empty($recipients) ? $recipients[array_key_first($recipients)] : null;
        }

        $CC = $paper->extractCCRecipients($recipients, $principalRecipient ? $principalRecipient->getUid() : null);

        if (empty($recipients)) {
            $recipients = $CC;
            $CC = [];
        }

        if (null !== $principalRecipient) {

            // link to manage article page
            $paper_url = $this->adminPaperUrl($tmpPaper->getDocid());

            $this->answerRevisionNotifyManager(
                $principalRecipient,
                $paper, $tmpPaper,
                $requestComment,
                $answerComment,
                true,
                [Episciences_Mail_Tags::TAG_PAPER_URL => $paper_url],
                $CC
            );

        } else {
            trigger_error('Answer revision with tmp version: mail not sent to managers: empty recipients');
        }

        // link to public article page
        $publicUrl = $this->view->url([
            self::CONTROLLER => self::PUBLIC_PAPER_CONTROLLER,
            self::ACTION => 'view',
            'id' => $tmpPaper->getDocid()
        ]);
        // empty coauthors if user don't want copy mail for co authors
        if (isset($post['copycoauthor']) && $post['coAuthors'] === 0) {
            $coAuthors = "";
        }
        $submitter = $tmpPaper->getSubmitter();
        Episciences_Mail_Send::sendMailFromReview(
            $submitter,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_NEW_VERSION_TEMPORARY_SUBMISSION_AUTHOR,
            [
                Episciences_Mail_Tags::TAG_PAPER_URL => $publicUrl,
                Episciences_Mail_Tags::TAG_ARTICLE_ID => $tmpPaper->getDocid(),
                Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $tmpPaper->getPaperid(),
                Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $tmpPaper->getTitle(),
                Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $tmpPaper->formatAuthorsMetadata($submitter->getLangueid(true)),
                Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME => $submitter->getFullName()
            ], $tmpPaper, null, [], false, $coAuthors
        );

        if (!$paper->isOwner()) {
            $url = $this->view->url(
                [
                    self::CONTROLLER => self::ADMINISTRATE_PAPER_CONTROLLER,
                    self::ACTION => 'view',
                    'id' => $tmpPaper->getDocid()
                ]);
        } else {

            $url = $this->url(['controller' => 'paper', 'action' => 'view', 'id' => $tmpPaper->getDocid()]);

        }

        // Redirection
        $this->_helper->redirector->gotoUrl($url);
    }

    /**
     * reinvite reviewers from a paper to another
     * @param $reviewers array of Episciences_Reviewer
     * @param Episciences_Paper $paper1
     * @param Episciences_Paper $paper2
     * @param Episciences_User|null $sender : user who reassigned the reviewers
     * @param string $submissionType
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     */
    private function reinviteReviewers(array $reviewers, Episciences_Paper $paper1, Episciences_Paper $paper2, Episciences_User $sender = null, string $submissionType = self::NEW_VERSION_TYPE): bool
    {

        if ($submissionType === self::NEW_VERSION_TYPE) {
            $template_key = Episciences_Mail_TemplatesManager::TYPE_PAPER_NEW_VERSION_REVIEWER_REINVITATION;
        } elseif ($submissionType === self::TMP_VERSION_TYPE) {
            $template_key = Episciences_Mail_TemplatesManager::TYPE_PAPER_TMP_VERSION_REVIEWER_REASSIGN;
        } else {
            return false;
        }

        // mail template init
        $template = new Episciences_Mail_Template();

        $template->findByKey($template_key);
        $template->loadTranslations();

        // link to previous version page
        $paper1_url = $this->view->url([
            self::CONTROLLER => self::CONTROLLER_NAME,
            self::ACTION => 'view',
            'id' => $paper1->getDocid()]);
        $paper1_url = SERVER_PROTOCOL . '://' . $_SERVER[self::SERVER_NAME_STR] . $paper1_url;

        // settings for new invitation / assignment
        $oReview = Episciences_ReviewsManager::find(RVID);
        $oReview->loadSettings();
        // new deadline is today + default deadline interval (journal setting)
        $deadline = Episciences_Tools::addDateInterval(date('Y-m-d'), $oReview->getSetting(Episciences_Review::SETTING_RATING_DEADLINE));
        $sender_uid = ($sender && is_a($sender, self::CLASS_EPI_USER_NAME)) ? $sender->getUid() : 666;

        // loop through each reviewer
        /** @var Episciences_Reviewer $reviewer */
        foreach ($reviewers as $reviewer) {

            // assign reviewer to tmp version (replicate invitation process)
            // reviewer invitation ******************************
            // save assignment (pending status)
            /** @var Episciences_User_Assignment $oAssignment */
            $oAssignment = $reviewer->assign($paper2->getDocid(), [self::DEADLINE_STR => $deadline, self::STATUS => Episciences_User_Assignment::STATUS_PENDING])[0];
            $rating_deadline = $oAssignment->getDeadline();

            // save invitation (pending status)
            $oInvitation = new Episciences_User_Invitation(['aid' => $oAssignment->getId(), 'sender_uid' => $sender_uid]);

            if ($oInvitation->save()) { // recharger l'objet
                $oInvitation = Episciences_User_InvitationsManager::findById($oInvitation->getId());
            }

            $invitation_deadline = $oInvitation->getExpiration_date();

            // link to rating invitation page
            $invitation_url = $this->view->url([
                self::CONTROLLER => 'reviewer',
                self::ACTION => 'invitation',
                'id' => $oInvitation->getId()]);
            $invitation_url = SERVER_PROTOCOL . '://' . $_SERVER[self::SERVER_NAME_STR] . $invitation_url;

            // update assignment with invitation_id
            $oAssignment->setInvitation_id($oInvitation->getId());
            $oAssignment->save();

            // mail init
            $locale = $reviewer->getLangueid();
            $template->setLocale($locale);

            $mail = new Episciences_Mail(self::ENCODING_TYPE);
            $mail->setDocid($paper2->getDocid());
            $mail->addTag(Episciences_Mail_Tags::TAG_ARTICLE_ID, $paper2->getDocid());
            $mail->addTag(Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID, $paper2->getPaperid());
            $mail->addTag(Episciences_Mail_Tags::TAG_ARTICLE_TITLE, $paper1->getTitle($locale, true));
            $mail->addTag(Episciences_Mail_Tags::TAG_AUTHORS_NAMES, $paper1->formatAuthorsMetadata());
            $mail->addTag(Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE, $this->view->Date($paper1->getWhen(), $locale));
            $mail->addTag(Episciences_Mail_Tags::TAG_PAPER_URL, $paper1_url);
            $mail->addTag(Episciences_Mail_Tags::TAG_INVITATION_URL, $invitation_url);
            $mail->addTag(Episciences_Mail_Tags::TAG_INVITATION_DEADLINE, $this->view->Date($invitation_deadline, $locale));
            $mail->addTag(Episciences_Mail_Tags::TAG_RATING_DEADLINE, $this->view->Date($rating_deadline, $locale));

            if ($submissionType === self::TMP_VERSION_TYPE) {

                // link to tmp version page
                $tmpUrl = $this->view->url([
                    self::CONTROLLER => self::CONTROLLER_NAME,
                    self::ACTION => 'view',
                    'id' => $paper2->getDocid()]);
                $tmpUrl = SERVER_PROTOCOL . '://' . $_SERVER[self::SERVER_NAME_STR] . $tmpUrl;

                $mail->addTag(Episciences_Mail_Tags::TAG_TMP_PAPER_URL, $tmpUrl);
            }

            if (is_a($sender, self::CLASS_EPI_USER_NAME)) {
                $mail->setFromWithTags($sender);
            } else {
                $mail->setFrom(RVCODE . '@' . DOMAIN);
            }
            $mail->setTo($reviewer);
            $mail->setSubject($template->getSubject());
            $mail->setTemplate($template->getPath(), $template->getKey() . self::TEMPLATE_EXTENSION);
            $mail->writeMail();


            // log mail
            $paper2->log(Episciences_Paper_Logger::CODE_MAIL_SENT, null, ['id' => $mail->getId(), 'mail' => $mail->toArray()]);

        }

        return true;
    }

    /**
     * new version form (revision request answer)
     * @throws Zend_Exception
     * @throws Zend_Json_Exception
     */
    public function newversionAction(): void
    {
        $this->_helper->layout->disableLayout();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $id = $request->getParam('id');
        $zIdentifier = $request->getParam('z-identifier');

        // fetch revision request
        $oComment = new Episciences_Comment();
        $oComment->find($id);
        $this->view->comment = $oComment->toArray();

        // fetch paper
        $paper = Episciences_PapersManager::get($oComment->getDocid());

        $options = ['newVersionOf' => $paper->getDocid()];

        if ($paper->isDataSetOrSoftware()) {
            $options['dataType'] = $paper->getType()[Episciences_Paper::TITLE_TYPE];
        }

        $isFromZSubmit = false;

        if ($zIdentifier) {

            $options['zIdentifier'] = $zIdentifier;

            if ($paper->isRevisionRequested()) { // new version submitted from z-submit application
                $isFromZSubmit = $paper->isFromZenodo();
            }

        }

        // load form
        $form = Episciences_Submit::getNewVersionForm($paper, $options);
        $form->setAction($this->url(['controller' => 'paper', 'action' => 'savenewversion', 'docid' => $oComment->getDocid(), 'pcid' => $oComment->getPcid()]));

        $this->view->form = $form;

        $this->view->isFromZSubmit = Zend_Json::encode($isFromZSubmit);
        $this->view->zenodoRepoId = Episciences_Repositories::ZENODO_REPO_ID;

        $zSubmitUrl = null;

        if ($isFromZSubmit) {

            $zSubmitUrl = $this->getZSubmitUrl(null, [
                'newVersion' => true,
                'epi-docid' => $paper->getDocid(), 'epi-rvcode' => RVCODE,
                'epi-cdoi' => $paper->getConcept_identifier()
            ]);
        }

        $this->view->zSubmitUrl = $zSubmitUrl;

    }

    /**
     * save new version (revision request answer)
     * @throws JsonException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    public function savenewversionAction(): void
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $post = $request->getPost();

        /** @var Episciences_Review $review */
        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();

        // revision request detail
        $requestId = $request->getQuery('pcid');
        $requestComment = new Episciences_Comment();
        $requestComment->find($requestId);
        $reassignReviewers = $requestComment->getOption('reassign_reviewers');
        $isAlreadyAccepted = $requestComment->getOption('isAlreadyAccepted');

        // previous version detail
        $docId = $request->getQuery(self::DOC_ID_STR);

        $paper = Episciences_PapersManager::get($docId, false);

        $form = Episciences_Submit::getNewVersionForm($paper, $paper->isDataSetOrSoftware() ? ['newVersionOf' => $paper->getDocid(), 'dataType' => $paper->isSoftware() ? Episciences_Paper::SOFTWARE_TYPE_TITLE : Episciences_Paper::DATASET_TYPE_TITLE] : []);

        if (!$form?->isValid($post)) {
            $this->renderFormErrors($form);
            $this->_helper->redirector->gotoUrl($this->url(['controller' => 'paper', 'action' => 'view', 'id' => $docId ]));
            return;
        }

        $paper->loadOtherVolumes(); // github #48
        $paper->loadDataDescriptors();

        //tmp version
        $hasHook = isset($post[self::SEARCH_DOC_STR]['h_hasHook']) && filter_var($post[self::SEARCH_DOC_STR]['h_hasHook'], FILTER_VALIDATE_BOOLEAN);
        $currentVersion = 1;

        if (
            ($paper->hasHook || $hasHook) &&
            !Episciences_Repositories::isDataverse((int)$post[self::SEARCH_DOC_STR]['h_repoId'])
        ) {

            $hookCleanIdentifiers = Episciences_Repositories::callHook('hookCleanIdentifiers', ['id' => $post[self::SEARCH_DOC_STR]['docId'], 'repoId' => $post[self::SEARCH_DOC_STR]['h_repoId']]);

            if (isset($hookCleanIdentifiers['identifier'])) {
                $post[self::SEARCH_DOC_STR]['h_docId'] = $hookCleanIdentifiers['identifier'];
            }

            $conceptIdentifier = null;
            $hookParams = ['identifier' => $post[self::SEARCH_DOC_STR]['h_docId'], 'repoId' => $post[self::SEARCH_DOC_STR]['h_repoId']];

            if (isset($post[self::SEARCH_DOC_STR]['version']) && $post[self::SEARCH_DOC_STR]['version'] !== '') {
                $hookParams['version'] = $post[self::SEARCH_DOC_STR]['version'];

            }

            $hookApiRecord = Episciences_Repositories::callHook('hookApiRecords', $hookParams);

            if (isset($hookApiRecord['conceptrecid'])) {
                $conceptIdentifier = $hookApiRecord['conceptrecid'];

            } else {
                $hookConceptIdentifier = Episciences_Repositories::callHook('hookConceptIdentifier', ['repoId' => $paper->getRepoid(), 'response' => $hookApiRecord]);
                if (isset($hookConceptIdentifier['conceptIdentifier'])) {
                    $conceptIdentifier = $hookConceptIdentifier['conceptIdentifier'];
                }
            }

            if (!$conceptIdentifier || $conceptIdentifier !== $paper->getConcept_identifier()) {
                $message = $this->view->translate("Vos modifications n'ont pas été prises en compte : la version du document n'est pas liée à la précédente.");
                $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
                $this->_helper->redirector->gotoUrl($this->url(['controller' => 'paper', 'action' => 'view', 'id' => $docId]));
                return;
            }

            $hookVersion = Episciences_Repositories::callHook('hookVersion', ['identifier' => $post[self::SEARCH_DOC_STR]['h_docId'], 'repoId' => $post[self::SEARCH_DOC_STR]['h_repoId'], 'response' => $hookApiRecord]);

            if (isset($hookVersion['version'])) {
                $currentVersion = (float)$hookVersion['version'];
            }


        } else {
            $currentVersion = (float)$post[self::SEARCH_DOC_STR][self::VERSION_STR];
        }

        if ($currentVersion < $paper->getVersion()) {
            $message = $this->view->translate("la version de l'article à mettre à jour doit être supérieure à la version précédente.");
            $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
            $this->_helper->redirector->gotoUrl($this->url(['controller' => 'paper', 'action' => 'view', 'id' => $docId]));
            return;
        }


        if (!$this->dataProcessing($post[self::SEARCH_DOC_STR])['isValid'] && $paper->isRequiredPaperPwd()) {
            $message = $this->view->translate("Votre soumission n'a pas été enregistrée : le mot de passe du papier n'a pas été rempli, veuillez réessayer.");
            $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
            $this->_helper->redirector->gotoUrl($this->url(['controller' => 'paper', 'action' => 'view', 'id' => $docId]));
            return;
        }


        $paperId = ($paper->getPaperid()) ?: $paper->getDocid();
        $reviewers = $paper->getReviewers(null, true);
        $editors = $paper->getEditors(true, true);
        $copyEditors = $paper->getCopyEditors(true, true);
        $coAuthors = $paper->getCoAuthors();

        // new version init
        $newPaper = clone($paper);

        $newPaper->setDocid(null);
        $newPaper->setPaperid($paperId); // object cloned remove it

        $isAssignedReviewers = $reassignReviewers && $reviewers;

        if (isset($post['copyEditingNewVersion'])) { // new formatted version
            $status = ($newPaper->getStatus() === Episciences_Paper::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION) ?
                Episciences_Paper::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION :
                Episciences_Paper::STATUS_CE_READY_TO_PUBLISH;
        } elseif ($isAlreadyAccepted && !$isAssignedReviewers) {

            $journalSettings = Zend_Registry::get('reviewSettings');
            $status = (
                isset($journalSettings[Episciences_Review::SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION]) &&
                (int)$journalSettings[Episciences_Review::SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION] === 1
            ) ?
                Episciences_Paper::STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING :
                Episciences_Paper::STATUS_ACCEPTED;
        } else {
            $status = $isAssignedReviewers ? $newPaper::STATUS_OK_FOR_REVIEWING : $newPaper::STATUS_SUBMITTED;
        }

        $newPaper->setStatus($status);
        $newPaper->setIdentifier($post[self::SEARCH_DOC_STR]['h_docId']);
        $newPaper->setVersion($currentVersion);
        $newPaper->setRepoid($post[self::SEARCH_DOC_STR]['h_repoId']);
        try {
            $newPaper->setRecord($post['xml']);
        } catch (DOMException|Zend_Db_Statement_Exception $e) {
            trigger_error($e->getMessage());
        }

        // get sure this article is a new version (paper does not already exists)
        if ($newPaper->alreadyExists()) {
            $message = $this->view->translate("L'article que vous tentez d'envoyer existe déjà.");
            $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
            $this->_helper->redirector->gotoUrl($this->url(['controller' => 'paper', 'action' => 'view', 'id' => $docId]));
            return;
        }


        if (!empty($post[self::SEARCH_DOC_STR]) && isset($post[self::SEARCH_DOC_STR]['paperPassword'])) {
            $newPaper->setPassword($post[self::SEARCH_DOC_STR]['paperPassword'], true);
        }

        // save new version
        if ($newPaper->save()) {

            if ($newPaper->getOtherVolumes()) { // github #48
                $newPaper->saveOtherVolumes();
            }

            $enrichment = [];
            $isEnrichment = isset($post['h_enrichment']) && $post['h_enrichment'] !== '';

            if ($isEnrichment) {
                try {
                    $enrichment = json_decode($post['h_enrichment'], true, 512, JSON_THROW_ON_ERROR);
                    Episciences_Submit::enrichmentProcess($newPaper, $enrichment);

                } catch (Exception $e) {
                    trigger_error($e->getMessage());
                }
            }

            $hookParams = ['repoId' => $newPaper->getRepoid(), 'identifier' => $newPaper->getIdentifier(), 'docId' => $newPaper->getDocid()];

            $response = Episciences_Repositories::callHook('hookFilesProcessing', ($isEnrichment && isset($enrichment['files'])) ? array_merge($hookParams, ['files' => $enrichment['files']]) : $hookParams);

            Episciences_Repositories::callHook('hookLinkedDataProcessing', array_merge($hookParams, ['response' => $response]));

            // admin can submit new version
            $commentUid = (Episciences_Auth::isSecretary() && ($paper->getUid() !== Episciences_Auth::getUid())) ? $paper->getUid() : Episciences_Auth::getUid();


            $data = [
                Episciences_Submit::COVER_LETTER_COMMENT_ELEMENT_NAME => $post[Episciences_Submit::COVER_LETTER_COMMENT_ELEMENT_NAME],
                Episciences_Submit::COVER_LETTER_FILE_ELEMENT_NAME => $_FILES[Episciences_Submit::COVER_LETTER_FILE_ELEMENT_NAME]['name'] ?? null,
                Episciences_Submit::DD_FILE_ELEMENT_NAME => $_FILES[Episciences_Submit::DD_FILE_ELEMENT_NAME]['name'] ?? null,
                Episciences_Submit::DD_PREVIOUS_VERSION_STR => $paper->getLatestDataDescriptor()?->getVersion()
            ];


            (new Episciences_Submit())->processCoverLetterAndDataDescriptor($newPaper, $data);

            // save answer (new version)
            $answerCommentType = !in_array($requestComment->getType(), Episciences_CommentsManager::$_copyEditingFinalVersionRequest, true) ?
                Episciences_CommentsManager::TYPE_REVISION_ANSWER_NEW_VERSION :
                Episciences_CommentsManager::TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED;

            $answerComment = new Episciences_Comment();
            $answerComment->setFilePath(REVIEW_FILES_PATH . $newPaper->getDocid() . self::COMMENTS_STR);
            $answerComment->setUid($commentUid);
            $answerComment->setMessage($post[Episciences_Submit::COVER_LETTER_COMMENT_ELEMENT_NAME]);
            $answerComment->setParentid($requestId);
            $answerComment->setType($answerCommentType);
            $answerComment->setDocid($docId);
            $answerComment->save(false, $answerComment->getUid());

            if ($answerComment->isCopyEditingComment()) {
                $file = $answerComment->getFile();
                // Copier le fichier
                if ($file) {

                    $path = Episciences_PapersManager::buildDocumentPath($docId);
                    $path .= DIRECTORY_SEPARATOR;
                    $path .= Episciences_CommentsManager::COPY_EDITING_SOURCES;
                    $path .= DIRECTORY_SEPARATOR;
                    $path .= $answerComment->getPcid();
                    $path .= DIRECTORY_SEPARATOR;

                    Episciences_Tools::cpFiles((array)$answerComment->getFile(), $answerComment->getFilePath(), $path);
                }

                $answerComment->logComment();
            }

            // unassign reviewers from previous version
            if (!isset($post['copyEditingNewVersion']) && $reviewers) {
                foreach ($reviewers as $reviewer) {
                    if (!$reviewer->getInvitation($docId)) {
                        continue;
                    }
                    $aid = $paper->unassign($reviewer->getUid(), Episciences_User_Assignment::ROLE_REVIEWER);
                    // log reviewer unassignment
                    try {
                        $paper->log(Episciences_Paper_Logger::CODE_REVIEWER_UNASSIGNMENT, null, ['aid' => $aid, 'user' => $reviewer->toArray()]);
                    } catch (Zend_Db_Adapter_Exception $e) {
                        trigger_error($e->getMessage());
                    }
                }
            }

            // unassign editors from previous version
            if (!empty($editors)) {
                foreach ($editors as $editor) {
                    $aid = $paper->unassign($editor->getUid(), Episciences_User_Assignment::ROLE_EDITOR);
                    // log editor unassignment
                    $paper->log(Episciences_Paper_Logger::CODE_EDITOR_UNASSIGNMENT, null, ["aid" => $aid, "user" => $editor->toArray()]);
                }
            }

            // unassign Copy editors from previous version
            if (!empty($copyEditors)) {
                /** @var Episciences_CopyEditor $copyEditor */
                foreach ($copyEditors as $copyEditor) {
                    $aid = $paper->unassign($copyEditor->getUid(), Episciences_User_Assignment::ROLE_COPY_EDITOR);
                    $paper->log(Episciences_Paper_Logger::CODE_COPY_EDITOR_UNASSIGNMENT, null, ["aid" => $aid, "user" => $copyEditor->toArray()]);
                }
            }

            // update previous version status
            $paper->setStatus($paper::STATUS_OBSOLETE);
            $paper->setVid();
            $paper->setOtherVolumes();
            $paper->setPassword();
            $paper->save();
            // log status change
            $paper->log(Episciences_Paper_Logger::CODE_STATUS, null, [self::STATUS => $paper->getStatus()]);

            // reassign reviewers to new version (nouvelle version -> demande de modifications)
            if ($reviewers && $reassignReviewers) {
                $sender = new Episciences_Editor();
                if (!$sender->findWithCAS($requestComment->getUid())) {
                    $sender = null;
                }
                $this->reinviteReviewers($reviewers, $paper, $newPaper, $sender);
            }

            //

            // reassign editors to new version
            if ($editors) {
                $this->reassignPaperManagers($editors, $newPaper);
            }

            // reassign copy editors to new version
            if ($copyEditors) {
                $this->reassignPaperManagers($copyEditors, $newPaper, Episciences_User_Assignment::ROLE_COPY_EDITOR);
            }

            // reassign co authors
            if (!empty($coAuthors)) {
                Episciences_User_AssignmentsManager::reassignPaperCoAuthors($coAuthors, $newPaper);
            }

            $recipients = $editors + $copyEditors;

            //Mail aux rédacteurs + selon les paramètres de la revue, aux admins et secrétaires de rédactions.
            Episciences_Review::checkReviewNotifications($recipients);
            unset($recipients[$paper->getUid()]);

            Episciences_PapersManager::keepOnlyUsersWithoutConflict($paper->getPaperid(), $recipients);


            if ($newPaper->isEditor($requestComment->getUid())) {

                $revisionInitiator = new Episciences_User();
                $revisionInitiator->find($requestComment->getUid());
                $principalRecipient = $revisionInitiator;

            } else {
                $principalRecipient = !empty($recipients) ? $recipients[array_key_first($recipients)] : null;
            }

            $CC = $paper->extractCCRecipients($recipients, $principalRecipient?->getUid());


            if ($principalRecipient) {

                // link to manage article page
                $paper_url = $this->view->url([
                    self::CONTROLLER => self::ADMINISTRATE_PAPER_CONTROLLER,
                    self::ACTION => 'view',
                    'id' => $newPaper->getDocid()
                ]);

                $paper_url = SERVER_PROTOCOL . '://' . $_SERVER[self::SERVER_NAME_STR] . $paper_url;
                $this->answerRevisionNotifyManager(
                    $principalRecipient,
                    $paper,
                    $newPaper,
                    $requestComment,
                    $answerComment,
                    true,
                    [Episciences_Mail_Tags::TAG_PAPER_URL => $paper_url],
                    $CC
                );

            } else {
                trigger_error('Answer revision with new version: mail not sent to managers: empty recipients');
            }

            $newPaperStatusDetails = [self::STATUS => $status];

            if ($isAlreadyAccepted) {
                $newPaperStatusDetails['isAlreadyAccepted'] = $isAlreadyAccepted;
            }
            // link to public article page
            $publicUrl = $this->view->url([
                self::CONTROLLER => self::PUBLIC_PAPER_CONTROLLER,
                self::ACTION => 'view',
                'id' => $newPaper->getDocid()
            ]);

            $submitter = $newPaper->getSubmitter();

            Episciences_Mail_Send::sendMailFromReview(
                $submitter,
                Episciences_Mail_TemplatesManager::TYPE_PAPER_NEW_VERSION_SUBMISSION_AUTHOR,
                [
                    Episciences_Mail_Tags::TAG_PAPER_URL => $publicUrl,
                    Episciences_Mail_Tags::TAG_ARTICLE_ID => $newPaper->getDocid(),
                    Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $newPaper->getPaperid(),
                    Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $newPaper->getTitle(),
                    Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $newPaper->formatAuthorsMetadata($submitter->getLangueid(true)),
                    Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME => $submitter->getFullName()
                ], $newPaper, null, [], false, $coAuthors
            );

            // log new version submission
            $newPaper->log(Episciences_Paper_Logger::CODE_STATUS, Episciences_Auth::getUid(), $newPaperStatusDetails);

            // success message
            $message = $this->view->translate("La nouvelle version de votre article a bien été enregistrée.");
            $this->_helper->FlashMessenger->setNamespace(self::SUCCESS)->addMessage($message);

            // Redirection
            if (($paper->isOwner())) {
                $redUrl = $this->url(['controller' => 'paper', 'action' => 'submitted']);
            } else {
                $redUrl = $this->url(['controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'action' => 'view', 'id' => $newPaper->getDocid()]);
            }
            $this->_helper->redirector->gotoUrl($redUrl);
        } else {
            $message = $this->view->translate("Une erreur s'est produite pendant l'enregistrement de votre article.");
            $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
            $this->_helper->redirector->gotoUrl($this->url(['controller' => 'paper', 'action' => 'view', 'id' => $docId]));
        }

    }

    /**
     * list papers submitted by user
     * @throws Zend_Exception
     */
    public function submittedAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ($request->isXmlHttpRequest()) {

            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender();

            $dataTableColumns = [
                '0' => 'paperid',
                '1' => self::DOC_ID_STR,
                '2' => self::STATUS,
                '3' => '', // désactiver dans js/paper/submitted.js, sinon prévoir une jointure si nécessaire
                '4' => 'vid',
                '5' => 'sid',
                '6' => 'when'
            ];

            $post = $request->getParams();
            $limit = Ccsd_Tools::ifsetor($post['length'], '10');
            $offset = Ccsd_Tools::ifsetor($post['start'], '0');
            $list_search = Ccsd_Tools::ifsetor($post['search']['value'], '');
            // L'ordre est un tableau de tableaux, chaque tableau intérieur étant composé de deux éléments:
            // index de la colonne et la direction
            $requestOrder = Ccsd_Tools::ifsetor($post[self::ORDER_STR], []);

            $review = Episciences_ReviewsManager::find(RVID);
            $volumes = $review->getVolumes();
            $sections = $review->getSections();
            $settings = [
                'is' => ['uid' => Episciences_Auth::getUid()] + Episciences_PapersManager::getFiltersParams(),
                'isNot' => [self::STATUS => Episciences_Paper::NOT_LISTED_STATUS],
                'limit' => $limit,
                'offset' => $offset
            ];

            if (!empty($requestOrder)) {
                $settings[self::ORDER_STR] = Episciences_Tools::dataTableOrder($requestOrder, $dataTableColumns);
            }

            // Pour limiter le nombre de requêtes SQL

            if (!empty($volumes)) {
                $settings[self::VOLUMES_STR] = $volumes;
            }

            if (!empty($sections)) {
                $settings[self::SECTIONS_STR] = $sections;
            }

            $list_search = trim($list_search);

            if ($list_search !== '') {
                $settings['list_search'] = $list_search;
            }

            // Le nombre total d'enregistrements, avant filtrage
            $papersCount = $review->getPapersCount($settings);
            // Le nombre total d'eregistrements, après filtrage
            $papersFiltredCount = $review->getPapersCount($settings, true);
            // La liste des articles, après filtrage
            $papers = $review->getPapers($settings, false, true);

            $tbody = ($papersFiltredCount > 0) ?
                $this->view->partial('partials/datatable_author_papers_list.phtml', [
                    'papers' => $papers,
                    self::VOLUMES_STR => $volumes,
                    self::SECTIONS_STR => $sections
                ]) :
                '';

            echo Episciences_Tools::getDataTableData($tbody, $post['draw'], $papersCount, $papersFiltredCount);
        }
    }

    /**
     * reviewer ratings reports and pending invitations
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Db_Statement_Exception|JsonException
     */
    public function ratingsAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ($request->isXmlHttpRequest()) {

            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender();

            $dataTableColumns = [
                '0' => '', // ***
                '1' => '', // ***
                '2' => '', // ***
                '3' => self::DOC_ID_STR,
                '4' => '', //( *** )désactiver dans js/paper/submitted.js, sinon prévoir une jointure si nécessaire
                '5' => '',
                '6' => 'vid',
                '7' => 'sid',
                '8' => 'when'

            ];

            $post = $request->getParams();
            $limit = Ccsd_Tools::ifsetor($post['length'], '10');
            $offset = Ccsd_Tools::ifsetor($post['start'], '0');
            $list_search = Ccsd_Tools::ifsetor($post['search']['value'], '');
            $ratingStatus = Ccsd_Tools::ifsetor($post['ratingStatus'], []);

            // L'ordre est un tableau de tableaux, chaque tableau intérieur étant composé de deux éléments:
            // index de la colonne et la direction
            $requestOrder = Ccsd_Tools::ifsetor($post[self::ORDER_STR], []);

            $settings = [
                'is' => ['rvid' => RVID] + Episciences_PapersManager::getFiltersParams(),
                'limit' => $limit,
                '$offset' => $offset
            ];

            $journal = Episciences_ReviewsManager::find(RVID);
            $volumes = $journal->getVolumes();
            $sections = $journal->getSections();

            $journal->loadSettings();
            $uid = Episciences_Auth::getUid();
            $reviewer = new Episciences_Reviewer;
            $reviewer->find($uid);

            // Pour limiter le nombre de requêtes SQL
            if (!empty($volumes)) {
                $settings['volumes'] = $volumes;
            }

            if (!empty($sections)) {
                $settings['sections'] = $sections;
            }

            if (!empty($requestOrder)) {
                $settings[self::ORDER_STR] = Episciences_Tools::dataTableOrder($requestOrder, $dataTableColumns);
            }

            if (!empty($ratingStatus)) {
                $settings['ratingStatus'] = $ratingStatus;
            }

            $list_search = trim($list_search);

            if ($list_search !== '') {
                $settings['list_search'] = $list_search;
            }

            // Total des articles assignés
            $allPapersCount = count($reviewer->getAssignedPapers($settings, true));
            // Total des articles assignés après filtrage
            $allPapersFiltredCount = count($reviewer->getAssignedPapers($settings, true, true, false));

            // Liste des articles à afficher
            $papers = $reviewer->getAssignedPapers($settings, true, true);

            /** @var Episciences_Paper $paper */
            foreach ($papers as $paper) {
                $reviewer->getReviewing($paper->getDocid());
            }

            foreach ($papers as &$paper) {
                $paper->getPreviousVersions();
            }
            unset($paper);

            $tbody = (count($papers) > 0) ?
                $this->view->partial('partials/datatable_ratings.phtml', [
                    'reviewer' => $reviewer,
                    'papers' => $papers,
                    self::VOLUMES_STR => $volumes,
                    self::SECTIONS_STR => $sections,
                    'review_deadline' => $journal->getSetting('rating_deadline')
                ]) :
                '';

            echo Episciences_Tools::getDataTableData($tbody, $post['draw'], $allPapersCount, $allPapersFiltredCount);
        }
    }

    /**
     * reviewer rating report
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Form_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    public function ratingAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        // Il est possible (depuis la page de la gestion de l'article) :
        // d'ajouter un rapport de relecture à la place d'un autre relecteur (relire à la place...)
        // d'ajouter un nouveau rapport de relecture (relire cet article...)
        $reviewer_uid = (int)$request->getParam('reviewer_uid');

        // paper block ***********************************************************************************
        $docId = $request->getParam('id');

        // invalid or missing id
        if (!$docId || !is_numeric($docId)) {
            $message = $this->view->translate(self::MSG_PAPER_DOES_NOT_EXIST);
            $this->_helper->FlashMessenger->setNamespace(self::WARNING)->addMessage($message);
            $this->_helper->redirector(self::RATINGS_ACTION, self::CONTROLLER_NAME, null, [PREFIX_ROUTE => RVCODE]);
            return;
        }

        $paper = Episciences_PapersManager::get($docId);

        // paper not found
        if (!$paper) {
            $message = $this->view->translate(self::MSG_PAPER_DOES_NOT_EXIST);
            $this->_helper->FlashMessenger->setNamespace(self::WARNING)->addMessage($message);
            $this->_helper->redirector(self::RATINGS_ACTION, self::CONTROLLER_NAME, null, [PREFIX_ROUTE => RVCODE]);
            return;
        }

        $uid = $paper->getUid();

        if ($uid === $reviewer_uid || $uid === Episciences_Auth::getUid()) { // Relecture de son propre article
            trigger_error('ACL: UID ' . Episciences_Auth::getUid() . ' tried to review his own article ' . $docId, E_USER_WARNING);
            $message = $this->view->translate("Cet article ne peut pas être relu par son auteur");
            $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
            if (Episciences_Auth::isAllowedToUploadPaperReport() || $paper->getEditor(Episciences_Auth::getUid())) {
                $this->_helper->redirector('list', self::ADMINISTRATE_PAPER_CONTROLLER, null, [PREFIX_ROUTE => RVCODE]);
            } else {
                $this->_helper->redirector(self::RATINGS_ACTION, self::CONTROLLER_NAME, null, [PREFIX_ROUTE => RVCODE]);
            }
            return;
        }

        // Check paper status
        $paperStatus = $this->checkPaperStatus($paper, ['fromAction' => 'rating']);

        if (!empty($paperStatus) && !array_key_exists('displayNotice', $paperStatus)) {
            $this->_helper->FlashMessenger->setNamespace(self::WARNING)->addMessage($paperStatus['message']);
            $this->_helper->redirector->gotoUrl($paperStatus['url']);
        }

        // access denied
        $accessToRating = $this->checkAccessToRating($paper, $reviewer_uid);

        if (!array_key_exists('canReviewing', $accessToRating)) {
            $this->_helper->FlashMessenger->setNamespace(self::WARNING)->addMessage($accessToRating['message']);
            $this->_helper->redirector->gotoUrl($accessToRating['url']);
            return;
        }


        $this->view->paper = $paper;

        // fetch journal detail
        $journal = Episciences_ReviewsManager::find(RVID);
        $journal->loadSettings();

        // Cover letter & comments
        $author_comments = Episciences_CommentsManager::getList(
            $paper->getDocid(),
            [
                'type' => Episciences_CommentsManager::TYPE_AUTHOR_COMMENT
            ]);

        $this->view->author_comments = $author_comments;

        if (!array_key_exists('displayNotice', $paperStatus) && $journal->getSetting(Episciences_Review::SETTING_REVIEWERS_CAN_COMMENT_ARTICLES)) {

            // fetch reviewers comments
            $settings = [self::TYPES_STR => [
                Episciences_CommentsManager::TYPE_INFO_REQUEST,
                Episciences_CommentsManager::TYPE_INFO_ANSWER,
                Episciences_CommentsManager::TYPE_CONTRIBUTOR_TO_REVIEWER
            ]];

            $comments = Episciences_CommentsManager::getList($paper->getDocid(), $settings);
            $this->view->comments = $comments;

            $comment_form = Episciences_CommentsManager::getForm();
            $this->view->comment_form = $comment_form;

            // save comment
            if ($request->getPost('postComment') !== null) {
                if ($this->save_reviewer_comment($paper)) {
                    $message = $this->view->translate("Votre commentaire a bien été envoyé.");
                    $this->_helper->FlashMessenger->setNamespace(self::SUCCESS)->addMessage($message);
                } else {
                    $message = $this->view->translate("Votre commentaire n'a pas pu être envoyé.");
                    $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
                }
                $this->_helper->redirector->gotoUrl($this->url(['controller' => 'paper', 'action' => 'rating', 'id' => $paper->getDocid()]));
            }
        }

        // rating block ***********************************************************************************

        $report = $this->checkRatingProcess($paper, $reviewer_uid);

        if (!$report) {
            $report = new Episciences_Rating_Report();
        }

        /* Mettre à jour "onbehalf_uid" : (Exp.)
         * si l'evaluation à la place d'un relecteur a été sauvegardée,
         * mais pas encore validée et c'est, ce dernier, qui va la complèter.
         *
         **/

        $isOwner = ($report->getUid() === Episciences_Auth::getUid());

        if ($onBehalf = !$isOwner) {
            $report->setOnbehalf_uid(Episciences_Auth::getUid());
        } else {
            $report->setOnbehalf_uid(null);
        }

        $this->view->report = $report;
        $this->view->onbehalf = $onBehalf; // paper/rating.phtml

        $this->ratingProcessing($request, $paper, $report);

        $previousRatings = Episciences_Rating_Manager::getPreviousRatings($paper, Episciences_Auth::getUid(), Episciences_Rating_Report::STATUS_COMPLETED);
        $this->view->previousRatings = $previousRatings;
        $this->view->metadata = $paper->getDatasetsFromEnrichment();
    }

    /**
     * @param Episciences_Paper $paper
     * @param array $option
     * @return array
     * @throws Zend_Exception
     */

    private function checkPaperStatus(Episciences_Paper $paper, array $option = []): array
    {

        $translator = Zend_Registry::get('Zend_Translate');
        $result = [];
        $url = '/' . $paper->getDocid();

        $fromRating = isset($option['fromAction']) && $option['fromAction'] === 'rating';

        if ($fromRating) {

            $report = Episciences_Rating_Report::find($paper->getDocid(), Episciences_Auth::getUid());

            if ($report && $report->isCompleted()) {
                return $result;
            }
        }

        // paper has been deleted
        if ($paper->isDeleted() || $paper->isRemoved()) {
            $result['message'] = $paper->isDeleted() ? $translator->translate("Le document demandé a été supprimé par son auteur.") : $translator->translate("Le document demandé a été supprimé par la revue.");
            $result['url'] = '/';
        } elseif ($paper->isAccepted() || $paper->isCopyEditingProcessStarted() || $paper->isReadyToPublish()) { // paper has been accepted or copy editing process has been started
            $result['message'] = $translator->translate("Cet article a déjà été accepté, il n'est plus nécessaire de le relire.");
            $result['url'] = $url;
        } elseif ($paper->isPublished()) {  // paper has been published
            $result['message'] = $translator->translate("Cet article a déjà été publié, il n'est plus nécessaire de le relire.");
            $result['url'] = $url;
        } elseif ($paper->isRefused()) {  // paper has been refused
            $result['message'] = $translator->translate("Cet article a été refusé, il n'est plus nécessaire de le relire.");
            $result['url'] = $url;
        } elseif ($paper->isObsolete()) { // paper is obsolete: display a notice

            $latestDocId = $paper->getLatestVersionId();
            $this->view->linkToLatestDocId = $this->adminPaperUrl($latestDocId);
            $result['displayNotice'] = true;
        }

        return $result;
    }

    /**
     * @param Episciences_Paper $paper
     * @param int|null $reviewerUid
     * @return array
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    private function checkAccessToRating(Episciences_Paper $paper, int $reviewerUid = null): array
    {
        $accessResult = []; // peut relire
        $translator = Zend_Registry::get('Zend_Translate');
        $reviewers = $paper->getReviewers([Episciences_User_Assignment::STATUS_ACTIVE, Episciences_User_Assignment::STATUS_INACTIVE]);
        $isReviewer = array_key_exists(Episciences_Auth::getUid(), $reviewers) || ($reviewerUid && $paper->getReviewer($reviewerUid));

        if (!$isReviewer || $reviewerUid === Episciences_Auth::getUid()) { // Not reviewer or add rating
            if (Episciences_Auth:: isAllowedToUploadPaperReport() || $paper->getEditor(Episciences_Auth::getUid())) {
                $invitations = $paper->getInvitations();
                // Une invitation à relire cet article est en cours  .
                if (array_key_exists(Episciences_Auth::getUid(), $invitations)) {
                    $lastArrayInvitation = end($invitations[Episciences_Auth::getUid()]);
                    $oLastInvitation = Episciences_User_InvitationsManager::find(['ID' => $lastArrayInvitation['INVITATION_ID']]);

                    if ($paper->getDocid() == $lastArrayInvitation['DOCID'] && !$oLastInvitation->hasExpired() && $oLastInvitation->getStatus() === Episciences_User_Invitation::STATUS_PENDING) {
                        $message = $translator->translate("Vous avez été redirigé, car une invitation vous a été envoyé.");
                        $url = '/reviewer/invitation/id/' . $invitations[Episciences_Auth::getUid()][0]['INVITATION_ID'];
                        $accessResult['message'] = $message;
                        $accessResult['url'] = $url;
                        return $accessResult;
                    }
                }

                // Pas d'invitaion en cours => il peut relire cet article
                $accessResult['canReviewing'] = true;

            } else {
                $message = $translator->translate("Vous avez été redirigé, car vous n'êtes pas relecteur pour cet article.");
                $url = '/';
                $accessResult['message'] = $message;
                $accessResult['url'] = $url;
            }// End not reviewer

        } else {
            $accessResult['canReviewing'] = true;
        }

        return $accessResult;
    }

    /**
     * save reviewer comment (from reviewer to contributor)
     * @param Episciences_Paper $paper
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    private function save_reviewer_comment(Episciences_Paper $paper): bool
    {

        // save comment to database ******************************************
        $oComment = $this->saveEpisciencesUserComment($paper, Episciences_CommentsManager::TYPE_INFO_REQUEST);

        // fetch contributor info ********************************************
        $contributor = new Episciences_User;
        $contributor->findWithCAS($paper->getUid());
        $locale = $contributor->getLangueid();
        $docId = $paper->getDocid();

        // send mail to contributor
        // paper page url
        $paper_url = $this->view->url([
            self::CONTROLLER => self::CONTROLLER_NAME,
            self::ACTION => 'view',
            'id' => $docId
        ]);

        $paper_url = SERVER_PROTOCOL . '://' . $_SERVER[self::SERVER_NAME_STR] . $paper_url;

        $contributorTags = [
            Episciences_Mail_Tags::TAG_SENDER_EMAIL => null,
            Episciences_Mail_Tags::TAG_SENDER_FULL_NAME => null,
            Episciences_Mail_Tags::TAG_ARTICLE_ID => $docId,
            Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
            Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($locale, true),
            Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata($locale),
            Episciences_Mail_Tags::TAG_SUBMISSION_DATE => $this->view->Date($paper->getSubmission_date(), $locale),
            Episciences_Mail_Tags::TAG_COMMENT => $oComment->getMessage(),
            Episciences_Mail_Tags::TAG_COMMENT_DATE => $this->view->Date($oComment->getWhen(), $locale),
            Episciences_Mail_Tags::TAG_PAPER_URL => $paper_url

        ];

        $attachmentFiles = [];
        if ($oComment->getFile()) {
            $attachmentFiles[$oComment->getFile()] = $oComment->getFilePath();
        }

        return
            (
                Episciences_Mail_Send::sendMailFromReview(
                    $contributor, Episciences_Mail_TemplatesManager::TYPE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR_AUTHOR_COPY,
                    $contributorTags,
                    $paper, Episciences_Auth::getUid(), $attachmentFiles, true
                ) &&
                $this->newCommentNotifyManager($paper, $oComment, $contributorTags)
            );
    }

    /**
     * @param Episciences_Paper $paper
     * @param int $reviewerUid
     * @return bool|Episciences_Rating_Report|null
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception|Zend_Exception|JsonException
     */
    private function checkRatingProcess(Episciences_Paper $paper, int $reviewerUid = 0)
    {
        $report = null;
        if ($reviewerUid < 0) {
            $this->redirect($this->url(['controller' => 'administratepaper', 'action' => 'view', 'id' => $paper->getDocid()]));

        } elseif ($reviewerUid === 0) { // Déjà relecteur pour cet article
            $report = Episciences_Rating_Report::find($paper->getDocid(), Episciences_Auth::getUid());

        } else {  // Ajouter une relecture ou relire à la place d'un autre relecteur
            $report = $this->reviewFromEditor($paper, $reviewerUid);
        }

        return $report;
    }

    /**
     * @param Episciences_Paper $paper
     * @param int $reviewerUid
     * @return Episciences_Rating_Report|null
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception|JsonException
     */
    private function reviewFromEditor(Episciences_Paper $paper, int $reviewerUid)
    {
        $user = new Episciences_User();
        if (!$user->find($reviewerUid)) {
            $message = self::MSG_REVIEWER_DOES_NOT_EXIST;
            $this->goToUrlAdministratePaper($paper, $message, self::WARNING);
        }

        if ($reviewerUid !== Episciences_Auth::getUid()) {
            $this->view->url_reviewer = $user->toArray();// paper/rating.phtml // Pour la relecture à la place
        }

        return $this->applyRating($paper, $reviewerUid);

    }

    /**
     * @param Episciences_Paper $paper
     * @param string $message
     * @param string $type
     * @param bool $report_status
     * @throws Zend_Exception
     */
    private function goToUrlAdministratePaper(Episciences_Paper $paper, string $message = '', string $type = self::ERROR, bool $report_status = false): void
    {

        $translator = Zend_Registry::get('Zend_Translate');

        $message = trim($message);

        if ('' !== $message) {
            $message = $translator->translate($message);
        }

        $this->_helper->FlashMessenger->setNamespace($type)->addMessage($message);
        $this->_helper->redirector('view', self::ADMINISTRATE_PAPER_CONTROLLER, null, [
            PREFIX_ROUTE => RVCODE,
            'id' => $paper->getDocid(),
            'is_completed' => json_encode($report_status)
        ]);
    }

    /**
     * @param Episciences_Paper $paper
     * @param int $reviewerUid
     * @return Episciences_Rating_Report|null
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception|JsonException
     */
    private function applyRating(Episciences_Paper $paper, int $reviewerUid): ?Episciences_Rating_Report
    {

        if ($reviewerUid <= 0) {
            return null;
        }

        $report = Episciences_Rating_Report::find($paper->getDocid(), $reviewerUid);

        if ($report) {
            $status = $report->getStatus();
            switch ($status) {
                case Episciences_Rating_Report::STATUS_WIP :
                    $report->setOnbehalf_uid(Episciences_Auth::getUid());
                    break;
                case Episciences_Rating_Report::STATUS_COMPLETED:
                    $message = self::MSG_REPORT_COMPLETED;
                    $this->goToUrlAdministratePaper($paper, $message, self::ERROR, $report->isCompleted());
                    break;
                default:
                    //not action
                    break;
            }
        } else {

            $user = new Episciences_User();
            $user->find($reviewerUid);

            $docId = $paper->getDocid();

            //save alias
            if ($paper->getPaperid() !== $docId) { // new version
                if ($user->hasAlias($docId, false)) { // already has an alias for at least one version
                    $user->createAlias($docId, $user->getAlias($docId, false));
                } else {
                    $user->createAlias($docId);
                }
            } elseif (!$user->hasAlias($docId)) { // first version
                $user->createAlias($docId);
            }

            //Assigner l'article
            $params = [
                'rvid' => RVID,
                'itemid' => $docId,
                'tmp_user' => 0,
                'item' => Episciences_User_Assignment::ITEM_PAPER,
                'roleid' => Episciences_User_Assignment::ROLE_REVIEWER,
            ];

            /** @var Episciences_User_Assignment $eAssignment */
            $eAssignment = Episciences_UsersManager::assign($reviewerUid, $params)[0];
            $eAssignment->save();
            // update paper status
            if ($paper->getStatus() === Episciences_Paper::STATUS_SUBMITTED) {
                $paper->setStatus(Episciences_Paper::STATUS_OK_FOR_REVIEWING);
                $paper->save();
                $paper->log(Episciences_Paper_Logger::CODE_STATUS, null, ['status' => Episciences_Paper::STATUS_OK_FOR_REVIEWING]);

            } else if ($paper->getStatus() === Episciences_Paper::STATUS_REVIEWED) {
                $paper->setStatus(Episciences_Paper::STATUS_BEING_REVIEWED);
                $paper->save();
                $paper->log(Episciences_Paper_Logger::CODE_STATUS, null, ['status' => Episciences_Paper::STATUS_BEING_REVIEWED]);

            }

            $report = $this->createRatingReport($paper, $reviewerUid);
        }
        return $report;
    }

    /**
     * @param Zend_Controller_Request_Http $request
     * @param Episciences_Paper $paper
     * @param Episciences_Rating_Report $report
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     * @throws Zend_Mail_Exception
     */
    private function ratingProcessing(Zend_Controller_Request_Http $request, Episciences_Paper $paper, Episciences_Rating_Report $report): void
    {
        if ($paper->isEditable() && !$report->isCompleted()) {

            if ($paper->isRevisionRequested()) {
                $message = $this->view->translate("Cet article est en cours de révision, il n'est plus nécessaire de le relire.");
                $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
                $this->_helper->redirector(self::RATINGS_ACTION, self::CONTROLLER_NAME, null, [PREFIX_ROUTE => RVCODE]);
                return;
            }

            $rating_form = Episciences_Rating_Manager::getRatingForm($report);
            if ($rating_form) {
                $this->view->rating_form = $rating_form;
                if ($request->getPost('submitRatingForm') !== null || $request->getPost('validateRating') !== null) {
                    if ($rating_form->isValid($request->getPost())) {
                        $this->save_rating($report, $paper);
                    } else {
                        $message = $this->view->translate("Ce formulaire comporte des erreurs.");
                        $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
                        $this->view->rating_form = $rating_form; // remove this ?
                    }
                }
            }
        }
    }

    /**
     * save reviewer rating report
     * @param Episciences_Rating_Report $report
     * @param Episciences_Paper $paper
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    private function save_rating(Episciences_Rating_Report $report, Episciences_Paper $paper): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        // upload attached files
        $uploads = Episciences_Tools::uploadFiles($report->getPath(), [
            $report->getAttachments(),
            'file_unique_id' => uniqid('Report_', false),
        ]);

        // process rating form and update rating grid
        $report->populate(array_merge($request->getPost(), $uploads));

        if ($report->save()) {
            $message = $this->view->translate("Votre évaluation a bien été enregistrée.");
            $this->_helper->FlashMessenger->setNamespace(self::SUCCESS)->addMessage($message);
            $this->completedRatingSendNotification($report, $paper);
        } else {
            trigger_error('Error: failed to save review of docid ' . $report->getDocid(), E_USER_WARNING);
            $message = $this->view->translate("Votre évaluation n'a pas pu être enregistrée.");
            $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
        }

        if (Episciences_Auth::isAllowedToUploadPaperReport() || $paper->getEditor(Episciences_Auth::getUid())) {
            $this->_helper->redirector->gotoUrl($this->url(['controller' => 'administratepaper', 'action' => 'view', 'id' => $paper->getDocid()]));
        } else {
            // show the usual reviewer all his reviews
            $this->_helper->redirector(self::RATINGS_ACTION, self::CONTROLLER_NAME, null, [PREFIX_ROUTE => RVCODE]);
        }

    }

    /**
     * @param Episciences_Rating_Report $report
     * @param Episciences_Paper $paper
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    private function completedRatingSendNotification(Episciences_Rating_Report $report, Episciences_Paper $paper): void
    {
        if (null !== $report->getOnbehalf_uid() && $report->getUid() !== Episciences_Auth::getUid()) { // Si la relecture est faite à la place d'un reviewer
            $user = new Episciences_User();
            $user->findWithCAS($report->getUid());

        } else { // reveiwer ou isAllowedToUploadPaperReport
            $user = Episciences_Auth::getInstance()->getIdentity();
        }

        // log report

        $this->logReport($user, $paper, $report);

        // update paper status
        $paper->ratingRefreshPaperStatus();

        // if report is completed, send mails to reviewer and editors
        if ($report->isCompleted()) {
            $locale = $user->getLangueid();

            $commonTags = [
                Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocid(),
                Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid()
            ];

            // send mail to reviewer *********************
            // url to rating page
            $paper_url = $this->view->url([self::CONTROLLER => self::CONTROLLER_NAME, self::ACTION => self::RATING_ACTION, 'id' => $report->getDocid()]);
            $paper_url = 'https://' . $_SERVER [self::SERVER_NAME_STR] . $paper_url;

            $reviewerTags = $commonTags + [
                    Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => $user->getUsername(),
                    Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => $user->getScreenName(),
                    Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $user->getFullName(),
                    Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($locale, true),
                    Episciences_Mail_Tags::TAG_SUBMISSION_DATE => $this->view->Date($paper->getSubmission_date(), $locale),
                    Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata($locale),
                    Episciences_Mail_Tags::TAG_PAPER_URL => $paper_url
                ];

            Episciences_Mail_Send::sendMailFromReview($user, Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWED_REVIEWER_COPY, $reviewerTags, $paper, Episciences_Auth::getUid(), [], false, [], null);

            // send mail to editors + notify chief editors, administrators and secretaries *********************
            $recipients = $paper->getEditors(true, true);
            Episciences_Review::checkReviewNotifications($recipients);

            // [RT#82641]
            Episciences_PapersManager::keepOnlyUsersWithoutConflict($paper->getPaperid(), $recipients);

            // url to paper administration page
            $paper_url = $this->adminPaperUrl((int)$report->getDocid());
            #git 295 : FYI
            $CC = $paper->extractCCRecipients($recipients);

            if (empty($recipients)) {
                $recipients = $CC;
                $CC = [];
            }

            /** @var Episciences_User $recipient */
            foreach ($recipients as $recipient) {

                if ($report->getUid() === $recipient->getUid()) {
                    continue;
                }

                $locale = $recipient->getLangueid();

                // rating display
                $partial = new Zend_View();
                $partial->locale = $locale;
                $partial->report = $report;
                $partial->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');
                $ratingDisplay = $partial->render('partials/paper_report_mail_version.phtml');
                $ratingDisplay = str_replace(chr(13) . chr(10), '', $ratingDisplay);
                //$ratingDisplay = Ccsd_Tools::clear_nl(Ccsd_Tools::br2space($ratingDisplay)); test #462

                $editorTags = $commonTags + [
                        Episciences_Mail_Tags::TAG_REVIEWER_FULLNAME => $user->getScreenName(),
                        Episciences_Mail_Tags::TAG_PAPER_RATING => $ratingDisplay,
                        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => $recipient->getUsername(),
                        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => $recipient->getScreenName(),
                        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $recipient->getFullName(),
                        Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($locale, true),
                        Episciences_Mail_Tags::TAG_SUBMISSION_DATE => $this->view->Date($paper->getSubmission_date(), $locale),
                        Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata($locale),
                        Episciences_Mail_Tags::TAG_PAPER_URL => $paper_url
                    ];

                $attachments = [];

                // add attachments to mail
                /** @var Episciences_Rating_Criterion $criterion */
                foreach ($report->getAttachments() as $fileName) {
                    $path = $report->getPath();
                    $attachments[$fileName] = $path;
                }

                Episciences_Mail_Send::sendMailFromReview($recipient, Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWED_EDITOR_COPY, $editorTags, $paper, Episciences_Auth::getUid(), $attachments, true, $CC, null);
                // reset CC
                $CC = [];
            }
        }

    }

    /**
     * @param Episciences_User $user
     * @param Episciences_Paper $paper
     * @param Episciences_Rating_Report $report
     * @throws Zend_Db_Adapter_Exception
     */
    private function logReport(Episciences_User $user, Episciences_Paper $paper, Episciences_Rating_Report $report): void
    {
        $paper->log(
            ($report->isCompleted()) ? Episciences_Paper_Logger::CODE_REVIEWING_COMPLETED : Episciences_Paper_Logger::CODE_REVIEWING_IN_PROGRESS,
            Episciences_Auth::getUid(),
            ['user' => $user->toArray(),
                self::RATING_ACTION => $report->toArray()]);

    }

    /**
     * Supprimer le fichier joint à un rapport de relecture
     * @throws Zend_Db_Statement_Exception
     */
    public function deleteattachmentreportAction(): void
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docid = (int)$request->getParam(self::DOC_ID_STR);
        $itemId = $request->getParam('cid');
        $uid = (int)$request->getParam('uid'); // Reviewer UID
        $file = $request->getParam('file');

        if (!$docid || !$uid) {
            $this->_helper->redirector(self::RATINGS_ACTION, self::CONTROLLER_NAME, null, [PREFIX_ROUTE => RVCODE]);
            return;
        }

        $paper = Episciences_PapersManager::get($docid);

        if (!$paper) {
            $this->_helper->redirector(self::RATINGS_ACTION, self::CONTROLLER_NAME, null, [PREFIX_ROUTE => RVCODE]);
            return;
        }

        if (Episciences_Auth::isAllowedToUploadPaperReport() || $paper->getEditor(Episciences_Auth::getUid()) || Episciences_Auth::getUid() == $uid) {
            $report = Episciences_Rating_Report::find($docid, $uid);
            $report_path = $report->getPath() . $file;
            if ($file && is_file($report_path) && !$report->isCompleted()) {
                unlink($report_path);
                // update XML report file
                $id = substr($itemId, -1);
                /** @var Episciences_Rating_Criterion $criterion */
                $criterion = $report->getCriterion($id);
                $criterion->setAttachment('');
                $report->save();
                $message = $this->view->translate("Le fichier a bien été supprimé.");
                $this->_helper->FlashMessenger->setNamespace(self::SUCCESS)->addMessage($message);
            } else {
                $message = $this->view->translate("Erreur lors de la suppression du fichier.");
                $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
            }

        } else {
            $message = $this->view->translate("Vous n'avez pas les autorisations nécessaires pour supprimer ce fichier.");
            $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
        }

        $this->redirect($this->url(['controller' => 'paper', 'action' => 'rating', 'id' => $docid, 'reviewer_uid' => $uid]));
    }

    /**
     * remove contributor paper (done by the contributor himself)
     * @throws JsonException
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     */
    public function removeAction(): void
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getParam('id');

        // check paper status before removal
        $authorizedStatus = [Episciences_Paper::STATUS_SUBMITTED];

        $paper = Episciences_PapersManager::get($docId, false);

        if ($paper) {

            if (!in_array($paper->getStatus(), $authorizedStatus, true)) {
                $message = $this->view->translate("L'article ne peut pas être supprimé en raison de son statut.");
                $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
                $this->_helper->redirector->gotoUrl($this->url(['controller' => 'index', 'id' => $docId]));
                return;
            }

            // check that user really is paper contributor
            if (!$paper->isOwner()) {
                $message = $this->view->translate("L'article ne peut être supprimé que par son déposant.");
                $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
                $this->_helper->redirector->gotoUrl($this->url(['controller' => 'index', 'id' =>  $docId]));
                return;
            }

            // remove paper (update its status)
            $paper->setStatus(Episciences_Paper::STATUS_DELETED);
            $paper->setPassword();
            $paper->save();

            // delete all paper datasets
            Episciences_Paper_DatasetsManager::deleteByDocIdAndRepoId($paper->getDocid(), $paper->getRepoid());
            // delete all paper files
            Episciences_Paper_FilesManager::deleteByDocId($paper->getDocid());
            // delete licences
            Episciences_Paper_LicenceManager::deleteLicenceByDocId($paper->getDocid());
            //delete authors
            Episciences_Paper_AuthorsManager::deleteAuthorsByPaperId($paper->getPaperid());


            // if reviewers were assigned, remove them
            $reviewers = $paper->getReviewers(null, true);
            foreach (array_keys($reviewers) as $uid) {
                $paper->unassign($uid, Episciences_User_Assignment::ROLE_REVIEWER);
            }

            // if there were editors, remove them
            $editors = $paper->getEditors(null, true);
            foreach (array_keys($editors) as $uid) {
                $paper->unassign($uid, Episciences_User_Assignment::ROLE_EDITOR);
            }

            // success message ***************************************************************
            $message = $this->view->translate("L'article a bien été supprimé.");
            $this->_helper->FlashMessenger->setNamespace(self::SUCCESS)->addMessage($message);

            // Contributor info
            /** @var  $contributor Episciences_User */
            $contributor = Episciences_Auth::getUser();
            $aLocale = $contributor->getLangueid();

            $commonTags = [
                Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocId(),
                Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME => $contributor->getFullName()
            ];

            $authorTags = $commonTags + [
                    Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($aLocale, true),
                    Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata($aLocale),
                    Episciences_Mail_Tags::TAG_SUBMISSION_DATE => $this->view->Date($paper->getSubmission_date(), $aLocale)
                ];

            // send mail to contributor - successful removal ***********************************

            Episciences_Mail_Send::sendMailFromReview($contributor, Episciences_Mail_TemplatesManager::TYPE_PAPER_DELETED_AUTHOR_COPY, $authorTags, $paper);

            // send mail to editors - warn paper editors that the paper was removed *************************
            // En fonction des paramètres de la revue, notifier les administrateurs, rédacteurs en chef et les secrétaires de rédaction
            $this->paperStatusChangedNotifyManagers($paper, Episciences_Mail_TemplatesManager::TYPE_PAPER_DELETED_EDITOR_COPY, null, $commonTags);

            // send mail to reviewers (if any) **********************************************
            foreach ($reviewers as $reviewer) {
                $rLocale = $reviewer->getLangueid();
                $rTags = [
                    Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($rLocale, true),
                    Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata($rLocale),
                    Episciences_Mail_Tags::TAG_SUBMISSION_DATE => $this->view->Date($paper->getSubmission_date(), $rLocale)
                ];

                Episciences_Mail_Send::sendMailFromReview($reviewer, Episciences_Mail_TemplatesManager::TYPE_PAPER_DELETED_REVIEWER_COPY, $commonTags + $rTags, $paper);
            }

        }

        // redirect *******************************
        $this->_helper->redirector->gotoUrl($this->url(['controller' => 'paper', 'action' => 'submitted']));
    }

    /**
     * Retourne l'ID de l'article dans sa dernière version
     * @throws Zend_Exception
     */
    public function ajaxgetlastpaperidAction(): void
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ($request->isXmlHttpRequest()) {

            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender();

            $result = [];
            $message = self::MSG_PAPER_DOES_NOT_EXIST;
            $result[self::DOC_ID_STR] = 0;
            $lastPaper = false;

            $docId = (int)$request->getPost('id');
            $from = $request->getPost('from');


            if (Episciences_Auth::isAllowedToManagePaper()) {
                $controller = self::ADMINISTRATE_PAPER_CONTROLLER;
            } else {
                $controller = self::CONTROLLER_NAME;
            }

            $result[self::CONTROLLER] = $controller;

            try {
                $paper = Episciences_PapersManager::get($docId);

                if ($paper && $paper->getRvid() === RVID) {

                    if ($from === 'my_submissions' && $paper->getUid() !== Episciences_Auth::getUid()) {
                        $message = "Vous n'êtes pas l'auteur de cet article";

                    } else if ($from === 'assigned_articles' && !$paper->getEditor(Episciences_Auth::getUid())) {
                        $message = "Vous n'êtes pas assigné à cet article";

                    } else {
                        $lastPaper = Episciences_PapersManager::getLastPaper($paper->getPaperid(), true);
                    }
                }

            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
                $message = "Une erreur interne s'est produite, veuillez recommencer.";
            }

            if (!$lastPaper) {
                $result[self::ERROR] = Zend_Registry::get('Zend_Translate')->translate($message);
            } else {
                $result[self::DOC_ID_STR] = $lastPaper->getDocid();
            }

            try {
                echo json_encode($result, JSON_THROW_ON_ERROR);

            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
            }
        }
    }

    /**
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    public function continuepublicationprocessAction(): void
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $this->_helper->layout->disableLayout();

        $errors = [];
        $message = '';
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $doAction = (bool)$request->getPost('doaction'); // 1 ==> true
        // L'id du document
        $docId = $request->get('docid');

        $paper = Episciences_PapersManager::get($docId);

        if (!$paper) {
            $errors[] = $translator->translate("Impossible de trouver l'article");
        }

        if ($request->getPost('docid') && $doAction) {// confirmation de l'action

            // Initialisation
            $lastStatus = $paper->getStatus();

            try {
                /** @var stdClass $actionDetail */
                $actionDetail = $paper->loadLastAbandonActionDetail();
                $lastStatus = $actionDetail->lastStatus;

                /* Pour des raisons de cohérence,
                 *l’abandon du processus de publication entraînera la suppression de toutes
                 *les invitations en cours et les relectures inachevées.
                 * Pour cela, un artcile dans l'un des états ci-dessous est rénitialisé à l'état SOUMIS
                */
                $ignoreStatus = [Episciences_Paper::STATUS_OK_FOR_REVIEWING, Episciences_Paper::STATUS_BEING_REVIEWED];

                $lastStatus = !in_array($lastStatus, $ignoreStatus, true) ? $lastStatus : Episciences_Paper::STATUS_SUBMITTED;

                // Logger l'action
                $logAction = $paper->log(
                    Episciences_Paper_Logger::CODE_CONTINUE_PUBLICATION_PROCESS,
                    Episciences_Auth::getUid(),
                    [
                        'lastStatus' => $lastStatus
                    ]
                );

            } catch (Zend_Exception $e) {
                $logAction = false;
                Ccsd_Log::message($e->getMessage(), false, Zend_Log::WARN, EPISCIENCES_EXCEPTIONS_LOG_PATH . RVCODE . '.publication-process');
            }

            if (!$logAction) {

                $error = mb_strtoupper(Episciences_Paper_Logger::CODE_CONTINUE_PUBLICATION_PROCESS);
                $error .= '_ERROR';
                $error .= '<br>';
                $error .= $translator->translate("Une erreur s'est produite pendant l'enregistrement de vos modifications.");

                echo json_encode(['error' => $error]);

            } else {
                $this->continuePublication($paper, $lastStatus);
                echo json_encode(true);
            }

            exit(0);
        }

        $messagePanel = "Vous êtes sur le point de reprendre le processus de publication de cet article.<br><span class='alert-info'>L'auteur a bien été informé qu'il ne pourra plus reprendre le processus de publication de son article et que cette décision est définitive.</span>";

        $this->view->message = $this->buildAlertMessage($message, $messagePanel);
        $this->view->errors = $errors;
        $this->view->docid = !$paper ? $docId : $paper->getDocid();

        $this->render('abandonpublicationprocess');
    }

    /**
     * @param Episciences_Paper $paper
     * @param int $lastStatus
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    private function continuePublication(Episciences_Paper $paper, int $lastStatus): void
    {
        $recipients = [];
        // Changer le status de l'article
        $paper
            ->setStatus($lastStatus)
            ->save();
        $paper->log(Episciences_Paper_Logger::CODE_RESTORATION_OF_STATUS, Episciences_Auth::getUid(), ['status' => $paper->getStatus()]);

        // Mail à l'auteur
        $author = new Episciences_User;
        $author->findWithCAS($paper->getUid());

        $this->informRecipient($author, $paper, Episciences_Mail_TemplatesManager::TYPE_PAPER_CONTINUE_PUBLICATION_AUTHOR_COPY);

        $recipients = $this->getAllEditors($paper);
        Episciences_Review::checkReviewNotifications($recipients);
        Episciences_PapersManager::keepOnlyUsersWithoutConflict($paper->getPaperid(), $recipients);

        $CC = $paper->extractCCRecipients($recipients);

        if (empty($recipients)) {
            $recipients = $CC;
            $CC = [];
        }

        /** @var Episciences_User $editor */
        foreach ($recipients as $recipient) {
            $this->informRecipient($recipient, $paper, Episciences_Mail_TemplatesManager::TYPE_PAPER_CONTINUE_PUBLICATION_EDITOR_COPY, $lastStatus, $CC);
            //reset $CC
            $CC = [];
        }
    }

    /**
     * @param Episciences_User $recipient
     * @param Episciences_Paper $paper
     * @param string $templateType
     * @param int $lastStatus
     * @param array $CC
     * @return bool
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    private function informRecipient(Episciences_User $recipient, Episciences_Paper $paper, string $templateType, int $lastStatus = 0, array $CC = []): bool
    {

        if ($recipient->getUid() === $paper->getUid()) { // send mail to author
            return $this->informContributor($paper, $templateType);
        }

        $translator = Zend_Registry::get('Zend_Translate');
        $locale = $recipient->getLangueid();
        $docId = $paper->getDocid();

        $tags = [
            Episciences_Mail_Tags::TAG_PAPER_URL => $this->adminPaperUrl($docId),
            Episciences_Mail_Tags::TAG_ARTICLE_ID => $docId,
            Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
            Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($locale, true),
            Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata($locale),
            Episciences_Mail_Tags::TAG_SUBMISSION_DATE => Episciences_View_Helper_Date::Date($paper->getSubmission_date(), $locale),
            Episciences_Mail_Tags::TAG_ACTION_DATE => Episciences_View_Helper_Date::Date(Zend_Date::now()->toString('dd-MM-yyy'), $locale),
            Episciences_Mail_Tags::TAG_ACTION_TIME => Zend_Date::now()->get(Zend_Date::TIME_MEDIUM),
        ];

        $lastStatusLabel =
            in_array($lastStatus, Episciences_Paper::STATUS_CODES) ?
                $translator->translate(Episciences_Paper::$_statusLabel[$lastStatus]) :
                $translator->translate('undefined_status');

        $tags[Episciences_Mail_Tags::TAG_LAST_STATUS] = $lastStatusLabel;

        return Episciences_Mail_Send::sendMailFromReview($recipient, $templateType, $tags, $paper, null, [], false, $CC);
    }

    /**
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    public function abandonpublicationprocessAction(): void
    {

        $translator = Zend_Registry::get('Zend_Translate');

        $this->_helper->layout->disableLayout();

        $errors = [];
        $message = '';
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $doAction = (bool)$request->getPost('doaction'); // 1 ==> true
        // L'id du document
        $docId = (!$doAction) ? $request->get('docid') : $request->getPost('docid');

        $paper = Episciences_PapersManager::get($docId);

        if (!$paper) {
            $errors[] = $translator->translate("Impossible de trouver l'article");
        }

        if ($request->getPost('docid') && $doAction) {// confirmation de l'action

            $lastStatus = (int)$paper->getStatus();

            // Logger l'action
            $logAction = $paper->log(
                Episciences_Paper_Logger::CODE_ABANDON_PUBLICATION_PROCESS,
                Episciences_Auth::getUid(),
                [
                    'lastStatus' => $lastStatus
                ]
            );

            if (!$logAction) {

                $error = mb_strtoupper(Episciences_Paper_Logger::CODE_ABANDON_PUBLICATION_PROCESS);
                $error .= '_ERROR';
                $error .= '<br>';
                $error .= $translator->translate("Une erreur s'est produite pendant l'enregistrement de vos modifications.");

                echo json_encode(['error' => $error]);

            } else {

                $this->applyAbandon($paper, $lastStatus);
                echo json_encode(true);
            }

            exit(0);

        }

        // Message de confirmation
        if ($paper->getUid() === Episciences_Auth::getUid()) {
            $messagePanel = $translator->translate("Attention, si vous décidez de poursuivre l'abandon, il ne vous sera plus possible de soumettre cet article dans cette revue. L'abandon est définitif.");
        } else {
            $messagePanel = $translator->translate("Vous êtes sur le point d’abandonner le processus de publication de cet article.");
        }

        $this->view->message = $this->buildAlertMessage($message, $messagePanel);
        $this->view->errors = $errors;
        $this->view->docid = !$paper ? $docId : $paper->getDocid();
    }

    /**
     * @param Episciences_Paper $paper
     * @param int $lastStatus
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    private function applyAbandon(Episciences_Paper $paper, int $lastStatus): void
    {
        // with cas data
        $recipients = $this->getAllEditors($paper) + $this->getAllCopyEditors($paper);

        $editorsTemplateKey = !empty($recipients) ?
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ABANDON_PUBLICATION_EDITOR_COPY :
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ABANDON_PUBLICATION_NO_ASSIGNED_EDITORS;

        $authorTemplateKey = ($paper->getUid() === Episciences_Auth::getUid()) ?
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ABANDON_PUBLICATION_BY_AUTHOR_AUTHOR_COPY :
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ABANDON_PUBLICATION_AUTHOR_COPY;

        $invitationsStatus = [Episciences_User_Assignment::STATUS_PENDING, Episciences_User_Assignment::STATUS_ACTIVE];

        $this->removeInvitations($paper, $invitationsStatus);

        // Changer le status de l'article
        $paper
            ->setStatus(Episciences_Paper::STATUS_ABANDONED)
            ->save();
        $paper->log(Episciences_Paper_Logger::CODE_STATUS, Episciences_Auth::getUid(), ['status' => $paper->getStatus()]);

        // Mail à l'auteur
        $author = new Episciences_User;
        $author->findWithCAS($paper->getUid());

        $this->informRecipient($author, $paper, $authorTemplateKey);

        Episciences_Review::checkReviewNotifications($recipients);
        Episciences_PapersManager::keepOnlyUsersWithoutConflict($paper->getPaperid(), $recipients);

        $CC = $paper->extractCCRecipients($recipients);

        if (empty($recipients)) {
            $recipients = $CC;
            $CC = [];
        }

        /** @var Episciences_User $recipient */
        foreach ($recipients as $recipient) {
            $this->informRecipient($recipient, $paper, $editorsTemplateKey, $lastStatus, $CC);
            //reset $CC
            $CC = [];
        }
    }

    /**
     * Annule les invitations
     * @param Episciences_Paper $paper
     * @param array $invitationsStatus
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    private function removeInvitations(Episciences_Paper $paper, array $invitationsStatus): void
    {
        /** @var $invitationsByStatus [] */
        $invitationsByStatus = $paper->getInvitations($invitationsStatus, true);

        // La page de l'article
        $paperUrl = $this->view->url([self::CONTROLLER => self::CONTROLLER_NAME, self::ACTION => 'view', 'id' => $paper->getDocid()]);
        $paperUrl = SERVER_PROTOCOL . '://' . $_SERVER['SERVER_NAME'] . $paperUrl;

        /** @var  $invitations [] */
        foreach ($invitationsByStatus as $invitations) {
            foreach ($invitations as $invitation) {
                /** @var Episciences_User_Assignment $assignment */
                $assignment = Episciences_User_AssignmentsManager::findById($invitation['ASSIGNMENT_ID']);
                /** @var Episciences_User $user */
                $user = $assignment->getAssignedUser();

                if (!$user) {
                    trigger_error('Erreur: Impossible de trouver le relecteur ( UID = ' . $assignment->getUid() . ' )', E_USER_ERROR);
                } else if ($this->applyRemoving($paper, $assignment, $user)) {

                    $locale = $user->getLangueid();

                    $tags = [
                        Episciences_Mail_Tags::TAG_PAPER_URL => $paperUrl,
                        Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocId(),
                        Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($locale, true),
                        Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata($locale),
                        Episciences_Mail_Tags::TAG_SUBMISSION_DATE => Episciences_View_Helper_Date::Date($paper->getSubmission_date(), $locale),
                        Episciences_Mail_Tags::TAG_ACTION_DATE => Episciences_View_Helper_Date::Date(Zend_Date::now()->toString('dd-MM-yyy'), $locale),
                        Episciences_Mail_Tags::TAG_ACTION_TIME => Zend_Date::now()->get(Zend_Date::TIME_MEDIUM)
                    ];

                    // Envoi de mail au relecteur
                    Episciences_Mail_Send::sendMailFromReview($user, Episciences_Mail_TemplatesManager::TYPE_PAPER_ABANDON_PUBLICATION_REVIEWER_REMOVAL, $tags, $paper);
                }
            }
        }
    }

    /**
     * @param Episciences_Paper $paper
     * @param Episciences_User_Assignment $assignment
     * @param Episciences_User $reviewer
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    private function applyRemoving(Episciences_Paper $paper, Episciences_User_Assignment $assignment, Episciences_User $reviewer): bool
    {
        $isRemoved = false;

        // Pour les comptes temporaires aussi
        $reviewingStatus = 0;

        if ($reviewer->isReviewer()) {
            /** @var Episciences_Reviewer $reviewer */
            /** @var Episciences_Reviewer_Reviewing $reviewing */
            $reviewing = $reviewer->getReviewing($paper->getDocid());
            $reviewingStatus = $reviewing->getStatus();
        }

        // get invitation
        /** @var Episciences_User_Invitation $invitation */
        $invitation = Episciences_User_InvitationsManager::findById($assignment->getInvitation_id());

        if ($reviewingStatus !== Episciences_Reviewer_Reviewing::STATUS_COMPLETE) { // On garde les relectures déjà terminées

            //Mettre à jour l'invitati]on
            $invitation->setStatus($invitation::STATUS_CANCELLED);
            $invitation->save();

            //Mettre à jour l'assignation
            $params = [
                'itemid' => $assignment->getItemid(),
                'item' => Episciences_User_Assignment::ITEM_PAPER,
                'roleid' => Episciences_User_Assignment::ROLE_REVIEWER,
                'status' => Episciences_User_Assignment::STATUS_CANCELLED,
                'tmp_user' => $assignment->isTmp_user()
            ];

            /** @var Episciences_User_Assignment $nAssignment */
            $nAssignment = Episciences_UsersManager::unassign($assignment->getUid(), $params)[0];
            $nAssignment->setInvitation_id($invitation->getId());
            $nAssignment->save();

            // logger la suppression de relecteur

            $log = $paper->log(
                Episciences_Paper_Logger::CODE_REVIEWER_UNASSIGNMENT,
                null,
                ['aid' => $nAssignment->getId(),
                    'invitation_id' => $invitation->getId(),
                    'tmp_user' => $nAssignment->isTmp_user(),
                    'uid' => $nAssignment->getUid(),
                    'user' => $reviewer->toArray()
                ]
            );


            if (!$log) {
                $msg = 'Le log de la suppression du relecteur ( UID = ';
                $msg .= $nAssignment->getUid();
                $msg .= ')';
                $msg .= ' n\'a pas pu être enregistré pour l\'article( DOCID = ';
                $msg .= $paper->getDocid();
                $msg .= ' )';
                trigger_error($msg, E_USER_WARNING);
            }

            $isRemoved = true;
        }

        return $isRemoved;
    }

    /**
     * Met à jour les métadonnées d'un article
     */
    public function updaterecorddataAction(): void
    {

        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $request = $this->getRequest();

        if ($request) {

            $docId = (int)$request->getPost('docid');
            $result = 0;

            try {
                $paper = Episciences_PapersManager::get($docId);
                $result = Episciences_PapersManager::updateRecordData($paper);

                if ($result !== 0) {
                    $message = "Les métadonnées de cet article ont bien été mises à jour.";
                } else {
                    $message = 'Les métadonnées de cet article sont à jour.';
                }

                // update index and notify even if nothing changed &
                $this->indexAndCOARNotify($paper);

            } catch (Exception $e) {
                $message = "Une erreur interne s'est produite, veuillez recommencer.";
                $jsonResult['error'] = $e->getMessage();
            }

            $message = $this->view->translate($message);

            $jsonResult['affectedRows'] = $result;
            $jsonResult['message'] = $message;

            echo json_encode($jsonResult);

        }

    }

    /**
     * @throws Zend_Form_Exception
     * @throws Zend_Json_Exception
     */
    public function contactrequestAction(): void
    {
        $this->_helper->layout->disableLayout();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $id = $request->getParam('id');
        $oComment = new Episciences_Comment;
        $oComment->find($id);
        $form = Episciences_CommentsManager::answerRevisionForm('contactRequest');
        $form->setAction($this->url(['controller' => 'paper', 'action' => 'saveanswer', 'docid' => $oComment->getDocid(), 'pcid' => $oComment->getPcid()]));
        $form->addElement('hidden', 'type', [
            'id' => 'hidden-id-' . $id,
            'value' => Episciences_CommentsManager::TYPE_REVISION_CONTACT_COMMENT
        ]);
        $this->view->form = $form;
        $this->view->comment = $oComment->toArray();
        $this->render('answerrequest');
    }

    public function cslAction()
    {

        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $request = $this->getRequest();
        $params = $request->getParams();
        if (isset($params['id'])) {
            echo \Episciences\Paper\Export::getCsl($params['id']);

        }

        header('Content-Type: application/json; charset=UTF-8');
        exit();
    }
}

