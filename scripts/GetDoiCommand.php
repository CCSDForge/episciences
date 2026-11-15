<?php
declare(strict_types=1);

use Episciences\Api\CrossrefDiagnosticParser;
use Episciences\Api\CrossrefSubmissionApiClient;
use Episciences\Console\ProgressAwareStreamHandler;
use Episciences\Volume\CrossrefVolumeExporter;
use Episciences\Volume\DoiQueueManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: manage DOI assignment, submission and status checking via Crossref.
 *
 * Replaces: scripts/getDoi.php (JournalScript)
 */
#[AsCommand(name: 'doi:manage', description: 'Manage DOI lifecycle: assign, submit to Crossref, check submission status, update metadata (papers, or proceedings volumes with --volumes)')]
class GetDoiCommand extends Command
{
    private const MAX_WITHOUT_CONFIRM = 20;

    protected function configure(): void
    {
        $this
            ->addOption('rvcode',           null, InputOption::VALUE_REQUIRED, 'Journal RV code')
            ->addOption('rvid',             null, InputOption::VALUE_REQUIRED, 'Journal RVID (integer)')
            ->addOption('paperid',          null, InputOption::VALUE_REQUIRED, 'Restrict --update to a single paper ID')
            ->addOption('assign-accepted',  null, InputOption::VALUE_NONE,     'Assign DOIs to accepted papers')
            ->addOption('assign-published', null, InputOption::VALUE_NONE,     'Assign DOIs to published papers')
            ->addOption('request',          null, InputOption::VALUE_NONE,     'Submit assigned DOIs to Crossref deposit API')
            ->addOption('check',            null, InputOption::VALUE_NONE,     'Check Crossref submission status')
            ->addOption('update',           null, InputOption::VALUE_NONE,     'Re-send metadata to Crossref for already-registered DOIs (free update)')
            ->addOption('fetch-journals',   null, InputOption::VALUE_NONE,     'Fetch active journals list from the API')
            ->addOption('volumes',          null, InputOption::VALUE_NONE,     'With --request or --check: handle the DOIs of proceedings volumes instead of papers')
            ->addOption('dry-run',          null, InputOption::VALUE_NONE,     'Simulate without writing: no DOI assigned/saved for --assign-*, Crossref test API used for --request/--check/--update');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('DOI management');

        $optionError = $this->validateVolumesOption($input);
        if ($optionError !== null) {
            $io->error($optionError);
            return Command::FAILURE;
        }

        $this->bootstrap();

        $logger = new Logger('getDoi');
        $logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'getDoi_' . date('Y-m-d') . '.log', Level::Info));

        // Handed to each action below so it can wire the active ProgressBar into the
        // handler — see ProgressAwareStreamHandler: a plain stdout handler otherwise lands
        // log lines mid-bar-redraw, since the bar overwrites its line with a bare carriage
        // return, not a fresh newline.
        $stdoutHandler = null;
        if (!$io->isQuiet() && !$io->isSilent()) {
            $stdoutHandler = new ProgressAwareStreamHandler('php://stdout', Level::Info);
            $logger->pushHandler($stdoutHandler);
        }

        if ($dryRun) {
            $io->note('Dry-run mode — no DOI will be assigned/saved; Crossref calls (if any) use the test API.');
        }

        $http = new Client();

        if ($input->getOption('fetch-journals')) {
            return $this->fetchJournals($io, $http, $logger);
        }

        $rvcode = $input->getOption('rvcode');
        $rvid   = $input->getOption('rvid');

        if ($rvcode === null && $rvid === null) {
            $io->error('Provide --rvcode or --rvid (or use --fetch-journals).');
            return Command::FAILURE;
        }

        $journalIdentifier = $rvcode ?? $rvid;
        $review            = Episciences_ReviewsManager::find($journalIdentifier);

        if (!$review instanceof Episciences_Review) {
            $io->error("No journal found for identifier '{$journalIdentifier}'.");
            return Command::FAILURE;
        }

        $review->loadSettings();
        $doiSettings = $review->getDoiSettings();

        // Episciences_PapersManager::getList() falls back to the global RVID constant
        // whenever its 'is' filter doesn't carry an (uppercase) 'RVID' key — which is
        // exactly the shape assignDois() below passes. Without this, that fallback hits
        // an undefined constant and fatals with no console-visible error message.
        if (!defined('RVID')) {
            define('RVID', $review->getRvid());
        }

        $this->setJournalConstants($review->getCode(), $io);

        $crossrefClient = new CrossrefSubmissionApiClient(
            $http,
            $logger,
            (string) DOI_API,
            (string) DOI_TESTAPI,
            (string) DOI_API_QUERY,
            (string) DOI_TESTAPI_QUERY,
            (string) DOI_LOGIN,
            (string) DOI_PASSWORD,
        );

        if ($input->getOption('volumes')) {
            return $input->getOption('request')
                ? $this->requestVolumeDois($io, $review, $dryRun, $crossrefClient, $logger, $stdoutHandler)
                : $this->checkVolumeDois($io, $review, $dryRun, $crossrefClient, $logger, $stdoutHandler);
        }

        if ($input->getOption('assign-accepted')) {
            return $this->assignDois(Episciences_Paper::STATUS_ACCEPTED, $io, $review, $doiSettings, $logger, $dryRun, $stdoutHandler);
        }

        if ($input->getOption('assign-published')) {
            return $this->assignDois(Episciences_Paper::STATUS_PUBLISHED, $io, $review, $doiSettings, $logger, $dryRun, $stdoutHandler);
        }

        if ($input->getOption('request')) {
            return $this->requestDois($io, $review, $dryRun, $http, $crossrefClient, $logger, $stdoutHandler);
        }

        if ($input->getOption('check')) {
            return $this->checkDois($io, $review, $dryRun, $crossrefClient, $logger, $stdoutHandler);
        }

        if ($input->getOption('update')) {
            $paperId = $input->getOption('paperid') !== null ? (int) $input->getOption('paperid') : null;
            return $this->updateDois($io, $review, $dryRun, $http, $crossrefClient, $logger, $paperId, $stdoutHandler);
        }

        $io->warning('No action specified. Use --assign-accepted, --assign-published, --request, --check, --update, or --fetch-journals.');
        return Command::FAILURE;
    }

    /**
     * --volumes only goes with exactly one of --request / --check.
     *
     * @return string|null the error to display, null when the options are consistent
     */
    private function validateVolumesOption(InputInterface $input): ?string
    {
        if (!$input->getOption('volumes')) {
            return null;
        }

        foreach (['assign-accepted', 'assign-published', 'update', 'fetch-journals', 'paperid'] as $option) {
            if ($input->getOption($option)) {
                return sprintf('--volumes cannot be combined with --%s.', $option);
            }
        }

        if ((bool)$input->getOption('request') === (bool)$input->getOption('check')) {
            return '--volumes requires either --request or --check.';
        }

        return null;
    }

    private function fetchJournals(SymfonyStyle $io, Client $http, Logger $logger): int
    {
        $url = EPISCIENCES_API_URL . 'journals/?page=1&itemsPerPage=30&pagination=false';

        try {
            $body       = $http->request('GET', $url, ['headers' => ['accept' => 'application/ld+json']])->getBody()->getContents();
            $targetFile = CACHE_PATH_METADATA . 'journals.json';
            file_put_contents($targetFile, $body);
            $logger->info("Journals list saved to {$targetFile}");
            $io->success("Journals list saved to {$targetFile}");
            return Command::SUCCESS;
        } catch (GuzzleException $e) {
            $logger->error('API request failed: ' . $e->getMessage());
            $io->error('API request failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function setJournalConstants(string $rvcode, SymfonyStyle $io): void
    {
        if (!defined('RVCODE')) {
            define('RVCODE', $rvcode);
        }

        $dataPath = APPLICATION_PATH . '/../data/' . $rvcode;
        $realPath = realpath($dataPath);
        if (!defined('REVIEW_PATH')) {
            define('REVIEW_PATH', ($realPath !== false ? $realPath : $dataPath) . '/');
        }
        if (!defined('REVIEW_LANG_PATH')) {
            define('REVIEW_LANG_PATH', REVIEW_PATH . 'languages/');
        }
        if (!defined('CACHE_PATH')) {
            define('CACHE_PATH', CACHE_PATH_METADATA . $rvcode . '/');
        }

        if (!is_dir(CACHE_PATH) && !mkdir(CACHE_PATH, 0755, true) && !is_dir(CACHE_PATH)) {
            $io->warning(sprintf('Could not create cache directory: %s', CACHE_PATH));
        }
    }

    /**
     * @throws \Zend_Db_Select_Exception
     * @throws \Zend_Exception
     */
    private function assignDois(
        int                            $paperStatus,
        SymfonyStyle                   $io,
        Episciences_Review             $review,
        Episciences_Review_DoiSettings $doiSettings,
        Logger                         $logger,
        bool                           $dryRun = false,
        ?ProgressAwareStreamHandler    $stdoutHandler = null
    ): int {
        $rvid   = $review->getRvid();
        $rvcode = $review->getCode();

        $papers = Episciences_PapersManager::getList(['is' => ['rvid' => $rvid, 'status' => $paperStatus]]);
        $total  = count($papers);
        $logger->info(sprintf('%s: %d papers with status %s', $rvcode, $total, $paperStatus));

        $progressBar = $io->createProgressBar($total);
        $stdoutHandler?->setProgressBar($progressBar);
        $progressBar->start();
        $assigned = 0;

        foreach ($papers as $paper) {
            /** @var Episciences_Paper $paper */
            if (!empty($paper->getDoi()) || $rvid !== $paper->getRvid()) {
                $progressBar->advance();
                continue;
            }

            $doi = $doiSettings->createDoiWithTemplate($paper, $rvcode);

            if ($dryRun) {
                $assigned++;
                $logger->info(sprintf('[dry-run] Would assign %s to paper #%d (%d/%d)', $doi, $paper->getPaperId(), $assigned, $total));
                $progressBar->advance();
                continue;
            }

            $paper->setDoi($doi);
            $paper->save();

            $doiQueue = new Episciences_Paper_DoiQueue([
                'paperid'    => $paper->getPaperId(),
                'doi_status' => Episciences_Paper_DoiQueue::STATUS_ASSIGNED,
            ]);

            try {
                Episciences_Paper_DoiQueueManager::add($doiQueue);
                $assigned++;
                $logger->info(sprintf('Assigned %s to paper #%d (%d/%d)', $doi, $paper->getPaperId(), $assigned, $total));
            } catch (\Exception $e) {
                if ((int) $e->getCode() !== 23000) {
                    $logger->error($e->getMessage());
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $stdoutHandler?->setProgressBar(null);
        $io->newLine();
        $io->success(sprintf('%s %d DOI(s) for journal %s.', $dryRun ? 'Would assign' : 'Assigned', $assigned, $rvcode));
        return Command::SUCCESS;
    }

    private function requestDois(
        SymfonyStyle                $io,
        Episciences_Review          $review,
        bool                        $dryRun,
        Client                      $http,
        CrossrefSubmissionApiClient $crossrefClient,
        Logger                      $logger,
        ?ProgressAwareStreamHandler $stdoutHandler = null
    ): int {
        $rvid   = $review->getRvid();
        $rvcode = $review->getCode();

        $res   = Episciences_Paper_DoiQueueManager::findDoisByStatus($rvid, Episciences_Paper::STATUS_PUBLISHED, Episciences_Paper_DoiQueue::STATUS_ASSIGNED);
        $total = count($res);

        if ($total === 0) {
            $logger->info("{$rvcode}: task list empty.");
            $io->info('No DOIs to submit.');
            return Command::SUCCESS;
        }

        if ($total > self::MAX_WITHOUT_CONFIRM) {
            $apiLabel = $dryRun ? 'test' : 'production';
            if (!$io->confirm(sprintf('Submit %d DOIs to %s API?', $total, $apiLabel), false)) {
                $io->note('Cancelled.');
                return Command::FAILURE;
            }
        }

        $logger->info(sprintf('%s: sending %d papers to Crossref', $rvcode, $total));
        $progressBar = $io->createProgressBar($total);
        $stdoutHandler?->setProgressBar($progressBar);
        $progressBar->start();

        foreach ($res as $doiToProcess) {
            /** @var Episciences_Paper $paper */
            $paper = $doiToProcess['paper'];
            /** @var Episciences_Paper_DoiQueue $doiQueue */
            $doiQueue = $doiToProcess['doiq'];

            $paperId = $paper->getPaperId();
            $logger->info(sprintf('%s: processing paper #%d', $rvcode, $paperId));

            $docId = Episciences_PapersManager::getPublishedPaperId($paperId);
            if ($docId === 0) {
                $logger->info("Paper #{$paperId} is not published yet, skipping.");
                $progressBar->advance();
                continue;
            }

            $xmlFileName = sprintf('%s-%d.xml', $rvcode, $paperId);
            $paperUrl    = sprintf('%spapers/export/%d/crossref?code=%s', EPISCIENCES_API_URL, $docId, $rvcode);

            $logger->info("Requesting metadata: {$paperUrl}");

            try {
                $body = $http->request('GET', $paperUrl)->getBody()->getContents();
            } catch (GuzzleException $e) {
                $logger->error("Metadata fetch failed for paper #{$paperId}: " . $e->getMessage());
                $progressBar->advance();
                continue;
            }

            try {
                $response = $crossrefClient->postMetadata($body, $xmlFileName, $dryRun);
                if (!$dryRun) {
                    $doiQueue->setDoi_status(Episciences_Paper_DoiQueue::STATUS_REQUESTED);
                    Episciences_Paper_DoiQueueManager::update($doiQueue);
                }
                $logger->info(sprintf('%s%s: Crossref answered: %s', $dryRun ? '[dry-run] ' : '', $rvcode, $response->getBody()));
            } catch (GuzzleException $e) {
                $logger->error("Crossref submission failed for paper #{$paperId}: " . $e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $stdoutHandler?->setProgressBar(null);
        $io->newLine();
        $io->success("Submission completed for journal {$rvcode}.");
        return Command::SUCCESS;
    }

    private function checkDois(
        SymfonyStyle                $io,
        Episciences_Review          $review,
        bool                        $dryRun,
        CrossrefSubmissionApiClient $crossrefClient,
        Logger                      $logger,
        ?ProgressAwareStreamHandler $stdoutHandler = null
    ): int {
        $rvid   = $review->getRvid();
        $rvcode = $review->getCode();

        $collection = array_merge(
            Episciences_Paper_DoiQueueManager::findDoisByStatus($rvid, Episciences_Paper::STATUS_PUBLISHED, Episciences_Paper_DoiQueue::STATUS_REQUESTED),
            Episciences_Paper_DoiQueueManager::findDoisByStatus($rvid, Episciences_Paper::STATUS_PUBLISHED, Episciences_Paper_DoiQueue::STATUS_UPDATE_PENDING)
        );

        $parser = new CrossrefDiagnosticParser();
        $progressBar = $io->createProgressBar(count($collection));
        $stdoutHandler?->setProgressBar($progressBar);
        $progressBar->start();

        foreach ($collection as $doiData) {
            /** @var Episciences_Paper $paper */
            $paper = $doiData['paper'];
            /** @var Episciences_Paper_DoiQueue $doiQueue */
            $doiQueue = $doiData['doiq'];

            $paperId     = $paper->getPaperId();
            $articleDoi  = $paper->getDoi();
            $xmlFileName = sprintf('%s-%d.xml', $rvcode, $paperId);

            $xmlBody = $crossrefClient->fetchStatus($xmlFileName, $dryRun);

            if ($xmlBody === null) {
                $logger->error("Failed to fetch DOI status for paper #{$paperId}.");
                $progressBar->advance();
                continue;
            }

            $result = $parser->parse($xmlBody, $articleDoi);

            if ($result === null) {
                $logger->error("Failed to parse Crossref XML for paper #{$paperId}.");
                $progressBar->advance();
                continue;
            }

            $logger->info(sprintf(
                'Paper #%d Crossref batch [%s]: %d success / %d failure / %d warning',
                $paperId, $result->batchStatus, $result->batchSuccess, $result->batchFailure, $result->batchWarning
            ));

            if (!$result->isCompleted()) {
                $logger->info("Paper #{$paperId}: batch not yet processed by Crossref, will retry on next --check.");
                $progressBar->advance();
                continue;
            }

            if (!$result->doiFound) {
                $logger->warning(sprintf('Paper #%d: DOI %s not found individually in Crossref response — falling back to batch count.', $paperId, $articleDoi));
            } else {
                $logger->info(sprintf('Paper #%d DOI %s: %s — %s', $paperId, $articleDoi, $result->doiStatus, $result->doiMsg));
            }

            if ($result->isSuccess() && $doiQueue->getDoi_status() !== Episciences_Paper_DoiQueue::STATUS_PUBLIC) {
                if ($dryRun) {
                    $logger->info("[dry-run] Paper #{$paperId}: DOI status would become public.");
                } else {
                    $doiQueue->setDoi_status(Episciences_Paper_DoiQueue::STATUS_PUBLIC);
                    Episciences_Paper_DoiQueueManager::update($doiQueue);
                    $logger->info("Paper #{$paperId}: DOI status is now public.");
                }
            } elseif ($result->doiFound && !$result->isSuccess()) {
                $logger->warning(sprintf('Paper #%d: DOI not confirmed as successful (status: %s).', $paperId, $result->doiStatus));
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $stdoutHandler?->setProgressBar(null);
        $io->newLine();
        $io->success("DOI status check completed for journal {$rvcode}.");
        return Command::SUCCESS;
    }

    /**
     * Deposit the DOIs of proceedings volumes (status assigned) to Crossref.
     * A volume DOI does not depend on its papers: only a volume that cannot be exported is skipped.
     */
    private function requestVolumeDois(
        SymfonyStyle                $io,
        Episciences_Review          $review,
        bool                        $dryRun,
        CrossrefSubmissionApiClient $crossrefClient,
        Logger                      $logger,
        ?ProgressAwareStreamHandler $stdoutHandler = null
    ): int {
        $rvcode = $review->getCode();
        $queue  = $this->findVolumeDois($review->getRvid(), Episciences_Volume_DoiQueue::STATUS_ASSIGNED);
        $total  = count($queue);

        if ($total === 0) {
            $logger->info("{$rvcode}: no volume DOI to submit.");
            $io->info('No volume DOIs to submit.');
            return Command::SUCCESS;
        }

        if ($total > self::MAX_WITHOUT_CONFIRM
            && !$io->confirm(sprintf('Submit %d volume DOIs to %s API?', $total, $dryRun ? 'test' : 'production'), false)) {
            $io->note('Cancelled.');
            return Command::FAILURE;
        }

        $logger->info(sprintf('%s: sending %d volume(s) to Crossref', $rvcode, $total));
        $exporter    = $this->createVolumeExporter();
        $progressBar = $io->createProgressBar($total);
        $stdoutHandler?->setProgressBar($progressBar);
        $progressBar->start();

        foreach ($queue as $entry) {
            $this->requestVolumeDoi($entry['volume'], $entry['doiq'], $review, $exporter, $crossrefClient, $logger, $dryRun);
            $progressBar->advance();
        }

        $progressBar->finish();
        $stdoutHandler?->setProgressBar(null);
        $io->newLine();
        $io->success("Volume DOI submission completed for journal {$rvcode}.");
        return Command::SUCCESS;
    }

    private function requestVolumeDoi(
        Episciences_Volume          $volume,
        Episciences_Volume_DoiQueue $doiQueue,
        Episciences_Review          $review,
        CrossrefVolumeExporter      $exporter,
        CrossrefSubmissionApiClient $crossrefClient,
        Logger                      $logger,
        bool                        $dryRun
    ): void {
        $vid    = (int) $volume->getVid();
        $prefix = $dryRun ? '[dry-run] ' : '';

        try {
            $xml = $exporter->export($volume, $review, true);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $logger->warning(sprintf('Volume #%d skipped: %s', $vid, $e->getMessage()));
            return;
        }

        try {
            $response = $crossrefClient->postMetadata($xml, self::getVolumeXmlFileName($review->getCode(), $vid), $dryRun);
        } catch (GuzzleException $e) {
            $logger->error(sprintf('Crossref submission failed for volume #%d: %s', $vid, $e->getMessage()));
            return;
        }

        if (!$dryRun) {
            $doiQueue->setDoi_status(Episciences_Volume_DoiQueue::STATUS_REQUESTED);
            $this->saveVolumeDoiQueue($doiQueue);
        }

        $logger->info(sprintf('%sVolume #%d: Crossref answered: %s', $prefix, $vid, $response->getBody()));
    }

    /**
     * Check the Crossref processing of the deposited volume DOIs (status requested).
     */
    private function checkVolumeDois(
        SymfonyStyle                $io,
        Episciences_Review          $review,
        bool                        $dryRun,
        CrossrefSubmissionApiClient $crossrefClient,
        Logger                      $logger,
        ?ProgressAwareStreamHandler $stdoutHandler = null
    ): int {
        $rvcode      = $review->getCode();
        $queue       = $this->findVolumeDois($review->getRvid(), Episciences_Volume_DoiQueue::STATUS_REQUESTED);
        $parser      = new CrossrefDiagnosticParser();
        $progressBar = $io->createProgressBar(count($queue));
        $stdoutHandler?->setProgressBar($progressBar);
        $progressBar->start();

        foreach ($queue as $entry) {
            $this->checkVolumeDoi($entry['volume'], $entry['doiq'], $rvcode, $parser, $crossrefClient, $logger, $dryRun);
            $progressBar->advance();
        }

        $progressBar->finish();
        $stdoutHandler?->setProgressBar(null);
        $io->newLine();
        $io->success("Volume DOI status check completed for journal {$rvcode}.");
        return Command::SUCCESS;
    }

    private function checkVolumeDoi(
        Episciences_Volume          $volume,
        Episciences_Volume_DoiQueue $doiQueue,
        string                      $rvcode,
        CrossrefDiagnosticParser    $parser,
        CrossrefSubmissionApiClient $crossrefClient,
        Logger                      $logger,
        bool                        $dryRun
    ): void {
        $vid     = (int) $volume->getVid();
        $doi     = (string) $volume->getSetting(Episciences_Volume::VOLUME_CONFERENCE_DOI);
        $xmlBody = $crossrefClient->fetchStatus(self::getVolumeXmlFileName($rvcode, $vid), $dryRun);
        $result  = $xmlBody === null ? null : $parser->parse($xmlBody, $doi);

        if ($result === null) {
            $logger->error(sprintf('Volume #%d: Crossref status could not be fetched or parsed.', $vid));
            return;
        }

        if (!$result->isCompleted()) {
            $logger->info(sprintf('Volume #%d: batch not yet processed by Crossref, will retry on next --check.', $vid));
            return;
        }

        if (!$result->isSuccess()) {
            $logger->warning(sprintf('Volume #%d: DOI %s not confirmed as successful (status: %s, %s).', $vid, $doi, $result->doiStatus, $result->doiMsg));
            return;
        }

        if ($dryRun) {
            $logger->info(sprintf('[dry-run] Volume #%d: DOI %s status would become public.', $vid, $doi));
            return;
        }

        $doiQueue->setDoi_status(Episciences_Volume_DoiQueue::STATUS_PUBLIC);
        $this->saveVolumeDoiQueue($doiQueue);
        $logger->info(sprintf('Volume #%d: DOI %s is now public.', $vid, $doi));
    }

    /**
     * Name of the deposited file: --check queries Crossref by file name, so it must differ
     * from the paper files ({rvcode}-{paperid}.xml).
     */
    private static function getVolumeXmlFileName(string $rvcode, int $vid): string
    {
        return sprintf('%s-volume-%d.xml', $rvcode, $vid);
    }

    /**
     * @return array<int, array{volume: Episciences_Volume, doiq: Episciences_Volume_DoiQueue}>
     */
    protected function findVolumeDois(int $rvid, string $status): array
    {
        return DoiQueueManager::findByStatus($rvid, $status);
    }

    protected function saveVolumeDoiQueue(Episciences_Volume_DoiQueue $doiQueue): void
    {
        DoiQueueManager::update($doiQueue);
    }

    protected function createVolumeExporter(): CrossrefVolumeExporter
    {
        return new CrossrefVolumeExporter();
    }

    /**
     * Re-send metadata XML to Crossref for already-registered DOIs.
     *
     * Crossref treats a POST for an existing DOI as a free metadata update.
     * doi_status transitions from STATUS_PUBLIC to STATUS_UPDATE_PENDING so
     * --check can verify the update was received before marking it public again.
     */
    private function updateDois(
        SymfonyStyle                $io,
        Episciences_Review          $review,
        bool                        $dryRun,
        Client                      $http,
        CrossrefSubmissionApiClient $crossrefClient,
        Logger                      $logger,
        ?int                        $paperId,
        ?ProgressAwareStreamHandler $stdoutHandler = null
    ): int {
        $rvid   = $review->getRvid();
        $rvcode = $review->getCode();

        $res = Episciences_Paper_DoiQueueManager::findDoisByStatus(
            $rvid,
            Episciences_Paper::STATUS_PUBLISHED,
            Episciences_Paper_DoiQueue::STATUS_PUBLIC
        );

        if ($paperId !== null) {
            $res = array_filter($res, static fn(array $entry): bool => $entry['paper']->getPaperId() === $paperId);
            $res = array_values($res);
        }

        $total = count($res);

        if ($total === 0) {
            $logger->info("{$rvcode}: no registered DOIs to update.");
            $io->info('No registered DOIs found to update.');
            return Command::SUCCESS;
        }

        if ($total > self::MAX_WITHOUT_CONFIRM) {
            $apiLabel = $dryRun ? 'test' : 'production';
            if (!$io->confirm(sprintf('Update %d DOIs on %s API?', $total, $apiLabel), false)) {
                $io->note('Cancelled.');
                return Command::FAILURE;
            }
        }

        $logger->info(sprintf('%s: updating %d DOI(s) on Crossref', $rvcode, $total));
        $progressBar = $io->createProgressBar($total);
        $stdoutHandler?->setProgressBar($progressBar);
        $progressBar->start();

        foreach ($res as $doiToProcess) {
            /** @var Episciences_Paper $paper */
            $paper = $doiToProcess['paper'];

            $currentPaperId = $paper->getPaperId();
            $logger->info(sprintf('%s: updating paper #%d', $rvcode, $currentPaperId));

            $docId = Episciences_PapersManager::getPublishedPaperId($currentPaperId);
            if ($docId === 0) {
                $logger->info("Paper #{$currentPaperId} has no published version, skipping.");
                $progressBar->advance();
                continue;
            }

            $xmlFileName = sprintf('%s-%d.xml', $rvcode, $currentPaperId);
            $paperUrl    = sprintf('%spapers/export/%d/crossref?code=%s', EPISCIENCES_API_URL, $docId, $rvcode);

            $logger->info("Fetching metadata: {$paperUrl}");

            try {
                $body = $http->request('GET', $paperUrl)->getBody()->getContents();
            } catch (GuzzleException $e) {
                $logger->error("Metadata fetch failed for paper #{$currentPaperId}: " . $e->getMessage());
                $progressBar->advance();
                continue;
            }

            try {
                $response = $crossrefClient->postMetadata($body, $xmlFileName, $dryRun);
                if (!$dryRun) {
                    /** @var Episciences_Paper_DoiQueue $doiQueue */
                    $doiQueue = $doiToProcess['doiq'];
                    $doiQueue->setDoi_status(Episciences_Paper_DoiQueue::STATUS_UPDATE_PENDING);
                    Episciences_Paper_DoiQueueManager::update($doiQueue);
                }
                $logger->info(sprintf('%s%s: Crossref answered: %s', $dryRun ? '[dry-run] ' : '', $rvcode, $response->getBody()));
            } catch (GuzzleException $e) {
                $logger->error("Crossref update failed for paper #{$currentPaperId}: " . $e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $stdoutHandler?->setProgressBar(null);
        $io->newLine();
        $io->success(sprintf('DOI metadata update submitted for journal %s. Use --check to confirm.', $rvcode));
        return Command::SUCCESS;
    }

    private function bootstrap(): void
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

        $libraries = [realpath(APPLICATION_PATH . '/../library')];
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, [get_include_path()])));
        require_once 'Zend/Application.php';

        // Do NOT call $application->bootstrap() — APPLICATION_MODULE may be undefined
        // (no rvcode) which causes Bootstrap::_initModule() to fail silently.
        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);

        Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
    }
}
