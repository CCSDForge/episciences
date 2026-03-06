<?php

declare(strict_types=1);

namespace Episciences\Notify;

/**
 * Immutable value object describing one COAR Notify source (e.g. HAL, Zenodo).
 *
 * Each source is identified by its origin inbox URL, which appears in every
 * COAR Notify payload at `origin.inbox`.
 */
final class NotifySourceConfig
{
    /**
     * @param int      $repoId        Internal Episciences repository ID.
     * @param string   $label         Human-readable label (e.g. 'HAL', 'Zenodo').
     * @param string   $originId      Repository home URL (e.g. 'https://hal.science/').
     * @param string   $originInbox   Inbox URL used to identify this source in payloads.
     * @param string[] $acceptedTypes COAR Notify types this source may send.
     */
    public function __construct(
        private readonly int    $repoId,
        private readonly string $label,
        private readonly string $originId,
        private readonly string $originInbox,
        private readonly array  $acceptedTypes = ['Offer', 'coar-notify:ReviewAction'],
    ) {}

    public function getRepoId(): int
    {
        return $this->repoId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getOriginId(): string
    {
        return $this->originId;
    }

    public function getOriginInbox(): string
    {
        return $this->originInbox;
    }

    /** @return string[] */
    public function getAcceptedTypes(): array
    {
        return $this->acceptedTypes;
    }
}
