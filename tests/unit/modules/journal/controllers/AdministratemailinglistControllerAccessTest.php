<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Security\AccessLog;
use Episciences_Acl;
use Episciences_User;
use PHPUnit\Framework\TestCase;
use Zend_Auth;
use Zend_Controller_Action_Helper_Redirector;
use Zend_Controller_Action_HelperBroker;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use unit\library\Episciences\Security\SecurityLogCaptureTrait;

require_once dirname(__DIR__, 3) . '/library/Episciences/Security/SecurityLogCaptureTrait.php';

/**
 * The mailing lists are managed by the journal staff only: any other user is sent to the access denied page
 * as soon as the controller starts, and the refusal is logged.
 */
final class AdministratemailinglistControllerAccessTest extends TestCase
{
    use SecurityLogCaptureTrait;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/AdministratemailinglistController.php';
        $this->redirector()->setExit(false);
    }

    protected function tearDown(): void
    {
        Zend_Auth::getInstance()->clearIdentity();
        $this->redirector()->setExit(true);
    }

    private function redirector(): Zend_Controller_Action_Helper_Redirector
    {
        /** @var Zend_Controller_Action_Helper_Redirector $redirector */
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');

        return $redirector;
    }

    private function logInAs(string $role): void
    {
        $user = new Episciences_User(['UID' => 4242]);
        $user->setRoles([RVID => [$role]]);
        Zend_Auth::getInstance()->getStorage()->write($user);
    }

    /**
     * The controller checks the roles when it is built
     */
    private function startController(): Zend_Controller_Response_HttpTestCase
    {
        $response = new Zend_Controller_Response_HttpTestCase();
        new \AdministratemailinglistController(new Zend_Controller_Request_HttpTestCase(), $response);

        return $response;
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function deniedRoles(): iterable
    {
        yield 'guest' => [null];
        yield 'editor' => [Episciences_Acl::ROLE_EDITOR];
        yield 'reviewer' => [Episciences_Acl::ROLE_REVIEWER];
    }

    /**
     * @dataProvider deniedRoles
     */
    public function testUserOutsideOfTheJournalStaffIsDeniedAndLogged(?string $role): void
    {
        if ($role !== null) {
            $this->logInAs($role);
        }

        $response = $this->startController();

        self::assertTrue($response->isRedirect());
        self::assertSame([[AccessLog::ACL_DENIED, 'role_check']], $this->loggedEvents());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function staffRoles(): iterable
    {
        yield 'secretary' => [Episciences_Acl::ROLE_SECRETARY];
        yield 'chief editor' => [Episciences_Acl::ROLE_CHIEF_EDITOR];
        yield 'administrator' => [Episciences_Acl::ROLE_ADMIN];
    }

    /**
     * @dataProvider staffRoles
     */
    public function testJournalStaffIsNotLogged(string $role): void
    {
        $this->logInAs($role);

        $response = $this->startController();

        self::assertFalse($response->isRedirect());
        self::assertSame([], $this->loggedEvents());
    }
}
