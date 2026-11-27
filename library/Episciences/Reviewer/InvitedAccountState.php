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

    /**
     * not activated, whatever the application it was created in (the accounts are shared): it is activated first,
     * with a new activation link sent to the invited address if needed
     */
    case Inactive = 'inactive';
}
