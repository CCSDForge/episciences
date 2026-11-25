<?php

declare(strict_types=1);

namespace tests\unit\library\Ccsd\View\Helper;

use Ccsd_View_Helper_Navbar;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Front;
use Zend_Controller_Request_Abstract;
use Zend_Controller_Request_Http;
use Zend_View;

/**
 * Rendering tests for the account area of the navbar ("accountActions" option, hidden in read-only maintenance mode).
 */
final class NavbarTest extends TestCase
{
    private ?Zend_Controller_Request_Abstract $previousRequest = null;

    /** @var array<string, mixed> */
    private array $previousGet = [];

    /** @var array<string, mixed> */
    private array $previousPost = [];

    protected function setUp(): void
    {
        // the request reads and writes the query and posted fields in the superglobals
        $this->previousGet = $_GET;
        $this->previousPost = $_POST;

        $front = Zend_Controller_Front::getInstance();
        $this->previousRequest = $front->getRequest();

        $request = new Zend_Controller_Request_Http();
        $request->setControllerName('paper')->setActionName('view');
        $front->setRequest($request);

        // The navbar builds its links with the url() helper, which needs the default route
        $router = $front->getRouter();
        if (!$router->hasRoute('default')) {
            $router->addDefaultRoutes();
        }
    }

    protected function tearDown(): void
    {
        $_GET = $this->previousGet;
        $_POST = $this->previousPost;

        // Restore the request only: resetting the front controller would drop the router other tests rely on
        $property = new \ReflectionProperty(Zend_Controller_Front::class, '_request');
        $property->setAccessible(true);
        $property->setValue(Zend_Controller_Front::getInstance(), $this->previousRequest);
    }

    public function testVisitorGetsTheLoginForm(): void
    {
        $html = $this->render(['isLogged' => false]);

        self::assertStringContainsString('id="form-login"', $html);
        self::assertStringNotContainsString('user/logout"', $html);
    }

    public function testLoginFormSendsThePageToReturnToInTheUrl(): void
    {
        $html = $this->render(['isLogged' => false]);

        // the login action reads the page to return to from the URL only, never from a posted body
        self::assertMatchesRegularExpression('#<form[^>]*id="form-login"[^>]*method="get"#', $html);
        self::assertStringContainsString('name="forward-controller" value="paper"', $html);
        self::assertStringContainsString('name="forward-action" value="view"', $html);
    }

    public function testLoginFormOnlyCarriesTheUrlParametersOfThePage(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = Zend_Controller_Front::getInstance()->getRequest();
        $request->setParam('id', '1');
        $request->setQuery('page', '2');
        $request->setPost(['EMAIL' => 'jdoe@example.org', 'PASSWORD' => 'MyS3cret-password']);

        $html = $this->render(['isLogged' => false]);

        self::assertStringContainsString('name="id" value="1"', $html);
        self::assertStringContainsString('name="page" value="2"', $html);
        self::assertStringNotContainsString('name="EMAIL"', $html);
        self::assertStringNotContainsString('name="PASSWORD"', $html);
        self::assertStringNotContainsString('MyS3cret-password', $html);
    }

    public function testVisitorGetsNoLoginFormWithoutAccountActions(): void
    {
        $html = $this->render(['isLogged' => false, 'accountActions' => false]);

        self::assertStringNotContainsString('id="form-login"', $html);
        self::assertStringNotContainsString('/user/create', $html);
        self::assertStringNotContainsString('user/logout"', $html);
    }

    public function testLoggedUserCanStillLogOutWithoutAccountActions(): void
    {
        $html = $this->render(['isLogged' => true, 'userRenderScript' => 'not-rendered.phtml', 'accountActions' => false]);

        // The URL is built by the url() helper: it may carry the journal path prefix on manager branches
        self::assertMatchesRegularExpression('#href="[^"]*/user/logout"#', $html);
        self::assertStringNotContainsString('id="form-login"', $html);
    }

    /**
     * @param array<string, mixed> $loginOptions
     */
    private function render(array $loginOptions): string
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/common/views/layout');

        $helper = new Ccsd_View_Helper_Navbar();
        $helper->setView($view);

        ob_start();
        try {
            $helper->navbar(false, false, true, $loginOptions);
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }
}
