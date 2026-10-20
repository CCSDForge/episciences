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
 * is dispatched for real with a logged user holding real roles; the versions of the paper, the roles of the user
 * on them, the journal and the conflict of interest answer, all read from the database, are stubbed, and the file
 * delivery is recorded (see TemporaryVersionFileLookupTest for the queries themselves).
 */
final class FileControllerTemporaryVersionFileTest extends TestCase
{
    private const UID = 4242;
    private const PAPERID = 12;
    /** Versions of the paper, the latest first */
    private const DOCIDS = [17, 15, self::PAPERID];

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
     * Roles of the logged user on the versions of the paper (see FileController::findUserRolesOnVersions())
     *
     * @param array<string, bool> $roles
     * @return array{owner: bool, coAuthor: bool, editor: bool, copyEditor: bool, reviewer: bool}
     */
    private static function roles(array $roles = []): array
    {
        return $roles + ['owner' => false, 'coAuthor' => false, 'editor' => false, 'copyEditor' => false, 'reviewer' => false];
    }

    /**
     * @param array<string, string> $settings
     */
    private function journal(array $settings = []): Episciences_Review
    {
        $journal = $this->createMock(Episciences_Review::class);
        $journal->method('getSettings')->willReturn($settings);

        return $journal;
    }

    /**
     * Record of the lookups made by the controller under test (see controller())
     */
    private static function log(): \stdClass
    {
        $log = new \stdClass();
        // Files delivered: [path, file]
        $log->delivered = [];
        // Paper ids whose versions are looked up
        $log->lookedUp = [];
        // Roles looked up: [docIds, uid, author uids]
        $log->roleLookups = [];
        // Versions loaded
        $log->loaded = [];
        // FileController::isAllowedToHandleDocumentFiles() calls: [path, docId, paperId]
        $log->handleChecks = [];

        return $log;
    }

    /**
     * Controller whose database lookups are stubbed and recorded in $log (see log())
     *
     * @param array<string, mixed> $stubs docIds (int[]|RuntimeException), roles (array|RuntimeException),
     *                                    journal, versions (Episciences_Paper|false|RuntimeException by docId),
     *                                    allowedToHandle (?bool: result of isAllowedToHandleDocumentFiles(), null
     *                                    for the real check)
     */
    private function controller(
        array $stubs,
        \stdClass $log,
        ?Zend_Controller_Request_HttpTestCase $request = null,
        ?Zend_Controller_Response_HttpTestCase $response = null
    ): \FileController
    {
        $stubs += [
            'docIds' => self::DOCIDS,
            'roles' => self::roles(),
            'journal' => $this->journal(),
            'versions' => [],
            'allowedToHandle' => null,
            'log' => $log,
        ];

        // The stubs reach the controller as invoke arguments, the way the front controller passes its own
        return new class (
            $request ?? new Zend_Controller_Request_HttpTestCase(),
            $response ?? new Zend_Controller_Response_HttpTestCase(),
            $stubs
        ) extends \FileController {
            protected function isAllowedToHandleDocumentFiles(string $path, int $docId, int $paperId): bool
            {
                $this->getInvokeArg('log')->handleChecks[] = [$path, $docId, $paperId];
                $allowed = $this->getInvokeArg('allowedToHandle');

                return $allowed ?? parent::isAllowedToHandleDocumentFiles($path, $docId, $paperId);
            }

            protected function findPaperDocIds(int $paperId): array
            {
                $this->getInvokeArg('log')->lookedUp[] = $paperId;
                $docIds = $this->getInvokeArg('docIds');
                if ($docIds instanceof RuntimeException) {
                    throw $docIds;
                }
                return $docIds;
            }

            protected function findUserRolesOnVersions(array $docIds, int $uid, array $authorUids): array
            {
                $this->getInvokeArg('log')->roleLookups[] = [$docIds, $uid, $authorUids];
                $roles = $this->getInvokeArg('roles');
                if ($roles instanceof RuntimeException) {
                    throw $roles;
                }
                return $roles;
            }

            protected function findPaper(int $docId): ?Episciences_Paper
            {
                $this->getInvokeArg('log')->loaded[] = $docId;
                $version = $this->getInvokeArg('versions')[$docId] ?? null;
                if ($version instanceof RuntimeException) {
                    throw $version;
                }
                return $version ?: null;
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
                $this->getInvokeArg('log')->delivered[] = [$path, $file];
            }
        };
    }

