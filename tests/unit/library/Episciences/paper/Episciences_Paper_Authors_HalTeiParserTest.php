<?php

namespace unit\library\Episciences;

use Episciences_Paper_Authors_HalTeiParser;
use PHPUnit\Framework\TestCase;

final class Episciences_Paper_Authors_HalTeiParserTest extends TestCase
{
    const DATA_DIR = '/data/';

    /**
     * Verify basic author name extraction from a TEI persName element
     */
    public function testGetAuthorInfoFromXmlTei(): void
    {
        $personName = simplexml_load_string(
            '<persName>
                <forename type="first">Marie</forename>
                <surname>Curie</surname>
            </persName>'
        );

        $result = Episciences_Paper_Authors_HalTeiParser::getAuthorInfoFromXmlTei($personName, []);

        self::assertCount(1, $result);
        self::assertEquals('Marie', $result[0]['given_name']);
        self::assertEquals('Curie', $result[0]['family']);
        self::assertEquals('Marie Curie', $result[0]['fullname']);
    }

    /**
     * Verify multiple affiliations are extracted with numeric indices (bug fix regression test)
     *
     * This specifically tests the SimpleXML foreach fix: affiliations must be
     * stored with numeric array indices (0, 1, 2...), not the XML element name
     * string "affiliation" which would cause all entries to overwrite each other.
     */
    public function testGetAuthorStructureFromXmlTeiMultipleAffiliations(): void
    {
        $authorNode = simplexml_load_string(
            '<author role="aut">
                <persName>
                    <forename type="first">Marie</forename>
                    <surname>Curie</surname>
                </persName>
                <affiliation ref="#struct-111"/>
                <affiliation ref="#struct-222"/>
                <affiliation ref="#struct-333"/>
            </author>'
        );

        $existingAuthors = [
            ['given_name' => 'Marie', 'family' => 'Curie', 'fullname' => 'Marie Curie']
        ];

        $result = Episciences_Paper_Authors_HalTeiParser::getAuthorStructureFromXmlTei($authorNode, $existingAuthors);

        self::assertArrayHasKey('affiliations', $result[0]);
        self::assertCount(3, $result[0]['affiliations']);
        self::assertEquals('struct-111', $result[0]['affiliations'][0]);
        self::assertEquals('struct-222', $result[0]['affiliations'][1]);
        self::assertEquals('struct-333', $result[0]['affiliations'][2]);
    }

    /**
     * Verify single affiliation also works correctly
     */
    public function testGetAuthorStructureFromXmlTeiSingleAffiliation(): void
    {
        $authorNode = simplexml_load_string(
            '<author role="aut">
                <persName>
                    <forename type="first">Albert</forename>
                    <surname>Einstein</surname>
                </persName>
                <affiliation ref="#struct-999"/>
            </author>'
        );

        $existingAuthors = [
            ['given_name' => 'Albert', 'family' => 'Einstein', 'fullname' => 'Albert Einstein']
        ];

        $result = Episciences_Paper_Authors_HalTeiParser::getAuthorStructureFromXmlTei($authorNode, $existingAuthors);

        self::assertCount(1, $result[0]['affiliations']);
        self::assertEquals('struct-999', $result[0]['affiliations'][0]);
    }

    /**
     * Verify ORCID extraction from TEI with URL prefix stripping
     */
    public function testGetOrcidAuthorFromXmlTeiStripsUrl(): void
    {
        $authorNode = simplexml_load_string(
            '<author role="aut">
                <persName>
                    <forename type="first">Marie</forename>
                    <surname>Curie</surname>
                </persName>
                <idno type="ORCID">https://orcid.org/0000-0001-2345-678X</idno>
            </author>'
        );

        $existingAuthors = [
            ['given_name' => 'Marie', 'family' => 'Curie', 'fullname' => 'Marie Curie']
        ];

        $result = Episciences_Paper_Authors_HalTeiParser::getOrcidAuthorFromXmlTei($authorNode, $existingAuthors);

        self::assertArrayHasKey('orcid', $result[0]);
        self::assertEquals('0000-0001-2345-678X', $result[0]['orcid']);
    }

    /**
     * Verify ORCID with lowercase x is normalized to uppercase X
     */
    public function testGetOrcidAuthorFromXmlTeiNormalizesLowercaseX(): void
    {
        $authorNode = simplexml_load_string(
            '<author role="aut">
                <persName>
                    <forename type="first">John</forename>
                    <surname>Doe</surname>
                </persName>
                <idno type="ORCID">https://orcid.org/0000-0001-2345-678x</idno>
            </author>'
        );

        $existingAuthors = [
            ['given_name' => 'John', 'family' => 'Doe', 'fullname' => 'John Doe']
        ];

        $result = Episciences_Paper_Authors_HalTeiParser::getOrcidAuthorFromXmlTei($authorNode, $existingAuthors);

        self::assertEquals('0000-0001-2345-678X', $result[0]['orcid']);
    }

