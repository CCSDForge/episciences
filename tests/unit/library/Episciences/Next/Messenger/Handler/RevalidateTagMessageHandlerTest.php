<?php

namespace unit\library\Episciences\Next\Messenger\Handler;

use Episciences\Next\Messenger\Handler\RevalidateTagMessageHandler;
use Episciences\Next\Messenger\Message\RevalidateTagMessage;
use Episciences\Next\Messenger\TokenResolver;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Exception\RecoverableExceptionInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Unit tests for RevalidateTagMessageHandler's HTTP response taxonomy.
 *
 * EPISCIENCES_ENABLE_NEXT_FRONT is never defined anywhere else in this test
 * process (see Episciences_Next_RevalidationServiceTest, which relies on
 * that) — every test here that needs it truthy runs in its own process via
 * #[RunInSeparateProcess] so defining it doesn't leak into unrelated tests.
 */
class RevalidateTagMessageHandlerTest extends TestCase
{
    public function testDoesNothingWhenFeatureFlagIsNotEnabled(): void
    {
        // An empty MockHandler queue throws if the handler tries an HTTP
        // call at all, so reaching the end of this test IS the assertion.
        $handler = $this->buildHandler(new MockHandler([]));

        $handler(new RevalidateTagMessage('epijinfo', 'article-42'));

        $this->addToAssertionCount(1);
    }

    #[RunInSeparateProcess]
    public function testSuccessOn2xxReturnsWithoutThrowing(): void
    {
        define('EPISCIENCES_ENABLE_NEXT_FRONT', true);
        $handler = $this->buildHandler(new MockHandler([new Response(204)]));

        $handler(new RevalidateTagMessage('epijinfo', 'article-42'));

        $this->addToAssertionCount(1);
    }

    #[RunInSeparateProcess]
    public function testVisibleRedirectIsUnrecoverable(): void
    {
        define('EPISCIENCES_ENABLE_NEXT_FRONT', true);
        $handler = $this->buildHandler(new MockHandler([new Response(302)]));

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler(new RevalidateTagMessage('epijinfo', 'article-42'));
    }

    #[RunInSeparateProcess]
    #[DataProvider('transientStatusProvider')]
    public function testTransientStatusIsAPlainRuntimeExceptionNotARecoverableOne(int $status): void
    {
        define('EPISCIENCES_ENABLE_NEXT_FRONT', true);
        $handler = $this->buildHandler(new MockHandler([new Response($status)]));

        try {
            $handler(new RevalidateTagMessage('epijinfo', 'article-42'));
            self::fail('Expected a RuntimeException.');
        } catch (RuntimeException $e) {
            // Critical: NOT RecoverableExceptionInterface — Symfony's
            // SendFailedMessageForRetryListener::shouldRetry() would retry
            // it forever, bypassing MultiplierRetryStrategy's bounded count.
            self::assertNotInstanceOf(RecoverableExceptionInterface::class, $e);
        }
    }

    /** @return array<string, array{0: int}> */
    public static function transientStatusProvider(): array
    {
        return [
            'request timeout 408' => [408],
            'too early 425' => [425],
            'too many requests 429' => [429],
            'internal server error 500' => [500],
            'bad gateway 502' => [502],
        ];
    }

    #[RunInSeparateProcess]
    public function testPermanentClientErrorIsUnrecoverable(): void
    {
        define('EPISCIENCES_ENABLE_NEXT_FRONT', true);
        $handler = $this->buildHandler(new MockHandler([new Response(403, [], 'forbidden')]));

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler(new RevalidateTagMessage('epijinfo', 'article-42'));
    }

    #[RunInSeparateProcess]
    public function testNetworkErrorIsAPlainRuntimeExceptionWrappingTheGuzzleException(): void
    {
        define('EPISCIENCES_ENABLE_NEXT_FRONT', true);
        $request = new Request('POST', 'https://next.example.test/api/revalidate');
        $handler = $this->buildHandler(new MockHandler([
            new ConnectException('Could not resolve host', $request),
        ]));

        try {
            $handler(new RevalidateTagMessage('epijinfo', 'article-42'));
            self::fail('Expected a RuntimeException.');
        } catch (RuntimeException $e) {
            self::assertNotInstanceOf(RecoverableExceptionInterface::class, $e);
            self::assertInstanceOf(ConnectException::class, $e->getPrevious());
        }
    }

    private function buildHandler(MockHandler $mockHandler): RevalidateTagMessageHandler
    {
        $client = new Client(['handler' => HandlerStack::create($mockHandler)]);

        return new RevalidateTagMessageHandler(
            $client,
            new TokenResolver(null, 'test-secret'),
            'https://next.example.test'
        );
    }
}
