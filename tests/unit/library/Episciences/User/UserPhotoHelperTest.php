<?php

declare(strict_types=1);

namespace unit\library\Episciences\User;

use Episciences\User\UserPhotoHelper;
use PHPUnit\Framework\TestCase;

final class UserPhotoHelperTest extends TestCase
{
    public function testSlicedPath(): void
    {
        $this->assertSame('00/00/12/34', UserPhotoHelper::slicedPath('1234'));
        $this->assertSame('/data/00/00/12/34', UserPhotoHelper::slicedPath('1234', '/data/'));
        $this->assertSame('00/12', UserPhotoHelper::slicedPath('12', '', 4, 2));
    }

    public function testIsSupportedImageByExtensionWhenFileDoesNotExist(): void
    {
        $this->assertTrue(UserPhotoHelper::isSupportedImage('photo.jpg'));
        $this->assertTrue(UserPhotoHelper::isSupportedImage('avatar.PNG'));
        $this->assertFalse(UserPhotoHelper::isSupportedImage('drawing.svg'));
        $this->assertFalse(UserPhotoHelper::isSupportedImage('document.pdf'));
    }

    public function testConvertToJpegRejectsSvgBehindPngExtension(): void
    {
        $tmpDir = sys_get_temp_dir() . '/uph_svg_' . uniqid('', true);
        mkdir($tmpDir, 0777, true);
        $source = $tmpDir . '/fake.png';
        file_put_contents($source, '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>');

        try {
            $this->assertFalse(UserPhotoHelper::isSupportedImage($source));
            $this->assertNull(UserPhotoHelper::convertToJpeg($source, $tmpDir, 16, 16, 'thumb'));
            $this->assertFileDoesNotExist($tmpDir . '/thumb.jpg');
        } finally {
            @unlink($source);
            @rmdir($tmpDir);
        }
    }

    public function testConvertToJpegResizesAndSavesValidPng(): void
    {
        $tmpDir = sys_get_temp_dir() . '/uph_ok_' . uniqid('', true);
        mkdir($tmpDir, 0777, true);
        $source = $tmpDir . '/source.png';

        $gd = imagecreatetruecolor(100, 50);
        $this->assertNotFalse($gd);
        imagepng($gd, $source);
        imagedestroy($gd);

        try {
            $this->assertTrue(UserPhotoHelper::isSupportedImage($source));
            $result = UserPhotoHelper::convertToJpeg($source, $tmpDir, 20, 20, 'thumb_1');
            $this->assertSame('thumb_1.jpg', $result);

            $dest = $tmpDir . '/thumb_1.jpg';
            $this->assertFileExists($dest);

            $info = getimagesize($dest);
            $this->assertIsArray($info);
            $this->assertSame(IMAGETYPE_JPEG, $info[2]);
            $this->assertSame(20, $info[0]);
            $this->assertSame(10, $info[1]);
        } finally {
            @unlink($tmpDir . '/thumb_1.jpg');
            @unlink($source);
            @rmdir($tmpDir);
        }
    }
}
