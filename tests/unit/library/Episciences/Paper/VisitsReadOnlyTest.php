<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper;

use Episciences\Maintenance\ReadOnlyMode;
use Episciences_Paper_Visits;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Statement_Exception;
use Zend_Db_Table_Abstract;

/**
 * Episciences_Paper_Visits::add() is the only database write of an anonymous consultation:
 * it must be skipped in read-only maintenance mode and must never break the page on a database error.
 */
final class VisitsReadOnlyTest extends TestCase
{
    private ?Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var Zend_Db_Adapter_Abstract&MockObject */
    private Zend_Db_Adapter_Abstract $adapter;

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $this->adapter->method('quote')->willReturnCallback(static fn($value): string => "'" . $value . "'");
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
        ReadOnlyMode::setOverride(null);
    }

    public function testNothingIsWrittenInReadOnlyMode(): void
    {
        ReadOnlyMode::setOverride(true);

        $this->adapter->expects(self::never())->method('insert');

        Episciences_Paper_Visits::add(42, Episciences_Paper_Visits::CONSULT_TYPE_FILE);
    }

    public function testVisitIsRecordedOutsideReadOnlyMode(): void
    {
        ReadOnlyMode::setOverride(false);

        $this->adapter->expects(self::once())
            ->method('insert')
            ->with(VISITS_TEMP, self::callback(static fn(array $row): bool => $row['DOCID'] === 42 && $row['CONSULT'] === 'notice'))
            ->willReturn(1);

        Episciences_Paper_Visits::add(42);
    }

    public function testDatabaseErrorDoesNotBreakTheConsultation(): void
    {
        ReadOnlyMode::setOverride(false);

        // What MySQL answers when super_read_only is on while the application flag is not set
        $this->adapter->expects(self::once())
            ->method('insert')
            ->willThrowException(new Zend_Db_Statement_Exception('The MySQL server is running with the --super-read-only option'));

        Episciences_Paper_Visits::add(42);

        $this->addToAssertionCount(1);
    }
}
