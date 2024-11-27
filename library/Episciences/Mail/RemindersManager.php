<?php

class Episciences_Mail_RemindersManager
{
    public const AUTHOR = 'author';
    public const REPETITION_MAP = [
        0 => 'Jamais',
        1 => 'Quotidienne',
        7 => 'Hebdomadaire',
        14 => 'Toutes les deux semaines',
        31 => 'Mensuelle'
    ];
    /**
     * @return array
     * @throws Zend_Exception
     */
    public static function getTemplates(): array
    {
        $templates = [];

        // invitation de relecture sans réponse (copie relecteur)
        self::addReminderTemplate(
            $templates,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_UNANSWERED_REVIEWER_INVITATION_REVIEWER_VERSION,
            Episciences_Mail_Reminder::TYPE_UNANSWERED_INVITATION,
            Episciences_Acl::ROLE_REVIEWER
        );

        // invitation de relecture sans réponse (copie rédacteur)
        self::addReminderTemplate(
            $templates,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_UNANSWERED_REVIEWER_INVITATION_EDITOR_VERSION,
            Episciences_Mail_Reminder::TYPE_UNANSWERED_INVITATION,
            Episciences_Acl::ROLE_EDITOR
        );

        // rappel avant deadline de relecture (copie relecteur)
        self::addReminderTemplate(
            $templates,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_BEFORE_RATING_DEADLINE_REVIEWER_VERSION,
            Episciences_Mail_Reminder::TYPE_BEFORE_REVIEWING_DEADLINE,
            Episciences_Acl::ROLE_REVIEWER
        );

        // rappel avant deadline de relecture (copie rédacteur)
        // !!! TODO : check construction de la constante sur Reminder.php (sur reminder before deadline / before rating deadline)
        self::addReminderTemplate(
            $templates,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_BEFORE_RATING_DEADLINE_EDITOR_VERSION,
            Episciences_Mail_Reminder::TYPE_BEFORE_REVIEWING_DEADLINE,
            Episciences_Acl::ROLE_EDITOR
        );

        // relance après deadline de relecture (copie relecteur)
        self::addReminderTemplate(
            $templates,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_AFTER_RATING_DEADLINE_REVIEWER_VERSION,
            Episciences_Mail_Reminder::TYPE_AFTER_REVIEWING_DEADLINE,
            Episciences_Acl::ROLE_REVIEWER
        );

        // relance après deadline de relecture (copie rédacteur)
        self::addReminderTemplate(
            $templates,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_AFTER_RATING_DEADLINE_EDITOR_VERSION,
            Episciences_Mail_Reminder::TYPE_AFTER_REVIEWING_DEADLINE,
            Episciences_Acl::ROLE_EDITOR
        );

        // rappel avant deadline de modification (copie auteur)
        self::addReminderTemplate(
            $templates,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_BEFORE_REVISION_DEADLINE_AUTHOR_VERSION,
            Episciences_Mail_Reminder::TYPE_BEFORE_REVISION_DEADLINE,
            self::AUTHOR
        );

        // rappel avant deadline de modification (copie rédacteur)
        self::addReminderTemplate(
            $templates,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_BEFORE_REVISION_DEADLINE_EDITOR_VERSION,
            Episciences_Mail_Reminder::TYPE_BEFORE_REVISION_DEADLINE,
            Episciences_Acl::ROLE_EDITOR
        );

        // relance après deadline de modification (copie auteur)
        self::addReminderTemplate(
            $templates,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_AFTER_REVISION_DEADLINE_AUTHOR_VERSION,
            Episciences_Mail_Reminder::TYPE_AFTER_REVISION_DEADLINE,
            self::AUTHOR
        );

        // relance après deadline de modification (copie rédacteur)
        self::addReminderTemplate(
            $templates,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_AFTER_REVISION_DEADLINE_EDITOR_VERSION,
            Episciences_Mail_Reminder::TYPE_AFTER_REVISION_DEADLINE,
            Episciences_Acl::ROLE_EDITOR
        );

        // pas assez de relecteurs
        self::addReminderTemplate(
            $templates,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_NOT_ENOUGH_REVIEWERS_EDITOR_VERSION,
            Episciences_Mail_Reminder::TYPE_NOT_ENOUGH_REVIEWERS,
            Episciences_Acl::ROLE_EDITOR
        );

        // Article accepté : si rien n’est fait et qu’un article reste “bloqué” à ce stade.
        self::addReminderTemplate(
            $templates,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_ARTICLE_BLOCKED_IN_ACCEPTED_STATE_EDITOR_VERSION,
            Episciences_Mail_Reminder::TYPE_ARTICLE_BLOCKED_IN_ACCEPTED_STATE,
            Episciences_Acl::ROLE_EDITOR
        );

        // Article accepté : si rien n’est fait et qu’un article reste “bloqué” à ce stade.
        self::addReminderTemplate(
            $templates,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_ARTICLE_BLOCKED_IN_ACCEPTED_STATE_EDITOR_VERSION,
            Episciences_Mail_Reminder::TYPE_ARTICLE_BLOCKED_IN_ACCEPTED_STATE,
            Episciences_Acl::ROLE_CHIEF_EDITOR
        );

        // Articles relus : si rien n’est fait et qu’un article reste “bloqué” à ce stade depuis un certain délai.
        self::addReminderTemplate(
            $templates,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_REVIEWED_ARTICLE_EDITOR_VERSION,
            Episciences_Mail_Reminder::TYPE_ARTICLE_BLOCKED_IN_REVIEWED_STATE,
            Episciences_Acl::ROLE_CHIEF_EDITOR
        );

        self::addReminderTemplate(
            $templates,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_REVIEWED_ARTICLE_EDITOR_VERSION,
            Episciences_Mail_Reminder::TYPE_ARTICLE_BLOCKED_IN_REVIEWED_STATE,
            Episciences_Acl::ROLE_EDITOR
        );

        // Nouvel article : si rien n’est fait et qu’un article reste “bloqué” à l'état initial (soumis) depuis un certain délai.
        self::addReminderTemplate(
            $templates,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_SUBMITTED_ARTICLE_EDITOR_VERSION,
            Episciences_Mail_Reminder::TYPE_ARTICLE_BLOCKED_IN_SUBMITTED_STATE,
            Episciences_Acl::ROLE_CHIEF_EDITOR
        );

        self::addReminderTemplate(
            $templates,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_SUBMITTED_ARTICLE_EDITOR_VERSION,
            Episciences_Mail_Reminder::TYPE_ARTICLE_BLOCKED_IN_SUBMITTED_STATE,
            Episciences_Acl::ROLE_EDITOR
        );

        return $templates;
    }

