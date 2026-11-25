<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use Episciences_User_Invitation;
use LogicException;

/**
 * Secret carried by the reviewer invitation link: holding the link is what grants access to the invitation.
 *
 * Each invitation row stores its own random value (USER_INVITATION.TOKEN). The link token is an HMAC of the
 * invitation id and that value, keyed with a global secret (REVIEWER_INVITATION.HMAC_SECRET in config/pwd.json),
 * so forging a link requires both the database and the configuration. Invitations created before this
 * mechanism have no row value and no link token (legacy invitations).
 *
 * An empty global secret is a configuration error: generating or checking a token then throws (fail closed).
 * New invitations are then saved without a row value, so they are sent as legacy invitations
 * (see Episciences_User_Invitation::save()).
 */
final class InvitationToken
{
    public const URL_PARAM = 'token';
    public const SECRET_CONSTANT_NAME = 'REVIEWER_INVITATION_HMAC_SECRET';

    private const ROW_SECRET_BYTES = 32;
    private const ALGORITHM = 'sha256';

    private string $secret;

    /**
     * @param string|null $secret the global secret; defaults to the configured one
     */
    public function __construct(?string $secret = null)
    {
        $this->secret = $secret ?? self::configuredSecret();
    }

    public function isConfigured(): bool
    {
        return $this->secret !== '';
    }

    /**
     * Random value stored in USER_INVITATION.TOKEN for a new pending invitation (64 hexadecimal characters)
     */
    public static function generateRowSecret(): string
    {
        return bin2hex(random_bytes(self::ROW_SECRET_BYTES));
    }

    /**
     * @throws LogicException when the global secret is not configured
     */
    public function compute(int $invitationId, string $rowSecret): string
    {
        if ($this->secret === '') {
            throw new LogicException(sprintf('The reviewer invitation link secret (%s) is not configured', self::SECRET_CONSTANT_NAME));
        }

        return hash_hmac(self::ALGORITHM, $invitationId . '|' . $rowSecret, $this->secret);
    }

    /**
     * Link token of an invitation, or null for a legacy invitation (no row value)
     * @throws LogicException when the global secret is not configured
     */
    public function forInvitation(Episciences_User_Invitation $invitation): ?string
    {
        $rowSecret = $invitation->getToken();

        if ($rowSecret === null || $rowSecret === '') {
            return null;
        }

        return $this->compute((int)$invitation->getId(), $rowSecret);
    }

    /**
     * @param mixed $presented the token taken from the request (any type: request values are untrusted)
     * @throws LogicException when the global secret is not configured
     */
    public function isValid(int $invitationId, string $rowSecret, mixed $presented): bool
    {
        $expected = $this->compute($invitationId, $rowSecret);

        if (!is_string($presented) || $presented === '') {
            return false;
        }

        return hash_equals($expected, $presented);
    }

    private static function configuredSecret(): string
    {
        if (!defined(self::SECRET_CONSTANT_NAME)) {
            return '';
        }

        return (string)constant(self::SECRET_CONSTANT_NAME);
    }
}
