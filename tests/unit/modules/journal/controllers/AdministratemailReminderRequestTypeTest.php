<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use unit\library\Episciences\Reviewer\RequestTypeTestDatabase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Request type of an automatic reminder (#319): only a type of the current journal is kept, otherwise the reminder
 * concerns every type.
 *
 * @covers \AdministratemailController
 */
final class AdministratemailReminderRequestTypeTest extends TestCase
{
    private ?Zend_Db_Adapter_Abstract $previousAdapter = null;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/AdministratemailController.php';
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db = RequestTypeTestDatabase::create();
        $db->insert('review_request_type', ['id' => 21, 'rvid' => RVID, 'labels' => '{"en": "Quick opinion"}']);
        $db->insert('review_request_type', ['id' => 22, 'rvid' => RVID + 1, 'labels' => '{"en": "Elsewhere"}']);
        Zend_Db_Table_Abstract::setDefaultAdapter($db);
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    private static function requestTypeId(mixed $posted): ?int
    {
        return (new ReflectionMethod(\AdministratemailController::class, 'reminderRequestTypeId'))->invoke(null, $posted);
    }

    public function testATypeOfTheJournalIsKept(): void
    {
        self::assertSame(21, self::requestTypeId('21'));
    }

    public function testATypeOfAnotherJournalAnUnknownTypeOrNoTypeMeansEveryType(): void
    {
        self::assertNull(self::requestTypeId('22'));
        self::assertNull(self::requestTypeId('99'));
        self::assertNull(self::requestTypeId(''));
        self::assertNull(self::requestTypeId(null));
        self::assertNull(self::requestTypeId(['21']));
    }
}
