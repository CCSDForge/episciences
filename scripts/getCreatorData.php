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
        foreach($db->fetchAll($select) as $value) {
            echo PHP_EOL . "PAPERID " . $value['PAPERID'];
            $paper = Episciences_PapersManager::get($value['DOCID']);
            if (empty(Episciences_Paper_AuthorsManager::getAuthorByPaperId($value['PAPERID']))) {
                $authors = $paper->getMetadata('authors');
                foreach ($authors as $author) {
                    $authorsFormatted = Episciences_Tools::reformatOaiDcAuthor($author);
                    $arrayAuthors[]['fullname'] =  $authorsFormatted;

                }

                Episciences_Paper_AuthorsManager::insert([
                    [
                        'authors' => json_encode($arrayAuthors,JSON_FORCE_OBJECT),
                        'paperId' => $value['PAPERID']
                    ]
                ]);
                unset($arrayAuthors);
            }
            var_dump($value['DOI']);
            if (!file_exists('../data/authors/openAire/'.explode("/",$value['DOI'])[1]."_creator.json")){
                $openAireCallArrayResp = $client->get('https://api.openaire.eu/search/publications/?doi=' . $value['DOI'] . '&format=json',[
                    'headers' => [
                        'User-Agent' => 'CCSD Episciences support@episciences.org',
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ]
                ])->getBody()->getContents();
                echo PHP_EOL.'https://api.openaire.eu/search/publications/?doi=' . $value['DOI'] . '&format=json';
                try {
                    $decodeOpenAireResp = json_decode($openAireCallArrayResp, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    $writer = new Zend_Log_Writer_Stream('./creatorEnrichment.log');
                    $logger = new Zend_Log($writer);
                    $logger->err($e->getMessage(). " for PAPER ". $value['PAPERID'] . ' URL called https://api.openaire.eu/search/publications/?doi=' . $value['DOI'] . '&format=json ');
                    continue;
                }
                if (!is_null($decodeOpenAireResp) && array_key_exists('result', $decodeOpenAireResp['response']['results'])) {
                    $creatorArrayOpenAire = $decodeOpenAireResp['response']['results']['result'][0]['metadata']['oaf:entity']['oaf:result']['creator'];
                    file_put_contents('../data/authors/openAire/'.explode("/",$value['DOI'])[1]."_creator.json", json_encode($creatorArrayOpenAire, JSON_THROW_ON_ERROR));
                }
                sleep('1');
            }
            if (file_exists('../data/authors/openAire/'.explode("/",$value['DOI'])[1]."_creator.json")){
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
                        var_dump($needleFullName);
                        $flagNewOrcid = 0;
                        foreach ($reformatFileFound as $authorInfoFromApi) {
                            if (array_search($needleFullName, $authorInfoFromApi, false) !== false ) {
                                if (array_key_exists("@orcid", $authorInfoFromApi) && !isset($decodeAuthor[$keyDbJson]['orcid'])){
                                    $decodeAuthor[$keyDbJson]['orcid'] = $authorInfoFromApi['@orcid'];
                                    $flagNewOrcid = 1;
                                }
                            }
                        }
                        if ($flagNewOrcid === 1) {
                            $newAuthorInfos = new Episciences_Paper_Authors();
                            $newAuthorInfos->setAuthors(json_encode($decodeAuthor,JSON_FORCE_OBJECT));
                            $newAuthorInfos->setPaperId($value['PAPERID']);
                            $newAuthorInfos->setAuthorsId($key);
                            Episciences_Paper_AuthorsManager::update($newAuthorInfos);
                            echo PHP_EOL. 'new Orcid for id ' . $key . ' and paper '.$value['PAPERID'];
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

}


$script = new getCreatorData($localopts);
$script->run();
