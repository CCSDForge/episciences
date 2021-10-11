<?php
require_once APPLICATION_PATH . '/modules/common/controllers/DefaultController.php';

class PaperDefaultController extends DefaultController
{
    const MSG_PAPER_DOES_NOT_EXIST = "Le document demande n’existe pas.";
    const MSG_REVIEWER_DOES_NOT_EXIST = "Le relecteur pour lequel vous souhaitez relire n'existe pas.";
    const MSG_REPORT_COMPLETED = "Votre rapport a été déjà renseigné.";
    const ERROR = 'error';
    const WARNING = 'warning';
    const SUCCESS = 'success';
    const AND_PC_ID_STR = '&pcid=';
    const CONTROLLER = 'controller';
    const ACTION = 'action';
    const NEW_VERSION_TYPE = 'new_version';
    const TMP_VERSION_TYPE = 'tmp_version';
    const ADMINISTRATE_PAPER_CONTROLLER = 'administratepaper';
    const PUBLIC_PAPER_CONTROLLER = 'paper';
    const TEMPLATE_EXTENSION = '.phtml';
    const ENCODING_TYPE = 'UTF-8';
    const COMMENTS_STR = '/comments/';
    const RATINGS_ACTION = 'ratings';
    const RATING_ACTION = 'rating';
    const PAPER_URL_STR = 'paper/view?id=';
    const STATUS = 'status';
    const SERVER_NAME_STR = 'SERVER_NAME';
    const DOC_ID_STR = 'docid';
    const VERSION_STR = 'version';
    const COMMENT_STR = 'comment';
    const TYPES_STR = 'types';
    const DEADLINE_STR = 'deadline';
    const ORDER_STR = 'order';
    const SECTIONS_STR = 'sections';
    const VOLUMES_STR = 'volumes';
    const SEARCH_DOC_STR = 'search_doc';
    const CLASS_EPI_USER_NAME = 'Episciences_User';
    const CONTROLLER_NAME = 'paper';
    // Copy editing
    const CE_REQUEST_REPLY_ARRAY = [
        Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST => Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_ANSWER,
        Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST => Episciences_CommentsManager::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER
    ];

    /**
     * retourne tous les rédacteurs d'un article, excepté l'auteur de l'article
     * @param Episciences_Paper $paper
     * @param bool $strict
     * @param bool $withCasData
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    protected function getAllEditors(Episciences_Paper $paper, bool $strict = false, bool $withCasData = true): array
    {

        // Rédacteurs assignés
        /** @var Episciences_Editor $assignedEditors */
        $editors = Episciences_PapersManager::getEditors($paper->getDocid(), true, $withCasData);
        $editors = !$editors ? [] : $editors;

        if ($strict) { // add chief editors
            $editors += Episciences_Review::getChiefEditors();
        }

        // Auteur de l'article
        if (array_key_exists($paper->getUid(), $editors)) {
            unset($editors[$paper->getUid()]);
        }

