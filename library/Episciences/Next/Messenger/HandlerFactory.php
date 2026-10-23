<?php

declare(strict_types=1);

namespace Episciences\Next\Messenger;

use Episciences\Next\Messenger\Handler\RevalidateTagMessageHandler;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

/**
 * Builds the RevalidateTagMessageHandler with its real Guzzle client and
 * TokenResolver — used by both the NextRevalidationProfile worker and
 * next:revalidate-cache, so the wiring exists in exactly one place.
 */
final class HandlerFactory
{
    public static function createRevalidateTagHandler(?LoggerInterface $logger = null): RevalidateTagMessageHandler
    {
        return new RevalidateTagMessageHandler(
            new Client(),
            TokenResolver::fromConstants($logger),
            defined('NEXT_BASE_URL') ? (string)NEXT_BASE_URL : '',
            $logger,
        );
    }
}
