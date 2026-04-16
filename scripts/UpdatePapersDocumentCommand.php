<?php
declare(strict_types=1);

require_once __DIR__ . '/../library/Episciences/Trait/Tools.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: update the DOCUMENT JSON column in the PAPERS table.
 *
 * Replaces: scripts/UpdatePapersNewJsonFieldDocument.php (JournalScript)
 */
class UpdatePapersDocumentCommand extends Command
{
    use Episciences\Trait\Tools;

    protected static $defaultName = 'papers:update-document';

    public const DEFAULT_BUFFER = 500;

    private const TABLE        = 'PAPERS';
    private const DOCUMENT_COL = 'DOCUMENT';

    protected function configure(): void
    {
        $this
            ->setDescription('Update the DOCUMENT JSON column in the PAPERS table from Paper::toJson().')
            ->addOption('docid',         null, InputOption::VALUE_REQUIRED, 'Process only this DOCID.')
            ->addOption('sqlwhere',      null, InputOption::VALUE_REQUIRED, "SQL WHERE clause to filter DOCIDs (e.g. 'DOCID > 1000').")
            ->addOption('buffer',        null, InputOption::VALUE_REQUIRED, 'Number of papers to process per page.', self::DEFAULT_BUFFER)
            ->addOption('update-record', null, InputOption::VALUE_NONE,     'Refresh the RECORD column from the repository before updating DOCUMENT.')
            ->addOption('notify',        null, InputOption::VALUE_NONE,     'Send COAR notifications after processing each paper.')
            ->addOption('json',          null, InputOption::VALUE_NONE,     'Output each paper JSON to stdout (one per line, compatible with jq).')
            ->addOption('dry-run',       null, InputOption::VALUE_NONE,     'Build SQL but do not execute it.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $isJsonOutput    = (bool) $input->getOption('json');
        $isDryRun        = (bool) $input->getOption('dry-run');
        $shouldUpdateRec = (bool) $input->getOption('update-record');
        $shouldNotify    = (bool) $input->getOption('notify');
        $buffer          = $this->validateBuffer($input->getOption('buffer'));

        if (!$isJsonOutput) {
            $io->title('Updating PAPERS.DOCUMENT column');
        }

        $this->bootstrap();

        $logger = new Logger('updatePapersDocument');
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'updatePapersDocument_' . date('Y-m-d') . '.log',
            Logger::INFO
        ));

        // In JSON mode stdout must stay clean for piping to jq — log to stderr only.
        if (!$io->isQuiet()) {
            $handle = $isJsonOutput ? 'php://stderr' : 'php://stdout';
            $logger->pushHandler(new StreamHandler($handle, Logger::INFO));
        }

        $db = \Zend_Db_Table_Abstract::getDefaultAdapter();

        if ($db === null) {
            $io->error('Database adapter not initialized.');
            return Command::FAILURE;
        }

