<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Acl;
use Episciences_Paper;
use Episciences_Review;
use Episciences_User;
use PHPUnit\Framework\TestCase;
use Zend_Auth;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * PaperController::isAllowedToWriteTemporaryVersion(): recording a temporary version (form and save) follows the
 * rule of the files it is uploaded with (see DocumentFilePolicy::isTemporaryVersionFileWritable()). The logged user
 * is a real identity with roles; the journal and the conflict of interest answer, read from the database, are
 * stubbed.
 */
final class PaperControllerTemporaryVersionAccessTest extends TestCase
{
    private const UID = 4242;

    /** Conflict of interest answer of the logged user, read by the controller under test */
    public static bool $hasConflict = false;
    /** Number of journal lookups by the controller under test */
    public static int $journalLookups = 0;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/PaperController.php';
        self::$hasConflict = false;
        self::$journalLookups = 0;
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
     * @param array{author?: bool, assigned?: bool} $state
     */
    private function paper(array $state = []): Episciences_Paper
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('isOwnerOrCoAuthor')->willReturn($state['author'] ?? false);
        $paper->method('isAssignedEditorOrCopyEditor')->willReturn($state['assigned'] ?? false);

        return $paper;
    }

    /**
     * @param array<string, mixed>|null $settings settings of the journal, null when it is not found
     */
    private function isAllowed(Episciences_Paper $paper, ?array $settings = []): bool
    {
        $journal = null;

        if ($settings !== null) {
            $journal = $this->createMock(Episciences_Review::class);
            $journal->method('getSettings')->willReturn($settings);
        }

        // The stub reaches the controller as an invoke argument, the way the front controller passes its own
        $controller = new class (
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase(),
            ['journal' => $journal]
        ) extends \PaperController {
            public function check(Episciences_Paper $paper): bool
            {
                return $this->isAllowedToWriteTemporaryVersion($paper);
            }

            protected function findJournal(): ?Episciences_Review
            {
                PaperControllerTemporaryVersionAccessTest::$journalLookups++;
                return $this->getInvokeArg('journal');
            }

            public static function isConflictDetected(Episciences_Paper $paper, Episciences_Review $journal = null): bool
            {
                return PaperControllerTemporaryVersionAccessTest::$hasConflict;
            }
        };

        return $controller->check($paper);
    }

    public function testAuthorIsAllowedWithoutLookingUpTheJournal(): void
    {
        $this->logInAs();

        self::assertTrue($this->isAllowed($this->paper(['author' => true])));
        self::assertSame(0, self::$journalLookups);
    }

    public function testSecretaryIsAllowed(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        self::assertTrue($this->isAllowed($this->paper()));
    }

    public function testSecretaryInConflictOfInterestIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        self::$hasConflict = true;

        self::assertFalse($this->isAllowed($this->paper()));
    }

    public function testAssignedEditorIsAllowed(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        self::assertTrue($this->isAllowed($this->paper(['assigned' => true])));
    }

    public function testUnassignedEditorIsRefusedWhenEditorsAreEncapsulated(): void
    {
        // Allowed to manage the papers of the journal (isPaperContributorOrManager()), but not assigned to this one
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        self::assertFalse($this->isAllowed($this->paper(), [Episciences_Review::SETTING_ENCAPSULATE_EDITORS => '1']));
    }

    public function testUnassignedEditorIsAllowedWhenEditorsAreNotEncapsulated(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        self::assertTrue($this->isAllowed($this->paper(), [Episciences_Review::SETTING_ENCAPSULATE_EDITORS => '0']));
    }

    public function testUnassignedGuestEditorIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_GUEST_EDITOR]);

        self::assertFalse($this->isAllowed($this->paper()));
    }

    public function testAssignedEditorInConflictOfInterestIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);
        self::$hasConflict = true;

        self::assertFalse($this->isAllowed($this->paper(['assigned' => true])));
    }

    public function testJournalNotFoundIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        self::assertFalse($this->isAllowed($this->paper(), null));
    }
}
