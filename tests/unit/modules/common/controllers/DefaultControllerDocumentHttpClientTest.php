<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Episciences\Http\RefusedAddressException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;
use ReflectionMethod;

/**
 * DefaultController::createDocumentHttpClient(): the client the PDF proxy uses to fetch
 * a paper's main document from its repository. Requests go through a mocked transport;
 * address literals avoid any DNS lookup.
 */
final class DefaultControllerDocumentHttpClientTest extends TestCase
{
    private const string PUBLIC_IP = '93.184.216.34';

    /** @var list<array{request: RequestInterface, options: array<string, mixed>}> */
    private array $sent = [];

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/DefaultController.php';
    }

    public function testAppliesRealTimeoutsAndPinsTheAddress(): void
    {
        $this->makeClient([new Response(200, ['Content-Type' => 'application/pdf'], '%PDF')])
            ->request('GET', 'https://' . self::PUBLIC_IP . '/document.pdf');

        $options = $this->sent[0]['options'];
        self::assertSame(10, $options['connect_timeout']);
        self::assertSame(20, $options['timeout']);
        self::assertSame([self::PUBLIC_IP . ':443:' . self::PUBLIC_IP], $options['curl'][CURLOPT_RESOLVE]);
        self::assertSame(DOMAIN, $this->sent[0]['request']->getHeaderLine('User-Agent'));
        self::assertFalse($this->sent[0]['request']->hasHeader('timeout'), 'timeouts are transfer options, not headers');
    }

    public function testRefusesARedirectToAnInternalAddress(): void
    {
        $client = $this->makeClient([new Response(302, ['Location' => 'http://127.0.0.1/admin'])]);

        try {
            $client->request('GET', 'https://' . self::PUBLIC_IP . '/document.pdf');
            self::fail('The redirect should have been refused');
        } catch (RefusedAddressException) {
            self::assertCount(1, $this->sent);
        }
    }

    public function testRefusesANonHttpUrl(): void
    {
        $this->expectException(RefusedAddressException::class);

        $this->makeClient([])->request('GET', 'file:///etc/passwd');
    }

    /**
     * @param list<Response> $responses
     */
    private function makeClient(array $responses): ClientInterface
    {
        $mock = new MockHandler($responses);
        $recordingHandler = function (RequestInterface $request, array $options) use ($mock) {
            $this->sent[] = ['request' => $request, 'options' => $options];

            return $mock($request, $options);
        };

        $controller = (new ReflectionClass(\DefaultController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(\DefaultController::class, 'createDocumentHttpClient');

        return $method->invoke($controller, $recordingHandler);
    }
}
