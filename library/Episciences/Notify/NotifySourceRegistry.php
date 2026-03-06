<?php

declare(strict_types=1);

namespace Episciences\Notify;

/**
 * Registry mapping origin inbox URLs to their NotifySourceConfig.
 *
 * Used to determine which repository sent a COAR Notify payload and to
 * retrieve the associated configuration (repo ID, accepted types, …).
 *
 * Unknown inboxes return null — the notification should then be ignored.
 */
final class NotifySourceRegistry
{
    /** @param array<string, NotifySourceConfig> $sources  keyed by originInbox URL */
    public function __construct(private readonly array $sources) {}

    /**
     * Returns the source config for the given inbox URL, or null when unknown.
     */
    public function findByOriginInbox(string $inbox): ?NotifySourceConfig
    {
        return $this->sources[$inbox] ?? null;
    }

    /**
     * Builds the registry from application constants (NOTIFY_TARGET_*).
     *
     * Add new sources here as additional repositories adopt COAR Notify.
     */
    public static function createFromConstants(): self
    {
        $sources = [];

        if (defined('NOTIFY_TARGET_HAL_INBOX') && NOTIFY_TARGET_HAL_INBOX !== '') {
            $sources[NOTIFY_TARGET_HAL_INBOX] = new NotifySourceConfig(
                repoId:        (int) \Episciences_Repositories::HAL_REPO_ID,
                label:         'HAL',
                originId:      NOTIFY_TARGET_HAL_URL,
                originInbox:   NOTIFY_TARGET_HAL_INBOX,
                acceptedTypes: ['Offer', 'coar-notify:ReviewAction'],
            );
        }

        // Future sources added here — or loaded from config/DB.

        return new self($sources);
    }
}
