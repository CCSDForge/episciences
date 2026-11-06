<?php

declare(strict_types=1);

namespace Episciences\Http;

use GuzzleHttp\Exception\TransferException;

/**
 * The host of an outgoing request did not resolve: possibly a transient DNS failure,
 * unlike RefusedAddressException, which is final.
 */
final class UnresolvedHostException extends TransferException
{
}
