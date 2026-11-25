<?php
declare(strict_types=1);

namespace Episciences\Paper\Import;

/**
 * Outcome of importing or updating one paper.
 */
final class Result
{
    public function __construct(
        public readonly bool $wasUpdate,
        public readonly int $docid,
    ) {
    }
}
