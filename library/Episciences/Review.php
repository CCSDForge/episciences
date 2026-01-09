<?php

/**
 * Class Episciences_Review
 * Journal Settings
 */
class Episciences_Review
{

    public const IS_NEW_FRONT_SWITCHED = 'is_new_front_switched';

    public const STATUS_NOTVALID = 0;
    public const STATUS_VALID = 1;
    public const STATUS_REFUSED = 2;
    public const DEFAULT_INVITATION_DEADLINE = '1 month';
    public const DEFAULT_RATING_DEADLINE = '2 month';
    public const DEFAULT_RATING_DEADLINE_MIN = '2 month';
    public const DEFAULT_RATING_DEADLINE_MAX = '6 month';

    public const ASSIGNMENT_EDITORS_MODE = ['predefined' => '0', 'default' => '1', 'advanced' => '2'];

    public const DEFAULT_LANG = 'en';

    public const SETTING_INVITATION_DEADLINE = 'invitation_deadline';
    public const SETTING_INVITATION_DEADLINE_UNIT = 'invitation_deadline_unit';
    public const SETTING_RATING_DEADLINE = 'rating_deadline';
    public const SETTING_RATING_DEADLINE_UNIT = 'rating_deadline_unit';
    public const SETTING_RATING_DEADLINE_MIN = 'rating_deadline_min';
    public const SETTING_RATING_DEADLINE_MIN_UNIT = 'rating_deadline_min_unit';
    public const SETTING_RATING_DEADLINE_MAX = 'rating_deadline_max';
    public const SETTING_RATING_DEADLINE_MAX_UNIT = 'rating_deadline_max_unit';
    public const SETTING_DESCRIPTION = 'description';
    public const SETTING_CAN_SPECIFY_UNWANTED_REVIEWERS = 'canSpecifyUnwantedReviewers';
    public const SETTING_CAN_SUGGEST_EDITOR = 'canSuggestEditor';
    public const SETTING_CAN_PICK_EDITOR = 'canPickEditors';
    public const SETTING_CAN_ANSWER_WITH_TMP_VERSION = 'canAnswerWithTmpVersion';
    public const SETTING_MAX_EDITORS = 'max_editors';
    public const SETTING_CAN_CHOOSE_VOLUME = 'canChooseVolume';
    // git 168
    public const SETTING_CAN_PICK_SECTION = 'canPickSEction';
    public const SETTING_CAN_SUGGEST_REVIEWERS = 'canSuggestReviewers';
    public const SETTING_REVIEWERS_CAN_COMMENT_ARTICLES = 'reviewersCanCommentArticles';
    public const SETTING_REQUIRED_REVIEWERS = 'requiredReviewers';
    public const SETTING_ENCAPSULATE_EDITORS = 'encapsulateEditors';
    public const SETTING_CAN_ABANDON_CONTINUE_PUBLICATION_PROCESS = 'canAbandonContinuePublicationProcess';
    // git #155
    public const SETTING_CAN_RESUBMIT_REFUSED_PAPER = 'canResubmitRefusedPaper';
    public const SETTING_ARXIV_PAPER_PASSWORD = 'canSharePaperPassword';

    //const SETTING_EDITORS_CAN_MAKE_DECISIONS = 'editorsCanMakeDecisions';
    public const SETTING_EDITORS_CAN_ABANDON_CONTINUE_PUBLICATION_PROCESS = 'editorsCanAbandonPublicationProcess';
    public const SETTING_EDITORS_CAN_ACCEPT_PAPERS = 'editorsCanAcceptPapers';
    public const SETTING_EDITORS_CAN_REJECT_PAPERS = 'editorsCanRejectPapers';
    public const SETTING_EDITORS_CAN_PUBLISH_PAPERS = 'editorsCanPublishPapers';
    public const SETTING_EDITORS_CAN_ASK_PAPER_REVISIONS = 'editorsCanAskPaperRevisions';
    public const SETTING_EDITORS_CAN_EDIT_TEMPLATES = 'editorsCanEditTemplates';
    public const SETTING_EDITORS_CAN_ASSIGN_REVIEWERS = 'editorsCanAssignReviewers';
    public const SETTING_EDITORS_CAN_ASSIGN_EDITORS = 'editorsCanAssignEditors';
    public const SETTING_SHOW_RATINGS = 'showRatings';
    public const SETTING_DOMAINS = 'domains';
    public const SETTING_ISSN = 'ISSN';
    public const SETTING_REPOSITORIES = 'repositories';
    public const SETTING_SPECIAL_ISSUE_ACCESS_CODE = 'specialIssueAccessCode';
    public const SETTING_ENCAPSULATE_REVIEWERS = 'encapsulateReviewers';
    public const SETTING_EDITORS_CAN_REASSIGN_ARTICLES = 'editorsCanReassignArticle';
    //Assignation automatique de rédacteurs
    public const SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT = 'systemAutoEditorsAssignment';
    //Paramétrage avancé
    public const SETTING_EDITORS_ASSIGNMENT_DETAILS = 'editorsAssignmentDetails';
    //Details de l'assignation de rédacteurs
    public const SETTING_SYSTEM_CAN_ASSIGN_CHIEF_EDITORS = 'systemCanAssignChiefEditors';
    public const SETTING_SYSTEM_CAN_ASSIGN_SUGGEST_EDITORS = 'systemCanAssignSuggestEditors';
    public const SETTING_SYSTEM_CAN_ASSIGN_SECTION_EDITORS = 'systemCanAssignSectionEditors';
    public const SETTING_SYSTEM_CAN_ASSIGN_SPECIAL_VOLUME_EDITORS = 'systemCanAssignOnlySpecialVolumeEditors';
    public const SETTING_SYSTEM_CAN_ASSIGN_VOLUME_EDITORS = 'systemCanAssignAllVolumeEditors';
    const SETTING_ENCAPSULATE_COPY_EDITORS = 'encapsulateCopyEditors';
    public const SETTING_DISPLAY_STATISTICS = 'displayStatistics';
    public const SETTING_DISABLE_AUTOMATIC_TRANSFER = 'disableAutomaticTransfer';

    /**
     * Do not allow the selection of an editor in chief when the author has the option to
     * propose an editor at the time of submission
     */

    public const SETTING_DO_NOT_ALLOW_EDITOR_IN_CHIEF_SELECTION = 'doNotAllowEditorInChiefSelection';


    // Notifications
    public const SETTING_SYSTEM_CAN_NOTIFY_CHIEF_EDITORS = 'systemCanNotifyChiefEditors';
    public const SETTING_SYSTEM_CAN_NOTIFY_ADMINISTRATORS = 'systemCanNotifyAdministrator';
    public const SETTING_SYSTEM_CAN_NOTIFY_SECRETARIES = 'systemCanNotifySecretaries';
    public const SETTING_SYSTEM_NOTIFICATIONS = 'systemNotifications';
    public const SETTING_SYSTEM_IS_COI_ENABLED = 'isCoiEnabled'; //Conflict Of Interest (COI) is Disabled by default
    public const SETTING_SYSTEM_COI_COMMENTS_TO_EDITORS_ENABLED = 'coiCommentsToEditorsEnabled';

    public const ASSIGNMENT_EDITORS_DETAIL = [
        self::SETTING_SYSTEM_CAN_ASSIGN_CHIEF_EDITORS => '0',
        self::SETTING_SYSTEM_CAN_ASSIGN_SECTION_EDITORS => '1',
        self::SETTING_SYSTEM_CAN_ASSIGN_SPECIAL_VOLUME_EDITORS => '2',
        self::SETTING_SYSTEM_CAN_ASSIGN_VOLUME_EDITORS => '3',
        self::SETTING_SYSTEM_CAN_ASSIGN_SUGGEST_EDITORS => '4',
        self::SETTING_SYSTEM_CAN_NOTIFY_ADMINISTRATORS => '5',
        self::SETTING_SYSTEM_CAN_NOTIFY_SECRETARIES => '6'
    ];

    public const ENABLED = '1';
    public const DISABLED = '0';

    // Automatically reassign the same reviewers
    public const SETTING_AUTOMATICALLY_REASSIGN_SAME_REVIEWERS_WHEN_NEW_VERSION = 'NewVersionAutomaticallyReassignSameReviewers';
    public const MAJOR_REVISION_ASSIGN_REVIEWERS = 'majorRevisionAssignReviewers';
    public const MINOR_REVISION_ASSIGN_REVIEWERS = 'minorRevisionAssignReviewers';
    public const SETTING_CONTACT_JOURNAL = 'contactJournal';
    public const SETTING_JOURNAL_NOTICE = 'contactJournalNotice';
    public const SETTING_CONTACT_JOURNAL_EMAIL = 'contactJournalEmail';
    //public const SETTING_CONTACT_TECH_SUPPORT = 'contactTechSupport'; // github#625
    public const SETTING_CONTACT_TECH_SUPPORT_EMAIL = 'contactTechSupportEmail';
    public const SETTING_ISSN_PRINT = 'ISSN_PRINT';
    public const SETTING_JOURNAL_DOI = 'journalAssignedDoi';

    public const SETTING_JOURNAL_PUBLISHER = 'journalPublisher';
    public const SETTING_JOURNAL_PUBLISHER_LOC = 'journalPublisherLoc';
    #git 303
    public const DEFAULT_REVISION_DEADLINE_MAX = '12 month';

    public const SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION = 'paperFinalDecisionAllowRevision';

    public const SETTING_CONTACT_ERROR_MAIL = "contactErrorMail";

    public const SETTING_REFUSED_ARTICLE_AUTHORS_MESSAGE_AUTOMATICALLY_SENT_TO_REVIEWERS =
        'refusedArticleAuthorsMsgSentToReviewers';
    public const SETTING_TO_REQUIRE_REVISION_DEADLINE = 'toRequireRevisionDeadline';
    public const SETTING_START_STATS_AFTER_DATE = 'startStatsAfterDate';

    /** @var int */
    public static $_currentReviewId = null;
    protected $_db = null;
    protected $_rvid = 0;
    protected $_code = '';
    protected $_name = '';
    protected $_status = '';
    protected $_creation = null;
    /**
     * @var int
     */
    protected $_piwikid = 0;
    protected $_primary = 'RVID'; // Paramètres à encoder/décoder en JSON
    protected $_jsonSettings = []; // Noms des paramètres autorisés
    protected $_settingsKeys = []; // Paramètres de la revue
    protected $_settings = [];
    protected $_issn = null;
    protected $_repositories = [];
    /**
     * @var Episciences_Review_DoiSettings
     */
    protected $_doiSettings;
    private bool $isNewFrontSwitched = false;

