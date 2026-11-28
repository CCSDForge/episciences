<?php

declare(strict_types=1);

namespace unit\scripts;

use Episciences\Api\ScholexplorerApiClient;
use GetLinkDataCommand;
use GuzzleHttp\Client;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionMethod;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Statement_Interface;
use Zend_Db_Table_Abstract;

require_once __DIR__ . '/../../../scripts/GetLinkDataCommand.php';

/**
 * GetLinkDataCommand stores the Scholix relation of a link in the linked data vocabulary.
 */
final class GetLinkDataCommandRelationshipTest extends TestCase
{
    private ?Zend_Db_Adapter_Abstract $previousAdapter = null;

    /** @var list<string> INSERT statements into T_PAPER_DATASETS */
    private array $datasetInserts = [];

    protected function setUp(): void
    {
        $adapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$adapter instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }

        $this->previousAdapter = $adapter;

        /** @var Zend_Db_Adapter_Abstract&MockObject $db */
        $db = $this->getMockBuilder($adapter::class)
            ->setConstructorArgs([$adapter->getConfig()])
            ->onlyMethods(['query', 'lastInsertId'])
            ->getMock();
        $db->method('lastInsertId')->willReturn('12');
        $db->method('query')->willReturnCallback(function (mixed $sql): Zend_Db_Statement_Interface {
            $sql = (string)$sql;

            if (str_starts_with($sql, 'INSERT') && str_contains($sql, '`' . T_PAPER_DATASETS . '`')) {
                $this->datasetInserts[] = $sql;
            }

            $statement = $this->createMock(Zend_Db_Statement_Interface::class);
            $statement->method('rowCount')->willReturn(1);

            return $statement;
        });

        Zend_Db_Table_Abstract::setDefaultAdapter($db);
    }

    protected function tearDown(): void
    {
        if ($this->previousAdapter !== null) {
            Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function scholixRelationProvider(): iterable
    {
        yield 'related to' => ['IsRelatedTo', 'isRelatedMaterial'];
        yield 'cites' => ['Cites', 'references'];
        yield 'reviewed by' => ['IsReviewedBy', 'hasReview'];
        yield 'supplemented by' => ['IsSupplementedBy', 'isSupplementedBy'];
        yield 'unknown' => ['IsWhatever', 'isRelatedMaterial'];
    }

    #[DataProvider('scholixRelationProvider')]
    public function testTheScholixRelationIsStoredInTheLinkedDataVocabulary(string $scholixRelation, string $stored): void
    {
        $this->insertLink([
            'identifier' => '21.11115/0000-0016-7FC9-8',
            'scheme' => 'handle',
            'link' => 'https://hdl.handle.net/21.11115/0000-0016-7FC9-8',
            'relationship' => $scholixRelation,
            'type' => 'dataset',
            'raw' => [],
        ]);

        self::assertCount(1, $this->datasetInserts);
        self::assertStringContainsString("'" . $stored . "'", $this->datasetInserts[0]);
    }

    /**
     * @param array<string, mixed> $linkItem
     */
    private function insertLink(array $linkItem): void
    {
        $client = new ScholexplorerApiClient(new Client(), new ArrayAdapter(0, false), new NullLogger());
        $logger = new Logger('test', [new NullHandler()]);

        (new ReflectionMethod(GetLinkDataCommand::class, 'insertLink'))
            ->invoke(new GetLinkDataCommand(), $client, 1234, $linkItem, $logger, '10.1/test');
    }
}
