<?php


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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

class mergePdfVol extends JournalScript
{

    /**
     * getDoi constructor.
     * @param $localopts
     */
    public const APICALLVOL = "volumes?page=1&itemsPerPage=1000&rvcode=";
    public const APICALLFORDOCID = "papers?page=1&itemsPerPage=1000&vid=";
    /**
     * @var bool
     */
    protected bool $_dryRun = true;

    public function __construct($localopts)
    {
        $this->setRequiredParams([]);
        $this->setArgs(array_merge($this->getArgs(), $localopts));
        parent::__construct();

    }

    /**
     * @return mixed|void
     * @throws JsonException
     */
    public
    function run()
    {

        $this->initApp();
        $this->initDb();
        $this->initTranslator();
        defineJournalConstants();
        $rvCode = $this->getParam('rvcode');
        if ($rvCode === null) {
            die('ERROR: MISSING RVCODE' . PHP_EOL);
        }

        if ($this->getParam('removecache') === '1') {
            $cache = new FilesystemAdapter("volume-pdf-" . $rvCode, 0, dirname(APPLICATION_PATH) . '/cache/');
            $cache->clear();
        }
        $client = new Client();
        $response = $client->get(EPISCIENCES_API_URL . self::APICALLVOL . $rvCode)->getBody()->getContents();
        foreach (json_decode($response, true, 512, JSON_THROW_ON_ERROR)['hydra:member'] as $res) {
            $strPdf = '';
            $getDocIdBypaperApi = $client->get(EPISCIENCES_API_URL . self::APICALLFORDOCID . $res['vid'])->getBody()->getContents();
            $docIds = $this->getDocIds($getDocIdBypaperApi, $res['vid']);
            $docIdList = [];
            foreach ($res['papers'] as $paper) {
                $docIdList[] = $docIds[$paper['paperid']];
            }
            if ($this->getParam('ignorecache') === '1' || (string)(json_decode(self::getCacheDocIdsList($res['vid'], $rvCode), true, 512, JSON_THROW_ON_ERROR) !== $docIdList)) {
                foreach ($res['papers'] as $paper) {
                    $docId = $docIds[$paper['paperid']];
                    $this->displayInfo('docId ' . $docId . "\n", true);
                    list($pdf, $pathDocId, $path) = $this->getPdfAndPath($rvCode, $docId);
                    if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
                        throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
                    }
                    $pathPdf = $path . $docId . ".pdf";
                    if (!file_exists($pathPdf)) {
                        try {
                            $this->downloadPdf($client, $pdf, $pathPdf, $path, $pathDocId);
                        } catch (GuzzleException $e) {
                            unlink($pathPdf);
                        }
                    }
                    if (file_exists($pathPdf)) {
                        $strPdf .= $pathPdf . ' ';
                    }
                }
                $pathPdfMerged = APPLICATION_PATH . '/../data/' . $rvCode . '/public/volume-pdf/' . $res['vid'] . '/';
                if (!is_dir($pathPdfMerged) && !mkdir($pathPdfMerged, 0777, true) && !is_dir($pathPdfMerged)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $pathPdfMerged));
                }
                self::setCacheDocIdsList((string)$res['vid'], $docIdList, $rvCode);
                $exportPdfPath = $pathPdfMerged . $res['vid'] . '.pdf';
                $this->displayInfo('merge volume : ' . $res['vid'] . "\n", true);
                system("pdfunite " . escapeshellcmd($strPdf) . " " . escapeshellcmd($exportPdfPath));
            } else {
                $this->displayInfo('docids are the same from the API', true);
            }
        }
        $this->displayInfo('Volumes pdf fusion completed. Good Bye ! =)', true);

    }

    /**
     * @param string $getDocIdBypaperApi
     * @param $vid
     * @return array
     * @throws JsonException
     */
    public function getDocIds(string $getDocIdBypaperApi, $vid): array
    {
        $docIds = [];
        foreach (json_decode($getDocIdBypaperApi, true, 512, JSON_THROW_ON_ERROR)["hydra:member"] as $papers) {
            $docIds[$papers['paperid']] = $papers['docid'];
        }
        $this->displayInfo('Volumes ' . $vid . "\n", true);
        return $docIds;
    }

    public static function getCacheDocIdsList(string $vid, string $rvCode): string
    {
        $cache = new FilesystemAdapter("volume-pdf-" . $rvCode, 0, dirname(APPLICATION_PATH) . '/cache/');
        $getVidsList = $cache->getItem($vid);
        if (!$getVidsList->isHit()) {
            return json_encode([''], JSON_THROW_ON_ERROR);
        }
        return $getVidsList->get();
    }

    /**
     * @param string $rvCode
     * @param mixed $docId
     * @return string[]
     */
    public function getPdfAndPath(string $rvCode, mixed $docId): array
    {
        $pdf = "https://" . $rvCode . ".episciences.org" . "/" . $docId . '/pdf';
        $pathDocId = APPLICATION_PATH . '/../data/' . $rvCode . '/files/' . $docId . '/';
        $path = APPLICATION_PATH . '/../data/' . $rvCode . '/files/' . $docId . '/documents/';
        return array($pdf, $pathDocId, $path);
    }

    /**
     * @param Client $client
     * @param string $pdf
     * @param string $pathPdf
     * @param string $path
     * @param string $pathDocId
     * @return void
     * @throws GuzzleException
     */
    public function downloadPdf(Client $client, string $pdf, string $pathPdf, string $path, string $pathDocId): void
    {
        // Attempt to download the PDF file and save it directly using the 'sink' option
        $response = $client->get($pdf, ['sink' => $pathPdf]);
        // Ensure the file is only considered valid if the status code is 200
        if ($response->getStatusCode() !== 200 || !file_exists($pathPdf)) {
            $this->removeDirectory($pathPdf, $path);
        }
        $this->removePdfNotValid($pathPdf, $path, $pathDocId);
    }

    /**
     * @param string $pathPdf
     * @param string $path
     * @return void
     */
    public function removeDirectory(string $pathPdf, string $path): void
    {
        unlink($pathPdf);
        rmdir($path);
    }

    /**
     * @param string $pathPdf
     * @param string $path
     * @param string $pathDocId
     * @return void
     */
    public function removePdfNotValid(string $pathPdf, string $path, string $pathDocId): void
    {
        if (!self::isValidPdf($pathPdf)) {
            unlink($pathPdf);
            rmdir($path);
            rmdir($pathDocId);
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

    /**
     * @return bool
     */
    public
    function isDryRun(): bool
    {
        return $this->_dryRun;
    }

    /**
     * @param bool $dryRun
     */
    public
    function setDryRun(bool $dryRun)
    {
        $this->_dryRun = $dryRun;
    }
}

$script = new mergePdfVol($localopts);
$script->run();
