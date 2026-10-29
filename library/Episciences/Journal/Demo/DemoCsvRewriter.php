<?php

declare(strict_types=1);

namespace Episciences\Journal\Demo;

use Episciences\Paper\Import\Row;

/**
 * Forces the uid and rvid columns of a demo-papers.csv row to the journal:seed-demo run's
 * --uid/--rvid, so the same versioned CSV (scripts/importSamples/demo-papers.csv) can be
 * seeded into any journal.
 */
final class DemoCsvRewriter
{
    /**
     * @param array<int, string> $row
     * @return array<int, string>
     */
    public static function rewrite(array $row, int $rvid, int $uid): array
    {
        $row[Row::COL_UID] = (string)$uid;
        $row[Row::COL_RVID] = (string)$rvid;

        return $row;
    }
}
