<?php

declare(strict_types=1);

namespace unit\library\Episciences\Submit;

use Episciences_Paper;
use Episciences_Repositories;
use Episciences_Submit;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Statement_Interface;
use Zend_Db_Table_Abstract;

/**
 * Episciences_Submit::datasetsProcessing() for a HAL paper: the linked data declared in HAL are read
 * from the TEI and stored with their relation type.
 */
final class HalTeiLinkedDataTest extends TestCase
{
    private const DOCID = 1234;
    /** Fake HAL repository: the label contains "HAL" (the repositories of the test database may differ) */
    private const HAL_REPO_ID = 9997;
    private const HANDLE = 'https://hdl.handle.net/21.11115/0000-0016-7FC9-8';
    private const SWHID = 'swh:1:dir:0000000000000000000000000000000000000001';
    private const OTHER_SWHID = 'swh:1:dir:0000000000000000000000000000000000000002';

    private ?Zend_Db_Adapter_Abstract $previousAdapter = null;

    /** @var list<string> INSERT statements sent to the database */
    private array $inserts = [];

    /** @var array<mixed> */
    private array $previousRepositories = [];

    protected function setUp(): void
    {
        $adapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$adapter instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }

        $this->previousAdapter = $adapter;

        $repositories = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $this->previousRepositories = (array)$repositories->getValue();
        $repositories->setValue(null, [
            self::HAL_REPO_ID => [
                Episciences_Repositories::REPO_LABEL => 'HAL Spy',
                Episciences_Repositories::REPO_TYPE => Episciences_Repositories::TYPE_PAPERS_REPOSITORY,
                Episciences_Repositories::REPO_API_URL => 'https://api.hal.test',
            ],
        ]);

        /** @var Zend_Db_Adapter_Abstract&MockObject $db */
        $db = $this->getMockBuilder($adapter::class)
            ->setConstructorArgs([$adapter->getConfig()])
            ->onlyMethods(['query', 'lastInsertId'])
            ->getMock();
        $db->method('lastInsertId')->willReturn('77');
        $db->method('query')->willReturnCallback(function (mixed $sql): Zend_Db_Statement_Interface {
            $sql = (string)$sql;

            if (str_starts_with($sql, 'INSERT')) {
                $this->inserts[] = $sql;
            }

            // No linked data stored yet (findByValue()), one row per insert
            $statement = $this->createMock(Zend_Db_Statement_Interface::class);
            $statement->method('fetch')->willReturn(false);
            $statement->method('fetchAll')->willReturn([]);
            $statement->method('rowCount')->willReturn(1);

            return $statement;
        });

        Zend_Db_Table_Abstract::setDefaultAdapter($db);
    }

    protected function tearDown(): void
    {
        // Nothing was replaced when setUp() skipped the test
        if ($this->previousAdapter === null) {
            return;
        }

        (new ReflectionProperty(Episciences_Repositories::class, '_repositories'))->setValue(null, $this->previousRepositories);
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    public function testTheRelatedItemsAndTheSwhidOfTheTeiAreStoredWithTheirRelation(): void
    {
        $tei = '<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><body><listBibl><biblFull>'
            . '<editionStmt><edition><idno type="swhid">' . self::SWHID . '</idno></edition></editionStmt>'
            . '<sourceDesc><biblStruct>'
            . '<relatedItem target="' . self::HANDLE . '" type="IsSupplementedBy" subtype="http://purl.org/coar/resource_type/c_ddb1"/>'
            . '</biblStruct></sourceDesc>'
            . '</biblFull></listBibl></body></text></TEI>';

        $affectedRows = Episciences_Submit::datasetsProcessing($this->halPaper(), $this->halClient($tei));

        self::assertSame(2, $affectedRows);
        self::assertCount(2, $this->inserts);
        self::assertStringContainsString('21.11115/0000-0016-7FC9-8', $this->inserts[0]);
        self::assertStringContainsString("'isSupplementedBy'", $this->inserts[0]);
        self::assertStringContainsString(self::SWHID, $this->inserts[1]);
        self::assertStringContainsString("'references'", $this->inserts[1]);
    }

    public function testATeiWithoutLinkedDataStoresNothing(): void
    {
        $tei = '<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><body><listBibl><biblFull/></listBibl></body></text></TEI>';

        self::assertSame(0, Episciences_Submit::datasetsProcessing($this->halPaper(), $this->halClient($tei)));
        self::assertSame([], $this->inserts);
    }

    public function testARelationOutsideTheVocabularyIsStoredAsReferences(): void
    {
        $affectedRows = Episciences_Submit::processDatasets($this->halPaper(), [
            ['identifier' => self::SWHID, 'relation' => 'Is supplemented by <b>'],
            ['identifier' => self::HANDLE, 'relation' => ['not', 'a', 'string']],
        ]);

        self::assertSame(2, $affectedRows);
        self::assertStringContainsString("'references'", $this->inserts[0]);
        self::assertStringNotContainsString('Is supplemented by', $this->inserts[0]);
        self::assertStringContainsString("'references'", $this->inserts[1]);
    }

    /**
     * A DataCite shaped repository (Zenodo) gives DataCite relation types: they are stored in the linked data vocabulary
     */
    public function testTheRelationsOfADataCiteShapedRepositoryAreNormalized(): void
    {
        $affectedRows = Episciences_Submit::processDatasets($this->zenodoPaper(), [
            ['identifier' => self::SWHID, 'relation' => 'IsReviewedBy', 'resource_type' => 'software'],
            ['identifier' => self::HANDLE, 'relation' => 'Cites', 'resource_type' => 'dataset'],
            ['identifier' => self::OTHER_SWHID, 'relation' => 'IsSourceOf', 'resource_type' => ['not', 'a', 'string']],
        ]);

        self::assertSame(3, $affectedRows);
        self::assertStringContainsString("'hasReview'", $this->inserts[0]);
        self::assertStringContainsString("'references'", $this->inserts[1]);
        self::assertStringContainsString("'hasDerivation'", $this->inserts[2]);
    }

    private function zenodoPaper(): Episciences_Paper
    {
        return new Episciences_Paper([
            'docid' => self::DOCID,
            'paperid' => self::DOCID,
            'repoid' => (int)Episciences_Repositories::ZENODO_REPO_ID,
            'identifier' => '1234567',
            'version' => 1,
        ]);
    }

    private function halClient(string $tei): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], $tei)]))]);
    }

    private function halPaper(): Episciences_Paper
    {
        return new Episciences_Paper([
            'docid' => self::DOCID,
            'paperid' => self::DOCID,
            'repoid' => self::HAL_REPO_ID,
            'identifier' => 'hal-01234567',
            'version' => 1,
        ]);
    }
}
