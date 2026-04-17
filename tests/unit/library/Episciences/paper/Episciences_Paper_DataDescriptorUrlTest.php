<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use Episciences_Paper_Dataset;
use Episciences_Repositories;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Zend_Registry;

/**
 * Unit tests for Episciences_Paper::getDataDescriptorUrl().
 *
 * All tests are DB-free: getLinkedDataByRelation() is stubbed via createPartialMock().
 * Zend_Registry is seeded with a minimal HAL metadataSources entry whose paper_url
 * template contains both %%ID and %%VERSION, so version extraction is observable.
 *
 * Key regression: before PR #997 the stored HAL identifier was stripped of its
 * version suffix and getDataDescriptorUrl() always produced a v1 link. The tests
 * below lock in the fixed behaviour.
 *
 * @covers Episciences_Paper::getDataDescriptorUrl
 */
final class Episciences_Paper_DataDescriptorUrlTest extends TestCase
{
    /** HAL paper_url includes %%VERSION so we can assert the version is used. */
    private static array $fakeMetadataSources = [
        0 => [
            'name'       => 'Episciences',
            'type'       => 'repository',
            'identifier' => null,
            'doc_url'    => '',
            'paper_url'  => '',
            'base_url'   => 'https://episciences.org/',
            'api_url'    => '',
            'doi_prefix' => '',
        ],
        1 => [ // Episciences_Repositories::HAL_REPO_ID = '1'
            'name'       => 'HAL',
            'type'       => 'repository',
            'identifier' => 'oai:HAL:%%IDv%%VERSION',
            'doc_url'    => 'https://hal.science/%%ID',
            'paper_url'  => 'https://hal.science/%%IDv%%VERSION',
            'base_url'   => 'https://hal.science/',
            'api_url'    => 'https://api.archives-ouvertes.fr/',
            'doi_prefix' => '',
        ],
    ];

    protected function setUp(): void
    {
        Zend_Registry::set('metadataSources', self::$fakeMetadataSources);
        $this->resetRepositoriesCache();
    }

    protected function tearDown(): void
    {
        $this->resetRepositoriesCache();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function resetRepositoriesCache(): void
    {
        $prop = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    private function makeDatasetPaper(?Episciences_Paper_Dataset $linkedData): Episciences_Paper
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['getLinkedDataByRelation']);
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::DATASET_TYPE_TITLE]);
        $paper->method('getLinkedDataByRelation')->willReturn($linkedData);
        return $paper;
    }

    private function makeSoftwarePaper(?Episciences_Paper_Dataset $linkedData): Episciences_Paper
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['getLinkedDataByRelation']);
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::SOFTWARE_TYPE_TITLE]);
        $paper->method('getLinkedDataByRelation')->willReturn($linkedData);
        return $paper;
    }

    private function makeHalDataset(string $value, string $name = 'HAL'): Episciences_Paper_Dataset
    {
        $ld = new Episciences_Paper_Dataset();
        $ld->setName($name);
        $ld->setValue($value);
        return $ld;
    }

    // -------------------------------------------------------------------------
    // Guard: paper type is not dataset/software
    // -------------------------------------------------------------------------

    public function testReturnsNullForArticleType(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['getLinkedDataByRelation']);
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::ARTICLE_TYPE_TITLE]);
        $paper->expects(self::never())->method('getLinkedDataByRelation');

        self::assertNull($paper->getDataDescriptorUrl());
    }

    public function testReturnsNullForPreprintType(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['getLinkedDataByRelation']);
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::DEFAULT_TYPE_TITLE]);
        $paper->expects(self::never())->method('getLinkedDataByRelation');

        self::assertNull($paper->getDataDescriptorUrl());
    }

    // -------------------------------------------------------------------------
    // Guard: no linked data
    // -------------------------------------------------------------------------

    public function testReturnsNullWhenNoLinkedDataForDataset(): void
    {
        self::assertNull($this->makeDatasetPaper(null)->getDataDescriptorUrl());
    }

    public function testReturnsNullWhenNoLinkedDataForSoftware(): void
    {
        self::assertNull($this->makeSoftwarePaper(null)->getDataDescriptorUrl());
    }

    // -------------------------------------------------------------------------
    // Guard: linked data from a non-HAL repository
    // -------------------------------------------------------------------------

    public function testReturnsNullWhenLinkedDataIsArxiv(): void
    {
        $ld = $this->makeHalDataset('2301.00001', 'arXiv');
        self::assertNull($this->makeDatasetPaper($ld)->getDataDescriptorUrl());
    }

    public function testReturnsNullWhenLinkedDataIsZenodo(): void
    {
        $ld = $this->makeHalDataset('7654321', 'Zenodo');
        self::assertNull($this->makeDatasetPaper($ld)->getDataDescriptorUrl());
    }

    // -------------------------------------------------------------------------
    // Happy path: identifier without version suffix (legacy data stored as-is)
    // PR #997 fix: defaults to version 1 when no suffix is present
    // -------------------------------------------------------------------------

    public function testReturnsV1UrlForIdentifierWithoutVersionSuffix(): void
    {
        $paper = $this->makeDatasetPaper($this->makeHalDataset('hal-12345678'));

        self::assertSame('https://hal.science/hal-12345678v1', $paper->getDataDescriptorUrl());
    }

    // -------------------------------------------------------------------------
    // Happy path: versioned identifier — regression guard for PR #997
    // Before the fix the stored value was version-stripped and getPaperUrl()
    // always defaulted to version 1, so "v2" and above were never reachable.
    // -------------------------------------------------------------------------

    public function testReturnsCorrectUrlForV2Identifier(): void
    {
        $paper = $this->makeDatasetPaper($this->makeHalDataset('hal-12345678v2'));

        self::assertSame('https://hal.science/hal-12345678v2', $paper->getDataDescriptorUrl());
    }

    public function testReturnsCorrectUrlForV3Identifier(): void
    {
        $paper = $this->makeDatasetPaper($this->makeHalDataset('hal-12345678v3'));

        self::assertSame('https://hal.science/hal-12345678v3', $paper->getDataDescriptorUrl());
    }

    public function testReturnsCorrectUrlForHighVersionNumber(): void
    {
        $paper = $this->makeDatasetPaper($this->makeHalDataset('hal-12345678v12'));

        self::assertSame('https://hal.science/hal-12345678v12', $paper->getDataDescriptorUrl());
    }

    // -------------------------------------------------------------------------
    // Software-type paper (isSoftware()) follows the same code path
    // -------------------------------------------------------------------------

    public function testReturnsUrlForVersionedHalIdentifierOnSoftwarePaper(): void
    {
        $paper = $this->makeSoftwarePaper($this->makeHalDataset('hal-99999999v5'));

        self::assertSame('https://hal.science/hal-99999999v5', $paper->getDataDescriptorUrl());
    }

    // -------------------------------------------------------------------------
    // HAL name matching is case-insensitive (strtoupper() applied in source)
    // -------------------------------------------------------------------------

    public function testHalNameMatchingIsCaseInsensitive(): void
    {
        $paper = $this->makeDatasetPaper($this->makeHalDataset('hal-12345678v2', 'hal'));

        self::assertSame('https://hal.science/hal-12345678v2', $paper->getDataDescriptorUrl());
    }
}