<?php
require_once "JournalScript.php";
require_once __DIR__ . '/../library/Episciences/Trait/Tools.php';

class UpdatePapersNewJsonFieldDocument extends JournalScript
{
    use Episciences\Trait\Tools;
    private const DEFAULT_SIZE = 500; // Number of documents to update at the same time

    private const TABLE = 'PAPERS';
    private const DOCUMENT_COLUMN = 'DOCUMENT';
    private const PARAM_DOCUMENT_ID = 'documentId';
    private const PARAM_SQLWHERE = 'sqlwhere';
    private const PARAM_BUFFER = 'buffer';
    private const PARAM_UPDATE_RECORD = 'updateRecord';
    private const PARAM_NOTIFY = 'notify';
    private const PARAM_JSON = 'json';

    public function __construct()
    {
        $this->setArgs(
            array_merge($this->getArgs(), [
                'documentId|D=i' => "paper docid [Optional: all documents will be processed if the script is run without this parameter.]",
                'sqlwhere-s' => "to specify the SQL condition to be used to find DOCIDs (exp. --sqlwhere 'DOCID > xxxx')",
                'buffer|b=i' => "Number of documents to update at the same time [default: buffer = 500]",
                'updateRecord|u' => 'Update record',
                'notify|sn' => 'send notification',
                'json|j' => 'Output the result as JSON (for use with jq)',
            ])
        );

        parent::__construct();
    }

    /**
     * @return void
     * @throws Zend_Locale_Exception
     * @throws Zend_Translate_Exception
     * @throws \Psr\Cache\InvalidArgumentException
     */

