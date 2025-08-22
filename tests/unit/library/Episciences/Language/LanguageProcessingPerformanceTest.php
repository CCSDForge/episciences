<?php

/**
 * Performance benchmark tests for language processing improvements
 */
class LanguageProcessingPerformanceTest extends PHPUnit\Framework\TestCase
{
    private const BENCHMARK_ITERATIONS = 1000;
    private const PERFORMANCE_THRESHOLD_SECONDS = 2.0;
    
    protected function setUp(): void
    {
        parent::setUp();
        Episciences_Language_LanguageCodeProcessor::clearCaches();
    }
    
    protected function tearDown(): void
    {
        Episciences_Language_LanguageCodeProcessor::clearCaches();
        parent::tearDown();
    }
    
    public function testLanguageValidationPerformance()
    {
        $processor = new Episciences_Language_LanguageCodeProcessor();
        
        $testLanguages = ['en', 'fr', 'de', 'es', 'it', 'pt', 'nl', 'sv', 'da', 'no'];
        
        $start = microtime(true);
        
        for ($i = 0; $i < self::BENCHMARK_ITERATIONS; $i++) {
            foreach ($testLanguages as $lang) {
                $processor->processLanguageCode($lang, 2, "paper{$i}", 'test');
            }
        }
        
        $duration = microtime(true) - $start;
        
        $operationsPerSecond = (self::BENCHMARK_ITERATIONS * count($testLanguages)) / $duration;
        
        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_SECONDS, 
            $duration, 
            "Language validation performance test failed: {$duration} seconds for " . 
            (self::BENCHMARK_ITERATIONS * count($testLanguages)) . " operations. " .
            "Performance: {$operationsPerSecond} ops/sec"
        );
        
