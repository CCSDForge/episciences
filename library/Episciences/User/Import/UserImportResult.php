<?php

declare(strict_types=1);

namespace Episciences\User\Import;

/**
 * Outcome of the import of one person
 */
final readonly class UserImportResult
{
    public const string ACTION_ATTACHED = 'attached';
    public const string ACTION_CREATED = 'created';
    public const string ACTION_INACTIVE = 'not activated';
    public const string ACTION_FAILED = 'failed';

    /**
     * @param list<string> $addedRoles roles added in the journal
     * @param list<string> $warnings
     */
    public function __construct(
        public int $line,
        public string $email,
        public ?int $uid,
        public string $action,
        public array $addedRoles,
        public MailOutcome $mail,
        public array $warnings = []
    ) {
    }
}
