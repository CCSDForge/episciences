<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use AdministratemailController;
use Episciences_Acl;
use unit\modules\ControllerTestCase;
use unit\modules\support\DispatchResult;

/**
 * Recipients search of the mail forms (AdministratemailController::getrecipientsAction()):
 * JSON answer, never cached, refused outside of an XHR GET with valid parameters, and refused to the
 * users the access policy does not allow.
 */
final class AdministratemailControllerRecipientsTest extends ControllerTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'AdministratemailController');
    }

    /**
     * @param array<string, string> $query
     */
    private function search(array $query, string $method = 'GET', bool $isXhr = true): DispatchResult
    {
        $request = $method === 'GET' ? $this->getRequest($query) : $this->postRequest()->setQuery($query);
        if ($isXhr) {
            $request->setHeader('X-Requested-With', 'XMLHttpRequest');
        }

        return $this->dispatch(AdministratemailController::class, 'getrecipients', $request);
    }

    private static function assertJsonNotCached(DispatchResult $result): void
    {
        self::assertSame('application/json; charset=utf-8', $result->header('Content-Type'));
        self::assertSame('no-store', $result->header('Cache-Control'));
        self::assertSame('nosniff', $result->header('X-Content-Type-Options'));
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
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        $result = $this->search($query, $method, $isXhr);

        self::assertSame(400, $result->statusCode());
        self::assertJsonNotCached($result);
        self::assertSame(['error' => 'bad_request'], $result->json());
    }

    public function testUserWithoutEditorialRoleIsRefusedWith403(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_MEMBER, Episciences_Acl::ROLE_REVIEWER]);

        $result = $this->search(['q' => 'dup']);

        self::assertSame(403, $result->statusCode());
        self::assertJsonNotCached($result);
        self::assertSame(['error' => 'forbidden'], $result->json());
        self::assertSame([], $this->db->queries(), 'refused before the journal is loaded');
    }
}
