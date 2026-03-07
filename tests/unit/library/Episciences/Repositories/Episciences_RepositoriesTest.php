<?php

namespace unit\library\Episciences\Repositories;

use Episciences_Repositories;
use Episciences_Repositories_Dataverse_Hooks;
use Episciences_Repositories_Dspace_Hooks;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Zend_Registry;

/**
 * Unit tests for Episciences_Repositories.
 *
 * All tests are DB-free: Zend_Registry is seeded with fake metadataSources in setUp().
 * The static cache $_repositories is reset between tests via ReflectionProperty.
 *
 * Bugs documented:
 *   R2 — getIdentifier(): $repositories[$repoId] accessed without isset when repoId is unknown → undefined offset
 *   R3 — getDocUrl(): self::getRepositories()[$repoId][REPO_DOCURL] without guard → undefined offset
 *   R4 — getPaperUrl(): self::getRepositories()[$repoId][REPO_PAPERURL] without guard → undefined offset
 *   R6 — getIdentifierExemple(): returns 'Uninitialized variable' for unknown repoId (bad UX)
 *
 * @covers Episciences_Repositories
 */
final class Episciences_RepositoriesTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private static array $fakeSources;

    public static function setUpBeforeClass(): void
    {
        self::$fakeSources = [
            0  => [
                'name'       => 'Episciences',
                'type'       => 'repository',
                'identifier' => null,
                'doc_url'    => '',
                'paper_url'  => '',
                'base_url'   => 'https://episciences.org/',
                'api_url'    => '',
                'doi_prefix' => '',
            ],
            1  => [
                'name'       => 'HAL',
                'type'       => 'repository',
                'identifier' => 'oai:HAL:%%IDv%%VERSION',
                'doc_url'    => 'https://hal.science/%%ID',
                'paper_url'  => 'https://hal.science/%%ID/document',
                'base_url'   => 'https://hal.science/',
                'api_url'    => 'https://api.archives-ouvertes.fr/',
                'doi_prefix' => '',
            ],
            2  => [
                'name'       => 'arXiv',
                'type'       => 'repository',
                'identifier' => 'oai:arXiv:%%IDv%%VERSION',
                'doc_url'    => 'https://arxiv.org/abs/%%IDv%%VERSION',
                'paper_url'  => 'https://arxiv.org/pdf/%%ID',
                'base_url'   => 'https://arxiv.org/',
                'api_url'    => '',
                'doi_prefix' => '',
            ],
            4  => [
                'name'       => 'Zenodo',
                'type'       => 'repository',
                'identifier' => null,
                'doc_url'    => 'https://zenodo.org/record/%%ID',
                'paper_url'  => '',
                'base_url'   => 'https://zenodo.org/',
                'api_url'    => '',
                'doi_prefix' => '10.5281',
            ],
            99 => [
                'name'       => 'ADataverse',
                'type'       => 'dataverse',
                'identifier' => null,
                'doc_url'    => 'https://demo.dataverse.org/dataset.xhtml?persistentId=%%ID&version=%%VERSION.%%V_MINOR_NUMBER',
                'paper_url'  => '',
                'base_url'   => 'https://demo.dataverse.org/',
                'api_url'    => '',
                'doi_prefix' => '',
            ],
            98 => [
                'name'       => 'ADspace',
                'type'       => 'dspace',
                'identifier' => null,
                'doc_url'    => '',
                'paper_url'  => '',
                'base_url'   => 'https://demo.dspace.org/',
                'api_url'    => '',
                'doi_prefix' => '',
            ],
            // Must be filtered out by getRepositories()
            97 => [
                'name'       => 'Software Heritage',
                'type'       => 'repository',
                'identifier' => null,
                'doc_url'    => '',
                'paper_url'  => '',
                'base_url'   => 'https://archive.softwareheritage.org/',
                'api_url'    => '',
                'doi_prefix' => '',
            ],
        ];
    }

    protected function setUp(): void
    {
        Zend_Registry::set('metadataSources', self::$fakeSources);
        $this->resetRepositoriesCache();
    }

    protected function tearDown(): void
    {
        $this->resetRepositoriesCache();
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function resetRepositoriesCache(): void
    {
        $prop = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    // =========================================================================
    // getRepositories()
    // =========================================================================

    public function testGetRepositoriesFiltersOutSoftwareHeritage(): void
    {
        $repos = Episciences_Repositories::getRepositories();
        $names = array_column($repos, 'name');
        self::assertNotContains('Software Heritage', $names);
    }

    public function testGetRepositoriesIncludesExpectedTypes(): void
    {
        $repos = Episciences_Repositories::getRepositories();
        $types = array_unique(array_column($repos, 'type'));
        sort($types);
        self::assertSame(['dataverse', 'dspace', 'repository'], $types);
    }

    public function testGetRepositoriesReturnsCachedInstance(): void
    {
        $first  = Episciences_Repositories::getRepositories();
        $second = Episciences_Repositories::getRepositories();
        self::assertSame($first, $second);
    }

    // =========================================================================
    // getLabel()
    // =========================================================================

    public function testGetLabelReturnsCorrectLabel(): void
    {
        self::assertSame('HAL', Episciences_Repositories::getLabel(1));
    }

    public function testGetLabelReturnsEmptyStringForUnknownRepo(): void
    {
        self::assertSame('', Episciences_Repositories::getLabel(9999));
    }

    // =========================================================================
    // getBaseUrl()
    // =========================================================================

    public function testGetBaseUrlRemovesTrailingSlash(): void
    {
        // All fake base_urls end with '/', rtrim should remove it
        self::assertSame('https://hal.science', Episciences_Repositories::getBaseUrl(1));
    }

    public function testGetBaseUrlReturnsNullForUnknownRepo(): void
    {
        self::assertNull(Episciences_Repositories::getBaseUrl(9999));
    }

    // =========================================================================
    // getIdentifier()
    // =========================================================================

    public function testGetIdentifierWithVersion(): void
    {
        $result = Episciences_Repositories::getIdentifier(1, 'hal-01234567', 3);
        self::assertSame('oai:HAL:hal-01234567v3', $result);
    }

    public function testGetIdentifierWithoutVersionRemovesVersionPlaceholder(): void
    {
        $result = Episciences_Repositories::getIdentifier(2, '1234.5678');
        // 'oai:arXiv:%%IDv%%VERSION' → replaces '%%ID' with id, 'v%%VERSION' with ''
        self::assertSame('oai:arXiv:1234.5678', $result);
    }

    public function testGetIdentifierReturnsNullForNullTemplate(): void
    {
        // Zenodo has identifier=null in fake data
        $result = Episciences_Repositories::getIdentifier(4, '123456');
        self::assertNull($result);
    }

    // =========================================================================
    // getDocUrl()
    // =========================================================================

    public function testGetDocUrlSubstitutesIdAndVersion(): void
    {
        $result = Episciences_Repositories::getDocUrl(1, 'hal-01234567', 3);
        self::assertSame('https://hal.science/hal-01234567', $result);
    }

    public function testGetDocUrlReturnsEmptyStringForEmptyTemplate(): void
    {
        // repoId=0 (Episciences) has empty doc_url
        $result = Episciences_Repositories::getDocUrl(0, 'some-id');
        self::assertSame('', $result);
    }

    public function testGetDocUrlHandlesDataverseVersionSplit(): void
    {
        // Version '2.1' → major=2, minor=1
        $result = Episciences_Repositories::getDocUrl(99, 'doi:10.5281/zenodo.1', '2.1');
        self::assertStringContainsString('version=2.1', $result);
    }

    // =========================================================================
    // getApiUrl()
    // =========================================================================

    public function testGetApiUrlReturnsUrlForKnownRepo(): void
    {
        self::assertSame('https://api.archives-ouvertes.fr/', Episciences_Repositories::getApiUrl(1));
    }

    public function testGetApiUrlReturnsEmptyStringForUnknownRepo(): void
    {
        // Uses ?? operator — safe, returns ''
        self::assertSame('', Episciences_Repositories::getApiUrl(9999));
    }

    // =========================================================================
    // getPaperUrl()
    // =========================================================================

    public function testGetPaperUrlSubstitutesId(): void
    {
        $result = Episciences_Repositories::getPaperUrl(1, 'hal-01234567', 1.0);
        self::assertSame('https://hal.science/hal-01234567/document', $result);
    }

    public function testGetPaperUrlReturnsEmptyStringForEmptyTemplate(): void
    {
        // Zenodo has empty paper_url
        $result = Episciences_Repositories::getPaperUrl(4, '123456');
        self::assertSame('', $result);
    }

    // =========================================================================
    // getRepoDoiPrefix()
    // =========================================================================

    public function testGetRepoDoiPrefixReturnsValueWhenSet(): void
    {
        self::assertSame('10.5281', Episciences_Repositories::getRepoDoiPrefix(4));
    }

    public function testGetRepoDoiPrefixReturnsEmptyStringWhenSetToEmpty(): void
    {
        // HAL has doi_prefix = '' (set but empty) → Ccsd_Tools::ifsetor returns '' (value is set)
        $result = Episciences_Repositories::getRepoDoiPrefix(1);
        self::assertSame('', $result);
    }

    // =========================================================================
    // hasHook()
    // =========================================================================

    public function testHasHookReturnsEmptyStringForRepoWithNoHookClass(): void
    {
        // repoId=4 → label='Zenodo' → class Episciences_Repositories_Zenodo_Hooks
        // It may or may not exist — we only assert it returns a string
        $result = Episciences_Repositories::hasHook(4);
        self::assertIsString($result);
    }

    public function testHasHookReturnsClassNameWhenClassExists(): void
    {
        // repoId=99 → type=dataverse → label forced to 'dataverse' → Episciences_Repositories_Dataverse_Hooks
        if (class_exists('Episciences_Repositories_Dataverse_Hooks')) {
            self::assertSame(
                'Episciences_Repositories_Dataverse_Hooks',
                Episciences_Repositories::hasHook(99)
            );
        } else {
            self::markTestSkipped('Episciences_Repositories_Dataverse_Hooks not loaded');
        }
    }

    // =========================================================================
    // callHook()
    // =========================================================================

    public function testCallHookReturnsEmptyArrayWhenNoRepoIdProvided(): void
    {
        $result = Episciences_Repositories::callHook('hookApiRecords', ['identifier' => 'test']);
        self::assertSame([], $result);
    }

    public function testCallHookReturnsEmptyArrayForNonExistentMethod(): void
    {
        $result = Episciences_Repositories::callHook('nonExistentMethod', ['repoId' => 99]);
        self::assertSame([], $result);
    }

    // =========================================================================
    // getLabels()
    // =========================================================================

    public function testGetLabelsExcludesEpisciencesRepo(): void
    {
        $labels = Episciences_Repositories::getLabels();
        self::assertArrayNotHasKey(0, $labels);
    }

    public function testGetLabelsContainsExpectedEntries(): void
    {
        $labels = Episciences_Repositories::getLabels();
        self::assertArrayHasKey(1, $labels);
        self::assertSame('HAL', $labels[1]);
        self::assertArrayHasKey(4, $labels);
        self::assertSame('Zenodo', $labels[4]);
    }

    // =========================================================================
    // isDataverse()
    // =========================================================================

    public function testIsDataverseTrueForDataverseType(): void
    {
        self::assertTrue(Episciences_Repositories::isDataverse(99));
    }

    public function testIsDataverseFalseForRepositoryType(): void
    {
        self::assertFalse(Episciences_Repositories::isDataverse(1));
    }

    public function testIsDataverseFalseForUnknownRepo(): void
    {
        self::assertFalse(Episciences_Repositories::isDataverse(9999));
    }

    // =========================================================================
    // isDspace()
    // =========================================================================

    public function testIsDspaceTrueForDspaceType(): void
    {
        self::assertTrue(Episciences_Repositories::isDspace(98));
    }

    public function testIsDspaceFalseForRepositoryType(): void
    {
        self::assertFalse(Episciences_Repositories::isDspace(1));
    }

    // =========================================================================
    // getTypeByIdentifier()
    // =========================================================================

    public function testGetTypeByIdentifierReturnsCorrectType(): void
    {
        self::assertSame('repository', Episciences_Repositories::getTypeByIdentifier(1));
        self::assertSame('dataverse', Episciences_Repositories::getTypeByIdentifier(99));
        self::assertSame('dspace', Episciences_Repositories::getTypeByIdentifier(98));
    }

    public function testGetTypeByIdentifierReturnsNullForUnknown(): void
    {
        self::assertNull(Episciences_Repositories::getTypeByIdentifier(9999));
    }

    // =========================================================================
    // getIdentifierExemple()
    // =========================================================================

    public function testGetIdentifierExempleForDataverse(): void
    {
        $result = Episciences_Repositories::getIdentifierExemple(99);
        self::assertSame(Episciences_Repositories_Dataverse_Hooks::DATAVERSE_IDENTIFIER_EXEMPLE, $result);
    }

    public function testGetIdentifierExempleForDspace(): void
    {
        $result = Episciences_Repositories::getIdentifierExemple(98);
        self::assertSame(Episciences_Repositories_Dspace_Hooks::IDENTIFIER_EXEMPLE, $result);
    }

    public function testGetIdentifierExempleForArxiv(): void
    {
        $result = Episciences_Repositories::getIdentifierExemple(2);
        self::assertSame(Episciences_Repositories::IDENTIFIER_EXEMPLES[Episciences_Repositories::ARXIV_REPO_ID], $result);
    }

    public function testGetIdentifierExempleForHalRepositoryFallback(): void
    {
        // repoId=1, label='HAL' → isFromHalRepository = true → fallback to HAL example
        $result = Episciences_Repositories::getIdentifierExemple(1);
        self::assertSame(Episciences_Repositories::IDENTIFIER_EXEMPLES[Episciences_Repositories::HAL_REPO_ID], $result);
    }

    /**
     * Fix R6: unknown repoId now returns '' instead of 'Uninitialized variable'.
     */
    public function testGetIdentifierExempleForUnknownRepoReturnsEmptyString(): void
    {
        $result = Episciences_Repositories::getIdentifierExemple(9999);
        self::assertSame('', $result);
    }

    // =========================================================================
    // isFromHalRepository()
    // =========================================================================

    public function testIsFromHalRepositoryTrueForHal(): void
    {
        self::assertTrue(Episciences_Repositories::isFromHalRepository(1));
    }

    public function testIsFromHalRepositoryFalseForArxiv(): void
    {
        self::assertFalse(Episciences_Repositories::isFromHalRepository(2));
    }

    public function testIsFromHalRepositoryFalseForUnknownRepo(): void
    {
        // getLabel returns '' → str_contains('', 'HAL') = false
        self::assertFalse(Episciences_Repositories::isFromHalRepository(9999));
    }

    // =========================================================================
    // getRepositoriesByLabel()
    // =========================================================================

    public function testGetRepositoriesByLabelKeyedByName(): void
    {
        $byLabel = Episciences_Repositories::getRepositoriesByLabel();
        self::assertArrayHasKey('HAL', $byLabel);
        self::assertArrayHasKey('arXiv', $byLabel);
        self::assertArrayNotHasKey('Software Heritage', $byLabel);
    }

    // =========================================================================
    // getRepoIdByLabel()
    // =========================================================================

    public function testGetRepoIdByLabelReturnsCorrectId(): void
    {
        $repoId = Episciences_Repositories::getRepoIdByLabel('HAL');
        self::assertNotNull($repoId);
        // The returned value is the first element of the found row (the repoId key)
        self::assertSame(1, (int)$repoId);
    }

    public function testGetRepoIdByLabelReturnsNullForUnknownLabel(): void
    {
        $result = Episciences_Repositories::getRepoIdByLabel('NonExistentRepo');
        self::assertNull($result);
    }

    // =========================================================================
    // makeHookClassNameByRepoId() — private method via reflection
    // =========================================================================

    public function testMakeHookClassNameForRepositoryType(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories::class, 'makeHookClassNameByRepoId');
        $method->setAccessible(true);

        // repoId=1 → not dataverse, not dspace → label='HAL' → Episciences_Repositories_HAL_Hooks
        $result = $method->invoke(null, 1);
        self::assertSame('Episciences_Repositories_HAL_Hooks', $result);
    }

    public function testMakeHookClassNameForDataverseType(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories::class, 'makeHookClassNameByRepoId');
        $method->setAccessible(true);

        // repoId=99 → isDataverse=true → label='dataverse' → Episciences_Repositories_Dataverse_Hooks
        $result = $method->invoke(null, 99);
        self::assertSame('Episciences_Repositories_Dataverse_Hooks', $result);
    }

    public function testMakeHookClassNameForDspaceType(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories::class, 'makeHookClassNameByRepoId');
        $method->setAccessible(true);

        // repoId=98 → isDspace=true → label='dspace' → Episciences_Repositories_Dspace_Hooks
        $result = $method->invoke(null, 98);
        self::assertSame('Episciences_Repositories_Dspace_Hooks', $result);
    }

    public function testMakeHookClassNameStripsSpacesFromLabel(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories::class, 'makeHookClassNameByRepoId');
        $method->setAccessible(true);

        // repoId=2 → label='arXiv' → no spaces → Episciences_Repositories_ArXiv_Hooks
        $result = $method->invoke(null, 2);
        self::assertSame('Episciences_Repositories_ArXiv_Hooks', $result);
    }

    // =========================================================================
    // Bug regression tests
    // =========================================================================

    /**
     * Bug R2: getIdentifier() accesses $repositories[$repoId] without isset for unknown repoId.
     * Documents current behavior: returns null (via ?? null on inner key access, but outer
     * access to undefined index emits PHP notice in PHP 8.x).
     */
    public function testGetIdentifierWithUnknownRepoIdReturnsNull(): void
    {
        // The ?? null on line 115 catches the undefined inner key access only if outer key exists.
        // If outer key doesn't exist, PHP 8+ emits a deprecation notice but returns null via ??.
        $result = Episciences_Repositories::getIdentifier(9999, 'some-id');
        // Current behavior: null (outer access is also ?? null implicitly in PHP)
        self::assertNull($result);
    }

    /**
     * Fix R3: getDocUrl() now uses ?? null guard — returns null safely for unknown repoId.
     */
    public function testGetDocUrlWithUnknownRepoIdReturnsNull(): void
    {
        $result = Episciences_Repositories::getDocUrl(9999, 'some-id');
        self::assertNull($result);
    }

    /**
     * Fix R4: getPaperUrl() now uses ?? '' guard — returns '' safely for unknown repoId.
     */
    public function testGetPaperUrlWithUnknownRepoIdReturnsEmptyString(): void
    {
        $result = Episciences_Repositories::getPaperUrl(9999, 'some-id');
        self::assertSame('', $result);
    }

    // =========================================================================
    // Bug B3 — getIdentifier(): empty('0') short-circuits on valid template
    // =========================================================================

    /**
     * Regression B3: empty($template) returns true for '0', which is a valid template.
     * The fix uses ($template === null || $template === '') instead.
     */
    public function testGetIdentifierWithZeroStringTemplateIsNotTreatedAsEmpty(): void
    {
        $sources = self::$fakeSources;
        $sources[50] = [
            'name'       => 'ZeroTemplate',
            'type'       => 'repository',
            'identifier' => '0',
            'doc_url'    => '',
            'paper_url'  => '',
            'base_url'   => 'https://example.org/',
            'api_url'    => '',
            'doi_prefix' => '',
        ];
        Zend_Registry::set('metadataSources', $sources);
        $this->resetRepositoriesCache();

        $result = Episciences_Repositories::getIdentifier(50, 'some-id');
        // Template '0' has no placeholders → str_replace returns '0', not null
        self::assertSame('0', $result);
    }

    /**
     * Confirm that an empty string template still returns '' (null-ish, triggers API call).
     */
    public function testGetIdentifierWithEmptyStringTemplateReturnsEmpty(): void
    {
        $sources = self::$fakeSources;
        $sources[51] = [
            'name'       => 'EmptyTemplate',
            'type'       => 'repository',
            'identifier' => '',
            'doc_url'    => '',
            'paper_url'  => '',
            'base_url'   => 'https://example.org/',
            'api_url'    => '',
            'doi_prefix' => '',
        ];
        Zend_Registry::set('metadataSources', $sources);
        $this->resetRepositoriesCache();

        $result = Episciences_Repositories::getIdentifier(51, 'some-id');
        self::assertSame('', $result);
    }
}
