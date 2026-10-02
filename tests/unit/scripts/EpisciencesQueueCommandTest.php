<?php

namespace unit\scripts;

use EpisciencesQueueCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../../../scripts/EpisciencesQueueCommand.php';

/**
 * Unit tests for EpisciencesQueueCommand's option validation (--transport,
 * exactly-one-action), all of which runs before bootstrap() and is
 * therefore testable without a DB.
 */
class EpisciencesQueueCommandTest extends TestCase
{
    private Command $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $application = new Application();
        $application->add(new EpisciencesQueueCommand());
        $this->command = $application->find('episciences:queue');
        $this->commandTester = new CommandTester($this->command);
    }

    public function testCommandName(): void
    {
        self::assertSame('episciences:queue', $this->command->getName());
    }

    public function testHasExpectedOptions(): void
    {
        $definition = $this->command->getDefinition();
        self::assertInstanceOf(InputDefinition::class, $definition);

        foreach (['transport', 'stats', 'list-failed', 'retry', 'limit', 'setup', 'list-dispatch-failures', 'retry-dispatch-failure'] as $option) {
            self::assertTrue($definition->hasOption($option), "Missing option --{$option}");
        }
    }

    public function testFailsWhenTransportOptionIsMissing(): void
    {
        $this->commandTester->execute(['--stats' => true]);

        self::assertSame(1, $this->commandTester->getStatusCode());
        self::assertStringContainsString('--transport is required', $this->commandTester->getDisplay());
    }

    public function testFailsWhenTransportIsUnknown(): void
    {
        $this->commandTester->execute(['--transport' => 'not_a_real_transport', '--stats' => true]);

        self::assertSame(1, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Unknown transport "not_a_real_transport"', $this->commandTester->getDisplay());
    }

    public function testFailsWhenNoActionProvided(): void
    {
        $this->commandTester->execute(['--transport' => 'solr_index']);

        self::assertSame(1, $this->commandTester->getStatusCode());
        // SymfonyStyle word-wraps long error messages onto multiple lines, so
        // normalise whitespace before the substring check instead of
        // asserting on the terminal's wrapped output verbatim.
        self::assertStringContainsString(
            'Exactly one of --stats, --list-failed, --retry, --setup, --list-dispatch-failures or --retry-dispatch-failure',
            preg_replace('/\s+/', ' ', $this->commandTester->getDisplay())
        );
    }

    public function testFailsWhenMultipleActionsProvided(): void
    {
        $this->commandTester->execute(['--transport' => 'solr_index', '--stats' => true, '--list-failed' => true]);

        self::assertSame(1, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            'Exactly one of --stats, --list-failed, --retry, --setup, --list-dispatch-failures or --retry-dispatch-failure',
            preg_replace('/\s+/', ' ', $this->commandTester->getDisplay())
        );
    }
}
