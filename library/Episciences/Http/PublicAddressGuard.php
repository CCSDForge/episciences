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
    /** @var Closure(string): list<string> */
    private Closure $resolver;

    /**
     * @param Closure(string): list<string>|null $resolver IPv4 addresses of a host name, empty when it does not resolve
     */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver ?? $this->resolveIpv4(...);
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
     * @throws UnresolvedHostException the host did not resolve (may be transient)
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

        if ($addresses === []) {
            throw new UnresolvedHostException('The host could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_GLOBAL_RANGE) === false) {
                throw new RefusedAddressException('The host address is not allowed.');
            }
        }

        $port = $uri->getPort() ?? ($scheme === 'http' ? 80 : 443);

        return sprintf('%s:%d:%s', $host, $port, $addresses[0]);
    }

    /**
     * @return list<string>
     */
    private function resolveIpv4(string $host): array
    {
        $addresses = gethostbynamel($host);

        return $addresses === false ? [] : $addresses;
    }
}
