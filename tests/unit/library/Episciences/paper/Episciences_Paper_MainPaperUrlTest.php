<?php

declare(strict_types=1);

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use Episciences_Repositories;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Zend_Registry;

/**
 * Unit tests for the repository capabilities carried by Episciences_Paper, and for
 * the main file URL they drive.
 *
 * All tests are DB-free: only repositories without files enrichment are exercised
 * through getMainPaperUrl(), so Episciences_Paper_FilesManager is never reached.
 * Zend_Registry is seeded with fake metadataSources whose labels resolve to the
 * real hook classes.
 *
 * Regression guarded: getMainPaperUrl() used to branch on $paper->hasHook, which is
 * merely "the repository has a hooks class". Giving arXiv and HAL a metadata-filtering
 * hooks class (#793) therefore sent their papers looking for a PAPER_FILES row that
 * never exists, so the URL resolved to null — /{docid}/pdf answered 404 "no PDF files
 * found" and the download button disappeared from the article page. bioRxiv and
 * medRxiv had been broken the same way ever since they got a hooks class.
 *
 * @covers Episciences_Paper::hasFilesEnrichment
 * @covers Episciences_Paper::hasConceptIdentifier
 * @covers Episciences_Paper::getMainPaperUrl
 * @covers Episciences_Paper::processFiles
 * @covers Episciences_Paper::setConcept_identifier
 */
final class Episciences_Paper_MainPaperUrlTest extends TestCase
{
    private const DATAVERSE_REPO_ID = 99;

    /** @var array<int, array<string, mixed>> */
    private static array $fakeSources = [];

    public static function setUpBeforeClass(): void
    {
        $source = static fn(string $name, string $paperUrl, string $type = 'repository'): array => [
            'name' => $name,
            'type' => $type,
            'identifier' => null,
            'doc_url' => '',
            'paper_url' => $paperUrl,
            'base_url' => '',
            'api_url' => '',
            'doi_prefix' => '',
        ];

        self::$fakeSources = [
            (int)Episciences_Repositories::HAL_REPO_ID => $source('HAL', 'https://hal.science/%%IDv%%VERSION/document'),
            (int)Episciences_Repositories::ARXIV_REPO_ID => $source('arXiv', 'https://arxiv.org/pdf/%%IDv%%VERSION'),
            (int)Episciences_Repositories::BIO_RXIV_ID => $source('bioRxiv', 'https://www.biorxiv.org/content/%%IDv%%VERSION.full.pdf'),
            (int)Episciences_Repositories::ZENODO_REPO_ID => $source('Zenodo', ''),
            self::DATAVERSE_REPO_ID => $source('ADataverse', '', 'dataverse'),
        ];
    }

    private bool $hadMetadataSources;
    /** @var mixed */
    private $originalMetadataSources;

    protected function setUp(): void
    {
        $this->hadMetadataSources = Zend_Registry::isRegistered('metadataSources');
        if ($this->hadMetadataSources) {
            $this->originalMetadataSources = Zend_Registry::get('metadataSources');
        }
        Zend_Registry::set('metadataSources', self::$fakeSources);
        $this->resetRepositoriesCache();
    }

    protected function tearDown(): void
    {
        // Zend_Registry is a process-wide singleton: restore whatever was registered
        // before this test so the fake sources above don't leak into other test files
        // running later in the same PHPUnit process.
        if ($this->hadMetadataSources) {
            Zend_Registry::set('metadataSources', $this->originalMetadataSources);
        } else {
            Zend_Registry::getInstance()->offsetUnset('metadataSources');
        }
        $this->resetRepositoriesCache();
    }

