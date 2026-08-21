<?php

declare(strict_types=1);

namespace unit\library\Episciences\Repositories;

use Episciences_Repositories;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Zend_Registry;

/**
 * Unit tests for the repository capability API of Episciences_Repositories.
 *
 * All tests are DB-free: Zend_Registry is seeded with fake metadataSources whose
 * labels resolve to the real hook classes shipped in library/Episciences/Repositories,
 * so what is asserted here is the interface each of those classes actually declares.
 *
 * Regression guarded: hasHook() only reports that a hooks class exists. Giving arXiv
 * and HAL a hooks class for metadata filtering (#793) therefore flipped every
 * hasHook-driven decision for those two repositories — the paper download URL, the
 * download button, the generic dataset enrichment and the required version number —
 * even though neither class declares any of those capabilities.
 *
 * @covers Episciences_Repositories::hasFilesEnrichment
 * @covers Episciences_Repositories::hasLinkedDataEnrichment
 * @covers Episciences_Repositories::handlesOwnEnrichment
 * @covers Episciences_Repositories::hasConceptIdentifier
 * @covers Episciences_Repositories::isVersionRequired
 */
final class Episciences_Repositories_CapabilitiesTest extends TestCase
{
    private const UNKNOWN_REPO_ID = 4242;
    private const DATAVERSE_REPO_ID = 99;
    private const DSPACE_REPO_ID = 98;

    /**
     * Labels matter: makeHookClassNameByRepoId() derives the hook class name from
     * them (spaces stripped, first letter upper-cased), so 'arXiv' resolves to
     * Episciences_Repositories_ArXiv_Hooks.
     *
     * @var array<int, array<string, mixed>>
     */
    private static array $fakeSources = [];

