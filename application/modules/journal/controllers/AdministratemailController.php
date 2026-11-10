<?php

use Episciences\AppRegistry;
use Episciences\Mail\AuthPaperAccessProvider;
use Episciences\Mail\RecipientAccessDenied;
use Episciences\Mail\RecipientAccessPolicy;
use Episciences\Mail\RecipientListValidator;
use Episciences\Mail\RecipientScope;
use Episciences\Mail\RecipientSearchAudit;
use Episciences\Mail\RecipientSearchService;
use Episciences\Mail\RecipientSuggestions;
use Episciences\Security\AccessLog;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

require_once APPLICATION_PATH . '/modules/common/controllers/DefaultController.php';

class AdministratemailController extends Zend_Controller_Action
{

    /**
     * @var boolean
     */
    private $_allowedToEdit;

    public function init(): void
    {
        $isAllowed = Episciences_Auth::isSecretary() || Episciences_Auth::isWebmaster();

        if (!$isAllowed) {

            try {
                $journalSettings = Zend_Registry::get('reviewSettings');
                $isAllowed = isset($journalSettings[Episciences_Review::SETTING_EDITORS_CAN_EDIT_TEMPLATES]) &&
                    !empty($journalSettings[Episciences_Review::SETTING_EDITORS_CAN_EDIT_TEMPLATES]);

            } catch (Exception $e) {
                trigger_error($e->getMessage());
            }

        }

        $this->setAllowedToEdit($isAllowed);

    }

    public function indexAction()
    {
        $this->renderScript('index/submenu.phtml');
    }

    /**
     * list all mail templates
     */
    public function templatesAction(): void
    {
        $this->view->templates = Episciences_Mail_TemplatesManager::getList([], RVID);
        $this->view->editorsCanEditTmplates = $this->isAllowedToEdit();
        $this->view->csrfToken = Episciences_Csrf_Helper::getSessionToken();
    }

    /**
     * @return bool
     */
    public function isAllowedToEdit(): bool
    {
        return $this->_allowedToEdit;
    }

    /**
     * @param bool $allowedToEdit
     */
    public function setAllowedToEdit($allowedToEdit)
    {
        $this->_allowedToEdit = (bool)$allowedToEdit;
    }

    /**
     * template edit form (shown in modal)
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public function edittemplateAction(): void
    {
        $this->_helper->layout->disableLayout();

        if (!$this->isAllowedToEdit()) {
            AccessLog::denied(AccessLog::ACL_DENIED, 'role_check');
            $this->_helper->redirector->gotoUrl('/error/deny');
            return;
        }

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if (!$request->isXmlHttpRequest()) {
            AccessLog::denied(AccessLog::ACL_DENIED, 'not_xhr');
            $this->_helper->redirector->gotoUrl('/error/deny');
            return;
        }

        $id = (int)$request->getPost('id');

        $oTemplate = new Episciences_Mail_Template();

        // only a default template or a custom template of the current journal can be edited
        if (!$oTemplate->findForJournal($id, RVID)) {
            AccessLog::denied(AccessLog::ACL_DENIED, 'foreign_template');
            $this->_helper->redirector->gotoUrl('/error/deny');
            return;
        }

        $langs = Episciences_Tools::getLanguages();
        $form = Episciences_Mail_TemplatesManager::getTemplateForm($oTemplate, $langs);

        $this->view->langs = $langs;
        $this->view->form = $form;
    }

    /**
     * save custom template
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    public function savetemplateAction()
    {
        $this->_helper->layout->disableLayout();

        if (!$this->isAllowedToEdit()) {
            AccessLog::denied(AccessLog::ACL_DENIED, 'role_check');
            $this->_helper->redirector->gotoUrl('/error/deny');
            return;
        }

        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $id = (int)$request->getQuery('id');

        $template = new Episciences_Mail_Template();

        // only a default template or a custom template of the current journal can be saved
        if (!$template->findForJournal($id, RVID)) {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("Les modifications n'ont pas abouti !");
            $this->_helper->redirector->gotoUrl('/administratemail/templates');
            return;
        }

        if (!$request->isPost() || !Episciences_Csrf_Helper::validateRequestToken($request)) {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("Les modifications n'ont pas abouti !");
            $this->_helper->redirector->gotoUrl('/administratemail/templates');
            return;
        }

        $post = $request->getPost();
        unset($post['csrf_token']);
        $options = [];
        foreach ($post as $lang => $data) {
            foreach ($data as $field => $value) {
                $options[$field][$lang] = $value;
            }
        }

        $options['rvid'] = RVID;

        $template->setOptions($options);

        if ($template->save()) {
            $this->_helper->FlashMessenger->setNamespace('success')->addMessage('Vos modifications ont bien été prises en compte');
        } else {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("Les modifications n'ont pas abouti !");
        }

        $this->_helper->redirector->gotoUrl('/administratemail/templates');
    }

    /**
     * delete a custom template and restore default template
     * @throws Zend_Db_Statement_Exception
     */
    public function deletetemplateAction()
    {
        $this->_helper->layout->disableLayout();

        if (!$this->isAllowedToEdit()) {
            AccessLog::denied(AccessLog::ACL_DENIED, 'role_check');
            $this->_helper->redirector->gotoUrl('/error/deny');
            return;
        }

        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if (!$request->isPost() || !Episciences_Csrf_Helper::validateRequestToken($request)) {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage('Votre session a expiré, veuillez réessayer.');
            $this->_helper->redirector->gotoUrl('/administratemail/templates');
            return;
        }

        $id = (int)$request->getPost('id');

        $template = new Episciences_Mail_Template();

        // only a custom template of the current journal can be deleted
        if ($template->findCustom($id, RVID) && $template->delete()) {
            $this->_helper->FlashMessenger->setNamespace('success')->addMessage('Le template par défaut a été restauré');
        } else {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage('La suppression du template personnalisé a échoué');
        }

        $this->_helper->redirector->gotoUrl('/administratemail/templates');

    }


