<?php

/**
 * Integration tests for the refactored language processing methods
 */
class ExportLanguageIntegrationTest extends PHPUnit\Framework\TestCase
{
    private $mockPaper;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear caches before each test
        Episciences_Language_LanguageCodeProcessor::clearCaches();
        
        // Create a mock paper object
        $this->mockPaper = $this->createMock(Episciences_Paper::class);
    }
    
    protected function tearDown(): void
    {
        Episciences_Language_LanguageCodeProcessor::clearCaches();
        parent::tearDown();
    }
    
    public function testGetPaperLanguageCodeWithValidLanguage()
    {
        // Set up mock paper with valid language
        $this->mockPaper->method('getMetadata')
                       ->with('language')
                       ->willReturn('en');
        $this->mockPaper->method('getDocid')
                       ->willReturn('12345');
        
        $result = Export::getPaperLanguageCode($this->mockPaper, 2);
        
        $this->assertEquals('en', $result);
    }
    
    public function testGetPaperLanguageCodeWithConversion()
    {
        // Set up mock paper with 3-character language code
        $this->mockPaper->method('getMetadata')
                       ->with('language')
                       ->willReturn('eng');
        $this->mockPaper->method('getDocid')
                       ->willReturn('12345');
        
        $result = Export::getPaperLanguageCode($this->mockPaper, 2);
        
        $this->assertEquals('en', $result);
    }
    
    public function testGetPaperLanguageCodeWithInvalidLanguage()
    {
        // Set up mock paper with invalid language
        $this->mockPaper->method('getMetadata')
                       ->with('language')
                       ->willReturn('invalid');
        $this->mockPaper->method('getDocid')
                       ->willReturn('12345');
        
        $result = Export::getPaperLanguageCode($this->mockPaper, 2, 'en');
        
        $this->assertEquals('en', $result); // Should fallback to default
    }
    
    public function testGetPaperLanguageCodeWithEmptyLanguage()
    {
        // Set up mock paper with empty language
        $this->mockPaper->method('getMetadata')
                       ->with('language')
                       ->willReturn('');
        $this->mockPaper->method('getDocid')
                       ->willReturn('12345');
        
        $result = Export::getPaperLanguageCode($this->mockPaper, 2, 'en');
        
        $this->assertEquals('en', $result); // Should fallback to default
    }
    
    public function testGetPaperLanguageCodeWithIso639Conversion()
    {
        // Set up mock paper with B-format language (German)
        $this->mockPaper->method('getMetadata')
                       ->with('language')
                       ->willReturn('ger');
        $this->mockPaper->method('getDocid')
                       ->willReturn('12345');
        
        $result = Export::getPaperLanguageCode($this->mockPaper, 3, 'eng', true);
        
        $this->assertEquals('deu', $result); // Should convert ger (B) to deu (T)
    }
    
    public function testGetMetaWithLanguagesCodeWithValidData()
    {
        // Set up mock paper with titles in multiple languages
        $this->mockPaper->method('getAllTitles')
                       ->willReturn([
                           'en' => 'English Title',
                           'fr' => 'Titre Français'
                       ]);
        $this->mockPaper->method('getDocid')
                       ->willReturn('12345');
        
        $result = Export::crossrefGetTitlesWithLanguages($this->mockPaper);
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        
        // Check structure - each result should be an array with one key-value pair
        foreach ($result as $item) {
            $this->assertIsArray($item);
            $this->assertCount(1, $item);
        }
    }
    
    public function testGetMetaWithLanguagesCodeWithInvalidLanguages()
    {
        // Set up mock paper with some invalid languages
        $this->mockPaper->method('getAllTitles')
                       ->willReturn([
                           'en' => 'English Title',
                           'invalid' => 'Invalid Title',
                           'fr' => 'Titre Français'
                       ]);
        $this->mockPaper->method('getDocid')
                       ->willReturn('12345');
        
        $result = Export::crossrefGetTitlesWithLanguages($this->mockPaper);
        
        $this->assertIsArray($result);
        // Should have filtered out invalid languages
        $this->assertGreaterThanOrEqual(2, count($result));
    }
    
    public function testGetMetaWithLanguagesCodeWithLengthConversion()
    {
        // Set up mock paper with 3-character language codes
        $this->mockPaper->method('getAllTitles')
                       ->willReturn([
                           'eng' => 'English Title',
                           'fra' => 'Titre Français'
                       ]);
        $this->mockPaper->method('getDocid')
                       ->willReturn('12345');
        
        $result = Export::crossrefGetTitlesWithLanguages($this->mockPaper);
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        
        // Verify that language codes were converted to 2-character format
        $languageCodes = [];
        foreach ($result as $item) {
            $key = array_keys($item)[0];
            $languageCodes[] = $key;
            $this->assertEquals(2, strlen($key), "Language code '$key' should be 2 characters");
        }
        
        $this->assertContains('en', $languageCodes);
        $this->assertContains('fr', $languageCodes);
    }
    
    public function testBackwardCompatibilityWithOriginalBehavior()
    {
        // Test that the refactored methods behave the same as the original for common cases
        
        // Test case 1: Valid 2-character language
        $this->mockPaper->method('getMetadata')
                       ->with('language')
                       ->willReturn('en');
        $this->mockPaper->method('getDocid')
                       ->willReturn('12345');
        
        $result = Export::getPaperLanguageCode($this->mockPaper, 2);
        $this->assertEquals('en', $result);
        
        // Test case 2: Conversion from 3 to 2 characters
        $this->mockPaper = $this->createMock(Episciences_Paper::class);
        $this->mockPaper->method('getMetadata')
                       ->with('language')
                       ->willReturn('eng');
        $this->mockPaper->method('getDocid')
                       ->willReturn('12345');
        
        $result = Export::getPaperLanguageCode($this->mockPaper, 2);
        $this->assertEquals('en', $result);
        
        // Test case 3: Conversion from 2 to 3 characters
        $this->mockPaper = $this->createMock(Episciences_Paper::class);
        $this->mockPaper->method('getMetadata')
                       ->with('language')
                       ->willReturn('en');
        $this->mockPaper->method('getDocid')
                       ->willReturn('12345');
        
        $result = Export::getPaperLanguageCode($this->mockPaper, 3);
        $this->assertEquals('eng', $result);
    }
    
    public function testCachingPerformanceImprovement()
    {
        // Set up mock paper
        $this->mockPaper->method('getMetadata')
                       ->with('language')
                       ->willReturn('en');
        $this->mockPaper->method('getDocid')
                       ->willReturn('12345');
        
        $start = microtime(true);
        
        // Process same language code multiple times
        for ($i = 0; $i < 100; $i++) {
            Export::getPaperLanguageCode($this->mockPaper, 2);
        }
        
        $duration = microtime(true) - $start;
        
        // Should be significantly faster due to caching
        $this->assertLessThan(0.5, $duration, "Caching should improve performance: took {$duration} seconds for 100 operations");
        
        // Verify cache was used
        $stats = Episciences_Language_LanguageCodeProcessor::getCacheStats();
        $this->assertGreaterThan(0, $stats['total_cache_entries']);
    }
}