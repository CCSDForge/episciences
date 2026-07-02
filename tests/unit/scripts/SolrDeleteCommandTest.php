<?php

namespace unit\scripts;

use PHPUnit\Framework\TestCase;
use SolrDeleteCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../../../scripts/SolrDeleteCommand.php';

/**
 * Unit tests for SolrDeleteCommand. The --docid/--query mutual-exclusivity
 * check runs before bootstrap(), so it's testable without a DB.
 */
class SolrDeleteCommandTest extends TestCase
{
    private Command $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $application = new Application();
        $application->add(new SolrDeleteCommand());
        $this->command = $application->find('solr:delete');
        $this->commandTester = new CommandTester($this->command);
    }

    public function testCommandName(): void
    {
        self::assertSame('solr:delete', $this->command->getName());
    }

    public function testHasExpectedOptions(): void
    {
        $definition = $this->command->getDefinition();
        self::assertInstanceOf(InputDefinition::class, $definition);

        foreach (['docid', 'query', 'sync'] as $option) {
            self::assertTrue($definition->hasOption($option), "Missing option --{$option}");
        }
    }

    public function testFailsWhenNeitherDocIdNorQueryProvided(): void
    {
        $this->commandTester->execute([]);

        self::assertSame(1, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Exactly one of --docid or --query', $this->commandTester->getDisplay());
    }

    public function testFailsWhenBothDocIdAndQueryProvided(): void
    {
        $this->commandTester->execute(['--docid' => '42', '--query' => 'docid:42']);

        self::assertSame(1, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Exactly one of --docid or --query', $this->commandTester->getDisplay());
    }
}
