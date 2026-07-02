<?php

namespace unit\scripts;

use PHPUnit\Framework\TestCase;
use SolrQueueCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../../../scripts/SolrQueueCommand.php';

/**
 * Unit tests for SolrQueueCommand's option validation, which runs before
 * bootstrap() and is therefore testable without a DB.
 */
class SolrQueueCommandTest extends TestCase
{
    private Command $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $application = new Application();
        $application->add(new SolrQueueCommand());
        $this->command = $application->find('solr:queue');
        $this->commandTester = new CommandTester($this->command);
    }

    public function testCommandName(): void
    {
        self::assertSame('solr:queue', $this->command->getName());
    }

    public function testHasExpectedOptions(): void
    {
        $definition = $this->command->getDefinition();
        self::assertInstanceOf(InputDefinition::class, $definition);

        foreach (['stats', 'list-failed', 'retry', 'limit', 'setup'] as $option) {
            self::assertTrue($definition->hasOption($option), "Missing option --{$option}");
        }
    }

    public function testFailsWhenNoActionProvided(): void
    {
        $this->commandTester->execute([]);

        self::assertSame(1, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Exactly one of --stats, --list-failed, --retry or --setup', $this->commandTester->getDisplay());
    }

    public function testFailsWhenMultipleActionsProvided(): void
    {
        $this->commandTester->execute(['--stats' => true, '--list-failed' => true]);

        self::assertSame(1, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Exactly one of --stats, --list-failed, --retry or --setup', $this->commandTester->getDisplay());
    }
}
