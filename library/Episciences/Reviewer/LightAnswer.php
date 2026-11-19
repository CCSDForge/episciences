<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use DateTimeImmutable;

/**
 * Answer given without an account to a review request of a "light answer" type (#319): the rating grid of the type
 * filled in from the invitation link. One answer per invitation, identified by the original assignment of the
 * invitation (USER_INVITATION.AID). The filled grid and its attached files are kept in the folder of the answer
 * (see LightAnswerReport). Only the editorial committee reads it.
 */
final class LightAnswer
{
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly ?int $id,
        private readonly int $aid,
        private readonly int $rvid,
        private readonly int $docid,
        private readonly int $requestTypeId,
        private readonly int $uid,
        private readonly bool $tmpUser,
        private readonly DateTimeImmutable $createdAt,
        private readonly ?DateTimeImmutable $updatedAt = null
    ) {
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
            'created_at' => $this->createdAt->format(self::DATE_FORMAT),
            'updated_at' => $this->updatedAt?->format(self::DATE_FORMAT),
        ];
    }

    /**
     * The same answer, edited at $now
     */
    public function edited(DateTimeImmutable $now): self
    {
        return new self(
            $this->id, $this->aid, $this->rvid, $this->docid, $this->requestTypeId, $this->uid, $this->tmpUser,
            $this->createdAt, $now
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id, $this->aid, $this->rvid, $this->docid, $this->requestTypeId, $this->uid, $this->tmpUser,
            $this->createdAt, $this->updatedAt
        );
    }

    /**
     * Folder of the filled grid and of its attached files, relative to the files of the paper
     */
    public function folder(): string
    {
        return self::folderOf($this->aid);
    }

    /**
     * Folder of the answer of an invitation, relative to the files of the paper
     */
    public static function folderOf(int $aid): string
    {
        return 'reports/light_' . $aid . '/';
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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
