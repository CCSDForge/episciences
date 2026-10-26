<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Files\UploadedFileToken;
use Episciences\Security\AccessLog;
use Episciences_Acl;
use Episciences_Paper;
use Episciences_Review;
use Episciences_User;
use PHPUnit\Framework\TestCase;
use Zend_Auth;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Session;
use Zend_Session_Namespace;
use unit\library\Episciences\Security\SecurityLogCaptureTrait;

require_once dirname(__DIR__, 3) . '/library/Episciences/Security/SecurityLogCaptureTrait.php';

/**
 * FileController::uploadAction() and deleteAction(): a user only removes the files they uploaded themselves
 * (see UploadedFileToken), in the upload directories expected, and the files of the answers to a copy-editing
 * request are handled by the author and the editorial staff of the paper, not by its reviewers.
 * The actions are dispatched for real with a logged user; the paper version, the journal, the copy-editing
 * request and the conflict of interest answer, all read from the database, are stubbed.
 */
final class FileControllerUploadedFileTest extends TestCase
{
    use SecurityLogCaptureTrait;

    public const UID = 4242;
    public const SECRET = 'uploaded-file-test-secret';
    private const CSRF_TOKEN = 'uploaded-file-csrf-token';
    private const DOCID = 31;
    private const PAPERID = 30;
    private const PCID = 77;

    /** Conflict of interest answer of the logged user, read by the controller under test */
    public static bool $hasConflict = false;

    private string $attachmentsDir;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/FileController.php';
        self::$hasConflict = false;

        Zend_Session::$_unitTestEnabled = true;
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->csrfToken = self::CSRF_TOKEN;

        // Mail attachments directory of the session (path ""), see Episciences_Tools::getAttachmentsPath()
        $this->attachmentsDir = sys_get_temp_dir() . '/uploaded-file-test-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->attachmentsDir);
        $session->currentAttachmentsPath = $this->attachmentsDir;

