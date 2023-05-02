<?php


use cottagelabs\coarNotifications\COARNotificationManager;
use cottagelabs\coarNotifications\orm\COARNotification;


require_once "Script.php";


class InboxNotifications extends Script
{

    private array $coarNotifyOrigin;
    private array $coarNotifyType;

    private $coarNotifyId;


    public const COAR_NOTIFY_AT_CONTEXT = [
        'https://www.w3.org/ns/activitystreams',
        'https://purl.org/coar/notify'
    ];

    public const NOTIFICATION_ID = 'notificationId';

    public const INBOX_SERVICE_TYPE = ['Service'];

    public const HAL_INBOX_URL = [
        'development' => 'https://inbox-development.hal.science/',
        'testing' => 'https://inbox-testing.hal.science/',
        'preprod' => 'https://inbox-preprod.hal.science/',
        'production' => 'https://inbox.hal.science/'
    ];


    public function __construct(string $id = '', array $type = [], array $origin = [])
    {

        $this->setArgs(array_merge($this->getArgs(), ['delNotifs=dpn' => "delete processed inbox notifications"]));
        parent::__construct();

        $this->coarNotifyId = $id;
        $this->coarNotifyType = $type;
        $this->coarNotifyOrigin = $origin;
    }


    public function run()
    {

        $t0 = time();

        // Language choice
        Zend_Registry::set('languages', ['fr', Episciences_Review::DEFAULT_LANG]);
        Zend_Registry::set('Zend_Locale', new Zend_Locale(Episciences_Review::DEFAULT_LANG));


        $this->checkAppEnv();

        define_table_constants();
        define_simple_constants();
        define_app_constants();

        $this->initApp();
        $this->initDb();

        $this->initTranslator(Episciences_Review::DEFAULT_LANG);

        $reader = new Episciences_Notify_Reader();

        $notificationsCollection = $reader->getNotifications();

        if (!$notificationsCollection) {

            $this->displayCritical('Failed to retrieve notifications' . PHP_EOL);
            die();

        }

        $allNotifications = $notificationsCollection->findAll();

        $count = count($allNotifications);


        $this->displaySuccess("Total number of notifications : " . $count . PHP_EOL, $this->isVerbose());


        foreach ($allNotifications as $index => $notification) {

            /** @var COARNotification $notification */
            if (($notification instanceof COARNotification) && $this->notificationsProcess($notification) &&
                $this->getParam('delNotifs')) {

                    $this->removeNotificationById($reader->getCoarNotificationManager(), $notification->getId());

                }

            if ($index < ($count - 1)) {
                $this->displayTrace('Next...' . PHP_EOL, $this->isVerbose());
            }

        }


        $t1 = time();
        $time = $t1 - $t0;

        $this->displayTrace("The script took $time seconds to run.", $this->isVerbose());

    }


    private function notificationsProcess(COARNotification $notification): bool
    {

        $isProcessed = false;

        $nOriginal = $notification->getOriginal();

        //$nOriginal = $this->subPattern('https://hal.science/hal-00919370v2');

        try {

            $notifyPayloads = json_decode($nOriginal, true, 512, JSON_THROW_ON_ERROR);


            $this->displayInfo("Current notification : " . $nOriginal . PHP_EOL, $this->isVerbose());


            if ($this->checkNotifyPayloads($notifyPayloads)) {


                $this->displayInfo('payloads specification check : OK' . PHP_EOL, $this->isVerbose());


                $parse = parse_url($notifyPayloads['target']['id']);

                $rvCode = mb_substr($parse['host'], 0, (mb_strlen($parse['host']) - mb_strlen(DOMAIN)) - 1);

                $journal = Episciences_ReviewsManager::findByRvcode($rvCode, true);


                $jCode = $journal ? $journal->getCode() : 'undefined';
                $this->displayInfo('The destination journal: ' . $jCode . PHP_EOL, $this->isVerbose());


                if ($journal) {
                    $journal->loadSettings();
                    $actor = $notifyPayloads['actor']['id'] ?? null; // uid

                    if (!$actor) {
                        $this->displayWarning('notification ' . $notification->getId() . ' ignored: undefined Actor' . PHP_EOL, true);
                    }

                    $object = filter_var($notifyPayloads['object']['id'], FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED); // preprint URL


                    if (!$object) {
                        $this->displayWarning('notification ' . $notification->getId() . ' ignored: undefined Object' . PHP_EOL, true);
                    } else {

                        $isProcessed = $this->initSubmission($journal, $actor, $object);
                    }


                } else {
                    $this->displayWarning('notification ' . $notification->getId() . ' ignored: undefined journal' . PHP_EOL, true);

                }

            }


        } catch (JsonException $e) {
            $this->displayCritical($e->getMessage() . PHP_EOL);
        }

        return $isProcessed;


    }

