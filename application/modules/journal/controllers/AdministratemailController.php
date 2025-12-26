<?php

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
            $this->_helper->redirector->gotoUrl('/error/deny');
        }

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if (!$request->isXmlHttpRequest()) {
            $this->_helper->redirector->gotoUrl('/error/deny');
        }

        $params = $request->getPost();
        $id = (int)$params['id'];

        $oTemplate = new Episciences_Mail_Template();
        $oTemplate->find($id);

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
            $this->_helper->redirector->gotoUrl('/error/deny');
            return;
        }

        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $id = (int)$request->getQuery('id');

        $template = new Episciences_Mail_Template();
        $template->find($id);

        if (!$template) {
            throw new Zend_Exception("Ce template n'existe pas");
        }

        $post = $request->getPost();
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
            $this->_helper->redirector->gotoUrl('/error/deny');
        }

        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $id = (int)$request->getQuery('id');

        $template = new Episciences_Mail_Template();

        if ($template->find($id) && $template->delete()) { // to avoid this type of exception : Syntax error or access violation: error in your SQL : DELETE FROM `MAIL_TEMPLATE` WHERE (ID = )
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

            $mail = $oMail->find($id) ? $oMail->toArray(true) : [];

            $this->view->mail = $mail;


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

        if ($ajax) {

            $this->_helper->layout->disableLayout();
            $to_enabled = false;
            $button_enabled = false;
        } else {

            $to_enabled = true;
            $button_enabled = true;
        }

        $form = Episciences_Mail_Send::getForm(null, $button_enabled, $to_enabled, $docId);

        // process form (send mail)
        if ($post && !array_key_exists('ajax', $post)) {

            $post['sender'] = Episciences_Auth::getUid();

            if ($this->checkRecipients($post)['isDetectedErrors']) {
                $form->setDefaults($post);
            }

            $result = $this->sendMail($post);

            if ($result) {
                $this->_helper->layout->disableLayout();
                $this->_helper->viewRenderer->setNoRender();
                echo $result;
                return;
            }

        } else {

            $uid = $request->getParam('recipient');

            if ($uid && is_numeric($uid)) {
                if (!$request->getParam('tmp')) {
                    $user = new Episciences_User;
                    if ($user->findWithCAS($uid)) {
                        $js_recipient = [
                            'uid' => $user->getUid(),
                            'fullname' => $user->getFullName(),
                            'screenName' => $user->getScreenName(),
                            'username' => $user->getUsername(),
                            'mail' => $user->getEmail(),
                            'label' => $user->getScreenName() . ' (' . mb_strtolower($user->getUsername()) . ') ' . '<' . $user->getEmail() . '>',
                            'htmlLabel' => '<div>' . $user->getScreenName() . ' <span class="darkgrey">' . '(' . mb_strtolower($user->getUsername()) . ')' . '</span>' . '</div>'
                                . '<div class="grey">' . $user->getEmail() . '</div>'
                        ];
                    }
                } else {
                    $user = new Episciences_User_Tmp;
                    if (!empty($user->find($uid))) {
                        $user->generateScreen_name();
                        $js_recipient = [
                            'uid' => $user->getId(),
                            'screenName' => $user->getScreenName(),
                            'fullname' => $user->getFullName(),
                            'mail' => $user->getEmail(),
                            'label' => $user->getScreenName() . '<' . $user->getEmail() . '>',
                            'htmlLabel' => '<div>' . $user->getScreenName() . '</div>' . '<div class="grey">' . $user->getEmail() . '</div>'
                        ];
                    }
                }
                if (isset($js_recipient)) {
                    $this->view->js_recipient = Zend_Json::encode($js_recipient);
                }
                if ($ajax && isset($js_recipient)) {
                    $form->setDefaults(['to' => $user->getScreenName() . ' <' . $user->getEmail() . '>']);
                }
            }

        }

        $this->view->js_users = Zend_Json::encode(array_values($this->compileUsers()));

        $this->view->form = $form;
        $this->view->ajax = $ajax;

    }

    /**
     * send a mail (process mailing form)
     * TODO: move this outside of the controller ?
     * @param array $post
     * @return string|void
     * @throws Exception
     */
    private function sendMail(array $post)
    {
        $paper = null;

        $isInModal = isset($post['in_modal']) && $post['in_modal']; // true: sent from the paper administration page

        /** @var Zend_View $selfView */
        $selfView = $this->view;

        $checkedRecipients = $this->checkRecipients($post);

        // Contrôle des erreurs
        $errors = [];
        $isEmptyMail = empty($post['subject']) && empty($post['content']) && empty($post[Episciences_Mail_Send::ATTACHMENTS]);

        if ($checkedRecipients['isDetectedErrors']) {
            $errors[] = "Veuillez saisir au moins un destinataire";
        }

        if (!empty($errors) || $isEmptyMail) {

            Episciences_Tools::deleteDir(Episciences_Tools::getAttachmentsPath());

            $message = '<p><strong>' . $selfView->translate("Votre message n'a pas pu être envoyé :") . '</strong></p>';

            if (!empty($errors)) {

                foreach ($errors as $error) {
                    $message .= '<div>' . $selfView->translate($error) . '</div>';
                }

            } else {
                $message .= $selfView->translate('Corps du message vide.');
            }

            if ($isInModal) {
                return $message;
            }

            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            return;
        }

        $validator = new Zend_Validate_EmailAddress();
        // set from & reply-to
        $default_from = Episciences_Auth::getFullName() . ' <' . RVCODE . '@' . DOMAIN . '>';
        $default_replyto = Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>';
        $from = Ccsd_Tools::ifsetor($post['from'], $default_from);
        $replyto = Ccsd_Tools::ifsetor($post['replyto'], $default_replyto);

        // Récupération des destinataires
        $to = $checkedRecipients['recipients']['to'];
        $cc = $checkedRecipients['recipients']['cc'];
        $bcc = $checkedRecipients['recipients']['bcc'];

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

        if ($from) {

            $postMailValidation = Episciences_Tools::postMailValidation($from);
            $email = $postMailValidation['email'];
            $name = $postMailValidation['name'];

            if ($validator->isValid($email)) {
                $mail->setFrom($email, $name);
            }
        }

        if ($replyto) {

            $postMailValidation = Episciences_Tools::postMailValidation($replyto);
            $email = $postMailValidation['email'];
            $name = $postMailValidation['name'];

            if ($validator->isValid($email)) {
                $mail->setReplyTo($email, $name);
            }
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

        if (isset($post['docid'])) {
            /** @var Episciences_Paper $paper */
            $paper = Episciences_PapersManager::get((int)$post['docid']);
            $mail->setDocid($paper->getDocid());
        }

        if (isset($post['sender'])) {
            $mail->setUid($post['sender']);
        }

        if (isset($post[Episciences_Mail_Send::ATTACHMENTS])) {
            // Errors : si une erreur s'est produite lors de la validation d'un fichier attaché par exemple(voir es.fileupload.js)
            $attachments = Episciences_Tools::arrayFilterEmptyValues($post[Episciences_Mail_Send::ATTACHMENTS]);
            $path = Episciences_Tools::getAttachmentsPath();
            foreach ($attachments as $attachment) {
                $filepath = $path . $attachment;
                if (file_exists($filepath)) {
                    $mail->addAttachedFile($filepath);
                }
            }
        }


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

            if (!$isInModal) {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            }

        }

        if ($isInModal) {
            return $message;
        }

        $this->_helper->redirector->gotoUrl('administratemail/send');

    }

    /**
     * Récupère une liste de destinataires en fonction du type choisi (relecteurs, membres...)
     */
    public function getrecipientsAction()
    {

        $this->_helper->getHelper('layout')->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $request = $this->getRequest();
        $type = $request->getParam('type');

        $result = [];
        $recipients = Episciences_UsersManager::getUsersWithRoles($type);
        /** @var Episciences_User $recipient */
        foreach ($recipients as $recipient) {
            $user = [];
            $user['uid'] = $recipient->getUid();
            $user['name'] = $recipient->getFullName();
            $user['mail'] = $recipient->getEmail();
            $user['label'] = $user['name'] . ' (' . $recipient->getUsername() . ') &lt;' . $user['mail'] . '&gt;';
            $result[] = $user;
        }

        echo Zend_Json::encode(array_values($result));
    }


    public function getcontactsAction()
    {
        $this->_helper->layout->disableLayout();
        $request = $this->getRequest();

        // fetch all contacts
        $contacts = Episciences_UsersManager::getUsersWithRoles();

        // sort contacts by name
        $contacts = Episciences_UsersManager::sortByName($contacts);

        $roles = [
            'chief_editors' => Episciences_Acl::ROLE_CHIEF_EDITOR,
            'guest_editors' => Episciences_Acl::ROLE_GUEST_EDITOR,
            'editors' => Episciences_Acl::ROLE_EDITOR,
            'secretaries' => Episciences_Acl::ROLE_SECRETARY,
            'webmasters' => Episciences_Acl::ROLE_WEBMASTER,
            'reviewers' => Episciences_Acl::ROLE_REVIEWER,
            'authors' => Episciences_Acl::ROLE_AUTHOR,
            'members' => Episciences_Acl::ROLE_MEMBER
        ];

        $js_contacts = [];

        /** @var Episciences_User $user */
        // loop through all contacts, and sort them according to their roles
        foreach ($contacts as $user) {

            // prepare js object
            $js_user = [
                'uid' => $user->getUid(),
                'username' => $user->getUsername(),
                'firstname' => $user->getFirstname(),
                'lastname' => $user->getLastname(),
                'fullname' => $user->getFullName(),
                'screen_name' => $user->getScreenName(),
                'mail' => $user->getEmail(),
                'role' => $user->getRoles(),
            ];

            // OPTIMIZATION: Removed redundant loadRoles() call - roles already loaded by getUsersWithRoles() with batch loading
            // This eliminates N queries (one per user) as roles are already available in memory

            foreach ($roles as $roleName => $roleCode) {
                // all contacts
                $js_contacts['contacts'][$user->getUid()] = $js_user;
                // sorted by role
                if ($user->hasRole($roleCode)) {
                    $js_contacts[$roleName][$user->getUid()] = $js_user;
                }
            }
        }

        $this->view->target = $request->getParam('target');
        $this->view->contacts = $js_contacts['contacts'];
        foreach ($js_contacts as $role => $contacts) {
            $varName = 'js_' . $role;
            $this->view->$varName = Zend_Json::encode($contacts);

        }

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
            $reminder = Episciences_Mail_RemindersManager::find($id);
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
        $recipient = $request->getParam('recipient');
        $type = $request->getParam('type');
        $templates = Episciences_Mail_RemindersManager::getTemplates();
        $isExistTemplateForThisRecipient = array_key_exists($recipient, $templates[$type]);

        if (!$isExistTemplateForThisRecipient) {
            trigger_error('reminder (type = ' . $type . ') not saved: no template defined for ' . $recipient . 'recipient');
            return;
        }

        $options = [
            'id' => $request->getParam('id'),
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
        $reminder->save();
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
        $id = $request->getParam('id');

        if (Episciences_Mail_RemindersManager::delete($id)) {
            return true;
        }

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
     * @param array $post
     * @param bool $strict [default true: the main recipient ($to) is required ]
     * @return array
     * @throws Zend_Json_Exception
     */
    private function checkRecipients(array $post, bool $strict = true): array
    {

        $to = (!empty(Ccsd_Tools::ifsetor($post['hidden_to']))) ? Zend_Json::decode($post['hidden_to']) : [];
        $cc = (!empty(Ccsd_Tools::ifsetor($post['hidden_cc']))) ? Zend_Json::decode($post['hidden_cc']) : [];
        $bcc = (!empty(Ccsd_Tools::ifsetor($post['hidden_bcc']))) ? Zend_Json::decode($post['hidden_bcc']) : [];

        return [
            'recipients' => [
                'to' => $to,
                'cc' => $cc,
                'bcc' => $bcc

            ],

            'isDetectedErrors' => $strict ? empty($to) : (empty($to) && empty($cc) && empty($bcc))
        ];

    }

    /**
     * @return array
     * @throws Zend_Db_Statement_Exception
     */

    private function compileUsers(): array
    {
        $compiledUsers = [];

        // OPTIMIZATION: Use eager loading with CAS batch (eliminates N queries USER + N queries CAS)
        // Before: Review::getUsers() → getUsersWithRoles() → N×findWithCAS() = 2+2N queries
        // After: getUsersWithRolesEagerCAS() = 2 queries (1 JOIN USER+ROLES + 1 batch CAS)
        // Performance: 99.5% reduction for 200 users (402 queries → 2 queries)
        $users = Episciences_UsersManager::getUsersWithRolesEagerCAS();

        if ($users) {
            foreach ($users as $user) {
                $cUser['uid'] = $user->getUid();
                $cUser['fullname'] = $user->getFullName();
                $cUser['username'] = $user->getUsername();
                $cUser['mail'] = $user->getEmail();
                $cUser['label'] = $user->getFullName() . ' (' . mb_strtolower($user->getUsername()) . ') ' . '<' . $user->getEmail() . '>';
                $cUser['htmlLabel'] = '<div>' . $user->getFullName() . ' <span class="darkgrey">' . '(' . mb_strtolower($user->getUsername()) . ')' . '</span>' . '</div>'
                    . '<div class="grey">' . $user->getEmail() . '</div>';

                $compiledUsers[$user->getUid()] = $cUser;
            }
        }

        return $compiledUsers;

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

        $docIds = [];

        try {
            $docIds = array_keys($journal->getPapers());
        } catch (Zend_Db_Select_Exception $e) {
            trigger_error($e->getMessage());
        }

        return $docIds;

    }
}
