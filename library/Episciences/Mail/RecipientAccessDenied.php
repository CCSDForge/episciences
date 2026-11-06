<?php

declare(strict_types=1);

namespace Episciences\Mail;

/**
 * The caller may not search recipients, or not in the context of the requested paper.
 */
final class RecipientAccessDenied extends \RuntimeException
{
}
