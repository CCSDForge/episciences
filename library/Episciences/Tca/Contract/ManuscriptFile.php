<?php

declare(strict_types=1);

namespace Episciences\Tca\Contract;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

/**
 * A manuscript ready to be streamed to Turnitin.
 *
 * The stream is positioned at its beginning and its exact size is known,
 * so it can be sent with a Content-Length header.
 */
final readonly class ManuscriptFile
{
    /** Turnitin upload limit: 100 MB. */
    public const int MAX_SIZE = 100 * 1024 * 1024;

    /**
     * @param StreamInterface $stream readable stream of the file content
     * @param string $fileName file name with its extension, not URL-encoded (may contain accents)
     * @param int $size size in bytes
     * @param string $mimeType detected MIME type, e.g. "application/pdf"
     */
    public function __construct(
        public StreamInterface $stream,
        public string $fileName,
        public int $size,
        public string $mimeType
    ) {
        if (!$stream->isReadable()) {
            throw new InvalidArgumentException('The manuscript stream must be readable.');
        }

        if ($size <= 0) {
            throw new InvalidArgumentException(sprintf('Invalid manuscript size: %d.', $size));
        }

        if (pathinfo($fileName, PATHINFO_EXTENSION) === '' || pathinfo($fileName, PATHINFO_FILENAME) === '') {
            throw new InvalidArgumentException(sprintf('The manuscript file name "%s" must have a name and an extension.', $fileName));
        }

        if ($mimeType === '') {
            throw new InvalidArgumentException('The manuscript MIME type must not be empty.');
        }
    }
}
