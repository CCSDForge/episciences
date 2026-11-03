<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Episciences\Security\AccessLog;
use Episciences_Acl;
use Episciences_Csrf_Helper;
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
 * Behavioural tests for the role required by the management of the site pages in
 * WebsiteDefaultController: menuAction(), which edits the pages and rebuilds the navigation,
 * the AJAX changes of the pages (add, order, remove) and the reset of the pages being edited.
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
     * Puts in session a navigation that must be neither changed nor saved, except by the
     * expected change of the pages (one of the methods of pageChangesProvider())
     */
    private function givenNavigationInSession(?string $expectedChange = null): Episciences_Website_Navigation
    {
        $navigation = $this->createMock(Episciences_Website_Navigation::class);
        foreach (['setPage', 'save', 'createNavigation', 'addPage', 'changeOrder', 'deletePage'] as $method) {
            $navigation->expects($method === $expectedChange ? self::once() : self::never())->method($method);
        }
        $navigation->method('getPages')->willReturn([]);
        $navigation->method('getOrder')->willReturn([]);
        $navigation->method('getPageTypes')->willReturn([]);

        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->website = $navigation;

        return $navigation;
    }

    private function navigationInSession(): mixed
    {
        return (new Zend_Session_Namespace(SESSION_NAMESPACE))->website;
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

    // -----------------------------------------------------------------------
    // AJAX changes of the pages
    // -----------------------------------------------------------------------

    /**
     * Each AJAX change of the pages, its parameters as sent by the menu page, and the method of
     * the navigation that applies it
     *
     * @return array<string, array{string, array<string, mixed>, string}>
     */
    private static function pageChanges(): array
    {
        return [
            'add a page' => ['ajaxformpageAction', ['type' => 'custom'], 'addPage'],
            'order the pages' => ['ajaxorderAction', ['page' => ['1', '0']], 'changeOrder'],
            'remove a page' => ['ajaxrmpageAction', ['idx' => '0'], 'deletePage'],
        ];
    }

    /**
     * @return array<string, array{string, array<string, mixed>, string}>
     */
    public static function pageChangesProvider(): array
    {
        return self::pageChanges();
    }

    /**
     * Every AJAX change of the pages for every user without site rights
     *
     * @return array<string, array{string, array<string, mixed>, string|null}>
     */
    public static function pageChangesWithoutSiteRightsProvider(): array
    {
        $cases = [];
        foreach (self::pageChanges() as $change => [$action, $post]) {
            foreach (self::rolesWithoutSiteRights() as $label => $role) {
                $cases[$change . ', ' . $label] = [$action, $post, $role];
            }
        }

        return $cases;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function dispatchPageChange(string $action, array $post, bool $withToken = true): void
    {
        if ($withToken) {
            $post['csrf_token'] = Episciences_Csrf_Helper::getSessionToken();
        }
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost($post)->setHeader('X-Requested-With', 'XMLHttpRequest');
        $_POST = $post;

        $this->controller = new WebsiteDefaultController($request, $this->response);
        $this->controller->$action();
    }

    /**
     * Refused before the request token is checked and before the navigation is loaded in
     * session, even with a valid token
     *
     * @param array<string, mixed> $post
     */
    #[DataProvider('pageChangesWithoutSiteRightsProvider')]
    public function testUserWithoutSiteRightsCannotChangeThePages(string $action, array $post, ?string $role): void
    {
        $this->logInAs($role);

        $this->dispatchPageChange($action, $post);

        self::assertSame(403, $this->response->getHttpResponseCode());
        self::assertSame('', $this->response->getBody(), 'nothing may be rendered');
        self::assertFalse($this->layout->isEnabled(), 'an AJAX answer has no layout');
        self::assertNull($this->navigationInSession(), 'the navigation must not be loaded in session');
        self::assertSame([[AccessLog::ACL_DENIED, 'role_check']], $this->loggedEvents());
    }

    /**
     * @param array<string, mixed> $post
     */
    #[DataProvider('pageChangesProvider')]
    public function testPagesAreNotChangedWithoutRequestToken(string $action, array $post): void
    {
        $this->logInAs(Episciences_Acl::ROLE_WEBMASTER);
        $this->givenNavigationInSession();

        $this->dispatchPageChange($action, $post, false);

        self::assertSame(403, $this->response->getHttpResponseCode());
        self::assertSame('', $this->response->getBody(), 'nothing may be rendered');
        self::assertSame([[AccessLog::CSRF_FAILED, AccessLog::REASON_CSRF_MISSING]], $this->loggedEvents());
    }

    /**
     * The order and the removal of the pages; adding a page also renders the form of the new
     * page, which needs the view scripts of the module.
     *
     * @return array<string, array{string, array<string, mixed>, string}>
     */
    public static function silentPageChangesProvider(): array
    {
        return array_diff_key(self::pageChanges(), ['add a page' => true]);
    }

    /**
     * @param array<string, mixed> $post
     */
    #[DataProvider('silentPageChangesProvider')]
    public function testUserWithSiteRightsChangesThePages(string $action, array $post, string $change): void
    {
        $this->logInAs(Episciences_Acl::ROLE_SECRETARY);
        $this->givenNavigationInSession($change);

        $this->dispatchPageChange($action, $post);

        self::assertSame(200, $this->response->getHttpResponseCode());
        self::assertSame([], $this->loggedEvents());
    }

    // -----------------------------------------------------------------------
    // Reset of the pages being edited
    // -----------------------------------------------------------------------

    #[DataProvider('rolesWithoutSiteRightsProvider')]
    public function testUserWithoutSiteRightsCannotResetThePages(?string $role): void
    {
        $this->logInAs($role);
        $navigation = $this->givenNavigationInSession();

        $this->controller = new WebsiteDefaultController(new Zend_Controller_Request_HttpTestCase(), $this->response);
        $this->controller->resetAction();

        self::assertSame(403, $this->response->getHttpResponseCode());
        self::assertFalse($this->response->isRedirect());
        self::assertSame($navigation, $this->navigationInSession(), 'the pages being edited must be kept');
        self::assertSame([[AccessLog::ACL_DENIED, 'role_check']], $this->loggedEvents());
    }

    #[DataProvider('rolesWithSiteRightsProvider')]
    public function testUserWithSiteRightsResetsThePages(string $role): void
    {
        $this->logInAs($role);
        $this->givenNavigationInSession();

        $this->controller = new WebsiteDefaultController(new Zend_Controller_Request_HttpTestCase(), $this->response);
        $this->controller->resetAction();

        self::assertTrue($this->response->isRedirect());
        self::assertNull($this->navigationInSession(), 'the pages being edited must be dropped');
        self::assertSame([], $this->loggedEvents());
    }
}
