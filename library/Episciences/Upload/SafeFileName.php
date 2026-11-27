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

    /**
     * True for a name that tries to reach another directory. An empty name, or the "." left by an empty name
     * and extension, is a broken request, not an attempt.
     */
    public static function isUnsafe(mixed $name): bool
    {
        return is_string($name) && $name !== '' && $name !== '.' && !self::isBare($name);
    }

    /**
     * True when the value holds a NUL byte or another control character, never part of a stored path.
     */
    public static function hasControlCharacter(string $value): bool
    {
        return preg_match('/[\x00-\x1f]/', $value) === 1;
    }
}
