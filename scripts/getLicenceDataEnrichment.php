<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;


$localopts = [
    'paperid=i' => "Paper ID",
    'dry-run' => 'Work with Test API',
    'check' => 'Check DOI submission status',
    'rvid=i' => 'RVID of a journal',
    'assign-accepted' => 'Assign DOI to all accepted papers',
    'assign-published' => 'Assign DOI to all accepted papers',
    'request' => 'Request all assigned DOI of a journal',
    'journal-hostname=s' => 'Get XML files from an alternate journal hostname, eg: test.episciences.org'
];

if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}
require_once "JournalScript.php";

class getLicenceDataEnrichment extends JournalScript
{
    /**
     * @var Episciences_Paper
     */
    protected $_paper;

    /**
     * @var Episciences_Review
     */
    protected $_review;
    /**
     * @var Episciences_Paper_DoiQueue
     */
    protected $_doiQueue;

    /**
     * @var Episciences_Review_DoiSettings
     */
    protected $_doiSettings;

    /**
     * @var bool
     */
    protected $_dryRun = true;

    /**
     * @var string
     */
    protected $_journalHostname;

    /**
     * getDoi constructor.
     * @param $localopts
     * @throws Zend_Db_Statement_Exception
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

        $journalHostname = $this->getParam('journal-hostname');
        if ($journalHostname === null) {
            $journalHostname = '';
        }
        $this->setJournalHostname($journalHostname);

    }

    /**
     * @return mixed|void
     * @throws GuzzleException
     */
    public
    function run()
    {
        $this->initApp();
        $this->initDb();
        $this->initTranslator();
        define_review_constants();
        $client = new Client();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from('PAPERS', ['IDENTIFIER', 'DOCID', 'REPOID', 'VERSION'])->where('REPOID != ? ', 0)->where('STATUS = ?', 16)->order('REPOID DESC'); // prevent empty row
        $prefixArxiv = '10.48550/arxiv.';
        $prefixZen = '10.5281/zenodo.';
        $communUrlArXZen = 'https://api.datacite.org/dois/';

        foreach ($db->fetchAll($select) as $value) {
            $cleanID = str_replace('/','',$value['IDENTIFIER']); // ARXIV CAN HAVE "/" in ID
            // arxiv ID can have some extra no needed
            if (strpos($value['IDENTIFIER'], '.LO/')){
                $value['IDENTIFIER'] = str_replace('.LO/','/',$value['IDENTIFIER']);
            }

            $fileName = "../data/enrichmentLicences/" . $cleanID . "_licence.json";
            echo PHP_EOL . $value['IDENTIFIER'];
            if (!file_exists($fileName)) {
                switch ($value['REPOID']) {
                    case "1": //HAL
                        $url = "https://api.archives-ouvertes.fr/search/?q=((halId_s:" . $value['IDENTIFIER'] . " OR halIdSameAs_s:" . $value['IDENTIFIER'] . ") AND version_i:" . $value['VERSION'] . ")&rows=1&wt=json&fl=licence_s";
                        $callArrayResp = $client->get($url, [
                            'headers' => [
                                'User-Agent' => 'CCSD Episciences support@episciences.org',
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json'
                            ]
                        ])->getBody()->getContents();
                        echo PHP_EOL . 'CALL: ' . $url;
                        echo PHP_EOL . 'API RESPONSE ' . $callArrayResp;
                        break;
                    case "2"://ARXIV
                        $url = $communUrlArXZen . $prefixArxiv . $value['IDENTIFIER'];
                        $callArrayResp = $client->get($url, [
                            'headers' => [
                                'User-Agent' => 'CCSD Episciences support@episciences.org',
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json'
                            ]
                        ])->getBody()->getContents();
                        echo PHP_EOL . 'CALL: ' . $url;
                        //echo PHP_EOL . 'API RESPONSE ' . $callArrayResp;
                        sleep(1);
                        break;
                    case "4": //ZENODO
                        $url = $communUrlArXZen . $prefixZen . $value['IDENTIFIER'];
                        $callArrayResp = $client->get($url, [
                            'headers' => [
                                'User-Agent' => 'CCSD Episciences support@episciences.org',
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json'
                            ]
                        ])->getBody()->getContents();
                        echo PHP_EOL . 'CALL: ' . $url;
                        //echo PHP_EOL . 'API RESPONSE ' . $callArrayResp;
                        sleep(1);
                        break;
                    default: //OTHERS
                        break;
                }
                if ($value['REPOID'] === "2" || $value['REPOID'] === "4") {
                    $licenceArray = json_decode($callArrayResp, true, 512, JSON_THROW_ON_ERROR);
                    if (isset($licenceArray['data']['attributes']['rightsList'][0]['rightsUri'])) {

                        file_put_contents('../data/enrichmentLicences/' . $cleanID . "_licence.json", json_encode($licenceArray['data']['attributes']['rightsList'][0], JSON_THROW_ON_ERROR));
                        $licenceGetter = $licenceArray['data']['attributes']['rightsList'][0]['rightsUri'];

                        $licenceGetter = $this->cleanLicence($licenceGetter);
                        echo PHP_EOL . $licenceGetter;
                        Episciences_Paper_LicenceManager::insert([
                            [
                                'licence' => $licenceGetter,
                                'docId' => $value['DOCID'],
                                'sourceId' => '7'
                            ]
                        ]);
                        echo PHP_EOL . 'INSERT DONE ';
                    }else{
                        file_put_contents('../data/enrichmentLicences/' . $value['IDENTIFIER'] . "_licence.json", json_encode([""], JSON_THROW_ON_ERROR));
                    }
                } elseif ($value['REPOID'] === "1") {
                    $licenceArray = json_decode($callArrayResp, true, 512, JSON_THROW_ON_ERROR);
                    if ($licenceArray['response']['numFound'] !== 0 && array_key_exists('licence_s', $licenceArray['response']['docs'][0])) {
                        $licenceGetter = $licenceArray['response']['docs'][0]['licence_s'];
                        $licenceGetter = $this->cleanLicence($licenceGetter);
                        echo PHP_EOL . $licenceGetter;
                        file_put_contents('../data/enrichmentLicences/' . $value['IDENTIFIER'] . "_licence.json", json_encode($licenceArray['response'], JSON_THROW_ON_ERROR));
                        Episciences_Paper_LicenceManager::insert([
                            [
                                'licence' => $licenceGetter,
                                'docId' => $value['DOCID'],
                                'sourceId' => '1'
                            ]
                        ]);
                        echo PHP_EOL . 'INSERT DONE ';
                    }else{
                        file_put_contents('../data/enrichmentLicences/' . $value['IDENTIFIER'] . "_licence.json", json_encode([""], JSON_THROW_ON_ERROR));
                    }
                }
            }
        }
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

    /**
     * @return Episciences_Paper
     */
    public
    function getPaper(): Episciences_Paper
    {
        return $this->_paper;
    }

    /**
     * @param Episciences_Paper $paper
     */
    public
    function setPaper($paper)
    {
        $this->_paper = $paper;
    }

    /**
     * @return string
     */
    public function getJournalHostname(): string
    {
        return $this->_journalHostname;
    }

    /**
     * @param string $journalDomain
     */
    public function setJournalHostname(string $journalDomain)
    {
        $this->_journalHostname = $journalDomain;
    }

    /**
     * @return Episciences_Review
     */
    public
    function getReview(): Episciences_Review
    {
        return $this->_review;
    }

    /**
     * @param Episciences_Review $review
     */
    public
    function setReview($review)
    {
        $this->_review = $review;
    }

    public
    function getDoiQueue(): Episciences_Paper_DoiQueue
    {
        return $this->_doiQueue;
    }

    /**
     * @param mixed $doiQueue
     */
    public
    function setDoiQueue($doiQueue)
    {
        $this->_doiQueue = $doiQueue;
    }

    /**
     * @param $licenceGetter
     * @return array|false|mixed|string|string[]
     */
    public function cleanLicence($licenceGetter)
    {
        if (str_contains($licenceGetter, "http://")) {
            $licenceGetter = str_replace("http://", "https://", $licenceGetter);
        }
        if (substr($licenceGetter, -1) == '/') {
            $licenceGetter = substr($licenceGetter, 0, -1);
        }
        if (substr($licenceGetter, strrpos($licenceGetter, '/' )+1) === "legalcode"){
            $licenceGetter = str_replace("/legalcode", "", $licenceGetter);
        }

        return $licenceGetter;
    }

}


$script = new getLicenceDataEnrichment($localopts);
$script->run();
