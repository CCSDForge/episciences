<?php

namespace unit\library\Ccsd;

use Ccsd_File;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd_File
 *
 * Only pure static methods are tested here (no filesystem I/O, no GD/Imagick).
 * Methods that require real files (getSize, getMimeType, isWellFormedXmlFile,
 * convertImg, compile, pdf2pdfa) are excluded.
 */
class Ccsd_FileTest extends TestCase
{
    // ------------------------------------------------------------------
    // convertFileSize
    // ------------------------------------------------------------------

    public function testConvertFileSizeBytes(): void
    {
        $this->assertSame('0 B', Ccsd_File::convertFileSize(0));
        $this->assertSame('500 B', Ccsd_File::convertFileSize(500));
        $this->assertSame('1023 B', Ccsd_File::convertFileSize(1023));
    }

    public function testConvertFileSizeKilobytes(): void
    {
        $this->assertSame('1 Ko', Ccsd_File::convertFileSize(1024));
        $this->assertSame('2 Ko', Ccsd_File::convertFileSize(2048));
        $this->assertSame('1.5 Ko', Ccsd_File::convertFileSize(1536));
    }

    public function testConvertFileSizeMegabytes(): void
    {
        $this->assertSame('1 Mo', Ccsd_File::convertFileSize(1048576));
        $this->assertSame('2 Mo', Ccsd_File::convertFileSize(2097152));
    }

    public function testConvertFileSizeGigabytes(): void
    {
        $this->assertSame('1 Go', Ccsd_File::convertFileSize(1073741824));
    }

    public function testConvertFileSizeTerabytes(): void
    {
        $this->assertSame('1 To', Ccsd_File::convertFileSize(1099511627776));
    }

    // ------------------------------------------------------------------
    // getExtension
    // ------------------------------------------------------------------

    public function testGetExtensionLowercase(): void
    {
        $this->assertSame('jpg', Ccsd_File::getExtension('photo.jpg'));
    }

    public function testGetExtensionUppercaseNormalized(): void
    {
        $this->assertSame('jpg', Ccsd_File::getExtension('photo.JPG'));
    }

    public function testGetExtensionMixed(): void
    {
        $this->assertSame('pdf', Ccsd_File::getExtension('document.PDF'));
    }

    public function testGetExtensionNoExtensionReturnsEmpty(): void
    {
        $this->assertSame('', Ccsd_File::getExtension('README'));
    }

    public function testGetExtensionWithPath(): void
    {
        $this->assertSame('txt', Ccsd_File::getExtension('/some/path/file.txt'));
    }

    // ------------------------------------------------------------------
    // getDirectory
    // ------------------------------------------------------------------

    public function testGetDirectoryWithPath(): void
    {
        $this->assertSame('/foo/bar', Ccsd_File::getDirectory('/foo/bar/file.txt'));
    }

    public function testGetDirectoryNoSlashReturnsDot(): void
    {
        $this->assertSame('.', Ccsd_File::getDirectory('file.txt'));
    }

    public function testGetDirectoryTrailingSlash(): void
    {
        $this->assertSame('/foo/bar', Ccsd_File::getDirectory('/foo/bar/'));
    }

    // ------------------------------------------------------------------
    // getFilename
    // ------------------------------------------------------------------

    public function testGetFilenameExtractsBasename(): void
    {
        $this->assertSame('file.txt', Ccsd_File::getFilename('/foo/bar/file.txt'));
    }

    public function testGetFilenameNoPath(): void
    {
        $this->assertSame('file.txt', Ccsd_File::getFilename('file.txt'));
    }

    // ------------------------------------------------------------------
    // getIconeExtension
    // ------------------------------------------------------------------

    public function testGetIconeExtensionMusic(): void
    {
        $this->assertSame('icon-music', Ccsd_File::getIconeExtension('mp3'));
    }

    public function testGetIconeExtensionFilm(): void
    {
        $this->assertSame('icon-film', Ccsd_File::getIconeExtension('mp4'));
    }

    public function testGetIconeExtensionPicture(): void
    {
        $this->assertSame('icon-picture', Ccsd_File::getIconeExtension('jpg'));
    }

    public function testGetIconeExtensionFile(): void
    {
        $this->assertSame('icon-file', Ccsd_File::getIconeExtension('pdf'));
    }

    public function testGetIconeExtensionUnknownDefaultsToFile(): void
    {
        $this->assertSame('icon-file', Ccsd_File::getIconeExtension('xyz'));
    }

    // ------------------------------------------------------------------
    // isAnArchive
    // ------------------------------------------------------------------

    public function testIsAnArchiveZip(): void
    {
        $this->assertTrue(Ccsd_File::isAnArchive('archive.zip'));
    }

    public function testIsAnArchiveTar(): void
    {
        $this->assertTrue(Ccsd_File::isAnArchive('archive.tar'));
    }

