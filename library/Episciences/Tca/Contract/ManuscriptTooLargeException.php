<?php

declare(strict_types=1);

namespace Episciences\Tca\Contract;

/**
 * The manuscript exceeds the size accepted by Turnitin.
 */
final class ManuscriptTooLargeException extends ManuscriptSourceException
{
    /**
     * @param int $observedSize size announced by the repository, or bytes received before the download was stopped
     */
    public function __construct(private readonly int $observedSize, int $maxSize = ManuscriptFile::MAX_SIZE)
    {
        parent::__construct(sprintf('The manuscript is larger than %d bytes (at least %d bytes).', $maxSize, $observedSize));
    }

    public function getObservedSize(): int
    {
        return $this->observedSize;
    }

    #[\Override]
    public function isRetryable(): bool
    {
        return false;
    }
}
