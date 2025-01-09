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

        $list = $db?->fetchAssoc($dataQuery);

        $count = count($list);

        if ($count === 0) {
            $this->logger->info('No data to process');
            exit(0);
        }


        $notificationSettings = $journal->getSetting($journal::SETTING_SYSTEM_NOTIFICATIONS);


        $isChiefEditorsChecked = false;
        $isSecretariesChecked = false;


        if ($notificationSettings) {
            $isChiefEditorsChecked = in_array($journal::SETTING_SYSTEM_CAN_NOTIFY_CHIEF_EDITORS, $notificationSettings, true);
            $isSecretariesChecked = in_array($journal::SETTING_SYSTEM_CAN_NOTIFY_SECRETARIES, $notificationSettings, true);
        }

        if ($isChiefEditorsChecked) {
            try {
                $this->addIfNotExists($journal::getChiefEditors(), $managersCanReportConflict);
            } catch (Zend_Db_Statement_Exception $e) {
                $this->logger->critical($e->getMessage());

            }
        }

        if ($isSecretariesChecked) {
            try {
                $this->addIfNotExists($journal::getSecretaries(), $managersCanReportConflict);
            } catch (Zend_Db_Statement_Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }

        $this->addIfNotExists($managersCanReportConflict, $tmpUsers);


        foreach ($list as $item) {

            try {
                $paper = new Episciences_Paper($item);
                $this->logger->info('Current Paper:');
                $this->logger->info(sprintf('PAPERID = %s', $paper->getPaperid()));
                $editors = $paper->getEditors();
                $this->addIfNotExists($editors, $tmpUsers);

                /** @var Episciences_User $user */
                foreach ($tmpUsers as $user) {
                    $this->logger->info('Current User:');
                    $this->logger->info(sprintf('UID = %s', $user->getUid()));
                    $userConflict = Episciences_Paper_ConflictsManager::findByUidAndAnswer($user->getUid(), null, Episciences_Paper_ConflictsManager::DEFAULT_MODE, $paper->getPaperid());

                    if (!$userConflict) {

                        $values[] = sprintf("(%s,%s,'%s',%s,'%s')", $paper->getPaperid(), $user->getUid(), Episciences_Paper_Conflict::AVAILABLE_ANSWER['no'], 'NULL', $date);

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

                        $logValues[] = sprintf("(%s,%s,%s,'%s','%s','%s',%s,'%s')", $paper->getPaperid(), $paper->getDocid(), EPISCIENCES_UID, $journal->getRvid(), Episciences_Paper_Logger::CODE_COI_REPORTED, $logDetail, 'NULL', $date);
                    } else {
                        $this->logger->info("Has already reported a conflict");
                    }

                }

                $tmpUsers = [];

            } catch (Zend_Db_Statement_Exception $e) {
                $this->logger->critical($e->getMessage());
                continue;
            }

        }

        if (empty($values)) {
            $conflictsDump = '';
            $paperLogsDump = '';
        } else {
            $conflictsDump = '--' . PHP_EOL;
            $conflictsDump .= '-- INSERT IN PAPER_CONFLICTS TABLE' . PHP_EOL;
            $conflictsDump .= '--' . PHP_EOL;
            $conflictsDump .= sprintf("INSERT IGNORE INTO %s (`paper_id`, `by`, `answer`, `message`, `date`) VALUES %s;", $db->quoteIdentifier(T_PAPER_CONFLICTS), implode(',', $values));

            $paperLogsDump = PHP_EOL . '--' . PHP_EOL;
            $paperLogsDump .= '-- INSERT IN PAPER_LOG TABLE' . PHP_EOL;
            $paperLogsDump .= '--' . PHP_EOL;
            $paperLogsDump .= sprintf("INSERT INTO %s (`PAPERID`, `DOCID`, `UID`, `RVID`, `ACTION`, `DETAIL`, `FILE`, `DATE`) VALUES %s;", $db->quoteIdentifier(T_LOGS), implode(',', $logValues) );

        }


        $fileName = sprintf('updateCOI_%s.sql', date("Y-m-d_H-i-s"));
        $file = fopen($fileName, 'wb+');

        if (!$file) {
            $this->logger->critical(sprintf('Unable to open file %s', $fileName));
            exit(1);
        }

        fwrite($file, $conflictsDump . $paperLogsDump);
        fclose($file);

        $timeEnd = microtime(true);
        $time = $timeEnd - $timeStart;

        $this->logger->info(sprintf('Start of script: %s ', date("H:i:s", $timeStart)));
        $this->logger->info(sprintf('End of script: %s', date("H:i:s", $timeEnd)));
        $this->logger->info(sprintf('Script executed in %s', number_format($time, 3) . ' sec.'));
        $this->logger->info(sprintf('Dump generated: %s/%s', getcwd(), $fileName));
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


