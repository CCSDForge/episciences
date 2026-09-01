<?php

declare(strict_types=1);

require_once __DIR__ . '/../library/Episciences/Notify/Notification.php';
require_once __DIR__ . '/../library/Episciences/Notify/NotificationsRepository.php';
require_once __DIR__ . '/../library/Episciences/Notify/NotifySourceConfig.php';
require_once __DIR__ . '/../library/Episciences/Notify/NotifySourceRegistry.php';
require_once __DIR__ . '/../library/Episciences/Notify/ValidationResult.php';
require_once __DIR__ . '/../library/Episciences/Notify/PayloadValidator.php';
require_once __DIR__ . '/../library/Episciences/Notify/PreprintUrlParser.php';
require_once __DIR__ . '/../library/Episciences/Notify/Reader.php';

use Episciences\Notify\Notification;
use Episciences\Notify\NotificationsRepository;
use Episciences\Notify\NotifySourceConfig;
use Episciences\Notify\NotifySourceRegistry;
use Episciences\Notify\PayloadValidator;
use Episciences\Notify\PreprintUrlParser;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: process inbound COAR Notify notifications from repository inboxes (e.g. HAL).
 *
 * Replaces: scripts/InboxNotifications.php (AbstractScript) & scripts/InitSubmissionsFromHalInbox.php
 *
 * Patterns supported:
 * @see https://notify.coar-repositories.org/patterns/request-review/
 * @see https://notify.coar-repositories.org/patterns/request-endorsement/
 */
class ProcessInboxNotificationsCommand extends Command
{
    protected static $defaultName = 'notify:process-inbox';

    public const COAR_NOTIFY_AT_CONTEXT = [
        'https://www.w3.org/ns/activitystreams',
        'https://purl.org/coar/notify',
    ];
    public const NOTIFICATION_ID       = 'notificationId';
    public const INBOX_SERVICE_TYPE     = ['Service'];
    public const OBJECT_IDENTIFIER_URL  = 'ietf:cite-as';
    public const FIRST_SUBMISSION       = 'firstSubmission';
    public const NEW_VERSION            = 'newVersion';
    public const VERSION_UPDATE         = 'versionUpdate';
    public const PAPER_CONTEXT          = 'previousPaperObject';

    private PreprintUrlParser $urlParser;

