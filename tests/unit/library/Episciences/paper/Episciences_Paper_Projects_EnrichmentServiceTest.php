<?php

namespace unit\library\Episciences;

use Episciences_Hal_TeiCacheManager;
use Episciences_Paper_Projects_EnrichmentService;
use Episciences_Paper_Projects_Repository;
use Episciences_Repositories;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use RuntimeException;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * @covers Episciences_Paper_Projects_EnrichmentService
 */
final class Episciences_Paper_Projects_EnrichmentServiceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // formatFundingOAForDB() — OpenAire Graph v3 direct project entries
    // -------------------------------------------------------------------------

    public function testFormatFundingOAForDBv3MapsDirectScalarFields(): void
    {
        $fileFound = [
            [
                'id'      => 'corda_______::824087',
                'code'    => '824087',
                'acronym' => 'EOSC-Pillar',
                'title'   => 'European Open Science Cloud Pillar',
                'funder'  => 'European Commission',
                'pids'    => null,
            ],
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, []);

        self::assertCount(1, $result);
        self::assertSame('European Open Science Cloud Pillar', $result[0]['projectTitle']);
        self::assertSame('824087', $result[0]['code']);
        self::assertSame('EOSC-Pillar', $result[0]['acronym']);
        self::assertSame('European Commission', $result[0]['funderName']);
    }

    public function testFormatFundingOAForDBv3NullAcronymOmitted(): void
    {
        $fileFound = [
            [
                'id'      => 'nih_________::01713ce3da46fe56c2b11399f029007c',
                'code'    => '5T15LM007059-20',
                'acronym' => null,
                'title'   => 'Pittsburgh Biomedical Informatics Training Program',
                'funder'  => 'National Institutes of Health',
                'pids'    => null,
            ],
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, []);

        self::assertCount(1, $result);
        self::assertArrayNotHasKey('acronym', $result[0]);
    }

    public function testFormatFundingOAForDBv3FunderAsDataModelObject(): void
    {
        $fileFound = [
            [
                'code'    => '824087',
                'acronym' => null,
                'title'   => 'European Open Science Cloud Pillar',
                'funder'  => ['name' => 'European Commission', 'shortName' => 'EC'],
                'pids'    => null,
            ],
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, []);

        self::assertSame('European Commission', $result[0]['funderName']);
    }

    public function testFormatFundingOAForDBv3MultipleProjectsMapped(): void
    {
        $fileFound = [
            [
                'code'    => '824087',
                'acronym' => 'EOSC-Pillar',
                'title'   => 'European Open Science Cloud Pillar',
                'funder'  => 'European Commission',
                'pids'    => null,
            ],
            [
                'code'    => 'ANR-11-LABX-0010',
                'acronym' => 'DRIIHM / IRDHEI',
                'title'   => 'Dispositif de recherche interdisciplinaire sur les Interactions Hommes-Milieux',
                'funder'  => 'French National Research Agency (ANR)',
                'pids'    => null,
            ],
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, []);

        self::assertCount(2, $result);
        self::assertSame('EOSC-Pillar', $result[0]['acronym']);
        self::assertSame('DRIIHM / IRDHEI', $result[1]['acronym']);
    }

    public function testFormatFundingOAForDBv3IgnoresEntriesWithoutScalarTitle(): void
    {
        $fileFound = [
            ['code' => 'CODE-001', 'title' => ['$' => 'legacy-shaped title, must be ignored']],
            ['code' => 'CODE-002'], // no 'title' key at all
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, []);

        self::assertSame([], $result);
    }

    public function testFormatFundingOAForDBv3PreservesExistingGlobalArray(): void
    {
        $existing = [['projectTitle' => 'Existing Project', 'funderName' => 'Existing Funder', 'code' => 'EX-001']];
        $fileFound = [
            [
                'code'    => 'NEW-001',
                'acronym' => null,
                'title'   => 'New Project',
                'funder'  => 'New Funder',
                'pids'    => null,
            ],
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, $existing);

        self::assertCount(2, $result);
        self::assertSame('Existing Project', $result[0]['projectTitle']);
        self::assertSame('New Project', $result[1]['projectTitle']);
    }

    public function testFormatFundingOAForDBv3KeepsOpenAireIdAndGrantDoi(): void
    {
        $fileFound = [
            [
                'id'      => 'corda__h2020::2f32bdaa3c76066bd8267f3ac90ba898',
                'code'    => '101017452',
                'acronym' => 'OpenAIRE Nexus',
                'title'   => 'OpenAIRE-Nexus Scholarly Communication Services for EOSC users',
                'funder'  => 'European Commission',
                'pids'    => [['scheme' => 'ark', 'value' => 'x'], ['scheme' => 'doi', 'value' => '10.3030/101017452']],
            ],
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, []);

        self::assertSame('corda__h2020::2f32bdaa3c76066bd8267f3ac90ba898', $result[0]['openaireId']);
        self::assertSame('10.3030/101017452', $result[0]['grantDoi']);
    }

    public function testFormatFundingOAForDBv3WithoutPidsHasNoGrantDoi(): void
    {
        $fileFound = [['id' => 'anr_________::abc', 'title' => 'T', 'pids' => null]];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, []);

        self::assertArrayNotHasKey('grantDoi', $result[0]);
        self::assertSame('anr_________::abc', $result[0]['openaireId']);
    }

    // -------------------------------------------------------------------------
    // enrichHalFundingFromTei() — a funding removed from HAL
    // -------------------------------------------------------------------------

    private const HAL_ID = 'hal-07654321';
    private const PAPER_ID = 987654322;

    private ?Zend_Db_Adapter_Abstract $previousAdapter = null;

    private ArrayAdapter $projectsCache;

    protected function tearDown(): void
    {
        Episciences_Hal_TeiCacheManager::setCachePool(null);
        Episciences_Hal_TeiCacheManager::setHttpClient(null);
        Episciences_Paper_Projects_Repository::setCachePool(new ArrayAdapter());
        if ($this->previousAdapter !== null) {
            Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
        }
    }

    /**
     * Cached TEI and cached HAL row of the paper; returns the DB adapter, which only deletions reach.
     *
     * @param array<int|string, array<string, mixed>> $halRow
     */
    private function givenHalTeiAndRow(string $tei, array $halRow): Zend_Db_Adapter_Abstract&MockObject
    {
        $teiCache = new ArrayAdapter(0, false);
        $teiCache->save($teiCache->getItem('hal-tei-' . self::HAL_ID . '.xml')->set($tei));
        Episciences_Hal_TeiCacheManager::setCachePool($teiCache);

        $this->projectsCache = new ArrayAdapter(0, false);
        $this->projectsCache->save($this->projectsCache->getItem(self::halRowCacheKey())->set($halRow));
        Episciences_Paper_Projects_Repository::setCachePool($this->projectsCache);

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['delete', 'update', 'query'])
            ->getMock();
        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);

        return $adapter;
    }

    private static function halRowCacheKey(): string
    {
        return 'projects_paper_' . self::PAPER_ID . '_source_' . Episciences_Repositories::HAL_REPO_ID;
    }

    private static function halTei(string $listBibl): string
    {
        return '<?xml version="1.0" encoding="utf-8"?><TEI xmlns="http://www.tei-c.org/ns/1.0"><text><body><listBibl>'
            . $listBibl . '</listBibl></body></text></TEI>';
    }

    public function testHalRowIsDeletedWhenTheDocumentNoLongerHasAnyFunding(): void
    {
        $adapter = $this->givenHalTeiAndRow(
            self::halTei('<biblFull><titleStmt><title>A paper</title></titleStmt></biblFull>'),
            [1 => ['funding' => '{"0":{"fundingStatement":"Removed funding"}}']]
        );

        $adapter->expects(self::once())
            ->method('delete')
            ->with(T_PAPER_PROJECTS, ['paperid = ?' => self::PAPER_ID, 'source_id = ?' => (int) Episciences_Repositories::HAL_REPO_ID])
            ->willReturn(1);
        $adapter->expects(self::never())->method('update');
        $this->expectOutputRegex('/no funding left for ' . self::HAL_ID . ', HAL funding project removed/');

        self::assertSame(1, Episciences_Paper_Projects_EnrichmentService::enrichHalFundingFromTei(self::PAPER_ID, self::HAL_ID, 0));
        self::assertFalse($this->projectsCache->hasItem(self::halRowCacheKey()), 'cached HAL row dropped');
    }

    public function testNothingIsDeletedWhenThereWasNoHalRow(): void
    {
        $adapter = $this->givenHalTeiAndRow(
            self::halTei('<biblFull><titleStmt><title>A paper</title></titleStmt></biblFull>'),
            []
        );

        $adapter->expects(self::never())->method('delete');

        self::assertSame(0, Episciences_Paper_Projects_EnrichmentService::enrichHalFundingFromTei(self::PAPER_ID, self::HAL_ID, 0));
    }

    public function testStoredFundingsAreKeptAndNewOnesAddedWhenAFunderRefersToAnUnknownProject(): void
    {
        // the project is missing from listOrg: the fundings read are incomplete, so none stored is removed
        $adapter = $this->givenHalTeiAndRow(
            self::halTei(
                '<biblFull><titleStmt><title>A paper</title><funder ref="#projanr-404"/>'
                . '<funder>Kept funding</funder><funder>New funding</funder></titleStmt></biblFull>'
            ),
            [1 => ['funding' => '{"0":{"fundingStatement":"Kept funding"},"1":{"projectTitle":"Project missing from the TEI","code":"ANR-19-CE23-0001"}}']]
        );

        $adapter->expects(self::never())->method('delete');
        $adapter->expects(self::once())
            ->method('update')
            ->with(T_PAPER_PROJECTS, ['funding' => json_encode([
                ['fundingStatement' => 'Kept funding'],
                ['fundingStatement' => 'New funding'],
                ['projectTitle' => 'Project missing from the TEI', 'code' => 'ANR-19-CE23-0001'],
            ], JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)], self::anything())
            ->willReturn(1);
        $this->expectOutputRegex('/refers to unknown project\(s\) projanr-404: stored HAL fundings missing from the TEI kept/');

        self::assertSame(1, Episciences_Paper_Projects_EnrichmentService::enrichHalFundingFromTei(self::PAPER_ID, self::HAL_ID, 0));
    }

    public function testUnavailableTeiIsAFailure(): void
    {
        // HAL did not return the TEI: the caller reports that the HAL fundings could not be updated
        $adapter = $this->givenHalTeiAndRow('', [1 => ['funding' => '{"0":{"fundingStatement":"Kept funding"}}']]);
        Episciences_Hal_TeiCacheManager::setHttpClient(new \GuzzleHttp\Client([
            'handler' => \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler([new \GuzzleHttp\Psr7\Response(200, [], '')])),
        ]));

        $adapter->expects(self::never())->method('delete');
        $adapter->expects(self::never())->method('update');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HAL TEI unavailable for ' . self::HAL_ID);

        Episciences_Paper_Projects_EnrichmentService::enrichHalFundingFromTei(self::PAPER_ID, self::HAL_ID, 0);
    }

    public function testHalRowIsKeptWhenTheTeiHoldsNoDocument(): void
    {
        // an empty search result says nothing about the fundings of the document
        $adapter = $this->givenHalTeiAndRow(
            self::halTei(''),
            [1 => ['funding' => '{"0":{"fundingStatement":"Kept funding"}}']]
        );

        $adapter->expects(self::never())->method('delete');
        $this->expectOutputRegex('/holds no document: HAL fundings not updated/');

        self::assertSame(0, Episciences_Paper_Projects_EnrichmentService::enrichHalFundingFromTei(self::PAPER_ID, self::HAL_ID, 0));
    }
}
