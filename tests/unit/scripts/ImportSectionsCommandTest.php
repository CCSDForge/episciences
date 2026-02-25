<?php

namespace unit\scripts;

use ImportSectionsCommand;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/ImportSectionsCommand.php';

/**
 * Unit tests for ImportSectionsCommand.
 *
 * Focuses on pure static logic (no bootstrap, no DB, no filesystem).
 */
class ImportSectionsCommandTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        $this->assertSame('import:sections', (new ImportSectionsCommand())->getName());
    }

    public function testCommandHasCsvFileOption(): void
    {
        $definition = (new ImportSectionsCommand())->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('csv-file'));
        $this->assertTrue($definition->getOption('csv-file')->isValueRequired(), 'csv-file must require a value');
    }

    public function testCommandHasDryRunOption(): void
    {
        $definition = (new ImportSectionsCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->acceptValue(), 'dry-run must be a flag');
    }

    public function testCommandHasNoRvidOption(): void
    {
        // rvid is read from the CSV, not from the command line
        $definition = (new ImportSectionsCommand())->getDefinition();
        $this->assertFalse($definition->hasOption('rvid'), 'rvid must NOT be a CLI option — it comes from the CSV');
    }

    // -------------------------------------------------------------------------
    // parseStatusValue() — public static, pure
    // -------------------------------------------------------------------------

    /**
     * @return array<array{string, int}>
     */
    public static function statusProvider(): array
    {
        return [
            'empty defaults to open'    => ['',    \Episciences_Section::SECTION_OPEN_STATUS],
            'whitespace defaults to open' => ['  ', \Episciences_Section::SECTION_OPEN_STATUS],
            'open status "1"'            => ['1',   \Episciences_Section::SECTION_OPEN_STATUS],
            'closed status "0"'          => ['0',   \Episciences_Section::SECTION_CLOSED_STATUS],
            'invalid falls back to open' => ['99',  \Episciences_Section::SECTION_OPEN_STATUS],
            'invalid text falls back'    => ['abc', \Episciences_Section::SECTION_OPEN_STATUS],
        ];
    }

    /** @dataProvider statusProvider */
    public function testParseStatusValue(string $raw, int $expected): void
    {
        $logger = new Logger('test');
        $result = ImportSectionsCommand::parseStatusValue($raw, 1, $logger);
        $this->assertSame($expected, $result);
    }
}
