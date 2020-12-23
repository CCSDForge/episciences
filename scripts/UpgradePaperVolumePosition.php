<?php

require_once "JournalScript.php";

/**
 * Class UpgradePaperVolumePosition
 */
class UpgradePaperVolumePosition extends JournalScript
{
    const TABLE = 'VOLUME_PAPER_POSITION';
    const CLONE = 'VOLUME_PAPER_POSITION_CLONE';

    public function __construct()
    {
        $this->display('*** Upgrade ' . self::TABLE . ' table' . PHP_EOL, true, ['bold']);
        $args = [];
        $this->setArgs(array_merge($this->getArgs(), $args));
        parent::__construct();
    }

    public function run()
    {
        if ($this->isVerbose()) {
            $this->displayInfo("Verbose mode: 1");
            $this->displayInfo("Debug mode: " . $this->isDebug());
        }

        $this->checkAppEnv();

        define_simple_constants();
        define_table_constants();
        define_app_constants();

        $this->initApp();
        $this->initDb();

        define_review_constants();
        $this->upgradeTable();
    }

    /**
     * @return bool
     */
    public function upgradeTable(): bool
    {
        $db = $this->getDb();
        $data = [];
        $dataQuery = $db->select()->from(self::TABLE);
        $tmpData = $db->fetchAll($dataQuery);
        $continue = true;

        foreach ($tmpData as $row) {
            $data [$row['VID']][] = $row;
        }

        unset($tmpData);

        if ($result = $this->cloneTable(false)) {

            if ($this->existColumn('DOCID')) {
                $alterTableQuery = 'ALTER TABLE ';
                $alterTableQuery .= self::CLONE . ' CHANGE ';
                $alterTableQuery .= $db->quoteIdentifier('DOCID');
                $alterTableQuery .= ' ';
                $alterTableQuery .= $db->quoteIdentifier('PAPERID');
                $alterTableQuery .= ' INT UNSIGNED NOT NULL';
                $result = $db->prepare($alterTableQuery)->execute();
            } else {
                $continue = ((int)$this->ask('Column DOCID not exist in original table [ ' . self::TABLE . ' ]. Do you want to continue?', ['yes', 'no']) === 0);
            }

            if ($result && $continue) {
                try {
                    $this->insertData($this->checkData($data));
                    // raname tables
                    if ($this->renameTable(self::TABLE, self::TABLE . '_ORIGINAL')) {
                        return $this->renameTable(self::CLONE, self::TABLE);
                    }

                } catch (Exception $e) {
                    $this->displayError($e->getMessage());
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param $fieldName
     * @return bool
     */
    public function existColumn($fieldName): bool
    {
        $db = $this->getDb();
        $sql = 'SHOW COLUMNS FROM ';
        $sql .= $db->quoteIdentifier(self::CLONE);
        $sql .= ' LIKE ';
        $sql .= "'$fieldName'";
        return ($db->prepare($sql)->execute() && ($db->fetchOne($sql) === $fieldName));
    }

    /**
     * @param bool $withData
     * @return bool
     */
    public function cloneTable(bool $withData = true): bool
    {
        $db = $this->getDb();
        $createQuery = 'CREATE TABLE IF NOT EXISTS ';
        $createQuery .= $db->quoteIdentifier(self::CLONE);
        $createQuery .= ' LIKE ';
        $createQuery .= $db->quoteIdentifier(T_VOLUME_PAPER_POSITION);
        $result = $db->prepare($createQuery)->execute();

        if ($result && $withData) {
            $insertQuery = 'INSERT INTO ';
            $insertQuery .= $db->quoteIdentifier(self::CLONE);
            $insertQuery .= ' SELECT * FROM ';
            $insertQuery .= $db->quoteIdentifier(self::TABLE);
            $result = $db->prepare($insertQuery)->execute();
        }
        return $result;
    }

    /**
     * @param array $data
     * @return array
     */
    public function checkData(array $data): array
    {
        $tmpData = [];
        foreach ($data as $vid => $colsValues) {
            foreach ($colsValues as $index => $values) {
                $docId = $values['DOCID'] ?? null;
                try {
                    $paper = Episciences_PapersManager::get($docId, false);
                } catch (Exception $e) {
                    $this->displayError($e->getMessage());
                    return [];
                }

                $paperId = $paper->getPaperid();

                if (array_key_exists($vid, $tmpData) && array_key_exists('PAPERID', $tmpData[$vid][0]) && $tmpData[$vid][0]['PAPERID'] === $paperId) {
                    continue;
                }

                $tmpData[$vid][] = ['VID' => $values['VID'], 'PAPERID' => $paperId, 'POSITION' => $values['POSITION']];
            }
        }

        return $tmpData;
    }

    /**
     * @param string $oldName
     * @param $newName
     * @return bool
     */
    public function renameTable(string $oldName, $newName): bool
    {
        $db = $this->getDb();
        $query = 'RENAME TABLE ';
        $query .= $db->quoteIdentifier($oldName);
        $query .= ' TO ' . $db->quoteIdentifier($newName);
        return $db->prepare($query)->execute();
    }

    /**
     * @param array $data ['vid' => ['VID', 'PAPERID', 'POSITION']]
     * @return int
     * @throws Zend_Db_Adapter_Exception
     */
    public function insertData(array $data): int
    {
        $count = 0;
        $db = $this->getDb();
        foreach ($data as $vid => $rows) {
            foreach ($rows as $row) {
                $db->insert(self::CLONE, $row);
                ++$count;
            }
        }
        return $count;
    }
}

$script = new UpgradePaperVolumePosition();
$script->run();