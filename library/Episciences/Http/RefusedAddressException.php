<?php

declare(strict_types=1);

namespace Episciences\Http;

use GuzzleHttp\Exception\TransferException;

/**
 * The URL of an outgoing request was refused by PublicAddressGuard.
 * A Guzzle exception, so the callers' usual transfer error handling applies.
 */
final class RefusedAddressException extends TransferException
{
}
