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
    'request' => 'Request all assigned DOI of a journal'
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
     * @const int
     */
    public const MAX_DOI_TO_DECLARE_WITHOUT_CONFIRMATION = 20;
    /**
     * @var Episciences_Paper
     */
    protected Episciences_Paper $_paper;
    /**
     * @var Episciences_Review
     */
    protected Episciences_Review $_review;
    /**
     * @var Episciences_Paper_DoiQueue
     */
    protected Episciences_Paper_DoiQueue $_doiQueue;
    /**
     * @var Episciences_Review_DoiSettings
     */
    protected Episciences_Review_DoiSettings $_doiSettings;
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

    public function run()
    {
        $this->initialize();

        if ($this->getParam('paperid')) {
            $this->setPaper(Episciences_PapersManager::get($this->getParam('paperid'), false));
        }

        if ($this->getParam('rvid')) {
            $this->processReview();
        }
    }

    private function initialize(): void
    {
        $this->initApp();
        $this->initDb();
        $this->initTranslator();

    }

    private function processReview(): void
    {
        $review = Episciences_ReviewsManager::findByRvid($this->getParam('rvid'));
        $review->loadSettings();
        $this->setReview($review);
        $this->setReviewConstants();

        if (!is_dir(CACHE_PATH) && !mkdir(CACHE_PATH, 0777, true) && !is_dir(CACHE_PATH)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', CACHE_PATH));
        }

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

        if ($this->getParam('check')) {
            $this->checkDois();
            exit;
        }
    }

    private function setReviewConstants(): void
    {
        $journalCode = $this->getReview()->getCode();
        define('RVCODE', $journalCode);
        define('REVIEW_PATH', realpath(APPLICATION_PATH . '/../data/' . $journalCode) . '/');
        define('REVIEW_LANG_PATH', REVIEW_PATH . 'languages/');
        define('CACHE_PATH', CACHE_PATH_METADATA . $journalCode . '/');
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
    public function setReview(Episciences_Review $review): void
    {
        $this->_review = $review;
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
    public function setDoiSettings(Episciences_Review_DoiSettings $doiSettings): void
    {
        $this->_doiSettings = $doiSettings;
    }

    /**
     * @param string $paperStatus
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Exception
     */
    private function assignDois(string $paperStatus): void
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
     * @param string $paperStatus
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Exception
     */
    private function requestDois(): void
    {
        $res = Episciences_Paper_DoiQueueManager::findDoisByStatus($this->getParam('rvid'), Episciences_Paper::STATUS_PUBLISHED, Episciences_Paper_DoiQueue::STATUS_ASSIGNED);


        $countOfPapers = count($res);

        if ($countOfPapers === 0) {
            echo sprintf("%sNothing to send, you may need to assign DOI before sending requests%s", PHP_EOL, PHP_EOL);
            exit;
        }

        if ($countOfPapers > self::MAX_DOI_TO_DECLARE_WITHOUT_CONFIRMATION) {
            $confirmation = $this->ask(sprintf('Please confirm requesting %d DOIs to production API', $countOfPapers), ['Yes', 'No', 'Help'], self::BASH_RED);
            if ($confirmation === 1) {
                die(sprintf("%sProcess canceled: nothing was sent.%s", PHP_EOL, PHP_EOL));
            }

            if ($confirmation === 2) {
                $this->displayHelp();
                exit;
            }
        }

        printf("Sending %d papers to API", $countOfPapers);

        foreach ($res as $doiToProcess) {

            $this->setPaper($doiToProcess['paper']);
            $this->setDoiQueue($doiToProcess['doiq']);
            echo sprintf("%sProcessing paperId: %s%s", PHP_EOL, $this->getPaper()->getPaperid(), PHP_EOL);
            $this->getMetadataFile();
            $response = $this->postMetadataFile();
            $this->updateMetadataQueue(Episciences_Paper_DoiQueue::STATUS_REQUESTED);
            echo sprintf("%sAPI Answered: %s%s", PHP_EOL, $response->getBody(), PHP_EOL);

        }
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
    public function setPaper(Episciences_Paper $paper): void
    {
        $this->_paper = $paper;
    }

    /**
     * @return void
     */
    private function getMetadataFile(): void
    {
        $paperId = $this->getPaper()->getPaperid();

        $docid = Episciences_PapersManager::getPublishedPaperId($paperId);
        if ($docid === 0) {
            echo sprintf("%sPaper ID %s is not published yet, skipping metadata request.%s", PHP_EOL, $paperId, PHP_EOL);
            return;
        }

        $paperUrl = sprintf("%spapers/export/%s/crossref?code=%s", EPISCIENCES_API_URL, $docid, $this->getReview()->getCode());
        echo PHP_EOL . 'Requesting: ' . $paperUrl;
        $client = new Client();
        try {
            $res = $client->request('GET', $paperUrl);
        } catch (GuzzleException $e) {
            echo PHP_EOL . 'Requesting: ' . $paperUrl . ' failed with error: ' . $e->getMessage();
            trigger_error($e->getMessage(), E_USER_ERROR);
        }
        file_put_contents($this->getMetadataPathFileName(), $res->getBody());

    }

    /**
     * @return string
     */
    private function getMetadataPathFileName(): string
    {
        return CACHE_PATH . $this->getMetadataFileName();
    }

    /**
     * @return string
     */
    private function getMetadataFileName(): string
    {
        return sprintf('%s-%s.xml', $this->getReview()->getCode(), $this->getPaper()->getPaperid());
    }

    /**
     * @return ResponseInterface
     * @throws GuzzleException
     */
    private function postMetadataFile(): ResponseInterface
    {

        if ($this->isDryRun()) {
            $apiUrl = DOI_TESTAPI;
        } else {
            $apiUrl = DOI_API;
        }
        echo PHP_EOL . 'Posting: ' . $this->getMetadataPathFileName();

        return (new Client())->request('POST', $apiUrl, [
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
                    'filename' => $this->getMetadataFileName(),
                    'contents' => fopen($this->getMetadataPathFileName(), 'rb')
                ],
            ]
        ]);
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
    public function setDryRun(bool $dryRun): void
    {
        $this->_dryRun = $dryRun;
    }

    /**
     * @param string $doiStatus
     * @return void
     */
    private function updateMetadataQueue(string $doiStatus = Episciences_Paper_DoiQueue::STATUS_REQUESTED): void
    {
        $this->getDoiQueue()->setDoi_status($doiStatus);
        Episciences_Paper_DoiQueueManager::update($this->getDoiQueue());
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
    public function setDoiQueue($doiQueue): void
    {
        $this->_doiQueue = $doiQueue;
    }

    private function checkDois()
    {
        $collectionOfDois = Episciences_Paper_DoiQueueManager::findDoisByStatus(
            $this->getReview()->getRvid(),
            Episciences_Paper::STATUS_PUBLISHED,
            Episciences_Paper_DoiQueue::STATUS_REQUESTED
        );

        foreach ($collectionOfDois as $doiData) {
            $this->processDoi($doiData);
        }
    }

    private function processDoi($doiData): void
    {
        $this->setPaper($doiData['paper']);
        $this->setDoiQueue($doiData['doiq']);

        $response = $this->requestCrossrefDoiStatus();

        if ($response === null) {
            echo 'Failed to request DOI status.' . PHP_EOL;
            return;
        }

        $status = $this->readCrossrefStatusResponse($response->getBody());

        $doiQueue = $this->getDoiQueue();

        if ((int)$status > 0 && $doiQueue->getDoi_status() !== Episciences_Paper_DoiQueue::STATUS_PUBLIC) {
            $doiQueue->setDoi_status(Episciences_Paper_DoiQueue::STATUS_PUBLIC);
            $this->updateDoiStatus($doiQueue);
        } else {
            echo sprintf("%sPaper ID # %s DOI status is: %s%s", PHP_EOL, $this->getPaper()->getPaperid(), $doiQueue->getDoi_status(), PHP_EOL);
        }
    }

    /**
     * https://doi.crossref.org/servlet/submissionDownload?usr=_username_&pwd=_password_&doi_batch_id=_doi batch id_&file_name=filename&type=_submission type_
     * @return ResponseInterface|null
     */
    private function requestCrossrefDoiStatus(): ?ResponseInterface
    {

        if ($this->isDryRun()) {
            $apiUrl = DOI_TESTAPI_QUERY;
        } else {
            $apiUrl = DOI_API_QUERY;
        }

        try {
            $doiStatus = (new Client())->request('GET', $apiUrl,
                [
                    'query' => [
                        'usr' => DOI_LOGIN,
                        'pwd' => DOI_PASSWORD,
                        'file_name' => $this->getMetadataFileName(),
                        'type' => 'result'
                    ]
                ]

            );

        } catch (GuzzleException $exception) {
            echo sprintf("%s %s%s", $exception->getMessage(), $exception->getCode(), PHP_EOL);
            $doiStatus = null;
        }

        return $doiStatus;
    }

    /**
     * @param $response
     * @return string
     */
    private function readCrossrefStatusResponse($response): string
    {
        $doi_batch_diagnostic = simplexml_load_string($response);
        return (string)$doi_batch_diagnostic->batch_data->success_count;
    }

    /**
     * Updates the DOI status of a given DOI queue.
     *
     * @param mixed $doiQueue The DOI queue to update.
     * @return void
     * @throws Exception If an error occurs during the update process.
     */
    private function updateDoiStatus($doiQueue): void
    {
        echo PHP_EOL . 'Paper ID # ' . $this->getPaper()->getPaperid() . ' Setting status to: ' . Episciences_Paper_DoiQueue::STATUS_PUBLIC;

        if (Episciences_Paper_DoiQueueManager::update($doiQueue)) {
            echo PHP_EOL . 'Paper ID # ' . $this->getPaper()->getPaperid() . ' DOI status is now: ' . Episciences_Paper_DoiQueue::STATUS_PUBLIC . PHP_EOL;
        }
    }


}


$script = new getDoi($localopts);
$script->run();