        // The test request keeps what an earlier one posted
        $_POST = [];
    }

    protected function tearDown(): void
    {
        Zend_Auth::getInstance()->clearIdentity();
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->csrfToken, $session->currentAttachmentsPath);

        foreach (glob($this->attachmentsDir . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->attachmentsDir);
        $_POST = [];
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

    private static function sign(string $folder, string $file): string
    {
        return (new UploadedFileToken(self::UID, self::SECRET))->sign($folder, $file);
    }

    private static function copyEditingFolder(int $pcId = self::PCID): string
    {
        return REVIEW_FILES_PATH . self::DOCID . '/copy_editing_sources/' . $pcId . '/';
    }

    /**
     * @param array{author?: bool, assigned?: bool} $state
     */
    private function version(array $state = []): Episciences_Paper
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getDocid')->willReturn(self::DOCID);
        $paper->method('getPaperid')->willReturn(self::PAPERID);
        $paper->method('isOwnerOrCoAuthor')->willReturn($state['author'] ?? false);
        $paper->method('getEditor')->willReturn(($state['assigned'] ?? false) ? new \stdClass() : false);
        $paper->method('getCopyEditor')->willReturn(false);
        // A reviewer of the paper is not granted anything on the copy-editing files: never asked
        $paper->expects(self::never())->method('getReviewer');

        return $paper;
    }

    /**
     * @param array<string, mixed> $post
     * @param array{paper?: Episciences_Paper, isRequest?: bool, isSubmitted?: bool, secret?: string} $stubs
     */
    private function controller(array $post, array $stubs = []): \FileController
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost($post + ['csrf_token' => self::CSRF_TOKEN]);

        $journal = $this->createMock(Episciences_Review::class);
        $journal->method('getSettings')->willReturn([]);

        $stubs += ['paper' => $this->version(), 'isRequest' => true, 'isSubmitted' => false, 'secret' => self::SECRET];

        return new class ($request, new Zend_Controller_Response_HttpTestCase(), $stubs + ['journal' => $journal]) extends \FileController {
            protected function findPaper(int $docId): ?Episciences_Paper
            {
                return $this->getInvokeArg('paper');
            }

            protected function findJournal(): Episciences_Review
            {
                return $this->getInvokeArg('journal');
            }

            protected function isCopyEditingRequest(int $docId, int $pcId): bool
            {
                return $this->getInvokeArg('isRequest');
            }

            protected function isSubmittedCopyEditingFile(int $docId, int $pcId, string $file): bool
            {
                return $this->getInvokeArg('isSubmitted');
            }

            public static function isConflictDetected(Episciences_Paper $paper, Episciences_Review $journal = null): bool
            {
                return FileControllerUploadedFileTest::$hasConflict;
            }

            protected function uploadedFileToken(): UploadedFileToken
            {
                return new UploadedFileToken(FileControllerUploadedFileTest::UID, $this->getInvokeArg('secret'));
            }
        };
    }

    /**
     * @param array<string, mixed> $post
     * @param array{paper?: Episciences_Paper, isRequest?: bool, isSubmitted?: bool, secret?: string} $stubs
     */
    private function runDelete(array $post, array $stubs = []): int
    {
        $controller = $this->controller($post, $stubs);
        $controller->deleteAction();

        return $controller->getResponse()->getHttpResponseCode();
    }

    /**
     * @param array<string, mixed> $post
     * @param array{paper?: Episciences_Paper, isRequest?: bool, isSubmitted?: bool, secret?: string} $stubs
     * @return array{int, mixed} HTTP response code, decoded JSON answer
     */
    private function runUpload(array $post, array $stubs = []): array
    {
        $controller = $this->controller($post, $stubs);

        ob_start();
        try {
            $controller->uploadAction();
        } finally {
            $output = (string)ob_get_clean();
        }

        return [$controller->getResponse()->getHttpResponseCode(), json_decode($output, true)];
    }

    private function storeAttachment(string $file): void
    {
        file_put_contents($this->attachmentsDir . $file, 'content');
    }

    // -----------------------------------------------------------------------
    // Removal: only the files uploaded by the user
    // -----------------------------------------------------------------------

    public function testFileUploadedByTheUserIsRemoved(): void
    {
        $this->logInAs();
        $this->storeAttachment('letter.pdf');

        $code = $this->runDelete([
            'path' => '',
            'file' => 'letter.pdf',
            'token' => self::sign($this->attachmentsDir, 'letter.pdf'),
        ]);

        self::assertSame(200, $code);
        self::assertFileDoesNotExist($this->attachmentsDir . 'letter.pdf');
        self::assertSame([], $this->loggedEvents());
    }

    public function testFileWithoutTokenIsKept(): void
    {
        $this->logInAs();
        $this->storeAttachment('letter.pdf');

        $code = $this->runDelete(['path' => '', 'file' => 'letter.pdf']);

        self::assertSame(403, $code);
        self::assertFileExists($this->attachmentsDir . 'letter.pdf');
        self::assertSame([[AccessLog::TOKEN_INVALID, 'uploaded_file_token']], $this->loggedEvents());
    }

    public function testTokenOfAnotherFileDoesNotRemoveTheFile(): void
    {
        $this->logInAs();
        $this->storeAttachment('letter.pdf');
        $this->storeAttachment('other.pdf');

        $code = $this->runDelete([
            'path' => '',
            'file' => 'other.pdf',
            'token' => self::sign($this->attachmentsDir, 'letter.pdf'),
        ]);

        self::assertSame(403, $code);
        self::assertFileExists($this->attachmentsDir . 'other.pdf');
    }

    public function testWithoutSecretNoFileIsRemoved(): void
    {
        $this->logInAs();
        $this->storeAttachment('letter.pdf');

        $code = $this->runDelete(
            ['path' => '', 'file' => 'letter.pdf', 'token' => self::sign($this->attachmentsDir, 'letter.pdf')],
            ['secret' => '']
        );

        self::assertSame(403, $code);
        self::assertFileExists($this->attachmentsDir . 'letter.pdf');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unexpectedPaths(): iterable
    {
        yield 'comments directory, used by no form' => ['comment_attachments'];
        yield 'unknown directory' => ['documents'];
    }

    /**
     * @dataProvider unexpectedPaths
     */
    public function testRemovalInAnUnexpectedDirectoryIsRejected(string $path): void
    {
        $this->logInAs();

        $code = $this->runDelete([
            'path' => $path,
            'docId' => (string)self::DOCID,
            'file' => 'letter.pdf',
            'token' => self::sign(REVIEW_FILES_PATH . self::DOCID . '/comments/', 'letter.pdf'),
        ]);

        self::assertSame(400, $code);
    }

    // -----------------------------------------------------------------------
    // Copy-editing answers: author and editorial staff only, files of the request kept
    // -----------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private static function copyEditingRemoval(string $file = 'sources.zip'): array
    {
        return [
            'path' => 'ce_attachments',
            'docId' => (string)self::DOCID,
            'pcId' => (string)self::PCID,
            'file' => $file,
            'token' => self::sign(self::copyEditingFolder(), $file),
        ];
    }

    public function testAuthorRemovesTheFileTheyUploadedForTheAnswer(): void
    {
        $this->logInAs();

        $code = $this->runDelete(self::copyEditingRemoval(), ['paper' => $this->version(['author' => true])]);

        self::assertSame(200, $code);
        self::assertSame([], $this->loggedEvents());
    }

    public function testReviewerCannotRemoveACopyEditingFile(): void
    {
        $this->logInAs();

        $code = $this->runDelete(self::copyEditingRemoval());

        self::assertSame(403, $code);
        self::assertSame([[AccessLog::HTTP_FORBIDDEN, 'copy_editing_file_write']], $this->loggedEvents());
    }

    public function testAssignedEditorRemovesACopyEditingFile(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        $code = $this->runDelete(self::copyEditingRemoval(), ['paper' => $this->version(['assigned' => true])]);

        self::assertSame(200, $code);
    }

    public function testEditorInConflictOfInterestCannotRemoveACopyEditingFile(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);
        self::$hasConflict = true;

        $code = $this->runDelete(self::copyEditingRemoval(), ['paper' => $this->version(['assigned' => true])]);

        self::assertSame(403, $code);
    }

    public function testDirectoryOfACommentOtherThanACopyEditingRequestIsRefused(): void
    {
        $this->logInAs();

        $code = $this->runDelete(
            self::copyEditingRemoval(),
            ['paper' => $this->version(['author' => true]), 'isRequest' => false]
        );

        self::assertSame(403, $code);
        self::assertSame([[AccessLog::HTTP_FORBIDDEN, 'unexpected_copy_editing_request']], $this->loggedEvents());
    }

    public function testFileOfTheRequestOrOfASavedAnswerIsKept(): void
    {
        $this->logInAs();

        $code = $this->runDelete(
            self::copyEditingRemoval(),
            ['paper' => $this->version(['author' => true]), 'isSubmitted' => true]
        );

        self::assertSame(403, $code);
    }

    public function testCopyEditingFileIsConsideredSubmittedWhenTheLookupFails(): void
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
            public function check(int $docId, int $pcId, string $file): bool
            {
                return $this->isSubmittedCopyEditingFile($docId, $pcId, $file);
            }
        };

        try {
            self::assertTrue($controller->check(self::DOCID, self::PCID, 'sources.zip'));
        } finally {
            \Zend_Db_Table_Abstract::setDefaultAdapter($previousAdapter);
        }
    }

    // -----------------------------------------------------------------------
    // Upload: same directories and rules, refused without a secret to sign the file
    // -----------------------------------------------------------------------

    /**
     * @dataProvider unexpectedPaths
     */
    public function testUploadInAnUnexpectedDirectoryIsRejected(string $path): void
    {
        $this->logInAs();

        [$code, $answer] = $this->runUpload(['path' => $path, 'docId' => (string)self::DOCID]);

        self::assertSame(400, $code);
        self::assertSame(['status' => 'error'], $answer);
    }

    public function testReviewerCannotUploadACopyEditingFile(): void
    {
        $this->logInAs();

        [$code, $answer] = $this->runUpload([
            'path' => 'ce_attachments',
            'docId' => (string)self::DOCID,
            'pcId' => (string)self::PCID,
        ]);

        self::assertSame(403, $code);
        self::assertSame(['status' => 'error'], $answer);
        self::assertSame([[AccessLog::HTTP_FORBIDDEN, 'copy_editing_file_write']], $this->loggedEvents());
    }

    public function testCopyEditingUploadWithoutRequestIsRefused(): void
    {
        $this->logInAs();

        [$code] = $this->runUpload(
            ['path' => 'ce_attachments', 'docId' => (string)self::DOCID],
            ['paper' => $this->version(['author' => true]), 'isRequest' => false]
        );

        self::assertSame(403, $code);
    }

    public function testUploadIsRefusedWithoutSecretToSignTheFile(): void
    {
        $this->logInAs();

        [$code, $answer] = $this->runUpload(['path' => ''], ['secret' => '']);

        self::assertSame(200, $code);
        self::assertSame('error', $answer['status']);
        self::assertNotEmpty($answer['messages']);
    }
}
