<?php

namespace unit\scripts;

use EpisciencesWorkerCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../../../scripts/EpisciencesWorkerCommand.php';

/**
 * Unit tests for EpisciencesWorkerCommand's metadata and --transport
 * validation, which runs before any bootstrap and is therefore testable
 * without a DB. parseMemoryLimit() itself is covered by
 * ParsesMemoryLimitTest.
 */
class EpisciencesWorkerCommandTest extends TestCase
{
    private EpisciencesWorkerCommand $command;

    protected function setUp(): void
    {
        $this->command = new EpisciencesWorkerCommand();
    }

    public function testCommandName(): void
    {
        self::assertSame('episciences:worker', $this->command->getName());
    }

    public function testHasExpectedOptions(): void
    {
        $definition = $this->command->getDefinition();
        self::assertInstanceOf(InputDefinition::class, $definition);

        foreach (['transport', 'limit', 'time-limit', 'memory-limit'] as $option) {
            self::assertTrue($definition->hasOption($option), "Missing option --{$option}");
        }
    }

    public function testFailsWhenTransportOptionIsMissing(): void
    {
        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('--transport is required', $tester->getDisplay());
    }

    public function testFailsWhenTransportIsUnknown(): void
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['--transport' => 'not_a_real_transport']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Unknown transport "not_a_real_transport"', $tester->getDisplay());
    }
}
