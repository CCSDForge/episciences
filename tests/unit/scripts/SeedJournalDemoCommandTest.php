<?php

namespace unit\scripts;

use PHPUnit\Framework\TestCase;
use SeedJournalDemoCommand;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/SeedJournalDemoCommand.php';

/**
 * Unit tests for SeedJournalDemoCommand.
 *
 * Focuses on pure static logic (no bootstrap, no DB, no network) — the actual seeding flow
 * (real HAL/arXiv/Zenodo/BAOBAB metadata fetches) is verified end-to-end against a real
 * database, see docs/journal-provisioning.md.
 */
class SeedJournalDemoCommandTest extends TestCase
{
    public function testCommandName(): void
    {
        $this->assertSame('journal:seed-demo', (new SeedJournalDemoCommand())->getName());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function optionArityProvider(): array
    {
        return [
            'rvcode' => ['rvcode', true],
            'uid' => ['uid', true],
            'csv-file' => ['csv-file', true],
            'force' => ['force', false],
            'dry-run' => ['dry-run', false],
        ];
    }

    /** @dataProvider optionArityProvider */
    public function testOptionArity(string $option, bool $acceptsValue): void
    {
        $definition = (new SeedJournalDemoCommand())->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption($option), "Missing option --$option");
        $this->assertSame($acceptsValue, $definition->getOption($option)->acceptValue());
    }

    public function testCsvFileDefaultsToTheVersionedDemoDataset(): void
    {
        $definition = (new SeedJournalDemoCommand())->getDefinition();
        $default = $definition->getOption('csv-file')->getDefault();
        $this->assertIsString($default);
        $this->assertStringEndsWith('importSamples/demo-papers.csv', $default);
        $this->assertFileExists($default);
    }

    public function testDoesNotDefineAVersionOption(): void
    {
        // --version/-V is reserved by Symfony\Component\Console\Application.
        $definition = (new SeedJournalDemoCommand())->getDefinition();
        $this->assertFalse($definition->hasOption('version'), 'Must not define a --version option (reserved by the Console Application)');
    }
}
