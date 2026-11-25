<?php

declare(strict_types=1);

namespace Episciences\Paper;

use Exception;
use Throwable;

/**
 * Database writes of the data of a paper whose failure must not end the script (the paper is already saved):
 * the non throwing variant of a write (insert()) reports the failure of its throwing variant (insertOrFail()).
 */
final class WriteFailure
{
    /**
     * Runs $write: a failure of the $caught class is reported as a warning, with $method, and $onFailure is returned.
     * The other failures are thrown.
     *
     * @template TResult
     * @template TFailure
     * @param string $method reported with the failure (__METHOD__ of the caller)
     * @param callable(): TResult $write
     * @param TFailure $onFailure
     * @param class-string<Throwable> $caught
     * @return TResult|TFailure
     */
    public static function reportAsWarning(
        string $method,
        callable $write,
        mixed $onFailure = 0,
        string $caught = Exception::class
    ): mixed {
        try {
            return $write();
        } catch (Throwable $e) {
            if (!$e instanceof $caught) {
                throw $e;
            }

            trigger_error($method . ': ' . $e->getMessage(), E_USER_WARNING);
            return $onFailure;
        }
    }
}
