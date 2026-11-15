<?php
declare(strict_types=1);

namespace Episciences\Volume;

use InvalidArgumentException;

/**
 * Entry of the deposit queue of a proceedings volume DOI (doi_queue_volumes).
 *
 * Lifecycle: not-assigned (no row) -> assigned (volume form) -> requested (doi:manage --request
 * --volumes) -> public (doi:manage --check --volumes).
 */
final class DoiQueue
{
    /** No row in the queue: never stored */
    public const STATUS_NOT_ASSIGNED = 'not-assigned';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_PUBLIC = 'public';

    /** Statuses stored in doi_queue_volumes.doi_status */
    public const STORED_STATUSES = [self::STATUS_ASSIGNED, self::STATUS_REQUESTED, self::STATUS_PUBLIC];

    /** The DOI was sent to Crossref: it can no longer change */
    public const LOCKED_STATUSES = [self::STATUS_REQUESTED, self::STATUS_PUBLIC];

    /** Label (to translate) and Bootstrap badge of each stored status */
    public const STATUS_BADGES = [
        self::STATUS_ASSIGNED => ['label' => 'Dépôt programmé', 'class' => 'label-primary'],
        self::STATUS_REQUESTED => ['label' => 'Dépôt en cours', 'class' => 'label-warning'],
        self::STATUS_PUBLIC => ['label' => 'DOI enregistré', 'class' => 'label-success'],
    ];

    private string $status;

    public function __construct(
        private readonly int $vid,
        string $status = self::STATUS_NOT_ASSIGNED,
        private readonly ?int $id = null
    ) {
        $this->setStatus($status);
    }

    /**
     * @param array<string, mixed> $row row of doi_queue_volumes
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int)($row['vid'] ?? 0),
            (string)($row['doi_status'] ?? '') ?: self::STATUS_NOT_ASSIGNED,
            isset($row['id']) ? (int)$row['id'] : null
        );
    }

    public static function isLockedStatus(string $status): bool
    {
        return in_array($status, self::LOCKED_STATUSES, true);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVid(): int
    {
        return $this->vid;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @throws InvalidArgumentException unknown status
     */
    public function setStatus(string $status): self
    {
        if ($status !== self::STATUS_NOT_ASSIGNED && !in_array($status, self::STORED_STATUSES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown volume DOI status "%s".', $status));
        }

        $this->status = $status;

        return $this;
    }

    public function isLocked(): bool
    {
        return self::isLockedStatus($this->status);
    }
}
