<?php

require_once 'Script.php';

abstract class JournalScript extends Script {

    const PARAM_RVCODE = 'rvcode';
    const PARAM_RVID = 'rvid';

    private $_journals;

    /**
     * set user defined parameter
     * @param $name
     * @param $value
     * @return bool
     */
    public function setParam($name, $value)
    {
        parent::setParam($name, $value);

        if ($name == self::PARAM_RVID && !defined('RVID')) {
            $this->displayTrace('RVID constant has been set to: ' . $value);
            define('RVID', $value);
        } elseif ($name == self::PARAM_RVCODE && !defined('RVCODE')) {
            $this->displayTrace('RVCODE constant has been set to: ' . $value);
            define('RVCODE', $value);
        }

        return true;
    }

    protected function setParamRvid($rvid)
    {
        if (!$this->hasParam(self::PARAM_RVCODE)) {
            $this->setParam(self::PARAM_RVCODE, $this->getJournal($rvid)->getCode());
        }
    }

    protected function setParamRvcode($rvcode)
    {
        if (!$this->hasParam(self::PARAM_RVID)) {
            $this->setParam(self::PARAM_RVID, Episciences_ReviewsManager::find($rvcode)->getRvid());
        }
    }

    /**
     * rvid is required (needed for app init)
     * check that it has been provided, or ask user for it
     */
    protected function checkRvid()
    {
        // if missing rvid, ask for it
        if (!$this->hasParam(self::PARAM_RVID)) {

            $journals = $this->getJournals();
            $journal_names = array();
            /** @var Episciences_Review $journal */
            foreach ($journals as $i=> $journal) {
                $journal_names[$i] = $journal->getCode() . ' - ' . $journal->getName();
            }
            $rvid = $this->ask('Missing review id. Please pick one of these:', $journal_names, static::BASH_YELLOW);
            $this->setParam(self::PARAM_RVID, $rvid);
        }

        // if missing rvcode, set it
        if (!$this->hasParam(self::PARAM_RVCODE)) {
            $this->setParam(self::PARAM_RVCODE, $this->getJournal($this->getParam(self::PARAM_RVID))->getCode());
        }
    }

    /**
     * check that rvcode has been provided, or try to find it
     */
    protected function checkRvcode()
    {
        // if missing rvcode, try to get it
        if (!$this->hasParam(self::PARAM_RVCODE)) {

            if ($this->hasParam(self::PARAM_RVID) && $this->getJournal($this->getParam(self::PARAM_RVID))) {
                $this->setParam(self::PARAM_RVCODE, $this->getJournal($this->getParam(self::PARAM_RVID))->getCode());
            } else {
                $journal_codes = array();
                /** @var Episciences_Review $journal */
                foreach ($this->getJournals() as $i=> $journal) {
                    $journal_codes[$i] = $journal->getCode();
                }
                $rvcode = $this->ask('Missing review code. Please pick one of these:', $journal_codes, static::BASH_YELLOW);
                $this->setParam(self::PARAM_RVCODE, $rvcode);
            }
        }
    }

    protected function loadJournals()
    {
        $journals = Episciences_ReviewsManager::getList();
        // skip Episciences portal
        unset($journals[0]);

        $this->_journals = $journals;
    }

    private function getJournals()
    {
        if (!isset($this->_journals)) {
            $this->loadJournals();
        }
        return $this->_journals;
    }

    /**
     * @param $rvid
     * @return Episciences_Review
     */
    private function getJournal($rvid)
    {
        $journals = $this->getJournals();
        return $journals[$rvid] ?? null;
    }


    /**
     * Clone a table
     * @param string $table : table to clone
     * @param bool $withData : populate the table
     * @return bool
     */
    protected function cloneTable(string $table, bool $withData = true): bool
    {
        $this->displayInfo(' *** Table cloning *** ', true);

        $clone = $table . '_CLONE-' . date("Y-m-d H:i:s");
        $db = $this->getDb();
        $createQuery = 'CREATE TABLE IF NOT EXISTS ';
        $createQuery .= $db->quoteIdentifier($clone);
        $createQuery .= ' LIKE ';
        $createQuery .= $db->quoteIdentifier($table);

        $this->displayTrace($createQuery, true);

        $result = $db->prepare($createQuery)->execute();

        if ($result && $withData) {
            $insertQuery = 'INSERT INTO ';
            $insertQuery .= $db->quoteIdentifier($clone);
            $insertQuery .= ' SELECT * FROM ';
            $insertQuery .= $db->quoteIdentifier($table);
            $result = $db->prepare($insertQuery)->execute();
        }

        return $result;
    }

    /**
     * @param string $oldName
     * @param $newName
     * @return bool
     */
    protected function renameTable(string $oldName, $newName): bool
    {
        $db = $this->getDb();
        $query = 'RENAME TABLE ';
        $query .= $db->quoteIdentifier($oldName);
        $query .= ' TO ' . $db->quoteIdentifier($newName);
        return $db->prepare($query)->execute();
    }

    /**
     * @param string $fieldName
     * @param string $inTable
     * @return bool
     */
    protected function existColumn(string $fieldName, string $inTable): bool
    {
        $db = $this->getDb();
        $sql = 'SHOW COLUMNS FROM ';
        $sql .= $db->quoteIdentifier($inTable);
        $sql .= ' LIKE ';
        $sql .= "'$fieldName'";
        return ($db->prepare($sql)->execute() && ($db->fetchOne($sql) === $fieldName));
    }
}
