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
     * Test getFlattenedRelationshipsIntraWorkRelation returns flattened intra work relationships
     */
    public function testGetFlattenedRelationshipsIntraWorkRelation(): void
    {
        $result = Episciences_Paper_Dataset::getFlattenedRelationshipsIntraWorkRelation();
        
        self::assertIsArray($result);
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
     * Test getSupportedRelationShipsIntraWorkRelation
     */
    public function testGetSupportedRelationShipsIntraWorkRelation(): void
    {
        $result = Episciences_Paper_Dataset::getSupportedRelationShipsIntraWorkRelation();
        
        self::assertIsArray($result);
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
        $result = Episciences_Paper_Dataset::getDisplayedRelationShipsIntraWorkRelation();
        
        self::assertIsArray($result);
        self::assertNotEmpty($result);
        
        // displayed categories
        self::assertArrayHasKey('Translation', $result);
        self::assertContains('isTranslationOf', $result['Translation']);
        self::assertContains('hasTranslation', $result['Translation']);
        
        self::assertArrayHasKey('Replacement', $result);
        self::assertContains('isReplacedBy', $result['Replacement']);
        self::assertContains('replaces', $result['Replacement']);
        
        self::assertArrayHasKey('Same as', $result);
        self::assertContains('isSameAs', $result['Same as']);
        
        // hidden categories should not be present in displayed result
        self::assertArrayNotHasKey('Preprint', $result);
        self::assertArrayNotHasKey('Manuscript', $result);
        self::assertArrayNotHasKey('Expression', $result);
        self::assertArrayNotHasKey('Manifestation', $result);
        self::assertArrayNotHasKey('Identical', $result);
        self::assertArrayNotHasKey('Variant form', $result);
        self::assertArrayNotHasKey('Version', $result);
        self::assertArrayNotHasKey('Format', $result);
    }

    // =========================================================================
    // setMetatextCitation() — null-argument regression (May 2026)
    // =========================================================================

    /**
     * Bug fix: setMetatextCitation() previously declared `string $metatextCitation`
     * with no default, so passing null (or omitting the argument) caused a fatal
     * TypeError. The fix adds `= ''` as a default value.
     *
     * Regression guard: calling without arguments must not throw.
     * We read the raw property via reflection because the public getter calls
     * buildMetatextCitation() when the stored value is empty, which needs the DB.
     */
    public function testSetMetatextCitationWithNoArgumentDoesNotThrow(): void
    {
        $dataset = new Episciences_Paper_Dataset();
        $dataset->setMetatextCitation();

        $prop = new \ReflectionProperty(Episciences_Paper_Dataset::class, 'metatextCitation');
        $prop->setAccessible(true);
        self::assertSame('', $prop->getValue($dataset));
    }

    /**
     * Calling setMetatextCitation() with an explicit value must still work.
     * strip_tags() in the getter is safe for plain-text input without DB.
     */
    public function testSetMetatextCitationWithValueStoresIt(): void
    {
        $dataset = new Episciences_Paper_Dataset();
        $dataset->setMetatextCitation('Author (2026). Title. Journal.');
        self::assertSame('Author (2026). Title. Journal.', $dataset->getMetatextCitation());
    }

    /**
     * Calling setMetatextCitation('') must store an empty string.
     * Raw-property check avoids triggering buildMetatextCitation().
     */
    public function testSetMetatextCitationWithEmptyStringStoresEmpty(): void
    {
        $dataset = new Episciences_Paper_Dataset();
        $dataset->setMetatextCitation('nonempty');
        $dataset->setMetatextCitation('');

        $prop = new \ReflectionProperty(Episciences_Paper_Dataset::class, 'metatextCitation');
        $prop->setAccessible(true);
        self::assertSame('', $prop->getValue($dataset));
    }

    /**
     * Data provider for removeFirstLevel tests
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