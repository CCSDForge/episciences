<?php

namespace unit\scripts;

use ImportSectionsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/ImportSectionsCommand.php';

/**
 * Unit tests for ImportSectionsCommand.
 *
 * Focuses on the command's own surface (no bootstrap, no DB, no filesystem).
 * CSV row parsing/status logic is tested separately in Episciences\Section\Import\RowTest.
 */
class ImportSectionsCommandTest extends TestCase
{
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
}
