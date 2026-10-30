<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

/**
 * Account already using the address a reviewer without an account was invited at
 */
enum InvitedAccountState: string
{
    /** no account: the reviewer creates one when accepting */
    case None = 'none';

    /** an active account: the reviewer signs in with it */
    case Active = 'active';

    /** created in Episciences, not activated: a new activation link can be sent */
    case Inactive = 'inactive';

    /** created in another application sharing the accounts, not activated */
    case InactiveElsewhere = 'inactive-elsewhere';

    /**
     * The account is activated first: nothing can be accepted from the invitation page yet
     */
    public function needsActivation(): bool
    {
        return $this === self::Inactive || $this === self::InactiveElsewhere;
    }
}
