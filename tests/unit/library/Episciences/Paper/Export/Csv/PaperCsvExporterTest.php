<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper\Export\Csv;

use Episciences\Paper\Export\Csv\Filters;
use Episciences\Paper\Export\Csv\PaperCsvExporter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Db_Select;

/**
 * Unit tests for PaperCsvExporter's SQL-shape (buildSelect()).
 *
 * Renders the Zend_Db_Select to SQL text via assemble() against the real test-DB adapter
 * bootstrapped by tests/bootstrap.php, without executing it — same convention as
 * Episciences_PapersManagerTest ("Only methods that do not require a database connection are
 * covered here"). This is also the round-trip proof for export:papers: every criterion this
 * command exposes must translate into the same WHERE shape PaperImporter::getMatchingPapers()
 * uses to re-match a paper by docid, or by identifier+version, on a later import:papers run.
 */
final class PaperCsvExporterTest extends TestCase
{
    private function assembleSelect(Filters $filters): string
    {
        $exporter = new PaperCsvExporter($filters);

        $method = new ReflectionMethod(PaperCsvExporter::class, 'buildSelect');
        $method->setAccessible(true);

        /** @var Zend_Db_Select $select */
        $select = $method->invoke($exporter);

        return $select->assemble();
    }

    public function testFiltersByRvid(): void
    {
        $sql = $this->assembleSelect(new Filters(rvid: 7));

        self::assertStringContainsString('RVID = 7', $sql);
    }

    public function testFiltersByVolumeIdThroughDocidSubquery(): void
    {
        $sql = $this->assembleSelect(new Filters(rvid: 7, volumeId: 10));

        // 'vid' is scoped via Episciences_PapersManager::volumesFilter()'s DOCID subquery,
        // not a plain "VID = " clause.
        self::assertStringContainsString('DOCID IN', $sql);
        self::assertStringContainsString('VID IN (10)', $sql);
    }

    public function testFiltersBySectionId(): void
    {
        $sql = $this->assembleSelect(new Filters(rvid: 7, sectionId: 20));

        self::assertStringContainsString('SID = 20', $sql);
    }

    public function testFiltersByYear(): void
    {
        $sql = $this->assembleSelect(new Filters(rvid: 7, year: 2024));

        self::assertStringContainsString('YEAR(PUBLICATION_DATE) = 2024', $sql);
    }

    public function testFiltersByDocids(): void
    {
        $sql = $this->assembleSelect(new Filters(rvid: 7, docids: [12, 34]));

        self::assertStringContainsString('DOCID IN (12, 34)', $sql);
    }

    public function testFiltersByStatuses(): void
    {
        $sql = $this->assembleSelect(new Filters(rvid: 7, statuses: [4, 16]));

        self::assertStringContainsString('STATUS IN (4, 16)', $sql);
    }

    public function testFiltersByRepoid(): void
    {
        $sql = $this->assembleSelect(new Filters(rvid: 7, repoid: 1));

        self::assertStringContainsString('REPOID = 1', $sql);
    }

    public function testFiltersByUid(): void
    {
        $sql = $this->assembleSelect(new Filters(rvid: 7, uid: 42));

        self::assertStringContainsString('UID = 42', $sql);
    }

    public function testFiltersByIdentifierUsesSameLikeClauseAsPaperImporter(): void
    {
        $sql = $this->assembleSelect(new Filters(rvid: 7, identifier: 'hal-04123456'));

        self::assertStringContainsString("IDENTIFIER LIKE 'hal-04123456'", $sql);
    }

    public function testFiltersByIdentifierAndVersion(): void
    {
        $sql = $this->assembleSelect(new Filters(rvid: 7, identifier: 'hal-04123456', version: '2'));

        self::assertStringContainsString("IDENTIFIER LIKE 'hal-04123456'", $sql);
        self::assertStringContainsString('VERSION = 2', $sql);
    }

    public function testIgnoresVersionWithoutIdentifier(): void
    {
        // Filters::fromOptions() already drops a lone version — this covers a Filters
        // instance built directly (e.g. by another future caller) with version but no
        // identifier: buildSelect() must not filter on VERSION alone either.
        $sql = $this->assembleSelect(new Filters(rvid: 7, version: '2'));

        self::assertStringNotContainsString('VERSION', $sql);
    }

    public function testAppliesRawSqlWhereAsIs(): void
    {
        $sql = $this->assembleSelect(new Filters(rvid: 7, sqlWhere: 'DOI IS NOT NULL'));

        self::assertStringContainsString('DOI IS NOT NULL', $sql);
    }

    public function testAppliesLimit(): void
    {
        $sql = $this->assembleSelect(new Filters(rvid: 7, limit: 100));

        self::assertStringContainsString('LIMIT 100', $sql);
    }

    public function testOrdersByDocidAscending(): void
    {
        $sql = $this->assembleSelect(new Filters(rvid: 7));

        self::assertStringContainsString('ORDER BY `DOCID` ASC', $sql);
    }

    public function testSelectsOnlyTheDocidColumn(): void
    {
        $sql = $this->assembleSelect(new Filters(rvid: 7));

        self::assertStringContainsString('SELECT `papers`.`DOCID`', $sql);
    }

    public function testExportThrowsExceptionWhenHeaderWriteFails(): void
    {
        $exporter = new PaperCsvExporter(new Filters(rvid: 7));
        $handle = fopen('php://temp', 'r');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to write CSV header.');

        try {
            $exporter->export($handle);
        } finally {
            fclose($handle);
        }
    }
}
