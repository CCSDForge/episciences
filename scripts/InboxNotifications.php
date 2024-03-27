<?php


use cottagelabs\coarNotifications\COARNotificationManager;
use cottagelabs\coarNotifications\orm\COARNotification;


require_once "Script.php";


class InboxNotifications extends Script
{

    public const COAR_NOTIFY_AT_CONTEXT = [
        'https://www.w3.org/ns/activitystreams',
        'https://purl.org/coar/notify'
    ];
    public const NOTIFICATION_ID = 'notificationId';
    public const INBOX_SERVICE_TYPE = ['Service'];
    public const OBJECT_IDENTIFIER_URL = 'ietf:cite-as';
    public const FIRST_SUBMISSION = 'firstSubmission';
    public const NEW_VERSION = 'newVersion';
    public const VERSION_UPDATE = 'versionUpdate';
    public const PAPER_CONTEXT = 'previousPaperObject';
    private array $coarNotifyOrigin;
    private array $coarNotifyType;
    private $coarNotifyId;

    public function __construct(string $id = '', array $type = [], array $origin = [])
    {

        define('SERVER_PROTOCOL', 'https');
        $this->setRequiredParams([]);
        $this->setArgs(array_merge($this->getArgs(), ['delNotifs|dpn' => "delete processed inbox notifications"]));
        parent::__construct();

        $this->coarNotifyId = $id;
        $this->coarNotifyType = $type;
        $this->coarNotifyOrigin = $origin;
    }


