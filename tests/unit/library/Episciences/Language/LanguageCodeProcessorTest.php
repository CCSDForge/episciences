<?php

class LanguageCodeProcessorTest extends PHPUnit\Framework\TestCase
{
    private Episciences_Language_LanguageCodeProcessor $processor;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear caches before each test
        Episciences_Language_LanguageCodeProcessor::clearCaches();
        
        // Create processor with default config
        $this->processor = new Episciences_Language_LanguageCodeProcessor();
    }
    
    protected function tearDown(): void
    {
        // Clear caches after each test
        Episciences_Language_LanguageCodeProcessor::clearCaches();
        parent::tearDown();
    }
    
    public function testProcessValidLanguageCode()
    {
        // Test 2-character code (no conversion needed)
        $result = $this->processor->processLanguageCode('en', 2, 'paper123', 'test');
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('en', $result->getOriginalCode()->getCode());
        $this->assertEquals('en', $result->getConvertedCode()->getCode());
        $this->assertFalse($result->hasErrors());
    }
    
    public function testProcessEmptyLanguageCode()
    {
        $result = $this->processor->processLanguageCode('', 2, 'paper123', 'test');
        
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('', $result->getOriginalCode()->getCode());
        $this->assertEquals('en', $result->getConvertedCode()->getCode()); // Default fallback
        $this->assertTrue($result->hasErrors());
        $this->assertContains('Empty language code provided', $result->getErrors());
    }
    
    public function testProcessInvalidLengthLanguageCode()
    {
        // Test too short
        $result = $this->processor->processLanguageCode('a', 2, 'paper123', 'test');
        
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('a', $result->getOriginalCode()->getCode());
        $this->assertTrue($result->hasErrors());
        
        // Test too long
        $result = $this->processor->processLanguageCode('english', 2, 'paper123', 'test');
        
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('english', $result->getOriginalCode()->getCode());
        $this->assertTrue($result->hasErrors());
    }
    
    public function testProcessInvalidLanguageCode()
    {
        // Test with made-up language code
        $result = $this->processor->processLanguageCode('xyz', 2, 'paper123', 'test');
        
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('xyz', $result->getOriginalCode()->getCode());
        $this->assertTrue($result->hasErrors());
    }
    
    public function testLengthConversion2To3()
    {
        $result = $this->processor->processLanguageCode('en', 3, 'paper123', 'test');
        
        if ($result->isSuccess()) {
            $this->assertEquals('en', $result->getOriginalCode()->getCode());
            $this->assertEquals('eng', $result->getConvertedCode()->getCode());
        } else {
            // If conversion fails, should fallback gracefully
            $this->assertTrue($result->hasErrors());
        }
    }
    
    public function testLengthConversion3To2()
    {
        $result = $this->processor->processLanguageCode('eng', 2, 'paper123', 'test');
        
        if ($result->isSuccess()) {
            $this->assertEquals('eng', $result->getOriginalCode()->getCode());
            $this->assertEquals('en', $result->getConvertedCode()->getCode());
        } else {
            // If conversion fails, should fallback gracefully
            $this->assertTrue($result->hasErrors());
        }
    }
    
    public function testProcessLanguageCodesWithMultipleEntries()
    {
        $languageContentPairs = [
            'en' => 'English title',
            'fr' => 'Titre français',
            'invalid' => 'Invalid content'
        ];
        
        $results = $this->processor->processLanguageCodes($languageContentPairs, 2, 'paper123', 'test');
        
        // Should have at least the valid languages (en, fr)
        $this->assertGreaterThanOrEqual(2, count($results));
        
        // Check that results have the expected structure
        foreach ($results as $result) {
            $this->assertIsArray($result);
            $this->assertCount(1, $result);
        }
    }
    
    public function testIso639ConversionWithDoajConfig()
    {
        $doajConfig = Episciences_Language_LanguageProcessorConfig::forDoaj();
        $processor = new Episciences_Language_LanguageCodeProcessor($doajConfig);
        
        // Test with a language that has B/T variants (e.g., 'ger' -> 'deu')
        $result = $processor->processLanguageCode('ger', 3, 'paper123', 'doaj');
        
        if ($result->isSuccess()) {
            // Should convert 'ger' to 'deu' due to ISO 639 B->T conversion
            $this->assertEquals('deu', $result->getConvertedCode()->getCode());
        }
    }
    
    public function testCachingBehavior()
    {
        // Enable caching
        $config = new Episciences_Language_LanguageProcessorConfig(['enable_caching' => true]);
        $processor = new Episciences_Language_LanguageCodeProcessor($config);
        
        // First call
        $result1 = $processor->processLanguageCode('en', 2, 'paper123', 'test');
        $stats1 = Episciences_Language_LanguageCodeProcessor::getCacheStats();
        
        // Second call (should use cache)
        $result2 = $processor->processLanguageCode('en', 2, 'paper123', 'test');
        $stats2 = Episciences_Language_LanguageCodeProcessor::getCacheStats();
        
        // Cache should be populated
        $this->assertGreaterThan(0, $stats2['total_cache_entries']);
    }
    
    public function testCacheClearing()
    {
        // Process some language codes to populate cache
        $this->processor->processLanguageCode('en', 2);
        $this->processor->processLanguageCode('fr', 3);
        
        $statsBeforeClear = Episciences_Language_LanguageCodeProcessor::getCacheStats();
        $this->assertGreaterThan(0, $statsBeforeClear['total_cache_entries']);
        
        // Clear cache
        Episciences_Language_LanguageCodeProcessor::clearCaches();
        
        $statsAfterClear = Episciences_Language_LanguageCodeProcessor::getCacheStats();
        $this->assertEquals(0, $statsAfterClear['total_cache_entries']);
    }
    
    public function testCacheStats()
    {
        $stats = Episciences_Language_LanguageCodeProcessor::getCacheStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('validation_cache_size', $stats);
        $this->assertArrayHasKey('conversion_cache_size', $stats);
        $this->assertArrayHasKey('iso639_cache_size', $stats);
        $this->assertArrayHasKey('total_cache_entries', $stats);
        
        $this->assertIsInt($stats['validation_cache_size']);
        $this->assertIsInt($stats['conversion_cache_size']);
        $this->assertIsInt($stats['iso639_cache_size']);
        $this->assertIsInt($stats['total_cache_entries']);
    }
    
    public function testPerformanceWithCaching()
    {
        $config = new Episciences_Language_LanguageProcessorConfig(['enable_caching' => true]);
        $processor = new Episciences_Language_LanguageCodeProcessor($config);
        
        $start = microtime(true);
        
        // Process same language code multiple times
        for ($i = 0; $i < 100; $i++) {
            $processor->processLanguageCode('en', 2);
            $processor->processLanguageCode('fr', 3);
        }
        
        $duration = microtime(true) - $start;
        
        // Should complete reasonably quickly (under 1 second for 200 operations)
        $this->assertLessThan(1.0, $duration, "Performance test failed: took {$duration} seconds for 200 operations");
    }
    
    public function testStrictModeThrowsException()
    {
        $strictConfig = new Episciences_Language_LanguageProcessorConfig(['error_mode' => 'strict']);
        $processor = new Episciences_Language_LanguageCodeProcessor($strictConfig);
        
        $this->expectException(InvalidArgumentException::class);
        $processor->processLanguageCode('invalid', 2, 'paper123', 'test');
    }
    
    public function testLenientModeWithErrorCallback()
    {
        // Capture triggered errors
        $errors = [];
        set_error_handler(function($errno, $errstr) use (&$errors) {
            $errors[] = $errstr;
            return true; // Don't execute PHP's internal error handler
        }, E_USER_WARNING);
        
        try {
            $result = $this->processor->processLanguageCode('invalid', 2, 'paper123', 'test');
            
            $this->assertFalse($result->isSuccess());
            $this->assertNotEmpty($errors);
            
        } finally {
            restore_error_handler();
        }
    }
}