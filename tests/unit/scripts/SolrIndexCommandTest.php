<?php

namespace unit\scripts;

use PHPUnit\Framework\TestCase;
use SolrIndexCommand;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/SolrIndexCommand.php';

/**
 * Unit tests for SolrIndexCommand metadata/option surface (no bootstrap, no DB).
 */
class SolrIndexCommandTest extends TestCase
{
    private SolrIndexCommand $command;

    protected function setUp(): void
    {
        $this->command = new SolrIndexCommand();
    }

    public function testCommandName(): void
    {
        self::assertSame('solr:index', $this->command->getName());
    }

    public function testHasExpectedOptions(): void
    {
        $definition = $this->command->getDefinition();
        self::assertInstanceOf(InputDefinition::class, $definition);

        foreach (['docid', 'sqlwhere', 'file', 'priority', 'sync'] as $option) {
            self::assertTrue($definition->hasOption($option), "Missing option --{$option}");
        }
    }

    public function testSyncIsAFlagNotAValueOption(): void
    {
        self::assertFalse($this->command->getDefinition()->getOption('sync')->acceptValue());
    }
}
