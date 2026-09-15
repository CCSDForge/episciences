<?php

namespace unit\library\Episciences\Paper;

use Episciences_Paper;
use Episciences_Paper_RepositoryVersionsService;
use Episciences_Repositories;
use Episciences_Repositories_BioMedRxiv;
use Episciences_Repositories_Dataverse_Hooks;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Zend_Registry;

/**
 * Unit tests for Episciences_Paper_RepositoryVersionsService.
 *
 * The service fetches the versions of a paper available in its source
 * repository (HAL, Zenodo, bioRxiv/medRxiv, Dataverse, arXiv, cryptology...).
 *
 * All tests are DB-free and network-free:
 *  - Zend_Registry is seeded with fake metadataSources in setUp() so the
 *    static Episciences_Repositories helpers can resolve repos.
 *  - The static Episciences_Repositories::$_repositories cache is reset
 *    between tests via ReflectionProperty.
 *  - Network/HOAI collaborators are injected as fakes through the constructor.
 *
 * @covers Episciences_Paper_RepositoryVersionsService
 */
final class Episciences_Paper_RepositoryVersionsServiceTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private static array $fakeSources;

    public static function setUpBeforeClass(): void
    {
        self::$fakeSources = [
            Episciences_Repositories::HAL_REPO_ID => [
                'name'       => 'HAL',
                'type'       => 'repository',
                'identifier' => 'oai:HAL:%%IDv%%VERSION',
                'base_url'   => 'https://hal.science/',
                'api_url'    => 'https://api.archives-ouvertes.fr/',
            ],
            Episciences_Repositories::ARXIV_REPO_ID => [
                'name'       => 'arXiv',
                'type'       => 'repository',
                'identifier' => 'oai:arXiv:%%IDv%%VERSION',
                'base_url'   => 'https://arxiv.org/',
                'api_url'    => '',
            ],
            Episciences_Repositories::ZENODO_REPO_ID => [
                'name'       => 'Zenodo',
                'type'       => 'repository',
                'identifier' => null,
                'base_url'   => 'https://zenodo.org/',
                'api_url'    => 'https://zenodo.org/api/',
            ],
            Episciences_Repositories::BIO_RXIV_ID => [
                'name'       => 'bioRxiv',
                'type'       => 'repository',
                'identifier' => null,
                'base_url'   => 'https://api.biorxiv.org/',
                'api_url'    => 'https://api.biorxiv.org/details/',
            ],
            99 => [
                'name'       => 'ADataverse',
                'type'       => 'dataverse',
                'identifier' => null,
                'base_url'   => 'https://demo.dataverse.org/',
                'api_url'    => 'https://demo.dataverse.org/api/',
            ],
            98 => [
                'name'       => 'SomeEprint',
                'type'       => 'repository',
                'identifier' => 'oai:eprint:%%IDv%%VERSION',
                'base_url'   => 'https://eprint.example/',
                'api_url'    => '',
            ],
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

    /**
     * @param array<string, mixed> $overrides
     */
    private function mockPaper(array $overrides = []): Episciences_Paper
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getRepoid')->willReturn($overrides['repoid'] ?? 1);
        $paper->method('getIdentifier')->willReturn($overrides['identifier'] ?? 'hal-01234567');
        $paper->method('getConcept_identifier')->willReturn($overrides['concept'] ?? 'hal-01234567');

        return $paper;
    }

    private function buildService(?callable $apiCaller = null): Episciences_Paper_RepositoryVersionsService
    {
        return new Episciences_Paper_RepositoryVersionsService($apiCaller);
    }

    // =========================================================================
    // HAL
    // =========================================================================

    public function testHalExtractsEditionNumbersFromXml(): void
    {
        $xml = '<record xmlns="http://www.tei-c.org/ns/1.0"><text><body><listBibl><biblFull><editionStmt>'
            . '<edition n="v1"></edition><edition n="v2"></edition><edition n="v3"></edition>'
            . '</editionStmt></biblFull></listBibl></body></text></record>';

        $service = $this->buildService(static function () use ($xml) {
            return ['response' => ['docs' => [['label_xml' => $xml]]]];
        });

        $versions = $service->getAvailableVersions($this->mockPaper());

        // Non-Zenodo results are sorted descending by arsort()
        $this->assertSame([2 => 3, 1 => 2, 0 => 1], $versions);
    }

    public function testHalReturnsEmptyWhenResponseIsNotArray(): void
    {
        $service = $this->buildService(static fn() => false);

        $this->assertSame([], $service->getAvailableVersions($this->mockPaper()));
    }

    public function testHalReturnsEmptyWhenNoDocs(): void
    {
        $service = $this->buildService(static fn() => ['response' => ['docs' => []]]);

        $this->assertSame([], $service->getAvailableVersions($this->mockPaper()));
    }

    public function testHalReturnsEmptyWhenNoLabelXml(): void
    {
        $service = $this->buildService(static fn() => ['response' => ['docs' => [[]]]]);

        $this->assertSame([], $service->getAvailableVersions($this->mockPaper()));
    }

    // =========================================================================
    // Zenodo
    // =========================================================================

    public function testZenodoKeepsItsOwnOrderingAndFiltersLowerIds(): void
    {
        $paper = $this->mockPaper([
            'repoid' => (int)Episciences_Repositories::ZENODO_REPO_ID,
            'identifier' => '12345',
        ]);

        // Latest known identifier is 12345. The API returns hits in reverse order
        // (newest first): 12347 then 12346. The service maps them with a reversed
        // index and returns WITHOUT arsort-ing, so the input order is preserved.
        $service = $this->buildService(function (string $url, array $options = []) {
            $this->assertStringContainsString('/api/records/12345/versions', $url);
            return ['hits' => ['hits' => [['id' => '12347'], ['id' => '12346']]]];
        });

        $this->assertSame([2 => '12347', 1 => '12346'], $service->getAvailableVersions($paper));
    }

    public function testZenodoSkipsHitsWithoutId(): void
    {
        $paper = $this->mockPaper([
            'repoid' => (int)Episciences_Repositories::ZENODO_REPO_ID,
            'identifier' => '10',
        ]);

        $service = $this->buildService(static fn() => ['hits' => ['hits' => [['id' => '11'], ['foo' => 'bar']]]]);

        $this->assertSame([2 => '11'], $service->getAvailableVersions($paper));
    }

    // =========================================================================
    // bioRxiv / medRxiv
    // =========================================================================

    public function testBioMedRxivExtractsVersionsFromCollection(): void
    {
        $paper = $this->mockPaper([
            'repoid' => (int)Episciences_Repositories::BIO_RXIV_ID,
            'identifier' => '10.1101/123456',
        ]);

        $response = [
            'messages' => [['status' => Episciences_Repositories_BioMedRxiv::SUCCESS_CODE]],
            'collection' => [
                ['version' => '1'],
                ['version' => '2'],
                ['version' => '3'],
            ],
        ];

        $service = $this->buildService(static fn() => $response);

        $this->assertSame([3 => '3', 2 => '2', 1 => '1'], $service->getAvailableVersions($paper));
    }

    public function testBioMedRxivReturnsEmptyWhenStatusNotSuccess(): void
    {
        $paper = $this->mockPaper([
            'repoid' => (int)Episciences_Repositories::BIO_RXIV_ID,
            'identifier' => '10.1101/123456',
        ]);

        $response = [
            'messages' => [['status' => 'error']],
            'collection' => [['version' => '1']],
        ];

        $service = $this->buildService(static fn() => $response);

        $this->assertSame([], $service->getAvailableVersions($paper));
    }

    public function testBioMedRxivReturnsEmptyWhenMessagesMissing(): void
    {
        $paper = $this->mockPaper([
            'repoid' => (int)Episciences_Repositories::BIO_RXIV_ID,
            'identifier' => '10.1101/123456',
        ]);

        $service = $this->buildService(static fn() => ['collection' => [['version' => '1']]]);

        $this->assertSame([], $service->getAvailableVersions($paper));
    }

    public function testBioMedRxivReturnsEmptyWhenMessagesNotAnArray(): void
    {
        $paper = $this->mockPaper([
            'repoid' => (int)Episciences_Repositories::BIO_RXIV_ID,
            'identifier' => '10.1101/123456',
        ]);

        $service = $this->buildService(static fn() => ['messages' => 'unexpected', 'collection' => []]);

        $this->assertSame([], $service->getAvailableVersions($paper));
    }

    // =========================================================================
    // Dataverse
    // =========================================================================

    public function testDataverseBuildsVersionListFromLatestVersion(): void
    {
        $paper = $this->mockPaper(['repoid' => 99, 'identifier' => 'doi:10.5072/FK2/ABC']);

        $response = [
            'status' => Episciences_Repositories_Dataverse_Hooks::SUCCESS_CODE,
            'data' => [
                'latestVersion' => [
                    'versionNumber' => 2,
                    'versionMinorNumber' => 0,
                ],
            ],
        ];

        $service = $this->buildService(static fn() => $response);

        $this->assertSame(['2.0', '1.0'], $service->getAvailableVersions($paper));
    }

    public function testDataverseReturnsEmptyWhenStatusNotSuccess(): void
    {
        $paper = $this->mockPaper(['repoid' => 99, 'identifier' => 'doi:10.5072/FK2/ABC']);

        $service = $this->buildService(static fn() => ['status' => 'ERROR']);

        $this->assertSame([], $service->getAvailableVersions($paper));
    }

    // =========================================================================
    // Hook-based repositories (no API url, not arXiv)
    // =========================================================================

    public function testNonCryptologyHookRepoReturnsEmpty(): void
    {
        $paper = $this->mockPaper(['repoid' => 98, 'identifier' => '2026/0001']);

        $hookCaller = function (string $hookName, array $params) {
            $this->assertSame('hookApiRecords', $hookName);
            return [];
        };

        $service = new Episciences_Paper_RepositoryVersionsService(null, null, $hookCaller);

        $this->assertSame([], $service->getAvailableVersions($paper));
    }

    public function testUnknownRepoWithoutApiReturnsEmpty(): void
    {
        $paper = $this->mockPaper(['repoid' => 42, 'identifier' => 'whatever']);

        $service = new Episciences_Paper_RepositoryVersionsService(null, null, static fn() => []);

        $this->assertSame([], $service->getAvailableVersions($paper));
    }

    // =========================================================================
    // arXiv (OAI path)
    // =========================================================================

    public function testArxivUsesInjectedFetcher(): void
    {
        $paper = $this->mockPaper(['repoid' => (int)Episciences_Repositories::ARXIV_REPO_ID, 'identifier' => '0123.45678']);

        $oaiFetcher = function (string $baseUrl, string $identifier) {
            $this->assertSame('https://arxiv.org', $baseUrl);
            $this->assertSame('oai:arXiv:0123.45678', $identifier);
            return ['v3'];
        };

        $service = new Episciences_Paper_RepositoryVersionsService(null, $oaiFetcher);

        $this->assertSame(['v3'], $service->getAvailableVersions($paper));
    }
}
