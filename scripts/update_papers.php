<?php
/**
 * Import or updates papers
 */

use GuzzleHttp\Client;

$localopts = [
    'repoid|r=i' => "repository id",
    'rvid=i' => "journal id",
    'identifier|i=s' => "paper external id",
    'docid=i' => "paper docid (for updating an existing paper)",
    'file=s' => "csv file path (for multiple imports)",
    'status=i' => "paper status id (default: accepted)",
    'version=i' => "paper version",
    'vid=i' => "volume id",
    'sid=i' => "section id",
    'uid=i' => "contributor id (default: randomly pick a chief editor)"
];

if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}

session_start();
require_once "JournalScript.php";

/**
 * Class UpdatePapers
 * script for updating Episciences Papers
 */
class UpdatePapers extends JournalScript
{
    // csv file column positions
    const COL_IDENTIFIER = 0;
    const COL_REPOID = 1;
    const COL_VERSION = 2;
    const COL_STATUS = 3;
    const COL_VOLUME = 4;
    const COL_SECTION = 5;
    const COL_UID = 6;
    const COL_PUBLICATION_DATE = 7;
    const COL_EDITORS = 8;
    const COL_DOI = 9;
    const COL_DOCID = 10;
    const COL_RVID = 11;
    const COL_SUBMISSION_DATE = 12;

    /** @var $_review Episciences_Review */
    protected $_review = null;

    public function __construct($localopts)
    {
        // missing required parameters will be asked later
        $this->setRequiredParams([]);

        $this->displayTrace('*** Updating papers', true);
        $this->setArgs(array_merge($this->getArgs(), $localopts));

        parent::__construct();


    }

    public function run()
    {
        $this->checkAppEnv();
        $this->initApp();
        $this->initDb();
        $this->initTranslator();
        $this->checkImportMethod();
        $this->checkRvid();
        $this->_review = Episciences_ReviewsManager::find($this->getParam('rvid'));
        $this->checkRepoId();
        defineJournalConstants($this->_review->getCode());

        // load review translation files
        if (is_dir(REVIEW_PATH . 'languages') && count(scandir(REVIEW_PATH . 'languages')) > 2) {
            Zend_Registry::get("Zend_Translate")->addTranslation(REVIEW_PATH . 'languages');
        }

        if ($this->hasParam('file')) {
            $this->process_csv_file($this->getParam('file'));
            $this->getProgressBar()->stop();
            $this->displayInfo("Update completed. Good Bye ! =)", true);
        } else {

            // check identifier (or docid)
            if (!$this->hasParam('identifier') && !$this->hasParam('docid')) {
                $method = $this->ask("How do you want to find the paper ?", ['identifier', 'docid']);
                if ($method == 0) {
                    $this->setParam('identifier', $this->ask("Enter paper identifier"));
                } else {
                    $this->setParam('docid', $this->ask("Enter paper docid"));
                }
            }

            $parameters = [
                'version' => 'Paper version',
                'vid' => 'Paper volume',
                'section' => 'Paper section',
                'doi' => 'DOI',
                'publication_date' => 'Publication date'
            ];

            foreach ($parameters as $paramKey => $paramPrompt) {
                if (!$this->hasParam($paramKey)) {
                    $input = $this->ask("$paramPrompt ? (leave blank for default)");
                    if ($input !== '') {
                        $this->setParam($paramKey, $input);
                    }
                }
            }


            $values = [
                'rvid' => $this->getParam('rvid'),
                'repoid' => $this->getParam('repoid'),
                'identifier' => $this->getParam('identifier'),
                'version' => $this->getParam('version'),
                'section' => $this->getParam('section'),
                'status' => $this->getParam('status'),
                'vid' => $this->getParam('vid'),
                'sid' => $this->getParam('sid'),
                'uid' => $this->getParam('uid'),
                'publication_date' => $this->getParam('publication_date'),
                'editors' => $this->getParam('editors'),
                'docid' => $this->getParam('docid'),
                'doi' => $this->getParam('doi'),
                'submission_date' => $this->getParam('submission_date')
            ];

            try {
                $this->process_single_paper($values);
            } catch (Zend_Exception $e) {
                $this->displayError($e->getMessage());
            }

        }
    }

