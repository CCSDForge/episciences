<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Episciences\Maintenance\ReadOnlyMode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Zend_Controller_Action_Helper_ViewRenderer;
use Zend_Controller_Action_HelperBroker;
use Zend_Layout;
use Zend_Layout_Controller_Action_Helper_Layout;

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

    /** @var array<string, \Zend_Controller_Action_Helper_Abstract|null> static action helpers replaced by a test */
    private array $replacedHelpers = [];

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

    protected function tearDown(): void
    {
        ReadOnlyMode::setOverride(null);
        ReadOnlyMode::setScheduleOverride(null, null);
        foreach ($this->replacedHelpers as $name => $previous) {
            Zend_Controller_Action_HelperBroker::removeHelper($name);
            if ($previous !== null) {
                Zend_Controller_Action_HelperBroker::addHelper($previous);
            }
        }
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

    public function testReadonlyActionAnswersServiceUnavailableWithRetryAfter(): void
    {
        ReadOnlyMode::setOverride(true);
        // Empty dates, not nulls: nulls would fall back to the schedule configured in pwd.json
        ReadOnlyMode::setScheduleOverride('', '');
        [$layout, $viewRenderer] = $this->useActionHelpers();
        $response = new \Zend_Controller_Response_Http();
        $this->controller->setRequest(new \Zend_Controller_Request_Http());
        $this->controller->setResponse($response);

        $this->controller->readonlyAction();

        self::assertSame(503, $response->getHttpResponseCode());
        self::assertContains(
            ['name' => 'Retry-After', 'value' => (string) ReadOnlyMode::DEFAULT_RETRY_AFTER_SECONDS, 'replace' => true],
            $response->getHeaders()
        );
        self::assertTrue($layout->isEnabled(), 'the maintenance page is rendered in the site layout');
        self::assertFalse($viewRenderer->getNoRender());
    }

    public function testReadonlyActionAnswersWithoutLayoutToXmlHttpRequests(): void
    {
        ReadOnlyMode::setOverride(true);
        [$layout] = $this->useActionHelpers();
        $request = new \Zend_Controller_Request_Http();
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $response = new \Zend_Controller_Response_Http();
        $this->controller->setRequest($request);
        $this->controller->setResponse($response);

        try {
            $this->controller->readonlyAction();
        } finally {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }

        self::assertSame(503, $response->getHttpResponseCode());
        self::assertFalse($layout->isEnabled());
    }

    /**
     * The ReadOnlyGate plugin turns the language switcher POST into a 303 redirect to the same page:
     * it must be sent as is, without any body.
     */
    public function testReadonlyActionKeepsTheLanguageSwitchRedirect(): void
    {
        ReadOnlyMode::setOverride(true);
        [$layout, $viewRenderer] = $this->useActionHelpers();
        $response = new \Zend_Controller_Response_Http();
        $response->setRedirect('/volume/view/id/1', 303);
        $this->controller->setRequest(new \Zend_Controller_Request_Http());
        $this->controller->setResponse($response);

        $this->controller->readonlyAction();

        self::assertSame(303, $response->getHttpResponseCode());
        self::assertNotContains('Retry-After', array_column($response->getHeaders(), 'name'));
        self::assertFalse($layout->isEnabled());
        self::assertTrue($viewRenderer->getNoRender());
    }

    public function testReadonlyActionIsNotFoundOutsideReadOnlyMode(): void
    {
        ReadOnlyMode::setOverride(false);
        [, $viewRenderer] = $this->useActionHelpers();
        $response = new \Zend_Controller_Response_Http();
        $this->controller->setRequest(new \Zend_Controller_Request_Http());
        $this->controller->setResponse($response);
        $this->controller->view = new \Zend_View();

        $this->controller->readonlyAction();

        self::assertSame(404, $response->getHttpResponseCode());
        self::assertNotContains('Retry-After', array_column($response->getHeaders(), 'name'));
        self::assertSame('Page not found', $this->controller->view->message);
        self::assertSame('error', $viewRenderer->getScriptAction());
    }

    /**
     * Give the controller, instantiated without its constructor, an action helper broker
     * with a layout and a view renderer that can be inspected.
     *
     * @return array{Zend_Layout, Zend_Controller_Action_Helper_ViewRenderer}
     */
    private function useActionHelpers(): array
    {
        $layout = new Zend_Layout();
        // The view is never rendered here: skip the view initialisation, which needs a full MVC stack
        $viewRenderer = new class extends Zend_Controller_Action_Helper_ViewRenderer {
            public function init(): void
            {
            }

            public function getName(): string
            {
                return 'ViewRenderer';
            }
        };

        foreach (['Layout' => new Zend_Layout_Controller_Action_Helper_Layout($layout), 'ViewRenderer' => $viewRenderer] as $name => $helper) {
            $this->replacedHelpers[$name] = Zend_Controller_Action_HelperBroker::hasHelper($name)
                ? Zend_Controller_Action_HelperBroker::getExistingHelper($name)
                : null;
            Zend_Controller_Action_HelperBroker::addHelper($helper);
        }

        $broker = new \ReflectionProperty(\Zend_Controller_Action::class, '_helper');
        $broker->setAccessible(true);
        $broker->setValue($this->controller, new Zend_Controller_Action_HelperBroker($this->controller));

        return [$layout, $viewRenderer];
    }
}
