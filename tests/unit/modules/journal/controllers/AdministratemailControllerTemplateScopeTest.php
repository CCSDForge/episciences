<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Csrf_Helper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Action_Helper_Redirector;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;
use Zend_Session;
use Zend_Session_Namespace;

/**
 * Journal scoping and request token of the mail template actions.
 *
 * Template ids come from request parameters, and all journals share the same table: an action
 * must only reach a default template or a custom template of the current journal, and the revert
 * (deletion of the custom template) must only run on a POST request carrying the session token.
 * The actions are called for real on a partially mocked database adapter: queries are built and
 * captured, nothing is executed.
 */
final class AdministratemailControllerTemplateScopeTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $previousAdapter;

    private bool $previousUnitTestEnabled;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    /** @var list<string> SQL of the SELECT statements sent to the adapter */
    private array $selects = [];

    protected function setUp(): void
    {
        $this->previousUnitTestEnabled = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;

        // The template constructor sets a locale, which reads the registry
        if (!\Zend_Registry::isRegistered('Zend_Locale')) {
            \Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
        }
        if (!\Zend_Registry::isRegistered('languages')) {
            \Zend_Registry::set('languages', ['en', 'fr']);
        }

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchRow', 'insert', 'update', 'delete'])
            ->getMock();
        $this->adapter->method('fetchRow')->willReturnCallback(function ($select) {
            $this->selects[] = $select instanceof Zend_Db_Select ? $select->assemble() : (string)$select;

            return false; // a template of another journal is not found
        });
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        require_once APPLICATION_PATH . '/modules/journal/controllers/AdministratemailController.php';
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
     * Call an action and return the URL it redirects to.
     *
     * @param array<string, mixed> $post
     * @param array<string, string> $headers
     */
    private function callAction(
        string $action,
        array $post,
        bool $isPost = true,
        bool $allowedToEdit = true,
        array $headers = []
    ): string {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod($isPost ? 'POST' : 'GET');
        $request->setPost($isPost ? $post : []);
        foreach ($headers as $name => $value) {
            $request->setHeader($name, $value);
        }
        $response = new Zend_Controller_Response_HttpTestCase();

        $controller = new \AdministratemailController($request, $response);
        $controller->setAllowedToEdit($allowedToEdit);
        $controller->getHelper('redirector')->setExit(false);

        ob_start();
        try {
            $controller->$action();
        } finally {
            ob_end_clean();
        }

        foreach ($response->getHeaders() as $header) {
            if (strcasecmp($header['name'], 'Location') === 0) {
                return (string)$header['value'];
            }
        }

        return '';
    }

    private function assertNoWrite(): void
    {
        $this->adapter->expects(self::never())->method('insert');
        $this->adapter->expects(self::never())->method('update');
        $this->adapter->expects(self::never())->method('delete');
    }

    // ------------------------------------------------------------------
    // Revert (deletion of the custom template)
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{bool, ?string}>
     */
    public static function refusedRevertProvider(): array
    {
        return [
            'GET with a valid token' => [false, 'valid'],
            'POST without token' => [true, null],
            'POST with a wrong token' => [true, 'forged'],
        ];
    }

    #[DataProvider('refusedRevertProvider')]
    public function testRevertIsRefusedWithoutAPostCarryingTheToken(bool $isPost, ?string $token): void
    {
        $this->assertNoWrite();

        $sessionToken = Episciences_Csrf_Helper::getSessionToken();
        $post = ['id' => '7'];
        if ($token !== null) {
            $post['csrf_token'] = $token === 'valid' ? $sessionToken : $token;
        }

        $url = $this->callAction('deletetemplateAction', $post, $isPost);

        self::assertStringEndsWith('/administratemail/templates', $url, 'back to the list with a message');
        self::assertSame([], $this->selects, 'no lookup before the token is checked');
    }

    public function testRevertOnlyReachesACustomTemplateOfTheJournal(): void
    {
        $this->assertNoWrite();

        $url = $this->callAction(
            'deletetemplateAction',
            ['id' => '7', 'csrf_token' => Episciences_Csrf_Helper::getSessionToken()]
        );

        self::assertStringEndsWith('/administratemail/templates', $url);
        self::assertCount(1, $this->selects);
        self::assertMatchesRegularExpression('/\bRVID = ' . RVID . '\b/', $this->selects[0]);
        self::assertStringContainsString('PARENTID > 0', $this->selects[0]);
    }

    public function testRevertIsRefusedWhenEditingTemplatesIsNotAllowed(): void
    {
        $this->assertNoWrite();

        $url = $this->callAction(
            'deletetemplateAction',
            ['id' => '7', 'csrf_token' => Episciences_Csrf_Helper::getSessionToken()],
            true,
            false
        );

        self::assertStringEndsWith('/error/deny', $url);
        self::assertSame([], $this->selects);
    }

    // ------------------------------------------------------------------
    // Edit form and save
    // ------------------------------------------------------------------

    public function testEditFormRefusesATemplateOfAnotherJournal(): void
    {
        $this->assertNoWrite();

        $url = $this->callAction(
            'edittemplateAction',
            ['id' => '7'],
            true,
            true,
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        self::assertStringEndsWith('/error/deny', $url);
        self::assertCount(1, $this->selects);
        self::assertMatchesRegularExpression('/\(RVID IS NULL OR RVID = ' . RVID . '\)/', $this->selects[0]);
    }

    public function testSaveRefusesATemplateOfAnotherJournal(): void
    {
        $this->assertNoWrite();

        $url = $this->callAction('savetemplateAction', ['en' => ['subject' => 'x', 'body' => 'y', 'name' => 'z']]);

        self::assertStringEndsWith('/administratemail/templates', $url);
        self::assertCount(1, $this->selects);
        self::assertMatchesRegularExpression('/\(RVID IS NULL OR RVID = ' . RVID . '\)/', $this->selects[0]);
    }
}
