<?php

namespace unit\scripts;

use CreateJournalCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/CreateJournalCommand.php';

/**
 * Unit tests for CreateJournalCommand.
 *
 * Focuses on pure static logic (no bootstrap, no DB, no filesystem) — the actual creation flow
 * is verified end-to-end against a real database (see docs/journal-provisioning.md).
 */
class CreateJournalCommandTest extends TestCase
{
    public function testCommandName(): void
    {
        $this->assertSame('journal:create', (new CreateJournalCommand())->getName());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function requiredValueOptionProvider(): array
    {
        return [
            'code' => ['code', true],
            'name' => ['name', true],
            'subtitle' => ['subtitle', true],
            'template-rvcode' => ['template-rvcode', true],
            'admin-uid' => ['admin-uid', true],
            'piwikid' => ['piwikid', true],
            'status' => ['status', true],
            'new-front' => ['new-front', true],
            'reset-doi' => ['reset-doi', false],
            'complete' => ['complete', false],
            'dry-run' => ['dry-run', false],
        ];
    }

    /** @dataProvider requiredValueOptionProvider */
    public function testOptionArity(string $option, bool $acceptsValue): void
    {
        $definition = (new CreateJournalCommand())->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption($option), "Missing option --$option");
        $this->assertSame($acceptsValue, $definition->getOption($option)->acceptValue());
    }

    public function testDoesNotDefineAVersionOption(): void
    {
        // --version/-V is reserved by Symfony\Component\Console\Application: an option named
        // "version" causes a fatal error on every invocation of this command.
        $definition = (new CreateJournalCommand())->getDefinition();
        $this->assertFalse($definition->hasOption('version'), 'Must not define a --version option (reserved by the Console Application)');
    }
}
