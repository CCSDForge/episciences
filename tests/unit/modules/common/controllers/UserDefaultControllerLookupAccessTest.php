<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Episciences_Acl;
use Episciences_Auth;
use Episciences_User;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Zend_Auth_Storage_Interface;
use Zend_Auth_Storage_NonPersistent;

/**
 * The user lookup endpoints and the user list page must refuse callers who
 * cannot manage papers, independently of the ACL layer.
 */
final class UserDefaultControllerLookupAccessTest extends TestCase
{
    private const LOOKUP_ACTIONS = [
        'findcasusersAction',
        'ajaxfindusersbymailAction',
        'findusersbyfirstnameandnameAction',
        'ajaxfindcasuserAction',
    ];

    private Zend_Auth_Storage_Interface $previousAuthStorage;

    /** @var array<mixed> */
    private array $previousGet;

    /** @var list<array{string, array<mixed>}> helper calls recorded by the broker stub */
    private array $helperCalls = [];

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php';
    }

    protected function setUp(): void
    {
        $this->previousAuthStorage = Episciences_Auth::getInstance()->getStorage();
        Episciences_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
        Episciences_Auth::getInstance()->clearIdentity();
        $this->previousGet = $_GET;
        $_GET = [];
    }

    protected function tearDown(): void
    {
        Episciences_Auth::getInstance()->clearIdentity();
        Episciences_Auth::getInstance()->setStorage($this->previousAuthStorage);
        $_GET = $this->previousGet;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function lookupActionProvider(): array
    {
        $cases = [];
        foreach (self::LOOKUP_ACTIONS as $action) {
            $cases[$action] = [$action];
        }

        return $cases;
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function roleWithoutPaperManagementProvider(): array
    {
        $cases = [];
        foreach ([
                     Episciences_Acl::ROLE_MEMBER,
                     Episciences_Acl::ROLE_AUTHOR,
                     Episciences_Acl::ROLE_REVIEWER,
                     Episciences_Acl::ROLE_COPY_EDITOR,
                     Episciences_Acl::ROLE_WEBMASTER,
                 ] as $role) {
            foreach (self::LOOKUP_ACTIONS as $action) {
                $cases["$role on $action"] = [$role, $action];
            }
        }

        return $cases;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function paperManagerRoleProvider(): array
    {
        return [
            'guest editor' => [Episciences_Acl::ROLE_GUEST_EDITOR],
            'secretary' => [Episciences_Acl::ROLE_SECRETARY],
            'editor' => [Episciences_Acl::ROLE_EDITOR],
        ];
    }

    /**
     * @dataProvider lookupActionProvider
     */
    public function testAnonymousCallerGetsForbiddenAndNoData(string $action): void
    {
        [$response, $output] = $this->runAction($action);

        self::assertSame(403, $response->getHttpResponseCode());
        self::assertSame('', $output);
        self::assertSame('[]', $response->getBody());
    }

    /**
     * @dataProvider roleWithoutPaperManagementProvider
     */
    public function testLoggedInCallerWithoutPaperManagementRoleGetsForbidden(string $role, string $action): void
    {
        $this->logInAs($role);

        [$response, $output] = $this->runAction($action);

        self::assertSame(403, $response->getHttpResponseCode());
        self::assertSame('', $output);
        self::assertSame('[]', $response->getBody());
    }

    /**
     * Without any "term", the CAS user search answers an empty list before
     * reaching the database: it shows that the guard let the request through.
     *
     * @dataProvider paperManagerRoleProvider
     */
    public function testPaperManagerPassesTheGuard(string $role): void
    {
        $this->logInAs($role);

        [$response, $output] = $this->runAction('findcasusersAction');

        self::assertSame(200, $response->getHttpResponseCode());
        self::assertSame('[]', $output);
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function userListDeniedRoleProvider(): array
    {
        return [
            'anonymous' => [null],
            'reviewer' => [Episciences_Acl::ROLE_REVIEWER],
            'guest editor' => [Episciences_Acl::ROLE_GUEST_EDITOR],
        ];
    }

    /**
     * @dataProvider userListDeniedRoleProvider
     */
    public function testUserListRedirectsCallerWhoIsNeitherSecretaryNorEditor(?string $role): void
    {
        if ($role !== null) {
            $this->logInAs($role);
        }

        $this->runAction('listAction');

        self::assertContains(['gotoUrl', ['/error/deny']], $this->helperCalls);
    }

    private function logInAs(string $role): void
    {
        $user = $this->createMock(Episciences_User::class);
        $user->method('getAllRoles')->willReturn([RVID => [$role]]);
        Episciences_Auth::getInstance()->getStorage()->write($user);
    }

    /**
     * @return array{\Zend_Controller_Response_Http, string} the response and the echoed output
     */
    private function runAction(string $action): array
    {
        $controller = (new ReflectionClass(\UserDefaultController::class))->newInstanceWithoutConstructor();

        $response = new \Zend_Controller_Response_Http();
        $request = new \Zend_Controller_Request_Http();
        $request->setParam('term', 'a');

        $reflection = new ReflectionClass(\Zend_Controller_Action::class);
        foreach (['_request' => $request, '_response' => $response, '_helper' => $this->helperBrokerStub()] as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($controller, $value);
        }

        ob_start();
        try {
            $controller->{$action}();
        } finally {
            $output = (string) ob_get_clean();
        }

        return [$response, $output];
    }

    /**
     * Stands in for the action helper broker: every helper accepts any call,
     * records it and returns itself, so layout / view renderer calls are no-ops.
     */
    private function helperBrokerStub(): object
    {
        $record = function (string $name, array $arguments): void {
            $this->helperCalls[] = [$name, $arguments];
        };

        return new class ($record) {
            public function __construct(private readonly \Closure $record)
            {
            }

            /**
             * @param array<mixed> $arguments
             */
            public function __call(string $name, array $arguments): self
            {
                ($this->record)($name, $arguments);

                return $this;
            }

            public function __get(string $name): self
            {
                return $this;
            }
        };
    }
}
