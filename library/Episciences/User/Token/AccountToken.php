<?php

declare(strict_types=1);

namespace Episciences\User\Token;

use DateTimeImmutable;

/**
 * A valid account link token, as found in the user_token table. Never holds the link token itself.
 */
final class AccountToken
{
    public function __construct(
        public readonly int               $uid,
        public readonly string            $email,
        public readonly TokenPurpose      $purpose,
        public readonly DateTimeImmutable $expiresAt
    )
    {
    }
}
