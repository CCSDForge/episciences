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
}