        try {
            $this->assertDocumentColumnExists($db);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $params = [
            'docid'    => $input->getOption('docid'),
            'sqlwhere' => $input->getOption('sqlwhere'),
        ];

        [$countQuery, $dataQuery, $filterMsg] = $this->buildPaperQueries($db, $shouldUpdateRec, $params);

        $count = (int) $db->fetchOne($countQuery);

        if ($count === 0) {
            $logger->info(sprintf('No papers to process%s.', $filterMsg));
            return Command::SUCCESS;
        }

        $logger->info(sprintf('Papers to process: %d%s. Buffer: %d', $count, $filterMsg, $buffer));

        try {
            $paginator = \Zend_Paginator::factory($dataQuery);
            $paginator->setItemCountPerPage($buffer);
        } catch (\Zend_Paginator_Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $totalPages     = $paginator->count();
        $processedCount = 0;
        $failureCount   = 0;

        $logger->info(sprintf('Total pages: %d', $totalPages));

        if (!$isJsonOutput) {
            $io->progressStart($count);
        }

        for ($page = 1; $page <= $totalPages; $page++) {
            $logger->info(sprintf('Processing page %d/%d', $page, $totalPages));
            $paginator->setCurrentPageNumber($page);

            $batchSql       = '';
            $currentRvId    = 0;
            $currentJournal = null;

            foreach ($paginator->getCurrentItems() as $values) {
                /** @var array<string, mixed> $values */
                $rvId = (int) $values['RVID'];

                if ($rvId !== $currentRvId) {
                    $currentRvId    = $rvId;
                    $currentJournal = \Episciences_ReviewsManager::find($currentRvId);

                    if (!$currentJournal instanceof \Episciences_Review) {
                        $logger->warning(sprintf('Journal RVID %d not found — skipping remaining papers for this RVID.', $currentRvId));
                        continue;
                    }

                    $logger->info(sprintf('Journal: %s (RVID %d)', $currentJournal->getCode(), $currentRvId));
                }

                if (!$currentJournal instanceof \Episciences_Review) {
                    continue;
                }

                $docId = (int) $values['DOCID'];

                try {
                    $paper = new \Episciences_Paper($values);
                } catch (\Zend_Db_Statement_Exception $e) {
                    $logger->error(sprintf('[DOCID %d] Paper instantiation failed: %s', $docId, $e->getMessage()));
                    $failureCount++;

                    if (!$isJsonOutput) {
                        $io->progressAdvance();
                    }

                    continue;
                }

                if ($shouldUpdateRec) {
                    $this->processUpdateRecord($paper, $logger);
                }

                if ($shouldNotify) {
                    $this->COARNotify($paper, $currentJournal);
                }

                try {
                    $paperJson = $paper->toJson();

                    if ($paperJson === null) {
                        $logger->warning(sprintf('[DOCID %d] toJson() returned null — skipped.', $docId));

                        if (!$isJsonOutput) {
                            $io->progressAdvance();
                        }

                        continue;
                    }

                    if ($isJsonOutput) {
                        echo $paperJson . PHP_EOL;
                    }

                    $batchSql .= PHP_EOL . $this->buildUpdateStatement($docId, (string) $db->quote($paperJson));
                    $logger->info(sprintf('[DOCID %d] JSON generated.', $docId));
                    $processedCount++;
                } catch (\Zend_Db_Statement_Exception|\JsonException $e) {
                    $logger->error(sprintf('[DOCID %d] toJson() failed: %s', $docId, $e->getMessage()));
                    $failureCount++;
                }

                if (!$isJsonOutput) {
                    $io->progressAdvance();
                }
            }

            if (trim($batchSql) === '') {
                $logger->info(sprintf('Page %d: nothing to update.', $page));
                continue;
            }

            if ($isDryRun) {
                $logger->info(sprintf('Page %d: dry-run — SQL not executed.', $page));
                continue;
            }

            try {
                $stmt = $db->query($batchSql);
                $rows = $stmt->rowCount();
                $stmt->closeCursor();
                $logger->info(sprintf('Page %d: %d row(s) updated.', $page, $rows));
            } catch (\Zend_Db_Statement_Exception|\Exception $e) {
                $logger->error(sprintf('Page %d: query failed: %s', $page, $e->getMessage()));
                $failureCount++;
            }
        }

        if (!$isJsonOutput) {
            $io->progressFinish();
        }

        $summary = sprintf(
            'Done. Processed: %d | Failures: %d%s',
            $processedCount,
            $failureCount,
            $isDryRun ? ' (dry-run)' : ''
        );

        $logger->info($summary);

        if (!$isJsonOutput) {
            if ($failureCount > 0) {
                $io->warning($summary);
            } else {
                $io->success($summary);
            }
        }

        return $failureCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Public pure helpers — testable without bootstrap or DB
    // -------------------------------------------------------------------------

    /**
     * Return the ordered list of columns to SELECT from PAPERS.
     * RECORD is always included: Paper::getMetadata() reads it synchronously (no lazy-load),
     * so toJson() needs it present to generate titles, abstracts and other metadata.
     *
     * @return list<string>
     */
    public function buildColumns(): array
    {
        return [
            'DOCID', 'PAPERID', 'DOI', 'RVID', 'VID', 'SID', 'UID',
            'IDENTIFIER', 'STATUS', 'VERSION', 'REPOID', 'TYPE',
            'CONCEPT_IDENTIFIER', 'FLAG', 'WHEN',
            'SUBMISSION_DATE', 'MODIFICATION_DATE', 'PUBLICATION_DATE',
            'RECORD',
        ];
    }

    /**
     * Normalise the --buffer option value.
     * Returns DEFAULT_BUFFER when the value is missing, zero, negative, or non-integer.
     */
    public function validateBuffer(mixed $value): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);

        if ($int === false || $int <= 0) {
            return self::DEFAULT_BUFFER;
        }

        return $int;
    }

