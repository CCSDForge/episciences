<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Answer given without an account to a review request of a "light answer" type (#319): a quick opinion typed and/or
 * uploaded from the invitation link. One answer per invitation, identified by the original assignment of the
 * invitation (USER_INVITATION.AID). Only the editorial committee reads it.
 */
final class LightAnswer
{
    /** Longest message accepted, in characters */
    public const MESSAGE_MAX_LENGTH = 20000;
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly ?int $id,
        private readonly int $aid,
        private readonly int $rvid,
        private readonly int $docid,
        private readonly int $requestTypeId,
        private readonly int $uid,
        private readonly bool $tmpUser,
        private readonly string $message,
        private readonly ?string $file,
        private readonly DateTimeImmutable $createdAt,
        private readonly ?DateTimeImmutable $updatedAt = null
    ) {
        if ($message === '' && ($file === null || $file === '')) {
            throw new InvalidArgumentException('An answer needs a message or a file');
        }

        if (mb_strlen($message) > self::MESSAGE_MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf('Message longer than %d characters', self::MESSAGE_MAX_LENGTH));
        }
    }

    /**
     * Whether an answer can still be given or edited: until the end of the due date of the request; always when the
     * request has no due date
     */
    public static function isEditable(?string $deadline, DateTimeImmutable $now): bool
    {
        if ($deadline === null || trim($deadline) === '') {
            return true;
        }

        $timestamp = strtotime($deadline);

        return $timestamp === false || (new DateTimeImmutable('@' . $timestamp))->setTimezone($now->getTimezone())->format('Y-m-d') >= $now->format('Y-m-d');
    }

    /**
     * Plain text of a posted message: tags removed, line breaks normalised, trimmed
     */
    public static function cleanMessage(mixed $posted): string
    {
        if (!is_string($posted)) {
            return '';
        }

        // the content of scripts and styles is not text
        $text = strip_tags(preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $posted) ?? $posted);
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return trim($text);
    }

    /**
     * @param array<string, mixed> $row a row of the review_light_answer table
     */
    public static function fromRow(array $row): self
    {
        return new self(
            isset($row['id']) ? (int)$row['id'] : null,
            (int)$row['aid'],
            (int)$row['rvid'],
            (int)$row['docid'],
            (int)$row['request_type_id'],
            (int)$row['uid'],
            (bool)$row['tmp_user'],
            (string)($row['message'] ?? ''),
            isset($row['file']) && $row['file'] !== '' ? (string)$row['file'] : null,
            new DateTimeImmutable((string)$row['created_at']),
            !empty($row['updated_at']) ? new DateTimeImmutable((string)$row['updated_at']) : null
        );
    }

    /**
     * @return array<string, int|string|null> the columns of the review_light_answer table, without the id
     */
    public function toRow(): array
    {
        return [
            'aid' => $this->aid,
            'rvid' => $this->rvid,
            'docid' => $this->docid,
            'request_type_id' => $this->requestTypeId,
            'uid' => $this->uid,
            'tmp_user' => (int)$this->tmpUser,
            'message' => $this->message,
            'file' => $this->file,
            'created_at' => $this->createdAt->format(self::DATE_FORMAT),
            'updated_at' => $this->updatedAt?->format(self::DATE_FORMAT),
        ];
    }

    /**
     * The same answer with a new message and file, edited at $now
     */
    public function edited(string $message, ?string $file, DateTimeImmutable $now): self
    {
        return new self(
            $this->id, $this->aid, $this->rvid, $this->docid, $this->requestTypeId, $this->uid, $this->tmpUser,
            $message, $file, $this->createdAt, $now
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id, $this->aid, $this->rvid, $this->docid, $this->requestTypeId, $this->uid, $this->tmpUser,
            $this->message, $this->file, $this->createdAt, $this->updatedAt
        );
    }

    /**
     * Folder of the attached file, relative to the files of the paper
     */
    public function folder(): string
    {
        return 'reports/light_' . $this->aid . '/';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAid(): int
    {
        return $this->aid;
    }

    public function getRvid(): int
    {
        return $this->rvid;
    }

    public function getDocid(): int
    {
        return $this->docid;
    }

    public function getRequestTypeId(): int
    {
        return $this->requestTypeId;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function isTmpUser(): bool
    {
        return $this->tmpUser;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
