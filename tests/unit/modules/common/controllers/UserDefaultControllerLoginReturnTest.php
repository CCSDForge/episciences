<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use UserDefaultController;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

require_once APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php';

/**
 * Page displayed after logging in: only the parameters of the URL are carried over to it,
 * never the fields of the posted login form.
 */
final class UserDefaultControllerLoginReturnTest extends TestCase
{
    // the test requests write the query string and the posted form to $_GET and $_POST
    protected function setUp(): void
    {
        $_GET = $_POST = [];
    }

    protected function tearDown(): void
    {
        $_GET = $_POST = [];
    }

    /**
     * @param array<string, mixed> $route
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private static function returnParams(array $route, array $query = [], array $post = []): array
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setQuery($query)->setPost($post)->setParams($route);

        $controller = new UserDefaultController($request, new Zend_Controller_Response_HttpTestCase());
        $method = new ReflectionMethod(UserDefaultController::class, 'loginReturnParams');

        return $method->invoke($controller, $request);
    }

    public function testPostedLoginFormIsNotCarriedOver(): void
    {
        $params = self::returnParams(
            ['controller' => 'user', 'action' => 'login', 'forward-controller' => 'reviewer', 'forward-action' => 'invitation', 'id' => '1'],
            [],
            ['username' => 'jdoe', 'password' => 'secret', 'csrf_token' => 'abc', 'Connexion' => 'Sign in']
        );

        self::assertSame(
            ['controller' => 'user', 'action' => 'login', 'forward-controller' => 'reviewer', 'forward-action' => 'invitation', 'id' => '1'],
            $params
        );
    }

    public function testQueryStringIsCarriedOver(): void
    {
        $params = self::returnParams(
            ['controller' => 'user', 'action' => 'login'],
            ['forward-controller' => 'user', 'forward-action' => 'edit']
        );

        self::assertSame('user', $params['forward-controller']);
        self::assertSame('edit', $params['forward-action']);
    }

    /**
     * Same precedence as getParam(): a route parameter wins over the query string
     */
    public function testRouteParameterWinsOverQueryString(): void
    {
        $params = self::returnParams(['id' => '1'], ['id' => '2']);

        self::assertSame('1', $params['id']);
    }
}
