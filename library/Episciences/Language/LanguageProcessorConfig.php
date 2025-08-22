<?php

/**
 * Configuration for language processing operations
 */
class Episciences_Language_LanguageProcessorConfig
{
    // Language code lengths
    public const ALPHA2_LENGTH = 2;
    public const ALPHA3_LENGTH = 3;
    
    // Default languages by format/context
    public const DEFAULT_CROSSREF_LANGUAGE = '';
    public const DEFAULT_DOAJ_LANGUAGE = 'eng';
    public const DEFAULT_PAPER_LANGUAGE = 'en';
    
    // Error handling modes
    public const ERROR_MODE_STRICT = 'strict';    // Throw exceptions on errors
    public const ERROR_MODE_LENIENT = 'lenient';  // Return defaults, log warnings
    
    // Conversion options
    public const CONVERT_ISO639_B_TO_T = true;
    public const CONVERT_ISO639_T_TO_B = false;
    
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }
    
    public function getDefaultLanguage(string $context = 'default'): string
    {
        $key = "default_languages.{$context}";
        return $this->get($key, self::DEFAULT_PAPER_LANGUAGE);
    }
    
    public function getErrorMode(): string
    {
        return $this->get('error_mode', self::ERROR_MODE_LENIENT);
    }
    
    public function isCachingEnabled(): bool
    {
        return $this->get('enable_caching', true);
    }
    
    public function shouldConvertIso639Code(): bool
    {
        return $this->get('convert_iso639_code', false);
    }
    
    public function isStrictMode(): bool
    {
        return $this->getErrorMode() === self::ERROR_MODE_STRICT;
    }
    
    public function getMaxCacheSize(): int
    {
        return $this->get('max_cache_size', 1000);
    }
    
    public function get(string $key, $default = null)
    {
        return $this->getNestedValue($this->config, $key, $default);
    }
    
    private function getNestedValue(array $array, string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    private function getDefaultConfig(): array
    {
        return [
            'error_mode' => self::ERROR_MODE_LENIENT,
            'enable_caching' => true,
            'max_cache_size' => 1000,
            'convert_iso639_code' => false,
            'default_languages' => [
                'crossref' => self::DEFAULT_CROSSREF_LANGUAGE,
                'doaj' => self::DEFAULT_DOAJ_LANGUAGE,
                'paper' => self::DEFAULT_PAPER_LANGUAGE,
                'default' => self::DEFAULT_PAPER_LANGUAGE
            ],
            'validation' => [
                'allow_empty' => false,
                'require_alpha_only' => true,
                'min_length' => self::ALPHA2_LENGTH,
                'max_length' => self::ALPHA3_LENGTH
            ]
        ];
    }
    
    public static function forCrossref(): self
    {
        return new self([
            'default_languages' => [
                'default' => self::DEFAULT_CROSSREF_LANGUAGE
            ]
        ]);
    }
    
    public static function forDoaj(): self
    {
        return new self([
            'convert_iso639_code' => true,
            'default_languages' => [
                'default' => self::DEFAULT_DOAJ_LANGUAGE
            ]
        ]);
    }
}