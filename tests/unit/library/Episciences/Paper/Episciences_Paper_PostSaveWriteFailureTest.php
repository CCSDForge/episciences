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
use Episciences_Paper_Licence;
use Episciences_Paper_LicenceManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Adapter_Exception;
use Zend_Db_Table_Abstract;

/**
 * These writes run after the paper is saved (datasets, authors, files, licence, citations):
 * a database failure is reported as a warning and the caller goes on, instead of ending the script.
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
        $this->db->method('update')->willThrowException(new Zend_Db_Adapter_Exception('update failed'));
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

        self::assertSame(0, $id);
        self::assertSame([[E_USER_WARNING, 'query failed']], $errors);
    }

    public function testDatasetInsertFailureIsAWarning(): void
    {
        $errors = $this->collectErrors(static fn() => Episciences_Paper_DatasetsManager::insert([self::dataset()]), $affectedRows);

        self::assertSame(0, $affectedRows);
        self::assertSame([[E_USER_WARNING, 'query failed']], $errors);
    }

    public function testRelationUpdateFailureIsAWarning(): void
    {
        $errors = $this->collectErrors(
            static fn() => Episciences_Paper_DatasetsManager::updateRelationAndTypeById(self::dataset()),
            $affectedRows
        );

        self::assertSame(0, $affectedRows);
        self::assertSame([[E_USER_WARNING, 'update failed']], $errors);
    }

    public function testUpdateFailureIsAWarning(): void
    {
        $errors = $this->collectErrors(
            static fn() => Episciences_Paper_DatasetsManager::update(self::dataset()),
            $affectedRows
        );

        self::assertSame(0, $affectedRows);
        self::assertSame([[E_USER_WARNING, 'update failed']], $errors);
    }

    public function testAuthorsInsertFailureIsAWarning(): void
    {
        $authors = new Episciences_Paper_Authors(['authors' => '[]', 'paperId' => 1]);

        $errors = $this->collectErrors(static fn() => Episciences_Paper_Authors_Repository::insert([$authors]), $affectedRows);

        self::assertSame(0, $affectedRows);
        self::assertSame([[E_USER_WARNING, 'query failed']], $errors);
    }

    public function testAuthorsUpdateFailureIsAWarning(): void
    {
        $authors = new Episciences_Paper_Authors(['authors' => '[]', 'paperId' => 1]);

        $errors = $this->collectErrors(static fn() => Episciences_Paper_Authors_Repository::update($authors), $affectedRows);

        self::assertSame(0, $affectedRows);
        self::assertSame([[E_USER_WARNING, 'update failed']], $errors);
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
        self::assertSame([[E_USER_WARNING, 'query failed']], $errors);
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
        self::assertSame([[E_USER_WARNING, 'query failed']], $errors);
    }

    public function testCitationsInsertFailureIsAWarning(): void
    {
        $citation = new Episciences_Paper_Citations();
        $citation->setCitation('[]')->setDocId(1)->setSourceId(1);

        $errors = $this->collectErrors(static fn() => Episciences_Paper_Citations_Repository::insert([$citation]), $affectedRows);

        self::assertSame(0, $affectedRows);
        self::assertSame([[E_USER_WARNING, 'query failed']], $errors);
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
