<?php

namespace unit\scripts;

use GetLinkDataCommand;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../scripts/GetLinkDataCommand.php';

/**
 * Unit tests for GetLinkDataCommand.
 *
 * Focuses on command metadata (no bootstrap, no DB, no filesystem/network access).
 */
class GetLinkDataCommandTest extends TestCase
{
    private GetLinkDataCommand $command;

    protected function setUp(): void
    {
        $this->command = new GetLinkDataCommand();
    }

    public function testCommandName(): void
    {
        $this->assertSame('enrichment:links', $this->command->getName());
    }

    public function testCommandHasRequiredOptions(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->acceptValue());

        $this->assertTrue($definition->hasOption('rvcode'));
        $this->assertTrue($definition->getOption('rvcode')->isValueRequired());

        $this->assertTrue($definition->hasOption('doi'));
        $this->assertTrue($definition->getOption('doi')->isValueRequired());

        $this->assertTrue($definition->hasOption('docid'));
        $this->assertTrue($definition->getOption('docid')->isValueRequired());

        $this->assertTrue($definition->hasOption('type'));
        $this->assertTrue($definition->getOption('type')->isValueOptional());

        $this->assertTrue($definition->hasOption('no-cache'));
        $this->assertFalse($definition->getOption('no-cache')->acceptValue());

        $this->assertTrue($definition->hasOption('no-bidirectional'));
        $this->assertFalse($definition->getOption('no-bidirectional')->acceptValue());
    }

    public function testTypeOptionDefaultsToDataset(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertSame('dataset', $definition->getOption('type')->getDefault());
    }
}
