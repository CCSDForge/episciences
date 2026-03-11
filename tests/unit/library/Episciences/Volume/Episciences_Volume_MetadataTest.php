<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Volume_Metadata.
 *
 * Pure-logic: constant, setters/getters, hasFile/isPDF/isPicture with no file,
 * getNameKey/getContentKey, hasContent, save() no-vid guard, setOptions priority.
 * DB-dependent methods (save full path, loadTranslations, loadTitles, loadContents)
 * and filesystem-dependent methods (getFileType, getFilePath, getFileUrl with file)
 * are excluded.
 *
 * @covers Episciences_Volume_Metadata
 */
class Episciences_Volume_MetadataTest extends TestCase
{
    private Episciences_Volume_Metadata $meta;

    protected function setUp(): void
    {
        $this->meta = new Episciences_Volume_Metadata();
    }

    // =========================================================================
    // Constant
    // =========================================================================

    public function testTranslationFileConstant(): void
    {
        self::assertSame('volumes.php', Episciences_Volume_Metadata::TRANSLATION_FILE);
    }

    // =========================================================================
    // id setter/getter
    // =========================================================================

    public function testSetAndGetId(): void
    {
        $this->meta->setId(5);
        self::assertSame(5, $this->meta->getId());
    }

    public function testSetIdReturnsFluent(): void
    {
        self::assertInstanceOf(Episciences_Volume_Metadata::class, $this->meta->setId(1));
    }

    // =========================================================================
    // vid setter/getter — casts to int
    // =========================================================================

    public function testSetAndGetVid(): void
    {
        $this->meta->setVid(3);
        self::assertSame(3, $this->meta->getVid());
    }

    public function testSetVidCastsToInt(): void
    {
        $this->meta->setVid('7');
        self::assertSame(7, $this->meta->getVid());
    }

    public function testSetVidReturnsFluent(): void
    {
        self::assertInstanceOf(Episciences_Volume_Metadata::class, $this->meta->setVid(1));
    }

    // =========================================================================
    // file setter/getter
    // =========================================================================

    public function testDefaultFileIsNull(): void
    {
        self::assertNull($this->meta->getFile());
    }

    public function testSetAndGetFile(): void
    {
        $this->meta->setFile('cover.jpg');
        self::assertSame('cover.jpg', $this->meta->getFile());
    }

    public function testSetFileReturnsFluent(): void
    {
        self::assertInstanceOf(Episciences_Volume_Metadata::class, $this->meta->setFile('f.pdf'));
    }

    // =========================================================================
    // hasFile — returns 1 or 0 (not bool)
    // =========================================================================

    public function testHasFileReturnsFalsyWhenNoFile(): void
    {
        self::assertSame(0, $this->meta->hasFile());
    }

    public function testHasFileReturnsTruthyWhenFileSet(): void
    {
        $this->meta->setFile('cover.jpg');
        self::assertSame(1, $this->meta->hasFile());
    }

    // =========================================================================
    // getFilePath / getFileUrl — null when no file
    // =========================================================================

    public function testGetFilePathReturnsNullWhenNoFile(): void
    {
        self::assertNull($this->meta->getFilePath());
    }

    public function testGetFileUrlReturnsNullWhenNoFile(): void
    {
        self::assertNull($this->meta->getFileUrl());
    }

    // =========================================================================
    // isPDF / isPicture — false when no file
    // =========================================================================

    public function testIsPdfReturnsFalseWhenNoFile(): void
    {
        self::assertFalse($this->meta->isPDF());
    }

    public function testIsPictureReturnsFalseWhenNoFile(): void
    {
        self::assertFalse($this->meta->isPicture());
    }

    // =========================================================================
    // getNameKey / getContentKey
    // =========================================================================

    public function testGetNameKey(): void
    {
        $this->meta->setVid(2);
        $this->meta->setId(10);
        self::assertSame('volume_2_md_10_name', $this->meta->getNameKey());
    }

    public function testGetContentKey(): void
    {
        $this->meta->setVid(4);
        $this->meta->setId(3);
        self::assertSame('volume_4_md_3_content', $this->meta->getContentKey());
    }

    // =========================================================================
    // title setter/getter (array)
    // =========================================================================

    public function testDefaultTitlesIsNull(): void
    {
        self::assertNull($this->meta->getTitles());
    }

    public function testSetAndGetTitles(): void
    {
        $this->meta->setTitle(['en' => 'Introduction', 'fr' => 'Introduction']);
        self::assertSame(['en' => 'Introduction', 'fr' => 'Introduction'], $this->meta->getTitles());
    }

    public function testGetTitleByLang(): void
    {
        $this->meta->setTitle(['en' => 'About', 'fr' => 'À propos']);
        self::assertSame('About', $this->meta->getTitle('en'));
        self::assertSame('À propos', $this->meta->getTitle('fr'));
    }

    public function testGetTitleForMissingLangReturnsNull(): void
    {
        $this->meta->setTitle(['en' => 'About']);
        self::assertNull($this->meta->getTitle('de'));
    }

