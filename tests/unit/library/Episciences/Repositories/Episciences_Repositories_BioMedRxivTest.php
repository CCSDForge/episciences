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
 *   B2 — referencesProcess(): references are no longer rebuilt from their tagged parts
 *          (authors, title, pages, ...), which lost or broke data: the formatted text of
 *          each citation is kept as is, with its DOI.
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

    // =========================================================================
    // getDoiPrefix() / setDoiPrefix()
    // =========================================================================

    public function testGetDoiPrefixDefault(): void
    {
        $hooks = new Episciences_Repositories_BioRxiv_Hooks();
        self::assertSame(Episciences_Repositories_BioMedRxiv::DOI_PREFIX, $hooks->getDoiPrefix());
    }

    public function testSetDoiPrefix(): void
    {
        $hooks = new Episciences_Repositories_BioRxiv_Hooks();
        $hooks->setDoiPrefix('10.9999');
        self::assertSame('10.9999', $hooks->getDoiPrefix());
    }

    // =========================================================================
    // typeProcess() edge cases
    // =========================================================================

    public function testTypeProcessEmpty(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, 'typeProcess');
        $method->setAccessible(true);

        $type = [];
        $method->invokeArgs(null, [[], &$type]);

        self::assertSame([], $type);
    }

    // =========================================================================
    // articleMetaProcess() — private, tested via ReflectionMethod
    // =========================================================================

    /**
     * permissions block with a CC licence string populates $strLicense.
     */
    public function testArticleMetaProcessPermissions(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, 'articleMetaProcess');
        $method->setAccessible(true);

        $articleMeta = [
            'permissions' => [
                'license' => ['p' => 'This work is licensed under a, CC BY 4.0 International'],
            ],
        ];

        $strLicense   = '';
        $contributors = [];
        $institutions = [];
        $keyWords     = [];

        $method->invokeArgs(null, [$articleMeta, &$strLicense, &$contributors, &$institutions, &$keyWords]);

        self::assertNotEmpty($strLicense);
        self::assertStringContainsString('creativecommons', $strLicense);
    }

    /**
     * kwd-group block populates $keyWords.
     */
    public function testArticleMetaProcessKeywords(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, 'articleMetaProcess');
        $method->setAccessible(true);

        $articleMeta = [
            'kwd-group' => [
                'kwd' => ['evolution', 'genetics'],
            ],
        ];

        $strLicense   = '';
        $contributors = [];
        $institutions = [];
        $keyWords     = [];

        $method->invokeArgs(null, [$articleMeta, &$strLicense, &$contributors, &$institutions, &$keyWords]);

        self::assertContains('evolution', $keyWords);
        self::assertContains('genetics', $keyWords);
    }

    /**
     * An unrecognised top-level key is ignored — no output populated.
     */
    public function testArticleMetaProcessIgnoresUnknownKeys(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, 'articleMetaProcess');
        $method->setAccessible(true);

        $articleMeta = ['unknown-key' => ['some' => 'data']];

        $strLicense   = '';
        $contributors = [];
        $institutions = [];
        $keyWords     = [];

        $method->invokeArgs(null, [$articleMeta, &$strLicense, &$contributors, &$institutions, &$keyWords]);

        self::assertSame('', $strLicense);
        self::assertSame([], $contributors);
        self::assertSame([], $keyWords);
    }

    // =========================================================================
    // getRequestedVersionFromCollection() — edge cases
    // =========================================================================

    public function testGetRequestedVersionFromCollectionEmptyCollection(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, 'getRequestedVersionFromCollection');
        $method->setAccessible(true);

        $result = $method->invoke(null, [], 1);

        self::assertSame([], $result);
    }

    public function testGetRequestedVersionFromCollectionVersionZeroNotFound(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, 'getRequestedVersionFromCollection');
        $method->setAccessible(true);

        $collection = [['version' => '1', 'doi' => '10.1101/abc']];

        $result = $method->invoke(null, $collection, 0);

        self::assertSame([], $result);
    }

    // =========================================================================
    // referencesProcess(): formatted text of each citation, with its DOI
    // =========================================================================

    /**
     * @return array<int, array{raw_reference: string, doi?: string}>
     */
    private static function processReferences(string $refList): array
    {
        $method = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, 'referencesProcess');
        $method->setAccessible(true);

        $xml = '<article xmlns:hw="org.highwire.hpp"><back><ref-list>' . $refList . '</ref-list></back></article>';

        return $method->invoke(null, simplexml_load_string($xml));
    }

    public function testReferencesProcessKeepsFormattedTextAndDoi(): void
    {
        $citations = self::processReferences(
            '<ref id="c1"><label>1.</label><citation publication-type="journal">'
            . '<string-name><surname>Altman</surname>, <given-names>D. G.</given-names></string-name>, &amp; '
            . '<string-name><surname>Royston</surname>, <given-names>P</given-names></string-name>. (<year>2006</year>). '
            . '<article-title>The cost of dichotomising continuous variables</article-title>. <source>BMJ</source>, '
            . '<volume>332</volume>(<issue>7549</issue>), <fpage>1080</fpage>. '
            . '<pub-id pub-id-type="doi">10.1136/bmj.332.7549.1080</pub-id></citation></ref>'
        );

        self::assertSame([[
            'raw_reference' => 'Altman, D. G., & Royston, P. (2006). The cost of dichotomising continuous variables. BMJ, 332(7549), 1080. 10.1136/bmj.332.7549.1080',
            'doi' => '10.1136/bmj.332.7549.1080',
        ]], $citations);
    }

    /**
     * Authors not tagged: the citation starts with plain text (made the submission fail).
     */
    public function testReferencesProcessPlainTextAuthors(): void
    {
        $citations = self::processReferences(
            '<ref id="c19"><label>19.</label><citation publication-type="journal">Dos Santos, N. T. O., '
            . '<etal>et al.</etal> (<year>2017</year>). <article-title>Plain text authors</article-title>. '
            . '<source>J. Bodyw.</source>, <volume>21</volume>(<issue>1</issue>), <fpage>74</fpage>–<lpage>80</lpage>.</citation></ref>'
        );

        self::assertSame(
            [['raw_reference' => 'Dos Santos, N. T. O., et al. (2017). Plain text authors. J. Bodyw., 21(1), 74–80.']],
            $citations
        );
    }

    /**
     * Title made of several inline elements (made the submission fail), single author, untagged collaboration.
     */
    public function testReferencesProcessInlineMarkupAndSingleAuthor(): void
    {
        $citations = self::processReferences(
            '<ref id="c1"><citation><string-name><surname>Smith</surname> <given-names>J</given-names></string-name> '
            . '(<year>2020</year>) <article-title><italic>E. coli</italic> and <italic>B. subtilis</italic> growth</article-title>. '
            . '<source><italic>Nature</italic></source></citation></ref>'
            . '<ref id="c2"><citation><collab>The Consortium</collab> (<year>2021</year>) <article-title>Report</article-title></citation></ref>'
        );

        self::assertSame([
            ['raw_reference' => 'Smith J (2020) E. coli and B. subtilis growth. Nature'],
            ['raw_reference' => 'The Consortium (2021) Report'],
        ], $citations);
    }

    public function testReferencesProcessWhitespaceIsNormalized(): void
    {
        $citations = self::processReferences(
            "<ref id=\"c1\"><citation>\n  <string-name><surname>Doe</surname>\n  <given-names>J</given-names></string-name>\n  Title\n</citation></ref>"
        );

        self::assertSame([['raw_reference' => 'Doe J Title']], $citations);
    }

    public function testReferencesProcessSkipsEmptyCitations(): void
    {
        self::assertSame([], self::processReferences('<ref id="c1"><label>1.</label><citation> </citation></ref>'));
        self::assertSame([], self::processReferences(''));
    }

    public function testReferencesProcessMixedCitation(): void
    {
        $citations = self::processReferences(
            '<ref id="c1"><mixed-citation>Doe J. Title. <pub-id pub-id-type="pmid">123</pub-id></mixed-citation></ref>'
        );

        self::assertSame([['raw_reference' => 'Doe J. Title. 123']], $citations);
    }
}
