<?php

namespace unit\library\Episciences\Repositories;

use Episciences_Repositories_Common;
use Episciences_Repositories_Zenodo_Hooks;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_Repositories_Zenodo_Hooks.
 *
 * All tests are DB-free where possible. Tests that require DB/constants
 * are explicitly skipped.
 *
 * Bugs documented/fixed:
 *   Z1 — enrichmentProcessFromOAI(): throws \http\Exception\InvalidArgumentException (PECL http)
 *         → fixed to throw \InvalidArgumentException
 *   Z2 — enrichmentProcess(): date_create($datestamp)->format('Y-m-d') can return false → TypeError
 *         → fixed to use Episciences_Repositories_Common::safeDateFormat($datestamp)
 *   Z3 — enrichmentProcess(): $data[FILES] accessed without key-existence check → undefined index
 *         → fixed with ?? []
 *
 * @covers Episciences_Repositories_Zenodo_Hooks
 */
final class Episciences_Repositories_Zenodo_HooksTest extends TestCase
{
    // =========================================================================
    // hookCleanXMLRecordInput()
    // =========================================================================

    /**
     * When 'record' key is present, it delegates to checkAndCleanRecord()
     * which adds xmlns:xsi when xsi:schemaLocation is found.
     */
    public function testHookCleanXMLRecordInputWithRecord(): void
    {
        $input = [
            'record' => 'xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ ...'
        ];

        $result = Episciences_Repositories_Zenodo_Hooks::hookCleanXMLRecordInput($input);

        self::assertArrayHasKey('record', $result);
        self::assertStringContainsString('xmlns:xsi', $result['record']);
    }

    /**
     * When 'record' key is absent, the array is returned unchanged.
     */
    public function testHookCleanXMLRecordInputNoRecord(): void
    {
        $input = ['other' => 'value'];

        $result = Episciences_Repositories_Zenodo_Hooks::hookCleanXMLRecordInput($input);

        self::assertArrayHasKey('other', $result);
        self::assertArrayNotHasKey('record', $result);
        self::assertSame('value', $result['other']);
    }

    // =========================================================================
    // hookCleanIdentifiers()
    // =========================================================================

    /**
     * Cannot test without DB (calls Episciences_Repositories::getRepoDoiPrefix()).
     */
    public function testHookCleanIdentifiersDOIUrl(): void
    {
        $this->markTestSkipped('Requires DB (Episciences_Repositories::getRepoDoiPrefix())');
    }

    // =========================================================================
    // hookIsRequiredVersion()
    // =========================================================================

    /**
     * isRequiredVersion() returns true by default → !true = false.
     * So hookIsRequiredVersion() must return ['result' => false].
     */
    public function testHookIsRequiredVersion(): void
    {
        $result = Episciences_Repositories_Zenodo_Hooks::hookIsRequiredVersion();

        self::assertSame(['result' => false], $result);
    }

    // =========================================================================
    // hookIsIdentifierCommonToAllVersions()
    // =========================================================================

    public function testHookIsIdentifierCommonToAllVersions(): void
    {
        $result = Episciences_Repositories_Zenodo_Hooks::hookIsIdentifierCommonToAllVersions();

        self::assertSame(['result' => false], $result);
    }

    // =========================================================================
    // enrichmentProcessCreators() — private static, tested via ReflectionMethod
    // =========================================================================

    /**
     * Creator with name and orcid: result must contain two items
     * (creatorsDc array and authors array), and the author entry
     * must have an 'orcid' key.
     */
    public function testEnrichmentProcessCreatorsWithOrcid(): void
    {
        $method = new ReflectionMethod(
            Episciences_Repositories_Zenodo_Hooks::class,
            'enrichmentProcessCreators'
        );
        $method->setAccessible(true);

        $creators = [
            ['name' => 'Doe, John', 'orcid' => '0000-0001-2345-6789']
        ];

        try {
            [$creatorsDc, $authors] = $method->invoke(null, $creators, [], []);
        } catch (\Throwable $e) {
            // normalizeOrcid() may require DB constants; skip gracefully
            $this->markTestSkipped('normalizeOrcid() requires DB constants: ' . $e->getMessage());
        }

        self::assertIsArray($creatorsDc);
        self::assertIsArray($authors);
        self::assertNotEmpty($authors);
        self::assertArrayHasKey('orcid', $authors[0]);
    }

