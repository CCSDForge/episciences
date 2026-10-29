<?php

declare(strict_types=1);

namespace Episciences\Notify;

/**
 * Immutable value object representing the outcome of a COAR Notify payload validation.
 */
final class ValidationResult
{
    /** @param string[] $warnings Non-blocking deviations from spec tolerated during validation. */
    private function __construct(
        private readonly bool    $valid,
        private readonly ?string $errorMessage = null,
        private readonly array   $warnings = []
    ) {}

    /** @param string[] $warnings */
    public static function success(array $warnings = []): self
    {
        return new self(true, null, $warnings);
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

    /** @return string[] */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
