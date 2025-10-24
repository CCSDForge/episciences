<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$localopts = [
    'rvcode=s' => "journal code",
    'ignorecache=b' => 'cache ignore for test',
    'removecache=b' => 'remove all cache'
];

if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}
require_once "JournalScript.php";

class MergePdfVol extends JournalScript
{
    public const APICALLVOL = "volumes?page=1&itemsPerPage=1000&rvcode=";

    protected bool $_dryRun = true;
    private Logger $logger;

    public function __construct($localopts)
    {
        $loggerName = 'mergePdfVol';
        $this->logger = new Logger($loggerName);

        // Log to file
        $logFilePath = EPISCIENCES_LOG_PATH . $loggerName . '.log';
        $this->logger->pushHandler(new StreamHandler($logFilePath, Logger::INFO));

        // Also log to stderr for immediate visibility
        $this->logger->pushHandler(new StreamHandler('php://stderr', Logger::ERROR));

        $this->logger->info("=== Logger initialized ===", ['logFile' => $logFilePath]);
        echo "Log file: " . $logFilePath . PHP_EOL;

        $this->setRequiredParams([]);
        $this->setArgs(array_merge($this->getArgs(), $localopts));
        parent::__construct();
    }

    public function run(): void
    {
        $startTime = microtime(true);
        $stats = [
            'processed' => 0,
            'skipped' => 0,
            'failed' => 0
        ];

        try {
            $this->logger->info('=== Starting volume PDF merge script ===');

            $this->initApp();
            $this->initDb();
            $this->initTranslator();
            defineJournalConstants();

            $rvCode = $this->getParam('rvcode');
            if ($rvCode === null) {
                $this->logger->error('ERROR: MISSING RVCODE');
                die('ERROR: MISSING RVCODE' . PHP_EOL);
            }

            $this->logger->info('Script parameters', [
                'rvcode' => $rvCode,
                'ignorecache' => $this->getParam('ignorecache'),
                'removecache' => $this->getParam('removecache')
            ]);

            if ($this->getParam('removecache') === '1') {
                $cache = new FilesystemAdapter("volume-pdf-" . $rvCode, 0, CACHE_PATH_METADATA);
                $cache->clear();
                $this->logger->info("Cache cleared for RV code", ['rvCode' => $rvCode]);
            }

            $volumeList = $this->getVolumeList($rvCode);

            $guzzleOptions = [
                'headers' => ['User-Agent' => EPISCIENCES_USER_AGENT,]
            ];

            $client = new Client($guzzleOptions);

            foreach ($volumeList as $index => $oneVolume) {
                $volumeStartTime = microtime(true);
                $this->logger->info("Processing volume", [
                    'index' => ($index + 1) . '/' . count($volumeList),
                    'volumeId' => $oneVolume['vid'] ?? 'unknown'
                ]);

                try {
                    $result = $this->mergePdfFromVolume($oneVolume, $client, $rvCode);
                    if ($result === 'skipped') {
                        $stats['skipped']++;
                    } else {
                        $stats['processed']++;
                    }
                } catch (Exception $e) {
                    $stats['failed']++;
                    $this->logger->error("Failed to process volume", [
                        'volumeId' => $oneVolume['vid'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }

                $volumeElapsed = microtime(true) - $volumeStartTime;
                $this->logger->info("Volume processing completed", [
                    'volumeId' => $oneVolume['vid'] ?? 'unknown',
                    'duration' => round($volumeElapsed, 2) . 's'
                ]);
            }

            $totalElapsed = microtime(true) - $startTime;

            $this->logger->info('=== Volume PDF fusion completed ===', [
                'totalVolumes' => count($volumeList),
                'processed' => $stats['processed'],
                'skipped' => $stats['skipped'],
                'failed' => $stats['failed'],
                'totalDuration' => round($totalElapsed, 2) . 's',
                'averageDuration' => count($volumeList) > 0 ? round($totalElapsed / count($volumeList), 2) . 's' : 'N/A'
            ]);

        } catch (Exception $e) {
            $logFilePath = EPISCIENCES_LOG_PATH . 'mergePdfVol.log';

            $errorDetails = [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];

            $this->logger->error('Fatal error occurred', $errorDetails);

            // Print to stderr for immediate visibility
            fwrite(STDERR, PHP_EOL . "=== FATAL ERROR ===" . PHP_EOL);
            fwrite(STDERR, "Message: " . $e->getMessage() . PHP_EOL);
            fwrite(STDERR, "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL);
            fwrite(STDERR, "Log file: " . $logFilePath . PHP_EOL);
            fwrite(STDERR, "Stack trace:" . PHP_EOL . $e->getTraceAsString() . PHP_EOL);

            die(PHP_EOL . "FATAL ERROR: " . $e->getMessage() . PHP_EOL . "Check log file: " . $logFilePath . PHP_EOL);
        }
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    private function getVolumeList(string $rvCode): mixed
    {
        $apiUrl = EPISCIENCES_API_URL . self::APICALLVOL . $rvCode;
        $this->logger->info("Fetching volume list from API", ['url' => $apiUrl, 'rvCode' => $rvCode]);

        try {
            $client = new Client();
            $response = $client->get($apiUrl)->getBody()->getContents();

            $this->logger->info("API response received", [
                'url' => $apiUrl,
                'responseSize' => strlen($response) . ' bytes'
            ]);

            $decodedResponse = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

            if (!isset($decodedResponse['hydra:member'])) {
                $this->logger->error("Invalid API response structure: missing 'hydra:member'", [
                    'url' => $apiUrl,
                    'responseKeys' => array_keys($decodedResponse ?? [])
                ]);
                throw new RuntimeException("Invalid API response: missing 'hydra:member' key");
            }

            $volumeList = $decodedResponse['hydra:member'];
            $volumeCount = is_array($volumeList) ? count($volumeList) : 0;

            $this->logger->info("Volume list retrieved successfully", [
                'rvCode' => $rvCode,
                'volumeCount' => $volumeCount,
                'volumeIds' => array_column($volumeList, 'vid')
            ]);

            if ($volumeCount === 0) {
                $this->logger->warning("No volumes found for journal", ['rvCode' => $rvCode]);
            }

            return $volumeList;

        } catch (GuzzleException $e) {
            $this->logger->error("Failed to fetch volume list from API", [
                'url' => $apiUrl,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e;
        } catch (JsonException $e) {
            $this->logger->error("Failed to decode API response JSON", [
                'url' => $apiUrl,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * @throws JsonException
     * @return string 'skipped' if volume was skipped, empty string otherwise
     */
    private function mergePdfFromVolume(mixed $res, Client $client, string $rvCode): string
    {
        $listOfPdfFilesToMerge = '';
        $paperIdCollection = self::getPaperIdCollection($res['papers']);

        $docIdCollection = $this->getDocIdsSortedByPosition($client, $paperIdCollection);

        $volumeId = $res['vid'];

        // Check cache status
        $ignoreCache = $this->getParam('ignorecache') === '1';
        $cachedDocIds = json_decode(self::getCacheDocIdsList($volumeId, $rvCode), true, 512, JSON_THROW_ON_ERROR);
        $docIdsChanged = $cachedDocIds !== $paperIdCollection;

        $this->logger->info("Cache check for volume", [
            'VolId' => $volumeId,
            'ignoreCache' => $ignoreCache,
            'cachedDocIds' => $cachedDocIds,
            'currentDocIds' => $paperIdCollection,
            'docIdsChanged' => $docIdsChanged
        ]);

        if ($ignoreCache || $docIdsChanged) {
            if ($ignoreCache) {
                $this->logger->info("Processing volume: cache ignored (ignorecache=1)", ['VolId' => $volumeId]);
            } else {
                $this->logger->info("Processing volume: document IDs have changed", [
                    'VolId' => $volumeId,
                    'cached' => json_encode($cachedDocIds),
                    'current' => json_encode($paperIdCollection)
                ]);
            }
            foreach ($docIdCollection as $docId) {
                $listOfPdfFilesToMerge = $this->fetchPdfFiles($docId, $rvCode, $client, $listOfPdfFilesToMerge);
            }
            $pathPdfMerged = sprintf("%s/../data/%s/public/volume-pdf/%s/", APPLICATION_PATH, $rvCode, $volumeId);
            if (!is_dir($pathPdfMerged) && !mkdir($pathPdfMerged, 0777, true) && !is_dir($pathPdfMerged)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $pathPdfMerged));
            }
            self::setCacheDocIdsList((string)$volumeId, $paperIdCollection, $rvCode);
            $exportPdfPath = $pathPdfMerged . $volumeId . '.pdf';

            // Build secure command with escapeshellarg for each file
            $pdfFiles = array_filter(explode(' ', trim($listOfPdfFilesToMerge)));
            $pdfFilesCount = count($pdfFiles);

            $this->logger->info("=== Starting PDF merge for volume ===", [
                'VolId' => $volumeId,
                'pdfFilesCount' => $pdfFilesCount,
                'destinationPath' => $exportPdfPath,
                'destinationDir' => $pathPdfMerged
            ]);

            if ($pdfFilesCount === 0) {
                $this->logger->error("No PDF files to merge for volume", ['VolId' => $volumeId]);
                return '';
            }

            // Verify all source files exist and are readable
            $this->logger->info("Verifying source PDF files");
            $totalSourceSize = 0;
            $missingFiles = [];
            $invalidFiles = [];

            foreach ($pdfFiles as $index => $pdfFile) {
                if (!file_exists($pdfFile)) {
                    $missingFiles[] = $pdfFile;
                    $this->logger->error("Source PDF file does not exist", [
                        'index' => $index + 1,
                        'file' => $pdfFile
                    ]);
                } elseif (!is_readable($pdfFile)) {
                    $this->logger->error("Source PDF file is not readable", [
                        'index' => $index + 1,
                        'file' => $pdfFile
                    ]);
                    $invalidFiles[] = $pdfFile;
                } else {
                    $fileSize = filesize($pdfFile);
                    $totalSourceSize += $fileSize;
                    $this->logger->info("Source PDF verified", [
                        'index' => $index + 1,
                        'file' => $pdfFile,
                        'size' => $fileSize . ' bytes'
                    ]);
                }
            }

            if (!empty($missingFiles) || !empty($invalidFiles)) {
                $this->logger->error("Cannot merge: some source files are missing or invalid", [
                    'VolId' => $volumeId,
                    'missingCount' => count($missingFiles),
                    'invalidCount' => count($invalidFiles),
                    'missingFiles' => $missingFiles,
                    'invalidFiles' => $invalidFiles
                ]);
                return '';
            }

            $this->logger->info("All source PDFs verified successfully", [
                'totalFiles' => $pdfFilesCount,
                'totalSourceSize' => $totalSourceSize . ' bytes'
            ]);

            // Check destination directory is writable
            if (!is_writable($pathPdfMerged)) {
                $this->logger->error("Destination directory is not writable", [
                    'path' => $pathPdfMerged,
                    'permissions' => substr(sprintf('%o', fileperms($pathPdfMerged)), -4)
                ]);
                return '';
            }

            $this->logger->info("Destination directory is writable", [
                'path' => $pathPdfMerged,
                'permissions' => substr(sprintf('%o', fileperms($pathPdfMerged)), -4)
            ]);

            // Delete existing file if present
            if (file_exists($exportPdfPath)) {
                $this->logger->warning("Destination file already exists, will be overwritten", [
                    'path' => $exportPdfPath,
                    'existingSize' => filesize($exportPdfPath) . ' bytes'
                ]);
            }

            $escapedFiles = array_map('escapeshellarg', $pdfFiles);
            $command = 'pdfunite ' . implode(' ', $escapedFiles) . ' ' . escapeshellarg($exportPdfPath);

            $this->logger->info("Executing pdfunite command", [
                'command' => $command,
                'workingDir' => getcwd()
            ]);

            $output = [];
            $returnCode = 0;
            $execStart = microtime(true);
            exec($command . ' 2>&1', $output, $returnCode);
            $execDuration = microtime(true) - $execStart;

            $this->logger->info("pdfunite command completed", [
                'VolId' => $volumeId,
                'returnCode' => $returnCode,
                'duration' => round($execDuration, 2) . 's',
                'outputLines' => count($output),
                'output' => empty($output) ? '(no output)' : implode("\n", $output)
            ]);

            if ($returnCode !== 0) {
                $this->logger->error("pdfunite command FAILED with non-zero return code", [
                    'VolId' => $volumeId,
                    'returnCode' => $returnCode,
                    'output' => implode("\n", $output),
                    'command' => $command,
                    'pdfFilesCount' => $pdfFilesCount
                ]);

                // Check if pdfunite is installed
                exec('which pdfunite 2>&1', $whichOutput, $whichCode);
                $this->logger->info("Checking pdfunite installation", [
                    'which' => implode("\n", $whichOutput),
                    'code' => $whichCode,
                    'found' => $whichCode === 0 ? 'yes' : 'no'
                ]);

                return '';
            }

            // Check if file was created
            if (!file_exists($exportPdfPath)) {
                $this->logger->error("CRITICAL: PDF file was NOT created despite zero return code", [
                    'VolId' => $volumeId,
                    'expectedPath' => $exportPdfPath,
                    'dirExists' => is_dir($pathPdfMerged) ? 'yes' : 'no',
                    'dirWritable' => is_writable($pathPdfMerged) ? 'yes' : 'no',
                    'returnCode' => $returnCode
                ]);

                // List directory contents to see what's there
                $dirContents = scandir($pathPdfMerged);
                $this->logger->info("Destination directory contents", [
                    'path' => $pathPdfMerged,
                    'contents' => $dirContents
                ]);

                return '';
            }

            // File exists - verify it
            $finalFileSize = filesize($exportPdfPath);
            $finalFileMime = mime_content_type($exportPdfPath);

            $this->logger->info("PDF file created successfully", [
                'VolId' => $volumeId,
                'path' => $exportPdfPath,
                'size' => $finalFileSize . ' bytes',
                'mimeType' => $finalFileMime,
                'sourceFilesTotal' => $totalSourceSize . ' bytes',
                'sizeRatio' => $totalSourceSize > 0 ? round(($finalFileSize / $totalSourceSize) * 100, 2) . '%' : 'N/A'
            ]);

            // Validate the merged PDF
            if ($finalFileSize < 100) {
                $this->logger->warning("Merged PDF file is suspiciously small", [
                    'VolId' => $volumeId,
                    'path' => $exportPdfPath,
                    'size' => $finalFileSize,
                    'sourceCount' => $pdfFilesCount
                ]);
            }

            if ($finalFileMime !== 'application/pdf') {
                $this->logger->error("Merged file has wrong MIME type", [
                    'VolId' => $volumeId,
                    'path' => $exportPdfPath,
                    'expected' => 'application/pdf',
                    'actual' => $finalFileMime
                ]);
            }

            // Verify PDF header
            $handle = fopen($exportPdfPath, 'r');
            if ($handle !== false) {
                $header = fread($handle, 5);
                fclose($handle);

                if ($header !== '%PDF-') {
                    $this->logger->error("Merged file does not have valid PDF header", [
                        'VolId' => $volumeId,
                        'path' => $exportPdfPath,
                        'expected' => '%PDF-',
                        'actual' => bin2hex($header)
                    ]);
                } else {
                    $this->logger->info("Merged PDF has valid header", ['VolId' => $volumeId]);
                }
            }

            $this->logger->info("=== PDF merge completed successfully ===", [
                'VolId' => $volumeId,
                'finalPath' => $exportPdfPath,
                'finalSize' => $finalFileSize . ' bytes'
            ]);
            return '';
        } else {
            $this->logger->info("Skipping volume: cache is valid (document IDs unchanged)", [
                'VolId' => $volumeId,
                'docIds' => json_encode($paperIdCollection)
            ]);
            return 'skipped';
        }
    }

    public static function getPaperIdCollection($data): array
    {
        return array_column($data, 'paperid');
    }

    public function getDocIdsSortedByPosition(Client $client, array $paperIdCollection): array
    {
        $docidCollection = [];
        $this->logger->info("Fetching document IDs and positions", ['paperCount' => count($paperIdCollection)]);

        foreach ($paperIdCollection as $paperId) {
            try {
                $apiUrl = EPISCIENCES_API_URL . 'papers/' . $paperId;
                $this->logger->info("Calling API for paper", ['paperId' => $paperId, 'url' => $apiUrl]);

                $response = $client->get($apiUrl)->getBody()->getContents();
                $paperProperties = json_decode($response);

                if ($paperProperties === null) {
                    $this->logger->error("Failed to decode JSON response for paper", [
                        'paperId' => $paperId,
                        'response' => substr($response, 0, 500) // Log first 500 chars
                    ]);
                    continue;
                }

                // Check if required properties exist
                if (!isset($paperProperties->document->database->current->identifiers->document_item_number)) {
                    $this->logger->error("Missing document_item_number in API response", [
                        'paperId' => $paperId,
                        'response' => json_encode($paperProperties)
                    ]);
                    continue;
                }

                if (!isset($paperProperties->document->database->current->position_in_volume)) {
                    $this->logger->error("Missing position_in_volume in API response", [
                        'paperId' => $paperId,
                        'response' => json_encode($paperProperties)
                    ]);
                    continue;
                }

                $docId = $paperProperties->document->database->current->identifiers->document_item_number;
                $position = $paperProperties->document->database->current->position_in_volume;

                $this->logger->info("Paper processed successfully", [
                    'paperId' => $paperId,
                    'docId' => $docId,
                    'position' => $position
                ]);

                $docidCollection[$position] = $docId;

            } catch (GuzzleException $e) {
                $this->logger->error("API call failed for paper", [
                    'paperId' => $paperId,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ]);
                continue;
            } catch (Exception $e) {
                $this->logger->error("Unexpected error while processing paper", [
                    'paperId' => $paperId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                continue;
            }
        }

        ksort($docidCollection);
        $this->logger->info("Document collection sorted by position", [
            'totalDocs' => count($docidCollection),
            'positions' => array_keys($docidCollection),
            'docIds' => array_values($docidCollection)
        ]);

        return $docidCollection;
    }

    /**
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getCacheDocIdsList(string $vid, string $rvCode): string
    {
        $cache = new FilesystemAdapter("volume-pdf-" . $rvCode, 0, CACHE_PATH_METADATA);
        $getVidsList = $cache->getItem($vid);
        if (!$getVidsList->isHit()) {
            return json_encode([''], JSON_THROW_ON_ERROR);
        }
        return $getVidsList->get();
    }

    public function fetchPdfFiles(mixed $docId, string $rvCode, Client $client, string $strPdf): string
    {
        $this->logger->info("Processing document for PDF fetch", ['docId' => $docId]);
        list($pdf, $pathDocId, $path) = $this->getPdfAndPath($rvCode, $docId);

        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            $this->logger->error("Failed to create directory", ['path' => $path, 'docId' => $docId]);
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
        }

        $pathPdf = $path . $docId . ".pdf";
        $realpathPDF = realpath($pathPdf);

        if (!file_exists($pathPdf)) {
            $this->logger->info("PDF file does not exist, attempting download", [
                'docId' => $docId,
                'url' => $pdf,
                'destination' => $pathPdf
            ]);

            try {
                $this->downloadPdf($client, $pdf, $pathPdf, $path, $pathDocId);
            } catch (GuzzleException $e) {
                $this->logger->error("Download failed with GuzzleException", [
                    'docId' => $docId,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ]);

                // Check if file exists before attempting to unlink
                if (file_exists($pathPdf)) {
                    if (unlink($pathPdf)) {
                        $this->logger->info("Removed failed/partial download", ['path' => $pathPdf]);
                    } else {
                        $this->logger->error("Failed to remove incomplete PDF file", ['path' => $pathPdf]);
                    }
                }
            }
        } else {
            $this->logger->info("PDF file already exists, skipping download", [
                'docId' => $docId,
                'path' => $pathPdf
            ]);
        }

        if (file_exists($pathPdf)) {
            $fileSize = filesize($pathPdf);
            $this->logger->info("Adding PDF to merge list", [
                'docId' => $docId,
                'path' => $realpathPDF ?: $pathPdf,
                'size' => $fileSize . ' bytes'
            ]);
            $strPdf .= $pathPdf . ' ';
        } else {
            $this->logger->warning("PDF file not available for document", [
                'docId' => $docId,
                'expectedPath' => $pathPdf
            ]);
        }

        return $strPdf;
    }

    public function getPdfAndPath(string $rvCode, mixed $docId): array
    {
        $pdf = "https://" . $rvCode . "." . DOMAIN . "/" . $docId . '/pdf';
        $pathDocId = APPLICATION_PATH . '/../data/' . $rvCode . '/files/' . $docId . '/';
        $path = APPLICATION_PATH . '/../data/' . $rvCode . '/files/' . $docId . '/documents/';
        return [$pdf, $pathDocId, $path];
    }

    public function downloadPdf(Client $client, string $pdf, string $pathPdf, string $path, string $pathDocId): void
    {
        try {
            $this->logger->info("Starting PDF download", ['url' => $pdf, 'destination' => $pathPdf]);
            $client->get($pdf, ['sink' => $pathPdf]);

            if (file_exists($pathPdf)) {
                $fileSize = filesize($pathPdf);
                $mimeType = mime_content_type($pathPdf);

                $this->logger->info("PDF downloaded successfully", [
                    'url' => $pdf,
                    'path' => $pathPdf,
                    'size' => $fileSize . ' bytes',
                    'mimeType' => $mimeType
                ]);

                $this->removeInvalidPDF($pathPdf);
            } else {
                $this->logger->error("PDF file not found after download", [
                    'url' => $pdf,
                    'expectedPath' => $pathPdf
                ]);
            }
        } catch (GuzzleException $e) {
            $this->logger->error("Failed to download PDF", [
                'url' => $pdf,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e; // Re-throw to be handled by caller
        }
    }

    public function removeInvalidPDF(string $pathPdf): void
    {
        if (!file_exists($pathPdf)) {
            $this->logger->warning("Cannot validate PDF: file does not exist", ['path' => $pathPdf]);
            return;
        }

        $fileSize = filesize($pathPdf);
        $mimeType = mime_content_type($pathPdf);
        $isValid = self::isValidPdf($pathPdf);

        $this->logger->info("PDF validation check", [
            'path' => $pathPdf,
            'size' => $fileSize . ' bytes',
            'mimeType' => $mimeType,
            'isValid' => $isValid
        ]);

        if (!$isValid) {
            $this->logger->warning("Invalid PDF detected, attempting removal", [
                'path' => $pathPdf,
                'size' => $fileSize,
                'mimeType' => $mimeType
            ]);

            if (unlink($pathPdf)) {
                $this->logger->info("Invalid PDF removed successfully", ['path' => $pathPdf]);
            } else {
                $this->logger->error("Failed to remove invalid PDF", ['path' => $pathPdf]);
            }
        }
    }

    public static function isValidPdf(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        // Check MIME type
        $mimeType = mime_content_type($filePath);
        if ($mimeType !== 'application/pdf') {
            return false;
        }

        // Additional check: verify PDF header
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 5);
        fclose($handle);

        // Valid PDF should start with %PDF-
        return $header === '%PDF-';
    }

    public static function setCacheDocIdsList($vid, array $jsonVidList, string $rvCode): void
    {
        $cache = new FilesystemAdapter("volume-pdf-" . $rvCode, 0, CACHE_PATH_METADATA);
        $setVidList = $cache->getItem($vid);
        $setVidList->set(json_encode($jsonVidList, JSON_THROW_ON_ERROR));
        $cache->save($setVidList);
    }
}

// Wrap script execution to catch early errors
try {
    $script = new MergePdfVol($localopts);
    $script->run();
} catch (Throwable $e) {
    // This catches errors that occur before the logger is initialized
    $logFilePath = defined('EPISCIENCES_LOG_PATH') ? EPISCIENCES_LOG_PATH . 'mergePdfVol.log' : '/tmp/mergePdfVol.log';

    fwrite(STDERR, PHP_EOL . "=== CRITICAL ERROR (before logger initialization) ===" . PHP_EOL);
    fwrite(STDERR, "Message: " . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL);
    fwrite(STDERR, "Type: " . get_class($e) . PHP_EOL);
    fwrite(STDERR, "Log file: " . $logFilePath . PHP_EOL);
    fwrite(STDERR, PHP_EOL . "Stack trace:" . PHP_EOL . $e->getTraceAsString() . PHP_EOL);

    // Try to log to file if possible
    if (defined('EPISCIENCES_LOG_PATH')) {
        $errorLog = sprintf(
            "[%s] CRITICAL ERROR: %s in %s:%d\nStack trace:\n%s\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        @file_put_contents($logFilePath, $errorLog, FILE_APPEND);
    }

    die(PHP_EOL . "CRITICAL ERROR: " . $e->getMessage() . PHP_EOL . "Check log file: " . $logFilePath . PHP_EOL);
}
