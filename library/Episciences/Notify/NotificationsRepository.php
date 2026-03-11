<?php

declare(strict_types=1);

namespace Episciences\Notify;

class NotificationsRepository
{
    public const MAX_INBOUND_FETCH = 1000;

    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function createFromConstants(): self
    {
        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s',
            (string) INBOX_DB_DRIVER,
            (string) INBOX_DB_HOST,
            (int)    INBOX_DB_PORT,
            (string) INBOX_DB_NAME
        );

        $pdo = new \PDO($dsn, (string) INBOX_DB_USER, (string) INBOX_DB_PASSWORD, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        return new self($pdo);
    }

    /**
     * @return Notification[]
     */
    public function findInbound(int $limit = self::MAX_INBOUND_FETCH): array
    {
        $sql = 'SELECT id, fromId, toId, inReplyToId, type, status, timestamp, original, direction
                FROM notifications
                WHERE direction = :direction
                ORDER BY timestamp DESC
                LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':direction', Notification::DIRECTION_INBOUND, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $notifications = [];
        /** @var array<string, string|null> $row */
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $notifications[] = Notification::fromRow($row);
        }

        return $notifications;
    }

    public function save(Notification $notification): void
    {
        $sql = 'INSERT INTO notifications (id, fromId, toId, inReplyToId, type, status, original, direction)
                VALUES (:id, :fromId, :toId, :inReplyToId, :type, :status, :original, :direction)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $notification->getId(), \PDO::PARAM_STR);
        $stmt->bindValue(':fromId', $notification->getFromId(), \PDO::PARAM_STR);
        $stmt->bindValue(':toId', $notification->getToId(), \PDO::PARAM_STR);
        $stmt->bindValue(':inReplyToId', $notification->getInReplyToId(), \PDO::PARAM_STR);
        $stmt->bindValue(':type', $notification->getType(), \PDO::PARAM_STR);
        $stmt->bindValue(':status', $notification->getStatus(), \PDO::PARAM_INT);
        $stmt->bindValue(':original', $notification->getOriginal(), \PDO::PARAM_STR);
        $stmt->bindValue(':direction', $notification->getDirection(), \PDO::PARAM_STR);
        $stmt->execute();
    }

    public function deleteById(string $id): void
    {
        $sql = 'DELETE FROM notifications WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_STR);
        $stmt->execute();
    }
}
