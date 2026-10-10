<?php

declare(strict_types=1);

namespace unit\scripts;

use AnonymizeSuLogsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../../../scripts/AnonymizeSuLogsCommand.php';

/**
 * Unit tests for AnonymizeSuLogsCommand.
 */
class AnonymizeSuLogsCommandTest extends TestCase
{
    private AnonymizeSuLogsCommand $command;

    protected function setUp(): void
    {
        $this->command = new AnonymizeSuLogsCommand();
    }

    public function testCommandName(): void
    {
        $this->assertSame('user:anonymize-su-logs', $this->command->getName());
    }

    public function testCommandHasDaysOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('days'));
        $this->assertTrue($definition->getOption('days')->isValueRequired(), '--days must require a value');
        $this->assertSame('365', $definition->getOption('days')->getDefault());
    }

    /**
     * Refused before the application is bootstrapped: no record is touched
     * @dataProvider invalidDays
     */
    public function testAnInvalidRetentionThresholdIsRefused(string $days): void
    {
        $tester = new CommandTester($this->command);

        $this->assertSame(Command::FAILURE, $tester->execute(['--days' => $days]));
        $this->assertStringContainsString('must be a positive integer', $tester->getDisplay());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDays(): iterable
    {
        yield 'typo of one year' => ['1y'];
        yield 'zero' => ['0'];
        yield 'negative' => ['-30'];
        yield 'decimal' => ['1.5'];
        yield 'empty' => [''];
    }
}
