<?php

use Symfony\Component\Intl\Languages;
use Symfony\Component\Intl\Exception\MissingResourceException;

/**
 * Service class for processing language codes with validation, conversion, and caching
 */
class Episciences_Language_LanguageCodeProcessor
{
    private Episciences_Language_LanguageProcessorConfig $config;
    private static array $validationCache = [];
    private static array $conversionCache = [];
    private static array $iso639ConversionCache = [];
    
    public function __construct(?Episciences_Language_LanguageProcessorConfig $config = null)
    {
        $this->config = $config ?? new Episciences_Language_LanguageProcessorConfig();
    }
    
    /**
     * Process a single language code with validation and conversion
     */
    public function processLanguageCode(
        string $languageCode,
        int $targetLength,
        ?string $paperId = null,
        ?string $context = null
    ): Episciences_Language_LanguageConversionResult {
        
        $originalCode = new Episciences_Language_LanguageCode($languageCode);
        $result = new Episciences_Language_LanguageConversionResult($originalCode, $originalCode);
        
        // Early return for empty codes
        if ($originalCode->isEmpty()) {
            $defaultCode = $this->getDefaultLanguageCode($context);
            return new Episciences_Language_LanguageConversionResult(
                $originalCode,
                $defaultCode,
                false,
                ['Empty language code provided']
            );
        }
        
        // Validate length
        if (!$originalCode->hasValidLength()) {
            $this->logLanguageError($paperId, $context, $originalCode->getCode(), 'Invalid language code length');
            $defaultCode = $this->getDefaultLanguageCode($context);
            return Episciences_Language_LanguageConversionResult::failed(
                $originalCode,
                "Invalid language code length: {$originalCode->getLength()}",
                $defaultCode
            );
        }
        
        // Validate language exists
        if (!$this->isValidLanguage($originalCode->getCode())) {
            $this->logLanguageError($paperId, $context, $originalCode->getCode(), 'Language does not exist');
            $defaultCode = $this->getDefaultLanguageCode($context);
            return Episciences_Language_LanguageConversionResult::failed(
                $originalCode,
                "Language '{$originalCode->getCode()}' does not exist",
                $defaultCode
            );
        }
        
        // Convert length if needed
        $convertedCode = $this->convertLanguageCodeLength($originalCode, $targetLength, $paperId, $context);
        if (!$convertedCode->isSuccess()) {
            return $convertedCode;
        }
        
        // Apply ISO 639-2 B/T conversion if needed
        if ($this->config->shouldConvertIso639Code() && $targetLength === 3) {
            $iso639ConvertedCode = $this->convertIso639Code($convertedCode->getConvertedCode());
            $convertedCode = new Episciences_Language_LanguageConversionResult(
                $originalCode,
                $iso639ConvertedCode,
                $convertedCode->isSuccess(),
                $convertedCode->getErrors(),
                $convertedCode->getWarnings()
            );
        }
        
        return $convertedCode;
    }
    
    /**
     * Process multiple language codes from paper metadata
     */
    public function processLanguageCodes(
        array $languageContentPairs,
        int $targetLength,
        ?string $paperId = null,
        ?string $context = null
    ): array {
        
        $results = [];
        
        foreach ($languageContentPairs as $languageCode => $content) {
            $result = $this->processLanguageCode($languageCode, $targetLength, $paperId, $context);
            
            if ($result->isSuccess() || !$result->getConvertedCode()->isEmpty()) {
                $finalLanguageCode = $result->getConvertedCode()->getCode();
                $results[] = [$finalLanguageCode => trim($content)];
            }
        }
        
        return $results;
    }
    
    /**
     * Validate if a language code exists (with caching)
     */
    private function isValidLanguage(string $languageCode): bool
    {
        if (!$this->config->isCachingEnabled()) {
            return Languages::exists($languageCode);
        }
        
        $cacheKey = strtolower($languageCode);
        
        if (!isset(self::$validationCache[$cacheKey])) {
            // Limit cache size to prevent memory issues
            if (count(self::$validationCache) >= $this->config->getMaxCacheSize()) {
                self::$validationCache = array_slice(self::$validationCache, -500, null, true);
            }
            
            self::$validationCache[$cacheKey] = Languages::exists($languageCode);
        }
        
        return self::$validationCache[$cacheKey];
    }
    
