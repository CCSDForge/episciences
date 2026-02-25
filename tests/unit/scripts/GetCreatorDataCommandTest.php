<?php

namespace unit\scripts;

use GetCreatorDataCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/GetCreatorDataCommand.php';

/**
 * Unit tests for GetCreatorDataCommand.
 *
 * Focuses on command metadata (no bootstrap, no DB, no HTTP).
 */
class GetCreatorDataCommandTest extends TestCase
{
    public function testCommandName(): void
    {
        $this->assertSame('enrichment:creators', (new GetCreatorDataCommand())->getName());
    }

    public function testCommandHasDryRunOption(): void
    {
        $definition = (new GetCreatorDataCommand())->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->acceptValue(), 'dry-run must be a flag');
    }

    public function testCommandHasNoCacheOption(): void
    {
        $definition = (new GetCreatorDataCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('no-cache'));
        $this->assertFalse($definition->getOption('no-cache')->acceptValue(), 'no-cache must be a flag');
    }

    public function testCommandHasDoiOption(): void
    {
        $definition = (new GetCreatorDataCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('doi'));
        $this->assertTrue($definition->getOption('doi')->isValueOptional(), 'doi must accept an optional value');
    }

    public function testCommandHasPaperidOption(): void
    {
        $definition = (new GetCreatorDataCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('paperid'));
        $this->assertTrue($definition->getOption('paperid')->isValueOptional(), 'paperid must accept an optional value');
    }
}