    public function testIsAnArchiveTgz(): void
    {
        $this->assertTrue(Ccsd_File::isAnArchive('archive.tgz'));
    }

    public function testIsAnArchiveNotAnArchive(): void
    {
        $this->assertFalse(Ccsd_File::isAnArchive('document.pdf'));
        $this->assertFalse(Ccsd_File::isAnArchive('photo.jpg'));
    }

    // ------------------------------------------------------------------
    // canConvertImg
    // ------------------------------------------------------------------

    public function testCanConvertImgJpg(): void
    {
        $this->assertTrue(Ccsd_File::canConvertImg('photo.jpg'));
    }

    public function testCanConvertImgPng(): void
    {
        $this->assertTrue(Ccsd_File::canConvertImg('image.png'));
    }

    public function testCanConvertImgSvg(): void
    {
        $this->assertTrue(Ccsd_File::canConvertImg('drawing.svg'));
    }

    public function testCanConvertImgPdfNotConvertible(): void
    {
        $this->assertFalse(Ccsd_File::canConvertImg('document.pdf'));
    }

    // ------------------------------------------------------------------
    // spaces2space
    // ------------------------------------------------------------------

    public function testSpaces2SpaceCollapses(): void
    {
        $this->assertSame('a b c', Ccsd_File::spaces2space('a  b   c'));
    }

    public function testSpaces2SpaceSingleSpaceUnchanged(): void
    {
        $this->assertSame('a b', Ccsd_File::spaces2space('a b'));
    }

    // ------------------------------------------------------------------
    // stripAccents
    // ------------------------------------------------------------------

    public function testStripAccentsFrench(): void
    {
        $this->assertSame('ca suffit', Ccsd_File::stripAccents('ça suffit'));
    }

    public function testStripAccentsLigature(): void
    {
        $this->assertSame('oeuvre', Ccsd_File::stripAccents('œuvre'));
    }

    public function testStripAccentsAccentedVowels(): void
    {
        // é, è → e; plain 'e' stays 'e'
        $this->assertSame('eeee', Ccsd_File::stripAccents('eéèe'));
        $this->assertSame('eeee', Ccsd_File::stripAccents('éèëê'));
    }

    // ------------------------------------------------------------------
    // renameFile (force=false, path='', no filesystem access)
    // ------------------------------------------------------------------

    public function testRenameFileReplacesSpaces(): void
    {
        $result = Ccsd_File::renameFile('hello world.pdf', '', false);
        $this->assertSame('hello_world.pdf', $result);
    }

    public function testRenameFileStripsAccents(): void
    {
        $result = Ccsd_File::renameFile('café.pdf', '', false);
        $this->assertSame('cafe.pdf', $result);
    }

    public function testRenameFileCollapsesDots(): void
    {
        $result = Ccsd_File::renameFile('file..double.pdf', '', false);
        $this->assertSame('file.double.pdf', $result);
    }

    public function testRenameFileCollapsesUnderscores(): void
    {
        $result = Ccsd_File::renameFile('file__double.pdf', '', false);
        $this->assertSame('file_double.pdf', $result);
    }

    // ------------------------------------------------------------------
    // shortenFilename
    // ------------------------------------------------------------------

    public function testShortenFilenameWhenLong(): void
    {
        $result = Ccsd_File::shortenFilename('a_very_long_filename_indeed.pdf', 20);
        $this->assertSame('a_very_long_filename....pdf', $result);
    }

    public function testShortenFilenameWhenShortEnoughUnchanged(): void
    {
        $result = Ccsd_File::shortenFilename('short.pdf', 20);
        $this->assertSame('short.pdf', $result);
    }

    // ------------------------------------------------------------------
    // replaceFileExtension
    // ------------------------------------------------------------------

    public function testReplaceFileExtension(): void
    {
        $this->assertSame('photo.png', Ccsd_File::replaceFileExtension('photo.jpg', 'png'));
    }

    public function testReplaceFileExtensionCaseInsensitive(): void
    {
        // Case insensitive: JPG → png
        $result = Ccsd_File::replaceFileExtension('photo.JPG', 'png');
        $this->assertSame('photo.png', $result);
    }

    // ------------------------------------------------------------------
    // slicedPathFromString
    // ------------------------------------------------------------------

    public function testSlicedPathFromStringDefault(): void
    {
        // '1234' padded to 8 chars → '00001234', wordwrap by 2 with '/'
        $result = Ccsd_File::slicedPathFromString('1234');
        $this->assertSame('00/00/12/34', $result);
    }

    public function testSlicedPathFromStringWithRootDir(): void
    {
        $result = Ccsd_File::slicedPathFromString('1234', '/data/');
        $this->assertSame('/data/00/00/12/34', $result);
    }

    public function testSlicedPathFromStringCustomLength(): void
    {
        $result = Ccsd_File::slicedPathFromString('12', '', 4, 2);
        $this->assertSame('00/12', $result);
    }
}
