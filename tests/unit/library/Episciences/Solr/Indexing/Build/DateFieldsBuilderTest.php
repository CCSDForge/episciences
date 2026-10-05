<?php

namespace unit\library\Episciences\Solr\Indexing\Build;

use DateTime;
use Episciences\Solr\Indexing\Build\DateFieldsBuilder;
use Episciences\Solr\Indexing\Model\SolrDocument;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DateFieldsBuilder. The clock is injected so the
 * indexing_date_tdate stamp can be asserted deterministically — the one input
 * the legacy getFormattedDate()/addMetadataToDoc() could never make testable
 * without freezing PHP's date() function itself.
 */
class DateFieldsBuilderTest extends TestCase
{
    public function testGetFormattedDateUsesDefaultIsoFormat(): void
    {
        $result = (new DateFieldsBuilder())->getFormattedDate('2024-01-15');

        self::assertSame('2024-01-15T00:00:00Z', $result);
    }

    public function testGetFormattedDateAcceptsCustomFormat(): void
    {
        $result = (new DateFieldsBuilder())->getFormattedDate('2024-03-20', 'Y-m-d');

        self::assertSame('2024-03-20', $result);
    }

    public function testGetFormattedDateFallsBackTo1970OnInvalidDate(): void
    {
        $result = (new DateFieldsBuilder())->getFormattedDate('not-a-valid-date-xyz');

        self::assertSame('1970-01-01T00:00:00Z', $result);
    }

    public function testGetFormattedDateFallbackIgnoresRequestedFormat(): void
    {
        // Deliberate legacy quirk, kept identical on purpose — see the
        // docblock on DateFieldsBuilder::getFormattedDate().
        $result = (new DateFieldsBuilder())->getFormattedDate('not-a-valid-date-xyz', 'Y-m-d');

        self::assertSame('1970-01-01T00:00:00Z', $result);
    }

    public function testComputeDatesReturnsNullsWhenBothDatesAreEmpty(): void
    {
        $dates = (new DateFieldsBuilder())->computeDates(null, null);

        self::assertSame(
            ['submission' => null, 'publication' => null, 'year' => null, 'month' => null, 'day' => null],
            $dates
        );
    }

    public function testComputeDatesSplitsPublicationDateIntoYearMonthDay(): void
    {
        $dates = (new DateFieldsBuilder())->computeDates(null, '2024-03-20 10:00:00');

        self::assertSame('2024', $dates['year']);
        self::assertSame('03', $dates['month']);
        self::assertSame('20', $dates['day']);
        self::assertSame('2024-03-20T10:00:00Z', $dates['publication']);
    }

    public function testComputeDatesFormatsSubmissionDate(): void
    {
        $dates = (new DateFieldsBuilder())->computeDates('2023-06-01 08:30:00', null);

        self::assertSame('2023-06-01T08:30:00Z', $dates['submission']);
    }

    public function testWithIndexingTimestampUsesInjectedClock(): void
    {
        $builder = new DateFieldsBuilder(static fn (): DateTime => new DateTime('2026-07-02 19:30:45'));

        $document = $builder->withIndexingTimestamp(SolrDocument::empty());

        self::assertSame(['2026-07-02T19:30:45Z'], $document->toArray()['indexing_date_tdate']);
    }
}
