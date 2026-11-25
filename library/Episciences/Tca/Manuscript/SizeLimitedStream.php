<?php

declare(strict_types=1);

namespace Episciences\Tca\Manuscript;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

/**
 * Download sink refusing to grow beyond a maximum size.
 *
 * Covers the responses that announce no Content-Length (or a wrong one): the write that crosses
 * the limit writes nothing and returns 0. cURL takes this short write as an error and aborts
 * the transfer (CURLE_WRITE_ERROR, 23); the caller then checks hasExceededLimit(), which also
 * covers a transfer handler that would ignore the short write.
 */
final class SizeLimitedStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private int $bytesReceived = 0;

    private bool $limitExceeded = false;

    public function __construct(private readonly StreamInterface $stream, private readonly int $maxBytes)
    {
    }

    #[\Override]
    public function write(string $string): int
    {
        if ($this->limitExceeded) {
            return 0;
        }

        $this->bytesReceived += strlen($string);

        if ($this->bytesReceived > $this->maxBytes) {
            $this->limitExceeded = true;
            return 0;
        }

        return $this->stream->write($string);
    }

    public function hasExceededLimit(): bool
    {
        return $this->limitExceeded;
    }

    public function getBytesReceived(): int
    {
        return $this->bytesReceived;
    }
}
