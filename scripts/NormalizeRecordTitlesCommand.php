<?php

declare(strict_types=1);

use Episciences\Paper\Record\TitleNormalizer;
use scripts\Command\AbstractCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Put the titles of the records already stored in PAPERS.RECORD on a single line.
 *
 * Companion to the normalization done at ingestion (@see \Episciences_Submit::getDoc() and
 * \Episciences_PapersManager::cleanRecord()): this command applies the very same
 * TitleNormalizer to the existing rows, so ingestion and backfill can never diverge.
 *
 * Only RECORD is rewritten: the caches derived from it (PAPERS.DOCUMENT, Solr) already read
 * the titles through Episciences_Paper::getMetadata(), which collapses their whitespace.
 *
 * Usage:
 *   php scripts/console.php papers:normalize-titles --dry-run
 *   php scripts/console.php papers:normalize-titles [--docid=123] [--buffer=500]
 */
#[AsCommand(
    name: 'papers:normalize-titles',
    description: 'Put the titles stored in PAPERS.RECORD on a single line (line breaks accepted by some repositories, e.g. arXiv, HAL).'
)]
class NormalizeRecordTitlesCommand extends AbstractCommand
{
    public const DEFAULT_BUFFER = 500;

    /**
     * Loose MySQL shortlist of the records holding a title with a control character (line break,
     * tab), two consecutive spaces, or a leading or trailing whitespace. Being too broad is harmless:
     * TitleNormalizer makes the final decision on the parsed XML.
     */
    public const SHORTLIST_REGEXP = '<([[:alnum:]]+:)?title([[:space:]][^>]*)?>([[:space:]]|[^<]*([[:cntrl:]]|  |[[:space:]]<))';

    protected function configure(): void
    {
        $this
            ->addOption('docid', null, InputOption::VALUE_REQUIRED, 'Process only this DOCID.')
            ->addOption('buffer', null, InputOption::VALUE_REQUIRED, 'Number of papers loaded per batch.', self::DEFAULT_BUFFER)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change without writing anything.');
    }

    protected function runLogic(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = (bool)$input->getOption('dry-run');
        $buffer = $this->validateBuffer($input->getOption('buffer'));
        $docIdOption = $input->getOption('docid');
        $docIdFilter = $docIdOption !== null ? (int)$docIdOption : null;

        if ($this->db === null) {
            $this->io->error('Database adapter not initialized.');
            return Command::FAILURE;
        }

        $this->io->title('Normalizing the titles stored in PAPERS.RECORD' . ($isDryRun ? ' (dry-run)' : ''));

        $modified = [];
        $untouched = [];
        $failures = 0;
        $lastDocId = 0;

        // Keyset pagination: a rewritten record leaves the shortlist, an OFFSET would skip rows
        do {
            $rows = $this->db->fetchAll($this->buildBatchQuery($lastDocId, $buffer, $docIdFilter));

            if ($rows === []) {
                break;
            }

            $lastDocId = (int)$rows[array_key_last($rows)]['DOCID'];
            $batch = $this->computeUpdates($rows);
            $untouched = array_merge($untouched, $batch['untouched']);

            if ($batch['updates'] === []) {
                continue;
            }

            if ($isDryRun) {
                foreach (array_keys($batch['updates']) as $docId) {
                    $this->logger->info(sprintf('[DOCID %d] dry-run: RECORD would be rewritten.', $docId));
                }
                $modified = array_merge($modified, array_keys($batch['updates']));
                continue;
            }

            try {
                $this->db->beginTransaction();

                foreach ($batch['updates'] as $docId => $record) {
                    $this->db->update(T_PAPERS, ['RECORD' => $record], ['DOCID = ?' => $docId]);
                }

                $this->db->commit();
                $modified = array_merge($modified, array_keys($batch['updates']));
                $this->logger->info(sprintf('%d RECORD(s) rewritten, up to DOCID %d.', count($batch['updates']), $lastDocId));
            } catch (Exception $e) {
                $this->db->rollBack();
                $this->logger->error(sprintf('Batch up to DOCID %d: transaction failed: %s', $lastDocId, $e->getMessage()));
                $failures += count($batch['updates']);
            }
        } while (count($rows) === $buffer);

        if ($untouched !== []) {
            // Shortlisted by the loose SQL filter but already normalized, or not parsable as XML
            $this->logger->info(sprintf('Shortlisted but left untouched: %d (DOCID: %s)', count($untouched), implode(',', $untouched)));
        }

        $summary = sprintf(
            '%s: %d | Untouched: %d | Failures: %d',
            $isDryRun ? 'Would be modified' : 'Modified',
            count($modified),
            count($untouched),
            $failures
        );

        if ($failures > 0) {
            $this->io->warning($summary);
            return Command::FAILURE;
        }

        $this->io->success($summary);
        return Command::SUCCESS;
    }

    /**
     * Normalize the titles of a batch of PAPERS rows.
     *
     * @param array<int, array<string, mixed>> $rows rows holding DOCID and RECORD
     * @return array{updates: array<int, string>, untouched: list<int>} new RECORD by DOCID, and the DOCIDs left as is
     */
    public function computeUpdates(array $rows): array
    {
        $updates = [];
        $untouched = [];

        foreach ($rows as $row) {
            $docId = (int)$row['DOCID'];
            $record = (string)($row['RECORD'] ?? '');
            $normalized = TitleNormalizer::normalize($record);

            if ($normalized === $record) {
                $untouched[] = $docId;
            } else {
                $updates[$docId] = $normalized;
            }
        }

        return ['updates' => $updates, 'untouched' => $untouched];
    }

    /**
     * Normalize the --buffer option value: DEFAULT_BUFFER when missing, zero, negative or non-integer.
     */
    public function validateBuffer(mixed $value): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);

        return ($int === false || $int <= 0) ? self::DEFAULT_BUFFER : $int;
    }

    private function buildBatchQuery(int $afterDocId, int $buffer, ?int $docId): Zend_Db_Select
    {
        $query = $this->db->select()
            ->from(T_PAPERS, ['DOCID', 'RECORD'])
            ->where('DOCID > ?', $afterDocId)
            ->where('RECORD REGEXP ?', self::SHORTLIST_REGEXP)
            ->order('DOCID ASC')
            ->limit($buffer);

        if ($docId !== null) {
            $query->where('DOCID = ?', $docId);
        }

        return $query;
    }
}