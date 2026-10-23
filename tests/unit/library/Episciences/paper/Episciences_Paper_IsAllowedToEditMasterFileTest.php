<?php

declare(strict_types=1);

namespace unit\library\Episciences\paper;

use Episciences_Acl;
use Episciences_Auth;
use Episciences_Paper;
use Episciences_User;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Paper::isAllowedToEditMasterFile().
 *
 * The method must allow exactly three kinds of users to designate a paper's
 * main file: the journal secretary, the paper's owner (author), and an
 * editor assigned to the paper. Everyone else must be rejected.
 *
 * @covers Episciences_Paper::isAllowedToEditMasterFile
 */
final class Episciences_Paper_IsAllowedToEditMasterFileTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('SESSION_NAMESPACE')) {
            define('SESSION_NAMESPACE', 'Episciences_Auth');
        }
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
     * @param array<int, string> $roles
     */
    private function createMockUser(int $uid, array $roles = [], int $rvid = RVID): Episciences_User
    {
        $user = $this->createMock(Episciences_User::class);
        $user->method('getUid')->willReturn($uid);
        $user->method('getRoles')->willReturn($roles);
        $user->method('getAllRoles')->willReturn([$rvid => $roles]);
        return $user;
    }

    private function loginUser(Episciences_User $user): void
    {
        Episciences_Auth::getInstance()->getStorage()->write($user);
    }

    // -----------------------------------------------------------------------
    // Nobody logged in / unrelated member
    // -----------------------------------------------------------------------

    public function testGuestIsNotAllowed(): void
    {
        $paper = new Episciences_Paper();
        $paper->setUid(42);

        self::assertFalse($paper->isAllowedToEditMasterFile());
    }

    public function testUnrelatedMemberIsNotAllowed(): void
    {
        $this->loginUser($this->createMockUser(99, [Episciences_Acl::ROLE_MEMBER]));

        // isEditor() is mocked to skip its DB-backed getEditor() lookup —
        // only isAllowedToEditMasterFile()'s own logic is under test here.
        $paper = $this->createPartialMock(Episciences_Paper::class, ['isEditor']);
        $paper->method('isEditor')->willReturn(false);
        $paper->setUid(42);

        self::assertFalse($paper->isAllowedToEditMasterFile());
    }

    // -----------------------------------------------------------------------
    // Owner
    // -----------------------------------------------------------------------

    public function testOwnerIsAllowed(): void
    {
        // Auth uid is 0 when nobody is logged in (test env) — set the paper
        // owner to the same value so isOwner() matches without a real login.
        $paper = new Episciences_Paper();
        $paper->setUid(0);

        self::assertTrue($paper->isAllowedToEditMasterFile());
    }

    public function testNonOwnerAuthorIsNotAllowed(): void
    {
        $this->loginUser($this->createMockUser(7, [Episciences_Acl::ROLE_AUTHOR]));

        $paper = $this->createPartialMock(Episciences_Paper::class, ['isEditor']);
        $paper->method('isEditor')->willReturn(false);
        $paper->setUid(42); // a different author owns the paper

        self::assertFalse($paper->isAllowedToEditMasterFile());
    }

    // -----------------------------------------------------------------------
    // Secretary — allowed regardless of ownership
    // -----------------------------------------------------------------------

    public function testSecretaryIsAllowedEvenWhenNotOwner(): void
    {
        $this->loginUser($this->createMockUser(1, [Episciences_Acl::ROLE_SECRETARY]));

        $paper = new Episciences_Paper();
        $paper->setUid(42);

        self::assertTrue($paper->isAllowedToEditMasterFile());
    }

    // -----------------------------------------------------------------------
    // Editor assigned to the paper — allowed regardless of ownership
    // -----------------------------------------------------------------------

    public function testAssignedEditorIsAllowedEvenWhenNotOwner(): void
    {
        $this->loginUser($this->createMockUser(5, [Episciences_Acl::ROLE_EDITOR]));

        $paper = $this->createPartialMock(Episciences_Paper::class, ['isEditor']);
        $paper->method('isEditor')->willReturn(true);
        $paper->setUid(42);

        self::assertTrue($paper->isAllowedToEditMasterFile());
    }

    public function testEditorNotAssignedToThisPaperIsNotAllowed(): void
    {
        $this->loginUser($this->createMockUser(5, [Episciences_Acl::ROLE_EDITOR]));

        $paper = $this->createPartialMock(Episciences_Paper::class, ['isEditor']);
        $paper->method('isEditor')->willReturn(false); // logged in, but not this paper's editor
        $paper->setUid(42);

        self::assertFalse($paper->isAllowedToEditMasterFile());
    }
}