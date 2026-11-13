<?php

declare(strict_types=1);

namespace Episciences\Tca\Contract;

/**
 * Provides the manuscript file of a paper version, to be submitted to Turnitin Core API.
 *
 * Episciences does not store manuscripts locally: implementations resolve the
 * repository file (arXiv, HAL, Zenodo...) and download it. Two exceptions, both uploaded to Episciences:
 * the data descriptor of datasets and software, and the file of a temporary version.
 */
interface ManuscriptSourceInterface
{
    /**
     * Returns the manuscript to submit to Turnitin for a given paper version,
     * or null when no analysable file is available.
     *
     * Null is a final answer (unknown paper, temporary version with several files or without file,
     * dataset or software without data descriptor,
     * no file, file removed or restricted by the repository, web page or metadata instead of a file,
     * redirect loop, unknown host, invalid certificate, download not completed within the time limit):
     * retrying will not help.
     *
     * @throws ManuscriptTemporarilyUnavailableException the repository could not be reached
     *                                                   or answered with a transient error; retry later
     * @throws ManuscriptTooLargeException the file exceeds ManuscriptFile::MAX_SIZE; do not retry
     */
    public function fetch(int $docId): ?ManuscriptFile;
}
