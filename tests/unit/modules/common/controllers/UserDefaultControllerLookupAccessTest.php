<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The user lookup endpoints must refuse callers who cannot manage papers,
 * independently of the ACL layer.
 */
final class UserDefaultControllerLookupAccessTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function lookupActionProvider(): array
    {
        $cases = [];
        foreach ([
                     'findcasusersAction',
                     'findusersAction',
                     'ajaxfindusersbymailAction',
                     'findusersbyfirstnameandnameAction',
                     'ajaxfindcasuserAction',
                     'getmailsAction',
                 ] as $action) {
            $cases[$action] = [$action];
        }

        return $cases;
    }

    /**
     * @dataProvider lookupActionProvider
     */
    public function testAnonymousCallerGetsForbiddenAndNoData(string $action): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php';

        $controller = (new ReflectionClass(\UserDefaultController::class))->newInstanceWithoutConstructor();

        $response = new \Zend_Controller_Response_Http();
        $request = new \Zend_Controller_Request_Http();
        $request->setParam('term', 'a');

        $reflection = new ReflectionClass(\Zend_Controller_Action::class);
        foreach (['_request' => $request, '_response' => $response] as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($controller, $value);
        }
        $helper = $reflection->getProperty('_helper');
        $helper->setAccessible(true);
        $helper->setValue($controller, self::helperBrokerStub());

        ob_start();
        $controller->{$action}();
        $output = ob_get_clean();

        self::assertSame(403, $response->getHttpResponseCode());
        self::assertSame('', (string) $output);
        self::assertSame('[]', $response->getBody());
    }

    /**
     * Stands in for the action helper broker: every helper accepts any call and
     * returns itself, so layout / view renderer calls are no-ops.
     */
    private static function helperBrokerStub(): object
    {
        return new class {
            /**
             * @param array<mixed> $arguments
             */
            public function __call(string $name, array $arguments): self
            {
                return $this;
            }

            public function __get(string $name): self
            {
                return $this;
            }
        };
    }
}
