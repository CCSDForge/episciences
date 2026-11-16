<?php

declare(strict_types=1);

namespace Episciences\User\Import;

use Episciences\User\EmailPolicy;
use Episciences_Acl;
use InvalidArgumentException;

/**
 * One person of a user import CSV file: identity, roles to add in the journal and language of a new account.
 */
final readonly class UserImportRow
{
    /**
     * @param list<string> $roles roles to add in the journal, never empty
     * @param string|null $lang language of a new account, null: the default language of the import
     */
    public function __construct(
        public int $line,
        public string $firstname,
        public string $lastname,
        public string $email,
        public array $roles,
        public ?string $lang
    ) {
    }

    /**
     * @param array<string, string> $record CSV values indexed by column name
     * @param list<string> $allowedRoles
     * @param list<string> $allowedLangs
     * @throws InvalidArgumentException when a value is missing or invalid
     */
    public static function fromRecord(int $line, array $record, array $allowedRoles, array $allowedLangs): self
    {
        $lastname = trim($record['lastname'] ?? '');
        if ($lastname === '') {
            throw new InvalidArgumentException('missing lastname');
        }

        $email = EmailPolicy::normalize(trim($record['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(sprintf('invalid email "%s"', $email));
        }

        return new self(
            $line,
            trim($record['firstname'] ?? ''),
            $lastname,
            $email,
            self::parseRoles($record['role'] ?? '', $allowedRoles),
            self::parseLang($record['lang'] ?? '', $allowedLangs)
        );
    }

    /**
     * @param list<string> $allowedRoles
     * @return list<string>
     */
    private static function parseRoles(string $value, array $allowedRoles): array
    {
        $roles = array_values(array_unique(array_filter(array_map('trim', explode(',', $value)), static fn(string $role): bool => $role !== '')));
        if ($roles === []) {
            return [Episciences_Acl::ROLE_MEMBER];
        }

        $unknown = array_diff($roles, $allowedRoles);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('unknown role "%s"', implode('", "', $unknown)));
        }

        return $roles;
    }

    /**
     * @param list<string> $allowedLangs
     */
    private static function parseLang(string $value, array $allowedLangs): ?string
    {
        $lang = strtolower(trim($value));
        if ($lang === '') {
            return null;
        }

        if (!in_array($lang, $allowedLangs, true)) {
            throw new InvalidArgumentException(sprintf('unavailable language "%s"', $lang));
        }

        return $lang;
    }
}
