<?php

declare(strict_types=1);

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
    // Request tokens (Episciences_Csrf_Helper)
    // -----------------------------------------------------------------------

    public function testDeleteActionValidatesPerUserToken(): void
    {
        $method = $this->extractMethod('deleteAction');
        self::assertStringContainsString("'user_delete_' . (int)\$userId", $method,
            'deleteAction must build the token name from the target user id');
        self::assertStringContainsString('Episciences_Csrf_Helper::validateToken', $method,
            'deleteAction must validate the request token');
    }

    public function testSaveRolesActionValidatesPerUserToken(): void
    {
        $method = $this->extractMethod('saverolesAction');
        self::assertStringContainsString("'user_saveroles_' . (int)\$uid", $method,
            'saverolesAction must build the token name from the target user id');
        self::assertStringContainsString('Episciences_Csrf_Helper::validateToken', $method,
            'saverolesAction must validate the request token');
    }

    public function testSaveRolesTokenIsCheckedBeforeSaving(): void
    {
        $method = $this->extractMethod('saverolesAction');
        $tokenPos = strpos($method, 'validateToken');
        $savePos = strpos($method, '$user->saveUserRoles(');
        self::assertNotFalse($tokenPos, 'saverolesAction must validate the token');
        self::assertNotFalse($savePos, 'saverolesAction must call saveUserRoles()');
        self::assertLessThan($savePos, $tokenPos,
            'the token must be validated before the roles are saved');
    }

    public function testRolesFormActionGeneratesPerUserToken(): void
    {
        $method = $this->extractMethod('rolesformAction');
        self::assertStringContainsString("Episciences_Csrf_Helper::generateToken('user_saveroles_'", $method,
            'rolesformAction must generate the token consumed by saverolesAction');
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

    // -----------------------------------------------------------------------
    // Change-email flow: account-duplication / account-hijack regression guards
    // -----------------------------------------------------------------------

    public function testProcessChangeEmailNeverMutatesTheSessionIdentityDirectly(): void
    {
        $method = $this->extractMethod('processChangeEmail');
        self::assertStringNotContainsString('Episciences_Auth::getUser()', $method,
            'processChangeEmail must load a detached object, never Episciences_Auth::getUser() (D4)');
    }

    public function testProcessChangeEmailChecksSharedAccountsOnTheStoredEmail(): void
    {
        $method = $this->extractMethod('processChangeEmail');
        self::assertStringContainsString('findLoginByEmail($currentEmail)', $method,
            'the "multiple accounts" check must run on the address actually stored, not the raw submitted value (D3)');
    }

    public function testProcessChangeEmailNormalizesBeforeStoring(): void
    {
        $method = $this->extractMethod('processChangeEmail');
        self::assertStringContainsString('EmailPolicy::normalize', $method,
            'processChangeEmail must normalize the submitted value the same way it will be stored (D2)');
    }

    public function testProcessChangeEmailChecksAvailabilityBeforeWriting(): void
    {
        $method = $this->extractMethod('processChangeEmail');
        $conflictPos = strpos($method, 'findConflictingUid');
        $setEmailPos = strpos($method, '->setEmail(');
        $savePos = strpos($method, '->save()');

        self::assertNotFalse($conflictPos, 'processChangeEmail must check for a conflicting UID');
        self::assertNotFalse($setEmailPos, 'processChangeEmail must call setEmail()');
        self::assertNotFalse($savePos, 'processChangeEmail must call save()');
        self::assertLessThan($setEmailPos, $conflictPos,
            'the availability check must run before setEmail() (D1)');
        self::assertLessThan($savePos, $setEmailPos,
            'setEmail() must run before save()');
    }

    public function testProcessChangeEmailDropsStaleCacheBeforeRefreshingSession(): void
    {
        $method = $this->extractMethod('processChangeEmail');
        $forgetPos = strpos($method, 'forgetStaticCache');
        $updatePos = strpos($method, 'updateIdentity');

        self::assertNotFalse($forgetPos, 'processChangeEmail must drop the stale identity cache entry (D5)');
        self::assertNotFalse($updatePos, 'processChangeEmail must refresh the session identity');
        self::assertLessThan($updatePos, $forgetPos,
            'the cache must be dropped before the session is refreshed from it (D5)');
    }

    public function testProcessChangeEmailComparesUidsAsIntegers(): void
    {
        $method = $this->extractMethod('processChangeEmail');
        self::assertDoesNotMatchRegularExpression('/getUid\(\)\s*===\s*\$postedUid/', $method,
            'processChangeEmail must not compare getUid() to the raw posted value (D6)');
        self::assertStringContainsString('(int)Episciences_Auth::getUid()', $method,
            'processChangeEmail must cast the acting user\'s UID to int before comparing (D6)');
    }

    public function testResolveEmailChangeTargetUidScopesSecretariesToTheirReview(): void
    {
        $method = $this->extractMethod('resolveEmailChangeTargetUid');
        self::assertStringContainsString('isSecretary()', $method,
            'resolveEmailChangeTargetUid must require the secretary role to target another account (D8)');
        self::assertStringContainsString('hasRoles(', $method,
            'resolveEmailChangeTargetUid must check the target has a role in the current review (D8)');
        self::assertStringContainsString('RVID', $method,
            'resolveEmailChangeTargetUid must scope the role check to the current review (D8)');
    }

    public function testChangeAccountEmailActionUsesTheSharedTargetResolver(): void
    {
        $method = $this->extractMethod('changeaccountemailAction');
        self::assertStringContainsString('resolveEmailChangeTargetUid(', $method,
            'changeaccountemailAction must resolve its target the same way processChangeEmail does (D8)');
    }

    // -----------------------------------------------------------------------
    // processChangeEmail — open-redirect guard on forward-controller/forward-action
    // -----------------------------------------------------------------------

    public function testProcessChangeEmailSanitizesForwardRouteSegments(): void
    {
        $method = $this->extractMethod('processChangeEmail');
        self::assertStringContainsString(
            "sanitizeForwardRouteSegment(\$request->getParam('forward-controller'",
            $method,
            'processChangeEmail must sanitize forward-controller before using it to build a redirect URL (CWE-601)'
        );
        self::assertStringContainsString(
            "sanitizeForwardRouteSegment(\$request->getParam('forward-action'",
            $method,
            'processChangeEmail must sanitize forward-action before using it to build a redirect URL (CWE-601)'
        );
    }

    /**
     * Extracts the validation regex from sanitizeForwardRouteSegment() and exercises
     * it directly: proves the pattern itself rejects anything that could turn the
     * redirect into an off-site URL, independently of how the method is wired in.
     */
    public function testSanitizeForwardRouteSegmentRegexRejectsUnsafeValues(): void
    {
        $method = $this->extractMethod('sanitizeForwardRouteSegment');
        self::assertMatchesRegularExpression('/preg_match\(/', $method,
            'sanitizeForwardRouteSegment must validate the value with preg_match()');

        self::assertMatchesRegularExpression("/preg_match\\('([^']+)'/", $method, 'could not locate the validation pattern');
        preg_match("/preg_match\\('([^']+)'/", $method, $matches);
        $pattern = $matches[1];

        $unsafe = [
            'http://evil.example',
            'https://evil.example',
            '//evil.example',
            '../../etc/passwd',
            'a/b',
            "a\nb",
            '',
            '1action',
        ];

        foreach ($unsafe as $value) {
            self::assertSame(0, preg_match($pattern, $value), "expected \"$value\" to be rejected");
        }

        $safe = ['user', 'change_account_email', 'some-action', 'a1'];

        foreach ($safe as $value) {
            self::assertSame(1, preg_match($pattern, $value), "expected \"$value\" to be accepted");
        }
    }
}
