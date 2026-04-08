<?php

namespace unit\scripts;

use GenerateSitemapCommand;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/GenerateSitemapCommand.php';

/**
 * Unit tests for GenerateSitemapCommand.
 *
 * Focuses on pure logic (no bootstrap, no HTTP) via reflection.
 */
class GenerateSitemapCommandTest extends TestCase
{
    private GenerateSitemapCommand $command;

    protected function setUp(): void
    {
        $this->command = new GenerateSitemapCommand();
    }

    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        $this->assertSame('sitemap:generate', $this->command->getName());
    }

    public function testCommandHasRvcodeArgument(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasArgument('rvcode'));
        $this->assertSame(InputArgument::REQUIRED, $definition->getArgument('rvcode')->isRequired() ? InputArgument::REQUIRED : InputArgument::OPTIONAL);
    }

    public function testCommandHasPrettyOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('pretty'));
        $this->assertFalse($definition->getOption('pretty')->acceptValue(), 'pretty must be a flag');
    }

    // -------------------------------------------------------------------------
    // getSitemapGenericEntries() — tested via reflection (pure, no HTTP/DB)
    // -------------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function getSitemapGenericEntries(string $rvcode): array
    {
        $method = new ReflectionMethod(GenerateSitemapCommand::class, 'getSitemapGenericEntries');
        $method->setAccessible(true);
        return $method->invoke($this->command, $rvcode);
    }

    public function testGetSitemapGenericEntries_ReturnsSixEntries(): void
    {
        // 1 (home) + 2 (articles, authors) + 3 (volumes, sections, about) = 6
        $entries = $this->getSitemapGenericEntries('dmtcs');
        $this->assertCount(6, $entries);
    }

    public function testGetSitemapGenericEntries_AllEntriesHaveRequiredKeys(): void
    {
        foreach ($this->getSitemapGenericEntries('dmtcs') as $entry) {
            $this->assertArrayHasKey('loc', $entry);
            $this->assertArrayHasKey('changefreq', $entry);
            $this->assertArrayHasKey('priority', $entry);
        }
    }

    public function testGetSitemapGenericEntries_AllLocsContainRvcode(): void
    {
        foreach ($this->getSitemapGenericEntries('testjournal') as $entry) {
            $this->assertStringContainsString('testjournal', $entry['loc']);
        }
    }

    public function testGetSitemapGenericEntries_HomePageHasPriorityOneAndDailyFreq(): void
    {
        $entries = $this->getSitemapGenericEntries('dmtcs');
        $home    = array_filter($entries, fn($e) => str_ends_with($e['loc'], '/'));
        $this->assertCount(1, $home);

        $home = array_values($home)[0];
        $this->assertSame('1', $home['priority']);
        $this->assertSame('daily', $home['changefreq']);
    }

    public function testGetSitemapGenericEntries_ArticlesAndAuthorsHaveDailyFreq(): void
    {
        $entries = $this->getSitemapGenericEntries('dmtcs');
        $daily   = array_filter($entries, fn($e) => $e['changefreq'] === 'daily' && $e['priority'] === '0.8');
        $locs    = array_column(array_values($daily), 'loc');

        $this->assertCount(2, $daily);
        $hasArticles = (bool) array_filter($locs, fn($l) => str_ends_with($l, '/articles'));
        $hasAuthors  = (bool) array_filter($locs, fn($l) => str_ends_with($l, '/authors'));
        $this->assertTrue($hasArticles, '/articles should have daily changefreq');
        $this->assertTrue($hasAuthors,  '/authors should have daily changefreq');
    }

    public function testGetSitemapGenericEntries_VolumesHaveWeeklyFreq(): void
    {
        $entries = $this->getSitemapGenericEntries('dmtcs');
        $weekly  = array_filter($entries, fn($e) => $e['changefreq'] === 'weekly');
        $this->assertCount(3, $weekly);
    }

    public function testGetSitemapGenericEntries_AllLocsStartWithHttps(): void
    {
        foreach ($this->getSitemapGenericEntries('dmtcs') as $entry) {
            $this->assertStringStartsWith('https://', $entry['loc']);
        }
    }
}
