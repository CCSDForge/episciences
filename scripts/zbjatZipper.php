<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$localopts = [
    'rvid=i' => 'RVID of a journal',
    'zo=s' => 'Zip Output'
];

if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}
require_once "JournalScript.php";

class ZbjatZipper extends JournalScript
{
    private Logger $logger;
    private Client $httpClient;
    private Episciences_Review $review;

    public function __construct($localopts)
    {
        $this->setRequiredParams([]);
        $this->setArgs(array_merge($this->getArgs(), $localopts));
        parent::__construct();

        $loggerName = 'zbjatZipper';
        $this->logger = new Logger($loggerName);
        $this->logger->pushHandler(new StreamHandler(sprintf('%s/%s.log', EPISCIENCES_LOG_PATH, $loggerName), Logger::DEBUG));

        if ($this->getParam('v')) {
            $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        $this->httpClient = new Client();
    }

    public function run(): void
    {
        $this->initialize();

        $cliRvid = $this->getParam('rvid');

        if (!$cliRvid) {
            $this->logger->error('Rvid missing');
            return;
        }

        $this->review = Episciences_ReviewsManager::findByRvid($cliRvid);
        $this->review->loadSettings();

        $tabvolRepoName = $this->processJournal();

        $this->createZipArchive($tabvolRepoName);

        $this->logger->info('Done');
    }

    private function initialize(): void
    {
        $this->initApp();
        $this->initDb();
        $this->initTranslator();
    }

    /**
     * @return string[] Volume directory names (e.g. ['volume1', 'volume2'])
     */
    private function processJournal(): array
    {
        $volumes = $this->review->getVolumesWithPapers([]);
        $tabvolRepoName = [];
        $ivol = 1;

        foreach ($volumes as $volume) {
            $this->logger->info('Processing volume', ['vid' => $volume->getVid()]);
            $this->processVolume($volume, $ivol);
            $tabvolRepoName[] = 'volume' . $ivol;
            $ivol++;
        }

        return $tabvolRepoName;
    }

    private function processVolume(Episciences_Volume $volume, int $ivol): void
    {
        $dirnameVol = sprintf('%svolume%d/', $this->getZbjatsPath(), $ivol);

        if (!is_dir($dirnameVol) && !mkdir($dirnameVol, 0776, true) && !is_dir($dirnameVol)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dirnameVol));
        }

        /** @var Episciences_Paper[] $paperList */
        $paperList = $volume->getSortedPapersFromVolume('object');
        $iArticle = 1;

        foreach ($paperList as $paper) {
            if (!$paper->isPublished()) {
                continue;
            }

            if ($this->downloadPaperFiles($paper, $dirnameVol, $iArticle)) {
                $iArticle++;
            }
        }
    }

    private function downloadPaperFiles(Episciences_Paper $paper, string $dirnameVol, int $iArticle): bool
    {
        $docId = $paper->getDocid();
        $pdfUrl = $this->buildPaperUrl($docId, 'pdf');
        $xmlUrl = $this->buildPaperUrl($docId, 'zbjats');

        try {
            $pdfResponse = $this->httpClient->request('GET', $pdfUrl);

            if ($pdfResponse->getStatusCode() !== 200) {
                $this->logger->warning('Unexpected status for PDF', ['docId' => $docId, 'status' => $pdfResponse->getStatusCode()]);
                return false;
            }

            file_put_contents(sprintf('%sarticle%d.pdf', $dirnameVol, $iArticle), $pdfResponse->getBody()->getContents());
            $this->logger->info('Downloaded PDF', ['docId' => $docId, 'url' => $pdfUrl]);

            $xmlResponse = $this->httpClient->request('GET', $xmlUrl);

            if ($xmlResponse->getStatusCode() === 200) {
                file_put_contents(sprintf('%sarticle%d.xml', $dirnameVol, $iArticle), $xmlResponse->getBody()->getContents());
                $this->logger->info('Downloaded XML', ['docId' => $docId, 'url' => $xmlUrl]);
            }

            return true;

        } catch (GuzzleException $e) {
            $this->logger->error('Failed to download paper files', ['docId' => $docId, 'url' => $pdfUrl, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function buildPaperUrl(int $docId, string $format): string
    {
        return sprintf('https://%s.%s/%d/%s', $this->review->getCode(), DOMAIN, $docId, $format);
    }

    private function createZipArchive(array $tabvolRepoName): void
    {
        $pathdir = $this->getZbjatsPath();
        $zipOutput = $this->getParam('zo');
        $reviewCode = $this->review->getCode();

        $zipcreated = $zipOutput
            ? sprintf('%s%s%s.zip', $pathdir, $zipOutput, $reviewCode)
            : sprintf('%s%s.zip', $pathdir, $reviewCode);

        $zip = new ZipArchive();

        if ($zip->open($zipcreated, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->logger->error('Failed to create ZIP archive', ['path' => $zipcreated]);
            return;
        }

        $this->logger->info('Creating ZIP archive', ['path' => $zipcreated]);

        foreach ($tabvolRepoName as $volumeDir) {
            $volumePath = $pathdir . $volumeDir;
            $iterator = new DirectoryIterator($volumePath);

            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isFile()) {
                    $zip->addFile($fileInfo->getPathname(), sprintf('%s/%s', $volumeDir, $fileInfo->getFilename()));
                }
            }
        }

        $zip->close();
        $this->logger->info('ZIP archive created', ['path' => $zipcreated]);
    }

    private function getZbjatsPath(): string
    {
        return sprintf('%s/data/%s/zbjats/', dirname(APPLICATION_PATH), $this->review->getCode());
    }
}

$script = new ZbjatZipper($localopts);
$script->run();
