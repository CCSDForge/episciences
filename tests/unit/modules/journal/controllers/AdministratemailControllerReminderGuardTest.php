<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Csrf_Helper;
use Episciences_Mail_Reminder;
use Episciences_Mail_RemindersManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Regression guards for the automatic reminder actions.
 *
 * - Reminder ids come from request parameters, and all journals share the same table: every
 *   operation starting from such an id must be restricted to the current journal (RVID).
 * - Saving and deleting a reminder change what the reminder cron sends: these actions must only
 *   run on a POST request carrying the per-session request token (CSRF).
 *
 * Library and controller are tested by behaviour: the controller actions are dispatched for real
 * on a stubbed database adapter.
 */
final class AdministratemailControllerReminderGuardTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $previousAdapter;

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!\Zend_Registry::isRegistered('Zend_Locale')) {
            \Zend_Registry::set('Zend_Locale', new \Zend_Locale('fr')); // needed to list the reminder templates
        }
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    // ------------------------------------------------------------------
    // Library layer: behaviour
    // ------------------------------------------------------------------

    public function testDeleteIsRestrictedToTheJournalAndRefusesAForeignReminder(): void
    {
        $adapter = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $adapter->expects(self::once())
            ->method('delete')
            ->with(self::anything(), ['ID = ?' => 12, 'RVID = ?' => 3])
            ->willReturn(0);
        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);

        self::assertFalse(Episciences_Mail_RemindersManager::delete(12, 3));
    }

    public function testSaveOfAForeignReminderWritesNothing(): void
    {
        $adapter = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $adapter->expects(self::once())
            ->method('fetchOne')
            ->with(self::anything(), [12, 3])
            ->willReturn(false);
        $adapter->expects(self::never())->method('update');
        $adapter->expects(self::never())->method('insert');
        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);

        // The constructor needs the application locale registry, which is irrelevant here
        $reminder = (new ReflectionClass(Episciences_Mail_Reminder::class))->newInstanceWithoutConstructor();
        $reminder->setId(12)->setRvid(3)->setType(Episciences_Mail_Reminder::TYPE_UNANSWERED_INVITATION);

        self::assertFalse($reminder->save());
    }

    /**
     * Callers must always state the journal: an optional journal id would silently give back
     * the unscoped behaviour to any caller forgetting it.
     */
    #[DataProvider('managerMethods')]
    public function testManagerMethodRequiresTheJournalId(string $name): void
    {
        $parameters = (new ReflectionMethod(Episciences_Mail_RemindersManager::class, $name))->getParameters();

        self::assertCount(2, $parameters);
        self::assertSame('rvid', $parameters[1]->getName());
        self::assertFalse($parameters[1]->isOptional());
        self::assertFalse($parameters[1]->allowsNull());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function managerMethods(): array
    {
        return [
            'find' => ['find'],
            'delete' => ['delete'],
        ];
    }

    // ------------------------------------------------------------------
    // Controller: dispatched actions
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $params
     */
    private function dispatch(string $action, array $params, bool $post = true, ?string $token = null): Zend_Controller_Response_HttpTestCase
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/AdministratemailController.php';

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod($post ? 'POST' : 'GET');
        $request->setParams($params);
        $request->setPost($post ? $params : []);
        if ($token !== null) {
            $request->setHeader('X-CSRF-Token', $token);
        }
        $response = new Zend_Controller_Response_HttpTestCase();

        $controller = new \AdministratemailController($request, $response);
        ob_start();
        try {
            $controller->$action();
        } finally {
            ob_end_clean();
        }
        $_POST = []; // setPost() writes the global

        return $response;
    }

    /**
     * @param array<string, mixed>|false $reminderRow
     * @return MockObject&Zend_Db_Adapter_Abstract
     */
    private function stubAdapter(array|false $reminderRow = false)
    {
        $adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchRow', 'fetchOne', 'insert', 'update', 'delete'])
            ->getMock();
        $adapter->method('fetchRow')->willReturn($reminderRow);
        $adapter->method('fetchOne')->willReturn(false);
        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);

        return $adapter;
    }

    public function testEditRefusesAReminderOfAnotherJournalWith403(): void
    {
        $this->stubAdapter(false);

        $response = $this->dispatch('editreminderAction', ['id' => '12'], false);

        self::assertSame(403, $response->getHttpResponseCode());
    }

    /**
     * @return array<string, array{bool, ?string}>
     */
    public static function rejectedWriteRequestProvider(): array
    {
        return [
            'GET with a valid token' => [false, 'valid'],
            'POST without token' => [true, null],
            'POST with a wrong token' => [true, 'forged'],
        ];
    }

    #[DataProvider('rejectedWriteRequestProvider')]
    public function testSaveAndDeleteAreRefusedWithoutAPostCarryingTheToken(bool $post, ?string $token): void
    {
        $adapter = $this->stubAdapter(['ID' => 12, 'RVID' => RVID]);
        $adapter->expects(self::never())->method('insert');
        $adapter->expects(self::never())->method('update');
        $adapter->expects(self::never())->method('delete');

        if ($token === 'valid') {
            $token = Episciences_Csrf_Helper::getSessionToken();
        } else {
            Episciences_Csrf_Helper::getSessionToken(); // the session holds a token the request does not send
        }

        foreach (['savereminderAction', 'deletereminderAction'] as $action) {
            $response = $this->dispatch($action, ['id' => '12', 'type' => 'x', 'recipient' => 'y'], $post, $token);
            self::assertSame(403, $response->getHttpResponseCode(), $action);
        }
    }

    public function testSaveRefusesAnUnknownTypeOrRecipientWith400(): void
    {
        $adapter = $this->stubAdapter(false);
        $adapter->expects(self::never())->method('insert');
        $adapter->expects(self::never())->method('update');

        $response = $this->dispatch(
            'savereminderAction',
            ['type' => 'no-such-type', 'recipient' => 'nobody'],
            true,
            Episciences_Csrf_Helper::getSessionToken()
        );

        self::assertSame(400, $response->getHttpResponseCode());
    }

    public function testSaveRefusesAReminderOfAnotherJournalWith403(): void
    {
        $adapter = $this->stubAdapter(false);
        $adapter->expects(self::never())->method('insert');
        $adapter->expects(self::never())->method('update');

        $templates = Episciences_Mail_RemindersManager::getTemplates();
        $type = (string)array_key_first($templates);
        $recipient = (string)array_key_first($templates[$type]);

        $response = $this->dispatch(
            'savereminderAction',
            ['id' => '12', 'type' => $type, 'recipient' => $recipient],
            true,
            Episciences_Csrf_Helper::getSessionToken()
        );

        self::assertSame(403, $response->getHttpResponseCode());
    }

    public function testDeleteOnlyTouchesAReminderOfTheCurrentJournal(): void
    {
        $adapter = $this->stubAdapter(false);
        $adapter->expects(self::once())
            ->method('delete')
            ->with(self::anything(), ['ID = ?' => 12, 'RVID = ?' => RVID])
            ->willReturn(0);

        $response = $this->dispatch('deletereminderAction', ['id' => '12'], true, Episciences_Csrf_Helper::getSessionToken());

        self::assertSame(403, $response->getHttpResponseCode(), 'nothing deleted: refused');
    }

    public function testDeleteOfAReminderOfTheCurrentJournalSucceeds(): void
    {
        $adapter = $this->stubAdapter(false);
        $adapter->expects(self::once())->method('delete')->willReturn(1);

        $response = $this->dispatch('deletereminderAction', ['id' => '12'], true, Episciences_Csrf_Helper::getSessionToken());

        self::assertSame(200, $response->getHttpResponseCode());
    }
}
