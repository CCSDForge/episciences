<?php

declare(strict_types=1);

use Episciences\Notify\Notification;
use Episciences\Notify\NotifySourceConfig;
use Episciences\Notify\NotifySourceRegistry;
use Episciences\Notify\PayloadValidator;
use Episciences\Notify\PreprintUrlParser;
use scripts\AbstractScript;

require_once "AbstractScript.php";

class InboxNotifications extends AbstractScript
{
    public const COAR_NOTIFY_AT_CONTEXT = [
        'https://www.w3.org/ns/activitystreams',
        'https://purl.org/coar/notify',
    ];
    public const NOTIFICATION_ID       = 'notificationId';
    public const INBOX_SERVICE_TYPE    = ['Service'];
    public const OBJECT_IDENTIFIER_URL = 'ietf:cite-as';
    public const FIRST_SUBMISSION      = 'firstSubmission';
    public const NEW_VERSION           = 'newVersion';
    public const VERSION_UPDATE        = 'versionUpdate';
    public const PAPER_CONTEXT         = 'previousPaperObject';

    private PreprintUrlParser $urlParser;

    public function __construct()
    {
        if (!defined('SERVER_PROTOCOL')) {
            define('SERVER_PROTOCOL', 'https');
        }

        $this->setRequiredParams([]);
        $this->setArgs(array_merge($this->getArgs(), ['delNotifs|dpn' => "delete processed inbox notifications"]));

        parent::__construct();

        $this->initLogging();
        $this->urlParser = new PreprintUrlParser();
    }

    public function run(): void
    {
        $t0 = time();

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
            $this->logger->critical('Failed to initialize Translator: ', ['context' => $e->getMessage()]);
            die();
        }

        $reader                  = new Episciences_Notify_Reader();
        $notificationsCollection = $reader->getNotifications();
        $count                   = count($notificationsCollection);

        if ($count >= \Episciences\Notify\NotificationsRepository::MAX_INBOUND_FETCH) {
            $this->logger->warning(sprintf(
                'Inbox fetch hit the %d-row limit — some notifications may have been skipped.',
                \Episciences\Notify\NotificationsRepository::MAX_INBOUND_FETCH
            ));
        }

        $registry = NotifySourceRegistry::createFromConstants();

        if ($this->isVerbose()) {
            foreach ($this->getParams() as $name => $value) {
                $this->logger->info($name . ' param has been set to: ' . $value);
            }

            if ($count < 1) {
                $this->logger->info('No notifications to process');
                return;
            }

            $this->logger->info(sprintf('Total number of notifications: %s', $count));
        }

        foreach ($notificationsCollection as $index => $notification) {
            /** @var Notification $notification */
            if (
                ($notification instanceof Notification) &&
                $this->notificationsProcess($notification, $registry) &&
                $this->getParam('delNotifs')
            ) {
                $reader->getRepository()->deleteById($notification->getId());
            }

            if (($index < ($count - 1)) && $this->isVerbose()) {
                $this->logger->info(sprintf('Process next notification [%s] ...', $notification->getId()));
            }
        }

