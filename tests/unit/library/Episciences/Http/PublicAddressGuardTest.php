<?php

declare(strict_types=1);

namespace unit\library\Episciences\Http;

use Episciences\Http\PublicAddressGuard;
use Episciences\Http\RefusedAddressException;
use Episciences\Http\UnresolvedHostException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\Http\PublicAddressGuard
 * @covers \Episciences\Http\UnresolvedHostException
 */
final class PublicAddressGuardTest extends TestCase
{
    private const string PUBLIC_IP = '93.184.216.34';

    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function testPinsAHostToItsPublicAddress(): void
    {
        $guard = new PublicAddressGuard(static fn(string $host): array => [self::PUBLIC_IP]);

        self::assertSame('hal.science:443:' . self::PUBLIC_IP, $guard->pin(new Uri('https://hal.science/hal-01/document')));
        self::assertSame('arxiv.org:80:' . self::PUBLIC_IP, $guard->pin(new Uri('http://arxiv.org/pdf/1')));
        self::assertSame('example.org:8443:' . self::PUBLIC_IP, $guard->pin(new Uri('https://example.org:8443/file')));
    }

    public function testAcceptsAPublicAddressLiteralWithoutResolving(): void
    {
        $guard = new PublicAddressGuard(static fn(string $host): array => self::fail('No DNS lookup expected'));

        self::assertSame(self::PUBLIC_IP . ':443:' . self::PUBLIC_IP, $guard->pin(new Uri('https://' . self::PUBLIC_IP . '/file')));
    }

    #[DataProvider('refusedUrlProvider')]
    public function testRefusesUrls(string $url, string $resolvedAddress): void
    {
        $guard = new PublicAddressGuard(static fn(string $host): array => [$resolvedAddress]);

        $this->expectException(RefusedAddressException::class);
        $guard->pin(new Uri($url));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function refusedUrlProvider(): array
    {
        return [
            'file scheme' => ['file:///etc/passwd', self::PUBLIC_IP],
            'ftp scheme' => ['ftp://example.org/file', self::PUBLIC_IP],
            'host resolving to loopback' => ['https://example.org/file', '127.0.0.1'],
            'host resolving to a private network' => ['https://example.org/file', '10.1.2.3'],
            'host resolving to link-local' => ['https://example.org/file', '169.254.169.254'],
            'host resolving to carrier-grade NAT' => ['https://example.org/file', '100.64.0.1'],
            'loopback literal' => ['http://127.0.0.1/file', self::PUBLIC_IP],
            'link-local literal' => ['http://169.254.169.254/latest/meta-data/', self::PUBLIC_IP],
            'IPv6 literal' => ['http://[::1]:8080/file', self::PUBLIC_IP],
        ];
    }

    public function testRefusesAHostWhenOneOfItsAddressesIsNotPublic(): void
    {
        $guard = new PublicAddressGuard(static fn(string $host): array => [self::PUBLIC_IP, '192.168.0.1']);

        $this->expectException(RefusedAddressException::class);
        $guard->pin(new Uri('https://example.org/file'));
    }

    public function testRefusesAHostWithoutIpv4Address(): void
    {
        $guard = new PublicAddressGuard(static fn(string $host): array => []);

        $this->expectException(RefusedAddressException::class);
        $guard->pin(new Uri('https://unknown.invalid/file'));
    }

    public function testReportsAFailedLookupAsUnresolved(): void
    {
        $guard = new PublicAddressGuard(static fn(string $host): ?array => null);

        $this->expectException(UnresolvedHostException::class);
        $guard->pin(new Uri('https://example.org/file'));
    }

    public function testSystemResolverReturnsTheAddressesOfTheHost(): void
    {
        $resolver = PublicAddressGuard::systemResolver(
            static fn(string $host): array => [self::PUBLIC_IP],
            static fn(string $host): array => self::fail('No DNS query expected')
        );

        self::assertSame([self::PUBLIC_IP], $resolver('example.org'));
    }

    /**
     * @param array<mixed>|false $records
     * @param list<string>|null $expected
     */
    #[DataProvider('unresolvedHostProvider')]
    public function testSystemResolverTellsAFinalAnswerFromAFailedLookup(array|false $records, ?array $expected): void
    {
        $queried = [];
        $resolver = PublicAddressGuard::systemResolver(
            static fn(string $host): false => false,
            static function (string $host) use ($records, &$queried): array|false {
                $queried[] = $host;
                return $records;
            }
        );

        self::assertSame($expected, $resolver('example.org'));
        self::assertSame(['example.org'], $queried);
    }

    /**
     * @return array<string, array{array<mixed>|false, list<string>|null}>
     */
    public static function unresolvedHostProvider(): array
    {
        return [
            'unknown domain or IPv6-only host: final' => [[], []],
            'DNS server failure or timeout: may be transient' => [false, null],
            'records the system lookup did not see: may be transient' => [[['type' => 'A', 'ip' => self::PUBLIC_IP]], null],
        ];
    }

    public function testPinRefusesAnUnknownDomainResolvedByTheSystemResolver(): void
    {
        $guard = new PublicAddressGuard(PublicAddressGuard::systemResolver(
            static fn(string $host): false => false,
            static fn(string $host): array => []
        ));

        $this->expectException(RefusedAddressException::class);
        $guard->pin(new Uri('https://expired-domain.example/file.pdf'));
    }

    public function testMiddlewarePinsEveryRedirectHop(): void
    {
        $addresses = ['hal.science' => [self::PUBLIC_IP], 'cdn.example.org' => ['93.184.216.35']];
        $client = $this->makeClient(
            [new Response(302, ['Location' => 'https://cdn.example.org/final.pdf']), new Response(200, [], '%PDF')],
            static fn(string $host): array => $addresses[$host] ?? []
        );

        $client->request('GET', 'https://hal.science/hal-01/document');

        self::assertCount(2, $this->history);
        self::assertSame(['hal.science:443:' . self::PUBLIC_IP], $this->history[0]['options']['curl'][CURLOPT_RESOLVE]);
        self::assertSame(['cdn.example.org:443:93.184.216.35'], $this->history[1]['options']['curl'][CURLOPT_RESOLVE]);
        self::assertSame('v4', $this->history[1]['options']['force_ip_resolve']);
    }

    public function testMiddlewareStopsARedirectToAnInternalAddress(): void
    {
        $client = $this->makeClient(
            [new Response(302, ['Location' => 'http://169.254.169.254/latest/meta-data/'])],
            static fn(string $host): array => [self::PUBLIC_IP]
        );

        try {
            $client->request('GET', 'https://hal.science/hal-01/document');
            self::fail('The redirect should have been refused');
        } catch (RefusedAddressException) {
            self::assertCount(1, $this->history, 'the internal address is never requested');
        }
    }

    /**
     * @param list<Response> $responses
     */
    private function makeClient(array $responses, \Closure $resolver): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push((new PublicAddressGuard($resolver))->middleware());
        $stack->push(Middleware::history($this->history));

        return new Client(['handler' => $stack, 'allow_redirects' => ['max' => 5, 'protocols' => ['http', 'https']]]);
    }
}
