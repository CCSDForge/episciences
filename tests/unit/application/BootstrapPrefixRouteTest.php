<?php

declare(strict_types=1);

namespace unit\application;

use Bootstrap;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Front;
use Zend_Controller_Request_Abstract;
use Zend_Controller_Request_Http;
use Zend_Controller_Router_Interface;
use Zend_Controller_Router_Rewrite;
use Zend_View_Helper_Url;

/**
 * Default route of journals served behind a "/<journal code>/" path prefix
 * (Bootstrap::createJournalPrefixRoute()): URLs assembled from it carry each parameter once.
 * The router is built here: the test environment does not serve journals behind a prefix,
 * so Bootstrap::_initRoutes() does not register this route.
 */
final class BootstrapPrefixRouteTest extends TestCase
{
    private const PREFIX_ROUTE = 'rv-code';

    private ?Zend_Controller_Router_Interface $previousRouter = null;
    private ?Zend_Controller_Request_Abstract $previousRequest = null;

    protected function setUp(): void
    {
        $front = Zend_Controller_Front::getInstance();
        $this->previousRouter = $front->getRouter();
        $this->previousRequest = $front->getRequest();

        $router = new Zend_Controller_Router_Rewrite();
        $router->removeDefaultRoutes();
        $router->addRoute('default', Bootstrap::createJournalPrefixRoute(self::PREFIX_ROUTE));
        $front->setRouter($router);
    }

    protected function tearDown(): void
    {
        $front = Zend_Controller_Front::getInstance();
        if ($this->previousRouter !== null) {
            $front->setRouter($this->previousRouter);
        }
        if ($this->previousRequest !== null) {
            $front->setRequest($this->previousRequest);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function currentRequestProvider(): array
    {
        return [
            'search of a document to submit' => ['/epijinfo/submit/getdoc/'],
            'paper page' => ['/epijinfo/paper/view/id/1234'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('currentRequestProvider')]
    public function testUrlCarriesEachParameterOnce(string $currentPath): void
    {
        $this->routeCurrentRequest($currentPath);

        $url = (new Zend_View_Helper_Url())->url(['controller' => 'paper', 'action' => 'view', 'id' => 2632]);

        self::assertSame('/epijinfo/paper/view/id/2632', $url);
    }

    public function testRequestParametersAreMatched(): void
    {
        $request = $this->routeCurrentRequest('/epijinfo/paper/view/id/2632/');

        self::assertSame('epijinfo', $request->getParam(self::PREFIX_ROUTE));
        self::assertSame('paper', $request->getControllerName());
        self::assertSame('view', $request->getActionName());
        self::assertSame('2632', $request->getParam('id'));
    }

    private function routeCurrentRequest(string $path): Zend_Controller_Request_Http
    {
        $front = Zend_Controller_Front::getInstance();
        $request = new Zend_Controller_Request_Http('https://journal.example.org' . $path);
        $request->setBaseUrl('');
        $front->setRequest($request);
        $front->getRouter()->route($request);

        return $request;
    }
}
