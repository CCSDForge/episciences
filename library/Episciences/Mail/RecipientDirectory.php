<?php

declare(strict_types=1);

namespace Episciences\Mail;

/**
 * Lookup of the accounts of a journal by email address or UID.
 */
interface RecipientDirectory
{
    /**
     * @param list<string> $emails
     * @return array<string, int> lower-cased email => UID
     */
    public function resolveEmails(int $rvid, array $emails): array;

    /**
     * @param list<int> $uids
     * @return array<int, string> UID => lower-cased email
     */
    public function reviewUserEmails(int $rvid, array $uids): array;

    /**
     * @return array<int, string> UID => role in the paper (author, coauthor, editor, copyeditor, reviewer)
     */
    public function paperRoles(int $rvid, int $paperId, bool $withReviewers): array;

    /**
     * Reviewers of a paper invited without an account (temporary users), while their invitation or assignment
     * is current: they have no UID in the journal, only an address
     * @return list<string> lower-cased emails
     */
    public function temporaryReviewerEmails(int $rvid, int $paperId): array;
}
