<?php

namespace unit\scripts;

use GenerateSitemapCommand;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
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

    public static function setUpBeforeClass(): void
    {
        if (!defined('DOMAIN')) {
            define('DOMAIN', 'episciences.org');
        }
        if (!defined('EPISCIENCES_API_URL')) {
            define('EPISCIENCES_API_URL', 'https://api.episciences.org/api/');
        }
    }

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
    // Helpers
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

    private function makeClient(array $responses): Client
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        return new Client(['handler' => $handler]);
    }

    private function callPrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod(GenerateSitemapCommand::class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($this->command, $args);
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

    public function testGetSitemapGenericEntries_AllEntriesHaveLocOnly(): void
    {
        foreach ($this->getSitemapGenericEntries('dmtcs') as $entry) {
            $this->assertArrayHasKey('loc', $entry);
            $this->assertArrayNotHasKey('changefreq', $entry);
            $this->assertArrayNotHasKey('priority', $entry);
        }
    }

    public function testGetSitemapGenericEntries_AllLocsContainRvcode(): void
    {
        foreach ($this->getSitemapGenericEntries('testjournal') as $entry) {
            $this->assertStringContainsString('testjournal', $entry['loc']);
        }
    }

    public function testGetSitemapGenericEntries_HomePageIsPresent(): void
    {
        $entries = $this->getSitemapGenericEntries('dmtcs');
        $homes   = array_filter($entries, fn($e) => str_ends_with($e['loc'], '/'));
        $this->assertCount(1, $homes);
    }

    public function testGetSitemapGenericEntries_AllExpectedPathsPresent(): void
    {
        $entries = $this->getSitemapGenericEntries('dmtcs');
        $locs    = array_column($entries, 'loc');

        foreach (['/articles', '/authors', '/volumes', '/sections', '/about'] as $path) {
            $found = array_filter($locs, fn($l) => str_ends_with($l, $path));
            $this->assertCount(1, $found, "Missing path {$path}");
        }
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

    public function testGetSitemapGenericEntries_WithLanguages_NoChangefreqOrPriority(): void
    {
        $entries = $this->getSitemapGenericEntries('jtcam', ['fr', 'en']);
        foreach ($entries as $entry) {
            $this->assertArrayNotHasKey('changefreq', $entry);
            $this->assertArrayNotHasKey('priority', $entry);
        }
    }

    // -------------------------------------------------------------------------
    // getSitemapArticleEntries() — lastmod from modification_date
    // -------------------------------------------------------------------------

    public function testGetSitemapArticleEntries_UsesModificationDateAsLastmod(): void
    {
        $body = json_encode([
            'hydra:member' => [
                [
                    'docid'    => 42,
                    'document' => [
                        'database' => [
                            'current' => [
                                'dates' => ['modification_date' => '2026-06-01 15:26:04'],
                            ],
                        ],
                    ],
                ],
            ],
            'hydra:view' => [],
        ]);

        $client  = $this->makeClient([new Response(200, [], $body)]);
        $logger  = $this->createMock(\Monolog\Logger::class);
        $entries = $this->callPrivate('getSitemapArticleEntries', ['jfp', $client, $logger, []]);

        $this->assertCount(1, $entries);
        $this->assertSame('2026-06-01', $entries[0]['lastmod']);
        $this->assertStringEndsWith('/articles/42', $entries[0]['loc']);
        $this->assertArrayNotHasKey('changefreq', $entries[0]);
        $this->assertArrayNotHasKey('priority', $entries[0]);
    }

    public function testGetSitemapArticleEntries_NullModificationDate_OmitsLastmod(): void
    {
        $body = json_encode([
            'hydra:member' => [['docid' => 7]],
            'hydra:view'   => [],
        ]);

        $client  = $this->makeClient([new Response(200, [], $body)]);
        $logger  = $this->createMock(\Monolog\Logger::class);
        $entries = $this->callPrivate('getSitemapArticleEntries', ['ops', $client, $logger, []]);

        $this->assertCount(1, $entries);
        $this->assertNull($entries[0]['lastmod']);
    }

    public function testGetSitemapArticleEntries_WithLanguages_GeneratesOneUrlPerLang(): void
    {
        $body = json_encode([
            'hydra:member' => [['docid' => 5]],
            'hydra:view'   => [],
        ]);

        $client  = $this->makeClient([new Response(200, [], $body)]);
        $logger  = $this->createMock(\Monolog\Logger::class);
        $entries = $this->callPrivate('getSitemapArticleEntries', ['jtcam', $client, $logger, ['fr', 'en']]);

        $this->assertCount(2, $entries);
        $this->assertStringContainsString('/fr/articles/5', $entries[0]['loc']);
        $this->assertStringContainsString('/en/articles/5', $entries[1]['loc']);
    }

    // -------------------------------------------------------------------------
    // getSitemapPageEntries()
    // -------------------------------------------------------------------------

    public function testGetSitemapPageEntries_ReturnsPageCodeAsPath(): void
    {
        $body = json_encode([
            ['page_code' => 'about',   'date_updated' => '2026-05-25T10:30:51+02:00'],
            ['page_code' => 'contact', 'date_updated' => null],
        ]);

        $client  = $this->makeClient([new Response(200, [], $body)]);
        $logger  = $this->createMock(\Monolog\Logger::class);
        $entries = $this->callPrivate('getSitemapPageEntries', ['jfp', $client, $logger, []]);

        $this->assertCount(2, $entries);
        $this->assertStringEndsWith('/about', $entries[0]['loc']);
        $this->assertStringEndsWith('/contact', $entries[1]['loc']);
    }

    public function testGetSitemapPageEntries_UsesDateUpdatedAsLastmod(): void
    {
        $body = json_encode([
            ['page_code' => 'about', 'date_updated' => '2026-05-25T10:30:51+02:00'],
        ]);

        $client  = $this->makeClient([new Response(200, [], $body)]);
        $logger  = $this->createMock(\Monolog\Logger::class);
        $entries = $this->callPrivate('getSitemapPageEntries', ['jfp', $client, $logger, []]);

        $this->assertSame('2026-05-25', $entries[0]['lastmod']);
    }

    public function testGetSitemapPageEntries_NullDateUpdated_OmitsLastmod(): void
    {
        $body = json_encode([
            ['page_code' => 'about', 'date_updated' => null],
        ]);

        $client  = $this->makeClient([new Response(200, [], $body)]);
        $logger  = $this->createMock(\Monolog\Logger::class);
        $entries = $this->callPrivate('getSitemapPageEntries', ['jfp', $client, $logger, []]);

        $this->assertNull($entries[0]['lastmod']);
    }

    public function testGetSitemapPageEntries_WithLanguages_GeneratesOneUrlPerLang(): void
    {
        $body = json_encode([
            ['page_code' => 'about', 'date_updated' => '2026-01-01T00:00:00+00:00'],
        ]);

        $client  = $this->makeClient([new Response(200, [], $body)]);
        $logger  = $this->createMock(\Monolog\Logger::class);
        $entries = $this->callPrivate('getSitemapPageEntries', ['jtcam', $client, $logger, ['fr', 'en']]);

        $this->assertCount(2, $entries);
        $this->assertStringContainsString('/fr/about', $entries[0]['loc']);
        $this->assertStringContainsString('/en/about', $entries[1]['loc']);
    }

    public function testGetSitemapPageEntries_NoChangefreqOrPriority(): void
    {
        $body = json_encode([
            ['page_code' => 'about', 'date_updated' => '2026-01-01T00:00:00+00:00'],
        ]);

        $client  = $this->makeClient([new Response(200, [], $body)]);
        $logger  = $this->createMock(\Monolog\Logger::class);
        $entries = $this->callPrivate('getSitemapPageEntries', ['jfp', $client, $logger, []]);

        $this->assertArrayNotHasKey('changefreq', $entries[0]);
        $this->assertArrayNotHasKey('priority', $entries[0]);
    }
}
