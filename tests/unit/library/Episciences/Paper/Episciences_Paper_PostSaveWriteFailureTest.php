<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper;

use Episciences_Paper_Authors;
use Episciences_Paper_Authors_Repository;
use Episciences_Paper_Citations;
use Episciences_Paper_Citations_Repository;
use Episciences_Paper_Dataset;
use Episciences_Paper_DatasetMetadata;
use Episciences_Paper_DatasetsManager;
use Episciences_Paper_DatasetsMetadataManager;
use Episciences_Paper_File;
use Episciences_Paper_FilesManager;
use Episciences_Paper_Licence;
use Episciences_Paper_LicenceManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Adapter_Exception;
use Zend_Db_Statement_Exception;
use Zend_Db_Table_Abstract;

/**
 * These writes run after the paper is saved (datasets, authors, files, licence, citations):
 * a database failure is reported as a warning (with the method) and the caller goes on, instead of ending the script.
 * Except the authors update, also used interactively, and the files sync: they throw, the post-save callers catch them.
 * The authors, citations, datasets and dataset metadata inserts also have a throwing variant (insertOrFail()),
 * used by the post-save processing.
 */
final class Episciences_Paper_PostSaveWriteFailureTest extends TestCase
{
    private ?Zend_Db_Adapter_Abstract $previousAdapter = null;

    /** @var Zend_Db_Adapter_Abstract&MockObject */
    private Zend_Db_Adapter_Abstract $db;

    protected function setUp(): void
    {
        $adapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$adapter instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }

