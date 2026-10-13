<?php

declare(strict_types=1);

namespace unit\library\Episciences\Controller\Plugin;

use Episciences\Controller\Plugin\ReadOnlyGate;
use Episciences\Maintenance\ReadOnlyMode;
use Episciences_Acl;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Request_Http;
use Zend_Controller_Response_Http;

/**
 * In read-only maintenance mode, only public pages that do not write to the database are dispatched.
 * The real acl.ini is used, so that a page opened to guests later is covered without touching the plugin.
 */
final class ReadOnlyGateTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup;

    /** @var array<string, mixed> */
    private array $postBackup;

    private static ?Episciences_Acl $acl = null;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->postBackup = $_POST;
        ReadOnlyMode::setOverride(true);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_POST = $this->postBackup;
        ReadOnlyMode::setOverride(null);
    }

    /**
     * @dataProvider publicPageProvider
     */
    public function testPublicPagesAreServed(string $controller, string $action, string $method): void
    {
        $request = $this->dispatch($controller, $action, $method);

        self::assertSame($controller, $request->getControllerName());
        self::assertSame($action, $request->getActionName());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function publicPageProvider(): array
    {
        return [
            'paper notice' => ['paper', 'view', 'GET'],
            'paper pdf' => ['paper', 'pdf', 'GET'],
            'paper notice, HEAD' => ['paper', 'view', 'HEAD'],
            'oa file' => ['file', 'oafiles', 'GET'],
            'volume' => ['volume', 'view', 'GET'],
            'search' => ['search', 'index', 'GET'],
            'bibtex export' => ['export', 'bibtex', 'GET'],
            'rss' => ['rss', 'papers', 'GET'],
            'logout' => ['user', 'logout', 'GET'],
            'return from the CAS logout' => ['user', 'logoutfromcas', 'GET'],
        ];
    }

    /**
     * @dataProvider deniedPageProvider
     */
    public function testWritingOrNonPublicPagesAreDenied(string $controller, string $action, string $method): void
    {
        $request = $this->dispatch($controller, $action, $method);

        self::assertSame(ReadOnlyGate::DENIED_CONTROLLER, $request->getControllerName());
        self::assertSame(ReadOnlyGate::DENIED_ACTION, $request->getActionName());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function deniedPageProvider(): array
    {
        return [
            'POST on a public page' => ['paper', 'view', 'POST'],
            'login (CAS callback writes the user)' => ['user', 'login', 'GET'],
            'account activation link' => ['user', 'activate', 'GET'],
            'account creation' => ['user', 'create', 'GET'],
            'password reset link' => ['user', 'resetpassword', 'GET'],
            'reviewer invitation link' => ['reviewer', 'invitation', 'GET'],
            'account merge API' => ['api', 'merge', 'GET'],
            'editorial page' => ['administratepaper', 'view', 'GET'],
            'reviewer rating page' => ['paper', 'rating', 'GET'],
        ];
    }

    public function testEditorialPagesAreDeniedWhateverTheResourceSpelling(): void
    {
        $request = $this->dispatch('administrate-paper', 'VIEW', 'GET');

        self::assertSame(ReadOnlyGate::DENIED_ACTION, $request->getActionName());
    }

    public function testDeniedPageIsNeverRedirectedAgain(): void
    {
        $request = $this->dispatch(ReadOnlyGate::DENIED_CONTROLLER, ReadOnlyGate::DENIED_ACTION, 'POST');

        self::assertSame(ReadOnlyGate::DENIED_CONTROLLER, $request->getControllerName());
        self::assertSame(ReadOnlyGate::DENIED_ACTION, $request->getActionName());
    }

    public function testUnknownResourceIsLeftToTheAuthPlugin(): void
    {
        $request = $this->dispatch('nosuchcontroller', 'nosuchaction', 'GET');

        self::assertSame('nosuchcontroller', $request->getControllerName());
    }

    public function testNothingChangesOutsideReadOnlyMode(): void
    {
        ReadOnlyMode::setOverride(false);

        $request = $this->dispatch('administratepaper', 'view', 'POST');

        self::assertSame('administratepaper', $request->getControllerName());
        self::assertSame('view', $request->getActionName());
    }

    public function testOaiModuleIsNotFiltered(): void
    {
        $request = $this->dispatch('index', 'index', 'POST', OAI);

        self::assertSame('index', $request->getControllerName());
    }

    public function testLanguageSwitchIsTurnedIntoARedirectToTheSamePage(): void
    {
        $_POST = ['lang' => 'en'];
        $response = new Zend_Controller_Response_Http();

        $request = $this->dispatch('volume', 'view', 'POST', null, '/volume/view/id/12?page=2', $response);

        self::assertTrue($response->isRedirect());
        self::assertSame(303, $response->getHttpResponseCode());
        self::assertContains(['name' => 'Location', 'value' => '/volume/view/id/12?page=2', 'replace' => true], $response->getHeaders());
        self::assertSame(ReadOnlyGate::DENIED_ACTION, $request->getActionName());
    }

    public function testLanguageSwitchNeverRedirectsToAnotherHost(): void
    {
        $_POST = ['lang' => 'en'];
        $response = new Zend_Controller_Response_Http();

        $this->dispatch('volume', 'view', 'POST', null, '//evil.example.org/', $response);

        self::assertContains(['name' => 'Location', 'value' => '/', 'replace' => true], $response->getHeaders());
    }

    public function testPostWithOtherFieldsIsNotALanguageSwitch(): void
    {
        $_POST = ['lang' => 'en', 'comment' => 'x'];
        $response = new Zend_Controller_Response_Http();

        $request = $this->dispatch('paper', 'view', 'POST', null, '/123', $response);

        self::assertFalse($response->isRedirect());
        self::assertSame(ReadOnlyGate::DENIED_ACTION, $request->getActionName());
    }

    private function dispatch(
        string $controller,
        string $action,
        string $method,
        ?string $module = null,
        string $uri = '/',
        ?Zend_Controller_Response_Http $response = null
    ): Zend_Controller_Request_Http {
        $_SERVER['REQUEST_METHOD'] = $method;

        $request = new Zend_Controller_Request_Http();
        $request->setRequestUri($uri);
        $request->setControllerName($controller);
        $request->setActionName($action);

        $plugin = new ReadOnlyGate(self::acl(), $module ?? PORTAL);
        $plugin->setResponse($response ?? new Zend_Controller_Response_Http());
        $plugin->preDispatch($request);

        return $request;
    }

    private static function acl(): Episciences_Acl
    {
        if (self::$acl === null) {
            self::$acl = new Episciences_Acl();
            self::$acl->loadRoles();
            self::$acl->loadDefaultAcl();
        }

        return self::$acl;
    }
}