    /**
     * Convert language code length (2 ↔ 3 characters)
     */
    private function convertLanguageCodeLength(
        Episciences_Language_LanguageCode $languageCode,
        int $targetLength,
        ?string $paperId = null,
        ?string $context = null
    ): Episciences_Language_LanguageConversionResult {
        
        $currentLength = $languageCode->getLength();
        
        // No conversion needed
        if ($currentLength === $targetLength) {
            return Episciences_Language_LanguageConversionResult::success($languageCode, $languageCode);
        }
        
        $cacheKey = $languageCode->getCode() . '_to_' . $targetLength;
        
        // Check cache first
        if ($this->config->isCachingEnabled() && isset(self::$conversionCache[$cacheKey])) {
            $cachedResult = self::$conversionCache[$cacheKey];
            if ($cachedResult['success']) {
                $convertedCode = new Episciences_Language_LanguageCode($cachedResult['code']);
                return Episciences_Language_LanguageConversionResult::success($languageCode, $convertedCode);
            } else {
                $defaultCode = $this->getDefaultLanguageCode($context);
                return Episciences_Language_LanguageConversionResult::failed(
                    $languageCode,
                    $cachedResult['error'],
                    $defaultCode
                );
            }
        }
        
        try {
            $convertedCodeString = ($targetLength === 2) 
                ? Languages::getAlpha2Code($languageCode->getCode())
                : Languages::getAlpha3Code($languageCode->getCode());
            
            $convertedCode = new Episciences_Language_LanguageCode($convertedCodeString);
            
            // Cache successful conversion
            if ($this->config->isCachingEnabled()) {
                self::$conversionCache[$cacheKey] = [
                    'success' => true,
                    'code' => $convertedCodeString
                ];
            }
            
            return Episciences_Language_LanguageConversionResult::success($languageCode, $convertedCode);
            
        } catch (MissingResourceException $e) {
            $error = "Failed to convert '{$languageCode->getCode()}' to {$targetLength}-character code: {$e->getMessage()}";
            
            // Cache failed conversion
            if ($this->config->isCachingEnabled()) {
                self::$conversionCache[$cacheKey] = [
                    'success' => false,
                    'error' => $error
                ];
            }
            
            $this->logLanguageError($paperId, $context, $languageCode->getCode(), $error);
            
            $defaultCode = $this->getDefaultLanguageCode($context);
            return Episciences_Language_LanguageConversionResult::failed($languageCode, $error, $defaultCode);
        }
    }
    
    /**
     * Convert between ISO 639-2 B and T formats
     */
    private function convertIso639Code(Episciences_Language_LanguageCode $languageCode): Episciences_Language_LanguageCode
    {
        $code = $languageCode->getCode();
        
        if ($this->config->isCachingEnabled()) {
            if (isset(self::$iso639ConversionCache[$code])) {
                return new Episciences_Language_LanguageCode(self::$iso639ConversionCache[$code]);
            }
        }
        
        $convertedCode = Episciences_Tools::convertIso639Code($code);
        
        if ($this->config->isCachingEnabled()) {
            self::$iso639ConversionCache[$code] = $convertedCode;
        }
        
        return new Episciences_Language_LanguageCode($convertedCode);
    }
    
    /**
     * Get default language code for context
     */
    private function getDefaultLanguageCode(?string $context = null): Episciences_Language_LanguageCode
    {
        $defaultLanguage = $this->config->getDefaultLanguage($context ?? 'default');
        return new Episciences_Language_LanguageCode($defaultLanguage);
    }
    
    /**
     * Log language-related errors
     */
    private function logLanguageError(?string $paperId, ?string $context, string $languageCode, string $message): void
    {
        $contextStr = $context ? ucfirst($context) : 'Language processing';
        $paperStr = $paperId ? "Paper # {$paperId}" : 'Paper';
        
        $fullMessage = sprintf(
            "%s for %s: '%s' - %s",
            $contextStr,
            $paperStr,
            htmlspecialchars($languageCode),
            $message
        );
        
        if ($this->config->isStrictMode()) {
            throw new InvalidArgumentException($fullMessage);
        } else {
            trigger_error($fullMessage, E_USER_WARNING);
        }
    }
    
    /**
     * Clear all caches (useful for testing)
     */
    public static function clearCaches(): void
    {
        self::$validationCache = [];
        self::$conversionCache = [];
        self::$iso639ConversionCache = [];
    }
    
    /**
     * Get cache statistics (for debugging/monitoring)
     */
    public static function getCacheStats(): array
    {
        return [
            'validation_cache_size' => count(self::$validationCache),
            'conversion_cache_size' => count(self::$conversionCache),
            'iso639_cache_size' => count(self::$iso639ConversionCache),
            'total_cache_entries' => count(self::$validationCache) + count(self::$conversionCache) + count(self::$iso639ConversionCache)
        ];
    }
}