    public function run(): void
    {
        defineProtocol();
        defineSimpleConstants();
        defineSQLTableConstants();

        // Initialize the application and database
        $this->initApp(false);
        $this->initDb();
        $this->initTranslator();

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $this->assertDocumentColumnExists();

        $params = $this->getParams();

        $buffer = (int)($params[self::PARAM_BUFFER] ?? self::DEFAULT_SIZE);

        if ($buffer <= 0) {
            $buffer = self::DEFAULT_SIZE;
        }

        $forceCriticalLog = true;
        $isJsonOutput = $this->hasParam(self::PARAM_JSON);
        $shouldLog = !$isJsonOutput && $this->isVerbose();
        $shouldUpdateRecord = $this->hasParam(self::PARAM_UPDATE_RECORD);
        $shouldNotify = $this->hasParam(self::PARAM_NOTIFY);

        $logInfo = function (string $message) use ($shouldLog): void {
            if ($shouldLog) {
                $this->displayInfo($message, true);
            }
        };


        $logCritical = function (string $message) use ($forceCriticalLog): void {
            if ($forceCriticalLog) {
                $this->displayCritical($message);
            }
        };

        $logTrace = function (string $message) use ($shouldLog): void {
            if ($shouldLog) {
                $this->displayTrace($message, true);
            }
        };


        [$countQuery, $dataQuery, $docFilterMessage] = $this->buildPaperQueries($db, $shouldUpdateRecord, $params);

        $count = (int)$db?->fetchOne($countQuery);

        if ($count === 0) {
            $logInfo(sprintf('No data to process%s', $docFilterMessage));
            exit(0);
        }

        $processedCount = 1;

        try {
            $paginator = Zend_Paginator::factory($dataQuery);
            $paginator->setItemCountPerPage($buffer);
        } catch (Zend_Paginator_Exception $e) {
            $logCritical($e->getMessage());
            exit(1);
        }

        $totalPages = $paginator->count();

        if ($shouldLog) {
            $this->displayInfo("*** Updating of the `DOCUMENT` column in the `PAPERS` table ***", true);
            $this->displayTrace('** Preparing the update...', true);
            $this->displayTrace(sprintf('Buffer: %s', $buffer), true);
            $this->displayTrace(sprintf('Total pages : %s', $totalPages), true);
        }

        for ($page = 1; $page <= $totalPages; $page++) {
            if ($shouldLog) {
                $this->displayTrace(sprintf('Page #%s', $page), true);
            }

            $updateSql = '';

            $paginator->setCurrentPageNumber($page);

            $currentRvId = 0;
            $currentJournal = null;

            if (!$isJsonOutput) {
                $this->getProgressBar()?->start();
            }

            foreach ($paginator->getCurrentItems() as $values) {
                if ((int)$values['RVID'] !== $currentRvId) {
                    $currentRvId = (int)$values['RVID'];
                    $currentJournal = Episciences_ReviewsManager::find($currentRvId);

                    if (!$currentJournal) {
                        continue;
                    }

                    if ($shouldLog) {
                        $this->displayInfo('Current Journal: ' . $currentJournal->getCode(), true);
                    }
                }

                $docId = $values['DOCID'];

                if ($shouldLog) {
                    $this->displayTrace(sprintf('[DOCID #%s]', $docId), true);
                }

                $progress = (int)round(($processedCount * 100) / $count);

                try {
                    $currentPaper = new Episciences_Paper($values);
                } catch (Zend_Db_Statement_Exception $e) {
                    if (!$isJsonOutput) {
                        $this->displayCritical($e->getMessage());
                    }
                    continue;
                }

                if ($shouldUpdateRecord) {

                    if ($currentPaper->isTmp()) {
                        try { // update-only type
                            $previousVersions = $currentPaper->getPreviousVersions(false, false);

                            if (!empty($previousVersions)) {
                                /** @var Episciences_Paper $latestRepoVersion */
                                $latestRepoVersion = $previousVersions[array_key_first($previousVersions)];
                                $latestRepoType = $latestRepoVersion->getType();

                                $needsDefaultTypeTitle =
                                    $latestRepoVersion->isPublished()
                                    && ($latestRepoType[Episciences_Paper::TITLE_TYPE] ?? null) === Episciences_Paper::ARTICLE_TYPE_TITLE;

                                $currentPaper->setType(
                                    $needsDefaultTypeTitle
                                        ? [Episciences_Paper::TITLE_TYPE => Episciences_Paper::DEFAULT_TYPE_TITLE]
                                        : $latestRepoType
                                );

                                $currentPaper->save();
                                $processedCount++;
                                continue;
                            }

                            $logTrace('/!\ No Previous version');
                        } catch (Zend_Db_Statement_Exception|Zend_Db_Adapter_Exception $e) {
                            $logCritical('#' . $docId . ' ' . $e->getMessage());
                        }
                    }

                    try {
                        $affectedRows = Episciences_PapersManager::updateRecordData($currentPaper);
                        $logTrace(sprintf('Update metadata... > Affected rows: %s', $affectedRows));
                    } catch (Exception $e) {
                        $logCritical('#' . $docId . ' ' . $e->getMessage());
                    }
                }

                if ($shouldNotify && $currentJournal && $currentJournal->getRvid() === $currentPaper->getRvid()) {
                    $this->COARNotify($currentPaper, $currentJournal);
                }

                try {
                    $paperJson = $currentPaper->toJson();

                    // Output raw JSON if --json option is used
                    if ($isJsonOutput) {
                        echo $paperJson . PHP_EOL;
                    }

                    $updateSql .= sprintf(
                        '%sUPDATE `PAPERS` set `DOCUMENT` = %s  WHERE DOCID = %s;',
                        PHP_EOL,
                        $db?->quote($paperJson),
                        $docId
                    );

                    if ($shouldLog) {
                        $this->displaySuccess(sprintf('** [#%s] exported to json format ...', $docId), true);
                    }
                } catch (Zend_Db_Statement_Exception $e) {
                    if (!$isJsonOutput) {
                        $this->displayCritical('#' . $docId . ' ' . $e->getMessage());
                    }
                }

                if (!$isJsonOutput) {
                    $this->getProgressBar()?->setProgress($progress);
                }

                $processedCount++;
            }

            if ($shouldLog) {
                $this->displayTrace(sprintf('Applying Update... %s %s', PHP_EOL, $updateSql), true);
            }

            $result = 0;

            if (!$this->isDebug()) {
                if (trim($updateSql) === '') {
                    if ($shouldLog) {
                        $this->displayCritical('#' . $updateSql . " Nothing to update! SQL Request is empty");
                    }
                    continue;
                }

                try {
                    $statement = $db?->query($updateSql);
                    $result = $statement->rowCount();
                    $statement->closeCursor();
                } catch (Zend_Db_Statement_Exception|Exception $e) {
                    if (!$isJsonOutput) {
                        $this->displayCritical($e->getMessage());
                    }
                }
            }

            if ($shouldLog) {
                if (!$this->isDebug()) {
                    $message = $result ? 'successfully updated' : 'Up to date';
                    $this->displaySuccess(sprintf('Page #%s processed: %s', $page, $message), true);
                } else {
                    $this->displayDebug(sprintf('Page #%s processed: %s', $page, 'successfully updated / Up to date '), true);
                }
            }
        }

        if ($shouldLog) {
            $this->displaySuccess('Updating complete', true);
        }

        exit(0);
    }


