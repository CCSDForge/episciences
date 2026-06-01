<?php

namespace unit\scripts;

use GenerateSitemapCommand;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
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

    public function testCommandHasRvcodeOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('rvcode'));
        $this->assertTrue($definition->getOption('rvcode')->isValueRequired(), '--rvcode must require a value');
    }

    public function testCommandHasAllOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('all'));
        $this->assertFalse($definition->getOption('all')->acceptValue(), '--all must be a flag');
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
    private function getSitemapGenericEntries(string $rvcode, array $languages = []): array
    {
        $method = new ReflectionMethod(GenerateSitemapCommand::class, 'getSitemapGenericEntries');
        $method->setAccessible(true);
        return $method->invoke($this->command, $rvcode, $languages);
    }

    /** @return string[] */
    private function buildLocUrls(string $base, string $path, array $languages): array
    {
        $method = new ReflectionMethod(GenerateSitemapCommand::class, 'buildLocUrls');
        $method->setAccessible(true);
        return $method->invoke($this->command, $base, $path, $languages);
    }

    // -------------------------------------------------------------------------
    // buildLocUrls()
    // -------------------------------------------------------------------------

    public function testBuildLocUrls_NoLanguages_ReturnsSingleUrl(): void
    {
        $urls = $this->buildLocUrls('https://dmtcs.episciences.org', '/articles/123', []);
        $this->assertSame(['https://dmtcs.episciences.org/articles/123'], $urls);
    }

    public function testBuildLocUrls_WithLanguages_ReturnsOneUrlPerLanguage(): void
    {
        $urls = $this->buildLocUrls('https://jtcam.episciences.org', '/articles/456', ['fr', 'en']);
        $this->assertSame([
            'https://jtcam.episciences.org/fr/articles/456',
            'https://jtcam.episciences.org/en/articles/456',
        ], $urls);
    }

    public function testBuildLocUrls_HomePath_NoLanguages(): void
    {
        $urls = $this->buildLocUrls('https://dmtcs.episciences.org', '/', []);
        $this->assertSame(['https://dmtcs.episciences.org/'], $urls);
    }

    public function testBuildLocUrls_HomePath_WithLanguages(): void
    {
        $urls = $this->buildLocUrls('https://jtcam.episciences.org', '/', ['en']);
        $this->assertSame(['https://jtcam.episciences.org/en/'], $urls);
    }

    // -------------------------------------------------------------------------
    // getSitemapGenericEntries() — no language prefix (legacy behaviour)
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // getSitemapGenericEntries() — with language prefixes (new sites)
    // -------------------------------------------------------------------------

    public function testGetSitemapGenericEntries_WithTwoLanguages_ReturnsTwelveEntries(): void
    {
        // 6 paths × 2 languages = 12
        $entries = $this->getSitemapGenericEntries('jtcam', ['fr', 'en']);
        $this->assertCount(12, $entries);
    }

    public function testGetSitemapGenericEntries_WithLanguages_AllLocsHaveLangPrefix(): void
    {
        $entries = $this->getSitemapGenericEntries('jtcam', ['fr', 'en']);
        foreach ($entries as $entry) {
            $this->assertMatchesRegularExpression(
                '#https://jtcam\.[^/]+/(fr|en)/#',
                $entry['loc'] . (str_ends_with($entry['loc'], '/') ? '' : '/')
            );
        }
    }

    public function testGetSitemapGenericEntries_WithOneLanguage_ReturnsSixEntries(): void
    {
        $entries = $this->getSitemapGenericEntries('dmtcs', ['en']);
        $this->assertCount(6, $entries);
    }

    public function testGetSitemapGenericEntries_WithLanguages_HomeHasPriorityOne(): void
    {
        $entries = $this->getSitemapGenericEntries('jtcam', ['fr', 'en']);
        $homes   = array_filter($entries, fn($e) => str_ends_with($e['loc'], '/en/') || str_ends_with($e['loc'], '/fr/'));
        $this->assertCount(2, $homes);
        foreach ($homes as $home) {
            $this->assertSame('1', $home['priority']);
            $this->assertSame('daily', $home['changefreq']);
        }
    }
}
