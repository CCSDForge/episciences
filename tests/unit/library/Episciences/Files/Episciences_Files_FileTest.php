<?php

namespace unit\library\Episciences\Files;

use Episciences\Files\File;
use Episciences\Files\FileManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences\Files\File.
 *
 * Pure-logic: constructor, setters/getters, toArray, setUploadedDate behaviour.
 * DB-dependent methods (save) excluded.
 *
 * @covers \Episciences\Files\File
 */
class Episciences_Files_FileTest extends TestCase
{
    // =========================================================================
    // Default id is null
    // =========================================================================

    public function testDefaultIdIsNull(): void
    {
        $file = new File();
        self::assertNull($file->getId());
    }

    // =========================================================================
    // setId / getId
    // =========================================================================

    public function testSetAndGetId(): void
    {
        $file = new File();
        $file->setId(42);
        self::assertSame(42, $file->getId());
    }

    public function testSetIdReturnsFluent(): void
    {
        $file = new File();
        $result = $file->setId(1);
        self::assertInstanceOf(File::class, $result);
    }

    public function testSetIdWithNull(): void
    {
        $file = new File();
        $file->setId(10);
        $file->setId(null);
        self::assertNull($file->getId());
    }

    // =========================================================================
    // setDocid / getDocid
    // =========================================================================

    public function testSetAndGetDocid(): void
    {
        $file = new File();
        $file->setDocid(99);
        self::assertSame(99, $file->getDocid());
    }

    public function testSetDocidReturnsFluent(): void
    {
        $file = new File();
        $result = $file->setDocid(1);
        self::assertInstanceOf(File::class, $result);
    }

    // =========================================================================
    // setName / getName
    // =========================================================================

    public function testSetAndGetName(): void
    {
        $file = new File();
        $file->setName('paper.pdf');
        self::assertSame('paper.pdf', $file->getName());
    }

    public function testSetNameReturnsFluent(): void
    {
        $file = new File();
        self::assertInstanceOf(File::class, $file->setName('test.pdf'));
    }

    // =========================================================================
    // setExtension / getExtension
    // =========================================================================

    public function testSetAndGetExtension(): void
    {
        $file = new File();
        $file->setExtension('pdf');
        self::assertSame('pdf', $file->getExtension());
    }

    // =========================================================================
    // setTypeMime / getTypeMime
    // =========================================================================

    public function testSetAndGetTypeMime(): void
    {
        $file = new File();
        $file->setTypeMime('application/pdf');
        self::assertSame('application/pdf', $file->getTypeMime());
    }

    // =========================================================================
    // setSize / getSize
    // =========================================================================

    public function testSetAndGetSize(): void
    {
        $file = new File();
        $file->setSize(1024);
        self::assertSame(1024, $file->getSize());
    }

    // =========================================================================
    // setMd5 / getMd5
    // =========================================================================

    public function testSetAndGetMd5(): void
    {
        $file = new File();
        $file->setMd5('d41d8cd98f00b204e9800998ecf8427e');
        self::assertSame('d41d8cd98f00b204e9800998ecf8427e', $file->getMd5());
    }

    // =========================================================================
    // setSource / getSource
    // =========================================================================

    public function testSetAndGetSource(): void
    {
        $file = new File();
        $file->setSource('hal');
        self::assertSame('hal', $file->getSource());
    }

    public function testSetSourceDefaultIsFileManagerConstant(): void
    {
        $file = new File();
        $file->setSource();
        self::assertSame(FileManager::DD_SOURCE, $file->getSource());
    }

    // =========================================================================
    // setUploadedDate / getUploadedDate
    // =========================================================================

    public function testDefaultUploadedDateIsNull(): void
    {
        $file = new File();
        self::assertNull($file->getUploadedDate());
    }

    public function testSetUploadedDateWithExplicitDate(): void
    {
        $file = new File();
        $file->setUploadedDate('2024-06-15 10:30:00');
        self::assertSame('2024-06-15 10:30:00', $file->getUploadedDate());
    }

    public function testSetUploadedDateWithNullGeneratesCurrentTimestamp(): void
    {
        $file = new File();
        $file->setUploadedDate(null);
        // Should auto-generate a date in Y-m-d H:i:s format
        $date = $file->getUploadedDate();
        self::assertNotNull($date);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date);
    }

    public function testSetUploadedDateWithEmptyStringGeneratesCurrentTimestamp(): void
    {
        $file = new File();
        $file->setUploadedDate('');
        $date = $file->getUploadedDate();
        self::assertNotNull($date);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date);
    }

    // =========================================================================
    // toArray
    // =========================================================================

    public function testToArrayContainsNineKeys(): void
    {
        $file = $this->buildFullFile();
        $array = $file->toArray();
        self::assertCount(9, $array);
    }

    public function testToArrayHasExpectedKeys(): void
    {
        $file = $this->buildFullFile();
        $array = $file->toArray();
        self::assertArrayHasKey('id', $array);
        self::assertArrayHasKey('docid', $array);
        self::assertArrayHasKey('name', $array);
        self::assertArrayHasKey('extension', $array);
        self::assertArrayHasKey('type_mime', $array);
        self::assertArrayHasKey('size', $array);
        self::assertArrayHasKey('md5', $array);
        self::assertArrayHasKey('source', $array);
        self::assertArrayHasKey('uploaded_date', $array);
    }

    public function testToArrayReflectsSetValues(): void
    {
        $file = $this->buildFullFile();
        $array = $file->toArray();

        self::assertSame(7, $array['id']);
        self::assertSame(100, $array['docid']);
        self::assertSame('article.pdf', $array['name']);
        self::assertSame('pdf', $array['extension']);
        self::assertSame('application/pdf', $array['type_mime']);
        self::assertSame(2048, $array['size']);
        self::assertSame('abc123', $array['md5']);
        self::assertSame('dd', $array['source']);
        self::assertSame('2024-01-01 00:00:00', $array['uploaded_date']);
    }

    // =========================================================================
    // setOptions via constructor
    // =========================================================================

    public function testConstructorWithEmptyArrayDoesNotThrow(): void
    {
        $file = new File([]);
        self::assertInstanceOf(File::class, $file);
        self::assertNull($file->getId());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function buildFullFile(): File
    {
        $file = new File();
        $file->setId(7);
        $file->setDocid(100);
        $file->setName('article.pdf');
        $file->setExtension('pdf');
        $file->setTypeMime('application/pdf');
        $file->setSize(2048);
        $file->setMd5('abc123');
        $file->setSource('dd');
        $file->setUploadedDate('2024-01-01 00:00:00');
        return $file;
    }
}
