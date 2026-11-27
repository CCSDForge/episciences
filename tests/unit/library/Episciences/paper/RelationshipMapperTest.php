<?php

declare(strict_types=1);

namespace unit\library\Episciences\paper;

use Episciences\Paper\Relationship;
use Episciences\Paper\RelationshipMapper;
use Episciences\Paper\RelationshipProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelationshipMapper::class)]
#[CoversClass(RelationshipProfile::class)]
final class RelationshipMapperTest extends TestCase
{
    /**
     * DataCite / Scholix relation types with a dedicated mapping
     *
     * @return iterable<string, array{string, string}>
     */
    public static function dedicatedInboundProvider(): iterable
    {
        yield 'reviewed by' => ['IsReviewedBy', 'hasReview'];
        yield 'reviews' => ['Reviews', 'isReviewOf'];
        yield 'cites' => ['Cites', 'references'];
        yield 'cited by' => ['IsCitedBy', 'isReferencedBy'];
        yield 'source of' => ['IsSourceOf', 'hasDerivation'];
        yield 'new version of' => ['IsNewVersionOf', 'isVersionOf'];
        yield 'previous version of' => ['IsPreviousVersionOf', 'hasVersion'];
        yield 'related to (Scholix)' => ['IsRelatedTo', 'isRelatedMaterial'];
        yield 'described by' => ['IsDescribedBy', 'isDocumentedBy'];
        yield 'describes' => ['Describes', 'documents'];
        yield 'obsoleted by' => ['IsObsoletedBy', 'isReplacedBy'];
        yield 'obsoletes' => ['Obsoletes', 'replaces'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function inboundProvider(): iterable
    {
        yield from self::dedicatedInboundProvider();
        yield 'same name' => ['IsSupplementedBy', 'isSupplementedBy'];
        yield 'derived from' => ['IsDerivedFrom', 'isDerivedFrom'];
        yield 'references' => ['References', 'references'];
        yield 'intra-work relation' => ['IsTranslationOf', 'isTranslationOf'];
        yield 'already stored' => ['hasReview', 'hasReview'];
        yield 'dedicated mapping in camelCase' => ['isReviewedBy', 'hasReview'];
        yield 'surrounding spaces' => ["  IsPartOf\n", 'isPartOf'];
    }

    #[DataProvider('inboundProvider')]
    public function testToCrossrefGivesTheStoredRelation(string $relationType, string $expected): void
    {
        self::assertSame($expected, RelationshipMapper::toCrossref($relationType));
    }

    #[DataProvider('inboundProvider')]
    public function testFromDataCiteGivesTheStoredRelation(string $relationType, string $expected): void
    {
        self::assertSame($expected, RelationshipMapper::fromDataCite($relationType));
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function crossrefExportProvider(): iterable
    {
        yield 'stored' => ['isSupplementedBy', 'isSupplementedBy'];
        yield 'legacy DataCite type' => ['IsReviewedBy', 'hasReview'];
        yield 'no relation' => [null, 'references'];
        yield 'empty' => ['', 'references'];
        yield 'unknown' => ['IsWhatever', 'references'];
    }

    #[DataProvider('crossrefExportProvider')]
    public function testToCrossrefOrDefaultFallsBackToReferences(?string $relationship, string $expected): void
    {
        self::assertSame($expected, RelationshipMapper::toCrossrefOrDefault($relationship));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function noStoredEquivalentProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'DataCite without equivalent' => ['HasMetadata'];
        yield 'DataCite 4.4 without equivalent' => ['IsPublishedIn'];
        yield 'unknown' => ['IsWhatever'];
        yield 'free text' => ['Is supplemented by'];
    }

    #[DataProvider('noStoredEquivalentProvider')]
    public function testToCrossrefGivesNullWithoutEquivalent(string $relationType): void
    {
        self::assertNull(RelationshipMapper::toCrossref($relationType));
    }

    #[DataProvider('noStoredEquivalentProvider')]
    public function testFromDataCiteFallsBackWithoutEquivalent(string $relationType): void
    {
        self::assertSame('references', RelationshipMapper::fromDataCite($relationType));
        self::assertSame(
            RelationshipMapper::FALLBACK_RELATED_MATERIAL,
            RelationshipMapper::fromDataCite($relationType, RelationshipMapper::FALLBACK_RELATED_MATERIAL)
        );
    }

    /**
     * Stored relations accepted by both profiles
     *
     * @return iterable<string, array{string, string}>
     */
    public static function outboundProvider(): iterable
    {
        yield 'same name' => ['isSupplementedBy', 'IsSupplementedBy'];
        yield 'references' => ['references', 'References'];
        yield 'intra-work relation' => ['isVersionOf', 'IsVersionOf'];
        yield 'has review' => ['hasReview', 'IsReviewedBy'];
        yield 'is review of' => ['isReviewOf', 'Reviews'];
        yield 'has derivation' => ['hasDerivation', 'IsSourceOf'];
        yield 'is based on' => ['isBasedOn', 'IsDerivedFrom'];
        yield 'based on data' => ['basedOnData', 'IsDerivedFrom'];
        yield 'is basis for' => ['isBasisFor', 'IsSourceOf'];
        yield 'is data basis for' => ['isDataBasisFor', 'IsSourceOf'];
        yield 'already in PascalCase' => ['IsReviewedBy', 'IsReviewedBy'];
        yield 'surrounding spaces' => [' isPartOf ', 'IsPartOf'];
    }

    #[DataProvider('outboundProvider')]
    public function testToDataCiteGivesTheRelationTypeForOpenAire(string $relationship, string $expected): void
    {
        self::assertSame($expected, RelationshipMapper::toDataCite($relationship, RelationshipProfile::OpenAire));
    }

    #[DataProvider('outboundProvider')]
    public function testToDataCiteGivesTheRelationTypeForDataCite(string $relationship, string $expected): void
    {
        self::assertSame($expected, RelationshipMapper::toDataCite($relationship));
    }

    /**
     * Relations added after DataCite 4.1 (4.2 and 4.6), unknown to OpenAIRE 4.0
     *
     * @return iterable<string, array{string, string}>
     */
    public static function dataCiteAfter41Provider(): iterable
    {
        yield 'translation of' => ['isTranslationOf', 'IsTranslationOf'];
        yield 'has translation' => ['hasTranslation', 'HasTranslation'];
        yield 'replaced by' => ['isReplacedBy', 'IsObsoletedBy'];
        yield 'replaces' => ['replaces', 'Obsoletes'];
    }

    #[DataProvider('dataCiteAfter41Provider')]
    public function testRelationsAddedAfterDataCite41AreOnlyExportedWithTheDataCiteProfile(string $relationship, string $expected): void
    {
        self::assertSame($expected, RelationshipMapper::toDataCite($relationship, RelationshipProfile::DataCite));
        self::assertNull(RelationshipMapper::toDataCite($relationship, RelationshipProfile::OpenAire));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function noDataCiteEquivalentProvider(): iterable
    {
        yield 'no relation' => [null];
        yield 'empty' => [''];
        yield 'related material' => ['isRelatedMaterial'];
        yield 'has related material' => ['hasRelatedMaterial'];
        yield 'financed by' => ['isFinancedBy'];
        yield 'comment on' => ['isCommentOn'];
        yield 'preprint of' => ['isPreprintOf'];
        yield 'same as' => ['isSameAs'];
        yield 'Scholix related to' => ['IsRelatedTo'];
    }

    #[DataProvider('noDataCiteEquivalentProvider')]
    public function testToDataCiteGivesNullWithoutEquivalent(?string $relationship): void
    {
        self::assertNull(RelationshipMapper::toDataCite($relationship, RelationshipProfile::OpenAire));
        self::assertNull(RelationshipMapper::toDataCite($relationship, RelationshipProfile::DataCite));
    }

    /**
     * Relations whose conversion is one-to-one: DataCite => stored => DataCite gives the same value
     *
     * @return iterable<string, array{string}>
     */
    public static function bijectiveProvider(): iterable
    {
        $lossy = ['Cites', 'IsCitedBy', 'IsNewVersionOf', 'IsPreviousVersionOf', 'IsRelatedTo', 'IsDescribedBy', 'Describes'];

        foreach (RelationshipProfile::DataCite->relationTypes() as $relationType) {
            if (!in_array($relationType, $lossy, true) && RelationshipMapper::toCrossref($relationType) !== null) {
                yield $relationType => [$relationType];
            }
        }
    }

    #[DataProvider('bijectiveProvider')]
    public function testRoundTripKeepsTheRelationType(string $relationType): void
    {
        self::assertSame($relationType, RelationshipMapper::toDataCite(RelationshipMapper::fromDataCite($relationType)));
    }

    public function testEveryDedicatedMappingTargetsAStoredRelation(): void
    {
        foreach (self::dedicatedInboundProvider() as [, $stored]) {
            self::assertTrue(RelationshipMapper::isStoredRelation($stored), $stored);
        }
    }

    public function testEveryStoredRelationIsKnown(): void
    {
        $stored = [...Relationship::getFlattenedRelationships(), ...Relationship::getFlattenedRelationshipsIntraWorkRelation()];

        foreach ($stored as $relationship) {
            self::assertTrue(RelationshipMapper::isStoredRelation($relationship), $relationship);
        }

        self::assertFalse(RelationshipMapper::isStoredRelation('IsSupplementedBy'));
    }

    public function testAStoredRelationIsKeptAsIs(): void
    {
        $stored = [...Relationship::getFlattenedRelationships(), ...Relationship::getFlattenedRelationshipsIntraWorkRelation()];

        foreach ($stored as $relationship) {
            self::assertSame($relationship, RelationshipMapper::toCrossref($relationship), $relationship);
        }
    }

    public function testTheOpenAireProfileIsTheDataCite41Vocabulary(): void
    {
        $openAire = RelationshipProfile::OpenAire->relationTypes();

        self::assertCount(31, $openAire);
        self::assertSame([], array_diff($openAire, RelationshipProfile::DataCite->relationTypes()));
        self::assertSame(
            ['IsObsoletedBy', 'Obsoletes', 'IsCollectedBy', 'Collects', 'IsPublishedIn', 'IsTranslationOf', 'HasTranslation'],
            array_values(array_diff(RelationshipProfile::DataCite->relationTypes(), $openAire))
        );
        self::assertNotContains('IsRelatedTo', RelationshipProfile::DataCite->relationTypes());
    }
}
