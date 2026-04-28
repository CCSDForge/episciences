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
 * Symfony Console command: merge PDFs for all volumes of one or all journals.
 *
 * Replaces: scripts/mergePdfVol.php (JournalScript)
 */
class MergePdfVolCommand extends Command
{
    protected static $defaultName = 'volume:merge-pdf';
    public const APICALLVOL = 'volumes?page=1&itemsPerPage=1000&rvcode=';

    private Logger $logger;

    protected function configure(): void
    {
        $this
            ->setDescription('Merge PDFs for all volumes of one or all journals (requires pdfunite).')
            ->addOption('rvcode', null, InputOption::VALUE_REQUIRED, 'Journal RV code, or "allJournals"')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate without downloading or merging PDFs')
            ->addOption('ignore-cache', null, InputOption::VALUE_NONE, 'Bypass cache and force re-merge')
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

        $io->title('Volume PDF merge');
        $this->bootstrap();

        $this->logger = new Logger('mergePdfVol');
        $this->logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'mergePdfVol_' . date('Y-m-d') . '.log', Logger::INFO
        ));
        if (!$io->isQuiet()) {
            $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        if ($dryRun) {
            $io->note('Dry-run mode enabled — no PDFs will be downloaded or merged.');
        }

        $startTime = microtime(true);
        $stats     = ['processed' => 0, 'skipped' => 0, 'failed' => 0];

        $client      = new Client(['headers' => ['User-Agent' => EPISCIENCES_USER_AGENT]]);
        $allJournals = $rvCodeParam === 'allJournals'
            ? $this->retrieveJournalCodes($client)
            : [$rvCodeParam];

        foreach ($allJournals as $rvCode) {
            $this->logger->info("Processing journal: {$rvCode}");

            if (!defined('RVCODE')) {
                define('RVCODE', $rvCode);
            }

            $this->logger->info('Script parameters', [
                'rvcode'      => $rvCode,
                'ignoreCache' => $ignoreCache,
                'removeCache' => $removeCache,
            ]);

            if ($removeCache) {
                $cache = new FilesystemAdapter("volume-pdf-{$rvCode}", 0, CACHE_PATH_METADATA);
                $cache->clear();
                $this->logger->info('Cache cleared', ['rvCode' => $rvCode]);
            }

            try {
                $volumeList = $this->getVolumeList($rvCode);
            } catch (\Throwable $e) {
                $this->logger->error("Failed to retrieve volume list for {$rvCode}", ['error' => $e->getMessage()]);
                continue;
            }

            foreach ($volumeList as $index => $oneVolume) {
                $volumeStart = microtime(true);
                $this->logger->info('Processing volume', [
                    'index'    => ($index + 1) . '/' . count($volumeList),
                    'volumeId' => $oneVolume['vid'] ?? 'unknown',
                    'rvCode'   => $rvCode,
                ]);

                try {
                    $result = $this->mergePdfFromVolume($oneVolume, $client, $rvCode, $ignoreCache, $dryRun);
                    if ($result === 'skipped') {
                        $stats['skipped']++;
                    } else {
                        $stats['processed']++;
                    }
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $this->logger->error('Failed to process volume', [
                        'volumeId' => $oneVolume['vid'] ?? 'unknown',
                        'error'    => $e->getMessage(),
                    ]);
                }

                $this->logger->info('Volume processing completed', [
                    'volumeId' => $oneVolume['vid'] ?? 'unknown',
                    'duration' => round(microtime(true) - $volumeStart, 2) . 's',
                ]);
            }
        }

        $this->logger->info('=== Volume PDF fusion completed ===', array_merge($stats, [
            'totalDuration' => round(microtime(true) - $startTime, 2) . 's',
        ]));

        $io->success('Volume PDF merge completed.');
        return $stats['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return array<string>
     */
    public function retrieveJournalCodes(Client $client, int $itemsPerPage = 30): array
    {
        $page     = 1;
        $allCodes = [];

        try {
            do {
                $response = $client->request('GET', EPISCIENCES_API_URL . 'journals/', [
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
        } catch (\Throwable $e) {
            $this->logger->error('Failed to retrieve journal codes: ' . $e->getMessage());
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
        $this->logger->info('Fetching volume list from API', ['url' => $apiUrl, 'rvCode' => $rvCode]);

        $response = (new Client())->get($apiUrl)->getBody()->getContents();
        $decoded  = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($decoded['hydra:member'])) {
            throw new \RuntimeException("Invalid API response: missing 'hydra:member' key");
        }

        $volumeList = $decoded['hydra:member'];
        $this->logger->info('Volume list retrieved', [
            'rvCode'      => $rvCode,
            'volumeCount' => count($volumeList),
        ]);

        return $volumeList;
    }

    /**
     * @param array<string, mixed> $res
     * @return string 'skipped' if volume was skipped, '' otherwise
     * @throws \JsonException
     */
    private function mergePdfFromVolume(
        array $res,
        Client $client,
        string $rvCode,
        bool $ignoreCache,
        bool $dryRun
    ): string {
        $listOfPdfFilesToMerge = '';
        $paperIdCollection     = self::getPaperIdCollection($res['papers']);
        $docIdCollection       = $this->getDocIdsSortedByPosition($client, $paperIdCollection);
        $volumeId              = $res['vid'];

        $cachedDocIds = json_decode(self::getCacheDocIdsList((string) $volumeId, $rvCode), true, 512, JSON_THROW_ON_ERROR);
        $docIdsChanged = $cachedDocIds !== $paperIdCollection;

        $this->logger->info('Cache check for volume', [
            'VolId'        => $volumeId,
            'ignoreCache'  => $ignoreCache,
            'docIdsChanged' => $docIdsChanged,
        ]);

        if (!$ignoreCache && !$docIdsChanged) {
            $this->logger->info('Skipping volume: cache is valid (document IDs unchanged)', [
                'VolId'  => $volumeId,
                'docIds' => json_encode($paperIdCollection),
            ]);
            return 'skipped';
        }

        if ($ignoreCache) {
            $this->logger->info('Processing volume: cache ignored (--ignore-cache)', ['VolId' => $volumeId]);
        } else {
            $this->logger->info('Processing volume: document IDs have changed', ['VolId' => $volumeId]);
        }

        foreach ($docIdCollection as $docId) {
            $listOfPdfFilesToMerge = $this->fetchPdfFiles($docId, $rvCode, $client, $listOfPdfFilesToMerge, $dryRun);
        }

        $pathPdfMerged = sprintf('%s/../data/%s/public/volume-pdf/%s/', APPLICATION_PATH, $rvCode, $volumeId);

        if ($dryRun) {
            $pdfFiles = array_filter(explode(' ', trim($listOfPdfFilesToMerge)));
            $this->logger->info('[dry-run] Would merge ' . count($pdfFiles) . ' PDF(s) into ' . $pathPdfMerged . $volumeId . '.pdf');
            return '';
        }

        if (!is_dir($pathPdfMerged) && !mkdir($pathPdfMerged, 0777, true) && !is_dir($pathPdfMerged)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $pathPdfMerged));
        }

        self::setCacheDocIdsList((string) $volumeId, $paperIdCollection, $rvCode);
        $exportPdfPath = $pathPdfMerged . $volumeId . '.pdf';

        $pdfFiles     = array_filter(explode(' ', trim($listOfPdfFilesToMerge)));
        $pdfFilesCount = count($pdfFiles);

        $this->logger->info('Starting PDF merge for volume', [
            'VolId'         => $volumeId,
            'pdfFilesCount' => $pdfFilesCount,
        ]);

        if ($pdfFilesCount === 0) {
            $this->logger->error('No PDF files to merge for volume', ['VolId' => $volumeId]);
            return '';
        }

        // Verify all source files exist and are readable
        $missingFiles = [];
        $invalidFiles = [];
        $totalSourceSize = 0;

        foreach ($pdfFiles as $index => $pdfFile) {
            if (!file_exists($pdfFile)) {
                $missingFiles[] = $pdfFile;
                $this->logger->error('Source PDF file does not exist', ['index' => $index + 1, 'file' => $pdfFile]);
            } elseif (!is_readable($pdfFile)) {
                $invalidFiles[] = $pdfFile;
                $this->logger->error('Source PDF file is not readable', ['index' => $index + 1, 'file' => $pdfFile]);
            } else {
                $totalSourceSize += filesize($pdfFile);
            }
        }

        if (!empty($missingFiles) || !empty($invalidFiles)) {
            $this->logger->error('Cannot merge: some source files are missing or invalid', ['VolId' => $volumeId]);
            return '';
        }

        if (!is_writable($pathPdfMerged)) {
            $this->logger->error('Destination directory is not writable', ['path' => $pathPdfMerged]);
            return '';
        }

        $escapedFiles = array_map('escapeshellarg', $pdfFiles);
        $command      = 'pdfunite ' . implode(' ', $escapedFiles) . ' ' . escapeshellarg($exportPdfPath);

        $this->logger->info('Executing pdfunite command', ['command' => $command]);

        $cmdOutput  = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $cmdOutput, $returnCode);

        if ($returnCode !== 0) {
            $this->logger->error('pdfunite command FAILED', [
                'VolId'      => $volumeId,
                'returnCode' => $returnCode,
                'output'     => implode("\n", $cmdOutput),
            ]);
            return '';
        }

        if (!file_exists($exportPdfPath)) {
            $this->logger->error('PDF file was NOT created despite zero return code', [
                'VolId'        => $volumeId,
                'expectedPath' => $exportPdfPath,
            ]);
            return '';
        }

        $finalFileSize = filesize($exportPdfPath);
        $finalFileMime = mime_content_type($exportPdfPath);

        if ($finalFileMime !== 'application/pdf') {
            $this->logger->error('Merged file has wrong MIME type', [
                'VolId'    => $volumeId,
                'expected' => 'application/pdf',
                'actual'   => $finalFileMime,
            ]);
        }

        // Verify PDF header
        $handle = fopen($exportPdfPath, 'r');
        if ($handle !== false) {
            $header = fread($handle, 5);
            fclose($handle);
            if ($header !== '%PDF-') {
                $this->logger->error('Merged file does not have valid PDF header', ['VolId' => $volumeId]);
            }
        }

        $this->logger->info('PDF merge completed successfully', [
            'VolId'     => $volumeId,
            'finalPath' => $exportPdfPath,
            'finalSize' => $finalFileSize . ' bytes',
        ]);

        return '';
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
    public function getDocIdsSortedByPosition(Client $client, array $paperIdCollection): array
    {
        $docidCollection = [];
        $this->logger->info('Fetching document IDs and positions', ['paperCount' => count($paperIdCollection)]);

        foreach ($paperIdCollection as $paperId) {
            try {
                $apiUrl           = EPISCIENCES_API_URL . 'papers/' . $paperId;
                $response         = $client->get($apiUrl)->getBody()->getContents();
                $paperProperties  = json_decode($response);

                if ($paperProperties === null) {
                    $this->logger->error('Failed to decode JSON response for paper', ['paperId' => $paperId]);
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
            } catch (\Throwable $e) {
                $this->logger->error('Error while processing paper', [
                    'paperId' => $paperId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        ksort($docidCollection);
        return $docidCollection;
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getCacheDocIdsList(string $vid, string $rvCode): string
    {
        $cache  = new FilesystemAdapter("volume-pdf-{$rvCode}", 0, CACHE_PATH_METADATA);
        $item   = $cache->getItem($vid);
        if (!$item->isHit()) {
            return json_encode([''], JSON_THROW_ON_ERROR);
        }
        return $item->get();
    }

    /**
     * @param array<mixed> $jsonVidList
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \JsonException
     */
    public static function setCacheDocIdsList(string $vid, array $jsonVidList, string $rvCode): void
    {
        $cache = new FilesystemAdapter("volume-pdf-{$rvCode}", 0, CACHE_PATH_METADATA);
        $item  = $cache->getItem($vid);
        $item->set(json_encode($jsonVidList, JSON_THROW_ON_ERROR));
        $cache->save($item);
    }

    public function fetchPdfFiles(
        int|string $docId,
        string $rvCode,
        Client $client,
        string $strPdf,
        bool $dryRun
    ): string {
        $this->logger->info('Processing document for PDF fetch', ['docId' => $docId]);
        [$pdf, $pathDocId, $path] = $this->getPdfAndPath($rvCode, $docId);

        if ($dryRun) {
            $this->logger->info('[dry-run] Would fetch PDF', ['docId' => $docId, 'url' => $pdf]);
            return $strPdf . $path . $docId . '.pdf ';
        }

        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
        }

        $pathPdf = $path . $docId . '.pdf';

        if (!file_exists($pathPdf)) {
            $this->logger->info('PDF file does not exist, attempting download', [
                'docId'       => $docId,
                'url'         => $pdf,
                'destination' => $pathPdf,
            ]);
            try {
                $this->downloadPdf($client, $pdf, $pathPdf, $path, $pathDocId);
            } catch (GuzzleException $e) {
                $this->logger->error('Download failed', ['docId' => $docId, 'error' => $e->getMessage()]);
                if (file_exists($pathPdf)) {
                    unlink($pathPdf);
                }
            }
        } else {
            $this->logger->info('PDF file already exists, skipping download', ['docId' => $docId]);
        }

        if (file_exists($pathPdf)) {
            $strPdf .= $pathPdf . ' ';
        } else {
            $this->logger->warning('PDF file not available for document', ['docId' => $docId]);
        }

        return $strPdf;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    public function getPdfAndPath(string $rvCode, int|string $docId): array
    {
        $pdf        = 'https://' . $rvCode . '.' . DOMAIN . '/' . $docId . '/pdf';
        $pathDocId  = APPLICATION_PATH . '/../data/' . $rvCode . '/files/' . $docId . '/';
        $path       = APPLICATION_PATH . '/../data/' . $rvCode . '/files/' . $docId . '/documents/';
        return [$pdf, $pathDocId, $path];
    }

    /**
     * @throws GuzzleException
     */
    public function downloadPdf(Client $client, string $pdf, string $pathPdf, string $path, string $pathDocId): void
    {
        $this->logger->info('Starting PDF download', ['url' => $pdf, 'destination' => $pathPdf]);
        $client->get($pdf, ['sink' => $pathPdf]);

        if (file_exists($pathPdf)) {
            $this->logger->info('PDF downloaded successfully', [
                'url'      => $pdf,
                'path'     => $pathPdf,
                'size'     => filesize($pathPdf) . ' bytes',
                'mimeType' => mime_content_type($pathPdf),
            ]);
            $this->removeInvalidPDF($pathPdf);
        } else {
            $this->logger->error('PDF file not found after download', ['url' => $pdf, 'expectedPath' => $pathPdf]);
        }
    }

    public function removeInvalidPDF(string $pathPdf): void
    {
        if (!file_exists($pathPdf)) {
            $this->logger->warning('Cannot validate PDF: file does not exist', ['path' => $pathPdf]);
            return;
        }

        if (!self::isValidPdf($pathPdf)) {
            $this->logger->warning('Invalid PDF detected, removing', ['path' => $pathPdf]);
            if (unlink($pathPdf)) {
                $this->logger->info('Invalid PDF removed', ['path' => $pathPdf]);
            } else {
                $this->logger->error('Failed to remove invalid PDF', ['path' => $pathPdf]);
            }
        }
    }

    public static function isValidPdf(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }
        if (mime_content_type($filePath) !== 'application/pdf') {
            return false;
        }
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return false;
        }
        $header = fread($handle, 5);
        fclose($handle);
        return $header === '%PDF-';
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
