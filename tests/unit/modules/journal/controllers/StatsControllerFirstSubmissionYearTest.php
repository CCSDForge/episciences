<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Repositories;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use StatsController;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;

/**
 * Figures counted in the journal database by StatsController: a paper is counted once, the year of its first
 * submission (the versions of a paper can have different submission dates).
 */
final class StatsControllerFirstSubmissionYearTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    /** @var list<string> SQL of the SELECT statements sent to the adapter */
    private array $selects = [];

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/StatsController.php';
    }

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchOne', 'fetchAll'])
            ->getMock();
        $this->adapter->method('fetchOne')->willReturnCallback(function ($select) {
            $this->selects[] = $select instanceof Zend_Db_Select ? $select->assemble() : (string)$select;

            return '4';
        });
        $this->adapter->method('fetchAll')->willReturnCallback(function ($select) {
            $this->selects[] = $select instanceof Zend_Db_Select ? $select->assemble() : (string)$select;

            return [['REPOID' => '1', 'YEAR' => '2023', 'NB' => '3']];
        });
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    private function invoke(string $name, mixed ...$args): mixed
    {
        $controller = (new ReflectionClass(StatsController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(StatsController::class, $name);
        $method->setAccessible(true);

        return $method->invoke($controller, ...$args);
    }

    private function countPapers(?int $year, ?string $startAfterDate): ?int
    {
        return $this->invoke('countPapersWithTemporaryVersion', $year, $startAfterDate, null);
    }

    // -------------------------------------------------------------------------
    // countPapersWithTemporaryVersion()
    // -------------------------------------------------------------------------

    public function testCountsEachPaperOnceAndFiltersOnTheFirstSubmission(): void
    {
        self::assertSame(4, $this->countPapers(2024, '2023-01-01'));
        self::assertCount(1, $this->selects);

        $sql = $this->selects[0];
        $repoId = (int)Episciences_Repositories::EPISCIENCES_REPO_ID;

        self::assertStringContainsString('COUNT(*)', $sql);
        self::assertStringContainsString('GROUP BY `PAPERID`', $sql);
        self::assertStringContainsString("SUM(REPOID = $repoId) > 0", $sql);
        self::assertStringContainsString("YEAR(MIN(SUBMISSION_DATE)) = 2024", $sql);
        self::assertStringContainsString("MIN(SUBMISSION_DATE) > '2023-01-01'", $sql);
        self::assertStringNotContainsString('WHERE (YEAR(SUBMISSION_DATE)', $sql);
        self::assertStringNotContainsString("AND (REPOID = $repoId)", $sql, 'the temporary version must not filter the rows');
    }

    public function testWithoutFilters_NoDateCondition(): void
    {
        $this->countPapers(null, null);

        self::assertStringNotContainsString('SUBMISSION_DATE', $this->selects[0]);
    }

    // -------------------------------------------------------------------------
    // getImportedByYearAndRepo()
    // -------------------------------------------------------------------------

    public function testImported_ByYearOfTheFirstSubmission(): void
    {
        $imported = $this->invoke('getImportedByYearAndRepo', '2023-01-01', null);

        self::assertSame([2023 => [(string)Episciences_Repositories::getLabel(1) => 3]], $imported);
        self::assertCount(1, $this->selects);

        $sql = $this->selects[0];

        self::assertStringContainsString('GROUP BY `PAPERID`', $sql);
        self::assertStringContainsString('YEAR(FIRST_SUBMISSION_DATE)', $sql);
        self::assertStringContainsString('COUNT(*)', $sql);
        self::assertStringContainsString("HAVING (MIN(SUBMISSION_DATE) > '2023-01-01')", $sql);
        self::assertStringNotContainsString("(SUBMISSION_DATE > '2023-01-01')", $sql);
    }
}
