<?php
require_once "JournalScript.php";


class UpdatePapersNewJsonFieldDocument extends JournalScript
{
    public const TABLE = 'PAPERS';
    public const DOCUMENT_COLUMN = 'DOCUMENT';
    public const DEFAULT_SIZE = 500; // Number of documents to update at the same time

    public function __construct()
    {
        $this->setArgs(
            array_merge($this->getArgs(), [
                'documentId|D=i' => "paper docid [Optional: all documents will be processed if the script is run without this parameter.]",
                'buffer|b=i' => "Number of documents to update at the same time [default: buffer = 500]",
                'updateRecord|u' => 'Update record',
            ]));

        parent::__construct();
    }

    /**
     * @return void
     * @throws Zend_Locale_Exception
     * @throws Zend_Translate_Exception
     */
    public function run(): void
    {
        defineProtocol();
        defineSimpleConstants();
        defineSQLTableConstants();

        if (!defined('CACHE_PATH_METADATA')) {
            define('CACHE_PATH_METADATA', dirname(__DIR__) . '/cache/');
        }

        // Initialize the application and database
        $this->initApp(false);
        $this->initDb();
        $this->initTranslator();

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if (!$this->existColumn(self::DOCUMENT_COLUMN, self::TABLE)) {
            $this->displayCritical(sprintf("Unknown column '%s'%s", self::DOCUMENT_COLUMN, PHP_EOL));
            $alter = "ALTER TABLE `PAPERS` ADD `DOCUMENT` JSON NULL DEFAULT NULL AFTER `RECORD`;";
            $alter .= PHP_EOL;
            $alter .= "ALTER TABLE `PAPERS` CHANGE `TYPE` `TYPE` JSON NULL DEFAULT NULL AFTER `REPOID`; -- repoId must be initialized before type @see Episciences_Paper::setType()";
            $this->displayInfo(sprintf("TO DO BEFORE => %s%s", PHP_EOL, $alter), true);
            exit(1);
        }

        $params = $this->getParams();
        $buffer = $params['buffer'] ?? self::DEFAULT_SIZE;
        $docParamMsg = '';

        $dataQuery = $db
            ->select()
            ->from(T_PAPERS);

        if (
            $this->hasParam('documentId')) {
            $dataQuery->where('DOCID = ?', $params['documentId']);
            $docParamMsg = sprintf(' for document #%s', $params['documentId']);
        }


        $data = $db->fetchAssoc($dataQuery);

        if (empty($data)) {
            $this->displayInfo(sprintf('No data to process%s', $docParamMsg), true);
            exit(0);
        }

        $count = count($data);

        $totalPages = ceil($count / $buffer);

        $cpt = 1;

        if ($this->isVerbose()) {
            $this->displayInfo("*** Updating of the `DOCUMENT` column in the `PAPERS` table ***", true);
            $this->displayTrace('** Preparing the update...', true);
            $this->displayTrace(sprintf('Buffer: %s', $buffer), true);
            $this->displayTrace(sprintf('Total pages : %s', $totalPages), true);
        }

        for ($page = 1; $page <= $totalPages; $page++) {

            if ($this->isVerbose()) {
                $this->displayTrace(sprintf('Page #%s', $page), true);
            }

            $toUpdate = '';
            $offset = ($page - 1) * $buffer;
            $cData = array_slice($data, $offset, $buffer);
            $this->getProgressBar()->start();

            foreach ($cData as $values) {
                $docId = $values['DOCID'];

                if ($this->isVerbose()) {
                    $this->displayTrace(sprintf('[DOCID #%s]', $docId), true);
                }
                $progress = round(($cpt * 100) / $count);
                try {
                    $currentPaper = new Episciences_Paper($values);

                } catch (Zend_Db_Statement_Exception $e) {
                    $this->displayCritical($e->getMessage());
                    continue;
                }


                if ($this->hasParam('updateRecord') && !$currentPaper->isTmp()) {
                    try {
                        Episciences_PapersManager::updateRecordData($currentPaper);
                    } catch (Exception $e) {
                        $this->displayCritical($e->getMessage());
                    }

                }

                $toJson = $currentPaper->toJson(Episciences_Paper_XmlExportManager::ALL_KEY);

                if ($this->isVerbose()) {
                    $this->displaySuccess(sprintf('** [#%s] exported to json format ...', $docId), true);
                    $toUpdate .= sprintf('%sUPDATE `PAPERS` set `DOCUMENT` = %s  WHERE DOCID = %s;', PHP_EOL, $db->quote($toJson), $docId);
                }

                $this->getProgressBar()->setProgress($progress);

                ++$cpt;
            }

            if ($this->isVerbose()) {
                $this->displayTrace(sprintf('Applying Update... %s %s', PHP_EOL, $toUpdate), true);
            }

            if (!$this->isDebug()) {
                $statement = $db->query($toUpdate);
                try {
                    $result = $statement->rowCount();
                    $statement->closeCursor();
                } catch (Zend_Db_Statement_Exception $e) {
                    $result = 0;
                    $this->displayCritical($e->getMessage());
                }

            }

            if ($this->isVerbose()) {

                if (!$this->isDebug()) {

                    if ($result) {
                        $message = 'successfully updated';
                    } else {
                        $message = 'Up to date';
                    }

                    $this->displaySuccess(sprintf('Page #%s processed: %s', $page, $message), true);

                } else {
                    $this->displayDebug(sprintf('Page #%s processed: %s', $page, 'successfully updated / Up to date '), true);
                }

            }
        }

        if ($this->isVerbose()) {
            $this->displaySuccess('Updating complete', true);
        }

        exit(0);

    }

}

$script = new UpdatePapersNewJsonFieldDocument();
$script->run();


