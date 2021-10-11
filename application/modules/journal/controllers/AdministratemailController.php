<?php

class AdministratemailController extends Zend_Controller_Action
{

    /**
     * @var boolean
     */
    private $_allowedToEdit;

    public function init()
    {
        $isAllowed = Episciences_Auth::isSecretary() || Episciences_Auth::isWebmaster();

        if (!$isAllowed) {
            $review = Episciences_ReviewsManager::find(RVID);
            $review->loadSettings();
            $isAllowed = $review->getSetting(Episciences_Review::SETTING_EDITORS_CAN_EDIT_TEMPLATES);
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
    public function templatesAction()
    {

        $this->view->templates = Episciences_Mail_TemplatesManager::getList();
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

        if(!$request->isXmlHttpRequest()){
            $this->_helper->redirector->gotoUrl('/error/deny');
        }

        $params = $request->getPost();
        $id = $params['id'];

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
        $id = $request->getQuery('id');

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
            $this->_helper->FlashMessenger->setNamespace('error')->addMessage('Les modifications ont échoué');
        }

        $this->_helper->redirector->gotoUrl('/administratemail/templates');
        return;
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
        $id = $request->getQuery('id');

        $template = new Episciences_Mail_Template();
        $template->find($id);

        if ($template->delete()) {
            $this->_helper->FlashMessenger->setNamespace('success')->addMessage('Le template par défaut a été restauré');
        } else {
            $this->_helper->FlashMessenger->setNamespace('error')->addMessage('La suppression du template personnalisé a échoué');
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
            $docIds = [];

            if (!Episciences_Auth::isSecretary()) {

                $editor = new Episciences_Editor();
                $editor->find(Episciences_Auth::getUid());

                if ($review->getSetting(Episciences_Review::SETTING_ENCAPSULATE_EDITORS)) {
                    $papers = $editor->getAssignedPapers();
                    $docIds = array_keys($papers);

                } elseif ($review->getSetting(Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED)) {
                    $docIds = $this->papersNotInConflictProcessing($editor);
                    $options['isCoiEnabled'] = true;
                }

            } else {
                $docIds[] = array_keys($review->getPapers());
            }

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
     * @throws Zend_Mail_Exception
     */
    public function viewAction()
    {
        $this->_helper->layout->disableLayout();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $id = $request->getParam('id');

        $oMail = new Episciences_Mail('UTF-8');
        $oMail->find($id);
        $mail = $oMail->toArray(true);

        $this->view->mail = $mail;

        return;
    }

    /**
     * mailing module
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    public function sendAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $post = $request->getPost();
        // process form (send mail)
        if ($post && !array_key_exists('ajax', $post)) {
            $this->sendMail($post);
        } else {

            $ajax = ($request->getParam('ajax')) ? true : false;
            $uid = $request->getParam('recipient');

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

            if ($uid && is_numeric($uid)) {
                if (!$request->getParam('tmp')) {
                    $user = new Episciences_User;
                    if ($user->findWithCAS($uid)) {
                        $js_recipient = [
                            'uid' => $user->getUid(),
                            'fullname' => $user->getFullName(),
                            'username' => $user->getUsername(),
                            'mail' => $user->getEmail(),
                            'label' => $user->getFullName() . ' (' . mb_strtolower($user->getUsername()) . ') ' . '<' . $user->getEmail() . '>',
                            'htmlLabel' => '<div>' . $user->getFullName() . ' <span class="darkgrey">' . '(' . mb_strtolower($user->getUsername()) . ')' . '</span>' . '</div>'
                                . '<div class="grey">' . $user->getEmail() . '</div>'];
                    }
                } else {
                    $user = new Episciences_User_Tmp;
                    if (!empty($user->find($uid))) {
                        $user->generateScreen_name();
                        $js_recipient = [
                            'uid' => $user->getId(),
                            'fullname' => $user->getFullName(),
                            'mail' => $user->getEmail(),
                            'label' => $user->getFullName() . '<' . $user->getEmail() . '>',
                            'htmlLabel' => '<div>' . $user->getFullName() . '</div>' . '<div class="grey">' . $user->getEmail() . '</div>'
                        ];
                    }
                }
                if (isset($js_recipient)) {
                    $this->view->js_recipient = Zend_Json::encode($js_recipient);
                }
                if ($ajax && isset($js_recipient)) {
                    $form->setDefaults(['to' => $user->getFullName() . ' <' . $user->getEmail() . '>']);
                }
            }

            $this->view->form = $form;
            $this->view->ajax = $ajax;

            $js_users = [];

            $review = Episciences_ReviewsManager::find(RVID);
            $users = $review->getUsers();

            if ($users) {
                foreach ($users as $user) {
                    $js_user['uid'] = $user->getUid();
                    $js_user['fullname'] = $user->getFullName();
                    $js_user['username'] = $user->getUsername();
                    $js_user['mail'] = $user->getEmail();
                    $js_user['label'] = $user->getFullName() . ' (' . mb_strtolower($user->getUsername()) . ') ' . '<' . $user->getEmail() . '>';
                    $js_user['htmlLabel'] = '<div>' . $user->getFullName() . ' <span class="darkgrey">' . '(' . mb_strtolower($user->getUsername()) . ')' . '</span>' . '</div>'
                        . '<div class="grey">' . $user->getEmail() . '</div>';
                    $js_users[$user->getUid()] = $js_user;
                }
            }

            $this->view->js_users = Zend_Json::encode(array_values($js_users));
        }
    }

    /**
     * send a mail (process mailing form)
     * TODO: move this outside of the controller ?
     * @param array $post
     * @return bool
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    private function sendMail(array $post)
    {
        /** @var Zend_View $selfView */
        $selfView = $this->view;
        $validator = new Zend_Validate_EmailAddress();
        // set from & reply-to
        $default_from = Episciences_Auth::getFullName() . ' <' . RVCODE . '@' . DOMAIN . '>';
        $default_replyto = Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>';
        $from = Ccsd_Tools::ifsetor($post['from'], $default_from);
        $replyto = Ccsd_Tools::ifsetor($post['replyto'], $default_replyto);

        // Récupération des destinataires
        $to = (!empty(Ccsd_Tools::ifsetor($post['hidden_to']))) ? Zend_Json::decode($post['hidden_to']) : [];
        $cc = (!empty(Ccsd_Tools::ifsetor($post['hidden_cc']))) ? Zend_Json::decode($post['hidden_cc']) : [];
        $bcc = (!empty(Ccsd_Tools::ifsetor($post['hidden_bcc']))) ? Zend_Json::decode($post['hidden_bcc']) : [];

        // récupération du contenu
        $subject = (!empty(Ccsd_Tools::ifsetor($post['subject']))) ? $post['subject'] : Zend_Registry::get('Zend_Translate')->translate('Aucun sujet');
        $content = Ccsd_Tools::clear_nl(Ccsd_Tools::ifsetor($post['content']));

        // Contrôle des erreurs
        $errors = [];
        if (empty($to) && empty($cc) && empty($bcc)) {
            $errors[] = "Veuillez saisir au moins un destinataire";
        }

        if (!empty($errors)) {
            $message = '<p><strong>' . $selfView->translate("Votre message n'a pas pu être envoyé.") . '</strong></p>';
            foreach ($errors as $error) {
                $message .= '<div>' . $selfView->translate($error) . '</div>';
            }
            $this->_helper->FlashMessenger->setNamespace('error')->addMessage($message);
            $this->_helper->redirector->gotoUrl('administratemail/send');
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
                error_log(RVCODE . 'FROM_MAILING_BAD_CC_MAIL: ' . $email);
            }
        }

        foreach ($bcc as $recipient) {

            $email = Episciences_Tools::postMailValidation($recipient['value'])['email'];

            if ($validator->isValid($email)) {
                $mail->addBcc($email);
            } else {
                error_log(RVCODE . 'FROM_MAILING_BAD_BCC_MAIL: ' . $email);
            }
        }

        if (isset($post['attachments'])) {
            // Errors : si une erreur s'est produite lors de la validation d'un fichier attaché par exemple(voir es.fileupload.js)
            $attachments = Episciences_Tools::arrayFilterAttachments($post['attachments']);
            $path = REVIEW_FILES_PATH . 'attachments/';
            foreach ($attachments as $attachment) {
                $filepath = $path . $attachment;
                if (file_exists($filepath)) {
                    $mail->addAttachedFile($filepath);
                }
            }
        }


        if ($mail->writeMail()) {

            if (isset($post['docid'])) {
                /** @var Episciences_Paper $paper */
                $paper = Episciences_PapersManager::get((int)$post['docid']);
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
                }
            }

            $message = '<strong>' . $selfView->translate("Votre message a bien été envoyé.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);

        } else {

            $message = '<strong>' . $selfView->translate("Une erreur interne s'est produite, veuillez recommencer.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('error')->addMessage($message);

        }

        $this->_helper->redirector->gotoUrl('administratemail/send');
        return true;

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
                'mail' => $user->getEmail()
            ];

            $user->loadRoles();

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
        $isExistTemplateForThisRecipient = array_key_exists($recipient, $templates[$type]) ;

        if(!$isExistTemplateForThisRecipient){
            error_log('reminder (type = ' . $type . ') not saved: no template defined for ' . $recipient .  'recipient');
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
        foreach ($papers as $paper){
            $versionIds = $paper->getVersionsIds();

            foreach ($versionIds as $docId){
                $docIds[] = $docId;
            }

        }

        return $docIds;

    }

}
