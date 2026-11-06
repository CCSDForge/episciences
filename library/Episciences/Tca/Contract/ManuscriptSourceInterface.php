<?php

declare(strict_types=1);

namespace Episciences\Tca\Contract;

/**
 * Provides the manuscript file of a paper version, to be submitted to Turnitin Core API.
 *
 * Episciences does not store manuscripts locally: implementations resolve the
 * repository file (arXiv, HAL, Zenodo...) and download it.
 */
interface ManuscriptSourceInterface
{
    /**
     * Returns the manuscript to submit to Turnitin for a given paper version,
     * or null when no analysable file is available.
     *
     * Null is a final answer (unknown paper, temporary version, dataset or software,
     * no file, file removed or restricted by the repository): retrying will not help.
     *
     * @throws ManuscriptTemporarilyUnavailableException the repository could not be reached
     *                                                   or answered with a transient error; retry later
     * @throws ManuscriptTooLargeException the file exceeds ManuscriptFile::MAX_SIZE; do not retry
     */
    public function fetch(int $docId): ?ManuscriptFile;
}
