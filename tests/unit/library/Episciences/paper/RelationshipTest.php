<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences\Paper\Relationship;
use PHPUnit\Framework\TestCase;

final class RelationshipTest extends TestCase
{
    /**
     * @dataProvider removeFirstLevelProvider
     * @param array<string|int, array<int, string>> $inputArray
     * @param array<int, string> $expectedOutput
     */
    public function testRemoveFirstLevel(array $inputArray, array $expectedOutput): void
    {
        $result = Relationship::removeFirstLevel($inputArray);
        self::assertEquals($expectedOutput, $result);
    }

    /**
     * Test removeFirstLevel with empty array
     */
    public function testRemoveFirstLevelWithEmptyArray(): void
    {
        $result = Relationship::removeFirstLevel([]);
        self::assertEquals([], $result);
    }

    /**
     * Test removeFirstLevel with single level array
     */
    public function testRemoveFirstLevelWithSingleLevel(): void
    {
        /** @var array<string|int, array<int, string>> $inputArray */
        $inputArray = [['value1'], ['value2'], ['value3']];
        $expectedOutput = ['value1', 'value2', 'value3'];
        
        $result = Relationship::removeFirstLevel($inputArray);
        self::assertEquals($expectedOutput, $result);
    }

    /**
     * Test removeFirstLevel with mixed content
     */
    public function testRemoveFirstLevelWithMixedContent(): void
    {
        /** @var array<string|int, array<int, string>> $inputArray */
        $inputArray = [
            ['string1', 'string2'],
            ['string3'],
            ['string4', 'string5', 'string6']
        ];
        $expectedOutput = ['string1', 'string2', 'string3', 'string4', 'string5', 'string6'];
        
        $result = Relationship::removeFirstLevel($inputArray);
        self::assertEquals($expectedOutput, $result);
    }

    /**
     * Test getFlattenedRelationships returns flattened relationships
     */
    public function testGetFlattenedRelationships(): void
    {
        $result = Relationship::getFlattenedRelationships();
        
        self::assertNotEmpty($result);
        
        self::assertContains('isBasedOn', $result);
        self::assertContains('isBasisFor', $result);
        self::assertContains('basedOnData', $result);
        self::assertContains('isDataBasisFor', $result);
        self::assertContains('isCommentOn', $result);
        self::assertContains('hasComment', $result);
        self::assertContains('references', $result);
        self::assertContains('isReferencedBy', $result);
        self::assertContains('requires', $result);
        self::assertContains('isRequiredBy', $result);
    }

    /**
     * Test getFlattenedRelationshipsIntraWorkRelation returns flattened intra work relationships
     */
    public function testGetFlattenedRelationshipsIntraWorkRelation(): void
    {
        $result = Relationship::getFlattenedRelationshipsIntraWorkRelation();
        
        self::assertNotEmpty($result);
        
        self::assertContains('isTranslationOf', $result);
        self::assertContains('hasTranslation', $result);
        self::assertContains('isPreprintOf', $result);
        self::assertContains('hasPreprint', $result);
        self::assertContains('isManuscriptOf', $result);
        self::assertContains('hasManuscript', $result);
        self::assertContains('isExpressionOf', $result);
        self::assertContains('hasExpression', $result);
        self::assertContains('isManifestationOf', $result);
        self::assertContains('hasManifestation', $result);
        self::assertContains('isReplacedBy', $result);
        self::assertContains('replaces', $result);
        self::assertContains('isSameAs', $result);
        self::assertContains('isIdenticalTo', $result);
        self::assertContains('isVariantFormOf', $result);
        self::assertContains('isOriginalFormOf', $result);
        self::assertContains('isVersionOf', $result);
        self::assertContains('hasVersion', $result);
        self::assertContains('isFormatOf', $result);
        self::assertContains('hasFormat', $result);
    }

    /**
     * Test that getFlattenedRelationships contains all expected relationship values
     */
    public function testGetFlattenedRelationshipsContainsAllValues(): void
    {
        $result = Relationship::getFlattenedRelationships();
        
        $expectedValues = [
            'isBasedOn', 'isBasisFor', 'basedOnData', 'isDataBasisFor',
            'isCommentOn', 'hasComment',
            'isContinuedBy', 'continues',
            'isDerivedFrom', 'hasDerivation',
            'isDocumentedBy', 'documents',
            'isFinancedBy',
            'isPartOf', 'hasPart',
            'isReviewOf', 'hasReview',
            'references', 'isReferencedBy',
            'hasRelatedMaterial', 'isRelatedMaterial',
            'isReplyTo', 'hasReply',
            'requires', 'isRequiredBy',
            'isCompiledBy', 'compiles',
            'isSupplementTo', 'isSupplementedBy'
        ];
        
        self::assertCount(count($expectedValues), $result);
        
        foreach ($expectedValues as $expectedValue) {
            self::assertContains($expectedValue, $result, "Expected relationship '$expectedValue' not found in flattened result");
        }
    }