    /**
     * mail history
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     */
    public function historyAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ($request->isXmlHttpRequest()) {

            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender();

            $dataTableColumns = [
                '0' => 'subject',
                '1' => ['to', 'cc', 'bcc'],
                '2' => 'files',
                '3' => 'when'
            ];

            $post = $request->getParams();
            $limit = Ccsd_Tools::ifsetor($post['length'], '10');
            $offset = Ccsd_Tools::ifsetor($post['start'], '0');
            $search = Ccsd_Tools::ifsetor($post['search']['value'], '');
            // L'ordre est un tableau de tableaux, chaque tableau intérieur étant composé de deux éléments:
            // index de la colonne et la direction
            $requestOrder = Ccsd_Tools::ifsetor($post['order'], []);

            $options = [
                'limit' => $limit,
                'offset' => $offset
            ];

            $options['search'] = trim($search);

            if (!empty($requestOrder)) {
                $options['order'] = Episciences_Tools::dataTableOrder($requestOrder, $dataTableColumns);
            }

            $review = Episciences_ReviewsManager::find(RVID);
            $review->loadSettings();
            $docIds = $this->historyProcessing($review, $options);
            $mails = new Episciences_Mail();
            // Le nombre total d'enregistrements, avant filtrage
            $historyCount = $mails->getCountHistory($docIds, $options);
            // Le nombre total d'eregistrements, après filtrage
            $historyFiltredCount = $mails->getCountHistory($docIds, $options, true);
            // La liste des mails
            $history = $mails->getHistory($docIds, $options, true);

            $tbody = ($historyCount > 0) ?
                $this->view->partial('administratemail/datatable_history.phtml', [
                    'history' => $history
                ]) :
                '';

            echo Episciences_Tools::getDataTableData($tbody, $post['draw'], $historyCount, $historyFiltredCount);

        }
    }

    /**
     * mail detail (shown in modal)
     * @return void
     * @throws Zend_Mail_Exception
     */
    public function viewAction()
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ($request->isXmlHttpRequest()) {
            $this->_helper->layout->disableLayout();
            $id = (int)$request->getParam('id');
            $oMail = new Episciences_Mail('UTF-8');

            // same scope as the history list (journal, allowed documents, conflicts of interest)
            $review = Episciences_ReviewsManager::find(RVID);
            $review->loadSettings();
            $options = [];
            $docIds = $this->historyProcessing($review, $options);

            if (!$oMail->isInHistory($id, $docIds, $options) || !$oMail->find($id)) {
                $this->getResponse()->setHttpResponseCode(404);
                $this->view->mail = [];
                return;
            }

            $this->view->mail = $oMail->toArray(true);

        } else {
            $this->getResponse()?->setHttpResponseCode(404);
            $this->renderScript('index/notfound.phtml');
        }

    }

    /**
     * mailing module
     * @throws Exception
     */
    public function sendAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $post = $request->getPost();

        $ajax = (bool)$request->getParam('ajax');

        $this->checkEmailAttachmentsPath($ajax || isset($post['in_modal'])); //on submission of the form: $ajax === false

        // Git #61
        $docId = (int)$request->get('paper');

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $scope = $docId > 0 ? $this->recipientScope($db, $docId) : null;

        if ($docId > 0 && $scope === null) {
            // The form lists the participants of its paper: it is only about a paper the user may access
            $docId = 0;
        }

        if ($ajax) {

            $this->_helper->layout->disableLayout();
            $to_enabled = false;
            $button_enabled = false;
        } else {

            $to_enabled = true;
            $button_enabled = true;
        }

        $form = Episciences_Mail_Send::getForm(null, $button_enabled, $to_enabled, $docId);

        $isSendRequest = $post
            && !array_key_exists('ajax', $post)
            && (isset($post['submit']) || isset($post['docid'])); // Le changement de langue du site Web entraînera la resoumission du formulaire.

        $isTokenRefused = $isSendRequest && !Episciences_Csrf_Helper::validateRequestToken($request);

        // Read once: the paper loaded for the mail is the one checked here. Null: no paper; false: malformed
        $postedDocId = self::postedDocId($post);
        $sendScope = $isSendRequest && !$isTokenRefused && is_int($postedDocId) ? $this->recipientScope($db, $postedDocId) : null;
        // A mail about a paper is written into its history: only for a paper the user may access
        $isPaperRefused = $isSendRequest && !$isTokenRefused && $postedDocId !== null && $sendScope === null;

        if ($isTokenRefused || $isPaperRefused) {
            $reason = $isTokenRefused ? 'Votre session a expiré, veuillez réessayer.' : "Vous n'avez pas accès à cet article.";
            $message = '<p><strong>' . $this->view->translate("Votre message n'a pas pu être envoyé :") . '</strong></p>'
                . '<div>' . $this->view->translate($reason) . '</div>';

            if (isset($post['in_modal'])) {
                // Not a success status: the modal keeps the text typed by the user
                $this->_helper->layout->disableLayout();
                $this->_helper->viewRenderer->setNoRender();
                $this->getResponse()->setHttpResponseCode(403);
                echo $message;
                return;
            }

            // Nothing is sent, and the form is not filled with the refused request:
            // its values may come from another site, or be about a paper the user may not access
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
        } elseif ($isSendRequest) {

            $checkedRecipients = $this->checkRecipients($db, $post, $sendScope);

            if ($checkedRecipients['isDetectedErrors']) {
                $form->setDefaults($post);
            }

            // Not refused: the posted paper, if any, is accessible ($sendScope is not null)
            $result = $this->sendMail($post, $checkedRecipients, is_int($postedDocId) ? $postedDocId : null);

            if ($result) {
                $this->_helper->layout->disableLayout();
                $this->_helper->viewRenderer->setNoRender();
                echo $result;
                return;
            }

        } else {

            $recipientId = $request->getParam('recipient');
            $js_recipient = is_numeric($recipientId) && (int)$recipientId > 0
                ? $this->prefilledRecipient($db, $scope ?? $this->recipientScope($db, null), (int)$recipientId, (bool)$request->getParam('tmp'), $docId)
                : null;

            if ($js_recipient !== null) {
                $this->view->js_recipient = RecipientSuggestions::toScriptJson($js_recipient);

                if ($ajax) {
                    $form->setDefaults(['to' => $js_recipient['fullname'] . ' <' . $js_recipient['mail'] . '>']);
                }
            }

        }

        $this->view->js_sender = RecipientSuggestions::senderJson();

        $this->view->form = $form;
        $this->view->ajax = $ajax;

    }

    /**
     * send a mail (process mailing form)
     * TODO: move this outside of the controller ?
     * @param array $post
     * @param ?int $docId paper of the mail, already checked as accessible to the sender
     * @return string|void
     * @throws Exception
     */
    private function sendMail(array $post, array $checkedRecipients, ?int $docId)
    {
        $paper = null;

        $isInModal = isset($post['in_modal']) && $post['in_modal']; // true: sent from the paper administration page

        /** @var Zend_View $selfView */
        $selfView = $this->view;

        // Contrôle des erreurs
        $errors = $checkedRecipients['errors'];
        $isEmptyMail = empty($post['subject']) && empty($post['content']) && empty($post[Episciences_Mail_Send::ATTACHMENTS]);

        if (!empty($errors) || $isEmptyMail) {

            // The attachments are kept: the form is sent again once corrected, with the same attachments

            $message = '<p><strong>' . $selfView->translate("Votre message n'a pas pu être envoyé :") . '</strong></p>';

            if (!empty($errors)) {

                foreach ($errors as $error) {
                    $text = $selfView->translate($error['message']);
                    $text = $error['argument'] === null ? $text : sprintf($text, $error['argument']);
                    $message .= '<div>' . $selfView->escape($text) . '</div>';
                }

            } else {
                $message .= $selfView->translate('Corps du message vide.');
            }

            if ($isInModal) {
                // Not a success status: the modal shows the message above the form, which keeps what was typed
                $this->getResponse()->setHttpResponseCode(422);
                return $message;
            }

            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            return;
        }

        if ($docId !== null) {
            /** @var Episciences_Paper|false $paper */
            $paper = Episciences_PapersManager::get($docId);
        }

        $mail = $this->buildMail($post, $checkedRecipients['recipients'], $paper ?: null);

        if ($mail->writeMail()) {

            $message = '<strong>' . $selfView->translate("Votre e-mail a bien été envoyé.") . '</strong>';

            if ($paper) {

                try {
                    $paper->log(
                        Episciences_Paper_Logger::CODE_MAIL_SENT,
                        Episciences_Auth::getUid(),
                        ['id' => $mail->getId(), 'mail' => $mail->toArray()]
                    );
                } catch (Exception $e) {
                    Ccsd_Log::message($e->getMessage(), false, Zend_Log::WARN, EPISCIENCES_EXCEPTIONS_LOG_PATH . RVCODE . '.mail');
                }

            } else {
                $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
            }

            Episciences_Auth::resetCurrentAttachmentsPath();


        } else {

            $message = '<strong>' . $selfView->translate("Une erreur interne s'est produite, veuillez recommencer.") . '</strong>';

            if ($isInModal) {
                // Not a success status: the modal keeps the form, to send it again
                $this->getResponse()->setHttpResponseCode(500);
            } else {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            }

        }

        if ($isInModal) {
            return $message;
        }

        $this->_helper->redirector->gotoUrl('administratemail/send');

    }

    /**
     * Build the mail of the mailing form (not sent)
     * @param array<string, mixed> $post
     * @param array{to: array<int, array<string, mixed>>, cc: array<int, array<string, mixed>>, bcc: array<int, array<string, mixed>>} $recipients
     * @throws Zend_Mail_Exception
     */
    private function buildMail(array $post, array $recipients, ?Episciences_Paper $paper): Episciences_Mail
    {
        $validator = new Zend_Validate_EmailAddress();

        // Récupération des destinataires
        $to = $recipients['to'];
        $cc = $recipients['cc'];
        $bcc = $recipients['bcc'];

        // récupération du contenu
        $subject = (!empty(Ccsd_Tools::ifsetor($post['subject']))) ? $post['subject'] : Zend_Registry::get('Zend_Translate')->translate('Aucun sujet');
        $content = Ccsd_Tools::clear_nl(Ccsd_Tools::ifsetor($post['content']));

        if (empty($content) && empty($post[Episciences_Mail_Send::ATTACHMENTS])) {
            $content = 'Empty message.';
        }

        $mail = new Episciences_Mail('UTF-8');
        $mail->setSubject($subject);
        $mail->setRawBody($content);

        $mail->addTag(Episciences_Mail_Tags::TAG_SENDER_EMAIL, Episciences_Auth::getEmail());
        $mail->addTag(Episciences_Mail_Tags::TAG_SENDER_FULL_NAME, Episciences_Auth::getFullName());
        $mail->addTag(Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME, Episciences_Auth::getScreenName());

        // From & Reply-To are always built server-side: client-supplied values are ignored
        $mail->setFrom(RVCODE . '@' . DOMAIN, Episciences_Auth::getFullName());

        if ($validator->isValid(Episciences_Auth::getEmail())) {
            $mail->setReplyTo(Episciences_Auth::getEmail(), Episciences_Auth::getFullName());
        }

        foreach ($to as $recipient) {

            $postMailValidation = Episciences_Tools::postMailValidation($recipient['value']);
            $email = $postMailValidation['email'];
            $name = $postMailValidation['name'];

            if ($validator->isValid($email)) {
                $mail->addTo($email, $name);
            }
        }

        foreach ($cc as $recipient) {

            $postMailValidation = Episciences_Tools::postMailValidation($recipient['value']);
            $email = $postMailValidation['email'];
            $name = $postMailValidation['name'];

            if ($validator->isValid($email)) {
                $mail->addCc($email, $name);
            } else {
                trigger_error(RVCODE . 'FROM_MAILING_BAD_CC_MAIL: ' . $email);
            }
        }

        foreach ($bcc as $recipient) {

            $email = Episciences_Tools::postMailValidation($recipient['value'])['email'];

            if ($validator->isValid($email)) {
                $mail->addBcc($email);
            } else {
                trigger_error(RVCODE . 'FROM_MAILING_BAD_BCC_MAIL: ' . $email);
            }
        }

        if ($paper) {
            $mail->setDocid($paper->getDocid());
        }

        $mail->setUid(Episciences_Auth::getUid());

        if (isset($post[Episciences_Mail_Send::ATTACHMENTS])) {
            // Errors : si une erreur s'est produite lors de la validation d'un fichier attaché par exemple(voir es.fileupload.js)
            $attachments = Episciences_Tools::arrayFilterEmptyValues($post[Episciences_Mail_Send::ATTACHMENTS]);
            $path = Episciences_Tools::getAttachmentsPath();
            foreach ($attachments as $attachment) {
                $filepath = Episciences_Tools::resolveAttachmentPath($path, $attachment);
                if ($filepath !== null) {
                    $mail->addAttachedFile($filepath);
                }
            }
        }

        return $mail;
    }

    /**
     * Recipients search of the mail forms (JSON): GET, XHR only.
     *
     * Parameters: q (text search), role (editorial role listed without text), docid (paper of the mail: its
     * participants are listed first). Without q nor role, the participants of the paper are returned.
     * The users the caller may find are decided by RecipientAccessPolicy (the ACL only allows the editorial roles).
     */
    public function getrecipientsAction(): void
    {
        $this->_helper->getHelper('layout')->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $params = $request->isGet() && $request->isXmlHttpRequest() ? self::recipientSearchParams($request) : null;

        if ($params === null) {
            $this->sendRecipientsJson(400, ['error' => 'bad_request']);
            return;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $rvid = (int)RVID;

        try {
            $scope = $this->recipientAccessPolicy($db)->scope($params['docid']);
        } catch (RecipientAccessDenied) {
            AccessLog::denied(AccessLog::HTTP_FORBIDDEN, 'recipients_search_scope', ['docid' => $params['docid']]);
            $this->sendRecipientsJson(403, ['error' => 'forbidden']);
            return;
        }

        (new RecipientSearchAudit(
            new FilesystemAdapter('recipientSearch', RecipientSearchAudit::DEFAULT_WINDOW_SECONDS, dirname(APPLICATION_PATH) . '/cache/'),
            AppRegistry::getMonoLogger()
        ))->record((int)Episciences_Auth::getUid(), $rvid);

        $service = new RecipientSearchService($db);

        if ($params['q'] !== '') {
            $results = $service->search($rvid, $scope, $params['q'], $params['role'], $params['limit'] ?? RecipientSearchService::DEFAULT_LIMIT);
        } elseif ($params['role'] !== null) {
            $results = $service->staff($rvid, $scope, $params['role'], $params['limit'] ?? RecipientSearchService::STAFF_LIMIT);
        } else {
            $results = $service->paperParticipants($rvid, $scope);
        }

        $this->sendRecipientsJson(200, ['results' => $results]);
    }

    /**
     * @return ?array{q: string, role: ?string, docid: ?int, limit: ?int} null when a parameter is invalid
     */
    private static function recipientSearchParams(Zend_Controller_Request_Http $request): ?array
    {
        // Query string only: route parameters must not override the search
        $query = $request->getQuery('q', '');
        $role = $request->getQuery('role', '');
        $docId = $request->getQuery('docid', '');
        $limit = $request->getQuery('limit', '');

        if (!is_string($query) || !is_string($role) || !is_string($docId) || !is_string($limit) || mb_strlen($query) > 100) {
            return null;
        }

        $isValid = ($role === '' || in_array($role, RecipientSearchService::BROWSEABLE_STAFF_ROLES, true))
            && ($docId === '' || ctype_digit($docId))
            && ($limit === '' || ctype_digit($limit));

        if (!$isValid) {
            return null;
        }

        return [
            'q' => trim($query),
            'role' => $role === '' ? null : $role,
            'docid' => $docId === '' ? null : (int)$docId,
            'limit' => $limit === '' ? null : (int)$limit,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendRecipientsJson(int $httpCode, array $payload): void
    {
        $this->getResponse()
            ->setHttpResponseCode($httpCode)
            ->setHeader('Content-Type', 'application/json; charset=utf-8', true)
            ->setHeader('Cache-Control', 'no-store', true)
            ->setHeader('X-Content-Type-Options', 'nosniff', true)
            ->setBody(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG));
    }

    /**
     * @throws RecipientAccessDenied
     */
    private function recipientAccessPolicy(Zend_Db_Adapter_Abstract $db): RecipientAccessPolicy
    {
        $roles = self::strictRoles();

        // Refused before anything is loaded
        if (array_intersect(RecipientAccessPolicy::ALLOWED_ROLES, $roles) === []) {
            throw new RecipientAccessDenied('Recipients search is not allowed for this user');
        }

        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();

        $papers = new AuthPaperAccessProvider(
            $db,
            (int)RVID,
            (int)Episciences_Auth::getUid(),
            static fn(Episciences_Paper $paper): bool => DefaultController::isConflictDetected($paper, $review)
        );

        return new RecipientAccessPolicy(
            $roles,
            (bool)$review->getSetting(Episciences_Review::SETTING_ENCAPSULATE_EDITORS),
            (bool)$review->getSetting(Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS),
            (bool)$review->getSetting(Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED),
            $papers
        );
    }

    /**
     * Paper of the posted mail: null when none, false when the value is not a positive integer
     * @param array<string, mixed> $post
     */
    private static function postedDocId(array $post): int|false|null
    {
        $value = $post['docid'] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) && preg_match('/^[1-9]\d{0,18}$/', $value) === 1 ? (int)$value : false;
    }

    /**
     * What the logged user may know of the recipients, about a paper or not; null if refused (no recipients search
     * for their roles, or a paper they may not access)
     */
    private function recipientScope(Zend_Db_Adapter_Abstract $db, ?int $docId): ?RecipientScope
    {
        try {
            return $this->recipientAccessPolicy($db)->scope($docId);
        } catch (RecipientAccessDenied) {
            return null;
        }
    }

    /**
     * Recipient given in the URL of the form ("Contact" links): only a user the recipients search would find
     *
     * @return ?array{uid: ?int, fullname: string, username: string, mail: string, roles: list<string>, group: string, paperRole: ?string}
     */
    private function prefilledRecipient(Zend_Db_Adapter_Abstract $db, ?RecipientScope $scope, int $id, bool $isTemporaryUser, int $docId): ?array
    {
        $service = new RecipientSearchService($db);
        $recipient = null;

        if ($scope !== null) {
            $recipient = $isTemporaryUser
                ? $service->findVisibleTmpReviewer((int)RVID, $scope, $id)
                : $service->findVisible((int)RVID, $scope, $id);
        }

        if ($recipient === null) {
            // The form stays empty, without telling whether the account exists
            AccessLog::denied(AccessLog::HTTP_FORBIDDEN, 'mail_recipient_prefill', ['uid' => $id, 'tmp' => $isTemporaryUser, 'docid' => $docId]);
        }

        return $recipient;
    }

    /**
     * Roles of the logged user in the current journal, without the administrator cascade
     * @return list<string>
     */
    private static function strictRoles(): array
    {
        $checks = [
            Episciences_Acl::ROLE_ROOT => Episciences_Auth::isRoot(),
            Episciences_Acl::ROLE_CHIEF_EDITOR => Episciences_Auth::isChiefEditor(RVID, true),
            Episciences_Acl::ROLE_ADMIN => Episciences_Auth::isAdministrator(RVID, true),
            Episciences_Acl::ROLE_SECRETARY => Episciences_Auth::isSecretary(RVID, true),
            Episciences_Acl::ROLE_EDITOR => Episciences_Auth::isEditor(RVID, true),
            Episciences_Acl::ROLE_GUEST_EDITOR => Episciences_Auth::isGuestEditor(RVID, true),
            Episciences_Acl::ROLE_COPY_EDITOR => Episciences_Auth::isCopyEditor(),
        ];

        return array_keys(array_filter($checks));
    }

    /**
     * @throws Zend_Exception
     */
    public function remindersAction()
    {
        $langs = Episciences_Tools::getLanguages();
        $locale = Episciences_Tools::getLocale();

        $this->view->locale = (array_key_exists($locale, $langs)) ? $locale : 'fr';
        $this->view->langs = $langs;
        $this->view->reminders = Episciences_Mail_RemindersManager::getReminders();
        $this->view->templates = Episciences_Mail_RemindersManager::getTemplates();

    }

    /**
     * reminder edit form (ajax)
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public function editreminderAction()
    {
        $this->_helper->layout->disableLayout();

        $request = $this->getRequest();
        $id = $request->getParam('id');

        if ($id) {
            // Only reminders of the current journal can be edited
            $reminder = Episciences_Mail_RemindersManager::find((int)$id, RVID);
            if (!$reminder) {
                $this->_helper->viewRenderer->setNoRender();
                $this->getResponse()->setHttpResponseCode(403);
                return;
            }
        } else {
            $reminder = null;
        }

        if ($reminder) {
            $this->view->reminder = $reminder;
            $this->view->js_reminder = Zend_Json::encode($reminder->toArray());
        }
        $this->view->js_recipient_options = Zend_Json::encode(Episciences_Mail_Reminder::MAPPING_REMINDER_RECIPIENTS);
        $this->view->reminderForm = Episciences_Mail_RemindersManager::getForm($reminder);
    }

    /**
     * @throws Zend_Exception
     */
    public function savereminderAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $request = $this->getRequest();

        if (!$this->isValidReminderWriteRequest($request)) {
            return;
        }

        $recipient = $request->getParam('recipient');
        $type = $request->getParam('type');
        $templates = Episciences_Mail_RemindersManager::getTemplates();

        if (!is_string($type) || !is_string($recipient)
            || !array_key_exists($type, $templates)
            || !array_key_exists($recipient, $templates[$type])
        ) {
            $this->getResponse()->setHttpResponseCode(400);
            return;
        }

        // Only reminders of the current journal can be modified
        $id = $request->getParam('id');
        if ($id && !Episciences_Mail_RemindersManager::find((int)$id, RVID)) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }

        $options = [
            'id' => $id ? (int)$id : null,
            'rvid' => RVID,
            'type' => $request->getParam('type'),
            'recipient' => $request->getParam('recipient'),
            'delay' => $request->getParam('delay'),
            'repetition' => $request->getParam('repetition'),
        ];

        $langs = Episciences_Tools::getLanguages();
        foreach ($langs as $code => $lang) {
            $options['custom'][$code] = $request->getParam($code . '_custom_template');
            if ($options['custom'][$code] == 1) {
                $options['subject'][$code] = $request->getParam($code . '_custom_subject');
                $options['body'][$code] = $request->getParam($code . '_custom_body');
            }
        }

        $reminder = new Episciences_Mail_Reminder($options);
        if (!$reminder->save()) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }

        // Success body preserved for the AJAX caller
        echo 1;
    }

    /**
     * delete reminder (ajax)
     * @return bool
     */
    public function deletereminderAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $request = $this->getRequest();

        if (!$this->isValidReminderWriteRequest($request)) {
            return false;
        }

        $id = $request->getParam('id');

        // Only reminders of the current journal can be deleted
        if ($id && Episciences_Mail_RemindersManager::delete((int)$id, RVID)) {
            return true;
        }

        $this->getResponse()->setHttpResponseCode(403);
        return false;
    }

    /**
     * Reminder changes must come from a POST request carrying the per-session request token (CSRF);
     * otherwise the request is answered with HTTP 403.
     */
    private function isValidReminderWriteRequest(Zend_Controller_Request_Http $request): bool
    {
        if ($request->isPost() && Episciences_Csrf_Helper::validateRequestToken($request)) {
            return true;
        }

        $this->getResponse()->setHttpResponseCode(403);
        return false;
    }

    /**
     * Liste les variables à insérer dans les templates
     * @return void
     */
    public function tagslistAction(): void
    {
        $oTemplates = [];
        $templates = Episciences_Mail_TemplatesManager::getDefaultList();
        $commonTags = Episciences_Mail_TemplatesManager::COMMON_TAGS;
        $allTags = $commonTags;

        /**
         * @var  int $id
         * @var  array $template
         */

        foreach ($templates as $id => $template) {

            try {
                $oTemplate = new Episciences_Mail_Template();
                $oTemplate->find($id);
                $oTemplates[$id] = $oTemplate;
                $allTags = array_merge($allTags, array_diff($oTemplate->getTags(), $allTags));
            } catch (Zend_Db_Statement_Exception $e) {
                trigger_error($e->getMessage());
            }

            unset($oTemplate);


        }

        $this->view->oTemplates = $oTemplates;
        $this->view->allTags = $allTags;

    }

    /**
     *  refresh reminders display (ajax)
     * @throws Zend_Exception
     */
    public function refreshremindersAction()
    {
        $this->_helper->layout->disableLayout();
        $this->view->reminders = Episciences_Mail_RemindersManager::getReminders();
        $this->renderScript('administratemail/reminders_list.phtml');
    }

    /**
     * @param Episciences_User $user
     * @return array
     */
    private function papersNotInConflictProcessing(Episciences_User $user): array
    {
        $docIds = [];
        $papers = $user->getPapersNotInConflict();

        /**
         * @var  $paperId int
         * @var  $paper Episciences_Paper
         */
        foreach ($papers as $paper) {
            $versionIds = $paper->getVersionsIds();

            foreach ($versionIds as $docId) {
                $docIds[] = $docId;
            }

        }

        return $docIds;

    }

    /**
     * Recipients of the mailing form, checked (structure, addresses, number, journal accounts, anonymity)
     * @param array<string, mixed> $post
     * @param ?RecipientScope $scope what the sender may know of the recipients (see recipientScope())
     * @param bool $strict [default true: the main recipient ($to) is required ]
     * @return array{recipients: array{to: list<array{key: string, value: string, uid: ?int}>, cc: list<array{key: string, value: string, uid: ?int}>, bcc: list<array{key: string, value: string, uid: ?int}>}, errors: list<array{message: string, argument: string|int|null}>, isDetectedErrors: bool}
     */
    private function checkRecipients(Zend_Db_Adapter_Abstract $db, array $post, ?RecipientScope $scope, bool $strict = true): array
    {
        // The anonymity check tells who the reviewers of the paper are: only for the users allowed to know them
        $paperId = $scope !== null && $scope->withContextReviewers ? $scope->contextPaperId : null;

        return (new RecipientListValidator(new RecipientSearchService($db), (int)RVID))->validate($post, $strict, $paperId);
    }

    /**
     * @param Episciences_Review $review
     * @param array $options
     * @return array
     */
    private function historyProcessing(Episciences_Review $review, array &$options = []): array
    {
        $docIds = [];
        $isCoiEnabled = (bool)$review->getSetting(Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED);

        $options['strict'] = $isCoiEnabled;

        if (!$isCoiEnabled) {

            if (Episciences_Auth::isSecretary()) { // // all history
                $docIds = $this->allDocIds($review);
            } else {

                $editor = new Episciences_Editor(['UID' => Episciences_Auth::getUid()]);

                if ($review->getSetting(Episciences_Review::SETTING_ENCAPSULATE_EDITORS)) {
                    try {
                        $papers = $editor->getAssignedPapers();
                    } catch (Zend_Exception $e) {
                        trigger_error($e->getMessage());
                        $papers = [];
                    }
                    $docIds = array_keys($papers);
                }
            }

        } else { // COI enabled
            $suUid = Episciences_Auth::getOriginalIdentity()->getUid();
            $loggedUid = Episciences_Auth::getUid();

            $loggedEditor = new Episciences_Editor(['UID' => $loggedUid]);

            if ($suUid !== $loggedUid) {
                $suEditor = new Episciences_Editor(['UID' => $suUid]);
                if ($suEditor->isNotAllowedToDeclareConflict()) {
                    if ($loggedEditor->isNotAllowedToDeclareConflict()) {
                        $options['strict'] = false;
                        $docIds = $this->allDocIds($review);
                    } else {
                        $docIds = $this->papersNotInConflictProcessing($loggedEditor);
                    }

                } else {
                    $docIds = $this->papersNotInConflictProcessing($suEditor);
                }

            } elseif (Episciences_Auth::isAllowedToDeclareConflict()) {

                $docIds = $this->papersNotInConflictProcessing($loggedEditor);

            } elseif (
                Episciences_Auth::isRoot() ||
                Episciences_Auth::isAdministrator(RVID, true)
            ) {
                if (!Episciences_Auth::isRoot()) {
                    $docIds = array_diff($this->allDocIds($review), Episciences_PapersManager::getDocIdsInConflitByUid($loggedUid));
                } else {
                    $options['strict'] = false;
                    $docIds = $this->allDocIds($review);
                }

            }

        }

        return $docIds;

    }

    /**
     * Check the current path for email attachments saved in session or randomly generated
     * @throws Exception
     */
    private function checkEmailAttachmentsPath(bool $isAjax): void
    {

        $currentAttachmentPath = Episciences_Tools::getAttachmentsPath();

        $subStr = substr(
            $currentAttachmentPath,
            mb_strlen(REVIEW_FILES_PATH),
            mb_strlen(Episciences_Mail_Send::ATTACHMENTS)
        );


        $isAttachments = $subStr === Episciences_Mail_Send::ATTACHMENTS;

        if (!$isAttachments) {
            Episciences_Auth::resetCurrentAttachmentsPath();
        } elseif ($isAjax) {

            if (strpos($currentAttachmentPath, Episciences_Mail_Send::FROM_MAILING) !== false) {
                Episciences_Auth::resetCurrentAttachmentsPath();
            }

        } else {

            $subStr = substr($currentAttachmentPath, mb_strlen(REVIEW_FILES_PATH . Episciences_Mail_Send::ATTACHMENTS . DIRECTORY_SEPARATOR));

            if (Episciences_Tools::startsWithNumber($subStr)) {
                Episciences_Auth::resetCurrentAttachmentsPath();
            }

        }

    }

    private function allDocIds(Episciences_Review $journal): array
    {
        // Only the identifiers are needed: do not hydrate every paper of the journal (memory)
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(T_PAPERS, ['DOCID'])->where('RVID = ?', $journal->getRvid());

        return array_map('intval', $db->fetchCol($select));
    }
}
