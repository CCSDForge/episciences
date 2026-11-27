<?php

declare(strict_types=1);

namespace Episciences\Mail;

use Episciences_User;

/**
 * Users suggested by the recipients autocomplete of the mail forms (To, Cc, Bcc).
 */
final class RecipientSuggestions
{
    /**
     * @param iterable<Episciences_User> $users
     * @return array<int, array{uid: int, fullname: string, username: string, mail: string, label: string, htmlLabel: string}> keyed by UID
     */
    public static function fromUsers(iterable $users): array
    {
        $suggestions = [];

        foreach ($users as $user) {
            $uid = (int)$user->getUid();
            $fullName = (string)$user->getFullName();
            $username = mb_strtolower((string)$user->getUsername());
            $email = (string)$user->getEmail();

            $suggestions[$uid] = [
                'uid' => $uid,
                'fullname' => $fullName,
                'username' => (string)$user->getUsername(),
                'mail' => $email,
                'label' => $fullName . ' (' . $username . ') <' . $email . '>',
                // Inline elements only: the label is rendered inside a link (see sass/components/_autocomplete.scss)
                'htmlLabel' => '<span class="ep-ac-name">' . self::escape($fullName)
                    . ' <span class="darkgrey">(' . self::escape($username) . ')</span></span>'
                    . '<span class="ep-ac-email">' . self::escape($email) . '</span>',
            ];
        }

        return $suggestions;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
