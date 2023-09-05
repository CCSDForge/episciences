<?php

require_once "JournalScript.php";

/**
 * Class UpgradePaperVolumePosition
 */
class UpgradePaperVolumePosition extends JournalScript
{
    public const TABLE = 'VOLUME_PAPER_POSITION';
    public const CLONE = 'VOLUME_PAPER_POSITION_CLONE';

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

        defineSimpleConstants();
        defineSQLTableConstants();
        defineApplicationConstants();

        $this->initApp();
        $this->initDb();

        defineJournalConstants();
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

        if ($result = $this->cloneTable(self::TABLE, false, self::CLONE)) {

            if ($this->existColumn('DOCID', self::CLONE)) {
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