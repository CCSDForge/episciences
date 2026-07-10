<?php

declare(strict_types=1);

namespace Episciences\Notify;

use coarnotify\http\HttpResponse;

/**
 * Workaround for an upstream bug in cottagelabs/coarnotify (vendor package, not ours to
 * edit): coarnotify\http\CurlHttpResponse::getHeader() (vendor/cottagelabs/coarnotify/src/
 * http/CurlHttpResponse.php:29) does a raw `$this->headers[$headerName]` array access with
 * no isset()/null-coalescing guard. coarnotify\client\COARNotifyClient::send() calls
 * getHeader("Location") on every 201 response, so any inbox that omits that header (which
 * is valid HTTP — the 201 body can carry the identifier instead) triggers an "Undefined
 * array key" PHP warning there.
 *
 * This class is a drop-in HttpResponse implementation that guards the same lookup with
 * `?? null`. Once upstream fixes CurlHttpResponse::getHeader() (or we no longer need to
 * work around it), this class can be deleted and CoarNotifyHttpLayer::request() reverted
 * to `new \coarnotify\http\CurlHttpResponse($httpStatusCode, $headers)`.
 */
class CoarNotifyHttpResponse implements HttpResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(private readonly int $statusCode, private readonly array $headers)
    {
    }

    public function getHeader(string $headerName): ?string
    {
        return $this->headers[$headerName] ?? null;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}