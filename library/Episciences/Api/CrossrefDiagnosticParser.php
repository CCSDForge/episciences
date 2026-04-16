<?php
declare(strict_types=1);

namespace Episciences\Api;

/**
 * Parses a Crossref doi_batch_diagnostic XML response.
 *
 * Crossref batches can contain multiple <record_diagnostic> elements — typically
 * one for the article DOI and one for the journal DOI. This parser locates the
 * record matching the given article DOI so the caller can make precise decisions
 * rather than relying on the aggregate success_count.
 */
class CrossrefDiagnosticParser
{
    /**
     * Parse a Crossref XML response and find the record for the given DOI.
     *
     * Returns null only when the XML itself is unparseable.
     * When the DOI is not found among the records, doiFound=false and doiStatus=''
     * — the caller should log a warning and fall back to batch-level counts.
     */
    public function parse(string $xmlBody, string $doi): ?CrossrefDiagnosticResult
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlBody);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if ($xml === false) {
            return null;
        }

        $batchStatus  = (string) ($xml['status'] ?? '');
        $batchSuccess = (int) (string) ($xml->batch_data->success_count ?? '0');
        $batchFailure = (int) (string) ($xml->batch_data->failure_count ?? '0');
        $batchWarning = (int) (string) ($xml->batch_data->warning_count ?? '0');

        $normalizedDoi = $this->normalizeDoi($doi);

        foreach ($xml->record_diagnostic as $record) {
            if ($this->normalizeDoi((string) $record->doi) === $normalizedDoi) {
                return new CrossrefDiagnosticResult(
                    batchStatus:  $batchStatus,
                    doiFound:     true,
                    doiStatus:    (string) ($record['status'] ?? ''),
                    doiMsg:       (string) ($record->msg ?? ''),
                    batchSuccess: $batchSuccess,
                    batchFailure: $batchFailure,
                    batchWarning: $batchWarning,
                );
            }
        }

        return new CrossrefDiagnosticResult(
            batchStatus:  $batchStatus,
            doiFound:     false,
            doiStatus:    '',
            doiMsg:       '',
            batchSuccess: $batchSuccess,
            batchFailure: $batchFailure,
            batchWarning: $batchWarning,
        );
    }

    /** Strip https://doi.org/ prefix and normalise case for comparison. */
    private function normalizeDoi(string $doi): string
    {
        $doi = strtolower(trim($doi));
        if (str_starts_with($doi, 'https://doi.org/')) {
            $doi = substr($doi, strlen('https://doi.org/'));
        } elseif (str_starts_with($doi, 'http://doi.org/')) {
            $doi = substr($doi, strlen('http://doi.org/'));
        }
        return $doi;
    }
}
