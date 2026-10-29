<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Episciences\Security\AccessLog;
use Episciences_Csrf_Helper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use unit\library\Episciences\Security\SecurityLogCaptureTrait;
use unit\modules\common\controllers\support\WebsiteControllerTestTrait;
use WebsiteDefaultController;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

require_once dirname(__DIR__, 3) . '/library/Episciences/Security/SecurityLogCaptureTrait.php';
require_once __DIR__ . '/support/WebsiteControllerTestTrait.php';

/**
 * Behavioural tests for the role required by the site settings of WebsiteDefaultController:
 * languages (common), style, public resources and news.
 *
 * The actions are called for real on a partially mocked database adapter, with a valid form
 * token: only the role decides.
 */
final class WebsiteDefaultControllerSettingsAccessTest extends TestCase
{
    use SecurityLogCaptureTrait;
    use WebsiteControllerTestTrait;

    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    private Zend_Controller_Response_HttpTestCase $response;

    private string $fileName;

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/WebsiteDefaultController.php';
    }

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchAll', 'fetchPairs', 'fetchRow', 'fetchCol', 'fetchOne', 'insert', 'update', 'delete'])
            ->getMock();
        // Nothing saved yet for the journal
        $this->adapter->method('fetchAll')->willReturn([]);
        $this->adapter->method('fetchPairs')->willReturn([]);
        $this->adapter->method('fetchRow')->willReturn(false);
        $this->adapter->method('fetchCol')->willReturn([]);
        $this->adapter->method('fetchOne')->willReturn(false);
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        if (!\Zend_Registry::isRegistered('Zend_Locale')) {
            \Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
        }

        // A file of the journal public resources, that a refused removal must leave in place
        $dir = REVIEW_PATH . 'public/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $this->fileName = uniqid('access-test-', true) . '.txt';
        file_put_contents($dir . $this->fileName, 'test');

        $this->response = new Zend_Controller_Response_HttpTestCase();
    }

    protected function tearDown(): void
    {
        $file = REVIEW_PATH . 'public/' . $this->fileName;
        if (is_file($file)) {
            unlink($file);
        }

        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    /**
     * A change of each setting, as sent by its form, with a valid form token
     *
     * @return array<string, array{string, array<string, mixed>}>
     */
    private static function settingsActions(): array
    {
        return [
            'languages' => ['commonAction', ['languages' => ['en']]],
            'style' => ['styleAction', ['navigation' => 'horizontal']],
            'public resources' => ['publicAction', ['method' => 'remove']],
            'news' => ['newsAction', ['action' => 'delete', 'newsid' => '7']],
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    private function dispatch(string $action, array $post): WebsiteDefaultController
    {
        if (($post['method'] ?? null) === 'remove') {
            $post['name'] = $this->fileName;
        }
        $post['csrf_token'] = Episciences_Csrf_Helper::getSessionToken();

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost($post);
        $_POST = $post;

        $controller = new WebsiteDefaultController($request, $this->response);
        $controller->$action();

        return $controller;
    }

    /**
     * Every setting changed by every user without site rights
     *
     * @return array<string, array{string, array<string, mixed>, string|null}>
     */
    public static function settingsChangedWithoutSiteRightsProvider(): array
    {
        $cases = [];
        foreach (self::settingsActions() as $setting => [$action, $post]) {
            foreach (self::rolesWithoutSiteRights() as $label => $role) {
                $cases[$setting . ', ' . $label] = [$action, $post, $role];
            }
        }

        return $cases;
    }

    /**
     * Refused before anything is read or written, even with a valid form token
     *
     * @param array<string, mixed> $post
     */
    #[DataProvider('settingsChangedWithoutSiteRightsProvider')]
    public function testUserWithoutSiteRightsIsRefused(string $action, array $post, ?string $role): void
    {
        $this->adapter->expects(self::never())->method('insert');
        $this->adapter->expects(self::never())->method('update');
        $this->adapter->expects(self::never())->method('delete');
        $this->logInAs($role);

        $controller = $this->dispatch($action, $post);

        self::assertSame(403, $this->response->getHttpResponseCode());
        self::assertFalse($this->response->isRedirect());
        self::assertNull($controller->view->form, 'no form may be shown');
        self::assertNull($controller->view->files, 'no public resource may be listed');
        self::assertNull($controller->view->news, 'no news may be listed');
        self::assertSame([], $this->errorMessages(), 'a refused user must not reach the form token check');
        self::assertSame([[AccessLog::ACL_DENIED, 'role_check']], $this->loggedEvents());
        self::assertFileExists(REVIEW_PATH . 'public/' . $this->fileName);
    }

    #[DataProvider('rolesWithSiteRightsProvider')]
    public function testUsersWithWebsiteRightsCanRemoveAPublicResource(string $role): void
    {
        $this->logInAs($role);

        $this->dispatch('publicAction', ['method' => 'remove']);

        self::assertNotSame(403, $this->response->getHttpResponseCode());
        self::assertFileDoesNotExist(REVIEW_PATH . 'public/' . $this->fileName);
        self::assertSame([], $this->loggedEvents());
    }

    #[DataProvider('rolesWithSiteRightsProvider')]
    public function testUsersWithWebsiteRightsReachTheLanguageSettings(string $role): void
    {
        $this->logInAs($role);

        // Opening the page only: a submission would save the languages of the journal
        $controller = new WebsiteDefaultController(new Zend_Controller_Request_HttpTestCase(), $this->response);
        $controller->commonAction();

        self::assertNotSame(403, $this->response->getHttpResponseCode());
        self::assertInstanceOf(\Zend_Form::class, $controller->view->form);
        self::assertSame([], $this->loggedEvents());
    }
}
