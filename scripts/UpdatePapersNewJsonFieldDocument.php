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
                'sqlwhere-s' => "to specify the SQL condition to be used to find DOCIDs (exp. --sqlwhere 'DOCID > xxxx')",
                'buffer|b=i' => "Number of documents to update at the same time [default: buffer = 500]",
                'updateRecord|u' => 'Update record',
                'json|j' => 'Output the result as JSON (for use with jq)',
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
        } elseif ($this->hasParam('sqlwhere')) {
            $dataQuery->where($params['sqlwhere']);
        }

        $data = $db->fetchAssoc($dataQuery);
        $isJsonOutput = $this->hasParam('json');

        if (empty($data)) {
            if (!$isJsonOutput) {
                $this->displayInfo(sprintf('No data to process%s', $docParamMsg), true);
            }
            exit(0);
        }

        $count = count($data);

        $totalPages = ceil($count / $buffer);

        $cpt = 1;
        $isJsonOutput = $this->hasParam('json');

        if ($this->isVerbose() && !$isJsonOutput) {
            $this->displayInfo("*** Updating of the `DOCUMENT` column in the `PAPERS` table ***", true);
            $this->displayTrace('** Preparing the update...', true);
            $this->displayTrace(sprintf('Buffer: %s', $buffer), true);
            $this->displayTrace(sprintf('Total pages : %s', $totalPages), true);
        }

        for ($page = 1; $page <= $totalPages; $page++) {

            if ($this->isVerbose() && !$isJsonOutput) {
                $this->displayTrace(sprintf('Page #%s', $page), true);
            }

            $toUpdate = '';
            $offset = ($page - 1) * $buffer;
            $cData = array_slice($data, $offset, $buffer);
            
            if (!$isJsonOutput) {
                $this->getProgressBar()->start();
            }

            foreach ($cData as $values) {
                $docId = $values['DOCID'];

                if ($this->isVerbose() && !$isJsonOutput) {
                    $this->displayTrace(sprintf('[DOCID #%s]', $docId), true);
                }
                $progress = round(($cpt * 100) / $count);
                try {
                    $currentPaper = new Episciences_Paper($values);

                } catch (Zend_Db_Statement_Exception $e) {
                    if (!$isJsonOutput) {
                        $this->displayCritical($e->getMessage());
                    }
                    continue;
                }


                if ($this->hasParam('updateRecord')) {

                    if ($currentPaper->isTmp()) {

                        try { // update only type
                            $previousVersion = $currentPaper->getPreviousVersions(false, false);

                            if (!empty($previousVersion)) {
                                /** @var Episciences_Paper $latestRepoVersion */
                                $latestRepoVersion = $previousVersion[array_key_first($previousVersion)];
                                $latestType = $latestRepoVersion->getType();
                                $currentPaper->setType($latestRepoVersion->isPublished() && $latestType[Episciences_Paper::TITLE_TYPE] === Episciences_Paper::ARTICLE_TYPE_TITLE ? Episciences_Paper::DEFAULT_TYPE_TITLE : $latestType);
                                $currentPaper->save();
                                continue;
                            }

                            if (!$isJsonOutput) {
                                $this->displayCritical('/!\ No Previous version');
                            }

                        } catch (Zend_Db_Statement_Exception|Zend_Db_Adapter_Exception $e) {
                            if (!$isJsonOutput) {
                                $this->displayCritical('#' . $docId . ' ' . $e->getMessage());
                            }
                        }

                    }

                    try {
                        $affectedRows = Episciences_PapersManager::updateRecordData($currentPaper);
                        if (!$isJsonOutput) {
                            $this->displayTrace(sprintf('Update metadata... > Affected rows: %s', $affectedRows), true);
                        }
                    } catch (Exception|\GuzzleHttp\Exception\GuzzleException|\Psr\Cache\InvalidArgumentException $e) {
                        if (!$isJsonOutput) {
                            $this->displayCritical('#' . $docId . ' ' . $e->getMessage());
                        }
                    }

                }

                try {
                    $toJson = $currentPaper->toJson();

                    // Output raw JSON if --json option is used
                    if ($this->hasParam('json')) {
                        echo $toJson . PHP_EOL;
                    }

                    if ($this->isVerbose() && !$isJsonOutput) {
                        $this->displaySuccess(sprintf('** [#%s] exported to json format ...', $docId), true);
                        $toUpdate .= sprintf('%sUPDATE `PAPERS` set `DOCUMENT` = %s  WHERE DOCID = %s;', PHP_EOL, $db->quote($toJson), $docId);
                    }
                } catch (Zend_Db_Statement_Exception $e) {
                    if (!$isJsonOutput) {
                        $this->displayCritical('#' . $docId . ' ' . $e->getMessage());
                    }
                }

                if (!$isJsonOutput) {
                    $this->getProgressBar()->setProgress($progress);
                }

                ++$cpt;
            }

            if ($this->isVerbose() && !$isJsonOutput) {
                $this->displayTrace(sprintf('Applying Update... %s %s', PHP_EOL, $toUpdate), true);
            }

            if (!$this->isDebug()) {
                if (empty(trim($toUpdate))) {
                    if (!$isJsonOutput) {
                        $this->displayCritical('#' . $docId . " Nothing to update! SQL Request is empty");
                    }
                    continue;
                }
                try {
                    $statement = $db->query($toUpdate);
                    $result = $statement->rowCount();
                    $statement->closeCursor();
                } catch (Zend_Db_Statement_Exception|Exception $e) {
                    $result = 0;
                    if (!$isJsonOutput) {
                        $this->displayCritical('#' . $docId . ' ' . $e->getMessage());
                    }
                }

            }

            if ($this->isVerbose() && !$isJsonOutput) {

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

        if ($this->isVerbose() && !$isJsonOutput) {
            $this->displaySuccess('Updating complete', true);
        }

        exit(0);

    }

}

$script = new UpdatePapersNewJsonFieldDocument();
$script->run();


