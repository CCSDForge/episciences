<?php

declare(strict_types=1);

namespace Episciences\Notify;

class Notification
{
    public const DIRECTION_INBOUND  = 'INBOUND';
    public const DIRECTION_OUTBOUND = 'OUTBOUND';

    public const STATUS_PENDING = 0;
    public const STATUS_FAILED  = -1;

    private string $id;
    private string $fromId;
    private string $toId;
    private ?string $inReplyToId = null;
    private string $type;
    private int $status = 0;
    private string $original;
    private string $direction;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getFromId(): string
    {
        return $this->fromId;
    }

    public function setFromId(string $fromId): void
    {
        $this->fromId = $fromId;
    }

    public function getToId(): string
    {
        return $this->toId;
    }

    public function setToId(string $toId): void
    {
        $this->toId = $toId;
    }

    public function getInReplyToId(): ?string
    {
        return $this->inReplyToId;
    }

    public function setInReplyToId(?string $inReplyToId): void
    {
        $this->inReplyToId = $inReplyToId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getOriginal(): string
    {
        return $this->original;
    }

    public function setOriginal(string $original): void
    {
        $this->original = $original;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): void
    {
        if (!in_array($direction, [self::DIRECTION_INBOUND, self::DIRECTION_OUTBOUND], true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid direction "%s". Expected one of: %s, %s.',
                $direction,
                self::DIRECTION_INBOUND,
                self::DIRECTION_OUTBOUND
            ));
        }
        $this->direction = $direction;
    }

    /**
     * @param array<string, string|null> $row PDO FETCH_ASSOC row — values are always string|null
     */
    public static function fromRow(array $row): self
    {
        $notification = new self();
        $notification->setId((string) $row['id']);
        $notification->setFromId((string) $row['fromId']);
        $notification->setToId((string) $row['toId']);
        $notification->setInReplyToId(isset($row['inReplyToId']) ? (string) $row['inReplyToId'] : null);
        $notification->setType((string) $row['type']);
        $notification->setStatus((int) $row['status']);
        $notification->setOriginal((string) $row['original']);
        $notification->setDirection((string) $row['direction']);
        return $notification;
    }
}
