<?php

declare(strict_types=1);

namespace Episciences\Notify;

/**
 * Immutable value object representing the outcome of a COAR Notify payload validation.
 */
final class ValidationResult
{
    private function __construct(
        private readonly bool    $valid,
        private readonly ?string $errorMessage = null
    ) {}

    public static function success(): self
    {
        return new self(true);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
