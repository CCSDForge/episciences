<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;


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

    public const ONE_MONTH = 3600 * 24 * 31;

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
     * @throws GuzzleException
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function run(): void
    {
        $this->initApp();
        $this->initDb();
        $this->initTranslator();
        defineJournalConstants();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db
            ->select()
            ->from(T_PAPERS, ['IDENTIFIER', 'DOCID', 'REPOID', 'VERSION'])
            ->where('REPOID != ? ', 0)
            ->where('STATUS IN (?)', Episciences_Paper::$_canBeAssignedDOI)->order('REPOID DESC'); // prevent empty row
        foreach ($db->fetchAll($select) as $value) {
            $identifier = $value['IDENTIFIER'];
            $repoId = $value['REPOID'];
            $docId = $value['DOCID'];
            $version = (int) $value['VERSION'];
            $cleanID = str_replace('/', '', $identifier); // ARXIV CAN HAVE "/" in ID
            $identifier = $this->cleanOldArxivId($identifier);
            $fileName = $cleanID . "_licence.json";
            echo PHP_EOL . $identifier;
            $cache = new FilesystemAdapter('enrichmentLicences', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
            $sets = $cache->getItem($fileName);
            $sets->expiresAfter(self::ONE_MONTH);
            if (!$sets->isHit()) {
                $callArrayResp = Episciences_Paper_LicenceManager::getApiResponseByRepoId($repoId, $identifier, $version);
                Episciences_Paper_LicenceManager::insertLicenceFromApiByRepoId($repoId, $callArrayResp, $docId, $identifier);
            }
        }

        $this->displayInfo('Licence Data Enrichment completed. Good Bye ! =)', true);

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
