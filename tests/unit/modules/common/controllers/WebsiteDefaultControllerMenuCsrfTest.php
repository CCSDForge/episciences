<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;
use WebsiteDefaultController;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Session;
use Zend_Session_Namespace;

/**
 * Behavioural tests of the form token check of WebsiteDefaultController::menuAction().
 */
class WebsiteDefaultControllerMenuCsrfTest extends TestCase
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

    /**
     * Runs menuAction() on a POST and returns the navigation double it was able to reach.
     *
     * @param array<string, mixed> $post
     */
    private function postMenu(array $post): object
    {
        // Any call on this double means the submitted pages were processed
        $website = new class {
            public int $calls = 0;

            /**
             * @param array<int, mixed> $arguments
             */
            public function __call(string $name, array $arguments): mixed
            {
                $this->calls++;
                return null;
            }
        };

        $controller = new class (new Zend_Controller_Request_HttpTestCase(), new Zend_Controller_Response_HttpTestCase()) extends WebsiteDefaultController {
            public bool $redirected = false;

            /**
             * @param array<string, mixed> $options
             */
            public function redirect($url, array $options = []): void
            {
                $this->redirected = true;
            }
        };

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST');
        $request->setPost($post);

        $setProperty = static function (string $name, $value) use ($controller): void {
            $property = new \ReflectionProperty(\Zend_Controller_Action::class, $name);
            $property->setAccessible(true);
            $property->setValue($controller, $value);
        };
        $setProperty('_request', $request);

        $sessionProperty = new \ReflectionProperty(WebsiteDefaultController::class, '_session');
        $sessionProperty->setAccessible(true);
        // A plain holder: a real session namespace would try to serialize the anonymous double
        $sessionProperty->setValue($controller, (object)['website' => $website]);

        $controller->menuAction();

        return (object)['website' => $website, 'redirected' => $controller->redirected];
    }

    public function testPostWithoutTokenIsRejectedBeforeAnyChange(): void
    {
        $result = $this->postMenu(['pages_1' => ['visibility' => '0']]);

        $this->assertTrue($result->redirected);
        $this->assertSame(0, $result->website->calls);
    }

    public function testPostWithWrongTokenIsRejectedBeforeAnyChange(): void
    {
        $result = $this->postMenu(['csrf_token' => 'forged', 'pages_1' => ['visibility' => '0']]);

        $this->assertTrue($result->redirected);
        $this->assertSame(0, $result->website->calls);
    }
}
