<?php

declare(strict_types=1);

namespace Episciences\Next\Enqueue;

use Episciences\Messenger\Enqueue\BoundedRetryDispatcher;
use Episciences\Next\Messenger\Message\RevalidateTagMessage;

/**
 * The single entry point used to enqueue a Next.js cache revalidation — by
 * the trigger call sites, indirectly, via
 * Episciences\Next\RevalidationService.
 *
 * Constructor form is deliberately identical to SolrIndexQueuePort's (one
 * BoundedRetryDispatcher) so the same test doubles serve both.
 *
 * Deduplicates identical (rvcode, tag) pairs for the lifetime of this
 * instance: Paper::save() and the CODE_STATUS hook both emit
 * "article-{docId}", and Section::save()+sort() both emit
 * "sections-{rvcode}" — without this, each becomes its own message and its
 * own POST, which also caps the burst that would otherwise risk a 429.
 */
final class NextRevalidationQueuePort
{
    /** @var array<string, true> */
    private array $seen = [];

    public function __construct(private readonly BoundedRetryDispatcher $dispatcher)
    {
    }

    public function enqueueTag(string $rvcode, string $tag): void
    {
        if (trim($rvcode) === '' || trim($tag) === '') {
            return;
        }

        $key = $rvcode . '|' . $tag;

        if (isset($this->seen[$key])) {
            return;
        }

        $this->seen[$key] = true;

        $this->dispatcher->dispatch(
            new RevalidateTagMessage($rvcode, $tag),
            'revalidate',
            ['rvcode' => $rvcode, 'tag' => $tag]
        );
    }

    /** @param string[] $tags */
    public function enqueueTags(string $rvcode, array $tags): void
    {
        foreach ($tags as $tag) {
            $this->enqueueTag($rvcode, $tag);
        }
    }
}