    private function checkNotifyPayloads(array $notifyPayloads): bool
    {
        $message = 'Notification id = ';
        $message .= $notifyPayloads['id'];
        $message .= ' not proceeded: ';

        /*
         *  https://notify.coar-repositories.org/patterns/request-review/
         *  The @context must include self::self::COAR_NOTIFY_AT_CONTEXT
         */

        $context = array_intersect(self::COAR_NOTIFY_AT_CONTEXT, $notifyPayloads['@context']);
        $type = array_intersect($this->getCoarNotifyType(), $notifyPayloads['type']);
        $isValidOrigin = $this->getCoarNotifyOrigin()['inbox'] === $notifyPayloads['origin']['inbox'];


        if ($context !== self::COAR_NOTIFY_AT_CONTEXT) {

            $message .= "@context property doesn't match: ";
            $message .= implode(', ', self::COAR_NOTIFY_AT_CONTEXT);
            $this->displayError($message);

            return false;
        }


        if (!$isValidOrigin) {

            $message .= "origine property doesn't match: ";
            $message .= $this->getCoarNotifyOrigin()['inbox'];
            $this->displayError($message);

            return false;
        }


        if ($type !== $this->getCoarNotifyType()) {
            $message .= "type property doesn't match: ";
            $message .= implode(', ', $this->getCoarNotifyType());
            $this->displayError($message);

            return false;
        }

        if (
            !isset($notifyPayloads['target']['id']) ||
            !filter_var($notifyPayloads['target']['id'], FILTER_VALIDATE_URL) ||
            strpos($notifyPayloads['target']['id'], DOMAIN) === false
        ) {

            $message .= 'Not valid notify target => ' . $notifyPayloads['target']['id'];

            $this->displayError($message);

            return false;

        }


        return true;

    }

    private function initSubmission(Episciences_Review $journal, string $actor, string $object): bool
    {

        $apply = false;
        $canBeReplaced = false;

        $data = $this->dataFromUrl($object);
        $data['rvid'] = $journal->getRvid();
        $data['repoid'] = (int)Episciences_Repositories::HAL_REPO_ID;
        $data['uid'] = $actor;

        $isVerbose = $this->isVerbose();


        $this->displayInfo('Submit to the journal : ' . $journal->getCode() . PHP_EOL, $isVerbose);
        $this->displayInfo('Actor : ' . $actor . PHP_EOL, $isVerbose);
        $this->displayInfo('Paper : ' . $data['identifier'] . '(version = ' . $data['version'] . ')' . PHP_EOL, $isVerbose);


        try {

            $result = $this->getRecord($data['repoid'], $data['identifier'], $data['version'], $journal->getRvid());

            if (isset($result['error'])) {

                $this->displayError($result['error'] . PHP_EOL);

            } elseif ($result['status'] === 1) {


                $data['record'] = $result['record'];

                $apply = true;


                $this->displaySuccess('Success' . PHP_EOL, $isVerbose);
                $this->displaySuccess('Reday to submit' . PHP_EOL, $isVerbose);


            } elseif ($result['status'] === 2) {

                $this->displayWarning('Existing article...' . PHP_EOL);

                $newVerErrors = $result['newVerErrors'] ?? null;


                if ($newVerErrors && isset($result['newVerErrors']['message'])) {

                    $this->displayTrace('Can be replaced...' . PHP_EOL);

                    if (isset($newVerErrors['canBeReplaced'])) {

                        $this->displayInfo($result['newVerErrors']['message'], $this->isVerbose());


                        if ($newVerErrors['canBeReplaced']) {

                            $data['record'] = $result['record'];

                            $canBeReplaced = true;

                            $apply = true;
                        }

                    } else {
                        $this->displayError($result['newVerErrors']['message'] . PHP_EOL);

                    }

                }
            }


        } catch (Zend_Exception $e) {

            $this->displayCritical($e->getMessage() . PHP_EOL);

        }

        return $apply && $this->addSubmission($journal, $data, $canBeReplaced);

    }

