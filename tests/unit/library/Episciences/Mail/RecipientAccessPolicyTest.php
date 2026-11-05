<?php

declare(strict_types=1);

namespace unit\library\Episciences\Mail;

use Episciences\Mail\PaperAccessProvider;
use Episciences\Mail\RecipientAccessDenied;
use Episciences\Mail\RecipientAccessPolicy;
use Episciences\Mail\RecipientScope;
use Episciences_Acl;
use PHPUnit\Framework\TestCase;

final class RecipientAccessPolicyTest extends TestCase
{
    private const DOCID = 100;
    private const PAPERID = 90;

    /**
     * @param list<string> $roles
     * @param array{paperId?: ?int, assigned?: bool, author?: bool, conflict?: bool, assignedPaperIds?: list<int>, withoutConflict?: list<int>} $facts
     */
    private static function policy(
        array $roles,
        array $facts = [],
        bool $encapsulateEditors = false,
        bool $encapsulateCopyEditors = false,
        bool $coi = false
    ): RecipientAccessPolicy {
        $papers = new class ($facts) implements PaperAccessProvider {
            /** @param array<string, mixed> $facts */
            public function __construct(private readonly array $facts)
            {
            }

            public function findPaperId(int $docId): ?int
            {
                return array_key_exists('paperId', $this->facts) ? $this->facts['paperId'] : 90;
            }

            public function isAssigned(int $docId): bool
            {
                return $this->facts['assigned'] ?? false;
            }

            public function isAuthor(int $docId): bool
            {
                return $this->facts['author'] ?? false;
            }

            public function hasConflict(int $docId): bool
            {
                return $this->facts['conflict'] ?? false;
            }

            public function assignedPaperIds(): array
            {
                return $this->facts['assignedPaperIds'] ?? [];
            }

            public function paperIdsWithoutConflict(): array
            {
                return $this->facts['withoutConflict'] ?? [];
            }
        };

        return new RecipientAccessPolicy($roles, $encapsulateEditors, $encapsulateCopyEditors, $coi, $papers);
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function fullAccessRoles(): array
    {
        return [
            'chief editor' => [[Episciences_Acl::ROLE_CHIEF_EDITOR]],
            'administrator' => [[Episciences_Acl::ROLE_ADMIN]],
            'secretary' => [[Episciences_Acl::ROLE_SECRETARY]],
            'root' => [[Episciences_Acl::ROLE_ROOT]],
        ];
    }

    /**
     * @dataProvider fullAccessRoles
     * @param list<string> $roles
     */
    public function testEditorialManagersSearchEveryUserOfTheJournal(array $roles): void
    {
        $scope = self::policy($roles, [], true, true, true)->scope();

        self::assertSame(RecipientScope::ALL_REVIEW_USERS, $scope->type);
        self::assertNull($scope->contextPaperId);
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function nonEditorialRoles(): array
    {
        return [
            'member' => [[Episciences_Acl::ROLE_MEMBER]],
            'author' => [[Episciences_Acl::ROLE_AUTHOR]],
            'reviewer' => [[Episciences_Acl::ROLE_REVIEWER]],
            'webmaster' => [[Episciences_Acl::ROLE_WEBMASTER]],
            'no role' => [[]],
        ];
    }

    /**
     * @dataProvider nonEditorialRoles
     * @param list<string> $roles
     */
    public function testOtherRolesAreRefused(array $roles): void
    {
        $this->expectException(RecipientAccessDenied::class);

        self::policy($roles)->scope();
    }

    public function testGuestEditorWithoutPaperOnlyFindsTheEditorialStaff(): void
    {
        $scope = self::policy([Episciences_Acl::ROLE_GUEST_EDITOR])->scope();

        self::assertSame(RecipientScope::STAFF_AND_PAPERS, $scope->type);
        self::assertSame([], $scope->paperIds);
    }

    public function testGuestEditorFindsTheParticipantsOfAnAssignedPaper(): void
    {
        $scope = self::policy([Episciences_Acl::ROLE_GUEST_EDITOR], ['assigned' => true])->scope(self::DOCID);

        self::assertSame(RecipientScope::STAFF_AND_PAPERS, $scope->type);
        self::assertSame([self::PAPERID], $scope->paperIds);
        self::assertSame(self::PAPERID, $scope->contextPaperId);
        self::assertTrue($scope->withContextReviewers);
    }

    public function testGuestEditorIsRefusedAPaperNotAssignedToThem(): void
    {
        $this->expectException(RecipientAccessDenied::class);

        self::policy([Episciences_Acl::ROLE_GUEST_EDITOR], ['assigned' => false])->scope(self::DOCID);
    }

    public function testCopyEditorDoesNotSeeTheReviewersOfThePaper(): void
    {
        $scope = self::policy([Episciences_Acl::ROLE_COPY_EDITOR], ['assigned' => true], false, true)->scope(self::DOCID);

        self::assertSame(RecipientScope::STAFF_AND_PAPERS, $scope->type);
        self::assertFalse($scope->withContextReviewers);
    }

    public function testCopyEditorsNotEncapsulatedMayUseAnyPaperOfTheJournal(): void
    {
        $scope = self::policy([Episciences_Acl::ROLE_COPY_EDITOR], ['assigned' => false])->scope(self::DOCID);

        self::assertSame(self::PAPERID, $scope->contextPaperId);
    }

    public function testEncapsulatedCopyEditorIsRefusedAPaperNotAssignedToThem(): void
    {
        $this->expectException(RecipientAccessDenied::class);

        self::policy([Episciences_Acl::ROLE_COPY_EDITOR], ['assigned' => false], false, true)->scope(self::DOCID);
    }

    public function testPaperOfAnotherJournalIsRefusedEvenToTheSecretary(): void
    {
        $this->expectException(RecipientAccessDenied::class);

        self::policy([Episciences_Acl::ROLE_SECRETARY], ['paperId' => null])->scope(self::DOCID);
    }

    public function testPaperInConflictIsRefusedEvenToTheChiefEditor(): void
    {
        $this->expectException(RecipientAccessDenied::class);

        self::policy([Episciences_Acl::ROLE_CHIEF_EDITOR], ['conflict' => true])->scope(self::DOCID);
    }

    /**
     * The participants of a paper include its reviewers: never listed to one of its authors
     */
    public function testOwnPaperIsRefusedEvenToTheSecretary(): void
    {
        $this->expectException(RecipientAccessDenied::class);

        self::policy([Episciences_Acl::ROLE_SECRETARY], ['author' => true])->scope(self::DOCID);
    }

    public function testEncapsulatedEditorWhoIsAlsoCopyEditorStillNeedsAnAssignment(): void
    {
        $this->expectException(RecipientAccessDenied::class);

        self::policy(
            [Episciences_Acl::ROLE_EDITOR, Episciences_Acl::ROLE_COPY_EDITOR],
            ['assigned' => false],
            true,
            false
        )->scope(self::DOCID);
    }

    public function testEditorOfAnOpenJournalSearchesEveryUser(): void
    {
        $scope = self::policy([Episciences_Acl::ROLE_EDITOR])->scope(self::DOCID);

        self::assertSame(RecipientScope::ALL_REVIEW_USERS, $scope->type);
        self::assertSame(self::PAPERID, $scope->contextPaperId);
    }

    public function testEncapsulatedEditorOnlyFindsTheAuthorsOfTheirAssignedPapers(): void
    {
        $scope = self::policy([Episciences_Acl::ROLE_EDITOR], ['assignedPaperIds' => [1, 2]], true)->scope();

        self::assertSame(RecipientScope::STAFF_AND_PAPERS, $scope->type);
        self::assertSame([1, 2], $scope->paperIds);
    }

    public function testEncapsulatedEditorIsRefusedAPaperNotAssignedToThem(): void
    {
        $this->expectException(RecipientAccessDenied::class);

        self::policy([Episciences_Acl::ROLE_EDITOR], ['assigned' => false], true)->scope(self::DOCID);
    }

    public function testEditorWithConflictsManagementOnlyFindsThePapersWithoutConflict(): void
    {
        $scope = self::policy([Episciences_Acl::ROLE_EDITOR], ['withoutConflict' => [3, 4]], false, false, true)->scope(self::DOCID);

        self::assertSame(RecipientScope::STAFF_AND_PAPERS, $scope->type);
        self::assertSame([3, 4, self::PAPERID], $scope->paperIds);
    }

    public function testEncapsulatedEditorWithConflictsManagementKeepsAssignedPapersWithoutConflict(): void
    {
        $scope = self::policy(
            [Episciences_Acl::ROLE_EDITOR],
            ['assignedPaperIds' => [1, 2, 3], 'withoutConflict' => [2, 3, 4]],
            true,
            false,
            true
        )->scope();

        self::assertSame([2, 3], $scope->paperIds);
    }

    public function testStrictRolesAreUsedAndNoCascadeIsAssumed(): void
    {
        // An administrator is not an editor here: the policy only trusts the roles it is given
        $scope = self::policy([Episciences_Acl::ROLE_GUEST_EDITOR, Episciences_Acl::ROLE_MEMBER])->scope();

        self::assertSame(RecipientScope::STAFF_AND_PAPERS, $scope->type);
    }

    public function testNonPositiveDocIdIsRefused(): void
    {
        $this->expectException(RecipientAccessDenied::class);

        self::policy([Episciences_Acl::ROLE_SECRETARY])->scope(0);
    }
}
