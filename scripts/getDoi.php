<?php
/**
 * This script will get the document metadata file and post the content to Crossref API
 * Only Crossref is supported. However, adding another agency should be easy.
 * Use --dry-run to use the test API
 */

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\RequestOptions;

$localopts = [
    'paperid=i' => "Paper ID",
    'dry-run' => 'Work with Test API',
    'check' => 'Check DOI submission status',
    'rvid=i' => 'RVID of a journal',
    'rvcode=s' => 'RVCODE of a journal',
    'assign-accepted' => 'Assign DOI to all accepted papers',
    'assign-published' => 'Assign DOI to all accepted papers',
    'request' => 'Request all assigned DOI of a journal',
    'fetch-journals' => 'Fetch the list of active journals from the API'
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
     * @var Logger
     */
    protected Logger $_logger;
    /**
     * @var bool
     */
    protected bool $_verbose = false;
    private Client $httpClient;

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

        $this->setDryRun((bool)$this->getParam('dry-run'));
        $this->_verbose = (bool)$this->getParam('v');

        $this->_logger = new Logger('getDoi');
        $this->_logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . '/getDoi.log', Logger::DEBUG));
        if ($this->_verbose) {
            $this->_logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        $this->httpClient = new Client();
    }

    public function run(): void
    {
        $this->initialize();

        if ($this->getParam('fetch-journals')) {
            $this->fetchJournals();
            exit;
        }

        if ($this->getParam('paperid')) {
            $this->setPaper(Episciences_PapersManager::get($this->getParam('paperid'), false));
        }

        if ($this->getParam('rvid') || $this->getParam('rvcode')) {
            $this->processReview();
        }
    }

    /**
     * @throws Zend_Translate_Exception
     * @throws Zend_Locale_Exception
     */
    private function initialize(): void
    {
        $this->initApp();
        $this->initDb();
        $this->initTranslator();
    }

    private function fetchJournals(): void
    {
        $url = EPISCIENCES_API_URL . 'journals/?page=1&itemsPerPage=30&pagination=false';
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'accept' => 'application/ld+json'
                ]
            ]);
            $body = $response->getBody()->getContents();
            $targetFile = CACHE_PATH_METADATA . 'journals.json';
            file_put_contents($targetFile, $body);
            $this->_logger->info("Journals list saved to $targetFile");
        } catch (GuzzleException $e) {
            $this->_logger->error("API request failed: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->_logger->error("Unexpected error: " . $e->getMessage());
        }
    }

    private function processReview(): void
    {
        $journalIdentifier = $this->getParam('rvid') ?? $this->getParam('rvcode');

        if (empty($journalIdentifier)) {
            throw new InvalidArgumentException('You must provide either rvid or rvcode parameter');
        }

        $review = Episciences_ReviewsManager::find($journalIdentifier);
        $review->loadSettings();
        $this->setReview($review);
        $this->setReviewConstants();

        if (!is_dir(CACHE_PATH) && !mkdir(CACHE_PATH, 0777, true) && !is_dir(CACHE_PATH)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', CACHE_PATH));
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
        if (!defined('RVCODE')) {
            define('RVCODE', $journalCode);
        }

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
        $rvid = $this->getReview()->getRvid();
        $rvcode = $this->getReview()->getCode();

        $settings['is'] = [
            'rvid' => $rvid,
            'status' => $paperStatus
        ];

        $papers = Episciences_PapersManager::getList($settings);
        $countOfPapers = count($papers);
        $this->_logger->info(sprintf("%s %s papers", $rvcode, $countOfPapers));
        $paperNumber = 1;

        foreach ($papers as $p) {
            /** @var $p Episciences_Paper */
            if (empty($p->getDoi()) && ($rvid === $p->getRvid())) {
                $doi = $doiSettings->createDoiWithTemplate($p, $rvcode);
                $p->setDoi($doi);
                $p->save();
                $doiQ = new Episciences_Paper_DoiQueue(['paperid' => $p->getPaperId(), 'doi_status' => Episciences_Paper_DoiQueue::STATUS_ASSIGNED]);
                try {
                    Episciences_Paper_DoiQueueManager::add($doiQ);
                    $paperNumber++;
                } catch (Exception $exception) {
                    if ((int)$exception->getCode() !== 23000) {
                        $this->_logger->error($exception->getMessage());
                    }
                }

                $this->_logger->info(sprintf('Assigned %s to %s. ', $doi, $p->getPaperId()) . sprintf('-> paper %d/%d' . PHP_EOL, $paperNumber, $countOfPapers));
            }
        }
    }

    /**
     * @throws GuzzleException
     * @throws Zend_Db_Statement_Exception
     */
    private function requestDois(): void
    {
        $rvid = $this->getReview()->getRvid();
        $rvcode = $this->getReview()->getCode();
        $res = Episciences_Paper_DoiQueueManager::findDoisByStatus($rvid, Episciences_Paper::STATUS_PUBLISHED, Episciences_Paper_DoiQueue::STATUS_ASSIGNED);

        $countOfPapers = count($res);

        if ($countOfPapers === 0) {
            $this->_logger->info(sprintf("%s Task list empty", $rvcode));
            exit;
        }

        if ($countOfPapers > self::MAX_DOI_TO_DECLARE_WITHOUT_CONFIRMATION) {
            $confirmation = $this->ask(sprintf('Please confirm requesting %d DOIs to production API', $countOfPapers), ['Yes', 'No', 'Help'], self::BASH_RED);
            if ($confirmation === 1) {
                die("Process canceled: nothing was sent.");
            }

            if ($confirmation === 2) {
                $this->displayHelp();
                exit;
            }
        }

        $this->_logger->info(sprintf("$rvcode Sending %d papers to API", $countOfPapers));

        foreach ($res as $doiToProcess) {
            $this->setPaper($doiToProcess['paper']);
            $this->setDoiQueue($doiToProcess['doiq']);
            $this->_logger->info(sprintf("%s Processing paperId: %s", $rvcode, $this->getPaper()->getPaperid()));
            $this->getMetadataFile();
            $response = $this->postMetadataFile();
            $this->updateMetadataQueue(Episciences_Paper_DoiQueue::STATUS_REQUESTED);
            $this->_logger->info(sprintf("%s API Answered: %s", $rvcode, $response->getBody()));
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
            $this->_logger->info(sprintf("Paper ID %s is not published yet, skipping metadata request.", $paperId));
            return;
        }

        $paperUrl = sprintf("%spapers/export/%s/crossref?code=%s", EPISCIENCES_API_URL, $docid, $this->getReview()->getCode());
        $this->_logger->info('Requesting: ' . $paperUrl);
        try {
            $res = $this->httpClient->request('GET', $paperUrl);
            file_put_contents($this->getMetadataPathFileName(), $res->getBody());
        } catch (GuzzleException $e) {
            $this->_logger->error('Requesting: ' . $paperUrl . ' failed with error: ' . $e->getMessage());
            trigger_error($e->getMessage(), E_USER_ERROR);
        }
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
        $apiUrl = $this->isDryRun() ? DOI_TESTAPI : DOI_API;
        $this->_logger->info('Posting: ' . $this->getMetadataPathFileName());

        return $this->httpClient->request('POST', $apiUrl, [
            RequestOptions::MULTIPART => [
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
        $rvid = $this->getReview()->getRvid();
        $collectionOfDois = Episciences_Paper_DoiQueueManager::findDoisByStatus(
            $rvid,
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
            $this->_logger->error('Failed to request DOI status.');
            return;
        }

        $status = $this->readCrossrefStatusResponse($response->getBody());

        $doiQueue = $this->getDoiQueue();

        if ((int)$status > 0 && $doiQueue->getDoi_status() !== Episciences_Paper_DoiQueue::STATUS_PUBLIC) {
            $doiQueue->setDoi_status(Episciences_Paper_DoiQueue::STATUS_PUBLIC);
            $this->updateDoiStatus($doiQueue);
        } else {
            $this->_logger->info(sprintf("Paper ID # %s DOI status is: %s", $this->getPaper()->getPaperid(), $doiQueue->getDoi_status()));
        }
    }

    /**
     * https://doi.crossref.org/servlet/submissionDownload?usr=_username_&pwd=_password_&doi_batch_id=_doi batch id_&file_name=filename&type=_submission type_
     * @return ResponseInterface|null
     */
    private function requestCrossrefDoiStatus(): ?ResponseInterface
    {
        $apiUrl = $this->isDryRun() ? DOI_TESTAPI_QUERY : DOI_API_QUERY;

        try {
            return $this->httpClient->request('GET', $apiUrl, [
                'query' => [
                    'usr' => DOI_LOGIN,
                    'pwd' => DOI_PASSWORD,
                    'file_name' => $this->getMetadataFileName(),
                    'type' => 'result'
                ]
            ]);
        } catch (GuzzleException $exception) {
            $this->_logger->error(sprintf("%s %s", $exception->getMessage(), $exception->getCode()));
            return null;
        }
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
        $paperId = $this->getPaper()->getPaperid();
        $this->_logger->info("Paper ID # $paperId Setting status to: " . Episciences_Paper_DoiQueue::STATUS_PUBLIC);

        if (Episciences_Paper_DoiQueueManager::update($doiQueue)) {
            $this->_logger->info("Paper ID # $paperId DOI status is now: " . Episciences_Paper_DoiQueue::STATUS_PUBLIC);
        }
    }
}

$script = new getDoi($localopts);
$script->run();
