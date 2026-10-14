<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Ccsd_View_Helper_Message;
use Episciences_Csrf_Helper;
use Episciences_Section;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Action;
use Zend_Controller_Action_Helper_Redirector;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Session;
use Zend_Session_Namespace;

/**
 * Form token check of the rating grid and section administration actions.
 *
 * A request without the per-session token must change nothing, and the user must be told
 * the session has expired. A form is shown again empty: the values of a refused request may
 * come from another site, and must not be submitted again by a click of the user. The actions are called
 * for real on a partially mocked database adapter: nothing is executed.
 */
final class FormTokenRefusalTest extends TestCase
{
    private const EXPIRED = 'Votre session a expiré, veuillez réessayer.';

    private Zend_Db_Adapter_Abstract $previousAdapter;

    private bool $previousUnitTestEnabled;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    private Zend_Controller_Response_HttpTestCase $response;

    protected function setUp(): void
    {
        $this->previousUnitTestEnabled = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchRow', 'fetchCol', 'insert', 'update', 'delete'])
            ->getMock();
        $this->adapter->expects(self::never())->method('insert');
        $this->adapter->expects(self::never())->method('update');
        $this->adapter->expects(self::never())->method('delete');
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        // The section form has a title per language of the journal, named from the locale
        if (!\Zend_Registry::isRegistered('Zend_Locale')) {
            \Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
        }
        if (!\Zend_Registry::isRegistered('languages')) {
            \Zend_Registry::set('languages', ['en', 'fr']);
        }
        // The grid actions redirect to an action, which needs the default route
        $router = \Zend_Controller_Front::getInstance()->getRouter();
        if (!$router->hasRoute('default')) {
            $router->addDefaultRoutes();
        }

        // Messages left by the other tests of the run
        (new \Zend_Controller_Action_Helper_FlashMessenger())->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->clearCurrentMessages();

        // The session holds a token the requests below do not send
        Episciences_Csrf_Helper::getSessionToken();

        require_once APPLICATION_PATH . '/modules/journal/controllers/GridController.php';
        require_once APPLICATION_PATH . '/modules/journal/controllers/SectionController.php';
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);

        /** @var Zend_Controller_Action_Helper_Redirector $redirector */
        $redirector = \Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->setExit(true);

        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->csrfToken);
        $_POST = []; // setPost() writes the global

        Zend_Session::$_unitTestEnabled = $this->previousUnitTestEnabled;
    }

    /**
     * Call an action and return the controller, its output is kept in $output.
     *
     * @param class-string<Zend_Controller_Action> $class
     * @param array<string, mixed> $post
     * @param-out string $output
     */
    private function callAction(string $class, string $action, array $post, bool $isPost = true, ?string &$output = null): Zend_Controller_Action
    {
        $_POST = [];
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod($isPost ? 'POST' : 'GET');
        $request->setPost($isPost ? $post : []);
        $this->response = new Zend_Controller_Response_HttpTestCase();

        $controller = new $class($request, $this->response);
        $controller->getHelper('redirector')->setExit(false);

        ob_start();
        try {
            $controller->$action();
        } finally {
            $output = (string)ob_get_clean();
        }

        return $controller;
    }

    private function redirectUrl(): string
    {
        foreach ($this->response->getHeaders() as $header) {
            if (strcasecmp($header['name'], 'Location') === 0) {
                return (string)$header['value'];
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function errorMessages(Zend_Controller_Action $controller): array
    {
        $messenger = $controller->getHelper('FlashMessenger')->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR);
        $messages = $messenger->getCurrentMessages();
        $messenger->clearCurrentMessages();

        return array_map('strip_tags', $messages);
    }

    // ------------------------------------------------------------------
    // Rating grids
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{bool, ?string}>
     */
    public static function refusedRequestProvider(): array
    {
        return [
            'GET with a valid token' => [false, 'valid'],
            'POST without token' => [true, null],
            'POST with a wrong token' => [true, 'forged'],
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private static function withToken(array $post, ?string $token): array
    {
        if ($token !== null) {
            $post['csrf_token'] = $token === 'valid' ? Episciences_Csrf_Helper::getSessionToken() : $token;
        }

        return $post;
    }

    #[DataProvider('refusedRequestProvider')]
    public function testGridCopyIsRefusedWithoutAPostCarryingTheToken(bool $isPost, ?string $token): void
    {
        $controller = $this->callAction(\GridController::class, 'copyAction', self::withToken(['from' => '0', 'to' => '1'], $token), $isPost);

        self::assertStringEndsWith('/grid/list', $this->redirectUrl());
        self::assertSame([self::EXPIRED], $this->errorMessages($controller));
    }

    public function testGridCreationIsRefusedWithoutToken(): void
    {
        // A volume id no grid file can exist for
        $volumeId = 987654321;

        $controller = $this->callAction(\GridController::class, 'addAction', ['submit' => '1', 'volume' => (string)$volumeId]);

        self::assertFileDoesNotExist(REVIEW_GRIDS_PATH . 'grid_' . $volumeId . '.xml');
        self::assertStringEndsWith('/grid/list', $this->redirectUrl());
        self::assertSame([self::EXPIRED], $this->errorMessages($controller));
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function gridAjaxActionsProvider(): array
    {
        return [
            'grid delete' => ['deleteAction', ['params' => ['rgid' => '1']]],
            'criterion delete' => ['deletecriterionAction', ['params' => ['rgid' => '1', 'id' => '1']]],
        ];
    }

    /**
     * The delete buttons show the answer as the reason of the failure.
     *
     * @param array<string, mixed> $post
     */
    #[DataProvider('gridAjaxActionsProvider')]
    public function testGridDeletionsAreRefusedWithoutToken(string $action, array $post): void
    {
        $this->callAction(\GridController::class, $action, $post, true, $output);

        self::assertSame(self::EXPIRED, strip_tags((string)$output));
    }

    public function testCriterionSortIsRefusedWithoutTokenWith403(): void
    {
        $this->callAction(\GridController::class, 'sortcriterionAction', ['sorted' => ['1', '2'], 'rgid' => '1'], true, $output);

        self::assertSame(403, $this->response->getHttpResponseCode());
        self::assertSame('', $output);
    }

    // ------------------------------------------------------------------
    // Sections
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{?string}>
     */
    public static function refusedTokenProvider(): array
    {
        return [
            'without token' => [null],
            'with a wrong token' => ['forged'],
        ];
    }

    #[DataProvider('refusedTokenProvider')]
    public function testSectionCreationDoesNotShowTheValuesOfARefusedRequest(?string $token): void
    {
        $title = Episciences_Section::SECTION_PREFIX_TITLE . 'en';
        $post = self::withToken(['submit' => '1', $title => 'Typed title', 'status' => '1'], $token);

        $controller = $this->callAction(\SectionController::class, 'addAction', $post);

        self::assertSame([self::EXPIRED], $this->errorMessages($controller));
        self::assertNull($controller->view->form->getValue($title));
        self::assertSame(Episciences_Csrf_Helper::getSessionToken(), $controller->view->form->getElement('csrf_token')->getValue());
    }
}
