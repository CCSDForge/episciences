<?php

declare(strict_types=1);

namespace Episciences\Tca\Manuscript;

use RuntimeException;

/**
 * Internal signal: the repository has no analysable file for this paper (final answer).
 * Never leaves RepositoryManuscriptSource, which turns it into a null result.
 *
 * @internal
 */
final class UnavailableManuscript extends RuntimeException
{
}
