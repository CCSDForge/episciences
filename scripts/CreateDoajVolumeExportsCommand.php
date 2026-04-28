<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: create DOAJ XML volume exports for one or all journals.
 *
 * Replaces: scripts/createDoajVolumeExports.php (JournalScript)
 */
class CreateDoajVolumeExportsCommand extends Command
{
    protected static $defaultName = 'doaj:export-volumes';
    public const APICALLVOL = 'volumes?page=1&itemsPerPage=1000&rvcode=';

    private Logger $logger;
    private Client $client;

    protected function configure(): void
    {
        $this
            ->setDescription('Create DOAJ XML volume exports for one journal or all journals.')
            ->addOption('rvcode', null, InputOption::VALUE_REQUIRED, 'Journal RV code, or "allJournals"')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate without writing files or updating cache')
            ->addOption('ignore-cache', null, InputOption::VALUE_NONE, 'Bypass cache and force re-export')
            ->addOption('remove-cache', null, InputOption::VALUE_NONE, 'Clear the cache for the given rvcode before processing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io          = new SymfonyStyle($input, $output);
        $dryRun      = (bool) $input->getOption('dry-run');
        $ignoreCache = (bool) $input->getOption('ignore-cache');
        $removeCache = (bool) $input->getOption('remove-cache');
        $rvCodeParam = (string) $input->getOption('rvcode');

        if ($rvCodeParam === '') {
            $io->error('Missing required option: --rvcode');
            return Command::FAILURE;
        }

        $io->title('DOAJ volume export');
        $this->bootstrap();

        $this->client = new Client(['headers' => ['User-Agent' => EPISCIENCES_USER_AGENT]]);

        if ($dryRun) {
            $io->note('Dry-run mode enabled — no files will be written.');
        }

        $allJournals = $rvCodeParam === 'allJournals'
            ? $this->retrieveJournalCodes()
            : [$rvCodeParam];

        $failed = false;

        foreach ($allJournals as $journal) {
            $this->initLoggerForJournal($journal, $io->isQuiet());
            try {
                if ($removeCache) {
                    $cache = new FilesystemAdapter("doaj-volume-export-{$journal}", 0, CACHE_PATH_METADATA);
                    $cache->clear();
                    $this->logger->info("Cache cleared for RV code: {$journal}");
                }

                $volumeList = $this->getVolumeList($journal);

                foreach ($volumeList as $oneVolume) {
                    $this->mergeDoajExportFromVolume($oneVolume, $journal, $ignoreCache, $dryRun);
                }

                $this->logger->info("{$journal} volumes export completed.");
            } catch (\Throwable $e) {
                $this->logger->error("{$journal}: an error occurred: " . $e->getMessage(), ['exception' => $e]);
                $failed = true;
            }
        }

        if ($failed) {
            $io->error('One or more journals failed. Check logs for details.');
            return Command::FAILURE;
        }

        $io->success('DOAJ volume export completed.');
        return Command::SUCCESS;
    }

    /**
     * @return array<string>
     */
    public function retrieveJournalCodes(int $itemsPerPage = 30): array
    {
        $page     = 1;
        $allCodes = [];

        try {
            do {
                $response = $this->client->request('GET', EPISCIENCES_API_URL . 'journals/', [
                    'query' => [
                        'page'         => $page,
                        'itemsPerPage' => $itemsPerPage,
                        'pagination'   => 'false',
                    ],
                ]);
                $journals = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                $allCodes = array_merge($allCodes, array_column($journals, 'code'));
                $page++;
            } while (count($journals) === $itemsPerPage);

            return $allCodes;
        } catch (GuzzleException $e) {
            $this->logger->error($e->getMessage(), [$e->getCode()]);
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws GuzzleException
     * @throws \JsonException
     */
    private function getVolumeList(string $rvCode): array
    {
        $apiUrl = EPISCIENCES_API_URL . self::APICALLVOL . $rvCode;
        $this->logger->info("Fetching volumes from {$apiUrl}");
        $response = $this->client->get($apiUrl)->getBody()->getContents();
        $decoded  = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($decoded['hydra:member'])) {
            throw new \RuntimeException("Invalid API response: missing 'hydra:member' key");
        }

        return $decoded['hydra:member'];
    }

    /**
     * @param array<string, mixed> $res
     * @throws \JsonException
     */
    private function mergeDoajExportFromVolume(
        array $res,
        string $rvCode,
        bool $ignoreCache,
        bool $dryRun
    ): void {
        $paperIdCollection = self::getPaperIdCollection($res['papers']);
        $docIdCollection   = $this->getDocIdsSortedByPosition($paperIdCollection);
        $volumeId          = $res['vid'];

        // Bug A fix: was (string)(json_decode() !== $collection), a bool-to-string coercion
        $cachedDocIds  = json_decode(self::getCacheDocIdsList((string) $volumeId, $rvCode), true, 512, JSON_THROW_ON_ERROR);
        $docIdsChanged = $cachedDocIds !== $paperIdCollection;

        if (!$ignoreCache && !$docIdsChanged) {
            $this->logger->info('DocIds unchanged, skipping volume', ['Volume' => $volumeId]);
            return;
        }

        $listOfDoajExports = [];
        foreach ($docIdCollection as $docId) {
            $listOfDoajExports[] = $this->fetchDoajExport($docId);
        }

        $pathDoajVolumeDir   = sprintf('%s/../data/%s/public/volume-doaj/%s/', APPLICATION_PATH, $rvCode, $volumeId);
        $exportDoajVolumePath = $pathDoajVolumeDir . $volumeId . '.xml';
        $xmlContent          = '<?xml version="1.0"?>' . PHP_EOL
                             . '<records>' . PHP_EOL
                             . implode(PHP_EOL, $listOfDoajExports) . PHP_EOL
                             . '</records>';

        if ($dryRun) {
            $this->logger->info('[dry-run] Would write DOAJ volume export', [
                'VolId' => $volumeId,
                'path'  => $exportDoajVolumePath,
                'size'  => strlen($xmlContent) . ' bytes',
            ]);
            return;
        }

        if (!is_dir($pathDoajVolumeDir) && !mkdir($pathDoajVolumeDir, 0777, true) && !is_dir($pathDoajVolumeDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $pathDoajVolumeDir));
        }

        self::setCacheDocIdsList((string) $volumeId, $paperIdCollection, $rvCode);
        file_put_contents($exportDoajVolumePath, $xmlContent);

        $this->logger->info('Wrote DOAJ volume export', ['VolId' => $volumeId, 'path' => $exportDoajVolumePath]);
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public static function getPaperIdCollection(array $data): array
    {
        return array_column($data, 'paperid');
    }

    /**
     * @param array<mixed> $paperIdCollection
     * @return array<int|string, mixed>
     */
    public function getDocIdsSortedByPosition(array $paperIdCollection): array
    {
        $docidCollection = [];

        foreach ($paperIdCollection as $paperId) {
            try {
                $response        = $this->client->get(EPISCIENCES_API_URL . 'papers/' . $paperId)->getBody()->getContents();
                $paperProperties = json_decode($response);

                if ($paperProperties === null) {
                    $this->logger->error('Failed to decode API response for paper', ['paperId' => $paperId]);
                    continue;
                }

                if (!isset($paperProperties->document->database->current->identifiers->document_item_number)) {
                    $this->logger->error('Missing document_item_number in API response', ['paperId' => $paperId]);
                    continue;
                }

                if (!isset($paperProperties->document->database->current->position_in_volume)) {
                    $this->logger->error('Missing position_in_volume in API response', ['paperId' => $paperId]);
                    continue;
                }

                $docId    = $paperProperties->document->database->current->identifiers->document_item_number;
                $position = $paperProperties->document->database->current->position_in_volume;

                $docidCollection[$position] = $docId;
            } catch (GuzzleException $e) {
                $this->logger->error($e->getMessage(), ['paperId' => $paperId, 'code' => $e->getCode()]);
            }
        }

        ksort($docidCollection);
        return $docidCollection;
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \JsonException
     */
    public static function getCacheDocIdsList(string $vid, string $rvCode): string
    {
        $cache = new FilesystemAdapter("doaj-volume-export-{$rvCode}", 0, CACHE_PATH_METADATA);
        $item  = $cache->getItem($vid);
        if (!$item->isHit()) {
            return json_encode([''], JSON_THROW_ON_ERROR);
        }
        return $item->get();
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \JsonException
     */
    /**
     * @param array<mixed> $jsonVidList
     */
    public static function setCacheDocIdsList(string $vid, array $jsonVidList, string $rvCode): void
    {
        $cache = new FilesystemAdapter("doaj-volume-export-{$rvCode}", 0, CACHE_PATH_METADATA);
        $item  = $cache->getItem($vid);
        $item->set(json_encode($jsonVidList, JSON_THROW_ON_ERROR));
        $cache->save($item);
    }

    /**
     * Download DOAJ XML for a single paper and strip the outer XML envelope.
     *
     * Bug E fix: legacy passed $this->client as a dead param — removed.
     */
    public function fetchDoajExport(int|string $docId): string
    {
        $this->logger->info('Processing DOAJ export', ['docId' => $docId]);
        $doajText = $this->downloadDoajExport((string) $docId);
        // Strip outer XML declaration and <records> wrapper so entries can be re-wrapped per volume
        return str_replace(['<?xml version="1.0"?>', '<records>', '</records>'], '', $doajText);
    }

    public function downloadDoajExport(string $docId): string
    {
        $urlApi = sprintf(EPISCIENCES_API_URL . 'papers/export/%s/doaj', $docId);
        try {
            $body = $this->client->get($urlApi)->getBody()->getContents();
            $this->logger->info('DOAJ export downloaded', ['docId' => $docId]);
            return $body;
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to download DOAJ export', ['docId' => $docId, 'error' => $e->getMessage()]);
            return '';
        }
    }

    private function initLoggerForJournal(string $journalCode, bool $quiet): void
    {
        $loggerName   = 'doajVolumeExports-' . $journalCode;
        $this->logger = new Logger($loggerName);
        $this->logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . $loggerName . '_' . date('Y-m-d') . '.log', Logger::INFO
        ));
        if (!$quiet) {
            $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }
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
        // Mirrors legacy JournalScript pattern: initApp() reads config, initDb() sets adapter.
        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);

        Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
    }
}