    /**
     * Episciences_Review constructor.
     * @param array|null $options
     */
    public function __construct(array $options = null)
    {
        $this->_db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $this->_settingsKeys = [
            self::SETTING_INVITATION_DEADLINE,
            self::SETTING_INVITATION_DEADLINE_UNIT,
            self::SETTING_RATING_DEADLINE,
            self::SETTING_RATING_DEADLINE_UNIT,
            self::SETTING_RATING_DEADLINE_MIN,
            self::SETTING_RATING_DEADLINE_MIN_UNIT,
            self::SETTING_RATING_DEADLINE_MAX,
            self::SETTING_RATING_DEADLINE_MAX_UNIT,
            self::SETTING_DESCRIPTION,
            self::SETTING_CAN_SPECIFY_UNWANTED_REVIEWERS,
            //self::SETTING_CAN_SUGGEST_EDITOR,
            self::SETTING_CAN_PICK_EDITOR,
            self::SETTING_CAN_ANSWER_WITH_TMP_VERSION,
            //self::SETTING_MAX_EDITORS,
            self::SETTING_CAN_CHOOSE_VOLUME,
            self::SETTING_CAN_PICK_SECTION,
            self::SETTING_CAN_SUGGEST_REVIEWERS,
            self::SETTING_REVIEWERS_CAN_COMMENT_ARTICLES,
            self::SETTING_REQUIRED_REVIEWERS,
            self::SETTING_ENCAPSULATE_EDITORS,
            //self::SETTING_EDITORS_CAN_MAKE_DECISIONS,
            self::SETTING_EDITORS_CAN_ACCEPT_PAPERS,
            self::SETTING_EDITORS_CAN_REJECT_PAPERS,
            self::SETTING_EDITORS_CAN_PUBLISH_PAPERS,
            self::SETTING_EDITORS_CAN_ASK_PAPER_REVISIONS,
            self::SETTING_EDITORS_CAN_EDIT_TEMPLATES,
            //self::SETTING_EDITORS_CAN_ASSIGN_REVIEWERS,
            //self::SETTING_EDITORS_CAN_ASSIGN_EDITORS,
            self::SETTING_SHOW_RATINGS,
            self::SETTING_DOMAINS,
            self::SETTING_ISSN,
            self::SETTING_ISSN_PRINT,
            self::SETTING_JOURNAL_DOI,
            self::SETTING_CONTACT_JOURNAL,
            self::SETTING_JOURNAL_NOTICE,
            self::SETTING_CONTACT_JOURNAL_EMAIL,
            self::SETTING_CONTACT_TECH_SUPPORT_EMAIL,
            self::SETTING_REPOSITORIES,
            self::SETTING_SPECIAL_ISSUE_ACCESS_CODE,
            self::SETTING_ENCAPSULATE_REVIEWERS,
            self::SETTING_EDITORS_CAN_REASSIGN_ARTICLES,
            self::SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT,
            self::SETTING_AUTOMATICALLY_REASSIGN_SAME_REVIEWERS_WHEN_NEW_VERSION,
            self::SETTING_SYSTEM_NOTIFICATIONS,
            self::SETTING_CAN_ABANDON_CONTINUE_PUBLICATION_PROCESS,
            self::SETTING_EDITORS_CAN_ABANDON_CONTINUE_PUBLICATION_PROCESS,
            self::SETTING_ENCAPSULATE_COPY_EDITORS,
            self::SETTING_CAN_RESUBMIT_REFUSED_PAPER,
            self::SETTING_SYSTEM_IS_COI_ENABLED,
            self::SETTING_SYSTEM_COI_COMMENTS_TO_EDITORS_ENABLED,
            self::SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION,
            self::SETTING_DO_NOT_ALLOW_EDITOR_IN_CHIEF_SELECTION,
            self::SETTING_ARXIV_PAPER_PASSWORD,
            self::SETTING_CONTACT_ERROR_MAIL,
            self::SETTING_DISPLAY_STATISTICS,
            self::SETTING_REFUSED_ARTICLE_AUTHORS_MESSAGE_AUTOMATICALLY_SENT_TO_REVIEWERS,
            self::SETTING_TO_REQUIRE_REVISION_DEADLINE,
            self::SETTING_START_STATS_AFTER_DATE,
            self::SETTING_JOURNAL_PUBLISHER,
            self::SETTING_JOURNAL_PUBLISHER_LOC,
        ];


        $this->_jsonSettings = [
            self::SETTING_REPOSITORIES,
            self::SETTING_DOMAINS,
            self::SETTING_AUTOMATICALLY_REASSIGN_SAME_REVIEWERS_WHEN_NEW_VERSION,
            self::SETTING_SYSTEM_NOTIFICATIONS,
            self::SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT,
            self::SETTING_DISABLE_AUTOMATIC_TRANSFER
        ];

        if (is_array($options)) {
            $this->setOptions($options);
        }

        self::setCurrentReviewId($this->getRvid()); // /!\ Instead of checking if $currentReviewId !== null => Side effect: once the value is initialized (@see InboxNotifications::notifyAuthorAndEditorialCommittee)

    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options): self
    {
        $methods = get_class_methods($this);

        $doiSet = new Episciences_Review_DoiSettings($options);

        $this->setDoiSettings($doiSet);

        foreach ($options as $key => $value) {

            $method = 'set';
            if ($key === self::IS_NEW_FRONT_SWITCHED) {
                $method .= sprintf('%s', Episciences_Tools::convertToCamelCase($key, '_', true));
            } else {
                $method .= ucfirst(strtolower($key));
            }

            if (in_array($method, $methods, true)) {
                $this->$method($value);
            } elseif (in_array($key, $this->_settingsKeys, true)) {
                $this->setSetting($key, $value);
            }
        }

        return $this;
    }

    /**
     * @param string $name
     * @param $value
     * @return $this
     */
    public function setSetting(string $name, $value): self
    {
        $this->_settings[$name] = $value;
        return $this;
    }

    /**
     * @return int
     */
    public function getRvid(): int
    {
        return $this->_rvid;
    }

    /**
     * @param $rvId
     * @return $this
     */
    public function setRvid($rvId): self
    {
        $this->_rvid = (int)$rvId;
        return $this;
    }

