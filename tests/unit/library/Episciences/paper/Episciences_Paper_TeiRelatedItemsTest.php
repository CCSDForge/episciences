<?php

declare(strict_types=1);

namespace unit\library\Episciences\paper;

use DOMDocument;
use DOMElement;
use Episciences_Paper_Tei;
use PHPUnit\Framework\TestCase;

/**
 * <relatedItem> of the TEI export: the relation is given in the DataCite vocabulary used by HAL TEI.
 */
final class Episciences_Paper_TeiRelatedItemsTest extends TestCase
{
    public function testTheRelationTypeIsTheDataCiteOne(): void
    {
        $items = $this->relatedItems([
            ['value' => '10.1/supplement', 'link' => 'doi', 'relationship' => 'isSupplementedBy'],
            ['value' => '10.1/review', 'link' => 'doi', 'relationship' => 'hasReview'],
            ['value' => '10.1/translation', 'link' => 'doi', 'relationship' => 'isTranslationOf'],
            ['value' => '10.1/legacy', 'link' => 'doi', 'relationship' => 'IsSourceOf'],
        ]);

        self::assertSame([
            'https://doi.org/10.1/supplement' => 'IsSupplementedBy',
            'https://doi.org/10.1/review' => 'IsReviewedBy',
            'https://doi.org/10.1/translation' => 'IsTranslationOf',
            'https://doi.org/10.1/legacy' => 'IsSourceOf',
        ], $items);
    }

    public function testARelationWithoutDataCiteEquivalentHasNoType(): void
    {
        $items = $this->relatedItems([
            ['value' => '10.1/funding', 'link' => 'doi', 'relationship' => 'isFinancedBy'],
            ['value' => '10.1/none', 'link' => 'doi', 'relationship' => null],
        ]);

        self::assertSame(['https://doi.org/10.1/funding' => null, 'https://doi.org/10.1/none' => null], $items);
    }

    public function testLinkedDataWithoutUrlAreLeftOut(): void
    {
        self::assertSame([], $this->relatedItems([['value' => 'x', 'link' => 'unknown', 'relationship' => 'references']]));
    }

    /**
     * @param array<int, array<string, mixed>> $linkedData
     * @return array<string, string|null> target => type (null when absent)
     */
    private function relatedItems(array $linkedData): array
    {
        $items = [];

        foreach (Episciences_Paper_Tei::relatedItems(new DOMDocument(), $linkedData) as $item) {
            self::assertInstanceOf(DOMElement::class, $item);
            self::assertSame('relatedItem', $item->tagName);
            $items[$item->getAttribute('target')] = $item->hasAttribute('type') ? $item->getAttribute('type') : null;
        }

        return $items;
    }
}
