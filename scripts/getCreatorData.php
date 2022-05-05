<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;


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

class getCreatorData extends JournalScript
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

    public function replace_accents($str): string
    {
        $str = htmlentities($str, ENT_COMPAT, "UTF-8");
        $str =  preg_replace('/&([a-zA-Z])(uml|acute|grave|circ|tilde|ring|slash);/','$1',$str);
        return html_entity_decode($str);
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
        $select = $db->select()->distinct('DOI')->from(T_PAPERS,['DOI','PAPERID','DOCID'])->where('DOI IS NOT NULL')->where('DOI != ""')->order('DOCID DESC'); // prevent empty row

        foreach($db->fetchAll($select) as $value) {
            $pathOpenAireCreator = '../data/authors/openAire/'.explode("/",$value['DOI'])[1]."_creator.json";
            echo PHP_EOL . "PAPERID " . $value['PAPERID'];
            echo PHP_EOL . "DOCID " . $value['DOCID'];
            echo PHP_EOL . "DOI " . $value['DOI'];
            //COPY PASTE AUTHOR FROM PAPER TO AUTHOR
            $paper = Episciences_PapersManager::get($value['DOCID']);
            Zend_Debug::dump($paper);
            if (empty(Episciences_Paper_AuthorsManager::getAuthorByPaperId($value['PAPERID']))) {
                $this->InsertAuthorsFromPapers($paper, $value['PAPERID']);
            }

            // CHECK IF FILE EXIST TO KNOW IF WE CALL OPENAIRE OR NOT
            if (!file_exists($pathOpenAireCreator)){
                $openAireCallArrayResp = $this->callOpenAireApi($client, $value['DOI']);
                echo PHP_EOL.'https://api.openaire.eu/search/publications/?doi=' . $value['DOI'] . '&format=json';
                // WE PUT EMPTY ARRAY IF RESPONSE IS NOT OK
                try {
                    $decodeOpenAireResp = json_decode($openAireCallArrayResp, true, 512, JSON_THROW_ON_ERROR);
                    $this->putInFileResponseOpenAireCall($decodeOpenAireResp, $value['DOI']);
                } catch (JsonException $e) {
                    // OPENAIRE CAN RETURN MALFORMED JSON SO WE LOG URL OPENAIRE
                    self::logErrorMsg($e->getMessage(). " for PAPER ". $value['PAPERID'] . ' URL called https://api.openaire.eu/search/publications/?doi=' . $value['DOI'] . '&format=json ');
                    file_put_contents($pathOpenAireCreator, [""]);
                    continue;
                }
                sleep('1');
            }
            if (file_exists($pathOpenAireCreator) && (filesize($pathOpenAireCreator) !== 0 )) {
                $fileFound = json_decode(file_get_contents($pathOpenAireCreator),true);
                $reformatFileFound = [];
                if (!array_key_exists(0,$fileFound)) {
                    $reformatFileFound[] = $fileFound;
                }else{
                    $reformatFileFound = $fileFound;
                }
                $selectAuthor = Episciences_Paper_AuthorsManager::getAuthorByPaperId($value['PAPERID']);
                foreach ($selectAuthor as $key => $authorInfo) {
                    // LOOP IN ARRAY FROM DB
                    $decodeAuthor = json_decode($authorInfo['authors'], true, 512, JSON_THROW_ON_ERROR);
                    // WE NEED TO DECODE JSON IN DB TO LOOP IN
                    foreach ($decodeAuthor as $keyDbJson => $authorFromDB) {
                        $needleFullName = $authorFromDB['fullname'];
                        $flagNewOrcid = 0;
                        // GET EACH FULLNAME TO COMPARE IN THE API ARRAY
                        foreach ($reformatFileFound as $authorInfoFromApi) {
                            // TRY TO FIND CORRESPONDING AUTHOR AND ORCID (IF EXIST)
                            [$decodeAuthor, $flagNewOrcid] = $this->getOrcidApiForDb($needleFullName, $authorInfoFromApi, $decodeAuthor, $keyDbJson, $flagNewOrcid);
                        }
                        if ($flagNewOrcid === 1) {
                            $this->insertAuthors($decodeAuthor, $value['PAPERID'], $key);
                        }

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
     * @param $decodeAuthor
     * @param $paperId
     * @param $key
     * @return void
     */
    public function insertAuthors($decodeAuthor, $paperId, $key): void
    {
        $newAuthorInfos = new Episciences_Paper_Authors();
        $newAuthorInfos->setAuthors(json_encode($decodeAuthor, JSON_FORCE_OBJECT));
        $newAuthorInfos->setPaperId($paperId);
        $newAuthorInfos->setAuthorsId($key);
        Episciences_Paper_AuthorsManager::update($newAuthorInfos);
        echo PHP_EOL . 'new Orcid for id ' . $key . ' and paper ' . $paperId. PHP_EOL;
    }

    /**
     * @param $needleFullName
     * @param $authorInfoFromApi
     * @param $decodeAuthor
     * @param $keyDbJson
     * @param int $flagNewOrcid
     * @return array
     */
    public function getOrcidApiForDb($needleFullName, $authorInfoFromApi, $decodeAuthor, $keyDbJson, int $flagNewOrcid): array
    {
        /*
         * FIRST IF PRETTY RAW SEARCHING
         * SECOND IF REPLACE ALL ACCENT IN BOTH FULLNAME
         */
        $msgLogAuthorFound = "Author Found \n Searching :\n". print_r($needleFullName, TRUE). "\n API: \n" .print_r($authorInfoFromApi, TRUE)." DB DATA:\n ".print_r($decodeAuthor,true);
        if (array_search($needleFullName, $authorInfoFromApi, false) !== false || array_search($this->replace_accents($needleFullName), $authorInfoFromApi, false)) {
            self::logErrorMsg($msgLogAuthorFound);
            if (array_key_exists("@orcid", $authorInfoFromApi) && !isset($decodeAuthor[$keyDbJson]['orcid'])) {
                $decodeAuthor[$keyDbJson]['orcid'] = $authorInfoFromApi['@orcid'];
                $flagNewOrcid = 1;
            }

        } elseif ($this->replace_accents($needleFullName) === $this->replace_accents($authorInfoFromApi['$'])) {
            self::logErrorMsg($msgLogAuthorFound);
            if (array_key_exists("@orcid", $authorInfoFromApi)) {
                $decodeAuthor[$keyDbJson]['orcid'] = $authorInfoFromApi['@orcid'];
                $flagNewOrcid = 1;
            }
        }else{
            self::logErrorMsg("No matching : API ". $authorInfoFromApi['$']. " #DB# ". $needleFullName);
        }
        //SOME LOGGING TO KNOW IF OCCURENCE WAS FOUND EACH LOOP OF ARRAYS
        if (!isset($decodeAuthor[$keyDbJson]['orcid'])){
            self::logErrorMsg("ORCID not found \n Searching :\n". print_r($needleFullName, TRUE). "\n API: \n" .print_r($authorInfoFromApi, TRUE)." DB DATA:\n ".print_r($decodeAuthor,true));
        }
        if ($flagNewOrcid === 1){
            self::logErrorMsg("ORCID found \n Searching :\n". print_r($needleFullName, TRUE). "\n API: \n" .print_r($authorInfoFromApi, TRUE)." DB DATA:\n ".print_r($decodeAuthor,true));

        }
        return array($decodeAuthor, $flagNewOrcid);
    }

    public static function logErrorMsg($msg){
        $logger = new Logger('my_logger');
        $logger->pushHandler(new StreamHandler(__DIR__ . '/creatorEnrichment.log', Logger::INFO));
        $logger->info($msg);
    }


    /**
     * @param Client $client
     * @param $doi
     * @return string
     * @throws GuzzleException
     */
    public function callOpenAireApi(Client $client, $doi): string
    {
        $openAireCallArrayResp = $client->get('https://api.openaire.eu/search/publications/?doi=' . $doi . '&format=json', [
            'headers' => [
                'User-Agent' => 'CCSD Episciences support@episciences.org',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ])->getBody()->getContents();
        return $openAireCallArrayResp;
    }

    /**
     * @param $decodeOpenAireResp
     * @param $doi
     * @return void
     * @throws JsonException
     */
    public function putInFileResponseOpenAireCall($decodeOpenAireResp, $doi): void
    {
        $pathCreator = '../data/authors/openAire/' . explode("/", $doi)[1] . "_creator.json";
        if (!is_null($decodeOpenAireResp) && array_key_exists('result', $decodeOpenAireResp['response']['results'])) {
            $creatorArrayOpenAire = $decodeOpenAireResp['response']['results']['result'][0]['metadata']['oaf:entity']['oaf:result']['creator'];
            file_put_contents($pathCreator, json_encode($creatorArrayOpenAire, JSON_THROW_ON_ERROR));
        } else {
            file_put_contents($pathCreator, [""]);
        }
    }

    /**
     * @param $paper
     * @param $paperId
     * @return void
     */
    public function InsertAuthorsFromPapers($paper, $paperId): void
    {
        $authors = $paper->getMetadata('authors');
        foreach ($authors as $author) {
            $authorsFormatted = Episciences_Tools::reformatOaiDcAuthor($author);
            [$familyName, $givenName] = explode(', ', $author);
            $arrayAuthors[] = [
                'fullname' => $authorsFormatted,
                'given' => $givenName,
                'family' => $familyName
            ];
        }

        Episciences_Paper_AuthorsManager::insert([
            [
                'authors' => json_encode($arrayAuthors, JSON_FORCE_OBJECT),
                'paperId' => $paperId
            ]
        ]);
        unset($arrayAuthors);
    }
}


$script = new getCreatorData($localopts);
$script->run();