    private function addLocalUserInNotExist(array $data, int $rvId): ?Episciences_User
    {
        if ($this->isVerbose()) {
            $this->displayInfo('Add local User if not exist' . PHP_EOL, true);
        }


        $user = new Episciences_User();

        try {
            $casUser = $user->findWithCAS($data['uid']);
        } catch (Zend_Db_Statement_Exception $e) {
            $this->displayCritical($e->getMessage());
            return null;
        }

        if (!$casUser) {
            $message = 'Notification id = ';
            $message .= $data[self::NOTIFICATION_ID];
            $message .= ' not processed:';
            $message .= ' CAS UID = ' . $data['uid'] . ' not found';
            $this->displayError($message . PHP_EOL);

            return null;

        }


        if (!$user->hasRoles($data['uid'], $rvId)) {

            if (!$this->isDebug() && $user->save(false, false, $rvId)) {

                if ($this->isVerbose()) {
                    $this->displayInfo('local User added [UID = ' . $data['uid'] . PHP_EOL, true);
                }

            }


        } else {

            if ($this->isVerbose()) {
                $this->displayInfo('Already existing profile' . PHP_EOL, true);
            }
        }

        if (!$this->isDebug()) {
            $user->addRole(Episciences_Acl::ROLE_AUTHOR, $rvId);
        }


        return $user;

    }

    private function logAction(Episciences_Paper $paper, bool $isJustAVersionUpdate = false)
    {

        if ($this->isVerbose()) {
            $this->displayInfo('log action...', true);
        }

        if (!$this->isDebug()) {

            $paper->log(
                Episciences_Paper_Logger::CODE_INBOX_COAR_NOTIFY_REVIEW,
                EPISCIENCES_UID,
                ['origin' => $paper->getRepoid(), 'paper' => $paper->toArray()]
            );

            !$isJustAVersionUpdate ?
                $paper->log(
                    Episciences_Paper_Logger::CODE_STATUS,
                    EPISCIENCES_UID,
                    ['status' => Episciences_Paper::STATUS_SUBMITTED]
                ) :
                $paper->log(
                    Episciences_Paper_Logger::CODE_PAPER_UPDATED,
                    EPISCIENCES_UID,
                    [
                        'user' => (new Episciences_User())->find(EPISCIENCES_UID),
                        'version' => [
                            'old' => Episciences_PapersManager::get($paper->getLatestVersionId(), false)->getVersion(),
                            'new' => $paper->getVersion()
                        ]
                    ]
                );

            if ($this->isVerbose()) {
                $this->displayInfo(
                    !$isJustAVersionUpdate ?
                        'New submission' :
                        'Article updated' . PHP_EOL,
                    true
                );
            }


        }


    }


