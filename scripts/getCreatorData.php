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
        $select = $db->select()->distinct('DOI')->from('PAPERS',['DOI'])->where('DOI IS NOT NULL')->where('DOI != ""'); // prevent empty row
        foreach($db->fetchAll($select) as $value) {
            echo PHP_EOL . 'call : https://api.openaire.eu/search/publications/?doi='.$value['DOI'].'&format=json';
            $openAireCallArrayResp = $client->get('https://api.openaire.eu/search/publications/?doi=' . $value['DOI'] . '&format=json')->getBody()->getContents();
            $creatorArray = json_decode($openAireCallArrayResp, true, 512, JSON_THROW_ON_ERROR)['response']['results']['result'][0]['metadata']['oaf:entity']['oaf:result']['creator'];
            file_put_contents('../data/openAire/'.explode("/",$value['DOI'])[1]."_creator.json",json_encode($creatorArray));
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


$script = new getCreatorData($localopts);
$script->run();
