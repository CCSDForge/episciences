<?php

declare(strict_types=1);

namespace Episciences\Upload;

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
}