    private function checkImportMethod()
    {
        // if no csv file and no paper identifier, ask to choose import method
        if (!$this->hasParam('file') && !$this->hasParam('docid') && !$this->hasParam('id')) {
            $input = $this->ask("Do you want to process a single paper, or a CSV file ?", ['single paper', 'CSV file']);

            if ($input == 1) {
                $file_path = $this->ask("Please enter the file path:");
                // check file path
                while (!file_exists($file_path)) {
                    $this->displayError("Invalid file path");
                    $file_path = $this->ask("Please enter the file path: ");
                }
                // check that file is CSV
                while (strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) !== 'csv') {
                    $file_path = $this->ask("This is not a CSV file. Please try again:");
                }

                $this->setParam('file', $file_path);
            }
        }
    }

    private function checkRepoId()
    {
        // if missing repoid, ask for it
        if (!$this->hasParam('repoid')) {

            if ($this->hasParam('file')) {
                // multiple import: repoid is optional, but can be set to a default value
                $input = $this->ask('Do you want to define a default repository id for all imports ?', ['yes', 'no']);
                $continue = ($input == 0);
            } else {
                // single import: repoid is required
                $this->displayError("Missing repository id");
                $continue = true;
            }

            // user needs to define repoid
            if ($continue) {
                $repositories = [];
                foreach (Episciences_Repositories::getRepositories() as $i => $repository) {
                    if ($i === 0) {
                        // skip Episciences repository
                        continue;
                    }
                    $repositories[$i] = $repository[Episciences_Repositories::REPO_LABEL];
                }
                $repoid = $this->ask('Please pick one of these:', $repositories);
                $this->setParam('repoid', $repoid);
            }
        }
    }

    /**
     * import or update papers from a CSV file
     * @param $path
     * @param null $max
     * @throws Zend_Exception
     */
    private function process_csv_file($path, $max = null)
    {
        $this->getProgressBar()->start();

        // check that this is a CSV file
        while (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'csv') {
            $path = $this->ask("This is not a CSV file. Please try again:");
        }
        $this->displayInfo("*** Processing CSV file");

        if (!($file = fopen($path, 'rb'))) {
            throw new Zend_Exception("File could not be opened");
        }

        // count total number of lines
        $total_lines = 0;
        while ($data = fgetcsv($file, $max, ';')) {
            $total_lines++;
        }

        $line = 0;

        $csv = fopen($path, 'rb');
        while ($data = fgetcsv($csv, $max, ';')) {

            $line++;

            // pass first line
            if (strtolower($data[0]) === 'identifier') {
                continue;
            }

            $uid =(int)$this->get_col($data, static::COL_UID);

            // prepare import
            $params = [
                'identifier' => $this->get_col($data, static::COL_IDENTIFIER),
                'repoid' => $this->get_col($data, static::COL_REPOID),
                'version' => $this->get_col($data, static::COL_VERSION),
                'status' => $this->get_col($data, static::COL_STATUS),
                'vid' => $this->get_col($data, static::COL_VOLUME),
                'sid' => $this->get_col($data, static::COL_SECTION),
                'uid' => $uid,
                'publication_date' => $this->get_col($data, static::COL_PUBLICATION_DATE),
                'editors' => $this->get_col($data, static::COL_EDITORS),
                'doi' => $this->get_col($data, static::COL_DOI),
                'docid' => $this->get_col($data, static::COL_DOCID),
                'rvid' => ($this->get_col($data, static::COL_RVID)) ?: $this->getParam('rvid'),
                'submission_date' => $this->get_col($data, static::COL_SUBMISSION_DATE) ?: date('Y-m-d H:i:s'),
                'uuid' => Episciences_UserManager::getUuidFromUid($uid)
            ];


            $this->displayInfo("** processing line $line/$total_lines");

            try {
                $this->process_single_paper($params);
            } catch (Zend_Exception $e) {
                $this->displayError($e->getMessage());
            }

            // set progress bar
            $progress = round(($line * 100) / $total_lines);
            $this->getProgressBar()->setProgress($progress);
            $this->displayProgressBar();
        }
    }

    private function get_col($data, $col)
    {
        $value = null;
        if (array_key_exists($col, $data) && trim($data[$col]) != '') {
            $value = trim($data[$col]);
        }

        return $value;
    }

    private function process_single_paper($params)
    {
        // try to find matching papers, so we know if this is an update or a new import
        $matching_papers = $this->getMatchingPapers($params['identifier'], $params['docid'], $params['rvid']);
        $identifier_string = ($params['identifier']) ?: $params['docid'];

        // check if update or new import, and init paper object
        // no matching papers, this is a new import
        if (count($matching_papers) === 0) {
            $isAnUpdate = false;
            $this->displayInfo("** importing paper " . $identifier_string, false);
            $paper = new Episciences_Paper();
        } // one matching paper, this is an update
        elseif (count($matching_papers) === 1) {
            $isAnUpdate = true;
            $this->displayInfo("** updating paper " . $identifier_string, false);
            $docid = array_shift($matching_papers)['DOCID'];
            $paper = Episciences_PapersManager::get($docid);
            if (!$paper) {
                throw new Zend_Exception("Paper #" . $docid . " not found");
            }
        } // multiple matching papers, DAMN!
        else {
            throw new Zend_Exception("can not update paper " . $params['identifier'] . ": multiple matching papers (" . implode(',', array_keys($matching_papers)) . ')');
        }


        $editors_uids = $params['editors'];

        // process input params
        $params = $this->processInputParams($params, $paper, $isAnUpdate);

        // set paper options
        $paper->setOptions($params);
        $paper->setFlag('imported');
        $id = $paper->getIdentifier();
        $version = $paper->getVersion();

        // load paper metadata
        $metadata = Episciences_Submit::getDoc($paper->getRepoid(), $id, $version, null, false);
        if (!$metadata || $metadata['status'] == 0) {
            throw new Zend_Exception("metadata not found for: " . $paper->getRepoid() . ' - ' . $paper->getIdentifier() . ' - v' . $paper->getVersion());
        }
        // set paper xml record
        $paper->setRecord($metadata['record']);

        // if uid is undefined, set default contributor uid (pick one of the chief editors)
        if (!$paper->getUid()) {
            $contributor_uid = $this->getDefaultUid();
            $paper->setUid($contributor_uid);
            $this->displayInfo("contributor uid has been set to: " . $contributor_uid);
        }

        // if status is "published", set publication date
        if ($paper->isPublished()) {
            $new_publication_date = $this->getPublicationDate($params, $paper);
            if ($new_publication_date != $paper->getPublication_date()) {
                $paper->setPublication_date($new_publication_date);
                $this->displayInfo("publication date has been set to: " . $new_publication_date);
            }
            $publishedPaperType[Episciences_Paper::TITLE_TYPE] = Episciences_Paper::ARTICLE_TYPE_TITLE;
            $paper->setType($publishedPaperType);
        }

        if (!$this->isDebug()) {
            $this->savePaper($paper, $isAnUpdate, $editors_uids);
        }

        return true;
    }

    /**
     * return papers from database, matching a given identifier and rvid
     * @param $identifier
     * @param $docid
     * @param $rvid
     * @return array
     */
    private function getMatchingPapers($identifier, $docid, $rvid)
    {
        $sql = $this->getDb()->select()
            ->from(T_PAPERS, ['DOCID'])
            ->where('RVID = ?', $rvid);

        if ($docid) {
            $sql->where('DOCID = ?', $docid);
        } elseif ($identifier) {
            $sql->where('IDENTIFIER LIKE ?', $identifier);
        } else {
            return [];
        }

        return $this->getDb()->fetchAssoc($sql);
    }

    /**
     * process input params:
     * if this is an update, merge older params with new params
     * check required parameters
     * set default for missing parameters
     * @param array $params
     * @param $paper
     * @param $update
     * @return array $params
     * @throws Zend_Exception
     */
    private function processInputParams(array $params, $paper, $update): array
    {
        foreach ($params as $param => $value) {
            // Display params
            $message = $param . ': ' . ($value ?: 'null');
            $this->displayTrace($message, false);

            // If update, merge input params and existing paper params
            if ($update && ($value === '' || is_null($value))) {
                $method = 'get' . ucfirst(strtolower($param));
                if (method_exists($paper, $method)) {
                    $paperParam = $paper->$method();
                    if (is_array($paperParam)) {
                        foreach ($paperParam as $paramValueKey => $paramValue) {
                            $this->displayTrace("$paramValueKey param has been set with: $paramValue");
                            $params[$param][$paramValueKey] = $paramValue;
                        }
                    } else {
                        $this->displayTrace("$param param has been set with: $paperParam");
                        $params[$param] = $paperParam;
                    }
                }
            }
        }

        // Check and set default values for missing parameters
        $defaultParams = [
            'status' => Episciences_Paper::STATUS_PUBLISHED,
            'vid' => 0,
            'sid' => 0
        ];

        foreach ($defaultParams as $paramKey => $defaultValue) {
            if (!array_key_exists($paramKey, $params) || is_null($params[$paramKey])) {
                $this->displayWarning("Missing $paramKey. Setting it to default: $defaultValue.");
                $params[$paramKey] = $defaultValue;
            }
        }

        // Check required parameters
        if (!$this->hasRequiredParams($params)) {
            throw new Zend_Exception("Missing required parameters");
        }

        return $params;
    }


    private function hasRequiredParams($params): bool
    {
        $requiredParams = [
            'repoid' => 'repository id',
            'identifier' => 'identifier',
            'rvid' => 'review id'
        ];

        $errors = [];

        foreach ($requiredParams as $paramKey => $paramDesc) {
            if (!array_key_exists($paramKey, $params) || empty($params[$paramKey])) {
                $errors[] = "Missing $paramDesc.";
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->displayError($error);
            }
            return false;
        }

        return true;
    }


    /**
     * try to return a chief editor uid
     * check journal chief editors, and pick one of them, according to method parameter
     * @param string $method
     * @return int|null
     * @throws Zend_Exception
     */
    private function getDefaultUid($method = 'oldest')
    {
        // fetch chief editors
        $chiefRedactors = Episciences_Review::getChiefEditors();

        // remove root uid
        if (array_key_exists(1, $chiefRedactors)) {
            unset($chiefRedactors[1]);
        }

        // throw an exception if there is no chief editors
        if (empty($chiefRedactors)) {
            throw new Zend_Exception("Missing contributor uid. Could not pick a chief editor uid, because there isn't any chief editors !");
        }

        // get chief editor creation date
        /** @var Episciences_User $user */
        $uids = [];
        foreach ($chiefRedactors as $uid => $user) {
            $uids[$uid] = $user->getTime_registered();
        }

        // get older chief editor uid
        $uid = match ($method) {
            'random' => array_rand($uids),
            'oldest' => array_search(min($uids), $uids, true),
            default => null,
        };

        return $uid;
    }

    /**
     * return a valid publication date datetime
     * try to find it using various inputs, in this order:
     * script parameters,
     * current paper publication date (if defined, check it is valid),
     * paper xml record (dc:date)
     * current date
     */
    private function getPublicationDate($params, Episciences_Paper $paper): string
    {
        // Default publication date is now()
        $defaultPublicationDate = date("Y-m-d H:i:s");
        $publication_date = $defaultPublicationDate;


        $this->displayInfo("Trying to retrieve publication date. Default will be: " . $defaultPublicationDate);


        $dateFromArgs = $this->getPublicationDateFromArgs($params);
        $dateFromPaper = $this->getPublicationDateFromPaper($paper);
        $dateFromOai = '';
        $dateFromApi = '';

        if ($dateFromArgs !== '') {
            $publication_date = $dateFromArgs;
        } elseif ($dateFromPaper !== '') {
            $publication_date = $dateFromPaper;
        } else {
            $record = $paper->getMetadata('record');
            if ($record) {
                $dateFromOai = $this->getPublicationDateFromOAI($record);
                if ($dateFromOai !== '') {
                    $publication_date = $dateFromOai;
                }
            }

            $dateFromApi = $this->getPublicationDateFromApi($params);
            if ($dateFromApi !== '') {
                $publication_date = $dateFromApi;
            }
        }

        return $publication_date;
    }


    /**
     * try to set if from script parameter (csv or arg)
     * @param array $params
     * @return string
     */
    private function getPublicationDateFromArgs(array $params): string
    {
        $publication_date = '';
        // try to set if from script parameter (csv or arg)
        if (array_key_exists('publication_date', $params)) {
            $publication_date_tested = Episciences_Tools::getValidSQLDateTime($params['publication_date']);
            if (Episciences_Tools::isValidSQLDateTime($publication_date_tested)) {
                $publication_date = $publication_date_tested;
                $this->displayInfo(sprintf("Publication date %s retrieved from script parameter", $publication_date_tested));
            }
        }

        return $publication_date;
    }

    /**
     * @param Episciences_Paper $paper
     * @return string
     */
    private function getPublicationDateFromPaper(Episciences_Paper $paper): string
    {
        $publication_date = '';
        if ($paper->getPublication_date()) {
            $publication_date_tested = Episciences_Tools::getValidSQLDateTime($paper->getPublication_date());
            if (Episciences_Tools::isValidSQLDateTime($publication_date)) {
                $publication_date = $publication_date_tested;
                if ($this->isDebug()) {
                    $this->displayInfo("Publication date retrieved from Paper");
                }
            }

        }
        return $publication_date;
    }

    /**
     * @param string $record
     * @return string
     */
    private function getPublicationDateFromOAI(string $record): string
    {
        // try to set it from xml record
        $dc_date = Ccsd_Tools::xpath($record, '//dc:date');
        $publication_date = '';
        if ($dc_date) {
            if (is_array($dc_date)) {
                $dc_date = array_shift($dc_date);
            }
            $publication_date_tested = Episciences_Tools::getValidSQLDateTime($dc_date);
            if (Episciences_Tools::isValidSQLDateTime($publication_date)) {
                $publication_date = $publication_date_tested;
                if ($this->isDebug()) {
                    $this->displayInfo("Publication date retrieved from OAI-PMH");
                }

            }
        }
        return $publication_date;
    }

    /**
     * @param array $params
     * @return string
     * @throws Exception
     */
    private function getPublicationDateFromApi(array $params): string
    {
        $publication_date = '';
        $halId = $params['identifier'];
        $halIdVersion = $params['version'] ?? 1;

        $apiUrl = sprintf("https://api.archives-ouvertes.fr/search/?indent=true&q=halId_s:%s&fq=version_i:%s&fl=publicationDate_tdate&wt=json", $halId, $halIdVersion);

        $cHeaders = ['headers' => ['Content-type' => 'application/json']];

        $client = new Client($cHeaders);

        try {
            $response = $client->get($apiUrl);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
        }

        $apiResult = json_decode($response->getBody()->getContents(), true);

        if (isset($apiResult['response']['docs'][0]["publicationDate_tdate"])) {
            $dateFromApi = $apiResult['response']['docs'][0]['publicationDate_tdate'];
            // expecting e.g. 2000-12-10T00:00:00Z
            $pubDate = new DateTime($dateFromApi);
            $publication_date = $pubDate->format("Y-m-d H:i:s");
            if ($this->isDebug()) {
                $this->displayInfo("Publication date retrieved from API");
            }
        }

        return $publication_date;


    }

    /**
     * @param Episciences_Paper|bool $paper
     * @param bool $update
     * @param $editors_uids
     * @return void
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     */
    private function savePaper(Episciences_Paper|bool $paper, bool $update, $editors_uids): void
    {
// save paper
        if ($paper->save()) {
            $this->displaySuccess("paper was successfully saved");
            if ($update === false) {
                $paper->log(Episciences_Paper_Logger::CODE_DOCUMENT_IMPORTED, $paper->getUid(), ['status' => $paper->getStatus()]);
                $paper->log(Episciences_Paper_Logger::CODE_STATUS, $paper->getUid(), ['status' => $paper->getStatus()]);
            }

        } else {
            throw new Zend_Exception("paper could not be saved");
        }

        // save editors
        if ($editors_uids) {
            $editors_uidsArray = explode('-', $editors_uids);
            $this->processEditors($editors_uidsArray, $paper);
        }

        if ($paper->isPublished()) {
            // reindex paper
            $this->reindex($paper);
        }
    }

    private function processEditors(array $editors_uids, Episciences_Paper $paper)
    {
        if (!empty($editors_uids)) {
            foreach ($editors_uids as $uid) {
                $editor = new Episciences_Editor();
                if (!$editor->findWithCAS($uid)) {
                    $this->displayError("editor " . $uid . " does not exist");
                    continue;
                }

                $editor->setUuid(Episciences_UserManager::getUuidFromUid($editor->getUid()));

                // save editor assignment
                $aid = $paper->assign($uid, Episciences_User_Assignment::ROLE_EDITOR);
                // log editor assignment
                $paper->log(
                    Episciences_Paper_Logger::CODE_EDITOR_ASSIGNMENT,
                    EPISCIENCES_UID,
                    ["aid" => $aid, "user" => $editor->toArray()]);

                if ($paper->isPublished()) {
                    // save editor unassignment
                    $aid = $paper->unassign($uid, Episciences_User_Assignment::ROLE_EDITOR);
                    // log editor unassignment
                    $paper->log(
                        Episciences_Paper_Logger::CODE_EDITOR_UNASSIGNMENT,
                        EPISCIENCES_UID,
                        ["aid" => $aid, "user" => $editor->toArray()]);
                }
            }
        }
    }

    private function reindex(Episciences_Paper $paper)
    {
        try {
            $this->displayInfo("adding paper to index queue");
            Ccsd_Search_Solr_Indexer::addToIndexQueue([$paper->getDocid()], 'episciences', 'UPDATE', 'episciences');
            $this->displayInfo("paper added to index queue");
        } catch (Exception $e) {
            throw new Zend_Exception("paper indexation failed for " . $paper->getDocid() . ": " . $e->getMessage());
        }
    }

}

$script = new UpdatePapers($localopts);
$script->run();
