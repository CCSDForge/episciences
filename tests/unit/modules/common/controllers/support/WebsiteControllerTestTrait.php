<?php

declare(strict_types=1);

namespace unit\modules\common\controllers\support;

use Ccsd_View_Helper_DisplayFlashMessages;
use Episciences_Acl;
use Episciences_Auth;
use Episciences_User;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Zend_Auth_Storage_Interface;
use Zend_Auth_Storage_NonPersistent;
use Zend_Controller_Action_Helper_Abstract;
use Zend_Controller_Action_Helper_FlashMessenger;
use Zend_Controller_Action_Helper_Redirector;
use Zend_Controller_Action_Helper_ViewRenderer;
use Zend_Controller_Action_HelperBroker;
use Zend_Layout;
use Zend_Layout_Controller_Action_Helper_Layout;
use Zend_Session;
use Zend_Session_Namespace;
use Zend_View;

/**
 * Environment and users of the tests of WebsiteDefaultController, whose actions are called directly.
 *
 * Each test runs with in-memory sessions, a non persistent authentication storage and its own
 * action helpers: views are never rendered, redirections do not exit and the layout can be
 * inspected. Everything is restored afterwards (these hooks run before setUp() and after tearDown()).
 *
 * The site settings are managed by the secretaries (which include administrators, chief editors
 * and root) and the webmasters.
 */
trait WebsiteControllerTestTrait
{
    private Zend_Layout $layout;

    private bool $previousUnitTestEnabled;
    private Zend_Auth_Storage_Interface $previousAuthStorage;
    /** @var Zend_Controller_Action_Helper_Abstract[] */
    private array $previousHelpers = [];

    #[Before]
    protected function setUpWebsiteController(): void
    {
        $this->previousUnitTestEnabled = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;

        $this->previousAuthStorage = Episciences_Auth::getInstance()->getStorage();
        Episciences_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());

        // Form elements are rendered with the view of the static view renderer: keep the bootstrap ones
        $this->previousHelpers = iterator_to_array(Zend_Controller_Action_HelperBroker::getStack(), false);
        Zend_Controller_Action_HelperBroker::resetHelpers();
        $viewRenderer = new Zend_Controller_Action_Helper_ViewRenderer(new Zend_View());
        $viewRenderer->setNeverRender(true);
        Zend_Controller_Action_HelperBroker::addHelper($viewRenderer);
        $redirector = new Zend_Controller_Action_Helper_Redirector();
        $redirector->setExit(false);
        Zend_Controller_Action_HelperBroker::addHelper($redirector);
        $this->layout = new Zend_Layout();
        Zend_Controller_Action_HelperBroker::addHelper(new Zend_Layout_Controller_Action_Helper_Layout($this->layout));

        // Messages left by the other tests of the run
        $this->clearErrorMessages();
    }

    #[After]
    protected function tearDownWebsiteController(): void
    {
        $this->clearErrorMessages();
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->csrfToken, $session->website);
        $_POST = [];

        Zend_Controller_Action_HelperBroker::resetHelpers();
        foreach ($this->previousHelpers as $helper) {
            Zend_Controller_Action_HelperBroker::addHelper($helper);
        }
        Episciences_Auth::getInstance()->clearIdentity();
        Episciences_Auth::getInstance()->setStorage($this->previousAuthStorage);
        Zend_Session::$_unitTestEnabled = $this->previousUnitTestEnabled;
    }

    /**
     * @return list<string>
     */
    private function errorMessages(): array
    {
        $flash = new Zend_Controller_Action_Helper_FlashMessenger();

        return array_values($flash->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->getCurrentMessages());
    }

    private function clearErrorMessages(): void
    {
        (new Zend_Controller_Action_Helper_FlashMessenger())->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->clearCurrentMessages();
    }

    /**
     * @param string|null $role null for a guest
     */
    private function logInAs(?string $role): void
    {
        Episciences_Auth::getInstance()->clearIdentity();
        if ($role === null) {
            return;
        }

        $user = $this->createMock(Episciences_User::class);
        $user->method('getAllRoles')->willReturn([RVID => [$role]]);
        Episciences_Auth::getInstance()->getStorage()->write($user);
    }

    /**
     * @return array<string, string|null> role by label, null for a guest
     */
    private static function rolesWithoutSiteRights(): array
    {
        return [
            'guest' => null,
            'member' => Episciences_Acl::ROLE_MEMBER,
            'author' => Episciences_Acl::ROLE_AUTHOR,
            'reviewer' => Episciences_Acl::ROLE_REVIEWER,
            'copy editor' => Episciences_Acl::ROLE_COPY_EDITOR,
            'editor' => Episciences_Acl::ROLE_EDITOR,
            'guest editor' => Episciences_Acl::ROLE_GUEST_EDITOR,
        ];
    }

    /**
     * @return array<string, string> role by label
     */
    private static function rolesWithSiteRights(): array
    {
        return [
            'root' => Episciences_Acl::ROLE_ROOT,
            'chief editor' => Episciences_Acl::ROLE_CHIEF_EDITOR,
            'administrator' => Episciences_Acl::ROLE_ADMIN,
            'secretary' => Episciences_Acl::ROLE_SECRETARY,
            'webmaster' => Episciences_Acl::ROLE_WEBMASTER,
        ];
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function rolesWithoutSiteRightsProvider(): array
    {
        return array_map(static fn(?string $role): array => [$role], self::rolesWithoutSiteRights());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesWithSiteRightsProvider(): array
    {
        return array_map(static fn(string $role): array => [$role], self::rolesWithSiteRights());
    }
}
