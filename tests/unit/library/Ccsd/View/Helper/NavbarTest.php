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

    protected function setUp(): void
    {
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
