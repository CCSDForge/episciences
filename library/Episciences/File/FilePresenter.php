<?php

declare(strict_types=1);

namespace Episciences\File;

/**
 * Presentation helpers for displaying file metadata (human-readable size and icon CSS class).
 */
final class FilePresenter
{
    /** @var array<string, list<string>> */
    private const EXTENSION_GROUPS = [
        'music' => [
            'aac', 'ac3', 'aif', 'aifc', 'aiff', 'au', 'bwf',
            'mp2', 'mp3', 'm4r', 'ogg', 'ogm', 'ra', 'ram', 'wma', 'wav',
        ],
        'film' => [
            'avi', 'flv', 'mov', 'movie', 'mp4', 'mpe', 'mpeg',
            'mpg', 'qt', 'rm', 'rmvb', 'rv', 'vob', 'wmv', 'm4a',
        ],
        'picture' => [
            'jpg', 'jpeg', 'jpe', 'jps', 'png', 'gif', 'tif',
            'tiff', 'ms3d', 'odg', 'otg', 'pct', 'svg',
        ],
        'file' => [
            'tex', 'zip', 'tar', 'gz', 'bz2', 'tar.gz', 'tar.bz2', 'tgz',
            'odc', 'ods', 'pdf', 'doc', 'docx', 'txt', 'dot', 'dotx',
            'rtf', 'odf', 'odt', 'ott', 'html', 'htm', 'ppt', 'pptx',
            'pot', 'potx', 'pps', 'ppsx', 'pptm', 'ppsm', 'ps', 'odp', 'ots',
        ],
    ];

    /**
     * Format a byte count into a human-readable string (French units: B, Ko, Mo, Go, To).
     */
    public static function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' Ko';
        }

        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' Mo';
        }

        if ($bytes < 1099511627776) {
            return round($bytes / 1073741824, 2) . ' Go';
        }

        if ($bytes < 1125899906842624) {
            return round($bytes / 1099511627776, 2) . ' To';
        }

        return '';
    }

    /**
     * Return the formatted size of a file on disk, or an empty string if unreadable.
     */
    public static function formattedFileSize(string $path): string
    {
        $bytes = is_file($path) ? filesize($path) : false;

        return $bytes === false ? '' : self::formatSize($bytes);
    }

    /**
     * Return the CSS icon class matching a lowercase file extension.
     */
    public static function iconClassForExtension(string $extension): string
    {
        $normalized = strtolower($extension);

        foreach (self::EXTENSION_GROUPS as $group => $formats) {
            if (in_array($normalized, $formats, true)) {
                return 'icon-' . $group;
            }
        }

        return 'icon-file';
    }
}
