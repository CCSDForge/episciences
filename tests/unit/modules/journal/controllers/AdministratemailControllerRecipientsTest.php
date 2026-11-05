<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Acl;
use PHPUnit\Framework\TestCase;
use Zend_Auth;
use Zend_Auth_Storage_Interface;
use Zend_Auth_Storage_NonPersistent;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * Recipients search of the mail forms (AdministratemailController::getrecipientsAction()):
 * JSON answer, never cached, refused outside of an XHR GET with valid parameters, and refused to the
 * users the access policy does not allow.
 */
final class AdministratemailControllerRecipientsTest extends TestCase
{
    private Zend_Auth_Storage_Interface $previousAuthStorage;

    protected function setUp(): void
    {
        $this->previousAuthStorage = Zend_Auth::getInstance()->getStorage();
        Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());

        require_once APPLICATION_PATH . '/modules/journal/controllers/AdministratemailController.php';
    }

    protected function tearDown(): void
    {
        $_GET = []; // setQuery() writes the global
        Zend_Auth::getInstance()->setStorage($this->previousAuthStorage);
    }

    /**
     * @param list<string> $roles roles of the logged user in the current journal
     */
    private function logIn(array $roles): void
    {
        $user = $this->createMock(\Episciences_User::class);
        $user->method('getUid')->willReturn(1);
        $user->method('getAllRoles')->willReturn([RVID => $roles]);
        $user->method('getRoles')->willReturn($roles);
        Zend_Auth::getInstance()->getStorage()->write($user);
    }

    /**
     * @param array<string, string> $query
     */
    private function search(array $query, string $method = 'GET', bool $isXhr = true): Zend_Controller_Response_HttpTestCase
    {
        $_GET = [];
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod($method);
        $request->setQuery($query);
        if ($isXhr) {
            $request->setHeader('X-Requested-With', 'XMLHttpRequest');
        }
        $response = new Zend_Controller_Response_HttpTestCase();

        (new \AdministratemailController($request, $response))->getrecipientsAction();

        return $response;
    }

    private static function assertJsonNotCached(Zend_Controller_Response_HttpTestCase $response): void
    {
        $headers = array_column($response->getHeaders(), 'value', 'name');

        self::assertSame('application/json; charset=utf-8', $headers['Content-Type'] ?? null);
        self::assertSame('no-store', $headers['Cache-Control'] ?? null);
        self::assertSame('nosniff', $headers['X-Content-Type-Options'] ?? null);
    }

    /**
     * @return array<string, array{array<string, string>, string, bool}>
     */
    public static function badRequests(): array
    {
        return [
            'not an XHR' => [['q' => 'dup'], 'GET', false],
            'POST' => [['q' => 'dup'], 'POST', true],
            'role that cannot be listed' => [['role' => 'reviewer'], 'GET', true],
            'docid not a number' => [['docid' => '1 OR 1=1'], 'GET', true],
            'limit not a number' => [['q' => 'dup', 'limit' => '-1'], 'GET', true],
            'query too long' => [['q' => str_repeat('a', 101)], 'GET', true],
        ];
    }

    /**
     * @dataProvider badRequests
     * @param array<string, string> $query
     */
    public function testInvalidRequestIsRefusedWith400(array $query, string $method, bool $isXhr): void
    {
        $this->logIn([Episciences_Acl::ROLE_SECRETARY]);

        $response = $this->search($query, $method, $isXhr);

        self::assertSame(400, $response->getHttpResponseCode());
        self::assertJsonNotCached($response);
        self::assertSame(['error' => 'bad_request'], json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testUserWithoutEditorialRoleIsRefusedWith403(): void
    {
        $this->logIn([Episciences_Acl::ROLE_MEMBER, Episciences_Acl::ROLE_REVIEWER]);

        $response = $this->search(['q' => 'dup']);

        self::assertSame(403, $response->getHttpResponseCode());
        self::assertJsonNotCached($response);
        self::assertSame(['error' => 'forbidden'], json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }
}