    public function addSubmission(Episciences_Review $journal, array $data, bool $caBeReplaced = false): bool
    {
        $isAdded = false;

        $paper = new Episciences_Paper($data);
        $paper->setSubmission_date();


        if ($caBeReplaced) {

            $values['search_doc']['docId'] = $paper->getIdentifier();
            $values['search_doc']['version'] = $paper->getVersion();
            $values['search_doc']['repoId'] = $paper->getRepoid();

            try {

                $uResult = $paper->updatePaper($values);

                if (isset($uResult['message'])) {

                    if ($uResult ['code'] === 0) {
                        $this->displaySuccess($uResult['message'], $this->isVerbose());
                    } else {

                        $this->displayWarning($uResult['message'], $this->isVerbose());
                    }

                }

            } catch (Exception $e) {
                $this->$this->displayCritical($e->getMessage());
            }

        }


        if ($paper->alreadyExists()) {
            $message = 'this article (identifier = ';
            $message .= $data['identifier'];
            $message .= ') has already been submitted';

            $this->displayWarning($message . PHP_EOL);

        } elseif ($author = $this->addLocalUserInNotExist($data, $journal->getRvid())) {
            try {

                if (!$this->isDebug()) {

                    if ($paper->save()) {

                        $message = 'The article (identifier = ' . $data['identifier'] . ') has been submitted';

                        $this->notifyAuthorAndEditorialCommitee($journal, $paper, $author);

                        $this->logAction($paper);

                        $this->displaySuccess($message . PHP_EOL, $this->isVerbose());

                        $isAdded = true;

                    } else {

                        $message = 'An error occurred while saving the article (identifier = ' . $data['identifier'] . ')';
                        $this->displayError($message . PHP_EOL);

                    }


                } else {

                    $message = '[Debug mode ON]: The article (identifier = ' . $data['identifier'] . ') has been submitted';

                    $this->displayDebug($message . PHP_EOL, $this->isVerbose());


                }
            } catch (Exception $e) {
                $this->displayCritical($e->getMessage() . PHP_EOL);
            }
        }

        return $isAdded;
    }


    /**
     * @param Episciences_Review $journal
     * @param Episciences_Paper $paper
     * @param Episciences_User $author
     * @return void
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     */
    private function notifyAuthorAndEditorialCommitee(Episciences_Review $journal, Episciences_Paper $paper, Episciences_User $author)
    {

        $rvCode = $journal->getCode();

        $translator = Zend_Registry::get('Zend_Translate');

        $journalPath = realpath(APPLICATION_PATH . '/../data/' . $rvCode);

        $journalPathLanguesDir = $journalPath ? $journalPath . '/languages' : null;

        if ($journalPathLanguesDir && is_dir($journalPathLanguesDir) && count(scandir($journalPathLanguesDir)) > 2) {
            $translator->addTranslation($journalPathLanguesDir);
        }


        $journalOptions = ['rvCode' => $journal->getCode(), 'rvId' => $journal->getRvid()];

        $isVerbose = $this->isVerbose();


        $this->displayInfo('Send notifications' . PHP_EOL, $isVerbose);


        $recipients = [];

        $authorTemplateKy = Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_AUTHOR_COPY;

        $paperUrl = HTTP . '://' . $journal->getCode() . DOMAIN . '/paper/view?id=' . $paper->getDocid();

        $aLocale = $author->getLangueid();

        $commonTags = [
            Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocId(),
            Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
            Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME => $author->getFullName()
        ];

        $authorTags = $commonTags + [
                Episciences_Mail_Tags::TAG_PAPER_URL => $paperUrl,
                Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($aLocale, true),
                Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata($aLocale)
            ];

        if (
            !$this->isDebug() &&
            Episciences_Mail_Send::sendMailFromReview(
                $author,
                $authorTemplateKy,
                $authorTags,
                $paper,
                null,
                [],
                false,
                [],
                $journalOptions
            )
        ) {
            $this->displaySuccess('Author: ' . $author->getScreenName() . ' notified' . PHP_EOL, $isVerbose);
        }

        Episciences_Review::checkReviewNotifications($recipients, !empty($recipients), $journal->getRvid());


        unset($recipients[$paper->getUid()]);

        if (!empty($recipients)) {


            if (!$this->isDebug()) {

                $paperUrl = HTTP . '://' . $journal->getCode() . DOMAIN . '/administratepaper/view?id=' . $paper->getDocid();

                $templateKey = Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_OTHERS_RECIPIENT_COPY;
                $adminTags = $commonTags;

                $adminTags[Episciences_Mail_Tags::TAG_PAPER_URL] = $paperUrl;

                /** @var Episciences_User $recipient */

                foreach ($recipients as $recipient) {

                    $adminTags[Episciences_Mail_Tags::TAG_ARTICLE_TITLE] =
                        $paper->getTitle($recipient->getLangueid(), true);
                    $adminTags[Episciences_Mail_Tags::TAG_AUTHORS_NAMES] =
                        $paper->formatAuthorsMetadata($recipient->getLangueid());

                    Episciences_Mail_Send::sendMailFromReview(
                        $recipient,
                        $templateKey,
                        $adminTags,
                        $paper,
                        null,
                        [],
                        false,
                        [],
                        $journalOptions
                    );

                    $this->displaySuccess($recipient->getScreenName() . ' notified > OK' . PHP_EOL, $isVerbose);
                }

                $this->displaySuccess('All editorial committée notified > OK' . PHP_EOL, $isVerbose);

            }

        }

    }


