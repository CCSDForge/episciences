<?php

declare(strict_types=1);

namespace unit\library\Episciences\File;

use Episciences\File\FilePresenter;
use PHPUnit\Framework\TestCase;

final class FilePresenterTest extends TestCase
{
    public function testFormatSizeCoversAllUnits(): void
    {
        $this->assertSame('0 B', FilePresenter::formatSize(0));
        $this->assertSame('1023 B', FilePresenter::formatSize(1023));
        $this->assertSame('1 Ko', FilePresenter::formatSize(1024));
        $this->assertSame('1.5 Ko', FilePresenter::formatSize(1536));
        $this->assertSame('1 Mo', FilePresenter::formatSize(1048576));
        $this->assertSame('1 Go', FilePresenter::formatSize(1073741824));
        $this->assertSame('1 To', FilePresenter::formatSize(1099511627776));
    }

    public function testFormattedFileSizeReadsRealFileAndHandlesMissingFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'fp_');
        $this->assertIsString($tmpFile);

        try {
            file_put_contents($tmpFile, str_repeat('a', 2048));
            $this->assertSame('2 Ko', FilePresenter::formattedFileSize($tmpFile));
        } finally {
            unlink($tmpFile);
        }

        $this->assertSame('', FilePresenter::formattedFileSize($tmpFile . '.missing'));
    }

    public function testIconClassForExtension(): void
    {
        $this->assertSame('icon-music', FilePresenter::iconClassForExtension('mp3'));
        $this->assertSame('icon-music', FilePresenter::iconClassForExtension('M4R'));
        $this->assertSame('icon-film', FilePresenter::iconClassForExtension('mp4'));
        $this->assertSame('icon-picture', FilePresenter::iconClassForExtension('JPG'));
        $this->assertSame('icon-file', FilePresenter::iconClassForExtension('pdf'));
        $this->assertSame('icon-file', FilePresenter::iconClassForExtension('unknown'));
    }
}
