<?php

declare(strict_types=1);

namespace Episciences\Mail;

use Episciences_Acl;

/**
 * Decides which users the logged user may find when choosing the recipients of a mail.
 *
 * - chief editors, administrators, secretaries: every user of the journal (text search only);
 * - editors: same, unless the journal encapsulates editors or manages conflicts of interest: then editorial
 *   staff and the authors of the papers they may manage;
 * - guest editors, copy editors: editorial staff and the participants of the paper of the mail.
 *
 * A paper given as context is never trusted: it must belong to the journal and be accessible to the caller.
 * Roles are the strict roles of the caller in the current journal (no cascade from administrator).
 */
final class RecipientAccessPolicy
{
    /** Roles allowed to search recipients */
    public const ALLOWED_ROLES = [
        Episciences_Acl::ROLE_ROOT,
        Episciences_Acl::ROLE_CHIEF_EDITOR,
        Episciences_Acl::ROLE_ADMIN,
        Episciences_Acl::ROLE_SECRETARY,
        Episciences_Acl::ROLE_EDITOR,
        Episciences_Acl::ROLE_GUEST_EDITOR,
        Episciences_Acl::ROLE_COPY_EDITOR,
    ];

    private const FULL_ACCESS_ROLES = [
        Episciences_Acl::ROLE_ROOT,
        Episciences_Acl::ROLE_CHIEF_EDITOR,
        Episciences_Acl::ROLE_ADMIN,
        Episciences_Acl::ROLE_SECRETARY,
    ];

    /**
     * @param list<string> $roles strict roles of the caller in the current journal
     */
    public function __construct(
        private readonly array $roles,
        private readonly bool $encapsulateEditors,
        private readonly bool $encapsulateCopyEditors,
        private readonly bool $coiEnabled,
        private readonly PaperAccessProvider $papers
    ) {
    }

    /**
     * @param ?int $docId document the mail is about, if any
     * @throws RecipientAccessDenied
     */
    public function scope(?int $docId = null): RecipientScope
    {
        if (!$this->hasAnyRole(self::ALLOWED_ROLES)) {
            throw new RecipientAccessDenied('Recipients search is not allowed for this user');
        }

        $contextPaperId = $docId === null ? null : $this->contextPaperId($docId);
        // Copy editors do not manage the peer review: they do not need to know who the reviewers are
        $withReviewers = !$this->hasOnlyCopyEditorAccess();

        if ($this->hasFullAccess() || ($this->isEditor() && !$this->isEditorRestricted())) {
            return new RecipientScope(RecipientScope::ALL_REVIEW_USERS, [], $contextPaperId, $withReviewers);
        }

        $paperIds = $this->isEditor() ? $this->restrictedEditorPaperIds() : [];

        if ($contextPaperId !== null && !in_array($contextPaperId, $paperIds, true)) {
            $paperIds[] = $contextPaperId;
        }

        return new RecipientScope(RecipientScope::STAFF_AND_PAPERS, $paperIds, $contextPaperId, $withReviewers);
    }

    /**
     * @throws RecipientAccessDenied
     */
    private function contextPaperId(int $docId): int
    {
        $paperId = $docId > 0 ? $this->papers->findPaperId($docId) : null;

        if ($paperId === null) {
            throw new RecipientAccessDenied('Unknown paper');
        }

        // Same rules as the paper management page: no access to one's own submission, nor in case of conflict
        if ($this->papers->isAuthor($docId) || $this->papers->hasConflict($docId)) {
            throw new RecipientAccessDenied('Paper not accessible');
        }

        if (!$this->mayAccessAnyPaper() && !$this->papers->isAssigned($docId)) {
            throw new RecipientAccessDenied('Paper not accessible');
        }

        return $paperId;
    }

    /**
     * Same rules, in the same order, as the paper management page (see
     * Episciences_Paper_AccessControlControllerTrait::buildRoleRedirection()): an encapsulated editor needs an
     * assignment, even if they are also a copy editor of a journal that does not encapsulate copy editors.
     */
    private function mayAccessAnyPaper(): bool
    {
        if ($this->hasFullAccess()) {
            return true;
        }

        if ($this->isEditor()) {
            return !$this->encapsulateEditors;
        }

        return $this->hasRole(Episciences_Acl::ROLE_COPY_EDITOR) && !$this->encapsulateCopyEditors;
    }

    /**
     * @return list<int>
     */
    private function restrictedEditorPaperIds(): array
    {
        if ($this->encapsulateEditors && $this->coiEnabled) {
            return array_values(array_intersect($this->papers->assignedPaperIds(), $this->papers->paperIdsWithoutConflict()));
        }

        return $this->encapsulateEditors ? $this->papers->assignedPaperIds() : $this->papers->paperIdsWithoutConflict();
    }

    private function isEditorRestricted(): bool
    {
        return $this->encapsulateEditors || $this->coiEnabled;
    }

    private function hasFullAccess(): bool
    {
        return $this->hasAnyRole(self::FULL_ACCESS_ROLES);
    }

    private function isEditor(): bool
    {
        return $this->hasRole(Episciences_Acl::ROLE_EDITOR);
    }

    private function hasOnlyCopyEditorAccess(): bool
    {
        return $this->hasRole(Episciences_Acl::ROLE_COPY_EDITOR)
            && !$this->hasFullAccess()
            && !$this->isEditor()
            && !$this->hasRole(Episciences_Acl::ROLE_GUEST_EDITOR);
    }

    private function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /**
     * @param list<string> $roles
     */
    private function hasAnyRole(array $roles): bool
    {
        return array_intersect($roles, $this->roles) !== [];
    }
}
