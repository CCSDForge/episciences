<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Build;

use DateTime;
use Episciences\AppRegistry;
use Episciences\Solr\Indexing\Model\SolrDocument;
use Exception;

/**
 * Port of the date-formatting logic from Ccsd_Search_Solr_Indexer_Episciences
 * (getFormattedDate(), the submission/publication date split, and the
 * indexing_date_tdate stamp). The invalid-date fallback below is a deliberate
 * behavioural quirk kept identical to legacy (see getFormattedDate() docblock) so
 * DocumentComparator's field-level diff stays meaningful.
 *
 * The clock is injectable so tests can freeze "now" instead of depending on
 * wall-clock time — the one input the legacy builder never made testable.
 */
final class DateFieldsBuilder
{
    private const DEFAULT_FORMAT = 'Y-m-d\TH:i:s\Z';
    private const INVALID_DATE_FALLBACK = '1970-01-01T00:00:00Z';

    /** @var callable(): DateTime */
    private $clock;

    /** @param (callable(): DateTime)|null $clock */
    public function __construct(?callable $clock = null)
    {
        $this->clock = $clock ?? static fn (): DateTime => new DateTime();
    }

    /**
     * @return array{submission: ?string, publication: ?string, year: ?string, month: ?string, day: ?string}
     */
    public function computeDates(?string $submissionDate, ?string $publicationDate): array
    {
        $submission = ($submissionDate) ? $this->getFormattedDate($submissionDate) : null;

        if ($publicationDate) {
            [$year, $month, $day] = explode('-', $this->getFormattedDate($publicationDate, 'Y-m-d'));
            $publication = $this->getFormattedDate($publicationDate);
        } else {
            $year = null;
            $month = null;
            $day = null;
            $publication = null;
        }

        return [
            'submission' => $submission,
            'publication' => $publication,
            'year' => $year,
            'month' => $month,
            'day' => $day,
        ];
    }

    public function withIndexingTimestamp(SolrDocument $document): SolrDocument
    {
        $now = ($this->clock)();

        return $document->withRawField('indexing_date_tdate', $now->format(self::DEFAULT_FORMAT));
    }

    /**
     * NB: on an invalid date string, legacy always falls back to the full
     * "1970-01-01T00:00:00Z" ISO string regardless of the requested $format —
     * e.g. requesting the 'Y-m-d' split format on an invalid publication date
     * yields the pieces ['1970', '01', '01T00:00:00Z'] once exploded on '-'.
     * That is preserved here on purpose: it's an existing legacy behaviour, not
     * one of the two confirmed bugs this rewrite fixes, and diverging from it
     * would make DocumentComparator report spurious diffs on malformed dates.
     */
    public function getFormattedDate(string $dateToFormat, string $format = self::DEFAULT_FORMAT): string
    {
        try {
            return (new DateTime($dateToFormat))->format($format);
        } catch (Exception $e) {
            AppRegistry::getMonoLogger()?->warning(
                sprintf('getFormattedDate: invalid date "%s": %s', $dateToFormat, $e->getMessage())
            );

            return self::INVALID_DATE_FALLBACK;
        }
    }
}
