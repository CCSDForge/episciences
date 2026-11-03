<?php

declare(strict_types=1);

namespace unit\modules;

use Episciences_Auth;
use Episciences_Csrf_Helper;
use Episciences_ReviewsManager;
use Episciences_User;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use unit\modules\support\DispatchResult;
use unit\modules\support\RecordingDbAdapter;
use unit\modules\support\ResponseSent;
use unit\modules\support\StoppingJsonHelper;
use unit\modules\support\StoppingRedirector;
use Zend_Auth;
use Zend_Controller_Action;
use Zend_Controller_Action_Helper_Abstract;
use Zend_Controller_Action_HelperBroker;
use Zend_Controller_Front;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Registry;
use Zend_Session;

/**
 * Base class of the tests that run controller actions for real, without database.
 *
 * Each test gets:
 * - $this->db, a default database adapter that never connects: it records the queries, inserts,
 *   updates and deletes, and answers the queries with the rows programmed by the test;
 * - request factories (GET, POST, AJAX POST), with or without a valid request token;
 * - a logged user with roles, possibly impersonated by another one (su);
 * - a journal loaded from programmed settings (givenJournal());
 * - dispatch(), which runs an action and returns its response; a redirection or a sent JSON
 *   answer stops the action where production would exit.
 *
 * The global state these touch (default adapter, superglobals, session, identity, registry,
 * journal cache, action helpers) is saved before each test and restored after it, whatever the test does in its
 * own setUp() and tearDown().
 */
abstract class ControllerTestCase extends TestCase
{
    protected const UID = 4242;

    protected RecordingDbAdapter $db;

    private ?Zend_Db_Adapter_Abstract $previousAdapter = null;

    /** @var array{get: array<mixed>, post: array<mixed>, cookie: array<mixed>, session: array<mixed>|null} */
    private array $previousGlobals;

    private bool $previousSessionTestMode;

    /** @var array<mixed> */
    private array $previousRegistry;

    /** @var array<string, Zend_Controller_Action_Helper_Abstract|null> */
    private array $previousHelpers = [];

    /** @var array{request: mixed, response: mixed} */
    private array $previousFrontState;

    /** @var array<mixed> */
    private array $previousJournalCache;

    #[Before]
    protected function setUpControllerEnvironment(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->previousGlobals = [
            'get' => $_GET,
            'post' => $_POST,
            'cookie' => $_COOKIE,
            'session' => $_SESSION ?? null,
        ];
        $this->previousSessionTestMode = Zend_Session::$_unitTestEnabled;
        $this->previousRegistry = Zend_Registry::getInstance()->getArrayCopy();
        $this->previousFrontState = $this->frontState();
        $this->previousJournalCache = $this->journalCache();

        // Journals are loaded from the rows programmed by the test, not from an earlier one
        $this->journalCache([]);

        // Sessions are kept in $_SESSION, no session is started
        Zend_Session::$_unitTestEnabled = true;

        $this->db = new RecordingDbAdapter();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->db);

        $this->replaceHelper('Redirector', new StoppingRedirector());
        $this->replaceHelper('Json', new StoppingJsonHelper());