        return $editors;
    }

    /**
     * @param Episciences_User $submitter
     * @param Episciences_Paper $paper
     * @param string $subject
     * @param string $message
     * @param array $data
     * @param array $tags
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    protected function sendMailFromModal(Episciences_User $submitter, Episciences_Paper $paper, string $subject, string $message, array $data, array $tags = [])
    {

        $mail = new Episciences_Mail('UTF-8');
        $mail->setDocid($paper->getDocid());

        foreach ($tags as $tag => $value) {
            if (!array_key_exists($tag, $mail->getTags())) {
                $mail->addTag($tag, $value);
            }
        }

        $mail->setTo($submitter);
        $mail->setSubject($subject);
        $mail->setRawBody(Ccsd_Tools::clear_nl($message));
        if (isset($data['attachments'])) {
            $path = REVIEW_FILES_PATH . 'attachments/';
            $attachments = Episciences_Tools::arrayFilterAttachments($data['attachments']);
            foreach ($attachments as $attachment) {
                $filepath = $path . $attachment;
                if (file_exists($filepath)) {
                    $mail->addAttachedFile($filepath);
                }
            }
        }

        // Other reciptients
        $cc = (!empty($data['cc'])) ? explode(';', $data['cc']) : array();
        $bcc = (!empty($data['bcc'])) ? explode(';', $data['bcc']) : array();
        $this->addOtherRecipients($mail, $cc, $bcc);
        $mail->writeMail();

        // log mail sending
        $paper->log(
            Episciences_Paper_Logger::CODE_MAIL_SENT,
            Episciences_Auth::getUid(),
            ['id' => $mail->getId(), 'mail' => $mail->toArray()]);
    }

    /**
     * @param Episciences_Mail $mail
     * @param array $cc
     * @param array $bcc
     * @return Episciences_Mail
     */

    protected function addOtherRecipients(Episciences_Mail $mail, array $cc, array $bcc)
    {
        $validator = new Zend_Validate_EmailAddress();
        if (is_array($cc) && !empty($cc)) {
            foreach ($cc as $recipient) {
                $recipient = trim($recipient);
                $recipient = Episciences_Tools::postMailValidation($recipient)['email'];
                if ($validator->isValid($recipient)) {
                    $mail->addCc($recipient);
                } else {
                    error_log(RVCODE . 'FROM_MODAL_BAD_CC_MAIL: ' . $recipient);
                }
            }
        }

        if (is_array($bcc) && !empty($bcc)) {
            foreach ($bcc as $recipient) {
                $recipient = trim($recipient);
                $recipient = Episciences_Tools::postMailValidation($recipient)['email'];
                if ($validator->isValid($recipient)) {
                    $mail->addBcc($recipient);
                } else {
                    error_log(RVCODE . 'FROM_MODAL__BAD_BCC_MAIL: ' . $recipient);
                }
            }
        }
        return $mail;
    }

    /**
     * @param array $paperManagers
     * @param Episciences_Paper $newPaper
     * @param string $roleId
     * @return array
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     */
    protected function reassignPaperManagers(array $paperManagers, Episciences_Paper $newPaper, string $roleId = Episciences_User_Assignment::ROLE_EDITOR)
    {
        //default action  => roleId = 'editor'
        $action = Episciences_Paper_Logger::CODE_EDITOR_ASSIGNMENT;

        if ($roleId == Episciences_User_Assignment::ROLE_COPY_EDITOR) {
            $action = Episciences_Paper_Logger::CODE_COPY_EDITOR_ASSIGNMENT;
        }

        // loop through each user
        /** @var Episciences_User $manager */
        foreach ($paperManagers as $manager) {

            // assign user to new version
            $aid = $newPaper->assign($manager->getUid(), $roleId, Episciences_User_Assignment::STATUS_ACTIVE);
            // log assignment
            $newPaper->log($action, null, ["aid" => $aid, "user" => $manager->toArray()]);

        }
        return $paperManagers;
    }

    /**
     * @param Episciences_User $manager
     * @param Episciences_Paper $paper1
     * @param Episciences_Paper $newPaper
     * @param Episciences_Comment $requestComment
     * @param Episciences_Comment $answerComment
     * @param bool $makeCopy
     * @param array $tags
     * @param array $CC
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     */

    protected function answerRevisionNotifyManager(
        Episciences_User $manager,
        Episciences_Paper $paper1,
        Episciences_Paper $newPaper,
        Episciences_Comment $requestComment,
        Episciences_Comment $answerComment,
        bool $makeCopy = false,
        array $tags = [],
        array $CC = []
    ): bool
    {
        $commentType = $answerComment->getType();
        $templateKey = '';

        if ($commentType === Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION) {
            $templateKey = Episciences_Mail_TemplatesManager::TYPE_PAPER_TMP_VERSION_SUBMITTED;
        } elseif ($commentType === Episciences_CommentsManager::TYPE_REVISION_ANSWER_NEW_VERSION) {
            $templateKey = Episciences_Mail_TemplatesManager::TYPE_PAPER_NEW_VERSION_SUBMITTED;
        } elseif ($commentType === Episciences_CommentsManager::TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED) {
            $templateKey = Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_EDITOR_AND_COPYEDITOR_COPY;
        }

        $requestCommentSender = new Episciences_User();
        $requestCommentSender->find($requestComment->getUid());

        $attachmentsFiles = [];
        $locale = $manager->getLangueid();

        $commonTags = [
            Episciences_Mail_Tags::TAG_ARTICLE_ID => $newPaper->getDocid(),
            Episciences_Mail_Tags::TAG_SENDER_EMAIL => Episciences_Auth::getEmail(),
            Episciences_Mail_Tags::TAG_SENDER_FULL_NAME => Episciences_Auth::getFullName(),
            Episciences_Mail_Tags::TAG_REQUEST_MESSAGE => $requestComment->getMessage(),
            Episciences_Mail_Tags::TAG_REQUEST_ANSWER => $answerComment->getMessage(),
            Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $newPaper->getTitle($locale, true),
            Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $newPaper->formatAuthorsMetadata($locale),
            Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE => $this->view->Date($paper1->getWhen(), $locale),
            Episciences_Mail_Tags::TAG_REQUEST_DATE => $this->view->Date($requestComment->getWhen(), $locale)
        ];

        if (!empty($tags)) {
            foreach ($commonTags as $key => $value) {
                if (!array_key_exists($key, $tags)) {
                    $tags[$key] = $value;
                }
            }
        } else {
            $tags = $commonTags;
        }

        $answerCommentFile = $answerComment->getFile();

        if ($answerCommentFile) {
            $jFiles = Episciences_Tools::isJson($answerCommentFile) ? json_decode($answerCommentFile) : (array)$answerCommentFile;
            foreach ($jFiles as $file) {
                $attachmentsFiles[$file] = $answerComment->getFilePath();
            }
        }

        return Episciences_Mail_Send::sendMailFromReview($manager, $templateKey, $tags, $newPaper, null, $attachmentsFiles, $makeCopy, $CC);

    }

    /**
     * @param Episciences_Paper $paper
     * @param string $templateType
     * @param array $tags
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    protected function paperStatusChangedNotifyReviewer(Episciences_Paper $paper, string $templateType, array $tags = [])
    {

        $docId = $paper->getDocid();

        $paperUrl = $this->view->url([
            'controller' => 'paper',
            'action' => 'view',
            'id' => $docId]);

        $paperUrl = HTTP . '://' . $_SERVER['SERVER_NAME'] . $paperUrl;

        $tags += [Episciences_Mail_Tags::TAG_ARTICLE_ID => $docId, Episciences_Mail_Tags::TAG_PAPER_URL => $paperUrl];

        /** @var  Episciences_Reviewer $reviewer [] */
        $reviewers = $paper->getReviewers(null, true);

        foreach ($reviewers as $reviewer) {
            $report = $reviewer->getReport($paper->getDocid());

            if ($report && $report->getStatus() == Episciences_Rating_Report::STATUS_COMPLETED) {
                continue;
            }

            $tags[Episciences_Mail_Tags::TAG_ARTICLE_TITLE] = $paper->getTitle($reviewer->getLangueid(), true);
            $tags[Episciences_Mail_Tags::TAG_AUTHORS_NAMES] = $paper->formatAuthorsMetadata();
            $tags[Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME] = $reviewer->getScreenName();
            $tags[Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME] = $reviewer->getUsername();

            Episciences_Mail_Send::sendMailFromReview($reviewer, $templateType, $tags, $paper);
        }
    }

    /**
     * notifie tous les rédacteurs de l'article, excepté le commentateur lui-même
     * Aussi, selon la configuration de la revue : le rédacteur en chef, l'administrateur et le secrétaire de rédaction
     * @param Episciences_Paper $paper
     * @param Episciences_Comment $oComment : request comment
     * @param array $tags : additional tags
     * @param array $additionalAttachments
     * @return bool
     * @throws Zend_Exception
     */
    protected function newCommentNotifyManager(Episciences_Paper $paper, Episciences_Comment $oComment, array $tags = [], array $additionalAttachments = [])
    {
        $commentatorUid = $oComment->getUid();
        $commentator = new Episciences_User();

        try {
            $commentator->findWithCAS($commentatorUid);
        } catch (Exception $e) {
            error_log('NEW_COMMENT_NOTIFY_MANAGERS_FAILED_TO_FETCH_CAS_DATA_UID_' . $commentatorUid . ' : ' . $e);
            return false;
        }

        $attachmentsFiles = [];
        $docId = (int)$paper->getDocid();
        $recipients = $this->getAllEditors($paper, false, true);
        Episciences_Review::checkReviewNotifications($recipients);
        $CC = $paper->extractCCRecipients($recipients);

        if (empty($recipients)) {
            $arrayKeyFirstCC = Episciences_Tools::epi_array_key_first($CC);
            $recipients = !empty($arrayKeyFirstCC) ? [$arrayKeyFirstCC => $CC[$arrayKeyFirstCC]] : [];
            unset($CC[$arrayKeyFirstCC]);
        }

        $makeCopy = true; // en fonction du type de commentaire, pour eviter de recopier le même fichier:  si une copie existe existe dèjà.

        $recipientTags = [
            Episciences_Mail_Tags::TAG_ARTICLE_ID => $docId,
            Episciences_Mail_Tags::TAG_COMMENT => $oComment->getMessage(),
            Episciences_Mail_Tags::TAG_PAPER_URL => $this->buildAdminPaperUrl($docId),
            Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME => Episciences_Auth::getScreenName(),
            Episciences_Mail_Tags::TAG_SENDER_FULL_NAME => Episciences_Auth::getFullName(),
            Episciences_Mail_Tags::TAG_SENDER_EMAIL => Episciences_Auth::getEmail()
        ];

        if ($oComment->getFile()) { //  attachment file
            $attachmentsFiles[$oComment->getFile()] = $oComment->getFilePath();
        }

        $attachmentsFiles = !empty($additionalAttachments) ? array_merge($attachmentsFiles, $additionalAttachments) : $attachmentsFiles;

        switch ($oComment->getType()) {
            case Episciences_CommentsManager::TYPE_INFO_REQUEST:
                $makeCopy = false; // see "save_contributor_answer" function: makeCopy = true
                $templateType = Episciences_Mail_TemplatesManager::TYPE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR_EDITOR_COPY;
                break;
            case  Episciences_CommentsManager::TYPE_SUGGESTION_ACCEPTATION:
                $templateType = Episciences_Mail_TemplatesManager::TYPE_PAPER_SUGGEST_ACCEPTATION;
                break;
            case  Episciences_CommentsManager::TYPE_SUGGESTION_NEW_VERSION:
                $templateType = Episciences_Mail_TemplatesManager::TYPE_PAPER_SUGGEST_NEW_VERSION;
                break;
            case  Episciences_CommentsManager::TYPE_SUGGESTION_REFUS:
                $templateType = Episciences_Mail_TemplatesManager::TYPE_PAPER_SUGGEST_REFUSAL;
                break;
            case Episciences_CommentsManager::TYPE_INFO_ANSWER:
                $makeCopy = false; // see "save_reviewer_comment" function: makeCopy = true
                $templateType = Episciences_Mail_TemplatesManager::TYPE_PAPER_COMMENT_ANSWER_EDITOR_COPY;
                break;
            default:
                $recipientTags[Episciences_Mail_Tags::TAG_EDITOR_SCREEN_NAME] = $commentator->getScreenName();
                $templateType = Episciences_Mail_TemplatesManager::TYPE_PAPER_COMMENT_BY_EDITOR_EDITOR_COPY;
        }

        if (!empty($tags)) {
            $recipientTags += $tags;
        }

        $nbNotifications = 0; // si = count($recipients) : tous les mails sont envoyés

        foreach ($recipients as $uid => $recipient) { // ne pas notifier le  commentateur
            if ($uid == $commentatorUid) {
                unset($recipients[$uid]);
                continue;
            }

            $locale = $recipient->getLangueid();
            $recipientTags[Episciences_Mail_Tags::TAG_ARTICLE_TITLE] = $paper->getTitle($locale, true);
            $recipientTags[Episciences_Mail_Tags::TAG_AUTHORS_NAMES] = $paper->formatAuthorsMetadata($locale);
            $recipientTags[Episciences_Mail_Tags::TAG_SUBMISSION_DATE] = $this->view->Date($paper->getSubmission_date(), $locale);

            try {
                Episciences_Mail_Send::sendMailFromReview(
                    $recipient, $templateType, $recipientTags, $paper, Episciences_Auth::getUid(), $attachmentsFiles, $makeCopy, $CC
                );
                ++$nbNotifications;
                $makeCopy = false;
                // TODO à remettre si passage à PHP - V 7.1 ou sup [Zend_Mail_Exception | Zend_Session_Exception $e (incompatible php -v 7.0)]
            } catch (Zend_Exception $e) {
                error_log('FAILED_TO_SEND_NEW_COMMENT_NOTIFICATION_TO_RECIPIENT_' . $uid . ' : ' . $e);
                continue;
            }
            // reset $CC
            $CC = [];
        }
        return count($recipients) === $nbNotifications;
    }

    /**
     * retourne tous les préparateurs de copie d'un article, excepté l'auteur de l'article
     * @param Episciences_Paper $paper
     * @param bool $withCasData
     * @return Episciences_CopyEditor[] || []
     */
    protected function getAllCopyEditors(Episciences_Paper $paper, $withCasData = true)
    {
        // Préparateurs de copie
        $assignedCopyEditors = Episciences_PapersManager::getCopyEditors($paper->getDocid(), true, $withCasData);

        // Auteur de l'article
        if (array_key_exists($paper->getUid(), $assignedCopyEditors)) {
            unset($assignedCopyEditors[$paper->getUid()]);
        }
        return $assignedCopyEditors;
    }

    /**
     * @param int $docId
     * @return string
     */
    public function buildAdminPaperUrl(int $docId)
    {
        $adminPaperUrl = $this->view->url(
            [
                self::CONTROLLER => self::ADMINISTRATE_PAPER_CONTROLLER,
                self::ACTION => 'view',
                'id' => $docId
            ]);

        return HTTP . '://' . $_SERVER[self::SERVER_NAME_STR] . $adminPaperUrl;
    }

    /**
     * @param int $docId
     * @return string
     */
    public function buildPublicPaperUrl(int $docId)
    {
        $paperUrl = $this->view->url(
            [
                self::CONTROLLER => self::PUBLIC_PAPER_CONTROLLER,
                self::ACTION => 'view',
                'id' => $docId
            ]);

        return HTTP . '://' . $_SERVER[self::SERVER_NAME_STR] . $paperUrl;
    }

    /**
     * @param Episciences_Paper $paper
     * @param string $templateType
     * @param Episciences_User|null $principalRecipient : action initiator
     * @param array $tags
     * @param array $attachments
     * @param boolean $strict = true : prendre en compte le module de notifications
     * @param array $ignoredRecipients
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     */
    protected function paperStatusChangedNotifyManagers(Episciences_Paper $paper, string $templateType, Episciences_User $principalRecipient = null, array $tags = [], array $attachments = [], bool $strict = true, array $ignoredRecipients = []): void
    {
        $docId = (int)$paper->getDocid();

        $tags = array_merge([Episciences_Mail_Tags::TAG_ARTICLE_ID => $docId, Episciences_Mail_Tags::TAG_PAPER_URL => $this->buildAdminPaperUrl($docId)], $tags);

        $recipients = $this->getAllEditors($paper) + $this->getAllCopyEditors($paper);

        Episciences_Review::checkReviewNotifications($recipients, RVID, $strict);

        foreach ($ignoredRecipients as $uid => $recep) {
            unset($recipients[$uid]);
        }

        $principalRecipientUid = (null !== $principalRecipient) ? $principalRecipient->getUid() : null;
        $CC = $paper->extractCCRecipients($recipients, $principalRecipientUid);

        if ($principalRecipientUid) {
            $recipients = [$principalRecipientUid => $principalRecipient];

        } elseif (empty($recipients)) {
            $arrayKeyFirstCC = Episciences_Tools::epi_array_key_first($CC);
            $recipients = !empty($arrayKeyFirstCC) ? [$arrayKeyFirstCC => $CC[$arrayKeyFirstCC]] : [];
            unset($CC[$arrayKeyFirstCC]);
        }

        foreach ($recipients as $recipient) {
            $locale = $recipient->getLangueid();
            $tags[Episciences_Mail_Tags::TAG_ARTICLE_TITLE] = $paper->getTitle($locale, true);
            $tags[Episciences_Mail_Tags::TAG_AUTHORS_NAMES] = $paper->formatAuthorsMetadata($locale);
            $tags[Episciences_Mail_Tags::TAG_SUBMISSION_DATE] = Episciences_View_Helper_Date::Date($paper->getSubmission_date(), $locale);
            Episciences_Mail_Send::sendMailFromReview($recipient, $templateType, $tags, $paper, null, $attachments, false, $CC);
            //reset $CC
            $CC = [];
        }
    }

    /**
     * @param string $message : message initial
     * @param string $alert
     * @return string
     * @throws Zend_Exception
     */
    protected function buildAlertMessage(string $message = '', string $alert = ''): string
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $alert = $translator->translate($alert);
        $message .= '<div class="panel panel-danger">';
        $message .= '<div class="panel-heading"><strong>' . $translator->translate("Êtes-vous sûr ?") . '</strong></div>';
        $message .= '<div class="panel-body">';
        $message .= '<span class="fas fa-exclamation-triangle fa-lg" style="margin-right: 5px;"></span>';
        $message .= $alert;
        $message .= '</div>';
        $message .= '</div>';
        return $message;
    }

    /**
     * @param Episciences_Paper $paper
     * @return array
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    protected function getEditors(Episciences_Paper $paper): array
    {

        $journalSettings = Zend_Registry::get('reviewSettings');

        // fetch all editors (chief editors included)
        $editors = Episciences_Review::getEditors(false);

        if (isset($journalSettings[Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED]) && $journalSettings[Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED]) {
            foreach ($this->usersWithReportedCoiProcessing($paper, 'editor') as $uid => $user) {
                unset($editors[$uid]);
            }
        }

        return $editors;

    }

    /**
     * @param Episciences_Paper $paper
     * @return array
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    protected function getCopyEditors(Episciences_Paper $paper): array
    {

        // fetch all copy editors
        $copyEditors = Episciences_Review::getCopyEditors();

        $journalSettings = Zend_Registry::get('reviewSettings');

        if (isset($journalSettings[Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED]) && $journalSettings[Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED]) {
            foreach ($this->usersWithReportedCoiProcessing($paper, 'copy_editor') as $uid => $user) {
                unset($copyEditors[$uid]);
            }
        }

        return $copyEditors;
    }

    /**
     * [COI] When assigning an editors/copy editors ; propose only editors/copy Editors that have not reported a COI
     * @param Episciences_Paper $paper
     * @param string $role
     * @param string $answer
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    protected function usersWithReportedCoiProcessing(Episciences_Paper $paper, string $role = 'user', string $answer = Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes'] ): array
    {
        $result = [];

        if ($role === 'editor') {
            $user = new Episciences_Editor();

        } elseif ($role === 'copy_editor') {
            $user = new Episciences_CopyEditor();

        } else {
            $user = new Episciences_User();

        }

        $uidS = Episciences_Paper_ConflictsManager::fetchSelectedCol('by', ['answer' => $answer, 'paper_id' => $paper->getPaperid()]);

        foreach ($uidS as $uid) {

            $user->find($uid);
            $result[$uid] = $user;
        }

        return $result;

    }
}