    /**
     * Test getSupportedRelationShipsIntraWorkRelation
     */
    public function testGetSupportedRelationShipsIntraWorkRelation(): void
    {
        $result = Relationship::getSupportedRelationShipsIntraWorkRelation();
        
        self::assertNotEmpty($result);
        
        self::assertArrayHasKey('Translation', $result);
        self::assertContains('isTranslationOf', $result['Translation']);
        self::assertContains('hasTranslation', $result['Translation']);
        
        self::assertArrayHasKey('Preprint', $result);
        self::assertContains('isPreprintOf', $result['Preprint']);
        
        self::assertArrayHasKey('Manuscript', $result);
        self::assertContains('isManuscriptOf', $result['Manuscript']);
        
        self::assertArrayHasKey('Expression', $result);
        self::assertContains('isExpressionOf', $result['Expression']);
        
        self::assertArrayHasKey('Manifestation', $result);
        self::assertContains('isManifestationOf', $result['Manifestation']);
        
        self::assertArrayHasKey('Replacement', $result);
        self::assertContains('isReplacedBy', $result['Replacement']);
        
        self::assertArrayHasKey('Same as', $result);
        self::assertContains('isSameAs', $result['Same as']);
        
        self::assertArrayHasKey('Identical', $result);
        self::assertContains('isIdenticalTo', $result['Identical']);
        
        self::assertArrayHasKey('Variant form', $result);
        self::assertContains('isVariantFormOf', $result['Variant form']);
        
        self::assertArrayHasKey('Version', $result);
        self::assertContains('isVersionOf', $result['Version']);
        
        self::assertArrayHasKey('Format', $result);
        self::assertContains('isFormatOf', $result['Format']);
    }

    /**
     * Test getDisplayedRelationShipsIntraWorkRelation
     */
    public function testGetDisplayedRelationShipsIntraWorkRelation(): void
    {
        $result = Relationship::getDisplayedRelationShipsIntraWorkRelation();
        
        self::assertNotEmpty($result);
        
        self::assertArrayHasKey('Translation', $result);
        self::assertContains('isTranslationOf', $result['Translation']);
        self::assertContains('hasTranslation', $result['Translation']);
        
        self::assertArrayHasKey('Replacement', $result);
        self::assertContains('isReplacedBy', $result['Replacement']);
        self::assertContains('replaces', $result['Replacement']);
        
        self::assertArrayHasKey('Same as', $result);
        self::assertContains('isSameAs', $result['Same as']);
        
        self::assertArrayNotHasKey('Preprint', $result);
        self::assertArrayNotHasKey('Manuscript', $result);
        self::assertArrayNotHasKey('Expression', $result);
        self::assertArrayNotHasKey('Manifestation', $result);
        self::assertArrayNotHasKey('Identical', $result);
        self::assertArrayNotHasKey('Variant form', $result);
        self::assertArrayNotHasKey('Version', $result);
        self::assertArrayNotHasKey('Format', $result);
    }

    /**
     * Data provider for removeFirstLevel tests
     * @return array<string, array{0: array<array<string>>, 1: array<string>}>
     */
    public static function removeFirstLevelProvider(): array
    {
        return [
            'basic_structure' => [
                [
                    'group1' => ['item1', 'item2'],
                    'group2' => ['item3', 'item4']
                ],
                ['item1', 'item2', 'item3', 'item4']
            ],
            'single_items' => [
                [
                    'a' => ['x'],
                    'b' => ['y'],
                    'c' => ['z']
                ],
                ['x', 'y', 'z']
            ],
            'varying_lengths' => [
                [
                    'short' => ['a'],
                    'medium' => ['b', 'c'],
                    'long' => ['d', 'e', 'f', 'g']
                ],
                ['a', 'b', 'c', 'd', 'e', 'f', 'g']
            ],
            'numeric_keys' => [
                [
                    0 => ['first', 'second'],
                    1 => ['third'],
                    2 => ['fourth', 'fifth']
                ],
                ['first', 'second', 'third', 'fourth', 'fifth']
            ]
        ];
    }
}
