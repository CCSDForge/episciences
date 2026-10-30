<?php

declare(strict_types=1);

namespace Episciences\User;

/**
 * What happened to a request for a new account activation link
 */
enum ActivationLinkOutcome
{
    /** a new link was e-mailed */
    case Sent;

    /** a link was sent a moment ago: nothing is sent, the invitee checks their mailbox */
    case AlreadySent;

    /** no link could be sent (no account to activate anymore, database or e-mail failure) */
    case Failed;
}
