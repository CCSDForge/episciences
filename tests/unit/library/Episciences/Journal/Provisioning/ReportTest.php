<?php

namespace unit\library\Episciences\Journal\Provisioning;

use Episciences\Journal\Provisioning\Report;
use PHPUnit\Framework\TestCase;

class ReportTest extends TestCase
{
    public function testToRowsPreservesInsertionOrder(): void
    {
        $report = new Report();
        $report->add('REVIEW', 'RVID 42 created');
        $report->add('Pages', '3 page(s) cloned');

        $this->assertSame(
            [
                ['REVIEW', 'RVID 42 created'],
                ['Pages', '3 page(s) cloned'],
            ],
            $report->toRows()
        );
    }

    public function testWarningsAreCollectedSeparatelyFromRows(): void
    {
        $report = new Report();
        $report->add('REVIEW', 'RVID 42 created');
        $report->warn('DOI settings use automatic assignment');

        $this->assertSame([['REVIEW', 'RVID 42 created']], $report->toRows());
        $this->assertSame(['DOI settings use automatic assignment'], $report->warnings());
    }

    public function testEmptyReportHasNoRowsOrWarnings(): void
    {
        $report = new Report();

        $this->assertSame([], $report->toRows());
        $this->assertSame([], $report->warnings());
    }
}