    /**
     * Build a single UPDATE statement for one DOCID.
     * $quotedJson must already be quoted by the DB adapter.
     */
    public function buildUpdateStatement(int $docId, string $quotedJson): string
    {
        return sprintf(
            'UPDATE `%s` SET `%s` = %s WHERE DOCID = %d;',
            self::TABLE,
            self::DOCUMENT_COL,
            $quotedJson,
            $docId
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @throws \RuntimeException when the DOCUMENT column does not exist.
     */
    private function assertDocumentColumnExists(\Zend_Db_Adapter_Abstract $db): void
    {
        $sql    = 'SHOW COLUMNS FROM ' . $db->quoteIdentifier(self::TABLE) . ' LIKE ' . (string) $db->quote(self::DOCUMENT_COL);
        $result = $db->fetchOne($sql);

        if ($result === self::DOCUMENT_COL) {
            return;
        }

        $alter = implode(PHP_EOL, [
            'ALTER TABLE `PAPERS` ADD `DOCUMENT` JSON NULL DEFAULT NULL AFTER `RECORD`;',
            'ALTER TABLE `PAPERS` CHANGE `TYPE` `TYPE` JSON NULL DEFAULT NULL AFTER `REPOID`;',
        ]);

        throw new \RuntimeException(sprintf(
            "Column '%s' not found in table '%s'. Run:%s%s",
            self::DOCUMENT_COL,
            self::TABLE,
            PHP_EOL,
            $alter
        ));
    }

    /**
     * @param array{docid: mixed, sqlwhere: mixed} $params
     * @return array{0: \Zend_Db_Select, 1: \Zend_Db_Select, 2: string}
     */
    private function buildPaperQueries(\Zend_Db_Adapter_Abstract $db, bool $shouldUpdateRecord, array $params): array
    {
        $cols      = $this->buildColumns();
        $filterMsg = '';

        $countQuery = $db->select()->from(T_PAPERS, [new \Zend_Db_Expr('COUNT(*)')]);
        $dataQuery  = $db->select()->from(T_PAPERS, $cols);

        $docId    = $params['docid'];
        $sqlwhere = $params['sqlwhere'];

        if ($docId !== null) {
            $filterMsg = sprintf(' for DOCID %d', (int) $docId);
            $countQuery->where('DOCID = ?', (int) $docId);
            $dataQuery->where('DOCID = ?', (int) $docId);
        } elseif ($sqlwhere !== null && $sqlwhere !== '') {
            $countQuery->where((string) $sqlwhere);
            $dataQuery->where((string) $sqlwhere);
        }

        $dataQuery->order('RVID ASC');

        return [$countQuery, $dataQuery, $filterMsg];
    }

    /**
     * Handle --update-record logic for one paper.
     * For tmp papers (update-only type): propagates the type from the latest non-tmp version.
     * For regular papers: refreshes RECORD from the repository via updateRecordData().
     */
    private function processUpdateRecord(\Episciences_Paper $paper, Logger $logger): void
    {
        $docId = (int) $paper->getDocid();

        if ($paper->isTmp()) {
            try {
                $previousVersions = $paper->getPreviousVersions(false, false);

                if (empty($previousVersions)) {
                    $logger->warning(sprintf('[DOCID %d] isTmp but no previous versions found — skipped.', $docId));
                    return;
                }

                /** @var \Episciences_Paper $latestRepoVersion */
                $latestRepoVersion = $previousVersions[array_key_first($previousVersions)];
                $latestRepoType    = $latestRepoVersion->getType();

                $needsDefaultTypeTitle =
                    $latestRepoVersion->isPublished()
                    && ($latestRepoType[\Episciences_Paper::TITLE_TYPE] ?? null) === \Episciences_Paper::ARTICLE_TYPE_TITLE;

                $paper->setType(
                    $needsDefaultTypeTitle
                        ? [\Episciences_Paper::TITLE_TYPE => \Episciences_Paper::DEFAULT_TYPE_TITLE]
                        : $latestRepoType
                );

                $paper->save();
                $logger->info(sprintf('[DOCID %d] isTmp: type propagated and saved.', $docId));
            } catch (\Zend_Db_Statement_Exception|\Zend_Db_Adapter_Exception $e) {
                $logger->error(sprintf('[DOCID %d] isTmp save failed: %s', $docId, $e->getMessage()));
            }

            return;
        }

        try {
            $affected = \Episciences_PapersManager::updateRecordData($paper);
            $logger->info(sprintf('[DOCID %d] RECORD refreshed (%d row(s) affected).', $docId, $affected));
        } catch (\Exception $e) {
            $logger->error(sprintf('[DOCID %d] updateRecordData failed: %s', $docId, $e->getMessage()));
        }
    }

    private function bootstrap(): void
    {
        if (!defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', realpath(__DIR__ . '/../application'));
        }

        require_once __DIR__ . '/../public/const.php';
        require_once __DIR__ . '/../public/bdd_const.php';

        defineProtocol();
        defineSimpleConstants();
        defineSQLTableConstants();
        defineApplicationConstants();
        defineJournalConstants();

        $libraries = [realpath(APPLICATION_PATH . '/../library')];
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, [get_include_path()])));
        require_once 'Zend/Application.php';

        // Do NOT call $application->bootstrap() — APPLICATION_MODULE may be undefined
        // which causes Bootstrap::_initModule() to fail silently.
        $application = new \Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = \Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = \Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        \Zend_Db_Table::setDefaultAdapter($db);

        \Zend_Registry::set('metadataSources', \Episciences_Paper_MetaDataSourcesManager::all(false));
        \Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
    }
}
