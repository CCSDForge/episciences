<?php
declare(strict_types=1);

namespace unit\scripts;

use CleanHalRecordDescriptionsCommand;
use Episciences_Repositories_HAL_Hooks;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/CleanHalRecordDescriptionsCommand.php';

/**
 * Unit tests for CleanHalRecordDescriptionsCommand.
 *
 * All tests are pure (no bootstrap, no I/O side-effects), except the forEachBatch() ones,
 * which run on a temporary table of the test database.
 */
class CleanHalRecordDescriptionsCommandTest extends TestCase
{
    private CleanHalRecordDescriptionsCommand $command;

    protected function setUp(): void
    {
        $this->command = new CleanHalRecordDescriptionsCommand();
    }

    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        self::assertSame('papers:clean-hal-descriptions', $this->command->getName());
    }

    public function testCommandHasDocidOptionRequiringAValue(): void
    {
        $definition = $this->command->getDefinition();

        self::assertInstanceOf(InputDefinition::class, $definition);
        self::assertTrue($definition->hasOption('docid'));
        self::assertTrue($definition->getOption('docid')->isValueRequired(), '--docid must require a value');
    }

    public function testCommandHasBufferOptionRequiringAValue(): void
    {
        $definition = $this->command->getDefinition();

        self::assertTrue($definition->hasOption('buffer'));
        self::assertTrue($definition->getOption('buffer')->isValueRequired(), '--buffer must require a value');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function flagOptionProvider(): array
    {
        return [
            'update-document' => ['update-document'],
            'no-reindex'      => ['no-reindex'],
            'dry-run'         => ['dry-run'],
        ];
    }

    /**
     * @dataProvider flagOptionProvider
     */
    public function testCommandFlagsTakeNoValue(string $option): void
    {
        $definition = $this->command->getDefinition();

        self::assertTrue($definition->hasOption($option));
        self::assertFalse($definition->getOption($option)->acceptValue(), "--{$option} must be a flag");
    }

    // -------------------------------------------------------------------------
    // validateBuffer()
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{0: mixed, 1: int}>
     */
    public static function bufferProvider(): array
    {
        $default = CleanHalRecordDescriptionsCommand::DEFAULT_BUFFER;

        return [
            'valid integer string' => ['250', 250],
            'valid integer'        => [42, 42],
            'zero falls back'      => ['0', $default],
            'negative falls back'  => ['-10', $default],
            'non numeric falls back' => ['abc', $default],
            'null falls back'      => [null, $default],
            'float falls back'     => ['1.5', $default],
        ];
    }

    /**
     * @dataProvider bufferProvider
     */
    public function testValidateBuffer(mixed $input, int $expected): void
    {
        self::assertSame($expected, $this->command->validateBuffer($input));
    }

    // -------------------------------------------------------------------------
    // buildLikePatterns()
    // -------------------------------------------------------------------------

    public function testBuildLikePatternsCoversEveryHookMarker(): void
    {
        $patterns = $this->command->buildLikePatterns();

        self::assertCount(
            count(Episciences_Repositories_HAL_Hooks::NON_ABSTRACT_DESCRIPTIONS),
            $patterns,
            'every marker must yield exactly one SQL pattern'
        );
    }

    /**
     * Spaces become wildcards so a marker split across lines in the stored XML is still
     * shortlisted — the hook then makes the exact decision.
     */
    public function testBuildLikePatternsTurnsSpacesIntoWildcards(): void
    {
        $patterns = $this->command->buildLikePatterns();

        self::assertContains('%International%audience%', $patterns);
        self::assertContains('%National%audience%', $patterns);
    }

    /**
     * RECORD is still utf8mb3 and the connection collation is not guaranteed, so a LIKE on
     * an accented character matches inconsistently. Accented words are dropped from the
     * pattern instead: the hook still decides on the parsed XML, so widening the shortlist
     * costs nothing while narrowing it would leave markers behind.
     */
    public function testBuildLikePatternsDropsNonAsciiWords(): void
    {
        $patterns = $this->command->buildLikePatterns();

        self::assertContains('%soumission%Episciences%', $patterns);

        foreach ($patterns as $pattern) {
            self::assertSame(
                $pattern,
                (string)preg_replace('/[^\x21-\x7E]/', '', $pattern),
                'patterns must stay pure ASCII'
            );
        }
    }

    public function testBuildLikePatternsAreWrappedInWildcards(): void
    {
        foreach ($this->command->buildLikePatterns() as $pattern) {
            self::assertStringStartsWith('%', $pattern);
            self::assertStringEndsWith('%', $pattern);
        }
    }

    // -------------------------------------------------------------------------
    // formatDocIdList()
    // -------------------------------------------------------------------------

    public function testFormatDocIdListPrintsShortListsInFull(): void
    {
        self::assertSame('1,2,3', $this->command->formatDocIdList([1, 2, 3]));
    }

    public function testFormatDocIdListTruncatesLongLists(): void
    {
        $result = $this->command->formatDocIdList(range(1, 120));

        self::assertStringStartsWith('1,2,3,', $result);
        self::assertStringContainsString('70 more', $result);
        // 50 shown + the truncation note
        self::assertStringContainsString('50,', $result);
    }

    public function testFormatDocIdListHandlesEmptyList(): void
    {
        self::assertSame('', $this->command->formatDocIdList([]));
    }

    // -------------------------------------------------------------------------
    // forEachBatch()
    // -------------------------------------------------------------------------

    private const BATCH_TABLE = 'tmp_clean_hal_descriptions_batches';

    /**
     * @param list<string> $records RECORD values of the rows, DOCID 1 to n
     */
    private function createBatchTable(array $records): Zend_Db_Adapter_Abstract
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }

        $db->query('DROP TEMPORARY TABLE IF EXISTS ' . self::BATCH_TABLE);
        $db->query('CREATE TEMPORARY TABLE ' . self::BATCH_TABLE . ' (DOCID INT PRIMARY KEY, RECORD VARCHAR(50))');

        foreach ($records as $index => $record) {
            $db->insert(self::BATCH_TABLE, ['DOCID' => $index + 1, 'RECORD' => $record]);
        }

        return $db;
    }

    public function testForEachBatchReachesEveryRowWhenRewrittenRowsLeaveTheFilter(): void
    {
        $db = $this->createBatchTable(array_fill(0, 10, 'has marker'));
        $query = $db->select()->from(self::BATCH_TABLE, ['DOCID', 'RECORD'])
            ->where('RECORD LIKE ?', '%marker%')
            ->order('DOCID ASC');

        $processed = [];

        try {
            $this->command->forEachBatch($db, $query, 3, function (array $rows) use ($db, &$processed): void {
                foreach ($rows as $row) {
                    $processed[] = (int)$row['DOCID'];
                    // The rewritten row no longer matches the filter, as a cleaned RECORD
                    $db->update(self::BATCH_TABLE, ['RECORD' => 'cleaned'], ['DOCID = ?' => $row['DOCID']]);
                }
            });

            $left = $db->fetchCol('SELECT DOCID FROM ' . self::BATCH_TABLE . " WHERE RECORD LIKE '%marker%'");
        } finally {
            $db->query('DROP TEMPORARY TABLE IF EXISTS ' . self::BATCH_TABLE);
        }

        self::assertSame(range(1, 10), $processed);
        self::assertSame([], $left);
    }

    public function testForEachBatchVisitsUntouchedRowsOnceAndPassesTheLastDocIdOfEachBatch(): void
    {
        $db = $this->createBatchTable(array_fill(0, 7, 'has marker'));
        $query = $db->select()->from(self::BATCH_TABLE, ['DOCID', 'RECORD'])->where('RECORD LIKE ?', '%marker%');

        $processed = [];
        $lastDocIds = [];

        try {
            // Nothing rewritten: the rows stay in the shortlist, as records the hook leaves alone
            $this->command->forEachBatch($db, $query, 3, function (array $rows, int $lastDocId) use (&$processed, &$lastDocIds): void {
                array_push($processed, ...array_map(static fn(array $row): int => (int)$row['DOCID'], $rows));
                $lastDocIds[] = $lastDocId;
            });
        } finally {
            $db->query('DROP TEMPORARY TABLE IF EXISTS ' . self::BATCH_TABLE);
        }

        self::assertSame(range(1, 7), $processed);
        self::assertSame([3, 6, 7], $lastDocIds);
    }
}
