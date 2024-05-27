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

class getDumpVolumeYear extends JournalScript
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

    public function run(): void
    {
        $this->initApp();
        $this->initDb();
        $this->initTranslator();
        defineJournalConstants();
        $strSQL = "";
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(["volume" => T_VOLUMES]);
        $myfile = fopen("/tmp/sqldumpYearVol.sql", "wb+") or die("Unable to open file!");
        foreach ($db->fetchAll($select) as $value) {
            $founded = 0;
            if ($value['titles'] !== null) {
                $titles = json_decode($value['titles'], true, 512, JSON_THROW_ON_ERROR);
                foreach ($titles as $title) {
                    $match = [];
                    if (preg_match('~19|20\d{2}~', $title, $match) && !empty($match)) {
                        $founded = 1;
                        $strSQL .= "UPDATE VOLUME SET vol_year = " . $match[0] . " WHERE `VOLUME`.`VID` = " . $value['VID'] . ';' . " #TITLE " . json_encode($titles) . "\n";
                        break;
                    }
                }
            }
            if ($value['descriptions'] !== null && $founded === 0) {
                $descriptions = json_decode($value['descriptions'], true, 512, JSON_THROW_ON_ERROR);
                foreach ($descriptions as $description) {
                    $match = [];
                    if (preg_match('~19|20\d{2}~', $description, $match) && !empty($match)) {
                        $strSQL .= "UPDATE VOLUME SET vol_year = " . $match[0] . " WHERE `VOLUME`.`VID` = " . $value['VID'] . ';' . " #DESCRIPTION " . json_encode($description) . "\n";
                        break;
                    }
                }
            }
        }
        fwrite($myfile, $strSQL);
        fclose($myfile);
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

$script = new getDumpVolumeYear($localopts);
$script->run();