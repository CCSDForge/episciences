<?php

declare(strict_types=1);

namespace Episciences\User\Token;

/**
 * What an account link token grants. A token is only accepted by the action matching its purpose.
 *
 * Lifetimes are configurable in config/pwd.json (USER_TOKEN section, in seconds);
 * a missing or invalid value falls back to the default.
 */
enum TokenPurpose: string
{
    /** Activation of a self-registered account */
    case ACCOUNT_ACTIVATION = 'ACCOUNT_ACTIVATION';

    /** Lost password */
    case PASSWORD_RESET = 'PASSWORD_RESET';

    /** First password of an account created on someone's behalf (reviewer account created by an editor) */
    case ACCOUNT_SETUP = 'ACCOUNT_SETUP';

    public function ttlSeconds(): int
    {
        $constantName = $this->ttlConstantName();

        return $this->resolveTtlSeconds(defined($constantName) ? constant($constantName) : null);
    }

    /**
     * Lifetime for a configured value: the default when the value is missing, or invalid (with a warning)
     */
    public function resolveTtlSeconds(mixed $configured): int
    {
        if ($configured === null || $configured === '') {
            return $this->defaultTtlSeconds();
        }

        $ttl = filter_var($configured, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($ttl === false) {
            trigger_error(sprintf('Invalid %s value: positive number of seconds expected, default used', $this->ttlConstantName()), E_USER_WARNING);
            return $this->defaultTtlSeconds();
        }

        return $ttl;
    }

    public function defaultTtlSeconds(): int
    {
        return match ($this) {
            self::PASSWORD_RESET => 3600,
            self::ACCOUNT_ACTIVATION => 7 * 86400,
            self::ACCOUNT_SETUP => 14 * 86400,
        };
    }

    public function ttlConstantName(): string
    {
        return 'USER_TOKEN_' . $this->value . '_TTL';
    }
}
