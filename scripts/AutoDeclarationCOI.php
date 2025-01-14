<?php

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require_once "JournalScript.php";


class AutoDeclarationCOI extends JournalScript
{
    private Logger $logger;

    public function __construct()
    {

        $this->setArgs(
            array_merge($this->getArgs(), [
                'rvid=i' => "set journal id",
                'date=s' => "(exp. --date Y-m-d)",
            ]));

        parent::__construct();

        // Initialize Monolog
        $this->logger = new Logger(basename(__FILE__));

        // File handler
        $fileHandler = new StreamHandler(EPISCIENCES_LOG_PATH . 'COI.log', Logger::DEBUG);
        $fileHandler->setFormatter(new LineFormatter(null, null, false, true));
        $this->logger->pushHandler($fileHandler);

        // Console handler
        $consoleHandler = new StreamHandler('php://stdout', Logger::INFO);
        $consoleHandler->setFormatter(new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n", null, false, true));
        $this->logger->pushHandler($consoleHandler);

    }


    public function run(): void
    {

        $timeStart = microtime(true);


        if (!$this->hasParam('date') || !Episciences_Tools::isValidSQLDate($this->getParam('date'))) {
            $this->logger->critical(sprintf('Invalid Date format "Y-m-d" [%s]', $this->getParam('date') ?? 'Empty'));
            exit(1);
        }

        defineSQLTableConstants();
        // Initialize the application and database
        $this->initApp(false);
        $this->initDb();
        $this->checkRvid();

        $params = $this->getParams();

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $journal = Episciences_ReviewsManager::find($params['rvid']);;

        if (!$journal) {
            $this->logger->critical(sprintf('Invalid journal ID / RVID [%s]', $params['rvid']));
            exit(1);
        }

        $journal->loadSettings();


        if ((int)$journal->getSetting($journal::SETTING_SYSTEM_IS_COI_ENABLED) === 0) {
            $this->logger->info('COI disabled');
            exit(0);
        }

        $date = $params['date'];

        $dataQuery = $db
            ->select()
            ->from(T_PAPERS)
            ->where('RVID = ? ', $journal->getRvid());

        if ($date) {
            $dataQuery->where('`WHEN` <= ?', $date);
        }

        $values = [];
        $logValues = [];
        $managersCanReportConflict = [];
        $tmpUsers = [];
        $tmpList = [];

        $list = $db?->fetchAssoc($dataQuery);

        foreach ($list as $item) {
            try {
                $tmpList[$item['PAPERID']] = new Episciences_Paper($item);
            } catch (Zend_Db_Statement_Exception $e) {
                $this->logger->critical($e->getMessage());
                continue;
            }
        }

        $count = count($tmpList);

        if ($count === 0) {
            $this->logger->info('No data to process');
            exit(0);
        }

        try {
            $this->addIfNotExists($journal::getChiefEditors(), $managersCanReportConflict);
        } catch (Zend_Db_Statement_Exception $e) {
            $this->logger->critical($e->getMessage());

        }

        try {
            $this->addIfNotExists($journal::getSecretaries(), $managersCanReportConflict);
        } catch (Zend_Db_Statement_Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        /** @var Episciences_Paper $paper */


        foreach ($tmpList as $paper) {

            try {

                $this->logger->info('Current Paper:');
                $this->logger->info(sprintf('PAPERID = %s', $paper->getPaperid()));

                $editors = $paper->getEditors();
                $this->addIfNotExists($managersCanReportConflict, $tmpUsers);
                $this->addIfNotExists($editors, $tmpUsers);

                $owner = $paper->getUid();

                if(array_key_exists($owner, $tmpUsers)){
                     unset($tmpUsers[$owner]); // exp. @see DMTCS #8
                     $ignored[] = ['paperId' => $paper->getPaperid(), 'uid' => $owner];
                }

                /** @var Episciences_User $user */
                foreach ($tmpUsers as $user) {
                    $this->logger->info('Current User:');
                    $this->logger->info(sprintf('UID = %s', $user->getUid()));

                    $userConflict = Episciences_Paper_ConflictsManager::findByUidAndAnswer($user->getUid(), null, Episciences_Paper_ConflictsManager::DEFAULT_MODE, $paper->getPaperid());

                    if (!$userConflict) {

                        $line = sprintf("(%s,%s,'%s',%s,'%s')", $paper->getPaperid(), $user->getUid(), Episciences_Paper_Conflict::AVAILABLE_ANSWER['no'], 'NULL', $date);
                        if ($this->isVerbose()) {
                            $this->logger->info(sprintf('Current insert in %s : %s', T_PAPER_CONFLICTS, $line));
                        }

                        $values[] = $line;

                        try {
                            $logDetail = json_encode([
                                'user' => ['fullname' => $user->getFullName()],
                                'conflict' => [
                                    'paperId' => $paper->getPaperId(),
                                    'by' => $user->getUid(),
                                    'answer' => Episciences_Paper_Conflict::AVAILABLE_ANSWER['no'],
                                    'message' => null,
                                    'date' => $date,
                                    'screenName' => $user->getScreenName()
                                ]

                            ], JSON_THROW_ON_ERROR);
                        } catch (JsonException $e) {
                            $this->logger->critical($e->getMessage());
                            $logDetail = 'NULL';
                        }

                        $logLine = sprintf("(%s,%s,%s,'%s','%s','%s',%s,'%s')", $paper->getPaperid(), $paper->getDocid(), EPISCIENCES_UID, $journal->getRvid(), Episciences_Paper_Logger::CODE_COI_REPORTED, $logDetail, 'NULL', $date);

                        if ($this->isVerbose()) {
                            $this->logger->info(sprintf('Current insert in %s : %s', T_LOGS, $logLine));
                        }

                        $logValues[] = $logLine;
                    } else {
                        $this->logger->info("Has already reported a conflict");
                    }

                }

                $tmpUsers = []; // to process next paper

            } catch (Zend_Db_Statement_Exception $e) {
                $this->logger->critical($e->getMessage());
                continue;
            }

        }

        if (empty($values)) {
            $conflictsDump = 'EMPTY';
            $paperLogsDump = 'EMPTY';
        } else {
            $conflictsDump = '--' . PHP_EOL;
            $conflictsDump .= '-- INSERT IN PAPER_CONFLICTS TABLE' . PHP_EOL;
            $conflictsDump .= '--' . PHP_EOL;
            $conflictsDump .= sprintf("INSERT IGNORE INTO %s (`paper_id`, `by`, `answer`, `message`, `date`) VALUES %s;", $db->quoteIdentifier(T_PAPER_CONFLICTS), implode(',', $values));

            $paperLogsDump = PHP_EOL . '--' . PHP_EOL;
            $paperLogsDump .= '-- INSERT IN PAPER_LOG TABLE' . PHP_EOL;
            $paperLogsDump .= '--' . PHP_EOL;
            $paperLogsDump .= sprintf("INSERT INTO %s (`PAPERID`, `DOCID`, `UID`, `RVID`, `ACTION`, `DETAIL`, `FILE`, `DATE`) VALUES %s;", $db->quoteIdentifier(T_LOGS), implode(',', $logValues));

        }


        $conflictFileName = sprintf('autoDeclarationCOI_%s.sql', date("Y-m-d_H-i-s"));
        $paperLogsFileName = sprintf('autoDeclarationPaperLogs_%s.sql', date("Y-m-d_H-i-s"));
        $file1 = fopen($conflictFileName, 'wb+');

        if (!$file1) {
            $this->logger->critical(sprintf('Unable to open file %s', $conflictFileName));
            exit(1);
        }

        $file2 = fopen($paperLogsFileName, 'wb+');

        if (!$file2) {
            $this->logger->critical(sprintf('Unable to open file %s', $paperLogsFileName));
            exit(1);
        }

        fwrite($file1, $conflictsDump);
        fclose($file1);
        fwrite($file2, $paperLogsDump);
        fclose($file2);

        $timeEnd = microtime(true);
        $time = $timeEnd - $timeStart;

        $this->logger->info(sprintf('Start of script: %s ', date("H:i:s", $timeStart)));
        $this->logger->info(sprintf('End of script: %s', date("H:i:s", $timeEnd)));
        $this->logger->info(sprintf('Script executed in %s', number_format($time, 3) . ' sec.'));
        $this->logger->info(sprintf('Number of rows to insert in table %s: %s', T_PAPER_CONFLICTS, count($values)));
        $this->logger->info(sprintf('Number of rows to insert in table %s: %s', T_LOGS, count($logValues)));
        $this->logger->info(sprintf('Dump generated: %s/%s', getcwd(), $conflictFileName));
        $this->logger->info(sprintf('Dump generated: %s/%s', getcwd(), $paperLogsFileName));

        if (isset($ignored)) {
            try {
                $this->logger->info('Ignored users cannot manage their own submissions:');
                $this->logger->info(json_encode($ignored, JSON_THROW_ON_ERROR));
            } catch (JsonException $e) {
                $this->logger->critical($e->getMessage());
            }
        }

    }

    /**
     * @param array $input
     * @param array $output
     */
    private function addIfNotExists(array $input, array &$output): void
    {
        $arrayDiff = array_diff_key($input, $output);
        foreach ($arrayDiff as $key => $val) {
            $output[$key] = $val;
        }
    }

}

$script = new AutoDeclarationCOI();
$script->run();


