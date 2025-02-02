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

class buildDoajVolumeExport extends JournalScript
{
    public const APICALLVOL = "volumes?page=1&itemsPerPage=1000&rvcode=";

    protected bool $_dryRun = true;
    private Logger $logger;
    private Client $client;

    public function __construct($localopts)
    {
        $loggerName = 'doajVolumeExports';
        $this->logger = new Logger($loggerName);
        $this->logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . $loggerName . '.log', Logger::INFO));

        $this->setRequiredParams([]);
        $this->setArgs(array_merge($this->getArgs(), $localopts));
        $guzzleOptions = [
            'headers' => ['User-Agent' => EPISCIENCES_USER_AGENT,]
        ];
        $this->client = new Client($guzzleOptions);
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
                $cache = new FilesystemAdapter("doaj-volume-export-" . $rvCode, 0, CACHE_PATH_METADATA);
                $cache->clear();
                $this->logger->info("Cache cleared for RV code: $rvCode");
            }

            $volumeList = $this->getVolumeList($rvCode);


            foreach ($volumeList as $oneVolume) {
                $this->mergeDoajExportFromVolume($oneVolume, $rvCode);
            }

            $this->logger->info('Volumes Export completed.');
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

        $apiUrl = EPISCIENCES_API_URL . self::APICALLVOL . $rvCode;
        $this->logger->info("Fetching Volumes from $apiUrl");
        $response = $this->client->get($apiUrl)->getBody()->getContents();
        return json_decode($response, true, 512, JSON_THROW_ON_ERROR)['hydra:member'];
    }

    /**
     * @throws JsonException
     */
    private function mergeDoajExportFromVolume(mixed $res, string $rvCode): void
    {
        $listOfDoajExportsToMerge = [];
        $paperIdCollection = self::getPaperIdCollection($res['papers']);

        $docIdCollection = $this->getDocIdsSortedByPosition($paperIdCollection);

        $volumeId = $res['vid'];

        if ($this->getParam('ignorecache') === '1' || (string)(json_decode(self::getCacheDocIdsList($volumeId, $rvCode), true, 512, JSON_THROW_ON_ERROR) !== $paperIdCollection)) {
            foreach ($docIdCollection as $docId) {
                $listOfDoajExportsToMerge[] = $this->fetchDoajExport($docId, $this->client);

            }
            $pathDoajVolumeDir = sprintf("%s/../data/%s/public/volume-doaj/%s/", APPLICATION_PATH, $rvCode, $volumeId);
            if (!is_dir($pathDoajVolumeDir) && !mkdir($pathDoajVolumeDir, 0777, true) && !is_dir($pathDoajVolumeDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $pathDoajVolumeDir));
            }
            self::setCacheDocIdsList((string)$volumeId, $paperIdCollection, $rvCode);
            $exportDoajVolumePath = $pathDoajVolumeDir . $volumeId . '.xml';


            $stringOfDoajTexts = '<?xml version="1.0"?>' . PHP_EOL . '<records>' . PHP_EOL . implode(PHP_EOL, $listOfDoajExportsToMerge) . PHP_EOL . '</records>';
            file_put_contents($exportDoajVolumePath, $stringOfDoajTexts);

            $this->logger->info("Creating DOAJ volume export", ['VolId' => $volumeId]);
            $this->logger->info('Wrote XML file', [$exportDoajVolumePath]);
        } else {
            $this->logger->info("DocIds are the same from the API", ['Volume' => $volumeId]);
        }
    }

    public static function getPaperIdCollection($data): array
    {
        return array_column($data, 'paperid');
    }

    public function getDocIdsSortedByPosition(array $paperIdCollection): array
    {
        $docidCollection = [];
        foreach ($paperIdCollection as $paperId) {
            $response = $this->client->get(EPISCIENCES_API_URL . 'papers/' . $paperId)->getBody()->getContents();
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
        $cache = new FilesystemAdapter("doaj-volume-export-" . $rvCode, 0, CACHE_PATH_METADATA);
        $getVidsList = $cache->getItem($vid);
        if (!$getVidsList->isHit()) {
            return json_encode([''], JSON_THROW_ON_ERROR);
        }
        return $getVidsList->get();
    }

    public function fetchDoajExport(mixed $docId): string
    {
        $this->logger->info("Processing", ['docId' => $docId]);
        $doajText = $this->downloadDoajExport($docId);
        return str_replace(['<?xml version="1.0"?>', '<records>', '</records>'], '', $doajText);
    }

    public function downloadDoajExport(string $docId): string
    {
        $doajText = '';
        $urlApi = sprintf(EPISCIENCES_API_URL . 'papers/export/%s/doaj', $docId);
        try {
            $doajText = $this->client->get($urlApi)->getBody()->getContents();
            $this->logger->info("Downloaded", ['DOAJ' => $docId]);
        } catch (GuzzleException $e) {
            $this->logger->info("Failed to download", [$e->getMessage()]);
        }
        return $doajText;
    }

    public static function setCacheDocIdsList($vid, array $jsonVidList, string $rvCode): void
    {
        $cache = new FilesystemAdapter("doaj-volume-export-" . $rvCode, 0, CACHE_PATH_METADATA);
        $setVidList = $cache->getItem($vid);
        $setVidList->set(json_encode($jsonVidList, JSON_THROW_ON_ERROR));
        $cache->save($setVidList);
    }

    /*    public function retrieveJournalCodes($itemsPerPage = 30)
        {
            $page = 1;
            $allCodes = [];
            try {
                do {
                    $response = $this->client->request('GET', EPISCIENCES_API_URL . 'journals/', [
                        'query' => [
                            'page' => $page,
                            'itemsPerPage' => $itemsPerPage,
                            'pagination' => 'false'
                        ]
                    ]);

                    $journals = json_decode($response->getBody(), true);

                    $codes = array_column($journals, 'code');
                    $allCodes = array_merge($allCodes, $codes);

                    $page++;
                } while (count($journals) === $itemsPerPage);

                return $allCodes;
            } catch (RequestException $e) {
                $this->logger->error($e->getMessage(), [$e->getCode()]);
                return [];
            } catch (GuzzleException $e) {
                $this->logger->error($e->getMessage(), [$e->getCode()]);
                return [];
            }
        }*/


}

$script = new buildDoajVolumeExport($localopts);
$script->run();
