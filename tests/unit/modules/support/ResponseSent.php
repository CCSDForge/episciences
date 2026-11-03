<?php

declare(strict_types=1);

namespace unit\modules\support;

use RuntimeException;

/**
 * Thrown where a controller would end the request with exit(): after a redirection, or once a
 * JSON answer is sent. It stops the action at the same point as in production.
 */
final class ResponseSent extends RuntimeException
{
}
