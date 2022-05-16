<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;


$localopts = [
    'dry-run' => 'Work with Test API',
];

if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}
require_once "JournalScript.php";

class getLicenceDataEnrichment extends JournalScript
{

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

    /**
     * @return void
     * @throws GuzzleException|JsonException
     */
    public function run(): void
    {
        $this->initApp();
        $this->initDb();
        $this->initTranslator();
        define_review_constants();
        $client = new Client();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(T_PAPERS, ['IDENTIFIER', 'DOCID', 'REPOID', 'VERSION'])->where('REPOID != ? ', 0)->where('STATUS = ?', 16)->order('REPOID DESC'); // prevent empty row
        $pathFile =  APPLICATION_PATH . '/../data/enrichmentLicences/';
        foreach ($db->fetchAll($select) as $value) {
            $identifier = $value['IDENTIFIER'];
            $repoId = $value['REPOID'];
            $docId = $value['DOCID'];
            $version = $value['VERSION'];
            $cleanID = str_replace('/', '', $identifier); // ARXIV CAN HAVE "/" in ID
            $identifier = $this->cleanOldArxivId($identifier);
            $fileName = $pathFile . $cleanID . "_licence.json";
            echo PHP_EOL . $identifier;
            if (!file_exists($fileName)) {
                $callArrayResp = Episciences_Paper_LicenceManager::getApiResponseByRepoId($repoId, $identifier, $version);
                Episciences_Paper_LicenceManager::InsertLicenceFromApiByRepoId($repoId, $callArrayResp, $docId, $identifier);
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
     * @param $identifier
     * @return array|mixed|string|string[]
     */
    public function cleanOldArxivId($identifier)
    {
// arxiv ID can have some extra no needed
        if (strpos($identifier, '.LO/')) {
            $identifier = str_replace('.LO/', '/', $identifier);
        }
        return $identifier;
    }
}


$script = new getLicenceDataEnrichment($localopts);
$script->run();