        Zend_Auth::getInstance()->clearIdentity();
    }

    #[After]
    protected function restoreControllerEnvironment(): void
    {
        foreach ($this->previousHelpers as $name => $helper) {
            Zend_Controller_Action_HelperBroker::removeHelper($name);
            if ($helper !== null) {
                Zend_Controller_Action_HelperBroker::addHelper($helper);
            }
        }
        $this->previousHelpers = [];
        $this->frontState($this->previousFrontState);
        $this->journalCache($this->previousJournalCache);

        Zend_Auth::getInstance()->clearIdentity();
        Zend_Registry::getInstance()->exchangeArray($this->previousRegistry);

        $_GET = $this->previousGlobals['get'];
        $_POST = $this->previousGlobals['post'];
        $_COOKIE = $this->previousGlobals['cookie'];
        $_SESSION = $this->previousGlobals['session'] ?? [];
        Zend_Session::$_unitTestEnabled = $this->previousSessionTestMode;

        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    // ------------------------------------------------------------------
    // Requests
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $params route parameters, which getParam() reads first
     */
    protected function getRequest(array $query = [], array $params = []): Zend_Controller_Request_HttpTestCase
    {
        return $this->newRequest('GET', $params)->setQuery($query);
    }

    /**
     * @param array<string, mixed> $post
     * @param bool $withRequestToken also post the valid session request token (csrf_token)
     * @param array<string, mixed> $params route parameters, which getParam() reads first
     */
    protected function postRequest(array $post = [], bool $withRequestToken = false, array $params = []): Zend_Controller_Request_HttpTestCase
    {
        if ($withRequestToken) {
            $post['csrf_token'] = $this->requestToken();
        }

        return $this->newRequest('POST', $params)->setPost($post);
    }

    /**
     * POST request sent by a script, which sends the request token as the X-CSRF-Token header.
     *
     * @param array<string, mixed> $post
     * @param bool $withRequestToken also send the valid session request token
     * @param array<string, mixed> $params route parameters, which getParam() reads first
     */
    protected function xhrPostRequest(array $post = [], bool $withRequestToken = false, array $params = []): Zend_Controller_Request_HttpTestCase
    {
        $request = $this->newRequest('POST', $params)->setPost($post);
        $request->setHeader('X-Requested-With', 'XMLHttpRequest');
        if ($withRequestToken) {
            $request->setHeader('X-CSRF-Token', $this->requestToken());
        }

        return $request;
    }

    /**
     * The per-session request token (see Episciences_Csrf_Helper::validateRequestToken()).
     */
    protected function requestToken(): string
    {
        return Episciences_Csrf_Helper::getSessionToken();
    }

    /**
     * Field to post for a form token (see Episciences_Csrf_Helper::generateToken()).
     *
     * @return array<string, string> token value, keyed by the field name
     */
    protected function formToken(string $name): array
    {
        $token = Episciences_Csrf_Helper::generateToken($name);

        return [(string)$token['name'] => (string)$token['value']];
    }

    // ------------------------------------------------------------------
    // Identity
    // ------------------------------------------------------------------

    /**
     * Log a user in, with $roles in journal $rvid.
     *
     * @param list<string> $roles
     */
    protected function logInAs(array $roles, int $uid = self::UID, int $rvid = RVID): Episciences_User
    {
        $user = $this->newUser($roles, $uid, $rvid);
        Zend_Auth::getInstance()->getStorage()->write($user);

        return $user;
    }

    /**
     * Switch the logged user to another one (su), as UserDefaultController::suAction() does.
     *
     * @param list<string> $roles roles of the impersonated user in journal $rvid
     */
    protected function impersonate(array $roles, int $uid, int $rvid = RVID): Episciences_User
    {
        self::assertTrue(Episciences_Auth::isLogged(), 'log the original user in before impersonating another one');

        $user = $this->newUser($roles, $uid, $rvid);
        Episciences_Auth::startImpersonation($user);

        return $user;
    }

    // ------------------------------------------------------------------
    // Journal
    // ------------------------------------------------------------------

    /**
     * Answer the lookups of the current journal (RVID) and of its settings.
     *
     * @param array<string, mixed> $settings setting values, keyed by setting name
     */
    protected function givenJournal(array $settings = []): void
    {
        $rows = [];
        foreach ($settings as $setting => $value) {
            $rows[] = ['RVID' => RVID, 'SETTING' => $setting, 'VALUE' => $value];
        }

        $this->db
            ->respondTo('/FROM `REVIEW` WHERE \\(RVID = ' . RVID . '\\)/', [['RVID' => RVID, 'CODE' => 'unittests', 'NAME' => 'Unit tests', 'STATUS' => 1]])
            ->respondTo('/FROM `REVIEW_SETTING` WHERE \\(RVID = ' . RVID . '\\)/', $rows);
    }

    // ------------------------------------------------------------------
    // Dispatch
    // ------------------------------------------------------------------

    /**
     * Load a controller class, which the autoloader does not find.
     *
     * @param string $module application module (journal, common, portal, oai)
     */
    protected static function loadController(string $module, string $class): void
    {
        require_once APPLICATION_PATH . '/modules/' . $module . '/controllers/' . $class . '.php';
    }

    /**
     * Run $action ("delete" runs deleteAction()) of a new $controllerClass on $request.
     *
     * Only the action method runs: neither preDispatch() nor the view rendering. As in a real
     * dispatch, the front controller holds the request and the response (the JSON view helper
     * reads it).
     *
     * @param class-string<Zend_Controller_Action> $controllerClass
     */
    protected function dispatch(string $controllerClass, string $action, Zend_Controller_Request_HttpTestCase $request): DispatchResult
    {
        $request->setActionName($action);
        $response = new Zend_Controller_Response_HttpTestCase();
        Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
        $controller = new $controllerClass($request, $response);
        $stopped = false;

        ob_start();
        try {
            $controller->{$action . 'Action'}();
        } catch (ResponseSent) {
            $stopped = true;
        } finally {
            $output = (string)ob_get_clean();
        }

        return new DispatchResult($response, $output, $stopped);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function newRequest(string $method, array $params): Zend_Controller_Request_HttpTestCase
    {
        // The request reads the superglobals: start from empty ones
        $_GET = [];
        $_POST = [];

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod($method)->setParams($params);

        return $request;
    }

    /**
     * @param list<string> $roles
     */
    private function newUser(array $roles, int $uid, int $rvid): Episciences_User
    {
        $user = new Episciences_User(['UID' => $uid]);
        $user->setRoles([$rvid => $roles]);

        return $user;
    }

    /**
     * Request and response held by the front controller, replaced by $state when given.
     *
     * The front controller has no setter accepting null, which is its state outside a dispatch.
     *
     * @param array{request: mixed, response: mixed}|null $state
     * @return array{request: mixed, response: mixed}
     */
    private function frontState(?array $state = null): array
    {
        $front = Zend_Controller_Front::getInstance();
        $request = new ReflectionProperty(Zend_Controller_Front::class, '_request');
        $response = new ReflectionProperty(Zend_Controller_Front::class, '_response');
        $current = ['request' => $request->getValue($front), 'response' => $response->getValue($front)];

        if ($state !== null) {
            $request->setValue($front, $state['request']);
            $response->setValue($front, $state['response']);
        }

        return $current;
    }

    /**
     * Journals cached by Episciences_ReviewsManager, replaced by $cache when given.
     *
     * @param array<mixed>|null $cache
     * @return array<mixed>
     */
    private function journalCache(?array $cache = null): array
    {
        $property = new ReflectionProperty(Episciences_ReviewsManager::class, '_cache');
        $current = $property->getValue();

        if ($cache !== null) {
            $property->setValue(null, $cache);
        }

        return is_array($current) ? $current : [];
    }

    private function replaceHelper(string $name, Zend_Controller_Action_Helper_Abstract $helper): void
    {
        $this->previousHelpers[$name] = Zend_Controller_Action_HelperBroker::hasHelper($name)
            ? Zend_Controller_Action_HelperBroker::getExistingHelper($name)
            : null;

        Zend_Controller_Action_HelperBroker::removeHelper($name);
        Zend_Controller_Action_HelperBroker::addHelper($helper);
    }
}
