<?php

namespace unit\scripts;

use ClearOpenAireCacheCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/ClearOpenAireCacheCommand.php';

/**
 * Unit tests for ClearOpenAireCacheCommand.
 *
 * Focuses on command metadata (no bootstrap, no DB, no filesystem access).
 */
class ClearOpenAireCacheCommandTest extends TestCase
{
    private ClearOpenAireCacheCommand $command;

    protected function setUp(): void
    {
        $this->command = new ClearOpenAireCacheCommand();
    }

    public function testCommandName(): void
    {
        $this->assertSame('enrichment:clear-cache', $this->command->getName());
    }

    public function testCommandHasNoOptions(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertSame([], $definition->getOptions());
    }

    public function testCommandHasDescription(): void
    {
        $this->assertNotSame('', $this->command->getDescription());
    }
}
