<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Ccsd_Db_Adapter_Cas;
use Episciences_Acl;
use Episciences_Csrf_Helper;
use Episciences_User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Zend_Auth;
use Zend_Auth_Storage_Interface;
use Zend_Auth_Storage_NonPersistent;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Account deletion by a journal secretary: only a valid identifier of another user, sent with
 * the request token of that user, deletes the local account data, and never the CAS account.
 * Both databases are partial mocks: nothing is executed.
 */
final class UserDeleteActionTest extends TestCase
{
    private const SECRETARY_UID = 42;

    private Zend_Db_Adapter_Abstract $previousAdapter;
    private mixed $previousCasAdapter;
    private Zend_Auth_Storage_Interface $previousAuthStorage;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $localDb;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $casDb;

    private Zend_Controller_Response_HttpTestCase $response;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php';

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->localDb = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['delete', 'query'])
            ->getMock();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->localDb);

        $this->casDb = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['delete'])
            ->getMock();
        $property = new ReflectionProperty(Ccsd_Db_Adapter_Cas::class, 'cas_adapter');
        $property->setAccessible(true);
        $this->previousCasAdapter = $property->getValue();
        $property->setValue(null, $this->casDb);

        $this->previousAuthStorage = Zend_Auth::getInstance()->getStorage();
        Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());

        $secretary = $this->createMock(Episciences_User::class);
        $secretary->method('getUid')->willReturn(self::SECRETARY_UID);
        $secretary->method('getAllRoles')->willReturn([RVID => [Episciences_Acl::ROLE_SECRETARY]]);
        Zend_Auth::getInstance()->getStorage()->write($secretary);
    }

    protected function tearDown(): void
    {
        // The test request writes its post data into $_POST
        $_POST = [];
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
        (new ReflectionProperty(Ccsd_Db_Adapter_Cas::class, 'cas_adapter'))->setValue(null, $this->previousCasAdapter);
        Zend_Auth::getInstance()->setStorage($this->previousAuthStorage);
    }

    /**
     * @param array<string, string> $post
     * @return string the body of the answer
     */
    private function delete(array $post, string $method = 'POST'): string
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod($method)->setPost($post);
        $this->response = new Zend_Controller_Response_HttpTestCase();
        $controller = new \UserDefaultController($request, $this->response);

        ob_start();
        try {
            $controller->deleteAction();
        } finally {
            $output = (string)ob_get_clean();
        }

        return $output;
    }

    /**
     * @return array<string, string> the post data of a delete request carrying a valid token
     */
    private function validRequest(int $uid, string $table = 'localUsers'): array
    {
        $token = Episciences_Csrf_Helper::generateToken('user_delete_' . $uid);

        return ['userId' => (string)$uid, 'table' => $table, (string)$token['name'] => (string)$token['value']];
    }

    public function testGetRequestIsRefusedWithoutDeletion(): void
    {
        $this->localDb->expects(self::never())->method('delete');

        $output = $this->delete($this->validRequest(5), 'GET');

        self::assertSame(403, $this->response->getHttpResponseCode());
        self::assertSame('0', $output);
    }

    public function testUserWithoutSecretaryRoleIsRefusedWithoutDeletion(): void
    {
        $member = $this->createMock(Episciences_User::class);
        $member->method('getUid')->willReturn(self::SECRETARY_UID);
        $member->method('getAllRoles')->willReturn([RVID => [Episciences_Acl::ROLE_MEMBER]]);
        Zend_Auth::getInstance()->getStorage()->write($member);
        $this->localDb->expects(self::never())->method('delete');

        $output = $this->delete($this->validRequest(5));

        self::assertSame(403, $this->response->getHttpResponseCode());
        self::assertSame('0', $output);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIdentifierProvider(): array
    {
        return [
            'condition appended' => ['5 OR 1=1'],
            'zero' => ['0'],
            'negative' => ['-3'],
            'decimal' => ['5.0'],
            'empty' => [''],
        ];
    }

    /**
     * @dataProvider invalidIdentifierProvider
     */
    public function testInvalidIdentifierIsRefusedWithoutDeletion(string $userId): void
    {
        $this->localDb->expects(self::never())->method('delete');
        $this->localDb->expects(self::never())->method('query');

        $output = $this->delete(['userId' => $userId, 'table' => 'localUsers']);

        self::assertSame(400, $this->response->getHttpResponseCode());
        self::assertSame('0', $output);
    }

    public function testRequestWithoutTokenIsRefusedWithoutDeletion(): void
    {
        $this->localDb->expects(self::never())->method('delete');

        $output = $this->delete(['userId' => '5', 'table' => 'localUsers']);

        self::assertSame(403, $this->response->getHttpResponseCode());
        self::assertSame('0', $output);
    }

    public function testSecretaryCannotDeleteTheirOwnAccount(): void
    {
        $this->localDb->expects(self::never())->method('delete');

        $output = $this->delete($this->validRequest(self::SECRETARY_UID));

        self::assertSame(403, $this->response->getHttpResponseCode());
        self::assertSame('0', $output);
    }

    public function testCasAccountIsNeverDeleted(): void
    {
        $this->casDb->expects(self::never())->method('delete');
        $this->localDb->expects(self::never())->method('delete');

        $output = $this->delete($this->validRequest(5, 'casUsers'));

        self::assertSame('0', $output);
    }

    public function testValidRequestDeletesTheLocalRolesForTheJournal(): void
    {
        $this->casDb->expects(self::never())->method('delete');
        $this->localDb->expects(self::once())->method('delete')
            ->with('USER_ROLES', ['RVID = ?' => RVID, 'UID = ?' => 5])
            ->willReturn(1);

        $output = $this->delete($this->validRequest(5));

        self::assertSame(200, $this->response->getHttpResponseCode());
        self::assertSame('1', $output);
    }
}
