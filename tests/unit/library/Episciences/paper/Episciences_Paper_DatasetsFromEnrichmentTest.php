<?php

declare(strict_types=1);

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use Episciences_Paper_Dataset;
use PHPUnit\Framework\TestCase;
use Zend_Registry;

/**
 * Episciences_Paper::getDatasetsFromEnrichment(): the linked data of the paper page are grouped by their relation
 * in the stored vocabulary, whatever the vocabulary of the stored row.
 */
final class Episciences_Paper_DatasetsFromEnrichmentTest extends TestCase
{
    private const int SOURCE_ID = 999;

    private mixed $previousMetadataSources = null;
    private bool $hadMetadataSources = false;

    protected function setUp(): void
    {
        $this->hadMetadataSources = Zend_Registry::isRegistered('metadataSources');
        if ($this->hadMetadataSources) {
            $this->previousMetadataSources = Zend_Registry::get('metadataSources');
        }
        Zend_Registry::set('metadataSources', [self::SOURCE_ID => ['name' => 'Source']]);
    }

    protected function tearDown(): void
    {
        Zend_Registry::set('metadataSources', $this->hadMetadataSources ? $this->previousMetadataSources : []);
    }

    public function testLegacyDataCiteRowsAreGroupedWithTheStoredRelation(): void
    {
        $groups = $this->displayGroups('dataset', ['isRelatedMaterial', 'IsRelatedTo', 'IsReviewedBy', 'hasReview', 'IsSupplementedBy']);

        self::assertSame(['IsSupplementedBy' => 1, 'IsRelatedMaterial' => 2, 'HasReview' => 2], $groups);
    }

    public function testRelationsWithoutEquivalentAreGroupedAsOther(): void
    {
        $groups = $this->displayGroups('software', [null, 'hasMetadata', '', 'references']);

        self::assertSame(['References' => 1, 'Other' => 3], $groups);
    }

    public function testAnUntypedLinkedDataIsAPublication(): void
    {
        $groups = $this->displayGroups('url', ['Cites']);

        self::assertSame(['References' => 1], $groups);
    }

    /**
     * @param list<string|null> $relationships stored relationships of the linked data of type $type
     * @return array<string, int> display group => number of linked data, in display order
     */
    private function displayGroups(string $type, array $relationships): array
    {
        $datasets = [];
        foreach ($relationships as $i => $relationship) {
            $dataset = (new Episciences_Paper_Dataset())
                ->setId($i + 1)
                ->setCode($type)
                ->setName($type)
                ->setValue('10.1/' . $i)
                ->setLink('doi')
                ->setRelationship($relationship);
            $dataset->setSourceId(self::SOURCE_ID);
            $datasets[] = $dataset;
        }

        $paper = $this->createPartialMock(Episciences_Paper::class, ['getDatasets']);
        $paper->method('getDatasets')->willReturn($datasets);

        $formatted = $paper->getDatasetsFromEnrichment();
        $displayType = in_array($type, ['dataset', 'software', 'publication'], true) ? $type : 'publication';
        self::assertArrayHasKey($displayType, $formatted);

        return array_map(
            static fn(array $bySource): int => count($bySource[self::SOURCE_ID] ?? []),
            $formatted[$displayType]
        );
    }
}