        echo "\nLanguage validation performance: {$operationsPerSecond} operations/second\n";
    }
    
    public function testLanguageConversionPerformance()
    {
        $processor = new Episciences_Language_LanguageCodeProcessor();
        
        $testLanguages = [
            ['en', 3],    // 2->3 conversion
            ['eng', 2],   // 3->2 conversion
            ['fr', 3],    // 2->3 conversion
            ['fra', 2],   // 3->2 conversion
            ['de', 3],    // 2->3 conversion
            ['deu', 2]    // 3->2 conversion
        ];
        
        $start = microtime(true);
        
        for ($i = 0; $i < self::BENCHMARK_ITERATIONS; $i++) {
            foreach ($testLanguages as [$lang, $targetLength]) {
                $processor->processLanguageCode($lang, $targetLength, "paper{$i}", 'test');
            }
        }
        
        $duration = microtime(true) - $start;
        
        $operationsPerSecond = (self::BENCHMARK_ITERATIONS * count($testLanguages)) / $duration;
        
        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_SECONDS, 
            $duration,
            "Language conversion performance test failed: {$duration} seconds for " .
            (self::BENCHMARK_ITERATIONS * count($testLanguages)) . " operations. " .
            "Performance: {$operationsPerSecond} ops/sec"
        );
        
        echo "\nLanguage conversion performance: {$operationsPerSecond} operations/second\n";
    }
    
    public function testCachingEffectiveness()
    {
        $processor = new Episciences_Language_LanguageCodeProcessor();
        
        // Test with caching enabled (default)
        $start = microtime(true);
        
        for ($i = 0; $i < self::BENCHMARK_ITERATIONS; $i++) {
            // Repeatedly process same language codes to test cache effectiveness
            $processor->processLanguageCode('en', 2, "paper{$i}", 'test');
            $processor->processLanguageCode('en', 3, "paper{$i}", 'test');
            $processor->processLanguageCode('fr', 2, "paper{$i}", 'test');
            $processor->processLanguageCode('fr', 3, "paper{$i}", 'test');
        }
        
        $cachedDuration = microtime(true) - $start;
        $cachedOpsPerSec = (self::BENCHMARK_ITERATIONS * 4) / $cachedDuration;
        
        // Clear cache and test without caching benefits
        Episciences_Language_LanguageCodeProcessor::clearCaches();
        $noCacheConfig = new Episciences_Language_LanguageProcessorConfig(['enable_caching' => false]);
        $noCacheProcessor = new Episciences_Language_LanguageCodeProcessor($noCacheConfig);
        
        $start = microtime(true);
        
        for ($i = 0; $i < self::BENCHMARK_ITERATIONS; $i++) {
            $noCacheProcessor->processLanguageCode('en', 2, "paper{$i}", 'test');
            $noCacheProcessor->processLanguageCode('en', 3, "paper{$i}", 'test');
            $noCacheProcessor->processLanguageCode('fr', 2, "paper{$i}", 'test');
            $noCacheProcessor->processLanguageCode('fr', 3, "paper{$i}", 'test');
        }
        
        $noCacheDuration = microtime(true) - $start;
        $noCacheOpsPerSec = (self::BENCHMARK_ITERATIONS * 4) / $noCacheDuration;
        
        $improvementFactor = $cachedOpsPerSec / $noCacheOpsPerSec;
        
        echo "\nCaching performance comparison:\n";
        echo "With caching: {$cachedOpsPerSec} ops/sec ({$cachedDuration}s)\n";
        echo "Without caching: {$noCacheOpsPerSec} ops/sec ({$noCacheDuration}s)\n";
        echo "Improvement factor: {$improvementFactor}x\n";
        
        // Caching should provide at least 20% improvement for repeated operations
        $this->assertGreaterThan(1.2, $improvementFactor, "Caching should provide at least 20% performance improvement");
    }
    
    public function testMemoryUsageWithLargeDataset()
    {
        $processor = new Episciences_Language_LanguageCodeProcessor();
        
        $memoryBefore = memory_get_usage(true);
        
        // Generate a large dataset of language processing operations
        $languages = ['en', 'fr', 'de', 'es', 'it', 'pt', 'nl', 'sv', 'da', 'no', 'fi', 'pl', 'cs', 'sk', 'hu'];
        
        for ($i = 0; $i < 5000; $i++) {
            foreach ($languages as $lang) {
                $processor->processLanguageCode($lang, rand(2, 3), "paper{$i}", 'test');
            }
        }
        
        $memoryAfter = memory_get_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;
        $memoryUsedMB = $memoryUsed / (1024 * 1024);
        
        echo "\nMemory usage for large dataset: {$memoryUsedMB} MB\n";
        
        // Should not use excessive memory (less than 50MB for this test)
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, "Memory usage should be reasonable: used {$memoryUsedMB} MB");
        
        // Verify cache size is limited
        $stats = Episciences_Language_LanguageCodeProcessor::getCacheStats();
        echo "Cache statistics: " . json_encode($stats) . "\n";
        
        // Cache should not grow unbounded
        $this->assertLessThan(2000, $stats['total_cache_entries'], "Cache size should be limited");
    }
    
    public function testMultipleLanguageProcessingPerformance()
    {
        $processor = new Episciences_Language_LanguageCodeProcessor();
        
        // Simulate processing metadata with multiple languages (like titles/abstracts)
        $testData = [
            'en' => 'English content',
            'fr' => 'Contenu français',
            'de' => 'Deutsche Inhalte',
            'es' => 'Contenido español',
            'it' => 'Contenuto italiano'
        ];
        
        $start = microtime(true);
        
        for ($i = 0; $i < self::BENCHMARK_ITERATIONS / 10; $i++) { // Reduced iterations since this is more complex
            $processor->processLanguageCodes($testData, 2, "paper{$i}", 'test');
            $processor->processLanguageCodes($testData, 3, "paper{$i}", 'test');
        }
        
        $duration = microtime(true) - $start;
        $operationsPerSecond = (self::BENCHMARK_ITERATIONS / 10 * 2) / $duration;
        
        echo "\nMultiple language processing performance: {$operationsPerSecond} operations/second\n";
        
        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_SECONDS,
            $duration,
            "Multiple language processing test failed: {$duration} seconds"
        );
    }
    
    public function testIso639ConversionPerformance()
    {
        $doajConfig = Episciences_Language_LanguageProcessorConfig::forDoaj();
        $processor = new Episciences_Language_LanguageCodeProcessor($doajConfig);
        
        // Test languages that have B/T variants
        $testLanguages = ['ger', 'fre', 'deu', 'fra', 'alb', 'sqi', 'arm', 'hye'];
        
        $start = microtime(true);
        
        for ($i = 0; $i < self::BENCHMARK_ITERATIONS; $i++) {
            foreach ($testLanguages as $lang) {
                $processor->processLanguageCode($lang, 3, "paper{$i}", 'doaj');
            }
        }
        
        $duration = microtime(true) - $start;
        $operationsPerSecond = (self::BENCHMARK_ITERATIONS * count($testLanguages)) / $duration;
        
        echo "\nISO 639 B/T conversion performance: {$operationsPerSecond} operations/second\n";
        
        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD_SECONDS,
            $duration,
            "ISO 639 conversion performance test failed: {$duration} seconds"
        );
    }
}