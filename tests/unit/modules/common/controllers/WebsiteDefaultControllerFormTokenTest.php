<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Episciences_Acl;
use Episciences_Csrf_Helper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use unit\modules\common\controllers\support\WebsiteControllerTestTrait;
use WebsiteDefaultController;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

require_once __DIR__ . '/support/WebsiteControllerTestTrait.php';

/**
 * Form token check of the website settings (common, style and menu actions of
 * WebsiteDefaultController).
 *
 * A submission without the per-session token must save nothing and tell the user the session
 * has expired. The actions are called for real on a partially mocked database adapter.
 */
final class WebsiteDefaultControllerFormTokenTest extends TestCase
{
    use WebsiteControllerTestTrait;

    private const EXPIRED = 'Votre session a expiré, veuillez réessayer.';

    /** Calls on the menu made only when the submitted pages are processed */
    private const MENU_WRITES = ['setPage', 'save', 'createNavigation'];

    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/WebsiteDefaultController.php';
    }

    protected function setUp(): void
    {
        // The site settings are managed by the secretaries and the webmasters
        $this->logInAs(Episciences_Acl::ROLE_SECRETARY);

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchAll', 'fetchPairs', 'fetchRow', 'fetchCol', 'insert', 'update', 'delete'])
            ->getMock();
        // Nothing saved yet for the journal
        $this->adapter->method('fetchAll')->willReturn([]);
        $this->adapter->method('fetchPairs')->willReturn([]);
        $this->adapter->method('fetchRow')->willReturn(false);
        $this->adapter->method('fetchCol')->willReturn([]);
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        if (!\Zend_Registry::isRegistered('Zend_Locale')) {
            \Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
        }

        // The session holds a token the requests below do not send
        Episciences_Csrf_Helper::getSessionToken();
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function dispatch(string $action, array $post): WebsiteDefaultController
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost($post);
        $_POST = $post;

        $controller = new WebsiteDefaultController($request, new Zend_Controller_Response_HttpTestCase());
        $controller->$action();

        return $controller;
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function settingsActionsProvider(): array
    {
        return [
            'languages' => ['commonAction', ['languages' => ['en']]],
            'style' => ['styleAction', ['navigation' => 'horizontal']],
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    #[DataProvider('settingsActionsProvider')]
    public function testSettingsAreNotSavedWithoutToken(string $action, array $post): void
    {
        $this->adapter->expects(self::never())->method('insert');
        $this->adapter->expects(self::never())->method('update');
        $this->adapter->expects(self::never())->method('delete');

        $this->dispatch($action, $post);

        self::assertSame([self::EXPIRED], $this->errorMessages());
    }

    /**
     * @param array<string, mixed> $post
     */
    #[DataProvider('settingsActionsProvider')]
    public function testSettingsAreNotSavedWithAWrongToken(string $action, array $post): void
    {
        $this->adapter->expects(self::never())->method('insert');
        $this->adapter->expects(self::never())->method('update');
        $this->adapter->expects(self::never())->method('delete');

        $this->dispatch($action, $post + ['csrf_token' => 'forged']);

        self::assertSame([self::EXPIRED], $this->errorMessages());
    }

    /**
     * Runs menuAction() on a POST and returns the methods called on the navigation kept in
     * session, the same double standing for its pages and their forms.
     *
     * @param array<string, mixed> $post
     * @return list<string>
     */
    private function postMenu(array $post): array
    {
        $website = new class {
            /** @var list<string> */
            public array $calls = [];

            /**
             * @param array<int, mixed> $arguments
             */
            public function __call(string $name, array $arguments): mixed
            {
                $this->calls[] = $name;
                // The page types are grouped by menuAction() on manager branches, which needs an array
                return $name === 'getPageTypes' ? [] : $this;
            }
        };

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost($post);
        $_POST = $post;
        $controller = new WebsiteDefaultController($request, new Zend_Controller_Response_HttpTestCase());

        // A plain holder: a real session namespace would try to serialize the anonymous double
        $sessionProperty = new \ReflectionProperty(WebsiteDefaultController::class, '_session');
        $sessionProperty->setAccessible(true);
        $sessionProperty->setValue($controller, (object)['website' => $website]);

        // menuAction() groups the page types according to the journal (manager branches): resolve it without the database
        $review = new \Episciences_Review();
        $review->setCode(RVCODE);
        $cacheProperty = new \ReflectionProperty(\Episciences_ReviewsManager::class, '_cache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue(null, array_merge($cacheProperty->getValue(), ['rvcode_' . RVCODE => $review]));

        $controller->menuAction();

        return $website->calls;
    }

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

    /**
     * The menu is shown again from the session, with the changes not saved yet.
     */
    #[DataProvider('refusedTokenProvider')]
    public function testMenuIsNotProcessedWhenTheTokenIsRefused(?string $token): void
    {
        $post = ['pages_1' => ['visibility' => '0']];
        if ($token !== null) {
            $post['csrf_token'] = $token;
        }

        $calls = $this->postMenu($post);

        self::assertSame([], array_values(array_intersect(self::MENU_WRITES, $calls)));
        self::assertSame([self::EXPIRED], $this->errorMessages());
    }

    public function testMenuIsProcessedWithAValidToken(): void
    {
        $calls = $this->postMenu(['pages_1' => ['visibility' => '0'], 'csrf_token' => Episciences_Csrf_Helper::getSessionToken()]);

        self::assertSame(self::MENU_WRITES, array_values(array_intersect(self::MENU_WRITES, $calls)));
        self::assertSame([], $this->errorMessages());
    }
}
