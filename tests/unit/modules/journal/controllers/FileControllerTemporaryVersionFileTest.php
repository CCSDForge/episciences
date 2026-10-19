<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Acl;
use Episciences_Paper;
use Episciences_Review;
use Episciences_User;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zend_Auth;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * FileController::tmpAction(): the files of the temporary versions, stored by paper, are only given to the
 * users allowed on a version of the paper (see DocumentFilePolicyTest for the rules themselves). The action
 * is dispatched for real with a logged user holding real roles; the versions of the paper, the journal and the
 * conflict of interest answer, all read from the database, are stubbed, and the file delivery is recorded.
 */
final class FileControllerTemporaryVersionFileTest extends TestCase
{
    private const UID = 4242;
    private const PAPERID = 12;

    /** Conflict of interest answer of the logged user, read by the controller under test */
    public static bool $hasConflict = false;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/FileController.php';
        self::$hasConflict = false;
    }

    protected function tearDown(): void
    {
        Zend_Auth::getInstance()->clearIdentity();
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
     * @param array{author?: bool, reviewer?: bool, assigned?: bool} $state
     */
    private function version(array $state = []): Episciences_Paper
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('isOwnerOrCoAuthor')->willReturn($state['author'] ?? false);
        $paper->method('getReviewers')->willReturn(($state['reviewer'] ?? false) ? [self::UID => new \stdClass()] : []);
        $paper->method('isAssignedEditorOrCopyEditor')->willReturn($state['assigned'] ?? false);

        return $paper;
    }

    /**
     * @param Episciences_Paper[]|RuntimeException $versions
     * @param array<string, mixed> $params
     * @return array{int, list<array{string, string}>, list<int>} HTTP response code, files delivered, paper ids looked up
     */
    private function runTmpAction(array|RuntimeException $versions, array $params = []): array
    {
        $journal = $this->createMock(Episciences_Review::class);
        $journal->method('getSettings')->willReturn([]);

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setParams($params + ['docId' => (string)self::PAPERID, 'filename' => 'answer', 'extension' => 'pdf']);
        $response = new Zend_Controller_Response_HttpTestCase();

        // The stubs reach the controller as invoke arguments, the way the front controller passes its own
        $controller = new class ($request, $response, ['versions' => $versions, 'journal' => $journal]) extends \FileController {
            /** @var list<array{string, string}> */
            public array $delivered = [];
            /** @var list<int> */
            public array $lookedUp = [];

            protected function findPaperVersions(int $paperId): array
            {
                $this->lookedUp[] = $paperId;
                $versions = $this->getInvokeArg('versions');
                if ($versions instanceof RuntimeException) {
                    throw $versions;
                }
                return $versions;
            }

            protected function findJournal(): Episciences_Review
            {
                return $this->getInvokeArg('journal');
            }

            public static function isConflictDetected(Episciences_Paper $paper, Episciences_Review $journal = null): bool
            {
                return FileControllerTemporaryVersionFileTest::$hasConflict;
            }

            protected function loadFile(string $path, string $file, bool $forceDownload = false): void
            {
                $this->delivered[] = [$path, $file];
            }
        };

        $controller->tmpAction();

        return [$response->getHttpResponseCode(), $controller->delivered, $controller->lookedUp];
    }

    public function testAuthorGetsTheFileFromTheTemporaryDirectoryOfThePaper(): void
    {
        $this->logInAs();

        [$code, $delivered, $lookedUp] = $this->runTmpAction([$this->version(['author' => true])]);

        self::assertSame(200, $code);
        self::assertSame([[REVIEW_FILES_PATH . self::PAPERID . '/tmp', 'answer.pdf']], $delivered);
        self::assertSame([self::PAPERID], $lookedUp);
    }

    public function testAnyOtherMemberIsRefused(): void
    {
        $this->logInAs();

        [$code, $delivered] = $this->runTmpAction([$this->version(), $this->version()]);

        self::assertSame(404, $code);
        self::assertSame([], $delivered);
    }

    public function testReviewerOfTheTemporaryVersionOnlyIsAllowed(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_REVIEWER]);

        [$code, $delivered] = $this->runTmpAction([$this->version(), $this->version(['reviewer' => true])]);

        self::assertSame(200, $code);
        self::assertCount(1, $delivered);
    }

    public function testAssignedEditorIsAllowed(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        [$code] = $this->runTmpAction([$this->version(['assigned' => true])]);

        self::assertSame(200, $code);
    }

    public function testAssignedEditorInConflictOfInterestIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);
        self::$hasConflict = true;

        [$code, $delivered] = $this->runTmpAction([$this->version(['assigned' => true])]);

        self::assertSame(404, $code);
        self::assertSame([], $delivered);
    }

    public function testPaperWithoutVersionInTheJournalIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        [$code, $delivered] = $this->runTmpAction([]);

        self::assertSame(404, $code);
        self::assertSame([], $delivered);
    }

    public function testFailureWhileLoadingTheVersionsIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        [$code, $delivered] = $this->runTmpAction(new RuntimeException('database down'));

        self::assertSame(404, $code);
        self::assertSame([], $delivered);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function invalidRequestProvider(): array
    {
        return [
            'directory in the file name' => [['filename' => '../reports/1/report']],
            'directory in the extension' => [['extension' => 'pdf/x']],
            'missing paper id' => [['docId' => '0']],
        ];
    }

    /**
     * @dataProvider invalidRequestProvider
     * @param array<string, string> $params
     */
    public function testInvalidRequestIsRefusedWithoutLookingUpThePaper(array $params): void
    {
        $this->logInAs();

        [$code, $delivered, $lookedUp] = $this->runTmpAction([$this->version(['author' => true])], $params);

        self::assertSame(404, $code);
        self::assertSame([], $delivered);
        self::assertSame([], $lookedUp);
    }

    /**
     * Upload and removal of the files of the temporary versions (FileController::isAllowedToHandleDocumentFiles())
     *
     * @param Episciences_Paper[] $versions
     * @return array{bool, list<int>} access granted, paper ids looked up
     */
    private function runHandleCheck(array $versions, int $docId, int $paperId, ?Episciences_Paper $version = null): array
    {
        $journal = $this->createMock(Episciences_Review::class);
        $journal->method('getSettings')->willReturn([]);

        $controller = new class (
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase(),
            ['versions' => $versions, 'journal' => $journal, 'version' => $version]
        ) extends \FileController {
            /** @var list<int> */
            public array $lookedUp = [];

            public function check(int $docId, int $paperId): bool
            {
                return $this->isAllowedToHandleDocumentFiles('tmp_attachments', $docId, $paperId);
            }

            protected function findPaper(int $docId): ?Episciences_Paper
            {
                return $this->getInvokeArg('version');
            }

            protected function findPaperVersions(int $paperId): array
            {
                $this->lookedUp[] = $paperId;
                return $this->getInvokeArg('versions');
            }

            protected function findJournal(): Episciences_Review
            {
                return $this->getInvokeArg('journal');
            }

            public static function isConflictDetected(Episciences_Paper $paper, Episciences_Review $journal = null): bool
            {
                return FileControllerTemporaryVersionFileTest::$hasConflict;
            }
        };

        return [$controller->check($docId, $paperId), $controller->lookedUp];
    }

    public function testAuthorCanHandleTheTemporaryVersionFiles(): void
    {
        $this->logInAs();

        [$allowed, $lookedUp] = $this->runHandleCheck([$this->version(['author' => true])], 15, self::PAPERID);

        self::assertTrue($allowed);
        self::assertSame([self::PAPERID], $lookedUp);
    }

    public function testUnassignedEditorCannotHandleTheTemporaryVersionFiles(): void
    {
        // Allowed to manage the papers of the journal, but encapsulated and not assigned to this one
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        [$allowed] = $this->runHandleCheck([$this->version()], 15, self::PAPERID);

        self::assertFalse($allowed);
    }

    public function testEditorInConflictOfInterestCannotHandleTheTemporaryVersionFiles(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);
        self::$hasConflict = true;

        [$allowed] = $this->runHandleCheck([$this->version(['assigned' => true])], 15, self::PAPERID);

        self::assertFalse($allowed);
    }

    public function testPaperIdIsTakenFromTheVersionWhenOnlyTheDocIdIsGiven(): void
    {
        $this->logInAs();
        $version = $this->createMock(Episciences_Paper::class);
        $version->method('getPaperid')->willReturn(self::PAPERID);

        [$allowed, $lookedUp] = $this->runHandleCheck([$this->version(['author' => true])], 15, 0, $version);

        self::assertTrue($allowed);
        self::assertSame([self::PAPERID], $lookedUp);
    }

    public function testTemporaryVersionFilesWithoutPaperAreRefused(): void
    {
        // Not the session-scoped attachments directory: the files would be written in a "0/tmp/" directory
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        [$allowed, $lookedUp] = $this->runHandleCheck([$this->version(['author' => true])], 0, 0);

        self::assertFalse($allowed);
        self::assertSame([], $lookedUp);
    }
}