    /**
     * Retourne la liste des reminders de la revue
     * @param null $rvid
     * @return array
     * @throws Zend_Exception
     */
    public static function getReminders($rvid = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()
            ->from(T_MAIL_REMINDERS)
            ->where('RVID = ?', ($rvid) ? $rvid : RVID)
            ->order(new Zend_Db_Expr("TYPE,
            CASE WHEN TYPE = 0 THEN DELAY END ASC,
            CASE WHEN TYPE = 1 THEN DELAY END DESC,
            CASE WHEN TYPE = 2 THEN DELAY END ASC"));

        $result = $db->fetchAssoc($select);
        $reminders = array();
        foreach ($result as $options) {
            $reminder = new Episciences_Mail_Reminder($options);
            $reminder->loadTranslations();
            $reminders[] = $reminder;
        }

        return $reminders;
    }

    /**
     * Trouve un reminder par son id, et charge ses données (bdd + translations)
     * @param $id
     * @return Episciences_Mail_Reminder|null
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    public static function find($id): ?Episciences_Mail_Reminder
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()->from(T_MAIL_REMINDERS)->where('ID = ? ', $id);
        $result = $select->query()->fetch();

        if ($result) {
            $reminder = new Episciences_Mail_Reminder();
            $reminder->setOptions($result);
            $reminder->loadTranslations();
            return $reminder;
        } else {
            return null;
        }
    }

    /**
     * Supprime un reminder par son id
     * @param $id
     * @return bool
     */
    public static function delete($id)
    {
        // Supprimer entrée en base
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->delete(T_MAIL_REMINDERS, array('ID = ?' => $id));

        // Supprimer templates custom
        foreach (scandir(REVIEW_LANG_PATH) as $lang) {
            if (!in_array($lang, array('.', '..')) && is_dir(REVIEW_LANG_PATH . $lang)) {
                $filepath = REVIEW_LANG_PATH . $lang . '/emails/reminder_' . $id . '.phtml';
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
            }
        }

        // Supprimer traductions des sujets
        $translations = Episciences_Tools::getOtherTranslations(REVIEW_LANG_PATH, Episciences_Mail_TemplatesManager::TPL_TRANSLATION_FILE_NAME, '#^reminder_' . $id . '#');
        Episciences_Tools::writeTranslations($translations, REVIEW_LANG_PATH, Episciences_Mail_TemplatesManager::TPL_TRANSLATION_FILE_NAME);

        return true;
    }

    /**
     * @param Episciences_Mail_Reminder|null $reminder
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getForm(Episciences_Mail_Reminder $reminder = null): \Ccsd_Form
    {
        $langs = Episciences_Tools::getLanguages();
        $locale = Episciences_Tools::getLocale();

        $form = new Ccsd_Form(array(
            'id' => 'reminder_form',
            'action' => ($reminder) ? '/administratemail/savereminder?id=' . $reminder->getId() : '/administratemail/savereminder',
            'class' => 'form-horizontal'
        ));
        $form->setDecorators(array(
            array('ViewScript', array(
                'viewScript' => '/administratemail/reminder_form.phtml',
                'locale' => (array_key_exists($locale, $langs)) ? $locale : 'fr',
                'langs' => $langs)),
            'FormTinymce',
            'FormCss',
            'FormJavascript'
        ));

        // Select: Type de la relance ******************************************************
        $type = ($reminder) ? $reminder->getType() : Episciences_Mail_Reminder::TYPE_UNANSWERED_INVITATION;
        $form->addElement(new Ccsd_Form_Element_Select([
            'name' => 'type',
            'label' => 'Type',
            'multioptions' => self::getAvailableReminders(),
            'value' => $type
        ]
        ));


        // Select: Destinataire *************************************************************
        $form->addElement(new Ccsd_Form_Element_Select([
            'name' => 'recipient',
            'label' => 'Destinataire',
            'multioptions' => ($reminder) ? Episciences_Mail_Reminder::MAPPING_REMINDER_RECIPIENTS[$reminder->getType()] : Episciences_Mail_Reminder::MAPPING_REMINDER_RECIPIENTS[Episciences_Mail_Reminder::TYPE_UNANSWERED_INVITATION],
            'value' => ($reminder) ? $reminder->getRecipient() : Episciences_Acl::ROLE_REVIEWER
        ]));

        $translator = Zend_Registry::get('Zend_Translate');

        $tooltipMsg = $translator->translate("Saisir un nombre de jours (un rappel automatique pour une absence de réponse à une invitation de relecture peut être envoyé x jours après l’invitation).");
        $tooltip = '<span id="delay-info" class="lightgrey glyphicon glyphicon-question-sign" data-toggle="tooltip" data-placement="bottom" title="' . $tooltipMsg . '"></span> ';

        // Text: délai de la relance ********************************************************
        $form->addElement(new Ccsd_Form_Element_Text([
            'name' => 'delay',
            'label' => $tooltip . $translator->translate('Délai'),
            'required' => true,
            'value' => ($reminder) ? $reminder->getDelay() : null,
            'description' => $tooltipMsg
        ]));

        // Select: Répétition ******************************************************
        $form->addElement(new Ccsd_Form_Element_Select([
            'name' => 'repetition',
            'label' => 'Répétition',
            'multioptions' => self::REPETITION_MAP,
            'value' => ($reminder) ? $reminder->getRepetition() : 0
        ]));

        foreach ($langs as $code => $lang) {

            // Select: Type de template **********************************************************
            $form->addElement(new Ccsd_Form_Element_Select([
                'name' => $code . '_custom_template',
                'label' => 'Personnalisation',
                'multioptions' => array(
                    0 => "Template par défaut",
                    1 => "Template personnalisé")
            ]));

            // Input (readonly): sujet par défaut *************************************************
            $form->addElement(new Ccsd_Form_Element_Text([
                'name' => $code . '_default_subject',
                'label' => 'Sujet',
                'class' => 'form-control',
                'readonly' => 'readonly'
            ]));

            // Input : sujet personnalisé ********************************************************
            $form->addElement(new Ccsd_Form_Element_Text([
                'name' => $code . '_custom_subject',
                'label' => 'Sujet',
                'class' => 'form-control'
            ]));

            // Textarea (readonly): message *******************************************************
            $form->addElement(new Ccsd_Form_Element_Textarea([
                'name' => $code . '_default_body',
                'label' => 'Message',
                'class' => 'form-control',
                'rows' => 15,
                'readonly' => 'readonly'
            ]));

            // Textarea : message personnalisé ****************************************************
            $form->addElement(new Ccsd_Form_Element_Textarea([
                'name' => $code . '_custom_body',
                'label' => 'Message',
                'class' => 'tinymce',
                'rows' => 15
            ]));

        }

        return $form;
    }

    /**
     * @param array $allTemplates
     * @param string $templateTypeToAdd
     * @param string $reminderType
     * @param string $role
     * @throws Zend_Exception
     */
    private static function addReminderTemplate(array &$allTemplates, string $templateTypeToAdd, string $reminderType, string $role): void
    {
        $oTemplate = new Episciences_Mail_Template();
        $oTemplate->findByKey($templateTypeToAdd);
        $oTemplate->loadTranslations();
        $allTemplates[$reminderType][$role]= $oTemplate->toArray();
    }

    private static function getAvailableReminders() : array {
        //keep this list in this order
        return [
            Episciences_Mail_Reminder::TYPE_UNANSWERED_INVITATION => Episciences_Mail_Reminder::$_typeLabel[Episciences_Mail_Reminder::TYPE_UNANSWERED_INVITATION],
            Episciences_Mail_Reminder::TYPE_BEFORE_REVIEWING_DEADLINE => Episciences_Mail_Reminder::$_typeLabel[Episciences_Mail_Reminder::TYPE_BEFORE_REVIEWING_DEADLINE],
            Episciences_Mail_Reminder::TYPE_AFTER_REVIEWING_DEADLINE => Episciences_Mail_Reminder::$_typeLabel[Episciences_Mail_Reminder::TYPE_AFTER_REVIEWING_DEADLINE],
            Episciences_Mail_Reminder::TYPE_BEFORE_REVISION_DEADLINE => Episciences_Mail_Reminder::$_typeLabel[Episciences_Mail_Reminder::TYPE_BEFORE_REVISION_DEADLINE],
            Episciences_Mail_Reminder::TYPE_AFTER_REVISION_DEADLINE => Episciences_Mail_Reminder::$_typeLabel[Episciences_Mail_Reminder::TYPE_AFTER_REVISION_DEADLINE],
            Episciences_Mail_Reminder::TYPE_NOT_ENOUGH_REVIEWERS => Episciences_Mail_Reminder::$_typeLabel[Episciences_Mail_Reminder::TYPE_NOT_ENOUGH_REVIEWERS],
            Episciences_Mail_Reminder::TYPE_ARTICLE_BLOCKED_IN_ACCEPTED_STATE => Episciences_Mail_Reminder::$_typeLabel[Episciences_Mail_Reminder::TYPE_ARTICLE_BLOCKED_IN_ACCEPTED_STATE],
            Episciences_Mail_Reminder::TYPE_ARTICLE_BLOCKED_IN_SUBMITTED_STATE => Episciences_Mail_Reminder::$_typeLabel[Episciences_Mail_Reminder::TYPE_ARTICLE_BLOCKED_IN_SUBMITTED_STATE],
            Episciences_Mail_Reminder::TYPE_ARTICLE_BLOCKED_IN_REVIEWED_STATE => Episciences_Mail_Reminder::$_typeLabel[Episciences_Mail_Reminder::TYPE_ARTICLE_BLOCKED_IN_REVIEWED_STATE]
        ];
    }
}