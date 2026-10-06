<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Mail_Reminder;
use Episciences_Mail_RemindersManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
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
 * The library layer is tested by behaviour (stubbed database adapter). ZF1 controllers are not
 * instantiable in isolation, so the controller and the script are checked by source analysis.
 */
final class AdministratemailControllerReminderGuardTest extends TestCase
{
    private const CONTROLLER = APPLICATION_PATH . '/modules/journal/controllers/AdministratemailController.php';
    private const REMINDERS_JS = APPLICATION_PATH . '/../public/js/administratemail/reminders.js';

    private mixed $previousAdapter;

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
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
    // Controller and script: source analysis
    // ------------------------------------------------------------------

    /**
     * Body of a controller method, delimited by the next method declaration.
     */
    private function extractMethod(string $methodName): string
    {
        $source = (string)file_get_contents(self::CONTROLLER);
        $start = strpos($source, 'function ' . $methodName . '(');
        self::assertNotFalse($start, "Method $methodName not found in the controller");

        $next = preg_match('/\n    (?:public|protected|private) function /', $source, $m, PREG_OFFSET_CAPTURE, $start + 1)
            ? $m[0][1]
            : strlen($source);

        return substr($source, $start, $next - $start);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function scopedActions(): array
    {
        return [
            'editreminderAction' => ['editreminderAction', 'find'],
            'savereminderAction' => ['savereminderAction', 'find'],
            'deletereminderAction' => ['deletereminderAction', 'delete'],
        ];
    }

    #[DataProvider('scopedActions')]
    public function testActionScopesReminderLookupToTheCurrentJournal(string $action, string $managerMethod): void
    {
        $method = $this->extractMethod($action);

        $count = preg_match_all('/RemindersManager::' . $managerMethod . '\(([^;]*);/', $method, $matches);
        self::assertGreaterThan(0, $count, "$action must go through RemindersManager::$managerMethod()");

        foreach ($matches[1] as $arguments) {
            self::assertMatchesRegularExpression('/,\s*RVID\s*\)/', $arguments,
                "$action must restrict the reminder to the current journal");
        }
    }

    public function testEditActionRefusesAReminderOfAnotherJournalWith403(): void
    {
        $method = $this->extractMethod('editreminderAction');

        self::assertMatchesRegularExpression(
            '/if \(!\$reminder\) \{.*?setHttpResponseCode\(403\).*?return;/s',
            $method,
            'editreminderAction must answer 403 instead of an empty modal'
        );
    }

    public function testSaveActionChecksRequestAndInputBeforeAnyLookupOrChange(): void
    {
        $method = $this->extractMethod('savereminderAction');

        $request = strpos($method, 'isValidReminderWriteRequest(');
        $params = strpos($method, "getParam('type')");
        $ownership = strpos($method, 'RemindersManager::find(');
        $save = strpos($method, '->save()');

        foreach ([$request, $params, $ownership, $save] as $position) {
            self::assertNotFalse($position);
        }

        self::assertLessThan($params, $request, 'The request token must be checked before reading parameters');
        self::assertLessThan($ownership, $params, 'The type and recipient must be validated before the lookup');
        self::assertLessThan($save, $ownership, 'Ownership must be checked before the reminder is saved');
        self::assertStringContainsString('setHttpResponseCode(400)', $method, 'Invalid input must answer 400');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function writeActions(): array
    {
        return [
            'savereminderAction' => ['savereminderAction', '->save()'],
            'deletereminderAction' => ['deletereminderAction', 'RemindersManager::delete('],
        ];
    }

    #[DataProvider('writeActions')]
    public function testWriteActionChecksTheRequestBeforeAnyChange(string $action, string $change): void
    {
        $method = $this->extractMethod($action);

        $check = strpos($method, 'if (!$this->isValidReminderWriteRequest($request))');
        $changeAt = strpos($method, $change);

        self::assertNotFalse($check, "$action must validate the request method and token");
        self::assertNotFalse($changeAt);
        self::assertLessThan($changeAt, $check, "$action must validate the request before changing anything");
    }

    public function testWriteRequestRequiresPostAndRequestToken(): void
    {
        $method = $this->extractMethod('isValidReminderWriteRequest');

        self::assertStringContainsString('$request->isPost()', $method);
        self::assertStringContainsString('Episciences_Csrf_Helper::validateRequestToken($request)', $method);
        self::assertStringContainsString('setHttpResponseCode(403)', $method);
    }

    public function testReminderScriptSendsTheTokenAndHandlesFailures(): void
    {
        $source = (string)file_get_contents(self::REMINDERS_JS);

        self::assertStringContainsString('meta[name="csrf-token"]', $source);
        self::assertSame(
            2,
            substr_count($source, "headers: { 'X-CSRF-Token': getCsrfToken() }"),
            'Both the save and the delete calls must send the request token'
        );
        self::assertSame(2, substr_count($source, 'error: function'), 'Both write calls must handle a failure');
        self::assertStringContainsString('$(container).html(previousContent)', $source,
            'A failed deletion must restore the reminder replaced by the loader');
    }
}
