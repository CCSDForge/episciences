<?php

/**
 * Result object for author-editor communication message submissions
 * Encapsulates success/error state, flash message info, and redirect behavior
 */
class Episciences_Paper_MessageSubmissionResult
{
    public const TYPE_SUCCESS = 'success';
    public const TYPE_ERROR = 'error';
    public const TYPE_VALIDATION_ERROR = 'validation_error';
    public const TYPE_CSRF_INVALID = 'csrf_invalid';
    public const TYPE_UNAUTHORIZED = 'unauthorized';
    public const TYPE_NOT_FOUND = 'not_found';

    /**
     * @param string $type Result type (success, error, etc.)
     * @param string $messageKey Translation key for the flash message
     * @param int|null $pcid Parent comment ID (for replies)
     * @param Episciences_Comment|null $comment The created comment on success
     */
    private function __construct(private readonly string $type, private readonly string $messageKey, private readonly ?int $pcid = null, private readonly ?Episciences_Comment $comment = null)
    {
    }

    /**
     * Create a success result
     *
     * @param string $messageKey Translation key for success message
     * @param int|null $pcid Parent comment ID
     * @param Episciences_Comment|null $comment The created comment
     */
    public static function success(string $messageKey, ?int $pcid = null, ?Episciences_Comment $comment = null): self
    {
        return new self(self::TYPE_SUCCESS, $messageKey, $pcid, $comment);
    }

    /**
     * Create a generic error result
     *
     * @param string $messageKey Translation key for error message
     */
    public static function error(string $messageKey): self
    {
        return new self(self::TYPE_ERROR, $messageKey);
    }

    /**
     * Create a validation error result (empty comment, etc.)
     *
     * @param string $messageKey Translation key for validation error
     */
    public static function validationError(string $messageKey): self
    {
        return new self(self::TYPE_VALIDATION_ERROR, $messageKey);
    }

    /**
     * Create a CSRF validation failure result
     *
     * @param string $messageKey Translation key for CSRF error
     */
    public static function csrfInvalid(string $messageKey): self
    {
        return new self(self::TYPE_CSRF_INVALID, $messageKey);
    }

    /**
     * Create an unauthorized access result
     *
     * @param string $messageKey Translation key for unauthorized error
     */
    public static function unauthorized(string $messageKey): self
    {
        return new self(self::TYPE_UNAUTHORIZED, $messageKey);
    }

    /**
     * Create a not found result (parent comment not found, etc.)
     *
     * @param string $messageKey Translation key for not found error
     */
    public static function notFound(string $messageKey): self
    {
        return new self(self::TYPE_NOT_FOUND, $messageKey);
    }

    /**
     * Check if the result indicates success
     */
    public function isSuccess(): bool
    {
        return $this->type === self::TYPE_SUCCESS;
    }

    /**
     * Get the result type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the translation key for the flash message
     */
    public function getMessageKey(): string
    {
        return $this->messageKey;
    }

    /**
     * Get the parent comment ID
     */
    public function getPcid(): ?int
    {
        return $this->pcid;
    }

    /**
     * Get the created comment (on success)
     */
    public function getComment(): ?Episciences_Comment
    {
        return $this->comment;
    }

    /**
     * Check if a redirect should be performed after handling
     * Currently always returns true, kept for interface consistency
     */
    public function shouldRedirect(): bool
    {
        return true;
    }

    /**
     * Get the flash messenger namespace based on result type
     *
     * @return string 'success' or 'error'
     */
    public function getFlashNamespace(): string
    {
        return $this->isSuccess() ? 'success' : 'error';
    }
}