        $this->previousAdapter = $adapter;
        $this->db = $this->getMockBuilder($adapter::class)
            ->setConstructorArgs([$adapter->getConfig()])
            ->onlyMethods(['query', 'update', 'lastInsertId'])
            ->getMock();
        $this->db->method('query')->willThrowException(new Zend_Db_Adapter_Exception('query failed'));
        Zend_Db_Table_Abstract::setDefaultAdapter($this->db);
    }

    protected function tearDown(): void
    {
        if ($this->previousAdapter !== null) {
            Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
        }
    }

    public function testMetadataInsertFailureIsAWarningAndReturnsNoId(): void
    {
        // An earlier insert of the connection: its id must not be returned for the failed one
        $this->db->method('lastInsertId')->willReturn('77');
        $metadata = new Episciences_Paper_DatasetMetadata(['metatext' => '{}']);

        $errors = $this->collectErrors(static fn() => Episciences_Paper_DatasetsMetadataManager::insert([$metadata]), $id);

        // null, not 0: stored as id_paper_datasets_meta, 0 would break the foreign key and drop the dataset
        self::assertNull($id);
        self::assertSame([[E_USER_WARNING, 'Episciences_Paper_DatasetsMetadataManager::insert: query failed']], $errors);
    }

    public function testMetadataInsertWithoutMetadataReturnsNoId(): void
    {
        $this->db->method('lastInsertId')->willReturn('77');
        $this->db->expects(self::never())->method('query');

        self::assertNull(Episciences_Paper_DatasetsMetadataManager::insert([]));
    }

    public function testDatasetInsertFailureIsAWarning(): void
    {
        $errors = $this->collectErrors(static fn() => Episciences_Paper_DatasetsManager::insert([self::dataset()]), $affectedRows);

        self::assertSame(0, $affectedRows);
        self::assertSame([[E_USER_WARNING, 'Episciences_Paper_DatasetsManager::insert: query failed']], $errors);
    }

    public function testDatasetInsertOrFailThrowsTheFailure(): void
    {
        // Used after the save of a paper: the failure is reported to the author (Episciences_Submit::processDatasets())
        $this->expectException(Zend_Db_Adapter_Exception::class);
        $this->expectExceptionMessage('query failed');

        Episciences_Paper_DatasetsManager::insertOrFail([self::dataset()]);
    }

    public function testMetadataInsertOrFailThrowsTheFailure(): void
    {
        $this->expectException(Zend_Db_Adapter_Exception::class);
        $this->expectExceptionMessage('query failed');

        Episciences_Paper_DatasetsMetadataManager::insertOrFail([new Episciences_Paper_DatasetMetadata(['metatext' => '{}'])]);
    }

    public function testRelationUpdateFailureIsAWarning(): void
    {
        $this->failUpdates();
        $errors = $this->collectErrors(
            static fn() => Episciences_Paper_DatasetsManager::updateRelationAndTypeById(self::dataset()),
            $affectedRows
        );

        self::assertSame(0, $affectedRows);
        self::assertSame([[E_USER_WARNING, 'Episciences_Paper_DatasetsManager::updateRelationAndTypeById: update failed']], $errors);
    }

    public function testAuthorsInsertFailureIsAWarning(): void
    {
        $authors = new Episciences_Paper_Authors(['authors' => '[]', 'paperId' => 1]);

        $errors = $this->collectErrors(static fn() => Episciences_Paper_Authors_Repository::insert([$authors]), $affectedRows);

        self::assertSame(0, $affectedRows);
        self::assertSame([[E_USER_WARNING, 'Episciences_Paper_Authors_Repository::insert: query failed']], $errors);
    }

    public function testAuthorsInsertOrFailThrowsTheFailure(): void
    {
        // Used after the save of a paper: the failure is reported to the author (Episciences_Submit::processSavedPaper())
        $authors = new Episciences_Paper_Authors(['authors' => '[]', 'paperId' => 1]);

        $this->expectException(Zend_Db_Adapter_Exception::class);
        $this->expectExceptionMessage('query failed');

        Episciences_Paper_Authors_Repository::insertOrFail([$authors]);
    }

    public function testAuthorsVerifyExistOrInsertOrFailThrowsTheFailure(): void
    {
        // Used by the update of the record of a paper: the failure is reported (Episciences_PapersManager::updateRecordData())
        $this->expectException(Zend_Db_Adapter_Exception::class);

        Episciences_Paper_Authors_Repository::verifyExistOrInsertOrFail(1, 987654);
    }

    public function testAuthorsVerifyExistOrInsertFailureIsAWarning(): void
    {
        $errors = $this->collectErrors(static fn() => Episciences_Paper_Authors_Repository::verifyExistOrInsert(1, 987655), $result);

        self::assertNull($result);
        self::assertCount(1, $errors);
        self::assertSame(E_USER_WARNING, $errors[0][0]);
        self::assertStringStartsWith('Episciences_Paper_Authors_Repository::verifyExistOrInsert: ', $errors[0][1]);
    }

    public function testAuthorsUpdateFailureIsThrown(): void
    {
        // Not 0: the ORCID and affiliation forms would read it as "nothing changed" and report a success
        $this->failUpdates();
        $authors = new Episciences_Paper_Authors(['authors' => '[]', 'paperId' => 1]);

        $this->expectException(Zend_Db_Statement_Exception::class);
        $this->expectExceptionMessage('update failed');

        Episciences_Paper_Authors_Repository::update($authors);
    }

    public function testFilesOfAnUnexpectedShapeRollBackTheTransaction(): void
    {
        // A size that is not a number: Episciences_Paper_File::setFileSize(int) throws a TypeError, not an Exception
        $db = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['beginTransaction', 'rollBack', 'commit', 'query'])
            ->getMock();
        $db->expects(self::once())->method('beginTransaction')->willReturnSelf();
        $db->expects(self::once())->method('rollBack')->willReturnSelf();
        $db->expects(self::never())->method('commit');
        $db->expects(self::never())->method('query');
        Zend_Db_Table_Abstract::setDefaultAdapter($db);

        $files = [[
            'doc_id' => 1,
            'source' => 1,
            'file_name' => 'article.pdf',
            'file_size' => 'not a size',
            'checksum' => 'abc',
            'checksum_type' => 'md5',
            'self_link' => 'https://repository.test/article.pdf',
        ]];

        // Thrown, not swallowed: the files hook fails, so the submission reports that the files are missing
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Files synchronization error [rollback]');

        Episciences_Paper_FilesManager::insert($files);
    }

    public function testCommitFailureIsThrownWithItsOwnMessage(): void
    {
        // commit() failed: no transaction is left, the rollback fails too and must not hide the commit error
        $db = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['beginTransaction', 'rollBack', 'commit', 'fetchAssoc', 'query'])
            ->getMock();
        $db->method('beginTransaction')->willReturnSelf();
        // No stored file: the file is inserted
        $db->method('fetchAssoc')->willReturn([]);
        $statement = $this->createStub(\Zend_Db_Statement_Interface::class);
        $statement->method('rowCount')->willReturn(1);
        $db->method('query')->willReturn($statement);
        $db->expects(self::once())->method('commit')->willThrowException(new Zend_Db_Adapter_Exception('commit failed'));
        $db->expects(self::once())->method('rollBack')->willThrowException(new \PDOException('There is no active transaction'));
        Zend_Db_Table_Abstract::setDefaultAdapter($db);

        $files = [[
            'doc_id' => 1,
            'source' => 1,
            'file_name' => 'article.pdf',
            'file_size' => 12,
            'file_type' => 'pdf',
            'checksum' => 'abc',
            'checksum_type' => 'md5',
            'self_link' => 'https://repository.test/article.pdf',
        ]];

        $thrown = null;
        $errors = $this->collectErrors(static function () use ($files, &$thrown): void {
            try {
                Episciences_Paper_FilesManager::insert($files);
            } catch (RuntimeException $e) {
                $thrown = $e;
            }
        }, $unused);

        self::assertInstanceOf(RuntimeException::class, $thrown);
        self::assertStringContainsString('commit failed', $thrown->getMessage());
        self::assertSame([[E_USER_WARNING, 'Files synchronization rollback failed: There is no active transaction']], $errors);
    }

    public function testFileSaveFailureIsAWarning(): void
    {
        $file = new Episciences_Paper_File([
            'docId' => 1,
            'fileName' => 'article.pdf',
            'checksum' => 'abc',
            'checksumType' => 'md5',
            'selfLink' => 'https://repository.test/article.pdf',
            'fileSize' => 1,
            'fileType' => 'pdf',
        ]);
        $file->setSource(1);

        $errors = $this->collectErrors(static fn() => $file->save(), $affectedRows);

        self::assertSame(0, $affectedRows);
        self::assertSame([[E_USER_WARNING, 'Episciences_Paper_File::save: query failed']], $errors);
    }

    public function testLicenceInsertFailureIsAWarning(): void
    {
        $licence = new Episciences_Paper_Licence([
            'licence' => 'https://creativecommons.org/licenses/by/4.0',
            'docid' => 1,
            'sourceId' => 1,
        ]);

        $errors = $this->collectErrors(static fn() => Episciences_Paper_LicenceManager::insert([$licence]), $affectedRows);

        self::assertSame(0, $affectedRows);
        self::assertSame([[E_USER_WARNING, 'Episciences_Paper_LicenceManager::insert: query failed']], $errors);
    }

    /**
     * Used by the licence step of a metadata update (Episciences_PapersManager::updateRecordData()), which reports it
     */
    public function testLicenceInsertOrFailThrowsTheFailure(): void
    {
        $licence = new Episciences_Paper_Licence([
            'licence' => 'https://creativecommons.org/licenses/by/4.0',
            'docid' => 1,
            'sourceId' => 1,
        ]);

        $this->expectException(Zend_Db_Adapter_Exception::class);
        $this->expectExceptionMessage('query failed');

        Episciences_Paper_LicenceManager::insertOrFail([$licence]);
    }

    public function testCitationsInsertFailureIsAWarning(): void
    {
        $citation = new Episciences_Paper_Citations();
        $citation->setCitation('[]')->setDocId(1)->setSourceId(1);

        $errors = $this->collectErrors(static fn() => Episciences_Paper_Citations_Repository::insert([$citation]), $affectedRows);

        self::assertSame(0, $affectedRows);
        self::assertSame([[E_USER_WARNING, 'Episciences_Paper_Citations_Repository::insert: query failed']], $errors);
    }

    public function testCitationsInsertOrFailThrowsTheFailure(): void
    {
        // Not 0: an upsert also returns 0 when nothing changed
        $citation = new Episciences_Paper_Citations();
        $citation->setCitation('[]')->setDocId(1)->setSourceId(1);

        $this->expectException(Zend_Db_Adapter_Exception::class);
        $this->expectExceptionMessage('query failed');

        Episciences_Paper_Citations_Repository::insertOrFail([$citation]);
    }

    /**
     * A failing UPDATE statement throws Zend_Db_Statement_Exception (Zend_Db_Statement_Pdo wraps the PDOException)
     */
    private function failUpdates(): void
    {
        $this->db->method('update')->willThrowException(new Zend_Db_Statement_Exception('update failed'));
    }

    private static function dataset(): Episciences_Paper_Dataset
    {
        $dataset = new Episciences_Paper_Dataset([
            'docId' => 1,
            'code' => 'dataset',
            'name' => 'doi',
            'value' => '10.1234/abc',
            'link' => 'doi',
            'sourceId' => 1,
        ]);
        $dataset->setId(1);

        return $dataset;
    }

    /**
     * @param mixed $result receives the value returned by $run
     * @return list<array{0: int, 1: string}> level and message of each error triggered by $run
     */
    private function collectErrors(callable $run, mixed &$result): array
    {
        $errors = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$errors): bool {
            $errors[] = [$errno, $errstr];
            return true;
        });

        try {
            $result = $run();
        } finally {
            restore_error_handler();
        }

        return $errors;
    }
}
