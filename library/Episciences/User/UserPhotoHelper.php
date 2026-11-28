<?php

declare(strict_types=1);

namespace Episciences\User;

use Episciences\AppRegistry;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

/**
 * Storage path sharding, validation, and JPEG thumbnail generation for user profile photos.
 */
final class UserPhotoHelper
{
    /** @var list<string> */
    private const ACCEPTED_EXTENSIONS = ['jpg', 'jpeg', 'jpe', 'gif', 'png', 'bmp'];

    /** @var list<string> */
    private const ACCEPTED_MIME_TYPES = [
        'image/jpeg',
        'image/gif',
        'image/png',
        'image/bmp',
        'image/x-ms-bmp',
    ];

    /**
     * Build a sharded directory path from an identifier (e.g. "1234" -> "/root/00/00/12/34").
     */
    public static function slicedPath(
        string $identifier,
        string $rootDir = '',
        int $length = 8,
        int $sliceLength = 2,
        string $padChar = '0'
    ): string {
        $padded = str_pad($identifier, $length, $padChar, STR_PAD_LEFT);
        $sliced = wordwrap($padded, $sliceLength, '/', true);

        return $rootDir . $sliced;
    }

    /**
     * Check whether a file path has an accepted raster image extension and, when the file exists
     * on disk, a matching raster MIME type.
     */
    public static function isSupportedImage(string $filePath): bool
    {
        $extension = Path::getExtension($filePath, true);
        if (!in_array($extension, self::ACCEPTED_EXTENSIONS, true)) {
            return false;
        }

        if (!is_file($filePath)) {
            return true;
        }

        $mimeType = MimeTypes::getDefault()->guessMimeType($filePath);

        return $mimeType !== null && in_array($mimeType, self::ACCEPTED_MIME_TYPES, true);
    }

    /**
     * Resize a raster image proportionally to fit within ($maxWidth, $maxHeight) and save it as JPEG.
     *
     * Uses the GD driver exclusively so vector formats such as SVG are never interpreted.
     *
     * @return string|null Saved JPEG filename on success, or null on failure
     */
    public static function convertToJpeg(
        string $sourcePath,
        string $destinationDir,
        int $maxWidth,
        int $maxHeight,
        string $outputBasename
    ): ?string {
        if (!is_file($sourcePath) || !is_dir($destinationDir) || $maxWidth < 1 || $maxHeight < 1) {
            return null;
        }

        if (!self::isSupportedImage($sourcePath)) {
            return null;
        }

        $jpgFile = $outputBasename . '.jpg';
        $destPath = rtrim($destinationDir, '/') . '/' . $jpgFile;

        try {
            $manager = ImageManager::usingDriver(GdDriver::class);
            $image = $manager->decodePath($sourcePath);
            $image->scale($maxWidth, $maxHeight);
            $image->save($destPath);

            return $jpgFile;
        } catch (Throwable $e) {
            AppRegistry::getMonoLogger()?->warning(
                sprintf('UserPhotoHelper::convertToJpeg() failed for "%s": %s', $sourcePath, $e->getMessage())
            );

            return null;
        }
    }
}
