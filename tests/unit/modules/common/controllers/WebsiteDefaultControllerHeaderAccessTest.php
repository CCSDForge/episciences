<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Episciences\Security\AccessLog;
use Episciences_Acl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use unit\library\Episciences\Security\SecurityLogCaptureTrait;
use unit\modules\common\controllers\support\WebsiteControllerTestTrait;
use WebsiteDefaultController;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

require_once dirname(__DIR__, 3) . '/library/Episciences/Security/SecurityLogCaptureTrait.php';
require_once __DIR__ . '/support/WebsiteControllerTestTrait.php';

/**
 * Behavioural tests for the access checks of the header of the site in WebsiteDefaultController:
 * the role required to open the page (headerAction) and its logo form (ajaxheaderAction), and
 * the CSRF token required to save it.
 *
 * Only the refused requests are run: they stop before the header is loaded or saved,
 * so they need neither the database nor the journal directories.
 */
final class WebsiteDefaultControllerHeaderAccessTest extends TestCase
{
    use SecurityLogCaptureTrait;
    use WebsiteControllerTestTrait;

    private Zend_Controller_Response_HttpTestCase $response;

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/WebsiteDefaultController.php';
    }

    protected function setUp(): void
    {
        $this->response = new Zend_Controller_Response_HttpTestCase();
    }

    /**
     * @param array<string, mixed> $post
     */
    private function dispatchHeader(?array $post = null): WebsiteDefaultController
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        if ($post !== null) {
            $request->setMethod('POST')->setPost($post);
            $_POST = $post;
        }

        $controller = new WebsiteDefaultController($request, $this->response);
        $controller->headerAction();

        return $controller;
    }

    // -----------------------------------------------------------------------
    // Role
    // -----------------------------------------------------------------------

    #[DataProvider('rolesWithoutSiteRightsProvider')]
    public function testUserWithoutSiteRightsIsRefused(?string $role): void
    {
        $this->logInAs($role);

        $this->dispatchHeader(['header' => '']);

        self::assertSame(403, $this->response->getHttpResponseCode());
        self::assertFalse($this->response->isRedirect());
        self::assertSame([], $this->errorMessages(), 'a refused user must not reach the form token check');
        self::assertSame([[AccessLog::ACL_DENIED, 'role_check']], $this->loggedEvents());
    }

    /**
     * The logo form loaded by the header page
     */
    #[DataProvider('rolesWithoutSiteRightsProvider')]
    public function testUserWithoutSiteRightsCannotLoadTheLogoForm(?string $role): void
    {
        $this->logInAs($role);

        $controller = new WebsiteDefaultController(new Zend_Controller_Request_HttpTestCase(), $this->response);
        $controller->ajaxheaderAction();

        self::assertSame(403, $this->response->getHttpResponseCode());
        self::assertSame('', $this->response->getBody(), 'nothing may be rendered');
        self::assertFalse($this->layout->isEnabled(), 'an AJAX answer has no layout');
        self::assertNull($controller->view->form, 'the logo form must not be built');
        self::assertSame([[AccessLog::ACL_DENIED, 'role_check']], $this->loggedEvents());
    }

    // -----------------------------------------------------------------------
    // CSRF token
    // -----------------------------------------------------------------------

    #[DataProvider('rolesWithSiteRightsProvider')]
    public function testSubmissionWithoutTokenIsNotSaved(string $role): void
    {
        $this->logInAs($role);

        $this->dispatchHeader(['header' => '', 'logo_0' => ['type' => 'text', 'text' => ['en' => 'x']]]);

        $this->assertRedirectedWithExpiredFormMessage();
    }

    public function testSubmissionWithWrongTokenIsNotSaved(): void
    {
        $this->logInAs(Episciences_Acl::ROLE_SECRETARY);

        $this->dispatchHeader(['header' => '', 'csrf_token' => 'forged']);

        $this->assertRedirectedWithExpiredFormMessage();
    }

    private function assertRedirectedWithExpiredFormMessage(): void
    {
        self::assertTrue($this->response->isRedirect(), 'A refused submission must redirect to the form');
        $location = array_column($this->response->getHeaders(), 'value', 'name')['Location'] ?? '';
        self::assertStringEndsWith('/website/header', $location);
        self::assertSame(
            ["Les modifications n'ont pas été enregistrées car le formulaire a expiré. Veuillez les saisir à nouveau."],
            $this->errorMessages()
        );
    }
}
