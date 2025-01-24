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
        $this->logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . $loggerName . '.log', Logger::INFO));

        $this->setRequiredParams([]);
        $this->setArgs(array_merge($this->getArgs(), $localopts));
        parent::__construct();
    }

    public function run(): void
    {
        try {
            $this->initApp();
            $this->initDb();
            $this->initTranslator();
            defineJournalConstants();

            $rvCode = $this->getParam('rvcode');
            if ($rvCode === null) {
                $this->logger->error('ERROR: MISSING RVCODE');
                die('ERROR: MISSING RVCODE' . PHP_EOL);
            }

            if ($this->getParam('removecache') === '1') {
                $cache = new FilesystemAdapter("volume-pdf-" . $rvCode, 0, dirname(APPLICATION_PATH) . '/cache/');
                $cache->clear();
                $this->logger->info("Cache cleared for RV code: $rvCode");
            }

            $volumeList = $this->getVolumeList($rvCode);
            $client = new Client();
            foreach ($volumeList as $oneVolume) {
                $this->mergePdfFromVolume($oneVolume, $client, $rvCode);
            }

            $this->logger->info('Volumes PDF fusion completed.');
        } catch (Exception $e) {
            $this->logger->error('An error occurred: ' . $e->getMessage(), ['exception' => $e]);
            die('An error occurred. Check the logs for details.' . PHP_EOL);
        }
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    private function getVolumeList(string $rvCode): mixed
    {
        $client = new Client();
        $response = $client->get(EPISCIENCES_API_URL . self::APICALLVOL . $rvCode)->getBody()->getContents();
        return json_decode($response, true, 512, JSON_THROW_ON_ERROR)['hydra:member'];
    }

    /**
     * @throws JsonException
     */
    private function mergePdfFromVolume(mixed $res, Client $client, string $rvCode): void
    {
        $listOfPdfFilesToMerge = '';
        $paperIdCollection = self::getPaperIdCollection($res['papers']);

        $docIdCollection = $this->getDocIdsSortedByPosition($client, $paperIdCollection);

        $volumeId = $res['vid'];

        if ($this->getParam('ignorecache') === '1' || (string)(json_decode(self::getCacheDocIdsList($volumeId, $rvCode), true, 512, JSON_THROW_ON_ERROR) !== $paperIdCollection)) {
            foreach ($docIdCollection as $docId) {
                $listOfPdfFilesToMerge = $this->fetchPdfFiles($docId, $rvCode, $client, $listOfPdfFilesToMerge);
            }
            $pathPdfMerged = sprintf("%s/../data/%s/public/volume-pdf/%s/", APPLICATION_PATH, $rvCode, $volumeId);
            if (!is_dir($pathPdfMerged) && !mkdir($pathPdfMerged, 0777, true) && !is_dir($pathPdfMerged)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $pathPdfMerged));
            }
            self::setCacheDocIdsList((string)$volumeId, $paperIdCollection, $rvCode);
            $exportPdfPath = $pathPdfMerged . $volumeId . '.pdf';
            $this->logger->info("Merging volume", ['VolId' => $volumeId]);
            system("pdfunite " . escapeshellcmd($listOfPdfFilesToMerge) . " " . escapeshellcmd($exportPdfPath));
            $this->logger->info("List of PDF Files merged", [$listOfPdfFilesToMerge]);
            $this->logger->info("New PDF file created", [$exportPdfPath]);
        } else {
            $this->logger->info("DocIds are the same from the API", ['Volume' => $volumeId]);
        }
    }

    public static function getPaperIdCollection($data): array
    {
        return array_column($data, 'paperid');
    }

    public function getDocIdsSortedByPosition(Client $client, array $paperIdCollection): array
    {
        $docidCollection = [];
        foreach ($paperIdCollection as $paperId) {
            $response = $client->get(EPISCIENCES_API_URL . 'papers/' . $paperId)->getBody()->getContents();
            $paperProperties = json_decode($response);
            $docId = $paperProperties->document->database->current->identifiers->document_item_number;
            $position = $paperProperties->document->database->current->position_in_volume;

            $docidCollection[$position] = $docId;
        }
        ksort($docidCollection);
        return $docidCollection;
    }

    /**
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getCacheDocIdsList(string $vid, string $rvCode): string
    {
        $cache = new FilesystemAdapter("volume-pdf-" . $rvCode, 0, dirname(APPLICATION_PATH) . '/cache/');
        $getVidsList = $cache->getItem($vid);
        if (!$getVidsList->isHit()) {
            return json_encode([''], JSON_THROW_ON_ERROR);
        }
        return $getVidsList->get();
    }

    public function fetchPdfFiles(mixed $docId, string $rvCode, Client $client, string $strPdf): string
    {
        $this->logger->info("Processing", ['docId' => $docId]);
        list($pdf, $pathDocId, $path) = $this->getPdfAndPath($rvCode, $docId);
        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
        }
        $pathPdf = $path . $docId . ".pdf";
        $realpathPDF = realpath($pathPdf);
        if (!file_exists($pathPdf)) {
            try {
                $this->downloadPdf($client, $pdf, $pathPdf, $path, $pathDocId);
            } catch (GuzzleException $e) {
                unlink($pathPdf);
                $this->logger->info('Removed', [$realpathPDF]);
            }
        }
        if (file_exists($pathPdf)) {
            $this->logger->info('Added', [$realpathPDF]);
            $strPdf .= $pathPdf . ' ';
        }
        return $strPdf;
    }

    public function getPdfAndPath(string $rvCode, mixed $docId): array
    {
        $pdf = "https://" . $rvCode . ".episciences.org" . "/" . $docId . '/pdf';
        $pathDocId = APPLICATION_PATH . '/../data/' . $rvCode . '/files/' . $docId . '/';
        $path = APPLICATION_PATH . '/../data/' . $rvCode . '/files/' . $docId . '/documents/';
        return [$pdf, $pathDocId, $path];
    }

    public function downloadPdf(Client $client, string $pdf, string $pathPdf, string $path, string $pathDocId): void
    {
        $response = $client->get($pdf, ['sink' => $pathPdf]);
        $this->logger->info("Downloaded", [$pdf]);
        if ($response->getStatusCode() !== 200 || !file_exists($pathPdf)) {
            $this->removeDirectory($pathPdf, $path);
        }
        $this->removePdfNotValid($pathPdf, $path, $pathDocId);
    }

    public function removeDirectory(string $pathPdf, string $path): void
    {
        unlink($pathPdf);
        $this->logger->info("Removed", [$pathPdf]);
        rmdir($path);
        $this->logger->info("Removed", [$path]);
    }

    public function removePdfNotValid(string $pathPdf, string $path, string $pathDocId): void
    {
        if (!self::isValidPdf($pathPdf)) {
            unlink($pathPdf);
            rmdir($path);
            rmdir($pathDocId);
            $this->logger->warning("Removed invalid", [$pathPdf]);
        }
    }

    public static function isValidPdf(string $filePath): bool
    {
        return mime_content_type($filePath) === 'application/pdf';
    }

    public static function setCacheDocIdsList($vid, array $jsonVidList, string $rvCode): void
    {
        $cache = new FilesystemAdapter("volume-pdf-" . $rvCode, 0, dirname(APPLICATION_PATH) . '/cache/');
        $setVidList = $cache->getItem($vid);
        $setVidList->set(json_encode($jsonVidList, JSON_THROW_ON_ERROR));
        $cache->save($setVidList);
    }
}

$script = new MergePdfVol($localopts);
$script->run();
