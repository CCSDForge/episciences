<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Security\AccessLog;
use Episciences_Acl;
use Episciences_User;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Zend_Auth;
use Zend_Controller_Action_Helper_Redirector;
use Zend_Controller_Action_HelperBroker;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * The mailing lists are managed by the journal staff only: any other user is sent to the access denied page
 * as soon as the controller starts, and the refusal is logged.
 */
final class AdministratemailinglistControllerAccessTest extends TestCase
{
    private TestHandler $logHandler;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/AdministratemailinglistController.php';
        $this->redirector()->setExit(false);
        $this->logHandler = new TestHandler();
        AccessLog::setLogger(new Logger(AccessLog::CHANNEL, [$this->logHandler]));
        AccessLog::reset();
    }

    protected function tearDown(): void
    {
        Zend_Auth::getInstance()->clearIdentity();
        $this->redirector()->setExit(true);
        AccessLog::setLogger(new NullLogger());
        AccessLog::reset();
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
     * @return list<array{string, string}> event and reason of each security log record
     */
    private function loggedEvents(): array
    {
        return array_map(
            static fn(LogRecord $record): array => [$record->message, $record->context['reason'] ?? ''],
            $this->logHandler->getRecords()
        );
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
