<?php

/**
 * Value object representing the result of a language code conversion operation
 */
class Episciences_Language_LanguageConversionResult
{
    private Episciences_Language_LanguageCode $originalCode;
    private Episciences_Language_LanguageCode $convertedCode;
    private bool $success;
    private array $errors;
    private array $warnings;
    
    public function __construct(
        Episciences_Language_LanguageCode $originalCode,
        Episciences_Language_LanguageCode $convertedCode,
        bool $success = true,
        array $errors = [],
        array $warnings = []
    ) {
        $this->originalCode = $originalCode;
        $this->convertedCode = $convertedCode;
        $this->success = $success;
        $this->errors = $errors;
        $this->warnings = $warnings;
    }
    
    public function getOriginalCode(): Episciences_Language_LanguageCode
    {
        return $this->originalCode;
    }
    
    public function getConvertedCode(): Episciences_Language_LanguageCode
    {
        return $this->convertedCode;
    }
    
    public function isSuccess(): bool
    {
        return $this->success;
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    public function getWarnings(): array
    {
        return $this->warnings;
    }
    
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
    
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }
    
    public function addError(string $error): void
    {
        $this->errors[] = $error;
        $this->success = false;
    }
    
    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }
    
    public static function failed(
        Episciences_Language_LanguageCode $originalCode,
        string $error,
        ?Episciences_Language_LanguageCode $fallbackCode = null
    ): self {
        $convertedCode = $fallbackCode ?? $originalCode;
        return new self($originalCode, $convertedCode, false, [$error]);
    }
    
    public static function success(
        Episciences_Language_LanguageCode $originalCode,
        Episciences_Language_LanguageCode $convertedCode
    ): self {
        return new self($originalCode, $convertedCode, true);
    }
}