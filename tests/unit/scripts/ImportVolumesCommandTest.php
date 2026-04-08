<?php

namespace unit\scripts;

use ImportVolumesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/ImportVolumesCommand.php';

/**
 * Unit tests for ImportVolumesCommand.
 *
 * Focuses on pure static logic (no bootstrap, no DB, no filesystem).
 */
class ImportVolumesCommandTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // COL_* constants
    // -------------------------------------------------------------------------

    public function testColumnConstants(): void
    {
        $this->assertSame(0, ImportVolumesCommand::COL_POSITION);
        $this->assertSame(1, ImportVolumesCommand::COL_STATUS);
        $this->assertSame(2, ImportVolumesCommand::COL_CURRENT_ISSUE);
        $this->assertSame(3, ImportVolumesCommand::COL_SPECIAL_ISSUE);
        $this->assertSame(4, ImportVolumesCommand::COL_BIB_REFERENCE);
        $this->assertSame(5, ImportVolumesCommand::COL_TITLE_EN);
        $this->assertSame(6, ImportVolumesCommand::COL_TITLE_FR);
        $this->assertSame(7, ImportVolumesCommand::COL_DESC_EN);
        $this->assertSame(8, ImportVolumesCommand::COL_DESC_FR);
    }

    // -------------------------------------------------------------------------
    // getCol() — public static, pure
    // -------------------------------------------------------------------------

    public function testGetCol_ReturnsValue(): void
    {
        $data = ['foo', 'bar', 'baz'];
        $this->assertSame('bar', ImportVolumesCommand::getCol($data, 1));
    }

    public function testGetCol_TrimsWhitespace(): void
    {
        $data = ['  hello  '];
        $this->assertSame('hello', ImportVolumesCommand::getCol($data, 0));
    }

    public function testGetCol_BlankReturnsEmpty(): void
    {
        $data = ['   '];
        $this->assertSame('', ImportVolumesCommand::getCol($data, 0));
    }

    public function testGetCol_MissingIndexReturnsEmpty(): void
    {
        $data = ['only'];
        $this->assertSame('', ImportVolumesCommand::getCol($data, 5));
    }

    public function testGetCol_EmptyArrayReturnsEmpty(): void
    {
        $this->assertSame('', ImportVolumesCommand::getCol([], 0));
    }
}