    /**
     * Version loaded for a docId (see FileController::findPaper())
     */
    private function versionOfDocId(int $paperId, int $docId): Episciences_Paper
    {
        $version = $this->createMock(Episciences_Paper::class);
        $version->method('getPaperid')->willReturn($paperId);
        $version->method('getDocid')->willReturn($docId);

        return $version;
    }

    /**
     * @param array<string, mixed> $stubs see controller()
     * @param array<string, mixed> $params
     * @return array{int, \stdClass} HTTP response code, lookups and files delivered (see log())
     */
    private function runTmpAction(array $stubs = [], array $params = []): array
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setParams($params + ['docId' => (string)self::PAPERID, 'filename' => 'answer', 'extension' => 'pdf']);
        $response = new Zend_Controller_Response_HttpTestCase();

        $log = self::log();
        $this->controller($stubs, $log, $request, $response)->tmpAction();

        return [$response->getHttpResponseCode(), $log];
    }

    public function testAuthorGetsTheFileFromTheTemporaryDirectoryOfThePaper(): void
    {
        $this->logInAs();

        [$code, $log] = $this->runTmpAction(['roles' => self::roles(['owner' => true])]);

        self::assertSame(200, $code);
        self::assertSame([[REVIEW_FILES_PATH . self::PAPERID . '/tmp', 'answer.pdf']], $log->delivered);
        self::assertSame([self::PAPERID], $log->lookedUp);
    }

    public function testCoAuthorGetsTheFile(): void
    {
        $this->logInAs();

        [$code] = $this->runTmpAction(['roles' => self::roles(['coAuthor' => true])]);

        self::assertSame(200, $code);
    }

    public function testRolesAreReadOnceOnEveryVersionWithoutLoadingThem(): void
    {
        $this->logInAs();

        [$code, $log] = $this->runTmpAction(['roles' => self::roles(['owner' => true])]);

        self::assertSame(200, $code);
        self::assertSame([[self::DOCIDS, self::UID, [self::UID]]], $log->roleLookups);
        self::assertSame([], $log->loaded);
    }

    public function testUserSignedInAsAnotherOneIsLookedUpAsAuthorToo(): void
    {
        // As Episciences_Paper::isOwnerOrCoAuthor(): the original identity counts as well
        $this->logInAs();
        \Zend_Session::$_unitTestEnabled = true;
        $session = new \Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->realIdentities = [new Episciences_User(['UID' => 777])];
        $session->impersonatedUid = self::UID;

        try {
            [, $log] = $this->runTmpAction();
        } finally {
            unset($session->realIdentities, $session->impersonatedUid);
        }

        self::assertSame([[self::DOCIDS, self::UID, [self::UID, 777]]], $log->roleLookups);
    }

    public function testConflictOfInterestIsNotLookedUpForTheAuthor(): void
    {
        // Only withdraws the access of the editorial staff
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        [$code, $log] = $this->runTmpAction(['roles' => self::roles(['owner' => true, 'editor' => true])]);

        self::assertSame(200, $code);
        self::assertSame(0, self::$conflictLookups);
        self::assertSame([], $log->loaded);
    }

    public function testConflictOfInterestIsLookedUpOnceOnTheLatestVersionForTheEditorialStaff(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);
        self::$hasConflict = true;

        [$code, $log] = $this->runTmpAction([
            'roles' => self::roles(['editor' => true]),
            'versions' => [17 => $this->versionOfDocId(self::PAPERID, 17)],
        ]);

        self::assertSame(404, $code);
        self::assertSame(1, self::$conflictLookups);
        self::assertSame([17], $log->loaded);
    }

    public function testConflictOfInterestIsLookedUpOnTheNextVersionWhenTheLatestIsNotFound(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        [$code, $log] = $this->runTmpAction([
            'roles' => self::roles(['editor' => true]),
            'versions' => [15 => $this->versionOfDocId(self::PAPERID, 15)],
        ]);

        self::assertSame(200, $code);
        self::assertSame([17, 15], $log->loaded);
    }

    public function testEditorialStaffIsRefusedWhenNoVersionCanBeLoaded(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        [$code] = $this->runTmpAction(['roles' => self::roles(['editor' => true])]);

        self::assertSame(404, $code);
        self::assertSame(0, self::$conflictLookups);
    }

    public function testAnyOtherMemberIsRefusedWithoutLoadingAVersion(): void
    {
        $this->logInAs();

        [$code, $log] = $this->runTmpAction();

        self::assertSame(404, $code);
        self::assertSame([], $log->delivered);
        self::assertCount(1, $log->roleLookups);
        self::assertSame([], $log->loaded);
        self::assertSame(0, self::$conflictLookups);
    }

    public function testReviewerOfAVersionIsAllowed(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_REVIEWER]);

        [$code, $log] = $this->runTmpAction(['roles' => self::roles(['reviewer' => true])]);

        self::assertSame(200, $code);
        self::assertCount(1, $log->delivered);
        self::assertSame(0, self::$conflictLookups);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function assignmentProvider(): array
    {
        return [
            'editor' => ['editor'],
            'copy editor' => ['copyEditor'],
        ];
    }

    /**
     * @dataProvider assignmentProvider
     */
    public function testAssignedEditorialStaffIsAllowed(string $role): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR, Episciences_Acl::ROLE_COPY_EDITOR]);

        [$code] = $this->runTmpAction([
            'roles' => self::roles([$role => true]),
            'versions' => [17 => $this->versionOfDocId(self::PAPERID, 17)],
        ]);

        self::assertSame(200, $code);
    }

    public function testAssignedEditorInConflictOfInterestIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);
        self::$hasConflict = true;

        [$code, $log] = $this->runTmpAction([
            'roles' => self::roles(['editor' => true]),
            'versions' => [17 => $this->versionOfDocId(self::PAPERID, 17)],
        ]);

        self::assertSame(404, $code);
        self::assertSame([], $log->delivered);
    }

    public function testReviewerInConflictOfInterestAsEditorKeepsTheReviewerAccess(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);
        self::$hasConflict = true;

        [$code] = $this->runTmpAction(['roles' => self::roles(['editor' => true, 'reviewer' => true])]);

        self::assertSame(200, $code);
        self::assertSame(0, self::$conflictLookups);
    }

    public function testSecretaryIsAllowedWithoutAssignment(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        [$code] = $this->runTmpAction(['versions' => [17 => $this->versionOfDocId(self::PAPERID, 17)]]);

        self::assertSame(200, $code);
        self::assertSame(1, self::$conflictLookups);
    }

    public function testSecretaryInConflictOfInterestIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        self::$hasConflict = true;

        [$code] = $this->runTmpAction(['versions' => [17 => $this->versionOfDocId(self::PAPERID, 17)]]);

        self::assertSame(404, $code);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function encapsulationProvider(): array
    {
        return [
            'editor' => [Episciences_Acl::ROLE_EDITOR, Episciences_Review::SETTING_ENCAPSULATE_EDITORS],
            'copy editor' => [Episciences_Acl::ROLE_COPY_EDITOR, Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS],
        ];
    }

    /**
     * @dataProvider encapsulationProvider
     */
    public function testNotEncapsulatedEditorialStaffIsAllowedWithoutAssignment(string $role, string $setting): void
    {
        $this->logInAs([$role]);
        $version = [17 => $this->versionOfDocId(self::PAPERID, 17)];

        [$encapsulated] = $this->runTmpAction(['journal' => $this->journal([$setting => '1']), 'versions' => $version]);
        [$notEncapsulated] = $this->runTmpAction(['journal' => $this->journal([$setting => '0']), 'versions' => $version]);

        self::assertSame(404, $encapsulated);
        self::assertSame(200, $notEncapsulated);
    }

    public function testPaperWithoutVersionInTheJournalIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        [$code, $log] = $this->runTmpAction(['docIds' => []]);

        self::assertSame(404, $code);
        self::assertSame([], $log->delivered);
        self::assertSame([], $log->roleLookups);
    }

    public function testFailureWhileLookingUpTheVersionsIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        [$code, $log] = $this->runTmpAction(['docIds' => new RuntimeException('database down')]);

        self::assertSame(404, $code);
        self::assertSame([], $log->delivered);
    }

    public function testFailureWhileLookingUpTheRolesIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        [$code, $log] = $this->runTmpAction(['roles' => new RuntimeException('database down')]);

        self::assertSame(404, $code);
        self::assertSame([], $log->delivered);
    }

    public function testFailureWhileLoadingTheVersionForTheConflictOfInterestIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        [$code] = $this->runTmpAction(['versions' => [17 => new RuntimeException('database down')]]);

        self::assertSame(404, $code);
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

        [$code, $log] = $this->runTmpAction(['roles' => self::roles(['owner' => true])], $params);

        self::assertSame(404, $code);
        self::assertSame([], $log->delivered);
        self::assertSame([], $log->lookedUp);
    }

    /**
     * Upload and removal of the files of the temporary versions: the paper id resolved once
     * (FileController::resolvePaperId()), then FileController::isAllowedToHandleDocumentFiles(), as uploadAction()
     * and deleteAction() do
     *
     * @param array<string, bool> $roles
     * @param Episciences_Paper|RuntimeException|false|null $version the version of the docId: by default one of
     *                                                               the paper, false when it is not found
     * @return array{bool, list<int>} access granted, paper ids looked up
     */
    private function runHandleCheck(
        array $roles,
        int $docId,
        int $paperId,
        Episciences_Paper|RuntimeException|false|null $version = null
    ): array
    {
        $log = self::log();
        $controller = $this->controller([
            'roles' => self::roles($roles),
            'versions' => [$docId => $version ?? $this->versionOfDocId(self::PAPERID, $docId)],
        ], $log);

        $paperId = (new \ReflectionMethod($controller, 'resolvePaperId'))->invoke($controller, $docId, $paperId);
        $allowed = $paperId !== null && (new \ReflectionMethod($controller, 'isAllowedToHandleDocumentFiles'))
            ->invoke($controller, 'tmp_attachments', $docId, $paperId);

        return [$allowed, $log->lookedUp];
    }

    public function testAuthorCanHandleTheTemporaryVersionFiles(): void
    {
        $this->logInAs();

        [$allowed, $lookedUp] = $this->runHandleCheck(['owner' => true], 15, self::PAPERID);

        self::assertTrue($allowed);
        self::assertSame([self::PAPERID], $lookedUp);
    }

    public function testReviewerCannotHandleTheTemporaryVersionFiles(): void
    {
        // Reads the author's answer (see testReviewerOfAVersionIsAllowed()), but must not alter it
        $this->logInAs([Episciences_Acl::ROLE_REVIEWER]);

        [$allowed] = $this->runHandleCheck(['reviewer' => true], 15, self::PAPERID);

        self::assertFalse($allowed);
    }

    public function testAssignedEditorCanHandleTheTemporaryVersionFiles(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        [$allowed] = $this->runHandleCheck(['editor' => true], 17, self::PAPERID);

        self::assertTrue($allowed);
    }

    public function testUnassignedEditorCannotHandleTheTemporaryVersionFiles(): void
    {
        // Allowed to manage the papers of the journal, but encapsulated and not assigned to this one
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        [$allowed] = $this->runHandleCheck([], 17, self::PAPERID);

        self::assertFalse($allowed);
    }

    public function testEditorInConflictOfInterestCannotHandleTheTemporaryVersionFiles(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);
        self::$hasConflict = true;

        [$allowed] = $this->runHandleCheck(['editor' => true], 17, self::PAPERID);

        self::assertFalse($allowed);
    }

    public function testPaperIdIsTakenFromTheVersionWhenOnlyTheDocIdIsGiven(): void
    {
        $this->logInAs();

        [$allowed, $lookedUp] = $this->runHandleCheck(['owner' => true], 15, 0);

        self::assertTrue($allowed);
        self::assertSame([self::PAPERID], $lookedUp);
    }

    public function testPaperIdOfAnotherPaperThanTheVersionIsRefused(): void
    {
        // Allowed on the paper of the version: the paper id given must not point the files elsewhere
        $this->logInAs();

        [$allowed, $lookedUp] = $this->runHandleCheck(['owner' => true], 15, 555);

        self::assertFalse($allowed);
        self::assertSame([], $lookedUp);
    }

    public function testVersionOfTheDocIdNotFoundIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        [$allowed, $lookedUp] = $this->runHandleCheck(['owner' => true], 15, self::PAPERID, false);

        self::assertFalse($allowed);
        self::assertSame([], $lookedUp);
    }

    public function testVersionWithoutPaperIdFallsBackToItsDocId(): void
    {
        // As the save paths of the temporary versions do (getPaperid() ?: docId)
        $this->logInAs();

        [$allowed, $lookedUp] = $this->runHandleCheck(['owner' => true], 15, 15, $this->versionOfDocId(0, 15));

        self::assertTrue($allowed);
        self::assertSame([15], $lookedUp);
    }

    public function testFailureWhileLoadingTheVersionOfTheDocIdIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        [$allowed, $lookedUp] = $this->runHandleCheck(['owner' => true], 15, 0, new RuntimeException('Database unavailable'));

        self::assertFalse($allowed);
        self::assertSame([], $lookedUp);
    }

    public function testTemporaryVersionFilesWithoutPaperAreRefused(): void
    {
        // Not the session-scoped attachments directory: the files would be written in a "0/tmp/" directory
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        [$allowed, $lookedUp] = $this->runHandleCheck(['owner' => true], 0, 0);

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

    /**
     * FileController::deleteAction() with the lookups of controller()
     *
     * @param array<string, string> $post
     * @param array<string, mixed> $stubs see controller()
     * @return array{int, \stdClass} HTTP response code, lookups (see log())
     */
    private function runDelete(array $post, array $stubs): array
    {
        \Zend_Session::$_unitTestEnabled = true;
        $session = new \Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->csrfToken = 'tmp-files-token';
        // The test request keeps what an earlier one posted
        $_POST = [];

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost($post + ['csrf_token' => 'tmp-files-token']);
        $response = new Zend_Controller_Response_HttpTestCase();
        $log = self::log();

        try {
            $this->controller($stubs, $log, $request, $response)->deleteAction();
        } finally {
            unset($session->csrfToken);
        }

        return [$response->getHttpResponseCode(), $log];
    }

    public function testVersionsAreLookedUpOnceOnRemoval(): void
    {
        // Access check, then protection of the submitted files (read from the database: none for these versions)
        $this->logInAs();

        [$code, $log] = $this->runDelete(
            ['path' => 'tmp_attachments', 'paperId' => (string)self::PAPERID, 'file' => 'tmp-files-access-test.pdf'],
            ['docIds' => [987654321], 'roles' => self::roles(['owner' => true])]
        );

        self::assertSame(200, $code);
        self::assertSame([self::PAPERID], $log->lookedUp);
        self::assertCount(1, $log->roleLookups);
        self::assertSame([], $log->loaded);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function versionStoragePathProvider(): array
    {
        return [
            'comments' => ['comment_attachments'],
            'copy editing' => ['ce_attachments'],
        ];
    }

    /**
     * @dataProvider versionStoragePathProvider
     */
    public function testFilesStoredByVersionDoNotResolveThePaperId(string $path): void
    {
        // Stored by docId: the paper id is not used, the version is not loaded for it
        $this->logInAs();

        [$code, $log] = $this->runDelete(
            ['path' => $path, 'docId' => '15', 'paperId' => '555', 'pcId' => '3', 'file' => 'tmp-files-access-test.pdf'],
            ['allowedToHandle' => true]
        );

        self::assertSame(200, $code);
        self::assertSame([], $log->loaded);
        self::assertSame([[$path, 15, 555]], $log->handleChecks);
    }

    public function testAttachmentsOfAnotherPaperThanTheVersionAreRefused(): void
    {
        // Session-scoped attachments, stored by paper id: the paper id given must be the one of the version
        $this->logInAs();

        [$code, $log] = $this->runDelete(
            ['path' => '', 'docId' => '15', 'paperId' => '555', 'file' => 'tmp-files-access-test.pdf'],
            ['allowedToHandle' => true, 'versions' => [15 => $this->versionOfDocId(self::PAPERID, 15)]]
        );

        self::assertSame(403, $code);
        self::assertSame([15], $log->loaded);
        self::assertSame([], $log->handleChecks);
    }
}
