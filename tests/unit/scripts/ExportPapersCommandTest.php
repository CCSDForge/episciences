<?php

namespace unit\scripts;

use ExportPapersCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/ExportPapersCommand.php';

/**
 * Unit tests for ExportPapersCommand.
 *
 * Focuses on pure static logic (no bootstrap, no DB, no filesystem).
 */
class ExportPapersCommandTest extends TestCase
{
    public function testCommandName(): void
    {
        $this->assertSame('export:papers', (new ExportPapersCommand())->getName());
    }

    public function testCommandHasRvidOption(): void
    {
        $definition = (new ExportPapersCommand())->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('rvid'));
        $this->assertTrue($definition->getOption('rvid')->isValueRequired(), 'rvid must require a value');
    }

    public function testCommandHasCsvFileOption(): void
    {
        $definition = (new ExportPapersCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('csv-file'));
        $this->assertTrue($definition->getOption('csv-file')->isValueRequired(), 'csv-file must require a value');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function scalarFilterOptionProvider(): array
    {
        return [
            'volume-id' => ['volume-id'],
            'section-id' => ['section-id'],
            'year' => ['year'],
            'identifier' => ['identifier'],
            'version' => ['version'],
            'repoid' => ['repoid'],
            'uid' => ['uid'],
            'sql-where' => ['sql-where'],
            'limit' => ['limit'],
        ];
    }

    /** @dataProvider scalarFilterOptionProvider */
    public function testCommandHasScalarFilterOption(string $option): void
    {
        $definition = (new ExportPapersCommand())->getDefinition();
        $this->assertTrue($definition->hasOption($option), "Missing option --{$option}");
        $this->assertTrue($definition->getOption($option)->isValueRequired(), "--{$option} must require a value");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function repeatableFilterOptionProvider(): array
    {
        return [
            'docid' => ['docid'],
            'status' => ['status'],
        ];
    }

    /** @dataProvider repeatableFilterOptionProvider */
    public function testCommandHasRepeatableFilterOption(string $option): void
    {
        $definition = (new ExportPapersCommand())->getDefinition();
        $this->assertTrue($definition->hasOption($option), "Missing option --{$option}");
        $this->assertTrue($definition->getOption($option)->isArray(), "--{$option} must accept multiple values");
    }

    public function testCommandHasNoDryRunOption(): void
    {
        // Export never writes to the database — there is nothing to simulate.
        $definition = (new ExportPapersCommand())->getDefinition();
        $this->assertFalse($definition->hasOption('dry-run'), 'export:papers has no side effect to dry-run');
    }
}
