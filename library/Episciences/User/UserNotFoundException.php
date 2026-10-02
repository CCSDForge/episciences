<?php

namespace Episciences\User;

use RuntimeException;

class UserNotFoundException extends RuntimeException
{
    public function __construct(int $uid, string $message = null)
    {
        parent::__construct(
                $message ?? "The User with ID $uid was not found"
        );
    }
}