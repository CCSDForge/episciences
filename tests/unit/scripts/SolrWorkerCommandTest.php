<?php

namespace unit\scripts;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SolrWorkerCommand;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/SolrWorkerCommand.php';

/**
 * Unit tests for SolrWorkerCommand's metadata and pure parseMemoryLimit()
 * logic (no bootstrap, no DB, no actual worker run).
 */
class SolrWorkerCommandTest extends TestCase
{
    private SolrWorkerCommand $command;

    protected function setUp(): void
    {
        $this->command = new SolrWorkerCommand();
    }

    public function testCommandName(): void
    {
        self::assertSame('solr:worker', $this->command->getName());
    }

    public function testHasExpectedOptions(): void
    {
        $definition = $this->command->getDefinition();
        self::assertInstanceOf(InputDefinition::class, $definition);

        foreach (['limit', 'time-limit', 'memory-limit'] as $option) {
            self::assertTrue($definition->hasOption($option), "Missing option --{$option}");
        }
    }

    /** @dataProvider memoryLimitProvider */
    public function testParseMemoryLimit(string $input, int $expectedBytes): void
    {
        $method = new ReflectionMethod(SolrWorkerCommand::class, 'parseMemoryLimit');
        $method->setAccessible(true);

        self::assertSame($expectedBytes, $method->invoke($this->command, $input));
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function memoryLimitProvider(): array
    {
        return [
            'gigabytes' => ['1G', 1024 * 1024 * 1024],
            'megabytes' => ['512M', 512 * 1024 * 1024],
            'kilobytes' => ['256K', 256 * 1024],
            'plain bytes' => ['1000', 1000],
        ];
    }
}
