<?php

namespace Episciences\User\Validate;

use Episciences\User\EmailPolicy;
use Zend_Validate_Interface;

/**
 * Replaces Zend_Validate_Db_NoRecordExists on EMAIL form elements: unlike that
 * validator, this one excludes the account being edited, so re-submitting an
 * unchanged address is not rejected as "already taken".
 */
final class EmailAvailability implements Zend_Validate_Interface
{
    public const NOT_AVAILABLE = 'emailNotAvailable';

    public const DEFAULT_MESSAGE = 'A record matching email (%value%) was found. Use login retrieve tools';

    /** @var array<string, string> */
    private array $messages = [];

    /** @var (callable(string): int[])|null */
    private $uidsFinder;

    /**
     * @param int $excludeUid UID to exclude from the conflict search (0 when creating an account: any match conflicts).
     * @param (callable(string): int[])|null $uidsFinder Injectable for tests; defaults to EmailPolicy's own lookup.
     */
    public function __construct(
        private readonly int $excludeUid = 0,
        private readonly string $message = self::DEFAULT_MESSAGE,
        ?callable $uidsFinder = null
    ) {
        $this->uidsFinder = $uidsFinder;
    }

    public function isValid($value): bool
    {
        $this->messages = [];
        $email = (string)$value;

        if (EmailPolicy::findConflictingUid($email, $this->excludeUid, $this->uidsFinder) !== null) {
            $this->messages[self::NOT_AVAILABLE] = str_replace('%value%', $email, $this->message);
            return false;
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