    public static function setUpBeforeClass(): void
    {
        $source = static fn(string $name, string $type = 'repository'): array => [
            'name' => $name,
            'type' => $type,
            'identifier' => null,
            'doc_url' => '',
            'paper_url' => '',
            'base_url' => '',
            'api_url' => '',
            'doi_prefix' => '',
        ];

        self::$fakeSources = [
            (int)Episciences_Repositories::HAL_REPO_ID => $source('HAL'),
            (int)Episciences_Repositories::ARXIV_REPO_ID => $source('arXiv'),
            (int)Episciences_Repositories::ZENODO_REPO_ID => $source('Zenodo'),
            (int)Episciences_Repositories::BIO_RXIV_ID => $source('bioRxiv'),
            (int)Episciences_Repositories::MED_RXIV_ID => $source('medRxiv'),
            (int)Episciences_Repositories::ARCHE_ID => $source('ARCHE'),
            (int)Episciences_Repositories::CRYPTOLOGY_EPRINT => $source('Cryptology ePrint'),
            self::DATAVERSE_REPO_ID => $source('ADataverse', 'dataverse'),
            self::DSPACE_REPO_ID => $source('ADspace', 'dspace'),
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

    // -------------------------------------------------------------------------
    // Sanity check: the fake labels really do resolve to the shipped hook classes.
    // Without this, every assertion below could pass for the wrong reason.
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{int, string}>
     */
    public static function hookClassProvider(): array
    {
        return [
            'HAL' => [(int)Episciences_Repositories::HAL_REPO_ID, 'Episciences_Repositories_HAL_Hooks'],
            'arXiv' => [(int)Episciences_Repositories::ARXIV_REPO_ID, 'Episciences_Repositories_ArXiv_Hooks'],
            'Zenodo' => [(int)Episciences_Repositories::ZENODO_REPO_ID, 'Episciences_Repositories_Zenodo_Hooks'],
            'bioRxiv' => [(int)Episciences_Repositories::BIO_RXIV_ID, 'Episciences_Repositories_BioRxiv_Hooks'],
            'medRxiv' => [(int)Episciences_Repositories::MED_RXIV_ID, 'Episciences_Repositories_MedRxiv_Hooks'],
            'ARCHE' => [(int)Episciences_Repositories::ARCHE_ID, 'Episciences_Repositories_ARCHE_Hooks'],
            'Cryptology ePrint' => [(int)Episciences_Repositories::CRYPTOLOGY_EPRINT, 'Episciences_Repositories_CryptologyePrint_Hooks'],
            'Dataverse' => [self::DATAVERSE_REPO_ID, 'Episciences_Repositories_Dataverse_Hooks'],
            'DSpace' => [self::DSPACE_REPO_ID, 'Episciences_Repositories_Dspace_Hooks'],
        ];
    }

    /**
     * @dataProvider hookClassProvider
     */
    public function testFakeLabelsResolveToTheShippedHookClasses(int $repoId, string $expectedClass): void
    {
        self::assertSame($expectedClass, Episciences_Repositories::hasHook($repoId));
    }

    // -------------------------------------------------------------------------
    // hasFilesEnrichment(): the repository mirrors its files into PAPER_FILES
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{int, bool}>
     */
    public static function filesEnrichmentProvider(): array
    {
        return [
            // The regression: a hooks class that only filters metadata.
            'HAL has no files enrichment' => [(int)Episciences_Repositories::HAL_REPO_ID, false],
            'arXiv has no files enrichment' => [(int)Episciences_Repositories::ARXIV_REPO_ID, false],
            // Broken by the same conflation long before #793: bioRxiv has had a hooks
            // class since it was added, and it declares no files enrichment either.
            'bioRxiv has no files enrichment' => [(int)Episciences_Repositories::BIO_RXIV_ID, false],
            'medRxiv has no files enrichment' => [(int)Episciences_Repositories::MED_RXIV_ID, false],
            'ARCHE has no files enrichment' => [(int)Episciences_Repositories::ARCHE_ID, false],
            'Zenodo mirrors its files' => [(int)Episciences_Repositories::ZENODO_REPO_ID, true],
            'Cryptology ePrint mirrors its files' => [(int)Episciences_Repositories::CRYPTOLOGY_EPRINT, true],
            'Dataverse mirrors its files' => [self::DATAVERSE_REPO_ID, true],
            'DSpace mirrors its files' => [self::DSPACE_REPO_ID, true],
            'unknown repository' => [self::UNKNOWN_REPO_ID, false],
        ];
    }

    /**
     * @dataProvider filesEnrichmentProvider
     */
    public function testHasFilesEnrichment(int $repoId, bool $expected): void
    {
        self::assertSame($expected, Episciences_Repositories::hasFilesEnrichment($repoId));
    }

    // -------------------------------------------------------------------------
    // hasLinkedDataEnrichment() / handlesOwnEnrichment()
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{int, bool}>
     */
    public static function linkedDataEnrichmentProvider(): array
    {
        return [
            'HAL' => [(int)Episciences_Repositories::HAL_REPO_ID, false],
            'arXiv' => [(int)Episciences_Repositories::ARXIV_REPO_ID, false],
            'bioRxiv' => [(int)Episciences_Repositories::BIO_RXIV_ID, false],
            'medRxiv' => [(int)Episciences_Repositories::MED_RXIV_ID, false],
            'Zenodo' => [(int)Episciences_Repositories::ZENODO_REPO_ID, true],
            'ARCHE' => [(int)Episciences_Repositories::ARCHE_ID, true],
            'Dataverse mirrors files only' => [self::DATAVERSE_REPO_ID, false],
            'DSpace mirrors files only' => [self::DSPACE_REPO_ID, false],
            'unknown repository' => [self::UNKNOWN_REPO_ID, false],
        ];
    }

    /**
     * @dataProvider linkedDataEnrichmentProvider
     */
    public function testHasLinkedDataEnrichment(int $repoId, bool $expected): void
    {
        self::assertSame($expected, Episciences_Repositories::hasLinkedDataEnrichment($repoId));
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function ownEnrichmentProvider(): array
    {
        return [
            // These two must fall back to Episciences_Submit::datasetsProcessing(),
            // as they did before they were given a hooks class.
            'HAL falls back to the generic enrichment' => [(int)Episciences_Repositories::HAL_REPO_ID, false],
            'arXiv falls back to the generic enrichment' => [(int)Episciences_Repositories::ARXIV_REPO_ID, false],
            'bioRxiv falls back to the generic enrichment' => [(int)Episciences_Repositories::BIO_RXIV_ID, false],
            'medRxiv falls back to the generic enrichment' => [(int)Episciences_Repositories::MED_RXIV_ID, false],
            'Zenodo enriches itself' => [(int)Episciences_Repositories::ZENODO_REPO_ID, true],
            'ARCHE enriches itself' => [(int)Episciences_Repositories::ARCHE_ID, true],
            // Files-only repositories have never gone through the generic enrichment.
            'Dataverse enriches itself' => [self::DATAVERSE_REPO_ID, true],
            'DSpace enriches itself' => [self::DSPACE_REPO_ID, true],
            'Cryptology ePrint enriches itself' => [(int)Episciences_Repositories::CRYPTOLOGY_EPRINT, true],
            'unknown repository' => [self::UNKNOWN_REPO_ID, false],
        ];
    }

    /**
     * @dataProvider ownEnrichmentProvider
     */
    public function testHandlesOwnEnrichment(int $repoId, bool $expected): void
    {
        self::assertSame($expected, Episciences_Repositories::handlesOwnEnrichment($repoId));
    }

    // -------------------------------------------------------------------------
    // hasConceptIdentifier()
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{int, bool}>
     */
    public static function conceptIdentifierProvider(): array
    {
        return [
            'Zenodo' => [(int)Episciences_Repositories::ZENODO_REPO_ID, true],
            'HAL' => [(int)Episciences_Repositories::HAL_REPO_ID, false],
            'arXiv' => [(int)Episciences_Repositories::ARXIV_REPO_ID, false],
            'Dataverse' => [self::DATAVERSE_REPO_ID, false],
            'unknown repository' => [self::UNKNOWN_REPO_ID, false],
        ];
    }

    /**
     * @dataProvider conceptIdentifierProvider
     */
    public function testHasConceptIdentifier(int $repoId, bool $expected): void
    {
        self::assertSame($expected, Episciences_Repositories::hasConceptIdentifier($repoId));
    }

    // -------------------------------------------------------------------------
    // isVersionRequired(): a hook returning [] must keep the historical default
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{int, bool}>
     */
    public static function versionRequiredProvider(): array
    {
        return [
            // Episciences_Repositories_{HAL,ArXiv}_Hooks::hookIsRequiredVersion()
            // deliberately return [] to keep the pre-existing default.
            'HAL still requires a version' => [(int)Episciences_Repositories::HAL_REPO_ID, true],
            'arXiv still requires a version' => [(int)Episciences_Repositories::ARXIV_REPO_ID, true],
            'no hooks class at all requires a version' => [self::UNKNOWN_REPO_ID, true],
            'bioRxiv requires a version' => [(int)Episciences_Repositories::BIO_RXIV_ID, true],
            'medRxiv requires a version' => [(int)Episciences_Repositories::MED_RXIV_ID, true],
            'Dataverse requires a version' => [self::DATAVERSE_REPO_ID, true],
            'Zenodo does not' => [(int)Episciences_Repositories::ZENODO_REPO_ID, false],
            'DSpace does not' => [self::DSPACE_REPO_ID, false],
            'ARCHE does not' => [(int)Episciences_Repositories::ARCHE_ID, false],
            'Cryptology ePrint does not' => [(int)Episciences_Repositories::CRYPTOLOGY_EPRINT, false],
        ];
    }

    /**
     * @dataProvider versionRequiredProvider
     */
    public function testIsVersionRequired(int $repoId, bool $expected): void
    {
        self::assertSame($expected, Episciences_Repositories::isVersionRequired($repoId));
    }
}
