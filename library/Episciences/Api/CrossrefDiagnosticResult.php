<?php
declare(strict_types=1);

namespace Episciences\Api;

/**
 * Structured result of parsing a Crossref doi_batch_diagnostic XML response.
 */
final class CrossrefDiagnosticResult
{
    public function __construct(
        /** Overall batch processing status ("completed", "queued_for_batch", …). */
        public readonly string $batchStatus,
        /** True when the specific article DOI was found in <record_diagnostic> elements. */
        public readonly bool   $doiFound,
        /** Status of the matched record ("Success", "Failure", "Warning", or "" when not found). */
        public readonly string $doiStatus,
        /** Human-readable message from Crossref for the matched record. */
        public readonly string $doiMsg,
        public readonly int    $batchSuccess,
        public readonly int    $batchFailure,
        public readonly int    $batchWarning,
    ) {}

    /** Crossref has finished processing the batch (records have definitive status). */
    public function isCompleted(): bool
    {
        return $this->batchStatus === 'completed';
    }

    /** The article DOI was found and Crossref reports it as successfully processed. */
    public function isSuccess(): bool
    {
        return $this->doiFound && $this->doiStatus === 'Success';
    }
}
