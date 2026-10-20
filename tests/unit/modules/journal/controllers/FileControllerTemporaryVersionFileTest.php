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
    /** Number of conflict of interest lookups by the controller under test */
    public static int $conflictLookups = 0;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/FileController.php';
        self::$hasConflict = false;
        self::$conflictLookups = 0;
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
        $paper->method('getEditor')->willReturn(($state['assigned'] ?? false) ? new \stdClass() : false);
        $paper->method('getCopyEditor')->willReturn(false);
        // Loads the latest version again: the check goes through every version instead
        $paper->expects(self::never())->method('isAssignedEditorOrCopyEditor');

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
                FileControllerTemporaryVersionFileTest::$conflictLookups++;
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

    public function testVersionsAfterTheOneGrantingAccessAreNotChecked(): void
    {
        $this->logInAs();
        $previous = $this->createMock(Episciences_Paper::class);
        $previous->expects(self::never())->method('isOwnerOrCoAuthor');
        $previous->expects(self::never())->method('getReviewers');

        // The latest version comes first (see FileController::findPaperVersions())
        [$code] = $this->runTmpAction([$this->version(['author' => true]), $previous]);

        self::assertSame(200, $code);
    }

    public function testConflictOfInterestIsNotLookedUpForTheAuthor(): void
    {
        // Only withdraws the access of the editorial staff
        $this->logInAs();

        [$code] = $this->runTmpAction([$this->version(['author' => true])]);

        self::assertSame(200, $code);
        self::assertSame(0, self::$conflictLookups);
    }

    public function testConflictOfInterestIsLookedUpOnceForTheEditorialStaff(): void
    {
        // Assigned to both versions, refused on both: declared for the paper, the conflict is not looked up again
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);
        self::$hasConflict = true;

        [$code] = $this->runTmpAction([$this->version(['assigned' => true]), $this->version(['assigned' => true])]);

        self::assertSame(404, $code);
        self::assertSame(1, self::$conflictLookups);
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
     * Upload and removal of the files of the temporary versions: the paper id resolved once
     * (FileController::resolvePaperId()), then FileController::isAllowedToHandleDocumentFiles(), as uploadAction()
     * and deleteAction() do
     *
     * @param Episciences_Paper[] $versions
     * @param Episciences_Paper|RuntimeException|false|null $version the version of the docId: by default one of
     *                                                               the paper, false when it is not found
     * @return array{bool, list<int>} access granted, paper ids looked up
     */
    private function runHandleCheck(
        array $versions,
        int $docId,
        int $paperId,
        Episciences_Paper|RuntimeException|false|null $version = null
    ): array
    {
        $journal = $this->createMock(Episciences_Review::class);
        $journal->method('getSettings')->willReturn([]);

        if ($version === null) {
            $version = $this->versionOfDocId(self::PAPERID, $docId);
        }

        $controller = new class (
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase(),
            ['versions' => $versions, 'journal' => $journal, 'version' => $version]
        ) extends \FileController {
            /** @var list<int> */
            public array $lookedUp = [];

            public function check(int $docId, int $paperId): bool
            {
                $paperId = $this->resolvePaperId($docId, $paperId);

                return $paperId !== null && $this->isAllowedToHandleDocumentFiles('tmp_attachments', $docId, $paperId);
            }

            protected function findPaper(int $docId): ?Episciences_Paper
            {
                $version = $this->getInvokeArg('version');
                if ($version instanceof RuntimeException) {
                    throw $version;
                }

                return $version ?: null;
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
                FileControllerTemporaryVersionFileTest::$conflictLookups++;
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

    public function testReviewerCannotHandleTheTemporaryVersionFiles(): void
    {
        // Reads the author's answer (see testReviewerOfTheTemporaryVersionOnlyIsAllowed()), but must not alter it
        $this->logInAs([Episciences_Acl::ROLE_REVIEWER]);

        [$allowed] = $this->runHandleCheck([$this->version(), $this->version(['reviewer' => true])], 15, self::PAPERID);

        self::assertFalse($allowed);
    }

    public function testAssignedEditorCanHandleTheTemporaryVersionFiles(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        [$allowed] = $this->runHandleCheck([$this->version(['assigned' => true])], 15, self::PAPERID);

        self::assertTrue($allowed);
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

    /**
     * Version loaded for the docId given (see FileController::resolvePaperId())
     */
    private function versionOfDocId(int $paperId, int $docId): Episciences_Paper
    {
        $version = $this->createMock(Episciences_Paper::class);
        $version->method('getPaperid')->willReturn($paperId);
        $version->method('getDocid')->willReturn($docId);

        return $version;
    }

    public function testPaperIdIsTakenFromTheVersionWhenOnlyTheDocIdIsGiven(): void
    {
        $this->logInAs();

        [$allowed, $lookedUp] = $this->runHandleCheck([$this->version(['author' => true])], 15, 0);

        self::assertTrue($allowed);
        self::assertSame([self::PAPERID], $lookedUp);
    }

    public function testPaperIdOfAnotherPaperThanTheVersionIsRefused(): void
    {
        // Allowed on the paper of the version: the paper id given must not point the files elsewhere
        $this->logInAs();

        [$allowed, $lookedUp] = $this->runHandleCheck([$this->version(['author' => true])], 15, 555);

        self::assertFalse($allowed);
        self::assertSame([], $lookedUp);
    }

    public function testVersionOfTheDocIdNotFoundIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        [$allowed, $lookedUp] = $this->runHandleCheck([$this->version(['author' => true])], 15, self::PAPERID, false);

        self::assertFalse($allowed);
        self::assertSame([], $lookedUp);
    }

    public function testVersionWithoutPaperIdFallsBackToItsDocId(): void
    {
        // As the save paths of the temporary versions do (getPaperid() ?: docId)
        $this->logInAs();

        [$allowed, $lookedUp] = $this->runHandleCheck(
            [$this->version(['author' => true])],
            15,
            15,
            $this->versionOfDocId(0, 15)
        );

        self::assertTrue($allowed);
        self::assertSame([15], $lookedUp);
    }

    public function testFailureWhileLoadingTheVersionOfTheDocIdIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        [$allowed, $lookedUp] = $this->runHandleCheck(
            [$this->version(['author' => true])],
            15,
            0,
            new RuntimeException('Database unavailable')
        );

        self::assertFalse($allowed);
        self::assertSame([], $lookedUp);
    }

    public function testTemporaryVersionFilesWithoutPaperAreRefused(): void
    {
        // Not the session-scoped attachments directory: the files would be written in a "0/tmp/" directory
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        [$allowed, $lookedUp] = $this->runHandleCheck([$this->version(['author' => true])], 0, 0);

        self::assertFalse($allowed);
        self::assertSame([], $lookedUp);
    }

    /**
     * FileController::deleteAction() on the temporary versions directory, for a user allowed to handle its files
     *
     * @return array{int, list<array{int, string}>} HTTP response code, submitted files looked up
     */
    private function runDeleteAction(bool $isSubmitted): array
    {
        \Zend_Session::$_unitTestEnabled = true;
        $session = new \Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->csrfToken = 'tmp-files-token';
        // The test request keeps what an earlier one posted
        $_POST = [];

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost([
            'csrf_token' => 'tmp-files-token',
            'path' => 'tmp_attachments',
            'paperId' => (string)self::PAPERID,
            'file' => 'answer.pdf',
        ]);
        $response = new Zend_Controller_Response_HttpTestCase();

        $controller = new class ($request, $response, ['isSubmitted' => $isSubmitted]) extends \FileController {
            /** @var list<array{int, string}> */
            public array $lookedUp = [];

            protected function isAllowedToHandleDocumentFiles(string $path, int $docId, int $paperId): bool
            {
                return true;
            }

            protected function isSubmittedTemporaryVersionFile(int $paperId, string $file): bool
            {
                $this->lookedUp[] = [$paperId, $file];
                return $this->getInvokeArg('isSubmitted');
            }
        };

        try {
            $controller->deleteAction();
        } finally {
            unset($session->csrfToken);
        }

        return [$response->getHttpResponseCode(), $controller->lookedUp];
    }

    public function testFileOfASubmittedAnswerIsNotRemoved(): void
    {
        $this->logInAs();

        [$code, $lookedUp] = $this->runDeleteAction(true);

        self::assertSame(403, $code);
        self::assertSame([[self::PAPERID, 'answer.pdf']], $lookedUp);
    }

    public function testUploadNotSubmittedYetCanBeRemoved(): void
    {
        $this->logInAs();

        [$code, $lookedUp] = $this->runDeleteAction(false);

        self::assertSame(200, $code);
        self::assertSame([[self::PAPERID, 'answer.pdf']], $lookedUp);
    }

    public function testFileIsConsideredSubmittedWhenTheLookupFails(): void
    {
        $previousAdapter = \Zend_Db_Table_Abstract::getDefaultAdapter();
        $adapter = $this->getMockBuilder($previousAdapter::class)
            ->setConstructorArgs([$previousAdapter->getConfig()])
            ->onlyMethods(['fetchCol'])
            ->getMock();
        $adapter->method('fetchCol')->willThrowException(new \Zend_Db_Statement_Exception('Database unavailable'));
        \Zend_Db_Table_Abstract::setDefaultAdapter($adapter);

        $controller = new class (
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase()
        ) extends \FileController {
            public function check(int $paperId, string $file): bool
            {
                return $this->isSubmittedTemporaryVersionFile($paperId, $file);
            }
        };

        try {
            self::assertTrue($controller->check(self::PAPERID, 'answer.pdf'));
        } finally {
            \Zend_Db_Table_Abstract::setDefaultAdapter($previousAdapter);
        }
    }
}
