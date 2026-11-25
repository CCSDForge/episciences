<?php

declare(strict_types=1);

namespace Episciences\Paper;

use RuntimeException;

/**
 * Some data of a paper could not be stored, after the other ones were:
 * carries the number of rows stored, so that the caller still counts them.
 */
class PartiallyStoredException extends RuntimeException
{
    public function __construct(string $message, private readonly int $affectedRows)
    {
        parent::__construct($message);
    }

    /**
     * Rows stored for the other data before the failure was thrown
     */
    public function getAffectedRows(): int
    {
        return $this->affectedRows;
    }
}