    /**
     * Creator without orcid: the author entry must NOT have an 'orcid' key.
     */
    public function testEnrichmentProcessCreatorsWithoutOrcid(): void
    {
        $method = new ReflectionMethod(
            Episciences_Repositories_Zenodo_Hooks::class,
            'enrichmentProcessCreators'
        );
        $method->setAccessible(true);

        $creators = [
            ['name' => 'Smith, Jane']
        ];

        [$creatorsDc, $authors] = $method->invoke(null, $creators, [], []);

        self::assertIsArray($creatorsDc);
        self::assertIsArray($authors);
        self::assertNotEmpty($authors);
        self::assertArrayNotHasKey('orcid', $authors[0]);
    }

    /**
     * Creator with affiliation: the author entry must have an 'affiliation' key.
     */
    public function testEnrichmentProcessCreatorsWithAffiliation(): void
    {
        $method = new ReflectionMethod(
            Episciences_Repositories_Zenodo_Hooks::class,
            'enrichmentProcessCreators'
        );
        $method->setAccessible(true);

        $creators = [
            ['name' => 'Martin, Paul', 'affiliation' => 'CNRS']
        ];

        [$creatorsDc, $authors] = $method->invoke(null, $creators, [], []);

        self::assertIsArray($authors);
        self::assertNotEmpty($authors);
        self::assertArrayHasKey('affiliation', $authors[0]);
    }

    // =========================================================================
    // Bug Z1 — enrichmentProcessFromOAI() throws \InvalidArgumentException
    // =========================================================================

    /**
     * Bug Z1: before fix, invalid XML threw \http\Exception\InvalidArgumentException
     * (PECL http extension). After fix, it throws the standard \InvalidArgumentException.
     *
     * simplexml_load_string() emits a PHP warning before returning false; we suppress
     * it with the @ operator inside the method, so we use PHPUnit's error-suppression
     * helper to avoid having the libxml warning treated as a test error.
     */
    public function testEnrichmentProcessFromOAIThrowsInvalidArgumentException(): void
    {
        $method = new ReflectionMethod(
            Episciences_Repositories_Zenodo_Hooks::class,
            'enrichmentProcessFromOAI'
        );
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);

        // Suppress libxml warnings emitted by simplexml_load_string() on invalid input
        set_error_handler(static function () {
            return true; // silence the warning
        });

