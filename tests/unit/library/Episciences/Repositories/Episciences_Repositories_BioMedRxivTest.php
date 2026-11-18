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
 *   B5 — authors, affiliations, keywords, licence and types were read from the JATS converted to an
 *          array (json_encode()), where a single <contrib>, <aff> or <kwd> is not a list: a single
 *          author made the submission fail. They are now read from the XML document.
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
    // JATS article-meta: authors, affiliations, keywords, licence, types
    // =========================================================================

    private static function jats(string $articleMeta): \SimpleXMLElement
    {
        $xml = '<article xmlns:hwp="http://schema.highwire.org/Journal" xmlns:xlink="http://www.w3.org/1999/xlink">'
            . '<front><article-meta>' . $articleMeta . '</article-meta></front></article>';

        return simplexml_load_string($xml);
    }

    private static function callPrivate(string $method, \SimpleXMLElement $doc): mixed
    {
        $reflection = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $doc);
    }

    /**
     * A single <contrib> made the submission fail (bioRxiv 10.1101/2025.08.07.669028).
     */
    public function testContributorsProcessSingleAuthor(): void
    {
        $doc = self::jats(
            '<contrib-group><contrib contrib-type="author" corresp="yes">'
            . '<contrib-id contrib-id-type="orcid">http://orcid.org/0000-0001-8887-2548</contrib-id>'
            . '<name><surname>Hase</surname><given-names>Kazuko</given-names></name>'
            . '<xref ref-type="aff" rid="a1">1</xref><xref ref-type="aff" rid="a2">2</xref><xref ref-type="corresp" rid="cor1">*</xref>'
            . '</contrib>'
            . '<aff id="a1"><label>1</label><institution>Tohoku University</institution>, Sendai, <country>Japan</country></aff>'
            . '<aff id="a2"><label>2</label><institution>SOKENDAI</institution>, Hayama, <country>Japan</country></aff>'
            . '</contrib-group>'
        );

        self::assertSame([[
            'degrees' => '',
            'fullname' => 'Kazuko Hase',
            'family' => 'Hase',
            'given' => 'Kazuko',
            'email' => '',
            'orcid' => '0000-0001-8887-2548',
            'affiliation' => [['name' => 'Tohoku University, Japan'], ['name' => 'SOKENDAI, Japan']],
        ]], self::callPrivate('contributorsProcess', $doc));
    }

    /**
     * A single <aff> was named "Array, 1, ..."; affiliations are linked by id, whatever their label ("a", "b"...).
     */
    public function testContributorsProcessSingleAffiliationWithLetterLabel(): void
    {
        $doc = self::jats(
            '<contrib-group>'
            . '<contrib contrib-type="author"><name><surname>Doe</surname><given-names>Jane</given-names></name><xref ref-type="aff" rid="a1">a</xref></contrib>'
            . '<contrib contrib-type="author"><name><surname>Roe</surname><given-names>Rick</given-names></name><xref ref-type="aff" rid="a1">a</xref></contrib>'
            . '<aff id="a1"><label>a</label><institution>The Salk Institute</institution></aff>'
            . '</contrib-group>'
        );

        $contributors = self::callPrivate('contributorsProcess', $doc);

        self::assertCount(2, $contributors);
        self::assertSame([['name' => 'The Salk Institute']], $contributors[0]['affiliation']);
        self::assertSame([['name' => 'The Salk Institute']], $contributors[1]['affiliation']);
    }

    /**
     * Several <institution> elements were named "Array, Array"; an untagged affiliation keeps its text.
     */
    public function testContributorsProcessAffiliationNames(): void
    {
        $doc = self::jats(
            '<contrib-group>'
            . '<contrib contrib-type="author"><name><surname>Doe</surname><given-names>Jane</given-names></name>'
            . '<xref ref-type="aff" rid="a1">1</xref><xref ref-type="aff" rid="a2">2</xref></contrib>'
            . '<aff id="a1"><label>1</label><institution>Baylor College of Medicine</institution>, Houston, '
            . '<institution>Fudan University</institution>, <country>USA</country></aff>'
            . '<aff id="a2"><label>2</label>Lab of Things, Paris, France</aff>'
            . '</contrib-group>'
        );

        self::assertSame(
            [['name' => 'Baylor College of Medicine, Fudan University, USA'], ['name' => 'Lab of Things, Paris, France']],
            self::callPrivate('contributorsProcess', $doc)[0]['affiliation']
        );
    }

    /**
     * A group author has no <name>: it was stored with an empty name.
     */
    public function testContributorsProcessGroupAuthorAndNamelessContributor(): void
    {
        $doc = self::jats(
            '<contrib-group>'
            . '<contrib contrib-type="author"><collab>Days of our Mice Consortium</collab></contrib>'
            . '<contrib contrib-type="author"><xref ref-type="aff" rid="a1">1</xref></contrib>'
            . '</contrib-group>'
        );

        self::assertSame([[
            'degrees' => '',
            'fullname' => 'Days of our Mice Consortium',
            'family' => 'Days of our Mice Consortium',
            'given' => '',
            'email' => '',
        ]], self::callPrivate('contributorsProcess', $doc));
    }

    public function testContributorsProcessNoContributor(): void
    {
        self::assertSame([], self::callPrivate('contributorsProcess', self::jats('')));
    }

    /**
     * A single <kwd> was not a list; inline markup was dropped ("r/<italic>K</italic>-selection").
     */
    public function testKeywordsProcess(): void
    {
        self::assertSame(['tadpole'], self::callPrivate('keywordsProcess', self::jats('<kwd-group><kwd>tadpole</kwd></kwd-group>')));

        $doc = self::jats(
            '<kwd-group><title>Key words</title><kwd><italic>Millepora</italic></kwd><kwd>r/<italic>K</italic>-selection</kwd><kwd> </kwd></kwd-group>'
            . '<kwd-group><kwd>ecology</kwd><kwd>r/K-selection</kwd></kwd-group>'
        );

        self::assertSame(['Millepora', 'r/K-selection', 'ecology'], self::callPrivate('keywordsProcess', $doc));
        self::assertSame([], self::callPrivate('keywordsProcess', self::jats('')));
    }

    public function testLicenseProcess(): void
    {
        $doc = self::jats(
            '<permissions><license license-type="creative-commons"><p>This pre-print is available under a Creative Commons License '
            . '(Attribution-NonCommercial 4.0 International), CC BY-NC 4.0, as described at '
            . '<ext-link ext-link-type="uri">http://creativecommons.org/licenses/by-nc/4.0/</ext-link></p></license></permissions>'
        );

        self::assertSame('https://creativecommons.org/licenses/by-nc/4.0', self::callPrivate('licenseProcess', $doc));
        self::assertSame('', self::callPrivate('licenseProcess', self::jats('')));
    }

    /**
     * An id typed as ORCID wins over an untyped id; an untyped id is an ORCID only in the ORCID format.
     */
    public function testContributorsProcessOrcid(): void
    {
        $contrib = static fn(string $ids): string => '<contrib contrib-type="author"><name><surname>Doe</surname><given-names>Jane</given-names></name>' . $ids . '</contrib>';

        $doc = self::jats(
            '<contrib-group>'
            . $contrib('<contrib-id>local-42</contrib-id><contrib-id contrib-id-type="orcid">https://orcid.org/0000-0002-1825-009x</contrib-id>')
            . $contrib('<contrib-id>http://orcid.org/0000-0001-8887-2548</contrib-id>')
            . $contrib('<contrib-id>local-42</contrib-id><contrib-id contrib-id-type="other">0000-0001-8887-2548</contrib-id>')
            . '</contrib-group>'
        );

        $contributors = self::callPrivate('contributorsProcess', $doc);

        self::assertSame('0000-0002-1825-009X', $contributors[0]['orcid']);
        self::assertSame('0000-0001-8887-2548', $contributors[1]['orcid']);
        self::assertArrayNotHasKey('orcid', $contributors[2]);
    }

    /**
     * The Creative Commons grant may follow a copyright notice in another paragraph.
     */
    public function testLicenseProcessReadsEveryParagraph(): void
    {
        $doc = self::jats(
            '<permissions><license><p>Copyright the authors.</p>'
            . '<p>This pre-print is available under a Creative Commons License (Attribution 4.0 International), CC BY 4.0, as described at http://creativecommons.org/licenses/by/4.0/</p>'
            . '</license></permissions>'
        );

        self::assertSame('https://creativecommons.org/licenses/by/4.0', self::callPrivate('licenseProcess', $doc));

        $allRightsReserved = self::jats('<permissions><license><p> </p><p>All rights reserved.</p></license></permissions>');

        self::assertSame('[CC_NO] All rights reserved.', self::callPrivate('licenseProcess', $allRightsReserved));
    }

    /**
     * Version selection, JATS enrichment and Dublin Core, from a local JATS file:
     * keywords are published even when they are the only metadata of the JATS.
     */
    public function testJatsEnrichmentReachesTheDublinCore(): void
    {
        $jatsFile = tempnam(sys_get_temp_dir(), 'jats');
        self::assertIsString($jatsFile);

        try {
            file_put_contents($jatsFile, '<article><front><article-meta><kwd-group>'
                . '<kwd>r/<italic>K</italic>-selection</kwd><kwd>ecology</kwd></kwd-group></article-meta></front></article>');

            $version = self::callStatic('getRequestedVersionFromCollection', [[
                ['version' => '1', 'doi' => '10.1101/abc', 'server' => 'bioRxiv', 'category' => 'ecology', 'jatsxml' => $jatsFile],
                ['version' => '2', 'doi' => '10.1101/abc', 'server' => 'bioRxiv', 'category' => 'ecology'],
            ], 1]);

            self::assertSame(['r/K-selection', 'ecology'], $version['enrichment']['kwd']);

            $dublinCore = self::callStatic('toDublinCore', [$version]);

            self::assertStringContainsString('<dc:subject>r/K-selection</dc:subject>', $dublinCore);
            self::assertStringContainsString('<dc:identifier>https://www.bioRxiv.org/content/10.1101/abc</dc:identifier>', $dublinCore);
        } finally {
            unlink($jatsFile);
        }
    }

    /**
     * @param array<int, mixed> $args
     */
    private static function callStatic(string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod(Episciences_Repositories_BioMedRxiv::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $args);
    }

    /**
     * The subject area ("hwp-journal-coll") is not a type: medRxiv got "hwp-journal-coll" as its type.
     */
    public function testTypeProcess(): void
    {
        $bioRxiv = self::jats(
            '<article-categories>'
            . '<subj-group subj-group-type="author-type"><subject>Regular Article</subject></subj-group>'
            . '<subj-group subj-group-type="heading"><subject>New Results</subject></subj-group>'
            . '<subj-group subj-group-type="hwp-journal-coll"><subject>Ecology</subject></subj-group>'
            . '</article-categories>'
        );

        self::assertSame(['Regular Article', 'New Results'], self::callPrivate('typeProcess', $bioRxiv));

        $medRxiv = self::jats(
            '<article-categories><subj-group subj-group-type="hwp-journal-coll"><subject>Sports Medicine</subject></subj-group></article-categories>'
        );

        self::assertSame([], self::callPrivate('typeProcess', $medRxiv));
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

    /**
     * <element-citation> has no punctuation between its fields: its text would read "SmithJ2020Title".
     */
    public function testReferencesProcessIgnoresElementCitation(): void
    {
        $citations = self::processReferences(
            '<ref id="c1"><element-citation><person-group><string-name><surname>Smith</surname>'
            . '<given-names>J</given-names></string-name></person-group><year>2020</year>'
            . '<article-title>Title</article-title></element-citation></ref>'
            . '<ref id="c2"><citation>Doe J (2021) Kept.</citation></ref>'
        );

        self::assertSame([['raw_reference' => 'Doe J (2021) Kept.']], $citations);
    }
}
