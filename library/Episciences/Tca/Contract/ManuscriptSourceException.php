<?php

declare(strict_types=1);

namespace Episciences\Tca\Contract;

use RuntimeException;

/**
 * Base class of the manuscript acquisition failures other than "no analysable file".
 */
abstract class ManuscriptSourceException extends RuntimeException
{
    /**
     * Whether a later attempt may succeed.
     */
    abstract public function isRetryable(): bool;
}