    public function dataFromUrl(string $url): array
    {

        $data = [
            'version' => 1,
            'identifier' => ''
        ];

        $aParse = parse_url($url);

        if ($aParse && isset($aParse['path'])) {

            $vPos = mb_strpos($aParse['path'], 'v');

            if ($vPos) {

                $version = mb_substr($aParse['path'], ($vPos + 1));

                if ($version) {
                    $data['version'] = (int)$version;

                }

            } else {
                $version = '';
            }

            $rPath = str_replace('/', '', $aParse['path']);

            $data['identifier'] = $vPos ? mb_substr($rPath, 0, mb_strlen($rPath) - (mb_strlen($version) + 1)) : $rPath;


        }

        return $data;
    }


    /**
     * @param int $repoId
     * @param string $identifier
     * @param int|null $version
     * @param int $rvId
     * @return array
     * @throws Zend_Exception
     */
    private function getRecord(int $repoId, string $identifier, int $version = null, int $rvId): array
    {
        if ($this->isVerbose()) {
            $this->displayInfo('get record...' . PHP_EOL, true);
        }

        return Episciences_Submit::getDoc($repoId, $identifier, $version, null, true, $rvId);
    }


    /**
     * @return string
     */
    public function getCoarNotifyId(): string
    {
        return $this->coarNotifyId;
    }

    /**
     * @param string $coarNotifyId
     * @return InboxNotifications
     */
    public function setCoarNotifyId(string $coarNotifyId): self
    {
        $this->coarNotifyId = $coarNotifyId;
        return $this;
    }


    /**
     * @return array
     */
    public function getCoarNotifyOrigin(): array
    {
        return $this->coarNotifyOrigin;
    }


    public function setCoarNotifyOrigin(array $coarNotifyOrigin): self
    {
        $this->coarNotifyOrigin = $coarNotifyOrigin;
        return $this;
    }

    /**
     * @return array
     */
    public function getCoarNotifyType(): array
    {
        return $this->coarNotifyType;
    }


    public function setCoarNotifyType(array $coarNotifyType): self
    {
        $this->coarNotifyType = $coarNotifyType;
        return $this;
    }


    private function defineMissingCosts(Episciences_Review $journal): void
    {
        define_review_constants($journal->getCode());
    }


    private function removeNotificationById(COARNotificationManager $cManger, string $notificationId): void
    {
        $cManger->removeNotificationById($notificationId);
    }

    public function subPattern(string $id): string
    {


        return $nOriginal = '{
            "@context": [
            "https://www.w3.org/ns/activitystreams",
            "https://purl.org/coar/notify"
        ],
  "actor": {
            "id": "1099714",
    "name": "Josiah Carberry",
    "type": "Person"
  },
  "id": "urn:uuid:0370c0fb-bb78-4a9b-87f5-bed307a509dd",
  "object": {
            "id": "' . $id . '",
    "ietf:cite-as": "' . $id . '",
    "type": "sorg:AboutPage",
    "url": {
                "id": "' . $id . '/pdf",
      "media-type": "application/pdf",
      "type": [
                    "Article",
                    "sorg:ScholarlyArticle"
                ]
    }
  },
  "origin": {
            "id": "https://hal.archives-ouvertes.fr/",
    "inbox": "https://inbox-preprod.hal.science/",
    "type": "Service"
  },
  "target": {
            "id": "https://local-djamel-journal-gpl.episciences.org",
    "inbox": "https://www.episciences.org/",
    "type": "Service"
  },
  "type": [
            "Offer",
            "coar-notify:ReviewAction"
        ]
}';
    }
}







