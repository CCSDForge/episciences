<?php

declare(strict_types=1);

namespace Episciences\Mail;

/**
 * Users a mail sender may find with the recipients search, as decided by RecipientAccessPolicy.
 */
final class RecipientScope
{
    /** Text search among every user of the journal */
    public const ALL_REVIEW_USERS = 'all_review_users';
    /** Editorial staff, and the authors of the papers the caller may manage */
    public const STAFF_AND_PAPERS = 'staff_and_papers';

    /**
     * @param list<int> $paperIds papers whose authors can be found (STAFF_AND_PAPERS only)
     * @param ?int $contextPaperId paper of the mail: its participants are listed first
     * @param bool $withContextReviewers whether the reviewers of the context paper are shown as such
     */
    public function __construct(
        public readonly string $type,
        public readonly array $paperIds = [],
        public readonly ?int $contextPaperId = null,
        public readonly bool $withContextReviewers = false
    ) {
        if (!in_array($type, [self::ALL_REVIEW_USERS, self::STAFF_AND_PAPERS], true)) {
            throw new \InvalidArgumentException('Unknown recipient scope: ' . $type);
        }
    }

    public function isAllReviewUsers(): bool
    {
        return $this->type === self::ALL_REVIEW_USERS;
    }
}
