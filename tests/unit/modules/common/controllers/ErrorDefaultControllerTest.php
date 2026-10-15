<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Behavioural tests for ErrorDefaultController::redactSensitiveParams().
 *
 * The method masks credential-like request parameters before they are written to
 * the application log. It is pure (array walk, no $this state, no DB, no MVC stack),
 * so the controller is instantiated without its constructor and the public method is
 * called directly.
 */
final class ErrorDefaultControllerTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/ErrorDefaultController.php';
        $class = new ReflectionClass(\ErrorDefaultController::class);
        $this->controller = $class->newInstanceWithoutConstructor();

        $bootstrap = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getOption'])
            ->getMock();
        $bootstrap->method('getOption')->willReturn(['db' => ['params' => ['profiler' => false]]]);

        $ref = new \ReflectionProperty(\Zend_Controller_Action::class, '_invokeArgs');
        $ref->setAccessible(true);
        $ref->setValue($this->controller, ['bootstrap' => $bootstrap]);
    }

    public function testCredentialLikeKeysAreMasked(): void
    {
        $in = [
            'username' => 'alice',
            'PASSWORD' => 'secret',
            'previous_password' => 'old',
            'API_PASSWORD' => 'k',
            'token' => 't',
        ];

        $out = $this->controller->redactSensitiveParams($in);

        self::assertSame('alice', $out['username'], 'non-sensitive values are kept');
        self::assertSame('***', $out['PASSWORD']);
        self::assertSame('***', $out['previous_password']);
        self::assertSame('***', $out['API_PASSWORD']);
        self::assertSame('***', $out['token']);
    }

    public function testMaskingIsCaseInsensitive(): void
    {
        $out = $this->controller->redactSensitiveParams(['PwD' => 'x', 'Secret' => 'y']);
        self::assertSame('***', $out['PwD']);
        self::assertSame('***', $out['Secret']);
    }

    public function testKeysContainingSensitiveFragmentsAreMasked(): void
    {
        $in = [
            'PASSWORD_VERIFY' => 'a',
            'API_PASSWORD_VERIFY' => 'b',
            'paperPassword' => 'c',
            'search_doc' => ['paperPassword' => 'd', 'title' => 'T'],
            'csrf' => 'e',
            'keywords' => 'kept',
        ];

        $out = $this->controller->redactSensitiveParams($in);

        self::assertSame('***', $out['PASSWORD_VERIFY']);
        self::assertSame('***', $out['API_PASSWORD_VERIFY']);
        self::assertSame('***', $out['paperPassword']);
        self::assertSame('***', $out['search_doc']['paperPassword']);
        self::assertSame('T', $out['search_doc']['title']);
        self::assertSame('***', $out['csrf']);
        self::assertSame('kept', $out['keywords']);
    }

    public function testSensitiveKeyHoldingAnArrayIsMaskedEntirely(): void
    {
        $out = $this->controller->redactSensitiveParams(['password' => ['a', 'b']]);
        self::assertSame('***', $out['password']);
    }

    public function testGenericNamesAreMaskedOnExactMatchOnly(): void
    {
        $out = $this->controller->redactSensitiveParams([
            'code' => '5f0c1a2b3d4e5',
            'ACCESS_CODE' => '5f0c1a2b3d4e5',
            'user_access_code' => 'kept',
            'language_code' => 'fr',
        ]);

        self::assertSame('***', $out['code']);
        self::assertSame('***', $out['ACCESS_CODE']);
        self::assertSame('kept', $out['user_access_code']);
        self::assertSame('fr', $out['language_code']);
    }

    public function testRoleNamesContainingAFragmentAreNotMasked(): void
    {
        $out = $this->controller->redactSensitiveParams([
            'roles' => ['secretary' => '1', 'secretaries' => ['12']],
            'client_secret' => 'y',
        ]);

        self::assertSame('1', $out['roles']['secretary']);
        self::assertSame(['12'], $out['roles']['secretaries']);
        self::assertSame('***', $out['client_secret']);
    }

    public function testErrorPageReceivesRedactedParameters(): void
    {
        // The failing request, as captured by the error handler plugin
        $failedRequest = new \Zend_Controller_Request_Http();
        $failedRequest->setParam('password', 'secret');
        $failedRequest->setParam('title', 'T');

        $errorHandler = new \ArrayObject([
            'type' => \Zend_Controller_Plugin_ErrorHandler::EXCEPTION_OTHER,
            'request' => $failedRequest,
            'exception' => new \Exception('Failure'),
        ], \ArrayObject::ARRAY_AS_PROPS);

        $errorRequest = new \Zend_Controller_Request_Http();
        $errorRequest->setParam('error_handler', $errorHandler);

        $this->controller->setRequest($errorRequest);
        $this->controller->setResponse(new \Zend_Controller_Response_Http());
        $this->controller->view = new \Zend_View();

        $this->controller->errorAction();

        self::assertSame('***', $this->controller->view->requestParams['password']);
        self::assertSame('T', $this->controller->view->requestParams['title']);
    }

    public function testNestedParametersAreMasked(): void
    {
        $in = ['form' => ['email' => 'a@b.c', 'password' => 'secret']];
        $out = $this->controller->redactSensitiveParams($in);

        self::assertSame('a@b.c', $out['form']['email']);
        self::assertSame('***', $out['form']['password']);
    }

    public function testNonSensitiveArrayIsUnchanged(): void
    {
        $in = ['controller' => 'user', 'action' => 'login', 'id' => '42'];
        self::assertSame($in, $this->controller->redactSensitiveParams($in));
    }

    public function testErrorActionSets403ForAccessDenied(): void
    {
        $request = new \Zend_Controller_Request_Http();
        $request->setParam('error_message', "Accès refusé");
        $request->setParam('error_description', "Vous ne disposez pas des droits nécessaires pour accéder à cette page.");

        $response = new \Zend_Controller_Response_Http();

        $this->controller->setRequest($request);
        $this->controller->setResponse($response);
        $this->controller->view = new \Zend_View();

        $this->controller->errorAction();

        self::assertSame(403, $response->getHttpResponseCode());
        self::assertSame("Accès refusé", $this->controller->view->message);
        self::assertSame("Vous ne disposez pas des droits nécessaires pour accéder à cette page.", $this->controller->view->description);
    }

    public function testErrorActionDoesNotSet403WhenErrorHandlerIsPresent(): void
    {
        $request = new \Zend_Controller_Request_Http();
        $response = new \Zend_Controller_Response_Http();

        $errorHandler = new \ArrayObject([
            'type' => \Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE,
            'request' => $request,
            'exception' => new \Exception("Route not found")
        ], \ArrayObject::ARRAY_AS_PROPS);

        $request->setParam('error_handler', $errorHandler);

        $this->controller->setRequest($request);
        $this->controller->setResponse($response);
        $this->controller->view = new \Zend_View();

        $this->controller->errorAction();

        // Should set 404 because of EXCEPTION_NO_ROUTE, not 403
        self::assertSame(404, $response->getHttpResponseCode());
    }
}
