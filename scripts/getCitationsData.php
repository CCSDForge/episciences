<?php

$localopts = [
    'dry-run' => 'Work with Test API',
];


if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}
require_once "JournalScript.php";

class getCitationsData extends JournalScript
{

    /**
     * @var bool
     */
    protected bool $_dryRun = true;

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
     * @throws JsonException
     * @throws Zend_Locale_Exception
     * @throws Zend_Translate_Exception
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function run(): void
    {
        $this->initApp();
        $this->initDb();
        $this->initTranslator();
        defineJournalConstants();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPERS, ["DOI", "DOCID"])
            ->where('DOI != ""')
            ->where("STATUS = ? ", Episciences_Paper::STATUS_PUBLISHED)
            ->order('DOCID DESC');
        foreach ($db->fetchAll($select) as $value) {
            $sets = Episciences_OpencitationsTools::getOpenCitationCitedByDoi($value['DOI']);
            $apiCallCitationCache = json_decode($sets->get(), true, 512, JSON_THROW_ON_ERROR);
            if (!empty($apiCallCitationCache) && reset($apiCallCitationCache) !== "") {
                Episciences_Paper_Citations_EnrichmentService::extractAndStore($apiCallCitationCache, $value['DOCID']);
            } else {
                $this->displayInfo('NO VALUE IN CACHE FOR ' . $value['DOCID'], true);
            }
        }
        $this->displayInfo('Citation Data Enrichment completed. Good Bye ! =)', true);
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

$script = new getCitationsData($localopts);
$script->run();
