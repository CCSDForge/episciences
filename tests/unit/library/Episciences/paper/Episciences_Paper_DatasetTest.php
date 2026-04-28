<?php

namespace unit\library\Episciences;

use Episciences_Paper_Dataset;
use PHPUnit\Framework\TestCase;

final class Episciences_Paper_DatasetTest extends TestCase
{
    /**
     * @dataProvider removeFirstLevelProvider
     */
    public function testRemoveFirstLevel(array $inputArray, array $expectedOutput): void
    {
        $result = Episciences_Paper_Dataset::removeFirstLevel($inputArray);
        self::assertEquals($expectedOutput, $result);
        self::assertIsArray($result);
    }

    /**
     * Test removeFirstLevel with empty array
     */
    public function testRemoveFirstLevelWithEmptyArray(): void
    {
        $result = Episciences_Paper_Dataset::removeFirstLevel([]);
        self::assertEquals([], $result);
        self::assertIsArray($result);
    }

    /**
     * Test removeFirstLevel with single level array
     */
    public function testRemoveFirstLevelWithSingleLevel(): void
    {
        $inputArray = [['value1'], ['value2'], ['value3']];
        $expectedOutput = ['value1', 'value2', 'value3'];
        
        $result = Episciences_Paper_Dataset::removeFirstLevel($inputArray);
        self::assertEquals($expectedOutput, $result);
    }

    /**
     * Test removeFirstLevel with mixed content
     */
    public function testRemoveFirstLevelWithMixedContent(): void
    {
        $inputArray = [
            ['string1', 'string2'],
            ['string3'],
            ['string4', 'string5', 'string6']
        ];
        $expectedOutput = ['string1', 'string2', 'string3', 'string4', 'string5', 'string6'];
        
        $result = Episciences_Paper_Dataset::removeFirstLevel($inputArray);
        self::assertEquals($expectedOutput, $result);
    }

    /**
     * Test getFlattenedRelationships returns flattened relationships
     */
    public function testGetFlattenedRelationships(): void
    {
        $result = Episciences_Paper_Dataset::getFlattenedRelationships();
        
        self::assertIsArray($result);
        self::assertNotEmpty($result);
        
        // Check that some expected values from supportedRelationShips are present
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
        
        // Verify no nested arrays remain (all values should be strings)
        foreach ($result as $value) {
            self::assertIsString($value);
        }
    }

    /**
     * Test that getFlattenedRelationships contains all expected relationship values
     */
    public function testGetFlattenedRelationshipsContainsAllValues(): void
    {
        $result = Episciences_Paper_Dataset::getFlattenedRelationships();
        
        // Expected count based on supportedRelationShips array structure
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
     * Data provider for removeFirstLevel tests
     */
    public function removeFirstLevelProvider(): array
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