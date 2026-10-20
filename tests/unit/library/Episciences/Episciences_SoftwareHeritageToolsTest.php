<?php

namespace unit\library\Episciences;

use Episciences\Api\AbstractApiClient;
use Episciences_SoftwareHeritageTools;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Unit tests for Episciences_SoftwareHeritageTools.
 */
class Episciences_SoftwareHeritageToolsTest extends TestCase
{
    protected function setUp(): void
    {
        Episciences_SoftwareHeritageTools::setClient(null);
    }

    protected function tearDown(): void
    {
        Episciences_SoftwareHeritageTools::setClient(null);
    }

    /**
     * HAL and SWH are called synchronously during a submission: an unresponsive
     * host must not block the request until max_execution_time.
     */
    public function testDefaultHttpClientHasBoundedTimeouts(): void
    {
        $getClient = new ReflectionMethod(Episciences_SoftwareHeritageTools::class, 'getClient');
        $getClient->setAccessible(true);
        $apiClient = $getClient->invoke(null);

        $httpClient = new ReflectionProperty(AbstractApiClient::class, 'client');
        $httpClient->setAccessible(true);
        /** @var Client $guzzle */
        $guzzle = $httpClient->getValue($apiClient);

        self::assertGreaterThan(0, $guzzle->getConfig('timeout'));
        self::assertGreaterThan(0, $guzzle->getConfig('connect_timeout'));
    }
}
