<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Episciences\Security\AccessLog;
use Episciences_Website_Navigation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use unit\library\Episciences\Security\SecurityLogCaptureTrait;
use unit\modules\common\controllers\support\WebsiteControllerTestTrait;
use WebsiteDefaultController;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Session_Namespace;

require_once dirname(__DIR__, 3) . '/library/Episciences/Security/SecurityLogCaptureTrait.php';
require_once __DIR__ . '/support/WebsiteControllerTestTrait.php';

/**
 * Behavioural tests for the role required by WebsiteDefaultController::menuAction(),
 * which edits the pages of the site and rebuilds its navigation.
 *
 * The navigation being edited is kept in session: a stub is put there, so the requests
 * need neither the database nor the journal directories.
 */
final class WebsiteDefaultControllerMenuAccessTest extends TestCase
{
    use SecurityLogCaptureTrait;
    use WebsiteControllerTestTrait;

    private Zend_Controller_Response_HttpTestCase $response;

    private WebsiteDefaultController $controller;

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/WebsiteDefaultController.php';
    }

    protected function setUp(): void
    {
        $this->response = new Zend_Controller_Response_HttpTestCase();
    }

    /**
     * Puts in session a navigation that must be neither changed nor saved
     */
    private function givenNavigationInSession(): void
    {
        $navigation = $this->createMock(Episciences_Website_Navigation::class);
        $navigation->expects(self::never())->method('setPage');
        $navigation->expects(self::never())->method('save');
        $navigation->expects(self::never())->method('createNavigation');
        $navigation->method('getPages')->willReturn([]);
        $navigation->method('getOrder')->willReturn([]);
        $navigation->method('getPageTypes')->willReturn([]);

        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->website = $navigation;
    }

    /**
     * A page change as sent by the menu form, without form token
     */
    private function dispatchMenuPost(): void
    {
        $post = ['pages_0' => ['labels' => ['en' => 'Injected page']]];
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost($post);
        $_POST = $post;

        $this->controller = new WebsiteDefaultController($request, $this->response);
        $this->controller->menuAction();
    }

    #[DataProvider('rolesWithoutSiteRightsProvider')]
    public function testUserWithoutSiteRightsIsRefused(?string $role): void
    {
        $this->logInAs($role);
        $this->givenNavigationInSession();

        $this->dispatchMenuPost();

        self::assertSame(403, $this->response->getHttpResponseCode());
        self::assertNull($this->controller->view->pages, 'the pages of the site must not be shown');
        self::assertSame([], $this->errorMessages(), 'a refused user must not reach the form token check');
        self::assertSame([[AccessLog::ACL_DENIED, 'role_check']], $this->loggedEvents());
    }

    /**
     * An authorized user reaches the page; the change is still refused without form token.
     */
    #[DataProvider('rolesWithSiteRightsProvider')]
    public function testUserWithSiteRightsReachesThePage(string $role): void
    {
        $this->logInAs($role);
        $this->givenNavigationInSession();

        $this->dispatchMenuPost();

        self::assertSame(200, $this->response->getHttpResponseCode());
        self::assertSame([], $this->controller->view->pages);
        self::assertSame(['Votre session a expiré, veuillez réessayer.'], $this->errorMessages());
        self::assertSame([[AccessLog::CSRF_FAILED, AccessLog::REASON_CSRF_MISSING]], $this->loggedEvents());
    }
}
