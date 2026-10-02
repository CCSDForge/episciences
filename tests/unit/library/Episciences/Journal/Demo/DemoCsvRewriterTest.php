<?php

namespace unit\library\Episciences\Journal\Demo;

use Episciences\Journal\Demo\DemoCsvRewriter;
use Episciences\Paper\Import\Row;
use PHPUnit\Framework\TestCase;

class DemoCsvRewriterTest extends TestCase
{
    public function testRewritesUidAndRvidColumns(): void
    {
        $row = array_fill(0, 19, '');
        $row[Row::COL_IDENTIFIER] = 'hal-01114989';
        $row[Row::COL_UID] = '';
        $row[Row::COL_RVID] = '';

        $rewritten = DemoCsvRewriter::rewrite($row, 42, 7);

        $this->assertSame('42', $rewritten[Row::COL_RVID]);
        $this->assertSame('7', $rewritten[Row::COL_UID]);
    }

    public function testLeavesEveryOtherColumnUntouched(): void
    {
        $row = array_fill(0, 19, '');
        $row[Row::COL_IDENTIFIER] = 'hal-01114989';
        $row[Row::COL_REPOID] = '1';
        $row[Row::COL_STATUS] = '16';
        $row[Row::COL_VOLUME_TITLE_EN] = 'Volume 1 (demo)';

        $rewritten = DemoCsvRewriter::rewrite($row, 42, 7);

        $this->assertSame('hal-01114989', $rewritten[Row::COL_IDENTIFIER]);
        $this->assertSame('1', $rewritten[Row::COL_REPOID]);
        $this->assertSame('16', $rewritten[Row::COL_STATUS]);
        $this->assertSame('Volume 1 (demo)', $rewritten[Row::COL_VOLUME_TITLE_EN]);
    }

    public function testOverwritesAnyPreexistingUidOrRvidValue(): void
    {
        $row = array_fill(0, 19, '');
        $row[Row::COL_UID] = '999';
        $row[Row::COL_RVID] = 'some-other-code';

        $rewritten = DemoCsvRewriter::rewrite($row, 42, 7);

        $this->assertSame('42', $rewritten[Row::COL_RVID]);
        $this->assertSame('7', $rewritten[Row::COL_UID]);
    }
}
