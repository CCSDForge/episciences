<?php

declare(strict_types=1);

namespace Episciences\Next\Messenger\Message;

use InvalidArgumentException;

/**
 * Asks the queue worker to trigger a Next.js ISR cache revalidation
 * (revalidateTag()) for one journal/tag pair.
 *
 * Refusing an empty rvcode/tag here moves the two "discard" branches the
 * legacy consumer (scripts/NextRevalidationQueue.php) used to handle at
 * dequeue time to enqueue time instead — NextRevalidationQueuePort checks
 * for emptiness before ever building this message, so the constructor
 * throwing is a caller bug, not a normal runtime case.
 */
final class RevalidateTagMessage
{
    public function __construct(
        public readonly string $rvcode,
        public readonly string $tag,
    ) {
        if (trim($rvcode) === '') {
            throw new InvalidArgumentException('RevalidateTagMessage requires a non-empty rvcode.');
        }

        if (trim($tag) === '') {
            throw new InvalidArgumentException('RevalidateTagMessage requires a non-empty tag.');
        }
    }
}
