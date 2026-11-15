<?php

declare(strict_types=1);

namespace Episciences\Http;

use GuzzleHttp\Exception\TransferException;

/**
 * The host name lookup of an outgoing request failed (DNS server failure, timeout): possibly transient,
 * unlike RefusedAddressException (unknown domain, host without IPv4 address...), which is final.
 */
final class UnresolvedHostException extends TransferException
{
}
