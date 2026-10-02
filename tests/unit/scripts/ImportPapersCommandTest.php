<?php

namespace unit\scripts;

use ImportPapersCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/ImportPapersCommand.php';

/**
 * Unit tests for ImportPapersCommand.
 *
 * Focuses on pure static logic (no bootstrap, no DB, no filesystem).
 */
class ImportPapersCommandTest extends TestCase
{
    public function testCommandName(): void
    {
        $this->assertSame('import:papers', (new ImportPapersCommand())->getName());
    }

    public function testCommandHasCsvFileOption(): void
    {
        $definition = (new ImportPapersCommand())->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('csv-file'));
        $this->assertTrue($definition->getOption('csv-file')->isValueRequired(), 'csv-file must require a value');
    }

    public function testCommandHasDryRunOption(): void
    {
        $definition = (new ImportPapersCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->acceptValue(), 'dry-run must be a flag');
    }

    public function testCommandHasNoRvidOption(): void
    {
        // rvid is read from the CSV, not from the command line
        $definition = (new ImportPapersCommand())->getDefinition();
        $this->assertFalse($definition->hasOption('rvid'), 'rvid must NOT be a CLI option — it comes from the CSV');
    }
}
