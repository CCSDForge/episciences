<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Acl;
use PaperController;
use unit\modules\ControllerTestCase;

/**
 * PaperController::removeAction(): the author deletes a submission from the form of the paper page,
 * which posts the session request token. Any other request is sent back to the paper page before
 * the paper is even read.
 */
final class PaperControllerRemoveTest extends ControllerTestCase
{
    private const DOCID = 1234;

    protected function setUp(): void
    {
        self::loadController('journal', 'PaperController');
        $this->logInAs([Episciences_Acl::ROLE_MEMBER]);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function refusedRequests(): iterable
    {
        yield 'GET (former link)' => ['GET', false];
        yield 'GET with the request token' => ['GET', true];
        yield 'POST without the request token' => ['POST', false];
    }

    /**
     * @dataProvider refusedRequests
     */
    public function testRequestWithoutTheFormIsSentBackToThePaperPage(string $method, bool $withRequestToken): void
    {
        $params = ['id' => (string)self::DOCID];
        $request = $method === 'POST'
            ? $this->postRequest([], $withRequestToken, $params)
            : $this->getRequest($withRequestToken ? ['csrf_token' => $this->requestToken()] : [], $params);

        $result = $this->dispatch(PaperController::class, 'remove', $request);

        self::assertSame('/' . self::DOCID, $result->redirectUrl());
        self::assertSame([], $this->db->queries(), 'the paper is not even read');
    }

    public function testPostWithAWrongRequestTokenIsSentBackToThePaperPage(): void
    {
        $this->requestToken();
        $request = $this->postRequest(['csrf_token' => 'forged'], false, ['id' => (string)self::DOCID]);

        $result = $this->dispatch(PaperController::class, 'remove', $request);

        self::assertSame('/' . self::DOCID, $result->redirectUrl());
        self::assertSame([], $this->db->queries(), 'the paper is not even read');
    }

    public function testPostWithTheRequestTokenLooksUpThePaper(): void
    {
        // No paper is programmed: the lookup finds nothing and the action ends on the list of the submissions
        $request = $this->postRequest([], true, ['id' => (string)self::DOCID]);

        $result = $this->dispatch(PaperController::class, 'remove', $request);

        self::assertNotSame([], $this->db->selects(), 'the paper is looked up');
        self::assertSame([], $this->db->updates(), 'nothing is changed for a missing paper');
        self::assertStringEndsWith('paper/submitted', (string)$result->redirectUrl());
    }
}
