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

class getDumpVolumeSpeIssue extends JournalScript
{
    public const DIR = '../data';

    public const ONE_MONTH = 3600 * 24 * 31;

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
        $select = $db->select()->from(["volume" => T_VOLUMES])->joinLeft(["setting" => T_VOLUME_SETTINGS],
            "volume.VID = setting.VID")
            ->where('setting.SETTING = ?', 'special_issue')
            ->where('setting.value = ?','1');// prevent empty row
        $myfile = fopen("/tmp/sqldumpSpecialIssue.sql", "wb+") or die("Unable to open file!");
        foreach($db->fetchAll($select) as $value){
            $selectVol =  $db->select()->from(["volume" => T_VOLUMES])->where('VID = ?',$value['VID']);
            $strSQL .= "UPDATE VOLUME SET vol_type = 'special_issue' WHERE `VOLUME`.`VID` = " . $value['VID'].';'."\n";
        }
        fwrite($myfile,$strSQL);
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
$script = new getDumpVolumeSpeIssue($localopts);
$script->run();