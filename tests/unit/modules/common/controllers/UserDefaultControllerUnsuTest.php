<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Episciences_Acl;
use Episciences_Auth;
use Episciences_User;
use PHPUnit\Framework\TestCase;
use Zend_Session_Namespace;

/**
 * Tests for the switch user exit action (UserDefaultController::unsuAction).
 */
final class UserDefaultControllerUnsuTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        if (!defined('SESSION_NAMESPACE')) {
            define('SESSION_NAMESPACE', 'Episciences_Auth');
        }
        if (!defined('RVID')) {
            define('RVID', 1);
        }

        $this->source = (string) file_get_contents(
            APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php'
        );

        Episciences_Auth::getInstance()->clearIdentity();
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->realIdentities);
    }

    protected function tearDown(): void
    {
        Episciences_Auth::getInstance()->clearIdentity();
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->realIdentities);
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

    public function testUnsuActionGuardsAgainstNonImpersonatingCall(): void
    {
        $method = $this->extractMethod('unsuAction');
        self::assertStringContainsString('!Episciences_Auth::isImpersonating()', $method,
            'unsuAction must check if the session is currently impersonating');
    }

    public function testUnsuActionPopsOriginalIdentityFromSession(): void
    {
        $method = $this->extractMethod('unsuAction');
        self::assertStringContainsString('array_pop($realIdentities)', $method,
            'unsuAction must pop the original identity from the realIdentities stack');
    }

    public function testUnsuActionRestoresOriginalIdentity(): void
    {
        $method = $this->extractMethod('unsuAction');
        self::assertStringContainsString('Episciences_Auth::updateIdentity($originalUser)', $method,
            'unsuAction must restore the active identity to the original user');
    }

    public function testUnsuActionLogsUnsuEvent(): void
    {
        $method = $this->extractMethod('unsuAction');
        self::assertStringContainsString("SuLogger::log(", $method,
            'unsuAction must audit the unsu event via SuLogger');
        self::assertStringContainsString("'UNSU'", $method,
            "unsuAction must pass 'UNSU' as the log action");
    }

    public function testUnsuActionSetsSuccessFlashMessageAndRedirects(): void
    {
        $method = $this->extractMethod('unsuAction');
        self::assertStringContainsString('FlashMessenger', $method,
            'unsuAction must display a feedback message');
        self::assertStringContainsString('dashboard', $method,
            'unsuAction must redirect to the user dashboard');
    }

    public function testSessionLifecycleSuThenUnsuRestoresIdentityCleanly(): void
    {
        $admin = $this->createMock(Episciences_User::class);
        $admin->method('getUid')->willReturn(10);
        $admin->method('getScreenName')->willReturn('Administrator');
        $admin->method('getRoles')->willReturn([Episciences_Acl::ROLE_ADMIN]);

        $target = $this->createMock(Episciences_User::class);
        $target->method('getUid')->willReturn(99);
        $target->method('getScreenName')->willReturn('Target Member');
        $target->method('getRoles')->willReturn([Episciences_Acl::ROLE_MEMBER]);

        // 1. Initial state: Admin logged in
        Episciences_Auth::getInstance()->getStorage()->write($admin);
        self::assertFalse(Episciences_Auth::isImpersonating());
        self::assertSame(10, Episciences_Auth::getUid());

        // 2. Switch user to target
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->realIdentities = [$admin];
        Episciences_Auth::getInstance()->getStorage()->write($target);

        self::assertTrue(Episciences_Auth::isImpersonating());
        self::assertSame(99, Episciences_Auth::getUid());
        self::assertSame($admin, Episciences_Auth::getOriginalIdentity());

        // 3. Unsu execution
        $restored = $this->popIdentityFromSession($session);
        if ($restored !== null) {
            Episciences_Auth::getInstance()->getStorage()->write($restored);
        }

        // 4. Verification post-unsu
        self::assertFalse(Episciences_Auth::isImpersonating());
        self::assertSame(10, Episciences_Auth::getUid());
        self::assertNull($session->realIdentities);
    }

    private function popIdentityFromSession(Zend_Session_Namespace $session): ?Episciences_User
    {
        /** @var mixed $realIdentities */
        $realIdentities = $session->realIdentities;
        if (!is_array($realIdentities) || empty($realIdentities)) {
            return null;
        }

        /** @var Episciences_User|null $restored */
        $restored = array_pop($realIdentities);
        if (empty($realIdentities)) {
            unset($session->realIdentities);
        } else {
            $session->realIdentities = $realIdentities;
        }

        return $restored;
    }
}
