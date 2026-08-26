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
 *
 * Neither method ever throws: a blank rvcode/tag is silently ignored rather
 * than rejected (unlike SolrIndexQueuePort::enqueueDelete(), which does
 * reject an unusable caller input on purpose), and BoundedRetryDispatcher
 * already catches every dispatch failure. RevalidationService relies on
 * this to stay best-effort without wrapping these calls itself.
 */
final class NextRevalidationQueuePort
{
    /**
     * Bounds $seen so a long-running process (e.g. a batch script publishing
     * many papers in one run) doesn't permanently suppress a journal-wide tag
     * ("articles-{rvcode}", "sitemap-{rvcode}") after its first occurrence —
     * $seen is small in the common case (a handful of tags per save()), so
     * this only ever triggers in a long-lived process.
     */
    private const MAX_SEEN = 200;

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

        if (count($this->seen) >= self::MAX_SEEN) {
            $this->seen = [];
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
