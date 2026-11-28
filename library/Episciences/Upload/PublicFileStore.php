<?php

declare(strict_types=1);

namespace Episciences\Upload;

use Symfony\Component\String\UnicodeString;

/**
 * Helpers for storing an uploaded file in a directory that is served to the public.
 *
 * The data describing the file comes back from the browser (hidden form field), so neither the path of the
 * temporary file nor its name can be trusted.
 */
final class PublicFileStore
{
    /**
     * Real path of the temporary file, only if it is inside the directory where the uploads are parked.
     *
     * @return string|null null when the file does not exist or is somewhere else
     */
    public static function resolveTemporaryFile(string $path, string $temporaryDirectory): ?string
    {
        // realpath('') would resolve to the current directory
        $directory = $temporaryDirectory === '' ? false : realpath($temporaryDirectory);
        $file = $path === '' ? false : realpath($path);

        if ($directory === false || $file === false || !is_file($file)) {
            return null;
        }

        return str_starts_with($file, rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) ? $file : null;
    }

    /**
     * Name to store the file under: the extension is kept (it was checked against the content of the file),
     * and the dots of the rest of the name are removed so that no earlier extension can be interpreted by the
     * web server (e.g. "script.php.png").
     */
    public static function storedName(string $originalName): string
    {
        $extension = MimeTypePolicy::extensionOf($originalName);
        $base = str_replace('.', '_', pathinfo($originalName, PATHINFO_FILENAME));

        if (trim($base, '_ ') === '') {
            $base = 'file';
        }

        return $extension === '' ? $base : $base . '.' . $extension;
    }

    /**
     * Sanitize a file name to ASCII-safe characters and optionally append a numeric suffix
     * to avoid overwriting an existing file in the target directory.
     */
    public static function uniqueSanitizedName(string $name, string $directory = '', bool $avoidCollision = true): string
    {
        $sanitized = (new UnicodeString($name))->ascii()->toString();
        $sanitized = preg_replace('/  +/', ' ', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/[^a-z0-9_.\-\/\\\\]/i', '_', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/\.\.+/', '.', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/__+/', '_', $sanitized) ?? $sanitized;

        if (!$avoidCollision || $directory === '' || !str_contains($sanitized, '.')) {
            return $sanitized;
        }

        $baseDir = rtrim($directory, '/') . '/';
        while (is_file($baseDir . $sanitized)) {
            if (preg_match('/_?(\d*)(\.\w*)$/', $sanitized, $matches) !== 1) {
                break;
            }
            $nextNumber = ((int)$matches[1]) + 1;
            $next = preg_replace('/_?\d*(\.\w*)$/', '_' . $nextNumber . $matches[2], $sanitized);
            if ($next === null || $next === $sanitized) {
                break;
            }
            $sanitized = $next;
        }

        return $sanitized;
    }
}
