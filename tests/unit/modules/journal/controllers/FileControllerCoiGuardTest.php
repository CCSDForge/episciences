<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard for the conflict-of-interest check in
 * FileController::reportAction().
 *
 * ZF1 module controllers are not Composer-autoloaded and need the full request
 * stack to instantiate, so — like the other controller tests in this suite — we
 * analyse the source to assert the expected handling stays in place.
 *
 * The check must treat an unresolved ('later') COI response as a conflict, not
 * only an explicit 'yes', and must exempt root/admin-only users, consistent
 * with DefaultController::isConflictDetected().
 *
 * @covers FileController::reportAction
 */
final class FileControllerCoiGuardTest extends TestCase
{
    private string $method;

    protected function setUp(): void
    {
        $source = (string) file_get_contents(
            APPLICATION_PATH . '/modules/journal/controllers/FileController.php'
        );

        $start = strpos($source, 'function reportAction(');
        self::assertNotFalse($start, 'Method reportAction not found in FileController');

        $end = strpos($source, "\n    public function ", $start + 1);
        $this->method = substr($source, $start, ($end !== false ? $end : strlen($source)) - $start);
    }

    public function testConflictResponsesIncludeBothYesAndLater(): void
    {
        self::assertMatchesRegularExpression(
            "/AVAILABLE_ANSWER\\['yes'\\].*AVAILABLE_ANSWER\\['later'\\]/s",
            $this->method,
            "reportAction() must treat an unresolved ('later') COI response as a conflict, not only an explicit 'yes'"
        );
    }

    public function testHasConflictCheckExemptsRootUsers(): void
    {
        self::assertStringContainsString(
            'Episciences_Auth::isRoot()',
            $this->method,
            'reportAction() must exempt root users from the COI check, like isConflictDetected() does'
        );
    }

    public function testHasConflictCheckExemptsAdministratorOnlyUsers(): void
    {
        self::assertStringContainsString(
            'Episciences_Auth::hasOnlyAdministratorRole()',
            $this->method,
            'reportAction() must exempt admin-only users from the COI check, like isConflictDetected() does'
        );
    }
}
