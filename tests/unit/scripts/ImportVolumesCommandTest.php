<?php

namespace unit\scripts;

use ImportVolumesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/ImportVolumesCommand.php';

/**
 * Unit tests for ImportVolumesCommand.
 *
 * Focuses on the command's own surface (no bootstrap, no DB, no filesystem).
 * CSV row parsing is tested separately in Episciences\Volume\Import\RowTest.
 */
class ImportVolumesCommandTest extends TestCase
{
    public function testCommandName(): void
    {
        $this->assertSame('import:volumes', (new ImportVolumesCommand())->getName());
    }

    public function testCommandHasRvidOption(): void
    {
        $definition = (new ImportVolumesCommand())->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('rvid'));
        $this->assertTrue($definition->getOption('rvid')->isValueRequired(), 'rvid must require a value');
    }

    public function testCommandHasCsvFileOption(): void
    {
        $definition = (new ImportVolumesCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('csv-file'));
        $this->assertTrue($definition->getOption('csv-file')->isValueRequired(), 'csv-file must require a value');
    }

    public function testCommandHasDryRunOption(): void
    {
        $definition = (new ImportVolumesCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->acceptValue(), 'dry-run must be a flag');
    }
}
