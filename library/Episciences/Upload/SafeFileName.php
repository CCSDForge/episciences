<?php

declare(strict_types=1);

namespace Episciences\Upload;

/**
 * Check on a file name that comes back from the browser before it is joined to a directory.
 */
final class SafeFileName
{
    /**
     * True for a plain file name, i.e. without any directory part.
     */
    public static function isBare(mixed $name): bool
    {
        return is_string($name)
            && $name !== ''
            && $name !== '.'
            && $name !== '..'
            && !str_contains($name, '/')
            && !str_contains($name, '\\')
            && !str_contains($name, "\0");
    }
}