    private function resetRepositoriesCache(): void
    {
        $prop = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    private function makePaper(int $repoId, string $identifier = 'hal-01234567', float $version = 2): Episciences_Paper
    {
        $paper = new Episciences_Paper();
        $paper->setRepoid($repoId);
        $paper->setIdentifier($identifier);
        $paper->setVersion($version);

        return $paper;
    }

    // -------------------------------------------------------------------------
    // hasFilesEnrichment() delegates to the repository capability, not to hasHook
    // -------------------------------------------------------------------------

    public function testHalPaperHasNoFilesEnrichmentEvenThoughItHasAHooksClass(): void
    {
        $paper = $this->makePaper((int)Episciences_Repositories::HAL_REPO_ID);

        self::assertTrue($paper->hasHook, 'HAL does have a hooks class');
        self::assertFalse($paper->hasFilesEnrichment());
    }

    public function testArxivPaperHasNoFilesEnrichmentEvenThoughItHasAHooksClass(): void
    {
        $paper = $this->makePaper((int)Episciences_Repositories::ARXIV_REPO_ID, '2301.00001');

        self::assertTrue($paper->hasHook, 'arXiv does have a hooks class');
        self::assertFalse($paper->hasFilesEnrichment());
    }

    public function testZenodoPaperHasFilesEnrichment(): void
    {
        self::assertTrue($this->makePaper((int)Episciences_Repositories::ZENODO_REPO_ID)->hasFilesEnrichment());
    }

    public function testDataversePaperHasFilesEnrichment(): void
    {
        self::assertTrue($this->makePaper(self::DATAVERSE_REPO_ID)->hasFilesEnrichment());
    }

    public function testTemporaryPaperHasNoFilesEnrichment(): void
    {
        self::assertFalse($this->makePaper(0)->hasFilesEnrichment());
    }

    // -------------------------------------------------------------------------
    // getMainPaperUrl(): the download URL of a repository without files enrichment
    // is the repository's own paper URL, never a PAPER_FILES lookup
    // -------------------------------------------------------------------------

    public function testHalPaperResolvesToTheRepositoryPaperUrl(): void
    {
        $paper = $this->makePaper((int)Episciences_Repositories::HAL_REPO_ID, 'hal-01234567', 3);

        self::assertSame('https://hal.science/hal-01234567v3/document', $paper->getMainPaperUrl());
    }

    public function testArxivPaperResolvesToTheRepositoryPaperUrl(): void
    {
        $paper = $this->makePaper((int)Episciences_Repositories::ARXIV_REPO_ID, '2301.00001', 1);

        self::assertSame('https://arxiv.org/pdf/2301.00001v1', $paper->getMainPaperUrl());
    }

    public function testBioRxivPaperResolvesToTheRepositoryPaperUrl(): void
    {
        $paper = $this->makePaper((int)Episciences_Repositories::BIO_RXIV_ID, '10.1101/339747', 2);

        self::assertSame(
            'https://www.biorxiv.org/content/10.1101/339747v2.full.pdf',
            $paper->getMainPaperUrl()
        );
    }

    public function testTemporaryPaperHasNoMainPaperUrl(): void
    {
        self::assertNull($this->makePaper(0)->getMainPaperUrl());
    }

    // -------------------------------------------------------------------------
    // processFiles(): feeds the "files" entry of the JSON v2 export (PAPERS.DOCUMENT)
    // -------------------------------------------------------------------------

    /**
     * @return array<mixed>
     */
    private function processFiles(Episciences_Paper $paper, string $journalUrl): array
    {
        $method = new \ReflectionMethod(Episciences_Paper::class, 'processFiles');
        $method->setAccessible(true);

        return $method->invoke($paper, $journalUrl);
    }

    public function testProcessFilesOfAPublishedHalPaperLinksToTheJournalPdfRoute(): void
    {
        $paper = $this->makePaper((int)Episciences_Repositories::HAL_REPO_ID);
        $paper->setDocid(18851);
        $paper->setStatus(Episciences_Paper::STATUS_PUBLISHED);

        self::assertSame(
            ['link' => 'https://epijournal.episciences.org/18851/pdf'],
            $this->processFiles($paper, 'https://epijournal.episciences.org')
        );
    }

    public function testProcessFilesOfAnUnpublishedHalPaperLinksToTheRepository(): void
    {
        $paper = $this->makePaper((int)Episciences_Repositories::HAL_REPO_ID, 'hal-01234567', 3);
        $paper->setDocid(18851);
        $paper->setStatus(Episciences_Paper::STATUS_SUBMITTED);

        self::assertSame(
            ['link' => 'https://hal.science/hal-01234567v3/document'],
            $this->processFiles($paper, 'https://epijournal.episciences.org')
        );
    }

    // -------------------------------------------------------------------------
    // hasConceptIdentifier() / setConcept_identifier()
    // -------------------------------------------------------------------------

    public function testOnlyZenodoExposesConceptIdentifiers(): void
    {
        self::assertTrue($this->makePaper((int)Episciences_Repositories::ZENODO_REPO_ID)->hasConceptIdentifier());
        self::assertFalse($this->makePaper((int)Episciences_Repositories::HAL_REPO_ID)->hasConceptIdentifier());
        self::assertFalse($this->makePaper((int)Episciences_Repositories::ARXIV_REPO_ID)->hasConceptIdentifier());
        self::assertFalse($this->makePaper(self::DATAVERSE_REPO_ID)->hasConceptIdentifier());
    }

    public function testConceptIdentifierIsAcceptedForZenodo(): void
    {
        $paper = $this->makePaper((int)Episciences_Repositories::ZENODO_REPO_ID, '123456');
        $paper->setConcept_identifier('10.5281/zenodo.123456');

        self::assertSame('10.5281/zenodo.123456', $paper->getConcept_identifier());
    }

    public function testConceptIdentifierIsRejectedForHal(): void
    {
        $paper = $this->makePaper((int)Episciences_Repositories::HAL_REPO_ID);

        $this->expectException(InvalidArgumentException::class);
        $paper->setConcept_identifier('hal-01234567');
    }

    public function testConceptIdentifierIsRejectedForArxiv(): void
    {
        $paper = $this->makePaper((int)Episciences_Repositories::ARXIV_REPO_ID, '2301.00001');

        $this->expectException(InvalidArgumentException::class);
        $paper->setConcept_identifier('2301.00001');
    }

    public function testConceptIdentifierIsToleratedOnATemporaryPaper(): void
    {
        // A temporary paper has no repository yet (repoId = 0): the guard cannot
        // decide, so it lets the value through.
        $paper = $this->makePaper(0);
        $paper->setConcept_identifier('10.5281/zenodo.123456');

        self::assertSame('10.5281/zenodo.123456', $paper->getConcept_identifier());
    }

    public function testNullConceptIdentifierIsAlwaysAccepted(): void
    {
        $paper = $this->makePaper((int)Episciences_Repositories::HAL_REPO_ID);
        $paper->setConcept_identifier(null);

        self::assertNull($paper->getConcept_identifier());
    }
}
