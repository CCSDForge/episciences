<?php

declare(strict_types=1);

namespace unit\modules\common\views;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Episciences_Csrf_Helper;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Zend_Controller_Front;
use Zend_Controller_Request_Http;
use Zend_Controller_Router_Route_Regex;
use Zend_View;

/**
 * Rendering tests for user/su_button.phtml: switching identity must be a POST carrying the
 * session request token, never a plain link.
 */
final class SuButtonPartialTest extends TestCase
{
    private Zend_View $view;

    private bool $requestWasSet = false;

    protected function setUp(): void
    {
        $this->view = new Zend_View();
        $this->view->setScriptPath(APPLICATION_PATH . '/modules/common/views/scripts');

        // The url() view helper needs a router holding the default route
        $front = Zend_Controller_Front::getInstance();
        if ($front->getRequest() === null) {
            $front->setRequest(new Zend_Controller_Request_Http('http://localhost/'));
            $this->requestWasSet = true;
        }
        $router = $front->getRouter();
        if (!$router->hasRoute('default')) {
            $router->addDefaultRoutes();
        }
    }

    protected function tearDown(): void
    {
        if ($this->requestWasSet) {
            $property = new ReflectionProperty(Zend_Controller_Front::class, '_request');
            $property->setAccessible(true);
            $property->setValue(Zend_Controller_Front::getInstance(), null);
        }
    }

    private function form(string $html): DOMElement
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_use_internal_errors($previous);

        $form = (new DOMXPath($document))->query('//form')->item(0);
        self::assertInstanceOf(DOMElement::class, $form);

        return $form;
    }

    private function input(DOMElement $form, string $name): string
    {
        $input = (new DOMXPath($form->ownerDocument))->query(".//input[@name='$name']", $form)->item(0);
        self::assertInstanceOf(DOMElement::class, $input, "missing '$name' field");

        return $input->getAttribute('value');
    }

    public function testRendersAPostFormToTheSuAction(): void
    {
        $html = $this->view->partial('user/su_button.phtml', ['uid' => 42]);

        $form = $this->form($html);
        self::assertSame('post', strtolower($form->getAttribute('method')));
        self::assertStringEndsWith('/user/su', $form->getAttribute('action'));
        self::assertSame('42', $this->input($form, 'uid'));
        self::assertStringNotContainsString('href=', $html);
    }

    public function testTargetsTheSuActionWhateverTheCurrentRoute(): void
    {
        // e.g. the paper page, served by the "paper" regex route
        $router = Zend_Controller_Front::getInstance()->getRouter();
        // Routing a request changes the router's current route: restore it for the next tests
        $currentRoute = new ReflectionProperty($router, '_currentRoute');
        $currentRoute->setAccessible(true);
        $previousRoute = $currentRoute->getValue($router);
        $router->addRoute('su_button_test_paper', new Zend_Controller_Router_Route_Regex(
            '(\\d+)',
            ['controller' => 'paper', 'action' => 'view'],
            ['id' => 1],
            '%d'
        ));
        $router->route(new Zend_Controller_Request_Http('http://localhost/123'));

        try {
            $form = $this->form($this->view->partial('user/su_button.phtml', ['uid' => 42]));
        } finally {
            $currentRoute->setValue($router, $previousRoute);
            $router->removeRoute('su_button_test_paper');
        }

        self::assertStringEndsWith('/user/su', $form->getAttribute('action'));
    }

    public function testCarriesTheSessionRequestToken(): void
    {
        $form = $this->form($this->view->partial('user/su_button.phtml', ['uid' => 42]));

        self::assertSame(Episciences_Csrf_Helper::getSessionToken(), $this->input($form, 'csrf_token'));
    }

    public function testTheUidIsAlwaysAnInteger(): void
    {
        $form = $this->form($this->view->partial('user/su_button.phtml', ['uid' => '7"><script>']));

        self::assertSame('7', $this->input($form, 'uid'));
    }

    public function testTheButtonHasAnAccessibleName(): void
    {
        $form = $this->form($this->view->partial('user/su_button.phtml', ['uid' => 42, 'buttonClass' => 'btn btn-link']));

        $button = $form->getElementsByTagName('button')->item(0);
        self::assertInstanceOf(DOMElement::class, $button);
        self::assertSame('submit', $button->getAttribute('type'));
        self::assertSame('btn btn-link', $button->getAttribute('class'));
        self::assertNotSame('', $button->getAttribute('aria-label'));
    }
}
