<?php


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$localopts = [
    'rvcode=s' => "journal code",
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
     * @var bool
     */
    protected bool $_dryRun = true;

    /**
     * getDoi constructor.
     * @param $localopts
     */
    public CONST APICALLVOL = "volumes?page=1&itemsPerPage=1000&rvcode=";
    public CONST APICALLFORDOCID = "papers?page=1&itemsPerPage=1000&vid=";
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

        $client = new Client();
        $response = $client->get(EPISCIENCES_API_URL.self::APICALLVOL.$rvCode)->getBody()->getContents();
        $strPdf = '';
        foreach (json_decode($response, true, 512, JSON_THROW_ON_ERROR)['hydra:member'] as $res){
            $getDocIdBypaperApi = $client->get(EPISCIENCES_API_URL.self::APICALLFORDOCID.$res['vid'])->getBody()->getContents();
            $docIds = [];
            foreach (json_decode($getDocIdBypaperApi, true, 512, JSON_THROW_ON_ERROR)["hydra:member"] as $papers) {
                $docIds[$papers['paperid']] = $papers['docid'];
            }
            $this->displayInfo('Volumes '. $res['vid']."\n", true);
            $docIdList = [];
            foreach ($res['papers'] as $paper) {
                $docIdList[] =  $docIds[$paper['paperid']];
            }
            if (json_decode(self::getCacheDocIdsList($res['vid']), true, 512, JSON_THROW_ON_ERROR) !== $docIdList) {
                foreach ($res['papers'] as $paper) {
                    $docId = $docIds[$paper['paperid']];
                    $this->displayInfo('docId '. $docId."\n", true);
                    $pdf = "https://".$rvCode.".episciences.org"."/".$docId.'/pdf';
                    $pathDocId = APPLICATION_PATH.'/../data/'.$rvCode.'/files/'.$docId.'/';
                    $path = APPLICATION_PATH.'/../data/'.$rvCode.'/files/'.$docId.'/documents/';
                    if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
                        throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
                    }
                    $pathPdf = $path.$docId.".pdf";
                    if (!file_exists($pathPdf)) {
                        try {
                            // Attempt to download the PDF file and save it directly using the 'sink' option
                            $response = $client->get($pdf, ['sink' => $pathPdf]);
                            // Ensure the file is only considered valid if the status code is 200
                            if ($response->getStatusCode() !== 200 || !file_exists($pathPdf)) {
                                unlink($pathPdf);
                                rmdir($path);
                            }
                            if (!self::isValidPdf($pathPdf)) {
                                unlink($pathPdf);
                                rmdir($path);
                                rmdir($pathDocId);
                            }
                        } catch (GuzzleException $e) {
                            unlink($pathPdf);
                        }
                        if (file_exists($pathPdf)) {
                            $strPdf .= $pathPdf . ' ';
                        }
                    }
                }
                $pathPdfMerged = APPLICATION_PATH.'/../data/'.$rvCode.'/public/volume-pdf/'.$res['vid'].'/';
                if (!is_dir($pathPdfMerged) && !mkdir($pathPdfMerged, 0777, true) && !is_dir($pathPdfMerged)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $pathPdfMerged));
                }
                self::setCacheDocIdsList($res['vid'],$docIdList);
                $exportPdfPath = $pathPdfMerged.$res['vid'].'.pdf';
                $this->displayInfo('merge volume : '. $res['vid']."\n", true);
                $output = `pdfunite {$strPdf} {$exportPdfPath}`;
                echo $output;
            } else {
                $this->displayInfo('docids are the same from the API', true);
            }
        }
        $this->displayInfo('Volumes pdf fusion completed. Good Bye ! =)', true);

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
    public static function isValidPdf(string $filePath): bool
    {
        $file = fopen($filePath, 'rb');
        if (!$file) {
            return false;
        }
        $header = fread($file, 4);
        fclose($file);
        return $header === '%PDF';
    }
    public static function getCacheDocIdsList($vid) : string
    {
        $cache = new FilesystemAdapter("volume-pdf", 0 ,dirname(APPLICATION_PATH) . '/cache/');
        $getVidsList = $cache->getItem($vid);
        if (!$getVidsList->isHit()) {
            return json_encode([''], JSON_THROW_ON_ERROR);
        }
        return $getVidsList->get();
    }
    public static function setCacheDocIdsList($vid, array $jsonVidList) : void {
        $cache = new FilesystemAdapter("volume-pdf", 0 ,dirname(APPLICATION_PATH) . '/cache/');
        $setVidList = $cache->getItem($vid);
        $setVidList->set(json_encode($jsonVidList, JSON_THROW_ON_ERROR));
        $cache->save($setVidList);
    }
}
$script = new mergePdfVol($localopts);
$script->run();