    /**
     * Verify affiliation details are extracted from TEI back/listOrg section
     */
    public function testGetAffiFromHalTeiExtractsOrganizations(): void
    {
        $tei = simplexml_load_file(__DIR__ . self::DATA_DIR . 'halTeiWithSeveralsAuthorsProvider.xml');

        $result = Episciences_Paper_Authors_HalTeiParser::getAffiFromHalTei($tei);

        self::assertIsArray($result);
        self::assertCount(21, $result);
        self::assertArrayHasKey('struct-7127', $result);
        self::assertEquals('Université de Caen Normandie', $result['struct-7127']['name']);
        self::assertEquals('https://ror.org/051kpcy16', $result['struct-7127']['ROR']);
    }

    /**
     * Verify empty TEI returns empty array for affiliations
     */
    public function testGetAffiFromHalTeiEmptyDocument(): void
    {
        $tei = simplexml_load_file(__DIR__ . self::DATA_DIR . 'halTeiEmptyProvider.xml');

        $result = Episciences_Paper_Authors_HalTeiParser::getAffiFromHalTei($tei);

        self::assertEmpty($result);
    }

    /**
     * Verify mergeAuthorInfoAndAffiTei replaces struct IDs with actual organization info
     */
    public function testMergeAuthorInfoAndAffiTeiResolvesStructRefs(): void
    {
        $authors = [
            [
                'given_name' => 'Marie',
                'family' => 'Curie',
                'fullname' => 'Marie Curie',
                'affiliations' => ['struct-100', 'struct-200'],
            ],
            [
                'given_name' => 'Albert',
                'family' => 'Einstein',
                'fullname' => 'Albert Einstein',
            ],
        ];

        $affiliations = [
            'struct-100' => ['name' => 'Sorbonne', 'ROR' => 'https://ror.org/0000001', 'acronym' => 'SU'],
            'struct-200' => ['name' => 'CNRS'],
        ];

        $result = Episciences_Paper_Authors_HalTeiParser::mergeAuthorInfoAndAffiTei($authors, $affiliations);

        // First author has resolved affiliations
        self::assertCount(2, $result[0]['affiliations']);
        self::assertEquals('Sorbonne', $result[0]['affiliations'][0]['name']);
        self::assertEquals('https://ror.org/0000001', $result[0]['affiliations'][0]['ROR']);
        self::assertEquals('SU', $result[0]['affiliations'][0]['acronym']);
        self::assertEquals('CNRS', $result[0]['affiliations'][1]['name']);
        self::assertArrayNotHasKey('ROR', $result[0]['affiliations'][1]);

        // Second author has no affiliations key
        self::assertArrayNotHasKey('affiliations', $result[1]);
    }

    /**
     * Verify full pipeline: getAuthorsFromHalTei extracts and produces correct structure
     */
    public function testGetAuthorsFromHalTeiFullPipeline(): void
    {
        $tei = simplexml_load_file(__DIR__ . self::DATA_DIR . 'halTeiProvider.xml');

        $authors = Episciences_Paper_Authors_HalTeiParser::getAuthorsFromHalTei($tei);

        self::assertNotEmpty($authors);
        self::assertEquals('Don', $authors[0]['given_name']);
        self::assertEquals('Zagier', $authors[0]['family']);
        self::assertEquals('Don Zagier', $authors[0]['fullname']);
        self::assertArrayHasKey('affiliations', $authors[0]);
        // Regression test: affiliations must be numerically indexed
        self::assertEquals('struct-426005', $authors[0]['affiliations'][0]);
        self::assertArrayHasKey('orcid', $authors[0]);
        self::assertEquals('0000-0004-8099-840X', $authors[0]['orcid']);
    }

    /**
     * Verify empty TEI returns empty array
     */
    public function testGetAuthorsFromHalTeiReturnsEmptyForEmptyDoc(): void
    {
        $tei = simplexml_load_file(__DIR__ . self::DATA_DIR . 'halTeiEmptyProvider.xml');

        $result = Episciences_Paper_Authors_HalTeiParser::getAuthorsFromHalTei($tei);

        self::assertEmpty($result);
    }
}
