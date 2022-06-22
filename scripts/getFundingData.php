<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;


$localopts = [
    'dry-run' => 'Work with Test API',
];


if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}
require_once "JournalScript.php";

class getFundingData extends JournalScript
{
    public const DIR = '../data';

    public const ONE_MONTH = 3600 * 24 * 31;

    /**
     * @var bool
     */
    protected bool $_dryRun = true;

    /**
     * getDoi constructor.
     * @param $localopts
     */


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
        $select = $db->select()->from(T_PAPERS, ['PAPERID', 'DOI'])->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED)->where('DOI != ?','NULL')->where('DOI != ?','')->order('REPOID DESC'); // prevent empty row
        $cache = new FilesystemAdapter('enrichmentFunding', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        foreach ($db->fetchAll($select) as $value) {
            $fileName = trim(explode("/", $value['DOI'])[1]) . "_funding.json";
            $sets = $cache->getItem($fileName);
            $sets->expiresAfter(self::ONE_MONTH);
            if (!$sets->isHit()) {
                echo PHP_EOL. $fileName. PHP_EOL;
                $openAireCallArrayResp = $this->callOpenAireApi(new Client(), trim($value['DOI']));
                echo PHP_EOL . 'https://api.openaire.eu/search/publications/?doi=' . trim($value['DOI']) . '&format=json'.PHP_EOL;
                // WE PUT EMPTY ARRAY IF RESPONSE IS NOT OK
                try {
                    $decodeOpenAireResp = json_decode($openAireCallArrayResp, true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                    $this->putInFileResponseOpenAireCall($decodeOpenAireResp, trim($value['DOI']));
                } catch (JsonException $e) {
                    // OPENAIRE CAN RETURN MALFORMED JSON SO WE LOG URL OPENAIRE
                    self::logErrorMsg($e->getMessage() . ' URL called https://api.openaire.eu/search/publications/?doi=' . $value['DOI'] . '&format=json ');
                    $sets->set(json_encode([""]));
                    $cache->save($sets);
                    continue;
                }
                sleep('1');
            }
            $cache = new FilesystemAdapter('enrichmentFunding', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
            $sets = $cache->getItem($fileName);
            $sets->expiresAfter(self::ONE_MONTH);
            $fileFound = json_decode($sets->get() , true, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $globalfundingArray = [];
            if (!empty($fileFound[0])) {
                $fundingArray = [];
                foreach ($fileFound as $openAireKey => $valueOpenAire){
                    if(array_key_exists('to', $valueOpenAire) && array_key_exists('@type', $valueOpenAire['to']) && $valueOpenAire['to']['@type'] === "project") {
                        if (array_key_exists('title',$valueOpenAire)){
                            $fundingArray['projectTitle'] = $valueOpenAire['title']['$'];
                        }
                        if (array_key_exists('acronym',$valueOpenAire)){
                            $fundingArray['acronym'] = $valueOpenAire['acronym']['$'];
                        }
                        if (array_key_exists( 'funder',$valueOpenAire['funding'])){
                            $fundingArray['funderName']  = $valueOpenAire['funding']['funder']['@name'];
                        }
                        if (array_key_exists( 'code',$valueOpenAire)){
                            $fundingArray['code']  = $valueOpenAire['code']['$'];
                        }

                        $globalfundingArray[] = $fundingArray;

                    }
                }
                if (!empty($globalfundingArray)){
                    Episciences_Paper_ProjectsManager::insert(
                        [
                            'funding'=>json_encode($globalfundingArray,JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                            'paperId'=> $value['PAPERID'],
                            'source_id' => Episciences_Repositories::GRAPH_OPENAIRE_ID
                        ]
                    );
                    $this->displayInfo('Project Founded '.json_encode($globalfundingArray,JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), true);
                }
            }
        }
        $this->displayInfo('Funding Data Enrichment completed. Good Bye ! =)', true);

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
    public function callOpenAireApi(Client $client, $doi): string
    {

        $openAireCallArrayResp = '';

        try {

            return $client->get('https://api.openaire.eu/search/publications/?doi=' . $doi . '&format=json', [
                'headers' => [
                    'User-Agent' => 'CCSD Episciences support@episciences.org',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ])->getBody()->getContents();

        } catch (GuzzleException $e) {

            trigger_error($e->getMessage());

        }

        return $openAireCallArrayResp;
    }

    public function putInFileResponseOpenAireCall($decodeOpenAireResp, $doi): void
    {
        $cache = new FilesystemAdapter('enrichmentFunding', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        $fileName = trim(explode("/", $doi)[1]) . "_funding.json";
        $sets = $cache->getItem($fileName);
        if (!is_null($decodeOpenAireResp) && !is_null($decodeOpenAireResp['response']['results'])) {
            if (array_key_exists('result', $decodeOpenAireResp['response']['results'])) {
                $preFundingArrayOpenAire = $decodeOpenAireResp['response']['results']['result'][0]['metadata']['oaf:entity']['oaf:result'];
                if (array_key_exists('rels',$preFundingArrayOpenAire)) {
                    if (!empty($preFundingArrayOpenAire['rels']) && array_key_exists('rel',$preFundingArrayOpenAire['rels'])){
                        $arrayFunding = $preFundingArrayOpenAire['rels']['rel'];
                        $sets->set(json_encode($arrayFunding,JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                        $cache->save($sets);
                    }else{
                        $sets->set(json_encode([""]));
                        $cache->save($sets);
                    }
                } else {
                    $sets->set(json_encode([""]));
                    $cache->save($sets);
                }
            }
        } else {
            $sets->set(json_encode([""]));
            $cache->save($sets);
        }
    }
    public static function logErrorMsg($msg)
    {
        $logger = new Logger('my_logger');
        $logger->pushHandler(new StreamHandler(__DIR__ . '/fundingEnrichment.log', Logger::INFO));
        $logger->info($msg);
    }


}


$script = new getFundingData($localopts);
$script->run();
