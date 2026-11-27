<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Controller_Request_HttpTestCase;

/**
 * UserDefaultController::editAction() must edit the same account on display and on
 * submission: the edited account comes from the "userid" request parameter, never
 * from the posted form values, and is kept in the form action and redirections.
 */
final class UserDefaultControllerEditTargetTest extends TestCase
{
    private const CURRENT_UID = 100;
    private const OTHER_UID = 1788032;

    /** @var array<string, mixed> */
    private array $savedGet;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php';
        $this->savedGet = $_GET;
    }

    protected function tearDown(): void
    {
        // Zend_Controller_Request_HttpTestCase::setQuery() writes to $_GET
        $_GET = $this->savedGet;
    }

    private function resolveEditedUserId(Zend_Controller_Request_HttpTestCase $request, bool $isSecretary): int
    {
        $method = new ReflectionMethod(\UserDefaultController::class, 'resolveEditedUserId');
        $method->setAccessible(true);

        return $method->invoke(null, $request, $isSecretary, self::CURRENT_UID);
    }

    /**
     * @return array<string, int>
     */
    private function editUrlParams(int $editedUid): array
    {
        $method = new ReflectionMethod(\UserDefaultController::class, 'editUrlParams');
        $method->setAccessible(true);

        return $method->invoke(null, $editedUid, self::CURRENT_UID);
    }

    private function submission(): Zend_Controller_Request_HttpTestCase
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST');
        $request->setPost(['submit' => '', 'ccsd' => ['LASTNAME' => 'Doe']]);

        return $request;
    }

    public function testDisplayBySecretaryTargetsTheRequestedAccount(): void
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setQuery('userid', (string)self::OTHER_UID);

        self::assertSame(self::OTHER_UID, $this->resolveEditedUserId($request, true));
    }

    public function testSubmissionBySecretaryKeepsTheRequestedAccount(): void
    {
        $request = $this->submission();
        $request->setParam('userid', (string)self::OTHER_UID);

        self::assertSame(self::OTHER_UID, $this->resolveEditedUserId($request, true));
    }

    public function testSubmissionWithoutUseridTargetsTheCurrentAccount(): void
    {
        self::assertSame(self::CURRENT_UID, $this->resolveEditedUserId($this->submission(), true));
    }

    public function testNonSecretaryAlwaysTargetsTheCurrentAccount(): void
    {
        $request = $this->submission();
        $request->setParam('userid', (string)self::OTHER_UID);

        self::assertSame(self::CURRENT_UID, $this->resolveEditedUserId($request, false));
    }

    public function testInvalidUseridTargetsTheCurrentAccount(): void
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setQuery('userid', 'abc');

        self::assertSame(self::CURRENT_UID, $this->resolveEditedUserId($request, true));
    }

    public function testUrlParamsNameTheEditedAccountOnlyWhenItIsNotTheCurrentOne(): void
    {
        self::assertSame(['userid' => self::OTHER_UID], $this->editUrlParams(self::OTHER_UID));
        self::assertSame([], $this->editUrlParams(self::CURRENT_UID));
    }
}
