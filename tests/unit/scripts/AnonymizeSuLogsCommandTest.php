<?php

declare(strict_types=1);

namespace unit\scripts;

use AnonymizeSuLogsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

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
}
