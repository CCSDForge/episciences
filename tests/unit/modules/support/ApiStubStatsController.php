<?php

declare(strict_types=1);

namespace unit\modules\support;

use Closure;
use Episciences\Api\DelegatedTokenClient;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;
use StatsController;

/**
 * The statistics page, with the answers of the API given by the test (load StatsController first)
 */
final class ApiStubStatsController extends StatsController
{
    /** @var (Closure(RequestInterface, array<string, mixed>): Response)|null */
    public static ?Closure $handler = null;

    protected function getHttpClient(): Client
    {
        $handler = self::$handler;

        return new Client(['handler' => HandlerStack::create(
            static fn(RequestInterface $request, array $options) => Create::promiseFor($handler($request, $options))
        )]);
    }

    protected function getDelegatedTokenClient(?Logger $logger): DelegatedTokenClient
    {
        return new DelegatedTokenClient($this->getHttpClient(), new NullLogger(), 'https://api.test/', str_repeat('k', 32));
    }
}
