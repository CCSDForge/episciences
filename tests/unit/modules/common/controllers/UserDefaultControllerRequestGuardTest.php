<?php

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for the request preconditions of two UserDefaultController
 * actions (deleteAction, saverolesAction).
 *
 * ZF1 module controllers are not Composer-autoloaded and require the full request
 * stack to instantiate, so — consistent with the other controller tests in this
 * suite — we analyse the source to assert the preconditions stay in place.
 *
 * @covers UserDefaultController
 */
final class UserDefaultControllerRequestGuardTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(
            APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php'
        );
    }

    private function extractMethod(string $methodName): string
    {
        $start = strpos($this->source, 'function ' . $methodName . '(');
        self::assertNotFalse($start, "Method $methodName not found in UserDefaultController");

        $end = strpos($this->source, "\n    public function ", (int) $start + 1);
        $end2 = strpos($this->source, "\n    private function ", (int) $start + 1);
        $end3 = strpos($this->source, "\n    protected function ", (int) $start + 1);
        $candidates = array_filter([$end, $end2, $end3], static fn($v) => $v !== false);
        $stop = $candidates ? min($candidates) : strlen($this->source);

        return substr($this->source, (int) $start, $stop - (int) $start);
    }

    // -----------------------------------------------------------------------
    // deleteAction — POST + secretary
    // -----------------------------------------------------------------------

    public function testDeleteActionRequiresPost(): void
    {
        $method = $this->extractMethod('deleteAction');
        self::assertStringContainsString('isPost()', $method,
            'deleteAction must only handle POST requests');
    }

    public function testDeleteActionRequiresSecretary(): void
    {
        $method = $this->extractMethod('deleteAction');
        self::assertStringContainsString('Episciences_Auth::isSecretary()', $method,
            'deleteAction must require the secretary role');
    }

    public function testDeleteActionAnswers403WhenPreconditionsFail(): void
    {
        $method = $this->extractMethod('deleteAction');
        self::assertMatchesRegularExpression('/setHttpResponseCode\(\s*403\s*\)/', $method,
            'deleteAction must answer 403 when its preconditions are not met');
    }

    // -----------------------------------------------------------------------
    // saverolesAction — POST + authenticated
    // -----------------------------------------------------------------------

    public function testSaveRolesActionRequiresPost(): void
    {
        $method = $this->extractMethod('saverolesAction');
        self::assertStringContainsString('isPost()', $method,
            'saverolesAction must only handle POST requests');
    }

    public function testSaveRolesActionRequiresAuthentication(): void
    {
        $method = $this->extractMethod('saverolesAction');
        self::assertStringContainsString('Episciences_Auth::isLogged()', $method,
            'saverolesAction must require an authenticated user');
    }

    public function testSaveRolesActionCheckRunsBeforeReadingUid(): void
    {
        $method = $this->extractMethod('saverolesAction');
        $guardPos = strpos($method, 'setHttpResponseCode(403)');
        $uidPos = strpos($method, "\$params['uid']");
        self::assertNotFalse($guardPos, 'saverolesAction must contain the 403 precondition check');
        self::assertNotFalse($uidPos, 'saverolesAction must read the uid parameter');
        self::assertLessThan($uidPos, $guardPos,
            'the precondition check must run before reading the parameters');
    }

    // -----------------------------------------------------------------------
    // logoutAction — return URL from the application base
    // -----------------------------------------------------------------------

    public function testLogoutBuildsReturnUrlFromApplicationBase(): void
    {
        $method = $this->extractMethod('logoutAction');
        self::assertStringContainsString('APPLICATION_URL', $method,
            'logoutAction must build the return URL from APPLICATION_URL');
        self::assertStringNotContainsString("\$_SERVER['HTTP_HOST']", $method,
            'logoutAction must not build the return URL from the request host');
    }
}
