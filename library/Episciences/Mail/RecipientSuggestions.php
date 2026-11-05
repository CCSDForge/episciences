<?php

declare(strict_types=1);

namespace Episciences\Mail;

use Ccsd_Tools;
use Episciences_Acl;
use Episciences_Auth;
use Episciences_User;

/**
 * Common format of the users suggested as recipients of a mail (To, Cc, Bcc).
 *
 * Values are raw: they are rendered as text by the browser (textContent), never as HTML.
 */
final class RecipientSuggestions
{
    public const GROUP_PAPER = 'paper';
    public const GROUP_STAFF = 'staff';
    public const GROUP_OTHER = 'other';

    /**
     * @param array<string, mixed> $row USER columns, ROLES: comma-separated roles in the current journal
     * @param ?string $paperRole role of the user in the paper of the mail
     * @return array{uid: int, fullname: string, username: string, mail: string, roles: list<string>, group: string, paperRole: ?string}
     */
    public static function fromRow(array $row, ?string $paperRole = null): array
    {
        $roles = array_values(array_filter(
            explode(',', (string)($row['ROLES'] ?? '')),
            static fn(string $role): bool => $role !== '' && $role !== Episciences_Acl::ROLE_MEMBER
        ));

        $fullName = Ccsd_Tools::formatUser((string)($row['FIRSTNAME'] ?? ''), (string)($row['LASTNAME'] ?? ''));

        return [
            'uid' => (int)($row['UID'] ?? 0),
            'fullname' => $fullName !== '' ? $fullName : (string)($row['SCREEN_NAME'] ?? ''),
            'username' => (string)($row['USERNAME'] ?? ''),
            'mail' => (string)($row['EMAIL'] ?? ''),
            'roles' => $roles,
            'group' => self::group($roles, $paperRole),
            'paperRole' => $paperRole,
        ];
    }

    /**
     * A known user as a recipient: the sender ("Receive a copy of this message"), a co-author, a default recipient
     *
     * @return array{uid: int, fullname: string, screen_name: string, username: string, mail: string}
     */
    public static function fromUser(Episciences_User $user): array
    {
        return [
            'uid' => (int)$user->getUid(),
            'fullname' => (string)$user->getFullName(),
            'screen_name' => (string)$user->getScreenName(),
            'username' => (string)$user->getUsername(),
            'mail' => (string)$user->getEmail(),
        ];
    }

    /**
     * The logged user as a recipient (JSON for the mail forms scripts), "null" when not logged in
     */
    public static function senderJson(): string
    {
        $identity = Episciences_Auth::isLogged() ? Episciences_Auth::getInstance()->getIdentity() : null;

        return $identity instanceof Episciences_User ? self::toScriptJson(self::fromUser($identity)) : 'null';
    }

    /**
     * JSON safe inside an inline <script> block
     *
     * @param array<string, mixed> $recipient
     */
    public static function toScriptJson(array $recipient): string
    {
        return json_encode($recipient, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /**
     * @param list<string> $roles
     */
    private static function group(array $roles, ?string $paperRole): string
    {
        if ($paperRole !== null) {
            return self::GROUP_PAPER;
        }

        return array_intersect($roles, RecipientSearchService::BROWSEABLE_STAFF_ROLES) !== [] ? self::GROUP_STAFF : self::GROUP_OTHER;
    }
}
