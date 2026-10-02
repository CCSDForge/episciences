<?php

namespace unit\scripts;

use ExportPapersCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
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

    /**
     * A command-local option sharing a name with one of the Application's own
     * (--version, --help, --quiet, ...) fatals every real invocation via console.php
     * with "An option named '...' already exists.", but getDefinition() alone — used by
     * the other tests below — never exercises that merge, so it wouldn't have caught it.
     */
    public function testCommandDefinitionDoesNotCollideWithApplicationOptions(): void
    {
        $application = new Application();
        $application->add(new ExportPapersCommand());

        $command = $application->find('export:papers');
        $command->mergeApplicationDefinition();

        $this->addToAssertionCount(1);
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
            'paper-version' => ['paper-version'],
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