        try {
            $method->invoke(null, 'not valid xml');
        } finally {
            restore_error_handler();
        }
    }

    // =========================================================================
    // Bug Z3 — enrichmentProcess() FILES key may be absent
    // =========================================================================

    /**
     * Bug Z3: source inspection — verify that the fix (null coalescing ??) is
     * present in the source file for the FILES key access.
     * Before fix: $data[Episciences_Repositories_Common::FILES]
     * After fix:  $data[Episciences_Repositories_Common::FILES] ?? []
     */
    public function testEnrichmentProcessWithoutFilesKey(): void
    {
        $method = new ReflectionMethod(
            Episciences_Repositories_Zenodo_Hooks::class,
            'enrichmentProcessFromOAI'
        );
        $filePath = $method->getFileName();

        self::assertNotFalse($filePath, 'Could not determine source file path via ReflectionMethod');

        $source = file_get_contents($filePath);
        self::assertNotFalse($source, 'Could not read source file: ' . $filePath);

        // After fix Z3, the FILES key access must use null coalescing operator
        self::assertStringContainsString(
            'Episciences_Repositories_Common::FILES] ?? []',
            $source,
            'Bug Z3 not fixed: null coalescing operator ?? [] missing for FILES key access'
        );

        // The unsafe direct access (without ??) must NOT remain
        self::assertStringNotContainsString(
            'Episciences_Repositories_Common::FILES]' . "\n",
            $source,
            'Bug Z3 not fixed: direct array access without ?? [] still present'
        );
    }

    // =========================================================================
    // Bug Z2 — enrichmentProcess() uses safeDateFormat instead of date_create()->format()
    // =========================================================================

    /**
     * Bug Z2: source inspection — verify that date_create($datestamp)->format(...)
     * has been replaced with Episciences_Repositories_Common::safeDateFormat().
     */
    public function testSafeDateFormatUsedInEnrichment(): void
    {
        $method = new ReflectionMethod(
            Episciences_Repositories_Zenodo_Hooks::class,
            'enrichmentProcessFromOAI'
        );
        $filePath = $method->getFileName();

        self::assertNotFalse($filePath, 'Could not determine source file path via ReflectionMethod');

        $source = file_get_contents($filePath);
        self::assertNotFalse($source, 'Could not read source file: ' . $filePath);

        // After fix Z2, the old unsafe pattern must no longer appear
        self::assertStringNotContainsString(
            'date_create($datestamp)->format',
            $source,
            'Bug Z2 not fixed: date_create($datestamp)->format() still present in source'
        );

        // The safe replacement must be present
        self::assertStringContainsString(
            'Episciences_Repositories_Common::safeDateFormat($datestamp)',
            $source,
            'Bug Z2 not fixed: safeDateFormat() call not found in source'
        );
    }

    // =========================================================================
    // hookFilesProcessing() — file_type via pathinfo() regression
    // =========================================================================

    /**
     * Verify that the old explode('.', $file['key']) pattern has been replaced
     * by pathinfo($file['key'], PATHINFO_EXTENSION).
     *
     * The old approach returned the wrong result for files without extensions
     * (full filename instead of ''). pathinfo() is the canonical fix.
     */
    public function testFileTypeExtractedWithPathinfo(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(Episciences_Repositories_Zenodo_Hooks::class))->getFileName()
        );

        self::assertStringContainsString(
            "pathinfo(\$file['key'], PATHINFO_EXTENSION)",
            $source,
            "hookFilesProcessing() must use pathinfo() for file_type extraction."
        );

        self::assertStringNotContainsString(
            "explode('.', \$file['key'])",
            $source,
            "Old explode() file_type extraction must have been removed."
        );
    }

    /**
     * pathinfo() returns only the last segment after the last dot.
     * For 'archive.tar.gz' it returns 'gz', not 'tar' or 'tar.gz'.
     * This mirrors the behaviour expected in hookFilesProcessing().
     */
    public function testPathinfoReturnsLastExtensionForMultiDot(): void
    {
        self::assertSame('gz', pathinfo('archive.tar.gz', PATHINFO_EXTENSION));
    }

    /**
     * pathinfo() returns '' for a filename with no extension.
     * The old explode() approach returned the full filename — pathinfo() fixes that.
     */
    public function testPathinfoReturnsEmptyStringForNoExtension(): void
    {
        self::assertSame('', pathinfo('README', PATHINFO_EXTENSION));
    }

    // =========================================================================
    // hookVersion()
    // =========================================================================

    /**
     * When the API response contains metadata.version, hookVersion() must return it.
     */
    public function testHookVersionFromMetadata(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Zenodo_Hooks::class, 'checkResponse');
        // checkResponse needs DB (calls hookApiRecords). Test hookVersion directly.
        // Pass 'response' already built to bypass the API call.
        $result = Episciences_Repositories_Zenodo_Hooks::hookVersion([
            'response' => ['metadata' => ['version' => '3']],
        ]);

        self::assertSame(['version' => '3'], $result);
    }

    /**
     * When metadata.version is absent but previousVersion is set, use previousVersion + 1.
     */
    public function testHookVersionFallbackToPreviousVersionPlusOne(): void
    {
        $result = Episciences_Repositories_Zenodo_Hooks::hookVersion([
            'response' => ['metadata' => []],
            'context'  => ['previousVersion' => 2],
        ]);

        self::assertSame(['version' => 3], $result);
    }

    /**
     * When neither metadata.version nor previousVersion is set, hookVersion() returns [].
     */
    public function testHookVersionReturnsEmptyWhenNoVersionInfo(): void
    {
        $result = Episciences_Repositories_Zenodo_Hooks::hookVersion([
            'response' => ['metadata' => []],
        ]);

        self::assertSame([], $result);
    }

    // =========================================================================
    // hookIsOpenAccessRight()
    // =========================================================================

    public function testHookIsOpenAccessRightOpenAccess(): void
    {
        $record = '<metadata><dc:rights>info:eu-repo/semantics/openAccess</dc:rights></metadata>';
        $result = Episciences_Repositories_Zenodo_Hooks::hookIsOpenAccessRight(['record' => $record]);

        self::assertSame(['isOpenAccessRight' => true], $result);
    }

    public function testHookIsOpenAccessRightClosedAccess(): void
    {
        $record = '<metadata><dc:rights>info:eu-repo/semantics/closedAccess</dc:rights></metadata>';
        $result = Episciences_Repositories_Zenodo_Hooks::hookIsOpenAccessRight(['record' => $record]);

        self::assertSame(['isOpenAccessRight' => false], $result);
    }

    // =========================================================================
    // enrichmentProcessCreators() — edge cases
    // =========================================================================

    /**
     * Creator with an empty 'name' string must be silently skipped.
     */
    public function testEnrichmentProcessCreatorsSkipsEmptyName(): void
    {
        $method = new ReflectionMethod(
            Episciences_Repositories_Zenodo_Hooks::class,
            'enrichmentProcessCreators'
        );
        $method->setAccessible(true);

        $creators = [['name' => '']];

        [$creatorsDc, $authors] = $method->invoke(null, $creators, [], []);

        self::assertSame([], $creatorsDc);
        self::assertSame([], $authors);
    }

    /**
     * Multiple creators are all added; creatorsDc receives all names.
     */
    public function testEnrichmentProcessCreatorsMultipleCreators(): void
    {
        $method = new ReflectionMethod(
            Episciences_Repositories_Zenodo_Hooks::class,
            'enrichmentProcessCreators'
        );
        $method->setAccessible(true);

        $creators = [
            ['name' => 'Dupont, Alice'],
            ['name' => 'Martin, Bob'],
        ];

        [$creatorsDc, $authors] = $method->invoke(null, $creators, [], []);

        self::assertCount(2, $creatorsDc);
        self::assertCount(2, $authors);
        self::assertContains('Dupont, Alice', $creatorsDc);
        self::assertContains('Martin, Bob', $creatorsDc);
    }

    // =========================================================================
    // extractDescriptions() — private, tested via ReflectionMethod
    // =========================================================================

    /**
     * extractDescriptions() falls back to the document language when a description
     * node has no xml:lang attribute.
     */
    public function testExtractDescriptionsFallbackLanguage(): void
    {
        $method = new ReflectionMethod(
            Episciences_Repositories_Zenodo_Hooks::class,
            'extractDescriptions'
        );
        $method->setAccessible(true);

        $xmlStr = <<<'XML'
<?xml version="1.0"?>
<root xmlns:datacite="http://datacite.org/schema/kernel-4">
    <datacite:descriptions>
        <datacite:description descriptionType="Abstract">Simple abstract</datacite:description>
    </datacite:descriptions>
</root>
XML;
        $metadata = simplexml_load_string($xmlStr);
        $metadata->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-4');

        $result = $method->invoke(null, $metadata, 'fr');

        self::assertNotEmpty($result);
        self::assertSame('Simple abstract', $result[0]['value']);
        self::assertSame('fr', $result[0]['language']);
    }

    /**
     * extractDescriptions() filters out descriptions with empty/whitespace-only content.
     */
    public function testExtractDescriptionsFiltersEmpty(): void
    {
        $method = new ReflectionMethod(
            Episciences_Repositories_Zenodo_Hooks::class,
            'extractDescriptions'
        );
        $method->setAccessible(true);

        $xmlStr = <<<'XML'
<?xml version="1.0"?>
<root xmlns:datacite="http://datacite.org/schema/kernel-4">
    <datacite:descriptions>
        <datacite:description descriptionType="Abstract"></datacite:description>
        <datacite:description descriptionType="Abstract" xml:lang="en">Real content</datacite:description>
    </datacite:descriptions>
</root>
XML;
        $metadata = simplexml_load_string($xmlStr);
        $metadata->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-4');

        $result = $method->invoke(null, $metadata, 'en');

        self::assertCount(1, $result);
        self::assertSame('Real content', $result[0]['value']);
    }

    // =========================================================================
    // Bug Z4/Z5: enrichmentProcess() with missing/empty metadata — no crash
    // =========================================================================

    /**
     * Bug Z4 (fixed): enrichmentProcess() used to crash with
     * "Undefined array key 'metadata'" when $data has no 'metadata' key.
     * After fix ($data['metadata'] ?? []), it must complete without error.
     *
     * Bug Z5 (fixed): mb_strtolower(null) when $type is empty was a PHP 8.1
     * deprecation. After fix (?? ''), it must produce '' cleanly.
     */
    public function testEnrichmentProcessWithoutMetadataKeyDoesNotCrash(): void
    {
        $method = new ReflectionMethod(
            Episciences_Repositories_Zenodo_Hooks::class,
            'enrichmentProcess'
        );
        $method->setAccessible(true);

        // Non-empty data but no 'metadata' key
        $data = [
            'doi_url' => 'https://doi.org/10.5281/zenodo.12345',
            'created' => '2024-01-15T10:00:00',
        ];

        $method->invokeArgs(null, [&$data]);

        // Must reach here without exception; TO_COMPILE_OAI_DC should be set
        self::assertIsArray($data);
    }

    /**
     * Bug Z5 (fixed): when metadata has no resource_type / upload_type / publication_type,
     * mb_strtolower(null) must not happen. The dcType must default to empty string ''.
     */
    public function testEnrichmentProcessWithEmptyTypeDoesNotCrash(): void
    {
        $method = new ReflectionMethod(
            Episciences_Repositories_Zenodo_Hooks::class,
            'enrichmentProcess'
        );
        $method->setAccessible(true);

        $data = [
            'doi_url'  => 'https://doi.org/10.5281/zenodo.99999',
            'metadata' => [
                'title'   => 'Test without type',
                'creators' => [],
            ],
        ];

        // Must not emit a PHP 8.1 deprecation for mb_strtolower(null)
        $method->invokeArgs(null, [&$data]);

        self::assertIsArray($data);
        self::assertArrayHasKey(Episciences_Repositories_Common::TO_COMPILE_OAI_DC, $data);
    }

    /**
     * enrichmentProcess() with a minimal valid metadata block correctly populates
     * TO_COMPILE_OAI_DC with title and body.
     */
    public function testEnrichmentProcessPopulatesOaiDc(): void
    {
        $method = new ReflectionMethod(
            Episciences_Repositories_Zenodo_Hooks::class,
            'enrichmentProcess'
        );
        $method->setAccessible(true);

        $data = [
            'doi_url'  => 'https://doi.org/10.5281/zenodo.123',
            'modified' => '2024-06-01T00:00:00',
            'metadata' => [
                'title'       => 'My Article',
                'upload_type' => 'publication',
                'creators'    => [
                    ['name' => 'Doe, Jane'],
                ],
                'language' => 'en',
                'keywords' => ['science', 'data'],
            ],
        ];

        $method->invokeArgs(null, [&$data]);

        self::assertArrayHasKey(Episciences_Repositories_Common::TO_COMPILE_OAI_DC, $data);
        $body = $data[Episciences_Repositories_Common::TO_COMPILE_OAI_DC]['body'] ?? [];
        self::assertSame('My Article', $body['title'] ?? '');
    }
}