    private function assertDocumentColumnExists(): void
    {
        if ($this->existColumn(self::DOCUMENT_COLUMN, self::TABLE)) {
            return;
        }

        $this->displayCritical(sprintf("Unknown column '%s'%s", self::DOCUMENT_COLUMN, PHP_EOL));

        $alter = "ALTER TABLE `PAPERS` ADD `DOCUMENT` JSON NULL DEFAULT NULL AFTER `RECORD`;";
        $alter .= PHP_EOL;
        $alter .= "ALTER TABLE `PAPERS` CHANGE `TYPE` `TYPE` JSON NULL DEFAULT NULL AFTER `REPOID`; -- repoId must be initialized before type @see Episciences_Paper::setType()";

        $this->displayInfo(sprintf("TO DO BEFORE => %s%s", PHP_EOL, $alter), true);
        exit(1);
    }

    /**
     * @return array{0: Zend_Db_Select, 1: Zend_Db_Select, 2: string}
     */
    private function buildPaperQueries($db, bool $shouldUpdateRecord, array $params): array
    {
        $docFilterMessage = '';

        $cols = [
            'DOCID',
            'PAPERID',
            'DOI',
            'RVID',
            'VID',
            'SID',
            'UID',
            'IDENTIFIER',
            'STATUS',
            'VERSION',
            'REPOID',
            'TYPE',
            'CONCEPT_IDENTIFIER',
            'FLAG',
            'WHEN',
            'SUBMISSION_DATE',
            'MODIFICATION_DATE',
            'PUBLICATION_DATE',
        ];

        if ($shouldUpdateRecord) { // Column 'RECORD' cannot be null, when RECORD updated
            $cols[] = 'RECORD';
        }

        $countQuery = $db?->select()->from(T_PAPERS, [new Zend_Db_Expr("COUNT(*)")]);
        $dataQuery = $db?->select()->from(T_PAPERS, $cols);

        if ($this->hasParam(self::PARAM_DOCUMENT_ID)) {
            $docFilterMessage = sprintf(' for document #%s', $params[self::PARAM_DOCUMENT_ID]);
            $dataQuery->where('DOCID = ?', $params[self::PARAM_DOCUMENT_ID]);
            $countQuery->where('DOCID = ?', $params[self::PARAM_DOCUMENT_ID]);
        } elseif ($this->hasParam(self::PARAM_SQLWHERE)) {
            $dataQuery->where($params[self::PARAM_SQLWHERE]);
            $countQuery->where($params[self::PARAM_SQLWHERE]);
        }

        $dataQuery->order(['RVID ASC']);

        return [$countQuery, $dataQuery, $docFilterMessage];
    }

}

try {
    (new UpdatePapersNewJsonFieldDocument())->run();
} catch (\Psr\Cache\InvalidArgumentException|Zend_Locale_Exception|Zend_Translate_Exception  $e) {
    $this->displayCritical($e->getMessage());
}

