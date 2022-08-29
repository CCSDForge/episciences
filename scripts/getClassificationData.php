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

class getClassificationData extends JournalScript
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

    public const CITATIONS_PREFIX_VALUE = "coci => ";

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


    public function run(): void
    {
        $this->initApp();
        $this->initDb();
        $this->initTranslator();
        define_review_constants();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db
            ->select()
            ->from(T_PAPERS, ["DOI", "DOCID",'PAPERID'])->where('DOI != ""')->where("STATUS = ? ", Episciences_Paper::STATUS_PUBLISHED)->order('DOCID DESC');
        foreach ($db->fetchAll($select) as $value) {

            $this->displayInfo($value['PAPERID'], true);
            echo $value['PAPERID'];
            var_dump(Episciences_Paper_ClassificationsManager::getClassificationByPaperId($value['PAPERID']));

            die;

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

$script = new getClassificationData($localopts);
$script->run();