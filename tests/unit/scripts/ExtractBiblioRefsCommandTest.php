<?php

namespace unit\scripts;

use ExtractBiblioRefsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/ExtractBiblioRefsCommand.php';

/**
 * Unit tests for ExtractBiblioRefsCommand.
 *
 * Covers command metadata only — no bootstrap, no DB, no HTTP.
 */
class ExtractBiblioRefsCommandTest extends TestCase
{
    private InputDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = (new ExtractBiblioRefsCommand())->getDefinition();
    }

    public function testCommandName(): void
    {
        $this->assertSame('enrichment:extract-biblio-refs', (new ExtractBiblioRefsCommand())->getName());
    }

    public function testCommandHasDescription(): void
    {
        $description = (new ExtractBiblioRefsCommand())->getDescription();
        $this->assertNotEmpty($description);
    }

    public function testDryRunIsFlag(): void
    {
        $this->assertTrue($this->definition->hasOption('dry-run'));
        $this->assertFalse($this->definition->getOption('dry-run')->acceptValue(), 'dry-run must be a flag');
    }

    public function testPublishedIsFlag(): void
    {
        $this->assertTrue($this->definition->hasOption('published'));
        $this->assertFalse($this->definition->getOption('published')->acceptValue(), 'published must be a flag');
    }

    public function testAcceptedIsFlag(): void
    {
        $this->assertTrue($this->definition->hasOption('accepted'));
        $this->assertFalse($this->definition->getOption('accepted')->acceptValue(), 'accepted must be a flag');
    }

    public function testDocidAcceptsRequiredValue(): void
    {
        $this->assertTrue($this->definition->hasOption('docid'));
        $this->assertTrue($this->definition->getOption('docid')->isValueRequired(), 'docid must require a value');
    }

    public function testRvcodeAcceptsRequiredValue(): void
    {
        $this->assertTrue($this->definition->hasOption('rvcode'));
        $this->assertTrue($this->definition->getOption('rvcode')->isValueRequired(), 'rvcode must require a value');
    }

    public function testApiUrlAcceptsRequiredValue(): void
    {
        $this->assertTrue($this->definition->hasOption('api-url'));
        $this->assertTrue($this->definition->getOption('api-url')->isValueRequired(), 'api-url must require a value');
    }
}
