<?php

declare(strict_types=1);

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Replacing the file of a volume metadata in save(): real files in the journal's upload and public
 * directories, save() stops before any database write when the file is refused.
 *
 * @covers Episciences_Volume_Metadata::save
 */
final class Episciences_Volume_MetadataSaveFileTest extends TestCase
{
    /** Not a real volume: its directory is created and removed by the test */
    private const VID = 987654321;

    /** Smallest valid PNG image */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private string $volumeDir;
    private string $upload;
    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['query', 'insert', 'update', 'delete'])
            ->getMock();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        $this->volumeDir = REVIEW_PUBLIC_PATH . 'volumes/' . self::VID . '/';
        if (!is_dir($this->volumeDir)) {
            mkdir($this->volumeDir, 0777, true);
        }
        file_put_contents($this->volumeDir . 'previous.png', base64_decode(self::PNG));

        if (!is_dir(REVIEW_TMP_PATH)) {
            mkdir(REVIEW_TMP_PATH, 0777, true);
        }
        $this->upload = REVIEW_TMP_PATH . 'volume-metadata-save-test-' . getmypid();
        file_put_contents($this->upload, base64_decode(self::PNG));
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);

        foreach (glob($this->volumeDir . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->volumeDir);

        if (is_file($this->upload)) {
            unlink($this->upload);
        }
    }

    public function testNewFileWhoseNameIsNotBareKeepsThePreviousFile(): void
    {
        $meta = new Episciences_Volume_Metadata();
        $meta->setVid(self::VID);
        $meta->setFile('previous.png');
        $meta->setTmpfile(['name' => 'cover\\page.png', 'tmp_name' => $this->upload]);
        $this->adapter->expects(self::never())->method('query');
        $this->adapter->expects(self::never())->method('insert');
        $this->adapter->expects(self::never())->method('update');
        $this->adapter->expects(self::never())->method('delete');

        self::assertFalse($meta->save());
        self::assertSame('previous.png', $meta->getFile());
        self::assertFileExists($this->volumeDir . 'previous.png');
        self::assertSame(['previous.png'], array_map('basename', glob($this->volumeDir . '*') ?: []), 'no file is added to the volume');
    }

    public function testFileNameWithADirectoryPartIsDropped(): void
    {
        $meta = new Episciences_Volume_Metadata();
        $meta->setVid(self::VID);
        $meta->setFile('../previous.png');

        self::assertNull($meta->getFile());
    }

    public function testDeleteListOnlyRemovesFilesOfTheJournalUploadsAndOfTheVolume(): void
    {
        $outside = REVIEW_PUBLIC_PATH . 'volumes/volume-metadata-save-test-outside-' . getmypid();
        file_put_contents($outside, 'kept');

        try {
            $meta = new Episciences_Volume_Metadata();
            $meta->setVid(self::VID);
            $meta->setId(42);
            $meta->setDeletelist([
                ['type' => 'tmp_file', 'path' => $outside],
                ['type' => 'current_file', 'name' => '../' . basename($outside)],
                ['type' => 'tmp_file', 'path' => $this->upload],
                ['type' => 'current_file', 'name' => 'previous.png'],
            ]);
            $this->adapter->expects(self::once())->method('update')
                ->with(T_VOLUME_METADATAS, self::anything(), ['ID = ?' => 42, 'VID = ?' => self::VID])
                ->willReturn(1);

            self::assertTrue($meta->save());
            self::assertFileExists($outside, 'a path outside the journal uploads is not removed');
            self::assertFileDoesNotExist($this->upload);
            self::assertFileDoesNotExist($this->volumeDir . 'previous.png');
        } finally {
            if (is_file($outside)) {
                unlink($outside);
            }
        }
    }
}
