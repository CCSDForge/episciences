<?php

declare(strict_types=1);

namespace unit\library\Episciences\Rating;

use Episciences_Acl;
use Episciences_Auth;
use Episciences_Paper;
use Episciences_Rating_Report;
use Episciences_User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Behavioural tests for Episciences_Rating_Report::isAttachmentAccessibleToLoggedUser(),
 * the permission check used by FileController::reportAction() before serving a
 * rating report attachment (RT#301096).
 *
 * DB-free: the paper is a partial mock and the logged-in user is written to the
 * Zend_Auth storage.
 *
 * @covers Episciences_Rating_Report::isAttachmentAccessibleToLoggedUser
 */
final class Episciences_Rating_ReportAttachmentAccessTest extends TestCase
{
    private const REVIEWER_UID = 7;
    private const ASSIGNED_UID = 42;
    private const OTHER_UID = 99;

    protected function setUp(): void
    {
        if (!defined('RVID')) {
            define('RVID', 1);
        }

        Episciences_Auth::getInstance()->clearIdentity();
    }

    protected function tearDown(): void
    {
        Episciences_Auth::getInstance()->clearIdentity();
    }

    /**
     * @param list<string> $roles
     */
    private function loginUser(int $uid, array $roles = [Episciences_Acl::ROLE_MEMBER]): void
    {
        $user = $this->createMock(Episciences_User::class);
        $user->method('getUid')->willReturn($uid);
        $user->method('getRoles')->willReturn($roles);
        $user->method('getAllRoles')->willReturn([RVID => $roles]);
        Episciences_Auth::getInstance()->getStorage()->write($user);
    }

    private function createReport(): Episciences_Rating_Report
    {
        $report = new Episciences_Rating_Report();
        $report->setUid(self::REVIEWER_UID);

        return $report;
    }

    /**
     * Paper version the report belongs to: no assignment on it, the assignments
     * of the latest version are provided by $latestVersion.
     */
    private function createPaper(
        ?Episciences_Paper $latestVersion = null,
        bool $reportsVisibleToAuthor = false
    ): Episciences_Paper&MockObject {
        $paper = $this->createPartialMock(
            Episciences_Paper::class,
            ['getEditor', 'getCopyEditor', 'findLatestVersion', 'isReportsVisibleToAuthor']
        );
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);
        $paper->method('findLatestVersion')->willReturn($latestVersion);
        $paper->method('isReportsVisibleToAuthor')->willReturn($reportsVisibleToAuthor);

        return $paper;
    }

    /**
     * Latest version with an editor (or guest editor) or a copy editor assigned.
     */
    private function createLatestVersion(?int $editorUid = null, ?int $copyEditorUid = null): Episciences_Paper
    {
        $latest = $this->createPartialMock(Episciences_Paper::class, ['getEditor', 'getCopyEditor']);
        $latest->method('getEditor')->willReturnCallback(
            fn($uid) => (int)$uid === $editorUid ? $this->createMock(Episciences_User::class) : false
        );
        $latest->method('getCopyEditor')->willReturnCallback(
            static fn($uid) => (int)$uid === $copyEditorUid
        );

        return $latest;
    }

    public function testDeniedWhenNotLogged(): void
    {
        self::assertFalse($this->createReport()->isAttachmentAccessibleToLoggedUser($this->createPaper()));
    }

    public function testDeniedWhenPaperNotFound(): void
    {
        $this->loginUser(self::REVIEWER_UID);

        self::assertFalse($this->createReport()->isAttachmentAccessibleToLoggedUser(null));
    }

    public function testDeniedToUnrelatedUser(): void
    {
        $this->loginUser(self::OTHER_UID);

        self::assertFalse($this->createReport()->isAttachmentAccessibleToLoggedUser($this->createPaper()));
    }

    public function testDeniedToUnassignedGuestEditor(): void
    {
        $this->loginUser(self::OTHER_UID, [Episciences_Acl::ROLE_GUEST_EDITOR]);
        $paper = $this->createPaper($this->createLatestVersion(editorUid: self::ASSIGNED_UID));

        self::assertFalse($this->createReport()->isAttachmentAccessibleToLoggedUser($paper));
    }

    public function testAllowedToEditorialStaff(): void
    {
        $this->loginUser(self::OTHER_UID, [Episciences_Acl::ROLE_SECRETARY]);

        self::assertTrue($this->createReport()->isAttachmentAccessibleToLoggedUser($this->createPaper()));
    }

    public function testAllowedToReviewerWhoAuthoredTheReport(): void
    {
        $this->loginUser(self::REVIEWER_UID, [Episciences_Acl::ROLE_REVIEWER]);

        self::assertTrue($this->createReport()->isAttachmentAccessibleToLoggedUser($this->createPaper()));
    }

    public function testAllowedToAuthorOnceReportsAreVisible(): void
    {
        $this->loginUser(self::OTHER_UID);
        $paper = $this->createPaper(reportsVisibleToAuthor: true);

        self::assertTrue($this->createReport()->isAttachmentAccessibleToLoggedUser($paper));
    }

    /**
     * RT#301096: a guest editor assigned to the latest version only must be able
     * to open the report attachments of a previous version.
     */
    public function testAllowedToGuestEditorAssignedToLatestVersion(): void
    {
        $this->loginUser(self::ASSIGNED_UID, [Episciences_Acl::ROLE_GUEST_EDITOR]);
        $paper = $this->createPaper($this->createLatestVersion(editorUid: self::ASSIGNED_UID));

        self::assertTrue($this->createReport()->isAttachmentAccessibleToLoggedUser($paper));
    }

    public function testAllowedToCopyEditorAssignedToLatestVersion(): void
    {
        $this->loginUser(self::ASSIGNED_UID, [Episciences_Acl::ROLE_COPY_EDITOR]);
        $paper = $this->createPaper($this->createLatestVersion(copyEditorUid: self::ASSIGNED_UID));

        self::assertTrue($this->createReport()->isAttachmentAccessibleToLoggedUser($paper));
    }
}
