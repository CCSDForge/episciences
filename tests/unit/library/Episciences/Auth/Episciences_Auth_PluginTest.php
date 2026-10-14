<?php

declare(strict_types=1);

use Episciences\Maintenance\ReadOnlyMode;
use PHPUnit\Framework\TestCase;

/**
 * @covers Episciences_Auth_Plugin
 */
class Episciences_Auth_PluginTest extends TestCase
{
    private Episciences_Auth_Plugin $plugin;

    protected function setUp(): void
    {
        $acl = new Zend_Acl();
        foreach (['user-findusers', 'api-openaire-metrics', 'file-index', 'my-ctrl-index', 'dup-openaire-metrics', 'dup-openairemetrics'] as $resource) {
            $acl->addResource(new Zend_Acl_Resource($resource));
        }

        $this->plugin = new Episciences_Auth_Plugin();
        $property = new ReflectionProperty(Ccsd_Auth_Plugin::class, '_acl');
        $property->setAccessible(true);
        $property->setValue($this->plugin, $acl);
    }

    public function testExactMatchIsReturnedAsIs(): void
    {
        self::assertSame('user-findusers', $this->plugin->resolveResource('user', 'findusers'));
    }

    /**
     * @dataProvider variantProvider
     */
    public function testVariantsResolveToTheProtectedResource(string $controller, string $action, string $expected): void
    {
        self::assertSame($expected, $this->plugin->resolveResource($controller, $action));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function variantProvider(): array
    {
        return [
            'case variant' => ['user', 'findUsers', 'user-findusers'],
            'upper case controller' => ['User', 'FINDUSERS', 'user-findusers'],
            'delimiter variant' => ['user', 'find-users', 'user-findusers'],
            'dot and underscore variant' => ['user', 'find.users', 'user-findusers'],
            'camel case for hyphenated key' => ['api', 'openaireMetrics', 'api-openaire-metrics'],
        ];
    }

    public function testHyphenatedControllerVariantResolves(): void
    {
        self::assertSame('my-ctrl-index', $this->plugin->resolveResource('myCtrl', 'index'));
    }

    public function testAmbiguousVariantFailsClosed(): void
    {
        self::assertSame('dup-OpenaireMetrics', $this->plugin->resolveResource('dup', 'OpenaireMetrics'));
    }

    public function testExactMatchWinsOverAmbiguity(): void
    {
        self::assertSame('dup-openairemetrics', $this->plugin->resolveResource('dup', 'openairemetrics'));
    }

    public function testUnknownResourceKeepsRawKey(): void
    {
        self::assertSame('foo-bar', $this->plugin->resolveResource('foo', 'bar'));
    }

    public function testUnknownResourceIsDeniedForXhrRequests(): void
    {
        $request = new class extends Zend_Controller_Request_Http {
            public function isXmlHttpRequest(): bool
            {
                return true;
            }
        };
        $request->setControllerName('dup')->setActionName('OpenaireMetrics');

        $this->plugin->denyUnknownResource($request);

        self::assertSame(Ccsd_Auth_Plugin::FAIL_AUTH_CONTROLLER, $request->getControllerName());
        self::assertSame(Ccsd_Auth_Plugin::FAIL_AUTH_ACTION, $request->getActionName());
    }

    public function testUnknownResourceIsNotFoundForRegularRequests(): void
    {
        $request = new Zend_Controller_Request_Http();
        $request->setControllerName('foo')->setActionName('bar');

        $this->plugin->denyUnknownResource($request);

        self::assertSame('index', $request->getControllerName());
        self::assertSame('notfound', $request->getActionName());
    }

    public function testNormalizeName(): void
    {
        self::assertSame('findusers', Episciences_Auth_Plugin::normalizeName('Find-Users'));
        self::assertSame('a1b', Episciences_Auth_Plugin::normalizeName('A.1_b'));
    }

    /**
     * @return Episciences_Auth_Plugin plugin whose ACL declares the "user-findusers" resource only
     */
    private function pluginWithAclStub(): Episciences_Auth_Plugin
    {
        $acl = $this->createMock(Episciences_Acl::class);
        $acl->method('has')->willReturnCallback(static fn($resource): bool => $resource === 'user-findusers');
        $acl->method('getResources')->willReturn(['user-findusers']);

        return new class($acl) extends Episciences_Auth_Plugin {
            public function __construct(private readonly Episciences_Acl $stub)
            {
            }

            public function getAcl(): ?Episciences_Acl
            {
                return $this->stub;
            }
        };
    }

    public function testPreDispatchDeniesUnknownResourceForXhrRequests(): void
    {
        $request = new class extends Zend_Controller_Request_Http {
            public function isXmlHttpRequest(): bool
            {
                return true;
            }
        };
        $request->setControllerName('website')->setActionName('notdeclared');

        $this->pluginWithAclStub()->preDispatch($request);

        self::assertSame(Ccsd_Auth_Plugin::FAIL_AUTH_CONTROLLER, $request->getControllerName());
        self::assertSame(Ccsd_Auth_Plugin::FAIL_AUTH_ACTION, $request->getActionName());
    }

    public function testPreDispatchSendsUnknownResourceToNotFoundForRegularRequests(): void
    {
        $request = new Zend_Controller_Request_Http();
        $request->setControllerName('website')->setActionName('notdeclared');

        $this->pluginWithAclStub()->preDispatch($request);

        self::assertSame('index', $request->getControllerName());
        self::assertSame('notfound', $request->getActionName());
    }

    /**
     * Run preDispatch() against the ACL of the application for a visitor holding the given roles
     * (none: not logged in) and return the controller and action the request ends up on.
     *
     * @param list<string> $roles
     * @return array{string, string}
     */
    private function dispatchWithApplicationAcl(string $controller, string $action, array $roles, bool $xhr = false, int $uid = 4242): array
    {
        $acl = new Episciences_Acl(); // acl.ini and the navigation files, as loaded by the application
        $acl->loadFromNavigation([APPLICATION_PATH . '/configs/' . APPLICATION_MODULE . '.navigation.json']);

        $previousStorage = Zend_Auth::getInstance()->getStorage();
        Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
        try {
            if ($roles !== []) {
                $user = new Episciences_User(['UID' => $uid]);
                $user->setRoles([RVID => $roles]); // roles are indexed by journal
                Zend_Auth::getInstance()->getStorage()->write($user);
            }

            $request = $xhr
                ? new class extends Zend_Controller_Request_Http {
                    public function isXmlHttpRequest(): bool
                    {
                        return true;
                    }
                }
                : new Zend_Controller_Request_Http();
            $request->setControllerName($controller)->setActionName($action);
            $plugin = new class($acl) extends Episciences_Auth_Plugin {
                public function __construct(private readonly Episciences_Acl $applicationAcl)
                {
                }

                public function getAcl(): Episciences_Acl
                {
                    return $this->applicationAcl;
                }
            };
            $plugin->preDispatch($request);

            return [$request->getControllerName(), $request->getActionName()];
        } finally {
            Zend_Auth::getInstance()->setStorage($previousStorage);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function restrictedActionProvider(): array
    {
        return [
            'exact spelling' => ['user', 'findusers'],
            'case variant' => ['user', 'findUsers'],
            'upper case' => ['USER', 'FINDUSERS'],
            'delimiter variant' => ['user', 'find-users'],
        ];
    }

    /**
     * Every spelling of a restricted action reaches the same ACL resource: a visitor who is not
     * logged in is sent to the login page, never to the action.
     *
     * @dataProvider restrictedActionProvider
     */
    public function testEverySpellingOfARestrictedActionIsDeniedToAGuest(string $controller, string $action): void
    {
        [$resolvedController, $resolvedAction] = $this->dispatchWithApplicationAcl($controller, $action, []);

        self::assertSame(['user', 'login'], [$resolvedController, $resolvedAction]);
    }

    /**
     * @dataProvider restrictedActionProvider
     */
    public function testEverySpellingOfARestrictedActionIsDeniedToAMemberWithoutTheRole(string $controller, string $action): void
    {
        [$resolvedController, $resolvedAction] = $this->dispatchWithApplicationAcl($controller, $action, [Episciences_Acl::ROLE_MEMBER]);

        self::assertSame([Ccsd_Auth_Plugin::FAIL_AUTH_CONTROLLER, Ccsd_Auth_Plugin::FAIL_AUTH_ACTION], [$resolvedController, $resolvedAction]);
    }

    /**
     * The accepted papers list is only an ACL resource when the journal navigation declares it:
     * without it, a client-supplied X-Requested-With header must not let the request through.
     */
    public function testUndeclaredAcceptedPapersListIsDeniedToAGuestSendingAnXhrHeader(): void
    {
        [$resolvedController, $resolvedAction] = $this->dispatchWithApplicationAcl('browse', 'accepted-docs', [], true);

        self::assertSame([Ccsd_Auth_Plugin::FAIL_AUTH_CONTROLLER, Ccsd_Auth_Plugin::FAIL_AUTH_ACTION], [$resolvedController, $resolvedAction]);
    }

    public function testMemberWithoutLocalAccountDataIsSentToTheProfileForm(): void
    {
        // The redirection carries the journal path prefix (PREFIX_URL) on manager branches
        $expected = (defined('PREFIX_URL') ? PREFIX_URL : '/') . 'user/edit';

        self::assertSame($expected, $this->profileRedirectOfAMemberWithoutLocalData(false));
    }

    /**
     * In read-only maintenance mode the profile form is not served (503): the user must keep the public site.
     */
    public function testMemberWithoutLocalAccountDataIsNotSentToTheProfileFormInReadOnlyMode(): void
    {
        self::assertNull($this->profileRedirectOfAMemberWithoutLocalData(true));
    }

    /**
     * Run preDispatch() on a public page for a logged-in member whose account is not completed on Episciences,
     * and return the URL the redirector was asked to go to (null when no redirection was requested).
     */
    private function profileRedirectOfAMemberWithoutLocalData(bool $readOnly): ?string
    {
        $uid = 4242;
        $redirector = new class extends Zend_Controller_Action_Helper_Redirector {
            public ?string $url = null;

            public function getName(): string
            {
                return 'Redirector';
            }

            public function gotoUrl($url, array $options = []): void
            {
                $this->url = $url;
            }
        };
        Zend_Controller_Action_HelperBroker::addHelper($redirector);
        Episciences_User::setStaticCache($uid, []); // no local row: hasLocalData() is false without any query
        ReadOnlyMode::setOverride($readOnly);

        try {
            $this->dispatchWithApplicationAcl('paper', 'view', [Episciences_Acl::ROLE_MEMBER], false, $uid);

            return $redirector->url;
        } finally {
            ReadOnlyMode::setOverride(null);
            Episciences_User::forgetStaticCache($uid);
            Zend_Controller_Action_HelperBroker::removeHelper('redirector');
        }
    }
}