    /**
     * check from database if review exists
     * @param int|string $rvid
     * @return boolean
     */
    public static function exist($rvid): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (is_numeric($rvid)) {
            $select = $db->select()->from('REVIEW', 'COUNT(*)')->where('RVID = ?', (int)$rvid);
        } else {
            $select = $db->select()->from('REVIEW', 'COUNT(*)')->where('CODE = ?', $rvid);
        }
        return (int)$db->fetchOne($select) === 1;
    }

    /**
     * fetch review config from database
     * @param $rvId
     * @return array
     */
    public static function getData($rvId, $status = 1): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (is_numeric($rvId)) {
            $select = $db->select()->from('REVIEW')->where('RVID = ?', (int)$rvId);
        } else {
            $select = $db->select()->from('REVIEW')->where('CODE = ?', $rvId);
        }
        return $db->fetchRow($select);
    }

    public static function getCryptoFile(): string
    {
        $filePah = Episciences_Review::getCryptoFilePath();
        return file_exists($filePah) ? $filePah : '';

    }

    /**
     * @param int|null $docId
     * @param string|null $role
     * @param bool $isEditorsNotified // Assigned editors are automatically added as (hidden) copies of messages
     * @return string
     * @throws Zend_Db_Statement_Exception
     */
    private static function buildFyiStr(?int $docId, ?string $role = null, bool $isEditorsNotified = false): string
    {
        $paper = null;
        $cc = [];
        $fyi = '';


        if ($docId) {
            $paper = Episciences_PapersManager::get($docId, false);
        }


        if ($paper) {

            if (!$role) {

                self::checkReviewNotifications($cc);

                if ($isEditorsNotified) {

                    Episciences_Submit::addIfNotExists($paper->getEditors(), $cc);
                }

                Episciences_PapersManager::keepOnlyUsersWithoutConflict($paper->getPaperid(), $cc);

            } elseif ($role === Episciences_Acl::ROLE_REVIEWER) {

                $cc = $paper->getReviewers(null, true);
                self::fyiReviewersProcess($paper->getPaperid(), $cc);
            }

        }


        /** @var Episciences_User $recipient */
        foreach ($cc as $recipient) {

            $fyi .= $recipient->getFullName() . ' <' . $recipient->getEmail() . '>';
            $fyi .= '; ';
        }

        $fyi = substr($fyi, 0, -2);

        return !$fyi ? '' : $fyi;
    }

    /**
     * fetch review editors
     * if strict is true, only fetch editors.
     * if strict is false, fetch editors and chief editors
     * @param bool $strict
     * @return Episciences_User[]
     * @throws Zend_Db_Statement_Exception
     */
    public static function getEditors(bool $strict = true): array
    {
        if ($strict) {
            return self::getUsers(Episciences_Acl::ROLE_EDITOR);
        }

        return self::getUsers([Episciences_Acl::ROLE_CHIEF_EDITOR, Episciences_Acl::ROLE_EDITOR]);
    }

    /**
     * fetch an array of Episciences_User, optionnaly filtered by role
     * @param array |string|null $role
     * @return Episciences_User[]
     * @throws Zend_Db_Statement_Exception
     */
    public static function getUsers(array|string $role = null): array
    {
        $result = [];
        /** @var Episciences_User[] $users */
        $users = Episciences_UsersManager::getUsersWithRoles($role);

        if ($users) {

            foreach ($users as $uid => $user) {

                if (!empty($role)) {
                    if (is_array($role)) {
                        $role = $role[0];
                    }

                    $class_name = 'Episciences_' . ucfirst($role);

                }

                $options = $user->toArray();
                if (isset($class_name) && @class_exists($class_name)) {
                    $result[$uid] = new $class_name($options);
                } else {
                    $result[$uid] = new Episciences_User($options);
                }
            }

        }

        return $result;
    }

    /**
     * @return Episciences_User[]
     * @throws Zend_Db_Statement_Exception
     */
    public static function getCopyEditors(): array
    {
        return self::getUsers(Episciences_Acl::ROLE_COPY_EDITOR);
    }

    /**
     * fetch review guest editors
     * @return Episciences_User[]
     * @throws Zend_Db_Statement_Exception
     */
    public static function getGuestEditors(): array
    {
        return self::getUsers(Episciences_Acl::ROLE_GUEST_EDITOR);
    }

    /**
     * fetch review reviewers
     * @return Episciences_User[]
     * @throws Zend_Db_Statement_Exception
     */
    public static function getReviewers(): array
    {
        return self::getUsers(Episciences_Acl::ROLE_REVIEWER);
    }

    /**
     * fetch review webmasters
     * @return Episciences_User[]
     * @throws Zend_Db_Statement_Exception
     */
    public static function getWebmasters(): array
    {
        return self::getUsers(Episciences_Acl::ROLE_WEBMASTER);
    }

    /**
     * @return int|string|null
     * @throws Zend_Exception
     */
    public static function getDefaultLanguage()
    {
        $languages = Episciences_Tools::getLanguages();
        return (array_key_exists(self::DEFAULT_LANG, $languages)) ? self::DEFAULT_LANG : key($languages);
    }

    /**
     * @return int
     */
    public static function getCurrentReviewId(): int
    {
        return self::$_currentReviewId;
    }

    /**
     * @param int $currentReviewId
     */
    public static function setCurrentReviewId(int $currentReviewId)
    {
        self::$_currentReviewId = $currentReviewId;
    }

    /**
     * @param int|null $docId
     * @param string|null $role
     * @param bool $isEditorsNotified // Assigned editors are automatically added as (hidden) copies of messages
     * @return string
     */
    public static function forYourInformation(?int $docId = null, ?string $role = null, bool $isEditorsNotified = false): string
    {
        $fyi = '';

        try {
            $fyi = self::buildFyiStr($docId, $role, $isEditorsNotified);

        } catch (Exception $e) {
            error_log($e->getMessage());

        }

        return $fyi;
    }

    /**
     * get the list of users to be notified
     * @param array $recipients
     * @param bool $strict = false [ignore the notification's module]
     * @param int | string $rvId : (rvid or rvcode)
     * @throws Zend_Db_Statement_Exception
     */
    public static function checkReviewNotifications(array &$recipients, bool $strict = true, $rvId = RVID): void
    {
        $review = Episciences_ReviewsManager::find($rvId);

        $isChiefEditorsChecked = false;
        $isSecretariesChecked = false;
        $isAdministratorsChecked = false;

        $notificationSettings = $review->getSetting(self::SETTING_SYSTEM_NOTIFICATIONS);

        if ($notificationSettings) {
            $isChiefEditorsChecked = in_array(self::SETTING_SYSTEM_CAN_NOTIFY_CHIEF_EDITORS, $notificationSettings, true);
            $isSecretariesChecked = in_array(self::SETTING_SYSTEM_CAN_NOTIFY_SECRETARIES, $notificationSettings, true);
            $isAdministratorsChecked = in_array(self::SETTING_SYSTEM_CAN_NOTIFY_ADMINISTRATORS, $notificationSettings, true);
        }

        if (
            !$strict &&
            !$isChiefEditorsChecked &&
            !$isSecretariesChecked &&
            !$isAdministratorsChecked
        ) { //github#508: If no option is checked and no restriction, the notification is sent to everyone
            Episciences_Submit::addIfNotExists(self::getChiefEditors(), $recipients);
            Episciences_Submit::addIfNotExists(self::getSecretaries(), $recipients);
            Episciences_Submit::addIfNotExists(self::getAdministrators(), $recipients);

        } else { // only checked roles receive the notification
            if ($isChiefEditorsChecked) {
                Episciences_Submit::addIfNotExists(self::getChiefEditors(), $recipients);
            }

            if ($isSecretariesChecked) {
                Episciences_Submit::addIfNotExists(self::getSecretaries(), $recipients);
            }

            if ($isAdministratorsChecked) {
                Episciences_Submit::addIfNotExists(self::getAdministrators(), $recipients);
            }

        }
    }


    /**
     * get the specified setting
     * @param $setting
     * @return mixed
     */
    public function getSetting($setting)
    {
        if (count($this->_settings) === 0) {
            $this->loadSettings();
        }
        return Ccsd_Tools::ifsetor($this->_settings[$setting], false);
    }

    /**
     * load review settings from database
     */
    public function loadSettings(): void
    {
        // review configuration
        $select = Zend_Db_Table_Abstract::getDefaultAdapter()->select()->from(T_REVIEW_SETTINGS)->where('RVID = ' . $this->_rvid);

        $journalDoiSettings = [];
        foreach (Zend_Db_Table_Abstract::getDefaultAdapter()->fetchAll($select) as $row) {
            if (in_array($row['SETTING'], $this->_jsonSettings, false)) {
                $value = json_decode($row['VALUE'], true);
                $this->setSetting($row['SETTING'], $value);
            } elseif (in_array($row['SETTING'], Episciences_Review_DoiSettings::getDoiSettings(), false)) {
                $journalDoiSettings[$row['SETTING']] = $row['VALUE'];
            } else {
                $this->setSetting($row['SETTING'], $row['VALUE']);

            }
        }

        $doiSettings = new Episciences_Review_DoiSettings($journalDoiSettings);
        $this->setDoiSettings($doiSettings);

    }

    /**
     * fetch review chief editors
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public static function getChiefEditors(): array
    {
        return self::getUsers(Episciences_Acl::ROLE_CHIEF_EDITOR);
    }

    /**
     * fetch review editorial secretaries
     * @return Episciences_User[]
     * @throws Zend_Db_Statement_Exception
     */
    public static function getSecretaries(): array
    {
        return self::getUsers(Episciences_Acl::ROLE_SECRETARY);
    }

    /**
     * fetch review administrators
     * @return Episciences_User[]
     * @throws Zend_Db_Statement_Exception
     */
    public static function getAdministrators(): array
    {
        return self::getUsers(Episciences_Acl::ROLE_ADMIN);
    }

    /**
     * Journal's URL
     * @return string
     */
    public function getUrl(): string
    {
        return SERVER_PROTOCOL . '://' . $this->getCode() . '.' . DOMAIN;
    }

    /**
     * Journal's URL
     * @return string
     */
    public function getBackEndUrl(): string
    {

        if (!isset($_ENV['MANAGER_APPLICATION_URL']) || !Ccsd_Tools::isFromCli() || !$this->isNewFrontSwitched()) {
            return sprintf('%s://%s', SERVER_PROTOCOL, $_SERVER['SERVER_NAME']);
        }

        $url = rtrim($_ENV['MANAGER_APPLICATION_URL'], DIRECTORY_SEPARATOR);
        $url .= DIRECTORY_SEPARATOR;
        $url .= $this->getCode();

        return $url;
    }




    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->_code;
    }

    /**
     * @param $code
     * @return $this
     */
    public function setCode($code): self
    {
        $this->_code = $code;
        return $this;
    }

    /**
     * Récupération d'une liste d'article de la revue, peut être filtrée par des options
     * @param array|null $options
     * $options['is']['key'] = value     : WHERE key = value
     * $options['isNot']['key'] = value : WHERE key != value
     * $options['limit'] = limit    : LIMIT limit
     * $options['offset'] = offset     : LIMIT limit, offset
     * @param bool $cached
     * @param bool $isFilterInfos
     * @param string|array|Zend_Db_Expr $cols //The columns to select
     * @return Episciences_Paper[]
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public function getPapers(array $options = null, bool $cached = false, bool $isFilterInfos = false, string|array|Zend_Db_Expr $cols = '*'): array
    {
        $options['is']['rvid'] = $this->getRvid();
        return Episciences_PapersManager::getList($options, $cached, $isFilterInfos, true,  $cols);
    }

    /**
     * Retourne le nombre d'enregistrements
     * @param array $options
     * @param bool $isFilterInfos :  Lorsqu'un utilisateur filtre les informations dans une table,
     * un message est ajouté pour donner une idée de la force du filtrage.
     * @return string
     * @throws Zend_Exception
     */
    public function getPapersCount(array $options = [], bool $isFilterInfos = false): string
    {
        $options['is']['rvid'] = $this->getRvid();
        return Episciences_PapersManager::getCount($options, $isFilterInfos);
    }

    /**
     * @param array|null $limit
     * @return array
     */
    public function getCurrentIssues(array $limit = null): array
    {
        $subSelect = $this->_db->select()
            ->from(T_VOLUME_SETTINGS, ['VID'])
            ->where('SETTING = ?', 'current_issue')
            ->where('VALUE = ?', 1);

        $select = $this->_db->select()->from(T_VOLUMES);
        $select->where('VID IN (' . new Zend_db_Expr($subSelect) . ')');
        $select->where('RVID = ?', $this->getRvid());
        $select->order('POSITION ASC');
        if ($limit) {
            $select->limit($limit[0], $limit[1]);
        }
        $result = $this->_db->fetchAll($select);

        $volumes = [];

        foreach ($result as $volume) {
            Episciences_VolumesAndSectionsManager::dataProcess($volume, 'decode');
            $oVolume = new Episciences_Volume($volume);
            $volumes[$oVolume->getVid()] = $oVolume;
        }

        return $volumes;
    }

    /**
     * fetch all volumes with papers
     * @param array|null $limit
     * @return Episciences_Volume[]
     */
    public function getVolumesWithPapers(array $limit = null): array
    {
        return $this->getVolumesFromSql($this->getVolumesWithPapersQuery(), $limit);
    }

    /**
     * given a sql query, fetch an array of Episciences_Volume
     * @param Zend_Db_Select $select
     * @param null $limit
     * @return Episciences_Volume[]
     */
    private function getVolumesFromSql(Zend_Db_Select $select, $limit = null): array
    {
        if ($limit && is_array($limit)) {
            $select->limit($limit[0], $limit[1]);
        }
        $result = $this->_db->fetchAll($select);

        $volumes = [];
        foreach ($result as $volume) {
            Episciences_VolumesAndSectionsManager::dataProcess($volume, 'decode');
            $oVolume = new Episciences_Volume($volume);
            $volumes[$oVolume->getVid()] = $oVolume;
        }

        return $volumes;
    }

    /**
     * return a sql query for getting all volumes with papers
     * @return Zend_Db_Select
     */
    private function getVolumesWithPapersQuery(): \Zend_Db_Select
    {
        // select all papers which have a master volume
        $subSelect = $this->_db->select()
            ->from(T_PAPERS, ['VID'])
            ->where('RVID = ?', $this->getRvid())
            ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED)
            ->where('VID > 0');

        // select all papers which have secondary volumes
        $subSelect2 = $this->_db->select()
            ->from(['p' => T_PAPERS], [])
            ->join(['vp' => T_VOLUME_PAPER], 'p.DOCID = vp.DOCID', ['VID'])
            ->where('RVID = ?', $this->getRvid())
            ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED);

        // select all volumes with papers
        $select = $this->_db->select()->from(T_VOLUMES);
        $select->where('VID IN (' . new Zend_db_Expr($subSelect) . ') OR VID IN (' . new Zend_db_Expr($subSelect2) . ')');
        $select->where('RVID = ?', $this->getRvid());
        $select->order('POSITION');
        $select->order('VID DESC');

        return $select;
    }

    /**
     * fetch all special issues with papers
     * @param array|null $limit
     * @return Episciences_Volume[]
     */
    public function getSpecialIssuesWithPapers(array $limit = null): array
    {
        return $this->getVolumesFromSql($this->getSpecialIssuesWithPapersQuery(), $limit);
    }

    /**
     * return an sql query for getting all special volumes with papers
     * @return Zend_Db_Select
     */
    private function getSpecialIssuesWithPapersQuery(): \Zend_Db_Select
    {
        $select = $this->getVolumesWithPapersQuery();

        // select all special volumes
        $subSelect = $this->_db->select()
            ->from(T_VOLUME_SETTINGS, ['VID'])
            ->where('SETTING = ?', 'special_issue')
            ->where('VALUE = ?', 1);

        // select all special issues with papers
        $select->where('VID IN (' . new Zend_db_Expr($subSelect) . ')');


        return $select;
    }

    /**
     * fetch all regular issues with papers
     * @param array|null $limit
     * @return Episciences_Volume[]
     */
    public function getRegularIssuesWithPapers(array $limit = null): array
    {
        return $this->getVolumesFromSql($this->getRegularIssuesWithPapersQuery(), $limit);
    }

    /**
     * return an sql query for getting all regular volumes with papers
     * @return Zend_Db_Select
     */
    private function getRegularIssuesWithPapersQuery(): \Zend_Db_Select
    {
        $select = $this->getVolumesWithPapersQuery();

        // select all regular volumes
        $subSelect = $this->_db->select()
            ->from(T_VOLUME_SETTINGS, ['VID'])
            ->where('SETTING = ?', 'special_issue')
            ->where('VALUE = ?', 0);

        // select all regular issues with papers
        $select->where('VID IN (' . new Zend_db_Expr($subSelect) . ')');

        return $select;
    }

    /**
     * @param array|null $options
     * @param bool $toArray
     * @return array
     */
    public function getSections(array $options = null, bool $toArray = false): array
    {
        $options['where'] = 'RVID = ' . $this->getRvid();
        return Episciences_SectionsManager::getList($options, $toArray);
    }

    /**
     * Get sections with published papers
     * @param array|null $limit
     * @return array of Episciences_Section
     */
    public function getSectionsWithPapers(array $limit = null): array
    {
        $subSelect = $this->_db->select()
            ->from(T_PAPERS, ['SID'])
            ->where('RVID = ?', $this->getRvid())
            ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED)
            ->where('SID > 0');

        $select = $this->_db->select()->from(T_SECTIONS);
        $select->where('SID IN (' . new Zend_db_Expr($subSelect) . ')');
        $select->where('RVID = ?', $this->getRvid());
        $select->order('POSITION ASC');
        if ($limit) {
            $select->limit($limit[0], $limit[1]);
        }
        $result = $this->_db->fetchAll($select);

        $sections = [];
        foreach ($result as $section) {
            Episciences_VolumesAndSectionsManager::dataProcess($section, 'decode');
            $oSection = new Episciences_Section($section);
            $oSection->getEditors();
            $sections[$oSection->getSid()] = $oSection;
        }

        return $sections;
    }

    /**
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public function settingsForm(): Ccsd_Form
    {
        $form = new Ccsd_Form();
        $form->setAttrib('class', 'form-horizontal');
        $form->getDecorator('FormRequired')->setOption('style', 'float: none;');

        // global settings **********************************************
        $form->addElement('text', self::SETTING_ISSN, [
                'label' => '<abbr title="International Standard Serial Number">eISSN</abbr> (online)',
                'description' => 'Format attendu : <code>1234-5678</code>',
                'validators' => [new Zend_Validate_StringLength(['max' => 9, 'min' => 9])]
            ]
        );
        $form->addElement('text', self::SETTING_ISSN_PRINT, [
                'label' => '<abbr title="International Standard Serial Number">ISSN</abbr> (print)',
                'description' => 'Format attendu : <code>1234-5678</code>',
                'validators' => [new Zend_Validate_StringLength(['max' => 9, 'min' => 9])]
            ]
        );

        $form->addElement('text', self::SETTING_JOURNAL_DOI, [
                'label' => 'Le DOI de votre revue',
                'description' => 'À renseigner si la revue possède un DOI',
                'validators' => [new Zend_Validate_StringLength(['max' => 255])]
            ]
        );

        $form->addElement('text', self::SETTING_JOURNAL_PUBLISHER, [
                'label' => 'Éditeur',
                'description' => "Nom de l'éditeur de la revue",
                'validators' => [new Zend_Validate_StringLength(['max' => 255])]
            ]
        );

        $form->addElement('text', self::SETTING_JOURNAL_PUBLISHER_LOC, [
                'label' => 'Lieu de publication',
                'description' => 'Ville, Pays',
                'validators' => [new Zend_Validate_StringLength(['max' => 255])]
            ]
        );
        $form->addElement('text', self::SETTING_CONTACT_JOURNAL, [
                'label' => 'Page de contact de la revue',
                'description' => 'URL',
                'validators' => [new Zend_Validate_StringLength(['max' => 255])]
            ]
        );

        $form->addElement('text', self::SETTING_JOURNAL_NOTICE, [
                'label' => 'Page de la notice dans le catalogue',
                'description' => 'URL',
                'validators' => [new Zend_Validate_StringLength(['max' => 255])]
            ]
        );

        $form->addElement('text', self::SETTING_CONTACT_JOURNAL_EMAIL, [
                'label' => 'Courriel de contact de la revue',
                'description' => 'Adresse de courriel',
                'validators' => [new Zend_Validate_EmailAddress()]
            ]
        );

        $form->addElement('text', self::SETTING_CONTACT_TECH_SUPPORT_EMAIL, [
                'label' => 'Courriel de contact du support technique',
                'description' => 'Adresse de courriel',
                'validators' => [new Zend_Validate_EmailAddress()]
            ]
        );


        $form->getElement(self::SETTING_ISSN)->getDecorator('label')->setOption('class', 'col-md-2');
        $form->getElement(self::SETTING_ISSN_PRINT)->getDecorator('label')->setOption('class', 'col-md-2');
        $form->getElement(self::SETTING_JOURNAL_DOI)->getDecorator('label')->setOption('class', 'col-md-2');
        $form->getElement(self::SETTING_JOURNAL_PUBLISHER)->getDecorator('label')->setOption('class', 'col-md-2');
        $form->getElement(self::SETTING_JOURNAL_PUBLISHER_LOC)->getDecorator('label')->setOption('class', 'col-md-2');
        $form->getElement(self::SETTING_CONTACT_JOURNAL)->getDecorator('label')->setOption('class', 'col-md-2');
        $form->getElement(self::SETTING_JOURNAL_NOTICE)->getDecorator('label')->setOption('class', 'col-md-2');
        $form->getElement(self::SETTING_CONTACT_JOURNAL_EMAIL)->getDecorator('label')->setOption('class', 'col-md-2');
        $form->getElement(self::SETTING_CONTACT_TECH_SUPPORT_EMAIL)->getDecorator('label')->setOption('class', 'col-md-2');

        // display group: global settings
        $form->addDisplayGroup([self::SETTING_ISSN, self::SETTING_ISSN_PRINT, self::SETTING_JOURNAL_DOI, self::SETTING_CONTACT_JOURNAL, self::SETTING_JOURNAL_NOTICE, self::SETTING_JOURNAL_PUBLISHER, self::SETTING_JOURNAL_PUBLISHER_LOC, self::SETTING_CONTACT_JOURNAL_EMAIL, self::SETTING_CONTACT_TECH_SUPPORT_EMAIL], 'global', ["legend" => "Paramètres généraux (affichés dans le pied de page)"]);
        $form->getDisplayGroup('global')->removeDecorator('DtDdWrapper');

        // publication settings **********************************************
        //Repositories settings
        $form = $this->addRepositoriesSettingsForm($form);

        //contributors settings
        $form = $this->addContributorsSettingsForm($form);

        //rating settings
        $form = $this->addRatingSettingsForm($form);

        // editor settings **********************************************
        $form = $this->addEditorsSettingsForm($form);

        // special issue settings **********************************************
        $form = $this->addSpecialIssueSettingsForm($form);

        $form = $this->addNotificationSettingsForm($form);

        //Copy editing checkBox
        $form = $this->addCopyEditorForm($form);

        //COI
        $form = $this->addCoiForm($form);

        // Allow post-acceptance revisions of articles

        $form = $this->addFinalDecisionForm($form);
        $form = $this->toRequireRevisionDeadlineForm($form);
        $form = $this->addStatisticsForm($form);

        //redirection mail for errors

        $form = $this->addRedirectionMailError($form);


        // display group: publication settings
        $form->addDisplayGroup([
            self::SETTING_REPOSITORIES,
            self::SETTING_CAN_PICK_SECTION,
            self::SETTING_CAN_PICK_EDITOR,
            self::SETTING_DO_NOT_ALLOW_EDITOR_IN_CHIEF_SELECTION,
            self::SETTING_CAN_SUGGEST_REVIEWERS,
            self::SETTING_CAN_SPECIFY_UNWANTED_REVIEWERS,
            self::SETTING_CAN_ANSWER_WITH_TMP_VERSION,
            self::SETTING_CAN_CHOOSE_VOLUME,
            self::SETTING_CAN_RESUBMIT_REFUSED_PAPER,
            self::SETTING_CAN_ABANDON_CONTINUE_PUBLICATION_PROCESS,
            self::SETTING_ARXIV_PAPER_PASSWORD
        ], 'publication', ["legend" => "Paramètres de soumission"]);
        $form->getDisplayGroup('publication')->removeDecorator('DtDdWrapper');

        // display group : rating settings
        $form->addDisplayGroup([
            self::SETTING_INVITATION_DEADLINE,
            self::SETTING_RATING_DEADLINE,
            self::SETTING_RATING_DEADLINE_MIN,
            self::SETTING_RATING_DEADLINE_MAX,
            self::SETTING_REVIEWERS_CAN_COMMENT_ARTICLES,
            self::SETTING_SHOW_RATINGS,
            self::SETTING_AUTOMATICALLY_REASSIGN_SAME_REVIEWERS_WHEN_NEW_VERSION
        ], 'reviewing', ["legend" => "Paramètres de relecture"]);
        $form->getDisplayGroup('publication')->removeDecorator('DtDdWrapper');

        // display group : editor settings
        $form->addDisplayGroup([
            self::SETTING_REQUIRED_REVIEWERS,
            self::SETTING_ENCAPSULATE_EDITORS,
            self::SETTING_EDITORS_CAN_ACCEPT_PAPERS,
            self::SETTING_EDITORS_CAN_PUBLISH_PAPERS,
            self::SETTING_EDITORS_CAN_REJECT_PAPERS,
            self::SETTING_EDITORS_CAN_ASK_PAPER_REVISIONS,
            self::SETTING_EDITORS_CAN_EDIT_TEMPLATES,
            self::SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT,
            self::SETTING_EDITORS_CAN_ABANDON_CONTINUE_PUBLICATION_PROCESS,
        ], 'editors', ["legend" => "Paramètres des rédacteurs"]);
        $form->getDisplayGroup('editors')->removeDecorator('DtDdWrapper');

        $form->addDisplayGroup([
            self::SETTING_SYSTEM_NOTIFICATIONS,
            self::SETTING_REFUSED_ARTICLE_AUTHORS_MESSAGE_AUTOMATICALLY_SENT_TO_REVIEWERS
        ], 'notifications', ['legend' => "Paramètres de notification"]);
        $form->getDisplayGroup('notifications')->removeDecorator('DtDdWrapper');

        // display group : special issues settings
        $form->addDisplayGroup([
            self::SETTING_SPECIAL_ISSUE_ACCESS_CODE,
            self::SETTING_ENCAPSULATE_REVIEWERS,
            self::SETTING_EDITORS_CAN_REASSIGN_ARTICLES
        ], 'special_issues', ["legend" => "Paramètres des volumes spéciaux"]);
        $form->getDisplayGroup('special_issues')->removeDecorator('DtDdWrapper');

        // display group : copy editors settings
        $form->addDisplayGroup([
            self::SETTING_ENCAPSULATE_COPY_EDITORS
        ], 'copyEditors', ["legend" => "Préparation de copie"]);
        $form->getDisplayGroup('copyEditors')->removeDecorator('DtDdWrapper');

        $form->addDisplayGroup([
            self::SETTING_TO_REQUIRE_REVISION_DEADLINE,
            self::SETTING_SYSTEM_IS_COI_ENABLED,
            self::SETTING_SYSTEM_COI_COMMENTS_TO_EDITORS_ENABLED,
            self::SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION,
            self::SETTING_DISPLAY_STATISTICS,
            self::SETTING_START_STATS_AFTER_DATE,
            self::SETTING_CONTACT_ERROR_MAIL
        ], 'additionalParams', ['legend' => 'Paramètres supplémentaires']);

        $form->getDisplayGroup('additionalParams')->removeDecorator('DtDdWrapper');

        // submit button
        $form->setActions(true)->createSubmitButton('submit', [
                'label' => 'Enregistrer les paramètres',
                'class' => 'btn btn-primary'
            ]
        );

        return $form;
    }

    /**
     * @param Ccsd_Form $form
     * @return Ccsd_form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    private function addRepositoriesSettingsForm(Ccsd_Form $form): \Ccsd_form
    {
        $translator = Zend_Registry::get('Zend_Translate');
        // enabled repositories
        $repositories = [];
        foreach (Episciences_Repositories::getRepositories() as $repoId => $repo) {
            if ($repoId > 0) {
                $repositories[$repoId] = $repo[Episciences_Repositories::REPO_LABEL];
            }
        }

        $form->addElement('multiselect', self::SETTING_REPOSITORIES, [
            'label' => 'Archives disponibles',
            'description' => 'Liste des archives disponibles pour la soumission d\'articles.',
            'multiOptions' => $repositories,
            'value' => array_keys($repositories),
            'required' => true,
        ]);

        $form->getElement(self::SETTING_REPOSITORIES)->getDecorator('label')->setOptions([
            'class' => 'col-md-2',
            'data-toggle' => 'tooltip',
            'title' => $translator->translate('Choisissez les archives (au moins une) qui seront disponibles pour la soumission d\'articles.')
        ]);

        return $form;
    }

    /**
     * @param Ccsd_Form $form
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    private function addContributorsSettingsForm(Ccsd_Form $form): \Ccsd_Form
    {

        $checkboxDecorators = [
            'ViewHelper',
            'Description',
            ['Label', ['placement' => 'APPEND']],
            ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9']],
            ['Errors', ['placement' => 'APPEND']]
        ];

        // contributor can choose the volume
        $form->addElement('checkbox', self::SETTING_CAN_CHOOSE_VOLUME, [
                'label' => 'Permettre aux auteurs de choisir le volume',
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );

        // choix de sections
        $form->addElement('select', self::SETTING_CAN_PICK_SECTION, [
                'label' => 'Choix de rubrique',
                'value' => 1,
                'multioptions' => [
                    '0' => "L'auteur ne peut pas choisir la rubrique",
                    '1' => "L'auteur peut choisir la rubrique",
                    '2' => "L'auteur doit choisir la rubrique"
                ]]
        );

        $form->getElement(self::SETTING_CAN_PICK_SECTION)->getDecorator('label')->setOption('class', 'col-md-2');

        // contributor can suggest reviewers
        $form->addElement('checkbox', self::SETTING_CAN_SUGGEST_REVIEWERS, [
                'label' => 'Permettre aux auteurs de suggérer des relecteurs',
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );

        // contributor can specify unwanted reviewers
        $form->addElement('checkbox', self::SETTING_CAN_SPECIFY_UNWANTED_REVIEWERS, [
                'label' => 'Permettre aux auteurs d\'indiquer par qui ils ne souhaitent pas être relus',
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );

        // contributor can pick an editor
        $form->addElement('select', self::SETTING_CAN_PICK_EDITOR, [
                'label' => 'Choix du rédacteur',
                'value' => 0,
                'multioptions' => [
                    '0' => "L'auteur ne peut pas choisir de rédacteurs",
                    '1' => "L'auteur peut choisir des rédacteurs",
                    '2' => "L'auteur doit choisir des rédacteurs",
                    '3' => "L'auteur doit choisir un et un seul rédacteur"
                ]]
        );

        $form->getElement(self::SETTING_CAN_PICK_EDITOR)->getDecorator('label')->setOption('class', 'col-md-2');

        // contributor can answer with a tmp version
        $form->addElement('checkbox', self::SETTING_CAN_ANSWER_WITH_TMP_VERSION, [
            'label' => 'Permettre aux auteurs de répondre à une demande de modifications par une version temporaire',
            'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
            'decorators' => $checkboxDecorators]);

        // Contriburor can abandon(continue) publication process
        $form->addElement('checkbox', self::SETTING_CAN_ABANDON_CONTINUE_PUBLICATION_PROCESS, [
                'label' => "Permettre aux auteurs d’abandonner le processus de publication",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );

        // possibilité de soumettre une nouvelle version d'un article refusé
        $form->addElement('checkbox', self::SETTING_CAN_RESUBMIT_REFUSED_PAPER, [
                'label' => "Permettre aux auteurs de resoumettre un article déjà refusé (nouvelle version)",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );

        // Possibility to share the paper password for arxiv submissions
        $form->addElement('select', self::SETTING_ARXIV_PAPER_PASSWORD, [
                'label' => 'Permettre aux auteurs de partager le mot de passe papier arXiv',
                'description' => "L’auteur peut déléguer à la revue la mise à jour de sa soumission publiée sur arXiv",
                'value' => 0,

                'multioptions' => [
                    0 => 'Non',
                    1 => 'Facultatif',
                    2 => 'Requis',
                ],

            ]
        );

        return $form;

    }

    /**
     * @param Ccsd_form $form
     * @return Ccsd_form|Zend_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    private function addRatingSettingsForm(Ccsd_form $form)
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $checkboxDecorators = [
            'ViewHelper',
            'Description',
            ['Label', ['placement' => 'APPEND']],
            ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9']],
            ['Errors', ['placement' => 'APPEND']]
        ];

        // delay before invitation expires (default: one month)
        $form->addElement('text', self::SETTING_INVITATION_DEADLINE, [
                'label' => "Délai avant expiration d'une invitation",
                'style' => 'width: 40px',
                'required' => true,
                'decorators' => [['ViewScript', ['viewScript' => '/review/deadline_element.phtml']]]
            ]
        );

        // default delay between invitation date and rating deadline (default: 2 months)
        $tooltipMsg = $translator->translate("Délai laissé au relecteur pour rendre son rapport d'évaluation. La date limite est calculée à partir de la date d'envoi de l'invitation.");
        $tooltip = '<span class="lightgrey glyphicon glyphicon-question-sign" data-toggle="tooltip" data-placement="bottom" title="' . $tooltipMsg . '"></span> ';
        $label = $translator->translate("Délai de relecture par défaut");
        $form->addElement('text', self::SETTING_RATING_DEADLINE, [
                'label' => $tooltip . $label,
                'style' => 'width: 40px',
                'required' => true,
                'decorators' => [['ViewScript', ['viewScript' => '/review/deadline_element.phtml']]],
                'validators' => [new Episciences_Form_Validate_CheckDefaultRatingDeadline()]
            ]
        );

        // minimum delay between invitation date and rating deadline (default: 2 months)
        $tooltipMsg = $translator->translate("Les rédacteurs ont la possibilité de modifier le délai de relecture pour chacun des relecteurs d'un article dont ils ont la charge. Ce délai modifié ne peut pas être inférieur au paramètre <strong>Délai de relecture minimum</strong>.");
        $tooltip = '<span class="lightgrey glyphicon glyphicon-question-sign" data-toggle="tooltip" data-placement="bottom" title="' . $tooltipMsg . '"></span> ';
        $label = $translator->translate("Délai de relecture minimum");
        $form->addElement('text', self::SETTING_RATING_DEADLINE_MIN, [
                'label' => $tooltip . $label,
                'style' => 'width: 40px',
                'required' => true,
                'decorators' => [['ViewScript', ['viewScript' => '/review/deadline_element.phtml']]],
                'validators' => [new Episciences_Form_Validate_CheckMinimumDeadlineDelay()]
            ]
        );

        // maximum delay between invitation date and rating deadline (default: 6 months)
        $tooltipMsg = $translator->translate("Les rédacteurs ont la possibilité de modifier le délai de relecture pour chacun des relecteurs d'un article dont ils ont la charge. Ce délai modifié ne peut pas être supérieur au paramètre <strong>Délai de relecture maximum</strong>.");
        $tooltip = '<span class="lightgrey glyphicon glyphicon-question-sign" data-toggle="tooltip" data-placement="bottom" title="' . $tooltipMsg . '"></span> ';
        $label = $translator->translate("Délai de relecture maximum");
        $form->addElement('text', self::SETTING_RATING_DEADLINE_MAX, [
                'label' => $tooltip . $label,
                'style' => 'width: 40px',
                'required' => true,
                'decorators' => [['ViewScript', ['viewScript' => '/review/deadline_element.phtml']]],
                'validators' => [new Episciences_Form_Validate_CheckMaximumDeadlineDelay()]
            ]
        );

        // reviewers can comment paper
        $form->addElement('checkbox', self::SETTING_REVIEWERS_CAN_COMMENT_ARTICLES, [
                'label' => "Permettre aux relecteurs d'envoyer des messages à l'auteur",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );

        // display rating reports on paper public page
        $form->addElement('checkbox', self::SETTING_SHOW_RATINGS, [
                'label' => "Afficher les rapports de relecture sur la page de consultation publique d'un article",
                'description' => 'Concerne seulement les articles publiés',
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );

        //Réassigner automatiquement les mêmes relecteurs quand une nouvelle version est soumise"

        return $this->AddAutomaticallyReassignSameReviewersSettingsForm($form);

    }

    /**
     * @param Ccsd_Form $form
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    private function AddAutomaticallyReassignSameReviewersSettingsForm(Ccsd_Form $form): \Ccsd_Form
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $description = $translator->translate("Réassigner automatiquement les mêmes relecteurs quand une nouvelle version est soumise");
        $twoPoints = $translator->translate(" :");
        $mjrLabel = $translator->translate("En cas de demande de modifications majeures");
        $mrLabel = $translator->translate("En cas de demande de modifications mineures");
        $multiCheckboxOptions = [self::MAJOR_REVISION_ASSIGN_REVIEWERS => $mjrLabel, self::MINOR_REVISION_ASSIGN_REVIEWERS => $mrLabel];
        $multiCheckboxDecorators = [
            'ViewHelper',
            ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9']],
            ['Description', ['tag' => 'span', 'class' => 'hint']],
            ['Errors', ['placement' => 'APPEND']],
            ['Label', ['tag' => 'label', 'class' => "col-md-9 control-label"]]
        ];

        return $form->addElement('multiCheckbox', self::SETTING_AUTOMATICALLY_REASSIGN_SAME_REVIEWERS_WHEN_NEW_VERSION, [
            'description' => $description . $twoPoints,
            'multiOptions' => $multiCheckboxOptions,
            'decorators' => $multiCheckboxDecorators
        ]);
    }

    /**
     * @param Ccsd_Form $form
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    private function addEditorsSettingsForm(Ccsd_Form $form): \Ccsd_Form
    {

        $translator = Zend_Registry::get('Zend_Translate');
        $checkboxDecorators = [
            'ViewHelper',
            'Description',
            ['Label', ['placement' => 'APPEND']],
            ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9 col-md-offset-3']],
            ['Errors', ['placement' => 'APPEND']]
        ];


        $tooltipMsg = $translator->translate("Nombre minimum de relectures avant de pouvoir accepter un article.");
        $tooltip = '<span class="lightgrey glyphicon glyphicon-question-sign" data-toggle="tooltip" data-placement="bottom" title="' . $tooltipMsg . '"></span> ';
        $label = $translator->translate("Minimum de relectures requis");
        $form->addElement('text', self::SETTING_REQUIRED_REVIEWERS, [
                'label' => $tooltip . $label,
                'value' => 0,
                'style' => 'width: 40px']
        );

        $form->addElement('checkbox', self::SETTING_ENCAPSULATE_EDITORS, [
                'label' => "Cloisonner les rédacteurs",
                'description' => "S'ils sont cloisonnés, les rédacteurs ne peuvent voir que les articles qui leur sont assignés",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );

        $form->addElement('checkbox', self::SETTING_EDITORS_CAN_ACCEPT_PAPERS, [
                'label' => "Les rédacteurs peuvent accepter les articles",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );

        $form->addElement('checkbox', self::SETTING_EDITORS_CAN_PUBLISH_PAPERS, [
                'label' => "Les rédacteurs peuvent publier les articles",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );

        $form->addElement('checkbox', self::SETTING_EDITORS_CAN_REJECT_PAPERS, [
                'label' => "Les rédacteurs peuvent refuser les articles",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );

        $form->addElement('checkbox', self::SETTING_EDITORS_CAN_ASK_PAPER_REVISIONS, [
                'label' => "Les rédacteurs peuvent demander des modifications sur les articles",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );

        $form->addElement('checkbox', self::SETTING_EDITORS_CAN_EDIT_TEMPLATES, [
                'label' => "Permettre aux rédacteurs de modifier les templates de mails",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );

        // Editors can abandon(continue) publication process
        $form->addElement('checkbox', self::SETTING_EDITORS_CAN_ABANDON_CONTINUE_PUBLICATION_PROCESS, [
                'label' => "Permettre aux rédacteurs d’abandonner le processus de publication",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );

        $form->addElement('checkbox', self::SETTING_EDITORS_CAN_REASSIGN_ARTICLES, [
                'label' => "Les rédacteurs peuvent réattribuer la gestion de l'article",
                //'description'    =>    "",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );


        $form->addElement('checkbox', self::SETTING_DO_NOT_ALLOW_EDITOR_IN_CHIEF_SELECTION, [
                'label' => "Ne pas permettre le choix d'un rédacteur en chef",
                'description' => "Quand l'auteur a la possibilité de proposer un rédacteur lors de la soumission",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => [
                    'ViewHelper',
                    'Description',
                    ['Label', ['placement' => 'APPEND']],
                    ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-10 col-md-offset-2']],
                    ['Errors', ['placement' => 'APPEND']]
                ]
            ]
        );

        // TODO
        // editor can reassign paper to another editor

        // assignation auto des rédacteurs
        return $this->addAutoAssignationEditorsSettingsForm($form);
    }

    /**
     * @param Ccsd_Form $form
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    private function addAutoAssignationEditorsSettingsForm(Ccsd_Form $form): \Ccsd_Form
    {
        $translator = Zend_Registry::get('Zend_Translate');

        $checkboxDecorators = [
            'ViewHelper',
            'Description',
            ['Label', ['placement' => 'APPEND']],
            ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9 col-md-offset-3']],
            ['Errors', ['placement' => 'APPEND']]
        ];

        $chiefEditorsCheckBox = $translator->translate('Rédacteurs en chef');
        $sectionCheckBox = $translator->translate("Rédacteurs de rubrique");
        $volumeCheckBox = $translator->translate("Rédacteurs de volume (hors volume spécial)");
        $suggestedEditorsCheckBox = $translator->translate("Rédacteurs suggérés par le contributeur");
        $specialVolumeCheckBox = $translator->translate("Rédacteurs de volume spécial");

        $multiCheckboxOptions = [
            self::SETTING_SYSTEM_CAN_ASSIGN_CHIEF_EDITORS => $chiefEditorsCheckBox,
            self::SETTING_SYSTEM_CAN_ASSIGN_SECTION_EDITORS => $sectionCheckBox,
            self::SETTING_SYSTEM_CAN_ASSIGN_VOLUME_EDITORS => $volumeCheckBox,
            self::SETTING_SYSTEM_CAN_ASSIGN_SUGGEST_EDITORS => $suggestedEditorsCheckBox,
            self::SETTING_SYSTEM_CAN_ASSIGN_SPECIAL_VOLUME_EDITORS => $specialVolumeCheckBox
        ];

        $form->addElement('multiCheckbox', self::SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT, [
            'description' => $translator->translate("Lorsqu'un article est soumis, assigner les") . $translator->translate(" :"),
            'multiOptions' => $multiCheckboxOptions,
            'separator' => '<br>',
            'decorators' => $checkboxDecorators
        ]);

        return $form;
    }

    /**
     * @param Ccsd_Form $form
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    private function addSpecialIssueSettingsForm(Ccsd_Form $form): \Ccsd_Form
    {

        $checkboxDecorators = [
            'ViewHelper',
            'Description',
            ['Label', ['placement' => 'APPEND']],
            ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9 col-md-offset-3']],
            ['Errors', ['placement' => 'APPEND']]
        ];

        $form->addElement('checkbox', self::SETTING_SPECIAL_ISSUE_ACCESS_CODE, [
                'label' => "Protéger la soumission dans les volumes spéciaux par un code d'accès",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );

        $form->addElement('checkbox', self::SETTING_ENCAPSULATE_REVIEWERS, [
                'label' => "Cloisonner les relecteurs",
                'description' => "Lorsque les relecteurs sont cloisonnés, et qu’un article appartient à un volume spécial avec cette option activée, seuls les relecteurs attribués à ce volume seront proposés pour l’évaluation dans la section des relecteurs connus de la revue",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );

        $form->addElement('checkbox', self::SETTING_REFUSED_ARTICLE_AUTHORS_MESSAGE_AUTOMATICALLY_SENT_TO_REVIEWERS, [
            'label' => "Activer la fonctionnalité",
            'description' => "En cas de refus d'un article, le message envoyé aux auteurs/autrices expliquant la décision finale prise par le rédacteur/la rédactrice responsable est transmise automatiquement aux relecteurs/relectrices.",
            'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
            'decorators' => $checkboxDecorators
        ]);

        return $form;
    }

    /**
     * @param Ccsd_Form $form
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    private function addNotificationSettingsForm(Ccsd_Form $form): Zend_Form
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $twoPoints = $translator->translate(" :");

        $checkboxDecorators = [
            'ViewHelper',
            'Description',
            ['Label', ['placement' => 'APPEND']],
            ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9 col-md-offset-3']],
            ['Errors', ['placement' => 'APPEND']]
        ];
        $chiefEditorsCheckBox = $translator->translate('Rédacteurs en chef');
        $adminCheckBox = $translator->translate("Administrateurs");
        $secretaryCheckBox = $translator->translate("Secrétaires de rédaction");
        $multiCheckboxOptions = [self::SETTING_SYSTEM_CAN_NOTIFY_CHIEF_EDITORS => $chiefEditorsCheckBox, self::SETTING_SYSTEM_CAN_NOTIFY_ADMINISTRATORS => $adminCheckBox, self::SETTING_SYSTEM_CAN_NOTIFY_SECRETARIES => $secretaryCheckBox];

        return $form->addElement('multiCheckbox', self::SETTING_SYSTEM_NOTIFICATIONS, [
            'description' => $translator->translate("Lorsqu'un article est soumis, mis à jour ou refusé, notifier les") . $twoPoints,
            'multiOptions' => $multiCheckboxOptions,
            'separator' => '<br>',
            'decorators' => $checkboxDecorators
        ]);

    }

    /**
     * @param Ccsd_Form $form
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    private function addCopyEditorForm(Ccsd_Form $form): \Ccsd_Form
    {

        $checkboxDecorators = [
            'ViewHelper',
            'Description',
            ['Label', ['placement' => 'APPEND']],
            ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9 col-md-offset-3']],
            ['Errors', ['placement' => 'APPEND']]
        ];

        return $form->addElement('checkbox', self::SETTING_ENCAPSULATE_COPY_EDITORS, [
                'label' => "Cloisonner les préparateurs de copie",
                'description' => "S'ils sont cloisonnés, les préparateurs de copie ne peuvent voir que les articles qui leur sont assignés",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );
    }

    /**
     * @param Ccsd_Form $form
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    private function addCoiForm(Ccsd_Form $form): \Ccsd_Form
    {

        $checkboxDecorators = [
            'ViewHelper',
            'Description',
            ['Label', ['placement' => 'APPEND']],
            ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9 col-md-offset-3']],
            ['Errors', ['placement' => 'APPEND']]
        ];
        $form->addElement('checkbox', self::SETTING_SYSTEM_IS_COI_ENABLED, [
                'label' => "Activer la déclaration CI",
                'description' => "Le mode conflit d'intérêts (CI) aura les effets suivants : toutes les informations non publiques concernant une soumission ne sont pas accessibles aux éditeurs en chef et aux éditeurs tant qu'ils n'auront pas déclaré l'absence de tout conflit d'intérêts.",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );
        return $form->addElement('checkbox', self::SETTING_SYSTEM_COI_COMMENTS_TO_EDITORS_ENABLED, [
                'label' => "Les rédacteurs reçoivent les 'commentaires pour les rédacteurs' avant la déclaration d'un conflit d'intérêt",
                'description' => "Activer pour permettre aux éditeurs de recevoir des commentaires sur l'article avant d'avoir déclaré un conflit d'intérêts.",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );
    }

    /**
     * @param Ccsd_Form $form
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    private function addFinalDecisionForm(Ccsd_Form $form): \Ccsd_Form
    {
        $checkboxDecorators = [
            'ViewHelper',
            'Description',
            ['Label', ['placement' => 'APPEND']],
            ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9 col-md-offset-3']],
            ['Errors', ['placement' => 'APPEND']]
        ];

        return $form->addElement('checkbox', self::SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION, [
                'label' => "Permettre la demande de revision",
                'description' => "Cette option permet de réviser les articles après leur acceptation, par exemple en demandant de nouvelles versions des prépublications acceptées",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );
    }

    private function addRedirectionMailError(Ccsd_Form $form): \Ccsd_Form
    {
        // Possibility to share the paper password for arxiv submissions
        return $form->addElement('select', self::SETTING_CONTACT_ERROR_MAIL, [
                'label' => 'Courriel de retour',
                'description' => "Sélectionner l'adresse qui recevra les échecs d'envoi de courriels",
                'value' => 0,
                'multiOptions' => [
                    0 => 'error@' . DOMAIN,
                    1 => $this->getCode() . '-error@' . DOMAIN,
                ],

            ]
        );
    }

    /**
     * @param Ccsd_Form $form
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */

    private function toRequireRevisionDeadlineForm(Ccsd_Form $form): \Ccsd_Form
    {
        $checkboxDecorators = [
            'ViewHelper',
            'Description',
            ['Label', ['placement' => 'APPEND']],
            ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9 col-md-offset-3']],
            ['Errors', ['placement' => 'APPEND']]
        ];

        return $form->addElement('checkbox', self::SETTING_TO_REQUIRE_REVISION_DEADLINE, [
                'label' => "Exiger que la demande de révision soit assortie d'un délai",
                'description' => "",
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
                'decorators' => $checkboxDecorators]
        );
    }

    private function addStatisticsForm(Ccsd_Form $form): \Ccsd_Form
    {
        $checkboxDecorators = [
            'ViewHelper',
            'Description',
            ['Label', ['placement' => 'APPEND']],
            ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9 col-md-offset-3']],
            ['Errors', ['placement' => 'APPEND']]
        ];


        $form->addElement('select', self::SETTING_DISPLAY_STATISTICS, [
                'label' => 'Visibilité des statistiques',
                'description' => "",
                'value' => 0,
                'placeholder' => '',

                'multioptions' => [
                    0 => 'Par défaut (cachée)',
                    1 => 'Publique',
                    2 => 'Réservée aux administrateurs',
                ],

            ]
        );


        $form->addElement('date', self::SETTING_START_STATS_AFTER_DATE, [
            'id' => self::SETTING_START_STATS_AFTER_DATE,
            'label' => '',
            'value' => '',
            'placeholder' => 'YYYY-MM-DD',
            'style' => 'width: 18%',
            'size' => '10',
            'maxlength' => '10',
            'maxsizelignt' => '10',
            'description' => "Il est possible de renseigner une date de début de statistiques au format: AAAA-MM-JJ",
            'decorators' => $checkboxDecorators,
            'pattern' => '(\d{4})-(0?[1-9]|1[0-2])-(0?[1-9]|[12]\d|30|31)',
            'validators' => [new Zend_Validate_Date('Y-m-d')]
        ]);

        return $form;


    }

    /**
     * Save review settings in DB
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function save(): bool
    {
        $allSettings = [];

        $settings = [
            self::SETTING_ISSN, self::SETTING_ISSN_PRINT, self::SETTING_JOURNAL_DOI,
            self::SETTING_CONTACT_JOURNAL, self::SETTING_JOURNAL_NOTICE,
            self::SETTING_CONTACT_JOURNAL_EMAIL, self::SETTING_CONTACT_TECH_SUPPORT_EMAIL,
            self::SETTING_REPOSITORIES, self::SETTING_CAN_CHOOSE_VOLUME,
            self::SETTING_CAN_PICK_SECTION, self::SETTING_CAN_SUGGEST_REVIEWERS,
            self::SETTING_CAN_SPECIFY_UNWANTED_REVIEWERS, self::SETTING_CAN_PICK_EDITOR,
            self::SETTING_DO_NOT_ALLOW_EDITOR_IN_CHIEF_SELECTION, self::SETTING_CAN_ANSWER_WITH_TMP_VERSION,
            self::SETTING_REVIEWERS_CAN_COMMENT_ARTICLES, self::SETTING_SHOW_RATINGS,
            self::SETTING_REQUIRED_REVIEWERS, self::SETTING_ENCAPSULATE_EDITORS,
            self::SETTING_EDITORS_CAN_ACCEPT_PAPERS, self::SETTING_EDITORS_CAN_PUBLISH_PAPERS,
            self::SETTING_EDITORS_CAN_REJECT_PAPERS, self::SETTING_EDITORS_CAN_ASK_PAPER_REVISIONS,
            self::SETTING_EDITORS_CAN_EDIT_TEMPLATES, self::SETTING_SPECIAL_ISSUE_ACCESS_CODE,
            self::SETTING_ENCAPSULATE_REVIEWERS, self::SETTING_EDITORS_CAN_REASSIGN_ARTICLES,
            self::SETTING_ENCAPSULATE_COPY_EDITORS, self::SETTING_AUTOMATICALLY_REASSIGN_SAME_REVIEWERS_WHEN_NEW_VERSION,
            self::SETTING_SYSTEM_NOTIFICATIONS, self::SETTING_CAN_ABANDON_CONTINUE_PUBLICATION_PROCESS,
            self::SETTING_EDITORS_CAN_ABANDON_CONTINUE_PUBLICATION_PROCESS, self::SETTING_CAN_RESUBMIT_REFUSED_PAPER,
            self::SETTING_SYSTEM_IS_COI_ENABLED, self::SETTING_SYSTEM_COI_COMMENTS_TO_EDITORS_ENABLED,
            self::SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION, self::SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT,
            self::SETTING_ARXIV_PAPER_PASSWORD, self::SETTING_DISPLAY_STATISTICS, self::SETTING_CONTACT_ERROR_MAIL,
            self::SETTING_REFUSED_ARTICLE_AUTHORS_MESSAGE_AUTOMATICALLY_SENT_TO_REVIEWERS,
            self::SETTING_TO_REQUIRE_REVISION_DEADLINE, self::SETTING_START_STATS_AFTER_DATE
        ];


        foreach ($settings as $setting) {
            $allSettings[$setting] = $this->getSetting($setting);
        }

        // Deadlines with units
        $deadlines = [
            self::SETTING_RATING_DEADLINE => self::SETTING_RATING_DEADLINE_UNIT,
            self::SETTING_RATING_DEADLINE_MIN => self::SETTING_RATING_DEADLINE_MIN_UNIT,
            self::SETTING_RATING_DEADLINE_MAX => self::SETTING_RATING_DEADLINE_MAX_UNIT,
            self::SETTING_INVITATION_DEADLINE => self::SETTING_INVITATION_DEADLINE_UNIT,
        ];

        foreach ($deadlines as $key => $unitKey) {
            $allSettings[$key] = $this->getSetting($key) . ' ' . $this->getSetting($unitKey);
        }

        // Publisher information
        $allSettings[self::SETTING_JOURNAL_PUBLISHER] = trim(strip_tags((string)$this->getSetting(self::SETTING_JOURNAL_PUBLISHER)));
        $allSettings[self::SETTING_JOURNAL_PUBLISHER_LOC] = trim(strip_tags((string)$this->getSetting(self::SETTING_JOURNAL_PUBLISHER_LOC)));

        if ($allSettings[self::SETTING_JOURNAL_PUBLISHER] === '' && $allSettings[self::SETTING_JOURNAL_PUBLISHER_LOC] !== '') {
            return false;
        }

        // DOI settings

        $doiSettings = $this->getDoiSettings();
        $allSettings = array_merge($allSettings, $doiSettings->__toArray());

        $values = [];

        // Enregistrement des paramètres
        foreach ($allSettings as $setting => $value) {
            $setting = $this->_db->quote($setting);
            if (is_array($value) && !empty($value)) {
                $value = Zend_Json::encode($value);
            }
            $value = $this->_db->quote($value);
            $values[] = '(' . $this->_rvid . ',' . $setting . ',' . $value . ')';
        }

        $sql = 'INSERT INTO ';
        $sql .= T_REVIEW_SETTINGS;
        $sql .= ' (RVID, SETTING, VALUE) VALUES ';
        $sql .= implode(',', $values);
        $sql .= ' ON DUPLICATE KEY UPDATE VALUE = VALUES(VALUE)';

        if (!$this->_db->getConnection()?->query($sql)) {
            return false;
        }

        $this->checkAndCreateIfNotExistsCryptoFile();

        return true;
    }

    /**
     * @return Episciences_Review_DoiSettings
     */
    public function getDoiSettings(): Episciences_Review_DoiSettings
    {
        return $this->_doiSettings;
    }

    /**
     * @param Episciences_Review_DoiSettings $doiSettings
     */
    public function setDoiSettings(Episciences_Review_DoiSettings $doiSettings): void
    {
        $this->_doiSettings = $doiSettings;
    }

    /**
     * delete settings
     */
    public function deleteSettings(): int
    {
        return $this->_db->delete(T_REVIEW_SETTINGS, 'RVID = ' . $this->_rvid . ' AND SETTING != "domains"');
    }

    /**
     * get repositories
     * @return array|mixed
     */
    public function getRepositories()
    {
        if (array_key_exists('repositories', $this->_settings)) {
            return $this->_settings['repositories'];
        }

        return [];
    }

    /**
     * get translations path
     * @return string
     */
    public function getTranslationsPath(): string
    {
        $translationPath = realpath($this->getPath() . '/languages') . '/';

        if ($translationPath === '/') {
            return APPLICATION_PATH . '/data/languages/'; //avoid loading translations from the root directory ($realpathPath == false, '/' returned)
        }

        return $translationPath;

    }

    /**
     * get review data path
     * @return bool|string
     */
    public function getPath()
    {
        return realpath(APPLICATION_PATH . '/../data/' . $this->getCode());
    }

    /**
     * get review name
     * @return string
     */
    public function getName(): string
    {
        return $this->_name;
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

    public function getCreation()
    {
        return $this->_creation;
    }

    /**
     * @param $creation
     * @return $this
     */
    public function setCreation($creation): self
    {
        $this->_creation = $creation;
        return $this;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->_status;
    }

    /**
     * @param $status
     * @return $this
     */
    public function setStatus($status): self
    {
        $this->_status = $status;
        return $this;
    }

    /**
     * @return int
     */
    public function getPiwikid(): int
    {
        return $this->_piwikid;
    }

    /**
     * @param int $piwikid
     * @return $this
     */
    public function setPiwikid(int $piwikid): self
    {
        $this->_piwikid = $piwikid;
        return $this;
    }

    /**
     * @deprecated : see suggestions: git #182
     * @return Ccsd_Form|Zend_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public function getEditorsAssignationForm()
    {
        $this->loadSettings();

        $form = new Ccsd_Form();
        $form->setAttrib('class', 'form-horizontal');
        $form->getDecorator('FormRequired')->setOption('style', 'float: none;');

        $multiOptions = [];

        $multiOptions[self::ASSIGNMENT_EDITORS_MODE['default']] = $this->getDefaultLabel();
        $multiOptions[self::ASSIGNMENT_EDITORS_MODE['predefined']] = "Prédéfini (tous)";
        $multiOptions[self::ASSIGNMENT_EDITORS_MODE['advanced']] = "Assignation avancée";

        // Mode d'assignation des rédacteurs
        $form->addElement('select', self::SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT, [
            'label' => 'Mode',
            'description' => "Selon le mode choisi, les soumissions sont automatiquement attribuées à tous les éditeurs",
            'value' => $this->getSetting(self::SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT),
            'multioptions' => $multiOptions]);

        $form->addSubForm($this->getEditorsAssignationDetailForm(), 'advancedAssignation');
        return $form;
    }

    /**
     * @return string
     * @throws Zend_Db_Statement_Exception
     */
    private function getDefaultLabel(): string
    {
        if (empty(self::getChiefEditors())) {
            if (empty(self::getAdministrators())) {
                if (empty(self::getSecretaries())) {
                    $label = 'Aucun';
                } else {
                    $label = 'Par defaut, notifier les secrétaires de rédaction';
                }
            } else {
                $label = 'Par defaut, notifier les administrateurs';
            }
        } else {
            $label = 'Par defaut, seulement les rédacteurs en chef';
        }
        return $label;
    }

    /**
     * @deprecated see suggestions: git #182
     * @return Ccsd_Form_SubForm
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public function getEditorsAssignationDetailForm(): \Ccsd_Form_SubForm
    {

        $form = new Ccsd_Form_SubForm();
        $translator = Zend_Registry::get('Zend_Translate');

        $backButtonOptions = [
            'class' => 'btn btn-default',
            'label' => 'Retour aux paramètres de la revue',
            'onclick' => 'window.location.href=JS_PREFIX_URL + "review/setting";',
            'decorators' => ['ViewHelper', ['HtmlTag', ['tag' => 'div', 'closeOnly' => true]]]
        ];

        $selectedOptions = [];
        $assignmentOptionsLabels = [];

        if (!empty(self::getChiefEditors())) {
            $assignmentOptionsLabels [self:: ASSIGNMENT_EDITORS_DETAIL[self::SETTING_SYSTEM_CAN_ASSIGN_CHIEF_EDITORS]] = 'Assigner tous les rédacteurs en chef';
        }

        if ((int)$this->getSetting(self::SETTING_CAN_PICK_SECTION) > 0) {
            $assignmentOptionsLabels [self:: ASSIGNMENT_EDITORS_DETAIL[self::SETTING_SYSTEM_CAN_ASSIGN_SECTION_EDITORS]] = 'Assigner tous les rédacteurs de la rubrique';

        }

        if ((int)$this->getSetting(self::SETTING_CAN_CHOOSE_VOLUME) > 0) {
            $assignmentOptionsLabels [self:: ASSIGNMENT_EDITORS_DETAIL[self::SETTING_SYSTEM_CAN_ASSIGN_VOLUME_EDITORS]] = 'Assigner tous les rédacteurs du volume (hors volume spécial)';
        }

        $assignmentOptionsLabels [self:: ASSIGNMENT_EDITORS_DETAIL[self::SETTING_SYSTEM_CAN_ASSIGN_SPECIAL_VOLUME_EDITORS]] = 'Assigner tous les rédacteurs du volume spécial';

        if ((int)$this->getSetting(self::SETTING_CAN_PICK_EDITOR) > 0) {
            $assignmentOptionsLabels[self::ASSIGNMENT_EDITORS_DETAIL[self::SETTING_SYSTEM_CAN_ASSIGN_SUGGEST_EDITORS]] = 'Assigner tous les rédacteurs suggérés par le contributeur';
        }

        if (!empty(self::getAdministrators())) {
            $assignmentOptionsLabels [self:: ASSIGNMENT_EDITORS_DETAIL[self::SETTING_SYSTEM_CAN_NOTIFY_ADMINISTRATORS]] = 'Notifier tous les administrateurs';
        }

        if (!empty(self::getSecretaries())) {
            $assignmentOptionsLabels [self:: ASSIGNMENT_EDITORS_DETAIL[self::SETTING_SYSTEM_CAN_NOTIFY_SECRETARIES]] = 'Notifier tous les secrétaires de rédaction';
        }

        if ($this->getSetting(self::SETTING_SYSTEM_CAN_ASSIGN_CHIEF_EDITORS)) {
            $selectedOptions[self::ASSIGNMENT_EDITORS_DETAIL[self::SETTING_SYSTEM_CAN_ASSIGN_CHIEF_EDITORS]] = 'Assigner tous les rédacteurs en chef';
        }

        if ($this->getSetting(self::SETTING_SYSTEM_CAN_ASSIGN_SECTION_EDITORS)) {
            $selectedOptions[self::ASSIGNMENT_EDITORS_DETAIL[self::SETTING_SYSTEM_CAN_ASSIGN_SECTION_EDITORS]] = 'Assigner tous les rédacteurs de la rubrique';
        }

        if ($this->getSetting(self::SETTING_SYSTEM_CAN_ASSIGN_SPECIAL_VOLUME_EDITORS)) {
            $selectedOptions[self::ASSIGNMENT_EDITORS_DETAIL[self::SETTING_SYSTEM_CAN_ASSIGN_SPECIAL_VOLUME_EDITORS]] = 'Assigner tous les rédacteurs du volume spécial';
        }

        if ($this->getSetting(self::SETTING_SYSTEM_CAN_ASSIGN_VOLUME_EDITORS)) {
            $selectedOptions[self::ASSIGNMENT_EDITORS_DETAIL[self::SETTING_SYSTEM_CAN_ASSIGN_VOLUME_EDITORS]] = 'Assigner tous les rédacteurs du volume (hors volume spécial)';
        }

        if ($this->getSetting(self::SETTING_SYSTEM_CAN_ASSIGN_SUGGEST_EDITORS)) {
            $selectedOptions[self::ASSIGNMENT_EDITORS_DETAIL[self::SETTING_SYSTEM_CAN_ASSIGN_SUGGEST_EDITORS]] = 'Assigner tous les rédacteurs suggérés par le contributeur';
        }

        if ($this->getSetting(self::SETTING_SYSTEM_CAN_NOTIFY_ADMINISTRATORS)) {
            $selectedOptions[self::ASSIGNMENT_EDITORS_DETAIL[self::SETTING_SYSTEM_CAN_NOTIFY_ADMINISTRATORS]] = 'Notifier tous les administraeurs';
        }

        if ($this->getSetting(self::SETTING_SYSTEM_CAN_NOTIFY_SECRETARIES)) {
            $selectedOptions[self::ASSIGNMENT_EDITORS_DETAIL[self::SETTING_SYSTEM_CAN_NOTIFY_SECRETARIES]] = 'Notifier tous les secrétaires de rédaction';
        }


        $advancedMultiSelectOptions = [
            'label' => "Option(s)",
            'disabled' => 'disabled',
            'multiOptions' => $assignmentOptionsLabels,
            'value' => array_keys($selectedOptions),
            'required' => $this->getSetting(self::SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT) === self::ASSIGNMENT_EDITORS_MODE['advanced']
        ];

        if ($this->getSetting(self::SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT) === self::ASSIGNMENT_EDITORS_MODE['advanced']) {
            $advancedMultiSelectOptions['description'] = "Liste des options disponibles";
            unset($advancedMultiSelectOptions['disabled']);
        }

        $form->addElement('multiselect', self::SETTING_EDITORS_ASSIGNMENT_DETAILS, $advancedMultiSelectOptions);
        $form->getElement(self::SETTING_EDITORS_ASSIGNMENT_DETAILS)->getDecorator('label')->setOptions([
            'class' => 'col-md-3 control-label',
            'data-toggle' => 'tooltip',
            'title' => $translator->translate("Choisissez au moins une option")
        ]);

        if ($this->getSetting(self::SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT) === self::ASSIGNMENT_EDITORS_MODE['advanced']) {

            $form->addElement('submit', 'save', [
                'label' => "Enregistrer les paramètres",
                'class' => 'btn btn-primary',
                'decorators' => [['HtmlTag', ['tag' => 'div', 'id' => 'save-content', 'openOnly' => true, 'class' => 'pull-right']], 'ViewHelper']
            ]);

            // back to settings
            $form->addElement('button', 'back', $backButtonOptions);

        } else {

            $backButtonOptions['decorators'] = [['HtmlTag', ['tag' => 'div', 'id' => 'save-content', 'openOnly' => true, 'class' => 'pull-right']], 'ViewHelper'];
            // back to
            $form->addElement('button', 'back', $backButtonOptions);

        }

        return $form;
    }

    /**
     *
     * @param bool $accessCodeFilterActivated //(default = true : check special volumes)
     * @return array
     */
    public function getVolumesOptions(bool $accessCodeFilterActivated = true): array
    {
        // Récupération des volumes
        $options[] = "Hors volume";
        $volumes = $this->getVolumes();
        $settings = $this->getSettings();
        /** @var Episciences_Volume $oVolume */
        foreach ($volumes as $oVolume) {
            $oVolume->loadSettings();

            if ($accessCodeFilterActivated) {
                if (
                    (int)$oVolume->getSetting('status') === 1 &&
                    (
                        (int)$oVolume->getSetting(Episciences_Volume::SETTING_SPECIAL_ISSUE) !== 1 ||
                        (
                            !array_key_exists(self::SETTING_SPECIAL_ISSUE_ACCESS_CODE, $settings) ||
                            (int)$settings[self::SETTING_SPECIAL_ISSUE_ACCESS_CODE] !== 1
                        )
                    )
                ) {
                    $options[$oVolume->getVid()] = $oVolume->getNameKey();
                }
            } else { // all volumes name keys
                $options[$oVolume->getVid()] = $oVolume->getNameKey();
            }

        }
        return $options;
    }

    /**
     * @param array|null $options
     * @param bool $toArray
     * @return array
     */
    public function getVolumes(array $options = null, $toArray = false): array
    {
        $options['where'] = 'RVID = ' . $this->getRvid();
        return Episciences_VolumesManager::getList($options, $toArray);
    }

    /**
     * get review settings
     * @return array
     */
    public function getSettings(): array
    {
        return $this->_settings;
    }


    public function isNewFrontSwitched(): bool
    {
        return $this->isNewFrontSwitched;
    }

    public function setIsNewFrontSwitched(bool $isNewFrontSwitched ): self
    {
        $this->isNewFrontSwitched = $isNewFrontSwitched;
        return $this;
    }

    /**
     * @deprecated see suggestions : git #182
     * Initialisation de parametres d'assignation automatique de rédacteurs
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    private function editorsAssignationDetailsInit(): array
    {
        $settingsValues = [];
        $autoAssignment = $this->getSetting(self::SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT);
        // Initialisation de paramètres d'assignation de rédacteurs
        foreach (self::ASSIGNMENT_EDITORS_DETAIL as $setting => $order) {

            if ($autoAssignment === self::ASSIGNMENT_EDITORS_MODE['default']) { // default mode

                $this->applyDefaultAssignation($setting, $settingsValues);

            } elseif ($autoAssignment === self::ASSIGNMENT_EDITORS_MODE['predefined']) { // Assigner tous les rédacteurs (volume + section + rédacteurs suggérés)
                $this->applyPredefinedAssignation($setting, $settingsValues);

            } else {
                $settingsValues[$setting] = $this->getSetting($setting);
            }
        }
        return $settingsValues;
    }

    /**
     * @deprecated see suggestions : git #182
     * @param string $setting
     * @param array $settingsValues
     * @throws Zend_Db_Statement_Exception
     */
    private function applyDefaultAssignation(string $setting, array &$settingsValues): void
    {
        if (!empty(self::getChiefEditors())) {// toutes les options sont désactivées sauf l'assignation des rédacteurs en chef
            $settingsValues[$setting] = ($setting === self::SETTING_SYSTEM_CAN_ASSIGN_CHIEF_EDITORS) ? $settingsValues[$setting] = self::ENABLED : $settingsValues[$setting] = self::DISABLED;
        } else if (!empty(self::getAdministrators())) {
            $settingsValues[$setting] = ($setting === self::SETTING_SYSTEM_CAN_NOTIFY_ADMINISTRATORS) ? $settingsValues[$setting] = self::ENABLED : $settingsValues[$setting] = self::DISABLED;
        } elseif (!empty(self::getSecretaries())) {// toutes les options sont désactivées sauf la notification des secrétaires de rédaction
            $settingsValues[$setting] = ($setting === self::SETTING_SYSTEM_CAN_NOTIFY_SECRETARIES) ? $settingsValues[$setting] = self::ENABLED : $settingsValues[$setting] = self::DISABLED;
        } else { // AUCUN
            $settingsValues[$setting] = self::DISABLED;
        }
    }

    /**
     * @deprecated see suggestions : git #182
     * @param string $setting
     * @param array $settingsValues
     */
    private function applyPredefinedAssignation(string $setting, array &$settingsValues): void
    {
        if ($setting === self::SETTING_SYSTEM_CAN_ASSIGN_SUGGEST_EDITORS) {
            $settingsValues[$setting] = (int)$this->getSetting(self::SETTING_CAN_PICK_EDITOR) > 0 ? self::ENABLED : self::DISABLED;
            return;
        }

        if ($setting === self::SETTING_SYSTEM_CAN_NOTIFY_SECRETARIES || $setting === self::SETTING_SYSTEM_CAN_NOTIFY_ADMINISTRATORS) { // pas de notifications
            $settingsValues[$setting] = self::DISABLED;
            return;
        }

        $settingsValues[$setting] = self::ENABLED; // toutes les autres options sont actives
    }

    /**
     * @param Ccsd_Form $form
     * @return Zend_Form
     * @throws Zend_Form_Exception
     */
    private function addDescriptionSettingsForm(Ccsd_Form $form): \Zend_Form
    {
        $lang = ['class' => 'Episciences_Tools', 'method' => 'getLanguages'];

        return $form->addElement('MultiTextAreaLang', 'description', [
            'label' => 'Description',
            'populate' => $lang,
            'tiny' => true,
            'rows' => 5,
            //'display' => Ccsd_Form_Element_MultiText::DISPLAY_ADVANCED
        ]);

    }

    /**
     * @param array $options
     * @param string $title
     * @deprecated
     */
    private function disable(array &$options, string $title = ""): void
    {

        $options['disabled'] = true;
        $options ['data-toggle'] = 'tooltip';
        $options ['title'] = $title;
    }

    /**
     * @param array $options
     * @deprecated
     */
    private function enable(array &$options): void
    {
        if (array_key_exists('disabled', $options)) {
            unset($options['disabled']);
        }
        if (array_key_exists('data-toggle', $options)) {
            unset($options['data-toggle']);
        }

        if (array_key_exists('title', $options)) {
            unset($options['title']);
        }
    }

    /**
     * A mapping between Episciences and HAL journal IDs
     * @param bool $keysAreEpisciencesJournalIds
     * @return array
     */
    public static function getHalJournalMappings(bool $keysAreEpisciencesJournalIds = true): array
    {
        $mappingsArray = [];
        $mappingsArrayMapped = [];
        $mappings = file_get_contents(APPLICATION_PATH . '/../config/halJournalMappings.json');
        if ($mappings) {
            $mappingsArray = json_decode($mappings, true);
        }

        if ($mappingsArray === null) {
            $mappingsArray = [];
        }

        if (($keysAreEpisciencesJournalIds) && !(empty($mappingsArray))) {
            foreach ($mappingsArray as $journal) {
                $mappingsArrayMapped[$journal['episciencesJournalId']] = $journal['halReferentialId'];
            }
            $mappingsArray = $mappingsArrayMapped;
        }

        return $mappingsArray;
    }

    /**
     * @return void
     */
    private function checkAndCreateIfNotExistsCryptoFile(): void
    {


        if (in_array(Episciences_Repositories::ARXIV_REPO_ID, self::getSetting(self::SETTING_REPOSITORIES)) && $this->getSetting(self::SETTING_ARXIV_PAPER_PASSWORD)) {

            $path = self::getCryptoFilePath();

            if (!file_exists($path)) {
                try {
                    if (!file_put_contents($path, json_encode(['key' => Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString()]), LOCK_EX)) {
                        die('Fatal error: unable to create file: ' . $path);
                    }
                } catch (\Defuse\Crypto\Exception\EnvironmentIsBrokenException $e) {
                    error_log($e->getMessage());
                }

                @chmod($path, 0444); // read only
            }

        }
    }

    /**
     * @return string
     */
    public static function getCryptoFilePath(): string
    {
        return REVIEW_FILES_PATH . RVCODE . '-crypto.json';
    }

    /**
     * @param int $paperId
     * @param array $cc
     * @return void
     */
    private static function fyiReviewersProcess(int $paperId, array &$cc): void
    {

        try {
            $journalSettings = Zend_Registry::get('reviewSettings');
            $isCoiEnabled = isset($journalSettings[self::SETTING_SYSTEM_IS_COI_ENABLED]) && (int)$journalSettings[self::SETTING_SYSTEM_IS_COI_ENABLED] === 1;

            $cUidS = $isCoiEnabled ?
                Episciences_Paper_ConflictsManager::fetchSelectedCol('by', ['answer' => Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes'], 'paper_id' => $paperId]) :
                [];

            foreach ($cc as $uid => $user) {

                if (in_array($uid, $cUidS, false)) {
                    unset($cc[$uid]);
                }
            }


        } catch (Zend_Exception $e) {
            trigger_error($e->getMessage());
        }

    }

}
