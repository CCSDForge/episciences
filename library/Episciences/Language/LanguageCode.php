<?php

/**
 * Immutable value object representing a language code with validation
 */
class Episciences_Language_LanguageCode
{
    public const LENGTH_ALPHA2 = 2;
    public const LENGTH_ALPHA3 = 3;
    public const MIN_LENGTH = self::LENGTH_ALPHA2;
    public const MAX_LENGTH = self::LENGTH_ALPHA3;
    
    private string $code;
    private int $length;
    private bool $isValid;
    
    public function __construct(string $code)
    {
        $this->code = strtolower(trim($code));
        $this->length = strlen($this->code);
        $this->isValid = $this->validateCode();
    }
    
    public function getCode(): string
    {
        return $this->code;
    }
    
    public function getLength(): int
    {
        return $this->length;
    }
    
    public function isValid(): bool
    {
        return $this->isValid;
    }
    
    public function isEmpty(): bool
    {
        return empty($this->code);
    }
    
    public function isAlpha2(): bool
    {
        return $this->length === self::LENGTH_ALPHA2;
    }
    
    public function isAlpha3(): bool
    {
        return $this->length === self::LENGTH_ALPHA3;
    }
    
    public function hasValidLength(): bool
    {
        return $this->length >= self::MIN_LENGTH && $this->length <= self::MAX_LENGTH;
    }
    
    private function validateCode(): bool
    {
        if ($this->isEmpty() || !$this->hasValidLength()) {
            return false;
        }
        
        return ctype_alpha($this->code);
    }
    
    public function __toString(): string
    {
        return $this->code;
    }
    
    public function equals(Episciences_Language_LanguageCode $other): bool
    {
        return $this->code === $other->code;
    }
}