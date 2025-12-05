<?php

use Episciences\Trait\UrlBuilder;

class Episciences_Mail_Reminder
{
    use UrlBuilder;

    // event types triggering a reminder
    public const TYPE_UNANSWERED_INVITATION = 0;        // unanswered invitation
    public const TYPE_BEFORE_REVIEWING_DEADLINE = 1;    // before rewiewing deadline
    public const TYPE_AFTER_REVIEWING_DEADLINE = 2;    // after rewiewing deadline
    public const TYPE_BEFORE_REVISION_DEADLINE = 3;    // before revision deadline
    public const TYPE_AFTER_REVISION_DEADLINE = 4;        // after revision deadline
    public const TYPE_NOT_ENOUGH_REVIEWERS = 5;        // not enough reviewers
    public const TYPE_ARTICLE_BLOCKED_IN_ACCEPTED_STATE = 6; // article accepté : si rien n’est fait et qu’un article reste “bloqué” à ce stade.
    public const TYPE_ARTICLE_BLOCKED_IN_SUBMITTED_STATE = 7;
    public const TYPE_ARTICLE_BLOCKED_IN_REVIEWED_STATE = 8;
    public const DEFAULT_WAITING_TIME = 30; // days

    // reminder types labels
    public static array $_typeLabel = [
        self::TYPE_UNANSWERED_INVITATION => 'Invitation de relecteur sans réponse',
        self::TYPE_BEFORE_REVIEWING_DEADLINE => 'Rappel avant date de livraison de relecture',
        self::TYPE_AFTER_REVIEWING_DEADLINE => 'Relance après date de livraison de relecture',
        self::TYPE_BEFORE_REVISION_DEADLINE => 'Rappel avant date limite de modification',
        self::TYPE_AFTER_REVISION_DEADLINE => 'Relance après date limite de modification',
        self::TYPE_NOT_ENOUGH_REVIEWERS => 'Pas assez de relecteurs',
        self::TYPE_ARTICLE_BLOCKED_IN_ACCEPTED_STATE => "Article bloqué à l'état accepté",
        self::TYPE_ARTICLE_BLOCKED_IN_SUBMITTED_STATE => "Article bloqué à l'état initial (soumis)",
        self::TYPE_ARTICLE_BLOCKED_IN_REVIEWED_STATE => "Article bloqué à l'état relu"
    ];

    // reminder types keys
    public static array $_typeKey = [
        self::TYPE_UNANSWERED_INVITATION => 'reminder_unanswered_reviewer_invitation',
        self::TYPE_BEFORE_REVIEWING_DEADLINE => 'reminder_before_rating_deadline',
        self::TYPE_AFTER_REVIEWING_DEADLINE => 'reminder_after_rating_deadline',
        self::TYPE_BEFORE_REVISION_DEADLINE => 'reminder_before_revision_deadline',
        self::TYPE_AFTER_REVISION_DEADLINE => 'reminder_after_revision_deadline',
        self::TYPE_NOT_ENOUGH_REVIEWERS => 'reminder_not_enough_reviewers',
        self::TYPE_ARTICLE_BLOCKED_IN_ACCEPTED_STATE => 'reminder_article_blocked_in_accepted_state',
        self::TYPE_ARTICLE_BLOCKED_IN_SUBMITTED_STATE => 'reminder_submitted_article',
        self::TYPE_ARTICLE_BLOCKED_IN_REVIEWED_STATE => 'reminder_reviewed_article',
    ];

    public const MAPPING_REMINDER_RECIPIENTS = [
        self::TYPE_UNANSWERED_INVITATION => [Episciences_Acl::ROLE_REVIEWER => Episciences_Acl::ROLE_REVIEWER, Episciences_Acl::ROLE_EDITOR => Episciences_Acl::ROLE_EDITOR],
        self::TYPE_BEFORE_REVIEWING_DEADLINE => [Episciences_Acl::ROLE_REVIEWER => Episciences_Acl::ROLE_REVIEWER, Episciences_Acl::ROLE_EDITOR => Episciences_Acl::ROLE_EDITOR],
        self::TYPE_AFTER_REVIEWING_DEADLINE => [Episciences_Acl::ROLE_REVIEWER => Episciences_Acl::ROLE_REVIEWER, Episciences_Acl::ROLE_EDITOR => Episciences_Acl::ROLE_EDITOR],
        self::TYPE_BEFORE_REVISION_DEADLINE => [Episciences_Acl::ROLE_AUTHOR => Episciences_Acl::ROLE_AUTHOR, Episciences_Acl::ROLE_EDITOR => Episciences_Acl::ROLE_EDITOR],
        self::TYPE_AFTER_REVISION_DEADLINE => [Episciences_Acl::ROLE_AUTHOR => Episciences_Acl::ROLE_AUTHOR, Episciences_Acl::ROLE_EDITOR => Episciences_Acl::ROLE_EDITOR],
        self::TYPE_NOT_ENOUGH_REVIEWERS => [Episciences_Acl::ROLE_EDITOR => Episciences_Acl::ROLE_EDITOR],
        self::TYPE_ARTICLE_BLOCKED_IN_ACCEPTED_STATE => [Episciences_Acl::ROLE_CHIEF_EDITOR => Episciences_Acl::ROLE_CHIEF_EDITOR, Episciences_Acl::ROLE_EDITOR => Episciences_Acl::ROLE_EDITOR],
        self::TYPE_ARTICLE_BLOCKED_IN_SUBMITTED_STATE => [Episciences_Acl::ROLE_CHIEF_EDITOR => Episciences_Acl::ROLE_CHIEF_EDITOR, Episciences_Acl::ROLE_EDITOR => Episciences_Acl::ROLE_EDITOR],
        self::TYPE_ARTICLE_BLOCKED_IN_REVIEWED_STATE => [Episciences_Acl::ROLE_CHIEF_EDITOR => Episciences_Acl::ROLE_CHIEF_EDITOR, Episciences_Acl::ROLE_EDITOR => Episciences_Acl::ROLE_EDITOR]
    ];

    private $_id;
    private $_rvid;
    private $_type;
    private $_recipient;
    private $_delay;
    private $_repetition;
    private $_custom;

    private $_locale;
    private $_name;
    private $_subject;
    private $_body;

    private $_deadline;
    private $_recipients;

    protected $_defaultLanguage = 'en';

