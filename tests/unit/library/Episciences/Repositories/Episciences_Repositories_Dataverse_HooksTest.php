<?php

namespace unit\library\Episciences\Repositories;

use Episciences_Repositories_Common;
use Episciences_Repositories_Dataverse_Hooks;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_Repositories_Dataverse_Hooks.
 *
 * All tests are DB-free: only pure static methods and source-inspection tests are included.
 *
 * Bugs documented/fixed:
 *   D1 — dataProcess(): date_create()->format() crashes when date_create() returns false.
 *         Fix: replaced with Episciences_Repositories_Common::safeDateFormat()
 *   D3 — dataProcess(): $projectTitle used via ?? but may be undefined if neither
 *         projectName nor projectTitle typeName appears in the current iteration.
 *         Fix: initialize $projectTitle = UNIDENTIFIED at top of outer foreach loop.
 *   D4 — hookApiRecords(): fallback string '"Empty record' had a spurious leading quote.
 *         Fix: changed to 'Empty record'.
 *   D5 — hookApiRecords(): direct array access $processedData[ENRICHMENT] without guard
 *         → PHP warning "undefined index". Fix: !empty() guard added.
 *
 * @covers Episciences_Repositories_Dataverse_Hooks
 */
final class Episciences_Repositories_Dataverse_HooksTest extends TestCase
{
    // =========================================================================
    // hookIsRequiredVersion()
    // =========================================================================

    /**
     * hookIsRequiredVersion() delegates to Common::isRequiredVersion() which defaults to true.
     */
    public function testHookIsRequiredVersionReturnsTrue(): void
    {
        $result = Episciences_Repositories_Dataverse_Hooks::hookIsRequiredVersion();
        self::assertSame(['result' => true], $result);
    }

    // =========================================================================
    // hookIsIdentifierCommonToAllVersions()
    // =========================================================================

    /**
     * Dataverse identifiers are NOT common to all versions — each version has its own DOI.
     */
    public function testHookIsIdentifierCommonToAllVersions(): void
    {
        $result = Episciences_Repositories_Dataverse_Hooks::hookIsIdentifierCommonToAllVersions();
        self::assertSame(['result' => false], $result);
    }

    // =========================================================================
    // hookVersion()
    // =========================================================================

    /**
     * When the response already carries a version in the enrichment block,
     * hookVersion() must return it as-is.
     */
    public function testHookVersionWithResponse(): void
    {
        $hookParams = [
            'response' => [
                Episciences_Repositories_Common::ENRICHMENT => ['version' => '2.0'],
            ],
        ];

        $result = Episciences_Repositories_Dataverse_Hooks::hookVersion($hookParams);
        self::assertSame(['version' => '2.0'], $result);
    }

    /**
     * When no response or version is provided, hookVersion() must return the integer default 1.
     */
    public function testHookVersionDefault(): void
    {
        $result = Episciences_Repositories_Dataverse_Hooks::hookVersion([]);
        self::assertSame(['version' => 1], $result);
    }

    // =========================================================================
    // Bug D4: spurious double-quote in fallback string
    // =========================================================================

    /**
     * Bug D4: the original fallback was '"Empty record' (with a spurious leading quote).
     * After the fix the spurious quote must be absent from the source file.
     */
    public function testErrorStringNoSpuriousQuote(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dataverse_Hooks::class, 'hookApiRecords');
        $filePath = $method->getFileName();

        self::assertNotFalse($filePath, 'Could not determine source file path via ReflectionMethod.');

        $source = file_get_contents($filePath);

        self::assertNotFalse($source, 'Could not read source file: ' . $filePath);

        self::assertStringNotContainsString(
            '"Empty record\'',
            $source,
            'Bug D4 regression: spurious double-quote at start of fallback string must not appear.'
        );

