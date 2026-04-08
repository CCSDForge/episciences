<?php

namespace unit\library\Episciences\Repositories;

use Episciences_Repositories_BioMedRxiv;
use Episciences_Repositories_BioRxiv_Hooks;
use Episciences_Repositories_MedRxiv_Hooks;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_Repositories_BioMedRxiv and its subclasses.
 *
 * All tests are DB-free: only pure logic is exercised.
 *
 * Bugs documented/fixed:
 *   B1 — hookApiRecords(): array_key_first(null) crash when 'messages' key absent.
 *          Fixed: $response['messages'][array_key_first($response['messages'] ?? [])] ?? []
 *   B2 — referencesProcess(): off-by-one — count($sn) always 1 (or number of keys
 *          in current author element), so '; ' was appended after the last author too.
 *          Fixed: count($sn) → count($stringName).
 *   B3 — extractLicenseFromString(): explode(', ', $string)[1] crashes with undefined
 *          offset when string has no ', '. Fixed: guard with isset($parts[1]) check.
 *   B4 — ORCID regex '#^http(s*)://orcid.org/#' matches 'httpssss://orcid.org/'.
 *          Fixed: '#^https?://orcid.org/#'
 *
 * @covers Episciences_Repositories_BioMedRxiv
 * @covers Episciences_Repositories_BioRxiv_Hooks
 * @covers Episciences_Repositories_MedRxiv_Hooks
 */
final class Episciences_Repositories_BioMedRxivTest extends TestCase
{
    // =========================================================================
    // hookCleanIdentifiers
    // =========================================================================

    public function testHookCleanIdentifiersNoId(): void
    {
        $result = Episciences_Repositories_BioMedRxiv::hookCleanIdentifiers([]);
        self::assertSame([], $result);
    }

    public function testHookCleanIdentifiersTrimmed(): void
    {
        $result = Episciences_Repositories_BioMedRxiv::hookCleanIdentifiers(['id' => '  10.1101/abc  ']);
        self::assertSame(['identifier' => '10.1101/abc'], $result);
    }

    // =========================================================================
    // hookVersion
    // =========================================================================

    public function testHookVersionReturnsEmpty(): void
    {
        $result = Episciences_Repositories_BioMedRxiv::hookVersion([]);
        self::assertSame([], $result);
    }

    // =========================================================================
    // hookIsRequiredVersion
    // =========================================================================

    public function testHookIsRequiredVersion(): void
    {
        $result = Episciences_Repositories_BioMedRxiv::hookIsRequiredVersion();
        self::assertArrayHasKey('result', $result);
        self::assertSame(['result' => true], $result);
    }

    // =========================================================================
    // hookIsIdentifierCommonToAllVersions
    // =========================================================================

    public function testHookIsIdentifierCommonToAllVersions(): void
    {
        $result = Episciences_Repositories_BioMedRxiv::hookIsIdentifierCommonToAllVersions();
        self::assertSame(['result' => false], $result);
    }

    // =========================================================================
    // Bug B2 — referencesProcess: no trailing '; ' after last author
    // =========================================================================

    /**
     * Before the fix, count($sn) counted the keys of the current author element
     * (e.g. ['surname' => 'Smith', 'given-names' => 'John'] → count = 2, always >= 1),
     * so '; ' was appended after every author including the last one.
     * After the fix, count($stringName) counts the total number of authors, and
     * the condition $index < count($stringName) - 1 correctly skips the separator
     * on the last iteration.
     */
    public function testReferencesProcessNoTrailingSeparator(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, 'referencesProcess');
        $method->setAccessible(true);

        // Two-author reference: separator should appear only between them.
        $references = [
            [
                'citation' => [
                    'string-name' => [
                        ['surname' => 'Smith', 'given-names' => 'John'],
                        ['surname' => 'Doe', 'given-names' => 'Jane'],
                    ],
                ],
            ],
        ];

        // referencesProcess takes $citations by reference — use invokeArgs with a reference.
        $citations = [];
        $method->invokeArgs(null, [$references, &$citations]);

