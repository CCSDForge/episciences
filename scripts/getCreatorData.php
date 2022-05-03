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
        $select = $db->select()->distinct('DOI')->from('PAPERS',['DOI','PAPERID','DOCID'])->where('DOI IS NOT NULL')->where('DOI != ""')->order('DOCID ASC'); // prevent empty row
        $i = 0;
        foreach($db->fetchAll($select) as $value) {
            echo PHP_EOL . "PAPERID " . $value['PAPERID'];
            $paper = Episciences_PapersManager::get($value['DOCID']);
            if (empty(Episciences_Paper_AuthorsManager::getAuthorByPaperId($value['PAPERID']))) {
                $this->InsertAuthorsFromPapers($paper, $value['PAPERID']);
            }
            var_dump($value['DOI']);
            if (!file_exists('../data/authors/openAire/'.explode("/",$value['DOI'])[1]."_creator.json")){
                $openAireCallArrayResp = $this->callOpenAireApi($client, $value['DOI']);
                echo PHP_EOL.'https://api.openaire.eu/search/publications/?doi=' . $value['DOI'] . '&format=json';
                try {
                    $decodeOpenAireResp = json_decode($openAireCallArrayResp, true, 512, JSON_THROW_ON_ERROR);
                    $this->putInFileResponseOpenAireCall($decodeOpenAireResp, $value['DOI']);
                } catch (JsonException $e) {
                    $writer = new Zend_Log_Writer_Stream('./creatorEnrichment.log');
                    $logger = new Zend_Log($writer);
                    $logger->err($e->getMessage(). " for PAPER ". $value['PAPERID'] . ' URL called https://api.openaire.eu/search/publications/?doi=' . $value['DOI'] . '&format=json ');
                    file_put_contents('../data/authors/openAire/'.explode("/",$value['DOI'])[1]."_creator.json", [""]);
                    continue;
                }
                sleep('1');
            }
            if (file_exists('../data/authors/openAire/'.explode("/",$value['DOI'])[1]."_creator.json") && (filesize('../data/authors/openAire/'.explode("/",$value['DOI'])[1]."_creator.json") !== 0 )) {
                $fileFound = json_decode(file_get_contents('../data/authors/openAire/'.explode("/",$value['DOI'])[1]."_creator.json"),true);
                $reformatFileFound = [];
                if (!array_key_exists(0,$fileFound)) {
                    $reformatFileFound[] = $fileFound;
                }else{
                    $reformatFileFound = $fileFound;
                }
                $selectAuthor = Episciences_Paper_AuthorsManager::getAuthorByPaperId($value['PAPERID']);
                foreach ($selectAuthor as $key => $authorInfo) {
                    $decodeAuthor = json_decode($authorInfo['authors'], true, 512, JSON_THROW_ON_ERROR);
                    foreach ($decodeAuthor as $keyDbJson => $authorFromDB) {
                        $needleFullName = $authorFromDB['fullname'];
                        $flagNewOrcid = 0;
                        $allOrcidFoundApi = [];
                        $allOrcidFoundDB = [];
                        foreach ($reformatFileFound as $authorInfoFromApi) {
                            [$decodeAuthor, $flagNewOrcid] = $this->getOrcidApiForDb($needleFullName, $authorInfoFromApi, $decodeAuthor, $keyDbJson, $flagNewOrcid);
                            if (array_key_exists("@orcid", $authorInfoFromApi)) {
                                $allOrcidFoundApi[] = $authorInfoFromApi['@orcid'];
                            }
                            if (array_key_exists("orcid", $decodeAuthor)){
                                $allOrcidFoundDB[] = $decodeAuthor['orcid'];
                            }

//                            var_dump($flagOrcidMatch);
//                                $writer = new Zend_Log_Writer_Stream('./creatorEnrichment.log');
//                                $logger = new Zend_Log($writer);
//                                $logger->err("Orcid Found in api but no correspondances with authors founded in DB :\nApi :\n". print_r($authorInfoFromApi, TRUE). "DB: \n" .print_r($decodeAuthor, TRUE));
                        }
                        if (!empty($allOrcidFoundApi)){
                            if (asort($allOrcidFoundApi) === asort($allOrcidFoundDB)){

                            }
                            die;
                        }

//                        if ($flagNewOrcid === 1) {
//                            $this->insertAuthors($decodeAuthor, $value['PAPERID'], $key);
//                        }

                    }
                }
            }
            $i++;
            if ($i === 10)die;
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
        echo PHP_EOL . 'new Orcid for id ' . $key . ' and paper ' . $paperId. '\n';
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
        if (array_search($needleFullName, $authorInfoFromApi, false) !== false || array_search($this->replace_accents($needleFullName), $authorInfoFromApi, false)) {
            if (array_key_exists("@orcid", $authorInfoFromApi) && !isset($decodeAuthor[$keyDbJson]['orcid'])) {
                $decodeAuthor[$keyDbJson]['orcid'] = $authorInfoFromApi['@orcid'];
                $flagNewOrcid = 1;
            }

        } elseif ($this->replace_accents($needleFullName) === $this->replace_accents($authorInfoFromApi['$'])) {
            if (array_key_exists("@orcid", $authorInfoFromApi)) {
                $decodeAuthor[$keyDbJson]['orcid'] = $authorInfoFromApi['@orcid'];
                $flagNewOrcid = 1;
            }
        }
        return array($decodeAuthor, $flagNewOrcid);
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
        if (!is_null($decodeOpenAireResp) && array_key_exists('result', $decodeOpenAireResp['response']['results'])) {
            $creatorArrayOpenAire = $decodeOpenAireResp['response']['results']['result'][0]['metadata']['oaf:entity']['oaf:result']['creator'];
            file_put_contents('../data/authors/openAire/' . explode("/", $doi)[1] . "_creator.json", json_encode($creatorArrayOpenAire, JSON_THROW_ON_ERROR));
        } else {
            file_put_contents('../data/authors/openAire/' . explode("/", $doi)[1] . "_creator.json", [""]);
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
