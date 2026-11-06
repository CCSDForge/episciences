<?php

declare(strict_types=1);

namespace Episciences\Tca\Manuscript;

use Episciences\Tca\Contract\ManuscriptTooLargeException;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

/**
 * Download sink refusing to grow beyond a maximum size.
 *
 * Covers the responses that announce no Content-Length (or a wrong one):
 * the write that crosses the limit throws, which aborts the transfer.
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
        $this->bytesReceived += strlen($string);

        if ($this->bytesReceived > $this->maxBytes) {
            $this->limitExceeded = true;
            throw new ManuscriptTooLargeException($this->bytesReceived, $this->maxBytes);
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
