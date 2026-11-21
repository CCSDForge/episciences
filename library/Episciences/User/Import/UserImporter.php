<?php

declare(strict_types=1);

namespace Episciences\User\Import;

use Episciences_Acl;
use Episciences_User;
use Throwable;

/**
 * Adds people to a journal: the account using their email is reused (the most recently modified valid one, otherwise
 * the most recently modified one not activated yet), or created not activated. Roles are only added, never removed,
 * and an existing account is never changed (names, language).
 */
final class UserImporter
{
    /**
     * @param UserImportMailer|null $mailer null: no mail is sent
     * @param string $defaultLang language of the new accounts without a language in the file
     */
    public function __construct(
        private readonly UserAccountStore $store,
        private readonly ?UserImportMailer $mailer,
        private readonly int $rvId,
        private readonly string $defaultLang
    ) {
    }

    /**
     * @param list<UserImportRow> $rows rows validated by UserCsvReader
     * @param bool $dryRun only reads: reports what would be done
     * @return list<UserImportResult>
     */
    public function import(array $rows, bool $dryRun): array
    {
        $results = [];
        foreach ($rows as $row) {
            try {
                $results[] = $dryRun ? $this->plan($row) : $this->importRow($row);
            } catch (Throwable $e) {
                $results[] = new UserImportResult($row->line, $row->email, null, UserImportResult::ACTION_FAILED, [], MailOutcome::None, [$e->getMessage()]);
            }
        }

        return $results;
    }

    private function importRow(UserImportRow $row): UserImportResult
    {
        $account = $this->store->findByEmail($row->email);
        $lang = $row->lang ?? $this->defaultLang;

        if ($account === null) {
            $user = $this->store->createAccount($row, $lang, $this->rvId);
            $held = [];
            $action = UserImportResult::ACTION_CREATED;
        } else {
            $user = $this->store->loadUser($account['UID']);
            // read before completeLocalData(): saving the Episciences data also adds the member role
            $held = $this->store->journalRoles($account['UID'], $this->rvId);
            $this->store->completeLocalData($user, $lang, $this->rvId);
            $action = $account['VALID'] === 1 ? UserImportResult::ACTION_ATTACHED : UserImportResult::ACTION_INACTIVE;
        }

        $uid = (int)$user->getUid();
        $added = array_values(array_diff($row->roles, $held));
        $this->store->addRoles($uid, $added, $this->rvId);

        $needsLink = $action !== UserImportResult::ACTION_ATTACHED;
        $mail = $this->notify($user, $needsLink, $added, array_values(array_unique([...$held, ...$row->roles])));

        return new UserImportResult($row->line, $row->email, $uid, $action, $added, $mail, $this->warnings($row, $user, $added));
    }

    private function plan(UserImportRow $row): UserImportResult
    {
        $account = $this->store->findByEmail($row->email);

        if ($account === null) {
            $action = UserImportResult::ACTION_CREATED;
            $added = $row->roles;
            $warnings = $this->roleWarnings($added);
        } else {
            $action = $account['VALID'] === 1 ? UserImportResult::ACTION_ATTACHED : UserImportResult::ACTION_INACTIVE;
            $added = array_values(array_diff($row->roles, $this->store->journalRoles($account['UID'], $this->rvId)));
            $warnings = $this->warnings($row, $this->store->loadUser($account['UID']), $added);
        }

        $willMail = $this->mailer !== null && ($action !== UserImportResult::ACTION_ATTACHED || $added !== []);

        return new UserImportResult($row->line, $row->email, $account['UID'] ?? null, $action, $added, $willMail ? MailOutcome::WouldSend : MailOutcome::None, $warnings);
    }

    /**
     * @param list<string> $added roles added by the import
     * @param list<string> $roles roles held in the journal after the import
     */
    private function notify(Episciences_User $user, bool $needsLink, array $added, array $roles): MailOutcome
    {
        if ($this->mailer === null) {
            return MailOutcome::None;
        }

        if ($needsLink) {
            return $this->mailer->sendAccountSetup($user, $roles);
        }

        return $added === [] ? MailOutcome::None : $this->mailer->sendAddedToJournal($user, $added);
    }

    /**
     * @param list<string> $added
     * @return list<string>
     */
    private function warnings(UserImportRow $row, Episciences_User $user, array $added): array
    {
        $warnings = $this->roleWarnings($added);

        $isSameName = self::isSameName((string)$user->getFirstname(), $row->firstname)
            && self::isSameName((string)$user->getLastname(), $row->lastname);

        if (!$isSameName) {
            $warnings[] = sprintf(
                'name kept: "%s %s" (file: "%s %s")',
                $user->getFirstname(),
                $user->getLastname(),
                $row->firstname,
                $row->lastname
            );
        }

        return $warnings;
    }

    /**
     * @param list<string> $added
     * @return list<string>
     */
    private function roleWarnings(array $added): array
    {
        return in_array(Episciences_Acl::ROLE_ROOT, $added, true)
            ? [sprintf('role "%s" gives full access to the whole platform', Episciences_Acl::ROLE_ROOT)]
            : [];
    }

    private static function isSameName(string $stored, string $imported): bool
    {
        return mb_strtolower(trim($stored)) === mb_strtolower(trim($imported));
    }
}
