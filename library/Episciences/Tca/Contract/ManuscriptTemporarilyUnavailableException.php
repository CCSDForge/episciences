<?php

declare(strict_types=1);

namespace Episciences\Tca\Contract;

/**
 * The repository could not be reached (connection error, timeout)
 * or answered with a transient error (429, 5xx).
 */
final class ManuscriptTemporarilyUnavailableException extends ManuscriptSourceException
{
    #[\Override]
    public function isRetryable(): bool
    {
        return true;
    }
}
