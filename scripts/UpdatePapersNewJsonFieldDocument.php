<?php
require_once "JournalScript.php";
require '../library/Episciences/Trait/Tools.php';

class UpdatePapersNewJsonFieldDocument extends JournalScript
{
    use Episciences\Trait\Tools;
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

        $dataQuery->order(['RVID ASC']);

        $data = $db?->fetchAssoc($dataQuery);
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


        if (!$isJsonOutput && $this->isVerbose()) {
            $this->displayInfo("*** Updating of the `DOCUMENT` column in the `PAPERS` table ***", true);
            $this->displayTrace('** Preparing the update...', true);
            $this->displayTrace(sprintf('Buffer: %s', $buffer), true);
            $this->displayTrace(sprintf('Total pages : %s', $totalPages), true);
        }

        for ($page = 1; $page <= $totalPages; $page++) {

            if (!$isJsonOutput && $this->isVerbose()) {
                $this->displayTrace(sprintf('Page #%s', $page), true);
            }

            $toUpdate = '';
            $offset = ($page - 1) * $buffer;
            $cData = array_slice($data, $offset, $buffer);

            $currentRvId = 0;
            $currentJournal = null;

            if (!$isJsonOutput) {
                $this->getProgressBar()?->start();
            }

            foreach ($cData as $values) {

                if ((int)$values['RVID'] !== $currentRvId) {
                    $currentRvId = (int)$values['RVID'];
                    $currentJournal = Episciences_ReviewsManager::find($currentRvId);

                    if (!$currentJournal) {
                        continue;
                    }

                    $this->displayInfo('Current Journal: ' . $currentJournal->getCode(), true);

                }

                $docId = $values['DOCID'];

                if (!$isJsonOutput && $this->isVerbose()) {
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

                        if($currentJournal->getRvid() === $currentPaper->getRvid()) {
                            $this->COARNotify($currentPaper, $currentJournal);
                        }

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

                    if (!$isJsonOutput && $this->isVerbose()) {
                        $this->displaySuccess(sprintf('** [#%s] exported to json format ...', $docId), true);
                        $toUpdate .= sprintf('%sUPDATE `PAPERS` set `DOCUMENT` = %s  WHERE DOCID = %s;', PHP_EOL, $db->quote($toJson), $docId);
                    }


                } catch (Zend_Db_Statement_Exception $e) {
                    if (!$isJsonOutput) {
                        $this->displayCritical('#' . $docId . ' ' . $e->getMessage());
                    }
                }

                if (!$isJsonOutput) {
                    $this->getProgressBar()?->setProgress($progress);
                }

                ++$cpt;
            }

            if (!$isJsonOutput && $this->isVerbose()) {
                $this->displayTrace(sprintf('Applying Update... %s %s', PHP_EOL, $toUpdate), true);
            }

            $result = 0;

            if (!$this->isDebug()) {
                if (empty(trim($toUpdate))) {
                    if (!$isJsonOutput) {
                        $this->displayCritical('#' . $toUpdate . " Nothing to update! SQL Request is empty");
                    }
                    continue;
                }
                try {
                    $statement = $db->query($toUpdate);
                    $result = $statement->rowCount();
                    $statement->closeCursor();
                } catch (Zend_Db_Statement_Exception|Exception $e) {
                    if (!$isJsonOutput) {
                        $this->displayCritical($e->getMessage());
                    }
                }

            }

            if (!$isJsonOutput && $this->isVerbose()) {

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

        if (!$isJsonOutput && $this->isVerbose()) {
            $this->displaySuccess('Updating complete', true);
        }

        exit(0);

    }

}

$script = new UpdatePapersNewJsonFieldDocument();
$script->run();


