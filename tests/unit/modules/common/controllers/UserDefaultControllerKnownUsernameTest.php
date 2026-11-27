<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Ccsd_User_Models_UserMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

require_once APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php';

/**
 * The login of a failed sign-in is written in the security log only when it names an existing account:
 * an unknown value may be a password typed in the wrong field.
 */
final class UserDefaultControllerKnownUsernameTest extends TestCase
{
    /** @var MockObject&Ccsd_User_Models_UserMapper */
    private Ccsd_User_Models_UserMapper $userMapper;
    private KnownUsernameTestController $controller;

    protected function setUp(): void
    {
        $this->userMapper = $this->createMock(Ccsd_User_Models_UserMapper::class);
        $this->controller = new KnownUsernameTestController(
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase()
        );
        $this->controller->userMapper = $this->userMapper;
    }

    public function testExistingLoginIsKept(): void
    {
        $this->userMapper->method('usernameExists')->with('jdoe')->willReturn(true);

        self::assertSame('jdoe', $this->controller->known('jdoe'));
    }

    public function testUnknownValueIsNeverKept(): void
    {
        $this->userMapper->method('usernameExists')->willReturn(false);

        self::assertNull($this->controller->known('MyS3cret-password'));
    }

    /**
     * Same trimming as the MySQL authentication adapter, non-breaking spaces included
     */
    public function testLoginIsLookedUpTrimmed(): void
    {
        $this->userMapper->expects(self::once())->method('usernameExists')->with('jdoe')->willReturn(true);

        self::assertSame('jdoe', $this->controller->known("\xC2\xA0 jdoe "));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function missingLogins(): iterable
    {
        yield 'no login' => [null];
        yield 'blank login' => [" \xC2\xA0 "];
        yield 'array login' => [['jdoe']];
    }

    /**
     * @dataProvider missingLogins
     */
    public function testMissingLoginIsNotLookedUp(mixed $username): void
    {
        $this->userMapper->expects(self::never())->method('usernameExists');

        self::assertNull($this->controller->known($username));
    }

    /**
     * Whether the value is a login cannot be told: it is left out
     */
    public function testLookupFailureKeepsNothing(): void
    {
        $this->userMapper->method('usernameExists')->willThrowException(new RuntimeException('CAS database unavailable'));

        self::assertNull($this->controller->known('jdoe'));
    }
}

final class KnownUsernameTestController extends \UserDefaultController
{
    public Ccsd_User_Models_UserMapper $userMapper;

    protected function getUserMapper(): Ccsd_User_Models_UserMapper
    {
        return $this->userMapper;
    }

    public function known(mixed $username): ?string
    {
        return $this->knownUsername($username);
    }
}