        self::assertStringContainsString(
            "'Empty record'",
            $source,
            "The corrected fallback string 'Empty record' must be present."
        );
    }

    // =========================================================================
    // Bug D5: direct array access without guard
    // =========================================================================

    /**
     * Bug D5: $processedData[ENRICHMENT] was accessed directly without isset/empty check,
     * causing PHP warnings when the key is absent.
     * After the fix the raw unguarded form must be absent, and !empty() must be present.
     */
    public function testEmptyEnrichmentUsesIsset(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dataverse_Hooks::class, 'hookApiRecords');
        $filePath = $method->getFileName();

        self::assertNotFalse($filePath, 'Could not determine source file path via ReflectionMethod.');

        $source = file_get_contents($filePath);

        self::assertNotFalse($source, 'Could not read source file: ' . $filePath);

        self::assertStringNotContainsString(
            'if ($processedData[Episciences_Repositories_Common::ENRICHMENT])',
            $source,
            'Bug D5 regression: unguarded direct array access must not appear.'
        );

        self::assertStringContainsString(
            '!empty($processedData',
            $source,
            'Bug D5 fix: !empty() guard must be present in source.'
        );
    }

    // =========================================================================
    // Bug D1: date_create()->format() without false-check
    // =========================================================================

    /**
     * Bug D1: date_create($data['releaseTime'])->format('Y-m-d') crashes when
     * date_create() returns false. After the fix safeDateFormat() is used instead.
     */
    public function testSafeDateFormatUsedInDataProcess(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dataverse_Hooks::class, 'dataProcess');
        $filePath = $method->getFileName();

        self::assertNotFalse($filePath, 'Could not determine source file path via ReflectionMethod.');

        $source = file_get_contents($filePath);

        self::assertNotFalse($source, 'Could not read source file: ' . $filePath);

        self::assertStringNotContainsString(
            "date_create(\$data['releaseTime'])->format",
            $source,
            'Bug D1 regression: unsafe date_create()->format() chain must not appear.'
        );

        self::assertStringContainsString(
            'Episciences_Repositories_Common::safeDateFormat(',
            $source,
            'Bug D1 fix: safeDateFormat() call must be present in source.'
        );
    }

    // =========================================================================
    // Bug D3: $projectTitle potentially undefined before use
    // =========================================================================

    /**
     * Bug D3: $projectTitle was used via ?? inside the inner foreach but was only
     * assigned conditionally. After the fix it is initialized to UNIDENTIFIED at the
     * top of the outer foreach loop.
     * Verified via source inspection: the initialization must appear before the inner loop.
     */
    public function testProjectTitleInitializedBeforeLoop(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dataverse_Hooks::class, 'dataProcess');
        $filePath = $method->getFileName();

        self::assertNotFalse($filePath, 'Could not determine source file path via ReflectionMethod.');

        $source = file_get_contents($filePath);

        self::assertNotFalse($source, 'Could not read source file: ' . $filePath);

        // The fix: $projectTitle is initialized before the inner foreach ($val as $cVal)
        self::assertStringContainsString(
            '$projectTitle = Episciences_Paper_ProjectsManager::UNIDENTIFIED;',
            $source,
            'Bug D3 fix: $projectTitle must be initialized before the inner foreach loop.'
        );

        // The initialization must come BEFORE the inner foreach loop.
        $initPos = strpos($source, '$projectTitle = Episciences_Paper_ProjectsManager::UNIDENTIFIED;');
        $innerForeachPos = strpos($source, 'foreach ($val as $cVal)');

        self::assertNotFalse($initPos, 'Initialization line not found.');
        self::assertNotFalse($innerForeachPos, 'Inner foreach not found.');
        self::assertLessThan(
            $innerForeachPos,
            $initPos,
            'Bug D3 fix: $projectTitle initialization must appear before foreach ($val as $cVal).'
        );
    }

    // =========================================================================
    // getAssembledLink() — private static, tested via ReflectionMethod
    // =========================================================================

    /**
     * When pidURL is present, getAssembledLink() must return it directly.
     */
    public function testGetAssembledLinkWithPidURL(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dataverse_Hooks::class, 'getAssembledLink');
        $method->setAccessible(true);

        $values = ['dataFile' => ['pidURL' => 'https://example.com/pid']];
        $result = $method->invoke(null, $values, null);

        self::assertSame('https://example.com/pid', $result);
    }

    /**
     * When no pidURL and no repoId is provided, getAssembledLink() must return '#'.
     */
    public function testGetAssembledLinkNoData(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dataverse_Hooks::class, 'getAssembledLink');
        $method->setAccessible(true);

        $values = ['dataFile' => []];
        $result = $method->invoke(null, $values, null);

        self::assertSame('#', $result);
    }

    // =========================================================================
    // processFiles() — private static, tested via ReflectionMethod
    // =========================================================================

    /**
     * processFiles() with an empty files array must return an empty array.
     */
    public function testProcessFilesEmpty(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dataverse_Hooks::class, 'processFiles');
        $method->setAccessible(true);

        $result = $method->invoke(null, [], []);

        self::assertSame([], $result);
    }

    /**
     * processFiles() with a file entry that has 'filename' must populate file_name and file_size.
     */
    public function testProcessFilesWithFilename(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dataverse_Hooks::class, 'processFiles');
        $method->setAccessible(true);

        $files = [
            [
                'dataFile' => [
                    'filename'  => 'data.csv',
                    'filesize'  => 1024,
                    'checksum'  => ['value' => 'abc123', 'type' => 'MD5'],
                    'pidURL'    => 'https://example.com/file',
                ],
            ],
        ];

        $result = $method->invoke(null, $files, ['repoId' => null]);

        self::assertCount(1, $result);
        self::assertSame('data.csv', $result[0]['file_name']);
        self::assertSame(1024, $result[0]['file_size']);
    }

    /**
     * processFiles() must fall back to 'label' when 'filename' is absent.
     */
    public function testProcessFilesWithLabelFallback(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dataverse_Hooks::class, 'processFiles');
        $method->setAccessible(true);

        $files = [
            [
                'dataFile' => [
                    'label'    => 'readme.txt',
                    'filesize' => 512,
                    'checksum' => ['value' => 'xyz', 'type' => 'SHA1'],
                    'pidURL'   => 'https://example.com/f',
                ],
            ],
        ];

        $result = $method->invoke(null, $files, ['repoId' => null]);

        self::assertCount(1, $result);
        self::assertSame('readme.txt', $result[0]['file_name']);
    }
}
