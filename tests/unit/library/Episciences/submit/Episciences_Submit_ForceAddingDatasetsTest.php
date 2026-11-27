<?php

declare(strict_types=1);

namespace unit\library\Episciences\submit;

use Episciences\Api\DoiApiClient;
use Episciences_DoiTools;
use Episciences_Paper;
use Episciences_Paper_Dataset;
use Episciences_Paper_DatasetsManager;
use Episciences_Submit;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Values whose DOI lookup failed are stored as is, each with the relation type
 * it was found with.
 */
final class Episciences_Submit_ForceAddingDatasetsTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $db;

    private ?int $docId = null;

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;

        $this->db->insert(T_PAPERS, [
            'PAPERID' => 1,
            'RVID' => RVID,
            'UID' => 4242,
            'STATUS' => Episciences_Paper::STATUS_SUBMITTED,
            'IDENTIFIER' => 'force-adding-datasets-test',
            'VERSION' => 1,
            'REPOID' => 1,
            'RECORD' => '<record/>',
            'WHEN' => '2020-01-02 03:04:05',
            'SUBMISSION_DATE' => '2020-01-02 03:04:05',
        ]);
        $this->docId = (int)$this->db->lastInsertId();
    }

    protected function tearDown(): void
    {
        Episciences_DoiTools::setClient(null);

        if ($this->docId !== null) {
            $metaIds = $this->db->fetchCol(
                $this->db->select()
                    ->from(T_PAPER_DATASETS, ['id_paper_datasets_meta'])
                    ->where('doc_id = ?', $this->docId)
                    ->where('id_paper_datasets_meta IS NOT NULL')
            );
            $this->db->delete(T_PAPER_DATASETS, ['doc_id = ?' => $this->docId]);
            if ($metaIds !== []) {
                $this->db->delete(T_PAPER_DATASETS_META, ['id IN (?)' => $metaIds]);
            }
            $this->db->delete(T_PAPERS, ['DOCID = ?' => $this->docId]);
        }
    }

    private function paper(): Episciences_Paper
    {
        return (new Episciences_Paper())->setDocid($this->docId)->setRepoid(1);
    }

    public function testKeepsTheRelationOfEachValue(): void
    {
        $inserted = Episciences_Submit::forceAddingDatasets($this->paper(), [
            Episciences_Paper_Dataset::DOI_CODE => [
                ['value' => '10.1234/first', 'relation' => 'IsSupplementTo'],
                ['value' => '10.1234/second', 'relation' => 'References'],
            ],
        ]);

        self::assertSame(2, $inserted);
        self::assertSame('IsSupplementTo', Episciences_Paper_DatasetsManager::findByValue((int)$this->docId, '10.1234/first')?->getRelationship());
        self::assertSame('References', Episciences_Paper_DatasetsManager::findByValue((int)$this->docId, '10.1234/second')?->getRelationship());
    }

    public function testUnknownCodeIsStoredWithAUrlLink(): void
    {
        $inserted = Episciences_Submit::forceAddingDatasets($this->paper(), [
            Episciences_Paper_Dataset::URL_CODE => [
                ['value' => 'https://example.org/data', 'relation' => 'References'],
            ],
        ]);

        self::assertSame(1, $inserted);
        self::assertSame(Episciences_Paper_Dataset::URL_CODE, Episciences_Paper_DatasetsManager::findByValue((int)$this->docId, 'https://example.org/data')?->getLink());
    }

    public function testUnresolvedDoiUrlIsStoredAsABareDoi(): void
    {
        $this->stubDoiResolver('');

        $inserted = Episciences_Submit::processDatasets($this->paper(), [['https://doi.org/10.1234/unresolved']]);

        self::assertSame(1, $inserted);
        self::assertNull(Episciences_Paper_DatasetsManager::findByValue((int)$this->docId, 'https://doi.org/10.1234/unresolved'));
        $dataset = Episciences_Paper_DatasetsManager::findByValue((int)$this->docId, '10.1234/unresolved');
        self::assertNotNull($dataset);
        self::assertSame(Episciences_Paper_Dataset::DOI_CODE, $dataset->getCode());
        self::assertSame(Episciences_Paper_Dataset::DOI_CODE, $dataset->getLink());
    }

    public function testResolvedDoiUrlIsStoredAsABareDoi(): void
    {
        $this->stubDoiResolver('{"type":"dataset"}');

        $inserted = Episciences_Submit::processDatasets($this->paper(), [['https://dx.doi.org/10.1234/resolved']]);

        self::assertSame(1, $inserted);
        $dataset = Episciences_Paper_DatasetsManager::findByValue((int)$this->docId, '10.1234/resolved');
        self::assertNotNull($dataset);
        self::assertSame(Episciences_Paper_Dataset::DOI_CODE, $dataset->getName());
        self::assertSame(Episciences_Paper_Dataset::DOI_CODE, $dataset->getLink());
    }

    public function testDoiUrlOfAnAlreadyStoredDoiIsSkipped(): void
    {
        $this->stubDoiResolver('');
        Episciences_Submit::processDatasets($this->paper(), [['10.1234/twice']]);

        $inserted = Episciences_Submit::processDatasets($this->paper(), [['https://doi.org/10.1234/twice']]);

        self::assertSame(0, $inserted);
    }

    public function testHttpDoiUrlIsStoredAsABareDoiWhateverTheCase(): void
    {
        $this->stubDoiResolver('');

        $inserted = Episciences_Submit::processDatasets($this->paper(), [['http://DOI.ORG/10.1234/upper']]);

        self::assertSame(1, $inserted);
        self::assertSame(Episciences_Paper_Dataset::DOI_CODE, Episciences_Paper_DatasetsManager::findByValue((int)$this->docId, '10.1234/upper')?->getLink());
    }

    public function testUrlOfAnotherHostIsNotTakenForADoi(): void
    {
        $client = $this->createMock(DoiApiClient::class);
        $client->expects(self::never())->method('fetchCsl');
        Episciences_DoiTools::setClient($client);

        Episciences_Submit::processDatasets($this->paper(), [['https://dx-doi.org/10.1234/elsewhere']]);

        self::assertNull(Episciences_Paper_DatasetsManager::findByValue((int)$this->docId, '10.1234/elsewhere'));
        self::assertNotSame(Episciences_Paper_Dataset::DOI_CODE, Episciences_Paper_DatasetsManager::findByValue((int)$this->docId, 'https://dx-doi.org/10.1234/elsewhere')?->getLink());
    }

    private function stubDoiResolver(string $csl): void
    {
        $client = $this->createMock(DoiApiClient::class);
        $client->method('fetchCsl')->willReturn($csl);
        Episciences_DoiTools::setClient($client);
    }
}
