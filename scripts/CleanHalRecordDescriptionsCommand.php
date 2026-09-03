<?php
declare(strict_types=1);

use Episciences\Console\ProgressAwareStreamHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: strip HAL's non-abstract <dc:description> markers from the
 * RECORD column of papers already stored in the database.
 *
 * Companion to Episciences_Repositories_HAL_Hooks::hookCleanXMLRecordInput(), which
 * removes those markers at ingestion. This command applies the very same hook to the
 * existing rows, so ingestion and backfill can never diverge.
 *
 * Two caches derive from RECORD and must be refreshed afterwards:
 *  - PAPERS.DOCUMENT (JSON), built from the CrossRef export via
 *    Episciences_Paper::getAbstractsCleaned() — handled by --update-document.
 *  - the Solr index — a reindex is queued for every modified paper unless
 *    --no-reindex is passed.
 */
class CleanHalRecordDescriptionsCommand extends Command
{
    protected static $defaultName = 'papers:clean-hal-descriptions';

    public const DEFAULT_BUFFER = 500;

    /** DOCIDs per delegated papers:update-document call, to keep the IN () list sane. */
    private const DOCUMENT_CHUNK = 1000;

    protected function configure(): void
    {
        $this
            ->setDescription("Strip HAL's non-abstract dc:description markers from PAPERS.RECORD.")
            ->addOption('docid', null, InputOption::VALUE_REQUIRED, 'Process only this DOCID.')
            ->addOption('buffer', null, InputOption::VALUE_REQUIRED, 'Number of papers loaded per page.', self::DEFAULT_BUFFER)
            ->addOption('update-document', null, InputOption::VALUE_NONE, 'Regenerate PAPERS.DOCUMENT for modified papers by delegating to papers:update-document.')
            ->addOption('no-reindex', null, InputOption::VALUE_NONE, 'Do not queue a Solr reindex for modified papers.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change without writing anything.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $isDryRun        = (bool)$input->getOption('dry-run');
        $shouldReindex   = !(bool)$input->getOption('no-reindex');
        $shouldUpdateDoc = (bool)$input->getOption('update-document');
        $buffer          = $this->validateBuffer($input->getOption('buffer'));

        $io->title('Cleaning HAL dc:description markers in PAPERS.RECORD');

        $this->bootstrap();

        $logger = new Logger('cleanHalRecordDescriptions');
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'cleanHalRecordDescriptions_' . date('Y-m-d') . '.log',
            Logger::INFO
        ));