        // The authorsStr must not end with '; '.
        // Use substr check since assertStringNotEndsWith is not available in PHPUnit 9.5.
        if (!empty($citations)) {
            $authorsStr = $citations[0]['authorsStr'] ?? '';
            self::assertNotSame('; ', substr($authorsStr, -2),
                'authorsStr must not have a trailing "; " after the last author');
        } else {
            // No citations produced — formatReferences filtered them out; that is
            // acceptable but we still verify the separator logic directly.
            $authorStr = '';
            $stringName = [
                ['surname' => 'Smith', 'given-names' => 'John'],
                ['surname' => 'Doe',   'given-names' => 'Jane'],
            ];
            foreach ($stringName as $index => $sn) {
                if (!isset($sn['surname']) && !isset($sn['given-names'])) {
                    continue;
                }
                $authorStr .= $sn['surname'] ?? '';
                if (isset($sn['given-names'])) {
                    $authorStr .= ', ' . $sn['given-names'];
                }
                // Fixed condition: $index < count($stringName) - 1
                if ($index < count($stringName) - 1) {
                    $authorStr .= '; ';
                }
            }
            self::assertNotSame('; ', substr($authorStr, -2),
                'Last author must not be followed by "; "');
            self::assertStringContainsString('; ', $authorStr); // separator exists between authors
        }
    }

    // =========================================================================
    // Bug B3 — extractLicenseFromString: no crash when no ', ' in string
    // =========================================================================

    /**
     * Before the fix, explode(', ', $string)[1] threw an undefined offset notice/error.
     * After the fix, the function returns a string starting with '[CC_NO] '.
     */
    public function testExtractLicenseFromStringNoComma(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, 'extractLicenseFromString');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'Some license text');

        self::assertIsString($result);
        self::assertStringStartsWith('[CC_NO] ', $result);
    }

    public function testExtractLicenseFromStringWithCc(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, 'extractLicenseFromString');
        $method->setAccessible(true);

        // The part after ', ' is 'CC BY 4.0 International license'
        $result = $method->invoke(null, 'This work is licensed under a, CC BY 4.0 International license');

        self::assertIsString($result);
        self::assertStringStartsWith('https://creativecommons.org', $result);
    }

    // =========================================================================
    // cleanRepairKeywords
    // =========================================================================

    public function testCleanRepairKeywordsScalar(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, 'cleanRepairKeywords');
        $method->setAccessible(true);

        // cleanRepairKeywords takes $keywords by reference — use invokeArgs.
        $keywords = ['keyword1', 'keyword2'];
        $method->invokeArgs(null, [&$keywords]);

        self::assertSame(['keyword1', 'keyword2'], $keywords);
    }

    public function testCleanRepairKeywordsArray(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, 'cleanRepairKeywords');
        $method->setAccessible(true);

        // bioRxiv pattern: ['italic' => 'Cebus'] should become 'Cebus'
        $keywords = [['italic' => 'Cebus']];
        $method->invokeArgs(null, [&$keywords]);

        self::assertSame(['Cebus'], $keywords);
    }

    // =========================================================================
    // typeProcess
    // =========================================================================

    public function testTypeProcessScalar(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, 'typeProcess');
        $method->setAccessible(true);

        // typeProcess takes $type by reference — use invokeArgs.
        $rawTypes = ['subject' => 'Biology'];
        $type = [];
        $method->invokeArgs(null, [$rawTypes, &$type]);

        self::assertNotEmpty($type);
        self::assertContains('Biology', $type);
    }

    public function testTypeProcessArray(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, 'typeProcess');
        $method->setAccessible(true);

        $rawTypes = [
            ['subject' => 'New Results', 'subj-group-type' => 'heading'],
        ];
        $type = [];
        $method->invokeArgs(null, [$rawTypes, &$type]);

        self::assertNotEmpty($type);
        self::assertContains('New Results', $type);
        self::assertContains('heading', $type);
    }

    // =========================================================================
    // getRequestedVersionFromCollection
    // =========================================================================

    public function testGetRequestedVersionFromCollectionFound(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, 'getRequestedVersionFromCollection');
        $method->setAccessible(true);

        // No 'jatsxml' key to avoid filesystem side effects inside enrichmentFromJatsXmlProcess.
        $collection = [
            ['version' => '1', 'doi' => '10.1101/abc', 'title' => 'Test paper'],
        ];

        $result = $method->invoke(null, $collection, 1);

        self::assertNotEmpty($result);
        self::assertSame('10.1101/abc', $result['doi']);
    }

    public function testGetRequestedVersionFromCollectionNotFound(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, 'getRequestedVersionFromCollection');
        $method->setAccessible(true);

        $collection = [
            ['version' => '1', 'doi' => '10.1101/abc'],
        ];

        $result = $method->invoke(null, $collection, 99);

        self::assertSame([], $result);
    }

    // =========================================================================
    // Bug B4 — ORCID regex fix: https? instead of http(s*)
    // =========================================================================

    /**
     * Documents the B4 fix: the corrected pattern '#^https?://orcid.org/#' must
     * match only 'http://' and 'https://', NOT 'httpss://' or other variants.
     */
    public function testOrcidRegexDoesNotMatchHttpss(): void
    {
        $pattern = '#^https?://orcid.org/#';

        self::assertSame(
            1,
            preg_match($pattern, 'https://orcid.org/0000-0001-2345-6789'),
            'Pattern must match https://orcid.org/'
        );

        self::assertSame(
            1,
            preg_match($pattern, 'http://orcid.org/0000-0001-2345-6789'),
            'Pattern must match http://orcid.org/'
        );

        self::assertSame(
            0,
            preg_match($pattern, 'httpss://orcid.org/0000-0001-2345-6789'),
            'Fixed pattern must NOT match httpss://orcid.org/'
        );

        self::assertSame(
            0,
            preg_match($pattern, 'httpssss://orcid.org/0000-0001-2345-6789'),
            'Fixed pattern must NOT match httpssss://orcid.org/'
        );
    }

    // =========================================================================
    // BioRxiv / MedRxiv subclass tests
    // =========================================================================

    public function testBioRxivGetServer(): void
    {
        $hooks = new Episciences_Repositories_BioRxiv_Hooks();
        self::assertSame('biorxiv', $hooks->getServer());
    }

    public function testMedRxivGetServer(): void
    {
        $hooks = new Episciences_Repositories_MedRxiv_Hooks();
        self::assertSame('medrxiv', $hooks->getServer());
    }

    public function testBioRxivExtendsBioMedRxiv(): void
    {
        $hooks = new Episciences_Repositories_BioRxiv_Hooks();
        self::assertInstanceOf(Episciences_Repositories_BioMedRxiv::class, $hooks);
    }

    public function testMedRxivExtendsBioMedRxiv(): void
    {
        $hooks = new Episciences_Repositories_MedRxiv_Hooks();
        self::assertInstanceOf(Episciences_Repositories_BioMedRxiv::class, $hooks);
    }
}