    /**
     * Episciences_Mail_Reminder constructor.
     * @param array|null $options
     * @throws Zend_Exception
     */
    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
        if (!$this->getLocale()) {
            $this->setLocale(Episciences_Tools::getLocale());
        }
    }

    /**
     * @param array $options
     */
    public function setOptions(array $options): void
    {
        $methods = get_class_methods($this);

        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst(strtolower($key));
            if (in_array($method, $methods)) {
                $this->$method($value);
            }
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'rvid' => $this->getRvid(),
            'delay' => $this->getDelay(),
            'repetition' => $this->getRepetition(),
            'recipient' => $this->getRecipient(),
            'type' => $this->getType(),
            'name' => $this->getNameTranslations(),
            'subject' => $this->getSubjectTranslations(),
            'body' => $this->getBodyTranslations(),
            'custom' => $this->getCustom()
        ];
    }

    /**
     * @throws Zend_Exception
     */
    public function loadTranslations(): bool
    {
        // Special processing: additional time (@see self::DEFAULT_WAITING_TIME) to be added to the delay.
        $reminderConst = [
            'TYPE_REMINDER_REVIEWED_ARTICLE_EDITOR_VERSION',
            'TYPE_REMINDER_REVIEWED_ARTICLE_CHIEF_EDITOR_VERSION',
            'TYPE_REMINDER_SUBMITTED_ARTICLE_CHIEF_EDITOR_VERSION',
            'TYPE_REMINDER_SUBMITTED_ARTICLE_EDITOR_VERSION'
        ];
        $langs = Episciences_Tools::getLanguages();
        $translator = Zend_Registry::get('Zend_Translate');
        $translations = [];

        $review = Episciences_ReviewsManager::find($this->getRvid());
        $review_filepath = realpath(APPLICATION_PATH . '/../data') . '/' . $review->getCode() . '/';

        // load default template
        $template = new Episciences_Mail_Template(['rvcode' => $review->getCode()]);

        if (!isset(self::$_typeKey[$this->getType()])) {
            return false;
        }

        $constant_name = 'Episciences_Mail_TemplatesManager::TYPE_' . strtoupper(self::$_typeKey[$this->getType()] . '_' . $this->getRecipient() . '_version');
        $cleanedConstName = str_replace('Episciences_Mail_TemplatesManager::', '', $constant_name);

        if (defined($constant_name)) {
            $templateConst = constant($constant_name);
        } else {
            trigger_error($constant_name . ' not defined');
            return false;

        }

        $template->findByKey($templateConst);
        $template->loadTranslations($langs, $review->getCode());

        $delay = in_array($cleanedConstName, $reminderConst, true) ? ($this->getDelay() + self::DEFAULT_WAITING_TIME) : $this->getDelay();

        foreach ($langs as $code => $lang) {
            // Reminder name
            if ($translator->isTranslated(self::$_typeLabel[$this->getType()], false, $code)) {
                $name = $translator->translate(self::$_typeLabel[$this->getType()], $code);
            } else {
                $name = self::$_typeLabel[$this->getType()];
            }
            $name .= ' - ' . $translator->translate('copie destinée au ' . mb_strtolower($translator->translate($this->getRecipient(), 'fr'), 'utf-8'), $code);
            $name .= sprintf(' (%s %s | %s%s %s)', $delay, $translator->translate(array('jour', 'jours', $this->getDelay()), $code), lcfirst($translator->translate('Répétition', $code)), $translator->translate(' :', $code), lcfirst($translator->translate(Episciences_Mail_RemindersManager::REPETITION_MAP[(int)$this->getRepetition()], $code)));
            $translations['name'][$code] = $name;

            // Reminder Subject & Body
            // check if a custom version of the reminder has been set
            $filepath = $review_filepath . 'languages/' . $code . '/emails/reminder_' . $this->getId() . '.phtml';
            if (file_exists($filepath)) {
                $translations['custom'][$code] = 1;
                $translations['body'][$code] = file_get_contents($filepath);
                $translations['subject'][$code] = $translator->translate('reminder_' . $this->getId() . Episciences_Mail_TemplatesManager::SUFFIX_TPL_SUBJECT, $code);
            } else {
                // else use default template
                $translations['custom'][$code] = 0;
                $translations['subject'][$code] = $template->getSubject($code);
                $translations['body'][$code] = $template->getBody($code);
            }
        }

        if (!empty($translations)) {
            $this->setCustom($translations['custom']);
            $this->setName($translations['name']);
            $this->setSubject($translations['subject']);
            $this->setBody($translations['body']);
        }

        return true;
    }

    /**
     * @throws Zend_Db_Adapter_Exception
     */
    public function save(): void
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $values = [
            'RVID' => $this->getRvid(),
            'TYPE' => $this->getType(),
            'RECIPIENT' => $this->getRecipient(),
            'DELAY' => $this->getDelay(),
            'REPETITION' => $this->getRepetition()
        ];

        // Enregistrement en base
        if ($this->getId()) {
            $edit = true;
            $db->update(T_MAIL_REMINDERS, $values, array('ID = ?' => $this->getId()));
        } else {
            $edit = false;
            $db->insert(T_MAIL_REMINDERS, $values);
            $this->setId($db->lastInsertId());
        }

        $key = 'reminder_' . $this->getId();

        // fetch translation file
        $translations = Episciences_Tools::getOtherTranslations(REVIEW_LANG_PATH, Episciences_Mail_TemplatesManager::TPL_TRANSLATION_FILE_NAME, '#^' . $key . '#');

        foreach ($this->getCustom() as $lang => $custom) {
            $path = REVIEW_LANG_PATH . $lang . '/emails/';
            if (!file_exists($path) && !mkdir($path) && !is_dir($path)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
            }
            $filename = $key . '.phtml';
            if ($custom == 1) {
                // write file (body)
                file_put_contents($path . $filename, $this->getBody($lang));

                // subject translations
                $translations[$lang][$key . Episciences_Mail_TemplatesManager::SUFFIX_TPL_SUBJECT] = $this->getSubject($lang);
            } else {
                if ($edit && file_exists($path . $filename)) {
                    unlink($path . $filename);
                }
            }
        }

        // update translation file
        Episciences_Tools::writeTranslations($translations, REVIEW_LANG_PATH, Episciences_Mail_TemplatesManager::TPL_TRANSLATION_FILE_NAME);

        echo true;
    }

    /**
     * load recipients list
     * @param bool $debug if debug is true, each query is displayed
     * @param mixed $date if date is not null, reminders are loaded for this specific date (default date is today)
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    public function loadRecipients(bool $debug = false, $date = null): void
    {
        $date = ($date) ? "'" . $date . "'" : 'CURDATE()';
        $recipients = [];

        $filters = [
            Episciences_Paper::STATUS_PUBLISHED,
            Episciences_Paper::STATUS_REFUSED,
            Episciences_Paper::STATUS_OBSOLETE,
            Episciences_Paper::STATUS_REMOVED,
            Episciences_Paper::STATUS_DELETED,
            Episciences_Paper::STATUS_ABANDONED,
        ];

        if (
            in_array(
                $this->getType(),
                [self::TYPE_AFTER_REVISION_DEADLINE, self::TYPE_BEFORE_REVISION_DEADLINE],
                true)
        ) {
            $filters = array_merge($filters, [Episciences_Paper::STATUS_ACCEPTED]);
        }

        $revisionStatus = [
            Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION, Episciences_Paper::STATUS_WAITING_FOR_MAJOR_REVISION
        ];

        $filtersToIgnoreReminders = array_merge($filters, $revisionStatus, Episciences_Paper::ACCEPTED_SUBMISSIONS);

        switch ($this->getType()) {

            // After author revision deadline
            case self::TYPE_AFTER_REVISION_DEADLINE:
                $recipients = $this->getAfterRevisionDeadlineRecipients($debug, $date, $filters);
                break;

            // Before author revision deadline
            case self::TYPE_BEFORE_REVISION_DEADLINE:
                $recipients = $this->getBeforeRevisionDeadlineRecipients($debug, $date, $filters);
                break;

            // Editor did not assign enough reviewers to the paper
            case self::TYPE_NOT_ENOUGH_REVIEWERS:
                $recipients = $this->getNotEnoughReviewersRecipients($debug, $date, $filtersToIgnoreReminders);
                break;

            // Unanswered reviewer invitation
            case self::TYPE_UNANSWERED_INVITATION:
                $recipients = $this->getUnansweredInvitationRecipients($debug, $date, $filtersToIgnoreReminders);
                break;

            // Before reviewer rating deadline
            case self::TYPE_BEFORE_REVIEWING_DEADLINE:
                $recipients = $this->getBeforeReviewingDeadlineRecipients($debug, $date, $filtersToIgnoreReminders);
                break;

            // After reviewer rating deadline
            case self::TYPE_AFTER_REVIEWING_DEADLINE:
                $recipients = $this->getAfterReviewingDeadlineRecipients($debug, $date, $filtersToIgnoreReminders);
                break;

            // article blocked at accepted state
            case self::TYPE_ARTICLE_BLOCKED_IN_ACCEPTED_STATE:
            case self::TYPE_ARTICLE_BLOCKED_IN_SUBMITTED_STATE:
            case self::TYPE_ARTICLE_BLOCKED_IN_REVIEWED_STATE:

                $time = self::DEFAULT_WAITING_TIME;
                $currentState = Episciences_Paper::STATUS_SUBMITTED;

                if ((int)$this->getType() === self::TYPE_ARTICLE_BLOCKED_IN_ACCEPTED_STATE) {
                    $time = 0;
                    $currentState = Episciences_Paper::STATUS_ACCEPTED;
                } elseif ((int)$this->getType() === self::TYPE_ARTICLE_BLOCKED_IN_REVIEWED_STATE) {
                    $currentState = Episciences_Paper::STATUS_REVIEWED;
                }

                try {
                    $recipients = $this->getArticlesBlockedAtCurrentState($currentState, $debug, $date, $time);
                } catch (Exception $e) {
                    trigger_error($e->getMessage());
                }
                break;

            // default: no recipients
            default:
                break;
        }

        $this->setRecipients($recipients);
    }

    /**
     * returns recipients list
     * @return mixed
     */
    public function getRecipients()
    {
        return $this->_recipients;
    }

    /**
     * Sets reminder id
     * @param $id
     * @return $this
     */
    public function setId($id): self
    {
        $this->_id = $id;
        return $this;
    }

    /**
     * Sets reminder rvid
     * @param $rvid
     * @return $this
     */
    public function setRvid($rvid): self
    {
        $this->_rvid = $rvid;
        return $this;
    }

    /**
     * Sets the reminder type
     * @param $type
     * @return $this
     */
    public function setType($type): self
    {
        $this->_type = $type;
        return $this;
    }

    /**
     * Defines if template is custom or default
     * @param $custom
     * @return $this
     */
    public function setCustom($custom): self
    {
        $this->_custom = $custom;
        return $this;
    }

    /**
     * Sets the delay before the reminder is sent (after an event determined by its type)
     * @param $delay
     * @return $this
     */
    public function setDelay($delay): self
    {
        $this->_delay = $delay;
        return $this;
    }

    /**
     * Sets the frequency between each reminder sending
     * @param $repetition
     * @return $this
     */
    public function setRepetition($repetition): self
    {
        $this->_repetition = $repetition;
        return $this;
    }

    /**
     * set recipient type (editor, reviewer, author...)
     * @param $recipient
     * @return $this
     */
    public function setRecipient($recipient): self
    {
        $this->_recipient = $recipient;
        return $this;
    }

    /**
     * @param $body
     * @return $this
     */
    public function setBody($body): self
    {
        $this->_body = $body;
        return $this;
    }

    /**
     * @param $name
     * @return $this
     */
    public function setName($name): self
    {
        $this->_name = $name;
        return $this;
    }

    /**
     * @param $subject
     * @return $this
     */
    public function setSubject($subject): self
    {
        $this->_subject = $subject;
        return $this;
    }

    /**
     * @param $locale
     * @return $this
     * @throws Zend_Exception
     */
    public function setLocale($locale): self
    {
        $availableLanguages = Episciences_Tools::getLanguages();

        if (array_key_exists($locale, $availableLanguages)) {
            // La langue choisie est disponible
            $this->_locale = $locale;
        } // Si elle n'est pas dispo, on regarde si la langue par défaut est dispo
        elseif (array_key_exists($this->_defaultLanguage, $availableLanguages)) {
            $this->_locale = $this->_defaultLanguage;
        } // Sinon, on prend la première langue dispo dans l'appli
        else {
            reset($availableLanguages);
            $this->_locale = key($availableLanguages);
        }
        return $this;
    }

    /**
     * @param $recipients
     * @return $this
     */
    public function setRecipients($recipients): self
    {
        $this->_recipients = $recipients;
        return $this;
    }

    /**
     * @param $deadline
     * @return $this
     */
    public function setDeadline($deadline): self
    {
        $this->_deadline = $deadline;
        return $this;
    }


    // GETTERS **********************************************************

    /**
     * Returns reminder id
     * @return mixed
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * Returns reminder rvid
     * @return mixed
     */
    public function getRvid()
    {
        return $this->_rvid;
    }

    /**
     * Returns reminder type
     * @return mixed
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * Returns the delay before the reminder is sent
     * @return mixed
     */
    public function getDelay()
    {
        return $this->_delay;
    }

    /**
     * Returns the frequency between each reminder sending
     * /* null: never, 1 day, 1 week, 2 week, 1 month
     * @return mixed
     */
    public function getRepetition()
    {
        return $this->_repetition;
    }

    /**
     * returns recipient type (reviewer, editor, author...)
     * @return mixed
     */
    public function getRecipient()
    {
        return $this->_recipient;
    }

    /**
     * @return mixed
     */
    public function getLocale()
    {
        return $this->_locale;
    }

    /**
     * @return mixed
     */
    public function getNameTranslations()
    {
        return $this->_name;
    }

    /**
     * Renvoie le nom du reminder dans la langue voulue, ou la langue par défaut
     * @param null $lang
     * @return mixed|null
     */
    public function getName($lang = null)
    {
        if (!$lang) {
            $lang = $this->getLocale();
        }
        if (is_array($this->_name) && array_key_exists($lang, $this->_name)) {
            return $this->_name[$lang];
        }

        return null;
    }

    // Renvoie le sujet dans la langue voulue, ou la langue par défaut

    /**
     * fetch subject translation, from a given (or default) lang
     * if no translation is found, force a result
     * @param null $lang
     * @return string|null
     */
    public function getSubject($lang = null): ?string
    {
        if (!$lang) {
            $lang = $this->getLocale();
        }

        return Episciences_Tools::getTranslation($this->getSubjectTranslations(), $lang);
    }

    /**
     * @return mixed
     */
    public function getSubjectTranslations()
    {
        return $this->_subject;
    }

    /**
     * fetch body translation, from a given (or default) lang
     * @param null $lang
     * @return string|null
     */
    public function getBody($lang = null): ?string
    {
        if (!$lang) {
            $lang = $this->getLocale();
        }
        return Episciences_Tools::getTranslation($this->getBodyTranslations(), $lang);
    }

    /**
     * @return mixed
     */
    public function getBodyTranslations()
    {
        return $this->_body;
    }

    /**
     * Returns true if template is custom, false if default
     * @return mixed
     */
    public function getCustom()
    {
        return $this->_custom;
    }

    /**
     * @param $lang
     * @return mixed|null
     */
    public function getCustomFor($lang)
    {
        if (is_array($this->_custom) && array_key_exists($lang, $this->_custom)) {
            return $this->_custom[$lang];
        }
        return null;
    }

    /**
     * Returns rating deadline
     * @return string | null
     */
    public function getDeadline(): ?string
    {
        if ($this->_deadline) {
            return $this->_deadline;
        }
        if ($this->getRvid()) {
            $review = Episciences_ReviewsManager::find($this->getRvid());
            $review->loadSettings();
            $deadline = ($review->getSetting('rating_deadline')) ?: Episciences_Review::DEFAULT_RATING_DEADLINE;
            $this->setDeadline($deadline);
            return $this->_deadline;
        }
        return null;
    }

    /**
     * @param $debug
     * @param $date
     * @param $filters
     * @return array
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    private function getBeforeRevisionDeadlineRecipients($debug, $date, $filters): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $this->getBeforeRevisionDeadlineRecipientsQuery($debug, $date);
        $tmp = $db?->fetchAll($sql);
        return $this->processRevisionRecipients($tmp, $filters);
    }

    /**
     * @param $debug
     * @param $date
     * @param $filters
     * @return array
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    private function getAfterRevisionDeadlineRecipients($debug, $date, $filters): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = $this->getAfterRevisionDeadlineRecipientsQuery($debug, $date);

        $tmp = $db?->fetchAll($sql);

        return $this->processRevisionRecipients($tmp, $filters);

    }

    /**
     * fetch recipients for "not enough reviewers" reminder
     * @param $debug
     * @param $date
     * @param $filters
     * @return array
     * TODO: use $debug & $date parameters
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    private function getNotEnoughReviewersRecipients($debug, $date, $filters): array
    {
        $recipients = [];
        $review = Episciences_ReviewsManager::find($this->getRvid());
        $journalOptions = ['rvCode' => $review->getCode(), Episciences_Review::IS_NEW_FRONT_SWITCHED => $review->isNewFrontSwitched()];

        // si on n'a pas spécifié de nombre minimum de relecteurs, on n'envoie pas de relances
        $required_reviewers = $review->getSetting('requiredReviewers');
        if (!$required_reviewers) {
            return $recipients;
        }

        $settings = [
            'is' => ['RVID' => $this->getRvid()],
            'isNot' => ['STATUS' => $filters]];
        $papers = Episciences_PapersManager::getList($settings);

        $today = new DateTime();
        $today = new DateTime($today->format('Y-m-d')); // strips time from today date


        /** @var Episciences_Paper $paper */
        foreach ($papers as $paper) {
            // recuperation des invitations (acceptées ou en attente) pour chaque article
            $invitations = $paper->getInvitations(array(Episciences_User_Assignment::STATUS_ACTIVE, Episciences_User_Assignment::STATUS_PENDING), false, $review->getRvid());
            // si il y a suffisamment d'invitations, on n'envoie pas de relance
            if (count($invitations) >= $required_reviewers) {
                continue;
            }

            $lostLoginLink = self::buildLostLoginUrl($journalOptions);

            $commonTags = [
                Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN =>  $lostLoginLink,
                Episciences_Mail_Tags::TAG_OBSOLETE_RECIPIENT_USERNAME_LOST_LOGIN => $lostLoginLink // present in custom templates
            ];

            // pour chacun des rédacteurs de l'article
            /** @var Episciences_Editor $editor */
            foreach ($paper->getEditors() as $editor) {

                // calcul de la date d'origine
                // si des invitations ont été envoyées: date de la dernière invitation
                // sinon: date d'assignation de l'article au rédacteur
                $last_action_date = '';
                $origin_date = '';
                if ($invitations) {
                    // on vérifie à quand remonte la dernière invitation
                    foreach ($invitations as $status) {
                        foreach ($status as $invitation) {
                            if (strtotime($invitation['ASSIGNMENT_DATE']) > strtotime($last_action_date)) {
                                $last_action_date = $invitation['ASSIGNMENT_DATE'];
                            }
                        }
                    }
                    // date de dernière invitation
                    $origin_date = $last_action_date;
                }
                if (!$invitations || ($last_action_date && strtotime($last_action_date) < strtotime($editor->getWhen()))) {
                    // date d'assignation du rédacteur à l'article
                    $origin_date = $editor->getWhen();
                }

                // calcul de la deadline
                $origin_date = new DateTime($origin_date);
                $origin_date = new DateTime($origin_date->format('Y-m-d')); // strips time from datetime
                $deadline = date_add($origin_date, date_interval_create_from_date_string($this->getDelay() . ' days'));

                // intervalle entre la deadline et aujourd'hui ( en nombre de jours)
                $interval = (int)date_diff($today, $deadline)->format('%a');

                $editorTags = [
                    Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => $editor->getScreenName(),
                    Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $editor->getFullName(),
                    Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => $editor->getUsername(),
                    Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocid(),
                    Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
                    Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($editor->getLangueid(), true),
                    Episciences_Mail_Tags::TAG_ARTICLE_LINK => self::buildAdminPaperUrl($paper->getDocid(), $journalOptions),
                    Episciences_Mail_Tags::TAG_INVITED_REVIEWERS_COUNT => count($invitations),
                    Episciences_Mail_Tags::TAG_REQUIRED_REVIEWERS_COUNT => $required_reviewers,
                ];

                if (
                    $interval === 0 || // faut-il envoyer la relance aujourd'hui ?
                    ($this->getRepetition() && ($interval % $this->getRepetition()) === 0)

                ) {
                    $recipients[] = [
                        'uid' => $editor->getUid(),
                        'fullname' => $editor->getFullName(),
                        'email' => $editor->getEmail(),
                        'lang' => $editor->getLangueid(true),
                        'tags' => array_merge($commonTags, $editorTags)
                    ];
                }

            } // endforeach $editors

        } // endforeach $papers

        // regarder pb de titre sur relance envoyée par revue-test (avant date de livraison de relecture - fr)
        // rajouter tags manquants sur l'envoi des nouvelles relances

        return $recipients;
    }

    /**
     * fetch recipients for unanswered invitations reminder
     * @param $debug
     * @param $date
     * @param $filters
     * @param array $commonTags
     * @return array
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    private function getUnansweredInvitationRecipients($debug, $date, $filters, array $commonTags = []): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $recipients = [];
        $review = Episciences_ReviewsManager::find($this->getRvid());

        $journalOptions = ['rvCode' => $review->getCode(), Episciences_Review::IS_NEW_FRONT_SWITCHED => $review->isNewFrontSwitched()];

        $lostLoginLink = self::buildLostLoginUrl($journalOptions);

        $commonTags = [...[
            Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN => $lostLoginLink,
            Episciences_Mail_Tags::TAG_OBSOLETE_RECIPIENT_USERNAME_LOST_LOGIN => $lostLoginLink // present in custom templates
        ], ...$commonTags];

        /* récupère toutes les invitations (T_USER_INVITATIONS)
        * restées en attente (pending), et non expirées
        * ( + INNER JOIN de USER_ASSIGNMENT pour infos supplémentaires)
        */

        $subquery = $db->select()
            ->from(
                T_USER_INVITATIONS,
                array('ID' => new Zend_Db_Expr('MAX(ID)')))
            ->group('AID');

        $sql = $db->select()
            ->from(
                array('ui1' => T_USER_INVITATIONS),
                array(
                    'INVITATION_ID' => 'ui1.ID',
                    //'ui1.TOKEN',
                    'ASSIGNMENT_ID' => 'ui1.AID',
                    'ua.UID',
                    'ua.TMP_USER',
                    'INVITATION_DATE' => 'ui1.SENDING_DATE',
                    'ui1.EXPIRATION_DATE',
                    'ua.RVID',
                    'DOCID' => 'ua.ITEMID'))
            ->joinInner(
                array('ui2' => $subquery),
                'ui1.ID = ui2.ID',
                array())
            ->joinInner(
                array('ua' => T_ASSIGNMENTS),
                'ui1.ID = ua.INVITATION_ID',
                array())
            ->where('ua.RVID = ?', $this->getRvid())
            ->where('ui1.STATUS = ?', 'pending')
            ->where("EXPIRATION_DATE >= $date") // don't send reminders for expired invitations
            ->group(['DOCID', 'ua.UID', 'ui1.ID', 'ua.TMP_USER']);

        if ($this->getRepetition()) {

            $repetition = $this->getRepetition();
            $delay = $this->getDelay();

            $sql->where("SENDING_DATE < $date"); // RT#204568

            // interval between today and invitation date should be 0
            $cond1 = new Zend_Db_Expr("TIMESTAMPDIFF(DAY, DATE_ADD(DATE(SENDING_DATE), INTERVAL $delay DAY), DATE($date)) = 0");

            // Make sure the invitation is sent x days [$this->getDelay()] later RT#208269
            $cond3 = new Zend_Db_Expr("DATEDIFF(DATE($date), DATE(SENDING_DATE)) > $delay");

            // interval should be divisible by "repetition"
            $cond2 = new Zend_Db_Expr(
                "MOD(TIMESTAMPDIFF(DAY, $date, DATE_ADD(DATE(SENDING_DATE), INTERVAL $delay DAY)), $repetition) = 0"
            );

            $sql->where("$cond1 OR ( $cond3 AND $cond2)");


        } else {
            $sql->where("$date = ?", new Zend_Db_Expr('DATE_ADD(DATE(SENDING_DATE), INTERVAL ' . $this->getDelay() . ' DAY)'));
        }


        if ($debug) {
            echo Episciences_Tools::$bashColors['light_blue'] . $sql . Episciences_Tools::$bashColors['default'] . PHP_EOL;
        }
        $tmp = $db->fetchAll($sql);

        foreach ($tmp as $data) {
            $paper = Episciences_PapersManager::get($data['DOCID']);
            // filter papers that don't need reminders
            if (!$paper || in_array($paper->getStatus(), $filters, true)) {
                continue;
            }

            $lang = $this->_defaultLanguage;
            $fullname = 'is probably undefined';
            $invitation_url = $review->getBackEndUrl() . "/reviewer/invitation/id/" . $data['INVITATION_ID'] . '/lang/';


            if ((int)$data['TMP_USER'] === 1) {
                $user = new Episciences_User_Tmp;

                if (!empty($user->find($data['UID']))) {
                    $user->generateScreen_name();
                    $fullname = $user->getFullName();
                    $lang = $user->getLangueid(true);
                    $invitation_url .= $lang . '/tmp/' . md5($user->getEmail());
                }

            } else {
                $user = new Episciences_User;
                $user->findWithCAS($data['UID']);
                $fullname = $user->getScreenName();
                $lang = $user->getLangueid(true);
                $invitation_url .= $lang;
            }

            $tags = [
                Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocid(),
                Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
                Episciences_Mail_Tags::TAG_INVITATION_LINK => $invitation_url,
                Episciences_Mail_Tags::TAG_INVITATION_DATE => Episciences_View_Helper_Date::Date($data['INVITATION_DATE'], $lang),
                Episciences_Mail_Tags::TAG_EXPIRATION_DATE => Episciences_View_Helper_Date::Date($data['EXPIRATION_DATE'], $lang)
            ];

            if ($this->getRecipient() === 'editor') {
                foreach ($paper->getEditors(true, true) as $editor) {

                    $editorTags = [
                        Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($editor->getLangueid(), true),
                        Episciences_Mail_Tags::TAG_REVIEWER_FULLNAME => $fullname,
                        Episciences_Mail_Tags::TAG_REVIEWER_MAIL => $user->getEmail(),
                        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => $editor->getScreenName(),
                        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $editor->getFullName(),
                        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => $editor->getUsername()
                    ];

                    $recipients[] = [
                        'uid' => $editor->getUid(),
                        'fullname' => $editor->getFullName(),
                        'email' => $editor->getEmail(),
                        'lang' => $editor->getLangueid(true),
                        'tags' => array_merge($commonTags, $tags, $editorTags),
                        'deadline' => $data['INVITATION_DATE']
                    ];
                }
            } else {

                $reviewerTags = [
                    Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($lang, true),
                    Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => $user->getUsername(),
                    Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => $user->getScreenName(),
                    Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $fullname,

                ];

                $recipients[] = [
                    'uid' => $data['UID'],
                    'fullname' => $fullname,
                    'email' => $user->getEmail(),
                    'lang' => $lang,
                    'tags' => array_merge($commonTags, $tags, $reviewerTags),
                    'deadline' => $data['INVITATION_DATE']
                ];
            }

        }
        return $recipients;
    }

    /**
     * @param $debug
     * @param $date
     * @param $filters
     * @return array
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */

    private function getBeforeReviewingDeadlineRecipients($debug, $date, $filters): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $recipients = [];
        $review = Episciences_ReviewsManager::find($this->getRvid());
        $journalOptions = ['rvCode' => $review->getCode(), Episciences_Review::IS_NEW_FRONT_SWITCHED => $review->isNewFrontSwitched()];

        $deadline_interval = $this->getDeadline();
        if (!$deadline_interval) {
            return $recipients;
        }

        $subquery = $db->select()
            ->from(
                T_USER_INVITATIONS,
                array('AID', 'max_date' => new Zend_Db_Expr('MAX(SENDING_DATE)')))
            ->group('AID');

        // Left Join Subquery
        $leftSQ = $db->select()->from(T_REVIEWER_REPORTS, array('DOCID', 'UID', 'RATING_STATUS' => 'STATUS'));

        $sql = $db->select()
            ->from(
                array('ui1' => T_USER_INVITATIONS),
                array(
                    'INVITATION_ID' => 'ui1.ID',
                    'ASSIGNMENT_ID' => 'ui1.AID',
                    'ua.UID',
                    'RECIPIENT_UID' => 'ua.UID',
                    //'ui1.TOKEN',
                    'INVITATION_DATE' => 'ui1.SENDING_DATE',
                    'ua.RVID',
                    'DOCID' => 'ua.ITEMID',
                    'DEADLINE' => 'ua.DEADLINE'

                ))
            ->joinInner(
                array('ui2' => $subquery),
                'ui1.AID = ui2.AID AND ui2.max_date = ui1.SENDING_DATE',
                array())
            ->joinInner(
                array('ua' => T_ASSIGNMENTS),
                'ui1.ID = ua.INVITATION_ID',
                array())
            ->joinLeft(
                array('pr' => $leftSQ),
                'pr.DOCID = ua.ITEMID AND pr.UID = ua.UID',
                array('RATING_STATUS')
            )
            ->joinLeft(array('p' => T_PAPERS), 'p.DOCID = ua.ITEMID', array())
            ->where('p.STATUS NOT IN (?)', $filters)
            ->where('ua.RVID = ?', $this->getRvid())
            ->where('ui1.STATUS = ?', 'accepted')
            ->where('RATING_STATUS = ' . Episciences_Rating_Report::STATUS_PENDING . ' OR RATING_STATUS = ' . Episciences_Rating_Report::STATUS_WIP)
            ->where("DEADLINE >= $date");


        if ($this->getRepetition()) {
            $sql->where(new Zend_Db_Expr("TIMESTAMPDIFF(DAY, $date, DEADLINE) <= " . $this->getDelay()));
            $sql->where(new Zend_Db_Expr('MOD(TIMESTAMPDIFF(DAY, DATE_SUB(DEADLINE, INTERVAL ' . $this->getDelay() . " DAY), $date), " . $this->getRepetition() . ') = 0'));
        } else {
            $sql->where(new Zend_Db_Expr('DATE_SUB(DEADLINE, INTERVAL ' . $this->getDelay() . " DAY) = $date"));
        }

        if ($debug) {
            echo Episciences_Tools::$bashColors['light_blue'] . $sql . Episciences_Tools::$bashColors['default'] . PHP_EOL;
        }
        $tmp = $db->fetchAll($sql);

        $lostLoginLink = self::buildLostLoginUrl($journalOptions);

        $commonTags = [
            Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN =>  $lostLoginLink,
            Episciences_Mail_Tags::TAG_OBSOLETE_RECIPIENT_USERNAME_LOST_LOGIN => $lostLoginLink // present in custom templates
        ];


        if ($this->getRecipient() === 'editor') {
            foreach ($tmp as $data) {

                $paper = Episciences_PapersManager::get($data['DOCID']);

                if (!$paper || $this->isPaperNotNeedToReminders($paper, $filters)) {
                    continue;
                }

                $reviewer = new Episciences_User;
                $reviewer->findWithCAS($data['UID']);

                foreach ($paper->getEditors(true, true) as $editor) {
                    $tags = [
                        Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocid(),
                        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
                        Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($editor->getLangueid(), true),
                        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => $editor->getUsername(),
                        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => $editor->getScreenName(),
                        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $editor->getFullName(),
                        Episciences_Mail_Tags::TAG_REVIEWER_FULLNAME => $reviewer->getScreenName(),
                        Episciences_Mail_Tags::TAG_REVIEWER_MAIL => $reviewer->getEmail(),
                        Episciences_Mail_Tags::TAG_ARTICLE_LINK => self::buildAdminPaperUrl($paper->getDocid(), $journalOptions)
                    ];

                    $recipients[] = [
                        'uid' => $editor->getUid(),
                        'fullname' => $editor->getFullName(),
                        'email' => $editor->getEmail(),
                        'lang' => $editor->getLangueid(true),
                        'tags' => array_merge($commonTags, $tags),
                        'deadline' => $data['DEADLINE']
                    ];
                }
            }
        } else {
            foreach ($tmp as $data) {

                $paper = Episciences_PapersManager::get($data['DOCID']);
                // filter papers that don't need reminders
                if (!$paper || $this->isPaperNotNeedToReminders($paper, $filters)) {
                    continue;
                }
                $reviewer = new Episciences_User;
                $reviewer->findWithCAS($data['UID']);

                $tags = [
                    Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocid(),
                    Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
                    Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($reviewer->getLangueid(), true),
                    Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => $reviewer->getUsername(),
                    Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => $reviewer->getScreenName(),
                    Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $reviewer->getFullName(),
                    Episciences_Mail_Tags::TAG_ARTICLE_RATING_LINK => $review->getBackEndUrl() . '/paper/rating/id/' . $paper->getDocid()
                ];

                $recipients[] = [
                    'uid' => $reviewer->getUid(),
                    'fullname' => $reviewer->getFullName(),
                    'email' => $reviewer->getEmail(),
                    'lang' => $reviewer->getLangueid(true),
                    'tags' => array_merge($commonTags, $tags),
                    'deadline' => $data['DEADLINE']
                ];
            }
        }

        return $recipients;
    }

    /**
     * @param $debug
     * @param $date
     * @param $filters
     * @return array
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    private function getAfterReviewingDeadlineRecipients($debug, $date, $filters): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $recipients = [];
        $review = Episciences_ReviewsManager::find($this->getRvid());
        $journalOptions = ['rvCode' => $review->getCode(), Episciences_Review::IS_NEW_FRONT_SWITCHED => $review->isNewFrontSwitched()];


        $deadline_interval = $this->getDeadline();
        if (!$deadline_interval) {
            return $recipients;
        }


        // inner join (last assignments)
        $innerSQ = $db->select()
            ->from(T_ASSIGNMENTS, array('RVID', 'ITEMID', 'ITEM', 'UID', 'LAST_STATUS' => new Zend_Db_Expr('MAX(`WHEN`)')))
            ->where('ROLEID = ?', 'reviewer')
            ->group('RVID')
            ->group('ITEMID')
            ->group('ITEM')
            ->group('UID');

        // left join (reviewer rating grids)
        $leftSQ = $db->select()->from(T_REVIEWER_REPORTS, array('DOCID', 'UID', 'RATING_STATUS' => 'STATUS'));

        $sql = $db->select()
            ->from(
                array('ua1' => T_ASSIGNMENTS),
                array('ua1.RVID',
                    'ua1.ITEM',
                    'DOCID' => 'ua1.ITEMID',
                    'ua1.UID',
                    'INVITATION_STATUS' => 'ua1.STATUS',
                    'DEADLINE' => 'ua1.DEADLINE'
                    //, 'RATING_STATUS'
                    //, 'LAST_STATUS'
                )
            )
            ->joinInner(
                array('ua2' => $innerSQ),
                'ua1.RVID = ua2.RVID 
                        AND ua1.ITEMID = ua2.ITEMID
                        AND ua1.ITEM = ua2.ITEM
                        AND ua1.UID = ua2.UID
                        AND ua1.`WHEN` = LAST_STATUS',
                array('LAST_STATUS')
            )
            ->joinLeft(
                array('pr' => $leftSQ),
                'pr.DOCID = ua1.ITEMID
                        AND pr.UID = ua1.UID',
                array('RATING_STATUS')
            )
            ->where('ROLEID = ?', 'reviewer')
            ->where('ua1.RVID = ?', $this->getRvid())
            ->where('ua1.`WHEN` = LAST_STATUS')
            ->where('ua1.STATUS = ?', 'active')
            ->where('RATING_STATUS = ' . Episciences_Rating_Report::STATUS_PENDING . ' OR RATING_STATUS = ' . Episciences_Rating_Report::STATUS_WIP)
            ->where("DEADLINE <= $date");

        if ($this->getRepetition()) {
            $sql->where(new Zend_Db_Expr("TIMESTAMPDIFF(DAY, DEADLINE, $date) >= " . $this->getDelay()));
            $sql->where(new Zend_Db_Expr('MOD(TIMESTAMPDIFF(DAY, DATE_ADD(DEADLINE, INTERVAL ' . $this->getDelay() . " DAY), $date), " . $this->getRepetition() . ') = 0'));
        } else {
            $sql->where(new Zend_Db_Expr('DATE_ADD(DEADLINE, INTERVAL ' . $this->getDelay() . " DAY) = $date"));
        }

        if ($debug) {
            echo Episciences_Tools::$bashColors['light_blue'] . $sql . Episciences_Tools::$bashColors['default'] . PHP_EOL;
        }
        $tmp = $db->fetchAll($sql);

        $lostLoginLink = self::buildLostLoginUrl($journalOptions);

        $commonTags = [
            Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN =>  $lostLoginLink,
            Episciences_Mail_Tags::TAG_OBSOLETE_RECIPIENT_USERNAME_LOST_LOGIN => $lostLoginLink // present in custom templates
        ];


        if ($this->getRecipient() === 'editor') {
            foreach ($tmp as $data) {

                $paper = Episciences_PapersManager::get($data['DOCID']);
                // filter papers that don't need reminders
                if (!$paper || $this->isPaperNotNeedToReminders($paper, $filters)) {
                    continue;
                }
                $reviewer = new Episciences_User;
                $reviewer->findWithCAS($data['UID']);

                foreach ($paper->getEditors(true, true) as $editor) {
                    $editorTags = [
                        Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocid(),
                        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
                        Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($editor->getLangueid(), true),
                        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => $editor->getUsername(),
                        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => $editor->getScreenName(),
                        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $editor->getFullName(),
                        Episciences_Mail_Tags::TAG_REVIEWER_FULLNAME => $reviewer->getScreenName(),
                        Episciences_Mail_Tags::TAG_REVIEWER_MAIL => $reviewer->getEmail(),
                        Episciences_Mail_Tags::TAG_ARTICLE_LINK => self::buildAdminPaperUrl($paper->getDocid(), $journalOptions)
                    ];

                    $recipients[] = [
                        'uid' => $editor->getUid(),
                        'fullname' => $editor->getFullName(),
                        'email' => $editor->getEmail(),
                        'lang' => $editor->getLangueid(true),
                        'tags' => array_merge($commonTags, $editorTags),
                        'deadline' => $data['DEADLINE']
                    ];
                }
            }
        } else {
            foreach ($tmp as $data) {

                $paper = Episciences_PapersManager::get($data['DOCID']);
                // filter papers that don't need reminders
                if (!$paper || $this->isPaperNotNeedToReminders($paper, $filters)) {
                    continue;
                }
                $reviewer = new Episciences_User;
                $reviewer->findWithCAS($data['UID']);

                $reviewerTags = [
                    Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocid(),
                    Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
                    Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($reviewer->getLangueid(), true),
                    Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => $reviewer->getUsername(),
                    Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => $reviewer->getScreenName(),
                    Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $reviewer->getFullName(),
                    Episciences_Mail_Tags::TAG_ARTICLE_RATING_LINK => $review->getBackEndUrl() . '/paper/rating/id/' . $paper->getDocid()];

                $recipients[] = [
                    'uid' => $reviewer->getUid(),
                    'fullname' => $reviewer->getFullName(),
                    'email' => $reviewer->getEmail(),
                    'lang' => $reviewer->getLangueid(true),
                    'tags' => array_merge($commonTags, $reviewerTags),
                    'deadline' => $data['DEADLINE']
                ];
            }
        }

        return $recipients;
    }

    /**
     * @param Episciences_Paper $paper
     * @param array $filters
     * @return bool
     */
    private function isPaperNotNeedToReminders(Episciences_Paper $paper, array $filters = []): bool
    {

        $latestDocId = (int)$paper->getLatestVersionId();

        if ($paper->getDocid() !== $latestDocId) {
            return true;
        }

        return in_array($paper->getStatus(), $filters, true);

    }

    /**
     * La relance n'est envoyée que si rien n'est fait pendant une période > (reminder delay + $time) = x (jours).
     * @param int $status
     * @param $debug
     * @param $date
     * @param int $waitingTime (days) : sensible à la date de modification "MODIFICATION_DATE"
     * @return array
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    private function getArticlesBlockedAtCurrentState(int $status, $debug, $date, int $waitingTime = self::DEFAULT_WAITING_TIME): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $recipients = [];
        $rRecipient = $this->getRecipient();
        $editors = [];

        $delay = (int)$this->getDelay();
        $repetition = $this->getRepetition();

        $review = Episciences_ReviewsManager::find($this->getRvid());
        $journalOptions = ['rvCode' => $review->getCode(), Episciences_Review::IS_NEW_FRONT_SWITCHED => $review->isNewFrontSwitched()];

        $settings = [
            'is' => [
                'RVID' => $this->getRvid(),
                'STATUS' => $status,
            ]
        ];

        $pq = Episciences_PapersManager::getListQuery($settings);

        $paperQuery = $db
            ->select()
            ->from(['p' => $pq])
            ->join(['pl' => T_LOGS], 'pl.DOCID = p.DOCID', ['ACTION', 'max(DATE) as  date'])
            ->where('ACTION = ?', 'status')
            ->group('pl.DOCID');

        $refDate = 'date'; // not based on the "MODIFICATION_DATE" column, which is not immutable: (e.g. modified when metadata is updated)
        $deadline = new Zend_Db_Expr("DATE_ADD(DATE_FORMAT(`$refDate`,'%Y-%m-%d'), INTERVAL $waitingTime DAY)");
        $date = new Zend_Db_Expr("DATE_FORMAT($date,'%Y-%m-%d')");

        if ($repetition) {
            $paperQuery->where(new Zend_Db_Expr("TIMESTAMPDIFF(DAY, $deadline, $date) >= $delay"));
            $paperQuery->where(new Zend_Db_Expr("MOD(TIMESTAMPDIFF(DAY, DATE_ADD($deadline, INTERVAL $delay DAY), $date), $repetition ) = 0"));
        } else {
            $paperQuery->where(new Zend_Db_Expr("DATE_ADD($deadline, INTERVAL $delay DAY) = $date"));
        }

        if ($debug) {
            $qToStr = $paperQuery->__toString();
            echo Episciences_Tools::$bashColors['light_blue'] . $qToStr . Episciences_Tools::$bashColors['default'] . PHP_EOL;
        }

        $resultQuery = $db->fetchAssoc($paperQuery);

        foreach ($resultQuery as $item) {
            $paper = new Episciences_Paper($item);

            if (Episciences_Acl::ROLE_CHIEF_EDITOR === $rRecipient) {
                $editors = Episciences_Review::getChiefEditors();
            } elseif (Episciences_Acl::ROLE_EDITOR === $rRecipient) {
                $editors = $paper->getEditors(true, true);
            }

            $acceptanceDate = $paper->isAcceptedSubmission() ? $paper->getModification_date() : '';

            $lostLoginLink = self::buildLostLoginUrl($journalOptions);

            $lostLoginTags = [
                Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN =>  $lostLoginLink,
                Episciences_Mail_Tags::TAG_OBSOLETE_RECIPIENT_USERNAME_LOST_LOGIN => $lostLoginLink // present in custom templates
            ];

            $tags = [...$lostLoginTags, ...[
                Episciences_Mail_Tags::TAG_ARTICLE_LINK => self::buildAdminPaperUrl($paper->getDocid(), $journalOptions),
                Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocid(),
                Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
            ]];

            /** @var Episciences_Editor $editor */
            foreach ($editors as $editor) {

                $editosTags = [
                    Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($editor->getLangueid(), true),
                    Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => $editor->getScreenName(),
                    Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $editor->getFullName(),
                    Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => $editor->getUsername(),
                    Episciences_Mail_Tags::TAG_ACCEPTANCE_DATE => $acceptanceDate !== '' ? Episciences_View_Helper_Date::Date($acceptanceDate, $editor->getLangueid()) : '',
                    Episciences_Mail_Tags::TAG_SUBMISSION_DATE => Episciences_View_Helper_Date::Date($paper->getWhen(), $editor->getLangueid())
                ];


                $recipients[] = [
                    'uid' => $editor->getUid(),
                    'fullname' => $editor->getFullName(),
                    'email' => $editor->getEmail(),
                    'lang' => $editor->getLangueid(true),
                    'tags' => array_merge($tags, $editosTags),
                    'deadline' => $item['date']
                ];

            }

        }

        return $recipients;

    }


    /**
     * @param $debug
     * @param $date
     * @return Zend_Db_Select
     */
    private function getAfterRevisionDeadlineRecipientsQuery($debug, $date): Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // recupere la dernière demande de modification de chaque article
        $subquery1 = $db->select()
            ->from(T_PAPER_COMMENTS, array('DOCID', 'REQUEST_DATE' => new Zend_Db_Expr('MAX(`WHEN`)')))
            ->where('`TYPE` = ?', Episciences_CommentsManager::TYPE_REVISION_REQUEST)
            ->group('DOCID');

        $sql = $db->select()
            ->from(array('x' => new Zend_Db_Expr('(' . $subquery1 . ')')), array())
            ->join(array('c' => T_PAPER_COMMENTS),
                'x.DOCID = c.DOCID AND x.REQUEST_DATE = c.WHEN',
                array('DEADLINE'))
            ->joinLeft(array('p' => T_PAPERS), 'p.DOCID = c.DOCID', array('DOCID', 'UID' => 'p.UID'))
            ->where('p.RVID = ?', $this->getRvid())
            ->where('c.DEADLINE IS NOT NULL')
            ->where('(p.STATUS = ' . Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION
                . ' OR p.STATUS = ' . Episciences_Paper::STATUS_WAITING_FOR_MAJOR_REVISION . ')')
            ->where("DEADLINE <= $date");

        if ($this->getRepetition()) {
            $sql->where(new Zend_Db_Expr("TIMESTAMPDIFF(DAY, DEADLINE, $date) >= " . $this->getDelay()));
            $sql->where(new Zend_Db_Expr("MOD(TIMESTAMPDIFF(DAY, DEADLINE, $date), " . $this->getRepetition() . ') = 0'));
        } else {
            $sql->where(new Zend_Db_Expr("TIMESTAMPDIFF(DAY, DEADLINE, $date) = " . $this->getDelay()));
        }

        if ($debug) {
            echo Episciences_Tools::$bashColors['light_blue'] . $sql . Episciences_Tools::$bashColors['default'] . PHP_EOL;
        }

        return $sql;
    }

    /**
     * @param $debug
     * @param $date
     * @return Zend_Db_Select
     */

    private function getBeforeRevisionDeadlineRecipientsQuery($debug, $date): Zend_Db_Select
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $subquery1 = $db?->select()
            ->from(T_PAPER_COMMENTS, array('DOCID', 'REQUEST_DATE' => new Zend_Db_Expr('MAX(`WHEN`)')))
            ->where('`TYPE` = ?', Episciences_CommentsManager::TYPE_REVISION_REQUEST)
            ->group('DOCID');

        $sql = $db?->select()
            ->from(array('x' => new Zend_Db_Expr('(' . $subquery1 . ')')), array())
            ->join(array('c' => T_PAPER_COMMENTS),
                'x.DOCID = c.DOCID AND x.REQUEST_DATE = c.WHEN',
                array('DEADLINE'))
            ->joinLeft(array('p' => T_PAPERS), 'p.DOCID = c.DOCID', array('DOCID', 'UID' => 'p.UID'))
            ->where('p.RVID = ?', $this->getRvid())
            ->where('c.DEADLINE IS NOT NULL')
            ->where('(p.STATUS = ' . Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION
                . ' OR p.STATUS = ' . Episciences_Paper::STATUS_WAITING_FOR_MAJOR_REVISION . ')')
            ->where("DEADLINE >= $date");

        if ($this->getRepetition()) {
            $sql->where(new Zend_Db_Expr("TIMESTAMPDIFF(DAY, $date, DEADLINE) <= " . $this->getDelay()));
            $sql->where(new Zend_Db_Expr("MOD(TIMESTAMPDIFF(DAY, $date, DEADLINE), " . $this->getRepetition() . ') = 0'));
        } else {
            $sql->where(new Zend_Db_Expr("TIMESTAMPDIFF(DAY, $date, DEADLINE) = " . $this->getDelay()));
        }

        if ($debug) {
            echo Episciences_Tools::$bashColors['light_blue'] . $sql . Episciences_Tools::$bashColors['default'] . PHP_EOL;
        }

        return $sql;

    }

    /**
     * @param array $data
     * @param array $filters
     * @return array
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */

    private function processRevisionRecipients(array $data, array $filters): array
    {

        $recipients = [];
        $review = Episciences_ReviewsManager::find($this->getRvid());
        $journalOptions = ['rvCode' => $review->getCode(), Episciences_Review::IS_NEW_FRONT_SWITCHED => $review->isNewFrontSwitched()];

        $lostLoginLink = self::buildLostLoginUrl($journalOptions);

        $commonTags = [
            Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN =>  $lostLoginLink,
            Episciences_Mail_Tags::TAG_OBSOLETE_RECIPIENT_USERNAME_LOST_LOGIN => $lostLoginLink // present in custom templates
        ];


        foreach ($data as $current) {

            $paper = Episciences_PapersManager::get($current['DOCID'], false);

            if (!$paper || in_array($paper->getStatus(), $filters, true)) {
                continue;
            }

            $author = new Episciences_User;
            $author->findWithCAS($current['UID']);

            $tags = [
                Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocid(),
                Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
            ];

            if ($this->getRecipient() === 'editor') {
                foreach ($paper->getEditors(true, true) as $editor) {

                    $editorTags = [
                        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => $editor->getScreenName(),
                        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $editor->getFullName(),
                        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => $editor->getUsername(),
                        Episciences_Mail_Tags::TAG_AUTHOR_FULL_NAME => $author->getFullName(),
                        Episciences_Mail_Tags::TAG_AUTHOR_EMAIL => $author->getEmail(),
                        Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($editor->getLangueid(), true),
                        Episciences_Mail_Tags::TAG_PAPER_URL => self::buildAdminPaperUrl($paper->getDocid(), $journalOptions),
                        Episciences_Mail_Tags::TAG_REVISION_DEADLINE => Episciences_View_Helper_Date::Date($current['DEADLINE'], $editor->getLangueid()),
                    ];

                    $recipients[] = [
                        'uid' => $editor->getUid(),
                        'fullname' => $editor->getFullName(),
                        'email' => $editor->getEmail(),
                        'lang' => $editor->getLangueid(true),
                        'tags' => array_merge($commonTags, $tags, $editorTags),
                        'deadline' => $current['DEADLINE']
                    ];


                }
            } else {

                $contribTags = [
                    Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => $author->getScreenName(),
                    Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $author->getFullName(),
                    Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => $author->getUsername(),
                    Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($author->getLangueid(), true),
                    Episciences_Mail_Tags::TAG_PAPER_URL => self::buildPublicPaperUrl($paper->getDocid(), $journalOptions),
                    Episciences_Mail_Tags::TAG_REVISION_DEADLINE => Episciences_View_Helper_Date::Date($current['DEADLINE'], $author->getLangueid()),
                ];

                $recipients[] = [
                    'uid' => $author->getUid(),
                    'fullname' => $author->getFullName(),
                    'email' => $author->getEmail(),
                    'lang' => $author->getLangueid(true),
                    'tags' => array_merge($commonTags, $tags, $contribTags),
                    'deadline' => $current['DEADLINE']
                ];
            }
        }


        return $recipients;

    }

}
