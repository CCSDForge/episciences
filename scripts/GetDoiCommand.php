<?php
declare(strict_types=1);

use Episciences\Api\CrossrefSubmissionApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
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
class GetDoiCommand extends Command
{
    protected static $defaultName = 'doi:manage';

    private const MAX_WITHOUT_CONFIRM = 20;

    protected function configure(): void
    {
        $this
            ->setDescription('Manage DOI lifecycle: assign, submit to Crossref, check submission status, update metadata')
            ->addOption('rvcode',           null, InputOption::VALUE_REQUIRED, 'Journal RV code')
            ->addOption('rvid',             null, InputOption::VALUE_REQUIRED, 'Journal RVID (integer)')
            ->addOption('paperid',          null, InputOption::VALUE_REQUIRED, 'Restrict --update to a single paper ID')
            ->addOption('assign-accepted',  null, InputOption::VALUE_NONE,     'Assign DOIs to accepted papers')
            ->addOption('assign-published', null, InputOption::VALUE_NONE,     'Assign DOIs to published papers')
            ->addOption('request',          null, InputOption::VALUE_NONE,     'Submit assigned DOIs to Crossref deposit API')
            ->addOption('check',            null, InputOption::VALUE_NONE,     'Check Crossref submission status')
            ->addOption('update',           null, InputOption::VALUE_NONE,     'Re-send metadata to Crossref for already-registered DOIs (free update)')
            ->addOption('fetch-journals',   null, InputOption::VALUE_NONE,     'Fetch active journals list from the API')
            ->addOption('dry-run',          null, InputOption::VALUE_NONE,     'Use Crossref test API instead of production');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('DOI management');

        $this->bootstrap();

        $logger = new Logger('getDoi');
        $logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'getDoi_' . date('Y-m-d') . '.log', Logger::INFO));
        if (!$io->isQuiet()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        if ($dryRun) {
            $io->note('Dry-run mode — using Crossref test API.');
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

        if ($input->getOption('assign-accepted')) {
            return $this->assignDois(Episciences_Paper::STATUS_ACCEPTED, $io, $review, $doiSettings, $logger);
        }

        if ($input->getOption('assign-published')) {
            return $this->assignDois(Episciences_Paper::STATUS_PUBLISHED, $io, $review, $doiSettings, $logger);
        }

        if ($input->getOption('request')) {
            return $this->requestDois($io, $review, $dryRun, $http, $crossrefClient, $logger);
        }

        if ($input->getOption('check')) {
            return $this->checkDois($io, $review, $dryRun, $crossrefClient, $logger);
        }

        if ($input->getOption('update')) {
            $paperId = $input->getOption('paperid') !== null ? (int) $input->getOption('paperid') : null;
            return $this->updateDois($io, $review, $dryRun, $http, $crossrefClient, $logger, $paperId);
        }

        $io->warning('No action specified. Use --assign-accepted, --assign-published, --request, --check, --update, or --fetch-journals.');
        return Command::FAILURE;
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
        define('REVIEW_PATH', ($realPath !== false ? $realPath : $dataPath) . '/');
        define('REVIEW_LANG_PATH', REVIEW_PATH . 'languages/');
        define('CACHE_PATH', CACHE_PATH_METADATA . $rvcode . '/');

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
        Logger                         $logger
    ): int {
        $rvid   = $review->getRvid();
        $rvcode = $review->getCode();

        $papers = Episciences_PapersManager::getList(['is' => ['rvid' => $rvid, 'status' => $paperStatus]]);
        $total  = count($papers);
        $logger->info(sprintf('%s: %d papers with status %s', $rvcode, $total, $paperStatus));

        $io->progressStart($total);
        $assigned = 0;

        foreach ($papers as $paper) {
            /** @var Episciences_Paper $paper */
            if (!empty($paper->getDoi()) || $rvid !== $paper->getRvid()) {
                $io->progressAdvance();
                continue;
            }

            $doi = $doiSettings->createDoiWithTemplate($paper, $rvcode);
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

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success(sprintf('Assigned %d DOI(s) for journal %s.', $assigned, $rvcode));
        return Command::SUCCESS;
    }

    /**
     * @throws \RuntimeException on metadata fetch failure.
     */
    private function requestDois(
        SymfonyStyle                $io,
        Episciences_Review          $review,
        bool                        $dryRun,
        Client                      $http,
        CrossrefSubmissionApiClient $crossrefClient,
        Logger                      $logger
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
        $io->progressStart($total);

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
                $io->progressAdvance();
                continue;
            }

            $xmlFileName = sprintf('%s-%d.xml', $rvcode, $paperId);
            $xmlFilePath = CACHE_PATH . $xmlFileName;
            $paperUrl    = sprintf('%spapers/export/%d/crossref?code=%s', EPISCIENCES_API_URL, $docId, $rvcode);

            $logger->info("Requesting metadata: {$paperUrl}");

            try {
                $body = $http->request('GET', $paperUrl)->getBody()->getContents();
                file_put_contents($xmlFilePath, $body);
            } catch (GuzzleException $e) {
                $logger->error("Metadata fetch failed for paper #{$paperId}: " . $e->getMessage());
                throw new \RuntimeException($e->getMessage(), 0, $e);
            }

            try {
                $response = $crossrefClient->postMetadata($xmlFilePath, $xmlFileName, $dryRun);
                $doiQueue->setDoi_status(Episciences_Paper_DoiQueue::STATUS_REQUESTED);
                Episciences_Paper_DoiQueueManager::update($doiQueue);
                $logger->info(sprintf('%s: Crossref answered: %s', $rvcode, $response->getBody()));
            } catch (GuzzleException $e) {
                $logger->error("Crossref submission failed for paper #{$paperId}: " . $e->getMessage());
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success("Submission completed for journal {$rvcode}.");
        return Command::SUCCESS;
    }

    private function checkDois(
        SymfonyStyle                $io,
        Episciences_Review          $review,
        bool                        $dryRun,
        CrossrefSubmissionApiClient $crossrefClient,
        Logger                      $logger
    ): int {
        $rvid   = $review->getRvid();
        $rvcode = $review->getCode();

        $collection = Episciences_Paper_DoiQueueManager::findDoisByStatus(
            $rvid,
            Episciences_Paper::STATUS_PUBLISHED,
            Episciences_Paper_DoiQueue::STATUS_REQUESTED
        );

        $io->progressStart(count($collection));

        foreach ($collection as $doiData) {
            /** @var Episciences_Paper $paper */
            $paper = $doiData['paper'];
            /** @var Episciences_Paper_DoiQueue $doiQueue */
            $doiQueue = $doiData['doiq'];

            $paperId     = $paper->getPaperId();
            $xmlFileName = sprintf('%s-%d.xml', $rvcode, $paperId);

            $xmlBody = $crossrefClient->fetchStatus($xmlFileName, $dryRun);

            if ($xmlBody === null) {
                $logger->error("Failed to fetch DOI status for paper #{$paperId}.");
                $io->progressAdvance();
                continue;
            }

            $successCount = $this->parseSuccessCount($xmlBody, $logger, $paperId);

            if ($successCount > 0 && $doiQueue->getDoi_status() !== Episciences_Paper_DoiQueue::STATUS_PUBLIC) {
                $logger->info("Paper #{$paperId}: setting status to public.");
                $doiQueue->setDoi_status(Episciences_Paper_DoiQueue::STATUS_PUBLIC);
                Episciences_Paper_DoiQueueManager::update($doiQueue);
                $logger->info("Paper #{$paperId}: DOI status is now public.");
            } else {
                $logger->info(sprintf('Paper #%d DOI status: %s', $paperId, $doiQueue->getDoi_status()));
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success("DOI status check completed for journal {$rvcode}.");
        return Command::SUCCESS;
    }

    /**
     * Re-send metadata XML to Crossref for already-registered DOIs.
     *
     * Crossref treats a POST for an existing DOI as a free metadata update.
     * The doi_status in database stays STATUS_PUBLIC — no queue change needed.
     *
     * @throws \RuntimeException on internal API metadata fetch failure.
     */
    private function updateDois(
        SymfonyStyle                $io,
        Episciences_Review          $review,
        bool                        $dryRun,
        Client                      $http,
        CrossrefSubmissionApiClient $crossrefClient,
        Logger                      $logger,
        ?int                        $paperId
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
        $io->progressStart($total);

        foreach ($res as $doiToProcess) {
            /** @var Episciences_Paper $paper */
            $paper = $doiToProcess['paper'];

            $currentPaperId = $paper->getPaperId();
            $logger->info(sprintf('%s: updating paper #%d', $rvcode, $currentPaperId));

            $docId = Episciences_PapersManager::getPublishedPaperId($currentPaperId);
            if ($docId === 0) {
                $logger->info("Paper #{$currentPaperId} has no published version, skipping.");
                $io->progressAdvance();
                continue;
            }

            $xmlFileName = sprintf('%s-%d.xml', $rvcode, $currentPaperId);
            $xmlFilePath = CACHE_PATH . $xmlFileName;
            $paperUrl    = sprintf('%spapers/export/%d/crossref?code=%s', EPISCIENCES_API_URL, $docId, $rvcode);

            $logger->info("Fetching metadata: {$paperUrl}");

            try {
                $body = $http->request('GET', $paperUrl)->getBody()->getContents();
                file_put_contents($xmlFilePath, $body);
            } catch (GuzzleException $e) {
                $logger->error("Metadata fetch failed for paper #{$currentPaperId}: " . $e->getMessage());
                throw new \RuntimeException($e->getMessage(), 0, $e);
            }

            try {
                $response = $crossrefClient->postMetadata($xmlFilePath, $xmlFileName, $dryRun);
                $logger->info(sprintf('%s: Crossref answered: %s', $rvcode, $response->getBody()));
            } catch (GuzzleException $e) {
                $logger->error("Crossref update failed for paper #{$currentPaperId}: " . $e->getMessage());
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success(sprintf('DOI metadata update completed for journal %s.', $rvcode));
        return Command::SUCCESS;
    }

    private function parseSuccessCount(string $xmlBody, Logger $logger, int $paperId): int
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlBody);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if ($xml === false) {
            $logger->error("Failed to parse Crossref XML response for paper #{$paperId}.");
            return 0;
        }

        return (int) (string) $xml->batch_data->success_count;
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
