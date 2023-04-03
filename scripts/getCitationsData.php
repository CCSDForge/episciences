<?php


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;


$localopts = [
    'dry-run' => 'Work with Test API',
];


if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}
require_once "JournalScript.php";

class getCitationsData extends JournalScript
{
    const OPENCITATIONS_API_CITATIONS = 'https://opencitations.net/index/api/v1/citations/';
    const OPENCITATIONS_API_METADATA = 'https://opencitations.net/index/api/v1/metadata/';
    const OPENCITATIONS_EPISCIENCES_USER_AGENT = 'CCSD Episciences support@episciences.org';
    /**
     * @var bool
     */
    protected bool $_dryRun = true;

    /**
     * getDoi constructor.
     * @param $localopts
     */

    public const ONE_MONTH = 3600 * 24 * 31;

    public const CITATIONS_PREFIX_VALUE = "coci => ";

    public function __construct($localopts)
    {

        // missing required parameters will be asked later
        $this->setRequiredParams([]);
        $this->setArgs(array_merge($this->getArgs(), $localopts));
        parent::__construct();

        if ($this->getParam('dry-run')) {
            $this->setDryRun(true);
        } else {
            $this->setDryRun(false);
        }
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function run(): void
    {
        $this->initApp();
        $this->initDb();
        $this->initTranslator();
        define_review_constants();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db
            ->select()
            ->from(T_PAPERS, ["DOI","DOCID"])->where('DOI != ""')->where("STATUS = ? ",Episciences_Paper::STATUS_PUBLISHED)->order('DOCID DESC');
        foreach ($db->fetchAll($select) as $value) {
            $trimDoi = trim($value['DOI']);
            $fileName = $trimDoi . "_citations.json";
            $cache = new FilesystemAdapter('enrichmentCitations', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
            $sets = $cache->getItem($fileName);
            $sets->expiresAfter(self::ONE_MONTH);
            if (!$sets->isHit()) {
                $this->displayInfo('Call API Opencitations for ' . $trimDoi, true);
                $respCitationsApi = self::retrieveAllCitationsByDoi($trimDoi);
                if ($respCitationsApi !== '' && $respCitationsApi !== '[]') {
                    $sets->set($respCitationsApi);
                } else {
                    $sets->set(json_encode([""]));
                }
                $this->displayInfo('PUT CACHE CALL FOR ' . $trimDoi, true);
                $cache->save($sets);
            }
            $this->displayInfo('GET CACHE CALL FOR ' . $trimDoi, true);
            $apiCallCitationCache = json_decode($sets->get(), true, 512, JSON_THROW_ON_ERROR);
            if (!empty($apiCallCitationCache) && reset($apiCallCitationCache) !== ""){
                $globalArrayCiteDOI = []; // array of all doi which cite the doi looped
                foreach ($apiCallCitationCache as $citationsValues){
                    $globalArrayCiteDOI[] = str_replace(self::CITATIONS_PREFIX_VALUE,"",$citationsValues['citing']);
                }
                $globalInfoMetadata = [];
                $i = 0;
                foreach ($globalArrayCiteDOI as $doiWhoCite){
                    $fileNameMetadata = $doiWhoCite . "_citationsMetadatas.json";
                    $setsMetadata = $cache->getItem($fileNameMetadata);
                    $setsMetadata->expiresAfter(self::ONE_MONTH);
                    if (!$setsMetadata->isHit()) {
                        $this->displayInfo('CALL API FOR METADATA ' . $doiWhoCite, true);
                        $respCitationMetadataApi = '';
                        if (!empty($doiWhoCite)) {
                            $respCitationMetadataApi = self::getMetadataByDoiCite($doiWhoCite);
                        }
                        if ($respCitationMetadataApi !== '') {
                            $setsMetadata->set($respCitationMetadataApi);
                        } else {
                            $setsMetadata->set(json_encode([""], JSON_THROW_ON_ERROR));
                        }
                        $cache->save($setsMetadata);
                    }
                    $this->displayInfo('METADATA FOUND IN CACHE ' . $doiWhoCite, true);
                    $metadataInfoCitation = json_decode($setsMetadata->get(), true, 512, JSON_THROW_ON_ERROR);
                    if (reset($metadataInfoCitation) !== ""){
                        foreach ($metadataInfoCitation as $infoCitation) {
                            $globalInfoMetadata[$i]['author'] = $infoCitation['author'];
                            $globalInfoMetadata[$i]['year'] = $infoCitation['year'];
                            $globalInfoMetadata[$i]['title'] = $infoCitation['title'];
                            $globalInfoMetadata[$i]['source_title'] = $infoCitation['source_title'];
                            $globalInfoMetadata[$i]['volume'] = $infoCitation['volume'];
                            $globalInfoMetadata[$i]['issue'] = $infoCitation['issue'];
                            $globalInfoMetadata[$i]['page'] = $infoCitation['page'];
                            $globalInfoMetadata[$i]['doi'] = $infoCitation['doi'];
                            $globalInfoMetadata[$i]['oa_link'] = $infoCitation['oa_link'];
                            $i++;
                        }
                    }
                }
                if (!empty($globalInfoMetadata)){
                    $globalInfoMetaAsJson = json_encode($globalInfoMetadata, JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                    echo $globalInfoMetaAsJson;
                    $citationObject = new Episciences_Paper_Citations();
                    $citationObject->setCitation($globalInfoMetaAsJson);
                    $citationObject->setDocId($value['DOCID']);
                    $citationObject->setSourceId(Episciences_Repositories::OPENCITATIONS_ID);
                    if (Episciences_Paper_CitationsManager::insert([$citationObject]) >= 1){
                        $this->displayInfo('CITATION INSERTED FOR ' . $value['DOCID'], true);
                        sleep(1);
                    } else {
                        $this->displayInfo('NO CHANGING CITATIONS FOR ' . $value['DOCID'], true);
                    }
                }

            }else{
                $this->displayInfo('NO VALUE IN CACHE FOR ' . $value['DOCID'], true);
            }

        }

        $this->displayInfo('Citation Data Enrichment completed. Good Bye ! =)', true);
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
    public static function retrieveAllCitationsByDoi($doi){

        $client = new Client();
        $openCitationCall = '';
        try {
            return $client->get(self::OPENCITATIONS_API_CITATIONS . $doi, [
                'headers' => [
                    'User-Agent' => self::OPENCITATIONS_EPISCIENCES_USER_AGENT,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'authorization' => OPENCITATIONS_TOKEN
                ]
            ])->getBody()->getContents();
        } catch (GuzzleException $e) {
            trigger_error($e->getMessage());
        }
        return $openCitationCall;
    }
    public static function getMetadataByDoiCite($doi){

        $client = new Client();
        $openCitationMetadataCall = '';
        try {
            return $client->get(self::OPENCITATIONS_API_METADATA . $doi, [
                'headers' => [
                    'User-Agent' => self::OPENCITATIONS_EPISCIENCES_USER_AGENT,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'authorization' => OPENCITATIONS_TOKEN
                ]
            ])->getBody()->getContents();
        } catch (GuzzleException $e) {
            trigger_error($e->getMessage());
        }
        sleep(1);
        return $openCitationMetadataCall;
    }
}
$script = new getCitationsData($localopts);
$script->run();