        $stdoutHandler = null;
        if (!$io->isQuiet()) {
            $stdoutHandler = new ProgressAwareStreamHandler('php://stdout', Logger::INFO);
            $logger->pushHandler($stdoutHandler);
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if ($db === null) {
            $io->error('Database adapter not initialized.');
            return Command::FAILURE;
        }

        $docIdOption = $input->getOption('docid');
        $docIdFilter = $docIdOption !== null ? (int)$docIdOption : null;

        [$countQuery, $dataQuery] = $this->buildQueries($db, $docIdFilter);

        $candidates = (int)$db->fetchOne($countQuery);

        if ($candidates === 0) {
            $io->success('No candidate paper found — nothing to do.');
            return Command::SUCCESS;
        }

        $logger->info(sprintf('Candidate papers: %d. Buffer: %d', $candidates, $buffer));

        try {
            $paginator = Zend_Paginator::factory($dataQuery);
            $paginator->setItemCountPerPage($buffer);
        } catch (Zend_Paginator_Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $totalPages   = $paginator->count();
        $modifiedIds  = [];
        $untouchedIds = [];
        $failureCount = 0;

        $progressBar = $io->createProgressBar($candidates);
        $stdoutHandler?->setProgressBar($progressBar);
        $progressBar->start();

        for ($page = 1; $page <= $totalPages; $page++) {
            $paginator->setCurrentPageNumber($page);

            $pageUpdates = [];

            foreach ($paginator->getCurrentItems() as $row) {
                /** @var array<string, mixed> $row */
                $docId  = (int)$row['DOCID'];
                $record = (string)($row['RECORD'] ?? '');

                // Reuse the ingestion hook itself rather than re-implementing its rules.
                $cleaned = (string)(Episciences_Repositories_HAL_Hooks::hookCleanXMLRecordInput(
                    ['record' => $record]
                )['record'] ?? $record);

                if ($cleaned === $record) {
                    // The SQL LIKE filter is deliberately loose: it only shortlists rows,
                    // the hook decides. A shortlisted record is left alone either because
                    // its text does not match exactly (the marker is a substring of a real
                    // abstract) or because its XML could not be parsed. Both are reported:
                    // now that the downstream filters are gone, anything still holding the
                    // marker will display it.
                    $untouchedIds[] = $docId;
                } elseif (trim($cleaned) === '') {
                    $logger->error(sprintf('[DOCID %d] cleaned record is empty — skipped.', $docId));
                    $failureCount++;
                } else {
                    $pageUpdates[$docId] = $cleaned;
                }

                $progressBar->advance();
            }

            if ($pageUpdates === []) {
                continue;
            }

            if ($isDryRun) {
                foreach (array_keys($pageUpdates) as $docId) {
                    $logger->info(sprintf('[DOCID %d] dry-run: RECORD would be rewritten.', $docId));
                    $modifiedIds[] = $docId;
                }
                continue;
            }

            try {
                $db->beginTransaction();

                foreach ($pageUpdates as $docId => $cleaned) {
                    $db->update(T_PAPERS, ['RECORD' => $cleaned], ['DOCID = ?' => $docId]);
                }

                $db->commit();

                $modifiedIds = array_merge($modifiedIds, array_keys($pageUpdates));
                $logger->info(sprintf('Page %d/%d: %d RECORD(s) rewritten.', $page, $totalPages, count($pageUpdates)));
            } catch (Exception $e) {
                $db->rollBack();
                $logger->error(sprintf('Page %d/%d: transaction failed: %s', $page, $totalPages, $e->getMessage()));
                $failureCount += count($pageUpdates);
            }
        }

        $progressBar->finish();
        $stdoutHandler?->setProgressBar(null);
        $io->newLine();

        $logger->info(sprintf(
            'Candidates: %d | Modified: %d | Untouched: %d | Failures: %d%s',
            $candidates,
            count($modifiedIds),
            count($untouchedIds),
            $failureCount,
            $isDryRun ? ' (dry-run)' : ''
        ));

        if ($untouchedIds !== []) {
            $logger->warning(sprintf(
                'Shortlisted but left untouched (no dc:description matched a marker exactly, or the XML could not be parsed) — DOCID: %s',
                $this->formatDocIdList($untouchedIds)
            ));
        }

        if ($modifiedIds === []) {
            $io->success('No RECORD needed rewriting.');
            return $failureCount > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        if ($isDryRun) {
            $io->warning(sprintf('dry-run: %d RECORD(s) would be rewritten. Nothing was written.', count($modifiedIds)));
            return Command::SUCCESS;
        }

        if ($shouldReindex) {
            try {
                Ccsd_Search_Solr_Indexer::addToIndexQueue(
                    $modifiedIds,
                    'episciences',
                    Ccsd_Search_Solr_Indexer::O_UPDATE,
                    'episciences'
                );
                $logger->info(sprintf('Solr reindex queued for %d paper(s).', count($modifiedIds)));
            } catch (Exception $e) {
                $logger->error('Queuing the Solr reindex failed: ' . $e->getMessage());
                $failureCount++;
            }
        }

        if ($shouldUpdateDoc) {
            $failureCount += $this->updateDocumentColumn($modifiedIds, $output, $logger);
        } else {
            // Without this, the JSON API keeps serving the abstract cached before the
            // rewrite — and that cache no longer filters the marker itself.
            $io->warning(sprintf(
                "PAPERS.DOCUMENT is now stale for %d paper(s). Run:\n  php scripts/console.php papers:update-document --sqlwhere='DOCID IN (%s)'\nor re-run this command with --update-document.",
                count($modifiedIds),
                $this->formatDocIdList($modifiedIds)
            ));
        }

        $summary = sprintf(
            'Done. Modified: %d | Untouched: %d | Failures: %d',
            count($modifiedIds),
            count($untouchedIds),
            $failureCount
        );

        $failureCount > 0 ? $io->warning($summary) : $io->success($summary);

        return $failureCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Public pure helpers — testable without bootstrap or DB
    // -------------------------------------------------------------------------

    /**
     * Normalise the --buffer option value.
     * Returns DEFAULT_BUFFER when the value is missing, zero, negative, or non-integer.
     */
    public function validateBuffer(mixed $value): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);

        return ($int === false || $int <= 0) ? self::DEFAULT_BUFFER : $int;
    }

    /**
     * Build the loose SQL LIKE patterns that shortlist candidate records, derived from
     * the hook's own marker list so the two stay in sync.
     *
     * Each marker is reduced to its ASCII-only words, joined by wildcards. Two reasons:
     *
     *  - A marker split across lines in the stored XML is still picked up, since the hook
     *    normalizes whitespace before comparing.
     *  - Accented characters are dropped rather than sent through LIKE. RECORD is still
     *    utf8mb3 and the connection collation is not guaranteed, so matching on 'à' gives
     *    different results depending on who asks — which risks silently skipping papers.
     *
     * Being too broad here is harmless: the hook makes the final, exact decision on the
     * parsed XML. Being too narrow would leave markers behind.
     *
     * @return list<string>
     */
    public function buildLikePatterns(): array
    {
        $patterns = [];

        foreach (Episciences_Repositories_HAL_Hooks::NON_ABSTRACT_DESCRIPTIONS as $marker) {
            $asciiWords = [];

            foreach (preg_split('/\s+/u', $marker, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                // Keep only words a LIKE can match byte-for-byte whatever the collation.
                if (preg_match('/^[\x21-\x7E]+$/', $word) !== 1) {
                    continue;
                }

                // Escape LIKE metacharacters before the word becomes part of the pattern.
                $asciiWords[] = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $word);
            }

            // A marker with no ASCII word at all shortlists everything rather than nothing:
            // the hook then filters, so no paper is missed.
            $patterns[] = $asciiWords === [] ? '%' : '%' . implode('%', $asciiWords) . '%';
        }

        return $patterns;
    }

    /**
     * Render a DOCID list for the copy-pasteable follow-up command, truncating the
     * middle so a 20 000-paper run does not print an unusable wall of digits.
     *
     * @param list<int> $docIds
     */
    public function formatDocIdList(array $docIds): string
    {
        $maxShown = 50;

        if (count($docIds) <= $maxShown) {
            return implode(',', $docIds);
        }

        return implode(',', array_slice($docIds, 0, $maxShown))
            . sprintf(',/* … %d more, see the log file */', count($docIds) - $maxShown);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @return array{0: Zend_Db_Select, 1: Zend_Db_Select}
     */
    private function buildQueries(Zend_Db_Adapter_Abstract $db, ?int $docId): array
    {
        $countQuery = $db->select()->from(T_PAPERS, [new Zend_Db_Expr('COUNT(*)')]);
        $dataQuery  = $db->select()->from(T_PAPERS, ['DOCID', 'RECORD']);

        foreach ([$countQuery, $dataQuery] as $query) {
            $query->where('REPOID = ?', (int)Episciences_Repositories::HAL_REPO_ID);

            $orConditions = [];
            foreach ($this->buildLikePatterns() as $pattern) {
                $orConditions[] = $db->quoteInto('RECORD LIKE ?', $pattern);
            }

            $query->where('(' . implode(' OR ', $orConditions) . ')');
        }

        $dataQuery->order('DOCID ASC');

        return [$countQuery, $dataQuery];
    }

    /**
     * Delegate PAPERS.DOCUMENT regeneration to the existing papers:update-document
     * command instead of duplicating its logic.
     *
     * @param list<int> $docIds
     * @return int number of failed chunks
     */
    private function updateDocumentColumn(array $docIds, OutputInterface $output, Logger $logger): int
    {
        $application = $this->getApplication();

        if ($application === null) {
            $logger->error('No console application available — cannot regenerate PAPERS.DOCUMENT.');
            return 1;
        }

        try {
            $command = $application->find('papers:update-document');
        } catch (Throwable $e) {
            $logger->error('papers:update-document not found: ' . $e->getMessage());
            return 1;
        }

        $failures = 0;

        foreach (array_chunk($docIds, self::DOCUMENT_CHUNK) as $index => $chunk) {
            $sqlwhere = sprintf('DOCID IN (%s)', implode(',', $chunk));

            try {
                $exitCode = $command->run(new ArrayInput(['--sqlwhere' => $sqlwhere]), $output);
            } catch (Throwable $e) {
                $logger->error(sprintf('papers:update-document chunk %d failed: %s', $index + 1, $e->getMessage()));
                $failures++;
                continue;
            }

            if ($exitCode !== Command::SUCCESS) {
                $logger->error(sprintf('papers:update-document chunk %d exited with code %d.', $index + 1, $exitCode));
                $failures++;
            }
        }

        if ($failures === 0) {
            $logger->info(sprintf('PAPERS.DOCUMENT regenerated for %d paper(s).', count($docIds)));
        }

        return $failures;
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
        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);

        Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
    }
}