<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;


$localopts = [
];

if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}
require_once "JournalScript.php";


class getCreatorData extends JournalScript
{

    public const ONE_MONTH = 3600 * 24 * 31;


    /**
     * @var bool
     */
    protected $_dryRun = true;

    /**
     * getCreatorData constructor.
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


    /**
     * @return mixed|void
     * @throws JsonException
     * @throws Zend_Db_Statement_Exception
     */
    public
    function run()
    {
        $this->initApp();
        $this->initDb();
        $this->initTranslator();
        define_review_constants();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->distinct('PAPERID')->from(T_PAPERS, ['DOI', 'PAPERID', 'DOCID','IDENTIFIER','REPOID','VERSION'])->order('DOCID DESC'); // prevent empty row

        foreach ($db->fetchAll($select) as $value) {
            $paperId = $value['PAPERID'];
            $info = PHP_EOL . "PAPERID " . $paperId;
            $info .= PHP_EOL . "DOCID " . $value['DOCID'];
            $identifier = trim($value['IDENTIFIER']);
            $version = (int) trim($value['VERSION']);
            $this->displayInfo($info, true);
            $doiTrim = trim($value['DOI']);
            if (empty($doiTrim)) {
                $this->displayTrace('EMPTY DOI', true);
                $this->displayInfo('COPY PASTE AUTHOR FROM PAPER TO AUTHOR', true);

                //COPY PASTE AUTHOR FROM PAPER TO AUTHOR
                $paper = Episciences_PapersManager::get($value['DOCID']);
                Episciences_Paper_AuthorsManager::InsertAuthorsFromPapers($paper, $paperId);

            } else {

                $info = PHP_EOL . "DOI " . $doiTrim;

                $this->displayInfo($info, true);

                if (!empty(Episciences_Paper_AuthorsManager::getAuthorByPaperId($paperId))) {

                    echo PHP_EOL;
                    $this->displayInfo('The authors for this paper [' . $paperId . '] already exist', true);

                }

                $this->displayInfo('COPY PASTE AUTHOR FROM PAPER TO AUTHOR', true);


                //COPY PASTE AUTHOR FROM PAPER TO AUTHOR
                $paper = Episciences_PapersManager::get($value['DOCID']);

                Episciences_Paper_AuthorsManager::InsertAuthorsFromPapers($paper, $paperId);

                // CHECK IF FILE EXIST TO KNOW IF WE CALL OPENAIRE OR NOT
                // BUT BEFORE CHECK GLOBAL CACHE
                Episciences_OpenAireResearchGraphTools::checkOpenAireGlobalInfoByDoi($doiTrim, $paperId);
                ///////////////////////////////////////////////////////////////////////////////////////////////////
                $setsGlobalOARG = Episciences_OpenAireResearchGraphTools::getsGlobalOARGCache($doiTrim);
                list($cacheCreator, $pathOpenAireCreator, $setsOpenAireCreator) = Episciences_OpenAireResearchGraphTools::getCreatorCacheOA($doiTrim);


                if ($setsGlobalOARG->isHit() && !$setsOpenAireCreator->isHit()) {
                    //create cache with the global cache of OpenAire Research Graph created or not before -> ("checkOpenAireGlobalInfoByDoi")
                    // WE PUT EMPTY ARRAY IF RESPONSE IS NOT OK
                    try {
                        $decodeOpenAireResp = json_decode($setsGlobalOARG->get(), true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                        Episciences_OpenAireResearchGraphTools::putCreatorInCache($decodeOpenAireResp, $doiTrim);
                        $this->displayInfo('Create Cache from Global openAireResearchGraph cache file for ' . $doiTrim, true);
                    } catch (JsonException $e) {

                        $eMsg = $e->getMessage() . " for PAPER " . $paperId . ' URL called https://api.openaire.eu/search/publications/?doi=' . $doiTrim . '&format=json ';
                        $this->displayError($eMsg, true);

                        // OPENAIRE CAN RETURN MALFORMED JSON SO WE LOG URL OPENAIRE
                        self::logErrorMsg($eMsg);
                        $setsOpenAireCreator->set(json_encode([""]));
                        $cacheCreator->save($setsOpenAireCreator);
                        continue;
                    }
                    sleep('1');
                }

                //we need to refresh cache creator to get the new file
                ////// CACHE CREATOR ONLY
                [$cacheCreator, $pathOpenAireCreator, $setsOpenAireCreator] = Episciences_OpenAireResearchGraphTools::getCreatorCacheOA($doiTrim);

                Episciences_OpenAireResearchGraphTools::insertOrcidAuthorFromOARG($setsOpenAireCreator, $paperId);
            }
            if ($value['REPOID'] === Episciences_Repositories::HAL_REPO_ID) {
                $selectAuthor = Episciences_Paper_AuthorsManager::getAuthorByPaperId($paperId);
                $decodeAuthor = '';
                foreach ($selectAuthor as $authorsDb) {
                    $decodeAuthor = json_decode($authorsDb['authors'], true, 512, JSON_THROW_ON_ERROR);
                }
                $insertCacheTei = Episciences_Paper_AuthorsManager::getHalTei($identifier, $version);
                if ($insertCacheTei === true) {
                    $this->displayInfo('Call Hal Tei ... put in cache '. $identifier, true);
                }
                $this->displayInfo('get Hal Tei from cache '. $identifier, true);
                $cacheTeiHal = Episciences_Paper_AuthorsManager::getHalTeiCache($identifier, $version);
                if ($cacheTeiHal !== '') {
                    $xmlString = simplexml_load_string($cacheTeiHal);
                    if (is_object($xmlString) && $xmlString->count() > 0) {
                        $authorTei = Episciences_Paper_AuthorsManager::getAuthorsFromHalTei($xmlString);
                        if (!empty($authorTei)) {
                            $this->displayInfo('Get Author from the TEI', true);
                            $affiInfo = Episciences_Paper_AuthorsManager::getAffiFromHalTei($xmlString);
                            $this->displayInfo('Get Affiliations from the TEI', true);
                            $authorTei = Episciences_Paper_AuthorsManager::mergeAuthorInfoAndAffiTei($authorTei, $affiInfo);
                            $this->displayInfo('Format TEI informations before merge for DB', true);
                            $this->displayInfo('Trying to merge TEI informations with those in Database for ' . $identifier, true);
                            $FormattedAuthorsForDb = Episciences_Paper_AuthorsManager::mergeInfoDbAndInfoTei($decodeAuthor, $authorTei);
                            $this->insertAuthors($FormattedAuthorsForDb, $paperId, array_key_first($selectAuthor));
                        } else {
                            $this->displayError('Author not found in TEI ' . $identifier . ' supposed not the lastest version ->' . $version);
                            self::logErrorMsg('NO AUTHOR IN TEI FOR ' . $identifier);
                        }

                    }
                }
            }
        }
        $this->displayInfo('Authors Enrichment completed. Good Bye ! =)', true);

    }



    public static function logErrorMsg($msg)
    {
        $logger = new Logger('my_logger');
        $logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'creatorEnrichment' . date('Y-m-d') . '.log', Logger::INFO));
        $logger->info($msg);
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
        $newAuthorInfos->setAuthors(json_encode($decodeAuthor, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT));
        $newAuthorInfos->setPaperId($paperId);
        $newAuthorInfos->setAuthorsId($key);
        Episciences_Paper_AuthorsManager::update($newAuthorInfos);
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


}


$script = new getCreatorData($localopts);
$script->run();