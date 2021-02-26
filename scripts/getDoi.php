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

class getDoi extends JournalScript
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


        if ($this->getParam('paperid')) {
            $paper = Episciences_PapersManager::get($this->getParam('paperid'), false);
            $this->setPaper($paper);
        }


        if ($this->getParam('check')) {
            $response = $this->getDoiStatus();
            echo $this->readCrossrefStatusResponse($response->getBody());
            exit;
        }


        if ($this->getParam('rvid')) {
            $review = Episciences_ReviewsManager::findByRvid($this->getParam('rvid'));
            $review->loadSettings();
            $this->setReview($review);
            define('RVCODE', $review->getCode());
            define('REVIEW_PATH', realpath(APPLICATION_PATH . '/../data/' . RVCODE) . '/');
            define('REVIEW_LANG_PATH', REVIEW_PATH . 'languages/');
            define('CACHE_PATH', REVIEW_PATH . "tmp/");

            $this->initTranslator();

            $this->setDoiSettings($review->getDoiSettings());

            if ($this->getParam('assign-accepted')) {
                $this->assignDois(Episciences_Paper::STATUS_ACCEPTED);
                exit;
            }

            if ($this->getParam('assign-published')) {
                $this->assignDois(Episciences_Paper::STATUS_PUBLISHED);
                exit;
            }
            if ($this->getParam('request')) {
                $this->requestDois();
                exit;
            }

        }


        $review = Episciences_ReviewsManager::find($this->getPaper()->getRvid());
        $this->setReview($review);
        define('RVCODE', $review->getCode());
        $doiQ = Episciences_Paper_DoiQueueManager::findByPaperId($this->getPaper()->getPaperid());
        $this->setDoiQueue($doiQ);
        $this->getMetadataFile();

        if (!$this->isDryRun()) {
            $confirmation = $this->ask('Please confirm sending to production API (Data charges may apply)', ['Yes', 'No', '¯\_(ツ)_/¯'], self::BASH_RED);
            if ($confirmation == 1) {
                die(PHP_EOL . 'Process canceled: nothing was sent.' . PHP_EOL);
            } elseif ($confirmation == 2) {
                $this->displayHelp();
                exit;
            }
        }


        $response = $this->postMetadataFile();
        $this->updateMetadataQueue();
        echo PHP_EOL . 'API Answered: ' . $response->getBody() . PHP_EOL;


    }

    /**
     * @return ResponseInterface
     * @throws GuzzleException
     * https://doi.crossref.org/servlet/submissionDownload?usr=_username_&pwd=_password_&doi_batch_id=_doi batch id_&file_name=filename&type=_submission type_
     */
    private
    function getDoiStatus(): ResponseInterface
    {

        if ($this->isDryRun()) {
            $apiUrl = DOI_TESTAPI_QUERY;
        } else {
            $apiUrl = DOI_API_QUERY;
        }


        $client = new Client();
        return $client->request('GET', $apiUrl,
            [
                'query' => ['usr' => DOI_LOGIN,
                    'pwd' => DOI_PASSWORD,
                    'file_name' => $this->getMetadataFileName(),
                    'type' => 'result'
                ]
            ]

        );
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
     * @return string
     */
    private
    function getMetadataFileName(): string
    {
        return $this->getPaper()->getPaperid() . '.xml';
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
     * @param $response
     * @return string
     */
    private function readCrossrefStatusResponse($response)
    {
        $doi_batch_diagnostic = simplexml_load_string($response);
        return (string)$doi_batch_diagnostic->record_diagnostic['status'];
    }

    /**
     * @param string $paperStatus
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Exception
     */
    private function assignDois(string $paperStatus)
    {
        $doiSettings = $this->getDoiSettings();
        $rvid = (int)$this->getParam('rvid');


        $settings['is'] = [
            'rvid' => $rvid,
            'status' => $paperStatus
        ];

        $papers = Episciences_PapersManager::getList($settings);
        $countOfPapers = count($papers);
        echo $countOfPapers . ' papers';
        $paperNumber = 1;

        foreach ($papers as $p) {
            /** @var $p Episciences_Paper */
            if (empty($p->getDoi()) && ($rvid === $p->getRvid())) {
                $doi = $doiSettings->createDoiWithTemplate($p);
                $p->setDoi($doi);
                $p->save();
                $doiQ = new Episciences_Paper_DoiQueue(['paperid' => $p->getPaperId(), 'doi_status' => Episciences_Paper_DoiQueue::STATUS_ASSIGNED]);
                try {
                    Episciences_Paper_DoiQueueManager::add($doiQ);
                    $paperNumber++;

                } catch (Exception $exception) {
                    if ((int)$exception->getCode() !== 23000) {
                        echo $exception->getMessage();
                    }
                }

                printf(PHP_EOL . 'Assigned %s to %s. ', $doi, $p->getPaperId());
                printf('-> paper %d/%d' . PHP_EOL, $paperNumber, $countOfPapers);
            }

        }
    }

    /**
     * @return Episciences_Review_DoiSettings
     */
    public function getDoiSettings(): Episciences_Review_DoiSettings
    {
        return $this->_doiSettings;
    }

    /**
     * @param Episciences_Review_DoiSettings $doiSettings
     */
    public function setDoiSettings(Episciences_Review_DoiSettings $doiSettings)
    {
        $this->_doiSettings = $doiSettings;
    }

    /**
     * @param string $paperStatus
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Exception
     */
    private function requestDois()
    {
        $res = Episciences_Paper_DoiQueueManager::findDoisToRequest($this->getParam('rvid'));


        $countOfPapers = count($res);

        if ($countOfPapers === 0) {
            echo PHP_EOL . 'Nothing to send, you may need to assign DOI before sending requests' . PHP_EOL;
            exit;
        }

        if (!$this->isDryRun()) {
            $confirmation = $this->ask(sprintf('Please confirm requesting %d DOIs to production API (Data charges may apply)', $countOfPapers), ['Yes', 'No', '¯\_(ツ)_/¯'], self::BASH_RED);
            if ($confirmation == 1) {
                die(PHP_EOL . 'Process canceled: nothing was sent.' . PHP_EOL);
            } elseif ($confirmation == 2) {
                $this->displayHelp();
                exit;
            }
        }

        printf("Sending %d papers to API", $countOfPapers);

        foreach ($res as $doiToProcess) {

            $this->setPaper($doiToProcess['paper']);
            $this->setDoiQueue($doiToProcess['doiq']);
            echo PHP_EOL . 'Processing paperId: ' . $this->getPaper()->getPaperid() . PHP_EOL;
            $this->getMetadataFile();
            $response = $this->postMetadataFile();
            $this->updateMetadataQueue(Episciences_Paper_DoiQueue::STATUS_REQUESTED);
            echo PHP_EOL . 'API Answered: ' . $response->getBody() . PHP_EOL;

        }
    }

    /**
     * @return mixed
     */
    private
    function getMetadataFile()
    {

        $paperUrl = HTTP . '://' . $this->getJournalUrl() . '/' . $this->getPaper()->getPaperid() . '/' . mb_strtolower(DOI_AGENCY);
        echo PHP_EOL . 'Requesting: ' . $paperUrl;
        $client = new Client();
        try {
            $res = $client->request('GET', $paperUrl);
        } catch (GuzzleException $e) {
            error_log($e->getMessage());
        }

        return file_put_contents($this->getMetadataPathFileName(), $res->getBody());
    }

    /**
     * @return string
     */
    private
    function getJournalUrl()
    {
        if ($this->getJournalHostname() != '') {
            return $this->getJournalHostname();
        }
        return $this->getReview()->getCode() . '.' . DOMAIN;
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

    /**
     * @return string
     */
    private
    function getMetadataPathFileName(): string
    {
        return CACHE_PATH . $this->getMetadataFileName();
    }

    /**
     * @return ResponseInterface
     * @throws GuzzleException
     */
    private
    function postMetadataFile(): ResponseInterface
    {

        if ($this->isDryRun()) {
            $apiUrl = DOI_TESTAPI;
        } else {
            $apiUrl = DOI_API;
        }

        $client = new Client();
        return $client->request('POST', $apiUrl, [
            'multipart' => [
                [
                    'name' => 'operation',
                    'contents' => 'doMDUpload'
                ],
                [
                    'name' => 'login_id',
                    'contents' => DOI_LOGIN
                ],
                [
                    'name' => 'login_passwd',
                    'contents' => DOI_PASSWORD,
                ],
                [
                    'name' => 'fname',
                    'contents' => fopen($this->getMetadataPathFileName(), 'rb')
                ],
            ]
        ]);
    }

    /**
     * @return int
     */
    private
    function updateMetadataQueue($doiStatus = Episciences_Paper_DoiQueue::STATUS_REQUESTED): int
    {
        $this->getDoiQueue()->setDoi_status($doiStatus);
        return Episciences_Paper_DoiQueueManager::update($this->getDoiQueue());
    }

    /**
     * @return Episciences_Paper_DoiQueue
     */
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


$script = new getDoi($localopts);
$script->run();
