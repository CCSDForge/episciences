<?php
/**
 * This script will get the document metadata file and post the content to Crossref API
 * Only Crossref is supported. However, adding another agency should be easy.
 * Use --dry-run to use the test API
 */

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

class getLinkData extends JournalScript
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
        $select = $db->select()
            ->distinct('DOI')
            ->from('PAPERS',['DOI','DOCID'])
            ->where('DOI IS NOT NULL')->limit('200');
        $docId = '';
        foreach ($db->fetchAll($select) as $value){
            $docId = $value['DOCID'];
            $fileName = '../data/scholexplorer/'.explode("/",$value['DOI'])[1].".json";

            if (!file_exists($fileName)){
                $apiResult = $client->get("http://api.scholexplorer.openaire.eu/v1/linksFromPid?pid=".$value['DOI'],[
                    'headers' => [
                        'User-Agent' => 'CCSD Episciences support@episciences.org',
                        'Content-Type' => 'application/json',
                        'Accept'=> 'application/json'
                    ]
                ])->getBody()->getContents();
                if (!empty(json_decode($apiResult, true, 512, JSON_THROW_ON_ERROR)) ) {
                    file_put_contents($fileName,$apiResult);
                }
            }
            if (file_exists($fileName)) {
                echo PHP_EOL . 'Found: ' . $value['DOI'];
                $arrayResult = json_decode(file_get_contents($fileName), true, 512, JSON_THROW_ON_ERROR);
                $relationship = $arrayResult[0]['relationship']['name'];
                $targetString = json_encode($arrayResult[0]['target'], JSON_THROW_ON_ERROR);
                $lastMetatextInserted = Episciences_Paper_DatasetsMetadataManager::insert(['metatext'=>$targetString]);

                foreach ($arrayResult[0]['target']['identifiers'] as $key => $identifier) {
                    try {
                        $enrichment = Episciences_Paper_DatasetsManager::insert([[
                            'docId'=> $docId,
                            'code' => "null",
                            'name' => $identifier['schema'],
                            'value' => $identifier['identifier'],
                            'link' => $identifier['schema'],
                            'sourceId'=> '5',
                            'relationship' => $relationship,
                            'idPaperDatasetsMeta'=> $lastMetatextInserted

                        ]]);
                    }catch (Exception $e){
                        var_dump('data existing '.$e->getMessage());
                        continue;
                    }
                    echo PHP_EOL . 'DB info inserted for ' . $value['DOI'];
                }
            } else {
                echo PHP_EOL . 'No match: ' . $value['DOI'];
            }

            sleep(1);
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


$script = new getLinkData($localopts);
$script->run();
