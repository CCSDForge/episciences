<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;
use WebsiteDefaultController;
use Zend_Controller_Request_HttpTestCase;
use Zend_Session;
use Zend_Session_Namespace;

/**
 * Behavioural tests of the CSRF check shared by the website administration actions.
 */
class WebsiteDefaultControllerCsrfTest extends TestCase
{
    private const TOKEN = 'expected-token';

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/WebsiteDefaultController.php';
        Zend_Session::$_unitTestEnabled = true;
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->csrfToken = self::TOKEN;
    }

    protected function tearDown(): void
    {
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->csrfToken);
    }

    private function validate(Zend_Controller_Request_HttpTestCase $request): bool
    {
        $controller = (new \ReflectionClass(WebsiteDefaultController::class))->newInstanceWithoutConstructor();
        $request->setMethod('POST');

        $setRequest = new \ReflectionProperty(\Zend_Controller_Action::class, '_request');
        $setRequest->setAccessible(true);
        $setRequest->setValue($controller, $request);

        $method = new \ReflectionMethod($controller, '_validateCsrf');
        $method->setAccessible(true);

        return $method->invoke($controller);
    }

    public function testPostWithoutTokenIsRejected(): void
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setPost(['method' => 'remove', 'name' => 'style.css']);

        $this->assertFalse($this->validate($request));
    }

    public function testPostWithWrongTokenIsRejected(): void
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setPost(['method' => 'remove', 'csrf_token' => 'forged']);

        $this->assertFalse($this->validate($request));
    }

    public function testPostWithSessionTokenIsAccepted(): void
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setPost(['method' => 'remove', 'csrf_token' => self::TOKEN]);

        $this->assertTrue($this->validate($request));
    }
}
