<?php

if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}
require_once "JournalScript.php";

class setNumVol extends JournalScript
{
    public function __construct()
    {
        parent::__construct();

        if ($this->getParam('dry-run')) {
            $this->setDryRun(true);
        } else {
            $this->setDryRun(false);
        }
    }
    public function run(): void
    {
        $this->initApp(false);
        $this->initDb();
        $this->initTranslator();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($this->isVerbose()) {
            $this->displayTrace('** Preparing the update...', true);
        }
        $select = $db->select()->from(T_VOLUMES)->order('POSITION ASC');

        foreach ($db->fetchAll($select) as $volumeInfo){
            if ($this->isVerbose()){
                $this->displayTrace(sprintf('VID : %s', $volumeInfo['VID']), true);
            }
            if ($volumeInfo['vol_num'] !== ''){
                $values = $volumeInfo['POSITION'] === '0' ? ['vol_num' => '1'] : ['vol_num' => $volumeInfo['POSITION']];
                $db->update(T_VOLUMES, $values, ['VID = ?' => $volumeInfo['VID']]);
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
}
$script = new setNumVol();
$script->run();