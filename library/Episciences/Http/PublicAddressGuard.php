<?php

declare(strict_types=1);

namespace Episciences\Http;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Restricts outgoing requests to HTTP(S) URLs whose host resolves to public IPv4 addresses.
 *
 * Used as an inner Guzzle middleware, it checks every request, redirects included,
 * and pins the connection to the address it checked (CURLOPT_RESOLVE).
 */
final readonly class PublicAddressGuard
{
    /** @var Closure(string): (list<string>|null) */
    private Closure $resolver;

    /**
     * @param Closure(string): (list<string>|null)|null $resolver IPv4 addresses of a host name:
     *        empty when the DNS answers that the host has none (final), null when the lookup failed (may be transient)
     */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver ?? self::systemResolver();
    }

    /**
     * Resolver of the system: the IPv4 addresses of the host (hosts file and DNS). When there are none,
     * a direct DNS query tells a final answer (unknown domain, IPv6-only host) from a failed lookup.
     *
     * @param (Closure(string): (list<string>|false))|null $hostLookup gethostbynamel() by default
     * @param (Closure(string): (array<mixed>|false))|null $recordLookup DNS A records, false when the query failed
     * @return Closure(string): (list<string>|null)
     */
    public static function systemResolver(?Closure $hostLookup = null, ?Closure $recordLookup = null): Closure
    {
        $hostLookup ??= gethostbynamel(...);
        // NXDOMAIN and "no A record" give an empty array, a server failure or a timeout gives false (and a warning)
        $recordLookup ??= static fn(string $host): array|false => @dns_get_record($host, DNS_A);

        return static function (string $host) use ($hostLookup, $recordLookup): ?array {
            $addresses = $hostLookup($host);

            if ($addresses !== false && $addresses !== []) {
                return $addresses;
            }

            return $recordLookup($host) === [] ? [] : null;
        };
    }

    /**
     * Guzzle middleware, to push after the default ones so that it sees every redirected request.
     */
    public function middleware(): Closure
    {
        return fn(callable $handler): Closure => function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $options['force_ip_resolve'] = 'v4';
            $options['curl'][CURLOPT_RESOLVE] = [$this->pin($request->getUri())];

            return $handler($request, $options);
        };
    }

    /**
     * @return string CURLOPT_RESOLVE entry "host:port:address"
     * @throws RefusedAddressException the URL is not allowed (final)
     * @throws UnresolvedHostException the host name lookup failed (may be transient)
     */
    public function pin(UriInterface $uri): string
    {
        $scheme = strtolower($uri->getScheme());

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new RefusedAddressException('Only HTTP and HTTPS URLs are allowed.');
        }

        $host = $uri->getHost();
        $literal = trim($host, '[]');
        $addresses = filter_var($literal, FILTER_VALIDATE_IP) !== false ? [$literal] : ($this->resolver)($host);

        if ($addresses === null) {
            throw new UnresolvedHostException('The host could not be resolved.');
        }

        if ($addresses === []) {
            throw new RefusedAddressException('The host has no IPv4 address.');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_GLOBAL_RANGE) === false) {
                throw new RefusedAddressException('The host address is not allowed.');
            }
        }

        $port = $uri->getPort() ?? ($scheme === 'http' ? 80 : 443);

        return sprintf('%s:%d:%s', $host, $port, $addresses[0]);
    }
}
