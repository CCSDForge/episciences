<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Acl;
use Episciences_Paper;
use Episciences_User;
use PHPUnit\Framework\TestCase;
use Zend_Auth;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Registry;

/**
 * PaperController::cslAction(): the CSL-JSON export follows the paper page access rules.
 * The logged user is a real identity with roles; the paper and its CSL-JSON, read from the database, are stubbed.
 */
final class PaperControllerCslAccessTest extends TestCase
{
    private const UID = 4242;
    private const DOCID = 12;
    public const CSL = '{"type":"article-journal"}';

    private mixed $previousSettings = null;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/PaperController.php';
        $this->previousSettings = Zend_Registry::isRegistered('reviewSettings') ? Zend_Registry::get('reviewSettings') : null;
        Zend_Registry::set('reviewSettings', []);
    }

    protected function tearDown(): void
    {
        Zend_Auth::getInstance()->clearIdentity();
        Zend_Registry::set('reviewSettings', $this->previousSettings);
    }

    /**
     * @param string[] $roles
     */
    private function logInAs(array $roles = [Episciences_Acl::ROLE_MEMBER]): void
    {
        $user = new Episciences_User(['UID' => self::UID]);
        $user->setRoles([RVID => $roles]);
        Zend_Auth::getInstance()->getStorage()->write($user);
    }

    /**
     * @param array{published?: bool, owner?: bool, deleted?: bool, removed?: bool} $state
     */
    private function paper(array $state = []): Episciences_Paper
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getDocid')->willReturn(self::DOCID);
        $paper->method('isDeleted')->willReturn($state['deleted'] ?? false);
        $paper->method('isRemoved')->willReturn($state['removed'] ?? false);
        $paper->method('isPublished')->willReturn($state['published'] ?? false);
        $paper->method('isOwner')->willReturn($state['owner'] ?? false);
        $paper->method('getReviewers')->willReturn([]);

        return $paper;
    }

    private function csl(?Episciences_Paper $paper, string $docId = '12'): Zend_Controller_Response_HttpTestCase
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setControllerName('paper')->setActionName('csl')->setParam('id', $docId);
        $response = new Zend_Controller_Response_HttpTestCase();

        // The stub reaches the controller as an invoke argument, the way the front controller passes its own
        $controller = new class ($request, $response, ['paper' => $paper]) extends \PaperController {
            protected function findPaper(int $docId): ?Episciences_Paper
            {
                return $this->getInvokeArg('paper');
            }

            protected function getCsl(Episciences_Paper $paper): string
            {
                return PaperControllerCslAccessTest::CSL;
            }
        };
        $controller->cslAction();

        return $response;
    }

    private static function assertNotFound(Zend_Controller_Response_HttpTestCase $response): void
    {
        self::assertSame(404, $response->getHttpResponseCode());
        self::assertSame('[]', $response->getBody());
    }

    public function testPublishedPaperIsExportedToAnonymousVisitors(): void
    {
        $response = $this->csl($this->paper(['published' => true]));

        self::assertSame(200, $response->getHttpResponseCode());
        self::assertSame(self::CSL, $response->getBody());
        self::assertContains(
            ['name' => 'Content-Type', 'value' => 'application/json; charset=UTF-8', 'replace' => true],
            $response->getHeaders()
        );
    }

    public function testUnpublishedPaperIsNotExportedToAnonymousVisitors(): void
    {
        self::assertNotFound($this->csl($this->paper()));
    }

    public function testUnpublishedPaperIsNotExportedToAMemberWithoutRoleOnIt(): void
    {
        $this->logInAs();

        self::assertNotFound($this->csl($this->paper()));
    }

    public function testUnpublishedPaperIsExportedToItsAuthor(): void
    {
        $this->logInAs();

        self::assertSame(self::CSL, $this->csl($this->paper(['owner' => true]))->getBody());
    }

    public function testUnpublishedPaperIsExportedToTheSecretary(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        self::assertSame(self::CSL, $this->csl($this->paper())->getBody());
    }

    public function testPaperOfAnotherJournalOrUnknownIsNotExported(): void
    {
        self::assertNotFound($this->csl(null));
    }

    public function testDeletedOrRemovedPaperIsNotExported(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        self::assertNotFound($this->csl($this->paper(['published' => true, 'deleted' => true])));
        self::assertNotFound($this->csl($this->paper(['published' => true, 'removed' => true])));
    }

    public function testNonNumericIdIsNotExported(): void
    {
        self::assertNotFound($this->csl($this->paper(['published' => true]), 'abc'));
    }
}