    public function run(): void
    {

        $t0 = time();

        // Language choice
        Zend_Registry::set('languages', ['fr', Episciences_Review::DEFAULT_LANG]);
        Zend_Registry::set('Zend_Locale', new Zend_Locale(Episciences_Review::DEFAULT_LANG));

        defineSQLTableConstants();
        defineSimpleConstants();
        defineApplicationConstants();

        $this->initApp();
        $this->initDb();

        try {
            $this->initTranslator(Episciences_Review::DEFAULT_LANG);

        } catch (Exception $e) {
            $this->displayCritical('Failed to initialize Translator' . PHP_EOL);
            die();
        }

        $reader = new Episciences_Notify_Reader();

        $notificationsCollection = $reader->getNotifications();


        $count = count($notificationsCollection);

        if ($count < 1) {
            $this->displayInfo("No notifications to process" . PHP_EOL, $this->isVerbose());
            return;
        }

        $this->displaySuccess("Total number of notifications : " . $count . PHP_EOL, $this->isVerbose());

        foreach ($notificationsCollection as $index => $notification) {

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


    /**
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     */
    public function notificationsProcess(COARNotification $notification): bool
    {

        $isProcessed = false;

        $nOriginal = $notification->getOriginal();

        try {

            $notifyPayloads = json_decode($nOriginal, true, 512, JSON_THROW_ON_ERROR);


            $this->displayInfo("Current notification : " . $nOriginal . PHP_EOL, $this->isVerbose());

            if ($this->checkNotifyPayloads($notifyPayloads)) {


                $this->displayInfo('payloads specification check : OK' . PHP_EOL, $this->isVerbose());

                $rvCode = $this->getRvCodeFromUrl($notifyPayloads['target']['id']);

                $journal = ($rvCode !== '') ? Episciences_ReviewsManager::findByRvcode($rvCode, true) : null;
                $jCode = $journal ? $journal->getCode() : 'undefined';

                $this->displayInfo('The destination journal: ' . $jCode . ' [$_currentReviewId = ' . Episciences_Review::$_currentReviewId . ']' . PHP_EOL, $this->isVerbose());


                if ($journal) {

                    Zend_Registry::set('reviewSettings', $journal->getSettings());

                    $actor = $notifyPayloads['actor']['id'] ?? null; // uid

                    if (!$actor) {
                        $this->displayWarning('notification ' . $notification->getId() . ' ignored: undefined Actor' . PHP_EOL, true);
                    }

                    $object = filter_var($notifyPayloads['object'][self::OBJECT_IDENTIFIER_URL], FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED); // preprint URL


                    if (!$object) {
                        $this->displayWarning('notification ' . $notification->getId() . ' ignored: undefined Object' . PHP_EOL, true);
                    } else {

                        try {
                            $isProcessed = $this->initSubmission($journal, $actor, $object, $notifyPayloads);
                        } catch (Zend_Db_Statement_Exception $e) {
                            $this->displayCritical($e->getMessage() . PHP_EOL);
                        }
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

    public function checkNotifyPayloads(array $notifyPayloads): bool
    {

        $result = true;

        $message = 'Notification id = ';
        $message .= $notifyPayloads['id'];
        $message .= ' not proceeded: ';

        /*
         *  https://notify.coar-repositories.org/patterns/request-review/
         *  The @context must include self::self::COAR_NOTIFY_AT_CONTEXT
         */

        $context = array_intersect(self::COAR_NOTIFY_AT_CONTEXT, $notifyPayloads['@context']);
        $type = array_intersect($this->getCoarNotifyType(), $notifyPayloads['type'] ?? []);
        $isValidOrigin = $this->getCoarNotifyOrigin()['inbox'] === $notifyPayloads['origin']['inbox'];


        if ($context !== self::COAR_NOTIFY_AT_CONTEXT) {

            /* commented because it fails to compare the url with different protocols https vs http
            TODO Fix test
            $message .= "the '@context' property doesn't match: ";
            $message .= implode(', ', self::COAR_NOTIFY_AT_CONTEXT);
            $this->displayWarning($message, $this->isVerbose());
            // Test always fails but context is valid ???
            */

        } elseif (!$isValidOrigin) {

            $message .= "the 'origin' property doesn't match: ";
            $message .= $this->getCoarNotifyOrigin()['inbox'];
            $this->displayWarning($message, $this->isVerbose());


        } elseif ($type !== $this->getCoarNotifyType()) {
            $message .= "the 'type' property doesn't match: ";
            $message .= implode(', ', $this->getCoarNotifyType());
            $this->displayWarning($message, $this->isVerbose());

        } elseif (
            !isset($notifyPayloads['target']['id']) ||
            !filter_var($notifyPayloads['target']['id'], FILTER_VALIDATE_URL) ||
            strpos($notifyPayloads['target']['id'], DOMAIN) === false
        ) {

            $message .= 'Not valid notify target => ' . $notifyPayloads['target']['id'];

            $this->displayError($message, $this->isVerbose());
            $result = false;

        }

        return $result;

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

    public function getRvCodeFromUrl(string $url = null): string
    {

        if (!$url) {
            $this->displayWarning('EMPTY TARGET IDENTIFIER' . PHP_EOL);
            return '';
        }

        $parse = parse_url($url);

        $rvCode = isset($parse['host']) ?
            mb_substr($parse['host'], 0, (mb_strlen($parse['host']) - mb_strlen(DOMAIN)) - 1) :
            '';

        $this->displayDebug('CURRENT RVCODE: ' . $rvCode . PHP_EOL);
        return $rvCode;
    }

    /**
     * @param Episciences_Review $journal
     * @param string $actor
     * @param string $object
     * @param array $notifyPayloads
     * @return bool
     * @throws JsonException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    private function initSubmission(Episciences_Review $journal, string $actor, string $object, array $notifyPayloads = []): bool
    {

        $apply = false;
        $newVerErrors = null;
        $data = $this->dataFromUrl($object);
        $data['rvid'] = $journal->getRvid();
        $data['notifyPayloads'] = $notifyPayloads;

        $repoId = (int)Episciences_Repositories::HAL_REPO_ID;

        if (defined('NOTIFY_TARGET_HAL_LINKED_REPOSITORY')) {

            $repoId = $this->getRepoId();

            if (null === $repoId) {
                $this->displayError('Undefined repository ID');
                return false;

            }
        }

        $data['repoid'] = $repoId;
        $data['uid'] = $this->getUidFromMailString($actor);

        $isVerbose = $this->isVerbose();

        $this->displayInfo('Submit to the journal : ' . $journal->getCode() . PHP_EOL, $isVerbose);
        $this->displayInfo('Actor : ' . $actor . PHP_EOL, $isVerbose);
        $this->displayInfo('Paper : ' . $data['identifier'] . '(version = ' . $data['version'] . ')' . PHP_EOL, $isVerbose);


        try {

            $result = $this->getRecord($data['repoid'], $data['identifier'], $data['version'], $journal->getRvid(), true);

            if (isset($result['record'])) {
                $data['record'] = $result['record'];
            }

            if (isset($result[Episciences_Repositories_Common::ENRICHMENT])) {
                $data[Episciences_Repositories_Common::ENRICHMENT] = $result[Episciences_Repositories_Common::ENRICHMENT];
            }


            if (isset($result['error'])) {

                $this->displayError($result['error'] . PHP_EOL);

            } elseif ($result['status'] === 1) {

                $apply = true;

                $this->displaySuccess('Success' . PHP_EOL, $isVerbose);
                $this->displaySuccess('Ready to submit' . PHP_EOL, $isVerbose);


            } elseif ($result['status'] === 2) {

                $this->displayWarning('Existing article...' . PHP_EOL);

                $newVerErrors = $result['newVerErrors'];

                if ($newVerErrors && isset($result['newVerErrors']['message'])) {

                    $this->displayTrace('Can be replaced...' . PHP_EOL);

                    if (isset($newVerErrors['canBeReplaced'])) {

                        $this->displayInfo($result['newVerErrors']['message'], $this->isVerbose());

                        if (
                            $newVerErrors['canBeReplaced'] || // replace existing version before the reviewing process begins
                            isset($newVerErrors[self::PAPER_CONTEXT]) // new version following a request for the final version or modification
                        ) {
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

        return $apply && $this->addSubmission($journal, $data, $newVerErrors);

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
     * @param int|null $rvId
     * @param bool $isEpiNotify : from Episciences - COAR Notification Manager inbox
     * @return array
     * @throws Zend_Exception
     */
    private function getRecord(int $repoId, string $identifier, int $version = null, int $rvId = null, bool $isEpiNotify = true): array
    {
        if ($this->isVerbose()) {
            $this->displayInfo('get record...' . PHP_EOL, true);
        }

        return Episciences_Submit::getDoc($repoId, $identifier, $version, null, true, $rvId, $isEpiNotify);
    }

    /**
     * @param Episciences_Review $journal
     * @param array $data
     * @param array|null $options
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws JsonException
     */
    public function addSubmission(Episciences_Review $journal, array $data, array $options = null): bool
    {
        $isDebug = $this->isDebug();
        $isAdded = false;
        $canBeReplaced = $options['canBeReplaced'] ?? false;
        $isFirstSubmission = !isset($options[self::PAPER_CONTEXT]);

        $logDetails = isset($data['notifyPayloads']) ? ['notifyPayloads' => $data['notifyPayloads']] : [];

        $paper = new Episciences_Paper($data);
        $paper->setSubmission_date();
        $paper->setWhen();

        if ($paper->alreadyExists()) {

            $message = 'This article (identifier = ';
            $message .= $data['identifier'];
            $message .= ') has already been submitted';

            $this->displayWarning($message . PHP_EOL);


        } elseif (null === $this->addLocalUserInNotExist($data)) {
            return false;
        }

        /** @var Episciences_Paper $context */
        $context = !$isFirstSubmission ? $options[self::PAPER_CONTEXT] : null; // previous paper

        if (!$isFirstSubmission) {


            if ($canBeReplaced) {


                $logDetails = array_merge($logDetails, ['oldVersion' => $context->getVersion(), 'oldStatus' => $context->getStatus()]);

                $values['search_doc']['docId'] = $paper->getIdentifier();
                $values['search_doc']['version'] = $paper->getVersion();
                $values['search_doc']['repoId'] = $paper->getRepoid();
                $values['rvid'] = $journal->getRvid();
                $values['isEpiNotify'] = true;

                try {

                    $uResult = $context->updatePaper($values);

                    if (isset($uResult['message'])) {

                        if ($uResult ['code'] === 0) {
                            $this->displaySuccess($uResult['message'], $this->isVerbose());
                        } else {
                            $this->displayWarning($uResult['message'], $this->isVerbose());

                            if (!$isDebug) {

                                if ($context->getStatus() === Episciences_Paper::STATUS_REFUSED) {

                                    $paper->setPaperid($context->getPaperid());

                                    $isAdded = $this->getFirstSubmissionResult($paper,
                                        $journal,
                                        $data,
                                        [
                                            'canBeReplaced' => $canBeReplaced,
                                            'logDetails' => ['oldStatus' => Episciences_Paper::STATUS_REFUSED, 'oldDocId' => $context->getDocid()]
                                        ]
                                    );

                                    if (!$isAdded) {
                                        $message = 'An error occurred while saving the article (identifier = ' . $data['identifier'] . ')';
                                        $this->displayError($message . PHP_EOL);

                                    }


                                } else {
                                    $context->setVersion($paper->getVersion());
                                    $context->setRecord($paper->getRecord());
                                    $context->setWhen($paper->getWhen());
                                    $context->save();
                                    $this->logAction($context, $logDetails, self::VERSION_UPDATE);
                                    // delete all paper datasets
                                    Episciences_Paper_DatasetsManager::deleteByDocIdAndRepoId($context->getDocid(), $context->getRepoid());
                                    $this->enrichment($context, $data);
                                    $this->notifyAuthorAndEditorialCommittee($journal, $context, ['canBeReplaced' => $canBeReplaced, 'oldStatus' => $logDetails['oldStatus']]);
                                    $isAdded = true;

                                }


                            }

                        }

                    }


                } catch (Exception $e) {
                    $this->$this->displayCritical($e->getMessage());
                }

            } elseif (!$isDebug) {

                try {
                    $isAdded = $this->saveNewVersion($context, $data, $journal, $logDetails);
                } catch (Exception $e) {
                    $this->displayCritical($e->getMessage());
                }

                $this->enrichment($paper, $data);
            }

            $message = 'The article (identifier = ' . $paper->getIdentifier() . ') has been submitted';
            $this->displaySuccess($message . PHP_EOL, $this->isVerbose());


        } else {
            try {
                if (!$isDebug) {

                    $isAdded = $this->getFirstSubmissionResult($paper, $journal, $data, $logDetails);

                    if (!$isAdded) {

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
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws JsonException
     */
    private function addLocalUserInNotExist(array $data): ?Episciences_User
    {
        if ($this->isVerbose()) {
            $this->displayInfo('Add local User if not exist' . PHP_EOL, true);
        }

        $rvId = $data['rvid'];
        $uid = $data['uid'] ?? 0;
        $user = new Episciences_User();

        try {
            $casUser = $user->findWithCAS($data['uid']);
        } catch (Zend_Db_Statement_Exception $e) {
            $this->displayCritical($e->getMessage());
            return null;
        }

        if (!$casUser) {
            $message = 'Notification id = ';
            $message .= $data[self::NOTIFICATION_ID] ?? 'undefined';
            $message .= ' not processed:';
            $message .= ' CAS UID = ' . $uid . ' not found. Original string was: ' . $data['uid'];
            $this->displayError($message . PHP_EOL);

            return null;

        }


        if (!$user->hasLocalData()) {

            if (
                !$this->isDebug() &&
                $user->save(false, false, $rvId) && $this->isVerbose()
            ) {
                $this->displayInfo('local User added [UID = ' . $uid . PHP_EOL, true);
            }


        } elseif ($this->isVerbose()) {
            $this->displayInfo('Already existing profile' . PHP_EOL, true);
        }

        if (!$this->isDebug()) {
            $user->addRole(Episciences_Acl::ROLE_AUTHOR, $rvId);
        }


        return $user;

    }

    private function getUidFromMailString(string $uid): int
    {
        $uid = ltrim($uid, 'mailto:');
        $uid = rtrim($uid, '@ccsd.cnrs.fr');
        return (int)$uid;
    }

    /**
     * @param Episciences_Review $journal
     * @param Episciences_Paper $paper
     * @param array $options
     * @return void
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     */
    private function notifyAuthorAndEditorialCommittee(Episciences_Review $journal, Episciences_Paper $paper, array $options = []): void
    {
        $originalRequest = $options['originalRequest'] ?? null;
        $canBeReplaced = $options['canBeReplaced'] ?? false;
        $isPreviousPaperRefused = $canBeReplaced && isset($options['oldStatus']) && $options['oldStatus'] === Episciences_Paper::STATUS_REFUSED;
        $author = $paper->getSubmitter();
        $authorTemplateKy = Episciences_Mail_TemplatesManager::TYPE_INBOX_PAPER_SUBMISSION_AUTHOR_COPY;

        $managersTemplateKey = $canBeReplaced && !$isPreviousPaperRefused ?
            Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_UPDATED_EDITOR_COPY :
            Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_OTHERS_RECIPIENT_COPY; // first submission

        $isFirstSubmission = (
            $originalRequest === null || (
                $originalRequest instanceof Episciences_Comment &&
                $originalRequest->getType() !== Episciences_CommentsManager::TYPE_REVISION_REQUEST
            ));

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

        $paperUrl = sprintf(SERVER_PROTOCOL . "://%s.%s/paper/view?id=%s", $journal->getCode(), DOMAIN, $paper->getDocid());

        $aLocale = $author->getLangueid(true);

        $commonTags = [
            Episciences_Mail_Tags::TAG_REVIEW_CODE => $journal->getCode(),
            Episciences_Mail_Tags::TAG_REVIEW_NAME => $journal->getName(),
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
                $paper->getCoAuthors(),
                $journalOptions
            )
        ) {
            $this->displaySuccess('Author: ' . $author->getScreenName() . ' notified' . PHP_EOL, $isVerbose);
        }

        $recipients = [];
        $cc = [];
        $refMessage = '';

        if (!$isFirstSubmission) {
            $recipients = $paper->getEditors(true, true) + $paper->getCopyEditors(true, true);
        }

        Episciences_Review::checkReviewNotifications($recipients, !empty($recipients), $journal->getRvid());

        Episciences_PapersManager::keepOnlyUsersWithoutConflict($paper->getPaperid(), $recipients);

        unset($recipients[$paper->getUid()]);

        // new version
        if (
            !$isFirstSubmission && (
                $paper->isEditor($originalRequest->getUid()) ||
                $paper->getCopyEditor($originalRequest->getUid())
            )) {

            $principalRecipient = new Episciences_User();
            $principalRecipient->find($originalRequest->getUid());

            $cc = $paper->extractCCRecipients($recipients, $principalRecipient->getUid());
            $recipients = array($principalRecipient);

        }

        if (!$this->isDebug()) {

            $paperUrl = sprintf(SERVER_PROTOCOL . "://%s.%s/administratepaper/view?id=%s", $journal->getCode(), DOMAIN, $paper->getDocid());

            if (!$isFirstSubmission) {// new version

                $commentType = $originalRequest->getType();

                if ($commentType === Episciences_CommentsManager::TYPE_REVISION_ANSWER_NEW_VERSION) {
                    $managersTemplateKey = Episciences_Mail_TemplatesManager::TYPE_PAPER_NEW_VERSION_SUBMITTED;
                } elseif ($commentType === Episciences_CommentsManager::TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED) {
                    $managersTemplateKey = Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_EDITOR_AND_COPYEDITOR_COPY;
                }

            }

            $adminTags = $commonTags;

            $adminTags[Episciences_Mail_Tags::TAG_PAPER_URL] = $paperUrl;

            if ($isPreviousPaperRefused && $options['oldDocId']) {
                $refMessage = 'Cet article a été précédemment refusé dans sa première version, pour le consulter, merci de suivre ce lien : ';

                $adminTags[Episciences_Mail_Tags::TAG_REFUSED_PAPER_URL] = sprintf(SERVER_PROTOCOL . "://%s.%s/administratepaper/view?id=%s", $journal->getCode(), DOMAIN, $options['oldDocId']);

            }

            foreach ($recipients as $recipient) {

                $rLocale = $recipient->getLangueid(true);

                $adminTags[Episciences_Mail_Tags::TAG_ARTICLE_TITLE] =
                    $paper->getTitle($rLocale, true);
                $adminTags[Episciences_Mail_Tags::TAG_AUTHORS_NAMES] =
                    $paper->formatAuthorsMetadata($rLocale);

                if ($refMessage !== '') {

                    $refMessage = $translator->translate($refMessage, $rLocale, true);
                    $adminTags[Episciences_Mail_Tags::TAG_REFUSED_ARTICLE_MESSAGE] = $refMessage;

                }


                Episciences_Mail_Send::sendMailFromReview(
                    $recipient,
                    $managersTemplateKey,
                    $adminTags,
                    $paper,
                    null,
                    [],
                    false,
                    $cc,
                    $journalOptions
                );

                $this->displaySuccess($recipient->getScreenName() . ' notified > OK' . PHP_EOL, $isVerbose);


            }

            $this->displaySuccess('All editorial committee notified > OK' . PHP_EOL, $isVerbose);

        }

    }

    /**
     * @param Episciences_Paper $paper
     * @param array $details
     * @param string $submissionType
     * @return void
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     */
    private function logAction(Episciences_Paper $paper, array $details = [], string $submissionType = self::FIRST_SUBMISSION): void
    {

        $details = array_merge(['origin' => $paper->getRepoid()], $details);

        if ($this->isVerbose()) {
            $this->displayInfo('log action...', true);
        }

        if (!$this->isDebug()) {

            $paper->log(
                Episciences_Paper_Logger::CODE_INBOX_COAR_NOTIFY_REVIEW,
                EPISCIENCES_UID,
                $details
            );

            if ($submissionType === self::VERSION_UPDATE) {
                $paper->log(
                    Episciences_Paper_Logger::CODE_PAPER_UPDATED,
                    EPISCIENCES_UID,
                    [
                        'user' => (new Episciences_User())->find(EPISCIENCES_UID),
                        'version' => [
                            'old' => $details['oldVersion'] ?? 1,
                            'new' => $paper->getVersion()
                        ]
                    ]
                );


            } else {
                $paper->log(
                    Episciences_Paper_Logger::CODE_STATUS,
                    EPISCIENCES_UID,
                    ['status' => Episciences_Paper::STATUS_SUBMITTED]
                );
            }

            if ($this->isVerbose()) {
                if ($submissionType === self::VERSION_UPDATE) {
                    $this->displayInfo(
                        'Article version updated' . PHP_EOL,
                        true
                    );
                } elseif ($submissionType === self::NEW_VERSION) {

                    $this->displayInfo(
                        'New version submitted',
                        true
                    );

                } else {
                    $this->displayInfo(
                        'New submission submitted',
                        true
                    );
                }
            }


        }


    }

    private function removeNotificationById(COARNotificationManager $cManger, string $notificationId): void
    {
        $cManger->removeNotificationById($notificationId);
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
     * @return int|null
     */
    private function getRepoId(): ?int
    {

        $allRepositories = Episciences_Repositories::getRepositories();

        foreach ($allRepositories as $repository) {

            foreach ($repository as $rKey => $value) {

                if ($rKey !== Episciences_Repositories::REPO_LABEL) {
                    continue;
                }

                if ($value === NOTIFY_TARGET_HAL_LINKED_REPOSITORY) {
                    return (int)$repository['id'];
                }
            }
        }

        return null;
    }


    /**
     * @param Episciences_Paper $context
     * @param array $newPaperData
     * @param Episciences_Review $journal
     * @param array $logDetails
     * @return bool
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     */
    private function saveNewVersion(Episciences_Paper $context, array $newPaperData, Episciences_Review $journal, array $logDetails = []): bool
    {

        $context->loadOtherVolumes();
        $journal->loadSettings();

        $isCopyEditingProcessStarted = $context->isCopyEditingProcessStarted();

        $comments = Episciences_CommentsManager::getRevisionRequests($context->getDocid(), [Episciences_CommentsManager::TYPE_REVISION_REQUEST]);

        $comment = new Episciences_Comment($comments[array_key_first($comments)]);


        $reassignReviewers = $comment->getOption('reassign_reviewers');
        $isAlreadyAccepted = $comment->getOption('isAlreadyAccepted');

        $paperId = ($context->getPaperid()) ?: $context->getDocid();
        $reviewers = $context->getReviewers(null, true);
        $editors = $context->getEditors(true, true);
        $copyEditors = $context->getCopyEditors(true, true);
        $coAuthors = $context->getCoAuthors();

        $newPaper = clone($context);
        $newPaper->setDocid(null);
        $newPaper->setPaperid($paperId);

        $newPaper->setWhen();

        $newPaper->setVersion($newPaperData['version']);
        $newPaper->setRecord($newPaperData['record']);
        $newPaper->setUid($newPaperData['uid']);
        $newPaper->setRepoid($newPaperData['repoid']);
        $newPaper->setIdentifier($newPaperData['identifier']);

        $isAssignedReviewers = $reassignReviewers && $reviewers;

        if ($isCopyEditingProcessStarted) {
            $status = ($newPaper->getStatus() === Episciences_Paper::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION) ?
                Episciences_Paper::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION :
                Episciences_Paper::STATUS_CE_READY_TO_PUBLISH;
        } elseif ($isAlreadyAccepted && !$isAssignedReviewers) {

            $status = ((int)$journal->getSetting(Episciences_Review::SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION) === 1) ?
                Episciences_Paper::STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING :
                Episciences_Paper::STATUS_ACCEPTED;
        } else {
            $status = $isAssignedReviewers ? $newPaper::STATUS_OK_FOR_REVIEWING : $newPaper::STATUS_SUBMITTED;
        }

        $newPaper->setStatus($status);

        $newPaper->save();

        $newPaperStatusDetails = ['status' => $status];

        if ($isAlreadyAccepted) {
            $newPaperStatusDetails['isAlreadyAccepted'] = $isAlreadyAccepted;
        }

        $this->logAction($newPaper, array_merge($logDetails, $newPaperStatusDetails), self::NEW_VERSION);

        if ($context->getVid()) {
            $newPaper->setVid($context->getVid());
        }

        if ($context->getOtherVolumes()) { // github #48
            $newPaper->setOtherVolumes($context->getOtherVolumes());
            $newPaper->saveOtherVolumes();
        }


        $context->setStatus(Episciences_Paper::STATUS_OBSOLETE);
        $context->setVid();
        $context->setOtherVolumes();
        $context->setPassword();
        $context->save();

        $context->log(Episciences_Paper_Logger::CODE_STATUS, null, ['status' => $context->getStatus()]);


        if (!$isCopyEditingProcessStarted && $reviewers) {
            foreach ($reviewers as $reviewer) {
                if (!$reviewer->getInvitation($context->getDocid(), $journal->getRvid())) {
                    continue;
                }
                $aid = $context->unassign($reviewer->getUid(), Episciences_User_Assignment::ROLE_REVIEWER);

                $context->log(Episciences_Paper_Logger::CODE_REVIEWER_UNASSIGNMENT, null, ['aid' => $aid, 'user' => $reviewer->toArray()]);
            }
        }

        if (!empty($editors)) {
            foreach ($editors as $editor) {
                $aid = $context->unassign($editor->getUid(), Episciences_User_Assignment::ROLE_EDITOR);
                $context->log(Episciences_Paper_Logger::CODE_EDITOR_UNASSIGNMENT, null, ["aid" => $aid, "user" => $editor->toArray()]);
            }
        }


        if (!empty($copyEditors)) {
            foreach ($copyEditors as $copyEditor) {
                $aid = $context->unassign($copyEditor->getUid(), Episciences_User_Assignment::ROLE_COPY_EDITOR);
                $context->log(Episciences_Paper_Logger::CODE_COPY_EDITOR_UNASSIGNMENT, null, ["aid" => $aid, "user" => $copyEditor->toArray()]);
            }
        }

        if ($reviewers && $reassignReviewers) {
            $sender = new Episciences_Editor();
            $sender->findWithCAS($comment->getUid());
            $this->reinviteReviewers($reviewers, $context, $newPaper, $sender, $journal);
        }

        if ($editors) {
            $this->reassignPaperManagers($editors, $newPaper);
        }

        if ($copyEditors) {
            $this->reassignPaperManagers($copyEditors, $newPaper, Episciences_User_Assignment::ROLE_COPY_EDITOR);
        }


        if (!empty($coAuthors)) {
            Episciences_User_AssignmentsManager::reassignPaperCoAuthors($coAuthors, $newPaper);
        }

        $this->notifyAuthorAndEditorialCommittee($journal, $newPaper, ['originalRequest' => $comment]);
        return true;

    }

    /**
     * @param array $reviewers
     * @param Episciences_Paper $context
     * @param Episciences_Paper $paper
     * @param Episciences_User|null $sender
     * @param Episciences_Review $journal
     * @return void
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     */

    private function reinviteReviewers(array $reviewers, Episciences_Paper $context, Episciences_Paper $paper, Episciences_User $sender = null, Episciences_Review $journal): void
    {
        $templateKey = Episciences_Mail_TemplatesManager::TYPE_PAPER_NEW_VERSION_REVIEWER_REINVITATION;

        // link to previous version page
        $context_url = sprintf(SERVER_PROTOCOL . "://%s.%s/paper/view?id=%s", $journal->getCode(), DOMAIN, $paper->getDocid());

        // new deadline is today + default deadline interval (journal setting)
        $deadline = Episciences_Tools::addDateInterval(date('Y-m-d'), $journal->getSetting(Episciences_Review::SETTING_RATING_DEADLINE));

        $params = [
            'deadline' => $deadline,
            'status' => Episciences_User_Assignment::STATUS_PENDING,
            'rvid' => $journal->getRvid()
        ];

        // loop through each reviewer
        /** @var Episciences_Reviewer $reviewer */
        foreach ($reviewers as $reviewer) {
            /** @var Episciences_User_Assignment $oAssignment */
            $oAssignment = $reviewer->assign($paper->getDocid(), $params)[0];

            $oInvitation = new Episciences_User_Invitation(['aid' => $oAssignment->getId(), 'sender_uid' => EPISCIENCES_UID]);

            if ($oInvitation->save()) {
                $oInvitation = Episciences_User_InvitationsManager::findById($oInvitation->getId());
            }


            // link to rating invitation page
            $invitation_url = sprintf(SERVER_PROTOCOL . "://%s.%s/reviewer/invitation?id=%s", $journal->getCode(), DOMAIN, $oInvitation->getId());


            // update assignment with invitation_id
            $oAssignment->setInvitation_id($oInvitation->getId());
            $oAssignment->save();

            $locale = $reviewer->getLangueid();

            $tags = [
                Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocid(),
                Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
                Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $context->getTitle($locale, true),
                Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $context->formatAuthorsMetadata($locale),
                Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE => Episciences_View_Helper_Date::Date($context->getWhen(), $locale),
                Episciences_Mail_Tags::TAG_PAPER_URL => $context_url,
                Episciences_Mail_Tags::TAG_INVITATION_URL => $invitation_url,
                Episciences_Mail_Tags::TAG_INVITATION_DEADLINE => Episciences_View_Helper_Date::Date($oInvitation->getExpiration_date(), $locale),
                Episciences_Mail_Tags::TAG_RATING_DEADLINE => Episciences_View_Helper_Date::Date($oAssignment->getDeadline(), $locale)

            ];

            $journalOptions = ['rvCode' => $journal->getCode(), 'rvId' => $journal->getRvid()];

            if ($sender) {
                $journalOptions['sender'] = $sender;
            }

            Episciences_Mail_Send::sendMailFromReview($reviewer, $templateKey, $tags, $paper, null, [], false, [], $journalOptions);

        }

    }


    private function reassignPaperManagers(array $paperManagers, Episciences_Paper $paper, string $roleId = Episciences_User_Assignment::ROLE_EDITOR): array
    {

        $action = Episciences_Paper_Logger::CODE_EDITOR_ASSIGNMENT;

        if ($roleId === Episciences_User_Assignment::ROLE_COPY_EDITOR) {
            $action = Episciences_Paper_Logger::CODE_COPY_EDITOR_ASSIGNMENT;
        }

        foreach ($paperManagers as $manager) {

            $aid = $paper->assign($manager->getUid(), $roleId, Episciences_User_Assignment::STATUS_ACTIVE);
            $paper->log($action, null, ["aid" => $aid, "user" => $manager->toArray()]);

        }

        return $paperManagers;
    }

    /**
     * @param Episciences_Paper $paper
     * @param array $additionalPaperData
     * @return void
     */


    private function enrichment(Episciences_Paper $paper, array $additionalPaperData = []): void
    {

        $enrichment = $additionalPaperData[Episciences_Repositories_Common::ENRICHMENT] ?? [];

        if (Episciences_Repositories::getApiUrl($paper->getRepoid())) {
            Episciences_Submit::datasetsProcessing($paper);
        }

        if (!isset($enrichment[Episciences_Repositories_Common::CONTRIB_ENRICHMENT])) {
            // insert author dc:creator to json author in the database
            Episciences_Paper_AuthorsManager::InsertAuthorsFromPapers($paper);
        }

        Episciences_Submit::enrichmentProcess($paper, $enrichment);

        try {

            if (Episciences_Repositories::isFromHalRepository($paper->getRepoid())) { // try to enrich with TEI HAL
                Episciences_Paper_AuthorsManager::enrichAffiOrcidFromTeiHalInDB($paper->getRepoid(), $paper->getPaperid(), $paper->getIdentifier(), (int)$paper->getVersion());
            }

        } catch (JsonException|\Psr\Cache\InvalidArgumentException $e) {
            $this->displayCritical($e->getMessage());
        }

    }

    /**
     * @param Episciences_Paper $paper
     * @param Episciences_Review $journal
     * @param array $data
     * @param array $options
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     */
    private function getFirstSubmissionResult(
        Episciences_Paper  $paper,
        Episciences_Review $journal,
        array              $data,
        array              $options = []
    ): bool
    {
        $message = 'The article (identifier = ' . $data['identifier'] . ') has been submitted';

        $logDetails = $options['logDetails'] ?? [];

        $nOptions = [];

        if (isset($options['canBeReplaced'])) {
            $nOptions['canBeReplaced'] = $options['canBeReplaced'];
        }

        if (isset($options['logDetails']['oldStatus'])) {
            $nOptions['oldStatus'] = $options['logDetails']['oldStatus'];
        }

        if (isset($options['logDetails']['oldDocId'])) {
            $nOptions['oldDocId'] = $options['logDetails']['oldDocId'];
        }

        if ($paper->save()) {
            $this->enrichment($paper, $data);
            $this->logAction($paper, $logDetails);
            $this->notifyAuthorAndEditorialCommittee($journal, $paper, $nOptions);
            $this->displaySuccess($message . PHP_EOL, $this->isVerbose());
            return true;

        }

        return false;
    }
}







