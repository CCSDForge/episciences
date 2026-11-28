<?php

declare(strict_types=1);

namespace unit\library\Episciences\Upload;

use Episciences\Upload\PublicFileStore;
use Episciences\Upload\UploadChecker;
use Episciences_Volume_Metadata;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\Upload\PublicFileStore
 * @covers \Episciences\Upload\UploadChecker::publicFilesPolicy
 * @covers \Episciences_Volume_Metadata::save
 */
final class PublicFileStoreTest extends TestCase
{
    private string $root;
    private string $uploads;

    protected function setUp(): void
    {
        $this->root = (string)realpath(sys_get_temp_dir()) . '/public_store_' . bin2hex(random_bytes(6));
        $this->uploads = $this->root . '/tmp';
        mkdir($this->uploads, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (array_merge(glob($this->uploads . '/*') ?: [], glob($this->root . '/*') ?: []) as $path) {
            if (is_file($path) || is_link($path)) {
                unlink($path);
            }
        }
        rmdir($this->uploads);
        rmdir($this->root);
    }

    public function testResolvesAFileOfTheUploadDirectory(): void
    {
        $file = $this->uploads . '/md_abc';
        file_put_contents($file, 'x');

        self::assertSame($file, PublicFileStore::resolveTemporaryFile($file, $this->uploads));
        self::assertSame($file, PublicFileStore::resolveTemporaryFile($file, $this->uploads . '/'));
    }

    public function testRefusesAFileOutsideTheUploadDirectory(): void
    {
        $outside = $this->root . '/secret';
        file_put_contents($outside, 'x');

        self::assertNull(PublicFileStore::resolveTemporaryFile($outside, $this->uploads));
        self::assertNull(PublicFileStore::resolveTemporaryFile($this->uploads . '/../secret', $this->uploads), 'path traversal');
    }

    public function testRefusesALinkToAFileOutsideTheUploadDirectory(): void
    {
        $outside = $this->root . '/secret';
        file_put_contents($outside, 'x');
        symlink($outside, $this->uploads . '/md_link');

        self::assertNull(PublicFileStore::resolveTemporaryFile($this->uploads . '/md_link', $this->uploads));
    }

    public function testRefusesSiblingDirectoriesWithTheSamePrefix(): void
    {
        mkdir($this->uploads . '-other');
        file_put_contents($this->uploads . '-other/md_abc', 'x');

        try {
            self::assertNull(PublicFileStore::resolveTemporaryFile($this->uploads . '-other/md_abc', $this->uploads));
        } finally {
            unlink($this->uploads . '-other/md_abc');
            rmdir($this->uploads . '-other');
        }
    }

    /**
     * @dataProvider providerUnusablePaths
     */
    public function testRefusesUnusablePaths(string $path, string $directory): void
    {
        self::assertNull(PublicFileStore::resolveTemporaryFile($path, $directory));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerUnusablePaths(): array
    {
        return [
            'empty path' => ['', '/tmp'],
            'empty directory' => ['/etc/passwd', ''],
            'missing file' => ['/tmp/does-not-exist-' . __CLASS__, '/tmp'],
            'directory instead of file' => ['/tmp', '/tmp'],
        ];
    }

    /**
     * @dataProvider providerNames
     */
    public function testStoredName(string $original, string $expected): void
    {
        self::assertSame($expected, PublicFileStore::storedName($original));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerNames(): array
    {
        return [
            'plain' => ['report.pdf', 'report.pdf'],
            'extension in lowercase' => ['Report.PDF', 'Report.pdf'],
            'earlier extension is neutralised' => ['script.php.png', 'script_php.png'],
            'several dots' => ['a.b.c.txt', 'a_b_c.txt'],
            'no base name' => ['.png', 'file.png'],
            'no extension' => ['README', 'README'],
        ];
    }

    public function testPublicFilesPolicyDoesNotAcceptHtml(): void
    {
        $policy = UploadChecker::publicFilesPolicy();

        self::assertFalse($policy->isExtensionAllowed('html'));
        self::assertTrue($policy->isExtensionAllowed('pdf'));
        self::assertTrue($policy->isExtensionAllowed('png'));
    }

    public function testHtmlIsRefusedForPublicFilesEvenWithAnHtmlContent(): void
    {
        $file = $this->root . '/page.html';
        file_put_contents($file, '<html><body><script>alert(1)</script></body></html>');

        self::assertNotNull(UploadChecker::firstPublicFileError($file, 'page.html'));
    }

    public function testMetadataDoesNotStoreAFileThatIsNotAnUploadOfTheJournal(): void
    {
        $outside = $this->root . '/secret.txt';
        file_put_contents($outside, 'content');

        $metadata = new Episciences_Volume_Metadata([
            'vid' => 424242,
            'tmpfile' => ['name' => 'secret.txt', 'tmp_name' => $outside],
        ]);

        self::assertFalse($metadata->save());
        self::assertFileExists($outside, 'the file was neither moved nor removed');
    }

    public function testUniqueSanitizedNameTransliteratesAndSanitizes(): void
    {
        self::assertSame('hello_world.pdf', PublicFileStore::uniqueSanitizedName('hello  world.pdf', '', false));
        self::assertSame('cafe.pdf', PublicFileStore::uniqueSanitizedName('café.pdf', '', false));
        self::assertSame('oeuvre.pdf', PublicFileStore::uniqueSanitizedName('œuvre.pdf', '', false));
        self::assertSame('file.double.pdf', PublicFileStore::uniqueSanitizedName('file..double.pdf', '', false));
        self::assertSame('file_double.pdf', PublicFileStore::uniqueSanitizedName('file__double.pdf', '', false));
    }

    public function testUniqueSanitizedNameReturnsABareFileName(): void
    {
        self::assertSame('._._etc_x.png', PublicFileStore::uniqueSanitizedName('../../etc/x.png', '', false));
        self::assertSame('._._a.png', PublicFileStore::uniqueSanitizedName('..\\..\\a.png', '', false));
        self::assertSame('_abs_x.png', PublicFileStore::uniqueSanitizedName('/abs/x.png', '', false));
    }

    public function testUniqueSanitizedNameAvoidsCollisionWhenEnabled(): void
    {
        file_put_contents($this->uploads . '/report.pdf', '1');
        file_put_contents($this->uploads . '/report_1.pdf', '2');

        self::assertSame('report_2.pdf', PublicFileStore::uniqueSanitizedName('report.pdf', $this->uploads, true));
        self::assertSame('report.pdf', PublicFileStore::uniqueSanitizedName('report.pdf', $this->uploads, false));
    }
}
