<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use unit\modules\ControllerTestCase;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * After signing in, the account goes back to the page it came from with the parameters of that page
 * (e.g. the invitation id and its link token), whatever the account has an Episciences profile or not.
 */
final class UserDefaultControllerForwardedUrlParamsTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        self::loadController('common', 'UserDefaultController');
    }

    /**
     * @param array<string, mixed> $routeParams
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function forwardedParams(array $routeParams, array $query = [], array $post = []): array
    {
        // the request helpers start from empty superglobals
        $request = $post === [] ? $this->getRequest($query, $routeParams) : $this->postRequest($post, false, $routeParams)->setQuery($query);

        $controller = new class (new Zend_Controller_Request_HttpTestCase(), new Zend_Controller_Response_HttpTestCase()) extends \UserDefaultController {
            /**
             * @return array<string, mixed>
             */
            public function forwarded(): array
            {
                return $this->forwardedUrlParams();
            }
        };

        // set once built: the module of the login URL plays no part here
        $controller->setRequest($request);

        return $controller->forwarded();
    }

    public function testParametersOfTheReturnPageAreKept(): void
    {
        $params = $this->forwardedParams([
            'module' => 'journal',
            'controller' => 'user',
            'action' => 'login',
            'forward-controller' => 'reviewer',
            'forward-action' => 'invitation',
            'id' => '12',
            'token' => 'link-token',
            'confirm-acceptance' => '1',
        ], ['tmp' => 'legacy']);

        self::assertSame(['tmp' => 'legacy', 'id' => '12', 'token' => 'link-token', 'confirm-acceptance' => '1'], $params);
    }

    public function testPostedCredentialsAreNeverCarriedInTheUrl(): void
    {
        $params = $this->forwardedParams(
            ['forward-controller' => 'reviewer', 'forward-action' => 'invitation', 'id' => '12'],
            [],
            ['username' => 'jdoe', 'password' => 'MyS3cret-password']
        );

        self::assertSame(['id' => '12'], $params);
    }

    public function testCasTicketIsNotCarriedInTheUrl(): void
    {
        $params = $this->forwardedParams(['id' => '12'], ['ticket' => 'ST-1-abc']);

        self::assertSame(['id' => '12'], $params);
    }
}
