<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Acl;
use Episciences_CommentsManager;
use Episciences_Paper;
use Episciences_Review;
use Episciences_User;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use ArrayObject;
use Zend_Auth;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * FileController::isAllowedToReadDocumentFile(): the roles of the logged user on the paper, gathered for
 * DocumentFilePolicy (see DocumentFilePolicyTest for the rules themselves). The logged user is a real
 * identity with roles; the paper, the journal, the comment types and the conflict of interest answer,
 * all read from the database, are stubbed.
 */
final class FileControllerDocumentFileAccessTest extends TestCase
{
    private const UID = 4242;
    private const DOCID = 12;

    /** @var ArrayObject<int, string> data read by the controller, in order */
    private ArrayObject $lookups;

    /** Conflict of interest answer of the logged user, read by the controller under test */
    public static bool $hasConflict = false;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/FileController.php';
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
     * @param array{published?: bool, author?: bool, reviewer?: bool, assigned?: bool} $state
     */
    private function paper(array $state = []): Episciences_Paper
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('isPublished')->willReturn($state['published'] ?? false);
        $paper->method('isOwnerOrCoAuthor')->willReturn($state['author'] ?? false);
        $paper->method('getReviewers')->willReturn(($state['reviewer'] ?? false) ? [self::UID => new \stdClass()] : []);
        $paper->method('isAssignedEditorOrCopyEditor')->willReturn($state['assigned'] ?? false);

        return $paper;
    }

    /**
     * @param array<string, mixed> $journalSettings
     * @param int[] $commentTypes
     */
    private function controller(
        Episciences_Paper|false|RuntimeException $paper,
        array $journalSettings = [],
        array $commentTypes = [],
        bool $hasConflict = false
    ): \FileController
    {
        $journal = $this->createMock(Episciences_Review::class);
        $journal->method('getSettings')->willReturn($journalSettings);

        $this->lookups = new ArrayObject();
        self::$hasConflict = $hasConflict;

        // The stubs reach the controller as invoke arguments, the way the front controller passes its own
        $invokeArgs = ['paper' => $paper, 'journal' => $journal, 'commentTypes' => $commentTypes, 'lookups' => $this->lookups];

        return new class (new Zend_Controller_Request_HttpTestCase(), new Zend_Controller_Response_HttpTestCase(), $invokeArgs) extends \FileController {
            private function record(string $lookup): void
            {
                $this->getInvokeArg('lookups')->append($lookup);
            }

            protected function findPaper(int $docId): ?Episciences_Paper
            {
                $this->record('paper');
                $paper = $this->getInvokeArg('paper');
                if ($paper instanceof RuntimeException) {
                    throw $paper;
                }
                return $paper ?: null;
            }

            protected function findJournal(): Episciences_Review
            {
                $this->record('journal');
                return $this->getInvokeArg('journal');
            }

            protected function findCommentTypes(int $docId, string $file): array
            {
                $this->record('comment types');
                return $this->getInvokeArg('commentTypes');
            }

            public static function isConflictDetected(Episciences_Paper $paper, Episciences_Review $journal = null): bool
            {
                return FileControllerDocumentFileAccessTest::$hasConflict;
            }
        };
    }

    /**
     * @return list<string>
     */
    private function lookupsList(): array
    {
        return array_values($this->lookups->getArrayCopy());
    }

    private function isAllowed(\FileController $controller, string $folder, string $file = 'file.pdf'): bool
    {
        $method = new ReflectionMethod($controller, 'isAllowedToReadDocumentFile');
        $method->setAccessible(true);

        return $method->invoke($controller, self::DOCID, $folder, $file);
    }

    public function testUnknownPaperIsRefused(): void
    {
        $this->logInAs();
        $controller = $this->controller(false);

        self::assertFalse($this->isAllowed($controller, 'comments'));
        self::assertSame(['paper'], $this->lookupsList());
    }

    public function testFailureWhileLoadingThePaperIsRefused(): void
    {
        $this->logInAs();

        self::assertFalse($this->isAllowed($this->controller(new RuntimeException('database down')), 'comments'));
    }

    public function testPublishedDataDescriptorIsServedWithoutLookingUpTheRoles(): void
    {
        $controller = $this->controller($this->paper(['published' => true]));

        self::assertTrue($this->isAllowed($controller, 'dd'));
        self::assertSame(['paper'], $this->lookupsList(), 'neither the journal nor the comments must be read');
    }

    public function testAuthorReadsTheFileOfTheirCoverLetter(): void
    {
        $this->logInAs();
        $controller = $this->controller($this->paper(['author' => true]), [], [Episciences_CommentsManager::TYPE_AUTHOR_COMMENT]);

        self::assertTrue($this->isAllowed($controller, 'comments'));
        self::assertContains('comment types', $this->lookupsList());
    }

    public function testAuthorDoesNotReadTheFileOfAnInternalEditorComment(): void
    {
        $this->logInAs();
        $controller = $this->controller($this->paper(['author' => true]), [], [Episciences_CommentsManager::TYPE_EDITOR_COMMENT]);

        self::assertFalse($this->isAllowed($controller, 'comments'));
    }

    public function testReviewerReadsTheFileOfAClarificationRequest(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_REVIEWER]);
        $controller = $this->controller($this->paper(['reviewer' => true]), [], [Episciences_CommentsManager::TYPE_INFO_REQUEST]);

        self::assertTrue($this->isAllowed($controller, 'comments'));
    }

    public function testReviewerDoesNotReadCopyEditingFiles(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_REVIEWER]);
        $controller = $this->controller($this->paper(['reviewer' => true]));

        self::assertFalse($this->isAllowed($controller, 'copy_editing_sources'));
        self::assertNotContains('comment types', $this->lookupsList());
    }

    public function testCommentTypesAreNotLookedUpForAnotherMember(): void
    {
        $this->logInAs();
        $controller = $this->controller($this->paper(), [], [Episciences_CommentsManager::TYPE_INFO_REQUEST]);

        self::assertFalse($this->isAllowed($controller, 'comments'));
        self::assertNotContains('comment types', $this->lookupsList());
    }

    public function testAssignedEditorReadsEveryFile(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);
        $settings = [Episciences_Review::SETTING_ENCAPSULATE_EDITORS => '1'];

        self::assertTrue($this->isAllowed($this->controller($this->paper(['assigned' => true]), $settings), 'copy_editing_sources'));
    }

    public function testUnassignedEditorOfAJournalNotRestrictingEditorsReadsTheFiles(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);
        $settings = [Episciences_Review::SETTING_ENCAPSULATE_EDITORS => '0'];

        self::assertTrue($this->isAllowed($this->controller($this->paper(), $settings), 'copy_editing_sources'));
    }

    public function testUnassignedEditorIsRefusedWhenTheJournalNeverSavedTheSetting(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        self::assertFalse($this->isAllowed($this->controller($this->paper()), 'copy_editing_sources'));
    }

    public function testEditorInConflictOfInterestIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);
        $controller = $this->controller($this->paper(['assigned' => true]), [], [], true);

        self::assertFalse($this->isAllowed($controller, 'copy_editing_sources'));
    }

    public function testEditorInConflictKeepsWhatTheReviewingPageShowsThem(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR, Episciences_Acl::ROLE_REVIEWER]);
        $controller = $this->controller(
            $this->paper(['reviewer' => true]),
            [Episciences_Review::SETTING_ENCAPSULATE_EDITORS => '0'],
            [Episciences_CommentsManager::TYPE_INFO_ANSWER],
            true
        );

        self::assertTrue($this->isAllowed($controller, 'comments'));
    }
}
