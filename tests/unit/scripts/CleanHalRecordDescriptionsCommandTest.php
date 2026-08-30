<?php
declare(strict_types=1);

namespace unit\scripts;

use CleanHalRecordDescriptionsCommand;
use Episciences_Repositories_HAL_Hooks;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/CleanHalRecordDescriptionsCommand.php';

/**
 * Unit tests for CleanHalRecordDescriptionsCommand.
 *
 * All tests are pure: no bootstrap, no database, no I/O side-effects.
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
}
