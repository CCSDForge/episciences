<?php

namespace Episciences\User;

use RuntimeException;

/**
 * Thrown when an operation would create or assign an email already used by
 * another account. Carries that account's UID so the caller can fall back to
 * attaching it instead of creating a duplicate.
 */
final class EmailAlreadyInUseException extends RuntimeException
{
    private int $uid;

    public function __construct(int $uid, string $email)
    {
        parent::__construct(sprintf('Email "%s" is already in use by account #%d', $email, $uid));
        $this->uid = $uid;
    }

    public function getUid(): int
    {
        return $this->uid;
    }
}
