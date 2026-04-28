<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require_once "JournalScript.php";

/**
 * Add the missing logs.
 * These relate to the status of the article.
 */
class AddMissingLogs extends JournalScript
{
    protected Logger $logger;
    protected bool $isVerbose = true;
    protected bool $isDebug = false;
    protected bool $withoutImported = false;

    public function __construct()
    {

        $this->setArgs(
            array_merge($this->getArgs(), [
                'rvid=i' => "set journal id",
                'status=i' => "set status code exp. --status 16",
                'ignoreimported|w' => "if defined then ignore imported articles",
            ]));

        parent::__construct();

        $this->logger = new Logger('addMissingLogs');
        try {
            $this->logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . '/addMissingLogs.log', Logger::DEBUG));
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
        }
        if ($this->hasParam('verbose') || $this->hasParam('debug')) {

            if ($this->hasParam('verbose')) {
                $this->isVerbose = (bool)$this->getParam('verbose');
            }

            if ($this->hasParam('debug')) {
                $this->isDebug = (bool)$this->getParam('debug');
            }

            $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        if ($this->hasParam('ignoreimported')) {
            $this->withoutImported = (bool)$this->getParam('ignoreimported');
        }

    }

    public function run(): void
    {

        $this->checkStatus();

        defineSQLTableConstants();

        $this->initApp(false);
        $this->initDb();

        $status = (int)$this->getParam('status');
        $rvId = $this->hasParam('rvid') ?: null;

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $subQuery = $db->select()
            ->distinct()
            ->from(['pl' => T_LOGS], 'DOCID')
            ->where('pl.DOCID = p.DOCID')
            ->where(' pl.status IS NOT NULL')
            ->where(' pl.status = ? ', $status);

        if ($rvId) {
            $subQuery->where('pl.RVID = ?', $rvId);
        }

        $select = $db->select()
            ->from(['p' => T_PAPERS], 'DOCID')
            ->where('p.STATUS = ?', $status)
            ->where('NOT EXISTS (' . $subQuery . ')');

        if($this->withoutImported) {
            $select->where('p.FLAG = ?', 'submitted');
        }

        if ($rvId) {
            $select->where('p.RVID = ?', $rvId);
        }


        $missingIdentifiers = $db->fetchCol($select);

        if (!$missingIdentifiers) {
            $this->logger->info('/!\ No missing logs to add');
            exit(0);
        }


        $path = sprintf('/tmp/addMissingLogsSqlDump-status_%s_%s.sql', $status, date('Ymd_His'));
        $ressource = fopen($path, "wb+");

        if (!$ressource) {
            $this->logger->error(sprintf('Unable to open file: %s', $path));
            exit(1);
        }
        $sqlDump = '';
        $insertMsg = 'INSERT INTO `PAPER_LOG` (`LOGID`,`PAPERID`, `DOCID`, `UID`, `RVID`, `ACTION`, `FILE`, `DATE`, `DETAIL`) VALUES ';

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        foreach ($missingIdentifiers as $docId) {

            try {
                $paper = Episciences_PapersManager::get($docId, false);
            } catch (Zend_Db_Statement_Exception $e) {
                $this->logger->error(sprintf("Paper [$docId] not processed: %s", $e->getMessage()));
                continue;
            }

            if ($paper) {

                if ($status === Episciences_Paper::STATUS_SUBMITTED) {
                    $pDate = $paper->getSubmission_date();
                } elseif ($status === Episciences_Paper::STATUS_PUBLISHED) {
                    $pDate = $paper->getPublication_date();
                } else {
                    $pDate = $paper->getModification_date();
                }

                $isImported = $paper->isImported();

                $data = [
                    'LOGID' => 'NULL',
                    'PAPERID' => $paper->getPaperid(),
                    'DOCID' => $paper->getDocid(),
                    'UID' => EPISCIENCES_UID,
                    'RVID' => $paper->getRvid(),
                    'ACTION' => $db->quote(Episciences_Paper_Logger::CODE_STATUS),
                    'FILE' => 'NULL',
                    'DATE' => $db->quote($pDate),
                    'DETAIL' => $db->quote(Zend_Json::encode(['status' => $status, 'imported' => $isImported])),
                ];


                $current = sprintf('(%s)', implode(', ', $data));

                if ($this->isVerbose) {
                    $this->logger->info($insertMsg . $current . ';' . PHP_EOL);
                }

                $sqlDump .=  $current;
                $sqlDump .= ',';

            }

        }

        $sqlDump = sprintf('%s%s;', $insertMsg, rtrim($sqlDump, ','));

        fwrite($ressource, $sqlDump);
        fclose($ressource);

        if (!$this->isDebug) {


            $statement = $db?->query($sqlDump);

            try {
                $result = $statement->rowCount();
                $statement->closeCursor();
            } catch (Zend_Db_Statement_Exception $e) {
                $this->logger->error(sprintf("Query failed: %s ", $e->getMessage()));
                $result = 0;
            }

            $this->logger->info(sprintf('Inserted rows: %s', $result));
            $this->logger->info(sprintf('For more details, see %s', $path));

        } else {
            $this->logger->info(sprintf('To update the Paper_Log table, see %s', $path));
        }

    }


    public function checkStatus(): void
    {
        if (!$this->hasParam('status')) {
            $msg = 'Missing paper status code';
            die($msg);
        }
    }

}

$script = new AddMissingLogs();
$script->run();