        if ($this->isVerbose()) {
            $this->logger->info(sprintf('The script took %s seconds to run.', time() - $t0));
        }
    }

    public function notificationsProcess(Notification $notification, NotifySourceRegistry $registry): bool
    {
        $isProcessed = false;
        $nOriginal   = $notification->getOriginal();

        try {
            $notifyPayloads = json_decode($nOriginal, true, 512, JSON_THROW_ON_ERROR);

            if ($this->isVerbose()) {
                $this->logger->info(sprintf("Current notification: %s ", $nOriginal));
            }

            $originInbox = $notifyPayloads['origin']['inbox'] ?? '';
            $source      = $registry->findByOriginInbox($originInbox);

            if ($source === null) {
                $this->logger->warning(sprintf(
                    'Notification %s ignored: unknown origin inbox %s',
                    $notification->getId(),
                    $originInbox
                ));
                return false;
            }

            if ($this->checkNotifyPayloads($notifyPayloads, $source)) {

                if ($this->isVerbose()) {
                    $this->logger->info('Check payloads specification: complete');
                }

                $rvCode  = $this->getRvCodeFromUrl($notifyPayloads['target']['id']);
                $journal = ($rvCode !== '') ? Episciences_ReviewsManager::findByRvcode($rvCode, true, true) : null;

                if ($journal) {
                    if ($this->isVerbose()) {
                        $this->logger->info(sprintf(
                            'Current journal: %s [#%s]',
                            $journal->getCode(),
                            Episciences_Review::$_currentReviewId
                        ));
                    }

                    Zend_Registry::set('reviewSettings', $journal->getSettings());

                    $actor = $notifyPayloads['actor']['id'] ?? null;
                    if (!$actor) {
                        $this->logger->warning(sprintf(
                            'Notification %s ignored: undefined Actor',
                            $notification->getId()
                        ));
                        return false;
                    }

                    $object = filter_var(
                        $notifyPayloads['object'][self::OBJECT_IDENTIFIER_URL],
                        FILTER_VALIDATE_URL,
                        FILTER_FLAG_PATH_REQUIRED
                    );

                    if (!$object) {
                        $this->logger->warning(sprintf(
                            'Notification %s ignored: undefined Object',
                            $notification->getId()
                        ));
                        return false;
                    }

                    $isProcessed = $this->initSubmission($journal, $actor, $object, $source, $notifyPayloads);

                } else {
                    $this->logger->warning(sprintf(
                        'notification %s ignored: undefined journal: %s',
                        $notification->getId(),
                        $rvCode
                    ));
                }
            }

        } catch (JsonException $e) {
            $this->logger->critical($e->getMessage());
        }

        return $isProcessed;
    }

    /**
     * Validates a COAR Notify payload against the source origin, type and target constraints.
     * Delegates to the pure PayloadValidator — logs warnings when validation fails.
     */
    public function checkNotifyPayloads(array $notifyPayloads, NotifySourceConfig $source): bool
    {
        $validator = new PayloadValidator(
            $source->getAcceptedTypes(),
            $source->getOriginInbox(),
            DOMAIN
        );

        $result = $validator->validate($notifyPayloads);

        if (!$result->isValid() && $this->isVerbose()) {
            $this->logger->warning(sprintf(
                "Notification id = %s not proceeded: %s",
                $notifyPayloads['id'] ?? 'unknown',
                $result->getErrorMessage()
            ));
        }

        return $result->isValid();
    }

    /**
     * Extracts the journal code from a target URL.
     * Example: https://revue-test.episciences.org → 'revue-test'
     */
    public function getRvCodeFromUrl(?string $url = null): string
    {
        if (!$url) {
            $this->logger->warning('EMPTY TARGET IDENTIFIER');
            return '';
        }

        $rvCode = $this->urlParser->extractRvCode($url, DOMAIN);

        $this->logger->info('CURRENT RVCODE: ' . $rvCode);

        return $rvCode;
    }

    /**
     * Extracts identifier and version from a preprint URL.
     * Example: https://hal.science/hal-03697346v3 → ['identifier' => 'hal-03697346', 'version' => 3]
     *
     * @return array{identifier: string, version: int}
     */
    public function dataFromUrl(string $url): array
    {
        return $this->urlParser->parseUrl($url);
    }

    public function setParam($name, $value, bool $force = false): bool
    {
        return parent::setParam($name, $value, $force); // avoids creating an unnecessary log file @see Script::log()
    }

    // -------------------------------------------------------------------------
    // Submission orchestration
    // -------------------------------------------------------------------------

    private function initSubmission(
        Episciences_Review $journal,
        string             $actor,
        string             $object,
        NotifySourceConfig $source,
        array              $notifyPayloads = []
    ): bool {
        $repoId = $source->getRepoId();

        $data                   = $this->dataFromUrl($object);
        $data['rvid']           = $journal->getRvid();
        $data['notifyPayloads'] = $notifyPayloads;
        $data['repoid']         = $repoId;
        $data['uid']            = $this->extractUid($actor);

        if ($this->isVerbose()) {
            $this->logger->info(sprintf('Submit to the journal: %s', $journal->getCode()));
            $this->logger->info(sprintf('Actor: %s', $actor));
            $this->logger->info(sprintf('Paper: %s (version = %s)', $data['identifier'], $data['version']));
        }

        $result = $this->getRecord($data['repoid'], $data['identifier'], $data['version'], $journal->getRvid());

        if (isset($result['error']) || !isset($result['record'])) {
            $this->logger->warning($result['error'] ?? 'Unknown error fetching record');
            return false;
        }

        $data['record'] = $result['record'];

        if (isset($result[Episciences_Repositories_Common::ENRICHMENT])) {
            $data[Episciences_Repositories_Common::ENRICHMENT] = $result[Episciences_Repositories_Common::ENRICHMENT];
        }

        $newVerErrors = isset($result['newVerErrors']) ? (array) $result['newVerErrors'] : [];
        $apply        = $this->resolveSubmissionApply((int) $result['status'], $newVerErrors);

        return $apply && $this->addSubmission($journal, $data, $newVerErrors);
    }

    /**
     * Determines whether the submission can proceed based on the record lookup status.
     *
     * Status 1: new submission — always allowed.
     * Status 2: existing article — allowed only when the version can be replaced.
     */
    private function resolveSubmissionApply(int $status, array $newVerErrors): bool
    {
        if ($status === 1) {
            if ($this->isVerbose()) {
                $this->logger->info('Success: ready to submit...');
            }
            return true;
        }

        if ($status !== 2) {
            return false;
        }

        if ($this->isVerbose()) {
            $this->logger->warning('Existing article...');
        }

        if (!isset($newVerErrors['message'])) {
            return false;
        }

        if ($this->isVerbose()) {
            $this->logger->warning('Check if it is possible to replace this version...');
        }

        if (!isset($newVerErrors['canBeReplaced'])) {
            if ($this->isVerbose()) {
                $this->logger->warning($newVerErrors['message'] . PHP_EOL);
            }
            return false;
        }

        if ($this->isVerbose()) {
            $this->logger->info($newVerErrors['message']);
        }

        return $newVerErrors['canBeReplaced'] || isset($newVerErrors[self::PAPER_CONTEXT]);
    }

    /**
     * Entry point for persisting a submission (first or subsequent version).
     */
    public function addSubmission(Episciences_Review $journal, array $data, array $options = null): bool
    {
        $canBeReplaced    = $options['canBeReplaced'] ?? false;
        $context          = $options[self::PAPER_CONTEXT] ?? null;
        $isFirstSubmission = $context === null;

        $logDetails = isset($data['notifyPayloads']) ? ['notifyPayloads' => $data['notifyPayloads']] : [];

        try {
            $paper = new Episciences_Paper($data);
        } catch (Zend_Db_Statement_Exception $e) {
            $this->logger->critical($e->getMessage());
            return false;
        }

        $paper->setSubmission_date();
        $paper->setWhen();

        if ($paper->alreadyExists()) {
            $this->logger->info(sprintf(
                'This article (identifier = %s) has already been submitted',
                $data['identifier']
            ));

            if ($context !== null && $context->getVersion() >= $paper->getVersion()) {
                $this->logger->warning('Abort processing: identical versions.');
                return false;
            }

        } elseif ($this->addLocalUserInNotExist($data) === null) {
            return false;
        }

        if ($isFirstSubmission) {
            return $this->processFirstSubmission($paper, $journal, $data, $logDetails);
        }

        /** @var Episciences_Paper $context */
        return $this->processSubsequentSubmission($paper, $context, $journal, $data, $logDetails, $canBeReplaced);
    }

    private function processFirstSubmission(
        Episciences_Paper  $paper,
        Episciences_Review $journal,
        array              $data,
        array              $logDetails
    ): bool {
        try {
            if ($this->isDebug()) {
                if ($this->isVerbose()) {
                    $this->logger->info(sprintf(
                        '[Debug mode ON]: The article (identifier = %s) has been submitted',
                        $data['identifier']
                    ));
                }
                return false;
            }

            $isAdded = $this->getFirstSubmissionResult($paper, $journal, $data, $logDetails);

            if (!$isAdded) {
                $this->logger->critical(sprintf(
                    'An error occurred while saving the article (identifier = %s)',
                    $data['identifier']
                ));
            }

            return $isAdded;
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
            return false;
        }
    }

    private function processSubsequentSubmission(
        Episciences_Paper  $paper,
        Episciences_Paper  $context,
        Episciences_Review $journal,
        array              $data,
        array              $logDetails,
        bool               $canBeReplaced
    ): bool {
        $isAdded = false;

        try {
            if ($canBeReplaced) {
                $isAdded = $this->handleVersionReplacement($paper, $context, $journal, $data, $logDetails);
            } elseif (!$this->isDebug()) {
                $isAdded = $this->saveNewVersion($context, $data, $journal, $logDetails);
            }
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        if ($this->isVerbose()) {
            $this->logger->info(sprintf(
                'The article (identifier = %s) has been submitted',
                $paper->getIdentifier()
            ));
        }

        return $isAdded;
    }

    /**
     * Attempts to replace an existing version via updatePaper().
     * Falls back to refused-paper replacement or in-place version update depending on context status.
     */
    private function handleVersionReplacement(
        Episciences_Paper  $paper,
        Episciences_Paper  $context,
        Episciences_Review $journal,
        array              $data,
        array              $logDetails
    ): bool {
        $logDetails = array_merge($logDetails, [
            'oldVersion' => $context->getVersion(),
            'oldStatus'  => $context->getStatus(),
        ]);

        $values = [
            'search_doc'  => [
                'docId'   => $paper->getIdentifier(),
                'version' => $paper->getVersion(),
                'repoId'  => $paper->getRepoid(),
            ],
            'rvid'        => $journal->getRvid(),
            'isEpiNotify' => true,
        ];

        try {
            $uResult = $context->updatePaper($values);
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
            return false;
        }

        if (!isset($uResult['message'])) {
            return false;
        }

        if ($uResult['code'] === 0) {
            if ($this->isVerbose()) {
                $this->logger->info($uResult['message']);
            }
            return false;
        }

        // updatePaper() returned an error code — log and choose fallback strategy
        if ($this->isVerbose()) {
            $this->logger->warning($uResult['message']);
        }

        if ($this->isDebug()) {
            return false;
        }

        if ($context->getStatus() === Episciences_Paper::STATUS_REFUSED) {
            return $this->handleRefusedPaperReplacement($paper, $context, $journal, $data);
        }

        return $this->performInPlaceVersionUpdate($paper, $context, $journal, $data, $logDetails);
    }

    /**
     * A previously refused paper is being re-submitted: save it as a fresh first submission
     * while preserving the link to the old (refused) document.
     */
    private function handleRefusedPaperReplacement(
        Episciences_Paper  $paper,
        Episciences_Paper  $context,
        Episciences_Review $journal,
        array              $data
    ): bool {
        $paper->setPaperid($context->getPaperid());

        $isAdded = $this->getFirstSubmissionResult($paper, $journal, $data, [
            'canBeReplaced' => true,
            'logDetails'    => [
                'oldStatus' => Episciences_Paper::STATUS_REFUSED,
                'oldDocId'  => $context->getDocid(),
            ],
        ]);

        if (!$isAdded) {
            $this->logger->critical(sprintf(
                'An error occurred while saving the article (identifier = %s)',
                $data['identifier']
            ));
        }

        return $isAdded;
    }

    /**
     * Updates the existing paper record in-place with the new version metadata.
     * Used when the paper version can be replaced before the review process begins.
     */
    private function performInPlaceVersionUpdate(
        Episciences_Paper  $paper,
        Episciences_Paper  $context,
        Episciences_Review $journal,
        array              $data,
        array              $logDetails
    ): bool {
        $context->setVersion($paper->getVersion());
        $context->setRecord($paper->getRecord());
        $context->setWhen($paper->getWhen());
        $context->save();

        $this->logAction($context, $logDetails, self::VERSION_UPDATE);

        Episciences_Paper_DatasetsManager::deleteByDocIdAndRepoId(
            $context->getDocid(),
            $context->getRepoid()
        );

        $this->notifyAuthorAndEditorialCommittee($journal, $context, [
            'canBeReplaced' => true,
            'oldStatus'     => $logDetails['oldStatus'],
        ]);

        $this->enrichment($context, $data);

        return true;
    }

    // -------------------------------------------------------------------------
    // User management
    // -------------------------------------------------------------------------

    /**
     * Extracts a numeric UID from an actor identifier string.
     *
     * Handles multiple formats:
     *   - plain integer:  "1099714"
     *   - mailto scheme:  "mailto:1099714@ccsd.cnrs.fr"
     *   - email string:   "1099714@hal.science"
     */
    protected function extractUid(string $actorId): int
    {
        if (str_starts_with($actorId, 'mailto:')) {
            $actorId = substr($actorId, 7);
        }

        if (str_contains($actorId, '@')) {
            $actorId = (string) strstr($actorId, '@', true);
        }

        return (int) $actorId;
    }

    private function addLocalUserInNotExist(array $data): ?Episciences_User
    {
        if ($this->isVerbose()) {
            $this->logger->info('Add local User if not exist...');
        }

        $rvId = $data['rvid'];
        $uid  = $data['uid'] ?? 0;
        $user = new Episciences_User();

        try {
            $casUser = $user->findWithCAS($data['uid']);
        } catch (Zend_Db_Statement_Exception $e) {
            $this->logger->critical($e->getMessage());
            return null;
        }

        if (!$casUser) {
            $this->logger->warning(sprintf(
                'Notification id = %s not processed: CAS UID = %s not found. Original string was: %s',
                $data[self::NOTIFICATION_ID] ?? 'undefined',
                $uid,
                $data['uid']
            ));
            return null;
        }

        try {
            if (!$user->hasLocalData()) {
                if (
                    !$this->isDebug() &&
                    $user->save(false, false, $rvId) &&
                    $this->isVerbose()
                ) {
                    $this->logger->info(sprintf('local User added [UID = %s]', $uid));
                }
            } elseif ($this->isVerbose()) {
                $this->logger->info('Already existing profile.');
            }
        } catch (JsonException|Zend_Db_Adapter_Exception|Zend_Db_Statement_Exception|Zend_Exception $e) {
            $this->logger->critical($e->getMessage());
            return null;
        }

        if (!$this->isDebug()) {
            $user->addRole(Episciences_Acl::ROLE_AUTHOR, $rvId);
        }

        return $user;
    }

    // -------------------------------------------------------------------------
    // Notification / email
    // -------------------------------------------------------------------------

    private function notifyAuthorAndEditorialCommittee(
        Episciences_Review $journal,
        Episciences_Paper  $paper,
        array              $options = []
    ): void {
        $originalRequest        = $options['originalRequest'] ?? null;
        $canBeReplaced          = $options['canBeReplaced'] ?? false;
        $isPreviousPaperRefused = $canBeReplaced
            && isset($options['oldStatus'])
            && $options['oldStatus'] === Episciences_Paper::STATUS_REFUSED;

        $isFirstSubmission = (
            $originalRequest === null || (
                $originalRequest instanceof Episciences_Comment &&
                $originalRequest->getType() !== Episciences_CommentsManager::TYPE_REVISION_REQUEST
            )
        );

        $author              = $paper->getSubmitter();
        $authorTemplateKey   = Episciences_Mail_TemplatesManager::TYPE_INBOX_PAPER_SUBMISSION_AUTHOR_COPY;
        $managersTemplateKey = $canBeReplaced && !$isPreviousPaperRefused
            ? Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_UPDATED_EDITOR_COPY
            : Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_OTHERS_RECIPIENT_COPY;

        $this->setupJournalTranslations($journal);

        $journalOptions = ['rvCode' => $journal->getCode(), 'rvId' => $journal->getRvid()];
        $paperUrl       = sprintf(
            SERVER_PROTOCOL . "://%s.%s/paper/view?id=%s",
            $journal->getCode(),
            DOMAIN,
            $paper->getDocid()
        );

        $commonTags = $this->buildCommonNotificationTags($journal, $paper, $author);

        if ($this->isVerbose()) {
            $this->logger->info('Send notifications ...');
        }

        $this->sendAuthorNotification($author, $authorTemplateKey, $commonTags, $paper, $paperUrl, $journalOptions);

        if ($this->isDebug()) {
            return;
        }

        [$recipients, $cc] = $this->resolveManagerRecipients($paper, $originalRequest, $isFirstSubmission, $journal);

        if (!$isFirstSubmission && $originalRequest !== null) {
            $commentType = $originalRequest->getType();
            if ($commentType === Episciences_CommentsManager::TYPE_REVISION_ANSWER_NEW_VERSION) {
                $managersTemplateKey = Episciences_Mail_TemplatesManager::TYPE_PAPER_NEW_VERSION_SUBMITTED;
            } elseif ($commentType === Episciences_CommentsManager::TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED) {
                $managersTemplateKey = Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_EDITOR_AND_COPYEDITOR_COPY;
            }
        }

        $adminPaperUrl = sprintf(
            SERVER_PROTOCOL . "://%s.%s/administratepaper/view?id=%s",
            $journal->getCode(),
            DOMAIN,
            $paper->getDocid()
        );

        $this->sendManagerNotifications(
            $recipients,
            $cc,
            $managersTemplateKey,
            $commonTags,
            $paper,
            $journal,
            $journalOptions,
            $adminPaperUrl,
            $isPreviousPaperRefused,
            $options
        );
    }

    private function setupJournalTranslations(Episciences_Review $journal): void
    {
        try {
            $translator = Zend_Registry::get('Zend_Translate');
        } catch (Zend_Exception $e) {
            $this->logger->critical($e->getMessage());
            return;
        }

        $journalPath  = realpath(APPLICATION_PATH . '/../data/' . $journal->getCode());
        $languagesDir = $journalPath ? $journalPath . '/languages' : null;

        if ($translator && $languagesDir && is_dir($languagesDir) && count(scandir($languagesDir)) > 2) {
            $translator->addTranslation($languagesDir);
        }
    }

    private function buildCommonNotificationTags(
        Episciences_Review $journal,
        Episciences_Paper  $paper,
        Episciences_User   $author
    ): array {
        return [
            Episciences_Mail_Tags::TAG_REVIEW_CODE           => $journal->getCode(),
            Episciences_Mail_Tags::TAG_REVIEW_NAME           => $journal->getName(),
            Episciences_Mail_Tags::TAG_ARTICLE_ID            => $paper->getDocId(),
            Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID  => $paper->getPaperid(),
            Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME => $author->getFullName(),
        ];
    }

    private function sendAuthorNotification(
        Episciences_User   $author,
        string             $templateKey,
        array              $commonTags,
        Episciences_Paper  $paper,
        string             $paperUrl,
        array              $journalOptions
    ): void {
        $aLocale = $author->getLangueid(true);

        try {
            $authorTags = $commonTags + [
                Episciences_Mail_Tags::TAG_PAPER_URL     => $paperUrl,
                Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($aLocale, true),
                Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata($aLocale),
            ];
        } catch (Zend_Exception $e) {
            $this->logger->critical($e->getMessage());
            $authorTags = $commonTags + [Episciences_Mail_Tags::TAG_PAPER_URL => $paperUrl];
        }

        try {
            if (
                !$this->isDebug() &&
                $this->isVerbose() &&
                Episciences_Mail_Send::sendMailFromReview(
                    $author,
                    $templateKey,
                    $authorTags,
                    $paper,
                    null,
                    [],
                    false,
                    $paper->getCoAuthors(),
                    $journalOptions
                )
            ) {
                $this->logger->info(sprintf("Author: %s notified.", $author->getScreenName()));
            }
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    /**
     * Resolves the list of editorial committee members to notify, filtered for conflicts of interest.
     *
     * @return array{0: Episciences_User[], 1: Episciences_User[]}  [$recipients, $cc]
     */
    private function resolveManagerRecipients(
        Episciences_Paper    $paper,
        ?Episciences_Comment $originalRequest,
        bool                 $isFirstSubmission,
        Episciences_Review   $journal
    ): array {
        $recipients = [];
        $cc         = [];

        if (!$isFirstSubmission) {
            try {
                $recipients = $paper->getEditors(true, true) + $paper->getCopyEditors(true, true);
            } catch (Zend_Db_Statement_Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }

        try {
            Episciences_Review::checkReviewNotifications($recipients, !empty($recipients), $journal->getRvid());
        } catch (Zend_Db_Statement_Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        Episciences_PapersManager::keepOnlyUsersWithoutConflict($paper->getPaperid(), $recipients);
        unset($recipients[$paper->getUid()]);

        if (!$isFirstSubmission && $originalRequest !== null) {
            try {
                if (
                    $paper->isEditor($originalRequest->getUid()) ||
                    $paper->getCopyEditor($originalRequest->getUid())
                ) {
                    $principalRecipient = new Episciences_User();
                    $principalRecipient->find($originalRequest->getUid());
                    $cc         = $paper->extractCCRecipients($recipients, $principalRecipient->getUid());
                    $recipients = [$principalRecipient];
                }
            } catch (Zend_Db_Statement_Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }

        return [$recipients, $cc];
    }

    private function sendManagerNotifications(
        array              $recipients,
        array              $cc,
        string             $managersTemplateKey,
        array              $commonTags,
        Episciences_Paper  $paper,
        Episciences_Review $journal,
        array              $journalOptions,
        string             $paperUrl,
        bool               $isPreviousPaperRefused,
        array              $options
    ): void {
        $adminTags                                        = $commonTags;
        $adminTags[Episciences_Mail_Tags::TAG_PAPER_URL] = $paperUrl;
        $refMessage                                       = '';

        if ($isPreviousPaperRefused && isset($options['oldDocId'])) {
            $refMessage = 'Cet article a été précédemment refusé dans sa première version, pour le consulter, merci de suivre ce lien : ';
            $adminTags[Episciences_Mail_Tags::TAG_REFUSED_PAPER_URL] = sprintf(
                SERVER_PROTOCOL . "://%s.%s/administratepaper/view?id=%s",
                $journal->getCode(),
                DOMAIN,
                $options['oldDocId']
            );
        }

        $unsent = [];

        foreach ($recipients as $recipient) {
            $rLocale = $recipient->getLangueid(true);

            try {
                $adminTags[Episciences_Mail_Tags::TAG_ARTICLE_TITLE] = $paper->getTitle($rLocale, true);
            } catch (Zend_Exception $e) {
                $this->logger->critical($e->getMessage());
            }

            try {
                $adminTags[Episciences_Mail_Tags::TAG_AUTHORS_NAMES] = $paper->formatAuthorsMetadata($rLocale);
            } catch (Zend_Exception $e) {
                $this->logger->critical($e->getMessage());
            }

            if ($refMessage !== '') {
                try {
                    $translator = Zend_Registry::get('Zend_Translate');
                    $refMessage = $translator->translate($refMessage, $rLocale, true);
                } catch (Zend_Exception $e) {
                    // Keep the untranslated message as a safe fallback
                }
                $adminTags[Episciences_Mail_Tags::TAG_REFUSED_ARTICLE_MESSAGE] = $refMessage;
            }

            try {
                $isNotified = Episciences_Mail_Send::sendMailFromReview(
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
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
                $isNotified = false;
                $unsent[]   = $recipient->getUid();
            }

            if ($this->isVerbose() && $isNotified) {
                $this->logger->info(sprintf('%s notified > OK', $recipient->getScreenName()));
            }
        }

        if ($this->isVerbose()) {
            if (empty($unsent)) {
                $this->logger->info('All editorial committee notified > OK');
            } else {
                $this->logger->warning(sprintf(
                    'Recipient(s) have not been notified: %s',
                    implode(', ', $unsent)
                ));
            }
        }
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    private function logAction(
        Episciences_Paper $paper,
        array             $details = [],
        string            $submissionType = self::FIRST_SUBMISSION
    ): void {
        $details = array_merge(['origin' => $paper->getRepoid()], $details);

        if ($this->isVerbose()) {
            $this->logger->debug('log action...');
        }

        if ($this->isDebug()) {
            return;
        }

        try {
            $paper->log(Episciences_Paper_Logger::CODE_INBOX_COAR_NOTIFY_REVIEW, EPISCIENCES_UID, $details);
        } catch (Zend_Db_Adapter_Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        if ($submissionType === self::VERSION_UPDATE) {
            $this->logVersionUpdate($paper, $details);
        } else {
            $this->logStatusChange($paper);
        }

        if ($this->isVerbose()) {
            $this->logger->debug($this->resolveLogActionMessage($submissionType));
        }
    }

    private function logVersionUpdate(Episciences_Paper $paper, array $details): void
    {
        try {
            $paper->log(
                Episciences_Paper_Logger::CODE_PAPER_UPDATED,
                EPISCIENCES_UID,
                [
                    'user'    => (new Episciences_User())->find(EPISCIENCES_UID),
                    'version' => [
                        'old' => $details['oldVersion'] ?? 1,
                        'new' => $paper->getVersion(),
                    ],
                ]
            );
        } catch (Zend_Db_Adapter_Exception|Zend_Db_Statement_Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    private function logStatusChange(Episciences_Paper $paper): void
    {
        try {
            $paper->log(
                Episciences_Paper_Logger::CODE_STATUS,
                EPISCIENCES_UID,
                ['status' => $paper->getStatus()]
            );
        } catch (Zend_Db_Adapter_Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    private function resolveLogActionMessage(string $submissionType): string
    {
        return match ($submissionType) {
            self::VERSION_UPDATE => 'Article version updated' . PHP_EOL,
            self::NEW_VERSION    => 'New version submitted' . PHP_EOL,
            default              => 'New submission submitted' . PHP_EOL,
        };
    }

    // -------------------------------------------------------------------------
    // Record fetching & persistence helpers
    // -------------------------------------------------------------------------

    private function getRecord(
        int    $repoId,
        string $identifier,
        ?int   $version = null,
        ?int   $rvId = null,
        bool   $isEpiNotify = true
    ): array {
        if ($this->isVerbose()) {
            $this->logger->info('Get record...');
        }

        try {
            return Episciences_Submit::getDoc($repoId, $identifier, $version, null, true, $rvId, $isEpiNotify);
        } catch (Zend_Exception $e) {
            $this->logger->critical($e->getMessage());
            return ['error' => 'Empty body'];
        }
    }

    private function getFirstSubmissionResult(
        Episciences_Paper  $paper,
        Episciences_Review $journal,
        array              $data,
        array              $options = []
    ): bool {
        $logDetails = $options['logDetails'] ?? [];

        $nOptions = array_filter([
            'canBeReplaced' => $options['canBeReplaced'] ?? null,
            'oldStatus'     => $options['logDetails']['oldStatus'] ?? null,
            'oldDocId'      => $options['logDetails']['oldDocId'] ?? null,
        ], static fn($v): bool => $v !== null);

        try {
            if ($paper->save()) {
                if ($this->isVerbose()) {
                    $this->logger->info(sprintf(
                        'The article (identifier = %s) has been submitted',
                        $data['identifier']
                    ));
                }

                $this->logAction($paper, $logDetails);
                $this->notifyAuthorAndEditorialCommittee($journal, $paper, $nOptions);
                $this->enrichment($paper, $data);

                return true;
            }
        } catch (Zend_Db_Adapter_Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // New version processing
    // -------------------------------------------------------------------------

    /**
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     * @throws DOMException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function saveNewVersion(
        Episciences_Paper  $context,
        array              $newPaperData,
        Episciences_Review $journal,
        array              $logDetails = []
    ): bool {
        $context->loadOtherVolumes();
        $journal->loadSettings();

        $isCopyEditingProcessStarted = $context->isCopyEditingProcessStarted();
        $comments                    = Episciences_CommentsManager::getRevisionRequests(
            $context->getDocid(),
            [Episciences_CommentsManager::TYPE_REVISION_REQUEST]
        );

        $comment           = new Episciences_Comment($comments[array_key_first($comments)]);
        $reassignReviewers = $comment->getOption('reassign_reviewers');
        $isAlreadyAccepted = $comment->getOption('isAlreadyAccepted');

        $paperId     = ($context->getPaperid()) ?: $context->getDocid();
        $reviewers   = $context->getReviewers(null, true);
        $editors     = $context->getEditors(true, true);
        $copyEditors = $context->getCopyEditors(true, true);
        $coAuthors   = $context->getCoAuthors();

        $newPaper = clone $context;
        $newPaper->setDocid(null);
        $newPaper->setPaperid($paperId);
        $newPaper->setWhen();
        $newPaper->setVersion($newPaperData['version']);
        $newPaper->setRecord($newPaperData['record']);
        $newPaper->setUid($newPaperData['uid']);
        $newPaper->setRepoid($newPaperData['repoid']);
        $newPaper->setIdentifier($newPaperData['identifier']);

        $isAssignedReviewers = $reassignReviewers && $reviewers;
        $status              = $this->resolveNewVersionStatus(
            $isCopyEditingProcessStarted,
            (bool) $isAlreadyAccepted,
            $isAssignedReviewers,
            $newPaper,
            $journal
        );

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

        if ($context->getOtherVolumes()) {
            $newPaper->setOtherVolumes($context->getOtherVolumes());
            $newPaper->saveOtherVolumes();
        }

        $context->setStatus(Episciences_Paper::STATUS_OBSOLETE);
        $context->setVid();
        $context->setOtherVolumes();
        $context->setPassword();
        $context->save();
        $context->log(Episciences_Paper_Logger::CODE_STATUS, null, ['status' => $context->getStatus()]);

        $this->unassignPaperMembers($context, $journal, $reviewers, $editors, $copyEditors, $isCopyEditingProcessStarted);

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
        $this->enrichment($newPaper, $newPaperData);

        return true;
    }

    private function resolveNewVersionStatus(
        bool               $isCopyEditingProcessStarted,
        bool               $isAlreadyAccepted,
        bool               $isAssignedReviewers,
        Episciences_Paper  $paper,
        Episciences_Review $journal
    ): int {
        if ($isCopyEditingProcessStarted) {
            return $paper->getStatus() === Episciences_Paper::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION
                ? Episciences_Paper::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION
                : Episciences_Paper::STATUS_CE_READY_TO_PUBLISH;
        }

        if ($isAlreadyAccepted && !$isAssignedReviewers) {
            return (int) $journal->getSetting(Episciences_Review::SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION) === 1
                ? Episciences_Paper::STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING
                : Episciences_Paper::STATUS_ACCEPTED;
        }

        return $isAssignedReviewers ? $paper::STATUS_OK_FOR_REVIEWING : $paper::STATUS_SUBMITTED;
    }

    private function unassignPaperMembers(
        Episciences_Paper  $context,
        Episciences_Review $journal,
        array              $reviewers,
        array              $editors,
        array              $copyEditors,
        bool               $isCopyEditingProcessStarted
    ): void {
        if (!$isCopyEditingProcessStarted) {
            foreach ($reviewers as $reviewer) {
                if (!$reviewer->getInvitation($context->getDocid(), $journal->getRvid())) {
                    continue;
                }
                $aid = $context->unassign($reviewer->getUid(), Episciences_User_Assignment::ROLE_REVIEWER);
                $context->log(
                    Episciences_Paper_Logger::CODE_REVIEWER_UNASSIGNMENT,
                    null,
                    ['aid' => $aid, 'user' => $reviewer->toArray()]
                );
            }
        }

        foreach ($editors as $editor) {
            $aid = $context->unassign($editor->getUid(), Episciences_User_Assignment::ROLE_EDITOR);
            $context->log(
                Episciences_Paper_Logger::CODE_EDITOR_UNASSIGNMENT,
                null,
                ['aid' => $aid, 'user' => $editor->toArray()]
            );
        }

        foreach ($copyEditors as $copyEditor) {
            $aid = $context->unassign($copyEditor->getUid(), Episciences_User_Assignment::ROLE_COPY_EDITOR);
            $context->log(
                Episciences_Paper_Logger::CODE_COPY_EDITOR_UNASSIGNMENT,
                null,
                ['aid' => $aid, 'user' => $copyEditor->toArray()]
            );
        }
    }

    private function reinviteReviewers(
        array              $reviewers,
        Episciences_Paper  $context,
        Episciences_Paper  $paper,
        ?Episciences_User  $sender,
        Episciences_Review $journal
    ): void {
        $templateKey = Episciences_Mail_TemplatesManager::TYPE_PAPER_NEW_VERSION_REVIEWER_REINVITATION;

        $contextUrl = sprintf(
            SERVER_PROTOCOL . "://%s.%s/paper/view?id=%s",
            $journal->getCode(),
            DOMAIN,
            $paper->getDocid()
        );

        $deadline = Episciences_Tools::addDateInterval(
            date('Y-m-d'),
            $journal->getSetting(Episciences_Review::SETTING_RATING_DEADLINE)
        );

        $params = [
            'deadline' => $deadline,
            'status'   => Episciences_User_Assignment::STATUS_PENDING,
            'rvid'     => $journal->getRvid(),
        ];

        $journalOptions = ['rvCode' => $journal->getCode(), 'rvId' => $journal->getRvid()];
        if ($sender) {
            $journalOptions['sender'] = $sender;
        }

        /** @var Episciences_Reviewer $reviewer */
        foreach ($reviewers as $reviewer) {
            /** @var Episciences_User_Assignment $oAssignment */
            $oAssignment = $reviewer->assign($paper->getDocid(), $params)[0];

            $oInvitation = new Episciences_User_Invitation([
                'aid'        => $oAssignment->getId(),
                'sender_uid' => EPISCIENCES_UID,
            ]);

            if ($oInvitation->save()) {
                $oInvitation = Episciences_User_InvitationsManager::findById($oInvitation->getId());
            }

            $invitationUrl = sprintf(
                SERVER_PROTOCOL . "://%s.%s/reviewer/invitation?id=%s",
                $journal->getCode(),
                DOMAIN,
                $oInvitation->getId()
            );

            $oAssignment->setInvitation_id($oInvitation->getId());
            $oAssignment->save();

            $locale = $reviewer->getLangueid();

            $tags = [
                Episciences_Mail_Tags::TAG_ARTICLE_ID            => $paper->getDocid(),
                Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID  => $paper->getPaperid(),
                Episciences_Mail_Tags::TAG_ARTICLE_TITLE         => $context->getTitle($locale, true),
                Episciences_Mail_Tags::TAG_AUTHORS_NAMES         => $context->formatAuthorsMetadata($locale),
                Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE => Episciences_View_Helper_Date::Date($context->getWhen(), $locale),
                Episciences_Mail_Tags::TAG_PAPER_URL             => $contextUrl,
                Episciences_Mail_Tags::TAG_INVITATION_URL        => $invitationUrl,
                Episciences_Mail_Tags::TAG_INVITATION_DEADLINE   => Episciences_View_Helper_Date::Date($oInvitation->getExpiration_date(), $locale),
                Episciences_Mail_Tags::TAG_RATING_DEADLINE       => Episciences_View_Helper_Date::Date($oAssignment->getDeadline(), $locale),
            ];

            Episciences_Mail_Send::sendMailFromReview($reviewer, $templateKey, $tags, $paper, null, [], false, [], $journalOptions);
        }
    }

    private function reassignPaperManagers(
        array             $paperManagers,
        Episciences_Paper $paper,
        string            $roleId = Episciences_User_Assignment::ROLE_EDITOR
    ): void {
        $action = $roleId === Episciences_User_Assignment::ROLE_COPY_EDITOR
            ? Episciences_Paper_Logger::CODE_COPY_EDITOR_ASSIGNMENT
            : Episciences_Paper_Logger::CODE_EDITOR_ASSIGNMENT;

        foreach ($paperManagers as $manager) {
            try {
                $aid = $paper->assign($manager->getUid(), $roleId, Episciences_User_Assignment::STATUS_ACTIVE);
            } catch (Zend_Exception $e) {
                $this->logger->critical($e->getMessage());
                $aid = 0;
            }

            try {
                $paper->log($action, null, ['aid' => $aid, 'user' => $manager->toArray()]);
            } catch (Zend_Db_Adapter_Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
    }

    // -------------------------------------------------------------------------
    // Enrichment & repository helpers
    // -------------------------------------------------------------------------

    private function enrichment(Episciences_Paper $paper, array $additionalPaperData = []): void
    {
        $enrichment = $additionalPaperData[Episciences_Repositories_Common::ENRICHMENT] ?? [];

        if (Episciences_Repositories::getApiUrl($paper->getRepoid())) {
            Episciences_Submit::datasetsProcessing($paper);
        }

        if (!isset($enrichment[Episciences_Repositories_Common::CONTRIB_ENRICHMENT])) {
            Episciences_Paper_AuthorsManager::InsertAuthorsFromPapers($paper);
        }

        Episciences_Submit::enrichmentProcess($paper, $enrichment);

        try {
            if (Episciences_Repositories::isFromHalRepository($paper->getRepoid())) {
                Episciences_Paper_AuthorsManager::enrichAffiOrcidFromTeiHalInDB(
                    $paper->getRepoid(),
                    $paper->getPaperid(),
                    $paper->getIdentifier(),
                    (int) $paper->getVersion()
                );
            }
        } catch (JsonException|\Psr\Cache\InvalidArgumentException $e) {
            $this->logger->critical($e->getMessage());
        }
    }

}
