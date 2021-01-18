<?php
/**
 * This script will get the document metadata file and post the content to Crossref API
 * Only Crossref is supported. However, adding another agency should be easy.
 * Use --dry-run to use the test API
 */

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

$localopts = [
    'paperid=i' => "Paper ID",
    'dry-run' => 'Dry run with Test API'
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
     * @var bool
     */
    protected $_dryRun = true;

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

        $paper = Episciences_PapersManager::get($this->getParam('paperid'), false);
        $this->setPaper($paper);
        $review = Episciences_ReviewsManager::find($this->getPaper()->getRvid());
        $this->setReview($review);
        define('RVCODE', $review->getCode());
        $doiQ = Episciences_Paper_DoiQueueManager::findByPaperId($this->getPaper()->getPaperid());
        $this->setDoiQueue($doiQ);

    }

    /**
     * @return Episciences_Paper
     */
    public function getPaper(): Episciences_Paper
    {
        return $this->_paper;
    }

    /**
     * @param Episciences_Paper $paper
     */
    public function setPaper($paper)
    {
        $this->_paper = $paper;
    }

    /**
     * @return mixed|void
     * @throws GuzzleException
     */
    public function run()
    {
        $this->initApp();
        $this->initDb();
        $this->initTranslator();
        define_review_constants();
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
     * @return mixed
     */
    private function getMetadataFile()
    {
        $paperUrl = HTTP . '://' . $this->getJournalUrl() . '/' . $this->getPaper()->getPaperid() . '/' . strtolower(DOI_AGENCY);
        $client = new Client();
        try {
            $res = $client->request('GET', $paperUrl);
        } catch (GuzzleException $e) {
            error_log($e->getMessage());
        }

        return file_put_contents($this->getMetadataFileName(), $res->getBody());
    }

    /**
     * @return string
     */
    private function getJournalUrl()
    {
        return $this->getReview()->getCode() . '.' . DOMAIN;
    }

    /**
     * @return Episciences_Review
     */
    public function getReview(): Episciences_Review
    {
        return $this->_review;
    }

    /**
     * @param Episciences_Review $review
     */
    public function setReview($review)
    {
        $this->_review = $review;
    }

    /**
     * @return string
     */
    private function getMetadataFileName(): string
    {
        return CACHE_PATH . $this->getPaper()->getPaperid() . '.xml';
    }

    /**
     * @return bool
     */
    public function isDryRun(): bool
    {
        return $this->_dryRun;
    }

    /**
     * @param bool $dryRun
     */
    public function setDryRun(bool $dryRun)
    {
        $this->_dryRun = $dryRun;
    }

    /**
     * @return \Psr\Http\Message\ResponseInterface
     * @throws GuzzleException
     */
    private function postMetadataFile(): \Psr\Http\Message\ResponseInterface
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
                    'contents' => fopen($this->getMetadataFileName(), 'rb')
                ],
            ]
        ]);
    }

    /**
     * @return int
     */
    private function updateMetadataQueue(): int
    {
        $this->getDoiQueue()->setDoi_status(Episciences_Paper_DoiQueue::STATUS_REQUESTED);
        return Episciences_Paper_DoiQueueManager::update($this->getDoiQueue());
    }

    /**
     * @return Episciences_Paper_DoiQueue
     */
    public function getDoiQueue(): Episciences_Paper_DoiQueue
    {
        return $this->_doiQueue;
    }

    /**
     * @param mixed $doiQueue
     */
    public function setDoiQueue($doiQueue)
    {
        $this->_doiQueue = $doiQueue;
    }


}


$script = new getDoi($localopts);
$script->run();
