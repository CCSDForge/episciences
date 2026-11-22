<?php

declare(strict_types=1);

namespace unit\library\Episciences\Repositories\HAL;

use Episciences\Repositories\HAL\TeiLinkedDataParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Linked data of a HAL document read from its TEI (HAL search API, wt=xml-tei).
 */
final class TeiLinkedDataParserTest extends TestCase
{
    /**
     * Shape of the TEI HAL returns for hal-05428815 (related data and publication, no SWHID)
     */
    private const TEI_RELATED_ITEMS = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><body><listBibl><biblFull>
  <sourceDesc><biblStruct>
    <idno type="doi">10.46298/jtcam.17355</idno>
    <relatedItem target="https://doi.org/10.5281/zenodo.22869346" type="IsSupplementedBy" subtype="http://purl.org/coar/resource_type/c_ddb1"/>
    <relatedItem target="https://hal.science/hal-05758441" type="IsReviewedBy" subtype="http://purl.org/coar/resource_type/c_1843"/>
  </biblStruct></sourceDesc>
</biblFull></listBibl></body></text></TEI>
XML;

    public function testReadsTheRelatedItemsWithTheirRelationAndResourceType(): void
    {
        self::assertSame([
            ['identifier' => 'https://doi.org/10.5281/zenodo.22869346', 'relation' => 'isSupplementedBy', 'resource_type' => 'dataset'],
            ['identifier' => 'https://hal.science/hal-05758441', 'relation' => 'hasReview', 'resource_type' => 'publication'],
        ], TeiLinkedDataParser::parse(self::TEI_RELATED_ITEMS));
    }

    public function testReadsTheSwhidAndLeavesOutDuplicatesAndEmptyTargets(): void
    {
        $tei = <<<'XML'
<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><body><listBibl><biblFull>
  <editionStmt><edition><idno type="swhid">swh:1:dir:2bec26ebee882f886be01081d642249e43a13f82;origin=https://github.com/example/code</idno></edition></editionStmt>
  <sourceDesc><biblStruct>
    <relatedItem target="https://doi.org/10.5061/dryad.b38k42m" type="Cites" subtype="http://purl.org/coar/resource_type/c_ddb1"/>
    <relatedItem target="https://DOI.org/10.5061/DRYAD.B38K42M" type="IsSupplementedBy"/>
    <relatedItem target=" " type="IsSupplementedBy"/>
    <relatedItem target="https://hal.science/hal-03955538v1" type="IsSupplementTo" subtype="http://purl.org/coar/resource_type/c_5ce6"/>
  </biblStruct></sourceDesc>
</biblFull></listBibl></body></text></TEI>
XML;

        self::assertSame([
            ['identifier' => 'https://doi.org/10.5061/dryad.b38k42m', 'relation' => 'references', 'resource_type' => 'dataset'],
            ['identifier' => 'https://hal.science/hal-03955538v1', 'relation' => 'isSupplementTo', 'resource_type' => 'software'],
            [
                'identifier' => 'swh:1:dir:2bec26ebee882f886be01081d642249e43a13f82;origin=https://github.com/example/code',
                'relation' => 'references',
                'resource_type' => 'software',
            ],
        ], TeiLinkedDataParser::parse($tei));
    }

    public function testADocumentWithoutLinkedDataGivesAnEmptyList(): void
    {
        $tei = '<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><body><listBibl><biblFull/></listBibl></body></text></TEI>';

        self::assertSame([], TeiLinkedDataParser::parse($tei));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function teiWithoutDocumentProvider(): array
    {
        return [
            'empty response' => [''],
            'not XML' => ['{"response":{"docs":[]}}'],
            'empty search result' => ['<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><body><listBibl/></body></text></TEI>'],
        ];
    }

    #[DataProvider('teiWithoutDocumentProvider')]
    public function testATeiWithoutTheDocumentGivesNull(string $tei): void
    {
        self::assertNull(TeiLinkedDataParser::parse($tei));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function relationshipProvider(): array
    {
        return [
            'same name in the vocabulary' => ['IsSupplementedBy', 'isSupplementedBy'],
            'part of' => ['IsPartOf', 'isPartOf'],
            'intra-work relation' => ['IsVersionOf', 'isVersionOf'],
            'reviewed by' => ['IsReviewedBy', 'hasReview'],
            'reviews' => ['Reviews', 'isReviewOf'],
            'cites' => ['Cites', 'references'],
            'cited by' => ['IsCitedBy', 'isReferencedBy'],
            'unknown' => ['IsObsoletedBy', 'references'],
            'empty' => ['', 'references'],
        ];
    }

    #[DataProvider('relationshipProvider')]
    public function testRelationshipMapsTheDataCiteRelationType(string $relationType, string $expected): void
    {
        self::assertSame($expected, TeiLinkedDataParser::relationship($relationType));
    }

    public function testResourceTypeMapsTheCoarType(): void
    {
        self::assertSame('dataset', TeiLinkedDataParser::resourceType('http://purl.org/coar/resource_type/c_ddb1'));
        self::assertSame('software', TeiLinkedDataParser::resourceType('http://purl.org/coar/resource_type/c_5ce6'));
        self::assertSame('publication', TeiLinkedDataParser::resourceType('http://purl.org/coar/resource_type/c_6501'));
        self::assertSame('publication', TeiLinkedDataParser::resourceType(''));
    }
}