    public function __construct()
    {
        parent::__construct();
        $this->urlParser = new PreprintUrlParser();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Process inbound COAR Notify notifications from repository inboxes (e.g. HAL)')
            ->setAliases(['inbox:process'])
            ->addOption('dry-run',          null, InputOption::VALUE_NONE,     'Simulate processing without persisting changes or sending emails')
            ->addOption('delNotifs',        'd',  InputOption::VALUE_NONE,     'Delete successfully processed notifications from the inbox repository')
            ->addOption('limit',            'l',  InputOption::VALUE_REQUIRED, 'Maximum number of inbound notifications to fetch and process', NotificationsRepository::MAX_INBOUND_FETCH)
            ->addOption('notification-id',  null, InputOption::VALUE_REQUIRED, 'Process only this specific notification ID (UUID)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Processing COAR Notify Inbox Notifications');

        $isDryRun         = (bool) $input->getOption('dry-run');
        $deleteProcessed  = (bool) $input->getOption('delNotifs');
        $targetNotifId    = $input->getOption('notification-id');
        $limitOption      = $input->getOption('limit');
        $limit            = is_numeric($limitOption) ? (int) $limitOption : NotificationsRepository::MAX_INBOUND_FETCH;

        if ($isDryRun) {
            $io->note('Dry-run mode enabled — no database writes or emails will be dispatched.');
        }

        // Constants (incl. EPISCIENCES_LOG_PATH) must be defined before the logger is built
        $this->bootstrapConstants();

        $logger = new Logger('inboxNotifications');
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'inboxNotifications_' . date('Y-m-d') . '.log',
            Logger::DEBUG
        ));

        if (!$io->isQuiet()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        \Episciences\AppRegistry::set('appLogger', $logger);

        // Bootstrap Episciences environment and main database
        try {
            $this->bootstrap();
        } catch (\Throwable $e) {
            $logger->critical('Failed to bootstrap Episciences environment or connect to main database: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ]);
            $io->error('Database error connecting to main Episciences database: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Fetch notifications from repository
        try {
            $reader = new Episciences_Notify_Reader();
            $notifications = $reader->getRepository()->findInbound($limit);
        } catch (\Throwable $e) {
            $logger->critical('Failed to connect to inbox database or read notifications: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ]);
            $io->error('Database error connecting to inbox repository: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if ($targetNotifId !== null && $targetNotifId !== '') {
            $notifications = array_values(array_filter(
                $notifications,
                static fn(Notification $n): bool => $n->getId() === (string) $targetNotifId
            ));

            if (empty($notifications)) {
                $logger->warning(sprintf('Target notification ID "%s" not found in the inbound queue.', $targetNotifId));
                $io->warning(sprintf('Target notification ID "%s" not found in the inbound queue.', $targetNotifId));
                return Command::SUCCESS;
            }
        }

        $count = count($notifications);
        $logger->info(sprintf('Fetched %d notification(s) to process (limit: %d).', $count, $limit));

        if ($count === 0) {
            $io->success('No notifications to process in inbox.');
            return Command::SUCCESS;
        }

        $registry = NotifySourceRegistry::createFromConstants();

        $io->progressStart($count);

        $successCount = 0;
        $failedCount  = 0;
        $deletedCount = 0;

        foreach ($notifications as $index => $notification) {
            $notifId = $notification->getId();
            $logger->info(sprintf('[%d/%d] Processing notification ID: %s (direction: %s, type: %s)', $index + 1, $count, $notifId, $notification->getDirection(), $notification->getType()));

            try {
                $isProcessed = $this->notificationsProcess($notification, $registry, $logger, $isDryRun);

                if ($isProcessed) {
                    $successCount++;
                    $logger->info(sprintf('Notification ID %s processed successfully.', $notifId));

                    if ($deleteProcessed && !$isDryRun) {
                        try {
                            $reader->getRepository()->deleteById($notifId);
                            $deletedCount++;
                            $logger->info(sprintf('Notification ID %s deleted from inbox repository.', $notifId));
                        } catch (\Throwable $delEx) {
                            $logger->error(sprintf('Failed to delete processed notification ID %s: %s', $notifId, $delEx->getMessage()), [
                                'trace' => $delEx->getTraceAsString(),
                            ]);
                        }
                    }
                } else {
                    $failedCount++;
                    $logger->warning(sprintf('Notification ID %s could not be processed.', $notifId));
                }
            } catch (\Throwable $e) {
                $failedCount++;
                $logger->critical(sprintf('Unhandled exception while processing notification ID %s: %s', $notifId, $e->getMessage()), [
                    'exception' => get_class($e),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                    'trace'     => $e->getTraceAsString(),
                ]);
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        $io->table(
            ['Total', 'Succeeded', 'Failed / Skipped', 'Deleted from inbox'],
            [[$count, $successCount, $failedCount, $deletedCount]]
        );

        if ($failedCount > 0) {
            $io->warning(sprintf('%d notification(s) failed or were skipped. Check log file for details.', $failedCount));
        } else {
            $io->success('All notifications processed successfully.');
        }

        return Command::SUCCESS;
    }

    public function notificationsProcess(
        Notification         $notification,
        NotifySourceRegistry $registry,
        LoggerInterface      $logger,
        bool                 $isDryRun = false
    ): bool {
        $nOriginal = $notification->getOriginal();
        $notifId   = $notification->getId();

        try {
            $notifyPayloads = json_decode($nOriginal, true, 512, JSON_THROW_ON_ERROR);

            // Some notifications are stored double-JSON-encoded by the inbox writer
            // (the "original" column holds a JSON string of the JSON payload), in
            // which case the first decode only yields the inner JSON as a string.
            if (is_string($notifyPayloads)) {
                $notifyPayloads = json_decode($notifyPayloads, true, 512, JSON_THROW_ON_ERROR);
            }

            if (!is_array($notifyPayloads)) {
                throw new \JsonException('Decoded payload is not an object/array');
            }
        } catch (\JsonException $e) {
            $logger->critical(sprintf(
                'Notification [%s] contains invalid JSON payload: %s. Raw snippet: %.250s',
                $notifId,
                $e->getMessage(),
                $nOriginal
            ));
            return false;
        }

        $logger->debug(sprintf('Notification [%s] raw payload: %s', $notifId, $nOriginal));

        $originInbox = $notifyPayloads['origin']['inbox'] ?? '';
        $source      = $registry->findByOriginInbox($originInbox);

        if ($source === null) {
            $logger->warning(sprintf(
                'Notification [%s] ignored: unknown origin inbox "%s" (payload ID: %s)',
                $notifId,
                $originInbox,
                $notifyPayloads['id'] ?? 'unknown'
            ));
            return false;
        }

        if (!$this->checkNotifyPayloads($notifyPayloads, $source, $logger)) {
            return false;
        }

        $targetUrl = $notifyPayloads['target']['id'] ?? '';
        $rvCode    = $this->getRvCodeFromUrl($targetUrl, $logger);

        if ($rvCode === '') {
            $logger->warning(sprintf(
                'Notification [%s] ignored: unable to extract journal code from target URL "%s"',
                $notifId,
                $targetUrl
            ));
            return false;
        }

        $journal = Episciences_ReviewsManager::findByRvcode($rvCode, true, true);

        if (!$journal instanceof Episciences_Review) {
            $logger->warning(sprintf(
                'Notification [%s] ignored: journal with code "%s" not found in database',
                $notifId,
                $rvCode
            ));
            return false;
        }

        $logger->info(sprintf('Matched journal: %s (RVID #%d)', $journal->getCode(), $journal->getRvid()));
        Zend_Registry::set('reviewSettings', $journal->getSettings());

        $actor = $notifyPayloads['actor']['id'] ?? null;
        if (!$actor) {
            $logger->warning(sprintf('Notification [%s] ignored: undefined or empty Actor in payload', $notifId));
            return false;
        }

        $rawObject = $notifyPayloads['object'][self::OBJECT_IDENTIFIER_URL] ?? null;
        $object    = filter_var($rawObject, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED);

        if (!$object) {
            $logger->warning(sprintf(
                'Notification [%s] ignored: undefined or invalid Object URL [%s = "%s"]',
                $notifId,
                self::OBJECT_IDENTIFIER_URL,
                (string) $rawObject
            ));
            return false;
        }

        return $this->initSubmission($journal, (string) $actor, (string) $object, $source, $notifyPayloads, $logger, $isDryRun, $notifId);
    }

    /**
     * Validates a COAR Notify payload against the source origin, type and target constraints.
     */
    public function checkNotifyPayloads(array $notifyPayloads, NotifySourceConfig $source, ?LoggerInterface $logger = null): bool
    {
        $domain = defined('DOMAIN') ? DOMAIN : 'episciences.org';
        $result = null;

        // A source may accept several COAR Notify patterns (e.g. Request Review vs
        // Request Endorsement); the payload is valid if it matches any one of them.
        foreach ($source->getAcceptedTypes() as $typePattern) {
            $result = (new PayloadValidator($typePattern, $source->getOriginInbox(), $domain))
                ->validate($notifyPayloads);

            if ($result->isValid()) {
                foreach ($result->getWarnings() as $warning) {
                    $logger?->warning(sprintf(
                        'Notification [%s] accepted from a non-conformant payload: %s',
                        $notifyPayloads['id'] ?? 'unknown',
                        $warning
                    ));
                }

                return true;
            }
        }

        if ($result !== null && $logger !== null) {
            $logger->warning(sprintf(
                'Notification payload validation failed for payload ID "%s": %s',
                $notifyPayloads['id'] ?? 'unknown',
                $result->getErrorMessage()
            ));
        }

        return false;
    }

    /**
     * Extracts the journal code from a target URL.
     * Example: https://revue-test.episciences.org → 'revue-test'
     */
    public function getRvCodeFromUrl(?string $url = null, ?LoggerInterface $logger = null): string
    {
        if (!$url) {
            if ($logger !== null) {
                $logger->warning('Target URL identifier is empty.');
            }
            return '';
        }

        $domain = defined('DOMAIN') ? DOMAIN : 'episciences.org';
        $rvCode = $this->urlParser->extractRvCode($url, $domain);

        if ($logger !== null && $rvCode !== '') {
            $logger->info('Extracted RV code: ' . $rvCode);
        }

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

    /**
     * Extracts a numeric UID from an actor identifier string.
     */
    public function extractUid(string $actorId): int
    {
        if (str_starts_with($actorId, 'mailto:')) {
            $actorId = substr($actorId, 7);
        }

        if (str_contains($actorId, '@')) {
            $actorId = (string) strstr($actorId, '@', true);
        }

        if (str_contains($actorId, '/')) {
            $path        = trim((string) parse_url($actorId, PHP_URL_PATH), '/');
            $lastSegment = basename($path);
            if (is_numeric($lastSegment)) {
                $actorId = $lastSegment;
            }
        }

        return (int) $actorId;
    }

    // -------------------------------------------------------------------------
    // Submission orchestration
    // -------------------------------------------------------------------------

    private function initSubmission(
        Episciences_Review $journal,
        string             $actor,
        string             $object,
        NotifySourceConfig $source,
        array              $notifyPayloads,
        LoggerInterface    $logger,
        bool               $isDryRun,
        string             $notifId
    ): bool {
        $repoId = $source->getRepoId();

        $data                   = $this->dataFromUrl($object);
        $data['rvid']           = $journal->getRvid();
        $data['notifyPayloads'] = $notifyPayloads;
        $data['repoid']         = $repoId;
        $data['uid']            = $this->extractUid($actor);
        $data['actorId']        = $actor;
        $data[self::NOTIFICATION_ID] = $notifId;

        $logger->info(sprintf(
            'Initiating submission: Journal=%s, Actor=%s (UID=%d), Paper=%s (version=%d, repoId=%d)',
            $journal->getCode(),
            $actor,
            $data['uid'],
            $data['identifier'],
            $data['version'],
            $data['repoid']
        ));

        $result = $this->getRecord($data['repoid'], $data['identifier'], $data['version'], $journal->getRvid(), $logger);

        if (isset($result['error']) || !isset($result['record'])) {
            $logger->warning(sprintf(
                'Notification [%s]: failed fetching record for paper %s (repoId=%d, version=%d): %s',
                $notifId,
                $data['identifier'],
                $data['repoid'],
                $data['version'],
                $result['error'] ?? 'Unknown error fetching record'
            ));
            return false;
        }

        $data['record'] = $result['record'];

        if (isset($result[Episciences_Repositories_Common::ENRICHMENT])) {
            $data[Episciences_Repositories_Common::ENRICHMENT] = $result[Episciences_Repositories_Common::ENRICHMENT];
        }

        $newVerErrors = isset($result['newVerErrors']) ? (array) $result['newVerErrors'] : [];
        $apply        = $this->resolveSubmissionApply((int) $result['status'], $newVerErrors, $logger, $notifId);

        if (!$apply) {
            return false;
        }

        return $this->addSubmission($journal, $data, $newVerErrors, $logger, $isDryRun);
    }

    /**
     * Determines whether the submission can proceed based on the record lookup status.
     */
    private function resolveSubmissionApply(
        int             $status,
        array           $newVerErrors,
        LoggerInterface $logger,
        string          $notifId
    ): bool {
        if ($status === 1) {
            $logger->info(sprintf('Notification [%s]: new submission ready to be created.', $notifId));
            return true;
        }

        if ($status !== 2) {
            $logger->warning(sprintf('Notification [%s] rejected: unexpected record lookup status code %d.', $notifId, $status));
            return false;
        }

        $logger->info(sprintf('Notification [%s]: existing article detected, checking version replacement...', $notifId));

        if (!isset($newVerErrors['message'])) {
            $logger->warning(sprintf('Notification [%s] rejected: existing article cannot be replaced (no message in newVerErrors).', $notifId));
            return false;
        }

        if (!isset($newVerErrors['canBeReplaced'])) {
            $logger->warning(sprintf('Notification [%s] rejected: cannot replace version — %s', $notifId, $newVerErrors['message']));
            return false;
        }

        $logger->info(sprintf('Notification [%s]: version check status: %s', $notifId, $newVerErrors['message']));

        return (bool) ($newVerErrors['canBeReplaced'] || isset($newVerErrors[self::PAPER_CONTEXT]));
    }

    /**
     * Entry point for persisting a submission (first or subsequent version).
     */
    public function addSubmission(
        Episciences_Review $journal,
        array              $data,
        ?array             $options = null,
        ?LoggerInterface   $logger = null,
        bool               $isDryRun = false
    ): bool {
        $canBeReplaced     = $options['canBeReplaced'] ?? false;
        $context           = $options[self::PAPER_CONTEXT] ?? null;
        $isFirstSubmission = $context === null;
        $logDetails        = isset($data['notifyPayloads']) ? ['notifyPayloads' => $data['notifyPayloads']] : [];

        try {
            $paper = new Episciences_Paper($data);
            $paper->setSubmission_date();
            $paper->setWhen();
            $alreadyExists = $paper->alreadyExists();
        } catch (\Throwable $e) {
            if ($logger !== null) {
                $logger->critical(sprintf('Failed to instantiate or check Episciences_Paper for %s: %s', $data['identifier'] ?? 'unknown', $e->getMessage()), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            return false;
        }

        if ($alreadyExists) {
            if ($logger !== null) {
                $logger->info(sprintf('This article (identifier = %s) has already been submitted.', $data['identifier']));
            }

            if ($context !== null && $context->getVersion() >= $paper->getVersion()) {
                if ($logger !== null) {
                    $logger->warning(sprintf('Abort processing paper %s: identical or older version (%d <= %d).', $data['identifier'], $paper->getVersion(), $context->getVersion()));
                }
                return false;
            }
        } elseif ($this->addLocalUserIfNotExist($data, $logger, $isDryRun) === null) {
            return false;
        }

        if ($isFirstSubmission) {
            return $this->processFirstSubmission($paper, $journal, $data, $logDetails, $logger, $isDryRun);
        }

        /** @var Episciences_Paper $context */
        return $this->processSubsequentSubmission($paper, $context, $journal, $data, $logDetails, (bool) $canBeReplaced, $logger, $isDryRun);
    }

    private function processFirstSubmission(
        Episciences_Paper  $paper,
        Episciences_Review $journal,
        array              $data,
        array              $logDetails,
        ?LoggerInterface   $logger,
        bool               $isDryRun
    ): bool {
        try {
            if ($isDryRun) {
                if ($logger !== null) {
                    $logger->info(sprintf('[Dry-run] Article (identifier = %s) first submission simulated.', $data['identifier']));
                }
                return true;
            }

            $isAdded = $this->getFirstSubmissionResult($paper, $journal, $data, $logDetails, $logger);

            if (!$isAdded && $logger !== null) {
                $logger->critical(sprintf('An error occurred while saving article (identifier = %s)', $data['identifier']));
            }

            return $isAdded;
        } catch (\Throwable $e) {
            if ($logger !== null) {
                $logger->critical(sprintf('Exception during first submission of %s: %s', $data['identifier'], $e->getMessage()), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            return false;
        }
    }

    private function processSubsequentSubmission(
        Episciences_Paper  $paper,
        Episciences_Paper  $context,
        Episciences_Review $journal,
        array              $data,
        array              $logDetails,
        bool               $canBeReplaced,
        ?LoggerInterface   $logger,
        bool               $isDryRun
    ): bool {
        $isAdded = false;

        try {
            if ($canBeReplaced) {
                $isAdded = $this->handleVersionReplacement($paper, $context, $journal, $data, $logDetails, $logger, $isDryRun);
            } elseif (!$isDryRun) {
                $isAdded = $this->saveNewVersion($context, $data, $journal, $logDetails, $logger);
            } else {
                if ($logger !== null) {
                    $logger->info(sprintf('[Dry-run] Subsequent submission of %s v%d simulated.', $paper->getIdentifier(), $paper->getVersion()));
                }
                $isAdded = true;
            }
        } catch (\Throwable $e) {
            if ($logger !== null) {
                $logger->critical(sprintf('Exception during subsequent submission of %s: %s', $paper->getIdentifier(), $e->getMessage()), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        if ($logger !== null && $isAdded) {
            $logger->info(sprintf('Subsequent submission of article (identifier = %s, version = %d) completed.', $paper->getIdentifier(), $paper->getVersion()));
        }

        return $isAdded;
    }

    private function handleVersionReplacement(
        Episciences_Paper  $paper,
        Episciences_Paper  $context,
        Episciences_Review $journal,
        array              $data,
        array              $logDetails,
        ?LoggerInterface   $logger,
        bool               $isDryRun
    ): bool {
        $logDetails = array_merge($logDetails, [
            'oldVersion' => $context->getVersion(),
            'oldStatus'  => $context->getStatus(),
        ]);

        $values = [
            'search_doc' => [
                'docId'   => $paper->getIdentifier(),
                'version' => $paper->getVersion(),
                'repoId'  => $paper->getRepoid(),
            ],
            'rvid'        => $journal->getRvid(),
            'isEpiNotify' => true,
        ];

        try {
            $uResult = $context->updatePaper($values);
        } catch (\Throwable $e) {
            if ($logger !== null) {
                $logger->critical(sprintf('updatePaper failed for %s: %s', $paper->getIdentifier(), $e->getMessage()), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            return false;
        }

        if (!isset($uResult['message'])) {
            if ($logger !== null) {
                $logger->warning(sprintf('updatePaper for %s returned unexpected result (no message key).', $paper->getIdentifier()));
            }
            return false;
        }

        if ($uResult['code'] === 0) {
            if ($logger !== null) {
                $logger->info('updatePaper: ' . $uResult['message']);
            }
            return false;
        }

        if ($logger !== null) {
            $logger->warning('updatePaper warning code: ' . $uResult['message']);
        }

        if ($isDryRun) {
            return true;
        }

        if ($context->getStatus() === Episciences_Paper::STATUS_REFUSED) {
            return $this->handleRefusedPaperReplacement($paper, $context, $journal, $data, $logger);
        }

        return $this->performInPlaceVersionUpdate($paper, $context, $journal, $data, $logDetails, $logger);
    }

    private function handleRefusedPaperReplacement(
        Episciences_Paper  $paper,
        Episciences_Paper  $context,
        Episciences_Review $journal,
        array              $data,
        ?LoggerInterface   $logger
    ): bool {
        $paper->setPaperid($context->getPaperid());

        $isAdded = $this->getFirstSubmissionResult($paper, $journal, $data, [
            'canBeReplaced' => true,
            'logDetails'    => [
                'oldStatus' => Episciences_Paper::STATUS_REFUSED,
                'oldDocId'  => $context->getDocid(),
            ],
        ], $logger);

        if (!$isAdded && $logger !== null) {
            $logger->critical(sprintf('Failed saving refused replacement paper (identifier = %s)', $data['identifier']));
        }

        return $isAdded;
    }

    private function performInPlaceVersionUpdate(
        Episciences_Paper  $paper,
        Episciences_Paper  $context,
        Episciences_Review $journal,
        array              $data,
        array              $logDetails,
        ?LoggerInterface   $logger
    ): bool {
        $context->setVersion($paper->getVersion());
        $context->setRecord($paper->getRecord());
        $context->setWhen($paper->getWhen());
        $context->save();

        $this->logAction($context, $logDetails, self::VERSION_UPDATE, $logger);

        Episciences_Paper_DatasetsManager::deleteByDocIdAndRepoId(
            $context->getDocid(),
            $context->getRepoid()
        );

        $this->notifyAuthorAndEditorialCommittee($journal, $context, [
            'canBeReplaced' => true,
            'oldStatus'     => $logDetails['oldStatus'] ?? null,
        ], $logger);

        $this->enrichment($context, $data, $logger);

        return true;
    }

    // -------------------------------------------------------------------------
    // User management
    // -------------------------------------------------------------------------

    private function addLocalUserIfNotExist(array $data, ?LoggerInterface $logger, bool $isDryRun): ?Episciences_User
    {
        $rvId = (int) ($data['rvid'] ?? 0);
        $uid  = (int) ($data['uid'] ?? 0);

        if ($uid <= 0) {
            if ($logger !== null) {
                $logger->warning(sprintf(
                    'Cannot add/find user for notification [%s]: invalid CAS UID (%d). Original actor string was: %s',
                    $data[self::NOTIFICATION_ID] ?? 'undefined',
                    $uid,
                    $data['actorId'] ?? 'unknown'
                ));
            }
            return null;
        }

        $user = new Episciences_User();

        try {
            $casUser = $user->findWithCAS($uid);
        } catch (\Throwable $e) {
            if ($logger !== null) {
                $logger->critical(sprintf('Database error in findWithCAS(%d): %s', $uid, $e->getMessage()), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            return null;
        }

        if (!$casUser) {
            if ($logger !== null) {
                $logger->warning(sprintf(
                    'Notification [%s] not processed: CAS UID %d not found in CAS database (actor: %s)',
                    $data[self::NOTIFICATION_ID] ?? 'undefined',
                    $uid,
                    $data['actorId'] ?? 'unknown'
                ));
            }
            return null;
        }

        try {
            if (!$user->hasLocalData()) {
                if (!$isDryRun) {
                    $user->save(false, false, $rvId);
                    if ($logger !== null) {
                        $logger->info(sprintf('Local User profile created [UID = %d, RVID = %d]', $uid, $rvId));
                    }
                }
            } elseif ($logger !== null) {
                $logger->debug(sprintf('Existing profile for UID %d.', $uid));
            }
        } catch (\Throwable $e) {
            if ($logger !== null) {
                $logger->critical(sprintf('Failed to save local user data for UID %d: %s', $uid, $e->getMessage()), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            return null;
        }

        if (!$isDryRun) {
            try {
                $user->addRole(Episciences_Acl::ROLE_AUTHOR, $rvId);
            } catch (\Throwable $e) {
                if ($logger !== null) {
                    $logger->warning(sprintf('Failed assigning author role to UID %d: %s', $uid, $e->getMessage()));
                }
            }
        }

        return $user;
    }

    // -------------------------------------------------------------------------
    // Notification / email
    // -------------------------------------------------------------------------

    private function notifyAuthorAndEditorialCommittee(
        Episciences_Review $journal,
        Episciences_Paper  $paper,
        array              $options = [],
        ?LoggerInterface   $logger = null
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

        $this->setupJournalTranslations($journal, $logger);

        $journalOptions = ['rvCode' => $journal->getCode(), 'rvId' => $journal->getRvid()];
        $serverProtocol = defined('SERVER_PROTOCOL') ? SERVER_PROTOCOL : 'https';
        $domain         = defined('DOMAIN') ? DOMAIN : 'episciences.org';

        $paperUrl = sprintf(
            "%s://%s.%s/paper/view?id=%s",
            $serverProtocol,
            $journal->getCode(),
            $domain,
            $paper->getDocid()
        );

        $commonTags = $this->buildCommonNotificationTags($journal, $paper, $author);

        if ($logger !== null) {
            $logger->info(sprintf('Sending notifications for doc ID %s (Journal: %s)...', $paper->getDocid(), $journal->getCode()));
        }

        $this->sendAuthorNotification($author, $authorTemplateKey, $commonTags, $paper, $paperUrl, $journalOptions, $logger);

        [$recipients, $cc] = $this->resolveManagerRecipients($paper, $originalRequest, $isFirstSubmission, $journal, $logger);

        if (!$isFirstSubmission && $originalRequest !== null) {
            $commentType = $originalRequest->getType();
            if ($commentType === Episciences_CommentsManager::TYPE_REVISION_ANSWER_NEW_VERSION) {
                $managersTemplateKey = Episciences_Mail_TemplatesManager::TYPE_PAPER_NEW_VERSION_SUBMITTED;
            } elseif ($commentType === Episciences_CommentsManager::TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED) {
                $managersTemplateKey = Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_EDITOR_AND_COPYEDITOR_COPY;
            }
        }

        $adminPaperUrl = sprintf(
            "%s://%s.%s/administratepaper/view?id=%s",
            $serverProtocol,
            $journal->getCode(),
            $domain,
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
            $options,
            $logger
        );
    }

    private function setupJournalTranslations(Episciences_Review $journal, ?LoggerInterface $logger): void
    {
        try {
            $translator = Zend_Registry::get('Zend_Translate');
        } catch (\Throwable $e) {
            if ($logger !== null) {
                $logger->warning('Translator not found in registry: ' . $e->getMessage());
            }
            return;
        }

        $journalPath  = realpath(APPLICATION_PATH . '/../data/' . $journal->getCode());
        $languagesDir = $journalPath ? $journalPath . '/languages' : null;

        if ($translator && $languagesDir && is_dir($languagesDir) && count(scandir($languagesDir)) > 2) {
            try {
                $translator->addTranslation($languagesDir);
            } catch (\Throwable $e) {
                if ($logger !== null) {
                    $logger->warning('Failed adding journal translation from ' . $languagesDir . ': ' . $e->getMessage());
                }
            }
        }
    }

    private function buildCommonNotificationTags(
        Episciences_Review $journal,
        Episciences_Paper  $paper,
        Episciences_User   $author
    ): array {
        return [
            Episciences_Mail_Tags::TAG_REVIEW_CODE           => $journal->getMailDisplayCode(),
            Episciences_Mail_Tags::TAG_REVIEW_NAME           => $journal->getName(),
            Episciences_Mail_Tags::TAG_ARTICLE_ID            => $paper->getDocId(),
            Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID  => $paper->getPaperid(),
            Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME => $author->getFullName(),
        ];
    }

    private function sendAuthorNotification(
        Episciences_User  $author,
        string            $templateKey,
        array             $commonTags,
        Episciences_Paper $paper,
        string            $paperUrl,
        array             $journalOptions,
        ?LoggerInterface  $logger
    ): void {
        $aLocale = $author->getLangueid(true);

        try {
            $authorTags = $commonTags + [
                Episciences_Mail_Tags::TAG_PAPER_URL      => $paperUrl,
                Episciences_Mail_Tags::TAG_ARTICLE_TITLE  => $paper->getTitle($aLocale, true),
                Episciences_Mail_Tags::TAG_AUTHORS_NAMES  => $paper->formatAuthorsMetadata($aLocale),
            ];
        } catch (\Throwable $e) {
            if ($logger !== null) {
                $logger->warning('Failed formatting author notification metadata: ' . $e->getMessage());
            }
            $authorTags = $commonTags + [Episciences_Mail_Tags::TAG_PAPER_URL => $paperUrl];
        }

        try {
            $isSent = Episciences_Mail_Send::sendMailFromReview(
                $author,
                $templateKey,
                $authorTags,
                $paper,
                null,
                [],
                false,
                $paper->getCoAuthors(),
                $journalOptions
            );

            if ($isSent && $logger !== null) {
                $logger->info(sprintf('Author (%s) notified successfully.', $author->getScreenName()));
            }
        } catch (\Throwable $e) {
            if ($logger !== null) {
                $logger->critical(sprintf('Failed sending notification email to author (%s): %s', $author->getScreenName(), $e->getMessage()), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * Resolves the list of editorial committee members to notify, filtered for conflicts of interest.
     *
     * @return array{0: Episciences_User[], 1: Episciences_User[]} [$recipients, $cc]
     */
    private function resolveManagerRecipients(
        Episciences_Paper    $paper,
        ?Episciences_Comment $originalRequest,
        bool                 $isFirstSubmission,
        Episciences_Review   $journal,
        ?LoggerInterface     $logger
    ): array {
        $recipients = [];
        $cc         = [];

        if (!$isFirstSubmission) {
            try {
                $recipients = $paper->getEditors(true, true) + $paper->getCopyEditors(true, true);
            } catch (\Throwable $e) {
                if ($logger !== null) {
                    $logger->critical('Failed fetching editors for paper: ' . $e->getMessage());
                }
            }
        }

        try {
            Episciences_Review::checkReviewNotifications($recipients, !empty($recipients), $journal->getRvid());
        } catch (\Throwable $e) {
            if ($logger !== null) {
                $logger->critical('Failed checking review notifications: ' . $e->getMessage());
            }
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
            } catch (\Throwable $e) {
                if ($logger !== null) {
                    $logger->critical('Failed resolving principal editor recipient: ' . $e->getMessage());
                }
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
        array              $options,
        ?LoggerInterface   $logger
    ): void {
        $adminTags  = $commonTags;
        $adminTags[Episciences_Mail_Tags::TAG_PAPER_URL] = $paperUrl;
        $refMessage = '';

        if ($isPreviousPaperRefused && isset($options['oldDocId'])) {
            $refMessage = 'Cet article a été précédemment refusé dans sa première version, pour le consulter, merci de suivre ce lien : ';
            $serverProtocol = defined('SERVER_PROTOCOL') ? SERVER_PROTOCOL : 'https';
            $domain         = defined('DOMAIN') ? DOMAIN : 'episciences.org';
            $adminTags[Episciences_Mail_Tags::TAG_REFUSED_PAPER_URL] = sprintf(
                "%s://%s.%s/administratepaper/view?id=%s",
                $serverProtocol,
                $journal->getCode(),
                $domain,
                $options['oldDocId']
            );
        }

        $unsent = [];

        foreach ($recipients as $recipient) {
            $rLocale = $recipient->getLangueid(true);

            try {
                $adminTags[Episciences_Mail_Tags::TAG_ARTICLE_TITLE] = $paper->getTitle($rLocale, true);
            } catch (\Throwable $e) {
                if ($logger !== null) {
                    $logger->warning('Failed getting localized title for editor notification: ' . $e->getMessage());
                }
            }

            try {
                $adminTags[Episciences_Mail_Tags::TAG_AUTHORS_NAMES] = $paper->formatAuthorsMetadata($rLocale);
            } catch (\Throwable $e) {
                if ($logger !== null) {
                    $logger->warning('Failed getting localized authors metadata for editor notification: ' . $e->getMessage());
                }
            }

            if ($refMessage !== '') {
                try {
                    $translator = Zend_Registry::get('Zend_Translate');
                    $translatedRefMsg = $translator->translate($refMessage, $rLocale, true);
                } catch (\Throwable $e) {
                    $translatedRefMsg = $refMessage;
                }
                $adminTags[Episciences_Mail_Tags::TAG_REFUSED_ARTICLE_MESSAGE] = $translatedRefMsg;
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
            } catch (\Throwable $e) {
                if ($logger !== null) {
                    $logger->critical(sprintf('Failed sending notification to editor (UID %d): %s', $recipient->getUid(), $e->getMessage()), [
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
                $isNotified = false;
                $unsent[]   = $recipient->getUid();
            }

            if ($isNotified && $logger !== null) {
                $logger->info(sprintf('Editor %s notified successfully.', $recipient->getScreenName()));
            }
        }

        if ($logger !== null) {
            if (empty($unsent)) {
                $logger->info('All editorial committee members notified successfully.');
            } else {
                $logger->warning(sprintf('The following recipient UID(s) could not be notified: %s', implode(', ', $unsent)));
            }
        }
    }

    // -------------------------------------------------------------------------
    // Logging & Paper History
    // -------------------------------------------------------------------------

    private function logAction(
        Episciences_Paper $paper,
        array             $details = [],
        string            $submissionType = self::FIRST_SUBMISSION,
        ?LoggerInterface  $logger = null
    ): void {
        $details = array_merge(['origin' => $paper->getRepoid()], $details);

        try {
            $paper->log(Episciences_Paper_Logger::CODE_INBOX_COAR_NOTIFY_REVIEW, EPISCIENCES_UID, $details);
        } catch (\Throwable $e) {
            if ($logger !== null) {
                $logger->critical('Failed logging COAR Notify review action to paper history: ' . $e->getMessage());
            }
        }

        if ($submissionType === self::VERSION_UPDATE) {
            $this->logVersionUpdate($paper, $details, $logger);
        } else {
            $this->logStatusChange($paper, $logger);
        }
    }

    private function logVersionUpdate(Episciences_Paper $paper, array $details, ?LoggerInterface $logger): void
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
        } catch (\Throwable $e) {
            if ($logger !== null) {
                $logger->critical('Failed logging paper version update: ' . $e->getMessage());
            }
        }
    }

    private function logStatusChange(Episciences_Paper $paper, ?LoggerInterface $logger): void
    {
        try {
            $paper->log(
                Episciences_Paper_Logger::CODE_STATUS,
                EPISCIENCES_UID,
                ['status' => $paper->getStatus()]
            );
        } catch (\Throwable $e) {
            if ($logger !== null) {
                $logger->critical('Failed logging paper status change: ' . $e->getMessage());
            }
        }
    }

    // -------------------------------------------------------------------------
    // Record fetching & persistence helpers
    // -------------------------------------------------------------------------

    private function getRecord(
        int              $repoId,
        string           $identifier,
        ?int             $version = null,
        ?int             $rvId = null,
        ?LoggerInterface $logger = null,
        bool             $isEpiNotify = true
    ): array {
        if ($logger !== null) {
            $logger->debug(sprintf('Fetching document from repository: repoId=%d, identifier=%s, version=%s, rvId=%s', $repoId, $identifier, (string) $version, (string) $rvId));
        }

        try {
            return Episciences_Submit::getDoc($repoId, $identifier, $version, null, true, $rvId, $isEpiNotify);
        } catch (\Throwable $e) {
            if ($logger !== null) {
                $logger->critical(sprintf('Episciences_Submit::getDoc error for paper %s (repoId=%d): %s', $identifier, $repoId, $e->getMessage()), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            return ['error' => $e->getMessage()];
        }
    }

    private function getFirstSubmissionResult(
        Episciences_Paper  $paper,
        Episciences_Review $journal,
        array              $data,
        array              $options = [],
        ?LoggerInterface   $logger = null
    ): bool {
        $logDetails = $options['logDetails'] ?? [];

        $nOptions = array_filter([
            'canBeReplaced' => $options['canBeReplaced'] ?? null,
            'oldStatus'     => $options['logDetails']['oldStatus'] ?? null,
            'oldDocId'      => $options['logDetails']['oldDocId'] ?? null,
        ], static fn($v): bool => $v !== null);

        try {
            if ($paper->save()) {
                if ($logger !== null) {
                    $logger->info(sprintf('Article (identifier = %s, docId = %s) successfully persisted.', $data['identifier'], $paper->getDocid()));
                }

                $this->logAction($paper, $logDetails, self::FIRST_SUBMISSION, $logger);
                $this->notifyAuthorAndEditorialCommittee($journal, $paper, $nOptions, $logger);
                $this->enrichment($paper, $data, $logger);

                return true;
            }
        } catch (\Throwable $e) {
            if ($logger !== null) {
                $logger->critical(sprintf('Paper::save() exception for %s: %s', $data['identifier'], $e->getMessage()), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // New version processing
    // -------------------------------------------------------------------------

    private function saveNewVersion(
        Episciences_Paper  $context,
        array              $newPaperData,
        Episciences_Review $journal,
        array              $logDetails = [],
        ?LoggerInterface   $logger = null
    ): bool {
        $context->loadOtherVolumes();
        $journal->loadSettings();

        $isCopyEditingProcessStarted = $context->isCopyEditingProcessStarted();
        $comments = Episciences_CommentsManager::getRevisionRequests(
            $context->getDocid(),
            [Episciences_CommentsManager::TYPE_REVISION_REQUEST]
        );

        $commentKey        = !empty($comments) ? array_key_first($comments) : null;
        $comment           = $commentKey !== null ? new Episciences_Comment($comments[$commentKey]) : null;
        $reassignReviewers = $comment ? $comment->getOption('reassign_reviewers') : false;
        $isAlreadyAccepted = $comment ? $comment->getOption('isAlreadyAccepted') : false;

        $paperId     = $context->getPaperid() ?: $context->getDocid();
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

        $isAssignedReviewers = $reassignReviewers && !empty($reviewers);
        $status = $this->resolveNewVersionStatus(
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

        $this->logAction($newPaper, array_merge($logDetails, $newPaperStatusDetails), self::NEW_VERSION, $logger);

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

        $this->unassignPaperMembers($context, $journal, $reviewers, $editors, $copyEditors, $isCopyEditingProcessStarted, $logger);

        if ($reviewers && $reassignReviewers && $comment) {
            $sender = new Episciences_Editor();
            $sender->findWithCAS($comment->getUid());
            $this->reinviteReviewers($reviewers, $context, $newPaper, $sender, $journal, $logger);
        }

        if ($editors) {
            $this->reassignPaperManagers($editors, $newPaper, Episciences_User_Assignment::ROLE_EDITOR, $logger);
        }

        if ($copyEditors) {
            $this->reassignPaperManagers($copyEditors, $newPaper, Episciences_User_Assignment::ROLE_COPY_EDITOR, $logger);
        }

        if (!empty($coAuthors)) {
            Episciences_User_AssignmentsManager::reassignPaperCoAuthors($coAuthors, $newPaper);
        }

        $this->notifyAuthorAndEditorialCommittee($journal, $newPaper, ['originalRequest' => $comment], $logger);
        $this->enrichment($newPaper, $newPaperData, $logger);

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

        return $isAssignedReviewers ? Episciences_Paper::STATUS_OK_FOR_REVIEWING : Episciences_Paper::STATUS_SUBMITTED;
    }

    private function unassignPaperMembers(
        Episciences_Paper  $context,
        Episciences_Review $journal,
        array              $reviewers,
        array              $editors,
        array              $copyEditors,
        bool               $isCopyEditingProcessStarted,
        ?LoggerInterface   $logger
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
        Episciences_Review $journal,
        ?LoggerInterface   $logger
    ): void {
        $templateKey    = Episciences_Mail_TemplatesManager::TYPE_PAPER_NEW_VERSION_REVIEWER_REINVITATION;
        $serverProtocol = defined('SERVER_PROTOCOL') ? SERVER_PROTOCOL : 'https';
        $domain         = defined('DOMAIN') ? DOMAIN : 'episciences.org';

        $contextUrl = sprintf(
            "%s://%s.%s/paper/view?id=%s",
            $serverProtocol,
            $journal->getCode(),
            $domain,
            $paper->getDocid()
        );

        $deadline = Episciences_Tools::addDateInterval(
            date('Y-m-d'),
            (int) $journal->getSetting(Episciences_Review::SETTING_RATING_DEADLINE)
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
            try {
                $assignmentResult = $reviewer->assign($paper->getDocid(), $params);
                if (empty($assignmentResult)) {
                    continue;
                }
                /** @var Episciences_User_Assignment $oAssignment */
                $oAssignment = $assignmentResult[0];

                $oInvitation = new Episciences_User_Invitation([
                    'aid'        => $oAssignment->getId(),
                    'sender_uid' => EPISCIENCES_UID,
                ]);

                if ($oInvitation->save()) {
                    $oInvitation = Episciences_User_InvitationsManager::findById($oInvitation->getId());
                }

                $invitationUrl = sprintf(
                    "%s://%s.%s/reviewer/invitation?id=%s",
                    $serverProtocol,
                    $journal->getCode(),
                    $domain,
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
                    Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE => Episciences_View_Helper_Date::Date($context->getSubmission_date(), $locale),
                    Episciences_Mail_Tags::TAG_PAPER_URL             => $contextUrl,
                    Episciences_Mail_Tags::TAG_INVITATION_URL        => $invitationUrl,
                    Episciences_Mail_Tags::TAG_INVITATION_DEADLINE   => Episciences_View_Helper_Date::Date($oInvitation->getExpiration_date(), $locale),
                    Episciences_Mail_Tags::TAG_RATING_DEADLINE       => Episciences_View_Helper_Date::Date($oAssignment->getDeadline(), $locale),
                ];

                Episciences_Mail_Send::sendMailFromReview($reviewer, $templateKey, $tags, $paper, null, [], false, [], $journalOptions);
            } catch (\Throwable $e) {
                if ($logger !== null) {
                    $logger->critical(sprintf('Failed reinviting reviewer (UID %d): %s', $reviewer->getUid(), $e->getMessage()), [
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }
    }

    private function reassignPaperManagers(
        array             $paperManagers,
        Episciences_Paper $paper,
        string            $roleId = Episciences_User_Assignment::ROLE_EDITOR,
        ?LoggerInterface  $logger = null
    ): void {
        $action = $roleId === Episciences_User_Assignment::ROLE_COPY_EDITOR
            ? Episciences_Paper_Logger::CODE_COPY_EDITOR_ASSIGNMENT
            : Episciences_Paper_Logger::CODE_EDITOR_ASSIGNMENT;

        foreach ($paperManagers as $manager) {
            try {
                $aid = $paper->assign($manager->getUid(), $roleId, Episciences_User_Assignment::STATUS_ACTIVE);
            } catch (\Throwable $e) {
                if ($logger !== null) {
                    $logger->critical(sprintf('Failed assigning manager role (%s) to UID %d: %s', $roleId, $manager->getUid(), $e->getMessage()));
                }
                $aid = 0;
            }

            try {
                $paper->log($action, null, ['aid' => $aid, 'user' => $manager->toArray()]);
            } catch (\Throwable $e) {
                if ($logger !== null) {
                    $logger->critical(sprintf('Failed logging manager assignment to paper history: %s', $e->getMessage()));
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Enrichment & repository helpers
    // -------------------------------------------------------------------------

    private function enrichment(Episciences_Paper $paper, array $additionalPaperData = [], ?LoggerInterface $logger = null): void
    {
        $enrichment = $additionalPaperData[Episciences_Repositories_Common::ENRICHMENT] ?? [];

        try {
            if (Episciences_Repositories::getApiUrl($paper->getRepoid())) {
                Episciences_Submit::datasetsProcessing($paper);
            }

            if (!isset($enrichment[Episciences_Repositories_Common::CONTRIB_ENRICHMENT])) {
                Episciences_Paper_AuthorsManager::InsertAuthorsFromPapers($paper);
            }

            Episciences_Submit::enrichmentProcess($paper, $enrichment);

            if (Episciences_Repositories::isFromHalRepository($paper->getRepoid())) {
                Episciences_Paper_AuthorsManager::enrichAffiOrcidFromTeiHalInDB(
                    $paper->getRepoid(),
                    $paper->getPaperid(),
                    $paper->getIdentifier(),
                    (int) $paper->getVersion()
                );
            }
        } catch (\Throwable $e) {
            if ($logger !== null) {
                $logger->critical(sprintf('Enrichment error for paper %s: %s', $paper->getIdentifier(), $e->getMessage()), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    private function bootstrapConstants(): void
    {
        if (!defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', realpath(__DIR__ . '/../application'));
        }
        require_once __DIR__ . '/../public/const.php';
        require_once __DIR__ . '/../public/bdd_const.php';

        defineProtocol();
        defineSimpleConstants();
        defineSQLTableConstants();
        defineApplicationConstants();
        defineJournalConstants();

        // Include path and fallback autoloader must be set up before any Episciences_*
        // or namespaced Episciences\* class (e.g. AppRegistry) can be autoloaded.
        $libraries = [realpath(APPLICATION_PATH . '/../library')];
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, [get_include_path()])));
        require_once 'Zend/Application.php';

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);
    }

    private function bootstrap(): void
    {
        // Constants, include path and the fallback autoloader are already set up by
        // bootstrapConstants(), called earlier in execute() to build the logger.

        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);

        Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));
        Zend_Registry::set('languages', ['fr', Episciences_Review::DEFAULT_LANG]);
        Zend_Registry::set('Zend_Locale', new Zend_Locale(Episciences_Review::DEFAULT_LANG));

        try {
            $translator = new Zend_Translate(
                Zend_Translate::AN_ARRAY,
                APPLICATION_PATH . '/languages',
                Episciences_Review::DEFAULT_LANG,
                ['scan' => Zend_Translate::LOCALE_DIRECTORY]
            );
            Zend_Registry::set('Zend_Translate', $translator);
        } catch (\Throwable $e) {
            // Keep silent if already loaded or fallback
        }
    }
}
