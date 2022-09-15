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
     * getClassificationData constructor.
     * @param $localopts
     */

    public const ONE_MONTH = 3600 * 24 * 31;

    public const FILE_NAME_BROKER_OUTPUT = "dump.json";

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
        $dir = dirname(APPLICATION_PATH) . '/cache/enrichmentClassifications';
        $file = $dir.'/'.self::FILE_NAME_BROKER_OUTPUT;
        if (!file_exists($dir)) {
            $result = mkdir($dir);
            if (!$result) {
                die('Fatal error: Failed to create directory: ' . $dir."\n");
            }
        }
        if (!file_exists($file)) {
                die('Fatal error: JSON DATA DOESNT EXIST: ' . $file."\n");
        }

        $brokerFile = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $arrayClassification = $this->cleanJsonBrokerClassification($brokerFile);

        $select = $db
            ->select()
            ->from(T_PAPERS, ["DOI", "DOCID",'PAPERID'])->where('DOI != ""')->where("STATUS = ? ", Episciences_Paper::STATUS_PUBLISHED)->order('DOCID DESC');
        foreach ($db->fetchAll($select) as $value) {
            if (isset($arrayClassification[$value['DOCID']])){
                $needleArray = $arrayClassification[$value['DOCID']];
                  foreach ($needleArray as $info){
                      $classification = new Episciences_Paper_Classifications();
                      $classification->setClassification($info['subjects[0].value']);
                      $classification->setType($info['subjects[0].type']);
                      $classification->setSourceId(Episciences_Repositories::GRAPH_OPENAIRE_ID);
                      $classification->setPaperId($value['PAPERID']);
                      $insert = Episciences_Paper_ClassificationsManager::insert([$classification]);
                      if ($insert>0) {
                          $this->displayInfo('New Classification for '.$value['PAPERID'], true);
                      } else {
                          $this->displayInfo('Classification already exist for '.$value['PAPERID'], true);
                      }
                  }
            }
        }

        $this->displayInfo('Classification Data Enrichment completed. Good Bye ! =)', true);
    }

    public function cleanJsonBrokerClassification($file): array {

        $cleanArray = [];
        foreach ($file as $value){
            if(preg_match("/^oai:episciences\.org:/",$value["originalId"]) === 1) {
                $explodeString = explode(':', $value['originalId']);
                $cleanArray[end($explodeString)][] = $value['message'];
            }
        }
        return $cleanArray;
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