    public function testSetTitleWithNullSetsNull(): void
    {
        $this->meta->setTitle(['en' => 'X']);
        $this->meta->setTitle(null);
        self::assertNull($this->meta->getTitles());
    }

    public function testSetTitleReturnsFluent(): void
    {
        self::assertInstanceOf(Episciences_Volume_Metadata::class, $this->meta->setTitle(null));
    }

    // =========================================================================
    // content setter/getter (array)
    // =========================================================================

    public function testDefaultContentsIsNull(): void
    {
        self::assertNull($this->meta->getContents());
    }

    public function testSetAndGetContents(): void
    {
        $this->meta->setContent(['en' => 'Body text']);
        self::assertSame(['en' => 'Body text'], $this->meta->getContents());
    }

    public function testGetContentByLang(): void
    {
        $this->meta->setContent(['en' => 'Hello', 'fr' => 'Bonjour']);
        self::assertSame('Hello', $this->meta->getContent('en'));
        self::assertSame('Bonjour', $this->meta->getContent('fr'));
    }

    public function testGetContentForMissingLangReturnsNull(): void
    {
        $this->meta->setContent(['en' => 'Hello']);
        self::assertNull($this->meta->getContent('de'));
    }

    public function testSetContentReturnsFluent(): void
    {
        self::assertInstanceOf(Episciences_Volume_Metadata::class, $this->meta->setContent(null));
    }

    // =========================================================================
    // hasContent
    // =========================================================================

    public function testHasContentReturnsFalseWhenNoContent(): void
    {
        $this->meta->setContent([]);
        self::assertFalse($this->meta->hasContent());
    }

    public function testHasContentReturnsFalseWhenAllValuesEmpty(): void
    {
        $this->meta->setContent(['en' => '', 'fr' => '']);
        self::assertFalse($this->meta->hasContent());
    }

    public function testHasContentReturnsTrueWhenSomeContentExists(): void
    {
        $this->meta->setContent(['en' => 'Text', 'fr' => '']);
        self::assertTrue($this->meta->hasContent());
    }

    // =========================================================================
    // position setter/getter
    // =========================================================================

    public function testSetAndGetPosition(): void
    {
        $this->meta->setPosition(3);
        self::assertSame(3, $this->meta->getPosition());
    }

    public function testSetPositionReturnsFluent(): void
    {
        self::assertInstanceOf(Episciences_Volume_Metadata::class, $this->meta->setPosition(1));
    }

    // =========================================================================
    // tmpfile setter/getter
    // =========================================================================

    public function testSetAndGetTmpfile(): void
    {
        $this->meta->setTmpfile(['name' => 'upload.pdf', 'tmp_name' => '/tmp/abc']);
        self::assertSame(['name' => 'upload.pdf', 'tmp_name' => '/tmp/abc'], $this->meta->getTmpfile());
    }

    // =========================================================================
    // deletelist setter/getter
    // =========================================================================

    public function testSetAndGetDeletelist(): void
    {
        $list = [['type' => 'file', 'name' => 'old.pdf']];
        $this->meta->setDeletelist($list);
        self::assertSame($list, $this->meta->getDeletelist());
    }

    // =========================================================================
    // dateCreation setter/getter
    // =========================================================================

    public function testDefaultDateCreationIsNull(): void
    {
        self::assertNull($this->meta->getDateCreation());
    }

    public function testSetAndGetDateCreation(): void
    {
        $this->meta->setDateCreation('2024-06-01');
        self::assertSame('2024-06-01', $this->meta->getDateCreation());
    }

    public function testSetDateCreationWithNullResetsToNull(): void
    {
        $this->meta->setDateCreation('2024-01-01');
        $this->meta->setDateCreation(null);
        self::assertNull($this->meta->getDateCreation());
    }

    public function testSetDateCreationReturnsFluent(): void
    {
        self::assertInstanceOf(Episciences_Volume_Metadata::class, $this->meta->setDateCreation('2024-01-01'));
    }

    // =========================================================================
    // save() — returns false when no VID (pure-logic guard, no filesystem)
    // =========================================================================

    public function testSaveReturnsFalseWhenNoVid(): void
    {
        // No vid set → save() returns false immediately without touching DB/filesystem
        self::assertFalse($this->meta->save());
    }

    // =========================================================================
    // setOptions — priority (vid, file, tmpfile executed first)
    // =========================================================================

    public function testSetOptionsPriorityKeyVidAppliedFirst(): void
    {
        $meta = new Episciences_Volume_Metadata([
            'id'  => 9,
            'vid' => 5,
        ]);
        self::assertSame(5, $meta->getVid());
        self::assertSame(9, $meta->getId());
    }

    public function testConstructorWithEmptyArrayDoesNotThrow(): void
    {
        $meta = new Episciences_Volume_Metadata([]);
        self::assertInstanceOf(Episciences_Volume_Metadata::class, $meta);
    }
}
