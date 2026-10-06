<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Mail_RemindersManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Regression guards for the automatic reminder actions.
 *
 * - Reminder ids come from request parameters, and all journals share the same table: every
 *   action starting from such an id must restrict it to the current journal (RVID).
 * - Saving and deleting a reminder change what the reminder cron sends: these actions must only
 *   run on a POST request carrying the per-session request token (CSRF).
 *
 * Source-analysis tests (ZF1 controllers are not instantiable in isolation) assert that these
 * checks stay in place.
 */
final class AdministratemailControllerReminderGuardTest extends TestCase
{
    private const CONTROLLER = APPLICATION_PATH . '/modules/journal/controllers/AdministratemailController.php';
    private const REMINDER = APPLICATION_PATH . '/../library/Episciences/Mail/Reminder.php';
    private const REMINDERS_JS = APPLICATION_PATH . '/../public/js/administratemail/reminders.js';

    private function extractMethod(string $file, string $methodName): string
    {
        $source = (string) file_get_contents($file);
        $start = strpos($source, 'function ' . $methodName . '(');
        self::assertNotFalse($start, "Method $methodName not found in " . basename($file));

        $candidates = array_filter([
            strpos($source, "\n    public function ", $start + 1),
            strpos($source, "\n    protected function ", $start + 1),
            strpos($source, "\n    private function ", $start + 1),
        ], static fn($v) => $v !== false);
        $stop = $candidates ? min($candidates) : strlen($source);

        return substr($source, $start, $stop - $start);
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

    /**
     * @dataProvider scopedActions
     */
    public function testActionScopesReminderLookupToTheCurrentJournal(string $action, string $managerMethod): void
    {
        $method = $this->extractMethod(self::CONTROLLER, $action);

        $count = preg_match_all('/RemindersManager::' . $managerMethod . '\(([^;]*);/', $method, $matches);
        self::assertGreaterThan(0, $count, "$action must go through RemindersManager::$managerMethod()");

        foreach ($matches[1] as $arguments) {
            self::assertMatchesRegularExpression('/,\s*RVID\s*\)/', $arguments,
                "$action must restrict the reminder to the current journal");
        }
    }

    public function testEditActionStopsRenderingWhenTheReminderIsRefused(): void
    {
        $method = $this->extractMethod(self::CONTROLLER, 'editreminderAction');

        self::assertMatchesRegularExpression(
            '/if \(!\$reminder\) \{[^}]*setNoRender\(\);[^}]*return;/',
            $method,
            'editreminderAction must render nothing for a reminder of another journal'
        );
    }

    public function testSaveActionChecksOwnershipBeforeSaving(): void
    {
        $method = $this->extractMethod(self::CONTROLLER, 'savereminderAction');

        $check = strpos($method, 'RemindersManager::find(');
        $save = strpos($method, '->save()');

        self::assertNotFalse($check);
        self::assertNotFalse($save);
        self::assertLessThan($save, $check, 'Ownership must be checked before the reminder is saved');
    }

    public function testReminderUpdateIsRestrictedToItsJournal(): void
    {
        $method = $this->extractMethod(self::REMINDER, 'save');

        self::assertMatchesRegularExpression(
            "/->update\(T_MAIL_REMINDERS,[^;]*'RVID = \?'/",
            $method,
            'Reminder::save() must only update a reminder of its own journal'
        );
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

    /**
     * The journal restriction is optional (null keeps the previous behaviour) so that
     * callers without a current journal are not broken.
     *
     * @dataProvider managerMethods
     */
    public function testManagerMethodAcceptsAnOptionalJournalId(string $name): void
    {
        $parameters = (new ReflectionMethod(Episciences_Mail_RemindersManager::class, $name))->getParameters();

        self::assertCount(2, $parameters);
        self::assertSame('rvid', $parameters[1]->getName());
        self::assertTrue($parameters[1]->allowsNull());
        self::assertTrue($parameters[1]->isDefaultValueAvailable());
        self::assertNull($parameters[1]->getDefaultValue());
    }

    /**
     * @dataProvider managerMethods
     */
    public function testManagerMethodFiltersOnTheJournalId(string $name): void
    {
        $method = $this->extractMethod(
            APPLICATION_PATH . '/../library/Episciences/Mail/RemindersManager.php',
            $name
        );

        self::assertMatchesRegularExpression("/'RVID = \?'/", $method,
            "RemindersManager::$name() must filter on RVID when a journal id is given");
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

    /**
     * @dataProvider writeActions
     */
    public function testWriteActionChecksTheRequestBeforeAnyChange(string $action, string $change): void
    {
        $method = $this->extractMethod(self::CONTROLLER, $action);

        $check = strpos($method, 'if (!$this->isValidReminderWriteRequest($request))');
        $changeAt = strpos($method, $change);

        self::assertNotFalse($check, "$action must validate the request method and token");
        self::assertNotFalse($changeAt);
        self::assertLessThan($changeAt, $check, "$action must validate the request before changing anything");
    }

    public function testWriteRequestRequiresPostAndRequestToken(): void
    {
        $method = $this->extractMethod(self::CONTROLLER, 'isValidReminderWriteRequest');

        self::assertStringContainsString('$request->isPost()', $method);
        self::assertStringContainsString('Episciences_Csrf_Helper::validateRequestToken($request)', $method);
        self::assertStringContainsString('setHttpResponseCode(403)', $method);
    }

    public function testReminderScriptSendsTheRequestTokenOnEveryWrite(): void
    {
        $source = (string) file_get_contents(self::REMINDERS_JS);

        self::assertStringContainsString('meta[name="csrf-token"]', $source);
        self::assertSame(
            2,
            substr_count($source, "headers: { 'X-CSRF-Token': getCsrfToken() }"),
            'Both the save and the delete calls must send the request token'
        );
    }
}
