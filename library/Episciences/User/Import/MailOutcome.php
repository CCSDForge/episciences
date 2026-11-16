<?php

declare(strict_types=1);

namespace Episciences\User\Import;

/**
 * What happened to the mail of an imported person
 */
enum MailOutcome: string
{
    case None = 'none';
    case Sent = 'sent';
    case AlreadySent = 'already sent';
    case Failed = 'failed';
    case WouldSend = 'would send';
}
